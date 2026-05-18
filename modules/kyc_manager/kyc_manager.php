<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: KYC Manager
Description: Know Your Customer compliance module. Manage client KYC profiles, documents, services, and generate branded PDF reports.
Version: 1.0.0
Requires at least: 2.3.*
*/

register_activation_hook('kyc_manager', 'kyc_manager_activation');
register_deactivation_hook('kyc_manager', 'kyc_manager_deactivation');
register_uninstall_hook('kyc_manager', 'kyc_manager_uninstall');

function kyc_manager_activation()
{
    $CI  = &get_instance();
    $pfx = db_prefix();

    // KYC Profiles
    if (!$CI->db->table_exists($pfx . 'kyc_profiles')) {
        $CI->db->query("
            CREATE TABLE `{$pfx}kyc_profiles` (
                `id`                    int(11)      NOT NULL AUTO_INCREMENT,
                `client_id`             int(11)      NOT NULL,
                `legal_name`            varchar(255) DEFAULT NULL,
                `registration_number`   varchar(100) DEFAULT NULL,
                `tax_id`                varchar(100) DEFAULT NULL,
                `date_of_incorporation` date         DEFAULT NULL,
                `date_of_birth`         date         DEFAULT NULL,
                `legal_address`         text,
                `operating_address`     text,
                `country`               varchar(100) DEFAULT NULL,
                `business_type`         varchar(50)  DEFAULT NULL,
                `industry`              varchar(255) DEFAULT NULL,
                `website`               varchar(255) DEFAULT NULL,
                `annual_turnover`       varchar(100) DEFAULT NULL,
                `employees`             varchar(50)  DEFAULT NULL,
                `risk_level`            varchar(20)  DEFAULT 'low',
                `status`                varchar(30)  NOT NULL DEFAULT 'not_started',
                `kyc_officer`           int(11)      DEFAULT NULL,
                `expiry_date`           date         DEFAULT NULL,
                `approval_date`         date         DEFAULT NULL,
                `rejection_reason`      text,
                `admin_notes`           text,
                `client_photo`          varchar(255) DEFAULT NULL,
                `date_created`          datetime     NOT NULL,
                `date_updated`          datetime     DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `client_id` (`client_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
    }

    // KYC Document Types
    if (!$CI->db->table_exists($pfx . 'kyc_document_types')) {
        $CI->db->query("
            CREATE TABLE `{$pfx}kyc_document_types` (
                `id`          int(11)      NOT NULL AUTO_INCREMENT,
                `name`        varchar(255) NOT NULL,
                `description` text,
                `required`    tinyint(1)   NOT NULL DEFAULT 0,
                `sort_order`  int(11)      DEFAULT 0,
                `active`      tinyint(1)   NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");

        // Insert default document types
        $defaultTypes = [
            ['Passport / ID Card',                    'Government-issued photo identification.',       1, 1],
            ['Trade License / Business Registration', 'Official business registration certificate.',   1, 2],
            ['Memorandum & Articles of Association',  'Company formation documents.',                  0, 3],
            ['Proof of Address',                      'Utility bill or bank statement (recent).',      1, 4],
            ['Bank Reference Letter',                 'Reference letter from the client\'s bank.',     0, 5],
            ['Audited Financial Statements',          'Most recent audited financial report.',         0, 6],
            ['Tax Registration Certificate',          'Tax registration or VAT certificate.',          0, 7],
            ['Shareholder / Director Information',    'List of shareholders and directors.',           0, 8],
            ['Power of Attorney',                     'If applicable, power of attorney document.',    0, 9],
            ['Other',                                 'Any other supporting document.',                0, 10],
        ];
        foreach ($defaultTypes as $dt) {
            $CI->db->insert($pfx . 'kyc_document_types', [
                'name'        => $dt[0],
                'description' => $dt[1],
                'required'    => $dt[2],
                'sort_order'  => $dt[3],
                'active'      => 1,
            ]);
        }
    }

    // KYC Documents
    if (!$CI->db->table_exists($pfx . 'kyc_documents')) {
        $CI->db->query("
            CREATE TABLE `{$pfx}kyc_documents` (
                `id`              int(11)      NOT NULL AUTO_INCREMENT,
                `client_id`       int(11)      NOT NULL,
                `document_type_id` int(11)     NOT NULL,
                `document_name`   varchar(255) DEFAULT NULL,
                `document_number` varchar(100) DEFAULT NULL,
                `issue_date`      date         DEFAULT NULL,
                `expiry_date`     date         DEFAULT NULL,
                `issuing_authority` varchar(255) DEFAULT NULL,
                `issuing_country` varchar(100) DEFAULT NULL,
                `filename`        varchar(255) DEFAULT NULL,
                `original_name`   varchar(255) DEFAULT NULL,
                `file_type`       varchar(20)  DEFAULT NULL,
                `status`          varchar(20)  NOT NULL DEFAULT 'pending',
                `staff_notes`     text,
                `uploaded_by`     int(11)      DEFAULT NULL,
                `uploaded_by_type` varchar(10) DEFAULT 'staff',
                `reviewed_by`     int(11)      DEFAULT NULL,
                `reviewed_date`   datetime     DEFAULT NULL,
                `date_created`    datetime     NOT NULL,
                PRIMARY KEY (`id`),
                KEY `client_id` (`client_id`),
                KEY `document_type_id` (`document_type_id`),
                KEY `status` (`status`),
                KEY `expiry_date` (`expiry_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
    }

    // KYC Service Map
    if (!$CI->db->table_exists($pfx . 'kyc_service_map')) {
        $CI->db->query("
            CREATE TABLE `{$pfx}kyc_service_map` (
                `id`          int(11)  NOT NULL AUTO_INCREMENT,
                `client_id`   int(11)  NOT NULL,
                `item_id`     int(11)  NOT NULL,
                `notes`       text,
                `date_added`  datetime NOT NULL,
                `added_by`    int(11)  DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `client_item` (`client_id`, `item_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
    }

    // KYC Audit Log
    if (!$CI->db->table_exists($pfx . 'kyc_audit_log')) {
        $CI->db->query("
            CREATE TABLE `{$pfx}kyc_audit_log` (
                `id`          int(11)      NOT NULL AUTO_INCREMENT,
                `client_id`   int(11)      NOT NULL,
                `action`      varchar(100) NOT NULL,
                `description` text,
                `old_value`   text,
                `new_value`   text,
                `staff_id`    int(11)      DEFAULT NULL,
                `date`        datetime     NOT NULL,
                PRIMARY KEY (`id`),
                KEY `client_id` (`client_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
    }

    // KYC Settings (using Perfex options table)
    $settings = [
        'kyc_pdf_heading'           => 'Know Your Customer (KYC) Form',
        'kyc_pdf_heading_color'     => '#2c3e50',
        'kyc_validity_months'       => '12',
        'kyc_client_upload_enabled' => '1',
        'kyc_expiry_reminder_days'  => '30',
        'kyc_expiry_reminder_enabled' => '1',
    ];
    foreach ($settings as $key => $val) {
        if (get_option($key) === false || get_option($key) === '') {
            add_option($key, $val);
        }
    }

    return true;
}

function kyc_manager_deactivation()
{
    // Nothing on deactivation
}

function kyc_manager_uninstall()
{
    $CI  = &get_instance();
    $pfx = db_prefix();
    $CI->db->query("DROP TABLE IF EXISTS `{$pfx}kyc_audit_log`");
    $CI->db->query("DROP TABLE IF EXISTS `{$pfx}kyc_service_map`");
    $CI->db->query("DROP TABLE IF EXISTS `{$pfx}kyc_documents`");
    $CI->db->query("DROP TABLE IF EXISTS `{$pfx}kyc_document_types`");
    $CI->db->query("DROP TABLE IF EXISTS `{$pfx}kyc_profiles`");

    $options = [
        'kyc_pdf_heading', 'kyc_pdf_heading_color', 'kyc_validity_months',
        'kyc_client_upload_enabled', 'kyc_expiry_reminder_days',
        'kyc_expiry_reminder_enabled',
    ];
    foreach ($options as $opt) {
        delete_option($opt);
    }
}

// ── PERMISSIONS ───────────────────────────────────────────
hooks()->add_filter('staff_permissions', 'kyc_manager_permissions');

function kyc_manager_permissions($permissions)
{
    $permissions['kyc_manager'] = [
        'name'         => _l('kyc_menu_title'),
        'capabilities' => [
            'view'   => _l('kyc_permission_view'),
            'create' => _l('kyc_permission_create'),
            'edit'   => _l('kyc_permission_edit'),
            'delete' => _l('kyc_permission_delete'),
        ],
    ];
    return $permissions;
}

// ── ADMIN HOOKS ───────────────────────────────────────────
hooks()->add_action('admin_init',                         'kyc_manager_admin_init');
hooks()->add_action('clients_summary_tab_title_before',   'kyc_manager_tab_title');
hooks()->add_action('clients_summary_tab_content_before', 'kyc_manager_tab_content');

// ── CLIENT PORTAL HOOKS ──────────────────────────────────
hooks()->add_action('app_client_assets',                  'kyc_manager_client_assets');
hooks()->add_action('app_customers_head',                 'kyc_manager_client_head');
hooks()->add_action('customers_navigation_start',         'kyc_manager_client_nav');

function kyc_manager_admin_init()
{
    $CI = &get_instance();
    kyc_manager_load_lang('admin');

    if (has_permission('kyc_manager', '', 'view')) {
        $CI->app_menu->add_sidebar_menu_item('kyc_manager', [
            'name'     => _l('kyc_menu_title'),
            'href'     => admin_url('kyc_manager/dashboard'),
            'icon'     => 'fa fa-id-card',
            'position' => 12,
        ]);
    }

    if (strpos(uri_string(), 'kyc_manager') !== false) {
        $CI->app_scripts->add(
            'kyc_manager_js',
            module_dir_url('kyc_manager', 'assets/js/kyc_manager.js'),
            true
        );
        $CI->app_css->add(
            'kyc_manager_css',
            module_dir_url('kyc_manager', 'assets/css/kyc_manager.css')
        );
    }
}

function kyc_manager_client_assets()
{
    kyc_manager_load_lang('client');
    if (strpos(uri_string(), 'kyc_manager') !== false) {
        $CI = &get_instance();
        $CI->app_css->add(
            'kyc_manager_css',
            module_dir_url('kyc_manager', 'assets/css/kyc_manager.css')
        );
    }
}

function kyc_manager_client_head()
{
    kyc_manager_load_lang('client');
}

function kyc_manager_load_lang($side = '')
{
    if (
        !empty($GLOBALS['_kyc_lang_loaded'])
        && isset($GLOBALS['_kyc_lang_side'])
        && $GLOBALS['_kyc_lang_side'] === $side
    ) {
        return;
    }

    $CI   = &get_instance();
    $lang = 'english';

    if ($side === 'client') {
        if (function_exists('is_client_logged_in') && is_client_logged_in() && function_exists('get_contact_user_id')) {
            $contactId     = get_contact_user_id();
            $contactsTable = db_prefix() . 'contacts';
            if ($contactId && $CI->db->field_exists('language', $contactsTable)) {
                $contact = $CI->db->select('language')
                    ->where('id', $contactId)
                    ->get($contactsTable)->row();
                if ($contact && !empty($contact->language)) {
                    $lang = $contact->language;
                }
            }
        }
        if ($lang === 'english') {
            $activeLang = get_option('active_language');
            if (!empty($activeLang)) {
                $lang = $activeLang;
            }
        }
    } else {
        if (isset($CI->session) && $CI->session->has_userdata('language')) {
            $lang = $CI->session->userdata('language');
        }
    }

    $langPath = module_dir_path('kyc_manager', 'language/' . $lang . '/');
    if (!is_dir($langPath)) {
        $lang = 'english';
    }

    $CI->lang->load('kyc_manager/kyc_manager', $lang);
    $GLOBALS['_kyc_lang_loaded'] = true;
    $GLOBALS['_kyc_lang_side']   = $side;
}

// ── ADMIN: CLIENT PROFILE TAB ─────────────────────────────
function kyc_manager_tab_title()
{
    if (!has_permission('kyc_manager', '', 'view')) {
        return;
    }
    echo '<li role="presentation">
        <a href="#tab_kyc" aria-controls="tab_kyc" role="tab" data-toggle="tab">
            <i class="fa fa-id-card"></i>&nbsp;' . _l('kyc_menu_title') . '
        </a>
    </li>';
}

function kyc_manager_tab_content()
{
    if (!has_permission('kyc_manager', '', 'view')) {
        return;
    }
    $CI       = &get_instance();
    $clientId = (int) $CI->uri->segment(3);
    if (!$clientId) {
        return;
    }
    $CI->load->model('kyc_manager/kyc_manager_model', 'kycmodel');
    $profile   = $CI->kycmodel->get_profile($clientId);
    $documents = $CI->kycmodel->get_documents($clientId);
    $services  = $CI->kycmodel->get_services($clientId);
    include module_dir_path('kyc_manager', 'views/admin/partials/client_tab.php');
}

// ── CLIENT NAV MENU ──────────────────────────────────────
function kyc_manager_client_nav()
{
    if (!is_client_logged_in()) {
        return;
    }
    kyc_manager_load_lang('client');

    $url    = site_url('kyc_manager/kyc_client');
    $active = (strpos(uri_string(), 'kyc_manager') !== false) ? 'active' : '';

    echo '<li class="customers-nav-item-kyc-manager">
        <a href="' . $url . '" class="nav-link customers-nav-item-link ' . $active . '">
            <i class="fa fa-id-card fa-fw"></i>
            <span class="customers-nav-item-name">' . _l('kyc_menu_title') . '</span>
        </a>
    </li>';
}

// ── HELPER FUNCTIONS ──────────────────────────────────────

if (!function_exists('kyc_statuses')) {
    function kyc_statuses()
    {
        return [
            'not_started' => _l('kyc_status_not_started'),
            'in_progress' => _l('kyc_status_in_progress'),
            'submitted'   => _l('kyc_status_submitted'),
            'verified'    => _l('kyc_status_verified'),
            'rejected'    => _l('kyc_status_rejected'),
            'expired'     => _l('kyc_status_expired'),
        ];
    }
}

if (!function_exists('kyc_status_label')) {
    function kyc_status_label($status)
    {
        $list = kyc_statuses();
        return isset($list[$status]) ? $list[$status] : ucwords(str_replace('_', ' ', $status));
    }
}

if (!function_exists('kyc_status_badge')) {
    function kyc_status_badge($status)
    {
        $map = [
            'not_started' => 'default',
            'in_progress' => 'info',
            'submitted'   => 'primary',
            'verified'    => 'success',
            'rejected'    => 'danger',
            'expired'     => 'warning',
        ];
        $cls = isset($map[$status]) ? $map[$status] : 'default';
        return '<span class="label label-' . $cls . '">'
            . htmlspecialchars(kyc_status_label($status)) . '</span>';
    }
}

if (!function_exists('kyc_doc_status_badge')) {
    function kyc_doc_status_badge($status)
    {
        $map = [
            'pending'  => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            'expired'  => 'default',
        ];
        $labels = [
            'pending'  => _l('kyc_doc_pending'),
            'approved' => _l('kyc_doc_approved'),
            'rejected' => _l('kyc_doc_rejected'),
            'expired'  => _l('kyc_doc_expired'),
        ];
        $cls   = isset($map[$status]) ? $map[$status] : 'default';
        $label = isset($labels[$status]) ? $labels[$status] : ucwords($status);
        return '<span class="label label-' . $cls . '">' . htmlspecialchars($label) . '</span>';
    }
}

if (!function_exists('kyc_risk_badge')) {
    function kyc_risk_badge($level)
    {
        $map = [
            'low'    => 'success',
            'medium' => 'warning',
            'high'   => 'danger',
        ];
        $cls = isset($map[$level]) ? $map[$level] : 'default';
        return '<span class="label label-' . $cls . '">'
            . htmlspecialchars(ucfirst($level)) . '</span>';
    }
}

if (!function_exists('kyc_get_client_email')) {
    function kyc_get_client_email($clientId)
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
        $CI->db->order_by('id', 'ASC');
        $contact = $CI->db->get($contactsTable)->row();
        return ($contact && !empty($contact->email)) ? $contact->email : '';
    }
}

if (!function_exists('kyc_get_client_name')) {
    function kyc_get_client_name($clientId)
    {
        $CI     = &get_instance();
        $client = $CI->db->where('userid', (int) $clientId)
            ->get(db_prefix() . 'clients')->row();
        return ($client && !empty($client->company)) ? $client->company : 'Client #' . $clientId;
    }
}

if (!function_exists('kyc_log_action')) {
    function kyc_log_action($clientId, $action, $description = '', $oldValue = '', $newValue = '')
    {
        $CI = &get_instance();
        $CI->db->insert(db_prefix() . 'kyc_audit_log', [
            'client_id'   => (int) $clientId,
            'action'      => $action,
            'description' => $description,
            'old_value'   => $oldValue,
            'new_value'   => $newValue,
            'staff_id'    => function_exists('get_staff_user_id') ? get_staff_user_id() : null,
            'date'        => date('Y-m-d H:i:s'),
        ]);
    }
}