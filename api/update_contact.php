<?php
/**
 * api/update_contact.php — Actualiza el nombre del contacto de una conversación.
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

$convId      = (int)($data['conversationId'] ?? 0);
$contactName = trim($data['contactName'] ?? '');

if ($convId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'conversationId requerido.']);
    exit;
}

if ($contactName === '' || mb_strlen($contactName) > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Nombre inválido (1-100 caracteres).']);
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

    $pdo->prepare(
        'UPDATE conversations SET contact_name = ?, updated_at = NOW() WHERE id = ?'
    )->execute([$contactName, $convId]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log('[api/update_contact] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno.']);
}
