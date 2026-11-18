<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Maibox Controller.
 */
class Mailbox extends AdminController
{
    /**
     * Controler __construct function to initialize options.
     */
    public function __construct()
    {
        try {
            parent::__construct();
            $this->load->model('mailbox_model');
            $this->load->model('emails_model');
        } catch (Exception $e) {
            throw $e; // Re-throw to let CodeIgniter handle it
        } catch (Error $e) {
            throw $e; // Re-throw to let CodeIgniter handle it
        }
    }

    /**
     * Test endpoint to check if module is loading
     */
    public function test()
    {
        echo json_encode([
            'status' => 'ok',
            'message' => 'Mailbox module is loading correctly',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        die();
    }

    /**
     * Go to Mailbox home page.
     *
     * @return view
     */
    public function index()
    {
        try {
            $data['title'] = _l('mailbox');
            $data['bodyclass'] = 'mailbox mailbox-emails';
            $group         = !$this->input->get('group') ? 'inbox' : $this->input->get('group');
            $data['group'] = $group;

            $data['clients']            = $this->mailbox_model->select_client();
            $data['leads']              = $this->mailbox_model->select_lead();
            $data['tickets']            = $this->mailbox_model->select_ticket();
            $data['contacts']           = $this->mailbox_model->select_contact();
            $data['staffs']             = $this->mailbox_model->select_staff();

            // Load rules if rules group
            if ($group == 'rules' && $this->db->table_exists(db_prefix() . 'mail_rules')) {
                $data['rules'] = $this->mailbox_model->get_rules(get_staff_user_id());
            }

            $this->load->view('mailbox', $data);

        } catch (Exception $e) {
            show_error('Mailbox Error: ' . $e->getMessage() . '<br><br>File: ' . $e->getFile() . '<br>Line: ' . $e->getLine() . '<br><br><pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>', 500, 'Mailbox Module Error');
        } catch (Error $e) {
            show_error('Mailbox Fatal Error: ' . $e->getMessage() . '<br><br>File: ' . $e->getFile() . '<br>Line: ' . $e->getLine() . '<br><br><pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>', 500, 'Mailbox Module Fatal Error');
        } catch (Throwable $e) {
            show_error('Mailbox Throwable Error: ' . $e->getMessage() . '<br><br>File: ' . $e->getFile() . '<br>Line: ' . $e->getLine() . '<br><br><pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>', 500, 'Mailbox Module Throwable Error');
        }
    }

    /**
     * User configuration page
     * Separate from main mailbox view
     */
    /**
     * Clear OAuth credentials
     */
    public function clear_oauth_credentials()
    {
        $staffId = get_staff_user_id();

        $updateData = [
            'oauth_client_id' => null,
            'oauth_client_secret' => null,
            'oauth_tenant_id' => null,
            'oauth_provider' => null,
            'oauth_access_token' => null,
            'oauth_refresh_token' => null,
            'oauth_token_expires_at' => null
        ];

        $this->db->where('staffid', $staffId);
        $result = $this->db->update(db_prefix() . 'staff', $updateData);

        if ($result) {
            log_activity('OAuth credentials cleared for staff: ' . $staffId);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    }

    /**
     * User configuration page
     * Separate from main mailbox view
     */
    public function config()
    {
        $data['title'] = _l('mailbox_config');
        $data['bodyclass'] = 'mailbox mailbox-config';

        if ($this->input->post()) {
            $current_staff_id = get_staff_user_id();
            // Debug logs removed to minimize activity log noise

            $this->load->model('staff_model');

            // Prepare update data
            $updateData = [];

            // Handle password if provided
            if (!empty($this->input->post('mail_password'))) {
                $updateData['mail_password'] = encrypt_mail_password($this->input->post('mail_password'));
            }

            // Handle signature
            if ($this->input->post('mail_signature') !== null) {
                $updateData['mail_signature'] = $this->input->post('mail_signature');
            }

            // Handle IMAP configuration (only for password auth)
            if ($this->input->post('auth_method') === 'password') {
                if ($this->input->post('imap_server') !== null) {
                    $updateData['imap_server'] = trim($this->input->post('imap_server'));
                }

                if ($this->input->post('imap_port') !== null) {
                    $updateData['imap_port'] = (int) $this->input->post('imap_port');
                }

                if ($this->input->post('imap_encryption') !== null) {
                    $updateData['imap_encryption'] = $this->input->post('imap_encryption');
                }

                // Handle SMTP configuration (for sending emails)
                if ($this->input->post('smtp_host') !== null) {
                    $updateData['smtp_host'] = trim($this->input->post('smtp_host'));
                }

                if ($this->input->post('smtp_port') !== null) {
                    $updateData['smtp_port'] = (int) $this->input->post('smtp_port');
                }

                if ($this->input->post('smtp_encryption') !== null) {
                    $updateData['smtp_encryption'] = $this->input->post('smtp_encryption');
                }

                if ($this->input->post('smtp_username') !== null) {
                    $updateData['smtp_username'] = trim($this->input->post('smtp_username'));
                }

                if ($this->input->post('smtp_password') !== null && $this->input->post('smtp_password') !== '') {
                    $updateData['smtp_password'] = encrypt_mail_password($this->input->post('smtp_password'));
                }
            }

            // Handle IMAP folder mappings (applies to both password and OAuth)
            $folder_fields = ['imap_folder_scan', 'imap_sent_folder', 'imap_draft_folder', 'imap_trash_folder', 'imap_spam_folder', 'imap_archive_folder'];
            foreach ($folder_fields as $field) {
                if ($this->input->post($field) !== null) {
                    $updateData[$field] = trim($this->input->post($field));
                }
            }

            // Handle import preferences (applies to both password and OAuth)
            if ($this->input->post('only_loop_on_unseen_emails') !== null) {
                $updateData['only_loop_on_unseen_emails'] = $this->input->post('only_loop_on_unseen_emails');
            }

            // Handle HTML rendering preference (applies to both password and OAuth)
            if ($this->input->post('enable_html') !== null) {
                $updateData['enable_html'] = $this->input->post('enable_html');
            }

            // Handle sync read status to server (applies to both password and OAuth)
            if ($this->input->post('sync_read_status_to_server') !== null) {
                $updateData['sync_read_status_to_server'] = $this->input->post('sync_read_status_to_server');
            }

            // Handle OAuth credentials (if OAuth method is selected)
            if ($this->input->post('auth_method') === 'oauth2') {
                $selected_provider = $this->input->post('oauth_provider_selected');
                // OAuth method selected (debug logs removed for cleaner activity log)

                if ($selected_provider === 'gmail' || $selected_provider === 'outlook') {
                    // Generic OAuth credentials (same field names for both providers)
                    $client_id = $this->input->post('oauth_client_id');
                    $client_secret = $this->input->post('oauth_client_secret');

                    if (!empty($client_id)) {
                        $updateData['oauth_client_id'] = encrypt_mail_password($client_id);
                    }
                    if (!empty($client_secret)) {
                        $updateData['oauth_client_secret'] = encrypt_mail_password($client_secret);
                    }

                    // Outlook-specific: Tenant ID
                    if ($selected_provider === 'outlook' && !empty($this->input->post('oauth_tenant_id'))) {
                        $updateData['oauth_tenant_id'] = $this->input->post('oauth_tenant_id');
                    }

                    // DON'T set oauth_provider here - it will be set AFTER successful OAuth authorization
                    // This prevents showing "Connected" status before tokens are obtained
                }
            }

            // Handle auth_method - only update if OAuth is not connected or password is being set
            $staff = $this->staff_model->get(get_staff_user_id());
            if (empty($staff->oauth_provider) || !empty($this->input->post('mail_password'))) {
                if ($this->input->post('auth_method') !== null) {
                    $updateData['auth_method'] = $this->input->post('auth_method');
                }
            }

            // Update using model method
            if (!empty($updateData)) {
                // Debug logs removed to minimize activity log noise

                $res = $this->mailbox_model->update_config($updateData, $current_staff_id);

                if ($res) {
                    // Database update successful (verification logs removed)

                    // Verify the save by re-fetching the staff record
                    $this->load->model('staff_model');
                    $verified_staff = $this->staff_model->get($current_staff_id);

                    $has_client_id = !empty($verified_staff->oauth_client_id);
                    $has_secret = !empty($verified_staff->oauth_client_secret);

                    set_alert('success', _l('mailbox_email_config_successfully'));

                    // If OAuth credentials were saved, redirect to OAuth authorization
                    $selected_provider = $this->input->post('oauth_provider_selected');
                    if ($this->input->post('auth_method') === 'oauth2'
                        && !empty($selected_provider)
                        && (!empty($updateData['oauth_client_id']) || !empty($updateData['oauth_client_secret']))) {
                        $redirect_url = admin_url('mailbox/oauth/authorize?provider=' . $selected_provider);
                        redirect($redirect_url);
                    } else {
                        redirect(admin_url('mailbox/config'));
                    }
                } else {
                    // Update failed
                    set_alert('danger', 'Failed to save mailbox configuration. Please check the Activity Log for details.');
                    redirect(admin_url('mailbox/config'));
                }
            } else {
                // No data to update (log removed for cleaner activity log)
            }
        }

        $this->load->model('staff_model');
        $data['member'] = $this->staff_model->get(get_staff_user_id());
        $this->load->view('mailbox/mailbox_config', $data);
    }

    /**
     * Go to Compose Form.
     *
     * @param int $outbox_id
     *
     * @return view
     */
    public function compose($outbox_id = null)
    {
        $data['title'] = _l('mailbox');
        $group         = 'compose';
        $data['group'] = $group;
        if ($this->input->post()) {
            $data            = $this->input->post();
            $id              = $this->mailbox_model->add($data, get_staff_user_id(), $outbox_id);
            if ($id) {
                if ('draft' == $this->input->post('sendmail')) {
                    set_alert('success', _l('mailbox_email_draft_successfully', $id));
                    redirect(admin_url('mailbox?group=draft'));
                } else {
                    set_alert('success', _l('mailbox_email_sent_successfully', $id));
                    redirect(admin_url('mailbox?group=inbox'));
                }
            }
        }

		$data['email_templates'] = $this->emails_model->get([
            'language' => 'english',
            'type' => 'mailbox',
            'active' => true,
        ]);

        if (isset($outbox_id)) {
            $mail                   = $this->mailbox_model->get($outbox_id, 'outbox');
            $data['mail']           = $mail;
            $data['outbox_id']      = $outbox_id;
        }
        $data['clients']            = $this->mailbox_model->select_client();
        $data['leads']              = $this->mailbox_model->select_lead();
        $data['tickets']            = $this->mailbox_model->select_ticket();
        $data['contacts']           = $this->mailbox_model->select_contact();
        $data['staffs']             = $this->mailbox_model->select_staff();
        $this->load->view('mailbox', $data);
    }

    /**
     * Get list email to dislay on datagrid.
     *
     * @param string $group
     *
     * @return
     */
    public function table($group = 'inbox')
    {
        if ($this->input->is_ajax_request()) {
            if ('sent' == $group || 'draft' == $group || 'scheduled' == $group) {
                $this->app->get_table_data(module_views_path('mailbox', 'table_outbox'), [
                    'group' => $group,
                ]);
            } else {
                $this->app->get_table_data(module_views_path('mailbox', 'table'), [
                    'group' => $group,
                ]);
            }
        }
    }

    /**
     * Get list email for preview pane (limited columns).
     *
     * @param string $group
     *
     * @return
     */
    public function table_preview($group = 'inbox')
    {
        if ($this->input->is_ajax_request()) {
            if ('sent' == $group || 'draft' == $group || 'scheduled' == $group) {
                // For sent/draft/scheduled, use regular table (preview pane not typically used)
                $this->app->get_table_data(module_views_path('mailbox', 'table_outbox'), [
                    'group' => $group,
                ]);
            } else {
                $this->app->get_table_data(module_views_path('mailbox', 'table_preview'), [
                    'group' => $group,
                ]);
            }
        }
    }

    /**
     * Go to Inbox Page.
     *
     * @param int $id
     *
     * @return view
     */
    public function inbox($id)
    {
        $inbox = $this->mailbox_model->get($id, 'inbox');

        // ALWAYS mark as read in single view (user explicitly clicked to open it)
        // Single view = intentional action, should always mark as read
        if ($inbox->read == 0) {
            $this->mailbox_model->update_field('detail', 'read', 1, $id, 'inbox');
        }

        $data['title']          = $inbox->subject;
        $group                  = 'detail';
        $data['group']          = $group;
        $data['mailbox']        = $inbox;
        $data['type']           = 'inbox';

        // Get attachments from database
        $attachments = $this->mailbox_model->get_mail_attachment($id, 'inbox');
        $data['attachments'] = $attachments;

        // Log for debugging
        if (empty($attachments) && isset($inbox->has_attachment) && $inbox->has_attachment > 0) {
            log_activity('Warning: Email ID ' . $id . ' has has_attachment=1 but no attachment records found in database', null);
        } elseif (!empty($attachments)) {
            log_activity('Found ' . count($attachments) . ' attachment(s) for email ID: ' . $id, null);
        }

        $data['clients']        = $this->mailbox_model->select_client();
        $data['leads']          = $this->mailbox_model->select_lead();
        $data['tickets']        = $this->mailbox_model->select_ticket();
        $data['contacts']       = $this->mailbox_model->select_contact();
        $data['staffs']         = $this->mailbox_model->select_staff();
        $data['mailbox_id']     = $id;
        $data['bodyclass']      = 'dynamic-create-groups';

        $this->mailbox_model->check_mailbox($id, 'inbox');

        $this->load->view('mailbox', $data);
    }

    /**
     * Go to Outbox Page.
     *
     * @param int $id
     *
     * @return view
     */
    public function outbox($id)
    {
        $outbox                 = $this->mailbox_model->get($id, 'outbox');
        $data['title']          = $outbox->subject;
        $group                  = 'detail';
        $data['group']          = $group;
        $data['mailbox']        = $outbox;
        $data['type']           = 'outbox';
        $data['attachments']    = $this->mailbox_model->get_mail_attachment($id, 'outbox');
        $data['leads']          = $this->mailbox_model->select_lead();
        $data['tickets']        = $this->mailbox_model->select_ticket();
        $data['contacts']       = $this->mailbox_model->select_contact();
        $data['staffs']         = $this->mailbox_model->select_staff();
        $data['mailbox_id']     = $id;
        $data['bodyclass']      = 'dynamic-create-groups';

        $this->mailbox_model->check_mailbox($id, 'outbox');

        $this->load->view('mailbox', $data);
    }

    /**
     * update email status.
     *
     * @return json
     */
    public function update_field()
    {
        if ($this->input->post()) {
            $group  = $this->input->post('group');
            $action = $this->input->post('action');
            $value  = $this->input->post('value');
            $id     = $this->input->post('id');
            $type   = $this->input->post('type');

            // Handle snooze action (special case - datetime value)
            if ('snooze' == $action) {
                $snooze_datetime = $this->input->post('snooze_datetime');
                if (!empty($snooze_datetime)) {
                    $arr_id = explode(',', $id);
                    foreach ($arr_id as $mail_id) {
                        if (strlen(trim($mail_id)) > 0) {
                            $this->db->where('id', $mail_id);
                            $this->db->update(db_prefix() . 'mail_'.$type, [
                                'snoozed_until' => $snooze_datetime,
                            ]);
                        }
                    }
                    $res = true;
                    $message = _l('mailbox_snooze').' '._l('mailbox_success');
                } else {
                    // Un-snooze (clear snoozed_until)
                    $arr_id = explode(',', $id);
                    foreach ($arr_id as $mail_id) {
                        if (strlen(trim($mail_id)) > 0) {
                            $this->db->where('id', $mail_id);
                            $this->db->update(db_prefix() . 'mail_'.$type, [
                                'snoozed_until' => null,
                            ]);
                        }
                    }
                    $res = true;
                    $message = _l('mailbox_unsnooze').' '._l('mailbox_success');
                }
            } else {
                // Handle other actions
                // Toggle behavior for starred/important, but NOT for read/trash
                // Read action should respect the value sent (for auto-mark on preview/single view)
                if ('trash' != $action && 'read' != $action) {
                    // Toggle for starred, important, etc.
                    if (1 == $value) {
                        $value = 0;
                    } else {
                        $value = 1;
                    }
                }
                // For 'read' action, use the value as-is (1 = read, 0 = unread)
                // This allows auto-mark on preview/single view to work correctly
                $res     = $this->mailbox_model->update_field($group, $action, $value, $id, $type);
                $message = _l('mailbox_'.$action).' '._l('mailbox_success');
            }

            if (false == $res) {
                $message = _l('mailbox_'.$action).' '._l('mailbox_fail');
            }
          
            echo json_encode([
                'success' => $res,
                'message' => $message,
            ]);
        }
    }

    /**
     * Get all emails in a thread (for expand/collapse functionality)
     *
     * @return json
     */
    public function get_thread_emails()
    {
        $thread_id = $this->input->post('thread_id');
        $group = $this->input->post('group') ?: 'inbox';

        if (empty($thread_id)) {
            echo json_encode([
                'success' => false,
                'message' => 'Thread ID required'
            ]);
            return;
        }

        // Check if threading is enabled
        $threading_enabled = $this->db->field_exists('thread_id', db_prefix() . 'mail_inbox')
            && ('1' == get_option('mailbox_threading_enabled'));

        if (!$threading_enabled) {
            echo json_encode([
                'success' => false,
                'message' => 'Threading not enabled'
            ]);
            return;
        }

        // Build WHERE conditions based on group
        $this->db->where('thread_id', $thread_id);
        $this->db->where('to_staff_id', get_staff_user_id());

        // Apply group filters
        if ($group == 'trash') {
            $this->db->where('trash', 1);
        } else if ($group == 'starred') {
            $this->db->where('stared', 1);
            $this->db->where('trash', 0);
        } else if ($group == 'sent') {
            $this->db->where('read', 1);
            $this->db->where('trash', 0);
        } else if ($group == 'archived') {
            if ($this->db->field_exists('archived', db_prefix() . 'mail_inbox')) {
                $this->db->where('archived', 1);
            }
        } else if ($group == 'snoozed') {
            if ($this->db->field_exists('snoozed_until', db_prefix() . 'mail_inbox')) {
                $this->db->where('snoozed_until IS NOT NULL AND snoozed_until > NOW()');
            }
        } else {
            // inbox or other - exclude trash
            $this->db->where('trash', 0);
        }

        // Order by date
        $this->db->order_by('date_received', 'ASC');

        $emails = $this->db->get(db_prefix() . 'mail_inbox')->result_array();

        // Format emails for display
        $formatted_emails = [];
        foreach ($emails as $email) {
            $formatted_emails[] = [
                'id' => $email['id'],
                'sender_name' => $email['sender_name'],
                'subject' => $email['subject'],
                'body' => text_limiter(clear_textarea_breaks($email['body']), 6, '...'),
                'date_received' => format_mailbox_date($email['date_received']),
                'read' => $email['read'],
                'stared' => $email['stared'],
                'important' => $email['important'],
                'has_attachment' => $email['has_attachment'],
                'in_reply_to' => isset($email['in_reply_to']) ? $email['in_reply_to'] : null,
            ];
        }

        echo json_encode([
            'success' => true,
            'emails' => $formatted_emails,
            'count' => count($formatted_emails)
        ]);
    }

    /**
     * Action for reply, reply all and forward.
     *
     * @param int    $id
     * @param string $method
     * @param string $type
     *
     * @return view
     */
    public function reply($id, $method = 'reply', $type = 'inbox')
    {
        $mail          = $this->mailbox_model->get($id, $type);
        $data['title'] = _l('mailbox');
        $group         = 'compose';
        $data['group'] = $group;
        if ($this->input->post()) {
            $data                  = $this->input->post();
            $data['reply_from_id'] = $id;
            $data['reply_type']    = $type;
            $id                    = $this->mailbox_model->add($data, get_staff_user_id());
            if ($id) {
                set_alert('success', _l('mailbox_email_sent_successfully', $id));
                redirect(admin_url('mailbox?group=inbox'));
            }
        }
        $data['attachments'] = $this->mailbox_model->get_mail_attachment($id, 'inbox');
        $data['group']       = $group;
        $data['type']        = 'reply';
        $data['action_type'] = $type;
        $data['method']      = $method;
        $data['mail']        = $mail;

        // Load email templates for reply/forward
        $data['email_templates'] = $this->emails_model->get([
            'language' => 'english',
            'type' => 'mailbox',
            'active' => true,
        ]);

        $this->load->view('mailbox', $data);
    }


    /**
     * Assign leads
     *
     * @return redirect
     */
    public function conversationLead() {
        if ($this->input->post()) {
            $data = $this->input->post();
            $this->load->model('mailbox_model');
            $leadData = $this->mailbox_model->conversation($data);
            if ($leadData) {
                set_alert('success', _l('lead_assign_successfully'));
                redirect(admin_url('mailbox/outbox/'.$data['mailbox_id']));
            }
        }
    }

    public function conversationLead_inbox() {
        if ($this->input->post()) {
            $data = $this->input->post();
            $this->load->model('mailbox_model');
            $leadData = $this->mailbox_model->conversation_inbox($data);
            if ($leadData) {
                set_alert('success', _l('lead_assign_successfully'));
                redirect(admin_url('mailbox/inbox/'.$data['mailbox_id']));
            }
        }
    }

    public function delete_mail_conversation() {
        if ($this->input->post()) {
           $result =  $this->mailbox_model->delete_mail_conversation($this->input->post('id'));
            if ($result) {
                echo json_encode(['data' => $result, 'message' => _l("delete_successfully")]); die();
            } else {
                echo json_encode(['error' => 'Mail Conversation has not delete']); die();
            }
        }
    }

    /**
     * Assign tickets
     *
     * @return redirect
     */
    public function conversationTicket() {
        if ($this->input->post()) {
            $data = $this->input->post();
            $this->load->model('mailbox_model');
            $ticketData = $this->mailbox_model->conversationTicket($data);
            if ($ticketData) {
                set_alert('success', _l('ticket_assign_successfully'));
            } else {
                set_alert('danger', _l('ticket_alrady_assign'));
            }
            redirect(admin_url('mailbox/'.$data['type'].'/'.$data['mailbox_id']));
        }
    }

    /**
     * Create task from email
     */
    public function assign_task()
    {
        $mail = $this->mailbox_model->get($this->input->post('mailbox_id'), $this->input->post('type'));

        // Retrieve the email subject and body from GET parameters
        $email_subject = $mail->subject; // Get the email subject from the URL query string
        $email_body = $mail->body; // Get the email body from the URL query string

        // Check if both subject and body are present
        if (empty($email_subject) || empty($email_body)) {
            set_alert('danger', 'Email subject or body is missing');
            redirect(admin_url('mailbox'));
        }

        // Get the current staff ID (the person performing the action)
        $staff_id = get_staff_user_id(); // This retrieves the logged-in staff user ID

        $this->db->where("id", $mail->taskid);
        $task = $this->db->get(db_prefix() . 'tasks')->row();

        if (!$task) {
            // Prepare task data using email subject as name and email body as description
            $task_data = [
                'name' => $email_subject, // Set task name as the email subject
                'startdate' => date('Y-m-d'), // Set start date as today's date
                'duedate' => null, // Set start date as today's date
                'description' => $email_body, // Set task description as the email body
            ];

            // Insert the task into the database and get the task ID
            $task_id = $this->tasks_model->add($task_data);

            // Insert the task into the task_assigned table (assign it to the current staff)
            $this->db->insert(db_prefix() . 'task_assigned', [
                'taskid'        => $task_id,
                'staffid'       => $this->input->post('select_customer'), // Assign to the current staff member performing the action
                'assigned_from' => $staff_id, // This also uses the current staff member as the one assigning
            ]);

            $this->db->where('id', $mail->id);
            $this->db->update(db_prefix() . 'mail_'.$this->input->post('type'), [
                'taskid' => $task_id,
            ]);

            // Optionally, return a success message and redirect
            set_alert('success', 'Email has been converted successfully to Task');
            redirect(admin_url('tasks')); // Redirect to the task list page
        }

        set_alert('danger', 'Email has been already converted to Task');
        redirect(admin_url('mailbox/'.$this->input->post('type').'/'.$this->input->post('mailbox_id')));
    }

    public function get_recipients() {
        $post_data = $this->input->post();
        $keyword = '';
        if (isset($post_data['keyword'])) {
            $keyword = $post_data['keyword'];
        }
        echo json_encode($this->mailbox_model->search_contacts($keyword));
        die();
    }

    public function contacts() {
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data(module_views_path('mailbox', 'contacts/table'));
        }

        $data['title'] = _l('contacts');
        $data['bodyclass'] = 'mailbox mailbox-contacts';
        $this->load->view('contacts/index', $data);
    }

    public function form_contact($contact_id = '') {
		$customer_id = $data['customer_id'] = $this->input->post('customer_id', '');
		$contact_id = $data['contactid'] = $contact_id ? $contact_id : '';

		$data['customers'] = $this->clients_model->get();

		if (staff_cant('view', 'customers'))
		{
			if (!is_customer_admin($customer_id))
			{
				echo _l('access_denied');
				die;
			}
		}
		if (is_automatic_calling_codes_enabled())
		{
			$clientCountryId = $this->db->select('country')->where('userid', $customer_id)->get('clients')->row()->country ?? null;
			$clientCountry = get_country($clientCountryId);
			$callingCode = $clientCountry ? '+' . ltrim($clientCountry->calling_code, '+') : null;
		} else {
			$callingCode = null;
		}
		if ($this->input->post())
		{
			$data = $this->input->post();
			$data['password'] = $this->input->post('password', false);
			if ($callingCode && !empty($data['phonenumber']) && $data['phonenumber'] == $callingCode)
			{
				$data['phonenumber'] = '';
			}
			unset($data['customer_id']);
			unset($data['contactid']);
			if ($contact_id == '')
			{
				if (staff_cant('create', 'customers'))
				{
					if (!is_customer_admin($customer_id))
					{
						header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad error');
						echo json_encode(['success' => false, 'message' => _l('access_denied') , ]);
						die;
					}
				}
				$id = $this->clients_model->add_contact($data, $customer_id);
				$message = '';
				$success = false;
				if ($id)
				{
					handle_contact_profile_image_upload($id);
					$success = true;
					$message = _l('added_successfully', _l('contact'));
				}
				echo json_encode(['success' => $success, 'message' => $message, 'has_primary_contact' => (total_rows(db_prefix() . 'contacts', ['userid' => $customer_id, 'is_primary' => 1]) > 0 ? true : false) , 'is_individual' => is_empty_customer_company($customer_id) && total_rows(db_prefix() . 'contacts', ['userid' => $customer_id]) == 1, ]);
				die;
			}
			if (staff_cant('edit', 'customers'))
			{
				if (!is_customer_admin($customer_id))
				{
					header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad error');
					echo json_encode(['success' => false, 'message' => _l('access_denied') , ]);
					die;
				}
			}
			$original_contact = $this->clients_model->get_contact($contact_id);
			$success = $this->clients_model->update_contact($data, $contact_id);
			$message = '';
			$proposal_warning = false;
			$original_email = '';
			$updated = false;
			if (is_array($success))
			{
				if (isset($success['set_password_email_sent']))
				{
					$message = _l('set_password_email_sent_to_client');
				} else if (isset($success['set_password_email_sent_and_profile_updated']))
				{
					$updated = true;
					$message = _l('set_password_email_sent_to_client_and_profile_updated');
				}
			} else {
				if ($success == true)
				{
					$updated = true;
					$message = _l('updated_successfully', _l('contact'));
				}
			}
			if (handle_contact_profile_image_upload($contact_id) && !$updated)
			{
				$message = _l('updated_successfully', _l('contact'));
				$success = true;
			}
			if ($updated == true)
			{
				$contact = $this->clients_model->get_contact($contact_id);
				if (total_rows(db_prefix() . 'proposals', ['rel_type' => 'customer', 'rel_id' => $contact->userid, 'email' => $original_contact->email, ]) > 0 && ($original_contact->email != $contact->email))
				{
					$proposal_warning = true;
					$original_email = $original_contact->email;
				}
			}
			echo json_encode(['success' => $success, 'proposal_warning' => $proposal_warning, 'message' => $message, 'original_email' => $original_email, 'has_primary_contact' => (total_rows(db_prefix() . 'contacts', ['userid' => $customer_id, 'is_primary' => 1]) > 0 ? true : false) , ]);
			die;
		}
		$data['calling_code'] = $callingCode;
		if ($contact_id == '')
		{
			$title = _l('add_new', _l('contact'));
		} else {
			$data['contact'] = $this->clients_model->get_contact($contact_id);
			if (!$data['contact'])
			{
				header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad error');
				echo json_encode(['success' => false, 'message' => 'Contact Not Found', ]);
				die;
			}
			$title = $data['contact']->firstname . ' ' . $data['contact']->lastname;
		}
		$data['customer_permissions'] = get_contact_permissions();
		$data['title'] = $title;
		$this->load->view('mailbox/contacts/modal', $data);
    }

    public function tags() {
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data(module_views_path('mailbox', 'tags/table'));
        }

        $data['title'] = _l('mailbox_tags');
        $data['bodyclass'] = 'mailbox mailbox-tags';
        $this->load->view('tags/index', $data);
    }

    public function form_tag($tag_id = '') {
		$tag_id = $data['tag_id'] = $tag_id ? $tag_id : '';

		if ($this->input->post())
		{
			$data = $this->input->post();
            if (isset($data['active'])) {
                $data['active'] = 1;
            } else {
                $data['active'] = 0;
            }
			if ($tag_id == '')
			{
				$id = $this->mailbox_model->add_tag($data, $tag_id);
				$message = '';
				$success = false;
				if ($id)
				{
					$success = true;
					$message = _l('added_successfully', _l('mailbox_tag'));
				}
				echo json_encode(['success' => $success, 'message' => $message ]);
				die;
			}
			$original_tag = $this->mailbox_model->get_tag($tag_id);
			$success = $this->mailbox_model->update_tag($data, $tag_id);
            $message = _l('updated_successfully', _l('mailbox_tag'));
			echo json_encode(['success' => $success, 'message' => $message ]);
			die;
		}
		if ($tag_id == '')
		{
			$title = _l('add_new', _l('mailbox_tag'));
		} else {
			$data['tag'] = $this->mailbox_model->get_tag($tag_id);
			if (!$data['tag'])
			{
				header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad error');
				echo json_encode(['success' => false, 'message' => 'Tag Not Found', ]);
				die;
			}
			$title = $data['tag']->name;
		}
		$data['title'] = $title;
		$this->load->view('mailbox/tags/modal', $data);
    }

	public function change_tag_status($id, $status)
	{
        if ($this->input->is_ajax_request())
        {
            return $this->mailbox_model->change_tag_status($id, $status);
        }
	}

    public function update_mail_tag($id, $tag_id = '', $type = 'outbox')
    {
        $message = _l('cant_find', _l('mailbox_tag'));
        $success = false;

        if ($id) {
            // Get type from POST data first (most reliable), then fall back to URL parameter, then default
            if ($this->input->post('type')) {
                $type = $this->input->post('type');
            } else if (!empty($type) && $type !== 'outbox') {
                // Use URL parameter if provided and not default
                $type = $type;
            } else {
                // Default to 'inbox' if not specified (more common case)
                $type = 'inbox';
            }

            $success = true;
            // Handle POST data (for multiple tags)
            if ($this->input->post('tag_id')) {
                $tag_id = $this->input->post('tag_id');
                // If it's an array, use it directly; otherwise convert to array
                if (!is_array($tag_id)) {
                    $tag_id = !empty($tag_id) ? [$tag_id] : [];
                }
            } else if (!empty($tag_id)) {
                // URL parameter: convert to array if not already
                $tag_id = is_array($tag_id) ? $tag_id : [$tag_id];
            } else {
                $tag_id = [];
            }

            $response = $this->mailbox_model->update_mail_tag($id, $tag_id, $type);
            if ($response) {
				$message = _l('updated_successfully', _l('mailbox_tag'));
            } else {
                // If update failed, set success to false
                $success = false;
            }
        }

        echo json_encode(['success' => $success, 'message' => $message, 'id' => $id, 'tag_id' => $tag_id, 'type' => $type ]);
        die;
    }

	public function delete_tag($id)
	{
		if (!$id)
		{
			redirect(admin_url('mailbox/tags'));
		}
		$response = $this->mailbox_model->delete_tag($id);
		if (is_array($response) && isset($response['referenced']))
		{
			set_alert('warning', _l('mailbox_tag_delete_transactions_warning', _l('mailbox_tags')));
		} else if ($response == true)
		{
			set_alert('success', _l('deleted', _l('mailbox_tag')));
		} else {
			set_alert('warning', _l('problem_deleting'));
		}
		redirect(admin_url('mailbox/tags'));
	}

    public function email_templates() {
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data(module_views_path('mailbox', 'email_templates/table'));
        }

        $data['title'] = _l('email_templates');
        $data['bodyclass'] = 'mailbox mailbox-email-templates';
        $this->load->view('email_templates/index', $data);
    }

    public function get_email_template($email_template_id = '') {
        if ($this->input->is_ajax_request())
        {
            $email_template = $this->emails_model->get_email_template_by_id($email_template_id);
            echo json_encode($email_template);
            exit;
        }
    }

    public function form_email_template($email_template_id = '') {
		$email_template_id = $data['emailtemplateid'] = $email_template_id ? $email_template_id : '';

		if ($this->input->post())
		{
            if (staff_cant('edit', 'email_templates')) {
                access_denied('email_templates');
            }

            $bluk_data = $this->input->post();
            if (isset($bluk_data['plaintext'])) {
                $bluk_data['plaintext'] = 1;
            } else {
                $bluk_data['plaintext'] = 0;
            }

            if (isset($bluk_data['active'])) {
                $bluk_data['active'] = 1;
            } else {
                $bluk_data['active'] = 0;
            }

            if (isset($bluk_data['autoreply'])) {
                $bluk_data['autoreply'] = 1;
            } else {
                $bluk_data['autoreply'] = 0;
            }

            $data = [
                'type' => 'mailbox',
                'slug' => preg_replace("/\-+/i", "-", strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $bluk_data['name'])))),
                'language' => 'english',
                'name' => $bluk_data['name'],
                'subject' => $bluk_data['subject']['english'],
                'message' => $bluk_data['message']['english'],
                'plaintext' => $bluk_data['plaintext'],
                'active' => $bluk_data['active'],
            ];

			if ($email_template_id == '')
			{
				$message = '';
				$success = false;
                foreach ($bluk_data['subject'] as $lang => $subject) {
                    $data['subject'] = $bluk_data['subject'][$lang];
                    $data['message'] = $bluk_data['message'][$lang];
                    $data['language'] = $lang;
                    $id = $this->emails_model->add_template($data);
                    if ($id)
                    {
                        $success = true;
                        $message = _l('added_successfully', _l('email_template'));
                    }
                }
				echo json_encode(['success' => $success, 'message' => $message ]);
				die;
			}
            $email_template = $this->emails_model->get_email_template_by_id($email_template_id);
            foreach ($bluk_data['subject'] as $lang => $subject) {
                $data['subject'] = $bluk_data['subject'][$lang];
                $data['message'] = $bluk_data['message'][$lang];
                $data['language'] = $lang;
                $original_email_template = $this->emails_model->get([
                    'slug' => $email_template->slug,
                    'language' => $lang
                ], 'row');
                $success = $this->mailbox_model->update_email_template($data, $original_email_template->emailtemplateid);
            }
            $message = _l('updated_successfully', _l('email_template'));
            echo json_encode(['success' => $success, 'message' => $message ]);
			die;
		}
		if ($email_template_id == '')
		{
			$title = _l('add_new', _l('email_template'));
		} else {
			$data['email_template'] = $this->emails_model->get_email_template_by_id($email_template_id);
			if (!$data['email_template'])
			{
				header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad error');
				echo json_encode(['success' => false, 'message' => 'Email Template Not Found', ]);
				die;
			}
			$title = $data['email_template']->name;
		}

        $data['available_languages'] = $this->app->get_available_languages();
        if (($key = array_search('english', $data['available_languages'])) !== false) {
            unset($data['available_languages'][$key]);
        }
		$data['title'] = $title;
		$data['email_templates'] = $this->emails_model->get([
            'language' => 'english',
            'active' => true,
        ]);
		$this->load->view('mailbox/email_templates/modal', $data);
    }

	public function update_email_template_status($id, $status)
	{
        if ($this->input->is_ajax_request())
        {
            $this->mailbox_model->update_email_template_status($id, $status);
        }
	}

    public function update_mail_template($id, $template_id = '', $type = 'outbox')
    {
        $message = _l('cant_find', _l('email_template'));

        if ($id) {
            $success = true;
            $response = $this->mailbox_model->update_mail_template($id, $template_id, $type);
            if ($response) {
				$message = _l('updated_successfully', _l('email_template'));
            }
        }

        echo json_encode(['success' => $success, 'message' => $message, 'id' => $id, 'template_id' => $template_id, 'type' => $type ]);
        die;
    }

	public function delete_email_template($id)
	{
		if (!$id)
		{
			redirect(admin_url('mailbox/email_templates'));
		}
		$response = $this->mailbox_model->delete_email_templates($id);
		if (is_array($response) && isset($response['referenced']))
		{
			set_alert('warning', _l('mailbox_email_template_delete_transactions_warning', _l('mailbox_email_templates')));
		} else if ($response == true)
		{
			set_alert('success', _l('deleted', _l('email_template')));
		} else {
			set_alert('warning', _l('problem_deleting'));
		}
		redirect(admin_url('mailbox/email_templates'));
	}

    public function auto_replies() {
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data(module_views_path('mailbox', 'auto_replies/table'));
        }

        $data['title'] = _l('mailbox_auto_replies');
        $data['bodyclass'] = 'mailbox mailbox-auto-replies';
        $this->load->view('auto_replies/index', $data);
    }

    public function form_auto_reply($auto_reply_id = '') {
		$auto_reply_id = $data['auto_reply_id'] = $auto_reply_id ? $auto_reply_id : '';

		if ($this->input->post())
		{
			$data = $this->input->post();
            if (isset($data['active'])) {
                $data['active'] = 1;
            } else {
                $data['active'] = 0;
            }
			if ($auto_reply_id == '')
			{
				$id = $this->mailbox_model->add_auto_reply($data, $auto_reply_id);
				$message = '';
				$success = false;
				if ($id)
				{
					$success = true;
					$message = _l('added_successfully', _l('mailbox_auto_reply'));
				}
				echo json_encode(['success' => $success, 'message' => $message ]);
				die;
			}
			$original_auto_reply = $this->mailbox_model->get_auto_reply($auto_reply_id);
			$success = $this->mailbox_model->update_auto_reply($data, $auto_reply_id);
            $message = _l('updated_successfully', _l('mailbox_auto_reply'));
			echo json_encode(['success' => $success, 'message' => $message ]);
			die;
		}
		if ($auto_reply_id == '')
		{
			$title = _l('add_new', _l('mailbox_auto_reply'));
		} else {
			$data['auto_reply'] = $this->mailbox_model->get_auto_reply($auto_reply_id);
			if (!$data['auto_reply'])
			{
				header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad error');
				echo json_encode(['success' => false, 'message' => 'Auto Reply Not Found', ]);
				die;
			}
			$title = $data['auto_reply']->name;
		}

		$data['email_templates'] = $this->emails_model->get([
            'language' => 'english',
            'active' => true,
            'type' => 'mailbox',
        ]);

		$data['title'] = $title;
		$this->load->view('mailbox/auto_replies/modal', $data);
    }

	public function change_auto_reply_status($id, $status)
	{
        if ($this->input->is_ajax_request())
        {
            return $this->mailbox_model->change_auto_reply_status($id, $status);
        }
	}

	public function delete_auto_reply($id)
	{
		if (!$id)
		{
			redirect(admin_url('mailbox/auto_replies'));
		}
		$response = $this->mailbox_model->delete_auto_reply($id);
		if (is_array($response) && isset($response['referenced']))
		{
			set_alert('warning', _l('mailbox_auto_reply_delete_transactions_warning', _l('mailbox_auto_replies')));
		} else if ($response == true)
		{
			set_alert('success', _l('deleted', _l('mailbox_auto_reply')));
		} else {
			set_alert('warning', _l('problem_deleting'));
		}
		redirect(admin_url('mailbox/auto_replies'));
	}

    /**
     * Assign customer from email
     */
    public function assign_email_to_customer()
    {
        // Retrieve the email subject and body from GET parameters
        $email_subject = $this->input->get('email_subject'); // Get the email subject from the URL query string
        $email_body = $this->input->get('email_body'); // Get the email body from the URL query string

        // Check if both subject and body are present
        if (empty($email_subject) || empty($email_body)) {
            set_alert('danger', 'Email subject or body is missing');
            redirect(admin_url('mailbox'));
        }

        // Get the current staff ID (the person performing the action)
        $staff_id = get_staff_user_id(); // This retrieves the logged-in staff user ID

        // Prepare task data using email subject as name and email body as description
        $task_data = [
            'name' => $email_subject, // Set task name as the email subject
            'startdate' => date('Y-m-d'), // Set start date as today's date
            'description' => $email_body, // Set task description as the email body
        ];

        // Insert the task into the database and get the task ID
        $task_id = $this->tasks_model->add($task_data);

        // Insert the task into the task_assigned table (assign it to the current staff)
        $this->db->insert(db_prefix() . 'task_assigned', [
            'taskid'        => $task_id,
            'staffid'       => $staff_id, // Assign to the current staff member performing the action
            'assigned_from' => $staff_id, // This also uses the current staff member as the one assigning
        ]);

        // Optionally, return a success message and redirect
        set_alert('success', 'Email has been converted successfully to Task');
        redirect(admin_url('tasks')); // Redirect to the task list page
    }

    /**
     * Assign customers
     *
     * @return redirect
     */
    public function assign_customers()
    {
        if ($this->input->post()) {
            $data = $this->input->post();
            $this->load->model('mailbox_model');
            $customerData = $this->mailbox_model->assign_customers($data);
            if ($customerData) {
                set_alert('success', _l('customers_assign_successfully'));
                redirect(admin_url('mailbox/'.$data['type'].'/'.$data['mailbox_id']));
            }
        }
    }

    public function unassign_customers()
    {
        if ($this->input->post()) {
            $data = $this->input->post();
            $this->load->model('mailbox_model');
            set_alert('success', _l('customers_unassign_successfully'));
            $success = $this->mailbox_model->unassign_customers($data);
        }

        echo json_encode(['success' => $success, 'message' => _l('customers_unassign_successfully'), 'id' => $data['mail_id'], 'client_id' => $data['client_id'], 'type' => $data['type'] ]);
        die;
    }

    public function table_client_emails($client_id = '')
    {
        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data(module_views_path('mailbox', 'table_client_emails'), ['client_id' => $client_id]);
        }
    }

    /**
     * OAuth authorization handler
     * Handles /admin/mailbox/oauth/authorize route
     * Updated to use per-staff OAuth credentials
     */
    public function oauth($action = 'authorize')
    {
        if ($action === 'authorize') {
            $provider = $this->input->get('provider');
            $staffId = get_staff_user_id();

            // Log that THIS method is being called (not the OAuth controller)
            log_activity('[MAILBOX] oauth() method called in Mailbox controller - Staff: ' . $staffId . ', Provider: ' . $provider);

            if (empty($provider)) {
                set_alert('danger', 'Provider not specified');
                redirect(admin_url('mailbox/config'));
            }

            try {
                // Fetch the CURRENT staff member's OAuth credentials (SAME as OAuth controller)
                $this->load->model('staff_model');
                $staff = $this->staff_model->get($staffId);

                if (empty($staff)) {
                    throw new Exception('Staff member not found');
                }

                log_activity('[MAILBOX] Fetching per-staff OAuth credentials for: ' . $staff->email);

                // Check if encrypted credentials exist
                if (empty($staff->oauth_client_id) || empty($staff->oauth_client_secret)) {
                    log_activity('[MAILBOX] ERROR: No encrypted OAuth credentials in database for staff: ' . $staffId);
                    throw new Exception('OAuth credentials not saved. Please save your Client ID and Secret first.');
                }

                log_activity('[MAILBOX] Encrypted credentials found - Client ID length: ' . strlen($staff->oauth_client_id));

                // Decrypt OAuth credentials
                $clientId = decrypt_mail_password($staff->oauth_client_id);
                $clientSecret = decrypt_mail_password($staff->oauth_client_secret);
                $tenantId = !empty($staff->oauth_tenant_id) ? $staff->oauth_tenant_id : null;

                log_activity('[MAILBOX] Decrypted credentials - Client ID length: ' . strlen($clientId) . ', Secret length: ' . strlen($clientSecret));

                if (empty($clientId) || empty($clientSecret)) {
                    log_activity('[MAILBOX] ERROR: Decryption returned empty values!');
                    throw new Exception('OAuth credential decryption failed. Please re-enter your Client ID and Secret.');
                }

                log_activity('[MAILBOX] Creating OAuth provider with per-staff credentials');

                $providerClass = 'modules\\mailbox\\services\\oauth\\' . ucfirst($provider) . 'OAuth';

                if (!class_exists($providerClass)) {
                    throw new Exception('OAuth provider not found: ' . $provider);
                }

                // Pass the staff member's decrypted credentials
                if ($provider === 'outlook') {
                    $oauth = new $providerClass($clientId, $clientSecret, $tenantId);
                } else {
                    $oauth = new $providerClass($clientId, $clientSecret);
                }

                $authUrl = $oauth->getAuthUrl();

                // Store provider and staff ID in session for callback
                $this->session->set_userdata('oauth_provider', $provider);
                $this->session->set_userdata('oauth_staff_id', $staffId);

                log_activity('[MAILBOX] Redirecting to OAuth provider authorization URL');
                redirect($authUrl);
            } catch (Exception $e) {
                log_activity('[MAILBOX] OAuth authorization error: ' . $e->getMessage());
                set_alert('danger', 'OAuth error: ' . $e->getMessage());
                redirect(admin_url('mailbox/config'));
            }
        } elseif ($action === 'callback') {
            $code = $this->input->get('code');
            $error = $this->input->get('error');
            $provider = $this->input->get('provider') ?: $this->session->userdata('oauth_provider');
            $staffId = $this->session->userdata('oauth_staff_id') ?: get_staff_user_id();

            log_activity('[MAILBOX] OAuth callback received - Provider: ' . $provider . ', Staff: ' . $staffId);

            if ($error) {
                log_activity('[MAILBOX] OAuth callback error: ' . $error);
                set_alert('danger', 'OAuth authorization failed: ' . $error);
                redirect(admin_url('mailbox/config'));
            }

            if (empty($code) || empty($provider)) {
                log_activity('[MAILBOX] OAuth callback - Missing code or provider');
                set_alert('danger', 'Invalid OAuth callback');
                redirect(admin_url('mailbox/config'));
            }

            try {
                // Fetch staff member's OAuth credentials (same as authorize)
                $this->load->model('staff_model');
                $staff = $this->staff_model->get($staffId);

                if (empty($staff)) {
                    throw new Exception('Staff member not found');
                }

                // Decrypt credentials
                $clientId = !empty($staff->oauth_client_id) ? decrypt_mail_password($staff->oauth_client_id) : null;
                $clientSecret = !empty($staff->oauth_client_secret) ? decrypt_mail_password($staff->oauth_client_secret) : null;
                $tenantId = !empty($staff->oauth_tenant_id) ? $staff->oauth_tenant_id : null;

                if (empty($clientId) || empty($clientSecret)) {
                    throw new Exception('OAuth credentials not found');
                }

                log_activity('[MAILBOX] Exchanging authorization code for tokens using per-staff credentials');

                $providerClass = 'modules\\mailbox\\services\\oauth\\' . ucfirst($provider) . 'OAuth';

                if (!class_exists($providerClass)) {
                    throw new Exception('OAuth provider not found: ' . $provider);
                }

                // Pass per-staff credentials
                if ($provider === 'outlook') {
                    $oauth = new $providerClass($clientId, $clientSecret, $tenantId);
                } else {
                    $oauth = new $providerClass($clientId, $clientSecret);
                }

                $tokens = $oauth->getAccessToken($code);

                if (empty($tokens['access_token'])) {
                    throw new Exception('Failed to get access token');
                }

                // Get staff email
                $this->load->model('staff_model');
                $staff = $this->staff_model->get($staffId);

                if (!$staff) {
                    throw new Exception('Staff member not found');
                }

                // Calculate token expiration time
                $expires_in = isset($tokens['expires_in']) ? (int)$tokens['expires_in'] : 3600;
                $expires_at = date('Y-m-d H:i:s', time() + $expires_in);

                // Save tokens to database
                $updateData = [
                    'auth_method' => 'oauth2',
                    'oauth_provider' => $provider,
                    'oauth_access_token' => encrypt_mail_password($tokens['access_token']),
                    'oauth_token_expires_at' => $expires_at
                ];

                // Save refresh token if provided
                if (isset($tokens['refresh_token']) && !empty($tokens['refresh_token'])) {
                    $updateData['oauth_refresh_token'] = encrypt_mail_password($tokens['refresh_token']);
                }

                $this->db->where('staffid', $staffId);
                $result = $this->db->update(db_prefix().'staff', $updateData);

                if (!$result) {
                    throw new Exception('Failed to save OAuth tokens to database');
                }

                // Clear session
                $this->session->unset_userdata('oauth_provider');

                set_alert('success', ucfirst($provider) . ' account connected successfully');
                log_activity('OAuth account connected: ' . $provider . ' for staff: ' . $staffId . ' (Email: ' . $staff->email . ')');

            } catch (Exception $e) {
                set_alert('danger', 'OAuth callback error: ' . $e->getMessage());
                log_activity('OAuth callback error: ' . $e->getMessage());
            }

            redirect(admin_url('mailbox?group=config'));
        } elseif ($action === 'disconnect') {
            $staffId = get_staff_user_id();

            $this->db->where('staffid', $staffId);
            $this->db->update(db_prefix().'staff', [
                'auth_method' => 'password',
                'oauth_provider' => null,
                'oauth_access_token' => null,
                'oauth_refresh_token' => null,
                'oauth_token_expires_at' => null
            ]);

            log_activity('OAuth account disconnected for staff: ' . $staffId);

            // If AJAX request, return success response
            if ($this->input->is_ajax_request()) {
                echo json_encode(['success' => true]);
                die();
            }

            set_alert('success', 'OAuth account disconnected successfully');
            redirect(admin_url('profile?tab=mailbox_config'));
        } else {
            show_404();
        }
    }

    /**
     * Email preview for preview pane
     */
    public function preview($id, $type = 'inbox')
    {
        try {
            // Handle both inbox and outbox
            $mailbox = $this->mailbox_model->get($id, $type);
            if (!$mailbox) {
                echo '<p class="text-danger">' . _l('mailbox_email_not_found') . '</p>';
                return;
            }

            // Auto-mark as read in preview pane if setting is enabled (inbox only)
            // This will also trigger IMAP sync if sync_read_status_to_server is enabled
            if ($type == 'inbox') {
                // Check staff's per-staff setting first, then fall back to global
                $staff_id = get_staff_user_id();
                $staff_settings = $this->staff_model->get($staff_id);
                $auto_mark_read = isset($staff_settings->mailbox_auto_mark_read_on_preview)
                    ? $staff_settings->mailbox_auto_mark_read_on_preview
                    : get_option('mailbox_auto_mark_read_on_preview');

                if ($auto_mark_read == '1' && $mailbox->read == 0) {
                    // This will also trigger IMAP sync if sync_read_status_to_server is enabled
                    $this->mailbox_model->update_field('detail', 'read', 1, $id, 'inbox');
                }
            }

            // Get attachments
            $attachments = $this->mailbox_model->get_mail_attachment($id, $type);

            $data['mailbox'] = $mailbox;
            $data['attachments'] = $attachments;
            $data['type'] = $type;

            $this->load->view('mailbox/mailbox_preview', $data);
        } catch (Exception $e) {
            if (function_exists('log_activity')) {
                log_activity('Mailbox preview() ERROR: ' . $e->getMessage(), null);
            }
            echo '<p class="text-danger">' . _l('mailbox_preview_error') . '</p>';
        }
    }

    /**
     * Rules management page (standalone, not part of main mailbox view)
     */
    public function rules_index()
    {
        if (!$this->db->table_exists(db_prefix() . 'mail_rules')) {
            show_404();
            return;
        }

        if (!get_option('mailbox_enable_rules') || get_option('mailbox_enable_rules') == '0') {
            show_404();
            return;
        }

        $data['title'] = _l('mailbox_rules');
        $data['bodyclass'] = 'mailbox mailbox-rules';
        $this->load->view('rules_manage', $data);
    }

    /**
     * Get rules table data
     */
    public function rules_table()
    {
        // Allow both AJAX and direct access for debugging
        if ($this->input->is_ajax_request() || $this->input->get('debug')) {
            try {
                // Set default DataTables parameters if testing with ?debug=1
                if ($this->input->get('debug') && !$this->input->get('draw')) {
                    $_GET['draw'] = 0;
                    $_GET['start'] = 0;
                    $_GET['length'] = 10;
                    $_GET['search'] = ['value' => '', 'regex' => false];
                }

                $this->app->get_table_data(module_views_path('mailbox', 'rules/table'));
            } catch (Exception $e) {
                echo json_encode([
                    'error' => true,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        } else {
            // If accessed directly without AJAX, show debug info
            echo json_encode([
                'error' => true,
                'message' => 'This endpoint requires AJAX request. Add ?debug=1 to test.',
                'is_ajax' => $this->input->is_ajax_request(),
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            ]);
        }
    }

    /**
     * Get single rule
     */
    public function rules_get($id)
    {
        $rule = $this->mailbox_model->get_rule($id, get_staff_user_id());
        if ($rule) {
            echo json_encode(['success' => true, 'rule' => $rule]);
        } else {
            echo json_encode(['success' => false, 'message' => _l('mailbox_rule_not_found')]);
        }
    }

    /**
     * Save rule
     */
    public function rules_save()
    {
        if ($this->input->post()) {
            $rule_id = $this->input->post('rule_id');
            $data = [
                'staff_id' => get_staff_user_id(),
                'name' => $this->input->post('rule_name'),
                'conditions' => json_encode($this->input->post('conditions')),
                'actions' => json_encode($this->input->post('actions')),
                'order' => (int)$this->input->post('rule_order'),
                'enabled' => $this->input->post('rule_enabled') ? 1 : 0,
            ];

            if ($rule_id) {
                $result = $this->mailbox_model->update_rule($rule_id, $data, get_staff_user_id());
                $message = $result ? _l('mailbox_rule_updated') : _l('mailbox_rule_update_failed');
            } else {
                $result = $this->mailbox_model->add_rule($data);
                $message = $result ? _l('mailbox_rule_created') : _l('mailbox_rule_create_failed');
            }

            echo json_encode([
                'success' => $result,
                'message' => $message,
            ]);
        }
    }

    /**
     * Delete rule
     */
    public function rules_delete($id)
    {
        $result = $this->mailbox_model->delete_rule($id, get_staff_user_id());
        $message = $result ? _l('mailbox_rule_deleted') : _l('mailbox_rule_delete_failed');

        echo json_encode([
            'success' => $result,
            'message' => $message,
        ]);
    }

    /**
     * Manual email sync endpoint for admin settings page
     * Triggers the scan_email_server hook
     */
    public function manual_sync()
    {
        // Only allow admins to trigger manual sync
        if (!is_admin()) {
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized. Only administrators can trigger manual sync.'
            ]);
            return;
        }

        try {
            // Execute the scan_email_server function
            hooks()->do_action('scan_email_server_cronjob');

            echo json_encode([
                'success' => true,
                'message' => 'Email sync triggered successfully. Check Activity Log for details.'
            ]);
        } catch (Exception $e) {
            log_activity('Manual email sync failed: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ]);
        }
    }
}
