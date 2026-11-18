<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Migration for Mailbox Module v2.1.1
 * Comprehensive fixes - Add missing columns and ensure settings work properly
 */
class Migration_Version_211 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        
        // ========================================
        // ADD ALL MISSING DATABASE COLUMNS
        // ========================================
        
        // Archive columns
        if (!$CI->db->field_exists('archived', db_prefix() . 'mail_inbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` ADD COLUMN `archived` TINYINT(1) DEFAULT 0');
        }
        if (!$CI->db->field_exists('archived', db_prefix() . 'mail_outbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_outbox` ADD COLUMN `archived` TINYINT(1) DEFAULT 0');
        }
        
        // Snooze columns
        if (!$CI->db->field_exists('snoozed_until', db_prefix() . 'mail_inbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` ADD COLUMN `snoozed_until` DATETIME DEFAULT NULL');
        }
        if (!$CI->db->field_exists('snoozed_until', db_prefix() . 'mail_outbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_outbox` ADD COLUMN `snoozed_until` DATETIME DEFAULT NULL');
        }
        
        // Email threading columns for inbox
        if (!$CI->db->field_exists('thread_id', db_prefix() . 'mail_inbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` ADD COLUMN `thread_id` VARCHAR(64) DEFAULT NULL');
        }
        if (!$CI->db->field_exists('message_id', db_prefix() . 'mail_inbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` ADD COLUMN `message_id` VARCHAR(255) DEFAULT NULL');
        }
        if (!$CI->db->field_exists('in_reply_to', db_prefix() . 'mail_inbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` ADD COLUMN `in_reply_to` VARCHAR(255) DEFAULT NULL');
        }
        if (!$CI->db->field_exists('references', db_prefix() . 'mail_inbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` ADD COLUMN `references` TEXT DEFAULT NULL');
        }
        
        // Threading columns for outbox
        if (!$CI->db->field_exists('thread_id', db_prefix() . 'mail_outbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_outbox` ADD COLUMN `thread_id` VARCHAR(64) DEFAULT NULL');
        }
        if (!$CI->db->field_exists('message_id', db_prefix() . 'mail_outbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_outbox` ADD COLUMN `message_id` VARCHAR(255) DEFAULT NULL');
        }
        
        // ========================================
        // CREATE MISSING TABLES
        // ========================================
        
        // Email rules table (may have been created by migration 208)
        if (!$CI->db->table_exists(db_prefix() . 'mail_rules')) {
            try {
                $CI->db->query('CREATE TABLE `' . db_prefix() . 'mail_rules` (
                    `id` INT PRIMARY KEY AUTO_INCREMENT,
                    `staff_id` INT NOT NULL,
                    `name` VARCHAR(255) NOT NULL,
                    `conditions` TEXT,
                    `actions` TEXT,
                    `priority` INT DEFAULT 0,
                    `enabled` TINYINT(1) DEFAULT 1,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    INDEX `idx_staff_id` (`staff_id`),
                    INDEX `idx_enabled` (`enabled`),
                    INDEX `idx_priority` (`priority`)
                ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set);
            } catch (Exception $e) {
                // Table might have been created by another process, ignore error
                log_activity('Migration 211: mail_rules table may already exist: ' . $e->getMessage(), null);
            }
        }
        
        // Add priority column if it doesn't exist (migration 208 used 'order' instead)
        if ($CI->db->table_exists(db_prefix() . 'mail_rules')) {
            if (!$CI->db->field_exists('priority', db_prefix() . 'mail_rules')) {
                try {
                    // Check if 'order' column exists (from migration 208)
                    if ($CI->db->field_exists('order', db_prefix() . 'mail_rules')) {
                        // Rename 'order' to 'priority'
                        $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_rules` CHANGE COLUMN `order` `priority` INT DEFAULT 0');
                    } else {
                        // Add priority column
                        $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_rules` ADD COLUMN `priority` INT DEFAULT 0');
                    }
                } catch (Exception $e) {
                    log_activity('Migration 211: Could not add/rename priority column: ' . $e->getMessage(), null);
                }
            }
            
            // Add idx_priority index if it doesn't exist
            try {
                $index_check = $CI->db->query("SHOW INDEX FROM `" . db_prefix() . "mail_rules` WHERE Key_name = 'idx_priority'");
                if (!$index_check || $index_check->num_rows() == 0) {
                    $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_rules` ADD INDEX `idx_priority` (`priority`)');
                }
            } catch (Exception $e) {
                // Index might already exist, continue
            }
        }
        
        // Multiple tags table (may have been created by migration 208)
        if (!$CI->db->table_exists(db_prefix() . 'mail_email_tags')) {
            try {
                $CI->db->query('CREATE TABLE `' . db_prefix() . 'mail_email_tags` (
                    `id` INT PRIMARY KEY AUTO_INCREMENT,
                    `email_id` INT NOT NULL,
                    `tag_id` INT NOT NULL,
                    `type` VARCHAR(20) NOT NULL DEFAULT "inbox",
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `email_tag_type` (`email_id`, `tag_id`, `type`),
                    INDEX `idx_email_id` (`email_id`),
                    INDEX `idx_tag_id` (`tag_id`),
                    INDEX `idx_type` (`type`)
                ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set);
            } catch (Exception $e) {
                // Table might have been created by another process, ignore error
                log_activity('Migration 211: mail_email_tags table may already exist: ' . $e->getMessage(), null);
            }
        }
        
        // Add idx_type index if table exists but index doesn't (migration 208 didn't have this index)
        if ($CI->db->table_exists(db_prefix() . 'mail_email_tags')) {
            try {
                $index_check = $CI->db->query("SHOW INDEX FROM `" . db_prefix() . "mail_email_tags` WHERE Key_name = 'idx_type'");
                if (!$index_check || $index_check->num_rows() == 0) {
                    $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_email_tags` ADD INDEX `idx_type` (`type`)');
                }
            } catch (Exception $e) {
                // Index might already exist, continue
            }
        }
        
        // ========================================
        // ENSURE ALL SETTINGS ARE ENABLED (DEFAULT: 1)
        // ========================================
        
        // Force enable all features by default
        $settings_to_enable = [
            'mailbox_archive_enabled' => '1',
            'mailbox_snooze_enabled' => '1',
            'mailbox_enable_rules' => '1',
            'mailbox_threading_enabled' => '1',
            'mailbox_enable_multiple_tags' => '1',
            'mailbox_enable_advanced_search' => '1',
            'mailbox_enable_bulk_actions' => '1',
            'mailbox_show_avatar_images' => '1',
            'mailbox_keyboard_shortcuts_enabled' => '1',
            'mailbox_auto_mark_read_on_preview' => '1',
        ];
        
        foreach ($settings_to_enable as $setting => $value) {
            // Check if setting exists
            $current = get_option($setting);
            if ($current === false || $current === '' || $current === null) {
                // Setting doesn't exist or is empty, add it
                add_option($setting, $value);
            } else {
                // Setting exists, update it to enabled
                update_option($setting, $value);
            }
        }
        
        // Log migration
        log_activity('Mailbox Module upgraded to v2.1.1 - All features enabled and database updated');
    }
    
    public function down()
    {
        // Nothing to rollback - features are stable
    }
}
