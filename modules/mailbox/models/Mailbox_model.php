<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Mailbox Model.
 */
class Mailbox_model extends App_Model
{
    /**
     * Controler __construct function to initialize options.
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->load->library('email');
    }

    /**
     * Add new ticket to database.
     *
     * @param mixed $data  ticket $_POST data
     * @param mixed $admin If admin adding the ticket passed staff id
     */
    public function add($data, $staff_id, $ob_id = null)
    {
        $outbox_id                      = '';
        $outbox                         = [];
        $outbox['sender_staff_id']      = $staff_id;
        $outbox['to']                   = $data['to'];
        $outbox['cc']                   = $data['cc'];
        $outbox['sender_name']          = get_staff_full_name($staff_id);
        $outbox['subject']              = _strip_tags($data['subject']);
        $outbox['body']                 = nl2br_save_html($data['body']);
        // Only set date_sent for actual sent emails, not drafts
        if (!isset($data['sendmail']) || $data['sendmail'] != 'draft') {
            $outbox['date_sent']        = date('Y-m-d H:i:s');
        }
        $outbox['tagid']                = $data['tagid'];
        $outbox['templateid']           = $data['templateid'];
        $outbox['scheduled_at']         = to_sql_date($data['scheduled_at'], true);
        $outbox['scheduled_status']     = $data['scheduled_at'] ? "Scheduled" : "";
        
        // Generate message_id for all emails (needed for threading)
        $threading_enabled = $this->db->field_exists('thread_id', db_prefix() . 'mail_outbox') && ('1' == get_option('mailbox_threading_enabled'));
        if ($threading_enabled || $this->db->field_exists('message_id', db_prefix() . 'mail_outbox')) {
            // Generate a unique message_id for this email
            $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
            $outbox['message_id'] = '<' . time() . '.' . uniqid() . '@' . $domain . '>';
        }
        
        if (isset($data['reply_from_id'])) {
            $outbox['reply_from_id'] = $data['reply_from_id'];
            
            // Get parent email to inherit thread information (only if threading columns exist AND threading is enabled)
            if ($threading_enabled) {
                $parentType = $data['reply_type'] ?? 'inbox';
                $parent = $this->get($data['reply_from_id'], $parentType);
                if ($parent) {
                    $outbox['in_reply_to'] = isset($parent->message_id) ? $parent->message_id : null;
                    $outbox['references'] = isset($parent->references) ? $parent->references : '';
                    if (!empty($parent->message_id)) {
                        $outbox['references'] = trim(($outbox['references'] ? $outbox['references'] . ' ' : '') . $parent->message_id);
                    }
                    // Inherit thread_id from parent
                    if (!empty($parent->thread_id)) {
                        $outbox['thread_id'] = $parent->thread_id;
                    } elseif (function_exists('generate_thread_id')) {
                        // Generate thread_id if parent doesn't have one
                        $outbox['thread_id'] = generate_thread_id(
                            $outbox['message_id'] ?? '',
                            $outbox['in_reply_to'] ?? '',
                            $outbox['references'] ?? '',
                            $outbox['subject'],
                            $staff_id
                        );
                    }
                } else {
                    // Parent not found, but still try to generate thread_id from subject
                    if (function_exists('generate_thread_id')) {
                        $outbox['thread_id'] = generate_thread_id(
                            $outbox['message_id'] ?? '',
                            '',
                            '',
                            $outbox['subject'],
                            $staff_id
                        );
                    }
                }
            }
        } elseif ($threading_enabled && function_exists('generate_thread_id')) {
            // New email - generate new thread_id
            $outbox['thread_id'] = generate_thread_id(
                $outbox['message_id'] ?? '',
                '',
                '',
                $outbox['subject'],
                $staff_id
            );
        }
        if (isset($data['reply_type'])) {
            $outbox['reply_type'] = $data['reply_type'];
        }
        if (isset($data['sendmail']) && 'draft' == $data['sendmail']) {
            $outbox['draft']      =  1;
            $this->db->insert(db_prefix() . 'mail_outbox', $outbox);

            return true;
        }
        if (isset($ob_id)) {
            $outbox['draft'] = 0;
            $this->db->where('id', $ob_id);
            $this->db->update(db_prefix() . 'mail_outbox', $outbox);
            $outbox_id = $ob_id;
        } else {
            $this->db->insert(db_prefix() . 'mail_outbox', $outbox);
            $outbox_id = $this->db->insert_id();
        }
        $inbox                       = [];
        $inbox['from_staff_id']      = $staff_id;
        $inbox['to']                 = $data['to'];
        $inbox['cc']                 = $data['cc'];
        $inbox['sender_name']        = get_staff_full_name($staff_id);
        $inbox['subject']            = _strip_tags($data['subject']);
        $inbox['body']               = nl2br_save_html($data['body']);
        $inbox['date_received']      = date('Y-m-d H:i:s');
        $inbox['folder']             = 'inbox';
        $inbox['from_email']         = get_staff_email_by_id($staff_id);
        $inbox['tagid']              = $data['tagid'];
        $inbox['templateid']         = $data['templateid'];
        
        // Inherit threading information from outbox (only if columns exist AND threading is enabled)
        if ($this->db->field_exists('thread_id', db_prefix() . 'mail_inbox') && ('1' == get_option('mailbox_threading_enabled'))) {
            if (isset($outbox['message_id'])) {
                $inbox['message_id'] = $outbox['message_id'];
            }
            if (isset($outbox['in_reply_to'])) {
                $inbox['in_reply_to'] = $outbox['in_reply_to'];
            }
            if (isset($outbox['references'])) {
                $inbox['references'] = $outbox['references'];
            }
            if (isset($outbox['thread_id'])) {
                $inbox['thread_id'] = $outbox['thread_id'];
            }
        }

        $array_send_to = [];
        $array_to      = explode(';', $data['to']);
        if (isset($array_to) && count($array_to) > 0) {
            foreach ($array_to as $value) {
                $array_send_to[$value] = $value;
            }
        }
        $array_cc = explode(';', $data['cc']);
        if (isset($array_cc) && count($array_cc) > 0) {
            foreach ($array_cc as $value) {
                $array_send_to[$value] = $value;
            }
        }

        $array_inbox_id = [];
        $sender_email = get_staff_email_by_id($staff_id);
        foreach ($array_send_to as $value) {
            $to = get_staff_id_by_email(trim($value));
            // Exclude sender from their own inbox - only add to inbox if recipient is different from sender
            if ($to > 0 && trim(strtolower($value)) !== trim(strtolower($sender_email))) {
                $d_inbox                = $inbox;
                $d_inbox['to_staff_id'] = $to;
                $this->db->insert(db_prefix() . 'mail_inbox', $d_inbox);
                $inbox_id         = $this->db->insert_id();
                $array_inbox_id[] = $inbox_id;
            }
        }
        $attachments = [];
        if ($outbox_id > 0) {
            if (count($array_inbox_id) > 0) {
                foreach ($array_inbox_id as $inbox_id) {
                    $attachments = handle_mail_attachments($inbox_id, 'inbox', 'attachments', 'copy');
                    if ($attachments) {
                        $this->insert_mail_attachments_to_database($attachments, $inbox_id, 'inbox');
                    }
                }
            }

            $attachments = handle_mail_attachments($outbox_id, 'outbox');
            if ($attachments) {
                $this->insert_mail_attachments_to_database($attachments, $outbox_id, 'outbox');
            }
        }

        // Send email - Check for OAuth first, fall back to SMTP
        if (!$outbox['scheduled_at']) {
            // Get staff OAuth and SMTP configuration
            $this->db->where('staffid', $staff_id);
            $this->db->select('staffid, email, firstname, lastname, auth_method, oauth_provider, oauth_access_token, oauth_refresh_token, oauth_token_expires_at, oauth_client_id, oauth_client_secret, oauth_tenant_id, smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_password');
            $staff = $this->db->get(db_prefix().'staff')->row_array();
            
            // Decrypt OAuth tokens and credentials if they exist
            if (!empty($staff['oauth_access_token'])) {
                $staff['oauth_access_token'] = decrypt_mail_password($staff['oauth_access_token']);
            }
            if (!empty($staff['oauth_refresh_token'])) {
                $staff['oauth_refresh_token'] = decrypt_mail_password($staff['oauth_refresh_token']);
            }
            if (!empty($staff['oauth_client_id'])) {
                $staff['oauth_client_id'] = decrypt_mail_password($staff['oauth_client_id']);
            }
            if (!empty($staff['oauth_client_secret'])) {
                $staff['oauth_client_secret'] = decrypt_mail_password($staff['oauth_client_secret']);
            }
            
            $emailSent = false;
            
            // Try OAuth first if configured
            if ($staff && !empty($staff['oauth_provider']) && $staff['auth_method'] == 'oauth2') {
                // Check if OAuth is configured for this staff member
                $oauthConfigured = !empty($staff['oauth_client_id']) && !empty($staff['oauth_client_secret']) && !empty($staff['oauth_refresh_token']);
                
                if ($oauthConfigured) {
                    try {
                        $emailSent = $this->send_email_via_oauth($staff, $inbox, $data, $outbox, $attachments, $outbox_id, $threading_enabled);
                        if ($emailSent) {
                            log_activity('Email sent via OAuth ('.$staff['oauth_provider'].'): ' . $inbox['subject'] . ' to ' . $data['to']);
                        }
                    } catch (Exception $e) {
                        log_activity('OAuth email sending failed, falling back to SMTP: ' . $e->getMessage());
                        $emailSent = false;
                    }
                } else {
                    log_activity('OAuth not fully configured (missing client_id/client_secret or refresh_token), using SMTP instead', null);
                }
            }
            
            // Fall back to SMTP if OAuth not configured or failed
            if (!$emailSent) {
                // Check for per-staff SMTP settings first, then fall back to global
                $smtp_host = !empty($staff['smtp_host']) ? $staff['smtp_host'] : get_option('smtp_host');
                $smtp_port = !empty($staff['smtp_port']) ? $staff['smtp_port'] : get_option('smtp_port');
                $smtp_encryption = !empty($staff['smtp_encryption']) ? $staff['smtp_encryption'] : get_option('smtp_encryption');
                $smtp_username = !empty($staff['smtp_username']) ? $staff['smtp_username'] : get_option('smtp_username');
                $smtp_password = !empty($staff['smtp_password']) ? decrypt_mail_password($staff['smtp_password']) : get_option('smtp_password');
                
                // Only proceed if we have SMTP configuration (either per-staff or global)
                if (!empty($smtp_host) && !empty($smtp_username) && !empty($smtp_password)) {
                    $config = [
                        'protocol' => 'smtp',
                        'smtp_host' => $smtp_host,
                        'smtp_port' => $smtp_port,
                        'smtp_user' => $smtp_username,
                        'smtp_pass' => $smtp_password,
                        'smtp_crypto' => $smtp_encryption,
                        'mailtype' => 'html',
                        'charset' => 'utf-8',
                        'newline' => "\r\n"
                    ];
                    
                    $this->email->initialize($config);
                    $this->email->clear(true);
                    $this->email->from($inbox['from_email'], $inbox['sender_name']);
                    $this->email->to(str_replace(';', ',', $data['to']));
                    if (isset($data['cc']) && strlen($data['cc']) > 0) {
                        $this->email->cc($data['cc']);
                    }
                    $this->email->subject($inbox['subject']);
                    $this->email->message($data['body']);
                    
                    // Set threading headers if threading is enabled
                    if ($threading_enabled && isset($outbox['message_id'])) {
                        $this->email->set_header('Message-ID', $outbox['message_id']);
                        if (isset($outbox['in_reply_to']) && !empty($outbox['in_reply_to'])) {
                            $this->email->set_header('In-Reply-To', $outbox['in_reply_to']);
                        }
                        if (isset($outbox['references']) && !empty($outbox['references'])) {
                            $this->email->set_header('References', $outbox['references']);
                        }
                    }
                    
                    if (isset($attachments) && $attachments && count($attachments)) {
                        foreach ($attachments as $attachment) {
                            $attachment_url = module_dir_url(MAILBOX_MODULE).'uploads/outbox/'.$outbox_id.'/'.$attachment['file_name'];
                            $this->email->attach($attachment_url);
                        }
                    }
                    $this->email->send(true);
                    
                    $smtp_source = !empty($staff['smtp_host']) ? 'per-staff SMTP' : 'global SMTP';
                    log_activity('Email sent via ' . $smtp_source . ': ' . $inbox['subject'] . ' to ' . $data['to']);
                }
            }
        
            // Auto Reply
            $this->db->select()->from(db_prefix() . 'mail_auto_replies')->where(db_prefix() . 'mail_auto_replies.active', true);
            $mail_auto_replies = $this->db->get()->result_array();

            $mail_inbox_auto_reply = null;
            foreach ($mail_auto_replies as $mail_auto_reply) {
                if (preg_match("/" . $mail_auto_reply['pattern'] . "/i", $inbox['subject']) || preg_match("/" . $mail_auto_reply['pattern'] . "/i", $data['body'])) {
                    $mail_inbox_auto_reply = $mail_auto_reply;
                }
            }
            if ($mail_inbox_auto_reply) {
                $this->email->initialize();
                
                $this->db->select()->from(db_prefix() . 'staff')->where(db_prefix() . 'staff.staffid', $inbox['from_staff_id']);
                $from_staff = $this->db->get()->row();

                foreach ($array_send_to as $value) {
                    $to = get_staff_id_by_email(trim($value));
                    if ($to > 0) {
                        $this->load->library('email');
                        $this->email->clear(true);

                        $this->db->select()->from(db_prefix() . 'staff')->where(db_prefix() . 'staff.staffid', $to);
                        $to_staff = $this->db->get()->row();

                        $this->email->from($to_staff->email, get_staff_full_name($to));
                        $this->email->to(str_replace(';', ',', $from_staff->email));
                        if ($mail_inbox_auto_reply['replyid']) {
                            $this->db->select()->from(db_prefix() . 'emailtemplates')->where(db_prefix() . 'emailtemplates.emailtemplateid', $mail_inbox_auto_reply['replyid']);
                            $auto_reply_emailtemplate = $this->db->get()->row();
        
                            $this->email->subject("RE: " . $inbox['subject']);
                            $this->email->message($auto_reply_emailtemplate->message);
                        } else {
                            $this->email->subject("RE: " . $inbox['subject']);
                            $this->email->message($mail_inbox_auto_reply['body']);
                        }
                        $this->email->send(true);
                        
                        log_activity('Auto Reply Email Sent - Name: ' . $mail_inbox_auto_reply['name'] . ' To: ' . $from_staff->email);
                    }
                }
            }
        }

        return true;
    }

    /**
     * Insert mail attachments to database.
     *
     * @param array $attachments array of attachment
     * @param mixed $mail_id
     */
    public function insert_mail_attachments_to_database($attachments, $mail_id, $type = 'inbox')
    {
        foreach ($attachments as $attachment) {
            $attachment['mail_id']  = $mail_id;
            $attachment['type']     = $type;
            $this->db->insert(db_prefix() . 'mail_attachment', $attachment);
            $this->db->where('id', $mail_id);
            $this->db->update(db_prefix() . 'mail_'.$type, [
                'has_attachment' => 1,
            ]);
        }
    }

    /**
     * Get detail email by id and type.
     *
     * @param int    $id
     * @param string $type
     *
     * @return row
     */
    public function get($id, $type='inbox')
    {
        $this->db->where('id', $id);

        return $this->db->get(db_prefix() . 'mail_'.$type)->row();
    }

    /**
     * Update email status.
     *
     * @param int    $group
     * @param string $action
     * @param int    $value
     * @param int    $mail_id
     * @param string $type
     *
     * @return bool
     */
    public function update_field($group, $action, $value, $mail_id, $type = 'inbox')
    {
        if ('starred' == $action) {
            $action = 'stared';
        }
        $arr_id = explode(',', $mail_id);
        foreach ($arr_id as $id) {
            if (strlen(trim($id)) > 0) {
                if (('trash' == $group || 'sent' == $group) && 'trash' == $action) {
                    if ('sent' == $group) {
                        $type = 'outbox';
                    }
                    $this->db->where('id', $id);
                    $this->db->delete(db_prefix() . 'mail_'.$type);

                    $this->db->where('mail_id', $id);
                    $file = $this->db->get(db_prefix() . 'mail_attachment')->result_array();
                    foreach ($file as $f) {
                        $path           = MAILBOX_MODULE_UPLOAD_FOLDER.'/'.$type.'/'.$id.'/'.$f['file_name'];
                        if (file_exists($path)) {
                            unlink($path);
                        }
                    }
                    $this->db->where('mail_id', $id);
                    $this->db->where('type', $type);
                    $this->db->delete(db_prefix() . 'mail_attachment');
                } else {
                    $this->db->where('id', $id);
                    $this->db->update(db_prefix() . 'mail_'.$type, [
                        $action => $value,
                    ]);
                    
                    // Sync read status to IMAP server if enabled (both read and unread)
                    if ($action == 'read' && $type == 'inbox') {
                        $this->sync_read_status_to_imap_server($id, $value == 1);
                    }
                }
            }
        }
        return true;
    }
    
    /**
     * Sync read status to IMAP server
     * Marks email as read/unread on IMAP server if sync_read_status_to_server setting is enabled
     *
     * @param int $email_id
     * @param bool $mark_as_read True to mark as read, false to mark as unread
     * @return void
     */
    private function sync_read_status_to_imap_server($email_id, $mark_as_read = true)
    {
        try {
            // Get email record
            $email = $this->get($email_id, 'inbox');
            if (!$email) {
                log_activity('IMAP Sync ERROR: Email ID ' . $email_id . ' not found', null);
                return;
            }
            
            // Check if UID and IMAP folder are available
            if (empty($email->uid)) {
                // Old emails (imported before migration 217) don't have UIDs - can't sync to server
                // This is expected - fail silently
                return;
            }
            
            if (empty($email->imap_folder)) {
                // Old emails might not have folder stored - fail silently
                return;
            }
            
            if (empty($email->to_staff_id)) {
                log_activity('IMAP Sync: Email ID ' . $email_id . ' has no to_staff_id', null);
                return;
            }
            
            // Get staff member's settings
            $this->db->where('staffid', $email->to_staff_id);
            $staff = $this->db->get(db_prefix() . 'staff')->row_array();
            
            if (!$staff) {
                return;
            }
            
            // Check if sync_read_status_to_server is enabled
            if (empty($staff['sync_read_status_to_server']) || $staff['sync_read_status_to_server'] != '1') {
                return; // Setting disabled - expected, no log
            }
            
            // Get IMAP connection details from staff settings
            $imap_server = !empty($staff['imap_server']) ? $staff['imap_server'] : get_option('mailbox_imap_server');
            $imap_port = !empty($staff['imap_port']) ? $staff['imap_port'] : 993;
            $imap_encryption = !empty($staff['imap_encryption']) ? $staff['imap_encryption'] : get_option('mailbox_encryption');
            
            if (empty($imap_server)) {
                log_activity('IMAP Sync ERROR: No IMAP server configured', null);
                return;
            }
            
            // Connect to IMAP using ImapService
            $imapServicePath = dirname(__FILE__) . '/../services/ImapService.php';
            require_once($imapServicePath);
            
            $imapService = new \modules\mailbox\services\ImapService([
                'imap_server' => $imap_server,
                'encryption' => $imap_encryption,
                'port' => $imap_port,
            ]);
            
            // Attempt to connect
            if (!$imapService->connect($staff)) {
                $error = $imapService->getError();
                log_activity('IMAP Sync ERROR: Connection failed - ' . $error, null);
                return;
            }
            
            // Select the folder
            if (!$imapService->selectFolder($email->imap_folder)) {
                log_activity('IMAP Sync ERROR: Cannot select folder "' . $email->imap_folder . '"', null);
                return;
            }
            
            // Mark as read/unread on IMAP server
            $result = $imapService->setUnseenMessage($email->uid, $mark_as_read);
            
            if (!$result) {
                $status = $mark_as_read ? 'read' : 'unread';
                log_activity('IMAP Sync ERROR: Failed to mark UID ' . $email->uid . ' as ' . $status, null);
            }
        } catch (Exception $e) {
            log_activity('IMAP Sync: Exception for email ID ' . $email_id . ': ' . $e->getMessage() . ' (File: ' . $e->getFile() . ', Line: ' . $e->getLine() . ')', null);
        }
    }

    /**
     * Get email attachments.
     *
     * @param int    $mail_id
     * @param string $type
     *
     * @return array
     */
    public function get_mail_attachment($mail_id, $type='inbox')
    {
        $this->db->where('mail_id', $mail_id);
        $this->db->where('type', $type);
        $this->db->order_by('id', 'ASC'); // Order by ID for consistent display

        $result = $this->db->get(db_prefix() . 'mail_attachment')->result_array();
        
        // Log for debugging if no results but should have attachments
        if (empty($result)) {
            // Check if email has has_attachment flag set
            $email = $this->db->select('has_attachment')->where('id', $mail_id)->get(db_prefix() . 'mail_' . $type)->row();
            if ($email && isset($email->has_attachment) && $email->has_attachment > 0) {
                log_activity('Warning: get_mail_attachment returned empty for mail_id=' . $mail_id . ', type=' . $type . ' but has_attachment=1', null);
            }
        }
        
        return $result;
    }

    /**
     * Update email configuration.
     *
     * @param array $data
     * @param int   $staff_id
     *
     * @return bool
     */
    public function update_config($data, $staff_id)
    {
        // Don't allow email change via this method
        unset($data['email']);
        
        try {
            $CI = &get_instance();
            
            // Verify staff exists
            $CI->db->where('staffid', $staff_id);
            $existing = $CI->db->get(db_prefix() . 'staff')->row();
            
            if (!$existing) {
                return false;
            }
            
            // Update staff record
            $CI->db->where('staffid', $staff_id);
            $result = $CI->db->update(db_prefix() . 'staff', $data);
            
            // Check for database errors
            $db_error = $CI->db->error();
            if (!empty($db_error['message'])) {
                log_activity('Config update ERROR: ' . $db_error['message'], null);
                return false;
            }
            
            return $result;
        } catch (Exception $e) {
            log_activity('Config update ERROR: ' . $e->getMessage(), null);
            return false;
        }
    }

    /**
     * Clients Data.
     *
     */
    public function select_client()
    {
        $this->db->select('*');
        $data = $this->db->get(db_prefix() . 'clients')->result_array();
        return $data;
    }

    /**
     * Leads Data.
     *
     */
    public function select_lead()
    {
        $this->db->select('*');
        $this->db->where("lost", 0);
        $this->db->where("junk", 0);
        $data = $this->db->get(db_prefix() . 'leads')->result_array();
        return $data;
    }

    public function conversation($data) {
        $lead_ids = isset($data['select_lead']) ? $data['select_lead'] : [];
        foreach($lead_ids as $lead_id) {
            $lead_mail = [];
            $lead_mail['outbox_id'] = $data['mailbox_id'];
            $lead_mail['lead_id'] = $lead_id;
            $this->db->select('*');
            $this->db->where("outbox_id", $data['mailbox_id']);
            $this->db->where("lead_id", $lead_id);
            $select_data = $this->db->get(db_prefix() . 'mail_conversation')->result_array();
            if (empty($select_data)) {
               $this->db->insert(db_prefix() . 'mail_conversation', $lead_mail);
               $mail_conversation_id = $this->db->insert_id();
            }
        }

        $this->db->where("outbox_id", $data['mailbox_id']);
        $mail_conversations = $this->db->get(db_prefix() . 'mail_conversation')->result_array();
        foreach ($mail_conversations as $mail_conversation) {
            if (!in_array($mail_conversation['lead_id'], $lead_ids)) {
                $this->db->where('id', $mail_conversation['id']);
                $this->db->delete(db_prefix() . 'mail_conversation');
            }
        }

        return true;
    }

    public function conversation_inbox($data) {
        $lead_ids = isset($data['select_lead']) ? $data['select_lead'] : [];
        foreach($lead_ids as $lead_id) {
            $lead_mail = [];
            $lead_mail['inbox_id'] = $data['mailbox_id'];
            $lead_mail['lead_id'] = $lead_id;
            $this->db->select('*');
            $this->db->where("inbox_id", $data['mailbox_id']);
            $this->db->where("lead_id", $lead_id);
            $select_data = $this->db->get(db_prefix() . 'mail_conversation')->result_array();
            if (empty($select_data)) {
               $this->db->insert(db_prefix() . 'mail_conversation', $lead_mail);
               $mail_conversation_id = $this->db->insert_id();
            } 
        }

        $this->db->where("inbox_id", $data['mailbox_id']);
        $mail_conversations = $this->db->get(db_prefix() . 'mail_conversation')->result_array();
        foreach ($mail_conversations as $mail_conversation) {
            if (!in_array($mail_conversation['lead_id'], $lead_ids)) {
                $this->db->where('id', $mail_conversation['id']);
                $this->db->delete(db_prefix() . 'mail_conversation');
            }
        }

        return true;
    }

    public function delete_mail_conversation($id) {
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'mail_conversation');
        if ($this->db->affected_rows() > 0) {
            return true;
        }
        return false;
    }	
	
    /**
     * Tickets Data.
     *
     */
    public function select_ticket()
    {
        $this->db->select('*');
		$this->db->where("status", 5);
        $data = $this->db->get(db_prefix() . 'tickets')->result_array();

        return $data;
    }
	
    /**
     * Contacts Data.
     *
     */
    public function select_contact()
    {
        $this->db->select(db_prefix() . 'contacts.id, CONCAT(' . db_prefix() . 'contacts.firstname, " ", ' . db_prefix() . 'contacts.lastname) as name');
        $data = $this->db->get(db_prefix() . 'contacts')->result_array();

        return $data;
    }
	
    /**
     * Check Mailbox.
     *
     */
    public function check_mailbox($id, $type)
    {
        $this->db->where("id", $id);
        $mail = $this->db->get(db_prefix() . 'mail_'.$type)->row();
        if ($mail->ticketid) {
            $this->db->where("ticketid", $mail->ticketid);
            $ticket = $this->db->get(db_prefix() . 'tickets')->row();
            if (!$ticket) {
                $this->db->where("id", $id);
                $this->db->update(db_prefix() . 'mail_'.$type, [
                    'ticketid' => null
                ]);
            }
        }
        if ($mail->taskid) {
            $this->db->where("id", $mail->taskid);
            $task = $this->db->get(db_prefix() . 'tasks')->row();
            if (!$task) {
                $this->db->where("id", $id);
                $this->db->update(db_prefix() . 'mail_'.$type, [
                    'taskid' => null
                ]);
            }
        }
    }
	
    /**
     * Staffs Data.
     *
     */
    public function select_staff()
    {
        $this->db->select(db_prefix() . 'staff.staffid, CONCAT(' . db_prefix() . 'staff.firstname, " ", ' . db_prefix() . 'staff.lastname) as name');
        $data = $this->db->get(db_prefix() . 'staff')->result_array();

        return $data;
    }

	public function conversationTicket($data) {
        $this->db->where("id", $data['mailbox_id']);
        $mail = $this->db->get(db_prefix() . 'mail_'.$data['type'])->row();
        
        if ($mail) {
            $ticket_mail = [];
            $ticket_mail['subject'] = $mail->subject; // Use the passed $mailsubject variable
            $ticket_mail['message'] = $mail->body;
            $ticket_mail['status'] = 1;
            $ticket_mail['department'] = 1;
            $ticket_mail['priority'] = 2;
            $this->db->select('id');
            $this->db->where('id', $data['select_customer']);
            $thecontactid = $this->db->get(db_prefix() . 'contacts')->row();
            $ticket_mail['contactid'] = $thecontactid->id;
            $ticket_mail['userid'] = $data['select_customer'];
            $ticket_mail['date'] = date("Y-m-d H:i:s");
            $ticket_mail['assigned'] = 1;
            $this->db->insert(db_prefix() . 'tickets', $ticket_mail);
            $ticket_id = $this->db->insert_id();
    
            $this->db->where("id", $data['mailbox_id']);
            $this->db->update(db_prefix() . 'mail_'.$data['type'], [
                'ticketid' => $ticket_id
            ]);

            return true;
        }

		return false;
	}

    public function search_contacts($search = '', $where = [])
	{
        $this->db->select('contacts.id,contacts.userid,contacts.is_primary,contacts.firstname,contacts.lastname,contacts.email,clients.company');
        $this->db->join(db_prefix() . 'clients', '' . db_prefix() . 'clients.userid = ' . db_prefix() . 'contacts.userid', 'left');
        $this->db->join(db_prefix() . 'countries', '' . db_prefix() . 'countries.country_id = ' . db_prefix() . 'clients.country', 'left');

        if ((is_array($where) && count($where) > 0) || (is_string($where) && $where != ''))
        {
            $this->db->where($where);
        }
        if ($search)
        {
            $this->db->where("LOWER(firstname) LIKE '%" . strtolower($search) . "%'");
            $this->db->or_where("LOWER(lastname) LIKE '%" . strtolower($search) . "%'");
            $this->db->or_where("LOWER(email) LIKE '%" . strtolower($search) . "%'");
        }
        
        return $this->db->get(db_prefix() . 'contacts')->result_array();
    }

    /**
     * Get mailbox tags
     * @param  mixed $tag_id
     * @param  array $where       perform where query
     * @param  array $whereIn     perform whereIn query
     * @return array
     */
    public function get_tags($tag_id = '', $where = ['active' => 1], $whereIn = [])
    {
        $this->db->where($where);
        if ($tag_id != '') {
            $this->db->where('id', $tag_id);
        }
        foreach ($whereIn as $key => $values) {
            if (is_string($key) && is_array($values)) {
                $this->db->where_in($key, $values);
            }
        }
        return $this->db->get(db_prefix() . 'mail_tags')->result_array();
    }

    /**
     * Get single mailbox tag
     * @param  mixed $id tag id
     * @return object
     */
    public function get_tag($id = '')
    {
        $this->db->where('id', $id);
        return $this->db->get(db_prefix() . 'mail_tags')->row();
    }

    /**
     * Add new mailbox tag
     * @param array  $data               $_POST data
     */
    public function add_tag($data)
    {
        if (isset($data['custom_fields'])) {
            $custom_fields           = $data['custom_fields'];
            unset($data['custom_fields']);
        }
        $data['name']                       = trim($data['name']);
        $data                               = hooks()->apply_filters('before_create_mailbox_tag', $data);
        $this->db->insert(db_prefix() . 'mail_tags', $data);
        $tag_id = $this->db->insert_id();
        if ($tag_id) {
            if (isset($custom_fields)) {
                handle_custom_fields_post($tag_id, $custom_fields);
            }
            log_activity('Mailbox Tag Created [ID: ' . $tag_id . ']');
            hooks()->do_action('mailbox_tag_created', $tag_id);
            return $tag_id;
        }
        return false;
    }

    /**
     * Update mailbox tag data
     * @param  array  $data           $_POST data
     * @param  mixed  $id             tag id
     * @return mixed
     */
    public function update_tag($data, $id)
    {
        $affectedRows   = 0;
        $tag            = $this->get_tag($id);
        if (isset($data['custom_fields'])) {
            $custom_fields = $data['custom_fields'];
            if (handle_custom_fields_post($id, $custom_fields)) {
                $affectedRows++;
            }
            unset($data['custom_fields']);
        }
        $data = hooks()->apply_filters('before_update_mailbox_tag', $data, $id);
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'mail_tags', $data);
        if ($this->db->affected_rows() > 0) {
            $affectedRows++;
        }
        if ($affectedRows > 0) {
            hooks()->do_action('mailbox_tag_updated', $id, $data);
        }
        if ($affectedRows > 0) {
            log_activity('Mailbox Tag Updated [ID: ' . $id . ']');
            return true;
        }
        return false;
    }

    /**
     * Change mailbox tag status
     * @param  mixed $id        tag id
     * @param  mixed $status    tag status
     * @return object
     */
    public function change_tag_status($id, $status)
    {
        $status = hooks()->apply_filters('change_tag_status', $status, $id);
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'mail_tags', ['active' => $status, ]);
        if ($this->db->affected_rows() > 0) {
            hooks()->do_action('mailbox_tag_status_changed', ['id' => $id, 'status' => $status, ]);
            log_activity('Mailbox Tag Status Changed [TagID: ' . $id . ' Status(Active/Inactive): ' . $status . ']');
            return true;
        }
        return false;
    }

    /**
     * Update mailbox tag (supports both single and multiple tags)
     * @param  mixed $id        mail id
     * @param  mixed $tag_id    tag id (single) or array of tag ids (multiple)
     * @param  mixed $type      type
     * @return object
     */
    public function update_mail_tag($id, $tag_id, $type = 'outbox')
    {
        // Check if mail_email_tags table exists (multiple labels support)
        // If it doesn't exist, create it automatically (for existing installations)
        if (!$this->db->table_exists(db_prefix() . 'mail_email_tags')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS `' . db_prefix() . 'mail_email_tags` (
                `id` INT PRIMARY KEY AUTO_INCREMENT,
                `email_id` INT NOT NULL,
                `tag_id` INT NOT NULL,
                `type` VARCHAR(20) NOT NULL DEFAULT "inbox",
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `email_tag_type` (`email_id`, `tag_id`, `type`),
                INDEX `idx_email_id` (`email_id`),
                INDEX `idx_tag_id` (`tag_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $this->db->char_set);
        }
        
        $use_multiple_tags = $this->db->table_exists(db_prefix() . 'mail_email_tags');
        
        log_activity('update_mail_tag called [ID: ' . $id . ' Type: ' . $type . ' TagID: ' . (is_array($tag_id) ? implode(',', $tag_id) : $tag_id) . ' UseMultipleTags: ' . ($use_multiple_tags ? 'YES' : 'NO') . ']');
        
        if ($use_multiple_tags) {
            // Multiple labels: use mail_email_tags table
            // Remove existing tags for this email
            $this->db->where('email_id', (int)$id);
            $this->db->where('type', $type);
            $deleted = $this->db->delete(db_prefix() . 'mail_email_tags');
            
            // Reset query builder after delete
            $this->db->reset_query();
            
            // Add new tags using batch insert for better reliability
            if (!empty($tag_id)) {
                $tag_ids = is_array($tag_id) ? $tag_id : [$tag_id];
                $insert_data = [];
                foreach ($tag_ids as $tid) {
                    if (!empty($tid)) {
                        $insert_data[] = [
                            'email_id' => (int)$id,
                            'tag_id' => (int)$tid,
                            'type' => $type
                        ];
                    }
                }
                if (!empty($insert_data)) {
                    $this->db->insert_batch(db_prefix() . 'mail_email_tags', $insert_data);
                    $inserted_count = count($insert_data);
                }
            }
            
            // Reset query builder before updating legacy tagid
            $this->db->reset_query();
            
            // Also update legacy tagid for backward compatibility (use first tag)
            $first_tag_id = is_array($tag_id) && !empty($tag_id) ? $tag_id[0] : (is_array($tag_id) ? null : $tag_id);
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'mail_' . $type, ['tagid' => $first_tag_id]);
            
            // Verify tags were actually saved - reset query builder first to ensure clean query
            $this->db->reset_query();
            $this->db->where('email_id', (int)$id);
            $this->db->where('type', $type);
            $saved_tags = $this->db->get(db_prefix() . 'mail_email_tags')->result_array();
            $saved_tag_ids = array_column($saved_tags, 'tag_id');
            
            // Also verify by direct SQL query as fallback
            $direct_query = $this->db->query("SELECT tag_id FROM " . db_prefix() . "mail_email_tags WHERE email_id = " . (int)$id . " AND type = " . $this->db->escape($type));
            $direct_tag_ids = [];
            if ($direct_query && $direct_query->num_rows() > 0) {
                foreach ($direct_query->result_array() as $row) {
                    $direct_tag_ids[] = $row['tag_id'];
                }
            }
            
            log_activity('Email Tags Updated [ID: ' . $id . ' Type: ' . $type . ' Requested: ' . (is_array($tag_id) ? implode(',', $tag_id) : $tag_id) . ' Saved: ' . implode(',', $saved_tag_ids) . ']');
            
            return true;
        } else {
            // Single label: use legacy tagid column
            // Handle both array and scalar values (controller may pass array for consistency)
            $single_tag_id = is_array($tag_id) ? (!empty($tag_id) ? $tag_id[0] : null) : $tag_id;
            
            if ($single_tag_id) {
                $this->db->where('id', $single_tag_id);
                $email_tag = $this->db->get(db_prefix() . 'mail_tags')->row();
            } else {
                $single_tag_id = null;
            }
            $this->db->where('id', $id);
            $email = $this->db->get(db_prefix() . 'mail_' . $type)->row();
            
            // Check if email exists before updating
            if (!$email) {
                log_activity('Email Tag Update Failed - Email not found [ID: ' . $id . ' Type: ' . $type . ' Table: ' . db_prefix() . 'mail_' . $type . ']');
                return false;
            }
            
            // Validate tag exists if tag_id is provided
            if ($single_tag_id && !isset($email_tag)) {
                $this->db->where('id', $single_tag_id);
                $email_tag = $this->db->get(db_prefix() . 'mail_tags')->row();
                if (!$email_tag) {
                    log_activity('Email Tag Update Failed - Tag not found [TagID: ' . $single_tag_id . ']');
                    return false;
                }
            }
            
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'mail_' . $type, ['tagid' => $single_tag_id]);
            $affectedRows = 0;
            if ($this->db->affected_rows() > 0) {
                $affectedRows++;
                hooks()->do_action('mailbox_tag_changed', ['id' => $email->id, 'tagid' => $single_tag_id]);
                log_activity('Email Tag Changed [ID: ' . $email->id . (isset($email_tag) && $email_tag ? ' Tag: ' . $email_tag->name : ' Removed' ) . ']');
            } else {
                // No rows affected - tagid might already be set to this value, but that's still success
                // Only log if we're trying to set a tag (not remove it)
                if ($single_tag_id !== null) {
                    log_activity('Email Tag Update - No change needed [ID: ' . $email->id . ' TagID: ' . $single_tag_id . ']');
                }
                // Return true even if no rows affected (tag already set correctly)
                return true;
            }

            return $affectedRows ? true : false;
        }
    }

    /**
     * Get all tags for an email (multiple labels support)
     * @param  mixed $id        mail id
     * @param  mixed $type      type
     * @return array
     */
    public function get_email_tags($id, $type = 'inbox')
    {
        // Check if mail_email_tags table exists
        if ($this->db->table_exists(db_prefix() . 'mail_email_tags')) {
            // Reset query builder to ensure clean query
            $this->db->reset_query();
            $this->db->select(db_prefix() . 'mail_tags.*');
            $this->db->from(db_prefix() . 'mail_email_tags');
            $this->db->join(db_prefix() . 'mail_tags', db_prefix() . 'mail_tags.id = ' . db_prefix() . 'mail_email_tags.tag_id');
            $this->db->where(db_prefix() . 'mail_email_tags.email_id', (int)$id);
            $this->db->where(db_prefix() . 'mail_email_tags.type', $type);
            $result = $this->db->get()->result_array();
            
            // Debug: Log what we're loading
            // Tags loaded successfully
            
            return $result;
        } else {
            // Fallback to single tag
            $this->db->select(db_prefix() . 'mail_tags.*');
            $this->db->from(db_prefix() . 'mail_' . $type);
            $this->db->join(db_prefix() . 'mail_tags', db_prefix() . 'mail_tags.id = ' . db_prefix() . 'mail_' . $type . '.tagid', 'left');
            $this->db->where(db_prefix() . 'mail_' . $type . '.id', $id);
            $result = $this->db->get()->row_array();
            return $result && !empty($result['id']) ? [$result] : [];
        }
    }

    /**
     * Delete mailbox tag
     * @param  mixed $id        tag id
     * @return object
     */
    public function delete_tag($id)
    {
        $affectedRows = 0;
        hooks()->do_action('before_mailbox_tag_deleted', $id);
        $last_activity = get_last_system_activity_id();
        if ($id) {
            $this->db->where('id', $id);
        }
        $this->db->delete(db_prefix() . 'mail_tags');
        if ($this->db->affected_rows() > 0) {
            $affectedRows++;
        }
        if ($affectedRows > 0) {
            hooks()->do_action('after_mailbox_tag_deleted', $id);
            // Delete activity log caused by delete customer function
            if ($last_activity) {
                $this->db->where('id >', $last_activity->id);
                $this->db->delete(db_prefix() . 'activity_log');
            }
            log_activity('Mailbox Tag Deleted [ID: ' . $id . ']');
            return true;
        }
        return false;
    }

    /**
     * Update Email Template
     * @param array  $data      $_POST data
     * @param  mixed $id        email template id
     * @return object
     */
    public function update_email_template($data, $id)
    {
        $this->db->where('emailtemplateid', $id);
        $email_template = $this->db->get(db_prefix() . 'emailtemplates')->row();

        $affectedRows = 0;
        $this->db->where('emailtemplateid', $id);
        $data['fromname'] = '';
        $data['fromemail'] = '';
        $this->db->update(db_prefix() . 'emailtemplates', $data);
        if ($this->db->affected_rows() > 0) {
            $affectedRows++;
            if ($affectedRows > 0) {
                $last_activity = get_last_system_activity_id();
                hooks()->do_action('after_mailbox_email_template_updated', $id);
                // Delete activity log caused by delete customer function
                if ($last_activity) {
                    $this->db->where('id >', $last_activity->id);
                    $this->db->delete(db_prefix() . 'activity_log');
                }
                log_activity('Email Template Updated [' . $email_template->name . ']');
            }
        }

        return true;
    }

    /**
     * Update Email Template Status
     * @param  mixed $id        email template id
     * @param  mixed $status    email template status
     * @return object
     */
    public function update_email_template_status($id, $status)
    {
        $this->db->where('emailtemplateid', $id);
        $email_template = $this->db->get(db_prefix() . 'emailtemplates')->row();

        $this->db->where('slug', $email_template->slug);
        $email_templates = $this->db->get(db_prefix() . 'emailtemplates')->result_array();

        $affectedRows = 0;
        foreach ($email_templates as $email_template) {
            $this->db->where('emailtemplateid', $email_template['emailtemplateid']);
            $this->db->update(db_prefix() . 'emailtemplates', ['active' => $status, ]);
            if ($this->db->affected_rows() > 0) {
                $affectedRows++;
                hooks()->do_action('mailbox_email_template_status_changed', ['id' => $email_template['emailtemplateid'], 'status' => $status, ]);
                log_activity('Email Template Status Changed [TagID: ' . $email_template['emailtemplateid'] . ' Status(Active/Inactive): ' . $status . ']');
            }
        }

        return $affectedRows ? true : false;
    }

    /**
     * Update mailbox template
     * @param  mixed $id        mail id
     * @param  mixed $tag_id    template id
     * @param  mixed $type      type
     * @return object
     */
    public function update_mail_template($id, $template_id, $type = 'outbox')
    {
        if ($template_id) {
            $this->db->where('emailtemplateid', $template_id);
            $email_template = $this->db->get(db_prefix() . 'emailtemplates')->row();
        } else {
            $template_id = null;
        }
        $this->db->where('id', $id);
        $email = $this->db->get(db_prefix() . 'mail_' . $type)->row();
        
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'mail_' . $type, ['templateid' => $template_id, ]);
        $affectedRows = 0;
        if ($this->db->affected_rows() > 0) {
            $affectedRows++;
            hooks()->do_action('mailbox_template_changed', ['id' => $email->id, 'templateid' => $template_id, ]);
            log_activity('Email Template Changed [ID: ' . $email->id . (isset($email_template) && $email_template ? ' Template: ' . $email_template->name : '' ) . ']');
        }

        return $affectedRows ? true : false;
    }

    /**
     * Delete Email Template
     * @param  mixed $id        email template id
     * @return object
     */
    public function delete_email_templates($id)
    {
        $this->db->where('emailtemplateid', $id);
        $email_template = $this->db->get(db_prefix() . 'emailtemplates')->row();

        $this->db->where('slug', $email_template->slug);
        $email_templates = $this->db->get(db_prefix() . 'emailtemplates')->result_array();

        $affectedRows = 0;
        foreach ($email_templates as $email_template) {
            hooks()->do_action('before_mailbox_email_template_deleted', $email_template['emailtemplateid']);
            $last_activity = get_last_system_activity_id();
            $this->db->where('emailtemplateid', $email_template['emailtemplateid']);
            $this->db->delete(db_prefix() . 'emailtemplates');
            if ($this->db->affected_rows() > 0) {
                $affectedRows++;
            }
            if ($affectedRows > 0) {
                hooks()->do_action('after_mailbox_email_template_deleted', $email_template['emailtemplateid']);
                // Delete activity log caused by delete customer function
                if ($last_activity) {
                    $this->db->where('id >', $last_activity->id);
                    $this->db->delete(db_prefix() . 'activity_log');
                }
                log_activity('Email Template Deleted [ID: ' . $email_template['emailtemplateid'] . ']');
            }
        }

        return $affectedRows ? true : false;
    }

    /**
     * Get auto replies
     * @param  mixed $tag_id
     * @param  array $where       perform where query
     * @param  array $whereIn     perform whereIn query
     * @return array
     */
    public function get_auto_replies($tag_id = '', $where = ['active' => 1], $whereIn = [])
    {
        $this->db->where($where);
        if ($tag_id != '') {
            $this->db->where('id', $tag_id);
        }
        foreach ($whereIn as $key => $values) {
            if (is_string($key) && is_array($values)) {
                $this->db->where_in($key, $values);
            }
        }
        return $this->db->get(db_prefix() . 'mail_auto_replies')->result_array();
    }

    /**
     * Get single mailbox auto reply
     * @param  mixed $id auto reply id
     * @return object
     */
    public function get_auto_reply($id = '')
    {
        $this->db->where('id', $id);
        return $this->db->get(db_prefix() . 'mail_auto_replies')->row();
    }

    /**
     * Add new mailbox auto reply
     * @param array  $data               $_POST data
     */
    public function add_auto_reply($data)
    {
        if (isset($data['custom_fields'])) {
            $custom_fields           = $data['custom_fields'];
            unset($data['custom_fields']);
        }
        $data['name']                       = trim($data['name']);
        $data['body']                       = nl2br_save_html($data['body']);
        $data                               = hooks()->apply_filters('before_create_mailbox_auto_reply', $data);
        $this->db->insert(db_prefix() . 'mail_auto_replies', $data);
        $auto_reply_id = $this->db->insert_id();
        if ($auto_reply_id) {
            if (isset($custom_fields)) {
                handle_custom_fields_post($auto_reply_id, $custom_fields);
            }
            log_activity('Mailbox Auto Reply Created [ID: ' . $auto_reply_id . ']');
            hooks()->do_action('mailbox_auto_reply_created', $auto_reply_id);
            return $auto_reply_id;
        }
        return false;
    }

    /**
     * Update mailbox auto reply data
     * @param  array  $data           $_POST data
     * @param  mixed  $id             auto reply id
     * @return mixed
     */
    public function update_auto_reply($data, $id)
    {
        $data['body']                 = nl2br_save_html($data['body']);
        $affectedRows   = 0;
        $auto_reply     = $this->get_auto_reply($id);
        if (isset($data['custom_fields'])) {
            $custom_fields = $data['custom_fields'];
            if (handle_custom_fields_post($id, $custom_fields)) {
                $affectedRows++;
            }
            unset($data['custom_fields']);
        }
        $data = hooks()->apply_filters('before_update_mailbox_auto_reply', $data, $id);
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'mail_auto_replies', $data);
        if ($this->db->affected_rows() > 0) {
            $affectedRows++;
        }
        if ($affectedRows > 0) {
            hooks()->do_action('mailbox_auto_reply_updated', $id, $data);
        }
        if ($affectedRows > 0) {
            log_activity('Mailbox Auto Reply Updated [ID: ' . $id . ']');
            return true;
        }
        return false;
    }

    /**
     * Change mailbox auto reply status
     * @param  mixed $id        auto reply id
     * @param  mixed $status    auto reply status
     * @return object
     */
    public function change_auto_reply_status($id, $status)
    {
        $status = hooks()->apply_filters('change_auto_reply_status', $status, $id);
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'mail_auto_replies', ['active' => $status, ]);
        if ($this->db->affected_rows() > 0) {
            hooks()->do_action('mailbox_auto_reply_status_changed', ['id' => $id, 'status' => $status, ]);
            log_activity('Mailbox Auto Reply Status Changed [ID: ' . $id . ' Status(Active/Inactive): ' . $status . ']');
            return true;
        }
        return false;
    }

    /**
     * Delete Auto Reply
     * @param  mixed $id        auto reply id
     * @return object
     */
    public function delete_auto_reply($id)
    {
        hooks()->do_action('before_mailbox_auto_reply_deleted', $id);
        $last_activity = get_last_system_activity_id();

        $affectedRows = 0;
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'mail_auto_replies');
        if ($this->db->affected_rows() > 0) {
            $affectedRows++;
        }
        if ($affectedRows > 0) {
            hooks()->do_action('after_mailbox_auto_reply_deleted', $id);
            // Delete activity log caused by delete customer function
            if ($last_activity) {
                $this->db->where('id >', $last_activity->id);
                $this->db->delete(db_prefix() . 'activity_log');
            }
            log_activity('Email Auto Reply Deleted [ID: ' . $id . ']');
        }

        return $affectedRows ? true : false;
    }

    public function assign_customers($data) {
        foreach($data['select_customers'] as $value) {
            $customer_mail = [];
            $customer_mail[$data['type'] . '_id'] = $data['mailbox_id'];
            $customer_mail['client_id'] = $value;
            $this->db->select('*');
            $this->db->where($data['type'] . "_id", $data['mailbox_id']);
            $this->db->where("client_id", $value);
            $select_data = $this->db->get(db_prefix() . 'mail_clients')->result_array();
            if (empty($select_data)) {
               $this->db->insert(db_prefix() . 'mail_clients', $customer_mail);
               $mail_customer_id = $this->db->insert_id();
            }
        }

        $this->db->where($data['type'] . "_id", $data['mailbox_id']);
        $mail_clients = $this->db->get(db_prefix() . 'mail_clients')->result_array();
        foreach ($mail_clients as $mail_client) {
            if (!in_array($mail_client['client_id'], $data['select_customers'])) {
                $this->db->where('id', $mail_client['id']);
                $this->db->delete(db_prefix() . 'mail_clients');
            }
        }

        $client_companies = mailbox_get_client_companies($data['mailbox_id'], $data['type']);
        $this->db->where('id', $data['mailbox_id']);
        $this->db->update(db_prefix() . 'mail_' . $data['type'], [
            'assigned_clients' => $client_companies,
        ]);
        if ($this->db->affected_rows() > 0) {
            hooks()->do_action('mailbox_assigned_customers', ['id' => $data['mailbox_id'], 'clients' => $client_companies, ]);
            log_activity('Mailbox Assigned Customers [ID: ' . $data['mailbox_id'] . ' Client Companies: ' . $client_companies . ']');
        }
        return true;
    }

    public function unassign_customers($data) {
        $this->db->where($data['type'] . '_id', $data['mail_id']);
        $this->db->where('client_id', $data['client_id']);
        $this->db->delete(db_prefix() . 'mail_clients');

        $client_companies = mailbox_get_client_companies($data['mail_id'], $data['type']);
        $this->db->where('id', $data['mail_id']);
        $this->db->update(db_prefix() . 'mail_' . $data['type'], [
            'assigned_clients' => $client_companies,
        ]);
        if ($this->db->affected_rows() > 0) {
            hooks()->do_action('mailbox_unassigned_customers', ['id' => $data['mail_id'], 'clients' => $client_companies, ]);
            log_activity('Mailbox UnAssigned Customers [ID: ' . $data['mail_id'] . ' Client Companies: ' . $client_companies . ']');
        }

        return true;
    }

    /**
     * Get all rules for a staff member
     */
    public function get_rules($staff_id)
    {
        if (!$this->db->table_exists(db_prefix() . 'mail_rules')) {
            return [];
        }
        $this->db->where('staff_id', $staff_id);
        $this->db->order_by('order', 'ASC');
        return $this->db->get(db_prefix() . 'mail_rules')->result_array();
    }

    /**
     * Get single rule
     */
    public function get_rule($id, $staff_id)
    {
        if (!$this->db->table_exists(db_prefix() . 'mail_rules')) {
            return null;
        }
        $this->db->where('id', $id);
        $this->db->where('staff_id', $staff_id);
        return $this->db->get(db_prefix() . 'mail_rules')->row_array();
    }

    /**
     * Add new rule
     */
    public function add_rule($data)
    {
        if (!$this->db->table_exists(db_prefix() . 'mail_rules')) {
            return false;
        }
        $this->db->insert(db_prefix() . 'mail_rules', $data);
        $rule_id = $this->db->insert_id();
        if ($rule_id) {
            log_activity('Mailbox Rule Created [ID: ' . $rule_id . ']');
            return $rule_id;
        }
        return false;
    }

    /**
     * Update rule
     */
    public function update_rule($id, $data, $staff_id)
    {
        if (!$this->db->table_exists(db_prefix() . 'mail_rules')) {
            return false;
        }
        $this->db->where('id', $id);
        $this->db->where('staff_id', $staff_id);
        $this->db->update(db_prefix() . 'mail_rules', $data);
        if ($this->db->affected_rows() > 0) {
            log_activity('Mailbox Rule Updated [ID: ' . $id . ']');
            return true;
        }
        return false;
    }

    /**
     * Delete rule
     */
    public function delete_rule($id, $staff_id)
    {
        if (!$this->db->table_exists(db_prefix() . 'mail_rules')) {
            return false;
        }
        $this->db->where('id', $id);
        $this->db->where('staff_id', $staff_id);
        $this->db->delete(db_prefix() . 'mail_rules');
        if ($this->db->affected_rows() > 0) {
            log_activity('Mailbox Rule Deleted [ID: ' . $id . ']');
            return true;
        }
        return false;
    }

    /**
     * Get enabled rules for a staff member (ordered by priority)
     */
    public function get_enabled_rules($staff_id)
    {
        if (!$this->db->table_exists(db_prefix() . 'mail_rules')) {
            return [];
        }
        $this->db->where('staff_id', $staff_id);
        $this->db->where('enabled', 1);
        $this->db->order_by('order', 'ASC');
        return $this->db->get(db_prefix() . 'mail_rules')->result_array();
    }

    /**
     * Send email via OAuth (Gmail or Outlook)
     *
     * @param array $staff Staff data with OAuth credentials
     * @param array $inbox Email data
     * @param array $data POST data
     * @param array $outbox Outbox data
     * @param array $attachments Attachments
     * @param int $outbox_id Outbox ID
     * @param bool $threading_enabled Threading enabled flag
     * @return bool Success status
     */
    private function send_email_via_oauth($staff, $inbox, $data, $outbox, $attachments, $outbox_id, $threading_enabled)
    {
        $provider = strtolower($staff['oauth_provider']);
        
        // Get and refresh access token if needed
        log_activity('Attempting to get OAuth access token for provider: ' . $provider, null);
        $accessToken = $this->getValidOAuthToken($staff);
        
        if (empty($accessToken)) {
            log_activity('OAuth access token is empty after getValidOAuthToken()', null);
            throw new Exception('Failed to get valid OAuth access token');
        }
        
        log_activity('OAuth access token retrieved successfully (length: ' . strlen($accessToken) . ')', null);
        
        // Build email message in RFC 2822 format
        $boundary = uniqid('boundary');
        $message = '';
        
        // Build headers
        $headers = [];
        $headers[] = 'From: ' . $inbox['sender_name'] . ' <' . $inbox['from_email'] . '>';
        $headers[] = 'To: ' . $data['to'];
        
        if (!empty($data['cc'])) {
            $headers[] = 'Cc: ' . $data['cc'];
        }
        
        $headers[] = 'Subject: ' . $inbox['subject'];
        $headers[] = 'MIME-Version: 1.0';
        
        // Add threading headers if enabled
        if ($threading_enabled && isset($outbox['message_id'])) {
            $headers[] = 'Message-ID: ' . $outbox['message_id'];
            if (isset($outbox['in_reply_to']) && !empty($outbox['in_reply_to'])) {
                $headers[] = 'In-Reply-To: ' . $outbox['in_reply_to'];
            }
            if (isset($outbox['references']) && !empty($outbox['references'])) {
                $headers[] = 'References: ' . $outbox['references'];
            }
        }
        
        // Check if we have attachments
        $hasAttachments = isset($attachments) && $attachments && count($attachments) > 0;
        
        if ($hasAttachments) {
            $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
        } else {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }
        
        // Build message body
        if ($hasAttachments) {
            $message .= '--' . $boundary . "\r\n";
            $message .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
            $message .= 'Content-Transfer-Encoding: quoted-printable' . "\r\n\r\n";
            $message .= quoted_printable_encode($data['body']) . "\r\n";
            
            // Add attachments
            foreach ($attachments as $attachment) {
                $attachment_path = FCPATH . 'modules/mailbox/uploads/outbox/' . $outbox_id . '/' . $attachment['file_name'];
                if (file_exists($attachment_path)) {
                    $file_content = file_get_contents($attachment_path);
                    $encoded_content = base64_encode($file_content);
                    
                    $message .= '--' . $boundary . "\r\n";
                    $message .= 'Content-Type: ' . ($attachment['file_type'] ?: 'application/octet-stream') . '; name="' . $attachment['file_name'] . '"' . "\r\n";
                    $message .= 'Content-Transfer-Encoding: base64' . "\r\n";
                    $message .= 'Content-Disposition: attachment; filename="' . $attachment['file_name'] . '"' . "\r\n\r\n";
                    $message .= chunk_split($encoded_content) . "\r\n";
                }
            }
            
            $message .= '--' . $boundary . '--';
        } else {
            $message = quoted_printable_encode($data['body']);
        }
        
        // Combine headers and message
        $rawMessage = implode("\r\n", $headers) . "\r\n\r\n" . $message;
        
        // Encode the message in base64url format
        $encodedMessage = rtrim(strtr(base64_encode($rawMessage), '+/', '-_'), '=');
        
        // Send via appropriate provider
        if ($provider === 'gmail') {
            return $this->send_via_gmail($encodedMessage, $accessToken);
        } elseif ($provider === 'outlook') {
            return $this->send_via_outlook($inbox, $data, $attachments, $outbox_id, $accessToken, $threading_enabled, $outbox);
        }
        
        return false;
    }
    
    /**
     * Send email via Gmail API
     */
    private function send_via_gmail($encodedMessage, $accessToken)
    {
        log_activity('Sending email via Gmail API (token length: ' . strlen($accessToken) . ', message length: ' . strlen($encodedMessage) . ')', null);
        
        $ch = curl_init('https://gmail.googleapis.com/gmail/v1/users/me/messages/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['raw' => $encodedMessage]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        
        $responseRaw = curl_exec($ch);
        $response = json_decode($responseRaw, true);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        log_activity('Gmail API response - HTTP ' . $httpCode . ': ' . substr($responseRaw, 0, 200), null);
        
        if ($httpCode === 200 && isset($response['id'])) {
            log_activity('Email sent successfully via Gmail API - Message ID: ' . $response['id'], null);
            return true;
        }
        
        $errorMsg = 'Gmail API error: ' . ($response['error']['message'] ?? $responseRaw ?? 'Unknown error');
        log_activity($errorMsg, null);
        throw new Exception($errorMsg);
    }
    
    /**
     * Send email via Outlook/Microsoft Graph API
     */
    private function send_via_outlook($inbox, $data, $attachments, $outbox_id, $accessToken, $threading_enabled, $outbox)
    {
        // Build message body for Outlook
        $message = [
            'message' => [
                'subject' => $inbox['subject'],
                'body' => [
                    'contentType' => 'HTML',
                    'content' => $data['body']
                ],
                'from' => [
                    'emailAddress' => [
                        'address' => $inbox['from_email'],
                        'name' => $inbox['sender_name']
                    ]
                ],
                'toRecipients' => []
            ]
        ];
        
        // Add recipients
        $recipients = explode(',', str_replace(';', ',', $data['to']));
        foreach ($recipients as $recipient) {
            $recipient = trim($recipient);
            if (!empty($recipient)) {
                $message['message']['toRecipients'][] = [
                    'emailAddress' => ['address' => $recipient]
                ];
            }
        }
        
        // Add CC recipients
        if (!empty($data['cc'])) {
            $message['message']['ccRecipients'] = [];
            $ccRecipients = explode(',', str_replace(';', ',', $data['cc']));
            foreach ($ccRecipients as $recipient) {
                $recipient = trim($recipient);
                if (!empty($recipient)) {
                    $message['message']['ccRecipients'][] = [
                        'emailAddress' => ['address' => $recipient]
                    ];
                }
            }
        }
        
        // Add threading headers
        if ($threading_enabled && !empty($outbox['in_reply_to'])) {
            $message['message']['internetMessageHeaders'] = [];
            if (!empty($outbox['in_reply_to'])) {
                $message['message']['internetMessageHeaders'][] = [
                    'name' => 'In-Reply-To',
                    'value' => $outbox['in_reply_to']
                ];
            }
            if (!empty($outbox['references'])) {
                $message['message']['internetMessageHeaders'][] = [
                    'name' => 'References',
                    'value' => $outbox['references']
                ];
            }
        }
        
        // Add attachments
        if (isset($attachments) && $attachments && count($attachments) > 0) {
            $message['message']['attachments'] = [];
            foreach ($attachments as $attachment) {
                $attachment_path = FCPATH . 'modules/mailbox/uploads/outbox/' . $outbox_id . '/' . $attachment['file_name'];
                if (file_exists($attachment_path)) {
                    $file_content = file_get_contents($attachment_path);
                    $message['message']['attachments'][] = [
                        '@odata.type' => '#microsoft.graph.fileAttachment',
                        'name' => $attachment['file_name'],
                        'contentType' => $attachment['file_type'] ?: 'application/octet-stream',
                        'contentBytes' => base64_encode($file_content)
                    ];
                }
            }
        }
        
        log_activity('Sending email via Outlook/Microsoft Graph API (token length: ' . strlen($accessToken) . ')', null);
        
        $ch = curl_init('https://graph.microsoft.com/v1.0/me/sendMail');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        log_activity('Outlook API response - HTTP ' . $httpCode . ': ' . substr($response, 0, 200), null);
        
        if ($httpCode === 202 || $httpCode === 200) {
            log_activity('Email sent successfully via Outlook/Microsoft Graph API', null);
            return true;
        }
        
        $error = json_decode($response, true);
        $errorMsg = 'Outlook API error: ' . ($error['error']['message'] ?? $response ?? 'Unknown error');
        log_activity($errorMsg, null);
        throw new Exception($errorMsg);
    }
    
    /**
     * Get valid OAuth access token, refresh if expired
     */
    private function getValidOAuthToken($staff)
    {
        // Check if token is still valid (with 5 minute buffer)
        if (isset($staff['oauth_token_expires_at']) && !empty($staff['oauth_token_expires_at'])) {
            $expiresAt = strtotime($staff['oauth_token_expires_at']);
            if ($expiresAt > (time() + 300)) {
                // Return decrypted token
                return decrypt_mail_password($staff['oauth_access_token']);
            }
        }
        
        // Token expired or about to expire, refresh it
        $provider = strtolower($staff['oauth_provider']);
        $refreshToken = decrypt_mail_password($staff['oauth_refresh_token']);
        
        if (empty($refreshToken)) {
            throw new Exception('No refresh token available');
        }
        
        // Use per-staff OAuth credentials
        $clientId = !empty($staff['oauth_client_id']) ? decrypt_mail_password($staff['oauth_client_id']) : '';
        $clientSecret = !empty($staff['oauth_client_secret']) ? decrypt_mail_password($staff['oauth_client_secret']) : '';
        
        if (empty($clientId) || empty($clientSecret)) {
            throw new Exception('OAuth credentials not configured for staff member. Please set client_id and client_secret in your mailbox configuration.');
        }
        
        if ($provider === 'gmail') {
            $tokenUrl = 'https://oauth2.googleapis.com/token';
            $postData = [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token'
            ];
        } elseif ($provider === 'outlook') {
            $tokenUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
            $postData = [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
                'scope' => 'https://graph.microsoft.com/Mail.ReadWrite https://graph.microsoft.com/Mail.Send offline_access'
            ];
        } else {
            throw new Exception('Unsupported OAuth provider: ' . $provider);
        }
        
        // Log the request for debugging (without sensitive data)
        log_activity('OAuth token refresh request - Provider: ' . $provider . ', TokenURL: ' . $tokenUrl . ', HasClientId: ' . (!empty($postData['client_id']) ? 'Yes' : 'No') . ', HasRefreshToken: ' . (!empty($refreshToken) ? 'Yes' : 'No'), null);
        
        // Make refresh token request
        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $responseRaw = curl_exec($ch);
        $response = json_decode($responseRaw, true);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Log the response for debugging
        if (!empty($curlError)) {
            log_activity('OAuth cURL error: ' . $curlError, null);
        }
        log_activity('OAuth token refresh response - Provider: ' . $provider . ', HTTP Code: ' . $httpCode . ', Response: ' . substr($responseRaw, 0, 200), null);
        
        if ($httpCode === 200 && isset($response['access_token'])) {
            $newAccessToken = $response['access_token'];
            $expiresIn = $response['expires_in'] ?? 3600;
            $newExpiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
            
            // Encrypt tokens before saving
            $newRefreshToken = isset($response['refresh_token']) ? $response['refresh_token'] : $refreshToken;
            
            // Update the database with new token (encrypted)
            $this->db->where('staffid', $staff['staffid']);
            $this->db->update(db_prefix().'staff', [
                'oauth_access_token' => encrypt_mail_password($newAccessToken),
                'oauth_token_expires_at' => $newExpiresAt,
                'oauth_refresh_token' => encrypt_mail_password($newRefreshToken)
            ]);
            
            log_activity('OAuth token refreshed successfully for staff ' . $staff['staffid'], null);
            // Return the decrypted token for immediate use
            return $newAccessToken;
        }
        
        // Provide user-friendly error messages
        $errorCode = $response['error'] ?? 'unknown';
        $errorDesc = $response['error_description'] ?? $responseRaw ?? 'Unknown error';
        
        if ($errorCode === 'invalid_grant') {
            $errorMsg = 'OAuth refresh token is invalid or expired. Please reconnect your ' . ucfirst($provider) . ' account in your profile settings.';
        } else {
            $errorMsg = 'Failed to refresh OAuth token (HTTP ' . $httpCode . '): ' . $errorDesc;
        }
        
        log_activity($errorMsg, null);
        throw new Exception($errorMsg);
    }
}