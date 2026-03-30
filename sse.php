<?php
/**
 * sse.php — Server-Sent Events para actualizaciones en tiempo real.
 * GET ?token={agent_token}
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Validar token via query string (SSE no soporta headers personalizados)
$token = trim($_GET['token'] ?? '');

if (empty($token)) {
    http_response_code(401);
    echo 'data: ' . json_encode(['error' => 'Token requerido']) . "\n\n";
    exit;
}

try {
    $pdo = DB::get();

    $stmt = $pdo->prepare(
        'SELECT s.agent_id, s.expires_at,
                a.id, a.username, a.name, a.role, a.status
         FROM agent_sessions s
         JOIN agents a ON a.id = s.agent_id
         WHERE s.token = ?
           AND s.expires_at > NOW()
           AND a.status = ?
         LIMIT 1'
    );
    $stmt->execute([$token, 'active']);
    $agentRow = $stmt->fetch();

    if (!$agentRow) {
        http_response_code(401);
        echo 'data: ' . json_encode(['error' => 'Sesión inválida']) . "\n\n";
        exit;
    }

} catch (PDOException $e) {
    error_log('[sse] DB error: ' . $e->getMessage());
    http_response_code(500);
    exit;
}

$agentId   = (int)$agentRow['id'];
$agentRole = $agentRow['role'];

// Obtener departamentos del agente
try {
    $dStmt = $pdo->prepare(
        'SELECT department_id FROM agent_departments WHERE agent_id = ?'
    );
    $dStmt->execute([$agentId]);
    $deptIds = $dStmt->fetchAll(PDO::FETCH_COLUMN);
    $deptIds = array_map('intval', $deptIds);
} catch (PDOException $e) {
    $deptIds = [];
}

// Headers SSE
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: ' . PANEL_URL);

// Desactivar compresión de salida
if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', '1');
}
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', '0');

set_time_limit(0);
ignore_user_abort(false);

// Timestamp de inicio para obtener solo eventos nuevos
$lastCheck = microtime(true);
$lastPing  = time();
$iterations = 0;

function sseSend(string $event, array $data): void
{
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    if (ob_get_level() > 0) ob_flush();
    flush();
}

// Enviar evento inicial de conexión
sseSend('connected', ['agentId' => $agentId, 'timestamp' => microtime(true)]);

while (true) {
    if (connection_aborted()) {
        break;
    }

    $iterations++;
    $now = microtime(true);

    try {
        $pdo = DB::get();

        // Actualizar last_seen cada iteración
        $pdo->prepare('UPDATE agents SET last_seen = NOW() WHERE id = ?')
            ->execute([$agentId]);

        $sinceStr = date('Y-m-d H:i:s', (int)$lastCheck);

        // ── Mensajes nuevos en conversaciones del agente ──
        if ($agentRole === 'supervisor') {
            $msgStmt = $pdo->prepare(
                'SELECT m.*, c.phone, c.contact_name, c.conv_key, c.status AS conv_status
                 FROM messages m
                 JOIN conversations c ON c.id = m.conversation_id
                 WHERE m.created_at > ?
                   AND m.direction = ?
                 ORDER BY m.created_at ASC
                 LIMIT 50'
            );
            $msgStmt->execute([$sinceStr, 'in']);
        } else {
            if (empty($deptIds)) {
                $msgStmt = null;
                $messages = [];
            } else {
                $placeholders = implode(',', array_fill(0, count($deptIds), '?'));
                $msgStmt = $pdo->prepare(
                    "SELECT m.*, c.phone, c.contact_name, c.conv_key, c.status AS conv_status
                     FROM messages m
                     JOIN conversations c ON c.id = m.conversation_id
                     WHERE m.created_at > ?
                       AND m.direction = ?
                       AND (
                         c.department_id IN ({$placeholders})
                         OR c.agent_id = ?
                       )
                     ORDER BY m.created_at ASC
                     LIMIT 50"
                );
                $params = array_merge([$sinceStr, 'in'], $deptIds, [$agentId]);
                $msgStmt->execute($params);
            }
        }

        if (isset($msgStmt)) {
            $messages = $msgStmt->fetchAll();
            foreach ($messages as $msg) {
                sseSend('new_message', $msg);
            }
        }

        // ── Conversaciones actualizadas ──
        if ($agentRole === 'supervisor') {
            $convStmt = $pdo->prepare(
                'SELECT c.*, a.name AS agent_name, d.name AS dept_name, d.color AS dept_color
                 FROM conversations c
                 LEFT JOIN agents a ON a.id = c.agent_id
                 LEFT JOIN departments d ON d.id = c.department_id
                 WHERE c.updated_at > ?
                 ORDER BY c.updated_at DESC
                 LIMIT 20'
            );
            $convStmt->execute([$sinceStr]);
        } else {
            if (!empty($deptIds)) {
                $placeholders = implode(',', array_fill(0, count($deptIds), '?'));
                $convStmt = $pdo->prepare(
                    "SELECT c.*, a.name AS agent_name, d.name AS dept_name, d.color AS dept_color
                     FROM conversations c
                     LEFT JOIN agents a ON a.id = c.agent_id
                     LEFT JOIN departments d ON d.id = c.department_id
                     WHERE c.updated_at > ?
                       AND (
                         c.department_id IN ({$placeholders})
                         OR c.agent_id = ?
                       )
                     ORDER BY c.updated_at DESC
                     LIMIT 20"
                );
                $params = array_merge([$sinceStr], $deptIds, [$agentId]);
                $convStmt->execute($params);
            } else {
                $convStmt = null;
            }
        }

        if (isset($convStmt)) {
            $convUpdates = $convStmt->fetchAll();
            foreach ($convUpdates as $c) {
                sseSend('conversation_updated', $c);
            }
        }

        // ── Notificaciones nuevas ──
        $notifStmt = $pdo->prepare(
            'SELECT n.*, c.phone, c.contact_name
             FROM notifications n
             JOIN conversations c ON c.id = n.conversation_id
             WHERE n.agent_id = ?
               AND n.created_at > ?
               AND n.read_at IS NULL
             ORDER BY n.created_at DESC
             LIMIT 20'
        );
        $notifStmt->execute([$agentId, $sinceStr]);
        $notifs = $notifStmt->fetchAll();

        foreach ($notifs as $notif) {
            sseSend('notification', $notif);
        }

        $lastCheck = $now;

    } catch (PDOException $e) {
        error_log('[sse] query error: ' . $e->getMessage());
    }

    // Ping cada 25 segundos para mantener la conexión
    if ((time() - $lastPing) >= 25) {
        echo ": ping " . time() . "\n\n";
        if (ob_get_level() > 0) ob_flush();
        flush();
        $lastPing = time();
    }

    sleep(2);
}
