<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">
                            <?php echo $title; ?>
                        </h4>
                        <hr class="hr-panel-heading" />

                        <?php echo form_open_multipart(admin_url('bizbot/bulk'), array('id' => 'bulk_form')); ?>

                        <div class="row">
                            <div class="col-md-6">
                                <label for="recipient_type">Recipient Group</label>
                                <select name="recipient_type" id="recipient_type" class="form-control selectpicker"
                                    data-live-search="true">
                                    <option value=""></option>
                                    <option value="all_leads">All Leads</option>
                                    <option value="all_c">All Clients (Primary Contact)</option>
                                    <option value="all_staff">All Staff</option>
                                    <option value="custom_csv">Custom CSV Upload</option>
                                </select>

                                <div id="csv_upload_div" class="hide mtop15">
                                    <label>Upload CSV (Column 1: Phone Numbers)</label>
                                    <input type="file" name="csv_file" extension="csv" class="form-control">
                                </div>

                                <!-- Filters -->
                                <div id="lead_filters" class="hide mtop15 panel_s padding-10 border-light">
                                    <p class="bold">Lead Filters</p>
                                    <div class="form-group">
                                        <label for="lead_status">Lead Status</label>
                                        <select name="lead_status" id="lead_status" class="form-control selectpicker" data-none-selected-text="All Statuses">
                                            <option value=""></option>
                                            <?php foreach ($leads_statuses as $status) { ?>
                                                <option value="<?php echo $status['id']; ?>"><?php echo $status['name']; ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="lead_assigned">Assigned Staff</label>
                                        <select name="lead_assigned" id="lead_assigned" class="form-control selectpicker" data-none-selected-text="All Staff" data-live-search="true">
                                            <option value=""></option>
                                            <?php foreach ($staff as $s) { ?>
                                                <option value="<?php echo $s['staffid']; ?>"><?php echo $s['firstname'] . ' ' . $s['lastname']; ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="lead_tags">Tags</label>
                                        <select name="lead_tags[]" id="lead_tags" class="form-control selectpicker" data-none-selected-text="All Tags" multiple data-live-search="true">
                                            <?php foreach ($tags as $tag) { ?>
                                                <option value="<?php echo $tag['name']; ?>"><?php echo $tag['name']; ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <div id="client_filters" class="hide mtop15 panel_s padding-10 border-light">
                                    <p class="bold">Customer Filters</p>
                                    <div class="form-group">
                                        <label for="customer_group">Customer Group</label>
                                        <select name="customer_group" id="customer_group" class="form-control selectpicker" data-none-selected-text="All Groups">
                                            <option value=""></option>
                                            <?php foreach ($groups as $group) { ?>
                                                <option value="<?php echo $group['id']; ?>"><?php echo $group['name']; ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <div id="date_filters" class="hide mtop15 panel_s padding-10 border-light">
                                    <p class="bold">Date Range (Created At)</p>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <?php echo render_date_input('from_date', 'From'); ?>
                                        </div>
                                        <div class="col-md-6">
                                            <?php echo render_date_input('to_date', 'To'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <?php echo render_textarea('message', 'Message Body', '', ['rows' => 10]); ?>
                                <p class="text-info">Bulk messages do not support CRM placeholders like {invoice_id} as
                                    there is no specific context. Use Global Placeholders only: {company_name}.</p>
                            </div>
                        </div>

                        <div class="row mtop20">
                            <div class="col-md-12">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label>Delay between messages (ms)</label>
                                        <input type="number" class="form-control" id="delay_ms" value="3000" min="0" step="100">
                                        <small class="text-muted">Recommended: 3000–10000ms</small>
                                    </div>
                                    <div class="col-md-3">
                                        <label>Random jitter (ms)</label>
                                        <input type="number" class="form-control" id="jitter_ms" value="1000" min="0" step="100">
                                        <small class="text-muted">Adds random delay to look less automated</small>
                                    </div>
                                    <div class="col-md-3">
                                        <label>Max messages this run (0 = all)</label>
                                        <input type="number" class="form-control" id="max_messages" value="0" min="0" step="1">
                                    </div>
                                    <div class="col-md-3">
                                        <label>Pause every N messages (0 = none)</label>
                                        <input type="number" class="form-control" id="pause_every" value="0" min="0" step="1">
                                        <small class="text-muted">Optional throttling</small>
                                    </div>
                                </div>

                                <div class="row mtop10">
                                    <div class="col-md-3">
                                        <label>Pause duration (ms)</label>
                                        <input type="number" class="form-control" id="pause_ms" value="60000" min="0" step="1000">
                                    </div>
                                </div>

                                <div class="mtop15">
                                    <button type="button" id="start_sending" class="btn btn-info">Start Sending</button>
                                    <button type="button" id="pause_sending" class="btn btn-default" disabled>Pause</button>
                                    <button type="button" id="resume_sending" class="btn btn-default" disabled>Resume</button>
                                    <button type="button" id="stop_sending" class="btn btn-danger" disabled>Stop</button>
                                </div>

                                <div class="mtop10">
                                    <small class="text-muted" id="bulk_status"></small>
                                </div>
                            </div>
                        </div>

                        <div id="progress_wrapper" class="hide mtop25">
                            <h4>Sending Progress</h4>
                            <div class="progress">
                                <div class="progress-bar progress-bar-info progress-bar-striped active"
                                    role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"
                                    style="width: 0%" id="progress_bar">
                                    0%
                                </div>
                            </div>
                            <div id="log_console"
                                style="height:200px; overflow-y:scroll; background:#f0f0f0; border:1px solid #ddd; padding:10px;">
                            </div>
                        </div>

                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>

<script>
    $(document).ready(function() {
        $('#recipient_type').on('change', function() {
            var val = $(this).val();
            $('#csv_upload_div, #lead_filters, #client_filters, #date_filters').addClass('hide');

            if (val == 'custom_csv') {
                $('#csv_upload_div').removeClass('hide');
            } else if (val == 'all_leads') {
                $('#lead_filters, #date_filters').removeClass('hide');
            } else if (val == 'all_c') {
                $('#client_filters, #date_filters').removeClass('hide');
            } else if (val == 'all_staff') {
                $('#date_filters').removeClass('hide');
            }
        });

        // Basic resume support (browser-local). Stores progress in localStorage.
        var stateKey = 'bizbot_bulk_state_v1';
        var isPaused = false;
        var isStopped = false;
        var numbers = [];
        var total = 0;
        var current = 0;
        var message = '';
        var type = '';
        var log_console = $('#log_console');

        function readSettings() {
            return {
                delayMs: parseInt($('#delay_ms').val() || '0', 10),
                jitterMs: parseInt($('#jitter_ms').val() || '0', 10),
                maxMessages: parseInt($('#max_messages').val() || '0', 10),
                pauseEvery: parseInt($('#pause_every').val() || '0', 10),
                pauseMs: parseInt($('#pause_ms').val() || '0', 10)
            };
        }

        function nextDelay(settings) {
            var jitter = settings.jitterMs > 0 ? Math.floor(Math.random() * (settings.jitterMs + 1)) : 0;
            return (settings.delayMs || 0) + jitter;
        }

        function updateProgress() {
            var percent = total > 0 ? Math.round((current / total) * 100) : 0;
            $('#progress_bar').css('width', percent + '%').text(percent + '%');
            $('#bulk_status').text('Sent ' + current + ' of ' + total + (isPaused ? ' (paused)' : '') + (isStopped ? ' (stopped)' : ''));
        }

        function saveState(settings) {
            try {
                localStorage.setItem(stateKey, JSON.stringify({
                    numbers: numbers,
                    total: total,
                    current: current,
                    message: message,
                    type: type,
                    settings: settings
                }));
            } catch (e) {
                // ignore
            }
        }

        function clearState() {
            try {
                localStorage.removeItem(stateKey);
            } catch (e) {
                // ignore
            }
        }

        function loadState() {
            try {
                var raw = localStorage.getItem(stateKey);
                if (!raw) return null;
                return JSON.parse(raw);
            } catch (e) {
                return null;
            }
        }

        function enableControls(running) {
            $('#start_sending').prop('disabled', running);
            $('#pause_sending').prop('disabled', !running);
            $('#stop_sending').prop('disabled', !running);
            $('#resume_sending').prop('disabled', running || !isPaused);
        }

        function sendNext() {
            var settings = readSettings();

            if (isStopped) {
                log_console.append('<p><b>Stopped.</b></p>');
                enableControls(false);
                updateProgress();
                saveState(settings);
                return;
            }

            if (isPaused) {
                enableControls(false);
                updateProgress();
                saveState(settings);
                return;
            }

            if (current >= total) {
                log_console.append('<p><b>Finished!</b></p>');
                enableControls(false);
                updateProgress();
                clearState();
                return;
            }

            // Optional pause every N messages
            if (settings.pauseEvery > 0 && current > 0 && (current % settings.pauseEvery) === 0) {
                log_console.append('<p>Auto pause: waiting ' + settings.pauseMs + 'ms after ' + current + ' messages...</p>');
                log_console.scrollTop(log_console[0].scrollHeight);
                saveState(settings);
                setTimeout(sendNext, settings.pauseMs);
                return;
            }

            var phone = numbers[current];

            $.post(admin_url + 'bizbot/send_single_message', {
                phone: phone,
                message: message
            }).done(function(res) {
                var status = 'failed';
                try {
                    var r = JSON.parse(res);
                    status = r.status;
                } catch (e) {
                    status = 'failed (invalid json)';
                }

                var $log = $('<p></p>').text('[' + (current + 1) + '/' + total + '] ' + phone + ': ' + status);
                log_console.append($log);
                log_console.scrollTop(log_console[0].scrollHeight);

                current++;
                updateProgress();
                saveState(settings);

                setTimeout(sendNext, nextDelay(settings));
            }).fail(function(xhr) {
                // Critical: if any request fails and we never increment, the loop "stops".
                // So we log the error and continue.
                var code = xhr && xhr.status ? xhr.status : 'n/a';
                var $log = $('<p style="color:#a94442;"></p>').text('[' + (current + 1) + '/' + total + '] ' + phone + ': failed (HTTP ' + code + ')');
                log_console.append($log);
                log_console.scrollTop(log_console[0].scrollHeight);

                current++;
                updateProgress();
                saveState(settings);

                setTimeout(sendNext, nextDelay(settings));
            });
        }

        // Show resume option if state exists
        var saved = loadState();
        if (saved && saved.numbers && saved.numbers.length) {
            $('#progress_wrapper').removeClass('hide');
            log_console.append('<p><b>Resume available:</b> ' + saved.current + ' / ' + saved.total + ' already processed. Click Resume to continue.</p>');
            isPaused = true;
            numbers = saved.numbers;
            total = saved.total;
            current = saved.current;
            message = saved.message || '';
            type = saved.type || '';

            // Restore settings
            if (saved.settings) {
                $('#delay_ms').val(saved.settings.delayMs);
                $('#jitter_ms').val(saved.settings.jitterMs);
                $('#max_messages').val(saved.settings.maxMessages);
                $('#pause_every').val(saved.settings.pauseEvery);
                $('#pause_ms').val(saved.settings.pauseMs);
            }

            updateProgress();
            $('#resume_sending').prop('disabled', false);
        }

        $('#start_sending').on('click', function() {
            type = $('#recipient_type').val();
            message = $('textarea[name="message"]').val();
            var settings = readSettings();

            if (type == '' && $('#csv_file').val() == '') {
                alert('Please select recipients');
                return;
            }
            if (message == '') {
                alert('Please enter a message');
                return;
            }

            if (type == 'custom_csv' && $('#csv_file').val() == '') {
                alert('Please select a CSV file');
                return;
            }

            isPaused = false;
            isStopped = false;
            current = 0;
            numbers = [];

            $('#progress_wrapper').removeClass('hide');
            log_console.append('<p>Fetching recipients...</p>');

            enableControls(true);

            // Use FormData for file upload support
            var formData = new FormData();
            formData.append('type', type);
            formData.append('lead_status', $('#lead_status').val());
            formData.append('lead_assigned', $('#lead_assigned').val());
            formData.append('lead_tags', $('#lead_tags').val());
            formData.append('customer_group', $('#customer_group').val());
            formData.append('from_date', $('input[name="from_date"]').val());
            formData.append('to_date', $('input[name="to_date"]').val());

            var csvFile = $('input[name="csv_file"]')[0].files[0];
            if (csvFile) {
                formData.append('csv_file', csvFile);
            }

            $.ajax({
                url: admin_url + 'bizbot/get_bulk_recipients',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(data) {
                    try {
                        numbers = JSON.parse(data) || [];
                    } catch (e) {
                        numbers = [];
                    }

                    if (settings.maxMessages > 0) {
                        numbers = numbers.slice(0, settings.maxMessages);
                    }

                    total = numbers.length;

                    log_console.append('<p>Found ' + total + ' recipients. Starting...</p>');
                    updateProgress();
                    saveState(settings);

                    sendNext();
                },
                error: function(xhr) {
                    enableControls(false);
                    var code = xhr && xhr.status ? xhr.status : 'n/a';
                    log_console.append('<p style="color:#a94442;"><b>Failed to fetch recipients</b> (HTTP ' + code + ')</p>');
                }
            });
        });

        $('#pause_sending').on('click', function() {
            isPaused = true;
            enableControls(false);
            updateProgress();
            log_console.append('<p><b>Paused.</b></p>');
            $('#resume_sending').prop('disabled', false);
            saveState(readSettings());
        });

        $('#resume_sending').on('click', function() {
            if (!numbers || !numbers.length) {
                var saved = loadState();
                if (saved && saved.numbers) {
                    numbers = saved.numbers;
                    total = saved.total;
                    current = saved.current;
                    message = saved.message || '';
                    type = saved.type || '';
                }
            }

            isPaused = false;
            isStopped = false;
            $('#progress_wrapper').removeClass('hide');
            enableControls(true);
            log_console.append('<p><b>Resumed.</b></p>');
            updateProgress();
            sendNext();
        });

        $('#stop_sending').on('click', function() {
            isStopped = true;
            isPaused = false;
            enableControls(false);
            updateProgress();
            log_console.append('<p><b>Stopping...</b></p>');
            saveState(readSettings());
        });
    });
</script>