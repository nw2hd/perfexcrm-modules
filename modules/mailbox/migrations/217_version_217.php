<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Migration: Add sync_read_status_to_server setting
 * Version: 2.1.7
 * 
 * Adds per-staff setting to control whether read status should be synced
 * to IMAP server when emails are read in Perfex CRM.
 */
class Migration_Version_217 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        
        // Add sync_read_status_to_server column to staff table
        if (!$CI->db->field_exists('sync_read_status_to_server', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` 
                ADD COLUMN `sync_read_status_to_server` TINYINT(1) NOT NULL DEFAULT 0 
                COMMENT "Sync read status to IMAP server (slower, keeps Gmail/Outlook in sync)" 
                AFTER `enable_html`');
        }
        
        // Also add uid column to mail_inbox if not exists (to store IMAP UID for marking as read)
        if (!$CI->db->field_exists('uid', db_prefix() . 'mail_inbox')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` 
                ADD COLUMN `uid` INT DEFAULT NULL 
                COMMENT "IMAP UID for syncing read status" 
                AFTER `id`');
        }
        
        // Add index for faster lookups (check if it exists first to avoid errors)
        $index_exists = false;
        try {
            $index_check = $CI->db->query("SHOW INDEX FROM `" . db_prefix() . "mail_inbox` WHERE Key_name = 'idx_uid'");
            if ($index_check && $index_check->num_rows() > 0) {
                $index_exists = true;
            }
        } catch (Exception $e) {
            // Index check failed, assume it doesn't exist
        }
        
        if (!$index_exists && $CI->db->field_exists('uid', db_prefix() . 'mail_inbox')) {
            try {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` 
                    ADD INDEX `idx_uid` (`uid`)');
            } catch (Exception $e) {
                // Index might have been created by another process, log and continue
                log_activity('Migration 217: Could not add idx_uid index (may already exist): ' . $e->getMessage(), null);
            }
        }
    }

    public function down()
    {
        $CI = &get_instance();
        
        // Remove uid column and index from mail_inbox
        if ($CI->db->field_exists('uid', db_prefix() . 'mail_inbox')) {
            // Drop index first
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` DROP INDEX `idx_uid`');
            // Then drop column
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` DROP COLUMN `uid`');
        }
        
        // Remove sync_read_status_to_server column from staff
        if ($CI->db->field_exists('sync_read_status_to_server', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` DROP COLUMN `sync_read_status_to_server`');
        }
    }
}
