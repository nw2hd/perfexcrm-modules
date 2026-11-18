<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Migration: Move personal preferences from global to per-staff
 * - "Import Only Unread Emails" (mailbox_only_loop_on_unseen_emails)
 * - "Enable HTML Email Rendering" (mailbox_enable_html)
 * Version: 2.1.4
 * Date: 2026-01-22
 */
class Migration_Version_214 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        
        // Add only_loop_on_unseen_emails column to tblstaff table
        if (!$CI->db->field_exists('only_loop_on_unseen_emails', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` 
                ADD COLUMN `only_loop_on_unseen_emails` TINYINT(1) NOT NULL DEFAULT 1 AFTER `oauth_tenant_id`');
        }
        
        // Add enable_html column to tblstaff table
        if (!$CI->db->field_exists('enable_html', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` 
                ADD COLUMN `enable_html` TINYINT(1) NOT NULL DEFAULT 1 AFTER `only_loop_on_unseen_emails`');
        }
        
        // Migrate existing global "Import Only Unread" setting to all staff members
        $global_unseen = get_option('mailbox_only_loop_on_unseen_emails');
        if ($global_unseen !== false && $global_unseen !== '') {
            $CI->db->query('UPDATE `' . db_prefix() . 'staff` 
                SET `only_loop_on_unseen_emails` = ' . (int)$global_unseen);
        }
        
        // Migrate existing global "Enable HTML" setting to all staff members
        $global_html = get_option('mailbox_enable_html');
        if ($global_html !== false && $global_html !== '') {
            $CI->db->query('UPDATE `' . db_prefix() . 'staff` 
                SET `enable_html` = ' . (int)$global_html);
        }
    }

    public function down()
    {
        $CI = &get_instance();
        
        // Remove only_loop_on_unseen_emails column from tblstaff table
        if ($CI->db->field_exists('only_loop_on_unseen_emails', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` 
                DROP COLUMN `only_loop_on_unseen_emails`');
        }
        
        // Remove enable_html column from tblstaff table
        if ($CI->db->field_exists('enable_html', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` 
                DROP COLUMN `enable_html`');
        }
    }
}
