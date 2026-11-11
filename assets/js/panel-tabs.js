/** panel-tabs.js — Una pestaña activa por agente y panel en este navegador. */
const PanelTabs = (() => {
  const id = window.crypto?.randomUUID?.() || Date.now() + '-' + Math.random();
  const key = 'panel-active-tab:' + location.pathname + ':' + window.PANEL_CONFIG.agentId;
  let active = false;
  let initialized = false;
  let pending = false;
  let overlay;
  let button;
  let channel;

  function read() {
    try { return JSON.parse(localStorage.getItem(key) || 'null'); }
    catch (_) { return null; }
  }

  function write() {
    localStorage.setItem(key, JSON.stringify({ id, time: Date.now() }));
  }

  function setActive(value) {
    active = value;
    overlay.hidden = value;
    document.querySelectorAll('body > :not(script):not(#panel-tab-dialog)').forEach(element => {
      element.inert = !value;
    });
    if (value) {
      if (!initialized) {
        initialized = true;
        App.init();
      } else {
        App.resumePanel();
      }
    } else {
      if (initialized) App.pausePanel();
      button.focus();
    }
  }

  function pause() {
    if (active) setActive(false);
  }

  function checkOwner() {
    if (active && read()?.id !== id) pause();
  }

  function activate() {
    write();
    setActive(true);
  }

  async function claim(force = false) {
    if (pending || active) return;
    pending = true;
    button.disabled = true;
    try {
      // No esperar a que una pestaña suspendida libere un bloqueo del navegador.
      const owner = read();
      if (force || !owner || owner.id === id) {
        activate();
        channel?.postMessage({ claim: id });
      }
    } catch (_) {
      pending = false;
      button.disabled = false;
      overlay.querySelector('p').textContent = 'Permite el almacenamiento del navegador para usar el panel en una sola pestaña.';
    } finally {
      pending = false;
      button.disabled = false;
    }
  }

  function init() {
    overlay = document.createElement('div');
    overlay.id = 'panel-tab-dialog';
    overlay.className = 'panel-tab-dialog';
    overlay.innerHTML = `<div class="panel-tab-dialog-box" role="alertdialog" aria-modal="true" aria-labelledby="panel-tab-title" aria-describedby="panel-tab-description">
      <i class="fas fa-desktop" aria-hidden="true"></i>
      <h2 id="panel-tab-title">El panel está abierto en otra pestaña</h2>
      <p id="panel-tab-description">El panel de agentes está siendo usado en otra pestaña o ventana. Pulsa «Usar aquí» para continuar en esta.</p>
      <div class="panel-tab-dialog-actions">
        <a class="btn-secondary" href="https://intermediahost.co">Cerrar</a>
        <button class="btn-primary" type="button" id="panel-tab-use-here">Usar aquí</button>
      </div>
    </div>`;
    document.body.appendChild(overlay);
    button = document.getElementById('panel-tab-use-here');
    button.addEventListener('click', () => claim(true));
    overlay.addEventListener('keydown', event => {
      if (event.key !== 'Tab') return;
      const first = overlay.querySelector('a');
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault(); button.focus();
      } else if (!event.shiftKey && document.activeElement === button) {
        event.preventDefault(); first.focus();
      }
    });
    if (window.BroadcastChannel) {
      channel = new BroadcastChannel(key);
      channel.onmessage = checkOwner;
    }
    window.addEventListener('storage', event => { if (event.key === key) checkOwner(); });
    window.addEventListener('focus', checkOwner);
    document.addEventListener('visibilitychange', checkOwner);
    window.addEventListener('pagehide', () => {
      if (active && read()?.id === id) localStorage.removeItem(key);
      pause();
    });
    window.addEventListener('pageshow', event => { if (event.persisted) claim(); });
    setActive(false);
    // Una pestaña recién abierta toma el control; la anterior muestra el aviso.
    claim(true);
  }

  return { init, isActive: () => active };
})();
