<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="panel_s">
    <div class="panel-heading">
        <h4 class="panel-title">
            <i class="fa fa-truck"></i>
            <?php echo _l('shipment_tracker_menu_title'); ?>
        </h4>
    </div>
    <div class="panel-body">
        <?php if (!empty($shipments)) : ?>
            <?php
            $statusColors = [
                'prepared'          => '#95a5a6',
                'ready_to_pickup'   => '#3498db',
                'picked_up'         => '#2980b9',
                'process_clearance' => '#f39c12',
                'in_flight'         => '#e67e22',
                'certificate'       => '#27ae60',
                'closed'            => '#1e8449',
            ];
            foreach ($shipments as $s) :
                $color = isset($statusColors[$s->status])
                    ? $statusColors[$s->status] : '#999';
            ?>
            <div style="border:1px solid #e8e8e8;border-radius:8px;
                        margin-bottom:16px;overflow:hidden;">
                <div style="padding:12px 16px;background:#fafafa;
                            border-bottom:1px solid #eee;
                            border-left:4px solid <?php echo $color; ?>;">
                    <div class="row">
                        <div class="col-xs-8">
                            <strong>
                                <i class="fa fa-barcode"></i>
                                <?php echo htmlspecialchars($s->tracking_number); ?>
                            </strong>
                        </div>
                        <div class="col-xs-4 text-right">
                            <?php echo shipment_tracker_badge($s->status); ?>
                        </div>
                    </div>
                </div>
                <div style="padding:14px 16px;">
                    <div class="row">
                        <div class="col-xs-6">
                            <small style="text-transform:uppercase;color:#aaa;font-size:.7em;">
                                <?php echo _l('shipment_origin'); ?>
                            </small>
                            <p style="margin:0;font-weight:500;font-size:.88em;">
                                <?php echo htmlspecialchars(isset($s->origin) ? $s->origin : '—'); ?>
                            </p>
                        </div>
                        <div class="col-xs-6">
                            <small style="text-transform:uppercase;color:#aaa;font-size:.7em;">
                                <?php echo _l('shipment_destination'); ?>
                            </small>
                            <p style="margin:0;font-weight:500;font-size:.88em;">
                                <?php echo htmlspecialchars(isset($s->destination) ? $s->destination : '—'); ?>
                            </p>
                        </div>
                    </div>
                    <?php if (!empty($s->invoice_id)) : ?>
                    <div class="row" style="margin-top:8px;">
                        <div class="col-xs-12">
                            <small style="text-transform:uppercase;color:#aaa;font-size:.7em;">
                                <?php echo _l('shipment_invoice'); ?>
                            </small>
                            <p style="margin:0;font-size:.88em;">
                                #<?php echo htmlspecialchars(isset($s->invoice_number) ? $s->invoice_number : $s->invoice_id); ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="row" style="margin-top:12px;">
                        <div class="col-xs-6">
                            <small style="color:#aaa;font-size:.8em;">
                                <?php echo date('d M Y', strtotime($s->date_created)); ?>
                            </small>
                        </div>
                        <div class="col-xs-6 text-right">
                            <a href="<?php echo site_url('shipment_tracker/shipment_tracker_client/track/' . $s->id); ?>"
                               class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i>
                                <?php echo _l('shipment_tracker_track'); ?>
                            </a>
                            <?php if (!empty($s->cipl_filename)) : ?>
                            <a href="<?php echo site_url('shipment_tracker/shipment_tracker_client/cipl/' . $s->id); ?>"
                               class="btn btn-default btn-sm">
                                <i class="fa fa-download"></i> CIPL
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else : ?>
        <div class="text-center" style="padding:60px 20px;">
            <i class="fa fa-truck fa-3x text-muted"></i>
            <p class="mtop15 text-muted">
                <?php echo _l('shipment_tracker_no_shipments'); ?>
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>