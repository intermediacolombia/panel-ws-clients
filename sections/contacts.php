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
    <div class="conv-search-wrap">
      <i class="fas fa-search conv-search-icon"></i>
      <input id="bc-search" type="text" class="conv-search"
             placeholder="Buscar por nombre o teléfono…"
             oninput="BotContacts.filter(this.value)" autocomplete="off">
    </div>
  </div>

  <!-- Lista -->
  <div id="bc-list" style="flex:1;overflow-y:auto;padding:8px 0 14px">
    <div id="bc-loading" style="display:flex;align-items:center;gap:10px;padding:30px 16px;color:var(--texto-suave)">
      <div class="spinner" style="width:20px;height:20px;border-color:var(--verde-mid);border-top-color:transparent;flex-shrink:0"></div>
      Cargando contactos…
    </div>
    <div id="bc-empty" style="display:none;text-align:center;padding:50px 16px;color:var(--texto-suave)">
      <i class="fas fa-address-book" style="font-size:2.4rem;opacity:.3;display:block;margin-bottom:10px"></i>
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
      (c.name || '').toLowerCase().includes(lq) || (c.phone || '').toLowerCase().includes(lq)
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

    items.innerHTML = contacts.map((c, i) => {
      const initials = _initials(c.name || c.phone);
      const display  = _esc(c.name || c.phone);
      const phone    = _esc(c.phone);
      const date     = _fmtDate(c.updated_at);
      const colors   = ['#25d366','#128c7e','#075e54','#34b7f1','#00a884'];
      const bg       = colors[i % colors.length];
      return '<div class="conv-item" style="cursor:default;padding:10px 14px;gap:12px">'
           +   '<div class="conv-avatar" style="background:' + bg + ';color:#fff;font-weight:700;text-transform:uppercase;flex-shrink:0;font-size:.85rem">'
           +     _esc(initials)
           +   '</div>'
           +   '<div class="conv-info" style="flex:1;min-width:0">'
           +     '<div class="conv-name">' + display + '</div>'
           +     '<div class="conv-preview" style="font-size:.79rem;margin-top:1px">' + phone + '</div>'
           +   '</div>'
           +   '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0">'
           +     '<span style="font-size:.72rem;color:var(--texto-suave);white-space:nowrap">' + date + '</span>'
           +     '<button class="btn-primary" style="font-size:.75rem;padding:4px 12px;border-radius:20px;white-space:nowrap"'
           +       ' onclick="BotContacts.startConv(' + JSON.stringify(c.phone) + ',' + JSON.stringify(c.name || '') + ')">'
           +       '<i class="fas fa-comment-dots" style="margin-right:5px"></i>Iniciar'
           +     '</button>'
           +   '</div>'
           + '</div>';
    }).join('');
  }

  function startConv(phone, name) {
    // Navegar a conversaciones y abrir el modal de nueva conversación prefilled
    if (typeof App !== 'undefined') {
      App.navigate('conversations');
    }
    setTimeout(function() {
      if (typeof App !== 'undefined' && typeof App.openNewConversation === 'function') {
        App.openNewConversation();
      }
      setTimeout(function() {
        var phoneEl = document.getElementById('new-conv-phone');
        var nameEl  = document.getElementById('new-conv-name');
        if (phoneEl) { phoneEl.value = phone; phoneEl.dispatchEvent(new Event('input')); }
        if (nameEl  && name) { nameEl.value = name; }
      }, 150);
    }, 300);
  }

  function _initials(str) {
    var parts = String(str || '?').trim().split(/\s+/);
    return (parts[0][0] + (parts[1] ? parts[1][0] : '')).toUpperCase();
  }

  function _esc(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function _fmtDate(dt) {
    if (!dt) return '';
    var d = new Date(dt.replace(' ', 'T'));
    if (isNaN(d)) return dt;
    var now  = new Date();
    var diff = (now - d) / 1000;
    if (diff < 60)    return 'Ahora';
    if (diff < 3600)  return Math.floor(diff / 60) + ' min';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h';
    return d.toLocaleDateString('es-CO', { day: '2-digit', month: 'short' });
  }

  function startAutoRefresh() {
    _refreshTimer = setInterval(load, 30000);
  }
  function stopAutoRefresh() {
    if (_refreshTimer) { clearInterval(_refreshTimer); _refreshTimer = null; }
  }

  load();
  startAutoRefresh();

  window.addEventListener('beforeunload', stopAutoRefresh);
  var observer = new MutationObserver(function() {
    if (!document.getElementById('bc-items')) stopAutoRefresh();
  });
  var sup = document.getElementById('supervisor-section');
  if (sup) observer.observe(sup, { childList: true });

  return { load: load, filter: filter, startConv: startConv };
})();
</script>
