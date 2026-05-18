<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<!-- Header -->
<div class="panel_s">
    <div class="panel-heading">
        <div class="row">
            <div class="col-xs-8">
                <h4 class="panel-title">
                    <i class="fa fa-barcode"></i>
                    <?php echo htmlspecialchars($shipment->tracking_number); ?>
                </h4>
            </div>
            <div class="col-xs-4 text-right">
                <?php echo shipment_tracker_badge($shipment->status); ?>
            </div>
        </div>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-sm-6">
                <table class="table table-condensed" style="border:none;">
                    <tr>
                        <td class="text-muted" style="border:none;width:110px;"><?php echo _l('shipment_shipper'); ?></td>
                        <td style="border:none;"><?php echo htmlspecialchars(isset($shipment->shipper) ? $shipment->shipper : '—'); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="border:none;"><?php echo _l('shipment_consignee'); ?></td>
                        <td style="border:none;"><?php echo htmlspecialchars(isset($shipment->consignee) ? $shipment->consignee : '—'); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="border:none;"><?php echo _l('shipment_origin'); ?></td>
                        <td style="border:none;"><?php echo htmlspecialchars(isset($shipment->origin) ? $shipment->origin : '—'); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="border:none;"><?php echo _l('shipment_destination'); ?></td>
                        <td style="border:none;"><?php echo htmlspecialchars(isset($shipment->destination) ? $shipment->destination : '—'); ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-sm-6">
                <table class="table table-condensed" style="border:none;">
                    <tr>
                        <td class="text-muted" style="border:none;width:110px;"><?php echo _l('shipment_description'); ?></td>
                        <td style="border:none;"><?php echo htmlspecialchars(isset($shipment->description) ? $shipment->description : '—'); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="border:none;"><?php echo _l('shipment_weight'); ?></td>
                        <td style="border:none;"><?php echo (isset($shipment->weight) && $shipment->weight) ? $shipment->weight . ' kg' : '—'; ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="border:none;"><?php echo _l('shipment_pieces'); ?></td>
                        <td style="border:none;"><?php echo (isset($shipment->pieces) && $shipment->pieces) ? $shipment->pieces : '—'; ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="border:none;"><?php echo _l('shipment_date_created'); ?></td>
                        <td style="border:none;"><?php echo date('d M Y', strtotime($shipment->date_created)); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <?php if (!empty($shipment->invoice_id) || !empty($shipment->cipl_filename)) : ?>
        <hr/>
        <div class="row">
            <?php if (!empty($shipment->invoice_id)) : ?>
            <div class="col-sm-6">
                <div class="alert alert-info">
                    <i class="fa fa-file-text-o"></i>
                    <strong><?php echo _l('shipment_invoice'); ?>:</strong>
                    #<?php echo htmlspecialchars(isset($shipment->invoice_number) ? $shipment->invoice_number : $shipment->invoice_id); ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($shipment->cipl_filename)) : ?>
            <div class="col-sm-6">
                <div class="alert alert-success">
                    <i class="fa fa-paperclip"></i>
                    <strong><?php echo _l('shipment_cipl'); ?></strong>
                    <a href="<?php echo site_url('shipment_tracker/shipment_tracker_client/cipl/' . $shipment->id); ?>"
                       class="btn btn-xs btn-success pull-right">
                        <i class="fa fa-download"></i>
                        <?php echo _l('shipment_download'); ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Progress Stepper -->
<div class="panel_s">
    <div class="panel-heading">
        <h4 class="panel-title">
            <i class="fa fa-map-signs"></i>
            <?php echo _l('shipment_progress'); ?>
        </h4>
    </div>
    <div class="panel-body">
        <div style="display:flex;align-items:flex-start;overflow-x:auto;padding-bottom:8px;">
            <?php
            $steps  = array_keys($statuses);
            $curIdx = array_search($shipment->status, $steps);
            foreach ($steps as $i => $sk) :
                $done   = ($i < $curIdx);
                $active = ($i === $curIdx);
                $clr    = $done   ? '#2ecc71' : ($active ? '#3498db' : '#e0e0e0');
                $txt    = $done   ? '#2ecc71' : ($active ? '#3498db' : '#aaa');
                $shadow = $active ? 'box-shadow:0 0 0 4px rgba(52,152,219,.2);' : '';
            ?>
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;
                        position:relative;text-align:center;min-width:80px;">
                <div style="width:34px;height:34px;border-radius:50%;background:<?php echo $clr; ?>;
                            display:flex;align-items:center;justify-content:center;
                            color:#fff;font-weight:700;font-size:.82em;
                            z-index:1;position:relative;<?php echo $shadow; ?>">
                    <?php if ($done) : ?>
                        <i class="fa fa-check"></i>
                    <?php elseif ($active) : ?>
                        <i class="fa fa-circle"></i>
                    <?php else : ?>
                        <?php echo $i + 1; ?>
                    <?php endif; ?>
                </div>
                <div style="font-size:.68em;text-transform:uppercase;color:<?php echo $txt; ?>;
                            margin-top:6px;line-height:1.2;max-width:72px;
                            <?php echo $active ? 'font-weight:600;' : ''; ?>">
                    <?php echo $statuses[$sk]; ?>
                </div>
                <?php if ($i < count($steps) - 1) : ?>
                <div style="position:absolute;top:17px;left:50%;right:-50%;height:2px;
                            background:<?php echo $done ? '#2ecc71' : '#e0e0e0'; ?>;z-index:0;"></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- History -->
<?php if (!empty($status_history)) : ?>
<div class="panel_s">
    <div class="panel-heading">
        <h4 class="panel-title">
            <i class="fa fa-history"></i>
            <?php echo _l('shipment_status_history'); ?>
        </h4>
    </div>
    <div class="panel-body">
        <ul style="list-style:none;padding:0;margin:0;">
            <?php foreach (array_reverse($status_history) as $h) : ?>
            <li style="display:flex;gap:10px;margin-bottom:14px;
                       padding-bottom:14px;border-bottom:1px solid #f0f0f0;">
                <div style="width:24px;height:24px;border-radius:50%;
                            background:#3498db;flex-shrink:0;display:flex;
                            align-items:center;justify-content:center;">
                    <i class="fa fa-dot-circle-o" style="color:#fff;font-size:10px;"></i>
                </div>
                <div>
                    <strong style="display:block;font-size:.9em;">
                        <?php echo shipment_tracker_status_label($h->status); ?>
                    </strong>
                    <?php if (!empty($h->note)) : ?>
                    <p style="margin:3px 0;font-size:.83em;color:#555;">
                        <?php echo htmlspecialchars($h->note); ?>
                    </p>
                    <?php endif; ?>
                    <small class="text-muted">
                        <i class="fa fa-clock-o"></i>
                        <?php echo date('d M Y H:i', strtotime($h->date)); ?>
                    </small>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<div class="text-center mtop15 mbottom20">
    <a href="<?php echo site_url('shipment_tracker/shipment_tracker_client'); ?>"
       class="btn btn-default">
        <i class="fa fa-arrow-left"></i>
        <?php echo _l('shipment_back_to_list'); ?>
    </a>
</div>