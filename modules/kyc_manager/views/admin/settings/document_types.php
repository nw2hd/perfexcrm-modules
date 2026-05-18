<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">
<div class="row">
<div class="col-md-8 col-md-offset-2">

    <div class="panel_s">
        <div class="panel-heading">
            <h4 class="panel-title"><i class="fa fa-list"></i> <?php echo _l('kyc_document_types'); ?></h4>
        </div>
        <div class="panel-body">

            <!-- Add new type -->
            <?php echo form_open(admin_url('kyc_manager/document_types')); ?>
            <div class="row" style="margin-bottom:15px;padding:15px;background:#f8f9fa;border-radius:6px;">
                <div class="col-md-4">
                    <input type="text" name="name" class="form-control" placeholder="<?php echo _l('kyc_type_name'); ?>" required>
                </div>
                <div class="col-md-3">
                    <input type="text" name="description" class="form-control" placeholder="<?php echo _l('kyc_type_description'); ?>">
                </div>
                <div class="col-md-1">
                    <input type="number" name="sort_order" class="form-control" placeholder="#" value="0">
                </div>
                <div class="col-md-2">
                    <label class="checkbox-inline">
                        <input type="checkbox" name="required" value="1"> <?php echo _l('kyc_required'); ?>
                    </label>
                    <label class="checkbox-inline">
                        <input type="checkbox" name="active" value="1" checked> <?php echo _l('kyc_active'); ?>
                    </label>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fa fa-plus"></i> <?php echo _l('kyc_add'); ?>
                    </button>
                </div>
            </div>
            <?php echo form_close(); ?>

            <!-- Existing types -->
            <?php if (!empty($doc_types)) : ?>
            <table class="table table-condensed">
                <thead><tr>
                    <th>#</th>
                    <th><?php echo _l('kyc_type_name'); ?></th>
                    <th><?php echo _l('kyc_type_description'); ?></th>
                    <th><?php echo _l('kyc_required'); ?></th>
                    <th><?php echo _l('kyc_active'); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($doc_types as $dt) : ?>
                <tr>
                    <td><?php echo $dt->sort_order; ?></td>
                    <td><strong><?php echo htmlspecialchars($dt->name); ?></strong></td>
                    <td><small class="text-muted"><?php echo htmlspecialchars(isset($dt->description) ? $dt->description : ''); ?></small></td>
                    <td><?php echo $dt->required ? '<i class="fa fa-check text-success"></i>' : ''; ?></td>
                    <td><?php echo $dt->active ? '<i class="fa fa-check text-success"></i>' : '<i class="fa fa-times text-muted"></i>'; ?></td>
                    <td>
                        <a href="<?php echo admin_url('kyc_manager/delete_document_type/' . $dt->id); ?>"
                           class="btn btn-xs btn-danger"
                           onclick="return confirm('<?php echo _l('kyc_delete_type_confirm'); ?>');">
                            <i class="fa fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <a href="<?php echo admin_url('kyc_manager/settings'); ?>" class="btn btn-default">
                <i class="fa fa-arrow-left"></i> <?php echo _l('kyc_back_settings'); ?>
            </a>
        </div>
    </div>

</div>
</div>
</div>
</div>
<?php init_tail(); ?>