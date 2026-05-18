<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">

    <div class="row">
        <div class="col-md-12">
            <h4 style="margin-bottom:20px;">
                <i class="fa fa-id-card"></i>
                <?php echo _l('kyc_menu_title'); ?>
                <div class="pull-right">
                    <a href="<?php echo admin_url('kyc_manager/profiles'); ?>" class="btn btn-default btn-sm">
                        <i class="fa fa-list"></i> <?php echo _l('kyc_all_profiles'); ?>
                    </a>
                    <a href="<?php echo admin_url('kyc_manager/report_kyc'); ?>" class="btn btn-default btn-sm">
                        <i class="fa fa-bar-chart"></i> <?php echo _l('kyc_reports'); ?>
                    </a>
                    <a href="<?php echo admin_url('kyc_manager/settings'); ?>" class="btn btn-default btn-sm">
                        <i class="fa fa-cog"></i> <?php echo _l('kyc_settings'); ?>
                    </a>
                </div>
            </h4>
        </div>
    </div>

    <!-- Status Cards -->
    <div class="row" style="margin-bottom:20px;">
        <?php
        $statusColors = [
            'not_started' => '#95a5a6',
            'in_progress' => '#3498db',
            'submitted'   => '#8e44ad',
            'verified'    => '#27ae60',
            'rejected'    => '#e74c3c',
            'expired'     => '#f39c12',
        ];
        foreach (kyc_statuses() as $key => $label) :
            $count = isset($status_counts[$key]) ? $status_counts[$key] : 0;
            $color = isset($statusColors[$key]) ? $statusColors[$key] : '#999';
        ?>
        <div class="col-md-2 col-sm-4 col-xs-6" style="margin-bottom:10px;">
            <a href="<?php echo admin_url('kyc_manager/profiles?status=' . $key); ?>"
               style="display:block;text-align:center;padding:12px 8px;border:2px solid <?php echo $color; ?>;
                      border-radius:6px;background:#fff;text-decoration:none;">
                <div style="font-size:1.8em;font-weight:700;color:<?php echo $color; ?>;"><?php echo $count; ?></div>
                <div style="font-size:.72em;color:#666;text-transform:uppercase;margin-top:3px;"><?php echo $label; ?></div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row">
        <!-- Expiring Profiles -->
        <div class="col-md-6">
            <div class="panel_s">
                <div class="panel-heading" style="background:#fef9e7;border-bottom:2px solid #f39c12;">
                    <h4 class="panel-title">
                        <i class="fa fa-clock-o" style="color:#f39c12;"></i>
                        <?php echo _l('kyc_expiring_profiles'); ?> (30 <?php echo _l('kyc_days'); ?>)
                    </h4>
                </div>
                <div class="panel-body">
                    <?php if (!empty($expiring_profiles)) : ?>
                    <table class="table table-condensed">
                        <thead><tr>
                            <th><?php echo _l('kyc_client'); ?></th>
                            <th><?php echo _l('kyc_expiry_date'); ?></th>
                            <th></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($expiring_profiles as $ep) : ?>
                        <tr>
                            <td><a href="<?php echo admin_url('kyc_manager/profile/' . $ep->client_id); ?>"><?php echo htmlspecialchars($ep->client_company ?? ''); ?></a></td>
                            <td><span class="text-warning"><strong><?php echo date('d M Y', strtotime($ep->expiry_date)); ?></strong></span></td>
                            <td><a href="<?php echo admin_url('kyc_manager/profile/' . $ep->client_id); ?>" class="btn btn-xs btn-default"><i class="fa fa-eye"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else : ?>
                    <p class="text-muted text-center"><?php echo _l('kyc_no_expiring'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Expiring Documents -->
        <div class="col-md-6">
            <div class="panel_s">
                <div class="panel-heading" style="background:#fdedec;border-bottom:2px solid #e74c3c;">
                    <h4 class="panel-title">
                        <i class="fa fa-file-o" style="color:#e74c3c;"></i>
                        <?php echo _l('kyc_expiring_documents'); ?> (30 <?php echo _l('kyc_days'); ?>)
                    </h4>
                </div>
                <div class="panel-body">
                    <?php if (!empty($expiring_documents)) : ?>
                    <table class="table table-condensed">
                        <thead><tr>
                            <th><?php echo _l('kyc_client'); ?></th>
                            <th><?php echo _l('kyc_document'); ?></th>
                            <th><?php echo _l('kyc_expiry_date'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($expiring_documents as $ed) : ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ed->client_company ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($ed->type_name ?? ''); ?></td>
                            <td><span class="text-danger"><?php echo date('d M Y', strtotime($ed->expiry_date)); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else : ?>
                    <p class="text-muted text-center"><?php echo _l('kyc_no_expiring'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recently Verified -->
        <div class="col-md-6">
            <div class="panel_s">
                <div class="panel-heading" style="background:#eafaf1;border-bottom:2px solid #27ae60;">
                    <h4 class="panel-title">
                        <i class="fa fa-check-circle" style="color:#27ae60;"></i>
                        <?php echo _l('kyc_recently_verified'); ?>
                    </h4>
                </div>
                <div class="panel-body">
                    <?php if (!empty($recently_verified)) : ?>
                    <table class="table table-condensed">
                        <thead><tr>
                            <th><?php echo _l('kyc_client'); ?></th>
                            <th><?php echo _l('kyc_approved_date'); ?></th>
                            <th><?php echo _l('kyc_risk'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($recently_verified as $rv) : ?>
                        <tr>
                            <td><a href="<?php echo admin_url('kyc_manager/profile/' . $rv->client_id); ?>"><?php echo htmlspecialchars($rv->client_company ?? ''); ?></a></td>
                            <td><?php echo isset($rv->approval_date) ? date('d M Y', strtotime($rv->approval_date)) : '—'; ?></td>
                            <td><?php echo kyc_risk_badge($rv->risk_level); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else : ?>
                    <p class="text-muted text-center"><?php echo _l('kyc_none_yet'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Clients Without KYC -->
        <div class="col-md-6">
            <div class="panel_s">
                <div class="panel-heading" style="background:#f8f8f8;border-bottom:2px solid #95a5a6;">
                    <h4 class="panel-title">
                        <i class="fa fa-exclamation-circle" style="color:#95a5a6;"></i>
                        <?php echo _l('kyc_no_kyc_clients'); ?>
                    </h4>
                </div>
                <div class="panel-body">
                    <?php if (!empty($no_kyc_clients)) : ?>
                    <table class="table table-condensed">
                        <thead><tr>
                            <th><?php echo _l('kyc_client'); ?></th>
                            <th></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach (array_slice($no_kyc_clients, 0, 10) as $nc) : ?>
                        <tr>
                            <td><?php echo htmlspecialchars($nc->company ?? 'Client #' . $nc->userid); ?></td>
                            <td>
                                <a href="<?php echo admin_url('kyc_manager/start_kyc/' . $nc->userid); ?>"
                                   class="btn btn-xs btn-primary">
                                    <i class="fa fa-plus"></i> <?php echo _l('kyc_start_kyc'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (count($no_kyc_clients) > 10) : ?>
                    <p class="text-muted text-center">
                        <?php echo sprintf(_l('kyc_and_more'), count($no_kyc_clients) - 10); ?>
                    </p>
                    <?php endif; ?>
                    <?php else : ?>
                    <p class="text-success text-center"><i class="fa fa-check"></i> <?php echo _l('kyc_all_have_kyc'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Risk Distribution -->
    <div class="row">
        <div class="col-md-4">
            <div class="panel_s">
                <div class="panel-heading">
                    <h4 class="panel-title"><i class="fa fa-shield"></i> <?php echo _l('kyc_risk_distribution'); ?></h4>
                </div>
                <div class="panel-body text-center">
                    <?php
                    $riskColors = ['low' => '#27ae60', 'medium' => '#f39c12', 'high' => '#e74c3c'];
                    foreach (['low', 'medium', 'high'] as $rl) :
                        $cnt   = isset($risk_counts[$rl]) ? $risk_counts[$rl] : 0;
                        $color = $riskColors[$rl];
                    ?>
                    <div style="display:inline-block;margin:0 15px;text-align:center;">
                        <div style="font-size:2em;font-weight:700;color:<?php echo $color; ?>;"><?php echo $cnt; ?></div>
                        <div style="font-size:.8em;text-transform:uppercase;color:#666;"><?php echo ucfirst($rl); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

</div>
</div>
<?php init_tail(); ?>