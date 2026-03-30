<?php
/**
 * api/start_conversation.php — Inicia una conversación saliente desde el panel.
 * POST { phone, message, name? }
 * Crea la conversación en BD, envía el primer mensaje por WhatsApp y
 * la asigna directamente al agente que la inicia.
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

$phone   = preg_replace('/[^0-9]/', '', trim($data['phone']   ?? ''));
$message = trim($data['message'] ?? '');
$name    = trim($data['name']    ?? '');

if ($phone === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'El número de teléfono es requerido.']);
    exit;
}

if (strlen($phone) < 7 || strlen($phone) > 15) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Número de teléfono inválido (incluye el código de país, ej: 573001234567).']);
    exit;
}

if ($message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'El mensaje no puede estar vacío.']);
    exit;
}

try {
    $pdo = DB::get();

    $clientId = defined('WA_CLIENT_ID') ? WA_CLIENT_ID : 'default';
    $convKey  = $clientId . '_' . $phone;
    $now      = date('Y-m-d H:i:s');

    // Buscar si ya existe una conversación activa con ese número
    $stmt = $pdo->prepare('SELECT * FROM conversations WHERE conv_key = ? LIMIT 1');
    $stmt->execute([$convKey]);
    $existing = $stmt->fetch();

    if ($existing && in_array($existing['status'], ['pending', 'attending'])) {
        http_response_code(409);
        echo json_encode([
            'success'        => false,
            'error'          => 'Ya existe una conversación activa con ese número.',
            'conversationId' => (int)$existing['id'],
        ]);
        exit;
    }

    // Enviar el mensaje por WhatsApp primero
    $result = apiSend($phone, $message);

    if (!$result['success']) {
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'No se pudo enviar el mensaje: ' . ($result['error'] ?? 'Error desconocido')]);
        exit;
    }

    $waId = $result['messageId'];

    // Crear o reactivar conversación
    if ($existing) {
        // Reactivar conversación resuelta/bot
        $pdo->prepare(
            'UPDATE conversations
             SET status = ?, agent_id = ?, assigned_at = ?, resolved_at = NULL,
                 last_message_at = ?, updated_at = ?
             WHERE id = ?'
        )->execute(['attending', $currentAgent['id'], $now, $now, $now, $existing['id']]);
        $convId = (int)$existing['id'];
    } else {
        // Crear nueva conversación
        $pdo->prepare(
            'INSERT INTO conversations
               (conv_key, phone, contact_name, client_id, status, agent_id,
                assigned_at, first_contact_at, last_message_at, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $convKey, $phone, $name ?: $phone, $clientId,
            'attending', $currentAgent['id'], $now, $now, $now, $now, $now,
        ]);
        $convId = (int)$pdo->lastInsertId();
    }

    // Registrar el mensaje saliente
    $pdo->prepare(
        'INSERT INTO messages
           (conversation_id, direction, type, content, agent_id, wa_message_id, status, created_at)
         VALUES (?,?,?,?,?,?,?,?)'
    )->execute([
        $convId, 'out', 'text', $message,
        $currentAgent['id'], $waId, 'sent', $now,
    ]);

    // No necesitamos insertar en bot_estados — el webhook detecta automáticamente
    // que la conversación está 'attending' en el panel y la silencia.

    // Leer conversación completa para que la app pueda abrir el chat directamente
    $fetchStmt = $pdo->prepare(
        'SELECT c.*,
                a.name  AS agent_name,
                d.name  AS dept_name,
                d.color AS dept_color
         FROM conversations c
         LEFT JOIN agents      a ON a.id = c.agent_id
         LEFT JOIN departments d ON d.id = c.department_id
         WHERE c.id = ?
         LIMIT 1'
    );
    $fetchStmt->execute([$convId]);
    $convRow = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    if ($convRow) {
        $convRow['id']            = (int)$convRow['id'];
        $convRow['agent_id']      = $convRow['agent_id']      !== null ? (int)$convRow['agent_id']      : null;
        $convRow['department_id'] = $convRow['department_id'] !== null ? (int)$convRow['department_id'] : null;
        $convRow['unread_count']  = (int)($convRow['unread_count'] ?? 0);
        $convRow['time_formatted'] = formatTime($convRow['updated_at'] ?? $convRow['first_contact_at'] ?? '');
    }

    echo json_encode([
        'success'        => true,
        'conversationId' => $convId,
        'conversation'   => $convRow ?: null,
    ]);

} catch (PDOException $e) {
    error_log('[api/start_conversation] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno.']);
}
