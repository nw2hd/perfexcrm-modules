<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * OAuth Controller for Mailbox
 * Handles OAuth2 authentication flow for Gmail, Outlook, etc.
 */
class OAuth extends AdminController
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->model('mailbox_model');
    }

    /**
     * Initiate OAuth authorization
     *
     * @param string $provider (gmail, outlook)
     */
    public function authorize()
    {
        $provider = $this->input->get('provider');
        $staffId = get_staff_user_id();
        
        if (empty($provider)) {
            set_alert('danger', 'Provider not specified');
            redirect(admin_url('profile?tab=mailbox_config'));
        }
        
        try {
            // Fetch the CURRENT staff member's OAuth credentials
            $this->load->model('staff_model');
            $staff = $this->staff_model->get($staffId);
            
            if (empty($staff)) {
                throw new Exception('Staff member not found');
            }
            
            // Check if encrypted credentials exist
            $has_raw_id = isset($staff->oauth_client_id) && !empty($staff->oauth_client_id);
            $has_raw_secret = isset($staff->oauth_client_secret) && !empty($staff->oauth_client_secret);
            
            if (!$has_raw_id || !$has_raw_secret) {
                throw new Exception('OAuth credentials not saved. Please save your Client ID and Secret first.');
            }
            
            // Decrypt OAuth credentials
            $clientId = decrypt_mail_password($staff->oauth_client_id);
            $clientSecret = decrypt_mail_password($staff->oauth_client_secret);
            $tenantId = !empty($staff->oauth_tenant_id) ? $staff->oauth_tenant_id : null;
            
            if (empty($clientId) || empty($clientSecret)) {
                throw new Exception('OAuth credentials not configured. Please save your Client ID and Secret first.');
            }
            
            $providerClass = 'modules\\mailbox\\services\\oauth\\' . ucfirst($provider) . 'OAuth';
            
            if (!class_exists($providerClass)) {
                throw new Exception('OAuth provider not found: ' . $provider);
            }
            
            // Pass the staff member's credentials to the OAuth provider
            if ($provider === 'outlook') {
                $oauth = new $providerClass($clientId, $clientSecret, $tenantId);
            } else {
                $oauth = new $providerClass($clientId, $clientSecret);
            }
            
            $authUrl = $oauth->getAuthUrl();
            
            // Store provider and staff ID in session for callback
            $this->session->set_userdata('oauth_provider', $provider);
            $this->session->set_userdata('oauth_staff_id', $staffId);
            
            redirect($authUrl);
        } catch (Exception $e) {
            log_activity('OAuth authorization ERROR: ' . $e->getMessage(), null);
            
            set_alert('danger', $e->getMessage());
            redirect(admin_url('profile?tab=mailbox_config'));
        }
    }

    /**
     * OAuth callback handler
     */
    public function callback()
    {
        $code = $this->input->get('code');
        $error = $this->input->get('error');
        $provider = $this->input->get('provider') ?: $this->session->userdata('oauth_provider');
        $staffId = $this->session->userdata('oauth_staff_id') ?: get_staff_user_id();
        
        if ($error) {
            set_alert('danger', 'OAuth authorization failed: ' . $error);
            redirect(admin_url('profile?tab=mailbox_config'));
        }
        
        if (empty($code) || empty($provider)) {
            set_alert('danger', 'Invalid OAuth callback');
            redirect(admin_url('profile?tab=mailbox_config'));
        }
        
        try {
            // Fetch the staff member's OAuth credentials
            $this->load->model('staff_model');
            $staff = $this->staff_model->get($staffId);
            
            if (empty($staff)) {
                throw new Exception('Staff member not found');
            }
            
            // Decrypt OAuth credentials
            $clientId = !empty($staff->oauth_client_id) ? decrypt_mail_password($staff->oauth_client_id) : null;
            $clientSecret = !empty($staff->oauth_client_secret) ? decrypt_mail_password($staff->oauth_client_secret) : null;
            $tenantId = !empty($staff->oauth_tenant_id) ? $staff->oauth_tenant_id : null;
            
            if (empty($clientId) || empty($clientSecret)) {
                throw new Exception('OAuth credentials not configured');
            }
            
            $providerClass = 'modules\\mailbox\\services\\oauth\\' . ucfirst($provider) . 'OAuth';
            
            if (!class_exists($providerClass)) {
                throw new Exception('OAuth provider not found: ' . $provider);
            }
            
            // Pass the staff member's credentials to the OAuth provider
            if ($provider === 'outlook') {
                $oauth = new $providerClass($clientId, $clientSecret, $tenantId);
            } else {
                $oauth = new $providerClass($clientId, $clientSecret);
            }
            
            $tokens = $oauth->getAccessToken($code);
            
            if (empty($tokens['access_token'])) {
                throw new Exception('Failed to get access token');
            }
            
            // Save tokens to database
            $updateData = [
                'auth_method' => 'oauth2',
                'oauth_provider' => $provider,
                'oauth_access_token' => encrypt_mail_password($tokens['access_token']),
                'oauth_token_expires_at' => date('Y-m-d H:i:s', time() + ($tokens['expires_in'] ?? 3600))
            ];
            
            // Save refresh token if provided
            if (isset($tokens['refresh_token'])) {
                $updateData['oauth_refresh_token'] = encrypt_mail_password($tokens['refresh_token']);
            }
            
            $this->db->where('staffid', $staffId);
            $this->db->update(db_prefix().'staff', $updateData);
            
            // Clear session
            $this->session->unset_userdata('oauth_provider');
            $this->session->unset_userdata('oauth_staff_id');
            
            $successMsg = ucfirst($provider) . ' account connected successfully!';
            set_alert('success', $successMsg);
            log_activity('OAuth connected: ' . $provider, null);
            
        } catch (Exception $e) {
            set_alert('danger', 'OAuth error: ' . $e->getMessage());
            log_activity('OAuth ERROR: ' . $e->getMessage(), null);
        }
        
        redirect(admin_url('profile?tab=mailbox_config'));
    }

    /**
     * Disconnect OAuth account
     */
    public function disconnect()
    {
        $staffId = get_staff_user_id();
        
        $this->db->where('staffid', $staffId);
        $this->db->update(db_prefix().'staff', [
            'auth_method' => 'password',
            'oauth_provider' => null,
            'oauth_access_token' => null,
            'oauth_refresh_token' => null,
            'oauth_token_expires_at' => null
        ]);
        
        set_alert('success', 'OAuth account disconnected');
        log_activity('OAuth disconnected', null);
        
        redirect(admin_url('mailbox?group=config'));
    }
}
