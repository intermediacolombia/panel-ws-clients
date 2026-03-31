/**
 * chat.js — Lógica del área de chat activo.
 */

const Chat = (() => {
  let _conv       = null;    // Conversación activa
  let _messages   = [];      // Mensajes cargados
  let _fileState  = null;    // Archivo pendiente de enviar { file, dataUrl, type }
  let _sending    = false;
  const _ppCache  = {};      // Cache foto de perfil: phone → url|false

  const el = {
    wrap:         () => document.getElementById('chat-wrap'),
    welcome:      () => document.getElementById('chat-welcome'),
    headerName:   () => document.getElementById('chat-contact-name'),
    headerSub:    () => document.getElementById('chat-contact-sub'),
    headerBadge:  () => document.getElementById('chat-status-badge'),
    headerActions:() => document.getElementById('chat-header-actions'),
    messages:     () => document.getElementById('chat-messages'),
    composer:     () => document.getElementById('chat-composer-content'),
    composerLocked:()=> document.getElementById('chat-composer-locked'),
    textarea:     () => document.getElementById('chat-textarea'),
    btnSend:      () => document.getElementById('btn-send'),
    attachMenu:   () => document.getElementById('attach-menu'),
    filePreview:  () => document.getElementById('file-preview'),
    captionWrap:  () => document.getElementById('caption-wrap'),
    captionInput: () => document.getElementById('caption-input'),
    inlineError:  () => document.getElementById('chat-inline-error'),
    infoPanel:    () => document.getElementById('chat-info-panel'),
    infoPanelBody:()  => document.getElementById('chat-info-body'),
  };

  // ── Cargar conversación ────────────────────────────────────
  async function load(convId) {
    try {
      const res  = await fetch('/api/conversation.php?id=' + convId, {
        credentials: 'include',
        headers: { 'Accept': 'application/json' },
      });
      const json = await res.json();
      if (!json.success) {
        Notify.showToast('Error al cargar conversación: ' + (json.error || ''), 'error');
        return;
      }
      _conv     = json.conversation;
      _messages = json.messages || [];
      _render();
      _renderInfo(json.conversation, json.previousConvs || []);
    } catch (e) {
      Notify.showToast('Error de red al cargar chat.', 'error');
    }
  }

  // ── Render completo ────────────────────────────────────────
  function _render() {
    if (!_conv) return;

    const chatWrap = el.wrap();
    const welcome  = el.welcome();
    if (chatWrap)  chatWrap.classList.remove('hidden');
    if (welcome)   welcome.classList.add('hidden');

    // Header
    const nameEl = el.headerName();
    const subEl  = el.headerSub();
    const badge  = el.headerBadge();

    if (nameEl) nameEl.textContent = _conv.contact_name || _conv.phone;
    if (subEl)  subEl.innerHTML = _buildSubLine();
    if (badge) {
      badge.textContent = _statusLabel(_conv.status);
      badge.className   = 'status-badge ' + _conv.status;
    }

    _renderActions();
    renderMessages(_messages);
    _updateComposer();
    _updateAvatar();
  }

  // ── Foto de perfil ─────────────────────────────────────────
  function _avatarInitials(name) {
    const parts = String(name || '?').trim().split(/\s+/);
    return (parts[0][0] + (parts[1] ? parts[1][0] : '')).toUpperCase();
  }

  function _updateAvatar() {
    const avatarEl = document.getElementById('chat-avatar');
    if (!avatarEl || !_conv) return;

    const phone    = _conv.phone;
    const initials = _avatarInitials(_conv.contact_name || phone);

    // Click abre modal de foto de perfil
    avatarEl.onclick = () => ProfileModal.open(phone, _conv.contact_name || phone, _conv.status);

    // Mostrar iniciales por defecto
    avatarEl.style.backgroundImage = '';
    avatarEl.innerHTML = initials;

    // Si ya sabemos que no tiene foto, no intentar de nuevo
    if (_ppCache[phone] === false) return;

    // El proxy hace stream de la imagen desde el servidor (evita restricciones CSP)
    const proxyUrl = '/api/profile_picture.php?phone=' + encodeURIComponent(phone);
    const img = new Image();
    img.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:50%;display:block';
    img.alt = '';
    img.onload = () => {
      // Verificar que la conversación sigue activa para este teléfono
      if (_conv && _conv.phone === phone) {
        avatarEl.innerHTML = '';
        avatarEl.style.backgroundImage = 'none';
        avatarEl.appendChild(img);
      }
      _ppCache[phone] = true;
    };
    img.onerror = () => {
      _ppCache[phone] = false;
    };
    img.src = proxyUrl;
  }

  function _buildSubLine() {
    let html = '<span>' + _escHtml(_conv.phone) + '</span>';
    if (_conv.area_label) {
      html += '<span class="area-chip">' + _escHtml(_conv.area_label) + '</span>';
    }
    return html;
  }

  function _statusLabel(s) {
    return { pending: 'Pendiente', attending: 'En atención', resolved: 'Resuelto', bot: 'Bot' }[s] || s;
  }

  function _renderActions() {
    const actEl = el.headerActions();
    if (!actEl) return;

    const role   = window.PANEL_CONFIG.agentRole;
    const myId   = window.PANEL_CONFIG.agentId;
    const status = _conv.status;
    const convId = _conv.id;

    let html = '';

    if (status === 'pending') {
      html += `<button class="btn-action btn-assign" onclick="App.handleAction('assign',${convId})">
                 <i class="fas fa-hand-pointer"></i><span> Asignarme</span>
               </button>`;
    }

    if (status === 'attending' && (role === 'supervisor' || _conv.agent_id === myId)) {
      html += `<button class="btn-action btn-resolve" onclick="App.handleAction('resolve',${convId})">
                 <i class="fas fa-check"></i><span> Resolver</span>
               </button>`;
    }

    if ((status === 'attending' || status === 'pending') &&
        (role === 'supervisor' || _conv.agent_id === myId)) {
      html += `<button class="btn-action btn-release" onclick="App.handleAction('release',${convId})">
                 <i class="fas fa-robot"></i><span> Bot</span>
               </button>`;
    }

    // Reabrir / enviar a bot desde estado resuelto
    if (status === 'resolved' && (role === 'supervisor' || role === 'agent')) {
      html += `<button class="btn-action btn-assign" onclick="App.handleAction('reopen',${convId})">
                 <i class="fas fa-redo"></i><span> Reabrir</span>
               </button>`;
      html += `<button class="btn-action btn-release" onclick="App.handleAction('release',${convId})">
                 <i class="fas fa-robot"></i><span> Bot</span>
               </button>`;
    }

    // Reabrir desde estado bot
    if (status === 'bot') {
      html += `<button class="btn-action btn-assign" onclick="App.handleAction('reopen',${convId})">
                 <i class="fas fa-redo"></i><span> Reabrir</span>
               </button>`;
    }

    // Botón transferir (solo en atención)
    if (status === 'attending' && (role === 'supervisor' || _conv.agent_id === myId)) {
      html += `<button class="btn-action btn-transfer" onclick="Chat.openTransferModal()" title="Transferir a otro agente">
                 <i class="fas fa-exchange-alt"></i><span> Transferir</span>
               </button>`;
    }

    // Botón editar nombre
    html += `<button class="btn-action btn-rename" onclick="Chat.openRenameModal()" title="Editar nombre del contacto">
               <i class="fas fa-user-edit"></i>
             </button>`;

    // Botón info
    html += `<button class="btn-action btn-info" onclick="Chat.toggleInfo()" title="Información del contacto">
               <i class="fas fa-info-circle"></i>
             </button>`;

    actEl.innerHTML = html;

  }

  function _updateComposer() {
    const comp   = el.composer();
    const locked = el.composerLocked();
    if (!comp || !locked) return;

    const status  = _conv.status;
    const myId    = window.PANEL_CONFIG.agentId;
    const role    = window.PANEL_CONFIG.agentRole;

    // Conversación atendida por OTRO agente (no soy supervisor)
    const attendedByOther = status === 'attending' &&
                            role !== 'supervisor'  &&
                            _conv.agent_id !== null &&
                            _conv.agent_id !== myId;

    if (status === 'resolved' || status === 'bot' || attendedByOther) {
      comp.classList.add('hidden');
      locked.classList.remove('hidden');

      if (attendedByOther) {
        locked.innerHTML = '<i class="fas fa-user-lock"></i> Conversación asignada a otro agente.';
      } else {
        locked.innerHTML =
          '<i class="fas fa-lock"></i> ' +
          (status === 'resolved' ? 'Conversación resuelta.' : 'Usuario en control del bot.') +
          ' <button class="btn-reactivate" onclick="Chat.reactivate()">Reactivar</button>';
      }
    } else {
      comp.classList.remove('hidden');
      locked.classList.add('hidden');
    }
  }

  // ── Render mensajes ────────────────────────────────────────
  function renderMessages(messages) {
    const container = el.messages();
    if (!container) return;

    container.innerHTML = '';
    _messages = messages;

    let lastDate = '';
    messages.forEach(msg => {
      const msgDate = msg.created_at ? msg.created_at.substring(0, 10) : '';
      if (msgDate && msgDate !== lastDate) {
        container.appendChild(_buildDateSeparator(msg.created_at));
        lastDate = msgDate;
      }
      container.appendChild(renderBubble(msg));
    });

    scrollToBottom();
  }

  function appendMessage(msg) {
    const container = el.messages();
    if (!container) return;

    // Evitar duplicados: si el mensaje ya existe, no agregar
    if (msg.id && _messages.some(m => m.id === msg.id)) return;

    // Separador de fecha si cambia el día
    const msgDate = msg.created_at ? msg.created_at.substring(0, 10) : '';
    const lastMsg = _messages[_messages.length - 1];
    const lastDate = lastMsg ? (lastMsg.created_at || '').substring(0, 10) : '';

    if (msgDate && msgDate !== lastDate) {
      container.appendChild(_buildDateSeparator(msg.created_at));
    }

    _messages.push(msg);
    const bubble = renderBubble(msg);
    container.appendChild(bubble);
    scrollToBottom();
  }

  function renderBubble(msg) {
    const wrap = document.createElement('div');
    wrap.className = 'bubble-wrap ' + msg.direction;
    wrap.dataset.msgId = msg.id;

    const bubble = document.createElement('div');
    bubble.className = 'bubble';

    let innerHtml = '';

    if (msg.type === 'image' && msg.file_url) {
      innerHtml += `<div class="bubble-image">
        <img src="${_escAttr(msg.file_url)}" alt="${_escAttr(msg.file_name || 'imagen')}"
             onclick="Chat.openImageModal('${_escAttr(msg.file_url)}')" loading="lazy">
      </div>`;
      if (msg.caption) {
        innerHtml += `<div class="bubble-caption">${_linkify(msg.caption)}</div>`;
      }
    } else if (msg.type === 'document' && msg.file_url) {
      const size = msg.file_size ? _formatFileSize(msg.file_size) : '';
      innerHtml += `<div class="bubble-doc">
        <i class="fas fa-file-alt bubble-doc-icon"></i>
        <div class="bubble-doc-info">
          <div class="bubble-doc-name">${_escHtml(msg.file_name || 'documento')}</div>
          ${size ? `<div class="bubble-doc-size">${size}</div>` : ''}
        </div>
        <a href="${_escAttr(msg.file_url)}" target="_blank" rel="noopener noreferrer">
          <button class="btn-download"><i class="fas fa-download"></i></button>
        </a>
      </div>`;
      if (msg.caption) {
        innerHtml += `<div class="bubble-caption">${_linkify(msg.caption)}</div>`;
      }
    } else {
      // Texto con enlaces clickeables
      innerHtml += `<div class="bubble-text">${_linkify(msg.content)}</div>`;
    }

    // Meta
    const time = msg.created_at ? msg.created_at.substring(11, 16) : '';
    innerHtml += `<div class="bubble-meta">
      ${msg.direction === 'out' && msg.agent_name
        ? `<span class="bubble-agent">${_escHtml(msg.agent_name)}</span>` : ''}
      <span class="bubble-time">${time}</span>
      ${msg.status === 'failed'
        ? '<span style="color:#e74c3c;font-size:.68rem"><i class="fas fa-exclamation-triangle"></i></span>' : ''}
    </div>`;

    bubble.innerHTML = innerHtml;
    wrap.appendChild(bubble);
    return wrap;
  }

  function _buildDateSeparator(datetime) {
    const div = document.createElement('div');
    div.className = 'date-separator';
    div.innerHTML = '<span>' + _formatDate(datetime) + '</span>';
    return div;
  }

  function _formatDate(datetime) {
    if (!datetime) return '';
    const d   = new Date(datetime.replace(' ', 'T'));
    const now = new Date();

    const pad = n => String(n).padStart(2,'0');
    const dStr = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
    const nStr = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}`;
    const yStr = (() => { const y = new Date(now); y.setDate(y.getDate()-1);
      return `${y.getFullYear()}-${pad(y.getMonth()+1)}-${pad(y.getDate())}`; })();

    if (dStr === nStr) return 'Hoy';
    if (dStr === yStr) return 'Ayer';
    return `${pad(d.getDate())}/${pad(d.getMonth()+1)}/${d.getFullYear()}`;
  }

  function scrollToBottom(smooth = false) {
    const c = el.messages();
    if (!c) return;
    c.scrollTo({ top: c.scrollHeight, behavior: smooth ? 'smooth' : 'instant' });
  }

  // ── Info lateral ───────────────────────────────────────────
  function _renderInfo(conv, prevConvs) {
    const body = el.infoPanelBody();
    if (!body) return;

    const fmt = s => s ? new Date(s.replace(' ','T')).toLocaleString('es-CO') : '-';

    let html = `
      <div class="info-section">
        <h4>Contacto</h4>
        <div class="info-row"><span class="info-label">Nombre</span><span class="info-value">${_escHtml(conv.contact_name || '-')}</span></div>
        <div class="info-row"><span class="info-label">Teléfono</span><span class="info-value">${_escHtml(conv.phone)}</span></div>
        <div class="info-row"><span class="info-label">Área</span><span class="info-value">${_escHtml(conv.area_label || '-')}</span></div>
        <div class="info-row"><span class="info-label">Primer contacto</span><span class="info-value">${fmt(conv.first_contact_at)}</span></div>
      </div>
      <div class="info-section">
        <h4>Asignación</h4>
        <div class="info-row"><span class="info-label">Agente</span><span class="info-value">${_escHtml(conv.agent_name || '-')}</span></div>
        <div class="info-row"><span class="info-label">Asignado el</span><span class="info-value">${fmt(conv.assigned_at)}</span></div>
        <div class="info-row"><span class="info-label">Departamento</span><span class="info-value">${_escHtml(conv.dept_name || '-')}</span></div>
      </div>`;

    if (prevConvs.length > 0) {
      html += '<div class="info-section"><h4>Conversaciones anteriores</h4>';
      prevConvs.forEach(pc => {
        html += `<div class="prev-conv-item">
          <div class="prev-conv-area">${_escHtml(pc.area_label || '-')}</div>
          <div style="font-size:.75rem;color:var(--texto-suave)">${fmt(pc.first_contact_at)}</div>
        </div>`;
      });
      html += '</div>';
    }

    body.innerHTML = html;
  }

  function toggleInfo() {
    const p = el.infoPanel();
    if (!p) return;
    p.classList.toggle('collapsed');
  }

  // ── Manejo de archivos ─────────────────────────────────────
  function handleFileSelect(file, type) {
    // type = 'image' | 'document'
    const ALLOWED_IMAGE = ['image/jpeg','image/png','image/gif','image/webp'];
    const ALLOWED_DOC   = [
      'application/pdf','application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'application/vnd.ms-excel',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'text/plain','application/zip',
    ];
    const MAX_IMG = 5 * 1024 * 1024;
    const MAX_DOC = 10 * 1024 * 1024;

    if (type === 'image' && !ALLOWED_IMAGE.includes(file.type)) {
      showError('Imagen no permitida. Solo JPEG, PNG, GIF, WebP.');
      return;
    }
    if (type === 'document' && !ALLOWED_DOC.includes(file.type)) {
      showError('Documento no permitido. Solo PDF, DOCX, XLSX, TXT, ZIP.');
      return;
    }
    if (type === 'image' && file.size > MAX_IMG) {
      showError('La imagen supera 5MB.');
      return;
    }
    if (type === 'document' && file.size > MAX_DOC) {
      showError('El documento supera 10MB.');
      return;
    }

    const reader = new FileReader();
    reader.onload = e => {
      _fileState = { file, dataUrl: e.target.result, type };
      _showFilePreview(file, type, e.target.result);
    };
    reader.readAsDataURL(file);
  }

  function _showFilePreview(file, type, dataUrl) {
    const previewEl = el.filePreview();
    const captionEl = el.captionWrap();
    if (!previewEl) return;

    let html = '';
    if (type === 'image') {
      html = `<img src="${_escAttr(dataUrl)}" alt="${_escAttr(file.name)}">`;
    } else {
      html = `<i class="fas fa-file-alt file-preview-doc"></i>`;
    }
    html += `<div class="file-preview-info">
      <div class="file-preview-name">${_escHtml(file.name)}</div>
      <div class="file-preview-size">${_formatFileSize(file.size)}</div>
    </div>
    <button class="file-preview-remove" onclick="Chat.removeFile()" title="Quitar archivo">
      <i class="fas fa-times"></i>
    </button>`;

    previewEl.innerHTML = html;
    previewEl.classList.remove('hidden');
    if (captionEl) captionEl.classList.remove('hidden');
    closeAttachMenu();
  }

  function removeFile() {
    _fileState = null;
    const p = el.filePreview();
    const c = el.captionWrap();
    if (p) { p.innerHTML = ''; p.classList.add('hidden'); }
    if (c) { c.classList.add('hidden'); }
    const ci = el.captionInput();
    if (ci) ci.value = '';
  }

  // ── Enviar mensaje ─────────────────────────────────────────
  async function handleSend() {
    if (!_conv || _sending) return;

    const textarea = el.textarea();
    if (!textarea) return;

    const text = textarea.value.trim();

    if (!_fileState && text === '') return;

    _sending = true;
    _disableComposer(true);
    clearError();

    try {
      let body;

      if (_fileState) {
        // Separar el prefijo base64
        const b64 = _fileState.dataUrl.split(',')[1] || _fileState.dataUrl;
        const caption = el.captionInput() ? el.captionInput().value.trim() : '';

        body = JSON.stringify({
          conversationId: _conv.id,
          type:           _fileState.type,
          message:        caption || _fileState.file.name,
          fileBase64:     b64,
          fileName:       _fileState.file.name,
          mimeType:       _fileState.file.type,
          caption:        caption,
        });
      } else {
        body = JSON.stringify({
          conversationId: _conv.id,
          type:           'text',
          message:        text,
        });
      }

      const res = await fetch('/api/send.php', {
        method:      'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'Accept':       'application/json',
        },
        body,
      });

      const json = await res.json();

      if (json.success && json.message) {
        // Limpiar
        textarea.value = '';
        _autoResize(textarea);
        removeFile();
        // Agregar burbuja
        appendMessage(json.message);
        // Mover conversación al tope (tipo WhatsApp)
        if (window.App) App.moveConvToTop(_conv.id, text || null);
        // Actualizar status de conv si cambió a attending
        if (_conv.status === 'pending' && json.message.status === 'sent') {
          _conv.status   = 'attending';
          _conv.agent_id = window.PANEL_CONFIG.agentId;
          _renderActions();
          _updateComposer();
          const badge = el.headerBadge();
          if (badge) { badge.textContent = 'En atención'; badge.className = 'status-badge attending'; }
        }
      } else {
        showError(json.error || 'Error al enviar mensaje.');
        // Si hay mensaje fallido en respuesta, también mostrarlo
        if (json.message) appendMessage(json.message);
      }
    } catch (e) {
      showError('Error de red. Intenta de nuevo.');
    } finally {
      _sending = false;
      _disableComposer(false);
      const ta = el.textarea();
      if (ta) ta.focus();
    }
  }

  function _disableComposer(disable) {
    const ta   = el.textarea();
    const btn  = el.btnSend();
    if (ta)  ta.disabled = disable;
    if (btn) {
      btn.disabled = disable;
      btn.innerHTML = disable
        ? '<div class="spinner"></div>'
        : '<i class="fas fa-paper-plane"></i>';
    }
  }

  function showError(msg) {
    const e = el.inlineError();
    if (!e) return;
    e.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${_escHtml(msg)}`;
    e.classList.remove('hidden');
  }

  function clearError() {
    const e = el.inlineError();
    if (e) e.classList.add('hidden');
  }

  // ── Reactivar conversación ─────────────────────────────────
  async function reactivate() {
    if (!_conv) return;
    try {
      const res = await fetch('/api/reopen.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ conversationId: _conv.id }),
      });
      const json = await res.json();
      if (json.success) {
        _conv.status   = 'attending';
        _conv.agent_id = window.PANEL_CONFIG.agentId;
        _render();
        Notify.showToast('Conversación reactivada.', 'success');
      } else {
        Notify.showToast(json.error || 'Error al reactivar.', 'error');
      }
    } catch (_) {
      Notify.showToast('Error de red.', 'error');
    }
  }

  // ── Editar nombre del contacto ────────────────────────────
  function openRenameModal() {
    if (!_conv) return;
    const modal = document.getElementById('modal-rename');
    const input = document.getElementById('rename-input');
    if (!modal || !input) return;
    input.value = _conv.contact_name || '';
    modal.classList.add('open');
    setTimeout(() => { input.focus(); input.select(); }, 80);
  }

  function closeRenameModal() {
    const modal = document.getElementById('modal-rename');
    if (modal) modal.classList.remove('open');
  }

  async function doRename() {
    if (!_conv) return;
    const input = document.getElementById('rename-input');
    const name  = (input?.value || '').trim();
    if (!name) { input?.focus(); return; }

    try {
      const res  = await fetch('/api/update_contact.php', {
        method:      'POST',
        credentials: 'include',
        headers:     { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body:        JSON.stringify({ conversationId: _conv.id, contactName: name }),
      });
      const json = await res.json();

      if (json.success) {
        _conv.contact_name = name;
        // Actualizar header
        const nameEl = document.getElementById('chat-contact-name');
        if (nameEl) nameEl.textContent = name;
        // Actualizar ítem en la lista
        const convItem = document.querySelector(`.conv-item[data-conv-id="${_conv.id}"] .conv-name`);
        if (convItem) convItem.textContent = name;
        closeRenameModal();
        Notify.showToast('Nombre actualizado.', 'success');
      } else {
        Notify.showToast(json.error || 'Error al guardar.', 'error');
      }
    } catch (_) {
      Notify.showToast('Error de red.', 'error');
    }
  }

  // ── Transferencia (modal) ──────────────────────────────────
  let _selectedTransferAgentId   = null;
  let _selectedTransferAgentName = null;

  async function openTransferModal() {
    if (!_conv) return;
    const modal = document.getElementById('modal-transfer');
    if (!modal) return;

    // Reset selección
    _selectedTransferAgentId   = null;
    _selectedTransferAgentName = null;

    // Mostrar modal con spinner
    modal.classList.add('open');
    const list = document.getElementById('transfer-agents-list');
    if (list) {
      list.innerHTML = '<div style="padding:20px;text-align:center;color:var(--texto-suave);font-size:.85rem"><i class="fas fa-spinner fa-spin"></i> Cargando agentes...</div>';
    }

    // Cargar agentes en línea
    try {
      const deptId = _conv.department_id || 0;
      const res    = await fetch('/api/online_agents.php?dept_id=' + deptId, {
        credentials: 'include',
      });
      const json = await res.json();
      _renderTransferList(json.agents || []);
    } catch (_) {
      if (list) list.innerHTML = '<div style="padding:20px;text-align:center;color:#c0392b;font-size:.85rem">Error al cargar agentes.</div>';
    }
  }

  function _renderTransferList(agents) {
    const list = document.getElementById('transfer-agents-list');
    if (!list) return;

    if (agents.length === 0) {
      list.innerHTML = '<div style="padding:24px;text-align:center;color:var(--texto-suave);font-size:.85rem"><i class="fas fa-user-slash" style="font-size:1.5rem;opacity:.4;display:block;margin-bottom:8px"></i>No hay agentes en línea en este momento.</div>';
      return;
    }

    list.innerHTML = agents.map(a => `
      <div class="transfer-agent-row" data-id="${a.id}" data-name="${_escHtml(a.name)}"
           onclick="Chat.selectTransferAgent(${a.id}, '${_escHtml(a.name)}', this)">
        <span class="online-dot online" style="flex-shrink:0"></span>
        <span class="transfer-agent-name">${_escHtml(a.name)}</span>
        ${a.role === 'supervisor'
          ? '<span class="transfer-agent-role">Supervisor</span>'
          : '<span class="transfer-agent-role">Agente</span>'}
        <i class="fas fa-check transfer-check hidden"></i>
      </div>`).join('');
  }

  function selectTransferAgent(id, name, rowEl) {
    _selectedTransferAgentId   = id;
    _selectedTransferAgentName = name;

    // Marcar selección visualmente
    document.querySelectorAll('.transfer-agent-row').forEach(r => {
      r.classList.remove('selected');
      const chk = r.querySelector('.transfer-check');
      if (chk) chk.classList.add('hidden');
    });
    rowEl.classList.add('selected');
    const chk = rowEl.querySelector('.transfer-check');
    if (chk) chk.classList.remove('hidden');

    // Habilitar botón confirmar
    const btn = document.getElementById('btn-transfer-confirm');
    if (btn) btn.disabled = false;
  }

  function closeTransferModal() {
    const modal = document.getElementById('modal-transfer');
    if (modal) modal.classList.remove('open');
    _selectedTransferAgentId   = null;
    _selectedTransferAgentName = null;
  }

  async function doTransfer() {
    if (!_selectedTransferAgentId || !_conv) return;

    const btn = document.getElementById('btn-transfer-confirm');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Transfiriendo...'; }

    try {
      const res  = await fetch('/api/transfer.php', {
        method:      'POST',
        credentials: 'include',
        headers:     { 'Content-Type': 'application/json' },
        body:        JSON.stringify({
          conversationId: _conv.id,
          targetAgentId:  _selectedTransferAgentId,
        }),
      });
      const json = await res.json();

      if (json.success) {
        closeTransferModal();
        Notify.showToast('Conversación transferida a ' + _selectedTransferAgentName + '.', 'success');
        await load(_conv.id);
        App.loadConversations();
      } else {
        Notify.showToast(json.error || 'Error al transferir.', 'error');
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-exchange-alt"></i> Transferir'; }
      }
    } catch (_) {
      Notify.showToast('Error de red.', 'error');
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-exchange-alt"></i> Transferir'; }
    }
  }

  // ── Modal de imagen ────────────────────────────────────────
  function openImageModal(url) {
    const overlay = document.createElement('div');
    overlay.className = 'img-modal-overlay';
    overlay.innerHTML = `<img src="${_escAttr(url)}" alt="imagen">`;
    overlay.onclick = () => overlay.remove();
    document.body.appendChild(overlay);
  }

  // ── Attach menu ────────────────────────────────────────────
  function toggleAttachMenu() {
    const m = el.attachMenu();
    if (m) m.classList.toggle('open');
  }

  function closeAttachMenu() {
    const m = el.attachMenu();
    if (m) m.classList.remove('open');
  }

  function toggleEmojiPicker() {
    const picker = document.getElementById('emoji-picker');
    if (!picker) return;

    if (picker.classList.contains('hidden')) {
      closeAttachMenu();
      if (picker.innerHTML === '') initEmojiPicker();
      picker.classList.remove('hidden');
    } else {
      picker.classList.add('hidden');
    }
  }

  function initEmojiPicker() {
    const picker = document.getElementById('emoji-picker');
    if (!picker) return;

    const categories = [
      { name: 'Recientes', icon: 'fa-clock', emojis: [] },
      { name: 'Caritas', icon: 'fa-grin', emojis: ['😀','😃','😄','😁','😆','😅','🤣','😂','🙂','🙃','😉','😊','😇','🥰','😍','🤩','😘','😗','😚','😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','🤐','🤨','😐','😑','😶','😏','😒','🙄','😬','🤥','😌','😔','😪','🤤','😴','😷','🤒','🤕','🤢','🤮','🥵','🥶','🥴','😵','🤯','🤠','🥳','🥸','😎','🤓','🧐','😕','😟','🙁','😮','😯','😲','😳','🥺','😦','😧','😨','😰','😥','😢','😭','😱','😖','😣','😞','😓','😩','😫','🥱','😤','😡','😠','🤬','😈','👿','💀','☠️','💩','🤡','👹','👺','👻','👽','👾','🤖'] },
      { name: 'Gestos', icon: 'fa-hand-paper', emojis: ['👍','👎','👊','✊','🤛','🤜','🤝','👏','🙌','👐','🤲','🤞','✌️','🤟','🤘','🤙','👈','👉','👆','👇','☝️','✋','🤚','🖐','🖖','👋','🤏','✍️','🙏','💪','🦾','🦿','🦵','🦶','👂','👃','🧠','🫀','🫁','🦷','🦴','👀','👁️','👅','👄'] },
      { name: 'Corazones', icon: 'fa-heart', emojis: ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝','💟','♥️'] },
      { name: 'Objetos', icon: 'fa-lightbulb', emojis: ['💼','📁','📂','📅','📆','📊','📈','📉','📋','📌','📎','🔗','📝','✏️','🔍','🔎','💡','🔔','📣','💬','💭','🗯','♠️','♣️','♥️','♦️','🎯','🎮','🎲','🧩','🔮','🛍','📱','💻','⌨️','🖥','🖨','💾','💿','📀','🎬','📷','📹','🎥','📽','🎞','📞','☎️','📟','📠','📺','📻','🧭','⏰','⏱','⏲','⏳','⌛','🔑','🗝','🔒','🔓','🔐','🔏'] },
      { name: 'Símbolos', icon: 'fa-star', emojis: ['✅','❌','❓','❗','‼️','⁉️','💯','🔴','🟠','🟡','🟢','🔵','🟣','⚫','⚪','🟤','🔶','🔷','🔸','🔹','💠','🔘','🔳','🔲','▪️','▫️','◾','◽','◼️','◻️','🟥','🟧','🟨','🟩','🟦','🟪','⬛','⬜','🟫','🏧','♻️','⚜️','🔱','📛','🔰','♟','🃏','🎴','🀄','🕐','🕑','🕒','🕓','🕔','🕕','🕖','🕗','🕘','🕙','🕚','🕛'] },
    ];

    let html = '<div class="emoji-categories">';
    categories.forEach((cat, i) => {
      html += `<button class="emoji-cat-btn ${i === 1 ? 'active' : ''}" data-cat="${i}" title="${cat.name}">
        <i class="fas ${cat.icon}"></i>
      </button>`;
    });
    html += '</div><div class="emoji-grid" id="emoji-grid"></div>';

    picker.innerHTML = html;

    picker.querySelectorAll('.emoji-cat-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        picker.querySelectorAll('.emoji-cat-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderEmojiCategory(parseInt(btn.dataset.cat));
      });
    });

    renderEmojiCategory(1);
  }

  function renderEmojiCategory(catIdx) {
    const categories = [
      { emojis: [] },
      { emojis: ['😀','😃','😄','😁','😆','😅','🤣','😂','🙂','🙃','😉','😊','😇','🥰','😍','🤩','😘','😗','😚','😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','🤐','🤨','😐','😑','😶','😏','😒','🙄','😬','🤥','😌','😔','😪','🤤','😴','😷','🤒','🤕','🤢','🤮','🥵','🥶','🥴','😵','🤯','🤠','🥳','🥸','😎','🤓','🧐','😕','😟','🙁','😮','😯','😲','😳','🥺','😦','😧','😨','😰','😥','😢','😭','😱','😖','😣','😞','😓','😩','😫','🥱','😤','😡','😠','🤬','😈','👿','💀','☠️','💩','🤡','👹','👺','👻','👽','👾','🤖'] },
      { emojis: ['👍','👎','👊','✊','🤛','🤜','🤝','👏','🙌','👐','🤲','🤞','✌️','🤟','🤘','🤙','👈','👉','👆','👇','☝️','✋','🤚','🖐','🖖','👋','🤏','✍️','🙏','💪','🦾','🦿','🦵','🦶','👂','👃','🧠','🫀','🫁','🦷','🦴','👀','👁️','👅','👄'] },
      { emojis: ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝','💟','♥️'] },
      { emojis: ['💼','📁','📂','📅','📆','📊','📈','📉','📋','📌','📎','🔗','📝','✏️','🔍','🔎','💡','🔔','📣','💬','💭','🗯','♠️','♣️','♥️','♦️','🎯','🎮','🎲','🧩','🔮','🛍','📱','💻','⌨️','🖥','🖨','💾','💿','📀','🎬','📷','📹','🎥','📽','🎞','📞','☎️','📟','📠','📺','📻','🧭','⏰','⏱','⏲','⏳','⌛','🔑','🗝','🔒','🔓','🔐','🔏'] },
      { emojis: ['✅','❌','❓','❗','‼️','⁉️','💯','🔴','🟠','🟡','🟢','🔵','🟣','⚫','⚪','🟤','🔶','🔷','🔸','🔹','💠','🔘','🔳','🔲','▪️','▫️','◾','◽','◼️','◻️','🟥','🟧','🟨','🟩','🟦','🟪','⬛','⬜','🟫','🏧','♻️','⚜️','🔱','📛','🔰','♟','🃏','🎴','🀄','🕐','🕑','🕒','🕓','🕔','🕕','�️','🕗','🕘','🕙','🕚','🕛'] },
    ];

    const grid = document.getElementById('emoji-grid');
    if (!grid) return;

    grid.innerHTML = categories[catIdx].emojis.map(e =>
      `<button class="emoji-btn" onclick="Chat.insertEmoji('${e}')">${e}</button>`
    ).join('');
  }

  function insertEmoji(emoji) {
    const ta = el.textarea();
    if (!ta) return;

    const start = ta.selectionStart;
    const end = ta.selectionEnd;
    const text = ta.value;

    ta.value = text.substring(0, start) + emoji + text.substring(end);
    ta.selectionStart = ta.selectionEnd = start + emoji.length;
    ta.focus();

    _autoResize(ta);
    document.getElementById('emoji-picker')?.classList.add('hidden');
  }

  function triggerFileInput(type) {
    closeAttachMenu();
    const input = document.createElement('input');
    input.type  = 'file';
    input.accept = type === 'image'
      ? 'image/jpeg,image/png,image/gif,image/webp'
      : '.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,application/pdf,application/msword';

    input.onchange = () => {
      if (input.files && input.files[0]) {
        handleFileSelect(input.files[0], type);
      }
    };
    input.click();
  }

  // ── Auto-resize textarea ───────────────────────────────────
  function _autoResize(ta) {
    ta.style.height = 'auto';
    ta.style.height = Math.min(ta.scrollHeight, 110) + 'px';
  }

  // ── Linkify: detecta URLs y emails y los convierte en <a> ──
  function _linkify(text) {
    if (!text) return '';
    const re = /(https?:\/\/[^\s<>"']+|www\.[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}[^\s<>"']*|[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/g;

    const parts = [];
    let last = 0, m;
    while ((m = re.exec(text)) !== null) {
      if (m.index > last) parts.push({ t: 'txt', v: text.slice(last, m.index) });
      // Quitar puntuación final que no es parte del enlace
      const trailMatch = m[0].match(/[.,;:!?)\]>]+$/);
      const trail = trailMatch ? trailMatch[0] : '';
      const raw   = m[0].slice(0, m[0].length - trail.length);
      parts.push({ t: 'url', v: raw });
      if (trail) parts.push({ t: 'txt', v: trail });
      last = m.index + m[0].length;
    }
    if (last < text.length) parts.push({ t: 'txt', v: text.slice(last) });

    return parts.map(p => {
      if (p.t === 'txt') return _escHtml(p.v);
      const isEmail = p.v.includes('@') && !p.v.startsWith('http');
      const href    = isEmail ? 'mailto:' + p.v
                              : (p.v.startsWith('www.') ? 'https://' + p.v : p.v);
      return `<a href="${_escAttr(href)}" target="_blank" rel="noopener noreferrer" class="msg-link">${_escHtml(p.v)}</a>`;
    }).join('');
  }

  // ── Helpers ────────────────────────────────────────────────
  function _escHtml(s) {
    return String(s)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  function _escAttr(s) { return String(s).replace(/"/g,'&quot;'); }

  function _formatFileSize(bytes) {
    if (bytes < 1024)          return bytes + ' B';
    if (bytes < 1024*1024)     return (bytes/1024).toFixed(1) + ' KB';
    return (bytes/1024/1024).toFixed(1) + ' MB';
  }

  // ── Estado actual ──────────────────────────────────────────
  function getConv()    { return _conv; }
  function getConvId()  { return _conv ? _conv.id : null; }

  // ── Init listeners (llamado desde index.php) ───────────────
  function initListeners() {
    const ta = el.textarea();
    if (ta) {
      ta.addEventListener('input', () => _autoResize(ta));
      ta.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          handleSend();
        }
      });
    }

    // Cerrar attach/transfer menu al click fuera
    document.addEventListener('click', e => {
      const attachMenu   = el.attachMenu();
      const btnAtt       = document.getElementById('btn-attach');
      if (attachMenu && !attachMenu.contains(e.target) && e.target !== btnAtt) {
        attachMenu.classList.remove('open');
      }

      const transferMenu = document.getElementById('transfer-menu');
      const btnTransfer  = document.querySelector('.btn-transfer');
      if (transferMenu && !transferMenu.contains(e.target) &&
          e.target !== btnTransfer && !btnTransfer?.contains(e.target)) {
        transferMenu.classList.remove('open');
      }
    });
  }

  return {
    load,
    renderMessages,
    appendMessage,
    renderBubble,
    handleFileSelect,
    handleSend,
    removeFile,
    showError,
    lockChat: _updateComposer,
    unlockChat: reactivate,
    reactivate,
    openImageModal,
    toggleAttachMenu,
    closeAttachMenu,
    triggerFileInput,
    toggleEmojiPicker,
    insertEmoji,
    toggleInfo,
    openRenameModal,
    closeRenameModal,
    doRename,
    openTransferModal,
    closeTransferModal,
    doTransfer,
    selectTransferAgent,
    getConv,
    getConvId,
    initListeners,
    updateConv: (conv) => {
      if (_conv && conv.id === _conv.id) {
        _conv = Object.assign(_conv, conv);
        _render();
      }
    },
  };
})();
