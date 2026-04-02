<?php
/**
 * sections/departments.php — Gestión de departamentos (solo supervisor).
 */
require_once __DIR__ . '/../auth.php';
requireSupervisor();
?>
<div class="section-wrap">

  <div class="section-header">
    <h2 class="section-title"><i class="fas fa-building"></i> Departamentos</h2>
    <button class="btn-primary" onclick="DeptsPanel.openCreate()">
      <i class="fas fa-plus"></i> Nuevo Departamento
    </button>
  </div>

  <!-- Grid de tarjetas -->
  <div class="dept-grid" id="dept-grid">
    <div class="text-muted">Cargando...</div>
  </div>

</div>

<!-- Modal crear/editar departamento -->
<div class="modal-overlay" id="modal-dept">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-header">
      <span class="modal-title" id="modal-dept-title">Nuevo Departamento</span>
      <button class="modal-close" onclick="App.closeModal('modal-dept')">&times;</button>
    </div>

    <form id="form-dept" onsubmit="DeptsPanel.save(event)">
      <input type="hidden" id="dept-edit-id" value="">

      <div class="form-row">
        <label>Nombre *</label>
        <input type="text" id="dept-name" required placeholder="Ej: Ventas">
      </div>
      <div class="form-row" id="slug-row">
        <label>Slug * <small style="font-weight:400;text-transform:none">(identificador único, solo letras y guiones)</small></label>
        <input type="text" id="dept-slug" required placeholder="ventas" pattern="[a-z0-9_-]+">
      </div>
      <div class="form-row">
        <label>Descripción</label>
        <textarea id="dept-description" rows="2" placeholder="Descripción opcional..."></textarea>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-row">
          <label>Color</label>
          <input type="color" id="dept-color" value="#25D366" style="height:38px">
        </div>
        <div class="form-row">
          <label>Ícono (Font Awesome)</label>
          <input type="text" id="dept-icon" placeholder="headset" value="headset">
        </div>
      </div>

      <div class="form-error hidden" id="dept-form-error">
        <i class="fas fa-exclamation-circle"></i> <span></span>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="App.closeModal('modal-dept')">Cancelar</button>
        <button type="submit" class="btn-primary" id="btn-dept-save">
          <i class="fas fa-save"></i> Guardar
        </button>
      </div>
    </form>
  </div>
</div>

<script>
window.DeptsPanel = (() => {
  let _depts = [];

  async function load() {
    try {
      const res  = await fetch('/api/departments.php', { credentials: 'include' });
      const json = await res.json();
      if (!json.success) return;
      _depts = json.departments || [];
      _render(_depts);
    } catch (_) {}
  }

  function _render(depts) {
    const grid = document.getElementById('dept-grid');
    if (!grid) return;

    if (!depts.length) {
      grid.innerHTML = '<div class="text-muted">No hay departamentos.</div>';
      return;
    }

    grid.innerHTML = depts.map(d => `
      <div class="dept-card" style="border-left-color:${d.color}">
        <div class="dept-card-header">
          <div class="dept-icon-circle" style="background:${d.color}">
            <i class="fas fa-${esc(d.icon)}"></i>
          </div>
          <div>
            <div class="dept-card-name">${esc(d.name)}</div>
            <div style="font-size:.72rem;color:var(--texto-suave)">${esc(d.slug)}</div>
          </div>
          ${!d.active ? '<span style="font-size:.72rem;color:var(--resolved);margin-left:auto">Inactivo</span>' : ''}
        </div>
        <div class="dept-card-desc">${esc(d.description || '—')}</div>
        <div class="dept-card-stats">
          <span><i class="fas fa-users"></i> ${d.agent_count} agente${d.agent_count!==1?'s':''}</span>
          <span><i class="fas fa-comments"></i> ${d.active_convs} activa${d.active_convs!==1?'s':''}</span>
        </div>
        <div class="dept-card-actions">
          <button class="btn-secondary" style="font-size:.78rem;padding:4px 10px"
                  onclick="DeptsPanel.openEdit(${d.id})">
            <i class="fas fa-edit"></i> Editar
          </button>
          <button class="btn-secondary" style="font-size:.78rem;padding:4px 10px"
                  onclick="DeptsPanel.toggleActive(${d.id}, ${d.active})">
            <i class="fas fa-${d.active?'toggle-on':'toggle-off'}"></i>
            ${d.active ? 'Desactivar' : 'Activar'}
          </button>
          <button class="btn-danger" style="font-size:.78rem;padding:4px 10px"
                  onclick="DeptsPanel.remove(${d.id})">
            <i class="fas fa-trash"></i>
          </button>
        </div>
      </div>`).join('');
  }

  function openCreate() {
    document.getElementById('modal-dept-title').textContent = 'Nuevo Departamento';
    document.getElementById('dept-edit-id').value = '';
    document.getElementById('dept-name').value = '';
    document.getElementById('dept-slug').value = '';
    document.getElementById('dept-description').value = '';
    document.getElementById('dept-color').value = '#25D366';
    document.getElementById('dept-icon').value = 'headset';
    const slugRow = document.getElementById('slug-row');
    if (slugRow) slugRow.style.display = '';
    document.getElementById('dept-slug').disabled = false;
    _clearError();
    App.openModal('modal-dept');
  }

  function openEdit(id) {
    const d = _depts.find(x => x.id === id);
    if (!d) return;
    document.getElementById('modal-dept-title').textContent = 'Editar Departamento';
    document.getElementById('dept-edit-id').value = id;
    document.getElementById('dept-name').value = d.name;
    document.getElementById('dept-slug').value = d.slug;
    document.getElementById('dept-description').value = d.description || '';
    document.getElementById('dept-color').value = d.color || '#25D366';
    document.getElementById('dept-icon').value = d.icon || 'headset';
    // No permitir editar el slug
    const slugRow = document.getElementById('slug-row');
    if (slugRow) slugRow.style.display = 'none';
    _clearError();
    App.openModal('modal-dept');
  }

  async function save(e) {
    e.preventDefault();
    _clearError();

    const editId = document.getElementById('dept-edit-id').value;
    const isEdit = !!editId;

    const payload = {
      name:        document.getElementById('dept-name').value.trim(),
      slug:        document.getElementById('dept-slug').value.trim(),
      description: document.getElementById('dept-description').value.trim(),
      color:       document.getElementById('dept-color').value,
      icon:        document.getElementById('dept-icon').value.trim() || 'headset',
    };

    if (isEdit) { payload.id = parseInt(editId); payload.active = 1; }

    const btn = document.getElementById('btn-dept-save');
    btn.disabled = true;

    try {
      const res  = await fetch('/api/departments.php', {
        method:      isEdit ? 'PUT' : 'POST',
        credentials: 'include',
        headers:     { 'Content-Type': 'application/json' },
        body:        JSON.stringify(payload),
      });
      const json = await res.json();
      if (json.success) {
        App.closeModal('modal-dept');
        Notify.showToast('Departamento guardado.', 'success');
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

  async function toggleActive(id, currentActive) {
    const d = _depts.find(x => x.id === id);
    if (!d) return;
    try {
      const res  = await fetch('/api/departments.php', {
        method:      'PUT',
        credentials: 'include',
        headers:     { 'Content-Type': 'application/json' },
        body:        JSON.stringify({
          id:     id,
          name:   d.name,
          color:  d.color,
          icon:   d.icon,
          active: currentActive ? 0 : 1,
        }),
      });
      const json = await res.json();
      if (json.success) { await load(); }
      else Notify.showToast(json.error || 'Error.', 'error');
    } catch (_) { Notify.showToast('Error de red.', 'error'); }
  }

  async function remove(id) {
    const confirmed = await ConfirmModal.show({
      title:        'Eliminar departamento',
      message:      'Las conversaciones asignadas a este departamento quedarán sin área. Esta acción no se puede deshacer.',
      icon:         'danger',
      confirmText:  'Eliminar',
      confirmClass: 'btn-danger',
    });
    if (!confirmed) return;
    try {
      const res  = await fetch('/api/departments.php', {
        method:      'DELETE',
        credentials: 'include',
        headers:     { 'Content-Type': 'application/json' },
        body:        JSON.stringify({ id }),
      });
      const json = await res.json();
      if (json.success) {
        Notify.showToast('Departamento eliminado.', 'success');
        await load();
      } else {
        Notify.showToast(json.error || 'No se pudo eliminar.', 'error');
      }
    } catch (_) { Notify.showToast('Error de red.', 'error'); }
  }

  function _showError(msg) {
    const el   = document.getElementById('dept-form-error');
    const span = el?.querySelector('span');
    if (span) { span.textContent = msg; el.classList.remove('hidden'); }
  }

  function _clearError() {
    const el = document.getElementById('dept-form-error');
    if (el) el.classList.add('hidden');
  }

  function esc(s) {
    return String(s)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  load();

  return { load, openCreate, openEdit, save, toggleActive, remove };
})();
</script>
