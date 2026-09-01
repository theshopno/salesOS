<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
    .pbx-btn-process {
        background-color: #626ed4 !important;
        color: white !important;
        border: none !important;
    }

    .pbx-btn-process:hover {
        background-color: #4e59c1 !important;
        color: white !important;
    }

    .pbx-btn-delete {
        background-color: #fc2d42 !important;
        color: #fff !important;
        width: 22px;
        height: 22px;
        padding: 2px 0;
        border-radius: 4px;
        display: inline-block;
        text-align: center;
    }

    .pbx-btn-delete i {
        font-size: 14px;
        line-height: 18px;
    }

    .pbx-btn-delete:hover {
        background-color: #ed2136 !important;
        color: #fff !important;
    }

    .customer-link {
        font-weight: 600;
        color: #337ab7;
    }

    .customer-link i {
        margin-right: 4px;
    }

    .staff-label {
        font-size: 11px;
        padding: 3px 6px;
        background-color: #f3f3f3;
        border: 1px solid #ddd;
        border-radius: 3px;
        display: inline-block;
    }
</style>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo $title; ?></h4>
                        <hr class="hr-panel-heading" />

                        <?php if (has_permission('pbxpilot', '', 'upload')) { ?>
                            <div class="_buttons">
                                <a href="#" class="btn btn-info pull-left display-block" data-toggle="modal" data-target="#upload_audio_modal">
                                    <i class="fa fa-upload"></i> Upload Call Recording
                                </a>
                            </div>
                            <div class="clearfix"></div>
                            <hr class="hr-panel-heading" />
                        <?php } ?>

                        <table class="table dt-table" data-order-col="0" data-order-type="desc">
                            <thead>
                                <tr>
                                    <th>#ID</th>
                                    <th>File Information</th>
                                    <th>Customer / Phone</th>
                                    <th>Staff</th>
                                    <th>Link / Map</th>
                                    <th>Type</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($calls as $call) { ?>
                                    <tr>
                                        <td><?php echo $call['id']; ?></td>
                                        <td>
                                            <span data-toggle="tooltip" title="<?php echo $call['file_name']; ?>" class="text-has-action">
                                                <?php echo substr($call['file_name'], 0, 15) . (strlen($call['file_name']) > 15 ? '...' : ''); ?>
                                            </span>
                                            <?php if ($call['is_duplicate']) { ?>
                                                <br /><span class="label label-warning" style="font-size: 9px;">Duplicate</span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?php if ($call['contact_info']) { ?>
                                                <a href="<?php echo $call['contact_info']['link']; ?>" target="_blank" class="customer-link">
                                                    <i class="fa <?php echo $call['contact_info']['rel_type'] == 'lead' ? 'fa-user' : 'fa-building'; ?>"></i>
                                                    <?php echo $call['contact_info']['name']; ?>
                                                </a>
                                                <br /><small class="text-muted"><?php echo $call['phone_number']; ?></small>
                                            <?php } else { ?>
                                                <span class="text-dark"><?php echo $call['phone_number'] ?: 'Unknown'; ?></span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?php if ($call['assigned_staff']) { ?>
                                                <span class="staff-label"><i class="fa fa-id-card-o"></i> <?php echo $call['assigned_staff']; ?></span>
                                            <?php } else { ?>
                                                <span class="text-muted">---</span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?php if (!$call['contact_info']) { ?>
                                                <button class="btn btn-default btn-xs" onclick="map_lead_modal(<?php echo $call['id']; ?>)">Map Contact</button>
                                            <?php } else { ?>
                                                <span class="text-success"><i class="fa fa-check-circle"></i> Linked</span>
                                            <?php } ?>
                                        </td>
                                        <td><?php echo pbxpilot_format_call_type($call['call_type']); ?></td>
                                        <td><?php echo $call['duration_seconds']; ?>s</td>
                                        <td>
                                            <?php if ($call['is_processed']) { ?>
                                                <span class="label label-success">Processed</span>
                                            <?php } elseif (!empty($call['last_error'])) { ?>
                                                <span class="label label-danger" data-toggle="tooltip" title="<?php echo htmlspecialchars($call['last_error']); ?>">Error</span>
                                            <?php } else { ?>
                                                <span class="label label-default">Pending</span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?php if ($call['ai_summary']) { ?>
                                                <button class="btn btn-info btn-xs" onclick="view_summary(<?php echo $call['id']; ?>)">View Summary</button>
                                            <?php } else { ?>
                                                <a href="<?php echo admin_url('pbxpilot/reprocess/' . $call['id']); ?>" class="btn pbx-btn-process btn-xs">Process AI</a>
                                            <?php } ?>
                                        </td>
                                        <td class="text-right">
                                            <a href="<?php echo admin_url('pbxpilot/delete/' . $call['id']); ?>" class="pbx-btn-delete _delete" data-toggle="tooltip" title="Delete Call"><i class="fa fa-remove"></i></a>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="upload_audio_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?php echo form_open_multipart(admin_url('pbxpilot/upload/do_upload'), ['id' => 'upload-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Upload Call Recording</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="audio_file">Audio File (mp3, wav, m4u)</label>
                    <input type="file" name="audio_file" class="form-control" required>
                </div>
                <!-- Call Case Selection -->
                <div class="form-group mb-3">
                    <label for="upload_call_case" class="control-label">Select Call Case (Intent)</label>
                    <select name="call_case" id="upload_call_case" class="form-control selectpicker" data-none-selected-text="Nothing selected" required data-width="100%">
                        <?php foreach ($cases as $key => $case) { ?>
                            <option value="<?php echo $key; ?>" data-default-format="<?php echo $case['default_format']; ?>">
                                <?php echo $case['name']; ?>
                            </option>
                        <?php } ?>
                    </select>
                    <small class="text-muted">This helps AI understand the purpose of the call.</small>
                </div>

                <!-- Output Format Selection -->
                <div class="form-group mb-3">
                    <label for="upload_preset_type" class="control-label">Select Summary Format</label>
                    <select name="preset_type" id="upload_preset_type" class="form-control selectpicker" data-none-selected-text="Nothing selected" required data-width="100%">
                        <?php foreach ($formats as $key => $label) { ?>
                            <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="ai_provider">Override AI Provider</label>
                    <select name="ai_provider" class="selectpicker" data-width="100%">
                        <option value="openai">OpenAI Whisper (Recommended)</option>
                        <option value="google">Google Speech-to-Text</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-info">Upload & Process</button>
            </div>
        </div>
        <?php echo form_close(); ?>
    </div>
</div>

<!-- Summary Modal -->
<div class="modal fade" id="summary_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">AI Summary & Version History</h4>
            </div>
            <div class="modal-body" style="background: #f9fafb;">
                <div id="summary_history_container">
                    <!-- History entries will be injected here -->
                </div>

                <div class="rephrase-section well mtop20">
                    <h5 class="bold"><i class="fa fa-magic"></i> Rephrase & Save New Version</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="rephrase_model">AI Model</label>
                                <select id="rephrase_model" class="selectpicker" data-width="100%">
                                    <option value="gpt-4o-mini">GPT-4o Mini (Fast & Cheap)</option>
                                    <option value="gpt-4o">GPT-4o (Smart & Detailed)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <!-- Call Case Selection -->
                            <div class="form-group mb-3">
                                <label for="rephrase_call_case" class="control-label">Select Call Case (Intent)</label>
                                <select name="rephrase_call_case" id="rephrase_call_case" class="form-control selectpicker" data-none-selected-text="Nothing selected" required>
                                    <?php foreach ($cases as $key => $case) { ?>
                                        <option value="<?php echo $key; ?>" data-default-format="<?php echo $case['default_format']; ?>">
                                            <?php echo $case['name']; ?>
                                        </option>
                                    <?php } ?>
                                </select>
                                <small class="text-muted">This helps AI understand the purpose of the call.</small>
                            </div>

                            <!-- Output Format Selection -->
                            <div class="form-group mb-3">
                                <label for="rephrase_preset_type" class="control-label">Select Summary Format</label>
                                <select name="rephrase_preset_type" id="rephrase_preset_type" class="form-control selectpicker" data-none-selected-text="Nothing selected" required>
                                    <?php foreach ($formats as $key => $label) { ?>
                                        <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <button type="button" class="btn btn-warning btn-block" onclick="rephrase_summary()">
                                <i class="fa fa-refresh"></i> Rephrase & Add to History
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<style>
    .summary-version-block {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .summary-version-meta {
        font-size: 11px;
        color: #64748b;
        margin-bottom: 10px;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 5px;
    }

    .summary-version-text {
        font-size: 13px;
        line-height: 1.6;
        color: #1e293b;
    }
</style>

<script>
    var current_call_id = null;
    var calls_data = <?php echo json_encode($calls); ?>;

    function view_summary(id) {
        current_call_id = id;
        var call = calls_data.find(c => c.id == id);
        if (!call) return;

        $('#summary_modal').modal('show');
        render_summary_history(call);

        $('#rephrase_model').selectpicker('val', call.last_model_used || 'gpt-4o-mini');

        var currentCase = call.last_case_used || 'CASE_GENERAL_INQUIRY';
        var currentFormat = call.preset_type || 'DETAILED_SUMMARY_ACTION';

        $('#rephrase_call_case').selectpicker('val', currentCase);
        $('#rephrase_preset_type').selectpicker('val', currentFormat);
    }

    function render_summary_history(call) {
        var container = $('#summary_history_container');
        container.empty();

        var history = [];
        try {
            history = typeof call.summary_history === 'string' ? JSON.parse(call.summary_history || '[]') : (call.summary_history || []);
        } catch (e) {
            console.error("History parse error", e);
        }

        if (history.length === 0) {
            if (call.ai_summary) {
                // Legacy support
                history = [{
                    id: 'legacy',
                    text: call.ai_summary,
                    model: call.last_model_used || 'N/A',
                    preset: call.preset_type || 'N/A',
                    created_at: 'Initial'
                }];
            } else {
                container.html('<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> AI বর্তমানে এই কলটি প্রসেস করছে। অনুগ্রহ করে একটু অপেক্ষা করুন...</div>');
                return;
            }
        }

        // Show versions in reverse order (newest first)
        history.slice().reverse().forEach(function(item, index) {
            var versionId = history.length - index;
            var escapedText = item.text.replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");

            var html = '<div class="summary-version-block">';
            html += '  <div class="summary-version-meta">';
            html += '    <span class="pull-right">#' + versionId + '</span>';
            html += '    <strong>Model:</strong> ' + item.model + ' | <strong>Preset:</strong> ' + item.preset + ' | <strong>Case:</strong> ' + (item.case || 'N/A') + ' | <strong>Time:</strong> ' + item.created_at;
            html += '  </div>';
            html += '  <div class="summary-version-editor">';
            html += '    <textarea id="summary_v' + versionId + '" class="form-control" rows="8" style="resize: vertical; border: 1px solid #cbd5e1; background: #fff;">' + escapedText + '</textarea>';
            html += '  </div>';
            html += '  <div class="mtop10 text-right">';
            html += '    <button class="btn btn-info btn-xs" onclick="inject_to_notes(' + versionId + ')">';
            html += '      <i class="fa fa-plus-circle"></i> Refine & Inject to Notes';
            html += '    </button>';
            html += '  </div>';
            html += '</div>';
            container.append(html);
        });
    }

    function rephrase_summary() {
        if (!current_call_id) return;

        var model = $('#rephrase_model').val();
        var case_type = $('#rephrase_call_case').val();
        var format = $('#rephrase_preset_type').val();
        var btn = $('.rephrase-section button');

        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Generating New Version...');

        $.post(admin_url + 'pbxpilot/rephrase_ajax', {
            call_id: current_call_id,
            model: model,
            call_case: case_type,
            preset_type: format,
            <?php echo $this->security->get_csrf_token_name(); ?>: '<?php echo $this->security->get_csrf_hash(); ?>'
        }, function(res) {
            var data = JSON.parse(res);
            if (data.status) {
                var callIndex = calls_data.findIndex(c => c.id == current_call_id);
                if (callIndex !== -1) {
                    calls_data[callIndex].ai_summary = data.summary;
                    calls_data[callIndex].summary_history = JSON.stringify(data.history);
                    render_summary_history(calls_data[callIndex]);
                }
                alert_float('success', 'New summary version added to history');
            } else {
                alert_float('danger', 'Error: ' + data.error);
            }
            btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> Rephrase & Add to History');
        });
    }

    function inject_to_notes(versionId) {
        var summaryText = $('#summary_v' + versionId).val();
        if (!current_call_id) return;

        alert_float('info', 'Injecting to notes...');

        $.post(admin_url + 'pbxpilot/inject_note_ajax', {
            call_id: current_call_id,
            summary: summaryText,
            <?php echo $this->security->get_csrf_token_name(); ?>: '<?php echo $this->security->get_csrf_hash(); ?>'
        }, function(res) {
            var data = JSON.parse(res);
            if (data.status) {
                alert_float('success', data.message);
            } else {
                alert_float('danger', data.error);
            }
        });
    }

    $(function() {
        // Auto-select format based on case in Upload Modal
        $('#upload_call_case').on('change', function() {
            var defaultFormat = $(this).find(':selected').data('default-format');
            if (defaultFormat) {
                $('#upload_preset_type').selectpicker('val', defaultFormat);
            }
        });

        // Auto-select format based on case in Rephrase Modal/Section
        $('#rephrase_call_case').on('change', function() {
            var defaultFormat = $(this).find(':selected').data('default-format');
            if (defaultFormat) {
                $('#rephrase_preset_type').selectpicker('val', defaultFormat);
            }
        });

        // Automatically trigger background processing for "Pending" calls
        $('span.label-default').each(function() {
            var label = $(this);
            var row = label.closest('tr');
            var id = row.find('td:first').text().trim();

            if (id && label.text().trim() == 'Pending') {
                label.removeClass('label-default').addClass('label-info').html('<i class="fa fa-spinner fa-spin"></i> Processing...');

                $.get(admin_url + 'pbxpilot/process_queued_ajax/' + id, function(res) {
                    var data = JSON.parse(res);
                    if (data.status) {
                        label.removeClass('label-info').addClass('label-success').text('Processed');

                        var callIndex = calls_data.findIndex(c => c.id == id);
                        if (callIndex !== -1) {
                            calls_data[callIndex].ai_summary = data.summary;
                            calls_data[callIndex].is_processed = 1;
                            // Initialize history locally if returned or just use the summary
                            calls_data[callIndex].summary_history = JSON.stringify([{
                                id: 'init',
                                text: data.summary,
                                model: 'Init',
                                preset: 'Init',
                                created_at: new Date().toLocaleString()
                            }]);
                        }

                        if (current_call_id == id && $('#summary_modal').is(':visible')) {
                            render_summary_history(calls_data[callIndex]);
                        }

                        var actionCell = row.find('td:nth-child(9)');
                        actionCell.html('<button class="btn btn-info btn-xs" onclick="view_summary(' + id + ')">View Summary</button>');
                    } else {
                        label.removeClass('label-info').addClass('label-danger').attr('title', data.error).text('Error');
                    }
                });
            }
        });
    });

    function map_lead_modal(id) {
        var lead_id = prompt("Please enter Lead ID to map:");
        if (lead_id) {
            $.post(admin_url + 'pbxpilot/map_lead', {
                call_id: id,
                lead_id: lead_id,
                <?php echo $this->security->get_csrf_token_name(); ?>: '<?php echo $this->security->get_csrf_hash(); ?>'
            }).done(function() {
                window.location.reload();
            });
        }
    }
</script>
</body>

</html>