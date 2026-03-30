<?php

require_once __DIR__ . '/config-general.php';

// ── Almacenamiento de archivos ───────────────────────────────
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', PANEL_URL . '/uploads/');

// ── Sesiones ─────────────────────────────────────────────────
define('SESSION_EXPIRE_HOURS',  720); // 30 días
define('SESSION_RENEW_MINUTES', 30);

// ── Rate limiting en login ───────────────────────────────────
define('LOGIN_MAX_ATTEMPTS',    5);
define('LOGIN_LOCKOUT_MINUTES', 15);
define('LOGIN_BLOCK_MINUTES',   15); // alias — usado en login.php

// ── Firebase Cloud Messaging (HTTP v1) ───────────────────────
// Descarga el JSON de: Firebase Console → ⚙️ Configuración → Cuentas de servicio → Generar nueva clave privada
// Guárdalo en la raíz del panel como: firebase-service-account.json
define('FCM_SERVICE_ACCOUNT', __DIR__ . '/app-ws-c9beb-firebase-adminsdk-fbsvc-fb4c74dea4.json');

// ── Zona horaria ─────────────────────────────────────────────
date_default_timezone_set('America/Bogota');

// ── Entorno ──────────────────────────────────────────────────
define('APP_DEBUG', false);

// ── Áreas / Departamentos ─────────────────────────────────────
// Define aquí los departamentos de la empresa.
// El webhook usa estos nombres al llamar a notifyPanel().
// El panel los crea automáticamente si no existen en la BD.
// Para cambiar de empresa: solo edita esta sección.


// ── Cabeceras de seguridad globales ──────────────────────────
if (!defined('SECURITY_HEADERS_SENT')) {
    define('SECURITY_HEADERS_SENT', true);
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: blob: " . UPLOAD_URL . "; connect-src 'self';");
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
