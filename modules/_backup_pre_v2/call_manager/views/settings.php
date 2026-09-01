<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo $title; ?></h4>
                        <hr class="hr-panel-heading" />
                        <?php echo form_open(admin_url('call_manager/settings')); ?>

                        <div class="form-group">
                            <label for="pbp_db_host">Issabel DB Host</label>
                            <input type="text" name="pbp_db_host" id="pbp_db_host" class="form-control" value="<?php echo get_option('call_manager_pbp_db_host'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="pbp_db_name">Issabel DB Name</label>
                            <input type="text" name="pbp_db_name" id="pbp_db_name" class="form-control" value="<?php echo get_option('call_manager_pbp_db_name'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="pbp_db_user">Issabel DB User</label>
                            <input type="text" name="pbp_db_user" id="pbp_db_user" class="form-control" value="<?php echo get_option('call_manager_pbp_db_user'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="pbp_db_password">Issabel DB Password</label>
                            <input type="password" name="pbp_db_password" id="pbp_db_password" class="form-control" value="<?php echo get_option('call_manager_pbp_db_password'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="recordings_url">Recordings Base URL (Local/Private)</label>
                            <input type="text" name="recordings_url" id="recordings_url" class="form-control" value="<?php echo get_option('call_manager_recordings_url'); ?>" placeholder="https://192.168.0.202/recordings/">
                            <p class="help-block">Full URL including protocol and trailing slash. This URL must be accessible from the computer you use to browse the CRM.</p>
                        </div>

                        <div class="text-right">
                            <button type="submit" class="btn btn-info">Save Settings</button>
                        </div>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>