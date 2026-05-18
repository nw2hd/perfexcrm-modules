<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Kyc_client extends ClientsController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('kyc_manager/kyc_manager_model', 'kycmodel');
    }

    private function _get_client_id()
    {
        if (function_exists('get_contact_user_id')) {
            $contactId = get_contact_user_id();
            if ($contactId) {
                $contact = $this->db->where('id', $contactId)
                    ->get(db_prefix() . 'contacts')->row();
                if ($contact && isset($contact->userid)) {
                    return (int) $contact->userid;
                }
            }
        }
        if (function_exists('get_client_user_id')) {
            return (int) get_client_user_id();
        }
        return 0;
    }

    public function index()
    {
        if (!is_client_logged_in()) {
            redirect(site_url('authentication/login'));
        }

        $clientId = $this->_get_client_id();

        $data['title']     = _l('kyc_menu_title');
        $data['profile']   = $this->kycmodel->get_profile($clientId);
        $data['documents'] = $this->kycmodel->get_documents($clientId);
        $data['services']  = $this->kycmodel->get_services($clientId);

        $uploadEnabled = get_option('kyc_client_upload_enabled');
        $data['upload_enabled'] = ($uploadEnabled === '1');
        $data['doc_types']      = $this->kycmodel->get_document_types(true);

        $this->data($data);
        $this->view('kyc_manager/client/index');
        $this->layout();
    }

    public function upload_document()
    {
        if (!is_client_logged_in()) {
            redirect(site_url('authentication/login'));
        }

        $uploadEnabled = get_option('kyc_client_upload_enabled');
        if ($uploadEnabled !== '1') {
            echo json_encode(['success' => false, 'message' => 'Upload not enabled']);
            die();
        }

        $clientId = $this->_get_client_id();
        $post     = $this->input->post(null, true);

        // Upload file
        $dir = FCPATH . 'uploads/kyc_manager/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $config = [
            'upload_path'   => $dir,
            'allowed_types' => 'pdf|doc|docx|jpg|jpeg|png',
            'max_size'      => 10240,
            'encrypt_name'  => true,
        ];

        $this->load->library('upload', $config);
        $this->upload->initialize($config);

        if (!$this->upload->do_upload('document_file')) {
            echo json_encode(['success' => false, 'message' => strip_tags($this->upload->display_errors())]);
            die();
        }

        $filename = $this->upload->data('file_name');

        $docData = [
            'client_id'         => $clientId,
            'document_type_id'  => (int) ($post['document_type_id'] ?? 0),
            'document_name'     => trim($post['document_name'] ?? ''),
            'document_number'   => trim($post['document_number'] ?? ''),
            'issue_date'        => !empty($post['issue_date'])  ? $post['issue_date']  : null,
            'expiry_date'       => !empty($post['expiry_date']) ? $post['expiry_date'] : null,
            'filename'          => $filename,
            'original_name'     => isset($_FILES['document_file']['name'])
                ? $_FILES['document_file']['name'] : '',
            'file_type'         => pathinfo($filename, PATHINFO_EXTENSION),
            'uploaded_by_type'  => 'client',
            'date_created'      => date('Y-m-d H:i:s'),
        ];

        $this->db->insert(db_prefix() . 'kyc_documents', $docData);
        $id = $this->db->insert_id();

        if ($id) {
            kyc_log_action($clientId, 'client_document_upload',
                'Client uploaded: ' . ($docData['document_name'] ?: $filename));
        }

        echo json_encode([
            'success' => (bool) $id,
            'message' => $id ? _l('kyc_document_uploaded') : _l('kyc_error'),
        ]);
        die();
    }

    public function download_document($id)
    {
        if (!is_client_logged_in()) {
            redirect(site_url('authentication/login'));
        }

        $doc = $this->kycmodel->get_document((int) $id);
        if (!$doc || empty($doc->filename)) {
            show_404();
        }

        $clientId = $this->_get_client_id();
        if ((int) $doc->client_id !== $clientId) {
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
}