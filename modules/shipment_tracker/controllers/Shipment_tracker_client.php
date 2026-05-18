<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Shipment_tracker_client extends ClientsController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('shipment_tracker/shipment_tracker_model', 'stmodel');
    }

    private function _get_client_id()
    {
        if (function_exists('get_contact_user_id')) {
            $contactId = get_contact_user_id();
            if ($contactId) {
                $contact = $this->db
                    ->where('id', $contactId)
                    ->get(db_prefix() . 'contacts')
                    ->row();
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

        $data['title']     = _l('shipment_tracker_menu_title');
        $data['shipments'] = $this->stmodel->get_by_client($this->_get_client_id());
        $data['statuses']  = shipment_tracker_statuses();

        $this->data($data);
        $this->view('shipment_tracker/client/index');
        $this->layout();
    }

    public function track($id = 0)
    {
        if (!is_client_logged_in()) {
            redirect(site_url('authentication/login'));
        }

        $shipment = $this->stmodel->get((int) $id);
        if (!$shipment) {
            show_404();
        }

        if ((int) $shipment->client_id !== $this->_get_client_id()) {
            show_404();
        }

        $data['title']          = _l('shipment_tracker_track')
            . ' ' . htmlspecialchars($shipment->tracking_number);
        $data['shipment']       = $shipment;
        $data['statuses']       = shipment_tracker_statuses();
        $data['status_history'] = $this->stmodel->get_history($id);

        $this->data($data);
        $this->view('shipment_tracker/client/track');
        $this->layout();
    }

    public function cipl($id = 0)
    {
        if (!is_client_logged_in()) {
            redirect(site_url('authentication/login'));
        }

        $s = $this->stmodel->get((int) $id);
        if (!$s || empty($s->cipl_filename)) {
            show_404();
        }

        if ((int) $s->client_id !== $this->_get_client_id()) {
            show_404();
        }

        $path = FCPATH . 'uploads/shipment_tracker/' . $s->cipl_filename;
        if (!file_exists($path)) {
            show_404();
        }

        $this->load->helper('download');
        force_download($s->cipl_filename, file_get_contents($path));
    }
}