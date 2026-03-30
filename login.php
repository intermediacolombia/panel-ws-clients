<?php
/**
 * login.php — Página de inicio de sesión del Panel de Agentes
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

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

// Si ya está logueado, redirigir
if (!empty($_SESSION['agent_token'])) {
    header('Location: index.php');
    exit;
}

$error     = '';
$csrfToken = '';

// Generar o recuperar CSRF token
if (empty($_SESSION['csrf_login'])) {
    $_SESSION['csrf_login'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_login'];

function getClientIpLogin(): string
{
    $headers = ['HTTP_CF_CONNECTING_IP','HTTP_X_REAL_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    do {
        // 1. CSRF
        $postCsrf = $_POST['csrf_token'] ?? '';
        if (!hash_equals($csrfToken, $postCsrf)) {
            $error = 'Token de seguridad inválido. Recarga la página.';
            break;
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Por favor completa todos los campos.';
            break;
        }

        $ip = getClientIpLogin();

        try {
            $pdo = DB::get();

            // 2. Rate limiting
            $lockoutSecs = LOGIN_LOCKOUT_MINUTES * 60;
            $cutoff      = date('Y-m-d H:i:s', time() - $lockoutSecs);
            $stmtAttempts = $pdo->prepare(
                'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > ?'
            );
            $stmtAttempts->execute([$ip, $cutoff]);
            $attemptCount = (int)$stmtAttempts->fetchColumn();

            if ($attemptCount >= LOGIN_MAX_ATTEMPTS) {
                $error = 'Demasiados intentos fallidos. Inténtalo en ' . LOGIN_LOCKOUT_MINUTES . ' minutos.';
                break;
            }

            // 3. Buscar agente
            $stmtAgent = $pdo->prepare(
                'SELECT id, password, name, status FROM agents WHERE username = ? LIMIT 1'
            );
            $stmtAgent->execute([$username]);
            $agent = $stmtAgent->fetch();

            if (!$agent || $agent['status'] !== 'active' || !password_verify($password, $agent['password'])) {
                // Registrar intento fallido
                $pdo->prepare('INSERT INTO login_attempts (ip) VALUES (?)')
                    ->execute([$ip]);
                $error = 'Credenciales incorrectas o cuenta inactiva.';
                break;
            }

            // 4. Crear sesión
            $token     = bin2hex(random_bytes(64));
            $expiresAt = date('Y-m-d H:i:s', time() + SESSION_EXPIRE_HOURS * 3600);
            $ua        = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $pdo->prepare(
                'INSERT INTO agent_sessions (agent_id, token, ip, user_agent, expires_at) VALUES (?,?,?,?,?)'
            )->execute([$agent['id'], $token, $ip, $ua, $expiresAt]);

            $pdo->prepare('UPDATE agents SET last_seen = NOW() WHERE id = ?')
                ->execute([$agent['id']]);

            // 5. Guardar token en sesión y cookie
            $_SESSION['agent_token'] = $token;
            setcookie('agent_token', $token, time() + SESSION_EXPIRE_HOURS * 3600, '/', '', true, true);

            // Regenerar CSRF
            unset($_SESSION['csrf_login']);

            header('Location: index.php');
            exit;

        } catch (PDOException $e) {
            error_log('[login] ' . $e->getMessage());
            $error = 'Error interno. Intenta de nuevo.';
        }
    } while (false);
}
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel de Agentes — Iniciar Sesión</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
  <div class="login-bg">
    <div class="login-card">

      <!-- Logo -->
      <div class="login-logo">
        <div class="logo-circle">
          <i class="fab fa-whatsapp"></i>
        </div>
      </div>

      <!-- Título -->
      <h1 class="login-title">Panel de Agentes</h1>
      <p class="login-subtitle">InterMedia Host — WhatsApp CRM</p>

      <!-- Error -->
      <?php if ($error !== ''): ?>
        <div class="login-error">
          <i class="fas fa-exclamation-circle"></i>
          <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <!-- Formulario -->
      <form method="POST" action="login.php" class="login-form" autocomplete="off" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <div class="form-group">
          <label for="username">
            <i class="fas fa-user"></i> Usuario
          </label>
          <input
            type="text"
            id="username"
            name="username"
            placeholder="Tu nombre de usuario"
            value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8') : '' ?>"
            autocomplete="username"
            required
            autofocus
          >
        </div>

        <div class="form-group">
          <label for="password">
            <i class="fas fa-lock"></i> Contraseña
          </label>
          <div class="input-password">
            <input
              type="password"
              id="password"
              name="password"
              placeholder="Tu contraseña"
              autocomplete="current-password"
              required
            >
            <button type="button" class="toggle-password" onclick="togglePassword()" tabindex="-1" aria-label="Mostrar contraseña">
              <i class="fas fa-eye" id="eye-icon"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-login">
          <i class="fas fa-sign-in-alt"></i>
          Entrar
        </button>
      </form>

      <p class="login-footer">
        &copy; <?= date('Y') ?> InterMedia Host
      </p>
    </div>
  </div>

  <script>
    function togglePassword() {
      const input = document.getElementById('password');
      const icon  = document.getElementById('eye-icon');
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
      } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
      }
    }
  </script>
</body>
</html>
