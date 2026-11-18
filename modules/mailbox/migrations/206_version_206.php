<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_206 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        
        // Add OAuth fields to staff table
        if (!$CI->db->field_exists('auth_method', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `auth_method` ENUM("password", "oauth2") DEFAULT "password"');
        }

        if (!$CI->db->field_exists('oauth_provider', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `oauth_provider` VARCHAR(50) DEFAULT NULL');
        }

        if (!$CI->db->field_exists('oauth_access_token', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `oauth_access_token` TEXT DEFAULT NULL');
        }

        if (!$CI->db->field_exists('oauth_refresh_token', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `oauth_refresh_token` TEXT DEFAULT NULL');
        }

        if (!$CI->db->field_exists('oauth_token_expires_at', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `oauth_token_expires_at` DATETIME DEFAULT NULL');
        }

        if (!$CI->db->field_exists('last_email_uid', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `last_email_uid` INT DEFAULT 0');
        }

        // Add indexes for performance (check if they exist first)
        $this->add_index_if_not_exists(db_prefix() . 'mail_inbox', 'idx_to_staff_read_trash', 'to_staff_id, `read`, trash');
        $this->add_index_if_not_exists(db_prefix() . 'mail_inbox', 'idx_date_received', 'date_received');
        $this->add_index_if_not_exists(db_prefix() . 'mail_outbox', 'idx_sender_date', 'sender_staff_id, date_sent');
        $this->add_index_if_not_exists(db_prefix() . 'mail_attachment', 'idx_mail_type', 'mail_id, type');

        // Add OAuth settings
        if (get_option('mailbox_gmail_client_id') === false) {
            add_option('mailbox_gmail_client_id', '');
        }
        if (get_option('mailbox_gmail_client_secret') === false) {
            add_option('mailbox_gmail_client_secret', '');
        }
        if (get_option('mailbox_outlook_client_id') === false) {
            add_option('mailbox_outlook_client_id', '');
        }
        if (get_option('mailbox_outlook_client_secret') === false) {
            add_option('mailbox_outlook_client_secret', '');
        }
        if (get_option('mailbox_outlook_tenant_id') === false) {
            add_option('mailbox_outlook_tenant_id', 'common');
        }
        if (get_option('mailbox_encryption_key') === false) {
            add_option('mailbox_encryption_key', bin2hex(random_bytes(32))); // Generate encryption key
        }
        if (get_option('mailbox_threading_enabled') === false) {
            add_option('mailbox_threading_enabled', 1); // Enable threading by default
        }
    }
    
    /**
     * Helper function to add index only if it doesn't exist
     */
    private function add_index_if_not_exists($table, $index_name, $columns)
    {
        $CI = &get_instance();
        
        // Check if index exists
        $query = $CI->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$index_name}'");
        
        if ($query->num_rows() == 0) {
            // Index doesn't exist, create it
            $CI->db->query("ALTER TABLE `{$table}` ADD INDEX `{$index_name}` ({$columns})");
        }
    }
}
