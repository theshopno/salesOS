<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!-- SalesOS Call Popup Container (populated by JS polling) -->
<div id="salesos-popup-container" style="position:fixed;bottom:20px;right:20px;z-index:9999;width:340px;"></div>

<!-- Outbound call dialer (hidden by default) -->
<div id="salesos-dialer" class="salesos-dialer" style="display:none;">
    <div class="salesos-dialer-header">
        <span><i class="fa fa-phone"></i> Quick Dial</span>
        <button onclick="document.getElementById('salesos-dialer').style.display='none'" class="salesos-close-btn">×</button>
    </div>
    <div class="salesos-dialer-body">
        <input type="tel" id="salesos-dial-number" class="salesos-dial-input" placeholder="Enter number...">
        <button class="salesos-call-btn" onclick="SalesOS.originate(document.getElementById('salesos-dial-number').value)">
            <i class="fa fa-phone"></i> Call
        </button>
    </div>
</div>

<!-- Floating dial button -->
<?php if (staff_can('make', 'salesos')): ?>
<button id="salesos-fab"
    onclick="document.getElementById('salesos-dialer').style.display='block'"
    title="Quick Dial"
    style="position:fixed;bottom:20px;left:20px;z-index:9998;
           width:48px;height:48px;border-radius:50%;border:none;
           background:#27ae60;color:#fff;font-size:18px;cursor:pointer;
           box-shadow:0 2px 8px rgba(0,0,0,.3);">
    <i class="fa fa-phone"></i>
</button>
<?php endif; ?>
