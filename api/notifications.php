<?php
/**
 * api/notifications.php — Notificaciones del agente autenticado.
 * GET  → listar no leídas
 * POST { markAll: true } → marcar todas como leídas
 * POST { id: N }        → marcar una como leída
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = DB::get();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare(
            'SELECT n.*, c.phone, c.contact_name, c.area_label
             FROM notifications n
             JOIN conversations c ON c.id = n.conversation_id
             WHERE n.agent_id = ?
             ORDER BY n.created_at DESC
             LIMIT 50'
        );
        $stmt->execute([$currentAgent['id']]);
        $notifications = $stmt->fetchAll();

        $unreadStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM notifications
             WHERE agent_id = ? AND read_at IS NULL'
        );
        $unreadStmt->execute([$currentAgent['id']]);
        $unread = (int)$unreadStmt->fetchColumn();

        foreach ($notifications as &$n) {
            $n['id']              = (int)$n['id'];
            $n['agent_id']        = (int)$n['agent_id'];
            $n['conversation_id'] = (int)$n['conversation_id'];
        }
        unset($n);

        echo json_encode([
            'success'       => true,
            'notifications' => $notifications,
            'unread'        => $unread,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);
        $now  = date('Y-m-d H:i:s');

        if (!empty($data['markAll'])) {
            $pdo->prepare(
                'UPDATE notifications SET read_at = ? WHERE agent_id = ? AND read_at IS NULL'
            )->execute([$now, $currentAgent['id']]);

            echo json_encode(['success' => true, 'marked' => 'all']);
            exit;
        }

        if (isset($data['id'])) {
            $notifId = (int)$data['id'];
            $pdo->prepare(
                'UPDATE notifications SET read_at = ? WHERE id = ? AND agent_id = ?'
            )->execute([$now, $notifId, $currentAgent['id']]);

            echo json_encode(['success' => true, 'marked' => $notifId]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Parámetros inválidos.']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);

} catch (PDOException $e) {
    error_log('[api/notifications] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno.']);
}
