<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="modal fade" id="mailbox_rule_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">
                    <span class="edit-title"><?php echo _l('mailbox_edit_rule'); ?></span>
                    <span class="add-title"><?php echo _l('mailbox_add_rule'); ?></span>
                </h4>
            </div>
            <?php echo form_open(admin_url('mailbox/rules_save'), ['id' => 'mailbox-rule-form']); ?>
            <div class="modal-body">
                <input type="hidden" name="rule_id" id="rule_id" value="">
                
                <div class="form-group">
                    <label for="rule_name"><?php echo _l('mailbox_rule_name'); ?> <span class="text-danger">*</span></label>
                    <input type="text" id="rule_name" name="rule_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label><?php echo _l('mailbox_rule_conditions'); ?> <span class="text-danger">*</span></label>
                    <div id="conditions_container">
                        <!-- Conditions will be added here dynamically -->
                    </div>
                    <button type="button" class="btn btn-sm btn-info" onclick="add_condition();">
                        <i class="fa fa-plus"></i> <?php echo _l('mailbox_add_condition'); ?>
                    </button>
                </div>
                
                <div class="form-group">
                    <label><?php echo _l('mailbox_rule_actions'); ?> <span class="text-danger">*</span></label>
                    <div id="actions_container">
                        <!-- Actions will be added here dynamically -->
                    </div>
                    <button type="button" class="btn btn-sm btn-info" onclick="add_action();">
                        <i class="fa fa-plus"></i> <?php echo _l('mailbox_add_action'); ?>
                    </button>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="rule_order"><?php echo _l('mailbox_rule_order'); ?></label>
                            <input type="number" id="rule_order" name="rule_order" class="form-control" value="0" min="0">
                            <small class="text-muted"><?php echo _l('mailbox_rule_order_help'); ?></small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="rule_enabled"><?php echo _l('mailbox_rule_status'); ?></label>
                            <div class="onoffswitch">
                                <input type="checkbox" name="rule_enabled" class="onoffswitch-checkbox" id="rule_enabled" checked>
                                <label class="onoffswitch-label" for="rule_enabled">
                                    <span class="onoffswitch-inner"></span>
                                    <span class="onoffswitch-switch"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>
</div>

<script>
// Wait for jQuery to be loaded before executing any code
(function() {
    if (typeof jQuery === 'undefined') {
        setTimeout(arguments.callee, 50);
        return;
    }
    
    // Now jQuery is loaded, define everything inside this closure
    (function($) {
        
var condition_count = 0;
var action_count = 0;

window.add_condition = function() {
    condition_count++;
    var html = '<div class="row mbot10 condition-row" data-id="' + condition_count + '">' +
        '<div class="col-md-4">' +
        '<select name="conditions[' + condition_count + '][field]" class="form-control" required>' +
        '<option value="from"><?php echo _l('mailbox_rule_field_from'); ?></option>' +
        '<option value="subject"><?php echo _l('mailbox_rule_field_subject'); ?></option>' +
        '<option value="body"><?php echo _l('mailbox_rule_field_body'); ?></option>' +
        '<option value="to"><?php echo _l('mailbox_rule_field_to'); ?></option>' +
        '</select>' +
        '</div>' +
        '<div class="col-md-3">' +
        '<select name="conditions[' + condition_count + '][operator]" class="form-control" required>' +
        '<option value="contains"><?php echo _l('mailbox_rule_operator_contains'); ?></option>' +
        '<option value="not_contains"><?php echo _l('mailbox_rule_operator_not_contains'); ?></option>' +
        '<option value="equals"><?php echo _l('mailbox_rule_operator_equals'); ?></option>' +
        '<option value="starts_with"><?php echo _l('mailbox_rule_operator_starts_with'); ?></option>' +
        '<option value="ends_with"><?php echo _l('mailbox_rule_operator_ends_with'); ?></option>' +
        '</select>' +
        '</div>' +
        '<div class="col-md-4">' +
        '<input type="text" name="conditions[' + condition_count + '][value]" class="form-control" placeholder="<?php echo _l('mailbox_rule_value'); ?>" required>' +
        '</div>' +
        '<div class="col-md-1">' +
        '<button type="button" class="btn btn-danger btn-sm" onclick="remove_condition(' + condition_count + ');"><i class="fa fa-remove"></i></button>' +
        '</div>' +
        '</div>';
    $('#conditions_container').append(html);
};

window.add_action = function() {
    action_count++;
    var html = '<div class="row mbot10 action-row" data-id="' + action_count + '">' +
        '<div class="col-md-4">' +
        '<select name="actions[' + action_count + '][type]" class="form-control action-type" required>' +
        '<option value="move_to_folder"><?php echo _l('mailbox_rule_action_move_to_folder'); ?></option>' +
        '<option value="mark_as_read"><?php echo _l('mailbox_rule_action_mark_as_read'); ?></option>' +
        '<option value="mark_as_important"><?php echo _l('mailbox_rule_action_mark_as_important'); ?></option>' +
        '<option value="add_tag"><?php echo _l('mailbox_rule_action_add_tag'); ?></option>' +
        '<option value="delete"><?php echo _l('mailbox_rule_action_delete'); ?></option>' +
        '</select>' +
        '</div>' +
        '<div class="col-md-7 action-param-container">' +
        '<input type="text" name="actions[' + action_count + '][value]" class="form-control action-param" placeholder="<?php echo _l('mailbox_rule_action_value'); ?>">' +
        '</div>' +
        '<div class="col-md-1">' +
        '<button type="button" class="btn btn-danger btn-sm" onclick="remove_action(' + action_count + ');"><i class="fa fa-remove"></i></button>' +
        '</div>' +
        '</div>';
    $('#actions_container').append(html);
    
    // Handle action type change
    $('.action-type').off('change').on('change', function() {
        var type = $(this).val();
        var paramContainer = $(this).closest('.action-row').find('.action-param-container');
        var paramInput = paramContainer.find('.action-param');
        
        if (type === 'add_tag') {
            paramInput.replaceWith('<select name="' + paramInput.attr('name') + '" class="form-control action-param selectpicker" data-live-search="true">' +
                '<?php 
                $CI = &get_instance();
                $CI->load->model("mailbox_model");
                $tags = $CI->mailbox_model->get_tags();
                if ($tags && is_array($tags)) {
                    foreach ($tags as $tag) {
                        echo '<option value="' . $tag['id'] . '">' . htmlspecialchars($tag['name']) . '</option>';
                    }
                }
                ?>' +
                '</select>');
            if (typeof $.fn.selectpicker !== 'undefined') {
                $('.selectpicker').selectpicker('refresh');
            }
        } else if (type === 'move_to_folder') {
            paramInput.replaceWith('<select name="' + paramInput.attr('name') + '" class="form-control action-param">' +
                '<option value="archive"><?php echo _l('mailbox_archive'); ?></option>' +
                '<option value="trash"><?php echo _l('mailbox_trash'); ?></option>' +
                '</select>');
        } else {
            paramInput.replaceWith('<input type="text" name="' + paramInput.attr('name') + '" class="form-control action-param" placeholder="<?php echo _l('mailbox_rule_action_value'); ?>">');
        }
    });
};

window.remove_condition = function(id) {
    $('.condition-row[data-id="' + id + '"]').remove();
};

window.remove_action = function(id) {
    $('.action-row[data-id="' + id + '"]').remove();
};

window.view_mailbox_rule = function(id) {
    $('#rule_id').val(id);
    condition_count = 0;
    action_count = 0;
    $('#conditions_container').html('');
    $('#actions_container').html('');
    
    if (id == 0) {
        $('#mailbox_rule_modal .add-title').show();
        $('#mailbox_rule_modal .edit-title').hide();
        $('#rule_name').val('');
        $('#rule_order').val('0');
        $('#rule_enabled').prop('checked', true);
        add_condition();
        add_action();
    } else {
        $('#mailbox_rule_modal .add-title').hide();
        $('#mailbox_rule_modal .edit-title').show();
        // Load rule data via AJAX
        $.get(admin_url + 'mailbox/rules_get/' + id, function(response) {
            try {
                if (typeof response === 'string') {
                    response = JSON.parse(response);
                }
                
                if (response.success && response.rule) {
                    var rule = response.rule;
                    console.log('Loading rule:', rule);
                    
                    $('#rule_name').val(rule.name || '');
                    $('#rule_order').val(rule.order || 0);
                    $('#rule_enabled').prop('checked', rule.enabled == 1);
                
                // Load conditions
                var conditions = JSON.parse(rule.conditions);
                // Convert object to array if needed (conditions stored as {"1": {...}, "2": {...}})
                if (conditions && typeof conditions === 'object') {
                    var conditionsArray = Array.isArray(conditions) ? conditions : Object.values(conditions);
                    conditionsArray.forEach(function(condition) {
                        condition_count++;
                        var html = '<div class="row mbot10 condition-row" data-id="' + condition_count + '">' +
                            '<div class="col-md-4">' +
                            '<select name="conditions[' + condition_count + '][field]" class="form-control" required>' +
                            '<option value="from" ' + (condition.field == 'from' ? 'selected' : '') + '><?php echo _l('mailbox_rule_field_from'); ?></option>' +
                            '<option value="subject" ' + (condition.field == 'subject' ? 'selected' : '') + '><?php echo _l('mailbox_rule_field_subject'); ?></option>' +
                            '<option value="body" ' + (condition.field == 'body' ? 'selected' : '') + '><?php echo _l('mailbox_rule_field_body'); ?></option>' +
                            '<option value="to" ' + (condition.field == 'to' ? 'selected' : '') + '><?php echo _l('mailbox_rule_field_to'); ?></option>' +
                            '</select>' +
                            '</div>' +
                            '<div class="col-md-3">' +
                            '<select name="conditions[' + condition_count + '][operator]" class="form-control" required>' +
                            '<option value="contains" ' + (condition.operator == 'contains' ? 'selected' : '') + '><?php echo _l('mailbox_rule_operator_contains'); ?></option>' +
                            '<option value="not_contains" ' + (condition.operator == 'not_contains' ? 'selected' : '') + '><?php echo _l('mailbox_rule_operator_not_contains'); ?></option>' +
                            '<option value="equals" ' + (condition.operator == 'equals' ? 'selected' : '') + '><?php echo _l('mailbox_rule_operator_equals'); ?></option>' +
                            '<option value="starts_with" ' + (condition.operator == 'starts_with' ? 'selected' : '') + '><?php echo _l('mailbox_rule_operator_starts_with'); ?></option>' +
                            '<option value="ends_with" ' + (condition.operator == 'ends_with' ? 'selected' : '') + '><?php echo _l('mailbox_rule_operator_ends_with'); ?></option>' +
                            '</select>' +
                            '</div>' +
                            '<div class="col-md-4">' +
                            '<input type="text" name="conditions[' + condition_count + '][value]" class="form-control" value="' + $('<div>').text(condition.value || '').html() + '" required>' +
                            '</div>' +
                            '<div class="col-md-1">' +
                            '<button type="button" class="btn btn-danger btn-sm" onclick="remove_condition(' + condition_count + ');"><i class="fa fa-remove"></i></button>' +
                            '</div>' +
                            '</div>';
                        $('#conditions_container').append(html);
                    });
                } else {
                    add_condition();
                }
                
                // Load actions
                var actions = JSON.parse(rule.actions);
                // Convert object to array if needed (actions stored as {"1": {...}, "2": {...}})
                if (actions && typeof actions === 'object') {
                    var actionsArray = Array.isArray(actions) ? actions : Object.values(actions);
                    actionsArray.forEach(function(action) {
                        action_count++;
                        var html = '<div class="row mbot10 action-row" data-id="' + action_count + '">' +
                            '<div class="col-md-4">' +
                            '<select name="actions[' + action_count + '][type]" class="form-control action-type" required>' +
                            '<option value="move_to_folder" ' + (action.type == 'move_to_folder' ? 'selected' : '') + '><?php echo _l('mailbox_rule_action_move_to_folder'); ?></option>' +
                            '<option value="mark_as_read" ' + (action.type == 'mark_as_read' ? 'selected' : '') + '><?php echo _l('mailbox_rule_action_mark_as_read'); ?></option>' +
                            '<option value="mark_as_important" ' + (action.type == 'mark_as_important' ? 'selected' : '') + '><?php echo _l('mailbox_rule_action_mark_as_important'); ?></option>' +
                            '<option value="add_tag" ' + (action.type == 'add_tag' ? 'selected' : '') + '><?php echo _l('mailbox_rule_action_add_tag'); ?></option>' +
                            '<option value="delete" ' + (action.type == 'delete' ? 'selected' : '') + '><?php echo _l('mailbox_rule_action_delete'); ?></option>' +
                            '</select>' +
                            '</div>' +
                            '<div class="col-md-7 action-param-container">' +
                            '<input type="text" name="actions[' + action_count + '][value]" class="form-control action-param" value="' + $('<div>').text(action.value || '').html() + '">' +
                            '</div>' +
                            '<div class="col-md-1">' +
                            '<button type="button" class="btn btn-danger btn-sm" onclick="remove_action(' + action_count + ');"><i class="fa fa-remove"></i></button>' +
                            '</div>' +
                            '</div>';
                        $('#actions_container').append(html);
                    });
                } else {
                    add_action();
                }
            } else {
                console.error('Failed to load rule:', response);
                alert_float('danger', response.message || 'Failed to load rule');
            }
        } catch (e) {
            console.error('Error parsing rule data:', e);
            alert_float('danger', 'Error loading rule: ' + e.message);
        }
        }).fail(function(xhr, status, error) {
            console.error('AJAX error loading rule:', error);
            alert_float('danger', 'Failed to load rule: ' + error);
        });
    }
    
    $('#mailbox_rule_modal').modal('show');
};

window.delete_mailbox_rule = function(id) {
    if (confirm('<?php echo _l('mailbox_rule_delete_confirm'); ?>')) {
        $.post(admin_url + 'mailbox/rules_delete/' + id, function(response) {
            response = JSON.parse(response);
            if (response.success) {
                alert_float('success', response.message);
                $('.table-mailbox-rules').DataTable().ajax.reload();
            } else {
                alert_float('danger', response.message);
            }
        });
    }
};

// Handle form submission via AJAX
$(document).off('submit', '#mailbox-rule-form').on('submit', '#mailbox-rule-form', function(e) {
    e.preventDefault();
    
    // Validate required fields
    var ruleName = $('#rule_name').val();
    if (!ruleName || ruleName.trim() === '') {
        alert_float('warning', 'Rule name is required');
        return false;
    }
    
    var conditionsCount = $('#conditions_container .condition-row').length;
    if (conditionsCount === 0) {
        alert_float('warning', 'At least one condition is required');
        return false;
    }
    
    var actionsCount = $('#actions_container .action-row').length;
    if (actionsCount === 0) {
        alert_float('warning', 'At least one action is required');
        return false;
    }
    
    var formData = $(this).serialize();
    var formAction = $(this).attr('action');
    
    $.ajax({
        url: formAction,
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (typeof response === 'string') {
                response = JSON.parse(response);
            }
            if (response.success) {
                alert_float('success', response.message);
                $('#mailbox_rule_modal').modal('hide');
                if ($.fn.DataTable && $.fn.DataTable.isDataTable('.table-mailbox-rules')) {
                    $('.table-mailbox-rules').DataTable().ajax.reload(null, false);
                }
            } else {
                alert_float('danger', response.message || 'Failed to save rule');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error saving rule:', error);
            alert_float('danger', 'Failed to save rule: ' + error);
        }
    });
    
    return false;
});

    })(jQuery); // End jQuery wrapper
})(); // End immediate function with jQuery check
</script>
