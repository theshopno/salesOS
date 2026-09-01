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

                        <div class="row mbot30">
                            <div class="col-md-12">
                                <div class="panel_s">
                                    <div class="panel-body bg-light">
                                        <h4 class="no-margin">Schedule Manual Message</h4>
                                        <hr class="hr-panel-heading" />
                                        <?php echo form_open(admin_url('bizbot/queue')); ?>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <?php echo render_input('phone', 'Phone Number (with Country Code)', '', 'text', ['required' => true]); ?>
                                            </div>
                                            <div class="col-md-3">
                                                <?php echo render_datetime_input('scheduled_at', 'Scheduled Date & Time', '', ['required' => true]); ?>
                                            </div>
                                            <div class="col-md-6">
                                                <?php echo render_textarea('message', 'Message', '', ['rows' => 1, 'required' => true]); ?>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-info pull-right">Schedule Message</button>
                                        <?php echo form_close(); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table dt-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Recipient</th>
                                        <th>Message</th>
                                        <th>Scheduled At</th>
                                        <th>Status</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ((array)($queue ?? []) as $item) { ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars((string)($item['id'] ?? '')); ?></td>
                                            <td><?php echo htmlspecialchars((string)($item['phone'] ?? '')); ?> (<?php echo htmlspecialchars((string)($item['rel_type'] ?? '')); ?> #<?php echo htmlspecialchars((string)($item['rel_id'] ?? '')); ?>)</td>
                                            <td>
                                                <div class="text-muted small" style="max-width:400px;"><?php
                                                                                                        $msg_preview = strip_tags((string)($item['message'] ?? ''));
                                                                                                        $msg_preview = (strlen($msg_preview) > 150) ? substr($msg_preview, 0, 150) . '...' : $msg_preview;
                                                                                                        echo htmlspecialchars((string)$msg_preview);
                                                                                                        ?></div>
                                            </td>
                                            <td><?php echo htmlspecialchars((string)($item['scheduled_at'] ?? '')); ?></td>
                                            <td>
                                                <?php
                                                $status_class = 'info';
                                                $status = (string)($item['status'] ?? 'pending');
                                                if ($status == 'sent') $status_class = 'success';
                                                if ($status == 'failed') $status_class = 'danger';
                                                if ($status == 'cancelled') $status_class = 'warning';
                                                ?>
                                                <span class="label label-<?php echo $status_class; ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars((string)($item['created_at'] ?? '')); ?></td>
                                            <td>
                                                <?php if ($status == 'pending') { ?>
                                                    <a href="<?php echo admin_url('bizbot/cancel_queue/' . $item['id']); ?>" class="btn btn-warning btn-icon" data-toggle="tooltip" title="Cancel"><i class="fa fa-ban"></i></a>
                                                <?php } ?>
                                                <a href="<?php echo admin_url('bizbot/delete_queue/' . $item['id']); ?>" class="btn btn-danger btn-icon _delete" data-toggle="tooltip" title="Delete"><i class="fa fa-trash"></i></a>
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
</div>
<?php init_tail(); ?>