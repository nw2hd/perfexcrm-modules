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
            <h4 class="no-margin">
                <i class="fa fa-truck"></i>
                <?php echo _l('shipment_tracker_menu_title'); ?>
            </h4>
        </div>
        <div class="col-md-6 text-right">
            <?php if (has_permission('shipment_tracker', '', 'create')) : ?>
            <a href="<?php echo admin_url('shipment_tracker/shipments_admin/form'); ?>"
               class="btn btn-primary">
                <i class="fa fa-plus"></i>
                <?php echo _l('shipment_tracker_create'); ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <hr/>

    <!-- Status Cards -->
    <div class="row" style="margin-bottom:15px;">
        <?php
        $statusColors = [
            'prepared'          => '#95a5a6',
            'ready_to_pickup'   => '#3498db',
            'picked_up'         => '#2980b9',
            'process_clearance' => '#f39c12',
            'in_flight'         => '#e67e22',
            'certificate'       => '#27ae60',
            'closed'            => '#1e8449',
        ];
        foreach ($statuses as $key => $label) :
            $count = isset($status_counts[$key]) ? $status_counts[$key] : 0;
            $color = isset($statusColors[$key])  ? $statusColors[$key]  : '#999';
        ?>
        <div class="col-md-2 col-sm-4 col-xs-6" style="margin-bottom:10px;">
            <a href="<?php echo admin_url('shipment_tracker/shipments_admin?status=' . $key); ?>"
               style="display:block;text-align:center;padding:12px 8px;
                      border:2px solid <?php echo $color; ?>;border-radius:6px;
                      background:#fff;text-decoration:none;transition:all .2s;">
                <div style="font-size:1.8em;font-weight:700;color:<?php echo $color; ?>;">
                    <?php echo $count; ?>
                </div>
                <div style="font-size:.72em;color:#666;text-transform:uppercase;margin-top:3px;">
                    <?php echo $label; ?>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <hr/>

    <!-- Filters -->
    <form method="GET"
          action="<?php echo admin_url('shipment_tracker/shipments_admin'); ?>"
          class="form-inline"
          style="margin-bottom:15px;">
        <div class="form-group" style="margin-right:5px;">
            <input type="text"
                   name="search"
                   class="form-control"
                   placeholder="<?php echo _l('shipment_tracker_search'); ?>"
                   value="<?php echo isset($filters['search']) ? htmlspecialchars($filters['search']) : ''; ?>">
        </div>
        <div class="form-group" style="margin-right:5px;">
            <select name="status" class="form-control selectpicker">
                <option value="">
                    <?php echo _l('shipment_tracker_all_statuses'); ?>
                </option>
                <?php foreach ($statuses as $key => $label) : ?>
                <option value="<?php echo $key; ?>"
                    <?php echo (!empty($filters['status']) && $filters['status'] === $key) ? 'selected' : ''; ?>>
                    <?php echo $label; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-default">
            <i class="fa fa-filter"></i>
            <?php echo _l('shipment_tracker_filter'); ?>
        </button>
        <?php if (!empty($filters['status']) || !empty($filters['search'])) : ?>
        <a href="<?php echo admin_url('shipment_tracker/shipments_admin'); ?>"
           class="btn btn-link">
            <i class="fa fa-times"></i>
            <?php echo _l('shipment_tracker_clear'); ?>
        </a>
        <?php endif; ?>
    </form>

    <!-- Table -->
    <?php if (!empty($shipments)) : ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>#</th>
                <th><?php echo _l('shipment_tracking_number'); ?></th>
                <th><?php echo _l('shipment_client'); ?></th>
                <th><?php echo _l('shipment_origin'); ?></th>
                <th><?php echo _l('shipment_destination'); ?></th>
                <th><?php echo _l('shipment_status'); ?></th>
                <th><?php echo _l('shipment_date_created'); ?></th>
                <th><?php echo _l('shipment_actions'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($shipments as $s) : ?>
            <tr>
                <td><?php echo $s->id; ?></td>
                <td>
                    <a href="<?php echo admin_url('shipment_tracker/shipments_admin/view/' . $s->id); ?>">
                        <strong><?php echo htmlspecialchars($s->tracking_number); ?></strong>
                    </a>
                </td>
                <td>
                    <?php
                    $clientName = !empty($s->client_company)
                        ? $s->client_company
                        : 'Client #' . $s->client_id;
                    ?>
                    <a href="<?php echo admin_url('clients/client/' . $s->client_id); ?>">
                        <?php echo htmlspecialchars($clientName); ?>
                    </a>
                </td>
                <td><?php echo htmlspecialchars(isset($s->origin)      ? $s->origin      : '—'); ?></td>
                <td><?php echo htmlspecialchars(isset($s->destination) ? $s->destination : '—'); ?></td>
                <td><?php echo shipment_tracker_badge($s->status); ?></td>
                <td><?php echo date('d M Y', strtotime($s->date_created)); ?></td>
                <td>
                    <div class="btn-group btn-group-xs">
                        <a href="<?php echo admin_url('shipment_tracker/shipments_admin/view/' . $s->id); ?>"
                           class="btn btn-default" title="View">
                            <i class="fa fa-eye"></i>
                        </a>
                        <?php if (has_permission('shipment_tracker', '', 'edit')) : ?>
                        <a href="<?php echo admin_url('shipment_tracker/shipments_admin/form/' . $s->id); ?>"
                           class="btn btn-default" title="Edit">
                            <i class="fa fa-pencil"></i>
                        </a>
                        <?php endif; ?>
                        <?php if (has_permission('shipment_tracker', '', 'delete')) : ?>
                        <a href="<?php echo admin_url('shipment_tracker/shipments_admin/delete/' . $s->id); ?>"
                           class="btn btn-danger" title="Delete"
                           onclick="return confirm('Delete this shipment? This cannot be undone.');">
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
        <i class="fa fa-truck fa-3x text-muted"></i>
        <p class="mtop15 text-muted">
            <?php echo _l('shipment_tracker_no_shipments'); ?>
        </p>
        <?php if (has_permission('shipment_tracker', '', 'create')) : ?>
        <a href="<?php echo admin_url('shipment_tracker/shipments_admin/form'); ?>"
           class="btn btn-primary mtop10">
            <i class="fa fa-plus"></i>
            <?php echo _l('shipment_tracker_create'); ?>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
</div>
</div>
</div>
</div>
</div>
<?php init_tail(); ?>