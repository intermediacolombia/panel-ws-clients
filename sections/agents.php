<?php
/**
 * sections/agents.php — Gestión de agentes (solo supervisor).
 */
require_once __DIR__ . '/../auth.php';
requireSupervisor();
?>
<div class="section-wrap">

  <div class="section-header">
    <h2 class="section-title"><i class="fas fa-users"></i> Agentes</h2>
    <button class="btn-primary" onclick="AgentsPanel.openCreate()">
      <i class="fas fa-plus"></i> Nuevo Agente
    </button>
  </div>

  <!-- Tabla -->
  <div class="data-table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Usuario</th>
          <th>Email</th>
          <th>Rol</th>
          <th>Departamentos</th>
          <th>Estado</th>
          <th>Último acceso</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="agents-tbody">
        <tr><td colspan="8" class="text-muted text-center" style="padding:20px">Cargando...</td></tr>
      </tbody>
    </table>
  </div>

</div>

<!-- Modal crear/editar agente -->
<div class="modal-overlay" id="modal-agent">
  <div class="modal-box">
    <div class="modal-header">
      <span class="modal-title" id="modal-agent-title">Nuevo Agente</span>
      <button class="modal-close" onclick="App.closeModal('modal-agent')">&times;</button>
    </div>

    <form id="form-agent" onsubmit="AgentsPanel.save(event)">
      <input type="hidden" id="agent-edit-id" value="">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-row">
          <label>Nombre completo *</label>
          <input type="text" id="agent-name" required placeholder="Juan Pérez">
        </div>
        <div class="form-row">
          <label>Usuario *</label>
          <input type="text" id="agent-username" required placeholder="juanperez" autocomplete="off">
        </div>
        <div class="form-row">
          <label>Email *</label>
          <input type="email" id="agent-email" required placeholder="juan@empresa.co">
        </div>
        <div class="form-row">
          <label>Contraseña <span id="pw-hint" style="font-weight:400;text-transform:none">(requerida)</span></label>
          <input type="password" id="agent-password" placeholder="Mínimo 6 caracteres" autocomplete="new-password">
        </div>
        <div class="form-row">
          <label>Rol *</label>
          <select id="agent-role">
            <option value="agente">Agente</option>
            <option value="supervisor">Supervisor</option>
          </select>
        </div>
        <div class="form-row">
          <label>Estado</label>
          <select id="agent-status">
            <option value="active">Activo</option>
            <option value="inactive">Inactivo</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <label>Departamentos</label>
        <div class="dept-checks" id="dept-checks-agent">
          <!-- Generado por JS -->
        </div>
      </div>

      <div class="form-error hidden" id="agent-form-error">
        <i class="fas fa-exclamation-circle"></i> <span></span>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="App.closeModal('modal-agent')">Cancelar</button>
        <button type="submit" class="btn-primary" id="btn-agent-save">
          <i class="fas fa-save"></i> Guardar
        </button>
      </div>
    </form>
  </div>
</div>

<script>
window.AgentsPanel = (() => {
  let _depts = [];
  let _agents = [];

  async function init() {
    await _loadDepts();
    await load();
  }

  async function _loadDepts() {
    try {
      const res  = await fetch('/api/departments.php', { credentials: 'include' });
      const json = await res.json();
      _depts = json.departments || [];
    } catch (_) {}
  }

  async function load() {
    try {
      const res  = await fetch('/api/agents.php', { credentials: 'include' });
      const json = await res.json();
      if (!json.success) return;
      _agents = json.agents || [];
      _render(_agents);
    } catch (_) {}
  }

  function _render(agents) {
    const tbody = document.getElementById('agents-tbody');
    if (!tbody) return;

    if (!agents.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-muted text-center">Sin agentes.</td></tr>';
      return;
    }

    tbody.innerHTML = agents.map(a => {
      const deptChips = (a.departments || []).map(d =>
        `<span class="dept-chip" style="background:${d.color||'#ccc'}">${esc(d.dept_name)}</span>`
      ).join('');

      const lastSeen = a.last_seen
        ? new Date(a.last_seen.replace(' ','T')).toLocaleString('es-CO')
        : 'Nunca';

      return `<tr>
        <td>
          <span class="online-dot ${a.online ? 'online' : 'offline'}"></span>
          <strong>${esc(a.name)}</strong>
        </td>
        <td>${esc(a.username)}</td>
        <td>${esc(a.email)}</td>
        <td>
          <span style="text-transform:capitalize;font-weight:500;color:${a.role==='supervisor'?'var(--verde-mid)':'inherit'}">${esc(a.role)}</span>
        </td>
        <td>${deptChips || '<span class="text-muted">—</span>'}</td>
        <td>
          <span style="color:${a.status==='active'?'var(--verde-wa)':'var(--resolved)'}">
            ${a.status === 'active' ? 'Activo' : 'Inactivo'}
          </span>
        </td>
        <td style="font-size:.8rem;color:var(--texto-suave)">${lastSeen}</td>
        <td>
          <div style="display:flex;gap:4px">
            <button class="btn-icon" onclick="AgentsPanel.openEdit(${a.id})" title="Editar">
              <i class="fas fa-edit"></i>
            </button>
            <button class="btn-icon" onclick="AgentsPanel.toggle(${a.id},'${a.status}')" title="${a.status==='active'?'Desactivar':'Activar'}">
              <i class="fas fa-${a.status==='active'?'ban':'check'}"></i>
            </button>
          </div>
        </td>
      </tr>`;
    }).join('');
  }

  function _buildDeptChecks(selectedIds) {
    const el = document.getElementById('dept-checks-agent');
    if (!el) return;
    el.innerHTML = _depts.map(d => `
      <label class="dept-check-label">
        <input type="checkbox" name="dept_ids" value="${d.id}"
               ${(selectedIds||[]).includes(d.id) ? 'checked' : ''}>
        ${esc(d.name)}
      </label>`).join('');
  }

  function openCreate() {
    document.getElementById('modal-agent-title').textContent = 'Nuevo Agente';
    document.getElementById('agent-edit-id').value = '';
    document.getElementById('agent-name').value = '';
    document.getElementById('agent-username').value = '';
    document.getElementById('agent-email').value = '';
    document.getElementById('agent-password').value = '';
    document.getElementById('agent-role').value = 'agente';
    document.getElementById('agent-status').value = 'active';
    document.getElementById('pw-hint').textContent = '(requerida)';
    const pwField = document.getElementById('agent-password');
    pwField.required = true;
    _buildDeptChecks([]);
    _clearError();
    App.openModal('modal-agent');
  }

  function openEdit(id) {
    const agent = _agents.find(a => a.id === id);
    if (!agent) return;

    document.getElementById('modal-agent-title').textContent = 'Editar Agente';
    document.getElementById('agent-edit-id').value = id;
    document.getElementById('agent-name').value = agent.name;
    document.getElementById('agent-username').value = agent.username;
    document.getElementById('agent-email').value = agent.email;
    document.getElementById('agent-password').value = '';
    document.getElementById('agent-role').value = agent.role;
    document.getElementById('agent-status').value = agent.status;
    document.getElementById('pw-hint').textContent = '(dejar vacío para no cambiar)';
    document.getElementById('agent-password').required = false;

    const selectedDeptIds = (agent.departments || []).map(d => parseInt(d.dept_id));
    _buildDeptChecks(selectedDeptIds);
    _clearError();
    App.openModal('modal-agent');
  }

  async function save(e) {
    e.preventDefault();
    _clearError();

    const editId   = document.getElementById('agent-edit-id').value;
    const deptChecks = document.querySelectorAll('#dept-checks-agent input[name="dept_ids"]:checked');
    const deptIds    = Array.from(deptChecks).map(cb => parseInt(cb.value));

    const payload = {
      name:     document.getElementById('agent-name').value.trim(),
      username: document.getElementById('agent-username').value.trim(),
      email:    document.getElementById('agent-email').value.trim(),
      password: document.getElementById('agent-password').value,
      role:     document.getElementById('agent-role').value,
      status:   document.getElementById('agent-status').value,
      dept_ids: deptIds,
    };

    const isEdit = !!editId;
    if (isEdit) { payload.id = parseInt(editId); delete payload.username; }

    const btn = document.getElementById('btn-agent-save');
    btn.disabled = true;

    try {
      const res  = await fetch('/api/agents.php', {
        method:      isEdit ? 'PUT' : 'POST',
        credentials: 'include',
        headers:     { 'Content-Type': 'application/json' },
        body:        JSON.stringify(payload),
      });
      const json = await res.json();

      if (json.success) {
        App.closeModal('modal-agent');
        Notify.showToast('Agente guardado correctamente.', 'success');
        await load();
      } else {
        _showError(json.error || 'Error al guardar.');
      }
    } catch (_) {
      _showError('Error de red.');
    } finally {
      btn.disabled = false;
    }
  }

  async function toggle(id, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    const msg = newStatus === 'inactive' ? '¿Desactivar este agente?' : '¿Activar este agente?';
    if (!confirm(msg)) return;

    const agent = _agents.find(a => a.id === id);
    if (!agent) return;

    const deptIds = (agent.departments || []).map(d => parseInt(d.dept_id));

    try {
      const res  = await fetch('/api/agents.php', {
        method:      'PUT',
        credentials: 'include',
        headers:     { 'Content-Type': 'application/json' },
        body:        JSON.stringify({
          id:       id,
          name:     agent.name,
          email:    agent.email,
          role:     agent.role,
          status:   newStatus,
          dept_ids: deptIds,
        }),
      });
      const json = await res.json();
      if (json.success) {
        Notify.showToast('Estado actualizado.', 'success');
        await load();
      } else {
        Notify.showToast(json.error || 'Error.', 'error');
      }
    } catch (_) {
      Notify.showToast('Error de red.', 'error');
    }
  }

  function _showError(msg) {
    const el  = document.getElementById('agent-form-error');
    const span = el.querySelector('span');
    if (!el || !span) return;
    span.textContent = msg;
    el.classList.remove('hidden');
  }

  function _clearError() {
    const el = document.getElementById('agent-form-error');
    if (el) el.classList.add('hidden');
  }

  function esc(s) {
    return String(s)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  init();

  return { load, openCreate, openEdit, save, toggle };
})();
</script>
