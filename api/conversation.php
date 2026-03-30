<?php
/**
 * api/conversation.php — Detalle de una conversación + mensajes.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$convId = (int)($_GET['id'] ?? 0);
if ($convId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de conversación requerido.']);
    exit;
}

try {
    $pdo = DB::get();

    // Obtener conversación con joins
    $stmt = $pdo->prepare(
        'SELECT c.*,
                a.name  AS agent_name,
                a.username AS agent_username,
                d.name  AS dept_name,
                d.color AS dept_color,
                d.icon  AS dept_icon
         FROM conversations c
         LEFT JOIN agents      a ON a.id = c.agent_id
         LEFT JOIN departments d ON d.id = c.department_id
         WHERE c.id = ?
         LIMIT 1'
    );
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

    // Marcar como leída
    $pdo->prepare('UPDATE conversations SET unread_count = 0 WHERE id = ?')
        ->execute([$convId]);

    $conv['unread_count']  = 0;
    $conv['id']            = (int)$conv['id'];
    $conv['department_id'] = $conv['department_id'] !== null ? (int)$conv['department_id'] : null;
    $conv['agent_id']      = $conv['agent_id'] !== null ? (int)$conv['agent_id'] : null;

    // Obtener mensajes ordenados cronológicamente
    $msgStmt = $pdo->prepare(
        'SELECT m.*, a.name AS agent_name
         FROM messages m
         LEFT JOIN agents a ON a.id = m.agent_id
         WHERE m.conversation_id = ?
         ORDER BY m.created_at ASC'
    );
    $msgStmt->execute([$convId]);
    $messages = $msgStmt->fetchAll();

    foreach ($messages as &$msg) {
        $msg['id']              = (int)$msg['id'];
        $msg['conversation_id'] = (int)$msg['conversation_id'];
        $msg['agent_id']        = $msg['agent_id'] !== null ? (int)$msg['agent_id'] : null;
        $msg['file_size']       = $msg['file_size'] !== null ? (int)$msg['file_size'] : null;
    }
    unset($msg);

    // Obtener últimas 5 conversaciones previas del mismo número (excluyendo esta)
    $prevStmt = $pdo->prepare(
        'SELECT id, area_label, status, first_contact_at, resolved_at
         FROM conversations
         WHERE phone = ? AND id != ?
         ORDER BY first_contact_at DESC
         LIMIT 5'
    );
    $prevStmt->execute([$conv['phone'], $convId]);
    $previousConvs = $prevStmt->fetchAll();

    echo json_encode([
        'success'       => true,
        'conversation'  => $conv,
        'messages'      => $messages,
        'previousConvs' => $previousConvs,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    error_log('[api/conversation] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno.']);
}
