<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_215 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        
        // Add per-staff SMTP configuration columns to tblstaff
        if (!$CI->db->field_exists('smtp_host', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD `smtp_host` VARCHAR(255) NULL DEFAULT NULL AFTER `enable_html`;');
        }
        
        if (!$CI->db->field_exists('smtp_port', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD `smtp_port` INT(11) NULL DEFAULT NULL AFTER `smtp_host`;');
        }
        
        if (!$CI->db->field_exists('smtp_encryption', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD `smtp_encryption` VARCHAR(10) NULL DEFAULT NULL COMMENT "ssl, tls, or none" AFTER `smtp_port`;');
        }
        
        if (!$CI->db->field_exists('smtp_username', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD `smtp_username` VARCHAR(255) NULL DEFAULT NULL AFTER `smtp_encryption`;');
        }
        
        if (!$CI->db->field_exists('smtp_password', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD `smtp_password` TEXT NULL DEFAULT NULL COMMENT "Encrypted" AFTER `smtp_username`;');
        }
    }

    public function down()
    {
        $CI = &get_instance();
        
        // Remove per-staff SMTP columns (reverse migration)
        if ($CI->db->field_exists('smtp_password', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` DROP COLUMN `smtp_password`;');
        }
        
        if ($CI->db->field_exists('smtp_username', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` DROP COLUMN `smtp_username`;');
        }
        
        if ($CI->db->field_exists('smtp_encryption', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` DROP COLUMN `smtp_encryption`;');
        }
        
        if ($CI->db->field_exists('smtp_port', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` DROP COLUMN `smtp_port`;');
        }
        
        if ($CI->db->field_exists('smtp_host', db_prefix() . 'staff')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` DROP COLUMN `smtp_host`;');
        }
    }
}
