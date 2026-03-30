<?php
/**
 * api/poll.php — Long polling como respaldo cuando SSE falla.
 * GET ?since=1234567890.123  (microtime float)
 * Espera hasta 25 segundos si no hay datos nuevos.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$since = (float)($_GET['since'] ?? (microtime(true) - 5));
if ($since <= 0) {
    $since = microtime(true) - 5;
}

$sinceStr   = date('Y-m-d H:i:s', (int)$since);
$maxWait    = 25;   // segundos máximos de espera
$sleepSecs  = 2;
$elapsed    = 0;

$agentId   = $currentAgent['id'];
$agentRole = $currentAgent['role'];
$deptIds   = $currentAgent['dept_ids'];

set_time_limit(60);

try {
    $pdo = DB::get();

    while ($elapsed < $maxWait) {
        $pdo->prepare('UPDATE agents SET last_seen = NOW() WHERE id = ?')
            ->execute([$agentId]);

        $messages      = [];
        $conversations = [];
        $notifications = [];

        // ── Mensajes nuevos ──────────────────────────────────────
        if ($agentRole === 'supervisor') {
            $mStmt = $pdo->prepare(
                'SELECT m.*, c.phone, c.contact_name, c.conv_key, c.status AS conv_status
                 FROM messages m
                 JOIN conversations c ON c.id = m.conversation_id
                 WHERE m.created_at > ?
                   AND m.direction = ?
                 ORDER BY m.created_at ASC LIMIT 50'
            );
            $mStmt->execute([$sinceStr, 'in']);
        } elseif (!empty($deptIds)) {
            $ph = implode(',', array_fill(0, count($deptIds), '?'));
            $mStmt = $pdo->prepare(
                "SELECT m.*, c.phone, c.contact_name, c.conv_key, c.status AS conv_status
                 FROM messages m
                 JOIN conversations c ON c.id = m.conversation_id
                 WHERE m.created_at > ?
                   AND m.direction = ?
                   AND (c.department_id IN ({$ph}) OR c.agent_id = ?)
                 ORDER BY m.created_at ASC LIMIT 50"
            );
            $params = array_merge([$sinceStr, 'in'], $deptIds, [$agentId]);
            $mStmt->execute($params);
        } else {
            $mStmt = null;
        }

        if (isset($mStmt)) {
            $messages = $mStmt->fetchAll();
        }

        // ── Conversaciones actualizadas ──────────────────────────
        if ($agentRole === 'supervisor') {
            $cStmt = $pdo->prepare(
                'SELECT c.*, a.name AS agent_name, d.name AS dept_name, d.color AS dept_color
                 FROM conversations c
                 LEFT JOIN agents a ON a.id = c.agent_id
                 LEFT JOIN departments d ON d.id = c.department_id
                 WHERE c.updated_at > ?
                 ORDER BY c.updated_at DESC LIMIT 20'
            );
            $cStmt->execute([$sinceStr]);
        } elseif (!empty($deptIds)) {
            $ph = implode(',', array_fill(0, count($deptIds), '?'));
            $cStmt = $pdo->prepare(
                "SELECT c.*, a.name AS agent_name, d.name AS dept_name, d.color AS dept_color
                 FROM conversations c
                 LEFT JOIN agents a ON a.id = c.agent_id
                 LEFT JOIN departments d ON d.id = c.department_id
                 WHERE c.updated_at > ?
                   AND (c.department_id IN ({$ph}) OR c.agent_id = ?)
                 ORDER BY c.updated_at DESC LIMIT 20"
            );
            $params = array_merge([$sinceStr], $deptIds, [$agentId]);
            $cStmt->execute($params);
        } else {
            $cStmt = null;
        }

        if (isset($cStmt)) {
            $conversations = $cStmt->fetchAll();
        }

        // ── Notificaciones nuevas ────────────────────────────────
        $nStmt = $pdo->prepare(
            'SELECT n.*, c.phone, c.contact_name
             FROM notifications n
             JOIN conversations c ON c.id = n.conversation_id
             WHERE n.agent_id = ?
               AND n.created_at > ?
               AND n.read_at IS NULL
             ORDER BY n.created_at DESC LIMIT 20'
        );
        $nStmt->execute([$agentId, $sinceStr]);
        $notifications = $nStmt->fetchAll();

        // Hay datos → responder inmediatamente
        if (!empty($messages) || !empty($conversations) || !empty($notifications)) {
            echo json_encode([
                'success'       => true,
                'timestamp'     => microtime(true),
                'messages'      => $messages,
                'conversations' => $conversations,
                'notifications' => $notifications,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        // Sin datos → esperar
        sleep($sleepSecs);
        $elapsed += $sleepSecs;
    }

    // Timeout sin datos
    echo json_encode([
        'success'       => true,
        'timestamp'     => microtime(true),
        'messages'      => [],
        'conversations' => [],
        'notifications' => [],
    ]);

} catch (PDOException $e) {
    error_log('[api/poll] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno.']);
}
