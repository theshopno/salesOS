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

                        <?php echo form_open(admin_url('bizbot/templates')); ?>

                        <div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
                            <?php
                            $i = 0;
                            foreach ($categories as $category_name => $events) {
                                echo '<h4 class="bold mtop25">' . $category_name . '</h4>';
                                foreach ($events as $event_slug => $event_name) {
                                    $template = null;
                                    foreach ($template_list as $t) {
                                        if ($t['event_slug'] == $event_slug) {
                                            $template = $t;
                                            break;
                                        }
                                    }
                                    // Defaults
                                    $active = $template ? $template['active'] : 0;
                                    $message = $template ? $template['message'] : '';
                                    $send_to_customer = $template ? $template['send_to_customer'] : 0;
                                    $send_to_staff = $template ? $template['send_to_staff'] : 0;
                                    $send_to_admin = $template ? $template['send_to_admin'] : 0;
                                    $send_to_followers = $template ? $template['send_to_followers'] : 0;
                                    $custom_numbers = $template ? $template['custom_numbers'] : '';
                                    $i++;
                            ?>
                                    <div class="panel panel-default">
                                        <div class="panel-heading" role="tab" id="heading<?php echo $i; ?>">
                                            <h4 class="panel-title">
                                                <a role="button" data-toggle="collapse" data-parent="#accordion"
                                                    href="#collapse<?php echo $i; ?>" aria-expanded="false"
                                                    aria-controls="collapse<?php echo $i; ?>">
                                                    <?php echo $event_name; ?>
                                                </a>
                                                <span class="pull-right">
                                                    <input type="checkbox" name="templates[<?php echo $event_slug; ?>][active]"
                                                        value="1" <?php if ($active) {
                                                                        echo 'checked';
                                                                    } ?>> Enable
                                                </span>
                                            </h4>
                                        </div>
                                        <div id="collapse<?php echo $i; ?>" class="panel-collapse collapse" role="tabpanel"
                                            aria-labelledby="heading<?php echo $i; ?>">
                                            <div class="panel-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>Recipients</label>
                                                            <div class="checkbox">
                                                                <input type="checkbox"
                                                                    name="templates[<?php echo $event_slug; ?>][send_to_customer]"
                                                                    value="1" <?php if ($send_to_customer) {
                                                                                    echo 'checked';
                                                                                } ?>>
                                                                <label><?php echo ($event_slug == 'lead_reminder' || $event_slug == 'lead_staff_reminder') ? 'Send to Lead' : 'Send to Customer'; ?></label>
                                                            </div>
                                                            <div class="checkbox">
                                                                <input type="checkbox"
                                                                    name="templates[<?php echo $event_slug; ?>][send_to_staff]"
                                                                    value="1" <?php if ($send_to_staff) {
                                                                                    echo 'checked';
                                                                                } ?>>
                                                                <label><?php echo ($event_slug == 'lead_reminder' || $event_slug == 'lead_staff_reminder') ? 'Send to Staff (Maker)' : 'Send to Assigned Staff'; ?></label>
                                                            </div>
                                                            <div class="checkbox">
                                                                <input type="checkbox"
                                                                    name="templates[<?php echo $event_slug; ?>][send_to_admin]"
                                                                    value="1" <?php if ($send_to_admin) {
                                                                                    echo 'checked';
                                                                                } ?>>
                                                                <label><?php echo ($event_slug == 'lead_reminder' || $event_slug == 'lead_staff_reminder') ? 'Send to Lead Assigned Staff' : 'Send to Admin'; ?></label>
                                                            </div>
                                                            <div class="checkbox">
                                                                <input type="checkbox"
                                                                    name="templates[<?php echo $event_slug; ?>][send_to_followers]"
                                                                    value="1" <?php if ($send_to_followers) {
                                                                                    echo 'checked';
                                                                                } ?>>
                                                                <label>Send to Followers</label>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Custom Numbers (comma separated)</label>
                                                                <input type="text" class="form-control"
                                                                    name="templates[<?php echo $event_slug; ?>][custom_numbers]"
                                                                    value="<?php echo $custom_numbers; ?>">
                                                            </div>
                                                            <?php if ($event_slug == 'project_deadline_reminder') { ?>
                                                                <div class="form-group">
                                                                    <label>Start reminders X days before deadline</label>
                                                                    <input type="number" class="form-control" min="0" max="30"
                                                                        name="templates[<?php echo $event_slug; ?>][days_before]"
                                                                        value="<?php echo (isset($template['days_before']) ? $template['days_before'] : 0); ?>">
                                                                    <p class="text-info"><i class="fa fa-info-circle"></i> Reminders will continue daily until the deadline is reached.</p>
                                                                </div>
                                                            <?php } ?>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label>Message Helper</label>
                                                            <p class="text-info">
                                                                <b>Global:</b>
                                                                {customer_id}, {customer_name}, {customer_company}, {customer_phone},
                                                                {staff_firstname}, {staff_lastname}, {staff_name}, {staff_phone},
                                                                {company_name}, {crm_url}<br>
                                                                <b>Task:</b>
                                                                {task_name}, {task_description}, {task_status}, {task_startdate}, {task_due_date}, {task_priority}, {task_assigned_names}, {task_link}<br>
                                                                <b>Invoice:</b>
                                                                {invoice_number}, {invoice_amount}, {invoice_due_date}, {invoice_link}<br>
                                                                <b>Lead:</b>
                                                                {lead_id}, {lead_name}, {lead_email}, {lead_phone}, {lead_phonenumber}, {company}, {lead_status}, {lead_assignee}, {lead_last_note}<br>
                                                                <b>Project:</b>
                                                                {project_name}, {project_description}, {project_status}, {project_start_date}, {project_deadline}, {project_assigned_names}, {project_link}, {days_left}, {days_remaining}<br>
                                                                <b>Milestone:</b>
                                                                {milestone_name}, {milestone_description}, {milestone_due_date}<br>
                                                                <b>Ticket:</b>
                                                                {ticket_id}, {ticket_subject}, {ticket_message}, {ticket_link}<br>
                                                                <b>Comment:</b>
                                                                {comment_body}, {comment_staff_name}<br>
                                                                <b>Reminder:</b>
                                                                {reminder_description}, {reminder_date}, {reminder_rel_name}
                                                            </p>
                                                        </div>
                                                        <?php echo render_textarea('templates[' . $event_slug . '][message]', 'Message Body', $message, ['rows' => 5]); ?>
                                                        <input type="hidden"
                                                            name="templates[<?php echo $event_slug; ?>][template_name]"
                                                            value="<?php echo $event_name; ?>">
                                                        <input type="hidden"
                                                            name="templates[<?php echo $event_slug; ?>][event_slug]"
                                                            value="<?php echo $event_slug; ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                            <?php }
                            } ?>
                        </div>

                        <button type="submit" class="btn btn-info p-2">Save Templates</button>
                        <?php echo form_close(); ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>