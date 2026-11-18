<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_208 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        
        // ========================================
        // PRIORITY 1: Multi-Folder Sync
        // ========================================
        
        // Add imap_folder column to mail_inbox
        if (!$CI->db->field_exists('imap_folder', db_prefix() . 'mail_inbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` ADD COLUMN `imap_folder` VARCHAR(100) DEFAULT "INBOX"');
        }
        
        // Add imap_folder column to mail_outbox
        if (!$CI->db->field_exists('imap_folder', db_prefix() . 'mail_outbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_outbox` ADD COLUMN `imap_folder` VARCHAR(100) DEFAULT NULL');
        }
        
        // Create mail_folder_sync table
        if (!$CI->db->table_exists(db_prefix() . 'mail_folder_sync')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'mail_folder_sync` (
                `id` INT PRIMARY KEY AUTO_INCREMENT,
                `staff_id` INT NOT NULL,
                `folder_name` VARCHAR(100) NOT NULL,
                `last_uid` INT DEFAULT 0,
                `last_sync` DATETIME DEFAULT NULL,
                `sync_enabled` TINYINT(1) DEFAULT 1,
                UNIQUE KEY `staff_folder` (`staff_id`, `folder_name`),
                INDEX `idx_staff_id` (`staff_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set);
        }
        
        // ========================================
        // PRIORITY 2: Archive Feature
        // ========================================
        
        if (!$CI->db->field_exists('archived', db_prefix() . 'mail_inbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` ADD COLUMN `archived` TINYINT(1) DEFAULT 0');
            $this->add_index_if_not_exists(db_prefix() . 'mail_inbox', ['archived'], 'idx_archived');
        }
        
        if (!$CI->db->field_exists('archived', db_prefix() . 'mail_outbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_outbox` ADD COLUMN `archived` TINYINT(1) DEFAULT 0');
            $this->add_index_if_not_exists(db_prefix() . 'mail_outbox', ['archived'], 'idx_archived');
        }
        
        // ========================================
        // PRIORITY 2: Snooze Feature
        // ========================================
        
        if (!$CI->db->field_exists('snoozed_until', db_prefix() . 'mail_inbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` ADD COLUMN `snoozed_until` DATETIME DEFAULT NULL');
            $this->add_index_if_not_exists(db_prefix() . 'mail_inbox', ['snoozed_until'], 'idx_snoozed_until');
        }
        
        if (!$CI->db->field_exists('snoozed_until', db_prefix() . 'mail_outbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_outbox` ADD COLUMN `snoozed_until` DATETIME DEFAULT NULL');
            $this->add_index_if_not_exists(db_prefix() . 'mail_outbox', ['snoozed_until'], 'idx_snoozed_until');
        }
        
        // ========================================
        // PRIORITY 2: Email Filtering/Rules
        // ========================================
        
        if (!$CI->db->table_exists(db_prefix() . 'mail_rules')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'mail_rules` (
                `id` INT PRIMARY KEY AUTO_INCREMENT,
                `staff_id` INT NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `conditions` TEXT,
                `actions` TEXT,
                `order` INT DEFAULT 0,
                `enabled` TINYINT(1) DEFAULT 1,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_staff_id` (`staff_id`),
                INDEX `idx_enabled` (`enabled`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set);
        }
        
        // ========================================
        // PRIORITY 2: Multiple Labels (Many-to-Many)
        // ========================================
        
        if (!$CI->db->table_exists(db_prefix() . 'mail_email_tags')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'mail_email_tags` (
                `id` INT PRIMARY KEY AUTO_INCREMENT,
                `email_id` INT NOT NULL,
                `tag_id` INT NOT NULL,
                `type` VARCHAR(20) NOT NULL DEFAULT "inbox",
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `email_tag_type` (`email_id`, `tag_id`, `type`),
                INDEX `idx_email_id` (`email_id`),
                INDEX `idx_tag_id` (`tag_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set);
        }
        
        // Add folder mapping settings
        add_option('mailbox_imap_sent_folder', '');
        add_option('mailbox_imap_draft_folder', '');
        add_option('mailbox_imap_trash_folder', '');
        add_option('mailbox_imap_spam_folder', '');
        add_option('mailbox_imap_archive_folder', '');
        
        // Add other settings
        add_option('mailbox_archive_enabled', 1);
        add_option('mailbox_snooze_enabled', 1);
        add_option('mailbox_keyboard_shortcuts_enabled', 1);
        add_option('mailbox_preview_pane_enabled', 0);
        add_option('mailbox_auto_mark_read_on_preview', 1);
        add_option('mailbox_default_preview_pane', 0);
        add_option('mailbox_max_emails_per_sync', 0);
        add_option('mailbox_sync_timeout', 300);
        add_option('mailbox_items_per_page', 25);
        add_option('mailbox_auto_refresh_interval', 0);
        add_option('mailbox_show_attachment_icons', 1);
        add_option('mailbox_show_avatar_images', 1);
        add_option('mailbox_enable_rules', 1);
        add_option('mailbox_enable_multiple_tags', 1);
        add_option('mailbox_enable_advanced_search', 1);
        add_option('mailbox_enable_bulk_actions', 1);
    }
    
    private function add_index_if_not_exists($table, $columns, $index_name)
    {
        $CI = &get_instance();
        $index_exists = false;
        try {
            $query = $CI->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$index_name}'");
            if ($query && $query->num_rows() > 0) {
                $index_exists = true;
            }
        } catch (Exception $e) {
            // Index check failed, assume it doesn't exist
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
}
