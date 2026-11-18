<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php init_head(); ?>
<div id="wrapper" class="mailbox-module-page">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo _l('mailbox_config'); ?></h4>
                        <hr class="hr-panel-heading" />
                        
<?php echo form_open_multipart(admin_url('mailbox/config'), ['id'=>'mailbox-config-form']); ?>

<!-- SECTION 1: Email & Authentication Method -->
<div class="row">
    <div class="col-md-12">
        <h4 class="bold"><i class="fa fa-user"></i> <?php echo _l('staff_add_edit_email'); ?> & <?php echo _l('mailbox_auth_method'); ?></h4>
        <p class="text-muted"><?php echo _l('mailbox_user_pass_instructions'); ?></p>
    </div>
    <div class="col-md-6">
        <?php $value = (isset($member) ? $member->email : ''); ?>
        <?php echo render_input('email', 'staff_add_edit_email', $value, 'email', ['autocomplete'=>'off', 'readonly'=>'readonly']); ?>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="auth_method" class="control-label"><?php echo _l('mailbox_auth_method'); ?></label>
            <?php 
            $auth_method = isset($member) && isset($member->auth_method) ? $member->auth_method : 'password';
            ?>
            <select name="auth_method" id="auth_method" class="selectpicker" data-width="100%" onchange="toggleAuthMethod()">
                <option value="password" <?php echo $auth_method == 'password' ? 'selected' : ''; ?>><?php echo _l('mailbox_auth_password'); ?></option>
                <option value="oauth2" <?php echo $auth_method == 'oauth2' ? 'selected' : ''; ?>><?php echo _l('mailbox_auth_oauth2'); ?></option>
            </select>
        </div>
    </div>
</div>

<!-- SECTION 2A: Password Authentication Settings -->
<div id="password_auth_section" style="<?php echo $auth_method == 'oauth2' ? 'display:none;' : ''; ?>">
    <hr style="margin: 30px 0;">
    <div class="row">
        <div class="col-md-12">
            <h4 class="bold"><i class="fa fa-lock"></i> <?php echo _l('mailbox_email_password'); ?></h4>
        </div>
        <div class="col-md-6">
            <label for="mail_password" class="control-label"><?php echo _l('mailbox_email_password'); ?></label>
            <div class="input-group">
                <input type="password" class="form-control password" name="mail_password" value="" autocomplete="new-password" placeholder="<?php echo isset($member->mail_password) && !empty($member->mail_password) ? _l('mailbox_leave_blank_keep_password') : _l('mailbox_enter_password'); ?>">
                <span class="input-group-addon">
                    <a href="#mail_password" class="show_password" onclick="showPassword('mail_password'); return false;"><i class="fa-solid fa-eye"></i></a>
                </span>
            </div>
            <?php if (isset($member->mail_password) && !empty($member->mail_password)): ?>
                <small class="text-muted"><i class="fa fa-check-circle text-success"></i> <?php echo _l('mailbox_password_saved'); ?></small>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- IMAP Server Configuration -->
    <hr style="margin: 30px 0;">
    <div class="row">
        <div class="col-md-12">
            <h4 class="bold"><i class="fa fa-server"></i> <?php echo _l('mailbox_imap_configuration'); ?></h4>
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> 
                <?php echo _l('mailbox_imap_config_instructions'); ?>
            </div>
        </div>
        <div class="col-md-6">
            <?php 
            $imap_server_value = isset($member->imap_server) && !empty($member->imap_server) ? $member->imap_server : get_option('mailbox_imap_server'); 
            echo render_input('imap_server', 'mailbox_imap_server', $imap_server_value, 'text', [
                'placeholder' => 'imap.gmail.com, outlook.office365.com, mail.company.com'
            ]); 
            ?>
        </div>
        <div class="col-md-3">
            <?php 
            $imap_port_value = isset($member->imap_port) && !empty($member->imap_port) ? $member->imap_port : (get_option('mailbox_encryption') == 'ssl' ? 993 : 143); 
            echo render_input('imap_port', 'mailbox_imap_port', $imap_port_value, 'number', [
                'min' => 1,
                'max' => 65535
            ]); 
            ?>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label for="imap_encryption" class="control-label"><?php echo _l('mailbox_encryption'); ?></label>
                <?php 
                $imap_encryption_value = isset($member->imap_encryption) && !empty($member->imap_encryption) ? $member->imap_encryption : get_option('mailbox_encryption');
                ?>
                <select name="imap_encryption" id="imap_encryption" class="selectpicker" data-width="100%">
                    <option value="ssl" <?php echo $imap_encryption_value == 'ssl' ? 'selected' : ''; ?>>SSL (Port 993)</option>
                    <option value="tls" <?php echo $imap_encryption_value == 'tls' ? 'selected' : ''; ?>>TLS/STARTTLS (Port 143)</option>
                    <option value="" <?php echo empty($imap_encryption_value) ? 'selected' : ''; ?>>None (Not Recommended)</option>
                </select>
            </div>
        </div>
        <div class="col-md-12">
            <p class="text-muted">
                <small>
                    <strong><?php echo _l('mailbox_common_imap_servers'); ?>:</strong><br>
                    • Gmail: <code>imap.gmail.com:993</code> (SSL)<br>
                    • Outlook/Office365: <code>outlook.office365.com:993</code> (SSL)<br>
                    • Yahoo: <code>imap.mail.yahoo.com:993</code> (SSL)
                </small>
            </p>
        </div>
    </div>
    
    <!-- SMTP Server Configuration (for sending emails) -->
    <hr style="margin: 30px 0;">
    <div class="row">
        <div class="col-md-12">
            <h4 class="bold"><i class="fa fa-paper-plane"></i> <?php echo _l('mailbox_smtp_configuration'); ?></h4>
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> 
                <?php echo _l('mailbox_smtp_config_instructions'); ?>
            </div>
        </div>
        <div class="col-md-6">
            <?php 
            $smtp_host_value = isset($member->smtp_host) && !empty($member->smtp_host) ? $member->smtp_host : ''; 
            echo render_input('smtp_host', 'mailbox_smtp_server', $smtp_host_value, 'text', [
                'placeholder' => 'smtp.gmail.com, smtp.office365.com, mail.company.com'
            ]); 
            ?>
        </div>
        <div class="col-md-3">
            <?php 
            $smtp_port_value = isset($member->smtp_port) && !empty($member->smtp_port) ? $member->smtp_port : 587; 
            echo render_input('smtp_port', 'mailbox_smtp_port', $smtp_port_value, 'number', [
                'min' => 1,
                'max' => 65535
            ]); 
            ?>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label for="smtp_encryption" class="control-label"><?php echo _l('mailbox_encryption'); ?></label>
                <?php 
                $smtp_encryption_value = isset($member->smtp_encryption) && !empty($member->smtp_encryption) ? $member->smtp_encryption : 'tls';
                ?>
                <select name="smtp_encryption" id="smtp_encryption" class="selectpicker" data-width="100%">
                    <option value="tls" <?php echo $smtp_encryption_value == 'tls' ? 'selected' : ''; ?>>TLS/STARTTLS (Port 587)</option>
                    <option value="ssl" <?php echo $smtp_encryption_value == 'ssl' ? 'selected' : ''; ?>>SSL (Port 465)</option>
                    <option value="" <?php echo empty($smtp_encryption_value) ? 'selected' : ''; ?>>None (Not Recommended)</option>
                </select>
            </div>
        </div>
        <div class="col-md-6">
            <?php 
            $smtp_username_value = isset($member->smtp_username) && !empty($member->smtp_username) ? $member->smtp_username : ''; 
            echo render_input('smtp_username', 'mailbox_smtp_username', $smtp_username_value, 'text', [
                'placeholder' => 'your.email@example.com'
            ]); 
            ?>
        </div>
        <div class="col-md-6">
            <?php 
            $smtp_password_placeholder = isset($member->smtp_password) && !empty($member->smtp_password) ? _l('mailbox_leave_blank_keep_password') : '••••••••••';
            echo render_input('smtp_password', 'mailbox_smtp_password', '', 'password', [
                'placeholder' => $smtp_password_placeholder
            ]); 
            ?>
            <?php if (isset($member->smtp_password) && !empty($member->smtp_password)): ?>
                <small class="text-muted"><i class="fa fa-check-circle text-success"></i> <?php echo _l('mailbox_password_saved'); ?></small>
            <?php endif; ?>
        </div>
        <div class="col-md-12">
            <p class="text-muted">
                <small>
                    <strong><?php echo _l('mailbox_common_smtp_servers'); ?>:</strong><br>
                    • Gmail: <code>smtp.gmail.com:587</code> (TLS)<br>
                    • Outlook/Office365: <code>smtp.office365.com:587</code> (TLS)<br>
                    • Yahoo: <code>smtp.mail.yahoo.com:465</code> (SSL)
                </small>
            </p>
        </div>
    </div>
</div>

<!-- SECTION 2B: OAuth Authentication Settings -->
<div id="oauth_auth_section" style="<?php echo $auth_method == 'password' ? 'display:none;' : ''; ?>">
    <?php if (isset($member) && isset($member->oauth_provider) && !empty($member->oauth_provider)): ?>
        <!-- OAuth Connected -->
        <hr style="margin: 30px 0;">
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-success" style="display: flex; align-items: center; justify-content: space-between;">
                    <span>
                        <i class="fa-solid fa-check-circle"></i> 
                        <?php echo _l('mailbox_oauth_connected', ucfirst($member->oauth_provider)); ?>
                    </span>
                    <button type="button" class="btn btn-sm btn-danger" onclick="disconnectOAuth()">
                        <i class="fa fa-unlink"></i> <?php echo _l('mailbox_oauth_disconnect'); ?>
                    </button>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- OAuth Not Connected - Show Credentials Form -->
        <hr style="margin: 30px 0;">
        
        <?php 
        // Check if credentials are already saved (but not connected yet)
        $has_saved_credentials = !empty($member->oauth_client_id) && !empty($member->oauth_client_secret);
        ?>
        
        <?php if ($has_saved_credentials): ?>
            <!-- Credentials are saved, show Connect button -->
            <div class="row">
                <div class="col-md-12">
                    <div class="alert alert-success">
                        <i class="fa fa-check-circle"></i> 
                        <strong>OAuth credentials saved!</strong> Click the button below to connect your email account.
                    </div>
                    <a href="<?php echo admin_url('mailbox/oauth/authorize?provider=gmail'); ?>" class="btn btn-lg btn-success">
                        <i class="fa-brands fa-google"></i> Connect Gmail
                    </a>
                    <a href="<?php echo admin_url('mailbox/oauth/authorize?provider=outlook'); ?>" class="btn btn-lg btn-primary">
                        <i class="fa-brands fa-microsoft"></i> Connect Outlook
                    </a>
                    <hr style="margin: 20px 0;">
                    <p class="text-muted"><small>Or update your credentials below:</small></p>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-12">
                <h4 class="bold"><i class="fa fa-key"></i> <?php echo _l('mailbox_oauth_credentials'); ?></h4>
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> 
                    <?php echo _l('mailbox_oauth_credentials_instructions'); ?>
                </div>
            </div>
        </div>
        
        <!-- Provider Selection -->
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label for="oauth_provider_select" class="control-label">Select OAuth Provider</label>
                    <select id="oauth_provider_select" name="oauth_provider_selected" class="selectpicker" data-width="100%" onchange="toggleOAuthProvider()">
                        <option value="">-- Select Your Email Provider --</option>
                        <option value="gmail"><i class="fa-brands fa-google"></i> Gmail (Personal & Workspace accounts)</option>
                        <option value="outlook"><i class="fa-brands fa-microsoft"></i> Outlook (Microsoft 365)</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- OAuth Setup Instructions -->
        <div id="oauth_instructions" class="row" style="display:none; margin-top: 20px;">
            <div class="col-md-12">
                <div class="alert alert-warning">
                    <h4 style="margin-top: 0;"><i class="fa fa-graduation-cap"></i> Quick Start Guide</h4>
                    <p>You need to create an OAuth application to use this authentication method. Follow the provider-specific instructions below.</p>
                    <p><strong>After setup:</strong></p>
                    <ol>
                        <li>Save your credentials below</li>
                        <li>Click the "Connect [Provider]" button that will appear</li>
                        <li>Authorize access to your email</li>
                    </ol>
                </div>
            </div>
        </div>
        
        <!-- Gmail OAuth Credentials -->
        <div id="gmail_oauth_section" style="display:none;">
            <div class="row">
                <div class="col-md-12">
                    <h5 class="bold"><i class="fa-brands fa-google"></i> Gmail OAuth Credentials</h5>
                </div>
                <div class="col-md-6">
                    <?php 
                    // Pre-fill with saved credentials if they exist (and not already connected)
                    $saved_client_id = '';
                    if (isset($member->oauth_client_id) && !empty($member->oauth_client_id) && empty($member->oauth_provider)) {
                        $saved_client_id = decrypt_mail_password($member->oauth_client_id);
                    }
                    echo render_input('oauth_client_id', 'mailbox_gmail_client_id', $saved_client_id, 'text', ['placeholder' => 'Your Google Cloud Client ID']); 
                    ?>
                </div>
                <div class="col-md-6">
                    <?php 
                    $saved_client_secret = '';
                    if (isset($member->oauth_client_secret) && !empty($member->oauth_client_secret) && empty($member->oauth_provider)) {
                        $saved_client_secret = decrypt_mail_password($member->oauth_client_secret);
                    }
                    echo render_input('oauth_client_secret', 'mailbox_gmail_client_secret', $saved_client_secret, 'password', ['placeholder' => 'Your Google Cloud Client Secret']); 
                    ?>
                </div>
                <div class="col-md-12">
                    <div class="alert alert-warning">
                        <strong><i class="fa fa-exclamation-triangle"></i> IMPORTANT: Token Expiry for Unverified Apps</strong>
                        <p style="margin-top: 10px;">
                            <strong>Google's Limitation (Not Ours):</strong> If your OAuth app is in <strong>"Testing" mode</strong> (not published/verified), 
                            <strong>refresh tokens expire after 7 days</strong>. This is a Google Cloud Platform policy, not a limitation of this mailbox module.
                        </p>
                        <p><strong>Solutions:</strong></p>
                        <ul style="margin-bottom: 0;">
                            <li><strong>Free Personal Use:</strong> Add your email to "Test users" in OAuth consent screen. You'll need to re-authenticate every 7 days.</li>
                            <li><strong>Production/Verified App:</strong> Complete Google's app verification process. Tokens won't expire (recommended for business use).</li>
                            <li><strong>Paid Google Workspace:</strong> Workspace admins can mark the app as "Internal" - no expiry for organization users.</li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong><i class="fa fa-info-circle"></i> Gmail OAuth Setup:</strong>
                        <ol style="margin-top: 10px; margin-bottom: 0;">
                            <li>Go to <a href="https://console.cloud.google.com/apis/credentials" target="_blank"><strong>Google Cloud Console - Credentials</strong></a></li>
                            <li>Create a new project or select existing one</li>
                            <li>Click <strong>"Create Credentials"</strong> → <strong>"OAuth client ID"</strong></li>
                            <li>Application type: <strong>Web application</strong></li>
                            <li>Add redirect URI: <code><?php echo admin_url('mailbox/oauth/callback?provider=gmail'); ?></code></li>
                            <li>Copy the Client ID and Secret to the fields above</li>
                            <li>Enable <strong>Gmail API</strong> in <a href="https://console.cloud.google.com/apis/library/gmail.googleapis.com" target="_blank">API Library</a></li>
                            <li><strong>Important:</strong> Go to <strong>"OAuth consent screen"</strong>:
                                <ul>
                                    <li><strong>Personal/Free:</strong> Set to "Testing" and add your email to "Test users"</li>
                                    <li><strong>Business:</strong> Publish the app or set as "Internal" (Workspace only)</li>
                                </ul>
                            </li>
                            <li><strong>Save</strong> this config page</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Outlook OAuth Credentials -->
        <div id="outlook_oauth_section" style="display:none;">
            <div class="row">
                <div class="col-md-12">
                    <h5 class="bold"><i class="fa-brands fa-microsoft"></i> Outlook OAuth Credentials</h5>
                </div>
                <div class="col-md-6">
                    <?php 
                    // Pre-fill with saved credentials if they exist (and not already connected)
                    $saved_client_id = '';
                    if (isset($member->oauth_client_id) && !empty($member->oauth_client_id) && empty($member->oauth_provider)) {
                        $saved_client_id = decrypt_mail_password($member->oauth_client_id);
                    }
                    echo render_input('oauth_client_id', 'mailbox_outlook_client_id', $saved_client_id, 'text', ['placeholder' => 'Your Azure Application (Client) ID']); 
                    ?>
                </div>
                <div class="col-md-6">
                    <?php 
                    $saved_client_secret = '';
                    if (isset($member->oauth_client_secret) && !empty($member->oauth_client_secret) && empty($member->oauth_provider)) {
                        $saved_client_secret = decrypt_mail_password($member->oauth_client_secret);
                    }
                    echo render_input('oauth_client_secret', 'mailbox_outlook_client_secret', $saved_client_secret, 'password', ['placeholder' => 'Your Azure Client Secret Value']); 
                    ?>
                </div>
                <div class="col-md-6">
                    <?php 
                    $saved_tenant_id = 'common';
                    if (isset($member->oauth_tenant_id) && !empty($member->oauth_tenant_id)) {
                        $saved_tenant_id = $member->oauth_tenant_id;
                    }
                    echo render_input('oauth_tenant_id', 'mailbox_outlook_tenant_id', $saved_tenant_id, 'text', ['placeholder' => 'common (or your Tenant ID)']); 
                    ?>
                </div>
                <div class="col-md-12">
                    <div class="alert alert-warning">
                        <strong><i class="fa fa-exclamation-triangle"></i> Azure AD Tenant Required:</strong>
                        <p style="margin-top: 10px;">Personal Microsoft accounts (@hotmail.com, @outlook.com) cannot create app registrations. Get a FREE Azure account at <a href="https://azure.microsoft.com/free/" target="_blank">azure.microsoft.com/free</a></p>
                    </div>
                    
                    <div class="alert alert-info" style="background-color: #e8f4fd; border-color: #bee5eb;">
                        <strong><i class="fa fa-clock"></i> Microsoft OAuth Token Policy</strong>
                        <p style="margin-top: 10px;">
                            <strong>Good News:</strong> Microsoft refresh tokens are generally <strong>long-lived</strong> and don't have the same 7-day expiry issue as Google's testing apps.
                        </p>
                        <p><strong>Token Validity:</strong></p>
                        <ul style="margin-bottom: 0;">
                            <li><strong>Personal Accounts:</strong> Refresh tokens remain valid until explicitly revoked or after extended inactivity (90+ days)</li>
                            <li><strong>Microsoft 365 Business:</strong> Tokens are long-lived by default. Admin policies may enforce session timeouts.</li>
                            <li><strong>Conditional Access:</strong> If your organization uses Conditional Access policies, re-authentication may be required based on admin settings.</li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong><i class="fa fa-info-circle"></i> Outlook OAuth Setup:</strong>
                        <ol style="margin-top: 10px; margin-bottom: 0;">
                            <li>Go to <a href="https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank"><strong>Azure Portal - App Registrations</strong></a></li>
                            <li>Click <strong>"New registration"</strong></li>
                            <li>Name: <code>Perfex Mailbox</code> | Account type: <strong>All directories + personal</strong></li>
                            <li>Add redirect URI (Web): <code><?php echo admin_url('mailbox/oauth/callback?provider=outlook'); ?></code></li>
                            <li>Copy the <strong>Application (client) ID</strong></li>
                            <li>Go to <strong>"Certificates & secrets"</strong> → Create secret → Copy the <strong>Value</strong></li>
                            <li>Go to <strong>"API permissions"</strong> → Add: <code>Mail.ReadWrite</code>, <code>Mail.Send</code>, <code>IMAP.AccessAsUser.All</code>, <code>offline_access</code></li>
                            <li><strong>Grant admin consent</strong> if available (ensures smooth operation)</li>
                            <li><strong>Save</strong> this config page</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- SECTION 3: Email Signature (Always Visible) -->
<hr style="margin: 30px 0;">
<div class="row">
    <div class="col-md-12">
        <h4 class="bold"><i class="fa fa-pencil"></i> <?php echo _l('mailbox_email_signature'); ?></h4>
        <div class="form-group">
            <?php $value = (isset($member) ? $member->mail_signature : 'Sent from Perfex'); ?>
            <?php echo render_textarea('mail_signature', '', $value, ['rows' => 6]); ?>
        </div>
    </div>
</div>

<!-- SECTION 4: Personal Preferences (Always Visible) -->
<hr style="margin: 30px 0;">
<div class="row">
    <div class="col-md-12">
        <h4 class="bold"><i class="fa fa-cog"></i> <?php echo _l('mailbox_personal_preferences'); ?></h4>
    </div>
    
    <!-- Import Only Unread Emails -->
    <div class="col-md-6">
        <div class="form-group">
            <label for="only_loop_on_unseen_emails" class="control-label clearfix">
                <?php echo _l('mailbox_import_only_unread'); ?>
            </label>
            <p class="text-muted">
                <small>
                    <strong><?php echo _l('mailbox_import_only_unread_description'); ?></strong><br>
                    • <strong>Yes (Recommended):</strong> Only fetch unread emails. Prevents duplicates and reduces server load.<br>
                    • <strong>No:</strong> Import all emails (including read ones). Use this only for initial setup or migration.
                </small>
            </p>
            <?php 
            $only_unseen = isset($member->only_loop_on_unseen_emails) ? $member->only_loop_on_unseen_emails : '1';
            ?>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="only_unseen_yes" name="only_loop_on_unseen_emails" value="1" <?php echo $only_unseen == '1' ? 'checked' : ''; ?>>
                <label for="only_unseen_yes"><?php echo _l('settings_yes'); ?> (Recommended)</label>
            </div>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="only_unseen_no" name="only_loop_on_unseen_emails" value="0" <?php echo $only_unseen == '0' ? 'checked' : ''; ?>>
                <label for="only_unseen_no"><?php echo _l('settings_no'); ?> (Import All)</label>
            </div>
        </div>
    </div>
    
    <!-- Enable HTML Email Rendering -->
    <div class="col-md-6">
        <div class="form-group">
            <label for="enable_html" class="control-label clearfix">
                <?php echo _l('mailbox_enable_html_rendering'); ?>
            </label>
            <p class="text-muted">
                <small>
                    Display emails with HTML formatting, images, and styling. Disable to show plain text only.
                </small>
            </p>
            <?php 
            $enable_html = isset($member->enable_html) ? $member->enable_html : '1';
            ?>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="enable_html_yes" name="enable_html" value="1" <?php echo $enable_html == '1' ? 'checked' : ''; ?>>
                <label for="enable_html_yes"><?php echo _l('settings_yes'); ?></label>
            </div>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="enable_html_no" name="enable_html" value="0" <?php echo $enable_html == '0' ? 'checked' : ''; ?>>
                <label for="enable_html_no"><?php echo _l('settings_no'); ?> (Plain Text Only)</label>
            </div>
        </div>
    </div>
    
    <!-- Sync Read Status to IMAP Server -->
    <div class="col-md-6">
        <div class="form-group">
            <label for="sync_read_status_to_server" class="control-label clearfix">
                Sync Read Status to Email Server
            </label>
            <p class="text-muted">
                <small>
                    <strong>Mark emails as read on your email server when you read them in Perfex.</strong><br>
                    • <strong>Disabled (Default):</strong> Keeps your email unread on Gmail/Outlook when you read in Perfex. Faster performance.<br>
                    • <strong>Enabled:</strong> Marks as read on Gmail/Outlook too. Slower, but keeps all your devices in sync.
                </small>
            </p>
            <?php 
            $sync_read = isset($member->sync_read_status_to_server) ? $member->sync_read_status_to_server : '0';
            ?>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="sync_read_no" name="sync_read_status_to_server" value="0" <?php echo $sync_read == '0' ? 'checked' : ''; ?>>
                <label for="sync_read_no"><?php echo _l('settings_no'); ?> (Default - Faster)</label>
            </div>
            <div class="radio radio-primary radio-inline">
                <input type="radio" id="sync_read_yes" name="sync_read_status_to_server" value="1" <?php echo $sync_read == '1' ? 'checked' : ''; ?>>
                <label for="sync_read_yes"><?php echo _l('settings_yes'); ?> (Slower, Keeps Gmail/Outlook Synced)</label>
            </div>
        </div>
    </div>
</div>

<!-- SECTION 5: IMAP Folder Mappings (Visible for ALL Auth Methods) -->
<div id="imap_folder_mappings_section">
    <hr style="margin: 30px 0;">
    <div class="row">
        <div class="col-md-12">
            <h4 class="bold"><i class="fa fa-folder"></i> <?php echo _l('mailbox_folder_mappings'); ?></h4>
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> 
                <?php echo _l('mailbox_folder_mappings_instructions'); ?>
            </div>
        </div>
        <div class="col-md-12">
            <?php 
            $folder_scan_value = isset($member->imap_folder_scan) && !empty($member->imap_folder_scan) ? $member->imap_folder_scan : 'INBOX'; 
            echo render_input('imap_folder_scan', 'leads_email_integration_folder', $folder_scan_value, 'text', [
                'placeholder' => 'INBOX'
            ]); 
            ?>
        </div>
        <div class="col-md-6">
            <?php 
            $sent_folder_value = isset($member->imap_sent_folder) && !empty($member->imap_sent_folder) ? $member->imap_sent_folder : ''; 
            echo render_input('imap_sent_folder', 'mailbox_imap_sent_folder', $sent_folder_value, 'text', [
                'placeholder' => 'Sent, Sent Items, INBOX/Sent'
            ]); 
            ?>
        </div>
        <div class="col-md-6">
            <?php 
            $draft_folder_value = isset($member->imap_draft_folder) && !empty($member->imap_draft_folder) ? $member->imap_draft_folder : ''; 
            echo render_input('imap_draft_folder', 'mailbox_imap_draft_folder', $draft_folder_value, 'text', [
                'placeholder' => 'Drafts, Draft, INBOX/Drafts'
            ]); 
            ?>
        </div>
        <div class="col-md-4">
            <?php 
            $trash_folder_value = isset($member->imap_trash_folder) && !empty($member->imap_trash_folder) ? $member->imap_trash_folder : ''; 
            echo render_input('imap_trash_folder', 'mailbox_imap_trash_folder', $trash_folder_value, 'text', [
                'placeholder' => 'Trash, Deleted Items'
            ]); 
            ?>
        </div>
        <div class="col-md-4">
            <?php 
            $spam_folder_value = isset($member->imap_spam_folder) && !empty($member->imap_spam_folder) ? $member->imap_spam_folder : ''; 
            echo render_input('imap_spam_folder', 'mailbox_imap_spam_folder', $spam_folder_value, 'text', [
                'placeholder' => 'Junk, Spam'
            ]); 
            ?>
        </div>
        <div class="col-md-4">
            <?php 
            $archive_folder_value = isset($member->imap_archive_folder) && !empty($member->imap_archive_folder) ? $member->imap_archive_folder : ''; 
            echo render_input('imap_archive_folder', 'mailbox_imap_archive_folder', $archive_folder_value, 'text', [
                'placeholder' => 'Archive'
            ]); 
            ?>
        </div>
    </div>
</div>

<!-- Save Button -->
<div class="row">
    <div class="col-md-12">
        <hr style="margin: 30px 0;">
        <button type="submit" id="mailbox-config-submit" autocomplete="off" data-loading-text="<?php echo _l('wait_text'); ?>" class="btn btn-primary btn-lg">    
            <i class="fa fa-save"></i> <?php echo _l('save'); ?>
        </button>
        
        <?php if (!empty($member->oauth_client_id) || !empty($member->oauth_client_secret)): ?>
            <button type="button" class="btn btn-danger btn-lg" onclick="clearOAuthCredentials()" style="margin-left: 10px;">
                <i class="fa fa-trash"></i> Clear OAuth Credentials
            </button>
        <?php endif; ?>
    </div>
</div>

<?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
function toggleAuthMethod() {
    var method = $('#auth_method').val();
    if (method === 'oauth2') {
        $('#password_auth_section').slideUp(300);
        $('#oauth_auth_section').slideDown(300);
    } else {
        $('#password_auth_section').slideDown(300);
        $('#oauth_auth_section').slideUp(300);
    }
}

function toggleOAuthProvider() {
    var provider = $('#oauth_provider_select').val();
    
    if (!provider) {
        $('#oauth_instructions, #gmail_oauth_section, #outlook_oauth_section').slideUp(300);
        // Disable all OAuth fields when no provider selected
        $('#gmail_oauth_section input, #outlook_oauth_section input').prop('disabled', true);
        return;
    }
    
    $('#oauth_instructions').slideDown(300);
    $('#gmail_oauth_section, #outlook_oauth_section').hide();
    
    if (provider === 'gmail') {
        // Enable Gmail fields, disable Outlook fields
        $('#gmail_oauth_section input').prop('disabled', false);
        $('#outlook_oauth_section input').prop('disabled', true);
        $('#gmail_oauth_section').slideDown(300);
    } else if (provider === 'outlook') {
        // Enable Outlook fields, disable Gmail fields
        $('#outlook_oauth_section input').prop('disabled', false);
        $('#gmail_oauth_section input').prop('disabled', true);
        $('#outlook_oauth_section').slideDown(300);
    }
}

// Auto-update port when encryption changes
$('#imap_encryption').on('change', function() {
    var encryption = $(this).val();
    var portField = $('input[name="imap_port"]');
    
    if (encryption === 'ssl' && (portField.val() == '' || portField.val() == '143')) {
        portField.val('993');
    } else if (encryption === 'tls' && (portField.val() == '' || portField.val() == '993')) {
        portField.val('143');
    }
});

function disconnectOAuth() {
    if (!confirm('Are you sure you want to disconnect your OAuth account?')) {
        return;
    }
    
    $.post('<?php echo admin_url('mailbox/oauth/disconnect'); ?>', function(response) {
        alert_float('success', 'OAuth account disconnected successfully');
        setTimeout(function() {
            window.location.reload();
        }, 500);
    }).fail(function() {
        alert_float('danger', 'Failed to disconnect OAuth account');
    });
}

function showPassword(inputName) {
    var input = $('input[name="' + inputName + '"]');
    var icon = $('a[href="#' + inputName + '"] i');
    
    if (input.attr('type') === 'password') {
        input.attr('type', 'text');
        icon.removeClass('fa-eye').addClass('fa-eye-slash');
    } else {
        input.attr('type', 'password');
        icon.removeClass('fa-eye-slash').addClass('fa-eye');
    }
    return false;
}

function clearOAuthCredentials() {
    if (!confirm('Are you sure you want to clear your OAuth credentials? You will need to re-enter them to use OAuth authentication.')) {
        return;
    }
    
    $.post('<?php echo admin_url("mailbox/clear_oauth_credentials"); ?>', function(response) {
        alert_float('success', 'OAuth credentials cleared successfully');
        setTimeout(function() {
            window.location.reload();
        }, 500);
    }).fail(function() {
        alert_float('danger', 'Failed to clear OAuth credentials');
    });
}

// Fix form submission issue
$(document).ready(function() {
    var $form = $('#mailbox-config-form');
    var $submitBtn = $('#mailbox-config-submit');
    var isSubmitting = false;
    
    console.log('[MAILBOX] Form initialized');
    
    // Intercept form submit to add debugging
    $form.on('submit', function(e) {
        console.log('[MAILBOX] Form submit event triggered');
        console.log('[MAILBOX] Form action:', $form.attr('action'));
        console.log('[MAILBOX] Form method:', $form.attr('method'));
        
        if (isSubmitting) {
            console.log('[MAILBOX] Already submitting, preventing double submit');
            e.preventDefault();
            return false;
        }
        
        // Ensure only the visible OAuth section's fields are enabled
        var authMethod = $('#auth_method').val();
        if (authMethod === 'oauth2') {
            var selectedProvider = $('#oauth_provider_select').val();
            console.log('[MAILBOX] OAuth mode, selected provider:', selectedProvider);
            
            if (selectedProvider === 'gmail') {
                $('#gmail_oauth_section input').prop('disabled', false);
                $('#outlook_oauth_section input').prop('disabled', true);
                console.log('[MAILBOX] Gmail fields enabled, Outlook disabled');
            } else if (selectedProvider === 'outlook') {
                $('#outlook_oauth_section input').prop('disabled', false);
                $('#gmail_oauth_section input').prop('disabled', true);
                console.log('[MAILBOX] Outlook fields enabled, Gmail disabled');
            }
        }
        
        // Check form validity (HTML5 validation)
        if ($form[0].checkValidity && !$form[0].checkValidity()) {
            console.log('[MAILBOX] Form validation failed!');
            console.log('[MAILBOX] Invalid fields:', $form.find(':invalid').map(function() {
                return this.name + ' (' + this.validationMessage + ')';
            }).get());
            return true; // Let browser show validation messages
        }
        
        isSubmitting = true;
        
        // Disable button and show loading text
        $submitBtn.prop('disabled', true);
        var loadingText = $submitBtn.data('loading-text') || 'Please Wait...';
        $submitBtn.html('<i class="fa fa-spinner fa-spin"></i> ' + loadingText);
        
        console.log('[MAILBOX] Form is valid, submitting...');
        console.log('[MAILBOX] OAuth Client ID field value:', $('input[name="oauth_client_id"]:enabled').val());
        console.log('[MAILBOX] OAuth Client Secret field value:', $('input[name="oauth_client_secret"]:enabled').val());
        
        // Force submit after a small delay to ensure all handlers run
        setTimeout(function() {
            console.log('[MAILBOX] Executing native submit');
            $form[0].submit();
        }, 100);
        
        // Prevent the default to allow our delayed submit
        e.preventDefault();
        return false;
    });
    
    // Debug button click
    $submitBtn.on('click', function(e) {
        console.log('[MAILBOX] Submit button clicked');
    });
    
    console.log('[MAILBOX] Selectpicker count: ' + $('.selectpicker').length);
});
</script>
