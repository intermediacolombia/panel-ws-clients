<?php
/**
 * api/login.php — Autenticación JSON para clientes móviles (Flutter).
 * POST { "username": "...", "password": "..." }
 * → { success, token, expires_at, agent: { id, username, name, email, role, departments } }
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

if ($username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Usuario y contraseña requeridos.']);
    exit;
}

// Obtener IP del cliente
$ip = '0.0.0.0';
foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $h) {
    if (!empty($_SERVER[$h])) {
        $candidate = trim(explode(',', $_SERVER[$h])[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            $ip = $candidate;
            break;
        }
    }
}

try {
    $pdo = DB::get();

    // Rate limiting
    $cutoff = date('Y-m-d H:i:s', time() - LOGIN_LOCKOUT_MINUTES * 60);
    $stmtAttempts = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > ?'
    );
    $stmtAttempts->execute([$ip, $cutoff]);
    if ((int)$stmtAttempts->fetchColumn() >= LOGIN_MAX_ATTEMPTS) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error'   => 'Demasiados intentos fallidos. Espera ' . LOGIN_LOCKOUT_MINUTES . ' minutos.',
        ]);
        exit;
    }

    // Buscar agente
    $stmt = $pdo->prepare(
        'SELECT id, username, name, email, role, status, password
         FROM agents WHERE username = ? LIMIT 1'
    );
    $stmt->execute([$username]);
    $agent = $stmt->fetch();

    if (!$agent || $agent['status'] !== 'active' || !password_verify($password, $agent['password'])) {
        $pdo->prepare('INSERT INTO login_attempts (ip) VALUES (?)')->execute([$ip]);
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Credenciales incorrectas o cuenta inactiva.']);
        exit;
    }

    // Crear token de sesión
    $token     = bin2hex(random_bytes(64));
    $expiresAt = date('Y-m-d H:i:s', time() + SESSION_EXPIRE_HOURS * 3600);
    $ua        = $_SERVER['HTTP_USER_AGENT'] ?? 'Flutter';

    $pdo->prepare(
        'INSERT INTO agent_sessions (agent_id, token, ip, user_agent, expires_at) VALUES (?,?,?,?,?)'
    )->execute([$agent['id'], $token, $ip, $ua, $expiresAt]);

    $pdo->prepare('UPDATE agents SET last_seen = NOW() WHERE id = ?')
        ->execute([$agent['id']]);

    // Obtener departamentos
    $dStmt = $pdo->prepare(
        'SELECT d.id, d.slug, d.name, d.color, d.icon
         FROM agent_departments ad
         JOIN departments d ON d.id = ad.department_id
         WHERE ad.agent_id = ?'
    );
    $dStmt->execute([$agent['id']]);
    $departments = $dStmt->fetchAll();

    echo json_encode([
        'success'    => true,
        'token'      => $token,
        'expires_at' => $expiresAt,
        'agent'      => [
            'id'          => (int)$agent['id'],
            'username'    => $agent['username'],
            'name'        => $agent['name'],
            'email'       => $agent['email'] ?? '',
            'role'        => $agent['role'],
            'departments' => $departments,
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('[api/login] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno.']);
}
