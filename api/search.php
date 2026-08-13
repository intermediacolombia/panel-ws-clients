<?php
/**
 * api/search.php — Busca en el contenido de mensajes y devuelve conversaciones.
 * GET ?q=texto (mínimo 3 caracteres)
 */

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$q = trim($_GET['q'] ?? '');

if (mb_strlen($q) < 3) {
    echo json_encode(['success' => false, 'error' => 'El parámetro q debe tener mínimo 3 caracteres.']);
    exit;
}

try {
    $pdo = DB::get();

    // ── Filtro de rol (igual que conversations.php) ───────────────
    $roleWhere  = ['1=1'];
    $roleParams = [];

    if ($currentAgent['role'] !== 'supervisor') {
        $deptIds = $currentAgent['dept_ids'];

        if (empty($deptIds)) {
            $roleWhere[]  = '(c.agent_id = ?)';
            $roleParams[] = $currentAgent['id'];
        } else {
            $placeholders = implode(',', array_fill(0, count($deptIds), '?'));
            $roleWhere[]  = "(c.department_id IN ({$placeholders}) OR c.agent_id = ?)";
            $roleParams   = array_merge($roleParams, $deptIds, [$currentAgent['id']]);
        }

        $roleWhere[]  = "(c.status != 'bot' OR c.agent_id = ?)";
        $roleParams[] = $currentAgent['id'];
    }

    $roleStr = implode(' AND ', $roleWhere);
    $like    = '%' . $q . '%';

    // ── Una coincidencia por conversación (la más reciente) ───────
    $sql = "SELECT
        c.id            AS conversation_id,
        c.contact_name,
        c.phone,
        c.status,
        c.agent_id,
        c.unread_count,
        c.last_message,
        c.last_message_at AS last_message_time,
        d.name          AS dept_name,
        d.color         AS dept_color,
        m.id            AS match_message_id,
        m.content       AS match_text,
        m.created_at    AS match_time,
        m.direction     AS match_direction
      FROM (
        SELECT id, conversation_id, content, created_at, direction,
               ROW_NUMBER() OVER (PARTITION BY conversation_id ORDER BY created_at DESC) AS rn
        FROM messages
        WHERE content LIKE ?
      ) m
      JOIN conversations c ON c.id = m.conversation_id
      LEFT JOIN departments d ON d.id = c.department_id
      WHERE m.rn = 1
        AND {$roleStr}
      ORDER BY m.created_at DESC
      LIMIT 50";

    $params = array_merge([$like], $roleParams);
    $stmt   = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // ── Recortar match_text centrado en el término buscado ────────
    foreach ($rows as &$row) {
        $row['conversation_id']  = (int)$row['conversation_id'];
        $row['match_message_id'] = (int)$row['match_message_id'];
        $row['agent_id']         = $row['agent_id'] !== null ? (int)$row['agent_id'] : null;
        $row['unread_count']     = (int)$row['unread_count'];
        $row['match_text']      = excerptAround($row['match_text'], $q, 120);
    }
    unset($row);

    echo json_encode(['success' => true, 'results' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    error_log('[api/search] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno.']);
}

/**
 * Recorta $text a $maxLen caracteres centrando $needle en el fragmento.
 */
function excerptAround(string $text, string $needle, int $maxLen): string
{
    $pos = mb_stripos($text, $needle);
    if ($pos === false || mb_strlen($text) <= $maxLen) {
        return mb_substr($text, 0, $maxLen);
    }

    $half  = (int)($maxLen / 2);
    $start = max(0, $pos - $half + (int)(mb_strlen($needle) / 2));
    $start = max(0, min($start, mb_strlen($text) - $maxLen));

    $excerpt = ($start > 0 ? '…' : '') . mb_substr($text, $start, $maxLen);
    if ($start + $maxLen < mb_strlen($text)) {
        $excerpt .= '…';
    }
    return $excerpt;
}
