<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php 
// Debug: Log view loading
if (function_exists('log_activity')) {
    try {
    } catch (Exception $e) {
        // Silently fail if logging fails
    }
}
?>

<?php init_head(); ?>

<div id="wrapper" class="mailbox-module-page">
    <div class="content" style="padding-top:50px;">
	    <div class="row">
            <div class="col-md-2">
                <div class="panel_s mbot50">
                    <div class="">
                        <a href="<?php echo admin_url().'mailbox/compose'; ?>" class="btn btn-danger display-block">
                            <i class="fa fa-edit"></i>
                            <?php echo _l('mailbox_compose'); ?>
                        </a>
                    </div>
                </div>

                <ul class="nav navbar-pills navbar-pills-flat nav-tabs nav-stacked customer-tabs" role="tablist">
                    <li class="<?php if ('inbox' == $group) { echo 'active '; } ?>mail_tab_<?php echo $group; ?>">
                        <a data-group="inbox" href="<?php echo admin_url('mailbox?group=inbox'); ?>">
                            <i class="fa fa-inbox menu-icon" aria-hidden="true"></i>
                            <?php echo _l('mailbox_inbox'); ?>
                            <?php
                                // Build where conditions for unread count
                                $CI = &get_instance();
                                $where_unread = ['read' => '0', 'to_staff_id' => get_staff_user_id(), 'trash' => '0'];
                                // Only add archived condition if column exists
                                if ($CI->db->field_exists('archived', db_prefix() . 'mail_inbox')) {
                                    $where_unread['archived'] = '0';
                                }
                                $num_unread = total_rows(db_prefix().'mail_inbox', $where_unread);
                                if ($num_unread > 0) { ?>
                                    <span class="badge menu-badge bg-danger"><?php echo $num_unread; ?></span>
                                <?php
                                }  ?>
                            <?php
                                $num_read = total_rows(db_prefix().'mail_inbox', ['read' => '1', 'to_staff_id' => get_staff_user_id(), 'trash' => '0']);
                            ?>
                            <span class="badge menu-badge bg-neutral"><?php echo $num_read; ?></span>
                        </a>
                    </li>
                    <li class="<?php if ('starred' == $group) { echo 'active '; } ?>mail_tab_<?php echo $group; ?>">
                        <a data-group="starred" href="<?php echo admin_url('mailbox?group=starred'); ?>">
                            <i class="fa fa-star menu-icon orange" aria-hidden="true"></i>
                            <?php echo _l('mailbox_starred'); ?>
                        </a>
                    </li>
                    <li class="<?php if ('sent' == $group) { echo 'active '; } ?>mail_tab_<?php echo $group; ?>">
                        <a data-group="sent" href="<?php echo admin_url('mailbox?group=sent'); ?>">
                            <i class="fa fa-envelope menu-icon" aria-hidden="true"></i>
                            <?php echo _l('mailbox_sent'); ?>
                        </a>
                    </li>
                    <li class="<?php if ('important' == $group) { echo 'active '; } ?>mail_tab_<?php echo $group; ?>">
                        <a data-group="important" href="<?php echo admin_url('mailbox?group=important'); ?>">
                            <i class="fa fa-bookmark menu-icon red" aria-hidden="true"></i>
                            <?php echo _l('mailbox_important'); ?>
                        </a>
                    </li>
                    <li class="<?php if ('draft' == $group) { echo 'active ';  } ?>mail_tab_<?php echo $group; ?>">
                        <a data-group="draft" href="<?php echo admin_url('mailbox?group=draft'); ?>">
                            <i class="fa fa-file menu-icon" aria-hidden="true"></i>
                            <?php echo _l('mailbox_draft'); ?>
                        </a>
                    </li>
                    <li class="<?php if ('scheduled' == $group) { echo 'active ';  } ?>mail_tab_<?php echo $group; ?>">
                        <a data-group="scheduled" href="<?php echo admin_url('mailbox?group=scheduled'); ?>">
                            <i class="fa fa-clock menu-icon" aria-hidden="true"></i>
                            <?php echo _l('mailbox_scheduled'); ?>
                            <?php
                                $CI = &get_instance();
                                $num_scheduled = total_rows(db_prefix().'mail_outbox', ['sender_staff_id' => get_staff_user_id(), 'scheduled_status' => 'Scheduled']);
                                if ($num_scheduled > 0) { ?>
                                    <span class="badge menu-badge bg-info"><?php echo $num_scheduled; ?></span>
                                <?php } ?>
                        </a>
                    </li>
                    <li class="<?php if ('trash' == $group) { echo 'active ';  } ?>mail_tab_<?php echo $group; ?>">
                        <a data-group="trash" href="<?php echo admin_url('mailbox?group=trash'); ?>">
                            <i class="fa fa-trash menu-icon" aria-hidden="true"></i>
                            <?php echo _l('mailbox_trash'); ?>
                        </a>
                    </li>
                    <?php
                        // Archive menu item (only if column exists AND feature is enabled)
                        $CI = &get_instance();
                        $archive_enabled_setting = get_option('mailbox_archive_enabled');
                        if ($archive_enabled_setting === '' || $archive_enabled_setting === null) {
                            $archive_enabled_setting = '1'; // Default: enabled
                        }
                        if ($CI->db->field_exists('archived', db_prefix() . 'mail_inbox') && $archive_enabled_setting == '1') {
                    ?>
                    <li class="<?php if ('archive' == $group) { echo 'active ';  } ?>mail_tab_<?php echo $group; ?>">
                        <a data-group="archive" href="<?php echo admin_url('mailbox?group=archive'); ?>">
                            <i class="fa fa-archive menu-icon" aria-hidden="true"></i>
                            <?php echo _l('mailbox_archive'); ?>
                        </a>
                    </li>
                    <?php } ?>
                    <?php
                        // Snoozed menu item (only if column exists AND feature is enabled)
                        $CI = &get_instance();
                        $snooze_enabled_setting = get_option('mailbox_snooze_enabled');
                        if ($snooze_enabled_setting === '' || $snooze_enabled_setting === null) {
                            $snooze_enabled_setting = '1'; // Default: enabled
                        }
                        if ($CI->db->field_exists('snoozed_until', db_prefix() . 'mail_inbox') && $snooze_enabled_setting == '1') {
                    ?>
                    <li class="<?php if ('snoozed' == $group) { echo 'active ';  } ?>mail_tab_<?php echo $group; ?>">
                        <a data-group="snoozed" href="<?php echo admin_url('mailbox?group=snoozed'); ?>">
                            <i class="fa fa-clock menu-icon" aria-hidden="true"></i>
                            <?php echo _l('mailbox_snoozed'); ?>
                        </a>
                    </li>
                    <?php } ?>
                    <?php
                        // Rules menu item (only if table exists AND feature is enabled)
                        $CI = &get_instance();
                        $rules_enabled_setting = get_option('mailbox_enable_rules');
                        if ($rules_enabled_setting === '' || $rules_enabled_setting === null) {
                            $rules_enabled_setting = '1'; // Default: enabled
                        }
                        if ($CI->db->table_exists(db_prefix() . 'mail_rules') && $rules_enabled_setting == '1') {
                    ?>
                    <li class="<?php if ('rules' == $group) { echo 'active ';  } ?>mail_tab_<?php echo $group; ?>">
                        <a data-group="rules" href="<?php echo admin_url('mailbox/rules_index'); ?>">
                            <i class="fa fa-filter menu-icon" aria-hidden="true"></i>
                            <?php echo _l('mailbox_rules'); ?>
                        </a>
                    </li>
                    <?php } ?>
                </ul>
                
                <?php
                // Display tags list below navigation tabs
                $CI = &get_instance();
                $CI->load->model('mailbox_model');
                $tags = get_mail_tags();
                if (!empty($tags)) {
                    $current_tag = $CI->input->get('search_tag');
                ?>
                <ul class="nav navbar-pills navbar-pills-flat nav-tabs nav-stacked customer-tabs mailbox-tags-nav" role="tablist" style="margin-top: 15px;">
                    <?php foreach ($tags as $tag) { 
                        $is_active = ($current_tag == $tag['id']) ? 'active' : '';
                    ?>
                    <li class="mailbox-tag-item <?php echo $is_active; ?>">
                        <a href="<?php echo admin_url('mailbox?group=' . $group . '&search_tag=' . $tag['id']); ?>" style="padding: 8px 15px;">
                            <span class="label" style="color: #fff; background: <?php echo htmlspecialchars($tag['color'] ?: '#999'); ?>; margin-right: 5px;"><?php echo htmlspecialchars($tag['name']); ?></span>
                        </a>
                    </li>
                    <?php } ?>
                    <?php if ($current_tag) { ?>
                    <li class="mailbox-tag-item">
                        <a href="<?php echo admin_url('mailbox?group=' . $group); ?>" style="padding: 8px 15px; color: #999;">
                            <i class="fa fa-times" style="margin-right: 5px;"></i> Clear filter
                        </a>
                    </li>
                    <?php } ?>
                </ul>
                <?php } ?>
            </div>
            <div class="col-md-10">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="tab-content">
                            <h4 class="customer-profile-group-heading">
                                <?php if ('detail' == $group) {
                                    echo $title;
                                } else {
                                    echo _l('mailbox_' . $group);
                                }
                                ?>
                            </h4>
                            <?php if ('compose' != $group && 'detail' != $group) { ?>
                            <!-- Advanced Search and Preview Pane Controls - Above all icons -->
                            <div class="row mbot15">
                                <div class="col-md-12">
                                    <div class="pull-left">
                                        <button type="button" class="btn btn-sm btn-info" id="toggle_advanced_search_btn" onclick="toggleAdvancedSearch();">
                                            <i class="fa fa-search"></i> <?php echo _l('mailbox_advanced_search'); ?>
                                            <i class="fa fa-chevron-down" id="advanced_search_chevron"></i>
                                        </button>
                                        <?php if ($group != 'draft') { ?>
                                        <button type="button" class="btn btn-sm btn-default" id="toggle_preview_pane" onclick="togglePreviewPane();" style="margin-left: 10px;">
                                            <i class="fa fa-columns"></i> <span id="preview_pane_text"><?php echo _l('mailbox_show_preview'); ?></span>
                                        </button>
                                        <?php } ?>
                                    </div>
                                    <div class="pull-right">
                                        <div class="btn-group" id="density_dropdown" style="margin-right: 8px;">
                                            <button type="button" class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <i class="fa fa-th"></i> <span id="density_display_text"><?php echo _l('mailbox_density_comfortable'); ?></span>
                                                <span class="caret"></span>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-right" id="density_menu">
                                                <li class="dropdown-header"><?php echo _l('mailbox_density'); ?></li>
                                                <li><a href="javascript:void(0);" data-density="comfortable" id="density_comfortable"><i class="fa fa-check"></i> <?php echo _l('mailbox_density_comfortable'); ?></a></li>
                                                <li><a href="javascript:void(0);" data-density="cozy" id="density_cozy"><i class="fa"></i> <?php echo _l('mailbox_density_cozy'); ?></a></li>
                                                <li><a href="javascript:void(0);" data-density="compact" id="density_compact"><i class="fa"></i> <?php echo _l('mailbox_density_compact'); ?></a></li>
                                            </ul>
                                        </div>
                                        <div class="btn-group" id="column_visibility_dropdown">
                                            <button type="button" class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <i class="fa fa-eye"></i> <?php echo _l('mailbox_columns'); ?>
                                                <span class="caret"></span>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-right" id="column_visibility_menu">
                                                <li class="dropdown-header"><?php echo _l('mailbox_show_hide_columns'); ?></li>
                                                <li><a href="javascript:void(0);" data-column="1"><i class="fa fa-check"></i> <?php echo _l('mailbox_tag_heading'); ?></a></li>
                                                <li><a href="javascript:void(0);" data-column="2"><i class="fa fa-check"></i> <?php echo ($group == 'sent' || $group == 'draft' || $group == 'scheduled') ? _l('mailbox_to') : _l('mailbox_from'); ?></a></li>
                                                <li><a href="javascript:void(0);" data-column="3"><i class="fa fa-check"></i> <?php echo _l('mailbox_subject'); ?></a></li>
                                                <li><a href="javascript:void(0);" data-column="4" data-preview-label="<?php echo _l('mailbox_date'); ?>" data-main-label="<?php echo _l('mailbox_body'); ?>"><i class="fa fa-check"></i> <span class="column-label"><?php echo _l('mailbox_body'); ?></span></a></li>
                                                <li><a href="javascript:void(0);" data-column="5"><i class="fa fa-check"></i> <?php echo _l('assigned_customers'); ?></a></li>
                                                <?php if ($group == 'sent' || $group == 'draft' || $group == 'scheduled') { ?>
                                                <li><a href="javascript:void(0);" data-column="6"><i class="fa fa-check"></i> <?php echo _l('mailbox_scheduled_at'); ?></a></li>
                                                <li><a href="javascript:void(0);" data-column="7"><i class="fa fa-check"></i> <?php echo _l('mailbox_date'); ?></a></li>
                                                <?php } else { ?>
                                                <li><a href="javascript:void(0);" data-column="6"><i class="fa fa-check"></i> <?php echo _l('mailbox_date'); ?></a></li>
                                                <?php } ?>
                                                <li role="separator" class="divider"></li>
                                                <li><a href="javascript:void(0);" id="show_all_columns"><i class="fa fa-check-square"></i> <?php echo _l('mailbox_show_all'); ?></a></li>
                                                <li><a href="javascript:void(0);" id="hide_all_columns"><i class="fa fa-square"></i> <?php echo _l('mailbox_hide_all'); ?></a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Advanced Search Panel -->
                            <div class="row mbot15" id="advanced_search_panel" style="display: none;">
                                <div class="col-md-12">
                                    <div class="panel panel-default">
                                        <div class="panel-body">
                                            <form id="advanced_search_form" onsubmit="applyAdvancedSearch(); return false;">
                                                <div class="row">
                                                    <div class="col-md-3">
                                                        <?php echo render_input('search_from', 'mailbox_search_from', '', 'text', ['placeholder' => _l('mailbox_search_from_placeholder')]); ?>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <?php echo render_input('search_subject', 'mailbox_search_subject', '', 'text', ['placeholder' => _l('mailbox_search_subject_placeholder')]); ?>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <?php echo render_input('search_body', 'mailbox_search_body', '', 'text', ['placeholder' => _l('mailbox_search_body_placeholder')]); ?>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="form-group">
                                                            <label for="search_tag"><?php echo _l('mailbox_search_tag'); ?></label>
                                                            <select name="search_tag" id="search_tag" class="form-control selectpicker" data-none-selected-text="<?php echo _l('mailbox_all_tags'); ?>">
                                                                <option value=""><?php echo _l('mailbox_all_tags'); ?></option>
                                                                <?php 
                                                                $tags = get_mail_tags();
                                                                foreach ($tags as $tag) {
                                                                    echo '<option value="' . htmlspecialchars($tag['id']) . '">' . htmlspecialchars($tag['name']) . '</option>';
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-3">
                                                        <?php echo render_date_input('search_date_from', 'mailbox_search_date_from', ''); ?>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <?php echo render_date_input('search_date_to', 'mailbox_search_date_to', ''); ?>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="form-group">
                                                            <label for="search_has_attachment"><?php echo _l('mailbox_has_attachment'); ?></label>
                                                            <select name="search_has_attachment" id="search_has_attachment" class="form-control selectpicker">
                                                                <option value=""><?php echo _l('mailbox_all'); ?></option>
                                                                <option value="1"><?php echo _l('mailbox_yes'); ?></option>
                                                                <option value="0"><?php echo _l('mailbox_no'); ?></option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="form-group">
                                                            <label for="search_read"><?php echo _l('mailbox_read_status'); ?></label>
                                                            <select name="search_read" id="search_read" class="form-control selectpicker">
                                                                <option value=""><?php echo _l('mailbox_all'); ?></option>
                                                                <option value="1"><?php echo _l('mailbox_read'); ?></option>
                                                                <option value="0"><?php echo _l('mailbox_unread'); ?></option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="fa fa-search"></i> <?php echo _l('mailbox_search'); ?>
                                                        </button>
                                                        <button type="button" class="btn btn-default" onclick="clearAdvancedSearch();">
                                                            <i class="fa fa-times"></i> <?php echo _l('mailbox_clear'); ?>
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php } ?>
                            
                            <?php if ('compose' != $group) {?>
                                <div class="horizontal-scrollable-tabs preview-tabs-top">
                                    <div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
                                    <div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
                                    <div class="horizontal-tabs">
                                        <ul class="nav nav-tabs nav-tabs-horizontal mbot15" role="tablist">
                                            <?php if (in_array($group, ['inbox', 'starred', 'important', 'archive', 'trash'])) {?>
                                                <li role="presentation" data-toggle="tooltip" title="" class="tab-separator" data-original-title="<?php echo _l('mailbox_select_all'); ?>">
                                                    <a href="Javascript:void(0)" aria-controls="tab_emails_tracking" role="tab" data-toggle="tab" onclick="$('#mass_select_all, .select-all-checkbox').prop('checked', true).trigger('change'); return false;">
                                                        <i class="fa fa-check-square" aria-hidden="true"></i>
                                                    </a>
                                                </li>
                                                <li role="presentation" data-toggle="tooltip" title="" class="tab-separator" data-original-title="<?php echo _l('mailbox_deselect_all'); ?>">
                                                    <a href="Javascript:void(0)" aria-controls="tab_emails_tracking" role="tab" data-toggle="tab" onclick="$('#mass_select_all, .select-all-checkbox, .mail-checkbox').prop('checked', false); return false;">
                                                        <i class="fa fa-square" aria-hidden="true"></i>
                                                    </a>
                                                </li>
                                                <li role="presentation" data-toggle="tooltip" title="" class="tab-separator" data-original-title="<?php echo _l('mailbox_add_star'); ?>">
                                                    <a href="Javascript:void(0)" aria-controls="tab_emails_tracking" role="tab" data-toggle="tab" onclick="update_mass('<?php echo $group; ?>','starred',0);window.location.reload(); return false;">
                                                        <i class="fa fa-star orange" aria-hidden="true"></i>
                                                    </a>
                                                </li>
                                                <li role="presentation" data-toggle="tooltip" title="" class="tab-separator" data-original-title="<?php echo _l('mailbox_remove_star'); ?>">
                                                    <a href="Javascript:void(0)" aria-controls="tab_emails_tracking" role="tab" data-toggle="tab" onclick="update_mass('<?php echo $group; ?>','starred',1);window.location.reload(); return false;">
                                                        <i class="fa fa-star" aria-hidden="true"></i>
                                                    </a>
                                                </li>
                                                <li role="presentation" data-toggle="tooltip" title="" class="tab-separator" data-original-title="<?php echo _l('mailbox_mark_as_important'); ?>">
                                                    <a href="Javascript:void(0)" aria-controls="tab_emails_tracking" role="tab" data-toggle="tab" onclick="update_mass('<?php echo $group; ?>','important',0);window.location.reload(); return false;">
                                                        <i class="fa fa-bookmark green" aria-hidden="true"></i>
                                                    </a>
                                                </li>
                                                <li role="presentation" data-toggle="tooltip" title="" class="tab-separator" data-original-title="<?php echo _l('mailbox_mark_as_not_important'); ?>">
                                                    <a href="Javascript:void(0)" aria-controls="tab_emails_tracking" role="tab" data-toggle="tab" onclick="update_mass('<?php echo $group; ?>','important',1);window.location.reload(); return false;">
                                                        <i class="fa fa-bookmark" aria-hidden="true"></i>
                                                    </a>
                                                </li>
                                                <?php
                                                    // Archive bulk actions (only if column exists AND feature is enabled)
                                                    $CI = &get_instance();
                                                    $archive_enabled_setting = get_option('mailbox_archive_enabled');
                                                    if ($archive_enabled_setting === '' || $archive_enabled_setting === null) {
                                                        $archive_enabled_setting = '1'; // Default: enabled
                                                    }
                                                    if ($CI->db->field_exists('archived', db_prefix() . 'mail_inbox') && $archive_enabled_setting == '1') {
                                                ?>
                                                <li role="presentation" data-toggle="tooltip" title="" class="tab-separator" data-original-title="<?php echo _l('mailbox_archive'); ?>">
                                                    <a href="Javascript:void(0)" aria-controls="tab_emails_tracking" role="tab" data-toggle="tab" onclick="update_mass('<?php echo $group; ?>','archived',0);window.location.reload(); return false;">
                                                        <i class="fa fa-archive" aria-hidden="true"></i>
                                                    </a>
                                                </li>
                                                <li role="presentation" data-toggle="tooltip" title="" class="tab-separator" data-original-title="<?php echo _l('mailbox_unarchive'); ?>">
                                                    <a href="Javascript:void(0)" aria-controls="tab_emails_tracking" role="tab" data-toggle="tab" onclick="update_mass('<?php echo $group; ?>','archived',1);window.location.reload(); return false;">
                                                        <i class="fa fa-archive green" aria-hidden="true"></i>
                                                    </a>
                                                </li>
                                                <?php } ?>
                                                <li role="presentation" data-toggle="tooltip" title="" class="tab-separator" data-original-title="<?php echo _l('mailbox_mark_as_unread'); ?>">
                                                    <a href="Javascript:void(0)" aria-controls="tab_emails_tracking" role="tab" data-toggle="tab" onclick="update_mass('<?php echo $group; ?>','read',0);window.location.reload(); return false;">
                                                        <i class="fa fa-envelope orange" aria-hidden="true"></i>
                                                    </a>
                                                </li>
                                                <li role="presentation" data-toggle="tooltip" title="" class="tab-separator" data-original-title="<?php echo _l('mailbox_mark_as_read'); ?>">
                                                    <a href="Javascript:void(0)" aria-controls="tab_emails_tracking" role="tab" data-toggle="tab" onclick="update_mass('<?php echo $group; ?>','read',1);window.location.reload(); return false;">
                                                        <i class="fa fa-envelope" aria-hidden="true"></i>
                                                    </a>
                                                </li>
                                            <?php } ?>
                                            <li role="presentation" data-toggle="tooltip" title="" class="tab-separator" data-original-title="<?php echo _l('mailbox_delete'); ?>">
                                                <a href="Javascript:void(0)" aria-controls="tab_emails_tracking" role="tab" data-toggle="tab" onclick="update_mass('<?php echo $group; ?>','trash',1,'<?php if ('draft' == $group) { echo 'outbox'; } else { echo 'inbox'; } ?>');window.location.reload(); return false;">
                                                    <i class="fa fa-trash red" aria-hidden="true"></i>
                                                </a>
                                            </li>
                                            <?php if ('detail' == $group) {
                                                // Get mailbox data for button states
                                                $starred       = 'fa-star text-muted';
                                                $msg_starred   = _l('mailbox_add_star');
                                                $important     = 'fa-bookmark text-muted';
                                                $msg_important = _l('mailbox_mark_as_important');
                                                if (1 == $mailbox->stared) {
                                                    $starred = 'fa-star orange';
                                                    $msg_starred = _l('mailbox_remove_star');
                                                }
                                                if (1 == $mailbox->important) {
                                                    $important = 'fa-bookmark text-danger';
                                                    $msg_important = _l('mailbox_mark_as_not_important');
                                                }
                                            ?>
                                                <!-- Star Button -->
                                                <li role="presentation" data-toggle="tooltip" title="<?php echo $msg_starred; ?>" class="tab-separator">
                                                    <a href="javascript:void(0);" onclick="update_field('detail','starred',<?php echo $mailbox->stared; ?>,<?php echo $mailbox->id; ?>);">
                                                        <i class="fa <?php echo $starred; ?>"></i>
                                                    </a>
                                                </li>
                                                <!-- Important Button -->
                                                <li role="presentation" data-toggle="tooltip" title="<?php echo $msg_important; ?>" class="tab-separator">
                                                    <a href="javascript:void(0);" onclick="update_field('detail','important',<?php echo $mailbox->important; ?>,<?php echo $mailbox->id; ?>);">
                                                        <i class="fa <?php echo $important; ?>"></i>
                                                    </a>
                                                </li>
                                                <?php
                                                    // Archive button (only if enabled)
                                                    $CI = &get_instance();
                                                    $archive_enabled_setting = get_option('mailbox_archive_enabled');
                                                    if ($archive_enabled_setting === '' || $archive_enabled_setting === null) {
                                                        $archive_enabled_setting = '1';
                                                    }
                                                    if ($CI->db->field_exists('archived', db_prefix() . 'mail_inbox') && $archive_enabled_setting == '1') {
                                                        $archived = isset($mailbox->archived) ? (int)$mailbox->archived : 0;
                                                        $archive_icon = $archived ? 'fa-archive text-success' : 'fa-archive text-muted';
                                                        $archive_msg = $archived ? _l('mailbox_unarchive') : _l('mailbox_archive');
                                                ?>
                                                <!-- Archive Button -->
                                                <li role="presentation" data-toggle="tooltip" title="<?php echo $archive_msg; ?>" class="tab-separator">
                                                    <a href="javascript:void(0);" onclick="update_field('detail','archived',<?php echo $archived; ?>,<?php echo $mailbox->id; ?>);">
                                                        <i class="fa <?php echo $archive_icon; ?>"></i>
                                                    </a>
                                                </li>
                                                <?php } ?>
                                                <?php
                                                    // Snooze button (only if enabled)
                                                    $snooze_enabled_setting = get_option('mailbox_snooze_enabled');
                                                    if ($snooze_enabled_setting === '' || $snooze_enabled_setting === null) {
                                                        $snooze_enabled_setting = '1';
                                                    }
                                                    if ($CI->db->field_exists('snoozed_until', db_prefix() . 'mail_inbox') && $snooze_enabled_setting == '1') {
                                                        $snoozed = !empty($mailbox->snoozed_until) && strtotime($mailbox->snoozed_until) > time();
                                                        $snooze_icon = $snoozed ? 'fa-clock text-warning' : 'fa-clock text-muted';
                                                        $snooze_msg = $snoozed ? _l('mailbox_unsnooze') : _l('mailbox_snooze');
                                                ?>
                                                <!-- Snooze Button -->
                                                <li role="presentation" data-toggle="tooltip" title="<?php echo $snooze_msg; ?>" class="tab-separator">
                                                    <a href="javascript:void(0);" onclick="<?php echo $snoozed ? 'unsnooze_email('.$mailbox->id.');' : '$(\'#snooze_modal\').modal(\'show\'); $(\'#snooze_email_id\').val('.$mailbox->id.');'; ?>">
                                                        <i class="fa <?php echo $snooze_icon; ?>"></i>
                                                    </a>
                                                </li>
                                                <?php } ?>
                                                <!-- Reply Button -->
                                                <li role="presentation" class="tab-separator">
                                                    <a href="<?php echo admin_url().'mailbox/reply/'.$mailbox->id.'/reply/'.$type; ?>" class="btn btn-sm btn-primary" data-toggle="tooltip" title="<?php echo _l('mailbox_reply'); ?>">
                                                        <i class="fa fa-reply"></i> <span class="d-none d-md-inline"><?php echo _l('mailbox_reply'); ?></span>
                                                    </a>
                                                </li>
                                                <!-- Reply All Button -->
                                                <li role="presentation" class="tab-separator">
                                                    <a href="<?php echo admin_url().'mailbox/reply/'.$mailbox->id.'/replyall/'.$type; ?>" class="btn btn-sm btn-info" data-toggle="tooltip" title="<?php echo _l('mailbox_reply_all'); ?>">
                                                        <i class="fa fa-reply-all"></i> <span class="d-none d-md-inline"><?php echo _l('mailbox_reply_all'); ?></span>
                                                    </a>
                                                </li>
                                                <!-- Forward Button -->
                                                <li role="presentation" class="tab-separator">
                                                    <a href="<?php echo admin_url().'mailbox/reply/'.$mailbox->id.'/forward/'.$type; ?>" class="btn btn-sm btn-secondary" data-toggle="tooltip" title="<?php echo _l('mailbox_forward'); ?>">
                                                        <i class="fa fa-share"></i> <span class="d-none d-md-inline"><?php echo _l('mailbox_forward'); ?></span>
                                                    </a>
                                                </li>
                                            <?php } ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php } ?>
                            
                            <div class="tab-content">
                                <?php if ('compose' == $group && !isset($type)) {
                                    $this->load->view('mailbox/mailbox_compose');
                                } else if ('compose' == $group && 'reply' == $type) {
                                    $this->load->view('mailbox/mailbox_reply');
                                } else if ('detail' == $group && 'inbox' == $type) {
                                    $this->load->view('mailbox/mailbox_detail');
                                } else if ('detail' == $group && 'outbox' == $type) {
                                    $this->load->view('mailbox/mailbox_detail_outbox');
                                } else if ('rules' == $group) {
                                    $this->load->view('mailbox/rules/index');
                                } else { ?>
                                    
                                    <!-- Split View Container - Hidden for drafts -->
                                    <?php if ($group != 'draft') { ?>
                                    <div class="row" id="mailbox_split_view" style="display: none;">
                                        <div class="col-md-6" id="mailbox_list_panel">
                                            <?php
                                                // Limited columns for preview pane: Tag, From, Subject, Date
                                                $table_data_preview = [];
                                                $obj_preview = [
                                                    'name'    => _l('mailbox_from'),
                                                    'th_attrs'=> ['class'=>'toggleable', 'id'=>'th-mailbox-from-preview'],
                                                ];
                                                if ('sent' == $group || 'scheduled' == $group) {
                                                    $obj_preview = [
                                                        'name'    => _l('mailbox_to'),
                                                        'th_attrs'=> ['class'=>'toggleable', 'id'=>'th-mailbox-to-preview'],
                                                    ];
                                                }
                                                $_table_data_preview = [
                                                    '<span class="hide"> - </span><div class="checkbox mass_select_all_wrap"><input type="checkbox" id="mass_select_all_preview" data-to-table="mailbox-preview"><label></label></div>',
                                                    [
                                                        'name'    => _l('mailbox_tag_heading'),
                                                        'th_attrs'=> ['class'=>'toggleable p-3', 'id'=>'th-mailbox-tag-preview', 'style'=>'width: 50px;'],
                                                    ],
                                                    $obj_preview,
                                                    [
                                                        'name'    => _l('mailbox_subject'),
                                                        'th_attrs'=> ['class'=>'toggleable', 'id'=>'th-mailbox-subject-preview'],
                                                    ],
                                                    [
                                                        'name'    => _l('mailbox_date'),
                                                        'th_attrs'=> ['class'=>'toggleable sorting sorting_desc', 'id'=>'th-mailbox-date-preview'],
                                                    ],
                                                ];
                                                foreach ($_table_data_preview as $_t) {
                                                    array_push($table_data_preview, $_t);
                                                }

                                                $table_data_preview = hooks()->apply_filters('mailbox_table_columns_preview', $table_data_preview);

                                                render_datatable($table_data_preview, 'mailbox-preview', [], [
                                                    'data-last-order-identifier' => 'mailbox-preview',
                                                    'data-default-order'         => get_table_last_order('mailbox'),
                                                    'data-manual-init'           => 'true', // Prevent auto-initialization
                                                ]);
                                                ?>
                                        </div>
                                        <div class="col-md-6" id="mailbox_preview_panel">
                                            <div class="panel panel-default">
                                                <div class="panel-heading">
                                                    <h4 class="panel-title"><?php echo _l('mailbox_preview'); ?></h4>
                                                    <button type="button" class="close pull-right" onclick="closePreviewPane();" style="margin-top: -20px; margin-right: -10px;">
                                                        <span>&times;</span>
                                                    </button>
                                                </div>
                                                <div class="panel-body" id="preview_content">
                                                    <p class="text-muted text-center"><?php echo _l('mailbox_select_email_to_preview'); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php } ?>
                                    
                                    <!-- Full Width Table (when preview is hidden) -->
                                    <div id="mailbox_full_table">
                                        <?php
                                        // Define table columns for full width view
                                        $table_data = [];
                                        $obj = [
                                            'name'    => _l('mailbox_from'),
                                            'th_attrs'=> ['class'=>'toggleable', 'id'=>'th-mailbox-from'],
                                        ];
                                        if ('sent' == $group || 'draft' == $group || 'scheduled' == $group) {
                                            $obj = [
                                                'name'    => _l('mailbox_to'),
                                                'th_attrs'=> ['class'=>'toggleable', 'id'=>'th-mailbox-to'],
                                            ];
                                        }
                                        $_table_data = [
                                            '<span class="hide"> - </span><div class="checkbox mass_select_all_wrap"><input type="checkbox" id="mass_select_all" data-to-table="mailbox"><label></label></div>',
                                            [
                                                'name'    => _l('mailbox_tag_heading'),
                                                'th_attrs'=> ['class'=>'toggleable p-3', 'id'=>'th-mailbox-tag', 'style'=>'width: 50px;'],
                                            ],
                                            $obj, // From/To
                                            [
                                                'name'    => _l('mailbox_subject'),
                                                'th_attrs'=> ['class'=>'toggleable', 'id'=>'th-mailbox-subject'],
                                            ],
                                            [
                                                'name'    => _l('mailbox_body'),
                                                'th_attrs'=> ['class'=>'toggleable', 'id'=>'th-mailbox-body', 'style'=>'width: 200px;'],
                                            ],
                                            [
                                                'name'    => _l('assigned_customers'),
                                                'th_attrs'=> ['class'=>'toggleable', 'id'=>'th-mailbox-template', 'style'=>'width: 100px;'],
                                            ],
                                        ];
                                        if ('sent' == $group || 'draft' == $group || 'scheduled' == $group) {
                                            $_table_data[] = [
                                                'name'    => _l('mailbox_scheduled_at'),
                                                'th_attrs'=> ['class'=>'toggleable', 'id'=>'th-mailbox-scheduled-at'],
                                            ];
                                        }
                                        $_table_data[] = [
                                            'name'    => _l('mailbox_date'),
                                            'th_attrs'=> ['class'=>'toggleable', 'id'=>'th-mailbox-date'],
                                        ];
                                        foreach ($_table_data as $_t) {
                                            array_push($table_data, $_t);
                                        }
                                        $table_data = hooks()->apply_filters('mailbox_table_columns', $table_data);
                                        
                                        render_datatable($table_data, 'mailbox', [], [
                                            'data-last-order-identifier' => 'mailbox',
                                            'data-default-order'         => get_table_last_order('mailbox'),
                                        ]);
                                        ?>
                                    </div>
                                <?php } ?>                          
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Snooze Modal -->
<div class="modal fade" id="snooze_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?php echo _l('mailbox_snooze_email'); ?></h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="snooze_datetime"><?php echo _l('mailbox_snooze_until'); ?> <span class="text-danger">*</span></label>
                    <input type="text" id="snooze_datetime" name="snooze_datetime" class="form-control" required />
                    <input type="hidden" id="snooze_email_id" name="snooze_email_id" />
                </div>
                <div class="form-group">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setSnoozeTime(1)"><?php echo _l('mailbox_snooze_1hour'); ?></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setSnoozeTime(24)"><?php echo _l('mailbox_snooze_1day'); ?></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setSnoozeTime(168)"><?php echo _l('mailbox_snooze_1week'); ?></button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
                <button type="button" class="btn btn-primary" onclick="snooze_email()"><?php echo _l('mailbox_snooze'); ?></button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
// Fix Export dropdown positioning and z-index
jQuery(document).ready(function() {
    // Create a MutationObserver to watch for dropdown creation
    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === 1) { // Element node
                    var $node = jQuery(node);
                    
                    // Check if this is the dropdown or contains the dropdown
                    if ($node.hasClass('dt-button-collection') || $node.find('.dt-button-collection').length > 0) {
                        var $dropdown = $node.hasClass('dt-button-collection') ? $node : $node.find('.dt-button-collection');
                        
                        // Wait a tiny bit for DataTables to finish positioning
                        setTimeout(function() {
                            fixDropdownPosition($dropdown);
                        }, 50);
                    }
                }
            });
        });
    });
    
    // Start observing the body for added nodes
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    function fixDropdownPosition($dropdown) {
        // Find the Export button
        var $exportButton = jQuery('.buttons-collection').first();
        
        // Move dropdown to body to escape all stacking contexts
        if (!$dropdown.parent().is('body')) {
            $dropdown.appendTo('body');
        }
        
        // Force maximum z-index and fixed positioning
        $dropdown.css({
            'z-index': '2147483647',
            'position': 'fixed'
        });
        
        // Calculate position relative to Export button
        var buttonOffset = $exportButton.offset();
        var buttonHeight = $exportButton.outerHeight();
        var dropdownLeft = buttonOffset.left;
        var dropdownTop = buttonOffset.top + buttonHeight + 2;
        
        $dropdown.css({
            'left': dropdownLeft + 'px',
            'top': dropdownTop + 'px'
        });
        
        // Remove any max-height restrictions
        $dropdown.css({
            'max-height': 'none',
            'height': 'auto',
            'overflow': 'visible',
            'overflow-y': 'visible'
        });
        $dropdown.find('ul, ul.dropdown-menu').css({
            'max-height': 'none',
            'height': 'auto',
            'overflow': 'visible',
            'overflow-y': 'visible'
        });
        
        // Check if dropdown would go off-screen and reposition if needed
        var dropdownHeight = $dropdown.outerHeight();
        var dropdownTop = parseInt($dropdown.css('top'));
        var viewportHeight = jQuery(window).height();
        var spaceBelow = viewportHeight - dropdownTop;
        
        // If dropdown is cut off at bottom, reposition or make scrollable
        if (spaceBelow < dropdownHeight) {
            var spaceAbove = dropdownTop;
            
            if (spaceAbove >= dropdownHeight + 10) {
                // Position above the button
                var newTop = dropdownTop - dropdownHeight - 10;
                $dropdown.css({
                    'top': newTop + 'px'
                });
            } else {
                // Make scrollable if doesn't fit above or below
                var maxHeight = Math.max(Math.min(spaceBelow, spaceAbove) - 20, 150);
                $dropdown.css({
                    'max-height': maxHeight + 'px',
                    'overflow-y': 'auto',
                    'overflow-x': 'hidden'
                });
            }
        }
    }
    
    // DataTables debugging removed - contact support if issues persist
});
</script>
</body>
</html>