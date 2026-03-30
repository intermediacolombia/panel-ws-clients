/**
 * notify.js — Notificaciones del navegador, sonido y título del tab.
 */

const Notify = (() => {
  let _pendingCount = 0;
  const _origTitle = document.title.replace(/^\(\d+\)\s*/, '');

  // ── Permiso de notificaciones ──────────────────────────────
  function requestPermission() {
    if (!('Notification' in window)) return;
    if (Notification.permission === 'default') {
      Notification.requestPermission().catch(() => {});
    }
  }

  // ── Sonido via AudioContext ────────────────────────────────
  function playSound() {
    try {
      const ctx  = new (window.AudioContext || window.webkitAudioContext)();
      const osc  = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.type = 'sine';
      osc.frequency.setValueAtTime(880, ctx.currentTime);
      gain.gain.setValueAtTime(0.15, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
      osc.start(ctx.currentTime);
      osc.stop(ctx.currentTime + 0.4);
      osc.onended = () => ctx.close();
    } catch (_) {
      // AudioContext puede fallar si no hay interacción previa del usuario
    }
  }

  // ── Notificación de navegador ──────────────────────────────
  function showNotification(title, body, convId) {
    if (!('Notification' in window)) return;
    if (Notification.permission !== 'granted') return;

    const notif = new Notification(title, {
      body: body,
      icon: '/assets/img/icon-wa.png',
      tag:  convId ? 'conv-' + convId : 'panel',
    });

    notif.onclick = () => {
      window.focus();
      if (convId && window.App) {
        App.openConversation(convId);
      }
      notif.close();
    };

    setTimeout(() => notif.close(), 6000);
  }

  // ── Título del tab ─────────────────────────────────────────
  function updateTitle(n) {
    _pendingCount = Math.max(0, n);
    if (_pendingCount > 0) {
      document.title = '(' + _pendingCount + ') ' + _origTitle;
    } else {
      document.title = _origTitle;
    }
  }

  function markTabActive() {
    document.title = _origTitle;
  }

  // ── Toast en pantalla ──────────────────────────────────────
  function showToast(message, type = 'info', duration = 3500) {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.className = 'toast-container';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(10px)';
      toast.style.transition = 'opacity 0.3s, transform 0.3s';
      setTimeout(() => toast.remove(), 320);
    }, duration);
  }

  // ── Gestión de badge de notificaciones ────────────────────
  function updateNotifBadge(count) {
    const badge = document.getElementById('notif-badge');
    if (!badge) return;
    if (count > 0) {
      badge.textContent = count > 99 ? '99+' : String(count);
      badge.classList.remove('hidden');
    } else {
      badge.classList.add('hidden');
    }
  }

  // ── Evento: tab activo ────────────────────────────────────
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) markTabActive();
  });

  return {
    requestPermission,
    playSound,
    showNotification,
    updateTitle,
    markTabActive,
    showToast,
    updateNotifBadge,
  };
})();
