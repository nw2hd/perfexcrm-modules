<?php
$companyName  = get_option('companyname');
$companyLogo  = get_option('company_logo');
$logoUrl      = $companyLogo ? base_url('uploads/company/' . $companyLogo) : '';
$headingColor = isset($heading_color) ? $heading_color : '#2c3e50';
$headingText  = isset($heading)       ? $heading       : 'Know Your Customer (KYC) Form';

$autoPrint = isset($auto_print) && $auto_print;

// Separate documents into images and non-images
$imageDocuments    = [];
$nonImageDocuments = [];

if (!empty($documents)) {
    foreach ($documents as $doc) {
        $ext = strtolower(isset($doc->file_type) ? $doc->file_type : '');
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $imageDocuments[] = $doc;
        } else {
            $nonImageDocuments[] = $doc;
        }
    }
}

// Also get ALL documents (not just approved) for full print
$CI  = &get_instance();
$allDocuments = [];
if (isset($client_id)) {
    $CI->load->model('kyc_manager/kyc_manager_model', 'kycmodel');
    $allDocuments = $CI->kycmodel->get_documents($client_id);

    // Separate all docs into images and non-images
    $allImageDocs    = [];
    $allNonImageDocs = [];
    foreach ($allDocuments as $adoc) {
        $ext = strtolower(isset($adoc->file_type) ? $adoc->file_type : '');
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $allImageDocs[] = $adoc;
        } else {
            $allNonImageDocs[] = $adoc;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>KYC — <?php echo htmlspecialchars(isset($client_name) ? $client_name : ''); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #333;
            padding: 30px;
            max-width: 800px;
            margin: 0 auto;
        }
        table { width: 100%; border-collapse: collapse; }

        .info-table td {
            padding: 6px 10px;
            border: 1px solid #ddd;
            font-size: 11px;
        }
        .info-table td:first-child {
            background: #f9f9f9;
            font-weight: bold;
            width: 180px;
        }

        .doc-table th, .doc-table td {
            padding: 6px 10px;
            border: 1px solid #ddd;
            font-size: 11px;
            text-align: left;
        }
        .doc-table th { background: #f0f0f0; }

        h3.section-heading {
            color: <?php echo htmlspecialchars($headingColor); ?>;
            border-bottom: 2px solid <?php echo htmlspecialchars($headingColor); ?>;
            padding-bottom: 5px;
            margin: 20px 0 10px;
            font-size: 14px;
        }

        .header-table td { border: none; vertical-align: middle; }

        .status-bar {
            background: #f8f9fa;
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 11px;
        }

        .signature-table td {
            border: none;
            padding-top: 40px;
            border-top: 1px solid #333;
            font-size: 11px;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 10px;
            color: #999;
        }

        /* Toolbar */
        .toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #2c3e50;
            padding: 10px 20px;
            z-index: 1000;
            text-align: center;
        }
        .toolbar a, .toolbar button {
            display: inline-block;
            padding: 8px 20px;
            margin: 0 5px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            color: #fff;
        }
        .toolbar .btn-print  { background: #27ae60; }
        .toolbar .btn-back   { background: #95a5a6; }
        .toolbar .btn-toggle { background: #3498db; }
        .toolbar select {
            padding: 8px 12px;
            border-radius: 4px;
            border: none;
            font-size: 13px;
            vertical-align: middle;
        }

        /* Document images */
        .doc-image-page {
            page-break-before: always;
            margin-top: 30px;
            text-align: center;
        }
        .doc-image-page:first-of-type {
            page-break-before: auto;
        }
        .doc-image-header {
            background: <?php echo htmlspecialchars($headingColor); ?>;
            color: #fff;
            padding: 8px 14px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 10px;
            border-radius: 4px 4px 0 0;
            text-align: left;
        }
        .doc-image-info {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-top: none;
            padding: 8px 14px;
            margin-bottom: 10px;
            font-size: 10px;
            text-align: left;
        }
        .doc-image-container {
            border: 1px solid #ddd;
            padding: 10px;
            background: #fff;
        }
        .doc-image-container img {
            max-width: 100%;
            max-height: 900px;
            display: block;
            margin: 0 auto;
        }

        .doc-pdf-reference {
            background: #fef9e7;
            border: 1px solid #f39c12;
            border-radius: 4px;
            padding: 10px 14px;
            margin-bottom: 8px;
            font-size: 11px;
        }

        /* Print controls */
        .include-docs-section {
            display: block;
        }
        .include-docs-section.hidden-section {
            display: none;
        }

        @media print {
            .toolbar { display: none !important; }
            body { padding: 10px; }
            .doc-image-page { page-break-before: always; }
            .include-docs-section.hidden-section { display: none !important; }
        }
    </style>
</head>
<body>

<!-- Toolbar -->
<div class="toolbar">
    <button class="btn-print" onclick="window.print();">
        ⎙ Print / Save as PDF
    </button>
    <button class="btn-toggle" onclick="toggleDocuments();">
        📎 Toggle Documents
    </button>
    <select onchange="changeDocFilter(this.value);">
        <option value="all">All Documents</option>
        <option value="approved">Approved Only</option>
        <option value="images">Images Only</option>
    </select>
    <a class="btn-back" href="<?php echo admin_url('kyc_manager/profile/' . (isset($client_id) ? $client_id : '')); ?>">
        ← Back
    </a>
</div>

<div style="margin-top:60px;">

    <!-- ═══════════════════════════════════════ -->
    <!-- HEADER -->
    <!-- ═══════════════════════════════════════ -->
    <table class="header-table" style="margin-bottom:20px;">
        <tr>
            <td style="width:30%;">
                <?php if ($logoUrl) : ?>
                <img src="<?php echo $logoUrl; ?>" style="max-height:60px;">
                <?php endif; ?>
            </td>
            <td style="width:40%;text-align:center;">
                <h2 style="color:<?php echo htmlspecialchars($headingColor); ?>;margin:0;font-size:18px;">
                    <?php echo htmlspecialchars($headingText); ?>
                </h2>
                <p style="margin:5px 0 0;font-size:11px;color:#666;">
                    <?php echo htmlspecialchars($companyName); ?>
                </p>
            </td>
            <td style="width:30%;text-align:right;">
                <?php if (isset($profile->client_photo) && !empty($profile->client_photo)) : ?>
                <img src="<?php echo base_url('uploads/kyc_manager/photos/' . $profile->client_photo); ?>"
                     style="max-height:60px;max-width:60px;border-radius:4px;border:1px solid #ddd;">
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <!-- ═══════════════════════════════════════ -->
    <!-- STATUS BAR -->
    <!-- ═══════════════════════════════════════ -->
    <?php if (isset($profile->status)) : ?>
    <div class="status-bar">
        <strong>Status:</strong> <?php echo strtoupper(kyc_status_label($profile->status)); ?>
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>Risk:</strong> <?php echo isset($profile->risk_level) ? strtoupper($profile->risk_level) : 'LOW'; ?>
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>Date:</strong> <?php echo date('d M Y'); ?>
        <?php if (isset($profile->expiry_date) && $profile->expiry_date) : ?>
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>Expires:</strong> <?php echo date('d M Y', strtotime($profile->expiry_date)); ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════ -->
    <!-- CLIENT INFORMATION -->
    <!-- ═══════════════════════════════════════ -->
    <h3 class="section-heading">Client Information</h3>
    <table class="info-table">
        <?php
        $fields = [
            ['Client Company',        isset($client_name) ? $client_name : ''],
            ['Legal Name',            isset($profile->legal_name)            ? $profile->legal_name : ''],
            ['Registration No.',      isset($profile->registration_number)   ? $profile->registration_number : ''],
            ['Tax ID',                isset($profile->tax_id)                ? $profile->tax_id : ''],
            ['Business Type',         isset($profile->business_type)         ? ucfirst($profile->business_type) : ''],
            ['Country',               isset($profile->country)               ? $profile->country : ''],
            ['Industry',              isset($profile->industry)              ? $profile->industry : ''],
            ['Date of Incorporation', (isset($profile->date_of_incorporation) && $profile->date_of_incorporation) ? date('d M Y', strtotime($profile->date_of_incorporation)) : ''],
            ['Date of Birth',         (isset($profile->date_of_birth) && $profile->date_of_birth) ? date('d M Y', strtotime($profile->date_of_birth)) : ''],
            ['Website',               isset($profile->website)               ? $profile->website : ''],
            ['Annual Turnover',       isset($profile->annual_turnover)       ? $profile->annual_turnover : ''],
            ['Employees',             isset($profile->employees)             ? $profile->employees : ''],
            ['Legal Address',         isset($profile->legal_address)         ? $profile->legal_address : ''],
            ['Operating Address',     isset($profile->operating_address)     ? $profile->operating_address : ''],
        ];
        foreach ($fields as $f) :
        ?>
        <tr>
            <td><?php echo $f[0]; ?></td>
            <td><?php echo htmlspecialchars($f[1]); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <!-- ═══════════════════════════════════════ -->
    <!-- DOCUMENTS TABLE -->
    <!-- ═══════════════════════════════════════ -->
    <?php if (!empty($allDocuments)) : ?>
    <h3 class="section-heading">Documents</h3>
    <table class="doc-table">
        <tr>
            <th>#</th>
            <th>Document Type</th>
            <th>Name</th>
            <th>Number</th>
            <th>Issue Date</th>
            <th>Expiry Date</th>
            <th>Status</th>
            <th>File</th>
        </tr>
        <?php $docNum = 0; foreach ($allDocuments as $doc) :
            $docNum++;
            $ext      = strtolower(isset($doc->file_type) ? $doc->file_type : '');
            $isImage  = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
            $statusTx = isset($doc->status) ? ucfirst($doc->status) : '';
        ?>
        <tr>
            <td><?php echo $docNum; ?></td>
            <td><?php echo htmlspecialchars(isset($doc->type_name)       ? $doc->type_name : ''); ?></td>
            <td><?php echo htmlspecialchars(isset($doc->document_name)   ? $doc->document_name : ''); ?></td>
            <td><?php echo htmlspecialchars(isset($doc->document_number) ? $doc->document_number : ''); ?></td>
            <td><?php echo (isset($doc->issue_date) && $doc->issue_date)   ? date('d M Y', strtotime($doc->issue_date))  : ''; ?></td>
            <td><?php echo (isset($doc->expiry_date) && $doc->expiry_date) ? date('d M Y', strtotime($doc->expiry_date)) : ''; ?></td>
            <td><?php echo $statusTx; ?></td>
            <td><?php echo $isImage ? '📷 Image' : '📄 ' . strtoupper($ext); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════ -->
    <!-- SERVICES -->
    <!-- ═══════════════════════════════════════ -->
    <?php if (!empty($services)) : ?>
    <h3 class="section-heading">Services</h3>
    <table class="doc-table">
        <tr>
            <th>Service</th>
            <th>Notes</th>
        </tr>
        <?php foreach ($services as $svc) : ?>
        <tr>
            <td><?php echo htmlspecialchars(isset($svc->item_name) ? $svc->item_name : ''); ?></td>
            <td><?php echo htmlspecialchars(isset($svc->notes) ? $svc->notes : ''); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════ -->
    <!-- SIGNATURE BLOCK -->
    <!-- ═══════════════════════════════════════ -->
    <table class="signature-table" style="margin-top:40px;">
        <tr>
            <td style="width:50%;">
                <strong>KYC Officer Signature</strong><br>
                <span style="color:#999;">Date: ________________</span>
            </td>
            <td style="width:50%;text-align:right;">
                <strong>Authorized Signatory</strong><br>
                <span style="color:#999;"><?php echo htmlspecialchars($companyName); ?></span>
            </td>
        </tr>
    </table>

    <p class="footer">
        Generated on <?php echo date('d M Y H:i'); ?> by <?php echo htmlspecialchars($companyName); ?>
        — This document is confidential.
    </p>

    <!-- ═══════════════════════════════════════ -->
    <!-- ATTACHED DOCUMENT IMAGES -->
    <!-- ═══════════════════════════════════════ -->
    <div id="attached-documents" class="include-docs-section">

        <?php if (!empty($allDocuments)) :
            $pageNum = 0;
            foreach ($allDocuments as $doc) :
                $ext     = strtolower(isset($doc->file_type) ? $doc->file_type : '');
                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
                $isPdf   = ($ext === 'pdf');
                $status  = isset($doc->status) ? $doc->status : 'pending';
                $pageNum++;

                $filePath = '';
                if (isset($doc->filename) && !empty($doc->filename)) {
                    $filePath = FCPATH . 'uploads/kyc_manager/' . $doc->filename;
                }
        ?>

        <div class="doc-image-page doc-page-item"
             data-status="<?php echo $status; ?>"
             data-type="<?php echo $isImage ? 'image' : 'file'; ?>">

            <div class="doc-image-header">
                📎 Document <?php echo $pageNum; ?>:
                <?php echo htmlspecialchars(isset($doc->type_name)     ? $doc->type_name : ''); ?>
                — <?php echo htmlspecialchars(isset($doc->document_name) ? $doc->document_name : ''); ?>
                <span style="float:right;"><?php echo ucfirst($status); ?></span>
            </div>

            <div class="doc-image-info">
                <?php if (isset($doc->document_number) && !empty($doc->document_number)) : ?>
                <strong>Number:</strong> <?php echo htmlspecialchars($doc->document_number); ?> &nbsp;|&nbsp;
                <?php endif; ?>
                <?php if (isset($doc->issue_date) && $doc->issue_date) : ?>
                <strong>Issued:</strong> <?php echo date('d M Y', strtotime($doc->issue_date)); ?> &nbsp;|&nbsp;
                <?php endif; ?>
                <?php if (isset($doc->expiry_date) && $doc->expiry_date) : ?>
                <strong>Expires:</strong> <?php echo date('d M Y', strtotime($doc->expiry_date)); ?> &nbsp;|&nbsp;
                <?php endif; ?>
                <?php if (isset($doc->issuing_authority) && !empty($doc->issuing_authority)) : ?>
                <strong>Authority:</strong> <?php echo htmlspecialchars($doc->issuing_authority); ?> &nbsp;|&nbsp;
                <?php endif; ?>
                <?php if (isset($doc->issuing_country) && !empty($doc->issuing_country)) : ?>
                <strong>Country:</strong> <?php echo htmlspecialchars($doc->issuing_country); ?>
                <?php endif; ?>
            </div>

            <?php if ($isImage && $filePath && file_exists($filePath)) : ?>
                <!-- Embed image directly -->
                <?php
                $imageData   = base64_encode(file_get_contents($filePath));
                $imageMime   = ($ext === 'png') ? 'image/png' : (($ext === 'gif') ? 'image/gif' : 'image/jpeg');
                ?>
                <div class="doc-image-container">
                    <img src="data:<?php echo $imageMime; ?>;base64,<?php echo $imageData; ?>"
                         alt="<?php echo htmlspecialchars(isset($doc->document_name) ? $doc->document_name : ''); ?>">
                </div>

            <?php elseif ($isPdf) : ?>
                <!-- PDF reference -->
                <div class="doc-pdf-reference">
                    <i>📄</i> <strong>PDF Document</strong> —
                    <?php echo htmlspecialchars(isset($doc->original_name) ? $doc->original_name : $doc->filename); ?>
                    <br>
                    <small style="color:#888;">
                        PDF files cannot be embedded in print view. Please download separately from the KYC profile page.
                    </small>
                </div>

            <?php else : ?>
                <!-- Other file reference -->
                <div class="doc-pdf-reference">
                    <i>📄</i> <strong><?php echo strtoupper($ext); ?> Document</strong> —
                    <?php echo htmlspecialchars(isset($doc->original_name) ? $doc->original_name : (isset($doc->filename) ? $doc->filename : '')); ?>
                    <br>
                    <small style="color:#888;">
                        This file type cannot be embedded. Please download separately.
                    </small>
                </div>

            <?php endif; ?>

        </div>

        <?php endforeach; ?>
        <?php endif; ?>

    </div>

</div>

<script>
    // Toggle documents section visibility
    var docsVisible = true;
    function toggleDocuments() {
        docsVisible = !docsVisible;
        document.getElementById('attached-documents')
            .classList.toggle('hidden-section', !docsVisible);
    }

    // Filter documents by type
    function changeDocFilter(filter) {
        var items = document.querySelectorAll('.doc-page-item');
        items.forEach(function(item) {
            var status = item.getAttribute('data-status');
            var type   = item.getAttribute('data-type');
            var show   = true;

            if (filter === 'approved' && status !== 'approved') {
                show = false;
            }
            if (filter === 'images' && type !== 'image') {
                show = false;
            }

            item.style.display = show ? 'block' : 'none';
        });
    }
</script>

<?php if ($autoPrint) : ?>
<script>
    window.onload = function() {
        setTimeout(function() { window.print(); }, 800);
    };
</script>
<?php endif; ?>

</body>
</html>