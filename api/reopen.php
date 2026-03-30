<?php
/**
 * api/reopen.php — Reabre una conversación resuelta o en bot, asignándola al agente actual.
 * POST { conversationId }
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$raw    = file_get_contents('php://input');
$data   = json_decode($raw, true);
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

    if (!in_array($conv['status'], ['resolved', 'bot'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Solo se pueden reabrir conversaciones resueltas o en bot.']);
        exit;
    }

    $now = date('Y-m-d H:i:s');

    $pdo->prepare(
        'UPDATE conversations
         SET status = ?, agent_id = ?, assigned_at = ?, resolved_at = NULL, updated_at = ?
         WHERE id = ?'
    )->execute(['attending', $currentAgent['id'], $now, $now, $convId]);

    echo json_encode(['success' => true, 'agentId' => $currentAgent['id']]);

} catch (PDOException $e) {
    error_log('[api/reopen] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno.']);
}
