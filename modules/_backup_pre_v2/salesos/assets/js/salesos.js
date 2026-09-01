/**
 * SalesOS Telephony JS
 * - Polling-based call popup (no WebRTC)
 * - Outbound originate
 * - Phone number click-to-call injection
 */

(function () {
    'use strict';

    if (typeof SalesOS === 'undefined') return;

    // ── CSRF helper ──────────────────────────────────────────────────────────
    function csrfParam() {
        if (SalesOS.csrf && SalesOS.csrf.name && SalesOS.csrf.hash) {
            return '&' + encodeURIComponent(SalesOS.csrf.name) + '=' + encodeURIComponent(SalesOS.csrf.hash);
        }
        return '';
    }

    // ── State ────────────────────────────────────────────────────────────────
    const seenCalls   = new Set();
    const popupTimers = {};

    // ── Polling ──────────────────────────────────────────────────────────────
    let pollTimer = null;

    function startPolling() {
        if (pollTimer) return;
        pollActive();
        pollTimer = setInterval(pollActive, SalesOS.pollInterval);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function pollActive() {
        fetch(SalesOS.apiBase + 'active_calls', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.calls) return;
            data.calls.forEach(call => {
                if (!seenCalls.has(call.uniqueid)) {
                    seenCalls.add(call.uniqueid);
                    showCallPopup(call);
                    markPopupSeen(call.id);
                }
            });
        })
        .catch(() => {});
    }

    // ── Popup ────────────────────────────────────────────────────────────────
    function showCallPopup(call) {
        const container = document.getElementById('salesos-popup-container');
        if (!container) return;

        const dir     = call.direction || 'inbound';
        const number  = dir === 'inbound' ? call.src : call.dst;
        const name    = call.caller_name || '';
        const elapsed = { val: 0 };

        const popup = document.createElement('div');
        popup.className = 'salesos-popup ' + dir;
        popup.id = 'salesos-popup-' + call.uniqueid;

        let entityLink = '';
        if (call.lead_id) {
            entityLink = '<a href="' + (window.admin_url || '/admin/') + 'leads/index/' + call.lead_id + '" class="salesos-btn-view" target="_blank"><i class="fa fa-user"></i> View Lead</a>';
        } else if (call.client_id) {
            entityLink = '<a href="' + (window.admin_url || '/admin/') + 'clients/client/' + call.client_id + '" class="salesos-btn-view" target="_blank"><i class="fa fa-building"></i> View Client</a>';
        } else {
            entityLink = '<a href="' + SalesOS.apiBase.replace('/api/', '/calls') + '" class="salesos-btn-view"><i class="fa fa-list"></i> Call Log</a>';
        }

        popup.innerHTML = `
            <div class="salesos-popup-header">
                <span class="salesos-popup-title">
                    <i class="fa fa-phone"></i>
                    ${dir === 'inbound' ? 'Incoming Call' : 'Outgoing Call'}
                </span>
                <span class="salesos-popup-dir ${dir}">${dir.charAt(0).toUpperCase() + dir.slice(1)}</span>
                <button class="salesos-popup-close" onclick="SalesOS.closePopup('${call.uniqueid}')">&times;</button>
            </div>
            <div class="salesos-popup-body">
                <div class="salesos-popup-number">${escHtml(number)}</div>
                ${name ? '<div class="salesos-popup-name">' + escHtml(name) + '</div>' : ''}
                <div class="salesos-popup-actions">
                    ${entityLink}
                    <button class="salesos-btn-close" onclick="SalesOS.closePopup('${call.uniqueid}')">Dismiss</button>
                </div>
                <div class="salesos-popup-timer" id="salesos-timer-${call.uniqueid}">0:00</div>
            </div>
        `;

        container.appendChild(popup);

        const dismissTimer = setTimeout(() => SalesOS.closePopup(call.uniqueid), 60000);
        popupTimers[call.uniqueid] = {
            dismiss: dismissTimer,
            elapsed: setInterval(() => {
                elapsed.val++;
                const el = document.getElementById('salesos-timer-' + call.uniqueid);
                if (el) {
                    const m = Math.floor(elapsed.val / 60);
                    const s = elapsed.val % 60;
                    el.textContent = m + ':' + String(s).padStart(2, '0');
                }
            }, 1000),
        };
    }

    function closePopup(uniqueid) {
        const popup = document.getElementById('salesos-popup-' + uniqueid);
        if (popup) popup.remove();
        if (popupTimers[uniqueid]) {
            clearTimeout(popupTimers[uniqueid].dismiss);
            clearInterval(popupTimers[uniqueid].elapsed);
            delete popupTimers[uniqueid];
        }
    }

    function markPopupSeen(id) {
        fetch(SalesOS.apiBase + 'popup_seen/' + id, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: '_dummy=1' + csrfParam(),
        }).catch(() => {});
    }

    // ── Originate ────────────────────────────────────────────────────────────
    function originate(number, lead_id, contact_id) {
        if (!SalesOS.canMakeCall) {
            alert('You do not have permission to make calls.');
            return;
        }

        number = number.replace(/\s/g, '');
        if (!number) {
            alert('Please enter a phone number.');
            return;
        }

        let body = 'number=' + encodeURIComponent(number) + csrfParam();
        if (lead_id)    body += '&lead_id='    + encodeURIComponent(lead_id);
        if (contact_id) body += '&contact_id=' + encodeURIComponent(contact_id);

        const indicator = document.createElement('div');
        indicator.className = 'salesos-popup outbound';
        indicator.style.cssText = 'padding:12px 16px;font-weight:600;';
        indicator.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Calling ' + escHtml(number) + '...';
        const container = document.getElementById('salesos-popup-container');
        if (container) container.prepend(indicator);

        fetch(SalesOS.apiBase + 'originate', {
            method:  'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    body,
        })
        .then(r => r.json())
        .then(d => {
            indicator.remove();
            if (d.success) {
                if (typeof alert_float === 'function') alert_float('success', 'Call initiated to ' + number);
            } else {
                if (typeof alert_float === 'function') alert_float('danger', d.error || 'Call failed');
                else alert('Call failed: ' + (d.error || 'unknown error'));
            }
        })
        .catch(err => {
            indicator.remove();
            if (typeof alert_float === 'function') alert_float('danger', 'Network error: ' + err.message);
        });
    }

    // ── Click-to-call injection ───────────────────────────────────────────────
    function injectClickToCall() {
        if (!SalesOS.canMakeCall) return;

        document.querySelectorAll('[data-salesos-phone]').forEach(el => {
            if (el.dataset.saleosInjected) return;
            el.dataset.saleosInjected = '1';
            const num = el.dataset.salesosPhone || el.textContent.trim();
            const btn = document.createElement('a');
            btn.className = 'salesos-call-inline';
            btn.href = '#';
            btn.innerHTML = '<i class="fa fa-phone"></i>';
            btn.title = 'Call ' + num;
            btn.addEventListener('click', e => {
                e.preventDefault();
                originate(num, el.dataset.leadId || '', el.dataset.contactId || '');
            });
            el.parentNode.insertBefore(btn, el.nextSibling);
        });
    }

    // ── Public API ───────────────────────────────────────────────────────────
    SalesOS.originate  = originate;
    SalesOS.closePopup = closePopup;

    // ── Boot ─────────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        startPolling();
        injectClickToCall();
    });

    function escHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(str || ''));
        return d.innerHTML;
    }

}());
