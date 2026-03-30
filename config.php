<?php
/**
 * config.php — Configuración central del Panel de Agentes (V2)
 * Ajusta todos los valores antes de desplegar en producción.
 */

// ── Base de datos ────────────────────────────────────────────
define('DB_HOST', 'intermediahost.co');
define('DB_PORT', '3306');
define('DB_USER', 'inte_whatsapp_activ');
define('DB_PASS', 'CCJRWNjuKxDR$J4Y');
define('DB_NAME', 'inte_whatsapp_activ');

// ── API Node.js Baileys ──────────────────────────────────────
// V2: renombradas a WA_API_URL / WA_API_KEY para evitar colisión
define('WA_API_URL', 'https://whatsapp.activgym.com.co');   // sin slash final
define('WA_API_KEY', 'e9a745d149950ff4650681538bc7a385ae1f6b9311ac59c109a7198f4b2adc32');

// ── Seguridad interna (compartido con webhook PHP) ───────────
define('AGENT_SECRET', '3RYj2gjSBiusBKlHZBq2btRK77B8dPDYb8pV2SiaHykvvXD4j8v7e2kd1HIGCl9i');

// ── Identificador de cliente WhatsApp ────────────────────────
// Debe coincidir con el client_id que envía la API de WhatsApp en el webhook.
// Se usa para construir conv_key y ses_key en bot_estados.
define('WA_CLIENT_ID', 'intermedia');

// ── URL pública del panel ────────────────────────────────────
define('PANEL_URL', 'https://panelws.intermediahost.co');  // sin slash final

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
define('PANEL_AREAS', [
    ['name' => 'Ventas',         'slug' => 'ventas',  'color' => '#E67E22', 'icon' => 'fa-shopping-cart'],
    ['name' => 'Soporte',        'slug' => 'soporte', 'color' => '#3498DB', 'icon' => 'fa-headset'],
    ['name' => 'Medios de Pago', 'slug' => 'pagos',   'color' => '#27AE60', 'icon' => 'fa-credit-card'],
    ['name' => 'Otros',          'slug' => 'otros',   'color' => '#95A5A6', 'icon' => 'fa-question-circle'],
]);

// ── Cabeceras de seguridad globales ──────────────────────────
if (!defined('SECURITY_HEADERS_SENT')) {
    define('SECURITY_HEADERS_SENT', true);
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: blob: " . UPLOAD_URL . "; connect-src 'self';");
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
