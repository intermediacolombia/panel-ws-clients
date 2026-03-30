<?php
/**
 * sections/stats.php — Panel de estadísticas (solo supervisor).
 * Cargado dinámicamente por app.js vía fetch.
 */
require_once __DIR__ . '/../auth.php';
requireSupervisor();
?>
<div class="section-wrap">

  <div class="section-header">
    <h2 class="section-title"><i class="fas fa-chart-bar"></i> Estadísticas</h2>
  </div>

  <!-- Selector de período -->
  <div class="stats-period-bar">
    <button class="period-btn active" data-period="today"  onclick="Stats.load('today',  this)">Hoy</button>
    <button class="period-btn"        data-period="week"   onclick="Stats.load('week',   this)">Esta semana</button>
    <button class="period-btn"        data-period="month"  onclick="Stats.load('month',  this)">Este mes</button>
  </div>

  <!-- Cards principales -->
  <div class="stats-grid" id="stats-grid">
    <div class="stat-card">
      <div class="stat-card-label">Total conversaciones</div>
      <div class="stat-card-value" id="s-total">—</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-label">Pendientes</div>
      <div class="stat-card-value pending" id="s-pending">—</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-label">En atención</div>
      <div class="stat-card-value attending" id="s-attending">—</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-label">Resueltos</div>
      <div class="stat-card-value" id="s-resolved">—</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-label">Tiempo prom. atención</div>
      <div class="stat-card-value" id="s-avgtime">—</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-label">Agentes en línea</div>
      <div class="stat-card-value online" id="s-online">—</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-label">Mensajes enviados</div>
      <div class="stat-card-value" id="s-sent">—</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-label">Mensajes recibidos</div>
      <div class="stat-card-value" id="s-recv">—</div>
    </div>
  </div>

  <!-- Gráfico por hora (CSS puro) -->
  <div class="hourly-chart">
    <h4><i class="fas fa-clock"></i> Conversaciones por hora</h4>
    <div class="chart-bars" id="chart-bars">
      <?php for ($h = 0; $h < 24; $h++): ?>
        <div class="chart-bar-wrap" title="<?= $h ?>:00">
          <div class="chart-bar" id="bar-<?= $h ?>" style="height:2px"></div>
          <div class="chart-bar-label"><?= $h ?></div>
        </div>
      <?php endfor; ?>
    </div>
  </div>

  <!-- Tabla de agentes -->
  <div class="data-table-wrap" id="agents-table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Agente</th>
          <th>Estado</th>
          <th>Asignadas</th>
          <th>Resueltas</th>
          <th>Tiempo prom.</th>
        </tr>
      </thead>
      <tbody id="agents-tbody">
        <tr><td colspan="5" class="text-muted text-center">Cargando...</td></tr>
      </tbody>
    </table>
  </div>

</div>

<script>
window.Stats = (() => {
  async function load(period, btn) {
    // Cambiar botón activo
    document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    try {
      const res  = await fetch('/api/stats.php?period=' + period, {
        credentials: 'include',
        headers: { 'Accept': 'application/json' },
      });
      const json = await res.json();
      if (!json.success) return;
      const s = json.stats;

      // Cards
      document.getElementById('s-total').textContent    = s.total    ?? '0';
      document.getElementById('s-pending').textContent  = s.pending  ?? '0';
      document.getElementById('s-attending').textContent= s.attending?? '0';
      document.getElementById('s-resolved').textContent = s.resolved ?? '0';
      document.getElementById('s-sent').textContent     = s.sent     ?? '0';
      document.getElementById('s-recv').textContent     = s.received ?? '0';
      document.getElementById('s-online').textContent   = s.online   ?? '0';
      document.getElementById('s-avgtime').textContent  =
        s.avg_minutes != null ? s.avg_minutes + ' min' : 'N/D';

      // Gráfico de barras
      const hourly = s.hourly || [];
      const maxVal = Math.max(...hourly, 1);
      for (let h = 0; h < 24; h++) {
        const bar = document.getElementById('bar-' + h);
        if (bar) {
          const pct = Math.round((hourly[h] / maxVal) * 100);
          bar.style.height = Math.max(2, pct) + '%';
          bar.title = h + ':00 — ' + (hourly[h] || 0) + ' conv.';
        }
      }

      // Tabla de agentes
      const tbody = document.getElementById('agents-tbody');
      if (tbody && s.agents && s.agents.length) {
        tbody.innerHTML = s.agents.map(a => `
          <tr>
            <td>
              <span class="online-dot ${a.online ? 'online' : 'offline'}"></span>
              ${escHtml(a.name)}
            </td>
            <td>${a.online ? '<span style="color:var(--verde-wa);font-weight:600">En línea</span>' : '<span style="color:var(--texto-suave)">Desconectado</span>'}</td>
            <td>${a.assigned ?? 0}</td>
            <td>${a.resolved ?? 0}</td>
            <td>${a.avg_minutes != null ? a.avg_minutes + ' min' : 'N/D'}</td>
          </tr>`).join('');
      } else if (tbody) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center">Sin datos.</td></tr>';
      }

    } catch (e) {
      console.error('[Stats]', e);
    }
  }

  function escHtml(s) {
    return String(s)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  return { load };
})();

// Cargar al iniciar
Stats.load('today', null);
</script>
