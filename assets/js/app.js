/**
 * app.js — Controlador principal del Panel de Agentes.
 */

const App = (() => {
  let _currentSection = 'conversations';
  let _currentConvId  = null;
  let _conversations  = [];
  let _realtime       = null;
  let _notifUnread    = 0;
  let _searchTimer    = null;
  let _activeTab      = 'all';
  const _ppCache      = {};   // Cache foto de perfil en lista: phone → true|false

  // ── Inicializar ────────────────────────────────────────────
  function init() {
    Chat.initListeners();

    // Pedir permiso de notificaciones
    Notify.requestPermission();

    // Leer sección inicial de URL
    const params  = new URLSearchParams(window.location.search);
    const section = params.get('section') || 'conversations';
    navigate(section, true);

    // Cargar notificaciones
    loadNotifications();

    // Iniciar realtime
    _realtime = new Realtime(
      window.PANEL_CONFIG.agentToken,
      _onNewMessage,
      _onConvUpdate,
      _onNotification,
    );
    _realtime.start();
  }

  // ── Navegación ─────────────────────────────────────────────
  function navigate(section, replace = false) {
    _currentSection = section;

    // Sidebar items activos
    document.querySelectorAll('.sidebar-item').forEach(el => {
      el.classList.toggle('active', el.dataset.section === section);
    });

    const convPanel      = document.getElementById('conv-panel');
    const chatArea       = document.getElementById('content-area');   // chat — siempre en DOM
    const supSection     = document.getElementById('supervisor-section'); // secciones dinámicas

    if (section === 'conversations') {
      // Mostrar lista y chat, ocultar sección dinámica
      if (convPanel)  convPanel.style.display  = '';
      if (chatArea)   chatArea.style.display   = '';
      if (supSection) {
        supSection.style.display = 'none';
        supSection.innerHTML     = '';   // liberar memoria al salir
      }
      // Móvil: volver a estado "lista visible"
      if (window.innerWidth <= 640) {
        if (convPanel) convPanel.classList.remove('conv-hidden');
        if (chatArea)  chatArea.classList.remove('chat-visible');
      }
      loadConversations();
    } else {
      // Ocultar lista y chat, mostrar sección dinámica
      if (convPanel)  convPanel.style.display  = 'none';
      if (chatArea)   chatArea.style.display   = 'none';
      if (supSection) {
        supSection.style.display       = 'flex';
        supSection.style.flexDirection = 'column';
        supSection.innerHTML           = '<div style="display:flex;align-items:center;justify-content:center;height:100%"><div class="spinner" style="border-color:var(--verde-mid);border-top-color:transparent;width:36px;height:36px"></div></div>';
      }
      _loadSection(section);
    }

    const url = '?section=' + encodeURIComponent(section);
    if (replace) {
      history.replaceState({ section }, '', url);
    } else {
      history.pushState({ section }, '', url);
    }
  }

  async function _loadSection(section) {
    const supSection = document.getElementById('supervisor-section');
    if (!supSection) return;

    try {
      const res = await fetch('/sections/' + section + '.php', {
        credentials: 'include',
        headers: { 'Accept': 'text/html' },
      });

      if (res.status === 401) { window.location.href = '/login.php'; return; }
      if (!res.ok) throw new Error('HTTP ' + res.status);

      const html = await res.text();
      supSection.innerHTML = html;

      // Ejecutar scripts inline del HTML cargado
      supSection.querySelectorAll('script').forEach(old => {
        const s = document.createElement('script');
        if (old.src) { s.src = old.src; }
        else { s.textContent = old.textContent; }
        old.replaceWith(s);
      });

    } catch (e) {
      supSection.innerHTML = '<div class="section-wrap"><p class="text-muted">Error cargando sección.</p></div>';
    }
  }

  // ── Conversaciones ─────────────────────────────────────────
  async function loadConversations(filters = {}) {
    const status = filters.status || _activeTab;
    const search = filters.search !== undefined
      ? filters.search
      : (document.getElementById('conv-search')?.value?.trim() || '');
    const dept   = filters.dept || 0;

    const params = new URLSearchParams({ status, search, dept, limit: 80, offset: 0 });

    try {
      const res  = await fetch('/api/conversations.php?' + params.toString(), {
        credentials: 'include',
        headers: { 'Accept': 'application/json' },
      });

      if (res.status === 401) { window.location.href = '/login.php'; return; }
      const json = await res.json();
      if (!json.success) return;

      _conversations = json.conversations || [];
      _renderConvList(_conversations);
      _updateTabCounts(json);

      // Actualizar badge pendientes en sidebar
      const badge = document.getElementById('sidebar-conv-badge');
      if (badge) {
        const p = json.pending || 0;
        badge.textContent = p > 99 ? '99+' : String(p);
        badge.style.display = p > 0 ? '' : 'none';
      }
    } catch (_) {}
  }

  function _renderConvList(convs) {
    const list = document.getElementById('conv-list');
    if (!list) return;

    if (convs.length === 0) {
      list.innerHTML = `<div class="conv-empty">
        <i class="fas fa-comments"></i>
        <p>Sin conversaciones.</p>
      </div>`;
      return;
    }

    list.innerHTML = convs.map(c => _buildConvItem(c)).join('');

    // Marcar la activa
    if (_currentConvId) {
      const el = list.querySelector('[data-conv-id="' + _currentConvId + '"]');
      if (el) el.classList.add('selected');
    }

    _loadListAvatars();
  }

  function _buildConvItem(c) {
    const initial  = (c.contact_name || c.phone || '?')[0].toUpperCase();
    const name     = c.contact_name || c.phone;
    const time     = c.time_formatted || '';
    const preview  = c.last_message ? _truncate(c.last_message, 42) : '';
    const unread   = (c.id === _currentConvId) ? 0 : (parseInt(c.unread_count) || 0);
    const area     = c.area_label || '';
    const agentLbl = c.agent_name ? 'Agente: ' + c.agent_name : '';

    return `<div class="conv-item" data-conv-id="${c.id}"
                 onclick="App.openConversation(${c.id})">
      <div class="conv-avatar ${c.status}"
           data-phone="${_escAttr(c.phone)}"
           data-name="${_escAttr(c.contact_name || c.phone)}"
           data-status="${_escAttr(c.status)}"
           onclick="event.stopPropagation();ProfileModal.openFromEl(this)">${_escHtml(initial)}</div>
      <div class="conv-info">
        <div class="conv-info-top">
          <span class="conv-name">${_escHtml(name)}</span>
          <span class="conv-time">${_escHtml(time)}</span>
        </div>
        <div class="conv-phone">${_escHtml(c.phone)}</div>
        <div class="conv-preview">${_escHtml(preview)}</div>
        <div class="conv-meta">
          ${area ? `<span class="area-chip" style="background:${c.dept_color || '#ccc'}22;color:${c.dept_color || '#667080'}">${_escHtml(area)}</span>` : ''}
          ${agentLbl ? `<span class="conv-agent-label">${_escHtml(agentLbl)}</span>` : ''}
          ${unread > 0 ? `<span class="unread-badge">${unread}</span>` : ''}
        </div>
      </div>
    </div>`;
  }

  function _updateTabCounts(json) {
    const tabs = {
      all:       (json.total || 0),
      pending:   (json.pending || 0),
      attending: (json.attending || 0),
      resolved:  (json.resolved || 0),
    };
    Object.entries(tabs).forEach(([key, val]) => {
      const el = document.getElementById('tab-count-' + key);
      if (el) el.textContent = val;
    });
  }

  // ── Abrir conversación ─────────────────────────────────────
  async function openConversation(convId) {
    convId = parseInt(convId);
    _currentConvId = convId;

    // Marcar en lista
    document.querySelectorAll('.conv-item').forEach(el => {
      el.classList.toggle('selected', parseInt(el.dataset.convId) === convId);
    });

    // Asegurarse de estar en sección conversations
    if (_currentSection !== 'conversations') {
      navigate('conversations');
    }

    // Mostrar chat
    const welcome  = document.getElementById('chat-welcome');
    const chatWrap = document.getElementById('chat-wrap');
    if (welcome)  welcome.classList.add('hidden');
    if (chatWrap) chatWrap.classList.remove('hidden');

    // Móvil: ocultar lista, mostrar chat
    if (window.innerWidth <= 640) {
      const convPanel = document.getElementById('conv-panel');
      const chatArea  = document.getElementById('content-area');
      if (convPanel) convPanel.classList.add('conv-hidden');
      if (chatArea)  chatArea.classList.add('chat-visible');
    }

    await Chat.load(convId);

    // Actualizar unread_count a 0 en la lista local
    const conv = _conversations.find(c => c.id === convId);
    if (conv) {
      conv.unread_count = 0;
      _updateConvItemUnread(convId, 0);
    }
  }

  // ── Volver a la lista (móvil) ──────────────────────────────
  function backToList() {
    _currentConvId = null;
    const convPanel = document.getElementById('conv-panel');
    const chatArea  = document.getElementById('content-area');
    if (convPanel) convPanel.classList.remove('conv-hidden');
    if (chatArea)  chatArea.classList.remove('chat-visible');
    document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('selected'));
  }

  function _updateConvItemUnread(convId, count) {
    const item = document.querySelector('[data-conv-id="' + convId + '"]');
    if (!item) return;
    const badge = item.querySelector('.unread-badge');
    if (count > 0) {
      if (!badge) {
        const meta = item.querySelector('.conv-meta');
        if (meta) {
          const b = document.createElement('span');
          b.className = 'unread-badge';
          b.textContent = count;
          meta.appendChild(b);
        }
      } else {
        badge.textContent = count;
      }
    } else if (badge) {
      badge.remove();
    }
  }

  function updateConversationInList(conv) {
    const idx = _conversations.findIndex(c => c.id === conv.id);

    if (idx !== -1) {
      _conversations[idx] = Object.assign(_conversations[idx], conv);
    } else {
      _conversations.unshift(conv);
    }

    // Re-renderizar el ítem específico
    const item = document.querySelector('[data-conv-id="' + conv.id + '"]');
    if (item) {
      const newItem = document.createElement('div');
      newItem.innerHTML = _buildConvItem(conv);
      const newEl = newItem.firstChild;
      item.replaceWith(newEl);
      const updatedAvatar = newEl.querySelector('.conv-avatar[data-phone]');
      if (updatedAvatar) _applyListAvatar(updatedAvatar, conv.phone);
    } else {
      // Conversación nueva: añadir al tope con animación
      const list = document.getElementById('conv-list');
      if (list) {
        const empty = list.querySelector('.conv-empty');
        if (empty) empty.remove();
        list.insertAdjacentHTML('afterbegin', _buildConvItem(conv));
        const newAvatar = list.firstElementChild?.querySelector('.conv-avatar[data-phone]');
        if (newAvatar) _applyListAvatar(newAvatar, conv.phone);
      }
    }

    if (parseInt(conv.id) === _currentConvId) {
      Chat.updateConv(conv);
    }
  }

  // ── Acciones sobre conversaciones ─────────────────────────
  async function handleAction(action, convId) {
    const labels = {
      assign:  { title: 'Asignar conversación', msg: '¿Asignarte esta conversación?', icon: 'info', confirmText: 'Asignarme' },
      resolve: { title: 'Resolver conversación', msg: '¿Marcar conversación como resuelta?', icon: 'success', confirmText: 'Resolver' },
      release: { title: 'Liberar al bot', msg: '¿Devolver el control al bot? Se enviará mensaje de despedida.', icon: 'warning', confirmText: 'Liberar' },
      reopen:  { title: 'Reabrir conversación', msg: '¿Reabrir esta conversación y asignártela?', icon: 'info', confirmText: 'Reabrir' },
    };

    const cfg = labels[action];
    if (!cfg) return;

    const confirmed = await ConfirmModal.show(cfg);
    if (!confirmed) return;

    try {
      const res  = await fetch('/api/' + action + '.php', {
        method:      'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'Accept':       'application/json',
        },
        body: JSON.stringify({ conversationId: convId }),
      });
      const json = await res.json();

      if (json.success) {
        Notify.showToast('Acción realizada correctamente.', 'success');
        if (convId === _currentConvId) {
          await Chat.load(convId);
        }
        loadConversations();
      } else {
        Notify.showToast(json.error || 'Error en la operación.', 'error');
      }
    } catch (_) {
      Notify.showToast('Error de red.', 'error');
    }
  }

  // ── Búsqueda con debounce ──────────────────────────────────
  function searchConversations(value) {
    clearTimeout(_searchTimer);
    _searchTimer = setTimeout(() => {
      loadConversations({ search: value });
    }, 300);
  }

  function setActiveTab(tab) {
    _activeTab = tab;
    document.querySelectorAll('.conv-tab').forEach(el => {
      el.classList.toggle('active', el.dataset.tab === tab);
    });
    loadConversations({ status: tab });
  }

  // ── Modal helpers ──────────────────────────────────────────
  function openModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.add('open');
  }

  function closeModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.remove('open');
  }

  // ── Notificaciones ─────────────────────────────────────────
  async function loadNotifications() {
    try {
      const res  = await fetch('/api/notifications.php', {
        credentials: 'include',
        headers: { 'Accept': 'application/json' },
      });
      const json = await res.json();
      if (!json.success) return;

      _notifUnread = json.unread || 0;
      Notify.updateNotifBadge(_notifUnread);
      _renderNotifDropdown(json.notifications || []);
    } catch (_) {}
  }

  function _renderNotifDropdown(notifs) {
    const body = document.getElementById('notif-list');
    if (!body) return;

    if (notifs.length === 0) {
      body.innerHTML = '<div class="notif-item text-muted text-center" style="padding:20px">Sin notificaciones.</div>';
      return;
    }

    body.innerHTML = notifs.map(n => `
      <div class="notif-item ${n.read_at ? '' : 'unread'}"
           onclick="App.openNotif(${n.conversation_id}, ${n.id})">
        <div class="notif-msg">${_escHtml(n.message)}</div>
        <div class="notif-time">${n.created_at ? n.created_at.substring(0,16) : ''}</div>
      </div>`).join('');
  }

  function toggleNotifDropdown() {
    const d = document.getElementById('notif-dropdown');
    if (!d) return;
    d.classList.toggle('open');
    if (d.classList.contains('open')) loadNotifications();
  }

  async function openNotif(convId, notifId) {
    // Marcar como leída
    fetch('/api/notifications.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: notifId }),
    }).catch(() => {});

    const d = document.getElementById('notif-dropdown');
    if (d) d.classList.remove('open');

    navigate('conversations');
    await openConversation(convId);
  }

  async function markAllNotifsRead() {
    await fetch('/api/notifications.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ markAll: true }),
    }).catch(() => {});
    _notifUnread = 0;
    Notify.updateNotifBadge(0);
    loadNotifications();
  }

  // ── Realtime handlers ──────────────────────────────────────
  function _onNewMessage(msg) {
    const convId   = parseInt(msg.conversation_id);
    const isActive = convId === _currentConvId;
    const isOutgoing = msg.direction === 'out';

    // Mover la conversación al tope de la lista (comportamiento tipo WhatsApp)
    const idx = _conversations.findIndex(c => c.id === convId);
    if (idx >= 0) {
      const [conv] = _conversations.splice(idx, 1);
      if (msg.content)    conv.last_message   = msg.content;
      if (msg.created_at) conv.time_formatted = msg.created_at.substring(11, 16);
      if (!isActive && !isOutgoing) conv.unread_count = (conv.unread_count || 0) + 1;
      _conversations.unshift(conv);
      _renderConvList(_conversations);

      // Solo mostrar notificación para mensajes entrantes cuando no estamos en la conversación
      if (!isActive && !isOutgoing) {
        Notify.playSound();
        Notify.showNotification(
          'Nuevo mensaje de ' + (conv.contact_name || conv.phone),
          msg.content || '',
          conv.id,
        );
      }
    }

    // Si es la conversación activa, agregar la burbuja (tanto in como out)
    if (isActive) Chat.appendMessage(msg);
  }

  // Mueve una conversación al tope tras enviar un mensaje propio
  function moveConvToTop(convId, lastMsg) {
    const idx = _conversations.findIndex(c => c.id === convId);
    if (idx <= 0) return; // ya está primera o no existe
    const [conv] = _conversations.splice(idx, 1);
    if (lastMsg != null) conv.last_message = lastMsg;
    _conversations.unshift(conv);
    _renderConvList(_conversations);
  }

  function _onConvUpdate(conv) {
    // conv.id viene como string desde el SSE (PDO sin cast), normalizar
    const convId = parseInt(conv.id);
    const idx = _conversations.findIndex(c => c.id === convId);
    if (idx === -1) {
      // No está en la lista actual (distinto tab/filtro), ignorar
      _renderConvList(_conversations);
      return;
    }

    // Actualizar solo campos escalares conocidos; NO pisar time_formatted ni last_message
    // porque el SSE no los incluye (son calculados en PHP)
    const local = _conversations[idx];
    if (conv.status     !== undefined) local.status       = conv.status;
    if (conv.agent_id   !== undefined) local.agent_id     = conv.agent_id   !== null ? parseInt(conv.agent_id)   : null;
    if (conv.agent_name !== undefined) local.agent_name   = conv.agent_name;
    if (conv.contact_name)             local.contact_name = conv.contact_name;
    if (conv.unread_count !== undefined) local.unread_count = parseInt(conv.unread_count) || 0;

    // Mover al tope si no está ya
    if (idx > 0) {
      _conversations.splice(idx, 1);
      _conversations.unshift(local);
    }

    _renderConvList(_conversations);
    if (convId === _currentConvId) Chat.updateConv(conv);
  }

  function _onNotification(notif) {
    _notifUnread++;
    Notify.updateNotifBadge(_notifUnread);
    Notify.updateTitle(_notifUnread);
    Notify.playSound();
    Notify.showNotification(
      'Panel de Agentes',
      notif.message || '',
      notif.conversation_id,
    );
    // Refrescar el dropdown para que aparezca el ítem inmediatamente
    loadNotifications();
  }

  // ── Fotos de perfil en lista ───────────────────────────────
  function _loadListAvatars() {
    document.querySelectorAll('#conv-list .conv-avatar[data-phone]').forEach(avatarEl => {
      const phone = avatarEl.dataset.phone;
      if (!phone || _ppCache[phone] === false) return;
      _applyListAvatar(avatarEl, phone);
    });
  }

  function _applyListAvatar(avatarEl, phone) {
    const img = new Image();
    img.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:50%;display:block';
    img.alt = '';
    img.onload = () => {
      if (avatarEl.isConnected && avatarEl.dataset.phone === phone) {
        avatarEl.innerHTML = '';
        avatarEl.style.backgroundImage = 'none';
        avatarEl.appendChild(img);
      }
      _ppCache[phone] = true;
    };
    img.onerror = () => { _ppCache[phone] = false; };
    img.src = '/api/profile_picture.php?phone=' + encodeURIComponent(phone);
  }

  // ── Helpers ────────────────────────────────────────────────
  function _escHtml(s) {
    return String(s)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  function _escAttr(s) { return String(s).replace(/"/g, '&quot;'); }

  function _truncate(s, max) {
    return s.length > max ? s.substring(0, max) + '...' : s;
  }

  // Historial del navegador
  window.addEventListener('popstate', e => {
    const section = e.state?.section || 'conversations';
    navigate(section, true);
  });

  // Cerrar dropdown al click fuera
  document.addEventListener('click', e => {
    const dd   = document.getElementById('notif-dropdown');
    const btn  = document.getElementById('notif-btn');
    if (dd && !dd.contains(e.target) && e.target !== btn && !btn?.contains(e.target)) {
      dd.classList.remove('open');
    }
  });

  // ── Nueva conversación saliente ────────────────────────────
  function openNewConversation() {
    const phone = document.getElementById('new-conv-phone');
    const name  = document.getElementById('new-conv-name');
    const msg   = document.getElementById('new-conv-message');
    const err   = document.getElementById('new-conv-error');
    if (phone) phone.value = '';
    if (name)  name.value  = '';
    if (msg)   msg.value   = '';
    if (err)   err.classList.add('hidden');
    openModal('modal-new-conv');
    setTimeout(() => { if (phone) phone.focus(); }, 100);
  }

  async function sendNewConversation() {
    const phone = document.getElementById('new-conv-phone')?.value.trim().replace(/\D/g, '');
    const name  = document.getElementById('new-conv-name')?.value.trim();
    const msg   = document.getElementById('new-conv-message')?.value.trim();
    const err   = document.getElementById('new-conv-error');
    const btn   = document.getElementById('btn-new-conv-send');

    const showErr = (text) => {
      if (err) { err.querySelector('span').textContent = text; err.classList.remove('hidden'); }
    };

    if (!phone || phone.length < 7) return showErr('Ingresa un número válido con código de país.');
    if (!msg)                        return showErr('El mensaje no puede estar vacío.');
    if (err) err.classList.add('hidden');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';

    try {
      const res  = await fetch('/api/start_conversation.php', {
        method:      'POST',
        credentials: 'include',
        headers:     { 'Content-Type': 'application/json' },
        body:        JSON.stringify({ phone, name, message: msg }),
      });
      const json = await res.json();

      if (json.success) {
        closeModal('modal-new-conv');
        await loadConversations();
        openConversation(json.conversationId);
        Notify.showToast('Conversación iniciada.', 'success');
      } else if (res.status === 409) {
        closeModal('modal-new-conv');
        const open = await ConfirmModal.show({
          title: 'Conversación existente',
          msg: 'Ya existe una conversación activa con ese número. ¿Quieres abrirla?',
          icon: 'info',
          confirmText: 'Abrir',
        });
        if (open) openConversation(json.conversationId);
      } else {
        showErr(json.error || 'Error al enviar.');
      }
    } catch (_) {
      showErr('Error de red. Intenta de nuevo.');
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar y abrir';
    }
  }

  return {
    init,
    navigate,
    loadConversations,
    openConversation,
    backToList,
    updateConversationInList,
    handleAction,
    searchConversations,
    setActiveTab,
    openModal,
    closeModal,
    loadNotifications,
    toggleNotifDropdown,
    openNotif,
    markAllNotifsRead,
    openNewConversation,
    sendNewConversation,
    moveConvToTop,
  };
})();

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => App.init());
