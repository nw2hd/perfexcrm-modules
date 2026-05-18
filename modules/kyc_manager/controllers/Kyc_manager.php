<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Kyc_manager extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('kyc_manager/kyc_manager_model', 'kycmodel');
        $this->load->library('form_validation');
    }

    // ─────────────────────────────────────────
    // DASHBOARD
    // ─────────────────────────────────────────

    public function dashboard()
    {
        if (!has_permission('kyc_manager', '', 'view')) {
            access_denied('kyc_manager');
        }

        $data['title']               = _l('kyc_menu_title');
        $data['status_counts']       = $this->kycmodel->count_by_status();
        $data['risk_counts']         = $this->kycmodel->count_by_risk();
        $data['expiring_profiles']   = $this->kycmodel->get_expiring_profiles(30);
        $data['expiring_documents']  = $this->kycmodel->get_expiring_documents(30);
        $data['recently_verified']   = $this->kycmodel->get_recently_verified(5);
        $data['no_kyc_clients']      = $this->kycmodel->get_clients_without_kyc();

        $this->load->view('kyc_manager/admin/dashboard', $data);
    }

    // ─────────────────────────────────────────
    // PROFILES
    // ─────────────────────────────────────────

    public function profiles()
    {
        if (!has_permission('kyc_manager', '', 'view')) {
            access_denied('kyc_manager');
        }

        $filters = [
            'status'     => $this->input->get('status'),
            'risk_level' => $this->input->get('risk_level'),
            'country'    => $this->input->get('country'),
            'search'     => $this->input->get('search'),
        ];

        $data['title']    = _l('kyc_profiles');
        $data['profiles'] = $this->kycmodel->get_all_profiles($filters);
        $data['statuses'] = kyc_statuses();
        $data['filters']  = $filters;

        $this->load->view('kyc_manager/admin/profile/index', $data);
    }

    public function profile($clientId = 0)
    {
        if (!$clientId) {
            redirect(admin_url('kyc_manager/profiles'));
        }

        if (!has_permission('kyc_manager', '', 'view')) {
            access_denied('kyc_manager');
        }

        if ($this->input->post()) {
            if (!has_permission('kyc_manager', '', 'edit')) {
                access_denied('kyc_manager');
            }
            $this->_save_profile($clientId);
            return;
        }

        $profile   = $this->kycmodel->get_profile($clientId);
        $documents = $this->kycmodel->get_documents($clientId);
        $services  = $this->kycmodel->get_services($clientId);
        $auditLog  = $this->kycmodel->get_audit_log($clientId);
        $docTypes  = $this->kycmodel->get_document_types(true);

        $staff = $this->db->select('staffid, firstname, lastname')
            ->where('active', 1)
            ->get(db_prefix() . 'staff')
            ->result();

        $items = $this->db->select('id, description')
            ->order_by('description', 'ASC')
            ->get(db_prefix() . 'items')
            ->result();

        $clientName = kyc_get_client_name($clientId);

        $data['title']       = _l('kyc_profile') . ' — ' . $clientName;
        $data['client_id']   = $clientId;
        $data['client_name'] = $clientName;
        $data['profile']     = $profile;
        $data['documents']   = $documents;
        $data['services']    = $services;
        $data['audit_log']   = $auditLog;
        $data['doc_types']   = $docTypes;
        $data['staff']       = $staff;
        $data['items']       = $items;
        $data['statuses']    = kyc_statuses();

        $this->load->view('kyc_manager/admin/profile/view', $data);
    }

    private function _save_profile($clientId)
    {
        $post = $this->input->post(null, true);

        $data = [
            'legal_name'            => trim($post['legal_name'] ?? ''),
            'registration_number'   => trim($post['registration_number'] ?? ''),
            'tax_id'                => trim($post['tax_id'] ?? ''),
            'date_of_incorporation' => !empty($post['date_of_incorporation'])
                ? $post['date_of_incorporation'] : null,
            'date_of_birth'         => !empty($post['date_of_birth'])
                ? $post['date_of_birth'] : null,
            'legal_address'         => trim($post['legal_address'] ?? ''),
            'operating_address'     => trim($post['operating_address'] ?? ''),
            'country'               => trim($post['country'] ?? ''),
            'business_type'         => $post['business_type'] ?? '',
            'industry'              => trim($post['industry'] ?? ''),
            'website'               => trim($post['website'] ?? ''),
            'annual_turnover'       => trim($post['annual_turnover'] ?? ''),
            'employees'             => trim($post['employees'] ?? ''),
            'risk_level'            => $post['risk_level'] ?? 'low',
            'status'                => $post['status'] ?? 'in_progress',
            'kyc_officer'           => !empty($post['kyc_officer'])
                ? (int) $post['kyc_officer'] : null,
            'admin_notes'           => trim($post['admin_notes'] ?? ''),
        ];

        $photo = $this->_upload_file('client_photo', 'kyc_manager/photos');
        if ($photo !== null && $photo !== false) {
            $data['client_photo'] = $photo;
        }

        $this->kycmodel->save_profile($clientId, $data);
        set_alert('success', _l('kyc_profile_saved'));
        redirect(admin_url('kyc_manager/profile/' . $clientId));
    }

    public function change_status()
    {
        if (!has_permission('kyc_manager', '', 'edit')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            die();
        }

        $clientId = (int) $this->input->post('client_id');
        $status   = $this->input->post('status', true);
        $reason   = $this->input->post('reason', true);

        if (!$clientId || !$status) {
            echo json_encode(['success' => false, 'message' => 'Invalid input']);
            die();
        }

        $this->kycmodel->update_status($clientId, $status, $reason);
        $this->_notify_client_status($clientId, $status, $reason);

        echo json_encode([
            'success' => true,
            'badge'   => kyc_status_badge($status),
            'message' => _l('kyc_status_updated'),
        ]);
        die();
    }

    public function delete_profile($clientId)
    {
        if (!has_permission('kyc_manager', '', 'delete')) {
            access_denied('kyc_manager');
        }

        $this->kycmodel->delete_profile((int) $clientId);
        set_alert('success', _l('kyc_profile_deleted'));
        redirect(admin_url('kyc_manager/profiles'));
    }

    public function start_kyc($clientId)
    {
        if (!has_permission('kyc_manager', '', 'create')) {
            access_denied('kyc_manager');
        }

        $profile = $this->kycmodel->get_profile($clientId);
        if (!$profile) {
            $this->kycmodel->save_profile($clientId, [
                'status' => 'in_progress',
            ]);
        }

        redirect(admin_url('kyc_manager/profile/' . $clientId));
    }

    // ─────────────────────────────────────────
    // DOCUMENTS
    // ─────────────────────────────────────────

    public function upload_document()
    {
        if (!has_permission('kyc_manager', '', 'create')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            die();
        }

        $clientId = (int) $this->input->post('client_id');
        $post     = $this->input->post(null, true);

        if (!$clientId) {
            echo json_encode(['success' => false, 'message' => 'Invalid client']);
            die();
        }

        $filename = $this->_upload_file('document_file', 'kyc_manager');
        if ($filename === false) {
            echo json_encode(['success' => false, 'message' => _l('kyc_upload_error')]);
            die();
        }

        $docData = [
            'client_id'         => $clientId,
            'document_type_id'  => (int) ($post['document_type_id'] ?? 0),
            'document_name'     => trim($post['document_name'] ?? ''),
            'document_number'   => trim($post['document_number'] ?? ''),
            'issue_date'        => !empty($post['issue_date'])  ? $post['issue_date']  : null,
            'expiry_date'       => !empty($post['expiry_date']) ? $post['expiry_date'] : null,
            'issuing_authority' => trim($post['issuing_authority'] ?? ''),
            'issuing_country'   => trim($post['issuing_country'] ?? ''),
            'filename'          => $filename,
            'original_name'     => isset($_FILES['document_file']['name'])
                ? $_FILES['document_file']['name'] : '',
            'file_type'         => pathinfo($filename, PATHINFO_EXTENSION),
        ];

        $id = $this->kycmodel->save_document($docData);

        echo json_encode([
            'success' => (bool) $id,
            'message' => $id ? _l('kyc_document_uploaded') : _l('kyc_error'),
        ]);
        die();
    }

    public function review_document()
    {
        if (!has_permission('kyc_manager', '', 'edit')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            die();
        }

        $docId  = (int) $this->input->post('document_id');
        $status = $this->input->post('status', true);
        $notes  = $this->input->post('notes', true);

        $ok = $this->kycmodel->review_document($docId, $status, $notes);

        echo json_encode([
            'success' => $ok,
            'badge'   => kyc_doc_status_badge($status),
            'message' => $ok ? _l('kyc_document_reviewed') : _l('kyc_error'),
        ]);
        die();
    }

    public function delete_document($id)
    {
        if (!has_permission('kyc_manager', '', 'delete')) {
            echo json_encode(['success' => false]);
            die();
        }

        $doc = $this->kycmodel->get_document($id);
        $ok  = $this->kycmodel->delete_document($id);

        if ($this->input->is_ajax_request()) {
            echo json_encode(['success' => $ok, 'message' => _l('kyc_document_deleted')]);
            die();
        }

        set_alert($ok ? 'success' : 'danger',
            $ok ? _l('kyc_document_deleted') : _l('kyc_error'));
        redirect(admin_url('kyc_manager/profile/' . ($doc ? $doc->client_id : '')));
    }

    public function download_document($id)
    {
        if (!has_permission('kyc_manager', '', 'view')) {
            access_denied('kyc_manager');
        }

        $doc = $this->kycmodel->get_document((int) $id);
        if (!$doc || empty($doc->filename)) {
            show_404();
        }

        $path = FCPATH . 'uploads/kyc_manager/' . $doc->filename;
        if (!file_exists($path)) {
            show_404();
        }

        $downloadName = !empty($doc->original_name) ? $doc->original_name : $doc->filename;

        $this->load->helper('download');
        force_download($downloadName, file_get_contents($path));
    }

    // ─────────────────────────────────────────
    // SERVICES
    // ─────────────────────────────────────────

    public function add_service()
    {
        if (!has_permission('kyc_manager', '', 'edit')) {
            echo json_encode(['success' => false]);
            die();
        }

        $clientId = (int) $this->input->post('client_id');
        $itemId   = (int) $this->input->post('item_id');
        $notes    = $this->input->post('notes', true);

        $id = $this->kycmodel->add_service($clientId, $itemId, $notes);

        echo json_encode([
            'success' => (bool) $id,
            'message' => $id ? _l('kyc_service_added') : _l('kyc_service_exists'),
        ]);
        die();
    }

    public function remove_service($id)
    {
        if (!has_permission('kyc_manager', '', 'edit')) {
            echo json_encode(['success' => false]);
            die();
        }

        $this->kycmodel->remove_service((int) $id);

        echo json_encode(['success' => true, 'message' => _l('kyc_service_removed')]);
        die();
    }

    // ─────────────────────────────────────────
    // DOCUMENT TYPES
    // ─────────────────────────────────────────

    public function document_types()
    {
        if (!has_permission('kyc_manager', '', 'edit')) {
            access_denied('kyc_manager');
        }

        if ($this->input->post()) {
            $post     = $this->input->post(null, true);
            $id       = (int) ($post['id'] ?? 0);
            $typeData = [
                'name'        => trim($post['name']),
                'description' => trim($post['description'] ?? ''),
                'required'    => isset($post['required']) ? 1 : 0,
                'sort_order'  => (int) ($post['sort_order'] ?? 0),
                'active'      => isset($post['active']) ? 1 : 0,
            ];
            $this->kycmodel->save_document_type($id, $typeData);
            set_alert('success', _l('kyc_doc_type_saved'));
            redirect(admin_url('kyc_manager/document_types'));
            return;
        }

        $data['title']     = _l('kyc_document_types');
        $data['doc_types'] = $this->kycmodel->get_document_types();
        $this->load->view('kyc_manager/admin/settings/document_types', $data);
    }

    public function delete_document_type($id)
    {
        if (!has_permission('kyc_manager', '', 'delete')) {
            access_denied('kyc_manager');
        }
        $this->kycmodel->delete_document_type((int) $id);
        set_alert('success', _l('kyc_doc_type_deleted'));
        redirect(admin_url('kyc_manager/document_types'));
    }

    // ─────────────────────────────────────────
    // SETTINGS
    // ─────────────────────────────────────────

    public function settings()
    {
        if (!has_permission('kyc_manager', '', 'edit')) {
            access_denied('kyc_manager');
        }

        if ($this->input->post()) {
            $post    = $this->input->post(null, true);
            $options = [
                'kyc_pdf_heading',
                'kyc_pdf_heading_color',
                'kyc_validity_months',
                'kyc_client_upload_enabled',
                'kyc_expiry_reminder_days',
                'kyc_expiry_reminder_enabled',
            ];
            foreach ($options as $key) {
                $val = isset($post[$key]) ? $post[$key] : '0';
                update_option($key, $val);
            }
            set_alert('success', _l('kyc_settings_saved'));
            redirect(admin_url('kyc_manager/settings'));
            return;
        }

        $data['title'] = _l('kyc_settings');
        $this->load->view('kyc_manager/admin/settings/index', $data);
    }

    // ─────────────────────────────────────────
    // REPORTS
    // ─────────────────────────────────────────

    public function report_kyc()
    {
        if (!has_permission('kyc_manager', '', 'view')) {
            access_denied('kyc_manager');
        }

        $filters = [
            'status'     => $this->input->get('status'),
            'risk_level' => $this->input->get('risk_level'),
            'country'    => $this->input->get('country'),
            'search'     => $this->input->get('search'),
        ];

        $data['title']    = _l('kyc_report');
        $data['profiles'] = $this->kycmodel->get_kyc_report($filters);
        $data['statuses'] = kyc_statuses();
        $data['filters']  = $filters;

        $this->load->view('kyc_manager/admin/reports/kyc_report', $data);
    }

    public function report_expiry()
    {
        if (!has_permission('kyc_manager', '', 'view')) {
            access_denied('kyc_manager');
        }

        $days = (int) ($this->input->get('days') ?: 90);
        $type = $this->input->get('type') ?: 'all';

        $data['title']   = _l('kyc_expiry_report');
        $data['results'] = $this->kycmodel->get_expiry_report($days, $type);
        $data['days']    = $days;
        $data['type']    = $type;

        $this->load->view('kyc_manager/admin/reports/expiry_report', $data);
    }

    public function report_services()
    {
        if (!has_permission('kyc_manager', '', 'view')) {
            access_denied('kyc_manager');
        }

        $data['title']    = _l('kyc_service_report');
        $data['services'] = $this->kycmodel->get_service_report();

        $this->load->view('kyc_manager/admin/reports/service_report', $data);
    }

    public function export_csv($type = 'kyc')
    {
        if (!has_permission('kyc_manager', '', 'view')) {
            access_denied('kyc_manager');
        }

        header('Content-Type: text/csv; charset=utf-8');

        if ($type === 'kyc') {
            header('Content-Disposition: attachment; filename=kyc_report_' . date('Y-m-d') . '.csv');
            $profiles = $this->kycmodel->get_all_profiles([]);
            $out      = fopen('php://output', 'w');
            fputcsv($out, [
                'Client', 'Legal Name', 'Country', 'Business Type',
                'Risk Level', 'Status', 'Expiry Date', 'Registration #', 'Tax ID',
            ]);
            foreach ($profiles as $p) {
                fputcsv($out, [
                    isset($p->client_company)      ? $p->client_company      : '',
                    isset($p->legal_name)           ? $p->legal_name           : '',
                    isset($p->country)              ? $p->country              : '',
                    isset($p->business_type)        ? $p->business_type        : '',
                    isset($p->risk_level)           ? $p->risk_level           : '',
                    isset($p->status)               ? $p->status               : '',
                    isset($p->expiry_date)          ? $p->expiry_date          : '',
                    isset($p->registration_number)  ? $p->registration_number  : '',
                    isset($p->tax_id)               ? $p->tax_id               : '',
                ]);
            }
            fclose($out);

        } elseif ($type === 'expiry') {
            header('Content-Disposition: attachment; filename=expiry_report_' . date('Y-m-d') . '.csv');
            $results = $this->kycmodel->get_expiry_report(90, 'all');
            $out     = fopen('php://output', 'w');
            fputcsv($out, ['Type', 'Client', 'Detail', 'Expiry Date', 'Days Left']);
            foreach ($results as $r) {
                fputcsv($out, [
                    $r['type'],
                    $r['client'],
                    $r['detail'],
                    $r['expiry_date'],
                    $r['days_left'],
                ]);
            }
            fclose($out);
        }

        die();
    }

    // ─────────────────────────────────────────
    // PDF — Browser print with embedded docs
    // ─────────────────────────────────────────

    public function pdf($clientId)
    {
        if (!has_permission('kyc_manager', '', 'view')) {
            access_denied('kyc_manager');
        }

        $clientId  = (int) $clientId;
        $profile   = $this->kycmodel->get_profile($clientId);
        $documents = $this->kycmodel->get_documents($clientId); // ALL docs
        $services  = $this->kycmodel->get_services($clientId);

        $data['profile']       = $profile;
        $data['documents']     = $documents;
        $data['services']      = $services;
        $data['client_name']   = kyc_get_client_name($clientId);
        $data['client_id']     = $clientId;
        $data['heading']       = get_option('kyc_pdf_heading') ?: 'Know Your Customer (KYC) Form';
        $data['heading_color'] = get_option('kyc_pdf_heading_color') ?: '#2c3e50';
        $data['print_mode']    = true;
        $data['auto_print']    = true;

        $this->load->view('kyc_manager/admin/pdf/kyc_form', $data);
    }

    public function print_kyc($clientId)
    {
        if (!has_permission('kyc_manager', '', 'view')) {
            access_denied('kyc_manager');
        }

        $clientId  = (int) $clientId;
        $profile   = $this->kycmodel->get_profile($clientId);
        $documents = $this->kycmodel->get_documents($clientId); // ALL docs
        $services  = $this->kycmodel->get_services($clientId);

        $data['profile']       = $profile;
        $data['documents']     = $documents;
        $data['services']      = $services;
        $data['client_name']   = kyc_get_client_name($clientId);
        $data['client_id']     = $clientId;
        $data['heading']       = get_option('kyc_pdf_heading') ?: 'Know Your Customer (KYC) Form';
        $data['heading_color'] = get_option('kyc_pdf_heading_color') ?: '#2c3e50';
        $data['print_mode']    = true;
        $data['auto_print']    = false;

        $this->load->view('kyc_manager/admin/pdf/kyc_form', $data);
    }

    // ─────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────

    private function _upload_file($fieldName, $subdir)
    {
        if (empty($_FILES[$fieldName]['name'])) {
            return null;
        }

        $dir = FCPATH . 'uploads/' . $subdir . '/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $config = [
            'upload_path'   => $dir,
            'allowed_types' => 'pdf|doc|docx|jpg|jpeg|png|gif',
            'max_size'      => 10240,
            'encrypt_name'  => true,
        ];

        $this->load->library('upload', $config);
        $this->upload->initialize($config);

        if (!$this->upload->do_upload($fieldName)) {
            log_message('error', '[KYC] Upload: ' . $this->upload->display_errors());
            return false;
        }

        return $this->upload->data('file_name');
    }

    private function _notify_client_status($clientId, $status, $reason = '')
    {
        $email = kyc_get_client_email($clientId);
        if (empty($email)) {
            return false;
        }

        $name    = kyc_get_client_name($clientId);
        $company = get_option('companyname');

        $subject = 'KYC Status Update — ' . kyc_status_label($status);

        $message = '<p>Dear ' . htmlspecialchars($name) . ',</p>'
            . '<p>Your KYC status has been updated to: <strong>'
            . htmlspecialchars(kyc_status_label($status)) . '</strong></p>';

        if ($status === 'rejected' && !empty($reason)) {
            $message .= '<p>Reason: ' . htmlspecialchars($reason) . '</p>';
        }

        if ($status === 'verified') {
            $message .= '<p>Your KYC verification is now complete. '
                . 'Thank you for your cooperation.</p>';
        }

        $message .= '<p>Regards,<br>' . htmlspecialchars($company) . '</p>';

        $CI = &get_instance();
        $CI->load->library('email');
        $CI->email->initialize(['mailtype' => 'html', 'charset' => 'utf-8']);
        $CI->email->from(get_option('smtp_email'), $company);
        $CI->email->to($email);
        $CI->email->subject($subject);
        $CI->email->message($message);

        return $CI->email->send(false);
    }
}