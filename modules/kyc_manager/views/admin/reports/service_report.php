<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">
<div class="row">
<div class="col-md-8 col-md-offset-2">
<div class="panel_s">
<div class="panel-heading">
    <h4 class="panel-title"><i class="fa fa-briefcase"></i> <?php echo _l('kyc_service_report'); ?></h4>
</div>
<div class="panel-body">
    <?php if (!empty($services)) : ?>
    <table class="table table-hover">
        <thead><tr>
            <th><?php echo _l('kyc_service_name'); ?></th>
            <th class="text-center"><?php echo _l('kyc_client_count'); ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($services as $s) : ?>
        <tr>
            <td><?php echo htmlspecialchars(isset($s->item_name) ? $s->item_name : 'Item #' . $s->item_id); ?></td>
            <td class="text-center">
                <span class="label label-<?php echo $s->client_count > 0 ? 'primary' : 'default'; ?>">
                    <?php echo $s->client_count; ?>
                </span>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else : ?>
    <p class="text-muted text-center"><?php echo _l('kyc_no_results'); ?></p>
    <?php endif; ?>
</div>
</div>
</div>
</div>
</div>
</div>
<?php init_tail(); ?>