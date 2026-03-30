<?php
/**
 * api/upload.php — Subida de archivos via multipart/form-data.
 * Devuelve la URL pública para usar en api/send.php.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$convId = (int)($_POST['conversationId'] ?? 0);

if ($convId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'conversationId requerido.']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['file']['error'] ?? -1;
    $errMsg  = match($errCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Archivo demasiado grande.',
        UPLOAD_ERR_NO_FILE => 'No se recibió ningún archivo.',
        default            => 'Error al subir archivo (código ' . $errCode . ').',
    };
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $errMsg]);
    exit;
}

const UP_ALLOWED_IMAGE_TYPES = ['image/jpeg','image/png','image/gif','image/webp'];
const UP_ALLOWED_DOC_TYPES   = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain',
    'application/zip',
];
const UP_MAX_IMAGE = 5 * 1024 * 1024;
const UP_MAX_DOC   = 10 * 1024 * 1024;

try {
    $pdo = DB::get();

    // Verificar que la conversación existe y el agente tiene acceso
    $stmt = $pdo->prepare('SELECT * FROM conversations WHERE id = ? LIMIT 1');
    $stmt->execute([$convId]);
    $conv = $stmt->fetch();

    if (!$conv) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Conversación no encontrada.']);
        exit;
    }

    if (!canAccessConversation($conv)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Sin acceso.']);
        exit;
    }

    $tmpPath  = $_FILES['file']['tmp_name'];
    $origName = $_FILES['file']['name'];
    $fileSize = $_FILES['file']['size'];

    // Detectar MIME real
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpPath);

    // Rechazar audio y video
    if (str_starts_with($mimeType, 'audio/') || str_starts_with($mimeType, 'video/')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Tipo de archivo no permitido (audio/video).']);
        exit;
    }

    $isImage = in_array($mimeType, UP_ALLOWED_IMAGE_TYPES);
    $isDoc   = in_array($mimeType, UP_ALLOWED_DOC_TYPES);

    if (!$isImage && !$isDoc) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Tipo MIME no permitido: ' . $mimeType]);
        exit;
    }

    $maxSize = $isImage ? UP_MAX_IMAGE : UP_MAX_DOC;
    if ($fileSize > $maxSize) {
        $maxMB = $maxSize / 1024 / 1024;
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Archivo supera el límite de {$maxMB}MB."]);
        exit;
    }

    // Crear directorio
    $uploadDir = UPLOAD_DIR . $convId . '/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'No se pudo crear directorio.']);
            exit;
        }
    }

    $safeName  = preg_replace('/[^a-zA-Z0-9_.-]/', '_', basename($origName));
    $savedName = uniqid('f_', true) . '_' . $safeName;
    $destPath  = $uploadDir . $savedName;

    if (!move_uploaded_file($tmpPath, $destPath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error al mover archivo.']);
        exit;
    }

    $fileUrl = UPLOAD_URL . $convId . '/' . $savedName;

    echo json_encode([
        'success'  => true,
        'url'      => $fileUrl,
        'filename' => $origName,
        'mimetype' => $mimeType,
        'size'     => $fileSize,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    error_log('[api/upload] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno.']);
}
