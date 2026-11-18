<?php
defined('BASEPATH') or exit('No direct script access allowed');

// Set default DataTables parameters if not provided (e.g., when testing with ?debug=1)
if (!isset($_GET['draw'])) {
    $_GET['draw'] = 0;
}
if (!isset($_GET['start'])) {
    $_GET['start'] = 0;
}
if (!isset($_GET['length'])) {
    $_GET['length'] = 10;
}
if (!isset($_GET['search'])) {
    $_GET['search'] = ['value' => '', 'regex' => 'false'];
}

$aColumns = [
    db_prefix() . 'mail_rules.name',
    db_prefix() . 'mail_rules.conditions',
    db_prefix() . 'mail_rules.actions',
    db_prefix() . 'mail_rules.order',
    db_prefix() . 'mail_rules.enabled',
    db_prefix() . 'mail_rules.id',
];

$sIndexColumn = 'id';
$sTable = db_prefix() . 'mail_rules';

$where = [];
array_push($where, 'AND staff_id = ' . get_staff_user_id());

try {
    $result = data_tables_init($aColumns, $sIndexColumn, $sTable, [], $where, [], '', []);

    $output = $result['output'];
    $rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];
    
    // Rule name (use prefixed column name or numeric index)
    $name = $aRow[db_prefix() . 'mail_rules.name'] ?? $aRow['name'] ?? '';
    $row[] = htmlspecialchars($name);
    
    // Conditions
    try {
        $conditions_json = $aRow[db_prefix() . 'mail_rules.conditions'] ?? $aRow['conditions'] ?? '';
        $conditions = json_decode($conditions_json, true);
            $condition_texts = [];
            if ($conditions && is_array($conditions)) {
                foreach ($conditions as $condition) {
                    if (is_array($condition) && isset($condition['field']) && isset($condition['operator']) && isset($condition['value'])) {
                        $condition_texts[] = htmlspecialchars($condition['field']) . ' ' . htmlspecialchars($condition['operator']) . ' "' . htmlspecialchars($condition['value']) . '"';
                    }
                }
            }
            $row[] = !empty($condition_texts) ? implode(' AND ', $condition_texts) : '-';
        } catch (Exception $e) {
            $row[] = 'Error parsing conditions';
            error_log('Rules table - Error parsing conditions: ' . $e->getMessage());
        }
    
    // Actions
    try {
        $actions_json = $aRow[db_prefix() . 'mail_rules.actions'] ?? $aRow['actions'] ?? '';
        $actions = json_decode($actions_json, true);
        $action_texts = [];
        if ($actions && is_array($actions)) {
            foreach ($actions as $action) {
                if (is_array($action) && isset($action['type'])) {
                    $action_texts[] = _l('mailbox_rule_action_' . $action['type']);
                }
            }
        }
        $row[] = !empty($action_texts) ? implode(', ', $action_texts) : '-';
    } catch (Exception $e) {
        $row[] = 'Error parsing actions';
        error_log('Rules table - Error parsing actions: ' . $e->getMessage());
    }
    
    // Order
    $order = $aRow[db_prefix() . 'mail_rules.order'] ?? $aRow['order'] ?? 0;
    $row[] = $order;
    
    // Status
    $enabled = $aRow[db_prefix() . 'mail_rules.enabled'] ?? $aRow['enabled'] ?? 0;
    if ($enabled) {
        $row[] = '<span class="label label-success">' . _l('mailbox_enabled') . '</span>';
    } else {
        $row[] = '<span class="label label-default">' . _l('mailbox_disabled') . '</span>';
    }
    
    // Options
    $rule_id = $aRow[db_prefix() . 'mail_rules.id'] ?? $aRow['id'] ?? 0;
    $options = '';
    $options .= '<a href="#" class="btn btn-default btn-icon" onclick="view_mailbox_rule(' . $rule_id . '); return false;" data-toggle="tooltip" title="' . _l('edit') . '">';
    $options .= '<i class="fa fa-pencil-square"></i>';
    $options .= '</a>';
    $options .= '<a href="#" class="btn btn-danger btn-icon" onclick="delete_mailbox_rule(' . $rule_id . '); return false;" data-toggle="tooltip" title="' . _l('delete') . '">';
    $options .= '<i class="fa fa-remove"></i>';
    $options .= '</a>';
    $row[] = $options;
    
    $output['aaData'][] = $row;
}

} catch (Exception $e) {
    // If there's an error, return error in JSON format
    error_log('Rules table - Fatal error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'draw' => isset($_GET['draw']) ? (int)$_GET['draw'] : 0,
        'iTotalRecords' => 0,
        'iTotalDisplayRecords' => 0,
        'aaData' => []
    ]);
    exit;
}

// Don't echo here - data_tables_init() already does it!
