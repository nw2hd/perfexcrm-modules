<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">
<div class="row">
<div class="col-md-10 col-md-offset-1">
<div class="panel_s">
<div class="panel-heading">
    <h4 class="panel-title">
        <i class="fa fa-truck"></i>
        <?php echo $title; ?>
    </h4>
</div>
<div class="panel-body">

    <?php
    $formUrl = isset($shipment->id)
        ? admin_url('shipment_tracker/shipments_admin/form/' . $shipment->id)
        : admin_url('shipment_tracker/shipments_admin/form');
    echo form_open_multipart($formUrl, ['id' => 'shipment-form']);
    ?>

    <!-- BASIC INFO -->
    <div class="panel_s">
        <div class="panel-heading">
            <h4 class="panel-title"><?php echo _l('shipment_section_basic'); ?></h4>
        </div>
        <div class="panel-body">
            <div class="row">

                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">
                            <?php echo _l('shipment_client'); ?>
                            <span class="text-danger">*</span>
                        </label>
                        <select name="client_id"
                                id="client_id"
                                class="form-control selectpicker"
                                data-live-search="true"
                                required>
                            <option value="">
                                <?php echo _l('shipment_select_client'); ?>
                            </option>
                            <?php
                            if (!empty($clients)) {
                                foreach ($clients as $c) {
                                    $cid  = isset($c->userid) ? $c->userid : 0;
                                    $name = (!empty($c->company))
                                        ? $c->company
                                        : 'Client #' . $cid;
                                    $sel  = (isset($shipment->client_id) && $shipment->client_id == $cid)
                                        ? 'selected' : '';
                                    echo '<option value="' . (int)$cid . '" ' . $sel . '>'
                                        . htmlspecialchars($name)
                                        . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label">
                            <?php echo _l('shipment_tracking_number'); ?>
                            <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               name="tracking_number"
                               class="form-control"
                               value="<?php echo isset($shipment->tracking_number) ? htmlspecialchars($shipment->tracking_number) : ''; ?>"
                               required>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label"><?php echo _l('shipment_shipper'); ?></label>
                        <input type="text" name="shipper" class="form-control"
                               value="<?php echo isset($shipment->shipper) ? htmlspecialchars($shipment->shipper) : ''; ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label"><?php echo _l('shipment_consignee'); ?></label>
                        <input type="text" name="consignee" class="form-control"
                               value="<?php echo isset($shipment->consignee) ? htmlspecialchars($shipment->consignee) : ''; ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label"><?php echo _l('shipment_origin'); ?></label>
                        <input type="text" name="origin" class="form-control"
                               value="<?php echo isset($shipment->origin) ? htmlspecialchars($shipment->origin) : ''; ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label"><?php echo _l('shipment_destination'); ?></label>
                        <input type="text" name="destination" class="form-control"
                               value="<?php echo isset($shipment->destination) ? htmlspecialchars($shipment->destination) : ''; ?>">
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group">
                        <label class="control-label"><?php echo _l('shipment_weight'); ?> (kg)</label>
                        <input type="number" name="weight" class="form-control"
                               step="0.01" min="0"
                               value="<?php echo isset($shipment->weight) ? htmlspecialchars($shipment->weight) : ''; ?>">
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group">
                        <label class="control-label"><?php echo _l('shipment_pieces'); ?></label>
                        <input type="number" name="pieces" class="form-control"
                               min="0"
                               value="<?php echo isset($shipment->pieces) ? htmlspecialchars($shipment->pieces) : ''; ?>">
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- LINKS -->
    <div class="panel_s">
        <div class="panel-heading">
            <h4 class="panel-title">
                <?php echo _l('shipment_section_links'); ?>
                <small class="text-muted">
                    (<?php echo _l('shipment_section_links_optional'); ?>)
                </small>
            </h4>
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label"><?php echo _l('shipment_project'); ?></label>
                        <select name="project_id" id="project_id"
                                class="form-control selectpicker"
                                data-live-search="true">
                            <option value="">
                                <?php echo _l('shipment_select_project'); ?>
                            </option>
                            <?php
                            if (!empty($projects)) {
                                foreach ($projects as $p) {
                                    $sel = (isset($shipment->project_id) && $shipment->project_id == $p->id)
                                        ? 'selected' : '';
                                    echo '<option value="' . $p->id . '" ' . $sel . '>'
                                        . htmlspecialchars($p->name) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label"><?php echo _l('shipment_invoice'); ?></label>
                        <select name="invoice_id" id="invoice_id"
                                class="form-control selectpicker"
                                data-live-search="true">
                            <option value="">
                                <?php echo _l('shipment_select_invoice'); ?>
                            </option>
                            <?php
                            if (!empty($invoices)) {
                                foreach ($invoices as $inv) {
                                    $num = isset($inv->number) ? $inv->number : $inv->id;
                                    $sel = (isset($shipment->invoice_id) && $shipment->invoice_id == $inv->id)
                                        ? 'selected' : '';
                                    echo '<option value="' . $inv->id . '" ' . $sel . '>'
                                        . '#' . htmlspecialchars($num) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- DETAILS -->
    <div class="panel_s">
        <div class="panel-heading">
            <h4 class="panel-title"><?php echo _l('shipment_section_details'); ?></h4>
        </div>
        <div class="panel-body">

            <div class="form-group">
                <label class="control-label"><?php echo _l('shipment_description'); ?></label>
                <input type="text" name="description" class="form-control"
                       value="<?php echo isset($shipment->description) ? htmlspecialchars($shipment->description) : ''; ?>">
            </div>

            <div class="form-group">
                <label class="control-label"><?php echo _l('shipment_details'); ?></label>
                <textarea name="details" class="form-control" rows="4"
                ><?php echo isset($shipment->details) ? htmlspecialchars($shipment->details) : ''; ?></textarea>
            </div>

            <div class="form-group">
                <label class="control-label">
                    <?php echo _l('shipment_admin_note'); ?>
                    <small class="text-muted">
                        (<?php echo _l('shipment_admin_note_hint'); ?>)
                    </small>
                </label>
                <textarea name="admin_note" class="form-control" rows="3"
                ><?php echo isset($shipment->admin_note) ? htmlspecialchars($shipment->admin_note) : ''; ?></textarea>
            </div>

            <div class="form-group">
                <label class="control-label"><?php echo _l('shipment_cipl'); ?></label>
                <?php if (isset($shipment->cipl_filename) && !empty($shipment->cipl_filename)) : ?>
                <div class="alert alert-info">
                    <i class="fa fa-file-pdf-o"></i>
                    <?php echo _l('shipment_cipl_current'); ?>:
                    <strong><?php echo htmlspecialchars($shipment->cipl_filename); ?></strong>
                    <a href="<?php echo admin_url('shipment_tracker/shipments_admin/cipl/' . $shipment->id); ?>"
                       class="btn btn-xs btn-primary pull-right">
                        <i class="fa fa-download"></i>
                        <?php echo _l('shipment_download'); ?>
                    </a>
                </div>
                <?php endif; ?>
                <input type="file" name="cipl_file" class="form-control"
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                <p class="help-block">
                    <?php echo _l('shipment_cipl_hint'); ?> (max 10MB)
                </p>
            </div>

        </div>
    </div>

    <!-- STATUS (edit mode only) -->
    <?php if (isset($shipment->id) && has_permission('shipment_tracker', '', 'edit')) : ?>
    <div class="panel_s">
        <div class="panel-heading">
            <h4 class="panel-title"><?php echo _l('shipment_section_status'); ?></h4>
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="control-label"><?php echo _l('shipment_status'); ?></label>
                        <select name="status" class="form-control selectpicker">
                            <?php foreach ($statuses as $k => $l) : ?>
                            <option value="<?php echo $k; ?>"
                                <?php echo (isset($shipment->status) && $shipment->status === $k) ? 'selected' : ''; ?>>
                                <?php echo $l; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="form-group">
                        <label class="control-label"><?php echo _l('shipment_status_note'); ?></label>
                        <input type="text" name="status_note" class="form-control"
                               placeholder="<?php echo _l('shipment_status_note_placeholder'); ?>">
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="send_notification" value="1" checked>
                            <?php echo _l('shipment_send_notification'); ?>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Buttons -->
    <button type="submit" class="btn btn-primary">
        <i class="fa fa-save"></i>
        <?php echo isset($shipment->id) ? _l('shipment_tracker_update') : _l('shipment_tracker_create'); ?>
    </button>
    <a href="<?php echo admin_url('shipment_tracker/shipments_admin'); ?>"
       class="btn btn-default">
        <i class="fa fa-times"></i>
        <?php echo _l('shipment_cancel'); ?>
    </a>

    <?php echo form_close(); ?>

</div>
</div>
</div>
</div>
</div>
</div>
<?php init_tail(); ?>
<script>
$(function(){
    $('#client_id').on('changed.bs.select change', function(){
        var cid = $(this).val();
        if (!cid) { return; }
        $.post(
            admin_url + 'shipment_tracker/shipments_admin/ajax_client_data',
            { client_id: cid },
            function(r){
                if (!r || !r.success) { return; }
                var $p = $('#project_id');
                $p.find('option:not(:first)').remove();
                if (r.projects && r.projects.length) {
                    $.each(r.projects, function(i, p){
                        $p.append($('<option>').val(p.id).text(p.name));
                    });
                }
                if ($.fn.selectpicker) { $p.selectpicker('refresh'); }

                var $inv = $('#invoice_id');
                $inv.find('option:not(:first)').remove();
                if (r.invoices && r.invoices.length) {
                    $.each(r.invoices, function(i, inv){
                        $inv.append($('<option>').val(inv.id).text('#' + (inv.number || inv.id)));
                    });
                }
                if ($.fn.selectpicker) { $inv.selectpicker('refresh'); }
            },
            'json'
        );
    });
});
</script>