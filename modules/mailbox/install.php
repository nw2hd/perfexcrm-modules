<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
 * The file is responsible for handing the mailbox installation
 */

add_option('mailbox_enabled', 1);
add_option('mailbox_imap_server', '');
add_option('mailbox_encryption', '');
add_option('mailbox_folder_scan', 'Inbox');
add_option('mailbox_check_every', 3);
add_option('mailbox_only_loop_on_unseen_emails', 1);
add_option('mailbox_enable_html', 0);
add_option('mailbox_threading_enabled', 1);

if (!$CI->db->table_exists(db_prefix().'mail_inbox')) {
  $CI->db->query('CREATE TABLE `'.db_prefix()."mail_inbox` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `uid` INT DEFAULT NULL COMMENT 'IMAP UID for syncing read status',
    `from_staff_id` int(11) NOT NULL DEFAULT '0',
    `to_staff_id` int(11) NOT NULL DEFAULT '0',
    `to` varchar(500) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
    `cc` varchar(500) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
    `bcc` varchar(500) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
    `sender_name` varchar(150) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
    `subject` mediumtext CHARACTER SET utf8 COLLATE utf8_unicode_ci,
    `body` mediumtext CHARACTER SET utf8 COLLATE utf8_unicode_ci,
    `has_attachment` tinyint(1) NOT NULL DEFAULT '0',
    `date_received` datetime NOT NULL,
    `read` tinyint(1) NOT NULL DEFAULT '0',
    `folder` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'inbox',
    `stared` tinyint(1) NOT NULL DEFAULT '0',
    `important` tinyint(1) NOT NULL DEFAULT '0',
    `trash` tinyint(1) NOT NULL DEFAULT '0',
    `from_email` varchar(150) DEFAULT NULL,
    `message_id` VARCHAR(255) DEFAULT NULL,
    `in_reply_to` VARCHAR(255) DEFAULT NULL,
    `references` TEXT DEFAULT NULL,
    `thread_id` VARCHAR(255) DEFAULT NULL,
    `imap_folder` VARCHAR(100) DEFAULT 'INBOX',
    `archived` TINYINT(1) DEFAULT 0,
    `snoozed_until` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_uid` (`uid`),
    INDEX `idx_thread_id` (`thread_id`),
    INDEX `idx_message_id` (`message_id`),
    INDEX `idx_archived` (`archived`),
    INDEX `idx_snoozed_until` (`snoozed_until`)
  ) ENGINE=InnoDB DEFAULT CHARSET=".$CI->db->char_set.';');
}

if (!$CI->db->table_exists(db_prefix().'mail_outbox')) {
  $CI->db->query('CREATE TABLE `'.db_prefix()."mail_outbox` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `sender_staff_id` int(11) NOT NULL DEFAULT '0',
    `to` varchar(500) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
    `cc` varchar(500) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
    `bcc` varchar(500) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
    `sender_name` varchar(150) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
    `subject` mediumtext CHARACTER SET utf8 COLLATE utf8_unicode_ci,
    `body` mediumtext CHARACTER SET utf8 COLLATE utf8_unicode_ci,
    `has_attachment` tinyint(1) NOT NULL DEFAULT '0',
    `date_sent` datetime NOT NULL,
    `stared` tinyint(1) NOT NULL DEFAULT '0',
    `important` tinyint(1) NOT NULL DEFAULT '0',
    `trash` tinyint(1) NOT NULL DEFAULT '0',
    `reply_from_id` int(11) DEFAULT NULL,
    `reply_type` varchar(45) NOT NULL DEFAULT 'inbox',
    `message_id` VARCHAR(255) DEFAULT NULL,
    `in_reply_to` VARCHAR(255) DEFAULT NULL,
    `references` TEXT DEFAULT NULL,
    `thread_id` VARCHAR(255) DEFAULT NULL,
    `imap_folder` VARCHAR(100) DEFAULT NULL,
    `archived` TINYINT(1) DEFAULT 0,
    `snoozed_until` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_thread_id` (`thread_id`),
    INDEX `idx_message_id` (`message_id`),
    INDEX `idx_archived` (`archived`),
    INDEX `idx_snoozed_until` (`snoozed_until`)
  ) ENGINE=InnoDB DEFAULT CHARSET=".$CI->db->char_set.';');
}

if (!$CI->db->table_exists(db_prefix().'mail_attachment')) {
  $CI->db->query('CREATE TABLE `'.db_prefix()."mail_attachment` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `mail_id` int(11) NOT NULL,
    `file_name` varchar(191) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
    `file_type` varchar(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
    `type` varchar(45) NOT NULL DEFAULT 'inbox',
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=".$CI->db->char_set.';');
}

if (!$CI->db->table_exists(db_prefix() . 'mail_conversation')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . 'mail_conversation` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `outbox_id` int(255) DEFAULT NULL,
    `lead_id` int(255) DEFAULT NULL,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

if (!$CI->db->table_exists(db_prefix().'mail_tags')) {
  $CI->db->query('CREATE TABLE `'.db_prefix()."mail_tags` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` varchar(127) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
    `color` varchar(127) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
    `active` tinyint(1) NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=".$CI->db->char_set.';');
}

if (!$CI->db->table_exists(db_prefix().'mail_auto_replies')) {
  $CI->db->query('CREATE TABLE `'.db_prefix()."mail_auto_replies` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` varchar(127) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
    `pattern` VARCHAR(250) NOT NULL,
    `replyid` int(11) UNSIGNED,
    `body` LONGTEXT NULL,
    `active` tinyint(1) NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=".$CI->db->char_set.';');
}

if (!$CI->db->table_exists(db_prefix() . 'mail_clients')) {
  $CI->db->query('CREATE TABLE `' . db_prefix() . 'mail_clients` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `outbox_id` int(255) DEFAULT NULL,
    `inbox_id` int(255) DEFAULT NULL,
    `client_id` int(255) DEFAULT NULL,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

if (!$CI->db->field_exists('draft', 'mail_outbox')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_outbox` ADD COLUMN `draft` tinyint(1) NOT NULL DEFAULT 0;');
}

if (!$CI->db->field_exists('tagid', 'mail_outbox')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_outbox` ADD COLUMN `tagid` int(11);');
}

if (!$CI->db->field_exists('tagid', 'mail_inbox')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` ADD COLUMN `tagid` int(11);');
}

if (!$CI->db->field_exists('templateid', 'mail_outbox')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_outbox` ADD COLUMN `templateid` int(11);');
}

if (!$CI->db->field_exists('templateid', 'mail_inbox')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` ADD COLUMN `templateid` int(11);');
}

if (!$CI->db->field_exists('scheduled_at', 'mail_outbox')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_outbox` ADD COLUMN `scheduled_at` datetime;');
}

if (!$CI->db->field_exists('scheduled_status', 'mail_outbox')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_outbox` ADD COLUMN `scheduled_status` VARCHAR(127)  DEFAULT "";');
}

if (!$CI->db->field_exists('assigned_clients', 'mail_inbox')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` ADD COLUMN `assigned_clients` VARCHAR(511)  DEFAULT "";');
}

if (!$CI->db->field_exists('assigned_clients', 'mail_outbox')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_outbox` ADD COLUMN `assigned_clients` VARCHAR(511)  DEFAULT "";');
}

if (!$CI->db->field_exists('taskid', 'mail_inbox')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` ADD COLUMN `taskid` int(11);');
}

if (!$CI->db->field_exists('taskid', 'mail_outbox')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_outbox` ADD COLUMN `taskid` int(11);');
}

if (!$CI->db->field_exists('ticketid', 'mail_inbox')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` ADD COLUMN `ticketid` int(11);');
}

if (!$CI->db->field_exists('ticketid', 'mail_outbox')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_outbox` ADD COLUMN `ticketid` int(11);');
}

if (!$CI->db->field_exists('conversationid', 'mail_inbox')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` ADD COLUMN `conversationid` int(11);');
}

if (!$CI->db->field_exists('conversationid', 'mail_outbox')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_outbox` ADD COLUMN `conversationid` int(11);');
}

if (!$CI->db->field_exists('pattern', 'mail_auto_replies')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_auto_replies` ADD COLUMN `pattern` VARCHAR(250) NOT NULL;');
}

if ($CI->db->field_exists('subject', 'mail_auto_replies')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_auto_replies` DROP COLUMN `subject`;');
}

if (!$CI->db->field_exists('body', 'mail_auto_replies')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_auto_replies` ADD COLUMN `body` LONGTEXT NULL;');
}

if (!$CI->db->field_exists('color', 'mail_tags')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_tags` ADD COLUMN `color` VARCHAR(127) NOT NULL;');
}

if (!$CI->db->field_exists('mail_password', 'staff')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff`  ADD COLUMN `mail_password` TEXT NULL COMMENT "Encrypted with AES-256-CBC"');
}

if (!$CI->db->field_exists('mail_signature', 'staff')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff`  ADD COLUMN `mail_signature` TEXT NULL');
}

if (!$CI->db->field_exists('last_email_check', 'staff')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff`  ADD COLUMN `last_email_check` VARCHAR(50) NULL');
}

if (!$CI->db->field_exists('inbox_id', 'mail_conversation')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_conversation` ADD COLUMN `inbox_id` int(255) DEFAULT NULL');
}

// Add OAuth fields to staff table (for new installations)
if (!$CI->db->field_exists('auth_method', db_prefix() . 'staff')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `auth_method` ENUM("password", "oauth2") DEFAULT "password"');
}

if (!$CI->db->field_exists('oauth_provider', db_prefix() . 'staff')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `oauth_provider` VARCHAR(50) DEFAULT NULL');
}

if (!$CI->db->field_exists('oauth_access_token', db_prefix() . 'staff')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `oauth_access_token` TEXT DEFAULT NULL');
}

if (!$CI->db->field_exists('oauth_refresh_token', db_prefix() . 'staff')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `oauth_refresh_token` TEXT DEFAULT NULL');
}

if (!$CI->db->field_exists('oauth_token_expires_at', db_prefix() . 'staff')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `oauth_token_expires_at` DATETIME DEFAULT NULL');
}

if (!$CI->db->field_exists('last_email_uid', db_prefix() . 'staff')) {
  $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `last_email_uid` INT DEFAULT 0');
}

// Add indexes for performance (for new installations)
// Check if indexes exist before adding to avoid errors
try {
  $indexes = $CI->db->query("SHOW INDEXES FROM `" . db_prefix() . "mail_inbox` WHERE Key_name = 'idx_to_staff_read_trash'")->result();
  if (empty($indexes)) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` ADD INDEX `idx_to_staff_read_trash` (`to_staff_id`, `read`, `trash`)');
  }
} catch (Exception $e) {
  // Index might already exist, continue
}

try {
  $indexes = $CI->db->query("SHOW INDEXES FROM `" . db_prefix() . "mail_inbox` WHERE Key_name = 'idx_date_received'")->result();
  if (empty($indexes)) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` ADD INDEX `idx_date_received` (`date_received`)');
  }
} catch (Exception $e) {
  // Index might already exist, continue
}

try {
  $indexes = $CI->db->query("SHOW INDEXES FROM `" . db_prefix() . "mail_outbox` WHERE Key_name = 'idx_sender_date'")->result();
  if (empty($indexes)) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_outbox` ADD INDEX `idx_sender_date` (`sender_staff_id`, `date_sent`)');
  }
} catch (Exception $e) {
  // Index might already exist, continue
}

try {
  $indexes = $CI->db->query("SHOW INDEXES FROM `" . db_prefix() . "mail_attachment` WHERE Key_name = 'idx_mail_type'")->result();
  if (empty($indexes)) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_attachment` ADD INDEX `idx_mail_type` (`mail_id`, `type`)');
  }
} catch (Exception $e) {
  // Index might already exist, continue
}

// Add OAuth settings (for new installations)
add_option('mailbox_gmail_client_id', '');
add_option('mailbox_gmail_client_secret', '');
add_option('mailbox_outlook_client_id', '');
add_option('mailbox_outlook_client_secret', '');
add_option('mailbox_outlook_tenant_id', 'common');
add_option('mailbox_encryption_key', bin2hex(random_bytes(32))); // Generate encryption key

// Add new tables for v2.0.8+ features
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
  ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

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
  ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

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
  ) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');
}

// Add new options
add_option('mailbox_imap_sent_folder', '');
add_option('mailbox_imap_draft_folder', '');
add_option('mailbox_imap_trash_folder', '');
add_option('mailbox_imap_spam_folder', '');
add_option('mailbox_imap_archive_folder', '');
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

// Add per-staff IMAP configuration columns (v2.1.2+)
if (!$CI->db->field_exists('imap_server', db_prefix() . 'staff')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `imap_server` VARCHAR(255) NULL DEFAULT NULL AFTER `mail_password`');
}
if (!$CI->db->field_exists('imap_port', db_prefix() . 'staff')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `imap_port` INT(5) NULL DEFAULT 993 AFTER `imap_server`');
}
if (!$CI->db->field_exists('imap_encryption', db_prefix() . 'staff')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `imap_encryption` VARCHAR(10) NULL DEFAULT "ssl" AFTER `imap_port`');
}

// Add per-staff IMAP folder mappings (v2.1.3+)
if (!$CI->db->field_exists('imap_folder_scan', db_prefix() . 'staff')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `imap_folder_scan` VARCHAR(255) NULL DEFAULT "INBOX" AFTER `imap_encryption`');
}
if (!$CI->db->field_exists('imap_sent_folder', db_prefix() . 'staff')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `imap_sent_folder` VARCHAR(255) NULL DEFAULT NULL AFTER `imap_folder_scan`');
}
if (!$CI->db->field_exists('imap_draft_folder', db_prefix() . 'staff')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `imap_draft_folder` VARCHAR(255) NULL DEFAULT NULL AFTER `imap_sent_folder`');
}
if (!$CI->db->field_exists('imap_trash_folder', db_prefix() . 'staff')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `imap_trash_folder` VARCHAR(255) NULL DEFAULT NULL AFTER `imap_draft_folder`');
}
if (!$CI->db->field_exists('imap_spam_folder', db_prefix() . 'staff')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `imap_spam_folder` VARCHAR(255) NULL DEFAULT NULL AFTER `imap_trash_folder`');
}
if (!$CI->db->field_exists('imap_archive_folder', db_prefix() . 'staff')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `imap_archive_folder` VARCHAR(255) NULL DEFAULT NULL AFTER `imap_spam_folder`');
}

// Add per-staff OAuth credentials (v2.1.3+)
if (!$CI->db->field_exists('oauth_client_id', db_prefix() . 'staff')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `oauth_client_id` TEXT NULL DEFAULT NULL AFTER `oauth_token_expires_at`');
}
if (!$CI->db->field_exists('oauth_client_secret', db_prefix() . 'staff')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `oauth_client_secret` TEXT NULL DEFAULT NULL AFTER `oauth_client_id`');
}
if (!$CI->db->field_exists('oauth_tenant_id', db_prefix() . 'staff')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `oauth_tenant_id` VARCHAR(255) NULL DEFAULT NULL AFTER `oauth_client_secret`');
}

// Add per-staff import preferences (v2.1.4+)
if (!$CI->db->field_exists('only_loop_on_unseen_emails', db_prefix() . 'staff')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `only_loop_on_unseen_emails` TINYINT(1) NOT NULL DEFAULT 1 AFTER `oauth_tenant_id`');
}
if (!$CI->db->field_exists('enable_html', db_prefix() . 'staff')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `enable_html` TINYINT(1) NOT NULL DEFAULT 1 AFTER `only_loop_on_unseen_emails`');
}
// Add sync read status to server setting (v2.1.7+)
if (!$CI->db->field_exists('sync_read_status_to_server', db_prefix() . 'staff')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `sync_read_status_to_server` TINYINT(1) NOT NULL DEFAULT 0 COMMENT "Sync read status to IMAP server (slower, keeps Gmail/Outlook in sync)" AFTER `enable_html`');
}

// Add uid column to mail_inbox for IMAP read status sync (v2.1.7+)
if (!$CI->db->field_exists('uid', db_prefix() . 'mail_inbox')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` ADD COLUMN `uid` INT DEFAULT NULL COMMENT "IMAP UID for syncing read status" AFTER `id`');
}

// Add idx_uid index if it doesn't exist (v2.1.7+)
if ($CI->db->field_exists('uid', db_prefix() . 'mail_inbox')) {
    try {
        $index_check = $CI->db->query("SHOW INDEX FROM `" . db_prefix() . "mail_inbox` WHERE Key_name = 'idx_uid'");
        if (!$index_check || $index_check->num_rows() == 0) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'mail_inbox` ADD INDEX `idx_uid` (`uid`)');
        }
    } catch (Exception $e) {
        // Index might already exist, continue
    }
}
// Per-staff SMTP settings (v2.1.5)
if (!$CI->db->field_exists('smtp_host', db_prefix() . 'staff')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `smtp_host` VARCHAR(255) NULL DEFAULT NULL AFTER `enable_html`');
}
if (!$CI->db->field_exists('smtp_port', db_prefix() . 'staff')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `smtp_port` INT(11) NULL DEFAULT NULL AFTER `smtp_host`');
}
if (!$CI->db->field_exists('smtp_encryption', db_prefix() . 'staff')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `smtp_encryption` VARCHAR(10) NULL DEFAULT NULL COMMENT "ssl, tls, or none" AFTER `smtp_port`');
}
if (!$CI->db->field_exists('smtp_username', db_prefix() . 'staff')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `smtp_username` VARCHAR(255) NULL DEFAULT NULL AFTER `smtp_encryption`');
}
if (!$CI->db->field_exists('smtp_password', db_prefix() . 'staff')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'staff` ADD COLUMN `smtp_password` TEXT NULL DEFAULT NULL COMMENT "Encrypted" AFTER `smtp_username`');
}