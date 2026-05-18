<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">
<div class="row">
<div class="col-md-8 col-md-offset-2">
<div class="panel_s">
<div class="panel-heading">
    <h4 class="panel-title"><i class="fa fa-cog"></i> <?php echo _l('kyc_settings'); ?></h4>
</div>
<div class="panel-body">

    <?php echo form_open(admin_url('kyc_manager/settings'), ['id' => 'kyc-settings-form']); ?>

    <div class="form-group">
        <label><?php echo _l('kyc_pdf_heading'); ?></label>
        <input type="text" name="kyc_pdf_heading" class="form-control"
               value="<?php echo htmlspecialchars(get_option('kyc_pdf_heading')); ?>">
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                <label><?php echo _l('kyc_pdf_heading_color'); ?></label>
                <input type="color" name="kyc_pdf_heading_color" class="form-control"
                       value="<?php echo htmlspecialchars(get_option('kyc_pdf_heading_color')); ?>"
                       style="height:40px;">
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label><?php echo _l('kyc_validity_months'); ?></label>
                <input type="number" name="kyc_validity_months" class="form-control"
                       min="1" max="120"
                       value="<?php echo htmlspecialchars(get_option('kyc_validity_months')); ?>">
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label><?php echo _l('kyc_reminder_days'); ?></label>
                <input type="number" name="kyc_expiry_reminder_days" class="form-control"
                       min="1" max="365"
                       value="<?php echo htmlspecialchars(get_option('kyc_expiry_reminder_days')); ?>">
            </div>
        </div>
    </div>

    <hr>

    <!-- Hidden fields ensure a value is always sent even when unchecked -->
    <input type="hidden" name="kyc_client_upload_enabled" value="0">
    <div class="form-group">
        <div class="checkbox checkbox-primary">
            <input type="checkbox"
                   name="kyc_client_upload_enabled"
                   id="kyc_client_upload_enabled"
                   value="1"
                   <?php echo (get_option('kyc_client_upload_enabled') == '1') ? 'checked' : ''; ?>>
            <label for="kyc_client_upload_enabled">
                <?php echo _l('kyc_enable_client_upload'); ?>
            </label>
        </div>
    </div>

    <input type="hidden" name="kyc_expiry_reminder_enabled" value="0">
    <div class="form-group">
        <div class="checkbox checkbox-primary">
            <input type="checkbox"
                   name="kyc_expiry_reminder_enabled"
                   id="kyc_expiry_reminder_enabled"
                   value="1"
                   <?php echo (get_option('kyc_expiry_reminder_enabled') == '1') ? 'checked' : ''; ?>>
            <label for="kyc_expiry_reminder_enabled">
                <?php echo _l('kyc_enable_reminders'); ?>
            </label>
        </div>
    </div>

    <hr>

    <button type="submit" class="btn btn-primary">
        <i class="fa fa-save"></i> <?php echo _l('kyc_save_settings'); ?>
    </button>

    <a href="<?php echo admin_url('kyc_manager/document_types'); ?>" class="btn btn-default">
        <i class="fa fa-list"></i> <?php echo _l('kyc_manage_doc_types'); ?>
    </a>

    <a href="<?php echo admin_url('kyc_manager/dashboard'); ?>" class="btn btn-default">
        <i class="fa fa-arrow-left"></i> <?php echo _l('kyc_dashboard'); ?>
    </a>

    <?php echo form_close(); ?>

</div>
</div>
</div>
</div>
</div>
</div>
<?php init_tail(); ?>