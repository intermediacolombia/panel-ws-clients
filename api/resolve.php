<?php
/**
 * api/resolve.php — Marca una conversación como resuelta.
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

$convId = (int)($data['conversationId'] ?? 0);

if ($convId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'conversationId requerido.']);
    exit;
}

try {
    $pdo = DB::get();

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

    $now = date('Y-m-d H:i:s');

    $pdo->prepare(
        'UPDATE conversations
         SET status = ?, resolved_at = ?, resolved_by = ?, updated_at = ?
         WHERE id = ?'
    )->execute(['resolved', $now, $currentAgent['id'], $now, $convId]);

    // Notificar si hay agente asignado diferente al que resolvió
    if ($conv['agent_id'] && (int)$conv['agent_id'] !== $currentAgent['id']) {
        $pdo->prepare(
            'INSERT INTO notifications (agent_id, conversation_id, type, message, created_at)
             VALUES (?,?,?,?,?)'
        )->execute([
            $conv['agent_id'],
            $convId,
            'resolved',
            $currentAgent['name'] . ' marcó la conversación de ' .
                ($conv['contact_name'] ?: $conv['phone']) . ' como resuelta.',
            $now,
        ]);
    }

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log('[api/resolve] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno.']);
}
