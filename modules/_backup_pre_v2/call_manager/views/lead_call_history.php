<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div role="tabpanel" class="tab-pane" id="call_history">
    <?php
    $phone = '';
    if (isset($lead) && is_object($lead)) {
        $phone = $lead->phonenumber;
    } elseif (isset($client) && is_object($client)) {
        $phone = $client->phonenumber;
    }

    if (empty($phone)) {
        echo '<div class="alert alert-warning">No phone number found for this record.</div>';
    } else {
        $CI = &get_instance();
        $CI->load->model('call_manager/call_manager_model');
        $calls = $CI->call_manager_model->get_call_history($phone);
    ?>
        <div class="table-responsive">
            <table class="table dt-table" data-order-col="0" data-order-type="desc">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Source</th>
                        <th>Destination</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Recording</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($calls as $call) { ?>
                        <tr>
                            <td><?php echo $call['calldate']; ?></td>
                            <td><?php echo $call['src']; ?></td>
                            <td><?php echo $call['dst']; ?></td>
                            <td><?php echo format_call_duration($call['billsec']); ?></td>
                            <td>
                                <span class="label label-<?php echo get_disposition_class($call['disposition']); ?>">
                                    <?php echo $call['disposition']; ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($call['recordingfile'])) {
                                    $base_url = get_option('call_manager_recordings_url');
                                    $base_url = rtrim($base_url, '/') . '/';
                                    $recording_url = $base_url . ltrim($call['recordingfile'], '/');
                                ?>
                                    <button class="btn btn-default btn-icon" onclick="play_call_recording('<?php echo $recording_url; ?>')">
                                        <i class="fa fa-play"></i>
                                    </button>
                                <?php } else { ?>
                                    <span class="text-muted">No recording</span>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <!-- Audio Player Modal -->
        <div class="modal fade" id="recording_modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title">Play Recording</h4>
                    </div>
                    <div class="modal-body text-center">
                        <audio id="call_audio" controls style="width: 100%;">
                            <source src="" type="audio/wav">
                            Your browser does not support the audio element.
                        </audio>
                    </div>
                </div>
            </div>
        </div>

        <script>
            function play_call_recording(url) {
                var audio = document.getElementById('call_audio');
                audio.src = url;
                $('#recording_modal').modal('show');
                audio.play();
            }
        </script>
    <?php } ?>
</div>