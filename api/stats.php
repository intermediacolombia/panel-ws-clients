<?php
/**
 * api/stats.php — Estadísticas del panel.
 * GET ?period=today|week|month
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$period = trim($_GET['period'] ?? 'today');
if (!in_array($period, ['today', 'week', 'month'])) {
    $period = 'today';
}

// Calcular rango de fechas
$now = new DateTime('now', new DateTimeZone('America/Bogota'));

switch ($period) {
    case 'week':
        $start = (clone $now)->modify('monday this week')->setTime(0, 0, 0)->format('Y-m-d H:i:s');
        break;
    case 'month':
        $start = (clone $now)->setDate((int)$now->format('Y'), (int)$now->format('m'), 1)
                             ->setTime(0, 0, 0)->format('Y-m-d H:i:s');
        break;
    default: // today
        $start = (clone $now)->setTime(0, 0, 0)->format('Y-m-d H:i:s');
        break;
}
$end = $now->format('Y-m-d H:i:s');

try {
    $pdo = DB::get();

    // ── Filtro de alcance ────────────────────────────────────────
    $isSupervisor = $currentAgent['role'] === 'supervisor';

    if (!$isSupervisor) {
        $deptIds = $currentAgent['dept_ids'];
        if (empty($deptIds)) {
            $scopeWhere = 'AND (c.agent_id = ' . (int)$currentAgent['id'] . ')';
        } else {
            $ph = implode(',', array_map('intval', $deptIds));
            $scopeWhere = 'AND (c.department_id IN (' . $ph . ') OR c.agent_id = ' . (int)$currentAgent['id'] . ')';
        }
    } else {
        $scopeWhere = '';
    }

    // ── Totales por status ───────────────────────────────────────
    // Pendientes/Attending/Bot: creados en el período
    // Resueltos: resueltos dentro del período (sin importar cuándo se crearon)
    $totStmt = $pdo->prepare(
        "SELECT
           COUNT(*) AS total,
           SUM(CASE WHEN c.status='pending'   THEN 1 ELSE 0 END) AS pending,
           SUM(CASE WHEN c.status='attending' THEN 1 ELSE 0 END) AS attending,
           SUM(CASE WHEN c.status='bot'       THEN 1 ELSE 0 END) AS bot
         FROM conversations c
         WHERE c.created_at BETWEEN ? AND ?
         {$scopeWhere}"
    );
    $totStmt->execute([$start, $end]);
    $totals = $totStmt->fetch();

    // Resueltos en el período (filtrado por resolved_at, sin importar estado actual)
    $resolvedStmt = $pdo->prepare(
        "SELECT COUNT(*) AS resolved
         FROM conversations c
         WHERE c.resolved_at IS NOT NULL
           AND c.resolved_at BETWEEN ? AND ?
         {$scopeWhere}"
    );
    $resolvedStmt->execute([$start, $end]);
    $resolvedRow = $resolvedStmt->fetch();

    // ── Tiempo promedio de atención (minutos) ────────────────────
    // Usa assigned_at si existe, si no usa created_at como inicio
    $avgStmt = $pdo->prepare(
        "SELECT AVG(TIMESTAMPDIFF(MINUTE, COALESCE(assigned_at, created_at), resolved_at)) AS avg_minutes
         FROM conversations c
         WHERE resolved_at IS NOT NULL
           AND resolved_at BETWEEN ? AND ?
           AND TIMESTAMPDIFF(MINUTE, COALESCE(assigned_at, created_at), resolved_at) >= 0
         {$scopeWhere}"
    );
    $avgStmt->execute([$start, $end]);
    $avgRow = $avgStmt->fetch();
    $avgMinutes = $avgRow['avg_minutes'] !== null ? round((float)$avgRow['avg_minutes'], 1) : null;

    // ── Conversaciones por hora (array 24 slots) ─────────────────
    $hourlyData = array_fill(0, 24, 0);
    $hourStmt   = $pdo->prepare(
        "SELECT HOUR(c.created_at) AS hora, COUNT(*) AS cnt
         FROM conversations c
         WHERE c.created_at BETWEEN ? AND ?
         {$scopeWhere}
         GROUP BY HOUR(c.created_at)"
    );
    $hourStmt->execute([$start, $end]);
    foreach ($hourStmt->fetchAll() as $row) {
        $hourlyData[(int)$row['hora']] = (int)$row['cnt'];
    }

    // ── Mensajes enviados vs recibidos ───────────────────────────
    $msgStmt = $pdo->prepare(
        "SELECT
           SUM(CASE WHEN m.direction='out' THEN 1 ELSE 0 END) AS sent,
           SUM(CASE WHEN m.direction='in'  THEN 1 ELSE 0 END) AS received
         FROM messages m
         JOIN conversations c ON c.id = m.conversation_id
         WHERE m.created_at BETWEEN ? AND ?
         {$scopeWhere}"
    );
    $msgStmt->execute([$start, $end]);
    $msgTotals = $msgStmt->fetch();

    // ── Tabla por agente (solo supervisor) ──────────────────────
    $agentStats = [];
    if ($isSupervisor) {
        $agStmt = $pdo->prepare(
            "SELECT
               a.id, a.name, a.username,
               CASE WHEN a.last_seen >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END AS online,
               COUNT(DISTINCT c.id) AS assigned,
               COUNT(DISTINCT CASE WHEN c.resolved_at IS NOT NULL AND c.resolved_at BETWEEN ? AND ? THEN c.id END) AS resolved,
               AVG(CASE WHEN c.resolved_at IS NOT NULL
                            AND c.resolved_at BETWEEN ? AND ?
                            AND TIMESTAMPDIFF(MINUTE, COALESCE(c.assigned_at, c.created_at), c.resolved_at) >= 0
                        THEN TIMESTAMPDIFF(MINUTE, COALESCE(c.assigned_at, c.created_at), c.resolved_at)
                        ELSE NULL END) AS avg_minutes
             FROM agents a
             LEFT JOIN conversations c ON c.agent_id = a.id
               AND c.created_at BETWEEN ? AND ?
             WHERE a.status = 'active'
             GROUP BY a.id
             ORDER BY resolved DESC"
        );
        $agStmt->execute([$start, $end, $start, $end, $start, $end]);
        $agentStats = $agStmt->fetchAll();

        foreach ($agentStats as &$ag) {
            $ag['id']          = (int)$ag['id'];
            $ag['online']      = (bool)$ag['online'];
            $ag['assigned']    = (int)$ag['assigned'];
            $ag['resolved']    = (int)$ag['resolved'];
            $ag['avg_minutes'] = $ag['avg_minutes'] !== null ? round((float)$ag['avg_minutes'], 1) : null;
        }
        unset($ag);
    }

    // ── Agentes online ahora ─────────────────────────────────────
    $onlineStmt = $pdo->query(
        "SELECT COUNT(*) FROM agents
         WHERE status = 'active'
           AND last_seen >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
    );
    $onlineCount = (int)$onlineStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'period'  => $period,
        'range'   => ['start' => $start, 'end' => $end],
        'stats'   => [
            'total'       => (int)$totals['total'],
            'pending'     => (int)$totals['pending'],
            'attending'   => (int)$totals['attending'],
            'resolved'    => (int)($resolvedRow['resolved'] ?? 0),
            'bot'         => (int)$totals['bot'],
            'avg_minutes' => $avgMinutes,
            'hourly'      => $hourlyData,
            'sent'        => (int)($msgTotals['sent'] ?? 0),
            'received'    => (int)($msgTotals['received'] ?? 0),
            'online'      => $onlineCount,
            'agents'      => $agentStats,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    error_log('[api/stats] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno.']);
}
