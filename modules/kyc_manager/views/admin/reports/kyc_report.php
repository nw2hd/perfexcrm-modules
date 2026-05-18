<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">
<div class="row">
<div class="col-md-12">
<div class="panel_s">
<div class="panel-body">

    <div class="row">
        <div class="col-md-6"><h4 class="no-margin"><i class="fa fa-bar-chart"></i> <?php echo _l('kyc_report'); ?></h4></div>
        <div class="col-md-6 text-right">
            <a href="<?php echo admin_url('kyc_manager/export_csv/kyc'); ?>" class="btn btn-success btn-sm">
                <i class="fa fa-download"></i> <?php echo _l('kyc_export_csv'); ?>
            </a>
            <a href="<?php echo admin_url('kyc_manager/report_expiry'); ?>" class="btn btn-warning btn-sm">
                <i class="fa fa-clock-o"></i> <?php echo _l('kyc_expiry_report'); ?>
            </a>
            <a href="<?php echo admin_url('kyc_manager/report_services'); ?>" class="btn btn-info btn-sm">
                <i class="fa fa-briefcase"></i> <?php echo _l('kyc_service_report'); ?>
            </a>
        </div>
    </div>
    <hr/>

    <form method="GET" class="form-inline" style="margin-bottom:15px;">
        <select name="status" class="form-control" style="margin-right:5px;">
            <option value=""><?php echo _l('kyc_all_statuses'); ?></option>
            <?php foreach ($statuses as $k => $l) : ?>
            <option value="<?php echo $k; ?>" <?php echo (isset($filters['status']) && $filters['status'] === $k) ? 'selected' : ''; ?>><?php echo $l; ?></option>
            <?php endforeach; ?>
        </select>
        <select name="risk_level" class="form-control" style="margin-right:5px;">
            <option value=""><?php echo _l('kyc_all_risk'); ?></option>
            <option value="low"    <?php echo (isset($filters['risk_level']) && $filters['risk_level'] === 'low')    ? 'selected' : ''; ?>>Low</option>
            <option value="medium" <?php echo (isset($filters['risk_level']) && $filters['risk_level'] === 'medium') ? 'selected' : ''; ?>>Medium</option>
            <option value="high"   <?php echo (isset($filters['risk_level']) && $filters['risk_level'] === 'high')   ? 'selected' : ''; ?>>High</option>
        </select>
        <input type="text" name="search" class="form-control" placeholder="<?php echo _l('kyc_search'); ?>" style="margin-right:5px;"
               value="<?php echo isset($filters['search']) ? htmlspecialchars($filters['search']) : ''; ?>">
        <button type="submit" class="btn btn-default"><i class="fa fa-filter"></i></button>
        <a href="<?php echo admin_url('kyc_manager/report_kyc'); ?>" class="btn btn-link"><?php echo _l('kyc_clear'); ?></a>
    </form>

    <?php if (!empty($profiles)) : ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr>
                <th><?php echo _l('kyc_client'); ?></th>
                <th><?php echo _l('kyc_legal_name'); ?></th>
                <th><?php echo _l('kyc_country'); ?></th>
                <th><?php echo _l('kyc_risk'); ?></th>
                <th><?php echo _l('kyc_status'); ?></th>
                <th><?php echo _l('kyc_expiry_date'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($profiles as $p) : ?>
            <tr>
                <td><a href="<?php echo admin_url('kyc_manager/profile/' . $p->client_id); ?>"><?php echo htmlspecialchars($p->client_company ?? ''); ?></a></td>
                <td><?php echo htmlspecialchars(isset($p->legal_name) ? $p->legal_name : ''); ?></td>
                <td><?php echo htmlspecialchars(isset($p->country) ? $p->country : ''); ?></td>
                <td><?php echo kyc_risk_badge($p->risk_level ?? 'low'); ?></td>
                <td><?php echo kyc_status_badge($p->status); ?></td>
                <td><?php echo (isset($p->expiry_date) && $p->expiry_date) ? date('d M Y', strtotime($p->expiry_date)) : '—'; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else : ?>
    <p class="text-muted text-center" style="padding:40px;"><?php echo _l('kyc_no_results'); ?></p>
    <?php endif; ?>

</div>
</div>
</div>
</div>
</div>
</div>
<?php init_tail(); ?>