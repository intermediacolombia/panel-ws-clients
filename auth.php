<?php
/**
 * auth.php — Validación de sesión de agente.
 * Incluir al inicio de cada endpoint/página protegida.
 * Popula $currentAgent con los datos del agente autenticado.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// ── CORS para clientes móviles (Flutter) ──────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_EXPIRE_HOURS * 3600,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Detectar si la petición es AJAX
function _isAjax(): bool
{
    return (
        !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) || (
        isset($_SERVER['HTTP_ACCEPT']) &&
        str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')
    );
}

// Leer token de sesión PHP o cookie
$_authToken = $_SESSION['agent_token'] ?? ($_COOKIE['agent_token'] ?? null);

// Bearer token para clientes móviles (Flutter) — sin sesión/cookie
if (empty($_authToken)) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (empty($authHeader) && function_exists('apache_request_headers')) {
        $authHeader = apache_request_headers()['Authorization'] ?? '';
    }
    if (preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $_bearerMatch)) {
        $_authToken = $_bearerMatch[1];
    }
}

if (empty($_authToken)) {
    if (_isAjax()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No autenticado.']);
        exit;
    }
    header('Location: /login.php');
    exit;
}

try {
    $pdo = DB::get();

    // Buscar sesión válida y no expirada
    $stmt = $pdo->prepare(
        'SELECT s.agent_id, s.expires_at,
                a.id, a.username, a.name, a.email, a.role, a.status
         FROM agent_sessions s
         JOIN agents a ON a.id = s.agent_id
         WHERE s.token = ?
           AND s.expires_at > NOW()
           AND a.status = ?
         LIMIT 1'
    );
    $stmt->execute([$_authToken, 'active']);
    $sessionRow = $stmt->fetch();

    if (!$sessionRow) {
        // Sesión inválida o expirada
        unset($_SESSION['agent_token']);
        if (!headers_sent()) {
            setcookie('agent_token', '', time() - 3600, '/', '', true, true);
        }
        if (_isAjax()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Sesión expirada.']);
            exit;
        }
        header('Location: /login.php');
        exit;
    }

    // Renovar sesión si faltan menos de 24 horas para expirar (sesión deslizante)
    $expiresTs = strtotime($sessionRow['expires_at']);
    $renewSecs = 24 * 3600; // renovar si queda menos de 1 día

    if (($expiresTs - time()) < $renewSecs) {
        $newExpires = date('Y-m-d H:i:s', time() + SESSION_EXPIRE_HOURS * 3600);
        $upd = $pdo->prepare('UPDATE agent_sessions SET expires_at = ? WHERE token = ?');
        $upd->execute([$newExpires, $_authToken]);
        if (!headers_sent()) {
            setcookie('agent_token', $_authToken, time() + SESSION_EXPIRE_HOURS * 3600, '/', '', true, true);
        }
    }

    // Actualizar last_seen
    $pdo->prepare('UPDATE agents SET last_seen = NOW() WHERE id = ?')
        ->execute([$sessionRow['id']]);

    // Obtener departamentos del agente
    $dStmt = $pdo->prepare(
        'SELECT d.id, d.slug, d.name
         FROM agent_departments ad
         JOIN departments d ON d.id = ad.department_id
         WHERE ad.agent_id = ?'
    );
    $dStmt->execute([$sessionRow['id']]);
    $depts = $dStmt->fetchAll();

    $deptIds   = array_column($depts, 'id');
    $deptSlugs = array_column($depts, 'slug');
    $deptNames = array_column($depts, 'name');

    // Variable global usada por todos los endpoints
    $currentAgent = [
        'id'          => (int)$sessionRow['id'],
        'username'    => $sessionRow['username'],
        'name'        => $sessionRow['name'],
        'email'       => $sessionRow['email'],
        'role'        => $sessionRow['role'],
        'status'      => $sessionRow['status'],
        'dept_ids'    => array_map('intval', $deptIds),
        'dept_slugs'  => $deptSlugs,
        'dept_names'  => $deptNames,
        'token'       => $_authToken,
    ];

    // Sincronizar PANEL_AREAS con la tabla departments (INSERT IGNORE)
    syncPanelAreas();

} catch (PDOException $e) {
    error_log('[auth] PDO error: ' . $e->getMessage());
    if (_isAjax()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error interno de autenticación.']);
        exit;
    }
    header('Location: /login.php');
    exit;
}

/**
 * Fuerza que el agente sea supervisor, o emite 403.
 */
function requireSupervisor(): void
{
    global $currentAgent;
    if (!isset($currentAgent) || $currentAgent['role'] !== 'supervisor') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acceso restringido a supervisores.']);
        exit;
    }
}

/**
 * Verifica si el agente actual tiene acceso a una conversación.
 * Supervisor: siempre. Agente: si es de su dept o está asignada a él.
 */
function canAccessConversation(array $conv): bool
{
    global $currentAgent;

    if ($currentAgent['role'] === 'supervisor') {
        return true;
    }

    // Asignada a este agente
    if ((int)$conv['agent_id'] === $currentAgent['id']) {
        return true;
    }

    // Departamento del agente
    if ($conv['department_id'] !== null && in_array((int)$conv['department_id'], $currentAgent['dept_ids'])) {
        return true;
    }

    return false;
}
