<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">
<div class="row">
<div class="col-md-12">
<div class="panel_s">
<div class="panel-body">

    <div class="row">
        <div class="col-md-6">
            <h4 class="no-margin"><i class="fa fa-id-card"></i> <?php echo _l('kyc_profiles'); ?></h4>
        </div>
        <div class="col-md-6 text-right">
            <a href="<?php echo admin_url('kyc_manager/dashboard'); ?>" class="btn btn-default">
                <i class="fa fa-dashboard"></i> <?php echo _l('kyc_dashboard'); ?>
            </a>
            <a href="<?php echo admin_url('kyc_manager/export_csv/kyc'); ?>" class="btn btn-success">
                <i class="fa fa-download"></i> <?php echo _l('kyc_export_csv'); ?>
            </a>
        </div>
    </div>
    <hr/>

    <!-- Filters -->
    <form method="GET" class="form-inline" style="margin-bottom:15px;">
        <div class="form-group" style="margin-right:5px;">
            <select name="status" class="form-control selectpicker">
                <option value=""><?php echo _l('kyc_all_statuses'); ?></option>
                <?php foreach ($statuses as $k => $l) : ?>
                <option value="<?php echo $k; ?>" <?php echo (isset($filters['status']) && $filters['status'] === $k) ? 'selected' : ''; ?>><?php echo $l; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin-right:5px;">
            <select name="risk_level" class="form-control">
                <option value=""><?php echo _l('kyc_all_risk'); ?></option>
                <option value="low"    <?php echo (isset($filters['risk_level']) && $filters['risk_level'] === 'low') ? 'selected' : ''; ?>>Low</option>
                <option value="medium" <?php echo (isset($filters['risk_level']) && $filters['risk_level'] === 'medium') ? 'selected' : ''; ?>>Medium</option>
                <option value="high"   <?php echo (isset($filters['risk_level']) && $filters['risk_level'] === 'high') ? 'selected' : ''; ?>>High</option>
            </select>
        </div>
        <div class="form-group" style="margin-right:5px;">
            <input type="text" name="search" class="form-control" placeholder="<?php echo _l('kyc_search'); ?>"
                   value="<?php echo isset($filters['search']) ? htmlspecialchars($filters['search']) : ''; ?>">
        </div>
        <button type="submit" class="btn btn-default"><i class="fa fa-filter"></i> <?php echo _l('kyc_filter'); ?></button>
        <a href="<?php echo admin_url('kyc_manager/profiles'); ?>" class="btn btn-link"><?php echo _l('kyc_clear'); ?></a>
    </form>

    <!-- Table -->
    <?php if (!empty($profiles)) : ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th><?php echo _l('kyc_client'); ?></th>
                    <th><?php echo _l('kyc_legal_name'); ?></th>
                    <th><?php echo _l('kyc_country'); ?></th>
                    <th><?php echo _l('kyc_business_type'); ?></th>
                    <th><?php echo _l('kyc_risk'); ?></th>
                    <th><?php echo _l('kyc_status'); ?></th>
                    <th><?php echo _l('kyc_expiry_date'); ?></th>
                    <th><?php echo _l('kyc_actions'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($profiles as $p) : ?>
            <tr>
                <td>
                    <a href="<?php echo admin_url('kyc_manager/profile/' . $p->client_id); ?>">
                        <strong><?php echo htmlspecialchars($p->client_company ?? 'Client #' . $p->client_id); ?></strong>
                    </a>
                </td>
                <td><?php echo htmlspecialchars(isset($p->legal_name) ? $p->legal_name : '—'); ?></td>
                <td><?php echo htmlspecialchars(isset($p->country) ? $p->country : '—'); ?></td>
                <td><?php echo htmlspecialchars(isset($p->business_type) ? ucfirst($p->business_type) : '—'); ?></td>
                <td><?php echo kyc_risk_badge($p->risk_level ?? 'low'); ?></td>
                <td><?php echo kyc_status_badge($p->status); ?></td>
                <td>
                    <?php
                    if (isset($p->expiry_date) && $p->expiry_date) {
                        $daysLeft = (int)((strtotime($p->expiry_date) - time()) / 86400);
                        $cls = ($daysLeft <= 30) ? 'text-danger' : (($daysLeft <= 60) ? 'text-warning' : '');
                        echo '<span class="' . $cls . '">' . date('d M Y', strtotime($p->expiry_date)) . '</span>';
                    } else {
                        echo '—';
                    }
                    ?>
                </td>
                <td>
                    <div class="btn-group btn-group-xs">
                        <a href="<?php echo admin_url('kyc_manager/profile/' . $p->client_id); ?>"
                           class="btn btn-default"><i class="fa fa-eye"></i></a>
                        <a href="<?php echo admin_url('kyc_manager/pdf/' . $p->client_id); ?>"
                           class="btn btn-info"><i class="fa fa-file-pdf-o"></i></a>
                        <?php if (has_permission('kyc_manager', '', 'delete')) : ?>
                        <a href="<?php echo admin_url('kyc_manager/delete_profile/' . $p->client_id); ?>"
                           class="btn btn-danger"
                           onclick="return confirm('<?php echo _l('kyc_delete_confirm'); ?>');">
                            <i class="fa fa-trash"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else : ?>
    <div class="text-center" style="padding:60px 20px;">
        <i class="fa fa-id-card fa-3x text-muted"></i>
        <p class="mtop15 text-muted"><?php echo _l('kyc_no_profiles'); ?></p>
    </div>
    <?php endif; ?>

</div>
</div>
</div>
</div>
</div>
</div>
<?php init_tail(); ?>