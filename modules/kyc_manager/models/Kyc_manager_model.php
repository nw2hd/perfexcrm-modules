<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Kyc_manager_model extends CI_Model
{
    protected $profiles;
    protected $documents;
    protected $docTypes;
    protected $serviceMap;
    protected $auditLog;

    public function __construct()
    {
        parent::__construct();
        $pfx = db_prefix();
        $this->profiles   = $pfx . 'kyc_profiles';
        $this->documents  = $pfx . 'kyc_documents';
        $this->docTypes   = $pfx . 'kyc_document_types';
        $this->serviceMap = $pfx . 'kyc_service_map';
        $this->auditLog   = $pfx . 'kyc_audit_log';
    }

    // ─────────────────────────────────────────
    // PROFILES
    // ─────────────────────────────────────────

    public function get_all_profiles($filters = [])
    {
        $this->db
            ->select('p.*, c.company AS client_company')
            ->from($this->profiles . ' p')
            ->join(db_prefix() . 'clients c', 'c.userid = p.client_id', 'left');

        if (!empty($filters['status'])) {
            $this->db->where('p.status', $filters['status']);
        }
        if (!empty($filters['risk_level'])) {
            $this->db->where('p.risk_level', $filters['risk_level']);
        }
        if (!empty($filters['country'])) {
            $this->db->like('p.country', $filters['country']);
        }
        if (!empty($filters['search'])) {
            $kw = $filters['search'];
            $this->db->group_start()
                ->like('p.legal_name', $kw)
                ->or_like('c.company', $kw)
                ->or_like('p.registration_number', $kw)
                ->or_like('p.tax_id', $kw)
                ->group_end();
        }
        if (!empty($filters['kyc_officer'])) {
            $this->db->where('p.kyc_officer', (int) $filters['kyc_officer']);
        }
        if (!empty($filters['expiring_days'])) {
            $days = (int) $filters['expiring_days'];
            $this->db->where('p.expiry_date <=', date('Y-m-d', strtotime("+{$days} days")));
            $this->db->where('p.expiry_date >=', date('Y-m-d'));
        }

        return $this->db->order_by('p.date_created', 'DESC')->get()->result();
    }

    public function get_profile($clientId)
    {
        return $this->db
            ->select('p.*, c.company AS client_company')
            ->from($this->profiles . ' p')
            ->join(db_prefix() . 'clients c', 'c.userid = p.client_id', 'left')
            ->where('p.client_id', (int) $clientId)
            ->get()
            ->row();
    }

    public function get_profile_by_id($id)
    {
        return $this->db
            ->select('p.*, c.company AS client_company')
            ->from($this->profiles . ' p')
            ->join(db_prefix() . 'clients c', 'c.userid = p.client_id', 'left')
            ->where('p.id', (int) $id)
            ->get()
            ->row();
    }

    public function save_profile($clientId, $data)
    {
        $existing = $this->get_profile($clientId);
        $data['date_updated'] = date('Y-m-d H:i:s');

        if ($existing) {
            // Track status change
            if (isset($data['status']) && $data['status'] !== $existing->status) {
                kyc_log_action($clientId, 'status_change',
                    'KYC status changed', $existing->status, $data['status']);
            }
            $this->db->where('client_id', (int) $clientId)
                ->update($this->profiles, $data);
            return $existing->id;
        } else {
            $data['client_id']    = (int) $clientId;
            $data['date_created'] = date('Y-m-d H:i:s');
            if (empty($data['status'])) {
                $data['status'] = 'in_progress';
            }
            $this->db->insert($this->profiles, $data);
            $id = $this->db->insert_id();
            kyc_log_action($clientId, 'profile_created', 'KYC profile created');
            return $id;
        }
    }

    public function update_status($clientId, $status, $reason = '')
    {
        $existing = $this->get_profile($clientId);
        $oldStatus = $existing ? $existing->status : '';

        $updateData = [
            'status'       => $status,
            'date_updated' => date('Y-m-d H:i:s'),
        ];

        if ($status === 'verified') {
            $updateData['approval_date'] = date('Y-m-d');
            $months = get_option('kyc_validity_months') ?: 12;
            $updateData['expiry_date'] = date('Y-m-d', strtotime("+{$months} months"));
        }

        if ($status === 'rejected') {
            $updateData['rejection_reason'] = $reason;
        }

        $this->db->where('client_id', (int) $clientId)
            ->update($this->profiles, $updateData);

        kyc_log_action($clientId, 'status_change',
            'KYC status changed to ' . $status,
            $oldStatus, $status);

        return true;
    }

    public function delete_profile($clientId)
    {
        // Delete documents files
        $docs = $this->get_documents($clientId);
        foreach ($docs as $doc) {
            if (!empty($doc->filename)) {
                $path = FCPATH . 'uploads/kyc_manager/' . $doc->filename;
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
        }

        // Delete all related data
        $this->db->where('client_id', (int) $clientId)->delete($this->documents);
        $this->db->where('client_id', (int) $clientId)->delete($this->serviceMap);
        $this->db->where('client_id', (int) $clientId)->delete($this->auditLog);
        $this->db->where('client_id', (int) $clientId)->delete($this->profiles);

        return true;
    }

    // Dashboard stats
    public function count_by_status()
    {
        $rows = $this->db->select('status, COUNT(*) AS cnt')
            ->from($this->profiles)->group_by('status')->get()->result();
        $result = [];
        foreach ($rows as $r) {
            $result[$r->status] = (int) $r->cnt;
        }
        return $result;
    }

    public function count_by_risk()
    {
        $rows = $this->db->select('risk_level, COUNT(*) AS cnt')
            ->from($this->profiles)->group_by('risk_level')->get()->result();
        $result = [];
        foreach ($rows as $r) {
            $result[$r->risk_level] = (int) $r->cnt;
        }
        return $result;
    }

    public function get_expiring_profiles($days = 30)
    {
        return $this->db
            ->select('p.*, c.company AS client_company')
            ->from($this->profiles . ' p')
            ->join(db_prefix() . 'clients c', 'c.userid = p.client_id', 'left')
            ->where('p.expiry_date <=', date('Y-m-d', strtotime("+{$days} days")))
            ->where('p.expiry_date >=', date('Y-m-d'))
            ->where('p.status', 'verified')
            ->order_by('p.expiry_date', 'ASC')
            ->get()
            ->result();
    }

    public function get_clients_without_kyc()
    {
        return $this->db
            ->select('c.userid, c.company')
            ->from(db_prefix() . 'clients c')
            ->where("c.userid NOT IN (SELECT client_id FROM {$this->profiles})", null, false)
            ->order_by('c.company', 'ASC')
            ->get()
            ->result();
    }

    public function get_recently_verified($limit = 10)
    {
        return $this->db
            ->select('p.*, c.company AS client_company')
            ->from($this->profiles . ' p')
            ->join(db_prefix() . 'clients c', 'c.userid = p.client_id', 'left')
            ->where('p.status', 'verified')
            ->order_by('p.approval_date', 'DESC')
            ->limit($limit)
            ->get()
            ->result();
    }

    // ─────────────────────────────────────────
    // DOCUMENTS
    // ─────────────────────────────────────────

    public function get_document_types($active_only = false)
    {
        if ($active_only) {
            $this->db->where('active', 1);
        }
        return $this->db->order_by('sort_order', 'ASC')
            ->get($this->docTypes)->result();
    }

    public function get_document_type($id)
    {
        return $this->db->where('id', (int) $id)->get($this->docTypes)->row();
    }

    public function save_document_type($id, $data)
    {
        if ($id) {
            $this->db->where('id', (int) $id)->update($this->docTypes, $data);
        } else {
            $this->db->insert($this->docTypes, $data);
            return $this->db->insert_id();
        }
        return $id;
    }

    public function delete_document_type($id)
    {
        $this->db->where('id', (int) $id)->delete($this->docTypes);
    }

    public function get_documents($clientId, $status = '')
    {
        $this->db
            ->select('d.*, dt.name AS type_name, dt.required AS type_required')
            ->from($this->documents . ' d')
            ->join($this->docTypes . ' dt', 'dt.id = d.document_type_id', 'left')
            ->where('d.client_id', (int) $clientId);

        if (!empty($status)) {
            $this->db->where('d.status', $status);
        }

        return $this->db->order_by('dt.sort_order', 'ASC')->get()->result();
    }

    public function get_document($id)
    {
        return $this->db
            ->select('d.*, dt.name AS type_name')
            ->from($this->documents . ' d')
            ->join($this->docTypes . ' dt', 'dt.id = d.document_type_id', 'left')
            ->where('d.id', (int) $id)
            ->get()
            ->row();
    }

    public function save_document($data)
    {
        $data['date_created'] = date('Y-m-d H:i:s');
        if (function_exists('get_staff_user_id')) {
            $data['uploaded_by'] = get_staff_user_id();
        }
        $data['uploaded_by_type'] = 'staff';

        $this->db->insert($this->documents, $data);
        $id = $this->db->insert_id();

        if ($id) {
            kyc_log_action($data['client_id'], 'document_uploaded',
                'Document uploaded: ' . ($data['document_name'] ?? ''));
        }

        return $id;
    }

    public function update_document($id, $data)
    {
        $this->db->where('id', (int) $id)->update($this->documents, $data);
        return $this->db->affected_rows() > 0;
    }

    public function review_document($id, $status, $notes = '')
    {
        $doc = $this->get_document($id);
        if (!$doc) {
            return false;
        }

        $this->db->where('id', (int) $id)->update($this->documents, [
            'status'        => $status,
            'staff_notes'   => $notes,
            'reviewed_by'   => function_exists('get_staff_user_id') ? get_staff_user_id() : null,
            'reviewed_date' => date('Y-m-d H:i:s'),
        ]);

        kyc_log_action($doc->client_id, 'document_' . $status,
            'Document ' . $status . ': ' . $doc->document_name,
            $doc->status, $status);

        return true;
    }

    public function delete_document($id)
    {
        $doc = $this->get_document($id);
        if (!$doc) {
            return false;
        }

        if (!empty($doc->filename)) {
            $path = FCPATH . 'uploads/kyc_manager/' . $doc->filename;
            if (file_exists($path)) {
                @unlink($path);
            }
        }

        kyc_log_action($doc->client_id, 'document_deleted',
            'Document deleted: ' . $doc->document_name);

        $this->db->where('id', (int) $id)->delete($this->documents);
        return true;
    }

    public function get_expiring_documents($days = 30)
    {
        return $this->db
            ->select('d.*, dt.name AS type_name, c.company AS client_company')
            ->from($this->documents . ' d')
            ->join($this->docTypes . ' dt', 'dt.id = d.document_type_id', 'left')
            ->join(db_prefix() . 'clients c', 'c.userid = d.client_id', 'left')
            ->where('d.expiry_date <=', date('Y-m-d', strtotime("+{$days} days")))
            ->where('d.expiry_date >=', date('Y-m-d'))
            ->where('d.status', 'approved')
            ->order_by('d.expiry_date', 'ASC')
            ->get()
            ->result();
    }

    public function count_documents_by_status($clientId = null)
    {
        if ($clientId) {
            $this->db->where('client_id', (int) $clientId);
        }
        $rows = $this->db->select('status, COUNT(*) AS cnt')
            ->from($this->documents)
            ->group_by('status')
            ->get()->result();
        $result = [];
        foreach ($rows as $r) {
            $result[$r->status] = (int) $r->cnt;
        }
        return $result;
    }

    // ─────────────────────────────────────────
    // SERVICES
    // ─────────────────────────────────────────

    public function get_services($clientId)
    {
        return $this->db
            ->select('sm.*, i.description AS item_name, i.long_description AS item_description,
                      i.rate AS item_rate')
            ->from($this->serviceMap . ' sm')
            ->join(db_prefix() . 'items i', 'i.id = sm.item_id', 'left')
            ->where('sm.client_id', (int) $clientId)
            ->order_by('sm.date_added', 'ASC')
            ->get()
            ->result();
    }

    public function get_clients_for_service($itemId)
    {
        return $this->db
            ->select('sm.*, c.company AS client_company')
            ->from($this->serviceMap . ' sm')
            ->join(db_prefix() . 'clients c', 'c.userid = sm.client_id', 'left')
            ->where('sm.item_id', (int) $itemId)
            ->order_by('c.company', 'ASC')
            ->get()
            ->result();
    }

    public function add_service($clientId, $itemId, $notes = '')
    {
        // Check duplicate
        $exists = $this->db
            ->where('client_id', (int) $clientId)
            ->where('item_id', (int) $itemId)
            ->count_all_results($this->serviceMap);
        if ($exists > 0) {
            return false;
        }

        $this->db->insert($this->serviceMap, [
            'client_id'  => (int) $clientId,
            'item_id'    => (int) $itemId,
            'notes'      => $notes,
            'date_added' => date('Y-m-d H:i:s'),
            'added_by'   => function_exists('get_staff_user_id') ? get_staff_user_id() : null,
        ]);

        kyc_log_action($clientId, 'service_added', 'Service mapped: Item #' . $itemId);

        return $this->db->insert_id();
    }

    public function remove_service($id)
    {
        $row = $this->db->where('id', (int) $id)->get($this->serviceMap)->row();
        if ($row) {
            kyc_log_action($row->client_id, 'service_removed', 'Service removed: Item #' . $row->item_id);
            $this->db->where('id', (int) $id)->delete($this->serviceMap);
        }
        return true;
    }

    // ─────────────────────────────────────────
    // AUDIT LOG
    // ─────────────────────────────────────────

    public function get_audit_log($clientId, $limit = 50)
    {
        $rows = $this->db
            ->where('client_id', (int) $clientId)
            ->order_by('date', 'DESC')
            ->limit($limit)
            ->get($this->auditLog)
            ->result();

        // Add staff name
        foreach ($rows as &$row) {
            $row->staff_name = '';
            if (!empty($row->staff_id)) {
                $staff = $this->db
                    ->where('staffid', (int) $row->staff_id)
                    ->get(db_prefix() . 'staff')
                    ->row();
                if ($staff) {
                    if (isset($staff->firstname)) {
                        $row->staff_name = trim($staff->firstname . ' ' . ($staff->lastname ?? ''));
                    } elseif (isset($staff->name)) {
                        $row->staff_name = $staff->name;
                    } else {
                        $row->staff_name = 'Staff #' . $row->staff_id;
                    }
                }
            }
        }

        return $rows;
    }

    // ─────────────────────────────────────────
    // REPORTS
    // ─────────────────────────────────────────

    public function get_kyc_report($filters = [])
    {
        return $this->get_all_profiles($filters);
    }

    public function get_expiry_report($days = 90, $type = 'all')
    {
        $results = [];

        if ($type === 'all' || $type === 'profiles') {
            $profiles = $this->get_expiring_profiles($days);
            foreach ($profiles as $p) {
                $results[] = [
                    'type'         => 'KYC Profile',
                    'client'       => $p->client_company,
                    'client_id'    => $p->client_id,
                    'detail'       => 'KYC expires ' . date('d M Y', strtotime($p->expiry_date)),
                    'expiry_date'  => $p->expiry_date,
                    'days_left'    => (int) ((strtotime($p->expiry_date) - time()) / 86400),
                ];
            }
        }

        if ($type === 'all' || $type === 'documents') {
            $docs = $this->get_expiring_documents($days);
            foreach ($docs as $d) {
                $results[] = [
                    'type'         => $d->type_name,
                    'client'       => $d->client_company,
                    'client_id'    => $d->client_id,
                    'detail'       => $d->document_name . ' expires ' . date('d M Y', strtotime($d->expiry_date)),
                    'expiry_date'  => $d->expiry_date,
                    'days_left'    => (int) ((strtotime($d->expiry_date) - time()) / 86400),
                ];
            }
        }

        usort($results, function($a, $b) {
            return strtotime($a['expiry_date']) - strtotime($b['expiry_date']);
        });

        return $results;
    }

    public function get_service_report()
    {
        return $this->db
            ->select('i.id AS item_id, i.description AS item_name,
                COUNT(sm.id) AS client_count')
            ->from(db_prefix() . 'items i')
            ->join($this->serviceMap . ' sm', 'sm.item_id = i.id', 'left')
            ->group_by('i.id')
            ->order_by('client_count', 'DESC')
            ->get()
            ->result();
    }
}