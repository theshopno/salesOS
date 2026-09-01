/* SalesOS Phone Workspace v3.0 — Phase 3C
 * Coordinates: SosCallFSM (state machine), SosWorkspace (UI controller),
 * WebRTC events (sos:*), WebSocket events (SalesOsWsInstance), wrap-up, search.
 */
(function (global) {
    'use strict';

    // ─── State machine ────────────────────────────────────────────────────────

    var STATES = {
        IDLE:         'IDLE',
        REGISTERING:  'REGISTERING',
        READY:        'READY',
        DIALING:      'DIALING',
        RINGING:      'RINGING',
        TALKING:      'TALKING',
        ON_HOLD:      'ON_HOLD',
        TRANSFERRING: 'TRANSFERRING',
        WRAP_UP:      'WRAP_UP',
    };

    var SosCallFSM = {
        state: STATES.IDLE,
        _data: {},

        transition: function (newState, data) {
            var prev   = this.state;
            this.state = newState;
            this._data = data || {};
            document.dispatchEvent(new CustomEvent('sos:state_change', {
                detail: { from: prev, to: newState, data: this._data }
            }));
        },

        is: function (s) { return this.state === s; },

        isActive: function () {
            return this.state === STATES.DIALING   ||
                   this.state === STATES.RINGING   ||
                   this.state === STATES.TALKING   ||
                   this.state === STATES.ON_HOLD   ||
                   this.state === STATES.TRANSFERRING;
        },
    };

    // ─── Module state ─────────────────────────────────────────────────────────

    var _panelOpen      = false;
    var _currentCall    = {};        // { number, name, direction, call_id, uniqueid, session_id }
    var _incomingPopups = {};        // keyed by session_id || uniqueid — dedup guard
    var _liveNoteTimer  = null;
    var _wuCountdown    = null;
    var _wuSeconds      = 0;
    var _searchTimer    = null;
    var _muted          = false;
    var _recentLoaded   = false;

    // ─── SosWorkspace public API ──────────────────────────────────────────────

    var SosWorkspace = {

        init: function () {
            _bindDomEvents();
            _bindWsEvents();
            _bindTabSwitchers();
            _bindSearchInput();
            _bindDialInput();
        },

        // ── Panel toggle ──────────────────────────────────────────────────────

        toggle: function () {
            _panelOpen = !_panelOpen;
            var panel   = $id('sos-ws-panel');
            var chevron = $id('sos-ws-chevron-icon');
            if (panel)   panel.style.display = _panelOpen ? 'flex' : 'none';
            if (chevron) chevron.className   = 'fa ' + (_panelOpen ? 'fa-chevron-down' : 'fa-chevron-up');
            if (_panelOpen) {
                if (!_recentLoaded) { _recentLoaded = true; _loadRecent(); }
                var searchEl = $id('sos-ws-search');
                if (searchEl) setTimeout(function () { searchEl.focus(); }, 80);
            }
        },

        // ── Dialpad ───────────────────────────────────────────────────────────

        dialKey: function (k) {
            var inp = $id('sos-ws-dial-input');
            if (inp) inp.value += k;
        },

        dialDel: function () {
            var inp = $id('sos-ws-dial-input');
            if (inp) inp.value = inp.value.slice(0, -1);
        },

        call: function (number) {
            var inp    = $id('sos-ws-dial-input');
            var target = (typeof number === 'string' ? number : '') || (inp ? inp.value.trim() : '');
            if (!target) return;
            if (global.SalesOS && typeof global.SalesOS.call === 'function') {
                global.SalesOS.call(target);
            }
        },

        // ── Incoming ──────────────────────────────────────────────────────────

        acceptIncoming: function () {
            if (global.SalesOSWebRTC) global.SalesOSWebRTC.acceptIncoming();
        },

        rejectIncoming: function () {
            if (global.SalesOSWebRTC) global.SalesOSWebRTC.rejectIncoming();
            _hideIncoming();
        },

        dismissIncoming: function () {
            _hideIncoming();
        },

        // ── Mute (goes through workspace so we can update the right button) ──

        toggleMute: function () {
            if (!global.SalesOSWebRTC) return;
            global.SalesOSWebRTC.mute();   // handles RTC track muting
            _muted = !_muted;
            var btn = $id('sos-ws-btn-mute');
            if (btn) {
                btn.innerHTML = _muted
                    ? '<i class="fa fa-microphone-slash"></i><span>Unmute</span>'
                    : '<i class="fa fa-microphone"></i><span>Mute</span>';
                btn.classList.toggle('active', _muted);
            }
        },

        // ── Wrap-up ───────────────────────────────────────────────────────────

        onOutcomeChange: function (val) {
            var hintMap = {
                'Interested':         'A follow-up task will be created for tomorrow.',
                'Busy':               'A callback task will be created in 2 hours.',
                'No Answer':          'A callback task will be created in 24 hours.',
                'Wrong Number':       'Number will be flagged Do Not Call.',
                'Callback Requested': 'A callback task will be created.',
            };
            var hint = $id('sos-wu-auto-hint');
            if (hint) {
                hint.textContent    = hintMap[val] || '';
                hint.style.display  = hintMap[val] ? 'block' : 'none';
            }

            var showFollowup = ['Interested', 'Busy', 'No Answer', 'Callback Requested'].indexOf(val) !== -1;
            var flWrap = $id('sos-wu-followup-wrap');
            if (flWrap) flWrap.style.display = showFollowup ? 'block' : 'none';

            var showStatus = ['Interested', 'Not Interested', 'Sale Closed', 'Other'].indexOf(val) !== -1;
            var lsWrap = $id('sos-wu-lead-status-wrap');
            if (lsWrap) lsWrap.style.display = showStatus ? 'block' : 'none';
        },

        saveWrapUp: function () {
            var disposition = $val('sos-wu-disposition');
            var outcome     = $val('sos-wu-outcome');
            if (!disposition || !outcome) {
                alert('Disposition and Outcome are required.');
                return;
            }
            var payload = {
                call_id:      $val('sos-wu-call-id'),
                uniqueid:     $val('sos-wu-uniqueid'),
                session_id:   $val('sos-wu-session-id'),
                disposition:  disposition,
                outcome:      outcome,
                notes:        $val('sos-wu-notes'),
                lead_status:  $val('sos-wu-lead-status'),
                follow_up_at: $val('sos-wu-followup'),
            };
            _post(_apiUrl('wrapup/save'), payload, function (d) {
                if (d.success) {
                    _clearLiveNote();
                    _hideWrapUp();
                    SosCallFSM.transition(STATES.READY);
                } else {
                    alert(d.message || 'Save failed.');
                }
            });
        },

        skipWrapUp: function () {
            var payload = {
                call_id:    $val('sos-wu-call-id'),
                uniqueid:   $val('sos-wu-uniqueid'),
                session_id: $val('sos-wu-session-id'),
            };
            _post(_apiUrl('wrapup/skip'), payload, function (d) {
                if (d.success) {
                    _clearLiveNote();
                    _hideWrapUp();
                    SosCallFSM.transition(STATES.READY);
                }
            });
        },
    };

    // ─── DOM event binding ────────────────────────────────────────────────────

    function _bindDomEvents() {
        // WebRTC registration
        document.addEventListener('sos:registered', function (e) {
            var ext = (e.detail && e.detail.extension) || '—';
            _setBarStatus('online', 'Ready', ext);
            _setPanelStatus('online', 'Ready');
            SosCallFSM.transition(STATES.READY);
        });

        document.addEventListener('sos:unregistered', function (e) {
            var ext = (e.detail && e.detail.extension) || '—';
            _setBarStatus('offline', 'Offline', ext);
            _setPanelStatus('offline', 'Offline');
            if (!SosCallFSM.isActive() && !SosCallFSM.is(STATES.WRAP_UP)) {
                SosCallFSM.transition(STATES.IDLE);
            }
        });

        // Outbound or accepted inbound call started
        document.addEventListener('sos:call_start', function (e) {
            var d = e.detail || {};
            _currentCall = {
                number:    d.number    || '',
                direction: d.direction || 'outbound',
                lead_id:   d.lead_id   || null,
            };
            _muted = false;
            _hideIncoming();
            _showActiveCall(d.number, d.direction);
            _setBarStatus('talking', 'Calling');
            _setPanelStatus('talking', 'In Call');
            SosCallFSM.transition(STATES.DIALING, _currentCall);
            _startLiveNoteAutosave();
            _preloadLiveNote();
            _fetchContext(d.number);
        });

        // Outbound call state updates
        document.addEventListener('sos:call_status', function (e) {
            var s = e.detail && e.detail.status;
            if (s === 'ringing') {
                SosCallFSM.transition(STATES.RINGING, _currentCall);
                _setActiveStatus('Ringing…');
                _setBarStatus('ringing', 'Ringing');
            } else if (s === 'connected') {
                SosCallFSM.transition(STATES.TALKING, _currentCall);
                _setActiveStatus('Connected');
                _setBarStatus('talking', 'Talking');
            }
        });

        // Call ended
        document.addEventListener('sos:call_end', function (e) {
            var duration = (e.detail && e.detail.duration) || 0;
            _stopLiveNoteAutosave();
            _hideActiveCall();
            _showWrapUp(_currentCall, duration);
            SosCallFSM.transition(STATES.WRAP_UP, _currentCall);
            _setBarStatus('busy', 'Wrap-up');
        });

        // Incoming WebRTC invitation
        document.addEventListener('sos:incoming', function (e) {
            var d = e.detail || {};
            _onIncoming(d.number || '', d.display_name || '', d.session_id || '');
        });

        // Incoming rejected (by other device / timeout / explicit reject)
        document.addEventListener('sos:incoming_rejected', function () {
            _incomingPopups = {};
            _hideIncoming();
        });
    }

    // ─── WebSocket event binding ──────────────────────────────────────────────

    function _bindWsEvents() {
        var ws = global.SalesOsWsInstance;
        if (!ws || typeof ws.on !== 'function') return;

        // AMI-sourced inbound ring (reaches agent before WebRTC onInvite in some configs)
        ws.on('call.ringing', function (ev) {
            var key = ev.session_id || ev.uniqueid || ev.callerid || String(Date.now());
            if (_incomingPopups[key]) {
                // Update the popup if we get more info
                var nameEl = $id('sos-inc-name');
                if (nameEl && ev.cid_name) nameEl.textContent = ev.cid_name;
                return;
            }
            _incomingPopups[key] = ev;
            _onIncoming(ev.callerid || ev.src || '', ev.cid_name || '', key);
            SosCallFSM.transition(STATES.RINGING, ev);
            _setBarStatus('ringing', 'Ringing');
        });

        ws.on('call.bridged', function () {
            SosCallFSM.transition(STATES.TALKING, _currentCall);
            _setBarStatus('talking', 'Talking');
            _setActiveStatus('Connected');
        });

        ws.on('call.ended', function (ev) {
            var key = (ev && (ev.session_id || ev.uniqueid)) || '';
            if (key) delete _incomingPopups[key];
            // If we're still ringing but never got sos:incoming_rejected (non-WebRTC path)
            if (SosCallFSM.is(STATES.RINGING)) {
                _hideIncoming();
                _setBarStatus('online', 'Ready');
                SosCallFSM.transition(STATES.READY);
            }
        });

        ws.on('agent.presence', function (ev) {
            _syncPresenceBar(ev.presence);
        });

        ws.on('snapshot:agents', function () {
            // Nothing to do at workspace level — agents/manage.php handles this
        });
    }

    // ─── Tab switchers ────────────────────────────────────────────────────────

    function _bindTabSwitchers() {
        var tabs = document.querySelectorAll('.sos-ws-tab');
        tabs.forEach(function (btn) {
            btn.addEventListener('click', function () {
                tabs.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                var target = btn.getAttribute('data-tab');
                var dialTab   = $id('sos-ws-tab-dialpad');
                var recentTab = $id('sos-ws-tab-recent');
                if (dialTab)   dialTab.style.display   = target === 'dialpad' ? 'block' : 'none';
                if (recentTab) recentTab.style.display  = target === 'recent'  ? 'block' : 'none';
                if (target === 'recent' && !_recentLoaded) {
                    _recentLoaded = true;
                    _loadRecent();
                }
            });
        });
    }

    // ─── Search ───────────────────────────────────────────────────────────────

    function _bindSearchInput() {
        var inp = $id('sos-ws-search');
        if (!inp) return;

        inp.addEventListener('input', function () {
            clearTimeout(_searchTimer);
            var q = inp.value.trim();
            if (q.length < 2) { _hideSearchResults(); return; }
            _searchTimer = setTimeout(function () { _doSearch(q); }, 300);
        });

        inp.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') { inp.value = ''; _hideSearchResults(); }
            if (ev.key === 'Enter')  {
                // If only one result, call it; otherwise let user click
                var first = document.querySelector('.sos-ws-search-row');
                if (first) first.click();
            }
        });

        document.addEventListener('click', function (ev) {
            var wrap = document.querySelector('.sos-ws-search-wrap');
            if (wrap && !wrap.contains(ev.target)) _hideSearchResults();
        });
    }

    function _doSearch(q) {
        var resultsEl = $id('sos-ws-search-results');
        if (!resultsEl) return;
        resultsEl.style.display = 'block';
        resultsEl.innerHTML     = '<div class="sos-ws-search-state">Searching…</div>';

        fetch(_apiUrl('search') + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var items = (d && d.data) || [];
                if (!items.length) {
                    resultsEl.innerHTML = '<div class="sos-ws-search-state">No results for "' + _esc(q) + '"</div>';
                    return;
                }
                var typeLabels = { lead: 'Lead', client: 'Client', contact: 'Contact', invoice: 'Invoice' };
                resultsEl.innerHTML = items.map(function (item) {
                    var label = typeLabels[item.type] || item.type;
                    var ph    = item.phone || '';
                    return '<div class="sos-ws-search-row" onclick="SosWorkspace.call(\'' + _esc(ph) + '\')">' +
                        '<span class="sos-ws-sr-type">' + _esc(label) + '</span>' +
                        '<span class="sos-ws-sr-name">' + _esc(item.name || '') + '</span>' +
                        (ph ? '<span class="sos-ws-sr-phone">' + _esc(ph) + '</span>' : '') +
                        '</div>';
                }).join('');
            })
            .catch(function () {
                resultsEl.innerHTML = '<div class="sos-ws-search-state">Search failed</div>';
            });
    }

    function _hideSearchResults() {
        var el = $id('sos-ws-search-results');
        if (el) el.style.display = 'none';
    }

    // ─── Dial input ───────────────────────────────────────────────────────────

    function _bindDialInput() {
        var inp = $id('sos-ws-dial-input');
        if (!inp) return;
        inp.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') SosWorkspace.call();
        });
    }

    // ─── Incoming popup ───────────────────────────────────────────────────────

    function _onIncoming(number, displayName, key) {
        if (!key) key = number || String(Date.now());

        // Dedup: same key means already showing this call
        if (_incomingPopups[key]) return;
        _incomingPopups[key] = { number: number, display_name: displayName };

        var el     = $id('sos-incoming');
        var numEl  = $id('sos-inc-number');
        var nameEl = $id('sos-inc-name');
        var metaEl = $id('sos-inc-meta');
        var crmEl  = $id('sos-inc-crm-links');

        if (numEl)  numEl.textContent  = number      || '—';
        if (nameEl) nameEl.textContent = displayName || '';
        if (metaEl) metaEl.textContent = '';
        if (crmEl)  crmEl.innerHTML    = '';
        if (el)     el.style.display   = 'flex';

        // Context fetch fills in entity name + CRM link
        if (number) _fetchContext(number, function (ctx) {
            if (!ctx) return;
            if (nameEl && !nameEl.textContent && ctx.entity && ctx.entity.name) {
                nameEl.textContent = ctx.entity.name;
            }
            if (metaEl && ctx.entity) {
                metaEl.textContent = ctx.entity.type + (ctx.calls_total ? ' · ' + ctx.calls_total + ' calls' : '');
            }
            if (crmEl && ctx.entity && ctx.entity.url) {
                crmEl.innerHTML = '<a href="' + _esc(ctx.entity.url) + '" target="_blank" class="sos-inc-crm-link">' +
                    '<i class="fa fa-external-link"></i> Open ' + _esc(ctx.entity.type) + '</a>';
            }
        });
    }

    function _hideIncoming() {
        var el = $id('sos-incoming');
        if (el) el.style.display = 'none';
        var crm = $id('sos-inc-crm-links');
        if (crm) crm.innerHTML = '';
        _incomingPopups = {};
    }

    // ─── Active call overlay ──────────────────────────────────────────────────

    function _showActiveCall(number, direction) {
        var el = $id('sos-ws-active');
        if (el) el.style.display = 'flex';

        var numEl    = $id('sos-ws-active-number');
        var nameEl   = $id('sos-ws-active-name');
        var metaEl   = $id('sos-ws-active-meta');
        var ctxEl    = $id('sos-ws-active-context');
        var statusEl = $id('sos-ws-active-status');
        var timerEl  = $id('salesos-call-timer');

        if (numEl)    numEl.textContent    = number || '—';
        if (nameEl)   nameEl.textContent   = '';
        if (metaEl)   metaEl.textContent   = '';
        if (ctxEl)    ctxEl.innerHTML      = '';
        if (statusEl) statusEl.textContent = direction === 'inbound' ? 'Answering…' : 'Calling…';
        if (timerEl)  timerEl.textContent  = '0:00';

        // Reset mute state
        _muted = false;
        var muteBtn = $id('sos-ws-btn-mute');
        if (muteBtn) muteBtn.innerHTML = '<i class="fa fa-microphone"></i><span>Mute</span>';
    }

    function _hideActiveCall() {
        var el = $id('sos-ws-active');
        if (el) el.style.display = 'none';
    }

    function _setActiveStatus(text) {
        var el = $id('sos-ws-active-status');
        if (el) el.textContent = text;
    }

    // ─── Context fetch ────────────────────────────────────────────────────────

    function _fetchContext(number, cb) {
        if (!number) return;
        var url = _apiUrl('incoming_context') + '?caller=' + encodeURIComponent(number);
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.success) { if (cb) cb(null); return; }
                // Update active call overlay
                if (d.entity) {
                    var nameEl = $id('sos-ws-active-name');
                    if (nameEl && d.entity.name) nameEl.textContent = d.entity.name;
                    var metaEl = $id('sos-ws-active-meta');
                    if (metaEl) metaEl.textContent = d.entity.type || '';
                    var ctxEl = $id('sos-ws-active-context');
                    if (ctxEl) ctxEl.innerHTML = _renderCtxBadges(d);
                }
                if (cb) cb(d);
            })
            .catch(function () { if (cb) cb(null); });
    }

    function _renderCtxBadges(ctx) {
        var parts = [];
        if (ctx.calls_total) parts.push('<span class="sos-ctx-badge">' + ctx.calls_total + ' call' + (ctx.calls_total !== 1 ? 's' : '') + '</span>');
        if (ctx.tasks_open)  parts.push('<span class="sos-ctx-badge sos-ctx-warn">' + ctx.tasks_open + ' open task' + (ctx.tasks_open !== 1 ? 's' : '') + '</span>');
        if (ctx.clv)         parts.push('<span class="sos-ctx-badge sos-ctx-info">CLV ' + _esc(String(ctx.clv)) + '</span>');
        return parts.join('');
    }

    // ─── Live note autosave ───────────────────────────────────────────────────

    function _noteKey() {
        var sid = global.SalesOS && global.SalesOS.staffId ? global.SalesOS.staffId : '0';
        return 'sos_live_note_' + sid;
    }

    function _startLiveNoteAutosave() {
        _stopLiveNoteAutosave();
        _liveNoteTimer = setInterval(function () {
            var note = $id('sos-ws-live-note');
            if (note) localStorage.setItem(_noteKey(), note.value);
        }, 5000);
    }

    function _stopLiveNoteAutosave() {
        if (_liveNoteTimer) { clearInterval(_liveNoteTimer); _liveNoteTimer = null; }
    }

    function _preloadLiveNote() {
        var note  = $id('sos-ws-live-note');
        var saved = localStorage.getItem(_noteKey());
        if (note && saved) note.value = saved;
    }

    function _clearLiveNote() {
        localStorage.removeItem(_noteKey());
        var note = $id('sos-ws-live-note');
        if (note) note.value = '';
    }

    // ─── Wrap-up panel ───────────────────────────────────────────────────────

    function _showWrapUp(call, duration) {
        var wuEl = $id('sos-wrapup');
        if (!wuEl) return;
        wuEl.style.display = 'flex';

        // Hidden fields
        _setInputVal('sos-wu-call-id',    call.call_id    || '');
        _setInputVal('sos-wu-uniqueid',   call.uniqueid   || '');
        _setInputVal('sos-wu-session-id', call.session_id || '');

        // Duration
        var durEl = $id('sos-wu-duration');
        if (durEl) {
            var m = Math.floor(duration / 60), s = duration % 60;
            durEl.textContent = m + ':' + (s < 10 ? '0' : '') + s;
        }

        // Caller display (text spans, not inputs)
        var numEl  = $id('sos-wu-number');
        var nameEl = $id('sos-wu-name');
        if (numEl)  numEl.textContent  = call.number || '';
        if (nameEl) nameEl.textContent = call.name   || '';

        // Pre-fill notes from live note
        var notesEl = $id('sos-wu-notes');
        if (notesEl) notesEl.value = localStorage.getItem(_noteKey()) || '';

        // Reset selects / optional fields
        _setInputVal('sos-wu-disposition', '');
        _setInputVal('sos-wu-outcome',     '');
        _setInputVal('sos-wu-lead-status', '');
        _setInputVal('sos-wu-followup',    '');

        var hint = $id('sos-wu-auto-hint');
        if (hint) { hint.style.display = 'none'; hint.textContent = ''; }
        var flWrap = $id('sos-wu-followup-wrap');
        if (flWrap) flWrap.style.display = 'none';
        var lsWrap = $id('sos-wu-lead-status-wrap');
        if (lsWrap) lsWrap.style.display = 'none';

        _startWuCountdown(120);
    }

    function _hideWrapUp() {
        var el = $id('sos-wrapup');
        if (el) el.style.display = 'none';
        _stopWuCountdown();
        _setBarStatus('online', 'Ready');
        _setPanelStatus('online', 'Ready');
    }

    function _startWuCountdown(seconds) {
        _stopWuCountdown();
        _wuSeconds = seconds;
        var countEl = $id('sos-wu-countdown');
        if (countEl) countEl.textContent = '(' + _wuSeconds + 's)';

        _wuCountdown = setInterval(function () {
            _wuSeconds--;
            if (countEl) countEl.textContent = '(' + Math.max(0, _wuSeconds) + 's)';
            if (_wuSeconds <= 0) {
                _stopWuCountdown();
                // Non-supervisor: skip button is hidden — auto-save with what's available
                var skipBtn = document.querySelector('#sos-wu-skip-wrap button');
                if (!skipBtn) SosWorkspace.saveWrapUp();
            }
        }, 1000);
    }

    function _stopWuCountdown() {
        if (_wuCountdown) { clearInterval(_wuCountdown); _wuCountdown = null; }
    }

    // ─── Recent calls ─────────────────────────────────────────────────────────

    function _loadRecent() {
        var list = $id('sos-ws-recent-list');
        if (!list) return;

        fetch(_apiUrl('history'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var rows = (d && d.data) || [];
                if (!rows.length) {
                    list.innerHTML = '<div class="sos-ws-empty text-muted">No recent calls</div>';
                    return;
                }
                list.innerHTML = rows.slice(0, 20).map(function (c) {
                    var num    = c.direction === 'outbound' ? (c.dst || '') : (c.src || '');
                    var name   = c.direction === 'outbound' ? (c.dst_name || '') : (c.src_name || '');
                    var isOut  = c.direction === 'outbound';
                    var dClass = isOut ? 'fa-arrow-up text-success' : 'fa-arrow-down text-primary';
                    return '<div class="sos-recent-row" onclick="SosWorkspace.call(\'' + _esc(num) + '\')">' +
                        '<i class="fa ' + dClass + ' sos-recent-dir"></i>' +
                        '<div class="sos-recent-info">' +
                            '<div class="sos-recent-num">' + _esc(num) + '</div>' +
                            (name ? '<div class="sos-recent-name">' + _esc(name) + '</div>' : '') +
                        '</div>' +
                        '<div class="sos-recent-date">' + _relTime(c.calldate) + '</div>' +
                        '</div>';
                }).join('');
            })
            .catch(function () {
                if (list) list.innerHTML = '<div class="sos-ws-empty text-muted">Could not load</div>';
            });
    }

    // ─── Bar / badge helpers ──────────────────────────────────────────────────

    function _setBarStatus(cls, text, ext) {
        var badge   = $id('sos-ws-reg-badge');
        var textEl  = $id('sos-ws-reg-text');
        var extEl   = $id('sos-ws-bar-ext');
        if (badge)  badge.className    = 'sos-reg-badge ' + cls;
        if (textEl) textEl.textContent = text || '';
        if (ext !== undefined && extEl) extEl.textContent = ext;
    }

    function _setPanelStatus(cls, text) {
        var el = $id('sos-ws-h-status');
        if (!el) return;
        el.className = 'sos-reg-badge ' + cls;
        var span = el.querySelector('span');
        if (span) span.textContent = text || '';
    }

    var _presenceMap = {
        READY:    ['online',  'Ready'],
        BUSY:     ['busy',    'Busy'],
        TALKING:  ['talking', 'Talking'],
        RINGING:  ['ringing', 'Ringing'],
        WRAP_UP:  ['busy',    'Wrap-up'],
        OFFLINE:  ['offline', 'Offline'],
    };

    function _syncPresenceBar(presence) {
        var pair = _presenceMap[presence] || ['offline', presence];
        _setBarStatus(pair[0], pair[1]);
        _setPanelStatus(pair[0], pair[1]);
    }

    // ─── Utility ──────────────────────────────────────────────────────────────

    function $id(id)        { return document.getElementById(id); }
    function $val(id)       { var e = $id(id); return e ? e.value : ''; }
    function _setInputVal(id, v) { var e = $id(id); if (e) e.value = v; }

    function _apiUrl(path) {
        return global.SalesOS && global.SalesOS.apiBase ? global.SalesOS.apiBase + path : '';
    }

    function _post(url, data, cb) {
        if (!url) return;
        var parts = [];
        var csrf  = global.SalesOS && global.SalesOS.csrf;
        if (csrf) parts.push(encodeURIComponent(csrf.name) + '=' + encodeURIComponent(csrf.hash));
        Object.keys(data).forEach(function (k) {
            if (data[k] !== null && data[k] !== undefined && data[k] !== '') {
                parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(data[k]));
            }
        });
        fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: parts.join('&'),
        }).then(function (r) { return r.json(); })
        .then(function (d) { if (cb) cb(d); })
        .catch(function () { if (cb) cb({ success: false }); });
    }

    function _esc(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function _relTime(dateStr) {
        if (!dateStr) return '';
        var diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
        if (diff < 60)    return 'just now';
        if (diff < 3600)  return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    // ─── Export & boot ────────────────────────────────────────────────────────

    global.SosCallFSM   = SosCallFSM;
    global.SosWorkspace = SosWorkspace;

    document.addEventListener('DOMContentLoaded', function () {
        SosWorkspace.init();
    });

}(window));
