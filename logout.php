<?php
/**
 * logout.php — Cierra la sesión del agente
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$token = $_SESSION['agent_token'] ?? ($_COOKIE['agent_token'] ?? null);

if ($token) {
    try {
        $pdo = DB::get();
        $pdo->prepare('DELETE FROM agent_sessions WHERE token = ?')
            ->execute([$token]);
    } catch (PDOException $e) {
        error_log('[logout] ' . $e->getMessage());
    }
}

// Destruir sesión PHP
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']
    );
}
session_destroy();

// Eliminar cookie de autenticación
setcookie('agent_token', '', time() - 3600, '/', '', true, true);

header('Location: login.php');
exit;
