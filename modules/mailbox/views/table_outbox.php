<?php

defined('BASEPATH') or exit('No direct script access allowed');

$aColumns = [
    db_prefix() . 'mail_outbox.id',
    db_prefix() . 'mail_tags.name as tag_name',
    db_prefix() . 'mail_outbox.to',
    db_prefix() . 'mail_outbox.subject',
    db_prefix() . 'mail_outbox.body',
    db_prefix() . 'mail_outbox.assigned_clients',
    db_prefix() . 'mail_outbox.scheduled_at',
    db_prefix() . 'mail_outbox.date_sent'
];

$sIndexColumn = 'id';
$sTable       = db_prefix() . 'mail_outbox';

$join = [
    'LEFT JOIN ' . db_prefix() . 'mail_tags ON ' . db_prefix() . 'mail_tags.id = ' . db_prefix() . 'mail_outbox.tagid',
    'LEFT JOIN ' . db_prefix() . 'emailtemplates ON ' . db_prefix() . 'emailtemplates.emailtemplateid = ' . db_prefix() . 'mail_outbox.templateid'
];
$where = [];
array_push($where, 'AND trash = 0');
if ($group == 'draft') {
    array_push($where, ' AND draft = 1');
} elseif ($group == 'scheduled') {
    array_push($where, ' AND scheduled_status = "Scheduled"');
} else {
    // For 'sent' group: show normal sent emails AND successfully sent scheduled emails
    array_push($where, ' AND draft = 0');
    if ($group != 'scheduled') {
        array_push($where, ' AND (scheduled_status IS NULL OR scheduled_status = "" OR scheduled_status = "Sent")');
    }
}
array_push($where, ' AND sender_staff_id = '.get_staff_user_id());
$group_by = ' GROUP BY ' . db_prefix() . 'mail_outbox.id';
// Check if timestamp columns exist
$CI = &get_instance();
$additional_fields = [
    db_prefix() . 'mail_outbox.id',
    db_prefix() . 'mail_outbox.stared',
    db_prefix() . 'mail_outbox.important',
    db_prefix() . 'mail_outbox.has_attachment',
    db_prefix() . 'mail_tags.color as tag_color',
    db_prefix() . 'mail_outbox.to',
    db_prefix() . 'mail_outbox.subject',
    db_prefix() . 'mail_outbox.body',
    db_prefix() . 'mail_outbox.assigned_clients',
    db_prefix() . 'mail_outbox.scheduled_at',
    db_prefix() . 'mail_outbox.date_sent'
];

// Add timestamp fields and draft status if they exist
if ($CI->db->field_exists('created_at', db_prefix() . 'mail_outbox')) {
    $additional_fields[] = db_prefix() . 'mail_outbox.created_at';
}
if ($CI->db->field_exists('updated_at', db_prefix() . 'mail_outbox')) {
    $additional_fields[] = db_prefix() . 'mail_outbox.updated_at';
}
$additional_fields[] = db_prefix() . 'mail_outbox.draft';

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, $additional_fields, $group_by, [3]);

$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];
    $starred = "fa-star";
    $msg_starred = _l('mailbox_add_star');
    $important = "fa-bookmark";
    $msg_important = _l('mailbox_mark_as_important');
    if ($aRow['stared'] == 1) {
        $starred = "fa-star orange";
        $msg_starred = _l('mailbox_remove_star');
    }
    if ($aRow['important'] == 1) {
        $important = "fa fa-bookmark green";
        $msg_important = _l('mailbox_mark_as_not_important');
    }
    $has_attachment = "";
    if ($aRow['has_attachment'] > 0) {
        $has_attachment = '<i class="fa fa-paperclip pull-right" data-toggle="tooltip" title="'._l('mailbox_file_attachment').'" data-original-title="fa-paperclip"></i>';
    }
    if ($group == "draft") {
        $type = "outbox"; 
    } else {
        $type = "outbox"; // Sent emails use outbox type
    }

    // Actions button with hover popup
    $row[] = '<div class="mail-row-actions">
                    <div class="checkbox"><input type="checkbox" class="mail-checkbox" value="' . $aRow['id'] . '" data-mail-id="' . $aRow['id'] . '"><label></label></div>
                    <div class="mail-actions-dropdown">
                        <button class="btn btn-sm btn-default mail-actions-trigger" type="button" data-toggle="tooltip" title="'. _l('mailbox_actions').'">
                            <i class="fa fa-ellipsis-v"></i>
                        </button>
                        <div class="mail-actions-popup">
                            <a class="mail-action-item" href="javascript:void(0);" data-toggle="tooltip" title="'. $msg_starred.'" onclick="update_field(\''.$group.'\',\'starred\','.$aRow['stared'].','.$aRow['id'].',\''.$type.'\'); return false;">
                                <i class="fa '.$starred.'"></i> <span>'. $msg_starred.'</span>
                            </a>
                            <a class="mail-action-item" href="javascript:void(0);" data-toggle="tooltip" title="'. $msg_important.'" onclick="update_field(\''.$group.'\',\'important\','.$aRow['important'].','.$aRow['id'].',\''.$type.'\'); return false;">
                                <i class="fa '.$important.'"></i> <span>'. $msg_important.'</span>
                            </a>
                            <a class="mail-action-item" href="javascript:void(0);" data-toggle="tooltip" title="'. _l('mailbox_delete').'" onclick="update_field(\''.$group.'\',\'trash\',1,'.$aRow['id'].',\''.$type.'\'); return false;">
                                <i class="fa fa-trash"></i> <span>'. _l('mailbox_delete').'</span>
                            </a>
                        </div>
                    </div>
                </div>';
    
    // Check if preview pane is enabled
    $preview_enabled = isset($_COOKIE['mailbox_preview_pane']) && $_COOKIE['mailbox_preview_pane'] == '1';
    if ($group == 'draft') {
        $click_action = $preview_enabled ? 'onclick="loadEmailPreview('.$aRow['id'].', \'outbox\'); return false;" href="#"' : 'href="'.admin_url().'mailbox/compose/'.$aRow['id'].'"';
    } else {
        $click_action = $preview_enabled ? 'onclick="loadEmailPreview('.$aRow['id'].', \'outbox\'); return false;" href="#"' : 'href="'.admin_url().'mailbox/outbox/'.$aRow['id'].'"';
    }
    $content = '<a '.$click_action.' data-email-id="'.$aRow['id'].'">';
    
    $row[] = $content.'<span class="label" style="color: #fff; background: '.$aRow['tag_color'].'">'.$aRow['tag_name'].'</span></a>';
    $row[] = $content.'<span>'.$aRow['to'].'</span></a>';
    $row[] = $content.'<span>'.$aRow['subject'].($has_attachment ? ' - </span>' . $has_attachment : '') . '</a>';
    $row[] = $content.text_limiter(clear_textarea_breaks($aRow['body']),10,'...').'</a>';
    $row[] = $content.'<span>'.$aRow['assigned_clients'].'</span></a>';
    $row[] = $content.'<span>'.format_mailbox_date($aRow['scheduled_at']).'</span></a>';
    
    // For date column: show date_sent for sent emails, or fallback to timestamps for drafts
    if ($group == 'draft') {
        // For drafts, try to show a timestamp (created_at, updated_at, or date_sent if it exists)
        if (isset($aRow['created_at']) && !empty($aRow['created_at'])) {
            $date_display = format_mailbox_date($aRow['created_at']);
        } elseif (isset($aRow['updated_at']) && !empty($aRow['updated_at'])) {
            $date_display = format_mailbox_date($aRow['updated_at']);
        } elseif (!empty($aRow['date_sent'])) {
            $date_display = format_mailbox_date($aRow['date_sent']);
        } else {
            $date_display = '<span class="text-muted">Draft</span>';
        }
    } else {
        // For sent emails, show date_sent
        $date_display = !empty($aRow['date_sent']) ? format_mailbox_date($aRow['date_sent']) : '-';
    }
    $row[] = $content.'<span>'.$date_display.'</span></a>';

    // Add CSS classes for starred and important emails
    $row_classes = [];
    if ($aRow['stared'] == 1) {
        $row_classes[] = 'mailbox-row-starred';
    }
    if ($aRow['important'] == 1) {
        $row_classes[] = 'mailbox-row-important';
    }
    if (!empty($row_classes)) {
        $row['DT_RowClass'] = implode(' ', $row_classes);
    }

    $output['aaData'][] = $row;
}