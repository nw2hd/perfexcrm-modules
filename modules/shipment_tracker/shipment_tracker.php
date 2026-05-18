<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Shipment Tracker
Description: Shipment tracking module for Perfex CRM with client portal support.
Version: 3.2.1
Requires at least: 2.3.*
*/

register_activation_hook('shipment_tracker', 'shipment_tracker_activation');

function shipment_tracker_activation()
{
    $CI  = &get_instance();
    $pfx = db_prefix();

    if (!$CI->db->table_exists($pfx . 'shipments')) {
        $CI->db->query("
            CREATE TABLE `{$pfx}shipments` (
                `id`              int(11)       NOT NULL AUTO_INCREMENT,
                `client_id`       int(11)       NOT NULL,
                `project_id`      int(11)       DEFAULT NULL,
                `invoice_id`      int(11)       DEFAULT NULL,
                `tracking_number` varchar(100)  NOT NULL,
                `shipper`         varchar(255)  DEFAULT NULL,
                `consignee`       varchar(255)  DEFAULT NULL,
                `origin`          varchar(255)  DEFAULT NULL,
                `destination`     varchar(255)  DEFAULT NULL,
                `description`     text,
                `weight`          decimal(10,2) DEFAULT NULL,
                `pieces`          int(11)       DEFAULT NULL,
                `details`         text,
                `admin_note`      text,
                `status`          varchar(50)   NOT NULL,
                `cipl_filename`   varchar(255)  DEFAULT NULL,
                `date_created`    datetime      NOT NULL,
                `date_updated`    datetime      DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
    }

    if (!$CI->db->table_exists($pfx . 'shipment_status_history')) {
        $CI->db->query("
            CREATE TABLE `{$pfx}shipment_status_history` (
                `id`          int(11)     NOT NULL AUTO_INCREMENT,
                `shipment_id` int(11)     NOT NULL,
                `status`      varchar(50) NOT NULL,
                `note`        text,
                `changed_by`  int(11)     DEFAULT NULL,
                `date`        datetime    NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
    }

    $t    = $pfx . 'shipments';
    $cols = [
        'shipper'      => "ALTER TABLE `{$t}` ADD COLUMN `shipper` varchar(255) DEFAULT NULL",
        'consignee'    => "ALTER TABLE `{$t}` ADD COLUMN `consignee` varchar(255) DEFAULT NULL",
        'origin'       => "ALTER TABLE `{$t}` ADD COLUMN `origin` varchar(255) DEFAULT NULL",
        'destination'  => "ALTER TABLE `{$t}` ADD COLUMN `destination` varchar(255) DEFAULT NULL",
        'description'  => "ALTER TABLE `{$t}` ADD COLUMN `description` text",
        'weight'       => "ALTER TABLE `{$t}` ADD COLUMN `weight` decimal(10,2) DEFAULT NULL",
        'pieces'       => "ALTER TABLE `{$t}` ADD COLUMN `pieces` int(11) DEFAULT NULL",
        'admin_note'   => "ALTER TABLE `{$t}` ADD COLUMN `admin_note` text",
        'date_updated' => "ALTER TABLE `{$t}` ADD COLUMN `date_updated` datetime DEFAULT NULL",
    ];
    foreach ($cols as $col => $sql) {
        if (!$CI->db->field_exists($col, $t)) {
            $CI->db->query($sql);
        }
    }

    return true;
}

// ── PERMISSIONS ───────────────────────────────────────────
hooks()->add_filter('staff_permissions', 'shipment_tracker_permissions');

function shipment_tracker_permissions($permissions)
{
    $permissions['shipment_tracker'] = [
        'name'         => _l('shipment_tracker_menu_title'),
        'capabilities' => [
            'view'   => _l('shipment_permission_view'),
            'create' => _l('shipment_permission_create'),
            'edit'   => _l('shipment_permission_edit'),
            'delete' => _l('shipment_permission_delete'),
        ],
    ];

    return $permissions;
}

// ── ADMIN HOOKS ───────────────────────────────────────────
hooks()->add_action('admin_init',                         'shipment_tracker_admin_init');
hooks()->add_action('clients_summary_tab_title_before',   'shipment_tracker_tab_title');
hooks()->add_action('clients_summary_tab_content_before', 'shipment_tracker_tab_content');

// ── CLIENT PORTAL HOOKS (Perfex 3.2.1) ──────────────────
hooks()->add_action('app_client_assets',                  'shipment_tracker_client_assets');
hooks()->add_action('app_customers_head',                 'shipment_tracker_client_head');
hooks()->add_action('customers_navigation_start',         'shipment_tracker_client_nav');
hooks()->add_action('customers_content_container_start',  'shipment_tracker_client_content_start');

function shipment_tracker_admin_init()
{
    $CI = &get_instance();
    shipment_tracker_lang($CI, 'admin');

    if (has_permission('shipment_tracker', '', 'view')) {
        $CI->app_menu->add_sidebar_menu_item('shipment_tracker', [
            'name'     => _l('shipment_tracker_menu_title'),
            'href'     => admin_url('shipment_tracker/shipments_admin'),
            'icon'     => 'fa fa-truck',
            'position' => 10,
        ]);
    }

    if (strpos(uri_string(), 'shipment_tracker') !== false) {
        $CI->app_scripts->add(
            'shipment_tracker_js',
            module_dir_url('shipment_tracker', 'assets/js/shipment_tracker.js'),
            true
        );
        $CI->app_css->add(
            'shipment_tracker_css',
            module_dir_url('shipment_tracker', 'assets/css/shipment_tracker.css')
        );
    }
}

function shipment_tracker_client_assets()
{
    $CI = &get_instance();
    // Force reload language for client side (may differ from admin)
    $GLOBALS['_st_lang_loaded'] = false;
    shipment_tracker_lang($CI, 'client');

    if (strpos(uri_string(), 'shipment_tracker') !== false) {
        $CI->app_css->add(
            'shipment_tracker_css',
            module_dir_url('shipment_tracker', 'assets/css/shipment_tracker.css')
        );
    }
}

function shipment_tracker_client_head()
{
    $CI = &get_instance();
    if (empty($GLOBALS['_st_lang_loaded']) || (isset($GLOBALS['_st_lang_side']) && $GLOBALS['_st_lang_side'] !== 'client')) {
        $GLOBALS['_st_lang_loaded'] = false;
        shipment_tracker_lang($CI, 'client');
    }
}

function shipment_tracker_client_content_start()
{
    $CI = &get_instance();
    if (empty($GLOBALS['_st_lang_loaded']) || (isset($GLOBALS['_st_lang_side']) && $GLOBALS['_st_lang_side'] !== 'client')) {
        $GLOBALS['_st_lang_loaded'] = false;
        shipment_tracker_lang($CI, 'client');
    }
}

// ── LANGUAGE LOADER ───────────────────────────────────────
function shipment_tracker_lang(&$CI, $side = '')
{
    if (!empty($GLOBALS['_st_lang_loaded']) && (empty($side) || (isset($GLOBALS['_st_lang_side']) && $GLOBALS['_st_lang_side'] === $side))) {
        return;
    }

    $lang = 'english';

    if ($side === 'client') {
        // CLIENT SIDE: Get contact's language first
        if (function_exists('is_client_logged_in') && is_client_logged_in() && function_exists('get_contact_user_id')) {
            $contactId = get_contact_user_id();
            if ($contactId) {
                $contactsTable = db_prefix() . 'contacts';
                if ($CI->db->field_exists('language', $contactsTable)) {
                    $contact = $CI->db
                        ->select('language')
                        ->where('id', $contactId)
                        ->get($contactsTable)
                        ->row();
                    if ($contact && !empty($contact->language)) {
                        $lang = $contact->language;
                    }
                }
            }
        }

        // Fallback: system default language
        if ($lang === 'english') {
            $defaultLang = get_option('active_language');
            if (!empty($defaultLang)) {
                $lang = $defaultLang;
            }
        }
    } else {
        // ADMIN SIDE: Use session language
        if (isset($CI->session) && $CI->session->has_userdata('language')) {
            $lang = $CI->session->userdata('language');
        }
    }

    // Verify language file exists for this module
    $langPath = module_dir_path('shipment_tracker', 'language/' . $lang . '/');
    if (!is_dir($langPath)) {
        $lang = 'english';
    }

    // Load language (CI will merge, not overwrite)
    $CI->lang->load('shipment_tracker/shipment_tracker', $lang);
    $GLOBALS['_st_lang_loaded'] = true;
    $GLOBALS['_st_lang_side']   = $side;
}

// ── ADMIN: CLIENT PROFILE TAB ─────────────────────────────
function shipment_tracker_tab_title()
{
    if (!has_permission('shipment_tracker', '', 'view')) {
        return;
    }
    echo '<li role="presentation">
        <a href="#tab_shipments" aria-controls="tab_shipments" role="tab" data-toggle="tab">
            <i class="fa fa-truck"></i>&nbsp;'
        . _l('shipment_tracker_menu_title')
        . '</a>
    </li>';
}

function shipment_tracker_tab_content()
{
    if (!has_permission('shipment_tracker', '', 'view')) {
        return;
    }
    $CI       = &get_instance();
    $clientId = (int) $CI->uri->segment(3);
    if (!$clientId) {
        return;
    }
    $CI->load->model('shipment_tracker/shipment_tracker_model', 'stmodel');
    $shipments = $CI->stmodel->get_by_client($clientId);
    $statuses  = shipment_tracker_statuses();
    include module_dir_path('shipment_tracker', 'views/admin/client_tab.php');
}

// ── CLIENT NAV MENU ──────────────────────────────────────
function shipment_tracker_client_nav()
{
    if (!is_client_logged_in()) {
        return;
    }

    $CI = &get_instance();
    // Force correct language for nav
    if (empty($GLOBALS['_st_lang_loaded']) || (isset($GLOBALS['_st_lang_side']) && $GLOBALS['_st_lang_side'] !== 'client')) {
        $GLOBALS['_st_lang_loaded'] = false;
        shipment_tracker_lang($CI, 'client');
    }

    $url    = site_url('shipment_tracker/shipment_tracker_client');
    $active = (strpos(uri_string(), 'shipment_tracker') !== false);
    $label  = _l('shipment_tracker_menu_title');

    $activeClass = $active ? 'active' : '';

    echo '
    <li class="customers-nav-item-shipment-tracker">
        <a href="' . $url . '"
           class="nav-link customers-nav-item-link ' . $activeClass . '">
            <i class="fa fa-truck fa-fw"></i>
            <span class="customers-nav-item-name">'
            . $label .
        '</span>
        </a>
    </li>';
}

// ── HELPERS ───────────────────────────────────────────────

if (!function_exists('shipment_tracker_statuses')) {
    function shipment_tracker_statuses()
    {
        return [
            'prepared'          => _l('shipment_status_prepared'),
            'ready_to_pickup'   => _l('shipment_status_ready_to_pickup'),
            'picked_up'         => _l('shipment_status_picked_up'),
            'process_clearance' => _l('shipment_status_process_clearance'),
            'in_flight'         => _l('shipment_status_in_flight'),
            'certificate'       => _l('shipment_status_certificate'),
            'closed'            => _l('shipment_status_closed'),
        ];
    }
}

if (!function_exists('shipment_tracker_status_label')) {
    function shipment_tracker_status_label($status)
    {
        $list = shipment_tracker_statuses();
        return isset($list[$status])
            ? $list[$status]
            : ucwords(str_replace('_', ' ', $status));
    }
}

if (!function_exists('shipment_tracker_badge')) {
    function shipment_tracker_badge($status)
    {
        $map = [
            'prepared'          => 'default',
            'ready_to_pickup'   => 'info',
            'picked_up'         => 'primary',
            'process_clearance' => 'warning',
            'in_flight'         => 'warning',
            'certificate'       => 'success',
            'closed'            => 'success',
        ];
        $cls = isset($map[$status]) ? $map[$status] : 'default';
        return '<span class="label label-' . $cls . '">'
            . htmlspecialchars(shipment_tracker_status_label($status))
            . '</span>';
    }
}

if (!function_exists('shipment_tracker_get_client_email')) {
    function shipment_tracker_get_client_email($clientId)
    {
        $CI            = &get_instance();
        $contactsTable = db_prefix() . 'contacts';

        if (!$CI->db->table_exists($contactsTable)) {
            return '';
        }

        $CI->db->where('userid', (int) $clientId);

        if ($CI->db->field_exists('active', $contactsTable)) {
            $CI->db->where('active', 1);
        }
        if ($CI->db->field_exists('is_primary', $contactsTable)) {
            $CI->db->order_by('is_primary', 'DESC');
        }
        if ($CI->db->field_exists('id', $contactsTable)) {
            $CI->db->order_by('id', 'ASC');
        }

        $contact = $CI->db->get($contactsTable)->row();

        return ($contact && !empty($contact->email)) ? $contact->email : '';
    }
}

if (!function_exists('shipment_tracker_get_client_name')) {
    function shipment_tracker_get_client_name($clientId)
    {
        $CI     = &get_instance();
        $client = $CI->db
            ->where('userid', (int) $clientId)
            ->get(db_prefix() . 'clients')
            ->row();

        if ($client && !empty($client->company)) {
            return $client->company;
        }

        return 'Client #' . $clientId;
    }
}

if (!function_exists('shipment_tracker_notify')) {
    function shipment_tracker_notify($shipment, $note = '')
    {
        $CI = &get_instance();

        $email = shipment_tracker_get_client_email($shipment->client_id);
        if (empty($email)) {
            return false;
        }

        $name    = shipment_tracker_get_client_name($shipment->client_id);
        $url     = site_url('shipment_tracker/shipment_tracker_client/track/' . $shipment->id);
        $company = get_option('companyname');

        $template = $CI->db
            ->where('slug', 'shipment_status_update')
            ->where('active', 1)
            ->get(db_prefix() . 'emailtemplates')
            ->row();

        if ($template) {
            $find = [
                '{client_name}', '{tracking_number}',
                '{status}',      '{origin}',
                '{destination}', '{status_note}',
                '{tracking_url}','{company_name}',
            ];
            $replace = [
                $name,
                $shipment->tracking_number,
                shipment_tracker_status_label($shipment->status),
                isset($shipment->origin)      ? $shipment->origin      : '',
                isset($shipment->destination) ? $shipment->destination : '',
                $note,
                $url,
                $company,
            ];
            $subject = str_replace($find, $replace, $template->subject);
            $message = str_replace($find, $replace, $template->message);
        } else {
            $subject = 'Shipment ' . $shipment->tracking_number
                . ' — ' . shipment_tracker_status_label($shipment->status);

            $message = '<p>Dear ' . htmlspecialchars($name) . ',</p>'
                . '<p>Your shipment <strong>'
                . htmlspecialchars($shipment->tracking_number)
                . '</strong> status updated to: <strong>'
                . htmlspecialchars(shipment_tracker_status_label($shipment->status))
                . '</strong></p>';

            if (!empty($note)) {
                $message .= '<p>Note: ' . htmlspecialchars($note) . '</p>';
            }

            $message .= '<p>Track: <a href="' . $url . '">' . $url . '</a></p>'
                . '<p>Regards,<br>' . htmlspecialchars($company) . '</p>';
        }

        $CI->load->library('email');
        $CI->email->initialize(['mailtype' => 'html', 'charset' => 'utf-8']);
        $CI->email->from(get_option('smtp_email'), $company);
        $CI->email->to($email);
        $CI->email->subject($subject);
        $CI->email->message($message);

        return $CI->email->send(false);
    }
}