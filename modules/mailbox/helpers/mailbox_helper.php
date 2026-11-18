<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Get staff id by email.
 *
 * @param string $email
 *
 * @return string staff id
 */
function get_staff_id_by_email($email)
{
    $CI = &get_instance();

    $staff = $CI->app_object_cache->get('staff-id-by-email-'.$email);

    if (!$staff) {
        $CI->db->where('email', $email);
        $staff = $CI->db->select('staffid')->from(db_prefix().'staff')->get()->row();
        $CI->app_object_cache->add('staff-id-by-email-'.$email, $staff);
    }

    return $staff ? $staff->staffid : 0;
}

/**
 * Format date for mailbox list view (Gmail-style)
 * - Today: Show only time (15:27)
 * - Last 7 days: Show day name (Tue, Wed)
 * - Older: Show short date (19/01, 18/01)
 * - Same year: Don't show year
 * - Different year: Show year (19/01/2025)
 *
 * @param string $datetime MySQL datetime string (2026-01-19 15:27:15)
 * @return string Formatted date
 */
function format_mailbox_date($datetime)
{
    if (empty($datetime) || $datetime == '0000-00-00 00:00:00') {
        return '-';
    }
    
    $date = strtotime($datetime);
    $now = time();
    $diff = $now - $date;
    
    // Today: Show only time (e.g., "15:27")
    if (date('Y-m-d', $date) == date('Y-m-d', $now)) {
        return date('H:i', $date);
    }
    
    // Yesterday: Show "Yesterday"
    $yesterday = strtotime('-1 day', $now);
    if (date('Y-m-d', $date) == date('Y-m-d', $yesterday)) {
        return 'Yesterday';
    }
    
    // Last 7 days: Show day name (e.g., "Mon", "Tue")
    if ($diff < 7 * 24 * 60 * 60) {
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        return $days[date('w', $date)];
    }
    
    // Same year: Show short date without year (e.g., "19/01")
    if (date('Y', $date) == date('Y', $now)) {
        return date('d/m', $date);
    }
    
    // Different year: Show full short date with year (e.g., "19/01/2025")
    return date('d/m/Y', $date);
}

/**
 * Generate thread ID for email threading
 *
 * @param string $messageId
 * @param string $inReplyTo
 * @param string $references
 * @param string $subject
 * @param int $staffId
 * @return string
 */
function generate_thread_id($messageId, $inReplyTo, $references, $subject, $staffId)
{
    $CI = &get_instance();
    
    // If we have in_reply_to or references, use them to find the parent thread
    if (!empty($inReplyTo)) {
        // Find parent email by message_id
        $CI->db->where('message_id', $inReplyTo);
        $CI->db->where('to_staff_id', $staffId);
        $parent = $CI->db->get(db_prefix() . 'mail_inbox')->row();
        if ($parent && !empty($parent->thread_id)) {
            return $parent->thread_id;
        }
    }
    
    // Check references for thread
    if (!empty($references)) {
        $refIds = explode(' ', trim($references));
        foreach ($refIds as $refId) {
            $refId = trim($refId);
            if (empty($refId)) continue;
            
            $CI->db->where('message_id', $refId);
            $CI->db->where('to_staff_id', $staffId);
            $parent = $CI->db->get(db_prefix() . 'mail_inbox')->row();
            if ($parent && !empty($parent->thread_id)) {
                return $parent->thread_id;
            }
        }
    }
    
    // If this is a reply, try to find thread by normalized subject
    $normalized_subject = normalize_email_subject($subject);
    if (!empty($normalized_subject)) {
        $CI->db->where('to_staff_id', $staffId);
        $CI->db->like('subject', $normalized_subject, 'after');
        $CI->db->or_like('subject', 'Re: ' . $normalized_subject, 'after');
        $CI->db->or_like('subject', 'RE: ' . $normalized_subject, 'after');
        $existing = $CI->db->get(db_prefix() . 'mail_inbox')->row();
        if ($existing && !empty($existing->thread_id)) {
            return $existing->thread_id;
        }
    }
    
    // Generate new thread ID based on message_id or normalized subject
    if (!empty($messageId)) {
        return md5($messageId . $staffId);
    }
    
    return md5($normalized_subject . $staffId);
}

/**
 * Normalize email subject for threading
 *
 * @param string $subject
 * @return string
 */
function normalize_email_subject($subject)
{
    // Remove Re:, Fwd:, Fw: prefixes and normalize whitespace
    $normalized = preg_replace('/^(re|fwd?|fw):\s*/i', '', $subject);
    $normalized = trim($normalized);
    return strtolower($normalized);
}

/**
 * Get staff email by id.
 *
 * @param string $email
 *
 * @return string staff id
 */
function get_staff_email_by_id($id)
{
    $CI = &get_instance();

    $staff = $CI->app_object_cache->get('staff-email-by-id-'.$id);

    if (!$staff) {
        $CI->db->where('staffid', $id);
        $staff = $CI->db->select('email')->from(db_prefix().'staff')->get()->row();
        $CI->app_object_cache->add('staff-email-by-id-'.$id, $staff);
    }

    return $staff ? $staff->email : '';
}

/**
 * Encrypt mail password for secure storage
 *
 * @param string $password
 *
 * @return string encrypted password
 */
function encrypt_mail_password($password)
{
    if (empty($password)) {
        return '';
    }

    $key = get_option('mailbox_encryption_key');
    if (empty($key)) {
        // Generate key if not exists
        $key = bin2hex(random_bytes(32));
        add_option('mailbox_encryption_key', $key);
    }

    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
    $encrypted = openssl_encrypt($password, 'AES-256-CBC', hex2bin($key), 0, $iv);
    
    return base64_encode($encrypted . '::' . bin2hex($iv));
}

/**
 * Decrypt mail password
 *
 * @param string $encrypted
 *
 * @return string decrypted password
 */
function decrypt_mail_password($encrypted)
{
    if (empty($encrypted)) {
        return '';
    }

    $key = get_option('mailbox_encryption_key');
    if (empty($key)) {
        // Backward compatibility: if no key exists, assume it's plain text
        return $encrypted;
    }

    try {
        $data = base64_decode($encrypted);
        
        // Check if :: exists in decoded data (our encryption format)
        if (strpos($data, '::') === false) {
            // Backward compatibility: old format or plain text
            return $encrypted;
        }
        
        list($encrypted_data, $iv_hex) = explode('::', $data, 2);
        $iv = hex2bin($iv_hex);
        $decrypted = openssl_decrypt($encrypted_data, 'AES-256-CBC', hex2bin($key), 0, $iv);
        
        if ($decrypted === false) {
            // Decryption failed, return original
            return $encrypted;
        }
        
        return $decrypted;
    } catch (Exception $e) {
        // If decryption fails, return original (might be plain text)
        return $encrypted;
    } catch (Throwable $e) {
        return $encrypted;
    }
}

/**
 * Check for outbox attachment after inserting outbox to database.
 *
 * @param mixed $outbox_id
 *
 * @return mixed false if no attachment || array uploaded attachments
 */
function handle_mail_attachments($mail_id, $type = 'inbox', $index_name = 'attachments', $method='move')
{
    $path           = MAILBOX_MODULE_UPLOAD_FOLDER.'/'.$type.'/'.$mail_id.'/';

    $uploaded_files = [];

    if (isset($_FILES[$index_name])) {
        _file_attachments_index_fix($index_name);

        for ($i = 0; $i < count($_FILES[$index_name]['name']); ++$i) {
            hooks()->do_action('before_upload_outbox_attachment', $mail_id);
            if ($i <= 100) {
                // Get the temp file path
                $tmpFilePath = $_FILES[$index_name]['tmp_name'][$i];
                // Make sure we have a filepath
                if (!empty($tmpFilePath) && '' != $tmpFilePath) {
                    // Getting file extension
                    $extension          = strtolower(pathinfo($_FILES[$index_name]['name'][$i], PATHINFO_EXTENSION));
                    $allowed_extensions = explode(',', get_option('allowed_files'));
                    $allowed_extensions = array_map('trim', $allowed_extensions);
                    // Check for all cases if this extension is allowed
                    if (!in_array('.'.$extension, $allowed_extensions)) {
                        continue;
                    }
                    _maybe_create_upload_path($path);
                    $filename    = unique_filename($path, $_FILES[$index_name]['name'][$i]);
                    $filename    = str_replace(' ', '_', $filename);
                    $newFilePath = $path.$filename;
                    // Upload the file into the temp dir
                    if ('copy' == $method) {
                        if (copy($tmpFilePath, $newFilePath)) {
                            array_push($uploaded_files, [
                                    'file_name'  => $filename,
                                    'file_type'  => $_FILES[$index_name]['type'][$i],
                                    ]);
                        }
                    } else {
                        if (move_uploaded_file($tmpFilePath, $newFilePath)) {
                            array_push($uploaded_files, [
                                    'file_name'  => $filename,
                                    'file_type'  => $_FILES[$index_name]['type'][$i],
                                    ]);
                        }
                    }
                }
            }
        }
    }
    if (count($uploaded_files) > 0) {
        return $uploaded_files;
    }

    return false;
}

/**
 * text limiter.
 *
 * @param string $str
 * @param int    $limit
 * @param string $end_char
 *
 * @return string
 */
function text_limiter($str, $limit = 100, $end_char = '&#8230;')
{
    if ('' === trim($str)) {
        return $str;
    }

    preg_match('/^\s*+(?:\S++\s*+){1,'.(int) $limit.'}/', $str, $matches);

    if (strlen($str) === strlen($matches[0])) {
        $end_char = '';
    }

    return rtrim($matches[0]).$end_char;
}

/**
 * Check whether column exists in a table
 * Custom function because Codeigniter is caching the tables and this is causing issues in migrations.
 *
 * @param string $column column name to check
 * @param string $table  table name to check
 *
 * @return bool
 */
function column_exists($column, $table)
{
    if (!startsWith($table, db_prefix())) {
        $table = db_prefix().$table;
    }

    $result = get_instance()->db->query('SHOW COLUMNS FROM '.$table." LIKE '".$column."';")->row();

    return (bool) $result;
}

/**
 * prepare imap email body html.
 *
 * @param string $body
 * @param int|null $staff_id Optional staff ID to check per-staff HTML preference
 *
 * @return string
 */
function prepare_imap_email_body_html($body, $staff_id = null)
{
    if (empty($body)) {
        return '';
    }
    
    // Trim message
    $body = trim($body);
    
    // Check if HTML is enabled (per-staff preference)
    $html_enabled = true; // Default to enabled
    if ($staff_id !== null) {
        $CI = &get_instance();
        $CI->db->select('enable_html');
        $CI->db->from(db_prefix() . 'staff');
        $CI->db->where('staffid', $staff_id);
        $staff_setting = $CI->db->get()->row();
        $html_enabled = isset($staff_setting->enable_html) && ($staff_setting->enable_html == 1);
    }
    
    if ($html_enabled) {
        // HTML is enabled - sanitize but preserve HTML structure
        // Remove dangerous scripts and event handlers
        // Note: PHP preg_replace doesn't support 'g' flag, it replaces all by default
        $body = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/is', '', $body);
        $body = preg_replace('/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/is', '', $body);
        $body = preg_replace('/on\w+\s*=\s*["\'][^"\']*["\']/i', '', $body);
        $body = preg_replace('/on\w+\s*=\s*[^\s>]*/i', '', $body);
        $body = preg_replace('/javascript:/i', '', $body);
        
        // Allow CSS in <style> tags but sanitize
        // Note: Perfex CRM may strip style tags in views, but we'll preserve them here
    } else {
        // HTML is disabled - strip all HTML tags and convert to plain text
        $body = str_replace('&nbsp;', ' ', $body);
        
        // Remove style tags and their content (CSS)
        $body = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/is', '', $body);
        
        // Strip unwanted tags while allowing specific safe tags
        // We are only allowing a limited set of tags to prevent XSS attacks
        $allowed_tags = '<br><a><p><b><i><u><strong><em><ul><ol><li><blockquote><span><div><hr>';
        $body = strip_tags($body, $allowed_tags);

        // Remove dangerous inline event handlers or scripts
        $body = preg_replace('/<[^>]+(on\w+|javascript:|data:|<script|<iframe)[^>]*>/i', '', $body);
        
        // Remove any remaining style attributes
        $body = preg_replace('/\s*style\s*=\s*["\'][^"\']*["\']/i', '', $body);

        // Normalize new lines (convert them to <br> tags)
        $body = preg_replace("/[\r\n]+/", "\n", $body);
        $body = preg_replace('/\n(\s*\n)+/', '<br />', $body);
        $body = preg_replace('/\n/', '<br>', $body);
    }

    return $body;
}

/**
 * Process email rules for a newly inserted email
 *
 * @param int $email_id
 * @param array $email_data (from, subject, body, to, etc.)
 * @param int $staff_id
 * @return void
 */
/**
 * Process mailbox rules for a newly received email
 * This is the main function that should be called after an email is inserted
 *
 * @param int $email_id
 * @param int $staff_id
 * @param string $type ('inbox' or 'outbox')
 * @return void
 */
function process_mailbox_rules($email_id, $staff_id, $type = 'inbox')
{
    $CI = &get_instance();
    
    // Check if rules table exists
    if (!$CI->db->table_exists(db_prefix() . 'mail_rules')) {
        return;
    }
    
    // Load mailbox model if not already loaded
    if (!isset($CI->mailbox_model)) {
        $CI->load->model('mailbox_model');
    }
    
    // Get enabled rules for this staff member
    $rules = $CI->mailbox_model->get_rules($staff_id);
    if (empty($rules)) {
        return;
    }
    
    // Filter only enabled rules
    $enabled_rules = array_filter($rules, function($rule) {
        return isset($rule['enabled']) && $rule['enabled'] == 1;
    });
    
    if (empty($enabled_rules)) {
        return;
    }
    
    // Get email data
    $email = $CI->mailbox_model->get($email_id, $type);
    if (!$email) {
        log_activity('Email not found for rule processing. ID: ' . $email_id . ', Type: ' . $type, null);
        return;
    }
    
    // Processing rules (log removed to minimize activity log noise)
    
    // Process each rule
    foreach ($enabled_rules as $rule) {
        $conditions = json_decode($rule['conditions'], true);
        $actions = json_decode($rule['actions'], true);
        
        if (empty($conditions) || empty($actions)) {
            continue;
        }
        
        // Check if all conditions match
        $match = true;
        foreach ($conditions as $condition) {
            $field = isset($condition['field']) ? $condition['field'] : '';
            $operator = isset($condition['operator']) ? $condition['operator'] : '';
            $value = isset($condition['value']) ? strtolower(trim($condition['value'])) : '';
            
            if (empty($field) || empty($operator) || empty($value)) {
                $match = false;
                break;
            }
            
            // Get email field value
            $email_field_value = '';
            switch ($field) {
                case 'from':
                    $email_field_value = strtolower(isset($email->from_email) ? $email->from_email : (isset($email->sender_name) ? $email->sender_name : ''));
                    break;
                case 'subject':
                    $email_field_value = strtolower(isset($email->subject) ? $email->subject : '');
                    break;
                case 'body':
                    $email_field_value = strtolower(isset($email->body) ? strip_tags($email->body) : '');
                    break;
                case 'to':
                    $email_field_value = strtolower(isset($email->to) ? $email->to : '');
                    break;
            }
            
            // Check condition
            $condition_met = false;
            switch ($operator) {
                case 'contains':
                    $condition_met = (strpos($email_field_value, $value) !== false);
                    break;
                case 'not_contains':
                    $condition_met = (strpos($email_field_value, $value) === false);
                    break;
                case 'equals':
                    $condition_met = ($email_field_value === $value);
                    break;
                case 'starts_with':
                    $condition_met = (strpos($email_field_value, $value) === 0);
                    break;
                case 'ends_with':
                    $condition_met = (substr($email_field_value, -strlen($value)) === $value);
                    break;
            }
            
            if (!$condition_met) {
                $match = false;
                break;
            }
        }
        
        // If all conditions match, execute actions
        if ($match) {
            log_activity('Rule matched: ' . $rule['name'] . ' (Rule ID: ' . $rule['id'] . ') for email ID: ' . $email_id, null);
            
            foreach ($actions as $action) {
                $action_type = isset($action['type']) ? $action['type'] : '';
                $action_value = isset($action['value']) ? $action['value'] : '';
                
                if (empty($action_type)) {
                    continue;
                }
                
                switch ($action_type) {
                    case 'move_to_folder':
                        $update_data = ['folder' => $action_value];
                        if ($action_value === 'archive') {
                            $update_data['archived'] = 1;
                            $update_data['trash'] = 0;
                        } elseif ($action_value === 'trash') {
                            $update_data['trash'] = 1;
                            $update_data['archived'] = 0;
                        } else {
                            $update_data['archived'] = 0;
                            $update_data['trash'] = 0;
                        }
                        
                        // Map system folder to IMAP folder if possible
                        if (function_exists('get_mailbox_folders_to_sync')) {
                            $folders = get_mailbox_folders_to_sync();
                            $imap_folder_map = array_flip($folders);
                            if (isset($imap_folder_map[$action_value])) {
                                $update_data['imap_folder'] = $imap_folder_map[$action_value];
                            }
                        }
                        
                        $CI->db->where('id', $email_id);
                        $CI->db->update(db_prefix() . 'mail_' . $type, $update_data);
                        log_activity('Action: Moved email ID ' . $email_id . ' to folder: ' . $action_value, null);
                        break;
                        
                    case 'mark_as_read':
                        $CI->db->where('id', $email_id);
                        $CI->db->update(db_prefix() . 'mail_' . $type, ['read' => 1]);
                        log_activity('Action: Marked email ID ' . $email_id . ' as read', null);
                        break;
                        
                    case 'mark_as_important':
                        $CI->db->where('id', $email_id);
                        $CI->db->update(db_prefix() . 'mail_' . $type, ['important' => 1]);
                        log_activity('Action: Marked email ID ' . $email_id . ' as important', null);
                        break;
                        
                    case 'add_tag':
                        if (!empty($action_value)) {
                            $CI->mailbox_model->update_mail_tag($email_id, $action_value, $type);
                            log_activity('Action: Added tag ID ' . $action_value . ' to email ID ' . $email_id, null);
                        }
                        break;
                        
                    case 'delete':
                    case 'delete_email':
                        $CI->db->where('id', $email_id);
                        $CI->db->update(db_prefix() . 'mail_' . $type, ['trash' => 1]);
                        log_activity('Action: Deleted email ID ' . $email_id, null);
                        break;
                }
            }
            
            // Stop processing after first matching rule (can be changed to continue)
            break;
        }
    }
}

/**
 * Legacy function name for backward compatibility
 * @deprecated Use process_mailbox_rules() instead
 */
function process_email_rules($email_id, $email_data, $staff_id)
{
    $CI = &get_instance();
    
    // Check if rules table exists
    if (!$CI->db->table_exists(db_prefix() . 'mail_rules')) {
        return;
    }
    
    // Load mailbox model if not already loaded
    if (!isset($CI->mailbox_model)) {
        $CI->load->model('mailbox_model');
    }
    
    // Get enabled rules for this staff member
    $rules = $CI->mailbox_model->get_enabled_rules($staff_id);
    
    if (empty($rules)) {
        return;
    }
    
    // Process each rule in order
    foreach ($rules as $rule) {
        $conditions = json_decode($rule['conditions'], true);
        $actions = json_decode($rule['actions'], true);
        
        if (!is_array($conditions) || !is_array($actions)) {
            continue;
        }
        
        // Check if all conditions match
        $conditions_match = true;
        foreach ($conditions as $condition) {
            if (!isset($condition['field']) || !isset($condition['operator']) || !isset($condition['value'])) {
                $conditions_match = false;
                break;
            }
            
            $field = $condition['field'];
            $operator = $condition['operator'];
            $value = strtolower(trim($condition['value']));
            
            // Get field value from email data
            $field_value = '';
            switch ($field) {
                case 'from':
                    $field_value = strtolower(isset($email_data['from_email']) ? $email_data['from_email'] : (isset($email_data['from']) ? $email_data['from'] : ''));
                    break;
                case 'subject':
                    $field_value = strtolower(isset($email_data['subject']) ? $email_data['subject'] : '');
                    break;
                case 'body':
                    $field_value = strtolower(isset($email_data['body']) ? strip_tags($email_data['body']) : '');
                    break;
                case 'to':
                    $field_value = strtolower(isset($email_data['to']) ? $email_data['to'] : '');
                    break;
            }
            
            // Check condition
            $match = false;
            switch ($operator) {
                case 'contains':
                    $match = strpos($field_value, $value) !== false;
                    break;
                case 'not_contains':
                    $match = strpos($field_value, $value) === false;
                    break;
                case 'equals':
                    $match = $field_value == $value;
                    break;
                case 'starts_with':
                    $match = strpos($field_value, $value) === 0;
                    break;
                case 'ends_with':
                    $match = substr($field_value, -strlen($value)) === $value;
                    break;
            }
            
            if (!$match) {
                $conditions_match = false;
                break;
            }
        }
        
        // If conditions match, execute actions
        if ($conditions_match) {
            foreach ($actions as $action) {
                if (!isset($action['type'])) {
                    continue;
                }
                
                switch ($action['type']) {
                    case 'move_to_folder':
                        $folder = isset($action['value']) ? $action['value'] : '';
                        if ($folder == 'archive') {
                            $CI->db->where('id', $email_id);
                            $CI->db->update(db_prefix() . 'mail_inbox', ['archived' => 1]);
                        } elseif ($folder == 'trash') {
                            $CI->db->where('id', $email_id);
                            $CI->db->update(db_prefix() . 'mail_inbox', ['trash' => 1]);
                        }
                        break;
                    case 'mark_as_read':
                        $CI->db->where('id', $email_id);
                        $CI->db->update(db_prefix() . 'mail_inbox', ['read' => 1]);
                        break;
                    case 'mark_as_important':
                        $CI->db->where('id', $email_id);
                        $CI->db->update(db_prefix() . 'mail_inbox', ['important' => 1]);
                        break;
                    case 'add_tag':
                        $tag_id = isset($action['value']) ? (int)$action['value'] : 0;
                        if ($tag_id > 0) {
                            $CI->db->where('id', $email_id);
                            $CI->db->update(db_prefix() . 'mail_inbox', ['tagid' => $tag_id]);
                        }
                        break;
                    case 'delete':
                        $CI->db->where('id', $email_id);
                        $CI->db->update(db_prefix() . 'mail_inbox', ['trash' => 1]);
                        break;
                }
            }
            
            // Log rule execution
            log_activity('Email rule executed: ' . $rule['name'] . ' (Rule ID: ' . $rule['id'] . ', Email ID: ' . $email_id . ')', null);
            
            // Stop processing further rules if rule matched (optional - can be changed to continue)
            // break; // Uncomment to stop after first matching rule
        }
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
function get_mail_attachment($mail_id, $type='inbox')
{
    $CI = &get_instance();
    $CI->db->where('mail_id', $mail_id);
    $CI->db->where('type', $type);

    return  $CI->db->get(db_prefix().'mail_attachment')->result_array();
}

/**
 * Get email tags.
 *
 * @param int    $active
 * @param string $type
 *
 * @return array
 */
function get_mail_tags($active = true)
{
    $CI = &get_instance();
    if ($active == true) {
        $CI->db->where('active', true);
    } else if ($active == false) {
        $CI->db->where('active', false);
    }

    return  $CI->db->get(db_prefix().'mail_tags')->result_array();
}

/**
 * Get email tags.
 *
 * @param int    $active
 * @param string $type
 *
 * @return array
 */
function get_mail_templates($active = true)
{
    $CI = &get_instance();
    $CI->db->where('language', 'english');
    if ($active == true) {
        $CI->db->where('active', true);
    } else if ($active == false) {
        $CI->db->where('active', false);
    }

    return $CI->db->get(db_prefix().'emailtemplates')->result_array();
}

function mailbox_get_leads($mailbox_id, $type = 'inbox', $is_string = true) {
    $lead_names = [];
    $lead_ids = [];
    $CI = &get_instance();
    $CI->db->where($type . '_id', $mailbox_id);
    $mail_conversations = $CI->db->get(db_prefix().'mail_conversation')->result_array();
    foreach ($mail_conversations as $mail_conversation) {
        $CI->db->where('id', $mail_conversation['lead_id']);
        $lead = $CI->db->get(db_prefix().'leads')->row();
        if ($lead) {
            if (!in_array($lead->name, $lead_names)) {
                $lead_names[] = $lead->name;
            }
            if (!in_array($lead->id, $lead_ids)) {
                $lead_ids[] = $lead->id;
            }
        }
    }
    if ($is_string) {
        return implode(", ", $lead_names);
    }
    return $lead_ids;
}

function mailbox_get_client_companies($mailbox_id, $type = 'inbox', $is_string = true) {
    $client_compaies = [];
    $client_ids = [];
    $CI = &get_instance();
    $CI->db->where($type . '_id', $mailbox_id);
    $mail_clients = $CI->db->get(db_prefix().'mail_clients')->result_array();
    foreach ($mail_clients as $mail_client) {
        $CI->db->where('userid', $mail_client['client_id']);
        $client = $CI->db->get(db_prefix().'clients')->row();
        if ($client) {
            if (!in_array($client->company, $client_compaies)) {
                $client_compaies[] = $client->company;
            }
            if (!in_array($client->userid, $client_ids)) {
                $client_ids[] = $client->userid;
            }
        }
    }
    if ($is_string) {
        return implode(", ", $client_compaies);
    }
    return $client_ids;
}

/**
 * Get folders to sync for multi-folder synchronization
 *
 * @return array Array of [imap_folder => system_folder] mappings
 */
function get_mailbox_folders_to_sync()
{
    $folders = [];
    
    // Always sync INBOX
    $inbox_folder = get_option('mailbox_folder_scan') ?: 'INBOX';
    $folders[$inbox_folder] = 'inbox';
    
    // Get folder mappings from settings
    $sent_folder = get_option('mailbox_imap_sent_folder');
    if (!empty($sent_folder)) {
        $folders[$sent_folder] = 'sent';
    }
    
    $draft_folder = get_option('mailbox_imap_draft_folder');
    if (!empty($draft_folder)) {
        $folders[$draft_folder] = 'drafts';
    }
    
    $trash_folder = get_option('mailbox_imap_trash_folder');
    if (!empty($trash_folder)) {
        $folders[$trash_folder] = 'trash';
    }
    
    $spam_folder = get_option('mailbox_imap_spam_folder');
    if (!empty($spam_folder)) {
        $folders[$spam_folder] = 'spam';
    }
    
    $archive_folder = get_option('mailbox_imap_archive_folder');
    if (!empty($archive_folder)) {
        $folders[$archive_folder] = 'archive';
    }
    
    return $folders;
}

/**
 * Get last UID for a folder sync
 *
 * @param int $staff_id
 * @param string $folder_name
 * @return int
 */
function get_mailbox_folder_last_uid($staff_id, $folder_name)
{
    $CI = &get_instance();
    
    // Auto-create table if it doesn't exist (suppress error if already exists)
    if (!$CI->db->table_exists(db_prefix() . 'mail_folder_sync')) {
        try {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'mail_folder_sync` (
                `id` INT PRIMARY KEY AUTO_INCREMENT,
                `staff_id` INT NOT NULL,
                `folder_name` VARCHAR(100) NOT NULL,
                `last_uid` INT DEFAULT 0,
                `last_sync` DATETIME DEFAULT NULL,
                `sync_enabled` TINYINT(1) DEFAULT 1,
                UNIQUE KEY `staff_folder` (`staff_id`, `folder_name`),
                INDEX `idx_staff_id` (`staff_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
        } catch (Exception $e) {
            // Table might have been created by another process, ignore error
        }
    }
    
    $CI->db->where('staff_id', $staff_id);
    $CI->db->where('folder_name', $folder_name);
    $result = $CI->db->get(db_prefix() . 'mail_folder_sync')->row();
    
    return $result ? (int)$result->last_uid : 0;
}

/**
 * Update last UID for a folder sync
 *
 * @param int $staff_id
 * @param string $folder_name
 * @param int $last_uid
 * @return bool
 */
function update_mailbox_folder_last_uid($staff_id, $folder_name, $last_uid)
{
    $CI = &get_instance();
    
    // Auto-create table if it doesn't exist (suppress error if already exists)
    if (!$CI->db->table_exists(db_prefix() . 'mail_folder_sync')) {
        try {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'mail_folder_sync` (
                `id` INT PRIMARY KEY AUTO_INCREMENT,
                `staff_id` INT NOT NULL,
                `folder_name` VARCHAR(100) NOT NULL,
                `last_uid` INT DEFAULT 0,
                `last_sync` DATETIME DEFAULT NULL,
                `sync_enabled` TINYINT(1) DEFAULT 1,
                UNIQUE KEY `staff_folder` (`staff_id`, `folder_name`),
                INDEX `idx_staff_id` (`staff_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
        } catch (Exception $e) {
            // Table might have been created by another process, ignore error
        }
    }
    
    // Check if record exists
    $CI->db->where('staff_id', $staff_id);
    $CI->db->where('folder_name', $folder_name);
    $exists = $CI->db->get(db_prefix() . 'mail_folder_sync')->row();
    
    $data = [
        'last_uid' => $last_uid,
        'last_sync' => date('Y-m-d H:i:s'),
        'sync_enabled' => 1,
    ];
    
    if ($exists) {
        $CI->db->where('staff_id', $staff_id);
        $CI->db->where('folder_name', $folder_name);
        return $CI->db->update(db_prefix() . 'mail_folder_sync', $data);
    } else {
        $data['staff_id'] = $staff_id;
        $data['folder_name'] = $folder_name;
        return $CI->db->insert(db_prefix() . 'mail_folder_sync', $data);
    }
}