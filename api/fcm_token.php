<?php
/**
 * api/fcm_token.php — Registra el token FCM del dispositivo del agente.
 * POST { fcm_token: string }
 * Requiere Bearer token (sesión de agente).
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$raw      = file_get_contents('php://input');
$data     = json_decode($raw, true) ?? [];
$fcmToken = trim($data['fcm_token'] ?? '');

if ($fcmToken === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'fcm_token requerido.']);
    exit;
}

try {
    $pdo = DB::get();
    $pdo->prepare('UPDATE agents SET fcm_token = ? WHERE id = ?')
        ->execute([$fcmToken, $currentAgent['id']]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log('[api/fcm_token] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno.']);
}
