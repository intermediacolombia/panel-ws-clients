<?php
/**
 * api/logout.php — Invalida el token actual.
 * POST {} con header Authorization: Bearer <token>
 */

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

try {
    $pdo = DB::get();
    $pdo->prepare('DELETE FROM agent_sessions WHERE token = ?')
        ->execute([$currentAgent['token']]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log('[api/logout] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno.']);
}
