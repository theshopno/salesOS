<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-6 col-md-offset-3">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo $title; ?></h4>
                        <hr class="hr-panel-heading" />

                        <?php echo form_open(admin_url('pbxpilot/settings')); ?>

                        <div class="form-group">
                            <label for="retention_days">Audio Retention Period (Days)</label>
                            <input type="number" name="retention_days" class="form-control" value="<?php echo $settings->retention_days; ?>" required>
                            <small class="text-muted">Audio files older than this will be deleted via cron.</small>
                        </div>

                        <div class="form-group">
                            <label for="default_ai_provider">Default AI Provider</label>
                            <select name="default_ai_provider" id="default_ai_provider" class="selectpicker" data-width="100%">
                                <option value="openai" <?php echo $settings->default_ai_provider == 'openai' ? 'selected' : ''; ?>>OpenAI Whisper</option>
                                <option value="google" <?php echo $settings->default_ai_provider == 'google' ? 'selected' : ''; ?>>Google Speech-to-Text</option>
                            </select>
                        </div>

                        <div id="openai_config" class="<?php echo $settings->default_ai_provider == 'openai' ? '' : 'hide'; ?>">
                            <div class="form-group">
                                <label for="openai_api_key">OpenAI API Key</label>
                                <div class="input-group">
                                    <input type="password" name="openai_api_key" id="openai_api_key" class="form-control" value="<?php echo $openai_key; ?>">
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-warning test-api" data-provider="openai">Test Connection</button>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="openai_model">OpenAI Model</label>
                                <select name="openai_model" id="openai_model" class="selectpicker" data-width="100%">
                                    <option value="gpt-4o-mini-transcribe" <?php echo get_option('pbxpilot_openai_model') == 'gpt-4o-mini-transcribe' ? 'selected' : ''; ?>>gpt-4o-mini-transcribe (Low cost)</option>
                                    <option value="gpt-4o-transcribe" <?php echo get_option('pbxpilot_openai_model') == 'gpt-4o-transcribe' ? 'selected' : ''; ?>>gpt-4o-transcribe (Standard accuracy)</option>
                                    <option value="gpt-4o-transcribe-diarize" <?php echo get_option('pbxpilot_openai_model') == 'gpt-4o-transcribe-diarize' ? 'selected' : ''; ?>>gpt-4o-transcribe-diarize (Speaker diarization)</option>
                                    <option value="whisper-1" <?php echo get_option('pbxpilot_openai_model', 'whisper-1') == 'whisper-1' ? 'selected' : ''; ?>>whisper-1 (Legacy)</option>
                                </select>
                            </div>
                        </div>

                        <div id="google_config" class="<?php echo $settings->default_ai_provider == 'google' ? '' : 'hide'; ?>">
                            <div class="form-group">
                                <label for="google_project_id">Google Project ID</label>
                                <input type="text" name="google_project_id" class="form-control" value="<?php echo get_option('pbxpilot_google_project_id'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="google_service_account">Google Service Account JSON</label>
                                <textarea name="google_service_account" id="google_service_account" class="form-control" rows="5"><?php echo get_option('pbxpilot_google_service_account'); ?></textarea>
                                <small class="text-muted">Paste the entire content of your JSON service account key file.</small>
                            </div>
                            <button type="button" class="btn btn-warning test-api margin-bottom-15" data-provider="google">Test Google Connection</button>
                        </div>

                        <div class="form-group">
                            <label for="transcription_language">Transcription Language</label>
                            <select name="transcription_language" id="transcription_language" class="selectpicker" data-width="100%">
                                <option value="" <?php echo get_option('pbxpilot_transcription_language') == '' ? 'selected' : ''; ?>>Auto Detect (Recommended)</option>
                                <option value="bn" <?php echo get_option('pbxpilot_transcription_language') == 'bn' ? 'selected' : ''; ?>>Bengali (bn)</option>
                                <option value="en" <?php echo get_option('pbxpilot_transcription_language') == 'en' ? 'selected' : ''; ?>>English (en)</option>
                            </select>
                            <small class="text-muted">If 'bn' returns error, choose 'Auto Detect'.</small>
                        </div>

                        <div class="form-group">
                            <label for="max_audio_size">Max Upload Size (MB)</label>
                            <input type="number" name="max_audio_size" class="form-control" value="<?php echo $settings->max_audio_size; ?>" required>
                        </div>

                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="duplicate_check_enabled" id="duplicate_check_enabled" <?php echo $settings->duplicate_check_enabled ? 'checked' : ''; ?>>
                            <label for="duplicate_check_enabled">Enable Duplicate Detection (Hash Check)</label>
                        </div>

                        <div class="form-group">
                            <label for="product_service">Product/Service Description (Context for AI)</label>
                            <textarea name="product_service" id="product_service" class="form-control" rows="4"><?php echo get_option('pbxpilot_product_service'); ?></textarea>
                            <small class="text-muted">Describe your business briefly. This helps the AI give better sales recommendations.</small>
                        </div>

                        <hr />
                        <h4 class="no-margin">Staff Extensions</h4>
                        <p class="text-muted">Assign extension numbers to staff members for automatic call mapping.</p>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Staff Member</th>
                                    <th>Extension Number</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staff_members as $member): ?>
                                    <tr>
                                        <td><?php echo $member['firstname'] . ' ' . $member['lastname']; ?></td>
                                        <td>
                                            <input type="text" name="staff_extensions[<?php echo $member['staffid']; ?>]"
                                                class="form-control"
                                                value="<?php echo isset($staff_extensions[$member['staffid']]) ? $staff_extensions[$member['staffid']] : ''; ?>"
                                                placeholder="e.g. 106">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <hr />
                        <button type="submit" class="btn btn-info pull-right">Save Settings</button>

                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    $(function() {
        $('#default_ai_provider').on('change', function() {
            var provider = $(this).val();
            if (provider == 'openai') {
                $('#openai_config').removeClass('hide');
                $('#google_config').addClass('hide');
            } else {
                $('#openai_config').addClass('hide');
                $('#google_config').removeClass('hide');
            }
        });

        $('.test-api').on('click', function() {
            var btn = $(this);
            var provider = btn.data('provider');
            var data = {
                provider: provider,
                openai_api_key: $('#openai_api_key').val(),
                google_project_id: $('input[name="google_project_id"]').val(),
                google_service_account: $('#google_service_account').val()
            };

            btn.prop('disabled', true).text('Testing...');

            $.post(admin_url + 'pbxpilot/test_connection', data).done(function(response) {
                response = JSON.parse(response);
                if (response.status) {
                    alert_float('success', response.message);
                } else {
                    alert_float('danger', response.message);
                }
            }).fail(function() {
                alert_float('danger', 'System error occurred while testing connection.');
            }).always(function() {
                btn.prop('disabled', false).text(provider == 'openai' ? 'Test Connection' : 'Test Google Connection');
            });
        });
    });
</script>
</body>

</html>