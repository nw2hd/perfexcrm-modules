<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="email-preview">
    <div class="email-header">
        <h4 class="mb-0"><?php echo htmlspecialchars($mailbox->subject); ?></h4>
        <div class="email-meta mt-2">
            <?php 
            // For inbox, show from; for outbox, show the sender
            if ($type == 'inbox') {
                $from_email = isset($mailbox->from_email) ? $mailbox->from_email : '';
                echo '<p class="mb-1"><strong>' . _l('mailbox_from') . ':</strong> ' . htmlspecialchars($mailbox->sender_name);
                if (!empty($from_email)) {
                    echo ' <span class="text-muted">(' . htmlspecialchars($from_email) . ')</span>';
                }
                echo '</p>';
            } else {
                // For outbox, show the sender (staff member)
                echo '<p class="mb-1"><strong>' . _l('mailbox_from') . ':</strong> ' . htmlspecialchars($mailbox->sender_name) . '</p>';
            }
            ?>
            <p class="mb-1"><strong><?php echo _l('mailbox_to'); ?>:</strong> <?php echo htmlspecialchars($mailbox->to); ?></p>
            <?php if (!empty($mailbox->cc)) { ?>
                <p class="mb-1"><strong>CC:</strong> <?php echo htmlspecialchars($mailbox->cc); ?></p>
            <?php } ?>
            <p class="mb-1"><strong><?php echo _l('mailbox_date'); ?>:</strong> 
                <?php 
                // Show appropriate date based on type
                if ($type == 'outbox') {
                    $date_field = !empty($mailbox->date_sent) ? $mailbox->date_sent : 
                                  (!empty($mailbox->updated_at) ? $mailbox->updated_at : 
                                   (isset($mailbox->created_at) ? $mailbox->created_at : ''));
                } else {
                    $date_field = isset($mailbox->date_received) ? $mailbox->date_received : '';
                }
                if (!empty($date_field)) {
                    echo date('d/m/Y H:i', strtotime($date_field));
                } else {
                    echo '-';
                }
                ?>
            </p>
        </div>
        <div class="email-actions mt-3">
            <?php 
            // Determine correct URL based on type and if it's a draft
            $is_draft = isset($mailbox->draft) && $mailbox->draft == 1;
            if ($is_draft) {
                $view_url = admin_url('mailbox/compose/'.$mailbox->id);
            } else {
                $view_url = ($type == 'outbox') ? admin_url('mailbox/outbox/'.$mailbox->id) : admin_url('mailbox/inbox/'.$mailbox->id);
            }
            $reply_url = admin_url('mailbox/reply/'.$mailbox->id.'/reply/'.$type);
            $replyall_url = admin_url('mailbox/reply/'.$mailbox->id.'/replyall/'.$type);
            $forward_url = admin_url('mailbox/reply/'.$mailbox->id.'/forward/'.$type);
            ?>
            <a href="<?php echo $view_url; ?>" class="btn btn-sm btn-primary mr-2">
                <i class="fa fa-external-link"></i> <?php echo $is_draft ? _l('mailbox_edit') : _l('mailbox_open_full'); ?>
            </a>
            <?php if (!$is_draft) { ?>
            <a href="<?php echo $reply_url; ?>" class="btn btn-sm btn-primary mr-2" data-toggle="tooltip" title="<?php echo _l('mailbox_reply'); ?>">
                <i class="fa fa-reply"></i> <?php echo _l('mailbox_reply'); ?>
            </a>
            <a href="<?php echo $replyall_url; ?>" class="btn btn-sm btn-info mr-2" data-toggle="tooltip" title="<?php echo _l('mailbox_reply_all'); ?>">
                <i class="fa fa-reply-all"></i> <?php echo _l('mailbox_reply_all'); ?>
            </a>
            <a href="<?php echo $forward_url; ?>" class="btn btn-sm btn-secondary mr-2" data-toggle="tooltip" title="<?php echo _l('mailbox_forward'); ?>">
                <i class="fa fa-share"></i> <?php echo _l('mailbox_forward'); ?>
            </a>
            <?php } ?>
        </div>
    </div>
    
    <hr>
    <div class="email-body">
        <?php 
        // Check if HTML is enabled for current staff (per-staff preference)
        $CI = &get_instance();
        $staff_id = get_staff_user_id();
        $CI->db->select('enable_html');
        $CI->db->from(db_prefix() . 'staff');
        $CI->db->where('staffid', $staff_id);
        $staff_html_setting = $CI->db->get()->row();
        $html_enabled = isset($staff_html_setting->enable_html) && ($staff_html_setting->enable_html == 1);
        
        if ($html_enabled) {
            // HTML enabled - output as-is (already sanitized)
            echo $mailbox->body;
        } else {
            // HTML disabled - output in paragraph
            echo '<p>' . $mailbox->body . '</p>';
        }
        ?>
    </div>
    <?php if (!empty($attachments) && is_array($attachments) && count($attachments) > 0) { ?>
    <hr>
    <div class="email-attachments">
        <h5><?php echo _l('mailbox_file_attachment'); ?></h5>
        <ul class="list-unstyled">
            <?php foreach ($attachments as $attachment) { 
                if (isset($attachment['file_name']) && !empty($attachment['file_name'])) {
                    $attachment_url = module_dir_url(MAILBOX_MODULE).'uploads/'.$type.'/'.$mailbox->id.'/'.$attachment['file_name'];
            ?>
            <li class="mb-3" style="padding-bottom: 8px; border-bottom: 1px solid #eee;">
                <i class="<?php echo get_mime_class(isset($attachment['file_type']) ? $attachment['file_type'] : ''); ?>"></i>
                <a href="<?php echo $attachment_url; ?>" target="_blank"><?php echo htmlspecialchars($attachment['file_name']); ?></a>
            </li>
            <?php } } ?>
        </ul>
    </div>
    <?php } ?>
</div>

<style>
.email-preview {
    max-height: 600px;
    overflow-y: auto;
}
.email-preview .email-body {
    max-height: 400px;
    overflow-y: auto;
}
.table-mailbox tbody tr.selected {
    background-color: #f0f0f0;
}
</style>
