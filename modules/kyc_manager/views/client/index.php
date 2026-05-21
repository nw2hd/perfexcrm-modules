<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<!-- KYC Status -->
<?php if ($profile) : ?>
<div class="panel_s">
    <div class="panel-heading">
        <h4 class="panel-title"><i class="fa fa-id-card"></i> <?php echo _l('kyc_menu_title'); ?></h4>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-sm-4">
                <strong><?php echo _l('kyc_status'); ?></strong><br>
                <?php echo kyc_status_badge($profile->status); ?>
            </div>
            <div class="col-sm-4">
                <strong><?php echo _l('kyc_expiry_date'); ?></strong><br>
                <?php echo (isset($profile->expiry_date) && $profile->expiry_date) ? date('d M Y', strtotime($profile->expiry_date)) : '—'; ?>
            </div>
            <div class="col-sm-4">
                <strong><?php echo _l('kyc_risk'); ?></strong><br>
                <?php echo kyc_risk_badge(isset($profile->risk_level) ? $profile->risk_level : 'low'); ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Documents -->
<div class="panel_s">
    <div class="panel-heading">
        <h4 class="panel-title">
            <i class="fa fa-folder-open"></i> <?php echo _l('kyc_documents'); ?>
            <?php if (isset($upload_enabled) && $upload_enabled) : ?>
            <button class="btn btn-primary btn-xs pull-right" data-toggle="modal" data-target="#clientUploadModal">
                <i class="fa fa-upload"></i> <?php echo _l('kyc_upload_document'); ?>
            </button>
            <?php endif; ?>
        </h4>
    </div>
    <div class="panel-body">
        <?php if (!empty($documents)) : ?>
        <table class="table table-condensed">
            <thead><tr>
                <th><?php echo _l('kyc_doc_type'); ?></th>
                <th><?php echo _l('kyc_doc_name'); ?></th>
                <th><?php echo _l('kyc_doc_status'); ?></th>
                <th><?php echo _l('kyc_expiry_date'); ?></th>
                <th></th>
            </table></thead>
            <tbody>
            <?php foreach ($documents as $doc) : ?>
            <tr>
                <td><?php echo htmlspecialchars(isset($doc->type_name) ? $doc->type_name : ''); ?></td>
                <td><?php echo htmlspecialchars(isset($doc->document_name) ? $doc->document_name : ''); ?></td>
                <td><?php echo kyc_doc_status_badge($doc->status); ?></td>
                <td><?php echo (isset($doc->expiry_date) && $doc->expiry_date) ? date('d M Y', strtotime($doc->expiry_date)) : '—'; ?></td>
                <td>
                    <a href="<?php echo site_url('kyc_manager/kyc_client/download_document/' . $doc->id); ?>"
                       class="btn btn-xs btn-default">
                        <i class="fa fa-download"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else : ?>
        <p class="text-muted text-center"><?php echo _l('kyc_no_documents'); ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Services -->
<?php if (!empty($services)) : ?>
<div class="panel_s">
    <div class="panel-heading">
        <h4 class="panel-title"><i class="fa fa-briefcase"></i> <?php echo _l('kyc_services'); ?></h4>
    </div>
    <div class="panel-body">
        <table class="table table-condensed">
            <thead><tr>
                <th><?php echo _l('kyc_service_name'); ?></th>
                <th><?php echo _l('kyc_service_notes'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($services as $svc) : ?>
            <tr>
                <td><?php echo htmlspecialchars(isset($svc->item_name) ? $svc->item_name : ''); ?></td>
                <td><?php echo htmlspecialchars(isset($svc->notes) ? $svc->notes : ''); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Client Upload Modal -->
<?php if (isset($upload_enabled) && $upload_enabled) : ?>
<div class="modal fade" id="clientUploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><?php echo _l('kyc_upload_document'); ?></h4>
            </div>
            <div class="modal-body">
                <form id="client-upload-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <label><?php echo _l('kyc_doc_type'); ?></label>
                        <select name="document_type_id" class="form-control">
                            <?php foreach ($doc_types as $dt) : ?>
                            <option value="<?php echo $dt->id; ?>"><?php echo htmlspecialchars($dt->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?php echo _l('kyc_doc_name'); ?></label>
                        <input type="text" name="document_name" class="form-control">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?php echo _l('kyc_doc_number'); ?></label>
                                <input type="text" name="document_number" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><?php echo _l('kyc_issue_date'); ?></label>
                                <input type="date" name="issue_date" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><?php echo _l('kyc_expiry_date'); ?></label>
                                <input type="date" name="expiry_date" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><?php echo _l('kyc_file'); ?></label>
                        <input type="file" name="document_file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('kyc_cancel'); ?></button>
                <button type="button" class="btn btn-primary" id="client-upload-btn">
                    <i class="fa fa-upload"></i> <?php echo _l('kyc_upload'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
<script>
$(function(){
    // Get CSRF token values
    var csrf_token_name = '<?php echo $this->security->get_csrf_token_name(); ?>';
    var csrf_hash = '<?php echo $this->security->get_csrf_hash(); ?>';
    
    $('#client-upload-btn').on('click', function(){
        var fd   = new FormData($('#client-upload-form')[0]);
        
        // ✅ ADD CSRF TOKEN - FIXES THE "PAGE EXPIRED" ERROR
        fd.append(csrf_token_name, csrf_hash);
        
        var $btn = $(this);
        var fileInput = $('input[name="document_file"]')[0];
        
        if (!fileInput.files.length) {
            alert('Please select a file to upload');
            return;
        }
        
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Uploading...');
        $.ajax({
            url: '<?php echo site_url('kyc_manager/kyc_client/upload_document'); ?>',
            type: 'POST', 
            data: fd, 
            processData: false, 
            contentType: false, 
            dataType: 'json',
            success: function(r){
                if (r.success) { 
                    location.reload(); 
                } else { 
                    alert(r.message); 
                    $btn.prop('disabled', false).html('<i class="fa fa-upload"></i> <?php echo _l('kyc_upload'); ?>');
                }
            },
            error: function(xhr, status, error) {
                alert('Upload failed: ' + error);
                $btn.prop('disabled', false).html('<i class="fa fa-upload"></i> <?php echo _l('kyc_upload'); ?>');
            }
        });
    });
});
</script>
<?php endif; ?>
