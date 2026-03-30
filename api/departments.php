<?php
/**
 * api/departments.php — Lista departamentos (solo lectura).
 * Los departamentos se crean automáticamente desde el webhook via getOrCreateDepartment().
 * Para agregar/cambiar departamentos edita PANEL_AREAS en config.php.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

try {
    $pdo = DB::get();

    $stmt = $pdo->query(
        'SELECT d.*,
                COUNT(DISTINCT ad.agent_id) AS agent_count,
                COUNT(DISTINCT CASE WHEN c.status IN (\'pending\',\'attending\') THEN c.id END) AS active_convs
         FROM departments d
         LEFT JOIN agent_departments ad ON ad.department_id = d.id
         LEFT JOIN conversations c ON c.department_id = d.id
         WHERE d.active = 1
         GROUP BY d.id
         ORDER BY d.name ASC'
    );
    $depts = $stmt->fetchAll();

    foreach ($depts as &$d) {
        $d['id']           = (int)$d['id'];
        $d['active']       = (bool)$d['active'];
        $d['agent_count']  = (int)$d['agent_count'];
        $d['active_convs'] = (int)$d['active_convs'];
    }
    unset($d);

    echo json_encode(['success' => true, 'departments' => $depts], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('[api/departments] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno.']);
}
