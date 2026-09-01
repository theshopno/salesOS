/**
 * SalesOS WebRTC Softphone — Phase 3B
 *
 * Loads SIP.js, auto-registers the logged-in agent's browser extension,
 * and provides SalesOS.call() as the transport abstraction:
 *   - Registered  → outbound WebRTC SIP INVITE via Asterisk
 *   - Unregistered → falls back to existing SalesOS.originate() (AMI)
 */
(function (global) {
    'use strict';

    var SIPJS_CDN = 'https://cdn.jsdelivr.net/npm/sip.js@0.21.2/lib/index.js';

    // ── SalesOSWebRTC ─────────────────────────────────────────────────────────

    function SalesOSWebRTC() {
        this._ua           = null;
        this._registerer   = null;
        this._session      = null;
        this._incoming     = null;   // pending inbound Invitation
        this._registered   = false;
        this._config       = null;
        this._timerHandle  = null;
        this._callSeconds  = 0;
        this._muted        = false;
        this._UserAgent    = null;
        this._Inviter      = null;
        this._SessionState = null;
    }

    SalesOSWebRTC.prototype.isRegistered = function () {
        return this._registered;
    };

    // ── Init: fetch config → load SIP.js → register ───────────────────────────

    SalesOSWebRTC.prototype.init = function () {
        if (!global.SalesOS || !global.SalesOS.webrtcEnabled) return;
        var self = this;
        fetch(global.SalesOS.apiBase + 'webrtc_config', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (cfg) {
                if (!cfg.success || !cfg.sip_password) {
                    console.info('[SalesOSWebRTC] No SIP password set for this agent — WebRTC disabled');
                    return;
                }
                self._config = cfg;
                self._loadAndRegister(cfg);
            })
            .catch(function (err) {
                console.warn('[SalesOSWebRTC] Config fetch failed:', err.message);
            });
    };

    SalesOSWebRTC.prototype._loadAndRegister = function (cfg) {
        var self = this;
        import(SIPJS_CDN).then(function (mod) {
            self._UserAgent    = mod.UserAgent;
            self._Inviter      = mod.Inviter;
            self._SessionState = mod.SessionState;

            var Registerer      = mod.Registerer;
            var RegistererState = mod.RegistererState;

            var parsedUri = mod.UserAgent.makeURI(cfg.sip_uri);
            if (!parsedUri) {
                console.error('[SalesOSWebRTC] Bad sip_uri:', cfg.sip_uri);
                return;
            }

            var Invitation = mod.Invitation;

            self._ua = new mod.UserAgent({
                uri:                   parsedUri,
                transportOptions:      { server: cfg.ws_server },
                authorizationUsername: cfg.extension,
                authorizationPassword: cfg.sip_password,
                displayName:           cfg.display_name,
                userAgentString:       'SalesOS-WebRTC/3.0',
                logLevel:              'warn',
                delegate: {
                    onInvite: function (invitation) {
                        // Incoming WebRTC call — store and notify workspace
                        self._incoming    = invitation;
                        var callerUri     = (invitation.remoteIdentity && invitation.remoteIdentity.uri)
                                            ? invitation.remoteIdentity.uri.user : 'Unknown';
                        var callerDisplay = (invitation.remoteIdentity && invitation.remoteIdentity.displayName)
                                            ? invitation.remoteIdentity.displayName : callerUri;

                        document.dispatchEvent(new CustomEvent('sos:incoming', {
                            detail: { number: callerUri, display_name: callerDisplay, invitation: invitation }
                        }));

                        invitation.stateChange.addListener(function (state) {
                            var SessionState = self._SessionState;
                            if (state === SessionState.Established) {
                                self._session  = invitation;
                                self._incoming = null;
                                self._attachAudio(invitation);
                                self._startTimer();
                                document.dispatchEvent(new CustomEvent('sos:call_start', {
                                    detail: { number: callerUri, direction: 'inbound' }
                                }));
                            } else if (state === SessionState.Terminated) {
                                if (self._session === invitation) self._session = null;
                                self._incoming = null;
                                self._stopTimer();
                                document.dispatchEvent(new CustomEvent('sos:call_end', {
                                    detail: { duration: self._callSeconds }
                                }));
                            }
                        });
                    }
                },
            });

            // Chain original transport.onMessage so SIP.js processes all responses
            var _orig = self._ua.transport.onMessage.bind(self._ua.transport);
            self._ua.transport.onMessage = function (raw) { _orig(raw); };

            self._ua.start()
                .then(function () {
                    self._registerer = new Registerer(self._ua, { expires: cfg.expires || 300 });

                    self._registerer.stateChange.addListener(function (state) {
                        self._registered = (state === RegistererState.Registered);
                        self._updateFab();
                        if (self._registered) {
                            console.log('[SalesOSWebRTC] Registered ✓  ext=' + cfg.extension);
                        }
                    });

                    self._registerer.register().catch(function (err) {
                        console.warn('[SalesOSWebRTC] Register error:', err.message);
                    });
                })
                .catch(function (err) {
                    console.warn('[SalesOSWebRTC] UA start failed:', err.message);
                });
        }).catch(function (err) {
            console.error('[SalesOSWebRTC] SIP.js load failed:', err.message);
        });
    };

    // ── Outbound call ─────────────────────────────────────────────────────────

    SalesOSWebRTC.prototype.call = function (number, lead_id, contact_id) {
        if (!this._ua || !this._registered) {
            if (global.SalesOS) global.SalesOS.originate(number, lead_id, contact_id);
            return;
        }
        if (this._session) {
            console.warn('[SalesOSWebRTC] Call already in progress');
            return;
        }

        number = String(number || '').replace(/\s/g, '');
        if (!number) return;

        var self   = this;
        var domain = (this._config.ws_server || '')
            .replace(/^wss?:\/\//, '').split('/')[0];
        var target = this._UserAgent.makeURI('sip:' + number + '@' + domain);
        if (!target) {
            console.error('[SalesOSWebRTC] Cannot parse target URI for', number);
            return;
        }

        this._postCallStart(number, lead_id, contact_id);

        var session      = new this._Inviter(this._ua, target);
        var SessionState = this._SessionState;
        this._session    = session;

        document.dispatchEvent(new CustomEvent('sos:call_start', {
            detail: { number: number, direction: 'outbound', lead_id: lead_id, contact_id: contact_id }
        }));

        session.stateChange.addListener(function (state) {
            switch (state) {
                case SessionState.Establishing:
                    document.dispatchEvent(new CustomEvent('sos:call_status', { detail: { status: 'ringing' } }));
                    break;
                case SessionState.Established:
                    document.dispatchEvent(new CustomEvent('sos:call_status', { detail: { status: 'connected' } }));
                    self._attachAudio(session);
                    self._startTimer();
                    break;
                case SessionState.Terminated:
                    self._onCallEnded();
                    break;
            }
        });

        session.invite({
            requestDelegate: {
                onProgress: function () { self._setStatus('Ringing…'); },
                onReject:   function (r) {
                    var reason = (r.message && r.message.reasonPhrase) || 'Rejected';
                    self._setStatus(reason);
                    setTimeout(function () { self._onCallEnded(); }, 2000);
                    self._session = null;
                },
            },
            sessionDescriptionHandlerOptions: {
                constraints: { audio: true, video: false },
            },
        }).catch(function (err) {
            console.error('[SalesOSWebRTC] INVITE error:', err);
            self._onCallEnded();
        });
    };

    // ── Accept incoming call ──────────────────────────────────────────────────

    SalesOSWebRTC.prototype.acceptIncoming = function () {
        var invitation = this._incoming;
        if (!invitation) return;
        invitation.accept({
            sessionDescriptionHandlerOptions: { constraints: { audio: true, video: false } },
        }).catch(function (err) {
            console.warn('[SalesOSWebRTC] Accept failed:', err.message);
        });
    };

    SalesOSWebRTC.prototype.rejectIncoming = function () {
        var invitation = this._incoming;
        if (!invitation) return;
        this._incoming = null;
        invitation.reject().catch(function () {});
        document.dispatchEvent(new CustomEvent('sos:incoming_rejected', {}));
    };

    // ── Hangup ────────────────────────────────────────────────────────────────

    SalesOSWebRTC.prototype.hangup = function () {
        var session = this._session;
        if (!session) return;

        var SessionState = this._SessionState;
        if (session.state === SessionState.Established) {
            session.bye().catch(function () {});
        } else {
            session.cancel().catch(function () {});
        }
        // _onCallEnded fires via stateChange → Terminated
    };

    // ── Mute toggle ───────────────────────────────────────────────────────────

    SalesOSWebRTC.prototype.mute = function () {
        if (!this._session) return;
        var sdh = this._session.sessionDescriptionHandler;
        if (!sdh || !sdh.peerConnection) return;

        var muted    = !this._muted;
        this._muted  = muted;

        sdh.peerConnection.getSenders().forEach(function (s) {
            if (s.track && s.track.kind === 'audio') s.track.enabled = !muted;
        });

        var btn = document.getElementById('salesos-btn-mute');
        if (btn) {
            btn.innerHTML = muted
                ? '<i class="fa fa-microphone-slash"></i><span>Unmute</span>'
                : '<i class="fa fa-microphone"></i><span>Mute</span>';
            btn.classList.toggle('active', muted);
        }
    };

    // ── Private ───────────────────────────────────────────────────────────────

    SalesOSWebRTC.prototype._attachAudio = function (session) {
        var sdh = session.sessionDescriptionHandler;
        if (!sdh || !sdh.peerConnection) return;

        var audioEl = document.getElementById('salesos-webrtc-audio');
        if (!audioEl) return;

        var pc = sdh.peerConnection;

        // Attach any tracks already on the connection
        pc.getReceivers().forEach(function (r) {
            if (r.track && r.track.kind === 'audio') {
                audioEl.srcObject = new MediaStream([r.track]);
            }
        });

        // And any tracks added later (e.g. after re-INVITE)
        pc.addEventListener('track', function (e) {
            if (e.track.kind === 'audio') {
                audioEl.srcObject = e.streams[0] || new MediaStream([e.track]);
            }
        });
    };

    SalesOSWebRTC.prototype._onCallEnded = function () {
        var duration  = this._callSeconds;
        this._session = null;
        this._muted   = false;
        this._stopTimer();
        var audioEl = document.getElementById('salesos-webrtc-audio');
        if (audioEl) audioEl.srcObject = null;
        document.dispatchEvent(new CustomEvent('sos:call_end', { detail: { duration: duration } }));
    };

    SalesOSWebRTC.prototype._startTimer = function () {
        this._callSeconds = 0;
        var self = this;
        this._timerHandle = setInterval(function () {
            self._callSeconds++;
            var el = document.getElementById('salesos-call-timer');
            if (el) {
                var m = Math.floor(self._callSeconds / 60);
                var s = self._callSeconds % 60;
                el.textContent = m + ':' + (s < 10 ? '0' : '') + s;
            }
        }, 1000);
    };

    SalesOSWebRTC.prototype._stopTimer = function () {
        if (this._timerHandle) { clearInterval(this._timerHandle); this._timerHandle = null; }
        this._callSeconds = 0;
        var el = document.getElementById('salesos-call-timer');
        if (el) el.textContent = '0:00';
    };

    SalesOSWebRTC.prototype._updateFab = function () {
        // Notify workspace of registration state change
        document.dispatchEvent(new CustomEvent(
            this._registered ? 'sos:registered' : 'sos:unregistered',
            { detail: { extension: this._config ? this._config.extension : '' } }
        ));
        // Post registration state to server so admin workspace cards update
        if (global.SalesOS) {
            var state = this._registered ? 'registered' : 'unregistered';
            var csrf  = global.SalesOS.csrf;
            var body  = 'state=' + state;
            if (csrf) body += '&' + encodeURIComponent(csrf.name) + '=' + encodeURIComponent(csrf.hash);
            fetch(global.SalesOS.apiBase + 'webrtc_presence', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body,
            }).catch(function () {});
        }
    };

    SalesOSWebRTC.prototype._postCallStart = function (number, lead_id, contact_id) {
        if (!global.SalesOS) return;
        var body = 'number=' + encodeURIComponent(number);
        if (lead_id)    body += '&lead_id='    + encodeURIComponent(lead_id);
        if (contact_id) body += '&contact_id=' + encodeURIComponent(contact_id);
        var csrf = global.SalesOS.csrf;
        if (csrf) body += '&' + encodeURIComponent(csrf.name) + '=' + encodeURIComponent(csrf.hash);
        fetch(global.SalesOS.apiBase + 'webrtc_call_start', {
            method:      'POST',
            credentials: 'same-origin',
            headers:     { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body:        body,
        }).catch(function () {});
    };

    // ── Export ────────────────────────────────────────────────────────────────

    global.SalesOSWebRTC = new SalesOSWebRTC();

    // SalesOS.call() — transport abstraction (set on DOMContentLoaded after
    // salesos.js has already booted and attached SalesOS.originate)
    document.addEventListener('DOMContentLoaded', function () {
        if (!global.SalesOS) return;

        global.SalesOS.call = function (number, lead_id, contact_id) {
            if (global.SalesOSWebRTC.isRegistered()) {
                global.SalesOSWebRTC.call(number, lead_id, contact_id);
            } else {
                global.SalesOS.originate(number, lead_id, contact_id);
            }
        };

        global.SalesOSWebRTC.init();
    });

}(window));
