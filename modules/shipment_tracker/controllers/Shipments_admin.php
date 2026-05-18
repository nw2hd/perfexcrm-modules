<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Shipments_admin extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('shipment_tracker/shipment_tracker_model', 'stmodel');
        $this->load->library('form_validation');
    }

    public function index()
    {
        if (!has_permission('shipment_tracker', '', 'view')) {
            access_denied('shipment_tracker');
        }

        $filters = [
            'status'    => $this->input->get('status'),
            'client_id' => $this->input->get('client_id'),
            'search'    => $this->input->get('search'),
        ];

        $data['title']         = _l('shipment_tracker_menu_title');
        $data['shipments']     = $this->stmodel->get_all($filters);
        $data['statuses']      = shipment_tracker_statuses();
        $data['status_counts'] = $this->stmodel->count_by_status();
        $data['filters']       = $filters;

        $this->load->view('shipment_tracker/admin/index', $data);
    }

    public function view($id = 0)
    {
        if (!has_permission('shipment_tracker', '', 'view')) {
            access_denied('shipment_tracker');
        }

        $shipment = $this->stmodel->get((int) $id);
        if (!$shipment) {
            show_404();
        }

        $data['title']          = _l('shipment_tracker_view') . ' #' . $id;
        $data['shipment']       = $shipment;
        $data['statuses']       = shipment_tracker_statuses();
        $data['status_history'] = $this->stmodel->get_history($id);

        $this->load->view('shipment_tracker/admin/view', $data);
    }

    public function form($id = 0)
    {
        if ($id && !has_permission('shipment_tracker', '', 'edit')) {
            access_denied('shipment_tracker');
        }
        if (!$id && !has_permission('shipment_tracker', '', 'create')) {
            access_denied('shipment_tracker');
        }

        if ($this->input->post()) {
            $this->_process_form((int) $id);
            return;
        }

        $shipment = $id ? $this->stmodel->get($id) : null;

        // Load clients — only columns that exist in tblclients
        $clients = $this->db
            ->select('userid, company')
            ->order_by('company', 'ASC')
            ->get(db_prefix() . 'clients')
            ->result();

        $data['title']    = $id
            ? _l('shipment_tracker_edit')
            : _l('shipment_tracker_create');
        $data['shipment'] = $shipment;
        $data['clients']  = $clients;
        $data['statuses'] = shipment_tracker_statuses();
        $data['projects'] = [];
        $data['invoices'] = [];

        if ($shipment) {
            $data['projects'] = $this->db
                ->where('clientid', $shipment->client_id)
                ->get(db_prefix() . 'projects')
                ->result();
            $data['invoices'] = $this->db
                ->where('clientid', $shipment->client_id)
                ->get(db_prefix() . 'invoices')
                ->result();
        }

        $this->load->view('shipment_tracker/admin/form', $data);
    }

    private function _process_form($id = 0)
    {
        $this->form_validation->set_rules(
            'client_id', 'Client', 'required|numeric'
        );
        $this->form_validation->set_rules(
            'tracking_number', 'Tracking Number', 'required|trim|max_length[100]'
        );

        if ($this->form_validation->run() === false) {
            set_alert('danger', validation_errors('<p>', '</p>'));
            redirect($id
                ? admin_url('shipment_tracker/shipments_admin/form/' . $id)
                : admin_url('shipment_tracker/shipments_admin/form'));
            return;
        }

        $post = $this->input->post(null, true);

        $data = [
            'client_id'       => (int) $post['client_id'],
            'project_id'      => !empty($post['project_id'])
                ? (int) $post['project_id'] : null,
            'invoice_id'      => !empty($post['invoice_id'])
                ? (int) $post['invoice_id'] : null,
            'tracking_number' => trim($post['tracking_number']),
            'shipper'         => trim($post['shipper']      ?? ''),
            'consignee'       => trim($post['consignee']    ?? ''),
            'origin'          => trim($post['origin']       ?? ''),
            'destination'     => trim($post['destination']  ?? ''),
            'description'     => trim($post['description']  ?? ''),
            'weight'          => !empty($post['weight'])
                ? (float) $post['weight'] : null,
            'pieces'          => !empty($post['pieces'])
                ? (int) $post['pieces'] : null,
            'details'         => trim($post['details']      ?? ''),
            'admin_note'      => trim($post['admin_note']   ?? ''),
        ];

        $upload = $this->_upload_cipl($id);
        if ($upload === false) {
            set_alert('danger', _l('shipment_tracker_upload_error'));
            redirect($id
                ? admin_url('shipment_tracker/shipments_admin/form/' . $id)
                : admin_url('shipment_tracker/shipments_admin/form'));
            return;
        }
        if (!empty($upload)) {
            $data['cipl_filename'] = $upload;
        }

        if ($id) {
            $newStatus = trim($post['status'] ?? '');
            if ($newStatus) {
                $existing = $this->stmodel->get($id);
                if ($existing && $existing->status !== $newStatus) {
                    $this->stmodel->change_status(
                        $id,
                        $newStatus,
                        trim($post['status_note'] ?? ''),
                        !empty($post['send_notification'])
                    );
                }
            }
            $ok = $this->stmodel->update($id, $data);
            set_alert(
                $ok ? 'success' : 'danger',
                $ok ? _l('shipment_tracker_updated') : _l('shipment_tracker_error')
            );
            redirect(admin_url('shipment_tracker/shipments_admin/view/' . $id));
        } else {
            $newId = $this->stmodel->create($data);
            if ($newId) {
                set_alert('success', _l('shipment_tracker_created'));
                redirect(admin_url(
                    'shipment_tracker/shipments_admin/view/' . $newId
                ));
            } else {
                set_alert('danger', _l('shipment_tracker_error'));
                redirect(admin_url('shipment_tracker/shipments_admin/form'));
            }
        }
    }

    public function delete($id = 0)
    {
        if (!has_permission('shipment_tracker', '', 'delete')) {
            access_denied('shipment_tracker');
        }
        $ok = $this->stmodel->delete((int) $id);
        set_alert(
            $ok ? 'success' : 'danger',
            $ok ? _l('shipment_tracker_deleted') : _l('shipment_tracker_error')
        );
        redirect(admin_url('shipment_tracker/shipments_admin'));
    }

    public function ajax_status()
    {
        if (!has_permission('shipment_tracker', '', 'edit')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            die();
        }

        $id     = (int) $this->input->post('id');
        $status = $this->input->post('status', true);
        $note   = $this->input->post('note',   true);
        $notify = (bool) $this->input->post('notify');

        if (!$id || !$status) {
            echo json_encode(['success' => false, 'message' => 'Invalid input']);
            die();
        }

        $this->stmodel->change_status($id, $status, $note, $notify);

        echo json_encode([
            'success' => true,
            'badge'   => shipment_tracker_badge($status),
            'label'   => shipment_tracker_status_label($status),
            'message' => _l('shipment_tracker_status_updated'),
        ]);
        die();
    }

    public function ajax_client_data()
    {
        if (!has_permission('shipment_tracker', '', 'view')) {
            echo json_encode(['success' => false]);
            die();
        }

        $clientId = (int) $this->input->post('client_id');
        if (!$clientId) {
            echo json_encode([
                'success'  => true,
                'projects' => [],
                'invoices' => [],
            ]);
            die();
        }

        $projects = $this->db
            ->select('id, name')
            ->where('clientid', $clientId)
            ->get(db_prefix() . 'projects')
            ->result();

        $invoices = $this->db
            ->select('id, number')
            ->where('clientid', $clientId)
            ->get(db_prefix() . 'invoices')
            ->result();

        echo json_encode([
            'success'  => true,
            'projects' => $projects,
            'invoices' => $invoices,
        ]);
        die();
    }

    public function cipl($id = 0)
    {
        if (!has_permission('shipment_tracker', '', 'view')) {
            access_denied('shipment_tracker');
        }

        $s = $this->stmodel->get((int) $id);
        if (!$s || empty($s->cipl_filename)) {
            show_404();
        }

        $path = FCPATH . 'uploads/shipment_tracker/' . $s->cipl_filename;
        if (!file_exists($path)) {
            show_404();
        }

        $this->load->helper('download');
        force_download($s->cipl_filename, file_get_contents($path));
    }

    private function _upload_cipl($shipmentId = 0)
    {
        if (empty($_FILES['cipl_file']['name'])) {
            return null;
        }

        $dir = FCPATH . 'uploads/shipment_tracker/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $config = [
            'upload_path'   => $dir,
            'allowed_types' => 'pdf|doc|docx|xls|xlsx|jpg|jpeg|png',
            'max_size'      => 10240,
            'encrypt_name'  => true,
        ];

        $this->load->library('upload', $config);
        $this->upload->initialize($config);

        if (!$this->upload->do_upload('cipl_file')) {
            log_message('error',
                '[ShipmentTracker] Upload: ' . $this->upload->display_errors()
            );
            return false;
        }

        $file = $this->upload->data('file_name');

        if ($shipmentId) {
            $existing = $this->stmodel->get($shipmentId);
            if ($existing && !empty($existing->cipl_filename)) {
                $old = $dir . $existing->cipl_filename;
                if (file_exists($old)) {
                    @unlink($old);
                }
            }
        }

        return $file;
    }
}