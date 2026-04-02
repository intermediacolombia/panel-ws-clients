<?php
/**
 * api/conversations.php — Lista de conversaciones filtrada según rol.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$statusFilter = trim($_GET['status'] ?? 'all');
$deptFilter   = (int)($_GET['dept']   ?? 0);
$search       = trim($_GET['search']  ?? '');
$limit        = max(1, min(200, (int)($_GET['limit']  ?? 50)));
$offset       = max(0, (int)($_GET['offset'] ?? 0));

try {
    $pdo = DB::get();

    // ── Construir WHERE ──────────────────────────────────────────
    $where  = ['1=1'];
    $params = [];

    // Filtro de rol
    if ($currentAgent['role'] !== 'supervisor') {
        $deptIds = $currentAgent['dept_ids'];

        if (empty($deptIds)) {
            // Agente sin departamentos — solo ve sus propias conversaciones asignadas
            $where[]  = '(c.agent_id = ?)';
            $params[] = $currentAgent['id'];
        } else {
            $placeholders = implode(',', array_fill(0, count($deptIds), '?'));
            $where[]  = "(c.department_id IN ({$placeholders}) OR c.agent_id = ?)";
            $params   = array_merge($params, $deptIds, [$currentAgent['id']]);
        }

        // Agente no ve conversaciones en modo bot a menos que sean suyas
        $where[]  = "(c.status != 'bot' OR c.agent_id = ?)";
        $params[] = $currentAgent['id'];
    }

    // Filtro de status
    if ($statusFilter !== 'all' && in_array($statusFilter, ['pending','attending','resolved','bot'])) {
        if ($statusFilter === 'resolved') {
            // Mostrar conversaciones resueltas (incluyendo las que regresaron a bot tras ser resueltas)
            $where[]  = '(c.status = ? OR (c.resolved_at IS NOT NULL AND c.status = ?))';
            $params[] = 'resolved';
            $params[] = 'bot';
        } else {
            $where[]  = 'c.status = ?';
            $params[] = $statusFilter;
        }
    }

    // Filtro de departamento
    if ($deptFilter > 0) {
        $where[]  = 'c.department_id = ?';
        $params[] = $deptFilter;
    }

    // Búsqueda por nombre o número
    if ($search !== '') {
        $like     = '%' . $search . '%';
        $where[]  = '(c.contact_name LIKE ? OR c.phone LIKE ?)';
        $params[] = $like;
        $params[] = $like;
    }

    $whereStr = implode(' AND ', $where);

    // ── Conteos ──────────────────────────────────────────────────
    $countSql = "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN c.status = 'pending'   THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN c.status = 'attending' THEN 1 ELSE 0 END) AS attending,
        SUM(CASE WHEN c.status = 'resolved' OR c.resolved_at IS NOT NULL THEN 1 ELSE 0 END) AS resolved,
        SUM(CASE WHEN c.status = 'bot'       THEN 1 ELSE 0 END) AS bot
      FROM conversations c
      WHERE {$whereStr}";

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $counts = $countStmt->fetch();

    // ── Lista ────────────────────────────────────────────────────
    // Ordenar: pending primero, luego attending, luego resolved/bot, por last_message_at DESC
    $sql = "SELECT
        c.id, c.conv_key, c.phone, c.contact_name, c.client_id,
        c.department_id, c.area_label, c.status,
        c.agent_id, c.assigned_at, c.resolved_at,
        c.first_contact_at, c.last_message_at, c.unread_count,
        c.created_at, c.updated_at,
        a.name  AS agent_name,
        d.name  AS dept_name,
        d.color AS dept_color,
        d.icon  AS dept_icon,
        (SELECT content FROM messages
           WHERE conversation_id = c.id
           ORDER BY created_at DESC LIMIT 1) AS last_message,
        (SELECT direction FROM messages
           WHERE conversation_id = c.id
           ORDER BY created_at DESC LIMIT 1) AS last_direction
      FROM conversations c
      LEFT JOIN agents      a ON a.id = c.agent_id
      LEFT JOIN departments d ON d.id = c.department_id
      WHERE {$whereStr}
      ORDER BY
        CASE c.status
          WHEN 'pending'   THEN 1
          WHEN 'attending' THEN 2
          WHEN 'resolved'  THEN 3
          ELSE 4
        END ASC,
        c.last_message_at DESC
      LIMIT ? OFFSET ?";

    $listParams   = array_merge($params, [$limit, $offset]);
    $listStmt     = $pdo->prepare($sql);
    $listStmt->execute($listParams);
    $conversations = $listStmt->fetchAll();

    // Formatear tiempo
    foreach ($conversations as &$conv) {
        $conv['time_formatted'] = formatTime($conv['last_message_at']);
        $conv['id']             = (int)$conv['id'];
        $conv['unread_count']   = (int)$conv['unread_count'];
        $conv['department_id']  = $conv['department_id'] !== null ? (int)$conv['department_id'] : null;
        $conv['agent_id']       = $conv['agent_id'] !== null ? (int)$conv['agent_id'] : null;
    }
    unset($conv);

    echo json_encode([
        'success'       => true,
        'conversations' => $conversations,
        'total'         => (int)$counts['total'],
        'pending'       => (int)$counts['pending'],
        'attending'     => (int)$counts['attending'],
        'resolved'      => (int)$counts['resolved'],
        'bot'           => (int)$counts['bot'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    error_log('[api/conversations] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno.']);
}
