<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div role="tabpanel" class="tab-pane" id="tab_shipments">
    <div class="panel-body">
        <?php if (has_permission('shipment_tracker', '', 'create')) : ?>
        <a href="<?php echo admin_url('shipment_tracker/shipments_admin/form?client_id=' . $clientId); ?>"
           class="btn btn-primary btn-xs pull-right">
            <i class="fa fa-plus"></i>
            <?php echo _l('shipment_tracker_create'); ?>
        </a>
        <?php endif; ?>
        <h5><i class="fa fa-truck"></i> <?php echo _l('shipment_tracker_menu_title'); ?></h5>
        <div class="clearfix"></div>
        <?php if (!empty($shipments)) : ?>
        <table class="table table-condensed" style="margin-top:10px;">
            <thead>
                <tr>
                    <th><?php echo _l('shipment_tracking_number'); ?></th>
                    <th><?php echo _l('shipment_origin'); ?></th>
                    <th><?php echo _l('shipment_destination'); ?></th>
                    <th><?php echo _l('shipment_status'); ?></th>
                    <th><?php echo _l('shipment_date_created'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($shipments as $s) : ?>
            <tr>
                <td>
                    <a href="<?php echo admin_url('shipment_tracker/shipments_admin/view/' . $s->id); ?>">
                        <?php echo htmlspecialchars($s->tracking_number); ?>
                    </a>
                </td>
                <td><?php echo htmlspecialchars(isset($s->origin) ? $s->origin : '—'); ?></td>
                <td><?php echo htmlspecialchars(isset($s->destination) ? $s->destination : '—'); ?></td>
                <td><?php echo shipment_tracker_badge($s->status); ?></td>
                <td><?php echo date('d M Y', strtotime($s->date_created)); ?></td>
                <td>
                    <a href="<?php echo admin_url('shipment_tracker/shipments_admin/view/' . $s->id); ?>"
                       class="btn btn-xs btn-default">
                        <i class="fa fa-eye"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else : ?>
        <p class="text-muted mtop10">
            <?php echo _l('shipment_tracker_no_shipments'); ?>
        </p>
        <?php endif; ?>
    </div>
</div>