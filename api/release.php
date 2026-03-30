<?php
/**
 * api/release.php — Devuelve una conversación al control del bot.
 * El webhook verá mode='bot' en bot_status.php y retomará automáticamente.
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
         SET status = ?, agent_id = NULL, assigned_at = NULL, updated_at = ?
         WHERE id = ?'
    )->execute(['bot', $now, $convId]);

    // Enviar mensaje de despedida al usuario por WhatsApp
    $farewell =
        "👋 *¡Gracias por contactarnos!*\n\n" .
        "Ha sido un placer atenderte. Recuerda que puedes escribirnos cuando lo necesites.\n\n" .
        "¡Hasta pronto! 😊\n\n" .
        "_Escribe *Menú* si deseas volver a nuestro asistente virtual._";

    apiSend($conv['phone'], $farewell);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log('[api/release] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno.']);
}
