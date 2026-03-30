<?php
/**
 * sections/settings.php — Configuración del panel (solo supervisor).
 */
require_once __DIR__ . '/../auth.php';
requireSupervisor();

$dayNames = [
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado',
    7 => 'Domingo',
];
?>
<div class="section-wrap">

  <div class="section-header">
    <h2 class="section-title"><i class="fas fa-cog"></i> Configuración</h2>
    <button class="btn-primary" onclick="SettingsPanel.save()">
      <i class="fas fa-save"></i> Guardar cambios
    </button>
  </div>

  <!-- ── Horario de atención ──────────────────────────────── -->
  <div style="background:var(--blanco);border-radius:var(--radius-md);box-shadow:var(--shadow-sm);padding:24px;margin-bottom:20px">

    <h3 style="font-size:1rem;font-weight:700;margin-bottom:4px">
      <i class="fas fa-clock" style="color:var(--verde-mid)"></i> Horario de atención
    </h3>
    <p style="font-size:.82rem;color:var(--texto-suave);margin-bottom:20px">
      Define los días y horas en que el bot derivará chats a asesores humanos.
      Fuera de este horario se envía el mensaje de ausencia.
    </p>

    <!-- Forzar horario -->
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;padding:12px 16px;background:#f7f9fc;border-radius:var(--radius-sm);border:1.5px solid var(--borde)">
      <i class="fas fa-toggle-on" style="color:var(--verde-mid);font-size:1.1rem"></i>
      <div style="flex:1">
        <div style="font-weight:600;font-size:.88rem">Forzar estado</div>
        <div style="font-size:.78rem;color:var(--texto-suave)">Anula el horario configurado (útil para festivos o emergencias)</div>
      </div>
      <select id="force_schedule" style="padding:7px 12px;border:1.5px solid var(--borde);border-radius:var(--radius-sm);font-size:.88rem;font-family:inherit;outline:none">
        <option value="auto">Automático (usar horario)</option>
        <option value="open">Siempre ABIERTO</option>
        <option value="closed">Siempre CERRADO</option>
      </select>
    </div>

    <!-- Tabla de días -->
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse">
        <thead>
          <tr style="background:#f7f9fc">
            <th style="padding:10px 14px;text-align:left;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--texto-suave)">Día</th>
            <th style="padding:10px 14px;text-align:center;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--texto-suave)">Activo</th>
            <th style="padding:10px 14px;text-align:center;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--texto-suave)">Apertura</th>
            <th style="padding:10px 14px;text-align:center;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--texto-suave)">Cierre</th>
            <th style="padding:10px 14px;text-align:center;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--texto-suave)">Vista previa</th>
          </tr>
        </thead>
        <tbody id="hours-table">
          <?php for ($d = 1; $d <= 7; $d++): ?>
          <tr id="row-day-<?= $d ?>" style="border-bottom:1px solid var(--borde)">
            <td style="padding:12px 14px;font-weight:600;font-size:.9rem">
              <?= $dayNames[$d] ?>
            </td>
            <td style="padding:12px 14px;text-align:center">
              <label class="toggle-switch">
                <input type="checkbox" id="day-open-<?= $d ?>"
                       onchange="SettingsPanel.toggleDay(<?= $d ?>)">
                <span class="toggle-slider"></span>
              </label>
            </td>
            <td style="padding:12px 14px;text-align:center">
              <input type="time" id="day-start-<?= $d ?>" value="08:00"
                     style="padding:5px 8px;border:1.5px solid var(--borde);border-radius:6px;font-size:.88rem;font-family:inherit;outline:none"
                     onchange="SettingsPanel.updatePreview(<?= $d ?>)">
            </td>
            <td style="padding:12px 14px;text-align:center">
              <input type="time" id="day-end-<?= $d ?>" value="18:00"
                     style="padding:5px 8px;border:1.5px solid var(--borde);border-radius:6px;font-size:.88rem;font-family:inherit;outline:none"
                     onchange="SettingsPanel.updatePreview(<?= $d ?>)">
            </td>
            <td style="padding:12px 14px;text-align:center" id="preview-<?= $d ?>">
              <span style="font-size:.8rem;color:var(--texto-suave)">—</span>
            </td>
          </tr>
          <?php endfor; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Zona horaria ─────────────────────────────────────── -->
  <div style="background:var(--blanco);border-radius:var(--radius-md);box-shadow:var(--shadow-sm);padding:24px;margin-bottom:20px">
    <h3 style="font-size:1rem;font-weight:700;margin-bottom:4px">
      <i class="fas fa-globe" style="color:var(--verde-mid)"></i> Zona horaria
    </h3>
    <p style="font-size:.82rem;color:var(--texto-suave);margin-bottom:16px">
      Zona horaria usada para calcular si la empresa está abierta.
    </p>
    <select id="timezone" style="padding:8px 12px;border:1.5px solid var(--borde);border-radius:var(--radius-sm);font-size:.9rem;font-family:inherit;outline:none;max-width:320px">
      <option value="America/Bogota">América/Bogotá (COT, UTC-5)</option>
      <option value="America/Mexico_City">América/Ciudad de México (CST, UTC-6)</option>
      <option value="America/Lima">América/Lima (PET, UTC-5)</option>
      <option value="America/Caracas">América/Caracas (VET, UTC-4)</option>
      <option value="America/Santiago">América/Santiago (CLT, UTC-3/-4)</option>
      <option value="America/Buenos_Aires">América/Buenos Aires (ART, UTC-3)</option>
      <option value="America/New_York">América/Nueva York (EST, UTC-5/-4)</option>
      <option value="Europe/Madrid">Europa/Madrid (CET, UTC+1/+2)</option>
    </select>
  </div>

  <!-- ── Mensaje fuera de horario personalizado ───────────── -->
  <div style="background:var(--blanco);border-radius:var(--radius-md);box-shadow:var(--shadow-sm);padding:24px;margin-bottom:20px">
    <h3 style="font-size:1rem;font-weight:700;margin-bottom:4px">
      <i class="fas fa-comment-slash" style="color:var(--verde-mid)"></i> Mensaje fuera de horario
    </h3>
    <p style="font-size:.82rem;color:var(--texto-suave);margin-bottom:16px">
      Mensaje personalizado enviado cuando el bot detecta que está fuera de horario.
      Si se deja vacío se usa el mensaje predeterminado del sistema.
    </p>
    <textarea id="out_of_hours_message" rows="5"
              placeholder="Ejemplo: 😴 En este momento no estamos disponibles. Nuestro horario es de Lunes a Viernes 8am - 6pm..."
              style="width:100%;padding:10px 12px;border:1.5px solid var(--borde);border-radius:var(--radius-sm);font-size:.88rem;font-family:inherit;resize:vertical;outline:none;transition:border-color .2s"
              onfocus="this.style.borderColor='var(--verde-wa)'"
              onblur="this.style.borderColor='var(--borde)'"></textarea>
    <p style="font-size:.75rem;color:var(--texto-suave);margin-top:6px">
      Puedes usar *negritas* y saltos de línea igual que en WhatsApp.
    </p>
  </div>

  <!-- Error/éxito -->
  <div id="settings-feedback" style="display:none;padding:12px 16px;border-radius:var(--radius-sm);font-size:.88rem;margin-bottom:16px"></div>

  <!-- Botón guardar inferior -->
  <div style="text-align:right">
    <button class="btn-primary" onclick="SettingsPanel.save()" id="btn-settings-save">
      <i class="fas fa-save"></i> Guardar cambios
    </button>
  </div>

</div>

<!-- Estilos del toggle switch -->
<style>
.toggle-switch {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
}
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
  position: absolute;
  cursor: pointer;
  inset: 0;
  background: #ccc;
  border-radius: 24px;
  transition: .3s;
}
.toggle-slider::before {
  content: '';
  position: absolute;
  width: 18px;
  height: 18px;
  left: 3px;
  bottom: 3px;
  background: white;
  border-radius: 50%;
  transition: .3s;
}
.toggle-switch input:checked + .toggle-slider { background: var(--verde-wa); }
.toggle-switch input:checked + .toggle-slider::before { transform: translateX(20px); }
</style>

<script>
window.SettingsPanel = (() => {
  const DAY_NAMES = {1:'Lunes',2:'Martes',3:'Miércoles',4:'Jueves',5:'Viernes',6:'Sábado',7:'Domingo'};

  async function init() {
    try {
      const res  = await fetch('/api/settings.php', { credentials: 'include' });
      const json = await res.json();
      if (!json.success) return;

      const s = json.settings;

      // Forzar horario
      const forceEl = document.getElementById('force_schedule');
      if (forceEl && s.force_schedule) {
        forceEl.value = s.force_schedule.value || 'auto';
      }

      // Zona horaria
      const tzEl = document.getElementById('timezone');
      if (tzEl && s.timezone) {
        tzEl.value = s.timezone.value || 'America/Bogota';
      }

      // Mensaje fuera de horario
      const msgEl = document.getElementById('out_of_hours_message');
      if (msgEl && s.out_of_hours_message) {
        msgEl.value = s.out_of_hours_message.value || '';
      }

      // Horarios por día
      if (s.business_hours && s.business_hours.parsed) {
        const hours = s.business_hours.parsed;
        for (let d = 1; d <= 7; d++) {
          const day   = hours[d] || {};
          const open  = !!day.open;
          const start = day.start || '08:00';
          const end   = day.end   || '18:00';

          const chk = document.getElementById('day-open-'  + d);
          const sta = document.getElementById('day-start-' + d);
          const en  = document.getElementById('day-end-'   + d);

          if (chk) chk.checked = open;
          if (sta) sta.value   = start;
          if (en)  en.value    = end;

          _applyDayState(d, open);
          updatePreview(d);
        }
      }
    } catch(e) {
      console.error('[Settings]', e);
    }
  }

  function toggleDay(d) {
    const chk  = document.getElementById('day-open-' + d);
    const open = chk ? chk.checked : false;
    _applyDayState(d, open);
    updatePreview(d);
  }

  function _applyDayState(d, open) {
    const sta = document.getElementById('day-start-' + d);
    const en  = document.getElementById('day-end-'   + d);
    if (sta) sta.disabled = !open;
    if (en)  en.disabled  = !open;

    const row = document.getElementById('row-day-' + d);
    if (row) row.style.opacity = open ? '1' : '0.45';
  }

  function updatePreview(d) {
    const chk   = document.getElementById('day-open-'  + d);
    const sta   = document.getElementById('day-start-' + d);
    const en    = document.getElementById('day-end-'   + d);
    const prev  = document.getElementById('preview-'   + d);
    if (!prev) return;

    const open = chk && chk.checked;
    if (!open) {
      prev.innerHTML = '<span style="color:var(--resolved);font-size:.8rem"><i class="fas fa-times-circle"></i> Cerrado</span>';
    } else {
      const s = sta ? sta.value : '08:00';
      const e = en  ? en.value  : '18:00';
      prev.innerHTML = `<span style="color:var(--verde-mid);font-size:.8rem"><i class="fas fa-check-circle"></i> ${s} – ${e}</span>`;
    }
  }

  async function save() {
    const btn = document.getElementById('btn-settings-save');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...'; }

    _hideFeedback();

    // Recoger horarios
    const hours = {};
    for (let d = 1; d <= 7; d++) {
      const chk = document.getElementById('day-open-'  + d);
      const sta = document.getElementById('day-start-' + d);
      const en  = document.getElementById('day-end-'   + d);
      hours[d]  = {
        open:  chk ? chk.checked : false,
        start: sta ? sta.value   : '08:00',
        end:   en  ? en.value    : '18:00',
      };
    }

    const payload = {
      business_hours:       hours,
      force_schedule:       document.getElementById('force_schedule')?.value       || 'auto',
      timezone:             document.getElementById('timezone')?.value             || 'America/Bogota',
      out_of_hours_message: document.getElementById('out_of_hours_message')?.value || '',
    };

    try {
      const res  = await fetch('/api/settings.php', {
        method:      'POST',
        credentials: 'include',
        headers:     { 'Content-Type': 'application/json' },
        body:        JSON.stringify(payload),
      });
      const json = await res.json();

      if (json.success) {
        _showFeedback('✅ Configuración guardada correctamente. El webhook usará los nuevos horarios de inmediato.', 'success');
        Notify.showToast('Configuración guardada.', 'success');
      } else {
        _showFeedback('❌ Error al guardar: ' + (json.error || 'desconocido'), 'error');
      }
    } catch(e) {
      _showFeedback('❌ Error de red.', 'error');
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Guardar cambios'; }
    }
  }

  function _showFeedback(msg, type) {
    const el = document.getElementById('settings-feedback');
    if (!el) return;
    el.textContent = msg;
    el.style.display = 'block';
    el.style.background = type === 'success' ? '#d1fae5' : '#fee2e2';
    el.style.color      = type === 'success' ? '#065f46' : '#c0392b';
    el.style.border     = '1px solid ' + (type === 'success' ? '#6ee7b7' : '#fca5a5');
    setTimeout(() => el.style.display = 'none', 5000);
  }

  function _hideFeedback() {
    const el = document.getElementById('settings-feedback');
    if (el) el.style.display = 'none';
  }

  init();

  return { toggleDay, updatePreview, save };
})();
</script>
