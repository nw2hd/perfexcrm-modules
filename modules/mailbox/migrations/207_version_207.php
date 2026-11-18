<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_207 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        
        // Add threading fields to mail_inbox
        if (!$CI->db->field_exists('message_id', db_prefix() . 'mail_inbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` ADD COLUMN `message_id` VARCHAR(255) DEFAULT NULL');
        }
        
        if (!$CI->db->field_exists('in_reply_to', db_prefix() . 'mail_inbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` ADD COLUMN `in_reply_to` VARCHAR(255) DEFAULT NULL');
        }
        
        if (!$CI->db->field_exists('references', db_prefix() . 'mail_inbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` ADD COLUMN `references` TEXT DEFAULT NULL');
        }
        
        if (!$CI->db->field_exists('thread_id', db_prefix() . 'mail_inbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` ADD COLUMN `thread_id` VARCHAR(255) DEFAULT NULL');
        }
        
        // Add threading fields to mail_outbox
        if (!$CI->db->field_exists('message_id', db_prefix() . 'mail_outbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_outbox` ADD COLUMN `message_id` VARCHAR(255) DEFAULT NULL');
        }
        
        if (!$CI->db->field_exists('in_reply_to', db_prefix() . 'mail_outbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_outbox` ADD COLUMN `in_reply_to` VARCHAR(255) DEFAULT NULL');
        }
        
        if (!$CI->db->field_exists('references', db_prefix() . 'mail_outbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_outbox` ADD COLUMN `references` TEXT DEFAULT NULL');
        }
        
        if (!$CI->db->field_exists('thread_id', db_prefix() . 'mail_outbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_outbox` ADD COLUMN `thread_id` VARCHAR(255) DEFAULT NULL');
        }
        
        // Add indexes for threading
        $this->add_index_if_not_exists(db_prefix() . 'mail_inbox', ['thread_id'], 'idx_thread_id');
        $this->add_index_if_not_exists(db_prefix() . 'mail_inbox', ['message_id'], 'idx_message_id');
        $this->add_index_if_not_exists(db_prefix() . 'mail_outbox', ['thread_id'], 'idx_thread_id');
        $this->add_index_if_not_exists(db_prefix() . 'mail_outbox', ['message_id'], 'idx_message_id');
        
        // Generate thread_id for existing emails based on subject normalization
        $this->generate_thread_ids_for_existing_emails();
    }
    
    private function add_index_if_not_exists($table, $columns, $index_name)
    {
        $CI = &get_instance();
        $index_exists = false;
        $query = $CI->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$index_name}'");
        if ($query->num_rows() > 0) {
            $index_exists = true;
        }

        if (!$index_exists) {
            $columns_str = implode('`, `', $columns);
            try {
                $CI->db->query("CREATE INDEX `{$index_name}` ON `{$table}` (`{$columns_str}`)");
            } catch (Exception $e) {
                log_activity('Failed to add index ' . $index_name . ' to ' . $table . ': ' . $e->getMessage(), null);
            }
        }
    }
    
    private function generate_thread_ids_for_existing_emails()
    {
        $CI = &get_instance();
        
        // Generate thread IDs for inbox emails based on normalized subject
        $inbox_emails = $CI->db->get(db_prefix() . 'mail_inbox')->result_array();
        foreach ($inbox_emails as $email) {
            $normalized_subject = $this->normalize_subject($email['subject']);
            $thread_id = md5($normalized_subject . $email['to_staff_id']);
            $CI->db->where('id', $email['id']);
            $CI->db->update(db_prefix() . 'mail_inbox', ['thread_id' => $thread_id]);
        }
        
        // Generate thread IDs for outbox emails based on normalized subject
        $outbox_emails = $CI->db->get(db_prefix() . 'mail_outbox')->result_array();
        foreach ($outbox_emails as $email) {
            $normalized_subject = $this->normalize_subject($email['subject']);
            $thread_id = md5($normalized_subject . $email['sender_staff_id']);
            $CI->db->where('id', $email['id']);
            $CI->db->update(db_prefix() . 'mail_outbox', ['thread_id' => $thread_id]);
        }
    }
    
    private function normalize_subject($subject)
    {
        // Remove Re:, Fwd:, Fw: prefixes and normalize whitespace
        $normalized = preg_replace('/^(re|fwd?|fw):\s*/i', '', $subject);
        $normalized = trim($normalized);
        return strtolower($normalized);
    }
}
