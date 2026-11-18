<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php init_head(); ?>

<div id="wrapper" class="mailbox-module-page">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="no-margin">
                                    <i class="fa fa-filter"></i> <?php echo _l('mailbox_rules'); ?>
                                </h4>
                                <p class="text-muted"><?php echo _l('mailbox_rules_description'); ?></p>
                            </div>
                            <div class="col-md-6 text-right">
                                <a href="<?php echo admin_url('mailbox?group=inbox'); ?>" class="btn btn-default">
                                    <i class="fa fa-arrow-left"></i> <?php echo _l('mailbox_inbox'); ?>
                                </a>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-12">
                                <a href="#" class="btn btn-info mbot15" onclick="view_mailbox_rule(0); return false;">
                                    <i class="fa fa-plus"></i> <?php echo _l('mailbox_add_rule'); ?>
                                </a>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="table-responsive">
                                    <?php
                                    $table_data = [
                                        _l('mailbox_rule_name'),
                                        _l('mailbox_rule_conditions'),
                                        _l('mailbox_rule_actions'),
                                        _l('mailbox_rule_order'),
                                        _l('mailbox_rule_status'),
                                        _l('options')
                                    ];
                                    render_datatable($table_data, 'mailbox-rules', [], [
                                        'data-last-order-identifier' => 'mailbox-rules',
                                        'data-default-order'         => get_table_last_order('mailbox-rules'),
                                    ]);
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Rules Modal -->
<?php $this->load->view('mailbox/rules/modal'); ?>

<!-- Prevent mailbox inline scripts from loading on rules page -->
<?php 
// Mark that we're on rules page to prevent mailbox scripts from loading
$_GET['__skip_mailbox_inline_scripts'] = true;
$_SERVER['__SKIP_MAILBOX_INLINE_SCRIPTS'] = true;
define('SKIP_MAILBOX_INLINE_SCRIPTS', true);
?>

<?php init_tail(); ?>

<script>
// Wait for jQuery and DataTables to be fully loaded
(function() {
    function initRulesTable() {
        if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') {
            setTimeout(initRulesTable, 100);
            return;
        }
        
        jQuery(document).ready(function($) {
            // Initialize DataTable for rules
            if (typeof initDataTable === 'function') {
                initDataTable('.table-mailbox-rules', admin_url + 'mailbox/rules_table', undefined, undefined, undefined, [3, 'asc']);
            } else {
                // Fallback to direct DataTable initialization
                $('.table-mailbox-rules').DataTable({
                    ajax: admin_url + 'mailbox/rules_table',
                    order: [[3, 'asc']],
                    serverSide: true,
                    processing: true
                });
            }
        });
    }
    
    initRulesTable();
})();
</script>
