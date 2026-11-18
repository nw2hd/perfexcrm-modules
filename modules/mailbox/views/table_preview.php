<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Check if multiple labels table exists FIRST
$CI = &get_instance();
$use_multiple_tags = $CI->db->table_exists(db_prefix() . 'mail_email_tags');

if ($use_multiple_tags) {
    // Multiple labels: Don't join tags in main query to avoid ANY duplicates
    // Tags will be fetched separately in the loop - this was working before
    $aColumns = [
        db_prefix() . 'mail_inbox.id',
        'NULL as tag_name',  // Placeholder, will be replaced in loop
        db_prefix() . 'mail_inbox.sender_name',
        db_prefix() . 'mail_inbox.subject',
        db_prefix() . 'mail_inbox.date_received',
    ];
    $join = [
        'LEFT JOIN ' . db_prefix() . 'emailtemplates ON ' . db_prefix() . 'emailtemplates.emailtemplateid = ' . db_prefix() . 'mail_inbox.templateid'
    ];
} else {
    // Single label: include tag_name in query
    $aColumns = [
        db_prefix() . 'mail_inbox.id',
        db_prefix() . 'mail_tags.name as tag_name',
        db_prefix() . 'mail_inbox.sender_name',
        db_prefix() . 'mail_inbox.subject',
        db_prefix() . 'mail_inbox.date_received',
    ];
    $join = [
        'LEFT JOIN ' . db_prefix() . 'mail_tags ON ' . db_prefix() . 'mail_tags.id = ' . db_prefix() . 'mail_inbox.tagid',
        'LEFT JOIN ' . db_prefix() . 'emailtemplates ON ' . db_prefix() . 'emailtemplates.emailtemplateid = ' . db_prefix() . 'mail_inbox.templateid'
    ];
}

$sIndexColumn = 'id';
$sTable       = db_prefix() . 'mail_inbox';

$where = [];
array_push($where, 'AND to_staff_id = '.get_staff_user_id());

// Advanced search filters
$CI = &get_instance();
if ($CI->input->get('search_from') && $CI->input->get('search_from') != '') {
    $search_from = $CI->db->escape_like_str($CI->input->get('search_from'));
    array_push($where, " AND (LOWER(" . db_prefix() . "mail_inbox.sender_name) LIKE '%" . strtolower($search_from) . "%' OR LOWER(" . db_prefix() . "mail_inbox.from_email) LIKE '%" . strtolower($search_from) . "%')");
}
if ($CI->input->get('search_subject') && $CI->input->get('search_subject') != '') {
    $search_subject = $CI->db->escape_like_str($CI->input->get('search_subject'));
    array_push($where, " AND LOWER(" . db_prefix() . "mail_inbox.subject) LIKE '%" . strtolower($search_subject) . "%'");
}
if ($CI->input->get('search_body') && $CI->input->get('search_body') != '') {
    $search_body = $CI->db->escape_like_str($CI->input->get('search_body'));
    array_push($where, " AND LOWER(" . db_prefix() . "mail_inbox.body) LIKE '%" . strtolower($search_body) . "%'");
}
if ($CI->input->get('search_tag') && $CI->input->get('search_tag') != '') {
    $tag_id = (int)$CI->input->get('search_tag');
    if ($use_multiple_tags) {
        // Multiple labels: use mail_email_tags table
        array_push($where, " AND EXISTS (SELECT 1 FROM " . db_prefix() . "mail_email_tags WHERE " . db_prefix() . "mail_email_tags.email_id = " . db_prefix() . "mail_inbox.id AND " . db_prefix() . "mail_email_tags.tag_id = " . $tag_id . " AND " . db_prefix() . "mail_email_tags.type = 'inbox')");
    } else {
        // Single label: use legacy tagid column
        array_push($where, " AND " . db_prefix() . "mail_inbox.tagid = " . $tag_id);
    }
}
if ($CI->input->get('search_date_from') && $CI->input->get('search_date_from') != '') {
    $date_from = $CI->db->escape_str($CI->input->get('search_date_from'));
    array_push($where, " AND DATE(" . db_prefix() . "mail_inbox.date_received) >= '" . $date_from . "'");
}
if ($CI->input->get('search_date_to') && $CI->input->get('search_date_to') != '') {
    $date_to = $CI->db->escape_str($CI->input->get('search_date_to'));
    array_push($where, " AND DATE(" . db_prefix() . "mail_inbox.date_received) <= '" . $date_to . "'");
}
if ($CI->input->get('search_has_attachment') !== null && $CI->input->get('search_has_attachment') !== '') {
    $has_attachment = (int)$CI->input->get('search_has_attachment');
    array_push($where, " AND " . db_prefix() . "mail_inbox.has_attachment = " . $has_attachment);
}
if ($CI->input->get('search_read') !== null && $CI->input->get('search_read') !== '') {
    $read = (int)$CI->input->get('search_read');
    array_push($where, " AND " . db_prefix() . "mail_inbox.read = " . $read);
}

if ($group == 'inbox') {
    array_push($where, ' AND trash = 0');
    // Exclude archived emails from inbox (only if column exists)
    $CI = &get_instance();
    if ($CI->db->field_exists('archived', db_prefix() . 'mail_inbox')) {
        array_push($where, ' AND (archived = 0 OR archived IS NULL)');
    }
    // Exclude snoozed emails from inbox (only if column exists)
    if ($CI->db->field_exists('snoozed_until', db_prefix() . 'mail_inbox')) {
        array_push($where, ' AND (snoozed_until IS NULL OR snoozed_until <= NOW())');
    }
} else if ($group == 'starred') {
    array_push($where, ' AND trash = 0 AND stared = 1');
    // Exclude archived emails from starred (only if column exists)
    $CI = &get_instance();
    if ($CI->db->field_exists('archived', db_prefix() . 'mail_inbox')) {
        array_push($where, ' AND (archived = 0 OR archived IS NULL)');
    }
} else if ($group == 'important') {
    array_push($where, ' AND trash = 0 AND important = 1');
    // Exclude archived emails from important (only if column exists)
    $CI = &get_instance();
    if ($CI->db->field_exists('archived', db_prefix() . 'mail_inbox')) {
        array_push($where, ' AND (archived = 0 OR archived IS NULL)');
    }
} else if ($group == 'trash') {
    array_push($where, ' AND trash = 1');
} else if ($group == 'archive') {
    array_push($where, ' AND trash = 0');
    // Only show archived emails (only if column exists)
    $CI = &get_instance();
    if ($CI->db->field_exists('archived', db_prefix() . 'mail_inbox')) {
        array_push($where, ' AND archived = 1');
    } else {
        // If column doesn't exist, show empty result
        array_push($where, ' AND 1 = 0');
    }
} else if ($group == 'snoozed') {
    array_push($where, ' AND trash = 0');
    // Only show snoozed emails (only if column exists)
    $CI = &get_instance();
    if ($CI->db->field_exists('snoozed_until', db_prefix() . 'mail_inbox')) {
        array_push($where, ' AND snoozed_until IS NOT NULL AND snoozed_until > NOW()');
    } else {
        // If column doesn't exist, show empty result
        array_push($where, ' AND 1 = 0');
    }
}

// Check if threading columns exist AND threading is enabled in settings (for backward compatibility)
$CI = &get_instance();
$threading_enabled = $CI->db->field_exists('thread_id', db_prefix() . 'mail_inbox') && ('1' == get_option('mailbox_threading_enabled'));

$additionalColumns = [
    db_prefix() . 'mail_inbox.id',
    db_prefix() . 'mail_inbox.stared',
    db_prefix() . 'mail_inbox.important',
    db_prefix() . 'mail_inbox.has_attachment',
    db_prefix() . 'mail_inbox.sender_name',
    db_prefix() . 'mail_inbox.subject',
    db_prefix() . 'mail_inbox.body',
    db_prefix() . 'mail_inbox.read',
    db_prefix() . 'mail_inbox.date_received', // Add date for PHP sorting
];

// Only add tag_color if using single tags (from join)
if (!$use_multiple_tags) {
    $additionalColumns[] = db_prefix() . 'mail_tags.color as tag_color';
}

// Add threading columns only if they exist
if ($threading_enabled) {
    $additionalColumns[] = db_prefix() . 'mail_inbox.thread_id';
    $additionalColumns[] = db_prefix() . 'mail_inbox.in_reply_to';
}

// Add archived and snoozed_until columns if they exist (for actions dropdown)
if ($CI->db->field_exists('archived', db_prefix() . 'mail_inbox')) {
    $additionalColumns[] = db_prefix() . 'mail_inbox.archived';
}
if ($CI->db->field_exists('snoozed_until', db_prefix() . 'mail_inbox')) {
    $additionalColumns[] = db_prefix() . 'mail_inbox.snoozed_until';
}

// Use GROUP BY to prevent duplicates - but when using multiple tags, we don't join tags so no duplicates possible
$group_by = ' GROUP BY ' . db_prefix() . 'mail_inbox.id';

// Get the requested length BEFORE calling data_tables_init()
// IMPORTANT: We need to ensure data_tables_init() respects the length parameter
// The issue is that data_tables_init() might cache the input value, so we need to
// modify $_GET BEFORE calling it, and ensure CodeIgniter's input class sees the change
$CI = &get_instance();
$requested_length = (int)($CI->input->get('length') ?: 10);
$start_param = (int)($CI->input->get('start') ?: 0);
error_log("[Table Preview Debug] Requested length: " . $requested_length . ", start: " . $start_param);

// Store original $_GET value
$original_get_length = isset($_GET['length']) ? $_GET['length'] : null;

// CRITICAL: Modify $_GET BEFORE data_tables_init() reads it
// data_tables_init() reads from $_GET directly or via $CI->input->get()
// We need to ensure it sees the correct length value
// Don't fetch extra rows - just use the requested length directly
// The deduplication happens AFTER data_tables_init(), so we'll handle it then
$_GET['length'] = $requested_length;
error_log("[Table Preview Debug] Set \$_GET['length'] to requested length: " . $requested_length);

// Sort by column 0 (id) - data_tables_init expects just column index, not direction
// Direction is handled by PHP usort() below for server-side sorting
// Column indices: 0=id, 1=tag, 2=from, 3=subject, 4=date
try {
    $result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, $additionalColumns, $group_by, [0]);
    error_log("[Table Preview Debug] After data_tables_init - rows returned: " . count($result['rResult']));
} catch (Exception $e) {
    error_log("[Table Preview Debug] ERROR in data_tables_init: " . $e->getMessage());
    error_log("[Table Preview Debug] Stack trace: " . $e->getTraceAsString());
    throw $e;
}

// Restore original length parameter (though it should already be the requested length)
if ($original_get_length !== null && $original_get_length != $requested_length) {
    $_GET['length'] = $original_get_length;
    error_log("[Table Preview Debug] Restored \$_GET['length'] to original: " . $original_get_length);
}

$output  = $result['output'];
$rResult = $result['rResult'];
$output['_original_count'] = count($rResult);
error_log("[Table Preview Debug] After data_tables_init - rows returned: " . count($rResult));

// Remove duplicates by email ID (safety measure - ensures no duplicates even if SQL returns them)
$unique_results = [];
$seen_ids = [];
foreach ($rResult as $aRow) {
    $email_id = isset($aRow['id']) ? (int)$aRow['id'] : 0;
    if ($email_id > 0 && !in_array($email_id, $seen_ids)) {
        $seen_ids[] = $email_id;
        $unique_results[] = $aRow;
    }
}
$rResult = $unique_results; // Use deduplicated results
$output['_after_dedup_count'] = count($rResult);

// IMPORTANT: data_tables_init() already paginates server-side, so $rResult only contains current page
// However, we need to ensure the total count is correct for pagination to work
// Get DataTables pagination parameters (use the originally requested length)
$CI = &get_instance();
$start = (int)($CI->input->get('start') ?: 0);
$length = $requested_length; // Use the originally requested length, not the fetch_length

// CRITICAL FIX: If data_tables_init() returned fewer rows than requested, manually fetch more
// This happens because data_tables_init() might not respect the length parameter correctly
if (count($rResult) < $length && $length > 0) {
    error_log("[Table Preview Debug] data_tables_init() returned " . count($rResult) . " rows, but " . $length . " were requested. Manually fetching more...");
    
    // Build raw SQL query with correct LIMIT
    $select_fields = [];
    foreach ($aColumns as $col) {
        $select_fields[] = $col;
    }
    foreach ($additionalColumns as $col) {
        if (!in_array($col, $aColumns)) {
            $select_fields[] = $col;
        }
    }
    
    // Build WHERE clause
    $where_sql = implode(' ', $where);
    
    // Build the query
    $manual_query = 'SELECT ' . implode(', ', $select_fields) . ' FROM ' . $sTable;
    
    // Add JOINs
    if (!empty($join)) {
        foreach ($join as $join_clause) {
            $manual_query .= ' ' . $join_clause;
        }
    }
    
    // Add WHERE
    if (!empty($where_sql)) {
        $manual_query .= ' WHERE 1=1 ' . $where_sql;
    }
    
    // Add GROUP BY
    if (!empty($group_by)) {
        $manual_query .= ' ' . $group_by;
    }
    
    // Add ORDER BY (sort by id DESC - newest first)
    $manual_query .= ' ORDER BY ' . db_prefix() . 'mail_inbox.id DESC';
    
    // Add LIMIT
    $manual_query .= ' LIMIT ' . (int)$start . ', ' . (int)$length;
    
    // Execute query with error handling
    $CI->db->reset_query();
    try {
        error_log("[Table Preview Debug] Manual query: " . $manual_query);
        $manual_results = $CI->db->query($manual_query)->result_array();
        error_log("[Table Preview Debug] Manual query returned " . count($manual_results) . " rows");
        
        // Remove duplicates
        $manual_unique = [];
        $manual_seen = [];
        foreach ($manual_results as $aRow) {
            $email_id = isset($aRow['id']) ? (int)$aRow['id'] : 0;
            if ($email_id > 0 && !in_array($email_id, $manual_seen)) {
                $manual_seen[] = $email_id;
                $manual_unique[] = $aRow;
            }
        }
        
        // Use manually fetched results if we got more rows
        if (count($manual_unique) >= count($rResult)) {
            $rResult = array_slice($manual_unique, 0, $length);
            $output['_manual_query'] = 'used_' . count($manual_unique) . '_rows';
            error_log("[Table Preview Debug] Using manually fetched " . count($rResult) . " rows (requested: " . $length . ")");
        } else {
            $output['_manual_query'] = 'not_used_only_' . count($manual_unique) . '_rows';
        }
    } catch (Exception $e) {
        $output['_manual_query'] = 'error: ' . $e->getMessage();
        error_log("[Table Preview Debug] ERROR in manual query: " . $e->getMessage());
        error_log("[Table Preview Debug] Query was: " . $manual_query);
        // Continue with whatever data_tables_init() returned
    }
    
    $CI->db->reset_query();
}

// Sort by date_received DESC (newest first) to match full table order
// This ensures preview pane shows newest emails first, regardless of DataTables client-side sorting
usort($rResult, function($a, $b) {
    $dateA = isset($a['date_received']) ? strtotime($a['date_received']) : (isset($a['id']) ? (int)$a['id'] : 0);
    $dateB = isset($b['date_received']) ? strtotime($b['date_received']) : (isset($b['id']) ? (int)$b['id'] : 0);
    
    // Fallback to ID if date_received is not available (higher ID = newer email)
    if ($dateA == 0 && isset($a['id'])) {
        $dateA = (int)$a['id'];
    }
    if ($dateB == 0 && isset($b['id'])) {
        $dateB = (int)$b['id'];
    }
    
    return $dateB - $dateA; // DESC order (newest first)
});

// Get the current total from output (set by data_tables_init)
$currentTotalRecords = isset($output['iTotalRecords']) ? (int)$output['iTotalRecords'] : 0;
$currentPageSize = count($rResult);

// Always get the actual total count to ensure it's correct
// Build WHERE clause from array
$where_sql = implode(' ', $where);

// Build the COUNT query manually
$count_query = 'SELECT COUNT(DISTINCT ' . db_prefix() . 'mail_inbox.id) as total FROM ' . db_prefix() . 'mail_inbox';

// Add JOINs
if (!empty($join)) {
    foreach ($join as $join_clause) {
        $count_query .= ' ' . $join_clause;
    }
}

// Add WHERE clause
if (!empty($where_sql)) {
    $count_query .= ' WHERE 1=1 ' . $where_sql;
}

// Execute count query
$CI->db->reset_query();
$total_result = $CI->db->query($count_query);
$total_row = $total_result->row();
$actualTotalRecords = $total_row ? (int)$total_row->total : $currentPageSize;

// Update output with correct total counts
$output['iTotalRecords'] = $actualTotalRecords;
$output['iTotalDisplayRecords'] = $actualTotalRecords;

// IMPORTANT: data_tables_init() already returns paginated data (rows for current page)
// We should NOT slice again unless we manually fetched more rows than requested
// Only slice if we explicitly used the manual query and got more rows
if (isset($output['_manual_query']) && strpos($output['_manual_query'], 'used_') === 0 && count($rResult) > $length) {
    // Manual query was used and returned more rows than requested - slice to exact length
    $beforeSlice = count($rResult);
    $rResult = array_slice($rResult, 0, $length);
    $afterSlice = count($rResult);
    error_log("[Table Preview Debug] Sliced manual query results from " . $beforeSlice . " to " . $afterSlice . " rows (length: " . $length . ")");
} else {
    error_log("[Table Preview Debug] Using data_tables_init() results as-is - rows: " . count($rResult) . ", requested length: " . $length);
}
error_log("[Table Preview Debug] Final output - iTotalRecords: " . $output['iTotalRecords'] . ", iTotalDisplayRecords: " . $output['iTotalDisplayRecords'] . ", data count: " . count($rResult));

$CI->db->reset_query();

// Add debug info to JSON response
$output['_debug'] = [
    'requested_length' => $requested_length,
    'start_param' => $start_param,
    'start_at_slice_time' => $start,
    'length_at_slice_time' => $length,
    'rows_from_data_tables_init' => isset($output['_original_count']) ? $output['_original_count'] : 'unknown',
    'rows_after_dedup' => isset($output['_after_dedup_count']) ? $output['_after_dedup_count'] : 'unknown',
    'rows_after_slice' => count($rResult),
    'slice_condition' => ($length > 0 && count($rResult) > $length) ? 'TRUE_DID_SLICE' : 'FALSE_NO_SLICE',
    'manual_query_executed' => isset($output['_manual_query']) ? $output['_manual_query'] : 'not_executed'
];

// Get thread counts for grouping (only if threading is enabled)
$thread_counts = [];
if ($threading_enabled && !empty($rResult)) {
    $thread_ids = array_filter(array_column($rResult, 'thread_id'));
    if (!empty($thread_ids)) {
        $CI->db->reset_query(); // Reset any previous query
        $CI->db->select('thread_id, COUNT(*) as count');
        $CI->db->where_in('thread_id', array_unique($thread_ids));
        $CI->db->where('to_staff_id', get_staff_user_id());
        $CI->db->where('trash', 0);
        $CI->db->group_by('thread_id');
        $thread_results = $CI->db->get(db_prefix() . 'mail_inbox')->result_array();
        foreach ($thread_results as $tr) {
            $thread_counts[$tr['thread_id']] = $tr['count'];
        }
    }
}

foreach ($rResult as $aRow) {
    $row = [];
    $read = "bold";
    if ($aRow['read'] == 1) {
        $read = "";
    }
    
    // Thread indicator (only if threading is enabled)
    $thread_indicator = '';
    $thread_count = 0;
    
    if ($threading_enabled && !empty($aRow['thread_id'])) {
        $thread_count = isset($thread_counts[$aRow['thread_id']]) ? $thread_counts[$aRow['thread_id']] : 1;
        
        // Show reply indicator for nested emails
        if (!empty($aRow['in_reply_to'])) {
            $thread_indicator = '<span class="text-muted" style="margin-right: 5px;">└─</span>';
        }
        
        // Show thread count if multiple emails in thread
        if ($thread_count > 1) {
            $thread_indicator .= '<i class="fa fa-comments text-muted" data-toggle="tooltip" title="' . $thread_count . ' messages in thread" style="margin-right: 5px;"></i>';
        }
    }
    
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

    // Actions button with hover popup
    $actions_popup_html = '<a class="mail-action-item" href="javascript:void(0);" data-toggle="tooltip" title="'. $msg_starred.'" onclick="update_field(\''.$group.'\',\'starred\','.$aRow['stared'].','.$aRow['id'].'); return false;">
                                <i class="fa '.$starred.'"></i> <span>'. $msg_starred.'</span>
                            </a>
                            <a class="mail-action-item" href="javascript:void(0);" data-toggle="tooltip" title="'. $msg_important.'" onclick="update_field(\''.$group.'\',\'important\','.$aRow['important'].','.$aRow['id'].'); return false;">
                                <i class="fa '.$important.'"></i> <span>'. $msg_important.'</span>
                            </a>';
    
    // Add Archive action (only if feature is enabled and column exists)
    $archive_enabled_setting = get_option('mailbox_archive_enabled');
    if ($archive_enabled_setting === '' || $archive_enabled_setting === null) {
        $archive_enabled_setting = '1'; // Default: enabled
    }
    if ($archive_enabled_setting == '1' && $CI->db->field_exists('archived', db_prefix() . 'mail_inbox')) {
        $archived = isset($aRow['archived']) ? (int)$aRow['archived'] : 0;
        $archive_icon = $archived ? 'fa-inbox' : 'fa-archive';
        $archive_msg = $archived ? _l('mailbox_unarchive') : _l('mailbox_archive');
        $actions_popup_html .= '<a class="mail-action-item" href="javascript:void(0);" data-toggle="tooltip" title="'. $archive_msg.'" onclick="update_field(\''.$group.'\',\'archived\','.$archived.','.$aRow['id'].'); return false;">
                                <i class="fa '.$archive_icon.'"></i> <span>'. $archive_msg.'</span>
                            </a>';
    }
    
    // Add Snooze action (only if feature is enabled and column exists)
    $snooze_enabled_setting = get_option('mailbox_snooze_enabled');
    if ($snooze_enabled_setting === '' || $snooze_enabled_setting === null) {
        $snooze_enabled_setting = '1'; // Default: enabled
    }
    if ($snooze_enabled_setting == '1' && $CI->db->field_exists('snoozed_until', db_prefix() . 'mail_inbox')) {
        $snoozed = isset($aRow['snoozed_until']) && strtotime($aRow['snoozed_until']) > time();
        $snooze_icon = $snoozed ? 'fa-bell' : 'fa-clock';
        $snooze_msg = $snoozed ? _l('mailbox_remove_snooze') : _l('mailbox_snooze');
        $snooze_onclick = $snoozed ? 'unsnooze_email('.$aRow['id'].');' : '$(\'#snooze_modal\').modal(\'show\'); $(\'#snooze_email_id\').val('.$aRow['id'].');';
        $actions_popup_html .= '<a class="mail-action-item" href="javascript:void(0);" data-toggle="tooltip" title="'. $snooze_msg.'" onclick="'. $snooze_onclick.' return false;">
                                <i class="fa '.$snooze_icon.'"></i> <span>'. $snooze_msg.'</span>
                            </a>';
    }
    
    $actions_popup_html .= '<a class="mail-action-item" href="javascript:void(0);" data-toggle="tooltip" title="'. _l('mailbox_delete').'" onclick="update_field(\''.$group.'\',\'trash\',1,'.$aRow['id'].'); return false;">
                                <i class="fa fa-trash"></i> <span>'. _l('mailbox_delete').'</span>
                            </a>';
    
    $row[] = '<div class="mail-row-actions">
                    <div class="checkbox"><input type="checkbox" class="mail-checkbox" value="' . $aRow['id'] . '" data-mail-id="' . $aRow['id'] . '"><label></label></div>
                    <div class="mail-actions-dropdown">
                        <button class="btn btn-sm btn-default mail-actions-trigger" type="button" data-toggle="tooltip" title="'. _l('mailbox_actions').'">
                            <i class="fa fa-ellipsis-v"></i>
                        </button>
                        <div class="mail-actions-popup">
                            ' . $actions_popup_html . '
                        </div>
                    </div>
                </div>';

    // Check if preview pane is enabled
    $preview_enabled = isset($_COOKIE['mailbox_preview_pane']) && $_COOKIE['mailbox_preview_pane'] == '1';
    $click_action = $preview_enabled ? 'onclick="loadEmailPreview('.$aRow['id'].'); return false;" href="#"' : 'href="'.admin_url().'mailbox/inbox/'.$aRow['id'].'"';
    $content = '<a '.$click_action.' data-email-id="'.$aRow['id'].'">';
    
    // Display tags (support multiple labels)
    $tag_display = '';
    if ($use_multiple_tags) {
        // Get all tags for this email - use a separate query to avoid conflicts
        $CI->db->reset_query(); // Reset any previous query
        $CI->db->select(db_prefix() . 'mail_tags.name, ' . db_prefix() . 'mail_tags.color');
        $CI->db->from(db_prefix() . 'mail_email_tags');
        $CI->db->join(db_prefix() . 'mail_tags', db_prefix() . 'mail_tags.id = ' . db_prefix() . 'mail_email_tags.tag_id');
        $CI->db->where(db_prefix() . 'mail_email_tags.email_id', $aRow['id']);
        $CI->db->where(db_prefix() . 'mail_email_tags.type', 'inbox');
        $tags = $CI->db->get()->result_array();
        $CI->db->reset_query(); // Reset query builder after use
        
        if (!empty($tags)) {
            $tag_labels = [];
            foreach ($tags as $tag) {
                $tag_labels[] = '<span class="label '.$read.' mailbox-tag-label" style="color: #fff; background: '.($tag['color'] ?: '#999').';">'.htmlspecialchars($tag['name']).'</span>';
            }
            $tag_display = implode('', $tag_labels);
        } else {
            $tag_display = '-';
        }
    } else {
        // Single label: use legacy tag_name
        if (!empty($aRow['tag_name'])) {
            $tag_display = '<span class="label '.$read.'" style="color: #fff; background: '.($aRow['tag_color'] ?: '#999').';">'.htmlspecialchars($aRow['tag_name']).'</span>';
        } else {
            $tag_display = '-';
        }
    }
    
    $row[] = $content.$tag_display.'</a>';
    $row[] = $content.'<span class="'.$read.'">'.$aRow['sender_name'].'</span></a>';
    $row[] = $content.$thread_indicator.'<span class="'.$read.'">'.$aRow['subject'].($has_attachment ? ' - </span>' . $has_attachment : '').'</a>';
    $row[] = $content.'<span class="'.$read.'">'.format_mailbox_date($aRow['date_received']).'</span></a>';

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

echo json_encode($output);
die();
