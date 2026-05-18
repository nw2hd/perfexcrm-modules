<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div role="tabpanel" class="tab-pane" id="tab_kyc">
    <div class="panel-body">
        <?php if ($profile) : ?>
        <div class="row">
            <div class="col-md-6">
                <table class="table table-condensed" style="border:none;">
                    <tr><td class="text-muted" style="border:none;width:120px;"><?php echo _l('kyc_status'); ?></td><td style="border:none;"><?php echo kyc_status_badge($profile->status); ?></td></tr>
                    <tr><td class="text-muted" style="border:none;"><?php echo _l('kyc_risk'); ?></td><td style="border:none;"><?php echo kyc_risk_badge($profile->risk_level ?? 'low'); ?></td></tr>
                    <tr><td class="text-muted" style="border:none;"><?php echo _l('kyc_expiry_date'); ?></td><td style="border:none;"><?php echo (isset($profile->expiry_date) && $profile->expiry_date) ? date('d M Y', strtotime($profile->expiry_date)) : '—'; ?></td></tr>
                    <tr><td class="text-muted" style="border:none;"><?php echo _l('kyc_documents'); ?></td><td style="border:none;"><?php echo count($documents); ?> uploaded</td></tr>
                    <tr><td class="text-muted" style="border:none;"><?php echo _l('kyc_services'); ?></td><td style="border:none;"><?php echo count($services); ?> mapped</td></tr>
                </table>
            </div>
            <div class="col-md-6 text-right">
                <a href="<?php echo admin_url('kyc_manager/profile/' . $clientId); ?>" class="btn btn-primary btn-sm">
                    <i class="fa fa-eye"></i> <?php echo _l('kyc_view_profile'); ?>
                </a>
                <a href="<?php echo admin_url('kyc_manager/pdf/' . $clientId); ?>" class="btn btn-info btn-sm">
                    <i class="fa fa-file-pdf-o"></i> <?php echo _l('kyc_download_pdf'); ?>
                </a>
            </div>
        </div>
        <?php else : ?>
        <div class="text-center" style="padding:30px;">
            <p class="text-muted"><?php echo _l('kyc_no_profile_yet'); ?></p>
            <?php if (has_permission('kyc_manager', '', 'create')) : ?>
            <a href="<?php echo admin_url('kyc_manager/start_kyc/' . $clientId); ?>" class="btn btn-primary">
                <i class="fa fa-plus"></i> <?php echo _l('kyc_start_kyc'); ?>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>