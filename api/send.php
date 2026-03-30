<?php
/**
 * api/send.php — Envía un mensaje (texto o archivo) a través de la API WhatsApp.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'JSON inválido.']);
    exit;
}

$convId     = (int)($data['conversationId'] ?? 0);
$type       = trim($data['type']       ?? 'text');
$message    = trim($data['message']    ?? '');
$fileBase64 = trim($data['fileBase64'] ?? '');
$fileName   = trim($data['fileName']   ?? '');
$mimeType   = trim($data['mimeType']   ?? '');
$caption    = trim($data['caption']    ?? '');

if ($convId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'conversationId requerido.']);
    exit;
}

// Whitelists
const ALLOWED_IMAGE_TYPES = ['image/jpeg','image/png','image/gif','image/webp'];
const ALLOWED_DOC_TYPES   = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain',
    'application/zip',
];
const MAX_IMAGE_SIZE = 5 * 1024 * 1024;  // 5 MB
const MAX_DOC_SIZE   = 10 * 1024 * 1024; // 10 MB

try {
    $pdo = DB::get();

    // Obtener conversación
    $stmt = $pdo->prepare('SELECT * FROM conversations WHERE id = ? LIMIT 1');
    $stmt->execute([$convId]);
    $conv = $stmt->fetch();

    if (!$conv) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Conversación no encontrada.']);
        exit;
    }

    // Verificar acceso
    if (!canAccessConversation($conv)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Sin acceso a esta conversación.']);
        exit;
    }

    // No permitir envío en conversaciones resueltas o bot
    if (in_array($conv['status'], ['resolved', 'bot'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Conversación no activa. Reactívala primero.']);
        exit;
    }

    // Para conversaciones en atención, solo el agente asignado o supervisor puede enviar
    if ($conv['status'] === 'attending' &&
        $currentAgent['role'] !== 'supervisor' &&
        (int)$conv['agent_id'] !== $currentAgent['id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Esta conversación está asignada a otro agente.']);
        exit;
    }

    $phone  = $conv['phone'];
    $now    = date('Y-m-d H:i:s');
    $savedFilePath = null;

    // ── Tipo texto ────────────────────────────────────────────────
    if ($type === 'text') {
        if ($message === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'El mensaje no puede estar vacío.']);
            exit;
        }

        $result = apiSend($phone, $message);

        $msgStatus  = $result['success'] ? 'sent' : 'failed';
        $errDetail  = $result['success'] ? null : $result['error'];
        $waId       = $result['success'] ? $result['messageId'] : null;

        $insMsgStmt = $pdo->prepare(
            'INSERT INTO messages
               (conversation_id, direction, type, content, agent_id,
                wa_message_id, status, error_detail, created_at)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $insMsgStmt->execute([
            $convId, 'out', 'text', $message,
            $currentAgent['id'], $waId, $msgStatus, $errDetail, $now,
        ]);
        $msgId = (int)$pdo->lastInsertId();

        if ($result['success'] && $conv['status'] === 'pending') {
            $pdo->prepare(
                'UPDATE conversations SET status=?, agent_id=?, assigned_at=? WHERE id=?'
            )->execute(['attending', $currentAgent['id'], $now, $convId]);
        }

        $pdo->prepare('UPDATE conversations SET last_message_at=?, updated_at=? WHERE id=?')
            ->execute([$now, $now, $convId]);

        // Leer mensaje insertado para devolver
        $msgStmt = $pdo->prepare('SELECT * FROM messages WHERE id = ? LIMIT 1');
        $msgStmt->execute([$msgId]);
        $savedMsg = $msgStmt->fetch();

        echo json_encode([
            'success' => $result['success'],
            'message' => $savedMsg,
            'error'   => $result['success'] ? null : $result['error'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // ── Tipo imagen o documento ───────────────────────────────────
    if (in_array($type, ['image', 'document'])) {
        if ($fileBase64 === '' || $fileName === '' || $mimeType === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Faltan datos del archivo (fileBase64, fileName, mimeType).']);
            exit;
        }

        // Rechazar audio y video explícitamente
        if (str_starts_with($mimeType, 'audio/') || str_starts_with($mimeType, 'video/')) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Tipo de archivo no permitido (audio/video).']);
            exit;
        }

        // Validar contra whitelist
        $isImage = in_array($mimeType, ALLOWED_IMAGE_TYPES);
        $isDoc   = in_array($mimeType, ALLOWED_DOC_TYPES);

        if (!$isImage && !$isDoc) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Tipo MIME no permitido: ' . $mimeType]);
            exit;
        }

        // Decodificar base64
        // Remover prefijo data:... si existe
        if (str_contains($fileBase64, ',')) {
            $fileBase64 = substr($fileBase64, strpos($fileBase64, ',') + 1);
        }
        $fileData = base64_decode($fileBase64, true);
        if ($fileData === false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Base64 inválido.']);
            exit;
        }

        $fileSize = strlen($fileData);
        $maxSize  = $isImage ? MAX_IMAGE_SIZE : MAX_DOC_SIZE;

        if ($fileSize > $maxSize) {
            $maxMB = $maxSize / 1024 / 1024;
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Archivo supera el límite de {$maxMB}MB."]);
            exit;
        }

        // Validar mimetype real con finfo
        $finfo        = new finfo(FILEINFO_MIME_TYPE);
        $realMimeType = $finfo->buffer($fileData);

        // Verificar que el mime real esté en la whitelist
        $allAllowed = array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_DOC_TYPES);
        if (!in_array($realMimeType, $allAllowed)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'El archivo real no coincide con el tipo declarado.']);
            exit;
        }

        // Crear directorio de uploads para esta conversación
        $uploadDir = UPLOAD_DIR . $convId . '/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'No se pudo crear directorio de uploads.']);
                exit;
            }
        }

        // Nombre único
        $ext       = pathinfo($fileName, PATHINFO_EXTENSION);
        $safeName  = preg_replace('/[^a-zA-Z0-9_.-]/', '_', basename($fileName));
        $savedName = uniqid('f_', true) . '_' . $safeName;
        $filePath  = $uploadDir . $savedName;

        if (file_put_contents($filePath, $fileData) === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'No se pudo guardar el archivo.']);
            exit;
        }

        $savedFilePath = $filePath;
        $fileUrl       = UPLOAD_URL . $convId . '/' . $savedName;

        // Determinar tipo de mensaje final por mimetype real
        $msgType = str_starts_with($realMimeType, 'image/') ? 'image' : 'document';

        // Llamar a la API
        $sendText   = $caption !== '' ? $caption : $fileName;
        $sendCaption = $caption !== '' ? $caption : $fileName;

        $result = apiSend($phone, $sendText, $fileUrl, $fileName, $sendCaption);

        $msgStatus = $result['success'] ? 'sent' : 'failed';
        $errDetail = $result['success'] ? null : $result['error'];
        $waId      = $result['success'] ? $result['messageId'] : null;

        // Si falló, eliminar archivo subido
        if (!$result['success'] && $savedFilePath && file_exists($savedFilePath)) {
            @unlink($savedFilePath);
            $fileUrl   = null;
            $savedName = null;
        }

        $content = $caption !== '' ? $caption : $fileName;

        $insMsgStmt = $pdo->prepare(
            'INSERT INTO messages
               (conversation_id, direction, type, content, file_url, file_name,
                file_mime, file_size, caption, agent_id, wa_message_id,
                status, error_detail, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $insMsgStmt->execute([
            $convId, 'out', $msgType, $content,
            $result['success'] ? $fileUrl   : null,
            $result['success'] ? $fileName  : null,
            $result['success'] ? $realMimeType : null,
            $result['success'] ? $fileSize  : null,
            $caption ?: null,
            $currentAgent['id'],
            $waId,
            $msgStatus,
            $errDetail,
            $now,
        ]);
        $msgId = (int)$pdo->lastInsertId();

        if ($result['success'] && $conv['status'] === 'pending') {
            $pdo->prepare(
                'UPDATE conversations SET status=?, agent_id=?, assigned_at=? WHERE id=?'
            )->execute(['attending', $currentAgent['id'], $now, $convId]);
        }

        $pdo->prepare('UPDATE conversations SET last_message_at=?, updated_at=? WHERE id=?')
            ->execute([$now, $now, $convId]);

        $msgStmt = $pdo->prepare('SELECT * FROM messages WHERE id = ? LIMIT 1');
        $msgStmt->execute([$msgId]);
        $savedMsg = $msgStmt->fetch();

        echo json_encode([
            'success' => $result['success'],
            'message' => $savedMsg,
            'error'   => $result['success'] ? null : $result['error'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Tipo no soportado: ' . $type]);

} catch (PDOException $e) {
    error_log('[api/send] PDO: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno.']);
}
