<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">

    <!-- Header -->
    <div class="row" style="margin-bottom:15px;">
        <div class="col-md-6">
            <h4>
                <i class="fa fa-id-card"></i>
                <?php echo htmlspecialchars($client_name); ?>
                <?php if (isset($profile->status)) : ?>
                &nbsp;<?php echo kyc_status_badge($profile->status); ?>
                <?php endif; ?>
                <?php if (isset($profile->risk_level)) : ?>
                &nbsp;<?php echo kyc_risk_badge($profile->risk_level); ?>
                <?php endif; ?>
            </h4>
        </div>
        <div class="col-md-6 text-right">
            <a href="<?php echo admin_url('kyc_manager/pdf/' . $client_id); ?>" class="btn btn-info btn-sm">
                <i class="fa fa-file-pdf-o"></i> <?php echo _l('kyc_download_pdf'); ?>
            </a>
            <a href="<?php echo admin_url('kyc_manager/print_kyc/' . $client_id); ?>" class="btn btn-default btn-sm" target="_blank">
                <i class="fa fa-print"></i> <?php echo _l('kyc_print'); ?>
            </a>
            <a href="<?php echo admin_url('kyc_manager/profiles'); ?>" class="btn btn-default btn-sm">
                <i class="fa fa-arrow-left"></i> <?php echo _l('kyc_back_to_list'); ?>
            </a>
        </div>
    </div>

    <div class="row">

        <!-- LEFT: Profile Form + Documents -->
        <div class="col-md-8">

            <!-- KYC Profile Form -->
            <div class="panel_s">
                <div class="panel-heading">
                    <h4 class="panel-title"><i class="fa fa-user"></i> <?php echo _l('kyc_profile_info'); ?></h4>
                </div>
                <div class="panel-body">
                    <?php echo form_open_multipart(admin_url('kyc_manager/profile/' . $client_id), ['id' => 'kyc-profile-form']); ?>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?php echo _l('kyc_legal_name'); ?></label>
                                <input type="text" name="legal_name" class="form-control"
                                       value="<?php echo isset($profile->legal_name) ? htmlspecialchars($profile->legal_name) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><?php echo _l('kyc_registration_no'); ?></label>
                                <input type="text" name="registration_number" class="form-control"
                                       value="<?php echo isset($profile->registration_number) ? htmlspecialchars($profile->registration_number) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><?php echo _l('kyc_tax_id'); ?></label>
                                <input type="text" name="tax_id" class="form-control"
                                       value="<?php echo isset($profile->tax_id) ? htmlspecialchars($profile->tax_id) : ''; ?>">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?php echo _l('kyc_business_type'); ?></label>
                                <select name="business_type" class="form-control">
                                    <option value="">—</option>
                                    <?php
                                    $types = ['individual', 'company', 'partnership', 'ngo', 'government', 'other'];
                                    foreach ($types as $t) :
                                        $sel = (isset($profile->business_type) && $profile->business_type === $t) ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo $t; ?>" <?php echo $sel; ?>>
                                        <?php echo ucfirst($t); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?php echo _l('kyc_country'); ?></label>
                                <input type="text" name="country" class="form-control"
                                       value="<?php echo isset($profile->country) ? htmlspecialchars($profile->country) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?php echo _l('kyc_industry'); ?></label>
                                <input type="text" name="industry" class="form-control"
                                       value="<?php echo isset($profile->industry) ? htmlspecialchars($profile->industry) : ''; ?>">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label><?php echo _l('kyc_date_incorporation'); ?></label>
                                <input type="date" name="date_of_incorporation" class="form-control"
                                       value="<?php echo isset($profile->date_of_incorporation) ? $profile->date_of_incorporation : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><?php echo _l('kyc_date_birth'); ?></label>
                                <input type="date" name="date_of_birth" class="form-control"
                                       value="<?php echo isset($profile->date_of_birth) ? $profile->date_of_birth : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><?php echo _l('kyc_annual_turnover'); ?></label>
                                <input type="text" name="annual_turnover" class="form-control"
                                       value="<?php echo isset($profile->annual_turnover) ? htmlspecialchars($profile->annual_turnover) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><?php echo _l('kyc_employees'); ?></label>
                                <input type="text" name="employees" class="form-control"
                                       value="<?php echo isset($profile->employees) ? htmlspecialchars($profile->employees) : ''; ?>">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?php echo _l('kyc_website'); ?></label>
                                <input type="text" name="website" class="form-control"
                                       value="<?php echo isset($profile->website) ? htmlspecialchars($profile->website) : ''; ?>">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?php echo _l('kyc_legal_address'); ?></label>
                                <textarea name="legal_address" class="form-control" rows="2"
                                ><?php echo isset($profile->legal_address) ? htmlspecialchars($profile->legal_address) : ''; ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?php echo _l('kyc_operating_address'); ?></label>
                                <textarea name="operating_address" class="form-control" rows="2"
                                ><?php echo isset($profile->operating_address) ? htmlspecialchars($profile->operating_address) : ''; ?></textarea>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?php echo _l('kyc_client_photo'); ?></label>
                                <?php if (isset($profile->client_photo) && !empty($profile->client_photo)) : ?>
                                <div style="margin-bottom:8px;">
                                    <img src="<?php echo base_url('uploads/kyc_manager/photos/' . $profile->client_photo); ?>"
                                         style="max-width:80px;max-height:80px;border-radius:4px;border:1px solid #ddd;">
                                </div>
                                <?php endif; ?>
                                <input type="file" name="client_photo" class="form-control" accept=".jpg,.jpeg,.png">
                            </div>
                        </div>
                    </div>

                    <!-- Admin-only fields -->
                    <hr>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><?php echo _l('kyc_status'); ?></label>
                                <select name="status" class="form-control">
                                    <?php foreach ($statuses as $k => $l) : ?>
                                    <option value="<?php echo $k; ?>"
                                        <?php echo (isset($profile->status) && $profile->status === $k) ? 'selected' : ''; ?>>
                                        <?php echo $l; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><?php echo _l('kyc_risk_level'); ?></label>
                                <select name="risk_level" class="form-control">
                                    <option value="low"    <?php echo (isset($profile->risk_level) && $profile->risk_level === 'low')    ? 'selected' : ''; ?>>Low</option>
                                    <option value="medium" <?php echo (isset($profile->risk_level) && $profile->risk_level === 'medium') ? 'selected' : ''; ?>>Medium</option>
                                    <option value="high"   <?php echo (isset($profile->risk_level) && $profile->risk_level === 'high')   ? 'selected' : ''; ?>>High</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><?php echo _l('kyc_officer'); ?></label>
                                <select name="kyc_officer" class="form-control selectpicker" data-live-search="true">
                                    <option value="">—</option>
                                    <?php foreach ($staff as $s) : ?>
                                    <option value="<?php echo $s->staffid; ?>"
                                        <?php echo (isset($profile->kyc_officer) && $profile->kyc_officer == $s->staffid) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(trim($s->firstname . ' ' . ($s->lastname ?? ''))); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><?php echo _l('kyc_admin_notes'); ?> <small class="text-muted">(<?php echo _l('kyc_internal_only'); ?>)</small></label>
                        <textarea name="admin_notes" class="form-control" rows="3"
                        ><?php echo isset($profile->admin_notes) ? htmlspecialchars($profile->admin_notes) : ''; ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> <?php echo _l('kyc_save_profile'); ?>
                    </button>

                    <?php echo form_close(); ?>
                </div>
            </div>

            <!-- Documents Section -->
            <div class="panel_s">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="fa fa-folder-open"></i> <?php echo _l('kyc_documents'); ?>
                        <?php if (has_permission('kyc_manager', '', 'create')) : ?>
                        <button class="btn btn-primary btn-xs pull-right" data-toggle="modal" data-target="#uploadDocModal">
                            <i class="fa fa-upload"></i> <?php echo _l('kyc_upload_document'); ?>
                        </button>
                        <?php endif; ?>
                    </h4>
                </div>
                <div class="panel-body">
                    <?php if (!empty($documents)) : ?>
                    <div class="table-responsive">
                        <table class="table table-condensed">
                            <thead><tr>
                                <th><?php echo _l('kyc_doc_type'); ?></th>
                                <th><?php echo _l('kyc_doc_name'); ?></th>
                                <th><?php echo _l('kyc_doc_number'); ?></th>
                                <th><?php echo _l('kyc_issue_date'); ?></th>
                                <th><?php echo _l('kyc_expiry_date'); ?></th>
                                <th><?php echo _l('kyc_doc_status'); ?></th>
                                <th><?php echo _l('kyc_actions'); ?></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($documents as $doc) : ?>
                            <tr id="doc-row-<?php echo $doc->id; ?>">
                                <td><?php echo htmlspecialchars(isset($doc->type_name) ? $doc->type_name : '—'); ?></td>
                                <td><?php echo htmlspecialchars(isset($doc->document_name) ? $doc->document_name : '—'); ?></td>
                                <td><?php echo htmlspecialchars(isset($doc->document_number) ? $doc->document_number : '—'); ?></td>
                                <td><?php echo (isset($doc->issue_date) && $doc->issue_date) ? date('d M Y', strtotime($doc->issue_date)) : '—'; ?></td>
                                <td>
                                    <?php if (isset($doc->expiry_date) && $doc->expiry_date) :
                                        $dLeft = (int)((strtotime($doc->expiry_date) - time()) / 86400);
                                        $eCls  = ($dLeft <= 30) ? 'text-danger' : (($dLeft <= 60) ? 'text-warning' : '');
                                    ?>
                                    <span class="<?php echo $eCls; ?>"><?php echo date('d M Y', strtotime($doc->expiry_date)); ?></span>
                                    <?php else : ?>—<?php endif; ?>
                                </td>
                                <td>
                                    <span id="doc-badge-<?php echo $doc->id; ?>">
                                        <?php echo kyc_doc_status_badge($doc->status); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-xs">
                                        <a href="<?php echo admin_url('kyc_manager/download_document/' . $doc->id); ?>"
                                           class="btn btn-default" title="<?php echo _l('kyc_download'); ?>">
                                            <i class="fa fa-download"></i>
                                        </a>
                                        <?php if (has_permission('kyc_manager', '', 'edit')) : ?>
                                        <button class="btn btn-success btn-review-doc"
                                                data-id="<?php echo $doc->id; ?>"
                                                data-action="approved"
                                                title="<?php echo _l('kyc_approve'); ?>">
                                            <i class="fa fa-check"></i>
                                        </button>
                                        <button class="btn btn-danger btn-review-doc"
                                                data-id="<?php echo $doc->id; ?>"
                                                data-action="rejected"
                                                title="<?php echo _l('kyc_reject'); ?>">
                                            <i class="fa fa-times"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if (has_permission('kyc_manager', '', 'delete')) : ?>
                                        <button class="btn btn-danger btn-delete-doc"
                                                data-id="<?php echo $doc->id; ?>"
                                                title="<?php echo _l('kyc_delete'); ?>">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else : ?>
                    <p class="text-muted text-center"><?php echo _l('kyc_no_documents'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Services Section -->
            <div class="panel_s">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="fa fa-briefcase"></i> <?php echo _l('kyc_services'); ?>
                    </h4>
                </div>
                <div class="panel-body">
                    <?php if (has_permission('kyc_manager', '', 'edit')) : ?>
                    <div class="row" style="margin-bottom:15px;">
                        <div class="col-md-5">
                            <select id="kyc-service-select" class="form-control selectpicker" data-live-search="true">
                                <option value=""><?php echo _l('kyc_select_service'); ?></option>
                                <?php foreach ($items as $item) : ?>
                                <option value="<?php echo $item->id; ?>">
                                    <?php echo htmlspecialchars($item->description); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="text" id="kyc-service-notes" class="form-control"
                                   placeholder="<?php echo _l('kyc_service_notes'); ?>">
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary btn-block" id="kyc-add-service"
                                    data-client="<?php echo $client_id; ?>">
                                <i class="fa fa-plus"></i> <?php echo _l('kyc_add_service'); ?>
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div id="kyc-services-list">
                    <?php if (!empty($services)) : ?>
                    <table class="table table-condensed">
                        <thead><tr>
                            <th><?php echo _l('kyc_service_name'); ?></th>
                            <th><?php echo _l('kyc_service_notes'); ?></th>
                            <th><?php echo _l('kyc_date_added'); ?></th>
                            <th></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($services as $svc) : ?>
                        <tr id="svc-row-<?php echo $svc->id; ?>">
                            <td><?php echo htmlspecialchars(isset($svc->item_name) ? $svc->item_name : '—'); ?></td>
                            <td><?php echo htmlspecialchars(isset($svc->notes) ? $svc->notes : ''); ?></td>
                            <td><?php echo date('d M Y', strtotime($svc->date_added)); ?></td>
                            <td>
                                <?php if (has_permission('kyc_manager', '', 'edit')) : ?>
                                <button class="btn btn-xs btn-danger btn-remove-service"
                                        data-id="<?php echo $svc->id; ?>">
                                    <i class="fa fa-times"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else : ?>
                    <p class="text-muted text-center"><?php echo _l('kyc_no_services'); ?></p>
                    <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- RIGHT: Quick Actions + Audit -->
        <div class="col-md-4">

            <!-- Quick Status Change -->
            <?php if (has_permission('kyc_manager', '', 'edit')) : ?>
            <div class="panel_s">
                <div class="panel-heading">
                    <h4 class="panel-title"><i class="fa fa-bolt"></i> <?php echo _l('kyc_quick_status'); ?></h4>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <select id="kyc-quick-status" class="form-control">
                            <?php foreach ($statuses as $k => $l) : ?>
                            <option value="<?php echo $k; ?>"
                                <?php echo (isset($profile->status) && $profile->status === $k) ? 'selected' : ''; ?>>
                                <?php echo $l; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="kyc-rejection-reason" style="display:none;">
                        <textarea id="kyc-reason" class="form-control" rows="2"
                                  placeholder="<?php echo _l('kyc_rejection_reason'); ?>"></textarea>
                    </div>
                    <button class="btn btn-success btn-block" id="kyc-change-status"
                            data-client="<?php echo $client_id; ?>">
                        <i class="fa fa-check"></i> <?php echo _l('kyc_update_status'); ?>
                    </button>
                    <div id="kyc-status-result" class="mtop10"></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Profile Summary -->
            <div class="panel_s">
                <div class="panel-heading">
                    <h4 class="panel-title"><i class="fa fa-info-circle"></i> <?php echo _l('kyc_summary'); ?></h4>
                </div>
                <div class="panel-body">
                    <table class="table table-condensed" style="border:none;">
                        <tr>
                            <td class="text-muted" style="border:none;width:110px;"><?php echo _l('kyc_status'); ?></td>
                            <td style="border:none;" id="kyc-status-display">
                                <?php echo kyc_status_badge(isset($profile->status) ? $profile->status : 'not_started'); ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted" style="border:none;"><?php echo _l('kyc_risk'); ?></td>
                            <td style="border:none;"><?php echo kyc_risk_badge(isset($profile->risk_level) ? $profile->risk_level : 'low'); ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted" style="border:none;"><?php echo _l('kyc_expiry_date'); ?></td>
                            <td style="border:none;">
                                <?php echo (isset($profile->expiry_date) && $profile->expiry_date) ? date('d M Y', strtotime($profile->expiry_date)) : '—'; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted" style="border:none;"><?php echo _l('kyc_approved_date'); ?></td>
                            <td style="border:none;">
                                <?php echo (isset($profile->approval_date) && $profile->approval_date) ? date('d M Y', strtotime($profile->approval_date)) : '—'; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted" style="border:none;"><?php echo _l('kyc_documents'); ?></td>
                            <td style="border:none;">
                                <?php echo count($documents); ?> <?php echo _l('kyc_uploaded'); ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted" style="border:none;"><?php echo _l('kyc_services'); ?></td>
                            <td style="border:none;"><?php echo count($services); ?> <?php echo _l('kyc_mapped'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Audit Trail -->
            <div class="panel_s">
                <div class="panel-heading">
                    <h4 class="panel-title"><i class="fa fa-history"></i> <?php echo _l('kyc_audit_trail'); ?></h4>
                </div>
                <div class="panel-body" style="max-height:400px;overflow-y:auto;">
                    <?php if (!empty($audit_log)) : ?>
                    <ul style="list-style:none;padding:0;margin:0;">
                        <?php foreach ($audit_log as $log) : ?>
                        <li style="display:flex;gap:10px;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #f0f0f0;">
                            <div style="width:22px;height:22px;border-radius:50%;background:#3498db;flex-shrink:0;display:flex;align-items:center;justify-content:center;">
                                <i class="fa fa-circle" style="color:#fff;font-size:8px;"></i>
                            </div>
                            <div>
                                <strong style="font-size:.85em;"><?php echo htmlspecialchars($log->action); ?></strong>
                                <?php if (!empty($log->description)) : ?>
                                <br><small style="color:#555;"><?php echo htmlspecialchars($log->description); ?></small>
                                <?php endif; ?>
                                <br><small class="text-muted">
                                    <?php echo date('d M Y H:i', strtotime($log->date)); ?>
                                    <?php if (!empty($log->staff_name)) : ?>
                                    — <?php echo htmlspecialchars($log->staff_name); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else : ?>
                    <p class="text-muted text-center"><?php echo _l('kyc_no_audit'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

</div>
</div>

<!-- Upload Document Modal -->
<div class="modal fade" id="uploadDocModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-upload"></i> <?php echo _l('kyc_upload_document'); ?></h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label><?php echo _l('kyc_doc_type'); ?> <span class="text-danger">*</span></label>
                    <select id="doc-type-id" class="form-control">
                        <?php foreach ($doc_types as $dt) : ?>
                        <option value="<?php echo $dt->id; ?>">
                            <?php echo htmlspecialchars($dt->name); ?>
                            <?php echo $dt->required ? ' *' : ''; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><?php echo _l('kyc_doc_name'); ?></label>
                    <input type="text" id="doc-name" class="form-control">
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><?php echo _l('kyc_doc_number'); ?></label>
                            <input type="text" id="doc-number" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><?php echo _l('kyc_issuing_authority'); ?></label>
                            <input type="text" id="doc-authority" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><?php echo _l('kyc_issuing_country'); ?></label>
                            <input type="text" id="doc-country" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><?php echo _l('kyc_issue_date'); ?></label>
                            <input type="date" id="doc-issue-date" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><?php echo _l('kyc_expiry_date'); ?></label>
                            <input type="date" id="doc-expiry-date" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label><?php echo _l('kyc_file'); ?> <span class="text-danger">*</span></label>
                    <input type="file" id="doc-file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('kyc_cancel'); ?></button>
                <button type="button" class="btn btn-primary" id="kyc-upload-doc-btn"
                        data-client="<?php echo $client_id; ?>">
                    <i class="fa fa-upload"></i> <?php echo _l('kyc_upload'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
$(function(){
    var clientId = <?php echo $client_id; ?>;

    // Show/hide rejection reason
    $('#kyc-quick-status').on('change', function(){
        $('#kyc-rejection-reason').toggle($(this).val() === 'rejected');
    });

    // Quick status change
    $('#kyc-change-status').on('click', function(){
        var status = $('#kyc-quick-status').val();
        var reason = $('#kyc-reason').val();
        $.post(admin_url + 'kyc_manager/change_status', {
            client_id: clientId, status: status, reason: reason
        }, function(r){
            if (r.success) {
                $('#kyc-status-display').html(r.badge);
                alert_float('success', r.message);
            }
        }, 'json');
    });

    // Upload document
    $('#kyc-upload-doc-btn').on('click', function(){
        var $btn = $(this);
        var fd   = new FormData();
        fd.append('client_id',        clientId);
        fd.append('document_type_id', $('#doc-type-id').val());
        fd.append('document_name',    $('#doc-name').val());
        fd.append('document_number',  $('#doc-number').val());
        fd.append('issuing_authority',$('#doc-authority').val());
        fd.append('issuing_country',  $('#doc-country').val());
        fd.append('issue_date',       $('#doc-issue-date').val());
        fd.append('expiry_date',      $('#doc-expiry-date').val());
        var fileInput = document.getElementById('doc-file');
        if (fileInput.files.length > 0) {
            fd.append('document_file', fileInput.files[0]);
        }
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        $.ajax({
            url: admin_url + 'kyc_manager/upload_document',
            type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
            success: function(r){
                if (r.success) {
                    alert_float('success', r.message);
                    location.reload();
                } else {
                    alert_float('danger', r.message);
                    $btn.prop('disabled', false).html('<i class="fa fa-upload"></i> Upload');
                }
            }
        });
    });

    // Review document
    $(document).on('click', '.btn-review-doc', function(){
        var id     = $(this).data('id');
        var action = $(this).data('action');
        var notes  = '';
        if (action === 'rejected') {
            notes = prompt('<?php echo _l('kyc_rejection_notes'); ?>', '');
            if (notes === null) return;
        }
        $.post(admin_url + 'kyc_manager/review_document', {
            document_id: id, status: action, notes: notes
        }, function(r){
            if (r.success) {
                $('#doc-badge-' + id).html(r.badge);
                alert_float('success', r.message);
            }
        }, 'json');
    });

    // Delete document
    $(document).on('click', '.btn-delete-doc', function(){
        if (!confirm('<?php echo _l('kyc_delete_doc_confirm'); ?>')) return;
        var id = $(this).data('id');
        $.post(admin_url + 'kyc_manager/delete_document/' + id, {}, function(r){
            if (r.success) {
                $('#doc-row-' + id).fadeOut();
                alert_float('success', r.message);
            }
        }, 'json');
    });

    // Add service
    $('#kyc-add-service').on('click', function(){
        var itemId = $('#kyc-service-select').val();
        var notes  = $('#kyc-service-notes').val();
        if (!itemId) return;
        $.post(admin_url + 'kyc_manager/add_service', {
            client_id: clientId, item_id: itemId, notes: notes
        }, function(r){
            if (r.success) {
                alert_float('success', r.message);
                location.reload();
            } else {
                alert_float('warning', r.message);
            }
        }, 'json');
    });

    // Remove service
    $(document).on('click', '.btn-remove-service', function(){
        var id = $(this).data('id');
        $.post(admin_url + 'kyc_manager/remove_service/' + id, {}, function(r){
            if (r.success) {
                $('#svc-row-' + id).fadeOut();
                alert_float('success', r.message);
            }
        }, 'json');
    });
});
</script>