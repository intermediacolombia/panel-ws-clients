<?php
/**
 * sections/contacts.php — Contactos Bot
 * Visible para todos los agentes.
 */
require_once __DIR__ . '/../auth.php';
?>
<div class="section-wrap">

  <div class="section-header">
    <div>
      <h2 class="section-title"><i class="fas fa-address-book"></i> Contactos Bot</h2>
      <p class="section-subtitle" style="margin:2px 0 0;font-size:.82rem;color:var(--texto-suave)">
        Contactos que solo han interactuado con el bot
      </p>
    </div>
    <button class="btn-icon" onclick="BotContacts.load()" title="Actualizar">
      <i class="fas fa-sync-alt"></i>
    </button>
  </div>

  <!-- Búsqueda -->
  <div style="padding:10px 14px 0">
    <div class="conv-search-wrap" style="max-width:360px">
      <i class="fas fa-search conv-search-icon"></i>
      <input id="bc-search" type="text" class="conv-search" placeholder="Buscar por nombre o teléfono…"
             oninput="BotContacts.filter(this.value)" autocomplete="off">
    </div>
  </div>

  <!-- Lista -->
  <div id="bc-list" style="flex:1;overflow-y:auto;padding:10px 14px 14px">
    <div id="bc-loading" style="display:flex;align-items:center;gap:10px;padding:30px 0;color:var(--texto-suave)">
      <div class="spinner" style="width:20px;height:20px;border-color:var(--verde-mid);border-top-color:transparent"></div>
      Cargando contactos…
    </div>
    <div id="bc-empty" style="display:none;text-align:center;padding:40px 0;color:var(--texto-suave)">
      <i class="fas fa-address-book" style="font-size:2rem;opacity:.35;display:block;margin-bottom:8px"></i>
      Sin contactos bot en este momento.
    </div>
    <div id="bc-items"></div>
  </div>

</div>

<script>
window.BotContacts = (() => {
  let _all = [];
  let _refreshTimer = null;

  async function load() {
    const loading = document.getElementById('bc-loading');
    const empty   = document.getElementById('bc-empty');
    const items   = document.getElementById('bc-items');
    if (loading) loading.style.display = 'flex';
    if (empty)   empty.style.display   = 'none';
    if (items)   items.innerHTML        = '';

    try {
      const res  = await fetch('/api/bot_contacts.php?limit=200', { credentials: 'include' });
      const json = await res.json();
      if (loading) loading.style.display = 'none';
      if (!json.success) return;

      _all = json.contacts || [];
      _render(_all);
    } catch (e) {
      if (loading) loading.style.display = 'none';
      console.error('[BotContacts]', e);
    }
  }

  function filter(q) {
    if (!q.trim()) { _render(_all); return; }
    const lq = q.toLowerCase();
    _render(_all.filter(c =>
      c.name.toLowerCase().includes(lq) || c.phone.toLowerCase().includes(lq)
    ));
  }

  function _render(contacts) {
    const empty = document.getElementById('bc-empty');
    const items = document.getElementById('bc-items');
    if (!items) return;

    if (!contacts.length) {
      items.innerHTML = '';
      if (empty) empty.style.display = 'block';
      return;
    }
    if (empty) empty.style.display = 'none';

    items.innerHTML = contacts.map(c => {
      const initials = _initials(c.name || c.phone);
      const display  = _esc(c.name || c.phone);
      const phone    = _esc(c.phone);
      const date     = _fmtDate(c.updated_at);
      return `<div class="conv-item" style="cursor:default">
        <div class="conv-avatar" style="background:var(--verde-mid);color:#fff;font-weight:700;text-transform:uppercase;flex-shrink:0">
          ${_esc(initials)}
        </div>
        <div class="conv-info" style="flex:1;min-width:0">
          <div class="conv-name" style="font-weight:600">${display}</div>
          <div class="conv-preview" style="color:var(--texto-suave);font-size:.8rem">${phone}</div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0">
          <span style="font-size:.72rem;color:var(--texto-suave)">${date}</span>
          <button class="btn-sm" style="font-size:.75rem;padding:4px 10px"
                  onclick="BotContacts.startConv(${JSON.stringify(c.phone)}, ${JSON.stringify(c.name)}, ${JSON.stringify(c.client_id)}, this)">
            <i class="fas fa-comments" style="margin-right:4px"></i>Iniciar
          </button>
        </div>
      </div>`;
    }).join('');
  }

  async function startConv(phone, name, clientId, btn) {
    if (btn) btn.disabled = true;

    // Si existe la función del panel para nueva conversación, usarla con prefill
    if (typeof App !== 'undefined' && typeof App.openNewConv === 'function') {
      App.openNewConv(phone, name);
      if (btn) btn.disabled = false;
      return;
    }

    // Fallback: llamar a start_conversation directamente si existe
    try {
      const res  = await fetch('/api/start_conversation.php', {
        method:      'POST',
        credentials: 'include',
        headers:     { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body:        JSON.stringify({ phone, name, client_id: clientId }),
      });
      const json = await res.json();
      if (json.success && json.conversation_id) {
        // Navegar a conversaciones y abrir el chat
        App.navigate('conversations');
        setTimeout(() => App.openConv && App.openConv(json.conversation_id), 300);
      } else {
        // Abrir modal de nueva conversación con número prefilled
        _openNewConvModal(phone, name);
      }
    } catch (_) {
      _openNewConvModal(phone, name);
    }
    if (btn) btn.disabled = false;
  }

  function _openNewConvModal(phone, name) {
    // Navegar a conversaciones y abrir modal de nueva conversación
    if (typeof App !== 'undefined') App.navigate('conversations');
    setTimeout(() => {
      // Intentar abrir modal de nueva conversación si existe
      const btn = document.getElementById('btn-new-conv');
      if (btn) {
        btn.click();
        setTimeout(() => {
          const phoneEl = document.getElementById('new-conv-phone');
          const nameEl  = document.getElementById('new-conv-name');
          if (phoneEl) phoneEl.value = phone;
          if (nameEl)  nameEl.value  = name;
        }, 200);
      }
    }, 300);
  }

  function _initials(str) {
    const parts = String(str || '?').trim().split(/\s+/);
    return (parts[0][0] + (parts[1] ? parts[1][0] : '')).toUpperCase();
  }

  function _esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function _fmtDate(dt) {
    if (!dt) return '';
    const d = new Date(dt.replace(' ', 'T'));
    if (isNaN(d)) return dt;
    const now  = new Date();
    const diff = (now - d) / 1000;
    if (diff < 60)   return 'Ahora';
    if (diff < 3600) return Math.floor(diff / 60) + ' min';
    if (diff < 86400)return Math.floor(diff / 3600) + 'h';
    return d.toLocaleDateString('es-CO', { day:'2-digit', month:'short' });
  }

  // Auto-refresh cada 30s mientras la sección esté visible
  function startAutoRefresh() {
    _refreshTimer = setInterval(load, 30000);
  }
  function stopAutoRefresh() {
    if (_refreshTimer) { clearInterval(_refreshTimer); _refreshTimer = null; }
  }

  // Iniciar al cargar la sección
  load();
  startAutoRefresh();

  // Limpiar timer cuando el DOM de la sección sea destruido
  window.addEventListener('beforeunload', stopAutoRefresh);
  // También detenerse si supervisor-section se vacía (otro navigate)
  const observer = new MutationObserver(() => {
    if (!document.getElementById('bc-items')) stopAutoRefresh();
  });
  const sup = document.getElementById('supervisor-section');
  if (sup) observer.observe(sup, { childList: true });

  return { load, filter, startConv };
})();
</script>
