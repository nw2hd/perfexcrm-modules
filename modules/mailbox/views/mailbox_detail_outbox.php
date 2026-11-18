<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="clearfix mtop20"></div>
<div class="">
    <div class="email-media">
        <div class="media mt-0">
            <?php echo staff_profile_image($mailbox->sender_staff_id, ['mr-2 rounded-circle']); ?>  
        
            <div class="media-body">
                <div class="float-right d-md-flex fs-15">
                    <small class="mr-3"><?php echo date('d/m/Y H:i', strtotime($mailbox->date_sent)); ?></small>
                    <a href="<?php echo admin_url().'mailbox/reply/'.$mailbox->id.'/reply/outbox'; ?>" class="btn btn-sm btn-primary mr-2" data-toggle="tooltip" title="<?php echo _l('mailbox_reply'); ?>">
                        <i class="fa fa-reply"></i> <?php echo _l('mailbox_reply'); ?>
                    </a>
                    <a href="<?php echo admin_url().'mailbox/reply/'.$mailbox->id.'/replyall/outbox'; ?>" class="btn btn-sm btn-info mr-2" data-toggle="tooltip" title="<?php echo _l('mailbox_reply_all'); ?>">
                        <i class="fa fa-reply-all"></i> <?php echo _l('mailbox_reply_all'); ?>
                    </a>
                    <a href="<?php echo admin_url().'mailbox/reply/'.$mailbox->id.'/forward/outbox'; ?>" class="btn btn-sm btn-secondary" data-toggle="tooltip" title="<?php echo _l('mailbox_forward'); ?>">
                        <i class="fa fa-share"></i> <?php echo _l('mailbox_forward'); ?>
                    </a>
                </div>
                <div class="media-title text-dark font-weight-semiblod"><?php echo $mailbox->sender_name; ?> <span class="text-muted">( <?php echo get_staff_email_by_id($mailbox->sender_staff_id); ?> )</span></div>
                <p class="mb-0 font-weight-semiblod">To: <?php echo $mailbox->to; ?></p>
                <p class="mb-0 font-weight-semiblod">Cc: <?php echo $mailbox->cc; ?></p>
            </div>
        </div>
    </div>
    <div class="email-body mleft20 mright20 mtop20 mbot20">
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
        <hr>
        <?php if ($mailbox->has_attachment > 0 || !empty($attachments)) { ?>
            <div class="email-attch">
                <p><?php echo _l('mailbox_file_attachment'); ?></p>
                <div class="emai-img">
                    <div class="">
                        <?php 
                        if (!empty($attachments) && is_array($attachments)) {
                            foreach ($attachments as $attachment) {
                                $attachment_url = module_dir_url(MAILBOX_MODULE).'uploads/'.$type.'/'.$mailbox->id.'/'.$attachment['file_name']; 
                        ?>
                            <div class="mbot15 row" data-attachment-id="<?php echo $attachment['id']; ?>">
                                <div class="col-md-8">
                                    <div class="mbpull-left"><i class="<?php echo get_mime_class($attachment['file_type']); ?>"></i></div>
                                    <a href="<?php echo $attachment_url; ?>" target="_blank"><?php echo $attachment['file_name']; ?></a>
                                    <br />
                                    <small class="text-muted"> <?php echo $attachment['file_type']; ?></small>
                                </div>
                            </div>
                        <?php 
                            }
                        } else {
                            echo '<p class="text-muted">No attachments found in database.</p>';
                        }
                        ?>        
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>

    <div class="pull-left">
        <div class="form-group">
            <?php
            // Check if multiple labels are supported (table exists AND setting is enabled)
            $CI = &get_instance();
            $CI->load->model('mailbox_model');
            $multiple_tags_enabled = get_option('mailbox_enable_multiple_tags');
            if ($multiple_tags_enabled === '' || $multiple_tags_enabled === null) {
                $multiple_tags_enabled = '1'; // Default: enabled
            }
            $use_multiple_tags = $CI->db->table_exists(db_prefix() . 'mail_email_tags') && ('1' == $multiple_tags_enabled);
            
            // Get selected tag IDs
            if ($use_multiple_tags) {
                // Multiple labels: get all tags for this email
                $email_tags = $CI->mailbox_model->get_email_tags($mailbox->id, 'outbox');
                // Extract tag IDs - handle both array and object results
                $selected_tag_ids = [];
                if (!empty($email_tags)) {
                    foreach ($email_tags as $tag) {
                        if (is_array($tag)) {
                            $selected_tag_ids[] = isset($tag['id']) ? $tag['id'] : (isset($tag['tag_id']) ? $tag['tag_id'] : null);
                        } else if (is_object($tag)) {
                            $selected_tag_ids[] = isset($tag->id) ? $tag->id : (isset($tag->tag_id) ? $tag->tag_id : null);
                        }
                    }
                }
                // Remove null values
                $selected_tag_ids = array_filter($selected_tag_ids, function($v) { return $v !== null; });
                $selected_tag_ids = array_values($selected_tag_ids); // Re-index array
            } else {
                // Single label: use legacy tagid
                $selected_tag_ids = $mailbox->tagid ? [$mailbox->tagid] : [];
            }
            
            // Prepare tags array for render_select (format: [['id' => X, 'name' => 'Y'], ...])
            $tags_array = [];
            $all_tags = get_mail_tags();
            foreach ($all_tags as $tag) {
                $tags_array[] = [
                    'id' => $tag['id'],
                    'name' => $tag['name']
                ];
            }
            
            // Use Perfex CRM's built-in render_select with conditional multi-select support
            $select_attrs = ['data-actions-box' => true, 'class' => 'mail-tag-multiple', 'data-id' => $mailbox->id, 'data-type' => 'outbox'];
            if ($use_multiple_tags) {
                $select_attrs['multiple'] = true;
            }
            echo render_select(
                'mail_tag_select_' . $mailbox->id,  // name
                $tags_array,                        // options
                ['id', 'name'],                     // option_attrs
                _l('mailbox_tags'),                 // label
                $selected_tag_ids,                  // selected
                $select_attrs,                      // select_attrs
                [],                                 // form_group_attr
                '',                                 // form_group_class
                '',                                 // select_class
                false                               // include_blank
            );
            ?>
        </div>
    </div>
    <div class="pull-right">
        <a class="btn btn-info mbot10 mr-2" type="button" data-toggle="modal" data-target="#customers_item_modal"><i class="fa fa-user"></i> <?php echo _l('assign_customers'); ?></a>
        <a class="btn btn-danger mbot10 mr-2" type="button" data-toggle="modal" data-target="#task_item_modal" <?php echo $mailbox->taskid ? "disabled" : "" ?>><i class="fa fa-tasks"></i> <?php echo _l('assign_task'); ?></a>
        <button class="btn btn-success mbot10 mr-2" type="button" data-toggle="modal" data-target="#sales_item_modal"><i class="fa fa-bullhorn"></i> <?php echo _l('assign_to_leads'); ?></button>
        <button class="btn btn-danger mbot10 mr-2" type="button" data-toggle="modal" data-target="#ticket_item_modal" <?php echo $mailbox->ticketid ? "disabled" : "" ?>><i class="fa fa-life-ring"></i> <?php echo _l('assign_to_tickets'); ?></button>
        <a href="<?php echo admin_url().'mailbox/reply/'.$mailbox->id.'/reply/outbox'; ?>" autocomplete="off" data-loading-text="<?php echo _l('wait_text'); ?>" class="btn btn-warning mbot10 mr-2">
            <i class="fa fa-reply"></i></i> <?php echo _l('mailbox_reply'); ?>
        </a>
        <a href="<?php echo admin_url().'mailbox/reply/'.$mailbox->id.'/forward/outbox'; ?>" autocomplete="off" data-loading-text="<?php echo _l('wait_text'); ?>" class="btn btn-info mbot10">
            <i class="fa fa-share"></i>
            <?php echo _l('mailbox_forward'); ?>
        </a>
    </div>
</div>

<script>
    var mailid = <?php echo $mailbox->id; ?>;
    var mailtype = '<?php echo $type; ?>';
</script> 

<div class="modal fade" id="customers_item_modal" tabindex="-1" role="dialog" aria-labelledby="customersItemModalLabel">
    <?php echo form_open_multipart(admin_url().'mailbox/assign_customers', ['id'=>'customer_assign_form']); ?>
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="customersItemModalLabel">
                    <span class="edit-title"><?php echo _l('assign_customers'); ?></span>
                </h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="alert alert-warning affect-warning hide">
                            <?php echo _l('changing_items_affect_warning'); ?>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <div id="customers">
                                        <?php
                                            $selected = mailbox_get_client_companies($mailbox_id, 'outbox', false);
                                            if (is_admin()) {
                                                echo render_select_with_input_group('select_customers[]', $clients, ['userid', 'company'], 'select_customers', $selected, '<div class="input-group-btn"><a href="#" class="btn btn-default" data-toggle="modal" data-target="#customer_group_modal"><i class="fa fa-plus"></i></a></div>', ['multiple' => true, 'data-actions-box' => true, 'required' => true], [], '', '', false);
                                            } else {
                                                echo render_select('select_customers[]', $clients, ['userid', 'company'], 'select_customers', $selected, ['multiple' => true, 'data-actions-box' => true, 'required' => true], [], '', '', false);
                                            }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="mailbox_id" value="<?php echo $mailbox_id ?>" >
                        <input type="hidden" name="type" value="outbox" />
                        <div class="clearfix mbot15"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
            </div>
        </div>
    </div>
    <?php echo form_close(); ?>
</div>

<div class="modal fade" id="sales_item_modal" tabindex="-1" role="dialog" aria-labelledby="salesItemModalLabel">
    <?php echo form_open_multipart(admin_url().'mailbox/conversationLead', ['id'=>'lead_assign_form']); ?>
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="salesItemModalLabel">
                    <span class="edit-title"><?php echo _l('assign_lead'); ?></span>
                </h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="alert alert-warning affect-warning hide">
                            <?php echo _l('changing_items_affect_warning'); ?>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <div id="leads">
                                        <?php
                                            $selected = mailbox_get_leads($mailbox_id, 'outbox', false);;
                                            if (is_admin() || get_option('staff_members_create_inline_customer_groups') == '1') {
                                                echo render_select_with_input_group('select_lead[]', $leads, ['id', 'name'], 'select_lead', $selected, '<div class="input-group-btn"><a href="#" class="btn btn-default" data-toggle="modal" data-target="#customer_group_modal"><i class="fa fa-plus"></i></a></div>', ['multiple' => true, 'data-actions-box' => true], [], '', '', false);
                                            } else {
                                                echo render_select('select_lead[]', $leads, ['id', 'name'], 'select_lead', $selected, ['multiple' => true, 'data-actions-box' => true], [], '', '', false);
                                            }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="mailbox_id" value="<?php echo $mailbox_id ?>" >
                        <div class="clearfix mbot15"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
            </div>
        </div>
    </div>
    <?php echo form_close(); ?>
</div>

<div class="modal fade" id="customer_group_modal" tabindex="-1" role="dialog" aria-labelledby="customerGroupModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button group="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="customerGroupModalLabel">
                    <span class="edit-title"><?php echo _l('customer_group_edit_heading'); ?></span>
                    <span class="add-title"><?php echo _l('add_new', _l('lead_lowercase')); ?></span>
                </h4>
            </div>
            <?php echo form_open('admin/leads/lead', ['id' => 'customer-group-modal']); ?>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <?php echo render_input('name', 'lead_group_name'); ?>
                        <?php echo render_input('email', 'lead_group_email'); ?>
                        <?php echo form_hidden('id'); ?>
                    </div>
                </div>
            </div>
            <input type="hidden" name="description" value="" >
            <input type="hidden" name="address" value="" >
            <input type="hidden" name="assigned" value="" >
            <input type="hidden" name="status" value="1">
            <input type="hidden" name="source" value="1">
            <div class="modal-footer">
                <button group="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <button group="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
</div>

<script>
    window.addEventListener('load', function() {
        appValidateForm($('#customer-group-modal'), {
            name: 'required',
            email: {
                required: true,
                email: true
            }
        }, manage_customer_groups);
        $('#customer_group_modal').on('show.bs.modal', function(e) {
            var invoker = $(e.relatedTarget);
            var group_id = $(invoker).data('id');
            $('#customer_group_modal .add-title').removeClass('hide');
            $('#customer_group_modal .edit-title').addClass('hide');
            $('#customer_group_modal input[name="id"]').val('');
            $('#customer_group_modal input[name="name"]').val('');
            // is from the edit button
            if (typeof(group_id) !== 'undefined') {
                $('#customer_group_modal input[name="id"]').val(group_id);
                $('#customer_group_modal .add-title').addClass('hide');
                $('#customer_group_modal .edit-title').removeClass('hide');
                $('#customer_group_modal input[name="name"]').val($(invoker).parents('tr').find('td').eq(0).text());
            }
        });
    });
    function manage_customer_groups(form) {
        var data = $(form).serialize();
        var url = form.action;
        var formData = new URLSearchParams(data);
        var nameValue = formData.get('name');
        $.post(url, data).done(function(response) {
            response = JSON.parse(response);
            if (response.success == true) {
                if ($.fn.DataTable.isDataTable('.table-customer-groups')) {
                    $('.table-customer-groups').DataTable().ajax.reload();
                }
                if ($('body').hasClass('dynamic-create-groups') && typeof(response.id) != 'undefined') {
                    var groups = $('select[name="select_lead[]"]');
                    groups.prepend('<option value="'+response.id+'">'+nameValue+'</option>');
                    groups.selectpicker('refresh');
                }
                alert_float('success', response.message);
            }
            $('#customer_group_modal').modal('hide');
        });
        return false;
    }
</script>

<div class="modal fade" id="ticket_item_modal" tabindex="-1" role="ticketItemModalLabel">
    <?php echo form_open_multipart(admin_url().'mailbox/conversationTicket', ['id'=>'ticket_assign_form']); ?>
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="ticketItemModalLabel">
                    <span class="edit-title"><?php echo _l('assign_ticket'); ?></span>
                </h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="alert alert-warning affect-warning hide">
                            <?php echo _l('changing_items_affect_warning'); ?>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <div id="tickets">
                                        <?php
                                            $selected = [];
                                            echo render_select('select_customer', $contacts, ['id', 'name'], 'select_customer', $selected, ['data-actions-box' => true, 'required' => true], [], '', '', false);
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="mailbox_id" value="<?php echo $mailbox_id ?>" >
                        <input type="hidden" name="type" value="inbox" >
                        <div class="clearfix mbot15"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
            </div>
        </div>
    </div>
    <?php echo form_close(); ?>
</div>

<div class="modal fade" id="task_item_modal" tabindex="-1" role="dialog" aria-labelledby="taskItemModalLabel">
    <?php echo form_open_multipart(admin_url().'mailbox/assign_task', ['id'=>'task_assign_form']); ?>
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="taskItemModalLabel">
                    <span class="edit-title"><?php echo _l('assign_task'); ?></span>
                </h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="alert alert-warning affect-warning hide">
                            <?php echo _l('changing_items_affect_warning'); ?>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <div id="tasks">
                                        <?php
                                            $selected = [];
                                            echo render_select('select_customer', $staffs, ['staffid', 'name'], 'select_customer', $selected, ['data-actions-box' => true, 'required' => true], [], '', '', false);
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="mailbox_id" value="<?php echo $mailbox_id ?>" />
                        <input type="hidden" name="type" value="outbox" />
                        <div class="clearfix mbot15"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
            </div>
        </div>
    </div>
    <?php echo form_close(); ?>
</div>