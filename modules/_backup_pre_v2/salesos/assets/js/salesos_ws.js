/**
 * SalesOS WebSocket Client — salesos_ws.js
 *
 * Manages the real-time WS connection to ws_server.php.
 * Emits typed events via a lightweight EventEmitter so UI components
 * can subscribe without coupling to this module's internals.
 *
 * Usage (included by the Realtime view):
 *
 *   const ws = new SalesOsWs({ tokenUrl: '/salesos/realtime/token' });
 *   ws.on('call.bridged',   (event) => { ... });
 *   ws.on('agent.ready',    (event) => { ... });
 *   ws.on('queue.*',        (event) => { ... }); // wildcard prefix
 *   ws.on('snapshot:calls', (data)  => { ... }); // initial snapshot
 *   ws.connect();
 */

(function (global) {
    'use strict';

    // ── Tiny EventEmitter ──────────────────────────────────────────────────────

    function EventEmitter() {
        this._handlers = {};
    }

    EventEmitter.prototype.on = function (event, fn) {
        if (!this._handlers[event]) this._handlers[event] = [];
        this._handlers[event].push(fn);
        return this;
    };

    EventEmitter.prototype.off = function (event, fn) {
        if (!this._handlers[event]) return this;
        this._handlers[event] = this._handlers[event].filter(function (h) { return h !== fn; });
        return this;
    };

    EventEmitter.prototype.emit = function (event, data) {
        var handlers = (this._handlers[event] || []).concat(this._handlers['*'] || []);
        for (var i = 0; i < handlers.length; i++) {
            try { handlers[i](data, event); } catch (e) { console.error('[SalesOsWs] handler error:', e); }
        }
        // Wildcard prefix matching: "call.*" matches "call.bridged" etc.
        var prefix = event.split('.')[0];
        var wildcardKey = prefix + '.*';
        if (wildcardKey !== event) {
            var wildcardHandlers = this._handlers[wildcardKey] || [];
            for (var j = 0; j < wildcardHandlers.length; j++) {
                try { wildcardHandlers[j](data, event); } catch (e) { console.error('[SalesOsWs] wildcard handler error:', e); }
            }
        }
    };

    // ── SalesOsWs ──────────────────────────────────────────────────────────────

    /**
     * @param {object} opts
     * @param {string} opts.tokenUrl         - URL to fetch a WS auth token (GET, JSON)
     * @param {string} [opts.wsUrl]          - Override ws:// URL (if not set, fetched from token response)
     * @param {number} [opts.reconnectDelay] - Base reconnect delay in ms (default 2000, max 30000)
     * @param {number} [opts.pingInterval]   - Ping interval in ms (default 25000)
     * @param {number} [opts.pollInterval]   - REST fallback poll interval in ms (default 5000)
     * @param {boolean}[opts.debug]          - Enable verbose console logging
     */
    function SalesOsWs(opts) {
        EventEmitter.call(this);

        this._tokenUrl      = opts.tokenUrl;
        this._wsUrl         = opts.wsUrl || null;
        this._reconnectBase = opts.reconnectDelay || 2000;
        this._reconnectMax  = 30000;
        this._pingInterval  = opts.pingInterval  || 25000;
        this._pollInterval  = opts.pollInterval  || 5000;
        this._debug         = opts.debug || false;

        this._ws            = null;
        this._token         = null;
        this._pingTimer     = null;
        this._reconnTimer   = null;
        this._pollTimer     = null;
        this._reconnAttempt = 0;
        this._connected     = false;
        this._destroyed     = false;
        this._usingPoll     = false;
    }

    SalesOsWs.prototype = Object.create(EventEmitter.prototype);
    SalesOsWs.prototype.constructor = SalesOsWs;

    // Public: start the connection
    SalesOsWs.prototype.connect = function () {
        if (this._destroyed) return;
        this._log('Connecting…');
        this._fetchTokenAndOpen();
    };

    // Public: graceful teardown
    SalesOsWs.prototype.destroy = function () {
        this._destroyed = true;
        this._clearTimers();
        if (this._ws) {
            this._ws.onclose = null;  // prevent reconnect loop
            this._ws.close();
            this._ws = null;
        }
    };

    // Public: current connection state
    SalesOsWs.prototype.isConnected = function () {
        return this._connected;
    };

    // ── Internal ───────────────────────────────────────────────────────────────

    SalesOsWs.prototype._log = function (msg) {
        if (this._debug) console.log('[SalesOsWs]', msg);
    };

    SalesOsWs.prototype._clearTimers = function () {
        if (this._pingTimer)  { clearInterval(this._pingTimer);  this._pingTimer  = null; }
        if (this._reconnTimer){ clearTimeout(this._reconnTimer); this._reconnTimer = null; }
        if (this._pollTimer)  { clearInterval(this._pollTimer);  this._pollTimer  = null; }
    };

    SalesOsWs.prototype._fetchTokenAndOpen = function () {
        var self = this;
        fetch(this._tokenUrl, { credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (body) {
                if (!body.success) throw new Error('Token fetch failed');
                self._token = body.token;
                var wsUrl   = self._wsUrl || body.ws_url;
                if (!wsUrl) throw new Error('ws_url not configured');
                self._openSocket(wsUrl);
            })
            .catch(function (err) {
                self._log('Token fetch error: ' + err.message);
                self._scheduleReconnect();
                // Fall back to REST polling if WS setup fails repeatedly
                if (self._reconnAttempt >= 3 && !self._usingPoll) {
                    self._startPolling();
                }
            });
    };

    SalesOsWs.prototype._openSocket = function (url) {
        if (this._destroyed) return;
        this._log('Opening WebSocket: ' + url);

        var self = this;
        var ws   = new WebSocket(url);
        this._ws = ws;

        ws.onopen = function () {
            self._log('WebSocket open — sending auth');
            ws.send(JSON.stringify({ type: 'auth', token: self._token }));
        };

        ws.onmessage = function (ev) {
            var msg;
            try { msg = JSON.parse(ev.data); } catch (e) { return; }
            self._onMessage(msg);
        };

        ws.onerror = function (e) {
            self._log('WebSocket error');
            self.emit('ws.error', {});
        };

        ws.onclose = function (ev) {
            self._log('WebSocket closed: code=' + ev.code);
            self._connected = false;
            self._clearTimers();
            self.emit('ws.disconnected', { code: ev.code });
            if (!self._destroyed) {
                self._scheduleReconnect();
                // Switch to REST polling after 5 failed reconnects
                if (self._reconnAttempt >= 5 && !self._usingPoll) {
                    self._startPolling();
                }
            }
        };
    };

    SalesOsWs.prototype._onMessage = function (msg) {
        var type = msg.type;

        if (type === 'auth_ok') {
            this._log('Authenticated as staff_id=' + msg.staff_id);
            this._connected  = true;
            this._reconnAttempt = 0;
            this._usingPoll  = false;
            this._stopPolling();
            this._startPing();
            this.emit('ws.connected', { staff_id: msg.staff_id });
            return;
        }

        if (type === 'auth_fail') {
            this._log('Auth failed: ' + msg.reason);
            this.emit('ws.auth_fail', msg);
            this._ws && this._ws.close();
            return;
        }

        if (type === 'pong') {
            this._log('Pong received');
            return;
        }

        if (type === 'snapshot') {
            this._log('Snapshot: ' + msg.scope + ' (' + Object.keys(msg.data || {}).length + ' entries)');
            this.emit('snapshot:' + msg.scope, msg.data);
            return;
        }

        if (type === 'event') {
            var event = msg.data || {};
            var event_type = event.event_type || '';
            this._log('Event: ' + event_type);
            this.emit(event_type, event);
            return;
        }

        if (type === 'error') {
            this.emit('ws.server_error', msg);
            return;
        }
    };

    SalesOsWs.prototype._startPing = function () {
        var self = this;
        this._pingTimer = setInterval(function () {
            if (self._ws && self._ws.readyState === WebSocket.OPEN) {
                self._ws.send(JSON.stringify({ type: 'ping' }));
            }
        }, this._pingInterval);
    };

    SalesOsWs.prototype._scheduleReconnect = function () {
        if (this._destroyed) return;
        this._reconnAttempt++;
        // Exponential backoff with jitter
        var delay = Math.min(
            this._reconnectBase * Math.pow(1.5, this._reconnAttempt - 1),
            this._reconnectMax
        );
        delay += Math.random() * 1000;
        this._log('Reconnect #' + this._reconnAttempt + ' in ' + Math.round(delay) + 'ms');
        var self = this;
        this._reconnTimer = setTimeout(function () {
            self._fetchTokenAndOpen();
        }, delay);
    };

    // ── REST polling fallback ──────────────────────────────────────────────────

    SalesOsWs.prototype._startPolling = function () {
        if (this._usingPoll) return;
        this._log('Switching to REST polling fallback');
        this._usingPoll = true;
        this.emit('ws.polling_fallback', {});

        var self = this;
        this._pollTimer = setInterval(function () {
            self._pollState();
        }, this._pollInterval);
        this._pollState();
    };

    SalesOsWs.prototype._stopPolling = function () {
        if (this._pollTimer) {
            clearInterval(this._pollTimer);
            this._pollTimer = null;
        }
        this._usingPoll = false;
    };

    SalesOsWs.prototype._pollState = function () {
        var self = this;
        var stateUrl = this._tokenUrl.replace('/token', '/state');
        fetch(stateUrl, { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (body) {
                if (!body.success) return;
                if (body.calls)  self.emit('snapshot:calls',  body.calls);
                if (body.agents) self.emit('snapshot:agents', body.agents);
                if (body.queues) self.emit('snapshot:queues', body.queues);
            })
            .catch(function () {});
    };

    // ── Incoming call popup helper ─────────────────────────────────────────────

    SalesOsWs.prototype.showIncomingPopup = function (event) {
        var caller = event.src        || 'Unknown';
        var entity = event.entity_name || '';
        var uid    = event.call_uid   || '';

        var el = document.getElementById('salesos-incoming-call');
        if (!el) {
            el = document.createElement('div');
            el.id = 'salesos-incoming-call';
            el.style.cssText = [
                'position:fixed', 'top:20px', 'right:20px', 'z-index:99999',
                'background:#fff', 'border:2px solid #4a90d9', 'border-radius:8px',
                'padding:16px 20px', 'box-shadow:0 4px 20px rgba(0,0,0,0.25)',
                'min-width:280px', 'font-family:sans-serif',
            ].join(';');
            document.body.appendChild(el);
        }

        el.innerHTML =
            '<div style="font-weight:bold;color:#4a90d9;margin-bottom:4px">Incoming Call</div>' +
            '<div style="font-size:18px;margin-bottom:4px">' + _esc(caller) + '</div>' +
            (entity ? '<div style="color:#666;margin-bottom:8px">' + _esc(entity) + '</div>' : '') +
            '<button onclick="document.getElementById(\'salesos-incoming-call\').remove()"' +
            ' style="background:#4a90d9;color:#fff;border:none;border-radius:4px;padding:6px 14px;cursor:pointer">Dismiss</button>';

        el.setAttribute('data-uid', uid);
        el.style.display = 'block';

        // Auto-dismiss after 30s
        clearTimeout(el._dismissTimer);
        el._dismissTimer = setTimeout(function () {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 30000);
    };

    SalesOsWs.prototype.hideIncomingPopup = function (uid) {
        var el = document.getElementById('salesos-incoming-call');
        if (el && (!uid || el.getAttribute('data-uid') === uid)) {
            el.parentNode && el.parentNode.removeChild(el);
        }
    };

    function _esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── Export ─────────────────────────────────────────────────────────────────

    global.SalesOsWs = SalesOsWs;

}(typeof window !== 'undefined' ? window : this));
