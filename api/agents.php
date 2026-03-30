<?php
/**
 * api/agents.php — CRUD de agentes (solo supervisor).
 * GET    → listar agentes con departamentos
 * POST   → crear agente
 * PUT    → actualizar agente
 * DELETE → desactivar agente
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';
requireSupervisor();

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = DB::get();

    // ── GET ──────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query(
            "SELECT a.id, a.username, a.name, a.email, a.phone, a.wa_alerts,
                    a.role, a.status, a.last_seen, a.created_at,
                    CASE WHEN a.last_seen >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                         THEN 1 ELSE 0 END AS online
             FROM agents a
             ORDER BY a.role ASC, a.name ASC"
        );
        $agents = $stmt->fetchAll();

        // Cargar departamentos de cada agente
        $deptStmt = $pdo->prepare(
            'SELECT ad.agent_id, d.id AS dept_id, d.name AS dept_name, d.slug, d.color
             FROM agent_departments ad
             JOIN departments d ON d.id = ad.department_id'
        );
        $deptStmt->execute();
        $allDepts = $deptStmt->fetchAll();

        // Indexar por agent_id
        $deptsByAgent = [];
        foreach ($allDepts as $d) {
            $deptsByAgent[(int)$d['agent_id']][] = $d;
        }

        foreach ($agents as &$ag) {
            $ag['id']           = (int)$ag['id'];
            $ag['wa_alerts']    = (bool)$ag['wa_alerts'];
            $ag['online']       = (bool)$ag['online'];
            $ag['departments']  = $deptsByAgent[$ag['id']] ?? [];
        }
        unset($ag);

        echo json_encode(['success' => true, 'agents' => $agents], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── POST (crear) ─────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);

        $username  = trim($data['username']  ?? '');
        $password  = trim($data['password']  ?? '');
        $name      = trim($data['name']      ?? '');
        $email     = trim($data['email']     ?? '');
        $phone     = trim($data['phone']     ?? '');
        $waAlerts  = !empty($data['wa_alerts']) ? 1 : 0;
        $role      = trim($data['role']      ?? 'agente');
        $deptIds   = array_map('intval', $data['dept_ids'] ?? []);

        if ($username === '' || $password === '' || $name === '' || $email === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Campos requeridos: username, password, name, email.']);
            exit;
        }

        if (!in_array($role, ['supervisor','agente'])) {
            $role = 'agente';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Email inválido.']);
            exit;
        }

        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres.']);
            exit;
        }

        $hashedPw = password_hash($password, PASSWORD_BCRYPT);
        $now      = date('Y-m-d H:i:s');

        $pdo->prepare(
            'INSERT INTO agents (username, password, name, email, phone, wa_alerts, role, status, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute([$username, $hashedPw, $name, $email, $phone ?: null, $waAlerts, $role, 'active', $now, $now]);

        $newId = (int)$pdo->lastInsertId();

        // Asignar departamentos
        if (!empty($deptIds)) {
            $insD = $pdo->prepare(
                'INSERT IGNORE INTO agent_departments (agent_id, department_id) VALUES (?,?)'
            );
            foreach ($deptIds as $dId) {
                if ($dId > 0) $insD->execute([$newId, $dId]);
            }
        }

        echo json_encode(['success' => true, 'id' => $newId]);
        exit;
    }

    // ── PUT (actualizar) ─────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);

        $agentId  = (int)($data['id'] ?? 0);
        $name     = trim($data['name']   ?? '');
        $email    = trim($data['email']  ?? '');
        $phone    = trim($data['phone']  ?? '');
        $waAlerts = isset($data['wa_alerts']) ? ((int)$data['wa_alerts'] ? 1 : 0) : 0;
        $role     = trim($data['role']   ?? '');
        $status   = trim($data['status'] ?? '');
        $password = trim($data['password'] ?? '');
        $deptIds  = array_map('intval', $data['dept_ids'] ?? []);

        if ($agentId <= 0 || $name === '' || $email === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'id, name y email son requeridos.']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Email inválido.']);
            exit;
        }

        if (!in_array($role, ['supervisor','agente'])) $role = 'agente';
        if (!in_array($status, ['active','inactive']))  $status = 'active';

        $params   = [$name, $email, $phone ?: null, $waAlerts, $role, $status];
        $setParts = 'name=?, email=?, phone=?, wa_alerts=?, role=?, status=?';

        if ($password !== '') {
            if (strlen($password) < 6) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres.']);
                exit;
            }
            $setParts .= ', password=?';
            $params[]  = password_hash($password, PASSWORD_BCRYPT);
        }

        $params[] = $agentId;
        $pdo->prepare("UPDATE agents SET {$setParts}, updated_at=NOW() WHERE id=?")
            ->execute($params);

        // Reemplazar departamentos
        $pdo->prepare('DELETE FROM agent_departments WHERE agent_id = ?')
            ->execute([$agentId]);

        if (!empty($deptIds)) {
            $insD = $pdo->prepare(
                'INSERT IGNORE INTO agent_departments (agent_id, department_id) VALUES (?,?)'
            );
            foreach ($deptIds as $dId) {
                if ($dId > 0) $insD->execute([$agentId, $dId]);
            }
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // ── DELETE (desactivar) ──────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);

        $agentId = (int)($data['id'] ?? 0);

        if ($agentId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'id requerido.']);
            exit;
        }

        // No desactivar al propio admin logueado
        if ($agentId === $currentAgent['id']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No puedes desactivar tu propia cuenta.']);
            exit;
        }

        // Invalidar sesiones del agente
        $pdo->prepare('DELETE FROM agent_sessions WHERE agent_id = ?')
            ->execute([$agentId]);

        $pdo->prepare("UPDATE agents SET status = 'inactive', updated_at = NOW() WHERE id = ?")
            ->execute([$agentId]);

        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);

} catch (PDOException $e) {
    error_log('[api/agents] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno.']);
}
