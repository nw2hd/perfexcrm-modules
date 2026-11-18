<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="row">
    <div class="col-md-12">
        <div class="panel_s">
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-12">
                        <h4 class="no-margin"><?php echo _l('mailbox_rules'); ?></h4>
                        <p class="text-muted"><?php echo _l('mailbox_rules_description'); ?></p>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-12">
                        <a href="#" class="btn btn-info" onclick="view_mailbox_rule(0); return false;">
                            <i class="fa fa-plus"></i> <?php echo _l('mailbox_add_rule'); ?>
                        </a>
                    </div>
                </div>
                <div class="row mtop15">
                    <div class="col-md-12">
                        <div class="table-responsive">
                            <table class="table dt-table table-mailbox-rules" data-order-col="4" data-order-type="asc">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('mailbox_rule_name'); ?></th>
                                        <th><?php echo _l('mailbox_rule_conditions'); ?></th>
                                        <th><?php echo _l('mailbox_rule_actions'); ?></th>
                                        <th><?php echo _l('mailbox_rule_order'); ?></th>
                                        <th><?php echo _l('mailbox_rule_status'); ?></th>
                                        <th><?php echo _l('options'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rules as $rule) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($rule['name']); ?></td>
                                        <td>
                                            <?php 
                                            $conditions = json_decode($rule['conditions'], true);
                                            if ($conditions && is_array($conditions)) {
                                                $condition_texts = [];
                                                foreach ($conditions as $condition) {
                                                    if (isset($condition['field']) && isset($condition['operator']) && isset($condition['value'])) {
                                                        $condition_texts[] = $condition['field'] . ' ' . $condition['operator'] . ' "' . htmlspecialchars($condition['value']) . '"';
                                                    }
                                                }
                                                echo implode(' AND ', $condition_texts);
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $actions = json_decode($rule['actions'], true);
                                            if ($actions && is_array($actions)) {
                                                $action_texts = [];
                                                foreach ($actions as $action) {
                                                    if (isset($action['type'])) {
                                                        $action_texts[] = _l('mailbox_rule_action_' . $action['type']);
                                                    }
                                                }
                                                echo implode(', ', $action_texts);
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo $rule['order']; ?></td>
                                        <td>
                                            <?php if ($rule['enabled']) { ?>
                                                <span class="label label-success"><?php echo _l('mailbox_enabled'); ?></span>
                                            <?php } else { ?>
                                                <span class="label label-default"><?php echo _l('mailbox_disabled'); ?></span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <a href="#" class="btn btn-default btn-icon" onclick="view_mailbox_rule(<?php echo $rule['id']; ?>); return false;" data-toggle="tooltip" title="<?php echo _l('edit'); ?>">
                                                <i class="fa fa-pencil-square-o"></i>
                                            </a>
                                            <a href="#" class="btn btn-danger btn-icon" onclick="delete_mailbox_rule(<?php echo $rule['id']; ?>); return false;" data-toggle="tooltip" title="<?php echo _l('delete'); ?>">
                                                <i class="fa fa-remove"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $this->load->view('mailbox/rules/modal'); ?>

<script>
    $(document).ready(function() {
        initDataTable('.table-mailbox-rules', admin_url + 'mailbox/rules/table', undefined, undefined, undefined, [4, 'asc']);
    });
</script>
