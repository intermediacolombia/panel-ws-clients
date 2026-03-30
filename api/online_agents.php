<?php
/**
 * api/online_agents.php — Lista agentes en línea para transferencia.
 * GET ?dept_id=X  (opcional — filtra por departamento)
 * Excluye al agente actual. Solo devuelve activos con last_seen < 10 min.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';

header('Content-Type: application/json; charset=utf-8');

$deptId = (int)($_GET['dept_id'] ?? 0);

try {
    $pdo = DB::get();

    if ($deptId > 0) {
        $stmt = $pdo->prepare(
            'SELECT DISTINCT a.id, a.name, a.role
             FROM agents a
             JOIN agent_departments ad ON ad.agent_id = a.id
             WHERE a.status = ?
               AND a.id != ?
               AND a.last_seen > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
               AND ad.department_id = ?
             ORDER BY a.name ASC'
        );
        $stmt->execute(['active', $currentAgent['id'], $deptId]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT a.id, a.name, a.role
             FROM agents a
             WHERE a.status = ?
               AND a.id != ?
               AND a.last_seen > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
             ORDER BY a.name ASC'
        );
        $stmt->execute(['active', $currentAgent['id']]);
    }

    $agents = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'agents'  => $agents,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('[api/online_agents] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno.']);
}
