<?php
/**
 * incoming.php — Receptor de mensajes entrantes desde el webhook.
 * NO requiere sesión de agente. Autenticado por X-Agent-Secret header.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json; charset=utf-8');

// Validar secreto interno
$secret = $_SERVER['HTTP_X_AGENT_SECRET'] ?? '';
if (!hash_equals(AGENT_SECRET, $secret)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden.']);
    exit;
}

// Solo POST
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

// Soporta formato nuevo (from/pushName/client_id/mediaBase64) y legado (phone/name/clientId/fileUrl)
$phone       = trim($data['from']        ?? $data['phone']    ?? '');
$name        = trim($data['pushName']    ?? $data['name']     ?? '');
$message     = trim($data['message']     ?? '');
$messageType = trim($data['messageType'] ?? 'text');
$clientId    = trim($data['client_id']   ?? $data['clientId'] ?? 'default');
$area        = trim($data['area']        ?? '');
$messageId   = trim($data['messageId']   ?? '');
$mediaBase64 = $data['mediaBase64']      ?? null;
$mimetypeRaw = trim($data['mimetype']    ?? $data['mime']     ?? '');
$filename    = trim($data['filename']    ?? '');
// legado: fileUrl directo (sin base64)
$fileUrlLegacy = trim($data['fileUrl']   ?? '');
$caption       = trim($data['caption']   ?? '');

if ($phone === '' || $clientId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Faltan campos requeridos: phone, clientId.']);
    exit;
}

try {
    $pdo = DB::get();

    $convKey = $clientId . '_' . $phone;

    // 1. Buscar conversación existente por conv_key
    $stmt = $pdo->prepare('SELECT * FROM conversations WHERE conv_key = ? LIMIT 1');
    $stmt->execute([$convKey]);
    $conv = $stmt->fetch();

    // 1b. Fallback: buscar por teléfono si no encontró por conv_key.
    // Cubre el caso de conversaciones iniciadas desde el panel con un client_id diferente.
    if (!$conv) {
        $stmt2 = $pdo->prepare(
            'SELECT * FROM conversations WHERE phone = ? ORDER BY updated_at DESC LIMIT 1'
        );
        $stmt2->execute([$phone]);
        $convAlt = $stmt2->fetch();
        // Solo usar si está activa (attending/pending) — no reutilizar resueltas de otro client_id
        if ($convAlt && in_array($convAlt['status'], ['attending', 'pending'])) {
            $conv    = $convAlt;
            $convKey = $convAlt['conv_key']; // usar el conv_key original
        }
    }

    $now = date('Y-m-d H:i:s');

    if (!$conv) {
        // 2a. Crear nueva conversación
        $deptId = getOrCreateDepartment($area);

        $ins = $pdo->prepare(
            'INSERT INTO conversations
               (conv_key, phone, contact_name, client_id, department_id, area_label,
                status, unread_count, first_contact_at, last_message_at, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,1,?,?,?,?)'
        );
        $ins->execute([
            $convKey,
            $phone,
            $name,
            $clientId,
            $deptId,
            $area,
            'pending',
            $now,
            $now,
            $now,
            $now,
        ]);
        $convId    = (int)$pdo->lastInsertId();
        $deptIdFor = $deptId;

    } else {
        // 2b. Conversación existente
        $convId    = (int)$conv['id'];
        $deptIdFor = $conv['department_id'];

        // Si estaba resuelta → reactivar siempre.
        // Si estaba en bot → solo reactivar si se especifica área (usuario solicitó asesor desde el bot).
        // Sin área = mensaje de tránsito bot→bot; no cambiar estado ni alertar agentes.
        if ($conv['status'] === 'resolved' || ($conv['status'] === 'bot' && $area !== '')) {
            $upd = $pdo->prepare(
                'UPDATE conversations
                 SET status = ?, agent_id = NULL, assigned_at = NULL,
                     unread_count = 0, area_label = ?, updated_at = ?
                 WHERE id = ?'
            );
            $upd->execute(['pending', $area ?: $conv['area_label'], $now, $convId]);
        }

        // Actualizar nombre si estaba vacío
        if ($conv['contact_name'] === '' && $name !== '') {
            $pdo->prepare('UPDATE conversations SET contact_name = ? WHERE id = ?')
                ->execute([$name, $convId]);
        }
    }

    // Bandera: conv en modo bot sin solicitud de asesor → silenciar contadores y notificaciones
    $isBotSilent = isset($conv) && $conv['status'] === 'bot' && $area === '';

    // 3. Guardar media base64 en disco (si aplica)
    $fileUrlVal  = null;
    $fileMimeVal = $mimetypeRaw !== '' ? $mimetypeRaw : null;
    $fileNameVal = $filename    !== '' ? $filename    : null;

    if ($mediaBase64 !== null && $mediaBase64 !== '' && $messageType !== 'text') {
        $fileData = base64_decode($mediaBase64, true);
        if ($fileData !== false) {
            $uploadDir = UPLOAD_DIR . $convId . '/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext       = _mimeToExt($mimetypeRaw);
            $savedName = uniqid('wa_', true) . '.' . $ext;
            if (file_put_contents($uploadDir . $savedName, $fileData) !== false) {
                $fileUrlVal = UPLOAD_URL . $convId . '/' . $savedName;
            }
        }
    } elseif ($fileUrlLegacy !== '') {
        $fileUrlVal = $fileUrlLegacy;
    }

    // 4. Registrar mensaje entrante
    $msgType = match($messageType) {
        'image'    => 'image',
        'audio'    => 'audio',
        'document' => 'document',
        default    => 'text',
    };

    // Para mensajes de media, el campo 'message' puede traer el caption
    if ($msgType === 'text') {
        $msgContent = $message !== '' ? $message : '[texto vacío]';
        $captionVal = null;
    } else {
        $msgContent = '[' . $messageType . ']';
        $captionVal = $message !== '' ? $message : ($caption !== '' ? $caption : null);
    }

    $waMessageId = $messageId !== '' ? $messageId : null;

    $insMsq = $pdo->prepare(
        'INSERT INTO messages
           (conversation_id, direction, type, content, file_url, file_name, file_mime,
            caption, wa_message_id, status, created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
    );
    $insMsq->execute([
        $convId, 'in', $msgType, $msgContent,
        $fileUrlVal, $fileNameVal, $fileMimeVal,
        $captionVal, $waMessageId, 'sent', $now,
    ]);

    // 5. Actualizar contadores de la conversación
    // En modo bot-silencioso no incrementar unread (agentes no deben ver el badge)
    if ($isBotSilent) {
        $pdo->prepare(
            'UPDATE conversations SET last_message_at = ?, updated_at = ? WHERE id = ?'
        )->execute([$now, $now, $convId]);
    } else {
        $pdo->prepare(
            'UPDATE conversations
             SET unread_count  = unread_count + 1,
                 last_message_at = ?,
                 updated_at      = ?
             WHERE id = ?'
        )->execute([$now, $now, $convId]);
    }

    // 6. Crear notificaciones para agentes del departamento
    if ($deptIdFor !== null && !$isBotSilent) {
        $agStmt = $pdo->prepare(
            'SELECT a.id, a.fcm_token FROM agents a
             JOIN agent_departments ad ON ad.agent_id = a.id
             WHERE ad.department_id = ? AND a.status = ?'
        );
        $agStmt->execute([$deptIdFor, 'active']);
        $agentRows = $agStmt->fetchAll(PDO::FETCH_ASSOC);
        $agentIds  = array_column($agentRows, 'id');

        $notifMsg = 'Nuevo mensaje de ' . ($name ?: $phone);
        if ($area !== '') {
            $notifMsg .= ' (' . $area . ')';
        }

        $notifStmt = $pdo->prepare(
            'INSERT INTO notifications
               (agent_id, conversation_id, type, message, created_at)
             VALUES (?,?,?,?,?)'
        );

        foreach ($agentRows as $ag) {
            $notifStmt->execute([
                $ag['id'],
                $convId,
                'new_message',
                $notifMsg,
                $now,
            ]);

            // Enviar push FCM si el agente tiene token registrado
            if (!empty($ag['fcm_token']) && defined('FCM_SERVICE_ACCOUNT')) {
                sendFcmPush(
                    token:   $ag['fcm_token'],
                    title:   $name ?: $phone,
                    body:    $msgContent,
                    convId:  $convId
                );
            }
        }
    }

    echo json_encode(['success' => true, 'conversationId' => $convId]);

} catch (PDOException $e) {
    error_log('[incoming] PDO: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno.']);
}

// ── Push FCM HTTP v1 ──────────────────────────────────────────────────────────

function sendFcmPush(string $token, string $title, string $body, int $convId): void
{
    $saPath = FCM_SERVICE_ACCOUNT;
    if (!file_exists($saPath)) {
        error_log('[FCM] No existe ' . $saPath);
        return;
    }

    $sa          = json_decode(file_get_contents($saPath), true);
    $accessToken = _fcmAccessToken($sa);
    if (!$accessToken) return;

    $projectId = $sa['project_id'];
    $payload   = json_encode([
        'message' => [
            'token'        => $token,
            'notification' => ['title' => $title, 'body' => $body],
            'data'         => [
                'conv_id' => (string)$convId,
                'contact' => $title,
                'message' => $body,
            ],
            'android' => [
                'priority'     => 'high',
                'notification' => ['sound' => 'default'],
            ],
        ],
    ]);

    $ch = curl_init("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $payload,
    ]);

    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log('[FCM] cURL: ' . $err);
    } elseif (str_contains($res, '"error"')) {
        error_log('[FCM] Respuesta: ' . $res);
    }
}

/**
 * Genera un access token OAuth2 firmando un JWT con la clave privada de la service account.
 * Cachea el token en /tmp para no generar un JWT en cada mensaje.
 */
function _fcmAccessToken(array $sa): ?string
{
    $cacheFile = sys_get_temp_dir() . '/fcm_token_' . md5($sa['client_email']) . '.json';

    if (file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if (!empty($cached['access_token']) && $cached['expires_at'] > time() + 60) {
            return $cached['access_token'];
        }
    }

    $now = time();
    $header  = _b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claims  = _b64url(json_encode([
        'iss'   => $sa['client_email'],
        'sub'   => $sa['client_email'],
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
    ]));

    $signing = "$header.$claims";
    $key     = openssl_pkey_get_private($sa['private_key']);
    if (!$key) {
        error_log('[FCM] Clave privada inválida en service account.');
        return null;
    }

    $sig = '';
    openssl_sign($signing, $sig, $key, 'sha256WithRSAEncryption');
    $jwt = $signing . '.' . _b64url($sig);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
    ]);
    $res = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($res, true);
    if (empty($data['access_token'])) {
        error_log('[FCM] No se obtuvo access_token: ' . $res);
        return null;
    }

    file_put_contents($cacheFile, json_encode([
        'access_token' => $data['access_token'],
        'expires_at'   => $now + ($data['expires_in'] ?? 3600),
    ]));

    return $data['access_token'];
}

function _b64url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Convierte un mimetype a extensión de archivo.
 * Maneja el caso "audio/ogg; codecs=opus" — toma solo la parte antes de ';'.
 */
function _mimeToExt(string $mime): string
{
    $mime = strtolower(trim(explode(';', $mime)[0]));
    return match($mime) {
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/gif'       => 'gif',
        'image/webp'      => 'webp',
        'audio/ogg'       => 'ogg',
        'audio/mpeg'      => 'mp3',
        'audio/mp4'       => 'm4a',
        'audio/aac'       => 'aac',
        'audio/webm'      => 'webm',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/plain'      => 'txt',
        'application/zip' => 'zip',
        default           => 'bin',
    };
}
