<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Migration: Add per-staff IMAP configuration fields
 * 
 * Allows each staff member to configure their own IMAP server settings
 * independently, enabling multi-provider support (Gmail, Outlook, custom servers)
 * 
 * Version: 2.1.2
 */
class Migration_Version_212 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        
        // Add IMAP configuration columns to staff table
        if (!$CI->db->field_exists('imap_server', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` 
                ADD COLUMN `imap_server` VARCHAR(255) NULL DEFAULT NULL COMMENT "Staff-specific IMAP server" AFTER `mail_password`');
        }
        
        if (!$CI->db->field_exists('imap_port', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` 
                ADD COLUMN `imap_port` INT(5) NULL DEFAULT 993 COMMENT "Staff-specific IMAP port" AFTER `imap_server`');
        }
        
        if (!$CI->db->field_exists('imap_encryption', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` 
                ADD COLUMN `imap_encryption` VARCHAR(10) NULL DEFAULT "ssl" COMMENT "Staff-specific IMAP encryption (ssl/tls/none)" AFTER `imap_port`');
        }
        
        log_activity('Mailbox Migration 212: Added per-staff IMAP configuration fields');
    }

    public function down()
    {
        $CI = &get_instance();
        
        // Remove columns if rolling back
        if ($CI->db->field_exists('imap_server', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` DROP COLUMN `imap_server`');
        }
        
        if ($CI->db->field_exists('imap_port', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` DROP COLUMN `imap_port`');
        }
        
        if ($CI->db->field_exists('imap_encryption', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` DROP COLUMN `imap_encryption`');
        }
    }
}
