<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">
<div class="row">

    <!-- Left: Details -->
    <div class="col-md-8">
        <div class="panel_s">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-8">
                        <h4 class="panel-title">
                            <i class="fa fa-truck"></i>
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
                                <td class="text-muted" style="width:120px;border:none;">
                                    <?php echo _l('shipment_client'); ?>
                                </td>
                                <td style="border:none;">
                                    <?php
                                    $cname = !empty($shipment->client_company)
                                        ? $shipment->client_company
                                        : 'Client #' . $shipment->client_id;
                                    ?>
                                    <a href="<?php echo admin_url('clients/client/' . $shipment->client_id); ?>">
                                        <?php echo htmlspecialchars($cname); ?>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted" style="border:none;"><?php echo _l('shipment_shipper'); ?></td>
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
                                <td class="text-muted" style="width:100px;border:none;"><?php echo _l('shipment_weight'); ?></td>
                                <td style="border:none;"><?php echo isset($shipment->weight) && $shipment->weight ? $shipment->weight . ' kg' : '—'; ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted" style="border:none;"><?php echo _l('shipment_pieces'); ?></td>
                                <td style="border:none;"><?php echo isset($shipment->pieces) && $shipment->pieces ? $shipment->pieces : '—'; ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted" style="border:none;"><?php echo _l('shipment_project'); ?></td>
                                <td style="border:none;">
                                    <?php if (!empty($shipment->project_id)) : ?>
                                    <a href="<?php echo admin_url('projects/view/' . $shipment->project_id); ?>">
                                        <?php echo htmlspecialchars(isset($shipment->project_name) ? $shipment->project_name : '#' . $shipment->project_id); ?>
                                    </a>
                                    <?php else : ?>—<?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted" style="border:none;"><?php echo _l('shipment_invoice'); ?></td>
                                <td style="border:none;">
                                    <?php if (!empty($shipment->invoice_id)) : ?>
                                    <a href="<?php echo admin_url('invoices/list_invoices/' . $shipment->invoice_id); ?>">
                                        #<?php echo htmlspecialchars(isset($shipment->invoice_number) ? $shipment->invoice_number : $shipment->invoice_id); ?>
                                    </a>
                                    <?php else : ?>—<?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted" style="border:none;"><?php echo _l('shipment_date_created'); ?></td>
                                <td style="border:none;"><?php echo date('d M Y H:i', strtotime($shipment->date_created)); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php if (!empty($shipment->description)) : ?>
                <p><strong><?php echo _l('shipment_description'); ?>:</strong>
                    <?php echo htmlspecialchars($shipment->description); ?></p>
                <?php endif; ?>

                <?php if (!empty($shipment->details)) : ?>
                <div class="form-group">
                    <label><?php echo _l('shipment_details'); ?></label>
                    <p><?php echo nl2br(htmlspecialchars($shipment->details)); ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($shipment->admin_note)) : ?>
                <div class="alert alert-warning">
                    <strong><i class="fa fa-sticky-note"></i>
                        <?php echo _l('shipment_admin_note'); ?>:</strong><br>
                    <?php echo nl2br(htmlspecialchars($shipment->admin_note)); ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($shipment->cipl_filename)) : ?>
                <div class="alert alert-info">
                    <i class="fa fa-paperclip"></i>
                    <strong><?php echo _l('shipment_cipl'); ?>:</strong>
                    <?php echo htmlspecialchars($shipment->cipl_filename); ?>
                    <a href="<?php echo admin_url('shipment_tracker/shipments_admin/cipl/' . $shipment->id); ?>"
                       class="btn btn-sm btn-primary pull-right">
                        <i class="fa fa-download"></i>
                        <?php echo _l('shipment_download'); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <div class="panel-footer">
                <?php if (has_permission('shipment_tracker', '', 'edit')) : ?>
                <a href="<?php echo admin_url('shipment_tracker/shipments_admin/form/' . $shipment->id); ?>"
                   class="btn btn-primary">
                    <i class="fa fa-pencil"></i>
                    <?php echo _l('shipment_tracker_edit'); ?>
                </a>
                <?php endif; ?>
                <a href="<?php echo admin_url('shipment_tracker/shipments_admin'); ?>"
                   class="btn btn-default">
                    <i class="fa fa-arrow-left"></i>
                    <?php echo _l('shipment_back'); ?>
                </a>
                <?php if (has_permission('shipment_tracker', '', 'delete')) : ?>
                <a href="<?php echo admin_url('shipment_tracker/shipments_admin/delete/' . $shipment->id); ?>"
                   class="btn btn-danger pull-right"
                   onclick="return confirm('Delete this shipment?');">
                    <i class="fa fa-trash"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right: Status + History -->
    <div class="col-md-4">

        <?php if (has_permission('shipment_tracker', '', 'edit')) : ?>
        <div class="panel_s">
            <div class="panel-heading">
                <h4 class="panel-title">
                    <i class="fa fa-refresh"></i>
                    <?php echo _l('shipment_update_status'); ?>
                </h4>
            </div>
            <div class="panel-body">
                <div class="form-group">
                    <label><?php echo _l('shipment_status'); ?></label>
                    <select id="st-status" class="form-control selectpicker">
                        <?php foreach ($statuses as $k => $l) : ?>
                        <option value="<?php echo $k; ?>"
                            <?php echo ($shipment->status === $k) ? 'selected' : ''; ?>>
                            <?php echo $l; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><?php echo _l('shipment_status_note'); ?></label>
                    <textarea id="st-note" class="form-control" rows="2"
                              placeholder="<?php echo _l('shipment_status_note_placeholder'); ?>"></textarea>
                </div>
                <div class="checkbox">
                    <label>
                        <input type="checkbox" id="st-notify" checked>
                        <?php echo _l('shipment_send_notification'); ?>
                    </label>
                </div>
                <button class="btn btn-success btn-block"
                        id="st-update-btn"
                        data-id="<?php echo $shipment->id; ?>">
                    <i class="fa fa-check"></i>
                    <?php echo _l('shipment_update_status'); ?>
                </button>
                <div id="st-result" class="mtop10"></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- History -->
        <div class="panel_s">
            <div class="panel-heading">
                <h4 class="panel-title">
                    <i class="fa fa-history"></i>
                    <?php echo _l('shipment_status_history'); ?>
                </h4>
            </div>
            <div class="panel-body">
                <?php if (!empty($status_history)) : ?>
                <ul style="list-style:none;padding:0;margin:0;">
                    <?php foreach ($status_history as $h) : ?>
                    <li style="display:flex;gap:10px;margin-bottom:14px;
                               padding-bottom:14px;border-bottom:1px solid #f0f0f0;">
                        <div style="width:24px;height:24px;border-radius:50%;
                                    background:#3498db;flex-shrink:0;display:flex;
                                    align-items:center;justify-content:center;">
                            <i class="fa fa-check" style="color:#fff;font-size:10px;"></i>
                        </div>
                        <div>
                            <strong style="display:block;font-size:.9em;">
                                <?php echo shipment_tracker_status_label($h->status); ?>
                            </strong>
                            <?php if (!empty($h->note)) : ?>
                            <span style="font-size:.82em;color:#555;">
                                <?php echo htmlspecialchars($h->note); ?>
                            </span><br>
                            <?php endif; ?>
                            <small class="text-muted">
                                <?php echo date('d M Y H:i', strtotime($h->date)); ?>
                                <?php if (!empty($h->staff_name)) : ?>
                                — <?php echo htmlspecialchars($h->staff_name); ?>
                                <?php endif; ?>
                            </small>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else : ?>
                <p class="text-muted"><?php echo _l('shipment_no_history'); ?></p>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>
</div>
</div>
<?php init_tail(); ?>
<script>
$(function(){
    $('#st-update-btn').on('click', function(){
        var $btn   = $(this);
        var id     = $btn.data('id');
        var status = $('#st-status').val();
        var note   = $('#st-note').val();
        var notify = $('#st-notify').is(':checked') ? 1 : 0;
        if (!status) { return; }
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        $.post(
            admin_url + 'shipment_tracker/shipments_admin/ajax_status',
            { id: id, status: status, note: note, notify: notify },
            function(r) {
                if (r && r.success) {
                    $('#st-result').html(
                        '<div class="alert alert-success">' + r.message + '</div>'
                    );
                    setTimeout(function(){ location.reload(); }, 1200);
                } else {
                    $('#st-result').html(
                        '<div class="alert alert-danger">'
                        + (r ? r.message : 'Error') + '</div>'
                    );
                    $btn.prop('disabled', false)
                        .html('<i class="fa fa-check"></i> Update Status');
                }
            },
            'json'
        ).fail(function(){
            $('#st-result').html('<div class="alert alert-danger">Request failed.</div>');
            $btn.prop('disabled', false)
                .html('<i class="fa fa-check"></i> Update Status');
        });
    });
});
</script>