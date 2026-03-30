<?php
/**
 * api/bot_status.php — Consultado por el webhook para saber si el agente
 * ya devolvió el control al bot para un usuario.
 * Autenticado por X-Agent-Secret header.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

// Validar secreto
$secret = $_SERVER['HTTP_X_AGENT_SECRET'] ?? '';
if (!hash_equals(AGENT_SECRET, $secret)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$phone    = trim($_GET['phone']    ?? '');
$clientId = trim($_GET['clientId'] ?? '');

if ($phone === '' || $clientId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Faltan parámetros: phone, clientId.']);
    exit;
}

$convKey = $clientId . '_' . $phone;

try {
    $pdo  = DB::get();
    $stmt = $pdo->prepare('SELECT status FROM conversations WHERE conv_key = ? LIMIT 1');
    $stmt->execute([$convKey]);
    $row = $stmt->fetch();

    if (!$row) {
        echo json_encode(['mode' => 'none']);
        exit;
    }

    $status = $row['status'];

    if ($status === 'bot') {
        echo json_encode(['mode' => 'bot']);
    } else {
        // pending, attending, resolved → el panel tiene el control
        echo json_encode(['mode' => 'asesor']);
    }

} catch (PDOException $e) {
    error_log('[bot_status] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno.']);
}
