/**
 * Update email status 
 * @param {boolean} silent - If true, no message/reload (for auto-mark as read in preview)
 */
function update_field(group, action, value, mail_id, type='inbox', silent=false) {
    var data = {};
    data.group = group;
    data.action = action;
    data.value = value;
    data.id = mail_id;
    data.type = type;
    if (group == 'detail') {
        data.type = mailtype; 
    }
    
    // Ensure CSRF setup is called before making request
    if (typeof window.csrf_jquery_ajax_setup === 'function') {
        window.csrf_jquery_ajax_setup();
    }
    
    // Use $.ajax to have better control and ensure ajaxSetup is respected
    $.ajax({
        url: admin_url + 'mailbox/update_field',
        type: 'POST',
        data: data,
        dataType: 'json',
        success: function(response) {
            try {
                if (typeof response === 'string') {
                    response = JSON.parse(response);
                }
                if (response.success === true || response.success == 'true') {
                    if (!silent) {
                        alert_float('success', response.message);
                        // Reload page for all groups to ensure UI updates correctly
                        setTimeout(function() {
                            window.location.reload();
                        }, 500);
                    } else {
                        // Silent mode: just reload the table, no message or page refresh
                        if ($.fn.DataTable && $.fn.DataTable.isDataTable('.table-mailbox')) {
                            $('.table-mailbox').DataTable().ajax.reload(null, false);
                        }
                        if ($.fn.DataTable && $.fn.DataTable.isDataTable('.table-mailbox-preview')) {
                            $('.table-mailbox-preview').DataTable().ajax.reload(null, false);
                        }
                    }
                } else {
                    if (!silent) {
                        alert_float('warning', response.message || 'Operation failed');
                    }
                }
            } catch (e) {
                console.error('Error parsing response:', e, response);
                if (!silent) {
                    alert_float('danger', 'Error processing response');
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', status, error, xhr);
            if (xhr.status === 419 || xhr.status === 403) {
                // CSRF error - refresh page to get new token
                alert_float('warning', 'Session expired. Refreshing page...');
                setTimeout(function() {
                    window.location.reload();
                }, 1000);
            } else {
                if (!silent) {
                    alert_float('danger', 'Failed to update: ' + (error || 'Unknown error'));
                }
            }
        }
    });
}

/**
 * Reload mailbox datagrid
 * @return 
 */
function reload_mailbox_tables() {
    var av_tasks_tables = ['.table-mailbox'];
    $.each(av_tasks_tables, function(i, selector) {
        if ($.fn.DataTable.isDataTable(selector)) {
            $(selector).DataTable().ajax.reload(null, false);
        }
    });
}

/**
 * Update multi-email 
 */
function update_mass(group, action, value, type = "inbox") {
    if (group == 'detail') {
        update_field(group, action, value, mailid, type);
    } else {
        // Check if action requires confirmation
        var needsConfirm = (action === 'trash' || action === 'delete');
        if (needsConfirm && !confirm_delete()) {
            return;
        }
        
        var table_mailbox = $('.table-mailbox');
        var checkboxes = table_mailbox.find('.mail-checkbox:checked');
        
        if (checkboxes.length === 0) {
            alert_float('warning', 'Please select at least one email');
            return;
        }
        
        var lstid = '';
        checkboxes.each(function() {
            lstid = lstid + $(this).val() + ',';
        });
        
        // Handle snooze action specially
        if (action === 'snooze') {
            alert_float('info', 'Snooze action is only available for individual emails. Please use the snooze button in the email detail view.');
            return;
        }
        
        update_field(group, action, value, lstid, type);
    }
}

function enable_email_autocomplete() {
    /**
     * Auto Complete for the receipt email
     */
    
    // Clean trailing commas before form submission
    $('#mailbox-compose-form').on('submit', function() {
        var toField = $('input[name="to"]');
        var ccField = $('input[name="cc"]');
        
        if (toField.length) {
            var toVal = toField.val().trim();
            // Remove trailing comma/semicolon and spaces
            toVal = toVal.replace(/[,;]\s*$/, '');
            toField.val(toVal);
        }
        
        if (ccField.length) {
            var ccVal = ccField.val().trim();
            // Remove trailing comma/semicolon and spaces
            ccVal = ccVal.replace(/[,;]\s*$/, '');
            ccField.val(ccVal);
        }
    });
    
    $('.email-autocomplete input').keyup(function() {
        let receipt_el = this;
        let fullValue = $(receipt_el).val();
        
        // Extract only the last email being typed (after the last comma)
        let lastCommaIndex = fullValue.lastIndexOf(',');
        let receipt_keyword = lastCommaIndex !== -1 ? fullValue.substring(lastCommaIndex + 1).trim() : fullValue.trim();
        
        if (receipt_keyword) {
            $.ajax({
                type: "POST",
                url: admin_url + "mailbox/get_recipients/",
                data: { keyword: receipt_keyword },
                success: function(response) {
                    let receipts = JSON.parse(response);
                    let recipients_html = '';
                    for (let receipt_index = 0; receipt_index < receipts.length; receipt_index++) {
                        recipients_html += '<div class="receipt" data-email="' + receipts[receipt_index].email + '">' + receipts[receipt_index].email + '(' + receipts[receipt_index].firstname + ' ' + receipts[receipt_index].lastname + ' - ' + receipts[receipt_index].company + ')</div>';
                    }
                    if ($(receipt_el).closest('.email-autocomplete').find('.receipts').length) {
                        $(receipt_el).closest('.email-autocomplete').find('.receipts').html(recipients_html);
                        if (recipients_html) {
                            $(receipt_el).closest('.email-autocomplete').find('.receipts').addClass('active');
                        }
                    } else {
                        $(receipt_el).closest('.email-autocomplete').append('<div class="receipts' + (recipients_html ? ' active' : '') + '">' + recipients_html + '</div>');
                    }
                    $('.email-autocomplete .receipts .receipt').click(function() {
                        var currentVal = $('.email-autocomplete input').val();
                        var selectedEmail = $(this).data('email');
                        
                        // Find the last comma and replace everything after it with the selected email
                        var lastCommaIndex = currentVal.lastIndexOf(',');
                        var newValue = '';
                        
                        if (lastCommaIndex !== -1) {
                            // There are previous emails - keep them and add the selected one
                            newValue = currentVal.substring(0, lastCommaIndex + 1) + ' ' + selectedEmail + ', ';
                        } else {
                            // This is the first email
                            newValue = selectedEmail + ', ';
                        }
                        
                        $('.email-autocomplete input').val(newValue);
                        
                        // Keep focus on input to allow adding more emails
                        $('.email-autocomplete input').focus();
                        
                        // Clear the dropdown
                        $('.email-autocomplete .receipts').html('').removeClass('active');
                    });
                }
            });
        } else {
            if ($(receipt_el).closest('.email-autocomplete').find('.receipts').length) {
                $(receipt_el).closest('.email-autocomplete').find('.receipts').html('');
            } else {
                $(receipt_el).closest('.email-autocomplete').append('<div class="receipts"></div>');
            }
        }
    });
    $('.email-autocomplete input').focus(function() {
        if ($('.email-autocomplete .receipts').length) {
            $('.email-autocomplete .receipts').addClass('active');
        }
    });
    $('.email-autocomplete input').blur(function() {
        if ($('.email-autocomplete .receipts').length) {
            setTimeout(function() {
                $('.email-autocomplete .receipts').removeClass('active');
            }, 500);
        }
    });
}

function view_contact(contact_id) {
    if (!contact_id || typeof contact_id == 'undefined') {
        contact_id = '';
    }
    requestGet('mailbox/form_contact/' + contact_id).done(function(response) {
        $('#contactModal .modal-content').html(response);
        $('#contactModal').modal({
            show: true,
            backdrop: 'static'
        });

        $('body').on('shown.bs.modal', '#contact', function() {
            if (contact_id == '') {
                $('#contact').find('input[name="firstname"]').focus();
            }
        });

        init_selectpicker();
        init_datepicker();
        custom_fields_hyperlink();
        validate_contact_form();
    }).fail(function(error) {
        var response = JSON.parse(error.responseText);

        alert_float('danger', response.message);
    });
}

function mailboxTagFormHandler(form) {
    var formURL = $(form).attr("action");

    var formData = new FormData($(form)[0]);

    $.ajax({
        type: 'POST',
        data: formData,
        mimeType: "multipart/form-data",
        contentType: false,
        cache: false,
        processData: false,
        url: formURL
    }).done(function(response) {
        response = JSON.parse(response);
        if (response.success) {
            alert_float('success', response.message);
        }

        if ($.fn.DataTable.isDataTable('.table-mailbox-tags')) {
            $('.table-mailbox-tags').DataTable().ajax.reload(null, false);
        }

        $('#tagModal').modal('hide');
    }).fail(function(error) {
        alert_float('danger', JSON.parse(error.responseText));
    });

    return false;
}

function validate_mailbox_tag_form() {
    appValidateForm('#mailbox-tag-form', {
        name: 'required',
    }, mailboxTagFormHandler);
}

function view_mailbox_tag(tag_id) {
    if (!tag_id || typeof tag_id == 'undefined') {
        tag_id = '';
    }
    requestGet('mailbox/form_tag/' + tag_id).done(function(response) {
        $('#tagModal .modal-content').html(response);
        $('#tagModal').modal({
            show: true,
            backdrop: 'static'
        });

        $('body').on('shown.bs.modal', '#mailbox-tag-form', function() {
            if (tag_id == '') {
                $('#mailbox-tag-form').find('input[name="name"]').focus();
            }
        });

        init_selectpicker();
        init_datepicker();
        init_color_pickers();
        custom_fields_hyperlink();
        validate_mailbox_tag_form();
    }).fail(function(error) {
        var response = JSON.parse(error.responseText);

        alert_float('danger', response.message);
    });
}

function mailboxEmailTemplateFormHandler(form) {
    var formURL = $(form).attr("action");

    var formData = new FormData($(form)[0]);

    $.ajax({
        type: 'POST',
        data: formData,
        mimeType: "multipart/form-data",
        contentType: false,
        cache: false,
        processData: false,
        url: formURL
    }).done(function(response) {
        response = JSON.parse(response);
        if (response.success) {
            alert_float('success', response.message);
        }

        if ($.fn.DataTable.isDataTable('.table-mailbox-email-templates')) {
            $('.table-mailbox-email-templates').DataTable().ajax.reload(null, false);
        }

        $('#emailTemplateModal').modal('hide');
    }).fail(function(error) {
        alert_float('danger', JSON.parse(error.responseText));
    });

    return false;
}

function validate_mailbox_email_template_form() {
    appValidateForm('#mailbox-email-template-form', {
        name: 'required',
    }, mailboxEmailTemplateFormHandler);
}

function view_mailbox_email_template(template_id) {
    if (!template_id || typeof template_id == 'undefined') {
        template_id = '';
    }
    requestGet('mailbox/form_email_template/' + template_id).done(function(response) {
        $('#emailTemplateModal .modal-content').html(response);
        $('#emailTemplateModal').modal({
            show: true,
            backdrop: 'static'
        });

        $('body').on('shown.bs.modal', '#mailbox-email-template-form', function() {
            if (template_id == '') {
                $('#mailbox-email-template-form').find('input[name="name"]').focus();
            }
        });

        init_selectpicker();
        init_datepicker();
        custom_fields_hyperlink();
        validate_mailbox_email_template_form();
        enable_email_autocomplete();
    }).fail(function(error) {
        var response = JSON.parse(error.responseText);

        alert_float('danger', response.message);
    });
}

function check_email_template() {
    let mailbox_templateid = $('#mailbox-compose-form [name="templateid"]').val() ? $('#mailbox-compose-form [name="templateid"]').val() : '';
    if (mailbox_templateid) {
        $.ajax({
            type: 'GET',
            url: admin_url + 'mailbox/get_email_template/' + mailbox_templateid,
        }).done(function(response) {
            response = JSON.parse(response);
            
            $('#mailbox-compose-form [name="subject"]').val(response.subject);
            tinymce.get('body').setContent(response.message);
        }).fail(function(error) {
            alert_float('danger', JSON.parse(error.responseText));
        });
    }
}

$('#mailbox-compose-form [name="templateid"]').change(function() {
    check_email_template();
});

function check_auto_reply_template() {
    let mailbox_templateid = $('#mailbox-auto-reply-form [name="replyid"]').val() ? $('#mailbox-auto-reply-form [name="replyid"]').val() : '';
    if (mailbox_templateid) {
        $.ajax({
            type: 'GET',
            url: admin_url + 'mailbox/get_email_template/' + mailbox_templateid,
        }).done(function(response) {
            response = JSON.parse(response);
            
            $('#mailbox-auto-reply-form [name="subject"]').val(response.subject);
            tinymce.get('body').setContent(response.message);
        }).fail(function(error) {
            alert_float('danger', JSON.parse(error.responseText));
        });
    }
}

function mailboxAutoReplyFormHandler(form) {
    var formURL = $(form).attr("action");

    var formData = new FormData($(form)[0]);
    formData.set("body", tinymce.get('body').getContent());

    $.ajax({
        type: 'POST',
        data: formData,
        mimeType: "multipart/form-data",
        contentType: false,
        cache: false,
        processData: false,
        url: formURL
    }).done(function(response) {
        response = JSON.parse(response);
        if (response.success) {
            alert_float('success', response.message);
        }

        if ($.fn.DataTable.isDataTable('.table-mailbox-auto-replies')) {
            $('.table-mailbox-auto-replies').DataTable().ajax.reload(null, false);
        }

        $('#autoReplyModal').modal('hide');
    }).fail(function(error) {
        alert_float('danger', JSON.parse(error.responseText));
    });

    return false;
}

function validate_mailbox_auto_reply_form() {
    appValidateForm('#mailbox-auto-reply-form', {
        name: 'required',
        pattern: 'required',
    }, mailboxAutoReplyFormHandler);
}

function view_mailbox_auto_reply(auto_reply_id) {
    if (!auto_reply_id || typeof auto_reply_id == 'undefined') {
        auto_reply_id = '';
    }
    requestGet('mailbox/form_auto_reply/' + auto_reply_id).done(function(response) {
        $('#autoReplyModal .modal-content').html(response);
        $('#autoReplyModal').modal({
            show: true,
            backdrop: 'static'
        });

        $('body').on('shown.bs.modal', '#mailbox-auto-reply-form', function() {
            if (auto_reply_id == '') {
                $('#mailbox-auto-reply-form').find('input[name="name"]').focus();
            }
        });

        init_selectpicker();
        init_datepicker();
        custom_fields_hyperlink();
        validate_mailbox_auto_reply_form();
        tinymce.remove('#mailbox-auto-reply-form [name="body"]');
        init_editor('#mailbox-auto-reply-form [name="body"]');

        $('#mailbox-auto-reply-form [name="replyid"]').change(function() {
            check_auto_reply_template();
        });
        check_auto_reply_template();
    }).fail(function(error) {
        var response = JSON.parse(error.responseText);

        alert_float('danger', response.message);
    });
}

function unassgin_customer(client_id, mail_id, type='inbox') {
    var data = {};
    data.client_id = client_id;
    data.mail_id = mail_id;
    data.type = type;
    $.post(admin_url + 'mailbox/unassign_customers', data).done(function(response) {
        response = JSON.parse(response);
        if (response.success === true || response.success == 'true') {
            alert_float('success', response.message);
            window.location.reload();
        } else {
            alert_float('warning', response.message);
        }
    });
}

/**
 * Snooze email
 */
function snooze_email() {
    var email_id = $('#snooze_email_id').val();
    var snooze_datetime = $('#snooze_datetime').val();
    
    if (!snooze_datetime) {
        alert_float('warning', 'Please select a date and time');
        return;
    }
    
    var data = {
        group: 'detail',
        action: 'snooze',
        value: 1,
        id: email_id,
        type: 'inbox',
        snooze_datetime: snooze_datetime
    };
    
    $.post(admin_url + 'mailbox/update_field', data).done(function(response) {
        response = JSON.parse(response);
        if (response.success === true || response.success == 'true') {
            alert_float('success', response.message);
            $('#snooze_modal').modal('hide');
            window.location.reload();
        } else {
            alert_float('warning', response.message);
        }
    });
}

/**
 * Un-snooze email
 */
function unsnooze_email(email_id) {
    if (!confirm('Are you sure you want to un-snooze this email?')) {
        return;
    }
    
    var data = {
        group: 'detail',
        action: 'snooze',
        value: 0,
        id: email_id,
        type: 'inbox',
        snooze_datetime: ''
    };
    
    $.post(admin_url + 'mailbox/update_field', data).done(function(response) {
        response = JSON.parse(response);
        if (response.success === true || response.success == 'true') {
            alert_float('success', response.message);
            window.location.reload();
        } else {
            alert_float('warning', response.message);
        }
    });
}

/**
 * Set quick snooze (hours) - alias for setSnoozeTime
 */
function setSnoozeQuick(hours) {
    setSnoozeTime(hours);
}

/**
 * Set quick snooze time (hours)
 */
function setSnoozeTime(hours) {
    var now = new Date();
    now.setHours(now.getHours() + hours);
    var year = now.getFullYear();
    var month = String(now.getMonth() + 1).padStart(2, '0');
    var day = String(now.getDate()).padStart(2, '0');
    var hour = String(now.getHours()).padStart(2, '0');
    var minute = String(now.getMinutes()).padStart(2, '0');
    var datetime = year + '-' + month + '-' + day + ' ' + hour + ':' + minute;
    $('#snooze_datetime').val(datetime);
    // Trigger change to update the datepicker
    $('#snooze_datetime').trigger('change');
}

/**
 * Select All checkbox functionality
 */
function initSelectAll() {
    // Use the checkbox from render_datatable (mass_select_all) or add our own
    if ($('.table-mailbox thead tr').length) {
        var firstTh = $('.table-mailbox thead tr th').first();
        var existingCheckbox = firstTh.find('#mass_select_all, .select-all-checkbox');
        
        if (existingCheckbox.length) {
            // Use existing checkbox - add our class if it doesn't have it
            if (!existingCheckbox.hasClass('select-all-checkbox')) {
                existingCheckbox.addClass('select-all-checkbox');
            }
        } else {
            // Add our own checkbox if none exists
            firstTh.prepend('<div class="checkbox select-all-wrapper"><input type="checkbox" class="select-all-checkbox" id="select-all-mails"><label for="select-all-mails"></label></div>');
        }
    }
    
    // Handle select all checkbox (works with both mass_select_all and select-all-checkbox and preview pane checkbox)
    $(document).off('change', '#mass_select_all, #mass_select_all_preview, .select-all-checkbox').on('change', '#mass_select_all, #mass_select_all_preview, .select-all-checkbox', function() {
        var isChecked = $(this).prop('checked');
        $('.mail-checkbox').prop('checked', isChecked);
        // Sync all checkboxes if they exist
        $('#mass_select_all, #mass_select_all_preview, .select-all-checkbox').not(this).prop('checked', isChecked);
    });
    
    // Handle individual checkbox changes
    $(document).off('change', '.mail-checkbox').on('change', '.mail-checkbox', function() {
        var totalCheckboxes = $('.mail-checkbox').length;
        var checkedCheckboxes = $('.mail-checkbox:checked').length;
        var allChecked = totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes;
        $('#mass_select_all, #mass_select_all_preview, .select-all-checkbox').prop('checked', allChecked);
    });
}

/**
 * Keyboard shortcuts
 */
function initKeyboardShortcuts() {
    $(document).on('keydown', function(e) {
        // Don't trigger shortcuts when typing in inputs, textareas, or contenteditable
        if ($(e.target).is('input, textarea, [contenteditable="true"]')) {
            return;
        }
        
        // Ctrl/Cmd + A: Select all emails
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 65) {
            e.preventDefault();
            $('.select-all-checkbox').prop('checked', true).trigger('change');
            return false;
        }
        
        // Delete: Delete selected emails
        if (e.keyCode === 46) {
            var checked = $('.mail-checkbox:checked');
            if (checked.length > 0) {
                e.preventDefault();
                var ids = [];
                checked.each(function() {
                    ids.push($(this).val());
                });
                if (confirm('Delete ' + ids.length + ' email(s)?')) {
                    update_field('inbox', 'trash', 1, ids.join(','));
                }
                return false;
            }
        }
        
        // 'r' key: Reply to first selected email
        if (e.keyCode === 82 && !e.ctrlKey && !e.metaKey) {
            var firstChecked = $('.mail-checkbox:checked').first();
            if (firstChecked.length) {
                var emailId = firstChecked.val();
                window.location.href = admin_url + 'mailbox/reply/' + emailId + '/reply/inbox';
                return false;
            }
        }
        
        // 'a' key: Archive selected emails
        if (e.keyCode === 65 && !e.ctrlKey && !e.metaKey) {
            var checked = $('.mail-checkbox:checked');
            if (checked.length > 0) {
                e.preventDefault();
                var ids = [];
                checked.each(function() {
                    ids.push($(this).val());
                });
                update_field('inbox', 'archived', 0, ids.join(','));
                return false;
            }
        }
    });
}

/**
 * Apply advanced search filters
 */
function applyAdvancedSearch() {
    var params = {};
    
    if ($('#search_from').val()) {
        params.search_from = $('#search_from').val();
    }
    if ($('#search_subject').val()) {
        params.search_subject = $('#search_subject').val();
    }
    if ($('#search_body').val()) {
        params.search_body = $('#search_body').val();
    }
    if ($('#search_tag').val()) {
        params.search_tag = $('#search_tag').val();
    }
    if ($('#search_date_from').val()) {
        params.search_date_from = $('#search_date_from').val();
    }
    if ($('#search_date_to').val()) {
        params.search_date_to = $('#search_date_to').val();
    }
    if ($('#search_has_attachment').val() !== '') {
        params.search_has_attachment = $('#search_has_attachment').val();
    }
    if ($('#search_read').val() !== '') {
        params.search_read = $('#search_read').val();
    }
    
    // Reload table with search parameters
    if ($.fn.DataTable && $.fn.DataTable.isDataTable('.table-mailbox')) {
        var table = $('.table-mailbox').DataTable();
        table.ajax.url(buildTableUrl(params)).load();
    } else {
        // If table not initialized yet, store params in sessionStorage
        sessionStorage.setItem('mailbox_search_params', JSON.stringify(params));
        window.location.href = buildTableUrl(params);
    }
}

/**
 * Clear advanced search filters
 */
/**
 * Toggle advanced search panel with one click
 */
function toggleAdvancedSearch() {
    var panel = $('#advanced_search_panel');
    var chevron = $('#advanced_search_chevron');
    
    if (panel.is(':visible')) {
        panel.slideUp(300);
        chevron.removeClass('fa-chevron-up').addClass('fa-chevron-down');
    } else {
        panel.slideDown(300);
        chevron.removeClass('fa-chevron-down').addClass('fa-chevron-up');
    }
}

function clearAdvancedSearch() {
    $('#advanced_search_form')[0].reset();
    $('.selectpicker').selectpicker('refresh');
    
    // Reload table without search parameters
    if ($.fn.DataTable && $.fn.DataTable.isDataTable('.table-mailbox')) {
        var table = $('.table-mailbox').DataTable();
        table.ajax.url(buildTableUrl({})).load();
    } else {
        sessionStorage.removeItem('mailbox_search_params');
        var currentUrl = window.location.href.split('?')[0];
        window.location.href = currentUrl;
    }
}

/**
 * Build table URL with search parameters
 */
function buildTableUrl(params) {
    var baseUrl = admin_url + 'mailbox/table/' + (typeof mailbox_group !== 'undefined' ? mailbox_group : 'inbox');
    var queryParams = [];
    
    for (var key in params) {
        if (params.hasOwnProperty(key) && params[key] !== '') {
            queryParams.push(key + '=' + encodeURIComponent(params[key]));
        }
    }
    
    if (queryParams.length > 0) {
        return baseUrl + '?' + queryParams.join('&');
    }
    
    return baseUrl;
}

$(document).ready(function() {
    enable_email_autocomplete();
    
    // Initialize select all
    initSelectAll();
    
    // Initialize keyboard shortcuts only if enabled (default: enabled)
    var keyboardShortcutsEnabled = typeof mailbox_keyboard_shortcuts_enabled !== 'undefined' ? mailbox_keyboard_shortcuts_enabled : '1';
    if (keyboardShortcutsEnabled === '1' || keyboardShortcutsEnabled === 1) {
        initKeyboardShortcuts();
    }
    
    // Initialize datetime picker for snooze modal when modal opens
    $(document).on('shown.bs.modal', '#snooze_modal', function() {
        if ($('#snooze_datetime').length) {
            init_datepicker('#snooze_datetime', {
                timepicker: true,
                format: 'Y-m-d H:i',
                minDate: new Date() // Prevent selecting past dates
            });
        }
    });
    
    // Initialize date pickers for advanced search
    if ($('#search_date_from').length) {
        init_datepicker($('#search_date_from'));
    }
    if ($('#search_date_to').length) {
        init_datepicker($('#search_date_to'));
    }
    
    // Re-initialize select all after table reload
    if ($.fn.DataTable && $.fn.DataTable.isDataTable('.table-mailbox')) {
        $('.table-mailbox').on('draw.dt', function() {
            initSelectAll();
        });
    }

    if ($('.table-mailbox-clients').length) {
       initDataTable('.table-mailbox-clients', admin_url + 'mailbox/table_client_emails/' + mailbox_client_id, undefined, [0], 'undefined', [2, 'asc']);
    }
});