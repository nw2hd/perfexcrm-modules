<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Shipment_tracker_model extends CI_Model
{
    protected $table   = '';
    protected $history = '';

    public function __construct()
    {
        parent::__construct();
        $this->table   = db_prefix() . 'shipments';
        $this->history = db_prefix() . 'shipment_status_history';
    }

    // ─────────────────────────────────────────
    // READ
    // ─────────────────────────────────────────

    public function get_all($filters = [])
    {
        $this->db
            ->select('s.*,
                c.company  AS client_company,
                c.userid   AS client_userid,
                p.name     AS project_name,
                inv.number AS invoice_number')
            ->from($this->table . ' s')
            ->join(db_prefix() . 'clients c',
                'c.userid = s.client_id', 'left')
            ->join(db_prefix() . 'projects p',
                'p.id = s.project_id', 'left')
            ->join(db_prefix() . 'invoices inv',
                'inv.id = s.invoice_id', 'left');

        if (!empty($filters['status'])) {
            $this->db->where('s.status', $filters['status']);
        }
        if (!empty($filters['client_id'])) {
            $this->db->where('s.client_id', (int) $filters['client_id']);
        }
        if (!empty($filters['search'])) {
            $kw = $filters['search'];
            $this->db->group_start()
                ->like('s.tracking_number', $kw)
                ->or_like('c.company', $kw)
                ->or_like('s.origin', $kw)
                ->or_like('s.destination', $kw)
                ->group_end();
        }

        return $this->db
            ->order_by('s.date_created', 'DESC')
            ->get()
            ->result();
    }

    public function get($id)
    {
        $row = $this->db
            ->select('s.*,
                c.company  AS client_company,
                c.userid   AS client_userid,
                p.name     AS project_name,
                inv.number AS invoice_number')
            ->from($this->table . ' s')
            ->join(db_prefix() . 'clients c',
                'c.userid = s.client_id', 'left')
            ->join(db_prefix() . 'projects p',
                'p.id = s.project_id', 'left')
            ->join(db_prefix() . 'invoices inv',
                'inv.id = s.invoice_id', 'left')
            ->where('s.id', (int) $id)
            ->get()
            ->row();

        if ($row) {
            // Get email from contacts table
            $row->client_email = shipment_tracker_get_client_email($row->client_id);
        }

        return $row;
    }

    public function get_by_client($clientId)
    {
        return $this->db
            ->select('s.*,
                inv.number AS invoice_number,
                p.name     AS project_name')
            ->from($this->table . ' s')
            ->join(db_prefix() . 'invoices inv',
                'inv.id = s.invoice_id', 'left')
            ->join(db_prefix() . 'projects p',
                'p.id = s.project_id', 'left')
            ->where('s.client_id', (int) $clientId)
            ->order_by('s.date_created', 'DESC')
            ->get()
            ->result();
    }

    public function get_history($shipmentId)
    {
        $rows = $this->db
            ->where('shipment_id', (int) $shipmentId)
            ->order_by('date', 'ASC')
            ->get($this->history)
            ->result();

        foreach ($rows as &$row) {
            $row->staff_name = '';
            if (!empty($row->changed_by)) {
                $staff = $this->db
                    ->where('staffid', (int) $row->changed_by)
                    ->get(db_prefix() . 'staff')
                    ->row();
                if ($staff) {
                    if (isset($staff->firstname)) {
                        $row->staff_name = trim(
                            $staff->firstname . ' ' . ($staff->lastname ?? '')
                        );
                    } elseif (isset($staff->name)) {
                        $row->staff_name = $staff->name;
                    } else {
                        $row->staff_name = 'Staff #' . $row->changed_by;
                    }
                }
            }
        }

        return $rows;
    }

    public function count_by_status()
    {
        $rows = $this->db
            ->select('status, COUNT(*) AS cnt')
            ->from($this->table)
            ->group_by('status')
            ->get()
            ->result();

        $result = [];
        foreach ($rows as $r) {
            $result[$r->status] = (int) $r->cnt;
        }
        return $result;
    }

    // ─────────────────────────────────────────
    // WRITE
    // ─────────────────────────────────────────

    public function create($data)
    {
        $data['status']       = 'prepared';
        $data['date_created'] = date('Y-m-d H:i:s');
        $data['date_updated'] = date('Y-m-d H:i:s');

        if (empty($data['project_id'])) {
            $data['project_id'] = null;
        }
        if (empty($data['invoice_id'])) {
            $data['invoice_id'] = null;
        }

        $this->db->insert($this->table, $data);
        $id = $this->db->insert_id();

        if ($id) {
            $this->log_history($id, 'prepared', 'Shipment created');
        }

        return $id;
    }

    public function update($id, $data)
    {
        $data['date_updated'] = date('Y-m-d H:i:s');

        if (empty($data['project_id'])) {
            $data['project_id'] = null;
        }
        if (empty($data['invoice_id'])) {
            $data['invoice_id'] = null;
        }

        $this->db->where('id', (int) $id)->update($this->table, $data);
        return $this->db->affected_rows() > 0;
    }

    public function change_status($id, $status, $note = '', $notify = true)
    {
        $this->db->where('id', (int) $id)->update($this->table, [
            'status'       => $status,
            'date_updated' => date('Y-m-d H:i:s'),
        ]);

        $this->log_history($id, $status, $note);

        if ($notify) {
            $shipment = $this->get($id);
            if ($shipment) {
                shipment_tracker_notify($shipment, $note);
            }
        }

        return true;
    }

    public function delete($id)
    {
        $s = $this->get($id);
        if ($s && !empty($s->cipl_filename)) {
            $f = FCPATH . 'uploads/shipment_tracker/' . $s->cipl_filename;
            if (file_exists($f)) {
                @unlink($f);
            }
        }
        $this->db->where('shipment_id', (int) $id)->delete($this->history);
        $this->db->where('id', (int) $id)->delete($this->table);
        return $this->db->affected_rows() > 0;
    }

    public function log_history($shipmentId, $status, $note = '')
    {
        $staffId = function_exists('get_staff_user_id')
            ? get_staff_user_id()
            : null;

        $this->db->insert($this->history, [
            'shipment_id' => (int) $shipmentId,
            'status'      => $status,
            'note'        => $note,
            'changed_by'  => $staffId,
            'date'        => date('Y-m-d H:i:s'),
        ]);
    }
}