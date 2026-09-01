<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="bizbot-chat-container">
    <?php if (count($history) > 0) { ?>
        <div class="chat-history-list" style="max-height: 400px; overflow-y: auto; padding: 10px; background: #f9f9f9; border-radius: 4px;">
            <?php foreach ($history as $chat) {
                $is_customer = $chat['sender'] == 'Customer';
                $bg_color = $is_customer ? '#e1ffc7' : '#ffffff';
                $alignment = $is_customer ? 'left' : 'right';
                $margin = $is_customer ? '0 50px 10px 0' : '0 0 10px 50px';
            ?>
                <div class="chat-msg" style="background: <?php echo $bg_color; ?>; padding: 8px 12px; border-radius: 8px; margin: <?php echo $margin; ?>; text-align: left; border: 1px solid #ddd; position: relative;">
                    <small style="color: #888; display: block; margin-bottom: 2px;">
                        <strong><?php echo $chat['sender']; ?></strong>
                        <span class="pull-right"><?php echo time_ago($chat['created_at']); ?></span>
                    </small>
                    <div class="msg-text"><?php echo nl2br(html_escape($chat['message'])); ?></div>
                </div>
            <?php } ?>
        </div>
    <?php } else { ?>
        <p class="text-muted text-center no-mbot">No recent chat history found.</p>
    <?php } ?>

    <?php if (isset($thread_guid) && !empty($thread_guid)) { ?>
        <div class="text-center ptop10">
            <a href="https://app.bizbot.one/app/chat/<?php echo $thread_guid; ?>" target="_blank" class="btn btn-success btn-sm">
                <i class="fa-brands fa-whatsapp"></i> View Full Chat in Bizbot
            </a>
        </div>
    <?php } ?>
</div>