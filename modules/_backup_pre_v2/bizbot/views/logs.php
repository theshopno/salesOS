<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin pull-left">
                            <?php echo $title; ?>
                        </h4>
                        <div class="pull-right">
                            <div class="btn-group">
                                <a href="<?php echo admin_url('bizbot/cleanup_logs?days=7'); ?>" class="btn btn-default" onclick="return confirm('Delete all logs older than 7 days?');">
                                    <i class="fa fa-trash"></i> Clean 7+ days
                                </a>
                                <a href="<?php echo admin_url('bizbot/cleanup_logs?days=30'); ?>" class="btn btn-default" onclick="return confirm('Delete all logs older than 30 days?');">
                                    <i class="fa fa-trash"></i> Clean 30+ days
                                </a>
                                <a href="<?php echo admin_url('bizbot/cleanup_logs?days=all'); ?>" class="btn btn-danger" onclick="return confirm('WARNING: Are you sure you want to delete ALL logs? This cannot be undone.');">
                                    <i class="fa fa-trash"></i> Clear All Logs
                                </a>
                            </div>
                        </div>
                        <div class="clearfix"></div>
                        <hr class="hr-panel-heading" />

                        <div class="table-responsive">
                            <table class="table dt-table" data-order-col="0" data-order-type="desc">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Date</th>
                                        <th>Trigger</th>
                                        <th>Phone</th>
                                        <th>Message</th>
                                        <th>Status</th>
                                        <th>Response</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log) { ?>
                                        <tr>
                                            <td><?php echo $log['id']; ?></td>
                                            <td><?php echo _dt($log['recorded_at']); ?></td>
                                            <td><?php echo $log['event_trigger']; ?></td>
                                            <td><?php echo $log['phone']; ?></td>
                                            <td style="max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"
                                                title="<?php echo htmlspecialchars($log['message']); ?>">
                                                <?php echo htmlspecialchars(substr($log['message'], 0, 100)) . '...'; ?>
                                            </td>
                                            <td>
                                                <?php if ($log['status'] == 'success') { ?>
                                                    <span class="label label-success">Success</span>
                                                <?php } else { ?>
                                                    <span class="label label-danger">Failed</span>
                                                <?php } ?>
                                            </td>
                                            <td style="max-width:200px; font-size:10px;">
                                                <?php echo htmlspecialchars($log['response']); ?></td>
                                            <td>
                                                <?php if ($log['status'] != 'success') { ?>
                                                    <a href="<?php echo admin_url('bizbot/retry_log/' . $log['id']); ?>"
                                                        class="btn btn-warning btn-icon" data-toggle="tooltip" title="Retry"><i class="fa fa-refresh"></i></a>
                                                <?php } ?>
                                                <a href="<?php echo admin_url('bizbot/delete_log/' . $log['id']); ?>"
                                                    class="btn btn-danger btn-icon _delete" data-toggle="tooltip" title="Delete"><i class="fa fa-trash"></i></a>
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