<?php

namespace modules\mailbox\services\oauth;

defined('BASEPATH') or exit('No direct script access allowed');

use Exception;

/**
 * Outlook/Microsoft 365 OAuth2 Provider
 */
class OutlookOAuth
{
    private $clientId;
    private $clientSecret;
    private $tenantId;
    private $redirectUri;
    
    public function __construct($clientId = null, $clientSecret = null, $tenantId = null)
    {
        // Use provided credentials (per-staff) or fall back to global settings
        $this->clientId = $clientId ?: get_option('mailbox_outlook_client_id');
        $this->clientSecret = $clientSecret ?: get_option('mailbox_outlook_client_secret');
        $this->tenantId = $tenantId ?: (get_option('mailbox_outlook_tenant_id') ?: 'common');
        $this->redirectUri = admin_url('mailbox/oauth/callback?provider=outlook');
    }
    
    /**
     * Get authorization URL
     *
     * @return string
     */
    public function getAuthUrl()
    {
        if (empty($this->clientId)) {
            throw new Exception('Outlook Client ID not configured');
        }
        
        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'response_mode' => 'query',
            'scope' => 'https://graph.microsoft.com/Mail.ReadWrite https://graph.microsoft.com/Mail.Send https://outlook.office.com/IMAP.AccessAsUser.All offline_access',
            'state' => bin2hex(random_bytes(16)) // CSRF protection
        ];
        
        return 'https://login.microsoftonline.com/' . $this->tenantId . '/oauth2/v2.0/authorize?' . http_build_query($params);
    }
    
    /**
     * Exchange authorization code for access token
     *
     * @param string $code
     * @return array
     * @throws Exception
     */
    public function getAccessToken($code)
    {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new Exception('Outlook OAuth credentials not configured');
        }
        
        $ch = curl_init('https://login.microsoftonline.com/' . $this->tenantId . '/oauth2/v2.0/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
            'scope' => 'https://graph.microsoft.com/Mail.ReadWrite https://graph.microsoft.com/Mail.Send https://outlook.office.com/IMAP.AccessAsUser.All offline_access'
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = json_decode(curl_exec($ch), true);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if (!empty($error)) {
            throw new Exception('CURL error: ' . $error);
        }
        
        if ($httpCode !== 200 || isset($response['error'])) {
            throw new Exception('OAuth token error: ' . ($response['error_description'] ?? $response['error'] ?? 'Unknown error'));
        }
        
        return $response;
    }
    
    /**
     * Refresh access token
     *
     * @param string $refreshToken
     * @return array
     * @throws Exception
     */
    public function refreshToken($refreshToken)
    {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new Exception('Outlook OAuth credentials not configured');
        }
        
        $ch = curl_init('https://login.microsoftonline.com/' . $this->tenantId . '/oauth2/v2.0/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
            'scope' => 'https://outlook.office.com/IMAP.AccessAsUser.All offline_access'
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = json_decode(curl_exec($ch), true);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if (!empty($error)) {
            throw new Exception('CURL error: ' . $error);
        }
        
        if ($httpCode !== 200 || isset($response['error'])) {
            throw new Exception('OAuth refresh error: ' . ($response['error_description'] ?? $response['error'] ?? 'Unknown error'));
        }
        
        return $response;
    }
}
