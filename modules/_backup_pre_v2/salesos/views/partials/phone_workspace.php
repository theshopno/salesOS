<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!-- SalesOS Phase 3C — Unified Phone Workspace -->
<div id="sos-workspace" class="sos-workspace">

    <!-- Collapsed bar (always visible) -->
    <div id="sos-ws-bar" class="sos-ws-bar" onclick="SosWorkspace.toggle()">
        <div class="sos-ws-bar-left">
            <span class="sos-ws-bar-icon"><i class="fa fa-phone"></i></span>
            <span id="sos-ws-bar-ext" class="sos-ws-bar-ext">—</span>
            <span id="sos-ws-bar-name" class="sos-ws-bar-name"></span>
        </div>
        <div class="sos-ws-bar-right">
            <span id="sos-ws-reg-badge" class="sos-reg-badge offline">
                <i class="fa fa-circle"></i> <span id="sos-ws-reg-text">Offline</span>
            </span>
            <span class="sos-ws-chevron"><i id="sos-ws-chevron-icon" class="fa fa-chevron-up"></i></span>
        </div>
    </div>

    <!-- Expanded panel -->
    <div id="sos-ws-panel" class="sos-ws-panel" style="display:none;">

        <!-- Panel header -->
        <div class="sos-ws-header">
            <div class="sos-ws-header-info">
                <span id="sos-ws-h-name" class="sos-ws-h-name">Loading…</span>
                <span id="sos-ws-h-pbx" class="sos-ws-h-pbx"></span>
            </div>
            <div class="sos-ws-header-actions">
                <span id="sos-ws-h-status" class="sos-reg-badge offline">
                    <i class="fa fa-circle"></i> <span>Offline</span>
                </span>
            </div>
        </div>

        <!-- Universal search -->
        <div class="sos-ws-search-wrap">
            <div class="sos-ws-search-inner">
                <i class="fa fa-search sos-ws-search-icon"></i>
                <input type="text" id="sos-ws-search" class="sos-ws-search-input"
                    placeholder="Search lead, number, company…" autocomplete="off">
            </div>
            <div id="sos-ws-search-results" class="sos-ws-search-results" style="display:none;"></div>
        </div>

        <!-- Tabs -->
        <div class="sos-ws-tabs">
            <button class="sos-ws-tab active" data-tab="dialpad">
                <i class="fa fa-th"></i> Dial
            </button>
            <button class="sos-ws-tab" data-tab="recent">
                <i class="fa fa-history"></i> Recent
            </button>
        </div>

        <!-- Dialpad tab -->
        <div id="sos-ws-tab-dialpad" class="sos-ws-tab-body">
            <div class="sos-ws-dialpad">
                <div class="sos-ws-dial-display">
                    <input type="tel" id="sos-ws-dial-input" class="sos-ws-dial-input"
                        placeholder="Enter number…" autocomplete="off">
                    <button class="sos-ws-dial-del" onclick="SosWorkspace.dialDel()" title="Delete">
                        <i class="fa fa-backspace"></i>
                    </button>
                </div>
                <div class="sos-ws-keypad">
                    <?php foreach (['1','2','3','4','5','6','7','8','9','*','0','#'] as $k): ?>
                    <button class="sos-ws-key" onclick="SosWorkspace.dialKey('<?= $k ?>')">
                        <span class="sos-ws-key-main"><?= $k ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php if (staff_can('make', 'salesos')): ?>
                <button id="sos-ws-call-btn" class="sos-ws-call-btn" onclick="SosWorkspace.call()">
                    <i class="fa fa-phone"></i> Call
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent tab -->
        <div id="sos-ws-tab-recent" class="sos-ws-tab-body" style="display:none;">
            <div id="sos-ws-recent-list" class="sos-ws-recent-list">
                <div class="sos-ws-empty text-muted">Loading…</div>
            </div>
        </div>

        <!-- Active call overlay (shown during call) -->
        <div id="sos-ws-active" class="sos-ws-active" style="display:none;">
            <div class="sos-ws-active-header">
                <div class="sos-ws-active-state">
                    <i class="fa fa-phone sos-pulse"></i>
                    <span id="sos-ws-active-status">Calling…</span>
                </div>
                <div id="salesos-call-timer" class="sos-ws-active-timer">0:00</div>
            </div>
            <div class="sos-ws-active-entity">
                <div id="sos-ws-active-number" class="sos-ws-active-number">—</div>
                <div id="sos-ws-active-name" class="sos-ws-active-name"></div>
                <div id="sos-ws-active-meta" class="sos-ws-active-meta"></div>
            </div>
            <div id="sos-ws-active-context" class="sos-ws-active-context"></div>
            <div class="sos-ws-active-note-wrap">
                <textarea id="sos-ws-live-note" class="sos-ws-live-note"
                    placeholder="Live note — auto-saved…" rows="3"></textarea>
            </div>
            <div class="sos-ws-active-actions">
                <button id="sos-ws-btn-mute" class="sos-ws-act-btn" onclick="SosWorkspace.toggleMute()">
                    <i class="fa fa-microphone"></i><span>Mute</span>
                </button>
                <button class="sos-ws-act-btn sos-btn-hangup" onclick="SalesOSWebRTC.hangup()">
                    <i class="fa fa-phone"></i><span>End</span>
                </button>
            </div>
        </div>

    </div><!-- /sos-ws-panel -->

</div><!-- /sos-workspace -->

<!-- Incoming call popup (outside panel, full overlay) -->
<div id="sos-incoming" class="sos-incoming" style="display:none;">
    <div class="sos-incoming-body">
        <div class="sos-incoming-pulse"><i class="fa fa-phone-square"></i></div>
        <div class="sos-incoming-info">
            <div id="sos-inc-dir" class="sos-inc-dir">Incoming Call</div>
            <div id="sos-inc-number" class="sos-inc-number">—</div>
            <div id="sos-inc-name" class="sos-inc-name"></div>
            <div id="sos-inc-meta" class="sos-inc-meta"></div>
        </div>
        <div class="sos-incoming-actions">
            <?php if (get_option('salesos_webrtc_enabled') == '1' && staff_can('make', 'salesos')): ?>
            <button class="sos-inc-btn sos-inc-accept" onclick="SosWorkspace.acceptIncoming()">
                <i class="fa fa-phone"></i> Answer
            </button>
            <?php endif; ?>
            <button class="sos-inc-btn sos-inc-reject" onclick="SosWorkspace.rejectIncoming()">
                <i class="fa fa-phone"></i> Reject
            </button>
            <button class="sos-inc-btn sos-inc-dismiss" onclick="SosWorkspace.dismissIncoming()">
                Dismiss
            </button>
        </div>
        <div id="sos-inc-crm-links" class="sos-inc-crm-links"></div>
    </div>
</div>

<!-- Remote audio element for WebRTC calls -->
<audio id="salesos-webrtc-audio" autoplay style="display:none;"></audio>
