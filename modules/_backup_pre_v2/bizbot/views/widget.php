<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo $title; ?></h4>
                        <hr class="hr-panel-heading" />

                        <div class="row">
                            <div class="col-md-6">
                                <?php echo form_open(admin_url('bizbot/widget')); ?>
                                <?php echo render_input('widget_id', 'AnyChat Widget ID', get_option('bizbot_widget_id'), 'text', ['required' => true]); ?>

                                <?php
                                $types = [
                                    ['id' => '1', 'name' => 'Legacy (Standard)'],
                                    ['id' => '2', 'name' => 'Pro Widget (v1)'],
                                    ['id' => '3', 'name' => 'Pro Widget (v2 - Recommended)'],
                                ];
                                echo render_select('integration_type', $types, ['id', 'name'], 'Integration Type', get_option('bizbot_widget_type') ?: '3');
                                ?>

                                <hr />
                                <h4>CRM Portal Injection</h4>
                                <p class="text-muted">Enable this to show the WhatsApp widget directly inside your CRM portals.</p>

                                <div class="checkbox checkbox-primary">
                                    <input type="checkbox" id="show_to_admin" name="show_to_admin" <?php echo get_option('bizbot_widget_show_to_admin') == '1' ? 'checked' : ''; ?>>
                                    <label for="show_to_admin">Show in Admin Panel</label>
                                </div>
                                <div class="checkbox checkbox-primary mbot20">
                                    <input type="checkbox" id="show_to_customer" name="show_to_customer" <?php echo get_option('bizbot_widget_show_to_customer') == '1' ? 'checked' : ''; ?>>
                                    <label for="show_to_customer">Show in Customer Portal (Client Area)</label>
                                </div>

                                <button type="submit" class="btn btn-info">Save & Generate Code</button>
                                <?php echo form_close(); ?>
                            </div>

                            <div class="col-md-6">
                                <?php
                                $widget_id = get_option('bizbot_widget_id');
                                $type = get_option('bizbot_widget_type') ?: '3';

                                if (!empty($widget_id)) {
                                    $script = '';
                                    if ($type == '2') {
                                        $script = "(function(d, s, id){
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) {return;}
  js = d.createElement(s); js.id = id;
  js.src = 'https://api.bizbot.one/widget/{$widget_id}?r=' + encodeURIComponent(window.location);
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'contactus-jssdk'));";
                                    } elseif ($type == '3') {
                                        $script = "(function(d, s, id){
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = 'https://api.bizbot.one/widget2/load?id={$widget_id}&r=' + encodeURIComponent(window.location);
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'anw2-sdk-{$widget_id}'));";
                                    } else {
                                        $script = "(function(d, s, id){
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) {return;}
  js = d.createElement(s); js.id = id;
  js.src = 'https://api.bizbot.one/widget/{$widget_id}/livechat-js?r=' + encodeURIComponent(window.location);
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'contactus-jssdk'));";
                                    }
                                ?>
                                    <h4>Generated Widget Code</h4>
                                    <p class="text-muted">Copy this code and paste it before the <code>&lt;/body&gt;</code> tag of your website.</p>
                                    <textarea class="form-control" rows="10" readonly id="widget_code_area"><script><?php echo $script; ?></script></textarea>
                                    <button class="btn btn-default mtop10" onclick="copyToClipboard()">Copy to Clipboard</button>
                                <?php } else { ?>
                                    <div class="alert alert-warning">Please enter your Widget ID and save to generate the code.</div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    function copyToClipboard() {
        var copyText = document.getElementById("widget_code_area");
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        document.execCommand("copy");
        alert("Code copied to clipboard!");
    }
</script>
<?php init_tail(); ?>