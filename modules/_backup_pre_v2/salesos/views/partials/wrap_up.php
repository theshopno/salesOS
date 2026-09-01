<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!-- SalesOS Post-Call Wrap-Up Panel (rendered by phone_workspace.js) -->
<div id="sos-wrapup" class="sos-wrapup" style="display:none;">
    <input type="hidden" id="sos-wu-call-id" value="">
    <input type="hidden" id="sos-wu-uniqueid" value="">
    <input type="hidden" id="sos-wu-session-id" value="">

    <div class="sos-wu-header">
        <div class="sos-wu-header-left">
            <i class="fa fa-check-circle" style="color:#27ae60;"></i>
            <span>Call Ended</span>
            <span id="sos-wu-duration" class="sos-wu-duration"></span>
        </div>
        <div id="sos-wu-skip-wrap">
            <?php if (staff_can('settings', 'salesos')): ?>
            <button class="btn btn-xs btn-link text-muted" onclick="SosWorkspace.skipWrapUp()">
                Skip <span id="sos-wu-countdown"></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="sos-wu-caller">
        <span id="sos-wu-number" class="sos-wu-number"></span>
        <span id="sos-wu-name" class="sos-wu-name"></span>
    </div>

    <div class="sos-wu-body">

        <div class="form-group sos-wu-group">
            <label class="sos-wu-label">Disposition <span class="text-danger">*</span></label>
            <select id="sos-wu-disposition" class="form-control input-sm">
                <option value="">Select…</option>
                <option value="ANSWERED">Answered</option>
                <option value="NO ANSWER">No Answer</option>
                <option value="BUSY">Busy</option>
                <option value="FAILED">Failed</option>
            </select>
        </div>

        <div class="form-group sos-wu-group">
            <label class="sos-wu-label">Outcome <span class="text-danger">*</span></label>
            <select id="sos-wu-outcome" class="form-control input-sm" onchange="SosWorkspace.onOutcomeChange(this.value)">
                <option value="">Select…</option>
                <option value="Interested">Interested</option>
                <option value="Not Interested">Not Interested</option>
                <option value="Busy">Busy</option>
                <option value="No Answer">No Answer</option>
                <option value="Wrong Number">Wrong Number</option>
                <option value="Callback Requested">Callback Requested</option>
                <option value="Sale Closed">Sale Closed</option>
                <option value="Other">Other</option>
            </select>
            <small id="sos-wu-auto-hint" class="text-muted sos-wu-auto-hint" style="display:none;"></small>
        </div>

        <div id="sos-wu-lead-status-wrap" class="form-group sos-wu-group" style="display:none;">
            <label class="sos-wu-label">Update Lead Status</label>
            <select id="sos-wu-lead-status" class="form-control input-sm">
                <option value="">No change</option>
                <option value="New">New</option>
                <option value="Contacted">Contacted</option>
                <option value="In Progress">In Progress</option>
                <option value="Lost">Lost</option>
                <option value="Junk">Junk</option>
            </select>
        </div>

        <div class="form-group sos-wu-group">
            <label class="sos-wu-label">Notes</label>
            <textarea id="sos-wu-notes" class="form-control input-sm sos-wu-notes"
                rows="3" placeholder="Conversation summary…"></textarea>
        </div>

        <div id="sos-wu-followup-wrap" class="form-group sos-wu-group" style="display:none;">
            <label class="sos-wu-label">Follow-up Date/Time</label>
            <input type="datetime-local" id="sos-wu-followup" class="form-control input-sm">
        </div>

    </div>

    <div class="sos-wu-footer">
        <button class="btn btn-primary btn-sm btn-block" onclick="SosWorkspace.saveWrapUp()">
            <i class="fa fa-save"></i> Save &amp; Close
        </button>
    </div>
</div>
