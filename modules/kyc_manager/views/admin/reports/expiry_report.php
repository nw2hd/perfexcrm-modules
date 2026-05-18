<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">
<div class="row">
<div class="col-md-12">
<div class="panel_s">
<div class="panel-body">

    <div class="row">
        <div class="col-md-6"><h4 class="no-margin"><i class="fa fa-clock-o"></i> <?php echo _l('kyc_expiry_report'); ?></h4></div>
        <div class="col-md-6 text-right">
            <a href="<?php echo admin_url('kyc_manager/export_csv/expiry'); ?>" class="btn btn-success btn-sm">
                <i class="fa fa-download"></i> <?php echo _l('kyc_export_csv'); ?>
            </a>
        </div>
    </div>
    <hr/>

    <form method="GET" class="form-inline" style="margin-bottom:15px;">
        <select name="days" class="form-control" style="margin-right:5px;">
            <?php foreach ([30, 60, 90, 180, 365] as $d) : ?>
            <option value="<?php echo $d; ?>" <?php echo ($days == $d) ? 'selected' : ''; ?>><?php echo $d; ?> <?php echo _l('kyc_days'); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="type" class="form-control" style="margin-right:5px;">
            <option value="all"       <?php echo ($type === 'all')       ? 'selected' : ''; ?>><?php echo _l('kyc_all'); ?></option>
            <option value="profiles"  <?php echo ($type === 'profiles')  ? 'selected' : ''; ?>><?php echo _l('kyc_profiles_only'); ?></option>
            <option value="documents" <?php echo ($type === 'documents') ? 'selected' : ''; ?>><?php echo _l('kyc_documents_only'); ?></option>
        </select>
        <button type="submit" class="btn btn-default"><i class="fa fa-filter"></i></button>
    </form>

    <?php if (!empty($results)) : ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr>
                <th><?php echo _l('kyc_type'); ?></th>
                <th><?php echo _l('kyc_client'); ?></th>
                <th><?php echo _l('kyc_detail'); ?></th>
                <th><?php echo _l('kyc_expiry_date'); ?></th>
                <th><?php echo _l('kyc_days_left'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($results as $r) :
                $cls = ($r['days_left'] <= 7) ? 'danger' : (($r['days_left'] <= 30) ? 'warning' : '');
            ?>
            <tr class="<?php echo $cls; ?>">
                <td><?php echo htmlspecialchars($r['type']); ?></td>
                <td><a href="<?php echo admin_url('kyc_manager/profile/' . $r['client_id']); ?>"><?php echo htmlspecialchars($r['client']); ?></a></td>
                <td><?php echo htmlspecialchars($r['detail']); ?></td>
                <td><?php echo date('d M Y', strtotime($r['expiry_date'])); ?></td>
                <td>
                    <strong class="<?php echo ($r['days_left'] <= 7) ? 'text-danger' : (($r['days_left'] <= 30) ? 'text-warning' : ''); ?>">
                        <?php echo $r['days_left']; ?> <?php echo _l('kyc_days'); ?>
                    </strong>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else : ?>
    <p class="text-muted text-center" style="padding:40px;"><?php echo _l('kyc_no_expiring'); ?></p>
    <?php endif; ?>

</div>
</div>
</div>
</div>
</div>
</div>
<?php init_tail(); ?>