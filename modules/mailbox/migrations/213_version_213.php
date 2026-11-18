<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Migration: Add per-staff IMAP folder mappings and OAuth credentials
 * 
 * Version: 2.1.3
 */
class Migration_Version_213 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        
        // Add IMAP folder mapping columns to staff table
        if (!$CI->db->field_exists('imap_sent_folder', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` 
                ADD COLUMN `imap_sent_folder` VARCHAR(255) NULL DEFAULT NULL AFTER `imap_encryption`');
        }
        
        if (!$CI->db->field_exists('imap_draft_folder', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` 
                ADD COLUMN `imap_draft_folder` VARCHAR(255) NULL DEFAULT NULL AFTER `imap_sent_folder`');
        }
        
        if (!$CI->db->field_exists('imap_trash_folder', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` 
                ADD COLUMN `imap_trash_folder` VARCHAR(255) NULL DEFAULT NULL AFTER `imap_draft_folder`');
        }
        
        if (!$CI->db->field_exists('imap_spam_folder', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` 
                ADD COLUMN `imap_spam_folder` VARCHAR(255) NULL DEFAULT NULL AFTER `imap_trash_folder`');
        }
        
        if (!$CI->db->field_exists('imap_archive_folder', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` 
                ADD COLUMN `imap_archive_folder` VARCHAR(255) NULL DEFAULT NULL AFTER `imap_spam_folder`');
        }
        
        // Add OAuth credential fields for per-staff OAuth apps
        if (!$CI->db->field_exists('oauth_client_id', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` 
                ADD COLUMN `oauth_client_id` TEXT NULL DEFAULT NULL AFTER `oauth_token_expires_at`');
        }
        
        if (!$CI->db->field_exists('oauth_client_secret', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` 
                ADD COLUMN `oauth_client_secret` TEXT NULL DEFAULT NULL AFTER `oauth_client_id`');
        }
        
        if (!$CI->db->field_exists('oauth_tenant_id', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` 
                ADD COLUMN `oauth_tenant_id` VARCHAR(255) NULL DEFAULT NULL AFTER `oauth_client_secret`');
        }
        
        log_activity('Mailbox Migration 213: Added per-staff folder mappings and OAuth credentials');
    }

    public function down()
    {
        $CI = &get_instance();
        
        // Remove folder mapping columns
        $columns = ['imap_sent_folder', 'imap_draft_folder', 'imap_trash_folder', 'imap_spam_folder', 'imap_archive_folder'];
        foreach ($columns as $column) {
            if ($CI->db->field_exists($column, db_prefix() . 'staff')) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` DROP COLUMN `' . $column . '`');
            }
        }
        
        // Remove OAuth credential columns
        $oauth_columns = ['oauth_client_id', 'oauth_client_secret', 'oauth_tenant_id'];
        foreach ($oauth_columns as $column) {
            if ($CI->db->field_exists($column, db_prefix() . 'staff')) {
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` DROP COLUMN `' . $column . '`');
            }
        }
    }
}
