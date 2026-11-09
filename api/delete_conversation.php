<?php
/**
 * api/delete_conversation.php — Elimina una conversación completa.
 * Solo supervisores. Borra mensajes, conversación y uploads/{id}/.
 * POST { conversationId }
 */

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if ($currentAgent['role'] !== 'supervisor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Solo supervisores pueden eliminar conversaciones.']);
    exit;
}

$data   = json_decode(file_get_contents('php://input'), true) ?: [];
$convId = (int)($data['conversationId'] ?? 0);

if ($convId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'conversationId requerido.']);
    exit;
}

try {
    $pdo = DB::get();

    $exists = $pdo->prepare('SELECT id FROM conversations WHERE id = ?');
    $exists->execute([$convId]);
    if (!$exists->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Conversación no encontrada.']);
        exit;
    }

    // messages y notifications caen por ON DELETE CASCADE
    $pdo->prepare('DELETE FROM conversations WHERE id = ?')->execute([$convId]);

    // Borrar carpeta de uploads de la conv
    $dir = UPLOAD_DIR . $convId . '/';
    if (is_dir($dir)) {
        foreach (glob($dir . '*') as $f) { if (is_file($f)) @unlink($f); }
        @rmdir($dir);
    }

    echo json_encode(['success' => true]);

} catch (\Throwable $e) {
    error_log('[api/delete_conversation] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
}
