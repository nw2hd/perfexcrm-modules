<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Migration for Mailbox Module v2.1.0
 * UI/UX Improvements and Settings Optimization
 */
class Migration_Version_209 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        
        // Set mailbox_enabled to 1 by default for all installations
        $enabled = get_option('mailbox_enabled');
        if (empty($enabled)) {
            update_option('mailbox_enabled', '1');
        }
        
        // Remove deprecated preview pane settings (now always available via toggle button)
        // The preview pane is now a user interface toggle, not a module setting
        
        // Set default values for important settings if not already set
        $settings_defaults = [
            'mailbox_fetch_only_unread' => '1',
            'mailbox_allow_html_content' => '1',
            'mailbox_auto_mark_read_preview' => '1',
            'mailbox_max_emails_per_sync' => '100',
            'mailbox_sync_timeout' => '300',
            'mailbox_keyboard_shortcuts_enabled' => '1',
        ];
        
        foreach ($settings_defaults as $setting => $default_value) {
            $current_value = get_option($setting);
            if ($current_value === '' || $current_value === null) {
                update_option($setting, $default_value);
            }
        }
        
        // Log migration
        log_activity('Mailbox Module upgraded to v2.1.0 - UI/UX Improvements');
    }
    
    public function down()
    {
        // Nothing to rollback - settings updates are safe
    }
}
