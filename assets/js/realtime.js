/**
 * realtime.js — Clase Realtime con SSE + fallback a long-polling.
 */

class Realtime {
  constructor(token, onMessage, onConvUpdate, onNotification) {
    this._token          = token;
    this._onMessage      = onMessage;
    this._onConvUpdate   = onConvUpdate;
    this._onNotification = onNotification;

    this._es          = null;
    this._pollTimer   = null;
    this._pollSince   = Date.now() / 1000 - 5;
    this._backoff     = 1000;  // ms para reconexión SSE
    this._maxBackoff  = 30000;
    this._useSSE      = true;
    this._running     = false;
  }

  start() {
    this._running = true;
    this._connectSSE();
  }

  stop() {
    this._running = false;
    if (this._es) {
      this._es.close();
      this._es = null;
    }
    clearTimeout(this._pollTimer);
  }

  // ── SSE ─────────────────────────────────────────────────────
  _connectSSE() {
    if (!this._running) return;
    if (!window.EventSource) {
      this._fallbackToPoll();
      return;
    }

    const url = '/sse.php?token=' + encodeURIComponent(this._token);

    try {
      this._es = new EventSource(url);
    } catch (e) {
      this._fallbackToPoll();
      return;
    }

    this._es.addEventListener('connected', () => {
      this._backoff = 1000;
      // SSE funciona, cancelar polling si lo había
      clearTimeout(this._pollTimer);
    });

    this._es.addEventListener('new_message', (e) => {
      try {
        const data = JSON.parse(e.data);
        this._onMessage(data);
        this._pollSince = Date.now() / 1000;
      } catch (_) {}
    });

    this._es.addEventListener('conversation_updated', (e) => {
      try {
        const data = JSON.parse(e.data);
        this._onConvUpdate(data);
        this._pollSince = Date.now() / 1000;
      } catch (_) {}
    });

    this._es.addEventListener('notification', (e) => {
      try {
        const data = JSON.parse(e.data);
        this._onNotification(data);
        this._pollSince = Date.now() / 1000;
      } catch (_) {}
    });

    this._es.onerror = () => {
      this._es.close();
      this._es = null;
      // Reconexión con backoff
      const delay = this._backoff;
      this._backoff = Math.min(this._backoff * 2, this._maxBackoff);
      // Mientras SSE no reconnecta, usar polling
      this._startPollCycle();
      setTimeout(() => {
        if (this._running) this._connectSSE();
      }, delay);
    };
  }

  // ── Polling (fallback) ─────────────────────────────────────
  _fallbackToPoll() {
    this._useSSE = false;
    this._startPollCycle();
  }

  _startPollCycle() {
    clearTimeout(this._pollTimer);
    if (!this._running) return;
    this._doPoll();
  }

  async _doPoll() {
    if (!this._running) return;

    try {
      const url = '/api/poll.php?since=' + this._pollSince;
      const res = await fetch(url, {
        credentials: 'include',
        headers: { 'Accept': 'application/json' },
      });

      if (res.ok) {
        const json = await res.json();
        if (json.success) {
          this._pollSince = json.timestamp || (Date.now() / 1000);

          (json.messages || []).forEach(m => this._onMessage(m));
          (json.conversations || []).forEach(c => this._onConvUpdate(c));
          (json.notifications || []).forEach(n => this._onNotification(n));
        }
      }
    } catch (_) {}

    // Si SSE está conectado, no seguir haciendo poll
    if (!this._es || this._es.readyState === EventSource.CLOSED) {
      this._pollTimer = setTimeout(() => this._doPoll(), 4000);
    }
  }
}
