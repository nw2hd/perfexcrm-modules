<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_216 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        
        // Fix mail_password column to TEXT to support encrypted passwords
        // Encrypted passwords can be 200+ characters, VARCHAR might truncate
        log_activity('[MAILBOX MIGRATION 216] Checking mail_password column length...');
        
        $query = $CI->db->query("SHOW COLUMNS FROM `" . db_prefix() . "staff` LIKE 'mail_password'");
        $column = $query->row();
        
        if ($column) {
            log_activity('[MAILBOX MIGRATION 216] Current mail_password type: ' . $column->Type);
            
            // Change to TEXT if it's not already TEXT or LONGTEXT
            if (strpos(strtolower($column->Type), 'text') === false) {
                log_activity('[MAILBOX MIGRATION 216] Converting mail_password to TEXT...');
                $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` MODIFY COLUMN `mail_password` TEXT NULL COMMENT "Encrypted with AES-256-CBC";');
                log_activity('[MAILBOX MIGRATION 216] mail_password converted to TEXT successfully');
            } else {
                log_activity('[MAILBOX MIGRATION 216] mail_password is already TEXT type, no change needed');
            }
        } else {
            log_activity('[MAILBOX MIGRATION 216] mail_password column does not exist, will be created by install.php');
        }
    }

    public function down()
    {
        // Don't revert - keeping TEXT is safe
    }
}
