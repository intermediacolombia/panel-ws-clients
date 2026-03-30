<?php
/**
 * index.php — Shell principal del Panel de Agentes.
 * Protegido por auth.php. Todas las secciones se cargan desde aquí.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

// CSRF token para JS
$csrfToken = '';
if (empty($_SESSION['csrf_panel'])) {
    $_SESSION['csrf_panel'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_panel'];

$isSupervisor = $currentAgent['role'] === 'supervisor';
$agentInitial = mb_strtoupper(mb_substr($currentAgent['name'], 0, 1, 'UTF-8'), 'UTF-8');
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel de Agentes — InterMedia Host</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/panel.css">
  <!-- Aplicar tema antes de renderizar para evitar flash -->
  <script>(function(){var t=localStorage.getItem('panel_theme');if(t==='dark')document.documentElement.setAttribute('data-theme','dark');})()</script>
</head>
<body>

<!-- Configuración global para JS -->
<script>
window.PANEL_CONFIG = {
  agentId:    <?= (int)$currentAgent['id'] ?>,
  agentName:  <?= json_encode($currentAgent['name'], JSON_UNESCAPED_UNICODE) ?>,
  agentRole:  <?= json_encode($currentAgent['role']) ?>,
  agentToken: <?= json_encode($currentAgent['token']) ?>,
  deptIds:    <?= json_encode($currentAgent['dept_ids']) ?>,
  deptNames:  <?= json_encode($currentAgent['dept_names']) ?>,
  csrfToken:  <?= json_encode($csrfToken) ?>,
  panelUrl:   <?= json_encode(PANEL_URL) ?>,
};
</script>

<div class="app-shell">

  <!-- ══ SIDEBAR ════════════════════════════════════════════════ -->
  <aside class="sidebar">

    <!-- Avatar del agente -->
    <div class="sidebar-avatar" title="<?= sanitize($currentAgent['name']) ?>">
      <?= sanitize($agentInitial) ?>
    </div>

    <!-- Navegación -->
    <nav class="sidebar-nav">

      <!-- Conversaciones (siempre visible) -->
      <div class="sidebar-item active" data-section="conversations"
           onclick="App.navigate('conversations')">
        <i class="fas fa-comments"></i>
        <span>Chats</span>
        <span class="sidebar-badge hidden" id="sidebar-conv-badge">0</span>
        <span class="sidebar-tooltip">Conversaciones</span>
      </div>

      <?php if ($isSupervisor): ?>
      <!-- Estadísticas -->
      <div class="sidebar-item" data-section="stats"
           onclick="App.navigate('stats')">
        <i class="fas fa-chart-bar"></i>
        <span>Stats</span>
        <span class="sidebar-tooltip">Estadísticas</span>
      </div>

      <!-- Agentes -->
      <div class="sidebar-item" data-section="agents"
           onclick="App.navigate('agents')">
        <i class="fas fa-users"></i>
        <span>Agentes</span>
        <span class="sidebar-tooltip">Gestión de agentes</span>
      </div>

      <!-- Configuración -->
      <div class="sidebar-item" data-section="settings"
           onclick="App.navigate('settings')">
        <i class="fas fa-cog"></i>
        <span>Config</span>
        <span class="sidebar-tooltip">Configuración</span>
      </div>
      <?php endif; ?>

    </nav>

    <!-- Notificaciones -->
    <div class="sidebar-item" onclick="App.toggleNotifDropdown()" id="notif-btn" style="position:relative">
      <i class="fas fa-bell"></i>
      <span>Notif</span>
      <span class="sidebar-badge hidden" id="notif-badge">0</span>
      <span class="sidebar-tooltip">Notificaciones</span>
    </div>

    <!-- Toggle tema -->
    <button class="btn-theme" id="btn-theme" onclick="Theme.toggle()" title="Cambiar tema">
      <i class="fas fa-moon" id="theme-icon"></i>
    </button>

    <!-- Logout -->
    <div class="sidebar-logout">
      <a href="logout.php" title="Cerrar sesión" onclick="return confirm('¿Cerrar sesión?')">
        <i class="fas fa-sign-out-alt"></i>
        <span>Salir</span>
      </a>
    </div>

  </aside>

  <!-- ══ ÁREA PRINCIPAL ═════════════════════════════════════════ -->
  <div class="main-area" id="main-area">

    <!-- Columna izquierda: lista de conversaciones -->
    <?php include __DIR__ . '/sections/conversations.php'; ?>

    <!-- Columna derecha: chat activo — SIEMPRE en el DOM, nunca se borra -->
    <?php include __DIR__ . '/sections/chat.php'; ?>

    <!-- Secciones de supervisor (stats/agentes/depts/config) — se cargan dinámicamente aquí -->
    <div id="supervisor-section" style="display:none;flex:1;flex-direction:column;overflow:hidden"></div>

  </div>

</div><!-- /app-shell -->

<!-- ── Dropdown de notificaciones ─────────────────────────── -->
<div class="notif-dropdown" id="notif-dropdown">
  <div class="notif-header">
    <span>Notificaciones</span>
    <button style="background:none;border:none;font-size:.78rem;color:var(--verde-mid);cursor:pointer"
            onclick="App.markAllNotifsRead()">
      Marcar todas leídas
    </button>
  </div>
  <div id="notif-list">
    <div class="notif-item text-muted text-center" style="padding:20px">Cargando...</div>
  </div>
</div>

<!-- ── Contenedor de Toasts ───────────────────────────────── -->
<div class="toast-container" id="toast-container"></div>

<!-- ── Scripts ────────────────────────────────────────────── -->
<script>
const Theme = (() => {
  function _apply(dark) {
    if (dark) {
      document.documentElement.setAttribute('data-theme', 'dark');
    } else {
      document.documentElement.removeAttribute('data-theme');
    }
    const icon = document.getElementById('theme-icon');
    if (icon) {
      icon.className = dark ? 'fas fa-sun' : 'fas fa-moon';
    }
    localStorage.setItem('panel_theme', dark ? 'dark' : 'light');
  }

  function toggle() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    _apply(!isDark);
  }

  function init() {
    const saved = localStorage.getItem('panel_theme');
    _apply(saved === 'dark');
  }

  return { toggle, init };
})();

Theme.init();
</script>
<script src="assets/js/notify.js"></script>
<script src="assets/js/realtime.js"></script>
<script src="assets/js/chat.js"></script>
<script src="assets/js/app.js"></script>

</body>
</html>
