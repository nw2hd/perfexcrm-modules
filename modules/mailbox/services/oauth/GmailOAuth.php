<?php

namespace modules\mailbox\services\oauth;

defined('BASEPATH') or exit('No direct script access allowed');

use Exception;

/**
 * Gmail OAuth2 Provider
 */
class GmailOAuth
{
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    
    public function __construct($clientId = null, $clientSecret = null)
    {
        // Debug: Log what we receive
        $CI = &get_instance();
        $debug_msg = date('Y-m-d H:i:s') . " [GmailOAuth Constructor]\n";
        $debug_msg .= "  Received clientId: " . ($clientId === null ? 'NULL' : ($clientId === '' ? 'EMPTY STRING' : substr($clientId, 0, 20) . '...')) . "\n";
        $debug_msg .= "  Received clientSecret: " . ($clientSecret === null ? 'NULL' : ($clientSecret === '' ? 'EMPTY STRING' : substr($clientSecret, 0, 10) . '...')) . "\n";
        
        if (file_exists(FCPATH . 'uploads/oauth_debug.txt')) {
            file_put_contents(FCPATH . 'uploads/oauth_debug.txt', $debug_msg, FILE_APPEND);
        }
        
        log_activity('[MAILBOX OAUTH] GmailOAuth received - Client ID: ' . ($clientId ? 'PROVIDED' : 'EMPTY') . ', Secret: ' . ($clientSecret ? 'PROVIDED' : 'EMPTY'));
        
        // Use provided credentials (per-staff) or fall back to global settings
        $this->clientId = $clientId ?: get_option('mailbox_gmail_client_id');
        $this->clientSecret = $clientSecret ?: get_option('mailbox_gmail_client_secret');
        $this->redirectUri = admin_url('mailbox/oauth/callback?provider=gmail');
        
        log_activity('[MAILBOX OAUTH] GmailOAuth after fallback - Client ID: ' . ($this->clientId ? 'SET' : 'EMPTY') . ', Secret: ' . ($this->clientSecret ? 'SET' : 'EMPTY'));
    }
    
    /**
     * Get authorization URL
     *
     * @return string
     */
    public function getAuthUrl()
    {
        // DEBUG: Log what clientId we're using
        $CI = &get_instance();
        $client_id_preview = $this->clientId ? substr($this->clientId, 0, 30) . '...' : 'EMPTY';
        log_activity('[MAILBOX OAUTH] getAuthUrl() - Using Client ID: ' . $client_id_preview . ' (length: ' . strlen($this->clientId) . ')');
        
        if (empty($this->clientId)) {
            log_activity('[MAILBOX OAUTH] ERROR: Client ID is empty in getAuthUrl()!');
            throw new Exception('Gmail Client ID not configured');
        }
        
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => 'https://mail.google.com/',
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];
        
        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        
        // DEBUG: Log the built URL (show client_id parameter)
        $url_parts = parse_url($authUrl);
        parse_str($url_parts['query'], $url_params);
        log_activity('[MAILBOX OAUTH] Built OAuth URL with client_id: ' . substr($url_params['client_id'], 0, 30) . '...');
        
        return $authUrl;
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
            throw new Exception('Gmail OAuth credentials not configured');
        }
        
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code'
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
            throw new Exception('Gmail OAuth credentials not configured');
        }
        
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token'
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
