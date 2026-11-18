<?php

namespace modules\mailbox\services;

defined('BASEPATH') or exit('No direct script access allowed');

use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;
use Exception;

/**
 * IMAP Service Wrapper
 * Provides unified interface for IMAP operations with OAuth2 support
 */
class ImapService
{
    private $client;
    private $config;
    private $currentFolder;
    
    public function __construct($config)
    {
        $this->config = $config;
        
        // Initialize ClientManager with default config if not already set
        // Check if config is empty or doesn't have the required structure
        $configPath = __DIR__ . '/../vendor/webklex/php-imap/src/config/imap.php';
        if (file_exists($configPath)) {
            // Use ClientManager's setConfig method to properly initialize
            try {
                $manager = new ClientManager($configPath);
            } catch (Exception $e) {
                // If that fails, try loading config directly
                if (empty(ClientManager::$config) || !isset(ClientManager::$config['default'])) {
                    $imapConfig = require $configPath;
                    ClientManager::$config = $imapConfig;
                }
            }
        } else {
            // Set minimal default config if file doesn't exist
            if (empty(ClientManager::$config) || !isset(ClientManager::$config['default'])) {
                ClientManager::$config = [
                    'default' => 'default',
                    'accounts' => [
                        'default' => [
                            'host' => 'localhost',
                            'port' => 993,
                            'protocol' => 'imap',
                            'encryption' => 'ssl',
                            'validate_cert' => true,
                            'username' => '',
                            'password' => '',
                            'authentication' => null,
                            'timeout' => 30,
                            'extensions' => [],
                            'proxy' => [
                                'socket' => null,
                                'request_fulluri' => false,
                                'username' => null,
                                'password' => null,
                            ],
                        ],
                    ],
                    'options' => [
                        'debug' => false,
                        'uid_cache' => true,
                        'delimiter' => '/',
                        'sequence' => 1,
                        'fetch' => 1,
                        'fetch_order' => 'asc',
                        'fetch_body' => true,
                        'fetch_flags' => true,
                        'soft_fail' => false,
                        'message_key' => 'list',
                        'common_folders' => [
                            'sent' => 'INBOX/Sent',
                        ],
                        'dispositions' => ['attachment', 'inline'],
                    ],
                ];
            }
        }
    }
    
    /**
     * Connect to IMAP server
     * Supports both password and OAuth2 authentication
     *
     * @param array $staff Staff data array
     * @return bool
     */
    public function connect($staff)
    {
        try {
            if (isset($staff['auth_method']) && $staff['auth_method'] === 'oauth2') {
                return $this->connectOAuth($staff);
            }
            return $this->connectPassword($staff);
        } catch (Exception $e) {
            log_activity('IMAP Connection Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Connect using OAuth2
     *
     * @param array $staff
     * @return bool
     */
    private function connectOAuth($staff)
    {
        $accessToken = $this->getValidAccessToken($staff);
        
        if (empty($accessToken)) {
            log_activity('OAuth access token is empty for staff: ' . $staff['email']);
            return false;
        }
        
        // Auto-detect IMAP server based on provider
        $provider = $staff['oauth_provider'] ?? '';
        $imapServer = $this->getOAuthImapServer($provider);
        
        // Build complete config array with all required fields
        $clientConfig = [
            'host'          => $imapServer,
            'port'          => 993, // OAuth providers use SSL on port 993
            'encryption'    => 'ssl',
            'validate_cert' => $this->config['validate_cert'] ?? true,
            'username'      => $staff['email'],
            'password'      => $accessToken,
            'protocol'      => 'imap',
            'authentication' => 'oauth',
            'timeout'       => 30,
            'extensions'    => [],
            'proxy'         => [
                'socket' => null,
                'request_fulluri' => false,
                'username' => null,
                'password' => null,
            ],
            'options'       => [
                'open' => [
                    'XOAUTH2' => $this->buildOAuthString($staff['email'], $accessToken)
                ]
            ]
        ];
        
        // Use ClientManager::make() to avoid config dependency issues  
        $manager = new ClientManager();
        $this->client = $manager->make($clientConfig);
        
        try {
            $connected = $this->client->connect();
            if ($connected) {
                log_activity('OAuth IMAP connection successful for ' . $staff['email']);
            } else {
                log_activity('OAuth IMAP connection failed for ' . $staff['email']);
            }
            return $connected;
        } catch (Exception $e) {
            // Detailed error logging for debugging
            $errorDetails = [
                'Exception Class' => get_class($e),
                'Message' => $e->getMessage(),
                'Code' => $e->getCode(),
                'File' => $e->getFile() . ':' . $e->getLine(),
            ];
            
            log_activity('OAuth IMAP connection failed for ' . $staff['email'] . '. Exception: ' . get_class($e));
            log_activity('IMAP Error Details: ' . json_encode($errorDetails));
            
            // Log previous exceptions in chain
            $previous = $e->getPrevious();
            $depth = 1;
            while ($previous && $depth <= 3) {
                log_activity('IMAP Underlying Error #' . $depth . ': [' . get_class($previous) . '] ' . $previous->getMessage());
                $previous = $previous->getPrevious();
                $depth++;
            }
            
            // Store error for retrieval
            $this->lastError = $e->getMessage();
            
            return false;
        }
    }
    
    /**
     * Get IMAP server for OAuth provider
     *
     * @param string $provider
     * @return string
     */
    private function getOAuthImapServer($provider)
    {
        switch (strtolower($provider)) {
            case 'gmail':
                return 'imap.gmail.com';
            case 'outlook':
            case 'microsoft365':
            case 'microsoft':
                return 'outlook.office365.com';
            default:
                // Fallback to configured server
                return $this->config['imap_server'] ?? 'localhost';
        }
    }
    
    /**
     * Connect using password (backward compatibility)
     *
     * @param array $staff
     * @return bool
     */
    private function connectPassword($staff)
    {
        $encrypted_password = $staff['mail_password'];
        $password = decrypt_mail_password($encrypted_password);
        
        if (empty($password)) {
            log_activity('IMAP Password is empty after decryption for ' . $staff['email']);
            return false;
        }
        
        // Debug logging (without revealing actual password)
        log_activity('IMAP Password Auth Debug for ' . $staff['email'] . ':');
        log_activity('  - Encrypted Password Length: ' . strlen($encrypted_password) . ' characters');
        log_activity('  - Decrypted Password Length: ' . strlen($password) . ' characters');
        log_activity('  - Looks Still Encrypted: ' . (strlen($password) > 40 ? 'YES (PROBLEM!)' : 'NO (Good)'));
        log_activity('  - Password First 4 chars: ' . substr($password, 0, 4) . '****');
        log_activity('  - Username: ' . $staff['email']);
        log_activity('  - Password Empty: ' . (empty($password) ? 'YES' : 'NO'));
        log_activity('  - Server: ' . $this->config['imap_server'] . ':' . ($this->config['port'] ?? 993));
        log_activity('  - Encryption: ' . ($this->config['encryption'] ?? 'ssl'));
        
        // Build complete config array with all required fields
        $clientConfig = [
            'host'          => $this->config['imap_server'],
            'port'          => $this->config['port'] ?? 993,
            'encryption'    => $this->config['encryption'] ?? 'ssl',
            'validate_cert' => $this->config['validate_cert'] ?? true,
            'username'      => $staff['email'],
            'password'      => $password,
            'protocol'      => 'imap',
            'authentication' => null,
            'timeout'       => 30,
            'extensions'    => [],
            'proxy'         => [
                'socket' => null,
                'request_fulluri' => false,
                'username' => null,
                'password' => null,
            ],
        ];
        
        log_activity('IMAP Config built for ' . $staff['email'] . ':');
        log_activity('  - Config Username: ' . $clientConfig['username']);
        log_activity('  - Config Password Length: ' . strlen($clientConfig['password']) . ' chars');
        log_activity('  - Config Password First 4: ' . substr($clientConfig['password'], 0, 4) . '****');
        log_activity('  - Config Host: ' . $clientConfig['host'] . ':' . $clientConfig['port']);
        log_activity('  - Config Encryption: ' . $clientConfig['encryption']);
        
        // Use ClientManager::make() to avoid config dependency issues  
        $manager = new ClientManager();
        $this->client = $manager->make($clientConfig);
        
        try {
            $connected = $this->client->connect();
            if ($connected) {
                log_activity('Password IMAP connection successful for ' . $staff['email']);
            }
            return $connected;
        } catch (Exception $e) {
            // Detailed error logging for debugging
            $errorDetails = [
                'Exception Class' => get_class($e),
                'Message' => $e->getMessage(),
                'Code' => $e->getCode(),
                'File' => $e->getFile() . ':' . $e->getLine(),
            ];
            
            log_activity('Password IMAP connection failed for ' . $staff['email'] . '. Exception: ' . get_class($e));
            log_activity('IMAP Error Details: ' . json_encode($errorDetails));
            
            // Log previous exceptions in chain
            $previous = $e->getPrevious();
            $depth = 1;
            while ($previous && $depth <= 3) {
                log_activity('IMAP Underlying Error #' . $depth . ': [' . get_class($previous) . '] ' . $previous->getMessage());
                $previous = $previous->getPrevious();
                $depth++;
            }
            
            // Store error for retrieval
            $this->lastError = $e->getMessage();
            
            return false;
        }
    }
    
    /**
     * Build OAuth2 string for IMAP
     *
     * @param string $username
     * @param string $accessToken
     * @return string
     */
    private function buildOAuthString($username, $accessToken)
    {
        return base64_encode("user=" . $username . "\x01auth=Bearer " . $accessToken . "\x01\x01");
    }
    
    /**
     * Get valid access token (refresh if expired)
     *
     * @param array $staff
     * @return string
     */
    private function getValidAccessToken($staff)
    {
        $CI = &get_instance();
        
        // Check if token is expired
        if (!empty($staff['oauth_token_expires_at'])) {
            $expiresAt = strtotime($staff['oauth_token_expires_at']);
            
            if ($expiresAt <= time() + 300) { // Refresh 5 minutes before expiry
                log_activity('Token expired or expiring soon, attempting refresh for ' . $staff['email']);
                
                // Check if per-staff OAuth credentials exist
                $clientId = !empty($staff['oauth_client_id']) ? decrypt_mail_password($staff['oauth_client_id']) : '';
                $clientSecret = !empty($staff['oauth_client_secret']) ? decrypt_mail_password($staff['oauth_client_secret']) : '';
                
                if (empty($clientId) || empty($clientSecret)) {
                    log_activity('OAuth credentials (client_id/client_secret) not configured for ' . $staff['email'] . '. Token refresh skipped. Please save OAuth credentials and reconnect.');
                    return decrypt_mail_password($staff['oauth_access_token']);
                }
                
                // Token expired or expiring soon, refresh it
                try {
                    $oauth = $this->getOAuthProvider($staff['oauth_provider'], $clientId, $clientSecret, $staff['oauth_tenant_id'] ?? null);
                    $refreshToken = decrypt_mail_password($staff['oauth_refresh_token']);
                    
                    if (empty($refreshToken)) {
                        log_activity('Refresh token is empty for ' . $staff['email']);
                        return decrypt_mail_password($staff['oauth_access_token']);
                    }
                    
                    log_activity('Refreshing token for ' . $staff['email']);
                    $tokens = $oauth->refreshToken($refreshToken);
                    
                    if ($tokens && isset($tokens['access_token'])) {
                        log_activity('Token refreshed successfully for ' . $staff['email']);
                        // Update database
                        $CI->db->where('staffid', $staff['staffid']);
                        $updateData = [
                            'oauth_access_token' => encrypt_mail_password($tokens['access_token']),
                            'oauth_token_expires_at' => date('Y-m-d H:i:s', time() + ($tokens['expires_in'] ?? 3600))
                        ];
                        
                        // Update refresh token if provided
                        if (isset($tokens['refresh_token'])) {
                            $updateData['oauth_refresh_token'] = encrypt_mail_password($tokens['refresh_token']);
                        }
                        
                        $CI->db->update(db_prefix().'staff', $updateData);
                        
                        return $tokens['access_token'];
                    }
                } catch (Exception $e) {
                    // Silent fail for token refresh
                }
            }
        }
        
        $token = decrypt_mail_password($staff['oauth_access_token']);
        return $token;
    }
    
    /**
     * Get OAuth provider instance
     *
     * @param string $provider
     * @param string $clientId
     * @param string $clientSecret
     * @param string|null $tenantId (for Outlook)
     * @return object
     * @throws Exception
     */
    private function getOAuthProvider($provider, $clientId = null, $clientSecret = null, $tenantId = null)
    {
        $providerClass = 'modules\\mailbox\\services\\oauth\\' . ucfirst($provider) . 'OAuth';
        if (class_exists($providerClass)) {
            if ($provider === 'outlook') {
                return new $providerClass($clientId, $clientSecret, $tenantId);
            } else {
                return new $providerClass($clientId, $clientSecret);
            }
        }
        throw new Exception("OAuth provider not found: {$provider}");
    }
    
    /**
     * Check if connected
     *
     * @return bool
     */
    public function isConnected()
    {
        return $this->client !== null && $this->client->isConnected();
    }
    
    /**
     * Get error message
     *
     * @return string
     */
    public function getError()
    {
        if (!$this->client) {
            return 'Not connected';
        }
        
        // Webklex Client throws exceptions instead of storing errors
        // Check connection status
        if (!$this->client->isConnected()) {
            return 'Connection failed';
        }
        
        // If connected, no error
        return '';
    }
    
    /**
     * Select folder
     *
     * @param string $folder
     * @return bool
     */
    public function selectFolder($folder)
    {
        if (!$this->isConnected()) {
            return false;
        }
        
        try {
            $this->currentFolder = $this->client->getFolder($folder);
            return $this->currentFolder !== null;
        } catch (Exception $e) {
            log_activity('Folder selection error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all folders
     *
     * @return array
     */
    public function getFolders()
    {
        if (!$this->isConnected()) {
            return [];
        }
        
        try {
            $folders = $this->client->getFolders();
            $folderNames = [];
            foreach ($folders as $folder) {
                $folderNames[] = $folder->name;
            }
            return $folderNames;
        } catch (Exception $e) {
            log_activity('Get folders error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get unread messages
     * Returns array compatible with old format
     *
     * @param bool $withbody
     * @return array
     */
    public function getUnreadMessages($withbody = true)
    {
        if (!$this->currentFolder) {
            log_activity('Cannot get unread messages: No folder selected');
            return [];
        }
        
        try {
            $messages = $this->currentFolder->messages()
                ->setFetchFlags(true)
                ->setFetchBody(true) // Ensure body is fetched to get attachment content
                ->unseen()
                ->get();
            return $this->formatMessages($messages, $withbody);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get all messages
     * Returns array compatible with old format
     *
     * @param bool $withbody
     * @return array
     */
    public function getMessages($withbody = true)
    {
        if (!$this->currentFolder) {
            return [];
        }
        
        try {
            log_activity('Fetching all messages from folder: ' . $this->currentFolder->name);
            $messages = $this->currentFolder->messages()
                ->setFetchFlags(true)
                ->setFetchBody(true) // Ensure body is fetched to get attachment content
                ->all()
                ->get();
            $count = is_countable($messages) ? count($messages) : 0;
            log_activity('Found ' . $count . ' total messages');
            return $this->formatMessages($messages, $withbody);
        } catch (Exception $e) {
            log_activity('Get messages error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return [];
        }
    }
    
    /**
     * Get messages since UID (for incremental sync)
     *
     * @param int $uid
     * @return array
     */
    public function getMessagesSinceUid($uid)
    {
        if (!$this->currentFolder) {
            return [];
        }
        
        try {
            // Get messages with UID greater than provided
            $messages = $this->currentFolder->messages()->where('uid', '>', $uid)->get();
            return $this->formatMessages($messages, true);
        } catch (Exception $e) {
            log_activity('Get messages since UID error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Format messages to match old format
     *
     * @param array $messages
     * @param bool $withbody
     * @return array
     */
    private function formatMessages($messages, $withbody = true)
    {
        $formatted = [];
        
        foreach ($messages as $message) {
            $formatted[] = $this->formatMessage($message, $withbody);
        }
        
        return $formatted;
    }
    
    /**
     * Format single message to match old format
     *
     * @param Message $message
     * @param bool $withbody
     * @return array
     */
    private function formatMessage(Message $message, $withbody = true)
    {
        $from = $message->getFrom();
        $to = $message->getTo();
        $cc = $message->getCc();
        
        // Handle from address safely
        $fromStr = '';
        if (!empty($from)) {
            try {
                // getFrom() returns an Attribute object
                if (method_exists($from, 'first')) {
                    $fromAddr = $from->first();
                    if ($fromAddr) {
                        // Check if it's an Address object with mail property
                        if (is_object($fromAddr) && isset($fromAddr->mail)) {
                            $fromStr = ($fromAddr->personal ?? '') . ' <' . ($fromAddr->mail ?? '') . '>';
                        } elseif (is_string($fromAddr)) {
                            // If it's already a string, use it directly
                            $fromStr = $fromAddr;
                        }
                    }
                } elseif (method_exists($from, 'toString')) {
                    // Fallback to toString if first() doesn't work
                    $fromStr = $from->toString();
                } elseif (is_string($from)) {
                    $fromStr = $from;
                }
            } catch (Exception $e) {
                log_activity('Error extracting From address: ' . $e->getMessage());
            }
        }
        
        // Handle to addresses safely
        $toArray = [];
        if ($to && method_exists($to, 'all')) {
            try {
                $toAll = $to->all();
                if (is_countable($toAll) && count($toAll) > 0) {
                    $toArray = array_map(function($address) {
                        return ($address->personal ?? '') . ' <' . ($address->mail ?? '') . '>';
                    }, $toAll);
                }
            } catch (Exception $e) {
                log_activity('Error getting To addresses: ' . $e->getMessage());
            }
        }
        
        // Handle cc addresses safely
        $ccArray = [];
        if ($cc && method_exists($cc, 'count') && method_exists($cc, 'all')) {
            try {
                if ($cc->count() > 0) {
                    $ccAll = $cc->all();
                    if (is_countable($ccAll) && count($ccAll) > 0) {
                        $ccArray = array_map(function($address) {
                            return ($address->personal ?? '') . ' <' . ($address->mail ?? '') . '>';
                        }, $ccAll);
                    }
                }
            } catch (Exception $e) {
                log_activity('Error getting CC addresses: ' . $e->getMessage());
            }
        }
        
        // Extract threading headers
        $messageId = '';
        $inReplyTo = '';
        $references = '';
        
        try {
            $msgIdAttr = $message->getMessageId();
            if ($msgIdAttr && method_exists($msgIdAttr, 'first')) {
                $messageId = $msgIdAttr->first() ?? '';
            } elseif ($msgIdAttr && method_exists($msgIdAttr, 'toString')) {
                $messageId = $msgIdAttr->toString();
            }
            
            $inReplyToAttr = $message->getInReplyTo();
            if ($inReplyToAttr) {
                if (is_array($inReplyToAttr)) {
                    $inReplyTo = !empty($inReplyToAttr) ? $inReplyToAttr[0] : '';
                } elseif (method_exists($inReplyToAttr, 'first')) {
                    $inReplyTo = $inReplyToAttr->first() ?? '';
                } elseif (method_exists($inReplyToAttr, 'toString')) {
                    $inReplyTo = $inReplyToAttr->toString();
                }
            }
            
            $referencesAttr = $message->getReferences();
            if ($referencesAttr) {
                if (is_array($referencesAttr)) {
                    $references = implode(' ', $referencesAttr);
                } elseif (method_exists($referencesAttr, 'all')) {
                    $refs = $referencesAttr->all();
                    $references = is_array($refs) ? implode(' ', $refs) : '';
                } elseif (method_exists($referencesAttr, 'toString')) {
                    $references = $referencesAttr->toString();
                }
            }
        } catch (Exception $e) {
            log_activity('Error extracting threading headers: ' . $e->getMessage());
        }
        
        $formatted = [
            'uid' => $message->getUid(),
            'subject' => $message->getSubject() ?? '',
            'from' => $fromStr,
            'to' => $toArray,
            'cc' => $ccArray,
            'date' => $this->getMessageDate($message),
            'unread' => $this->isMessageUnread($message),
            'answered' => $this->isMessageAnswered($message),
            'deleted' => $this->isMessageDeleted($message),
            'message_id' => $messageId,
            'in_reply_to' => $inReplyTo,
            'references' => $references,
        ];
        
        if ($withbody) {
            $bodyData = $this->getBody($message);
            $formatted['body'] = $bodyData['body'];
            $formatted['html'] = $bodyData['html'];
        }
        
        // Get attachments safely (metadata only - content fetched separately)
        $formatted['attachments'] = [];
        try {
            // Ensure message body is parsed to get attachment metadata
            if (!$message->getBodies() && $withbody) {
                $message->parseBody();
            }
            
            $attachments = $message->getAttachments();
            if ($attachments && method_exists($attachments, 'count') && $attachments->count() > 0) {
                $attachmentIndex = 0;
                foreach ($attachments as $attachment) {
                    $name = '';
                    // Try multiple methods to get name
                    try {
                        if (method_exists($attachment, 'getName')) {
                            $name = $attachment->getName();
                        }
                        if (empty($name) && property_exists($attachment, 'name')) {
                            $name = $attachment->name;
                        }
                        if (empty($name) && isset($attachment->name)) {
                            $name = $attachment->name;
                        }
                    } catch (Exception $e) {
                        // Ignore errors
                    }
                    
                    // Fallback if still empty
                    if (empty($name)) {
                        $name = 'attachment_' . ($attachmentIndex + 1);
                    }
                    
                    $size = 0;
                    try {
                        if (method_exists($attachment, 'getSize')) {
                            $size = $attachment->getSize();
                        } elseif (isset($attachment->size)) {
                            $size = $attachment->size;
                        }
                    } catch (Exception $e) {
                        // Ignore size errors
                    }
                    
                    $formatted['attachments'][] = [
                        'name' => $name,
                        'size' => $size,
                        'filename' => $name,
                    ];
                    
                    $attachmentIndex++;
                }
            }
        } catch (Exception $e) {
            log_activity('Error getting attachments: ' . $e->getMessage());
        }
        
        return $formatted;
    }
    
    /**
     * Get message date safely
     *
     * @param Message $message
     * @return string
     */
    private function getMessageDate(Message $message)
    {
        try {
            $dateAttr = $message->getDate();
            if ($dateAttr && method_exists($dateAttr, 'toDate')) {
                $carbonDate = $dateAttr->toDate();
                if ($carbonDate instanceof \Carbon\Carbon) {
                    return $carbonDate->format('Y-m-d H:i:s');
                }
            }
            // Fallback: try to get first value
            if ($dateAttr && method_exists($dateAttr, 'first')) {
                $dateValue = $dateAttr->first();
                if ($dateValue) {
                    if ($dateValue instanceof \Carbon\Carbon) {
                        return $dateValue->format('Y-m-d H:i:s');
                    }
                    if (is_string($dateValue)) {
                        $parsed = \Carbon\Carbon::parse($dateValue);
                        return $parsed->format('Y-m-d H:i:s');
                    }
                }
            }
        } catch (Exception $e) {
            log_activity('Error parsing message date: ' . $e->getMessage());
        }
        
        // Final fallback
        return date('Y-m-d H:i:s');
    }
    
    /**
     * Check if message is unread (not seen)
     *
     * @param Message $message
     * @return bool
     */
    private function isMessageUnread(Message $message)
    {
        try {
            $flags = $message->getFlags();
            if ($flags && method_exists($flags, 'get')) {
                $seenFlag = $flags->get("seen");
                return $seenFlag === null; // Not seen = unread
            }
        } catch (Exception $e) {
            log_activity('Error checking unread status: ' . $e->getMessage());
        }
        return true; // Default to unread if we can't determine
    }
    
    /**
     * Check if message is answered
     *
     * @param Message $message
     * @return bool
     */
    private function isMessageAnswered(Message $message)
    {
        try {
            $flags = $message->getFlags();
            if ($flags && method_exists($flags, 'get')) {
                $answeredFlag = $flags->get("answered");
                return $answeredFlag !== null;
            }
        } catch (Exception $e) {
            // Ignore errors
        }
        return false;
    }
    
    /**
     * Check if message is deleted
     *
     * @param Message $message
     * @return bool
     */
    private function isMessageDeleted(Message $message)
    {
        try {
            $flags = $message->getFlags();
            if ($flags && method_exists($flags, 'get')) {
                $deletedFlag = $flags->get("deleted");
                return $deletedFlag !== null;
            }
        } catch (Exception $e) {
            // Ignore errors
        }
        return false;
    }
    
    /**
     * Get email body
     *
     * @param mixed $message Message object or UID
     * @return array
     */
    public function getBody($message)
    {
        if (is_numeric($message)) {
            // If UID provided, get message first
            try {
                $message = $this->currentFolder->messages()->getMessageByUid($message);
            } catch (Exception $e) {
                log_activity('Get body error for UID ' . $message . ': ' . $e->getMessage());
                return ['body' => '', 'html' => false];
            }
        }
        
        if (!$message instanceof Message) {
            return ['body' => '', 'html' => false];
        }
        
        $htmlBody = $message->getHTMLBody();
        $textBody = $message->getTextBody();
        
        return [
            'body' => $htmlBody ?: $textBody,
            'html' => !empty($htmlBody)
        ];
    }
    
    /**
     * Get attachment
     *
     * @param int $uid
     * @param int $index
     * @return array|false
     */
    public function getAttachment($uid, $index = 0)
    {
        if (!$this->currentFolder) {
            return false;
        }
        
        try {
            // Fetch message with body and attachments
            $message = $this->currentFolder->messages()
                ->setFetchBody(true)
                ->setFetchFlags(true)
                ->getMessageByUid($uid);
            
            if (!$message) {
                return false;
            }
            
            // Parse body to load attachments
            if (empty($message->getBodies())) {
                $message->parseBody();
            }
            
            $attachments = $message->getAttachments();
            if (!$attachments) {
                return false;
            }
            
            $attachmentCount = $attachments->count();
            if (!$attachmentCount || $attachmentCount <= $index) {
                return false;
            }
            
            // Convert collection to array to access by numeric index
            // AttachmentCollection extends PaginatedCollection which may not support numeric get()
            $attachmentsArray = $attachments->all();
            if (!isset($attachmentsArray[$index])) {
                // Try alternative: iterate to find by index
                $currentIndex = 0;
                $attachment = null;
                foreach ($attachments as $att) {
                    if ($currentIndex == $index) {
                        $attachment = $att;
                        break;
                    }
                    $currentIndex++;
                }
                
                if (!$attachment) {
                    return false;
                }
            } else {
                $attachment = $attachmentsArray[$index];
            }
            
            if (!$attachment) {
                return false;
            }
            
            
            // Get attachment content - try multiple methods
            $content = '';
            try {
                // Method 1: Use getContent() method
                if (method_exists($attachment, 'getContent')) {
                    $content = $attachment->getContent();
                }
                
                // Method 2: Access content property directly
                if (empty($content) && property_exists($attachment, 'content')) {
                    $content = $attachment->content;
                }
                
                // Method 3: Try magic getter
                if (empty($content)) {
                    try {
                        $reflection = new \ReflectionClass($attachment);
                        if ($reflection->hasProperty('content')) {
                            $prop = $reflection->getProperty('content');
                            $prop->setAccessible(true);
                            $content = $prop->getValue($attachment);
                        }
                    } catch (\ReflectionException $e) {
                        // Ignore reflection errors
                    }
                }
                
                // Method 4: Try accessing as array/object
                if (empty($content) && is_object($attachment)) {
                    try {
                        $content = $attachment->content ?? '';
                    } catch (Exception $e) {
                        // Ignore
                    }
                }
            } catch (Exception $e) {
                // Silent fail
            }
            
            // Get attachment name - try multiple methods
            $name = '';
            try {
                // Method 1: Try getName() method (magic method)
                try {
                    $name = $attachment->getName();
                } catch (Exception $e) {
                    // Ignore
                }
                
                // Method 2: Try direct property access
                if (empty($name) && property_exists($attachment, 'name')) {
                    $name = $attachment->name;
                }
                
                // Method 3: Try isset check
                if (empty($name) && isset($attachment->name)) {
                    $name = $attachment->name;
                }
                
                // Method 4: Try reflection to access protected/private property
                if (empty($name)) {
                    try {
                        $reflection = new \ReflectionClass($attachment);
                        if ($reflection->hasProperty('name')) {
                            $prop = $reflection->getProperty('name');
                            $prop->setAccessible(true);
                            $name = $prop->getValue($attachment);
                        }
                    } catch (\ReflectionException $e) {
                        // Ignore reflection errors
                    }
                }
                
                // Method 5: Try accessing attributes array (if it exists)
                if (empty($name) && property_exists($attachment, 'attributes')) {
                    try {
                        $reflection = new \ReflectionClass($attachment);
                        if ($reflection->hasProperty('attributes')) {
                            $prop = $reflection->getProperty('attributes');
                            $prop->setAccessible(true);
                            $attrs = $prop->getValue($attachment);
                            if (is_array($attrs) && isset($attrs['name'])) {
                                $name = $attrs['name'];
                            }
                        }
                    } catch (\ReflectionException $e) {
                        // Ignore
                    }
                }
            } catch (Exception $e) {
                // Silent fail
            }
            
            // Get attachment size
            $size = 0;
            try {
                if (method_exists($attachment, 'getSize')) {
                    $size = $attachment->getSize();
                } elseif (property_exists($attachment, 'size')) {
                    $size = $attachment->size;
                } elseif (isset($attachment->size)) {
                    $size = $attachment->size;
                }
            } catch (Exception $e) {
                // Ignore size errors
            }
            
            // Only use fallback if name is truly empty
            if (empty($name) || trim($name) === '') {
                $name = 'attachment_' . ($index + 1);
            } else {
                // Clean up the name - remove any null bytes or weird characters
                $name = trim($name);
                $name = str_replace(["\0", "\r", "\n"], '', $name);
            }
            
            $result = [
                'name' => $name,
                'size' => $size,
                'content' => $content,
            ];
            
            return $result;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Mark message as read/unread
     *
     * @param int $uid
     * @param bool $seen
     * @return bool
     */
    /**
     * Set message as seen/unseen
     *
     * @param int $uid
     * @param bool $seen
     * @return bool
     */
    public function setUnseenMessage($uid, $seen = true)
    {
        if (!$this->currentFolder) {
            return false;
        }
        
        try {
            $message = $this->currentFolder->messages()->getMessageByUid($uid);
            
            if (!$message) {
                return false;
            }
            
            if ($seen) {
                // Mark as read by setting the "Seen" flag
                $message->setFlag("Seen");
            } else {
                // Mark as unread by removing the "Seen" flag
                $message->unsetFlag("Seen");
            }
            
            return true;
        } catch (Exception $e) {
            log_activity('Set unseen message error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get reply-to addresses
     *
     * @param int $uid
     * @return array
     */
    public function getReplyToAddresses($uid)
    {
        if (!$this->currentFolder) {
            return [];
        }
        
        try {
            $message = $this->currentFolder->messages()->getMessageByUid($uid);
            
            if (!$message) {
                return [];
            }
            
            $replyTo = $message->getReplyTo();
            if (!$replyTo || !method_exists($replyTo, 'all')) {
                return [];
            }
            
            $replyToAll = $replyTo->all();
            if (!is_countable($replyToAll) || count($replyToAll) === 0) {
                return [];
            }
            
            return array_map(function($address) {
                return $address->mail ?? '';
            }, $replyToAll);
        } catch (Exception $e) {
            log_activity('Get reply-to error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Close connection
     */
    public function close()
    {
        if ($this->client) {
            try {
                $this->client->disconnect();
            } catch (Exception $e) {
                // Ignore disconnect errors
            }
        }
    }
    
    public function __destruct()
    {
        $this->close();
    }
}
