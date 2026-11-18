<?php defined('BASEPATH') or exit('No direct script access allowed');

// ADMIN ONLY - Check if user is admin
if (!is_admin()) {
    show_404();
    return;
}

$enabled      = get_option('mailbox_enabled');
$imap_server  = get_option('mailbox_imap_server');
$encryption   = get_option('mailbox_encryption');
$folder_scan  = get_option('mailbox_folder_scan');
$check_every  = get_option('mailbox_check_every');
$unseen_email = get_option('mailbox_only_loop_on_unseen_emails');
$enable_html  = get_option('mailbox_enable_html');

?>

<div class="row">
    <div class="col-md-12">
        <!-- Enable Mailbox module setting removed - use module activation/deactivation instead -->
        <?php 
        // Always ensure mailbox is enabled when settings are accessed
        if (get_option('mailbox_enabled') !== '1') {
            update_option('mailbox_enabled', '1');
        }
        ?>
        <?php $threading_enabled = get_option('mailbox_threading_enabled'); ?>
        <div class="form-group"> 
            <label for="mailbox_threading_enabled" class="control-label clearfix">
                <?php echo _l('mailbox_threading_enabled'); ?>
            </label>
            <p class="text-muted"><?php echo _l('mailbox_threading_enabled_description'); ?></p>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="y_opt_1_mailbox_threading_enabled" name="settings[mailbox_threading_enabled]" value="1" <?php if ('1' == $threading_enabled) { echo ' checked'; } ?>>
                <label for="y_opt_1_mailbox_threading_enabled"><?php echo _l('settings_yes'); ?></label>
            </div> 
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="y_opt_2_mailbox_threading_enabled" name="settings[mailbox_threading_enabled]" value="0" <?php if ('0' == $threading_enabled) { echo ' checked'; } ?>>
                <label for="y_opt_2_mailbox_threading_enabled">
                    <?php echo _l('settings_no'); ?>
                </label>
            </div>
        </div> 
        <hr/>

		<?php echo render_input('settings[mailbox_check_every]', 'leads_email_integration_check_every', $check_every, 'number', ['min'=>hooks()->apply_filters('leads_email_integration_check_every', 3), 'data-ays-ignore'=>true]); ?>
        <hr/>
        <h4><?php echo _l('mailbox_feature_settings'); ?></h4>
        <p class="text-muted"><?php echo _l('mailbox_feature_settings_description'); ?></p>
        
        <?php 
        // Get options with defaults for smooth behavior
        $archive_enabled = get_option('mailbox_archive_enabled');
        if ($archive_enabled === '' || $archive_enabled === null) {
            $archive_enabled = '1'; // Enabled by default - useful feature
        }
        
        $snooze_enabled = get_option('mailbox_snooze_enabled');
        if ($snooze_enabled === '' || $snooze_enabled === null) {
            $snooze_enabled = '1'; // Enabled by default - useful feature
        }
        
        $keyboard_shortcuts_enabled = get_option('mailbox_keyboard_shortcuts_enabled');
        if ($keyboard_shortcuts_enabled === '' || $keyboard_shortcuts_enabled === null) {
            $keyboard_shortcuts_enabled = '1'; // Enabled by default - power users love shortcuts
        }
        
        $preview_pane_enabled = get_option('mailbox_preview_pane_enabled');
        if ($preview_pane_enabled === '' || $preview_pane_enabled === null) {
            $preview_pane_enabled = '0'; // Disabled by default - can be overwhelming, let users enable it
        }
        ?>
        
        <div class="form-group">
            <label for="mailbox_archive_enabled" class="control-label clearfix">
                <?php echo _l('mailbox_archive_enabled'); ?>
            </label>
            <p class="text-muted"><?php echo _l('mailbox_archive_enabled_description'); ?></p>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="y_opt_1_mailbox_archive_enabled" name="settings[mailbox_archive_enabled]" value="1" <?php if ('1' == $archive_enabled) { echo ' checked'; } ?>>
                <label for="y_opt_1_mailbox_archive_enabled"><?php echo _l('settings_yes'); ?></label>
            </div>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="y_opt_2_mailbox_archive_enabled" name="settings[mailbox_archive_enabled]" value="0" <?php if ('0' == $archive_enabled) { echo ' checked'; } ?>>
                <label for="y_opt_2_mailbox_archive_enabled"><?php echo _l('settings_no'); ?></label>
            </div>
        </div>
        <hr/>
        
        <div class="form-group">
            <label for="mailbox_snooze_enabled" class="control-label clearfix">
                <?php echo _l('mailbox_snooze_enabled'); ?>
            </label>
            <p class="text-muted"><?php echo _l('mailbox_snooze_enabled_description'); ?></p>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="y_opt_1_mailbox_snooze_enabled" name="settings[mailbox_snooze_enabled]" value="1" <?php if ('1' == $snooze_enabled) { echo ' checked'; } ?>>
                <label for="y_opt_1_mailbox_snooze_enabled"><?php echo _l('settings_yes'); ?></label>
            </div>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="y_opt_2_mailbox_snooze_enabled" name="settings[mailbox_snooze_enabled]" value="0" <?php if ('0' == $snooze_enabled) { echo ' checked'; } ?>>
                <label for="y_opt_2_mailbox_snooze_enabled"><?php echo _l('settings_no'); ?></label>
            </div>
        </div>
        <hr/>
        
        <div class="form-group">
            <label for="mailbox_keyboard_shortcuts_enabled" class="control-label clearfix">
                <?php echo _l('mailbox_keyboard_shortcuts_enabled'); ?>
            </label>
            <p class="text-muted"><?php echo _l('mailbox_keyboard_shortcuts_enabled_description'); ?></p>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="y_opt_1_mailbox_keyboard_shortcuts_enabled" name="settings[mailbox_keyboard_shortcuts_enabled]" value="1" <?php if ('1' == $keyboard_shortcuts_enabled) { echo ' checked'; } ?>>
                <label for="y_opt_1_mailbox_keyboard_shortcuts_enabled"><?php echo _l('settings_yes'); ?></label>
            </div>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="y_opt_2_mailbox_keyboard_shortcuts_enabled" name="settings[mailbox_keyboard_shortcuts_enabled]" value="0" <?php if ('0' == $keyboard_shortcuts_enabled) { echo ' checked'; } ?>>
                <label for="y_opt_2_mailbox_keyboard_shortcuts_enabled"><?php echo _l('settings_no'); ?></label>
            </div>
        </div>
        <hr/>
        
        <!-- Preview Pane Enable setting removed - now controlled by UI toggle button -->
        
        <?php 
        // Get options with defaults for smooth behavior
        $auto_mark_read = get_option('mailbox_auto_mark_read_on_preview');
        if ($auto_mark_read === '' || $auto_mark_read === null) {
            $auto_mark_read = '1'; // Enabled by default - smooth UX, marks as read when previewed
        }
        
        $default_preview = get_option('mailbox_default_preview_pane');
        if ($default_preview === '' || $default_preview === null) {
            $default_preview = '0'; // Disabled by default - matches preview_pane_enabled default
        }
        ?>
        <div class="form-group">
            <label for="mailbox_auto_mark_read_on_preview" class="control-label clearfix">
                <?php echo _l('mailbox_auto_mark_read_on_preview'); ?>
            </label>
            <p class="text-muted"><?php echo _l('mailbox_auto_mark_read_on_preview_description'); ?></p>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="y_opt_1_mailbox_auto_mark_read_on_preview" name="settings[mailbox_auto_mark_read_on_preview]" value="1" <?php if ('1' == $auto_mark_read) { echo ' checked'; } ?>>
                <label for="y_opt_1_mailbox_auto_mark_read_on_preview"><?php echo _l('settings_yes'); ?></label>
            </div>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="y_opt_2_mailbox_auto_mark_read_on_preview" name="settings[mailbox_auto_mark_read_on_preview]" value="0" <?php if ('0' == $auto_mark_read) { echo ' checked'; } ?>>
                <label for="y_opt_2_mailbox_auto_mark_read_on_preview"><?php echo _l('settings_no'); ?></label>
            </div>
        </div>
        <hr/>
        
        <!-- Default Preview Pane State removed - controlled by user's UI toggle preference -->
        
        <h4><?php echo _l('mailbox_sync_settings'); ?></h4>
        <p class="text-muted"><?php echo _l('mailbox_sync_settings_description'); ?></p>
        
        <?php 
        $max_emails = get_option('mailbox_max_emails_per_sync') ?: '0';
        $sync_timeout = get_option('mailbox_sync_timeout') ?: '300';
        ?>
        <?php echo render_input('settings[mailbox_max_emails_per_sync]', 'mailbox_max_emails_per_sync', $max_emails, 'number', ['min' => 0, 'data-ays-ignore' => true, 'placeholder' => '0 = unlimited']); ?>
        <p class="text-muted"><small><?php echo _l('mailbox_max_emails_per_sync_description'); ?></small></p>
        <br>
        <?php echo render_input('settings[mailbox_sync_timeout]', 'mailbox_sync_timeout', $sync_timeout, 'number', ['min' => 30, 'max' => 3600, 'data-ays-ignore' => true]); ?>
        <p class="text-muted"><small><?php echo _l('mailbox_sync_timeout_description'); ?></small></p>
        <hr/>
        
        <h4><?php echo _l('mailbox_ui_settings'); ?></h4>
        <p class="text-muted"><?php echo _l('mailbox_ui_settings_description'); ?></p>
        
        <?php 
        $items_per_page = get_option('mailbox_items_per_page') ?: '25';
        $auto_refresh = get_option('mailbox_auto_refresh_interval') ?: '0';
        $show_attachment_icons = get_option('mailbox_show_attachment_icons') ?: '1';
        $show_avatars = get_option('mailbox_show_avatar_images') ?: '1';
        ?>
        <!-- Emails per Page removed - controlled by DataTables page length dropdown -->
        <?php echo render_input('settings[mailbox_auto_refresh_interval]', 'mailbox_auto_refresh_interval', $auto_refresh, 'number', ['min' => 0, 'max' => 3600, 'data-ays-ignore' => true, 'placeholder' => '0 = disabled']); ?>
        <p class="text-muted"><small><?php echo _l('mailbox_auto_refresh_interval_description'); ?></small></p>
        <br>
        <!-- Show Attachment Icons removed - icons not properly supported -->
        <div class="form-group">
            <label for="mailbox_show_avatar_images" class="control-label clearfix">
                <?php echo _l('mailbox_show_avatar_images'); ?>
            </label>
            <p class="text-muted"><?php echo _l('mailbox_show_avatar_images_description'); ?></p>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="y_opt_1_mailbox_show_avatar_images" name="settings[mailbox_show_avatar_images]" value="1" <?php if ('1' == $show_avatars) { echo ' checked'; } ?>>
                <label for="y_opt_1_mailbox_show_avatar_images"><?php echo _l('settings_yes'); ?></label>
            </div>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="y_opt_2_mailbox_show_avatar_images" name="settings[mailbox_show_avatar_images]" value="0" <?php if ('0' == $show_avatars) { echo ' checked'; } ?>>
                <label for="y_opt_2_mailbox_show_avatar_images"><?php echo _l('settings_no'); ?></label>
            </div>
        </div>
        <hr/>
        
        <h4><?php echo _l('mailbox_advanced_features'); ?></h4>
        <p class="text-muted"><?php echo _l('mailbox_advanced_features_description'); ?></p>
        
        <?php 
        // Get settings - use proper null check (not ?:) because '0' is a valid value
        $enable_rules = get_option('mailbox_enable_rules');
        if ($enable_rules === '' || $enable_rules === false || $enable_rules === null) {
            $enable_rules = '1'; // Default: enabled
        }
        
        $enable_multiple_tags = get_option('mailbox_enable_multiple_tags');
        if ($enable_multiple_tags === '' || $enable_multiple_tags === false || $enable_multiple_tags === null) {
            $enable_multiple_tags = '1'; // Default: enabled
        }
        
        $enable_advanced_search = get_option('mailbox_enable_advanced_search');
        if ($enable_advanced_search === '' || $enable_advanced_search === false || $enable_advanced_search === null) {
            $enable_advanced_search = '1'; // Default: enabled
        }
        
        $enable_bulk_actions = get_option('mailbox_enable_bulk_actions');
        if ($enable_bulk_actions === '' || $enable_bulk_actions === false || $enable_bulk_actions === null) {
            $enable_bulk_actions = '1'; // Default: enabled
        }
        ?>
        <div class="form-group">
            <label for="mailbox_enable_rules" class="control-label clearfix">
                <?php echo _l('mailbox_enable_rules'); ?>
            </label>
            <p class="text-muted"><?php echo _l('mailbox_enable_rules_description'); ?></p>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="y_opt_1_mailbox_enable_rules" name="settings[mailbox_enable_rules]" value="1" <?php if ('1' == $enable_rules) { echo ' checked'; } ?>>
                <label for="y_opt_1_mailbox_enable_rules"><?php echo _l('settings_yes'); ?></label>
            </div>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="y_opt_2_mailbox_enable_rules" name="settings[mailbox_enable_rules]" value="0" <?php if ('0' == $enable_rules) { echo ' checked'; } ?>>
                <label for="y_opt_2_mailbox_enable_rules"><?php echo _l('settings_no'); ?></label>
            </div>
        </div>
        <hr/>
        <div class="form-group">
            <label for="mailbox_enable_multiple_tags" class="control-label clearfix">
                <?php echo _l('mailbox_enable_multiple_tags'); ?>
            </label>
            <p class="text-muted"><?php echo _l('mailbox_enable_multiple_tags_description'); ?></p>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="y_opt_1_mailbox_enable_multiple_tags" name="settings[mailbox_enable_multiple_tags]" value="1" <?php if ('1' == $enable_multiple_tags) { echo ' checked'; } ?>>
                <label for="y_opt_1_mailbox_enable_multiple_tags"><?php echo _l('settings_yes'); ?></label>
            </div>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="y_opt_2_mailbox_enable_multiple_tags" name="settings[mailbox_enable_multiple_tags]" value="0" <?php if ('0' == $enable_multiple_tags) { echo ' checked'; } ?>>
                <label for="y_opt_2_mailbox_enable_multiple_tags"><?php echo _l('settings_no'); ?></label>
            </div>
        </div>
        <hr/>
        <div class="form-group">
            <label for="mailbox_enable_advanced_search" class="control-label clearfix">
                <?php echo _l('mailbox_enable_advanced_search'); ?>
            </label>
            <p class="text-muted"><?php echo _l('mailbox_enable_advanced_search_description'); ?></p>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="y_opt_1_mailbox_enable_advanced_search" name="settings[mailbox_enable_advanced_search]" value="1" <?php if ('1' == $enable_advanced_search) { echo ' checked'; } ?>>
                <label for="y_opt_1_mailbox_enable_advanced_search"><?php echo _l('settings_yes'); ?></label>
            </div>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="y_opt_2_mailbox_enable_advanced_search" name="settings[mailbox_enable_advanced_search]" value="0" <?php if ('0' == $enable_advanced_search) { echo ' checked'; } ?>>
                <label for="y_opt_2_mailbox_enable_advanced_search"><?php echo _l('settings_no'); ?></label>
            </div>
        </div>
        <hr/>
        <div class="form-group">
            <label for="mailbox_enable_bulk_actions" class="control-label clearfix">
                <?php echo _l('mailbox_enable_bulk_actions'); ?>
            </label>
            <p class="text-muted"><?php echo _l('mailbox_enable_bulk_actions_description'); ?></p>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="y_opt_1_mailbox_enable_bulk_actions" name="settings[mailbox_enable_bulk_actions]" value="1" <?php if ('1' == $enable_bulk_actions) { echo ' checked'; } ?>>
                <label for="y_opt_1_mailbox_enable_bulk_actions"><?php echo _l('settings_yes'); ?></label>
            </div>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="y_opt_2_mailbox_enable_bulk_actions" name="settings[mailbox_enable_bulk_actions]" value="0" <?php if ('0' == $enable_bulk_actions) { echo ' checked'; } ?>>
                <label for="y_opt_2_mailbox_enable_bulk_actions"><?php echo _l('settings_no'); ?></label>
            </div>
        </div>
        <hr/>
        
        <h4><i class="fa fa-clock"></i> <?php echo _l('mailbox_cron_job_required'); ?></h4>
        <div class="alert alert-warning">
            <p><strong><i class="fa fa-exclamation-triangle"></i> IMPORTANT:</strong> <?php echo _l('mailbox_cron_job_description'); ?></p>
            <p class="mtop15"><strong>Cron Command:</strong></p>
            <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; margin-bottom: 15px;"><code>*/5 * * * * php <?php echo FCPATH; ?>index.php cron/index</code></pre>
            <p class="text-muted"><small>This command should run every 5 minutes. Adjust the frequency based on your needs.</small></p>
            <div style="margin-top: 15px;">
                <a href="<?php echo admin_url('settings?group=cronjob'); ?>" class="btn btn-info">
                    <i class="fa fa-cog"></i> <?php echo _l('mailbox_cronjob_settings'); ?>
                </a>
            </div>
        </div>
    </div>
</div>
</div>