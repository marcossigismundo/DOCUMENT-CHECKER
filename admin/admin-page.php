<?php
/**
 * Admin page template - Email Tab Section
 * Add this section to your admin-page.php file within the tabs
 */
?>

<!-- Email Settings Tab -->
<div class="tcd-tab-content tcd-email <?php echo $active_tab === 'email' ? 'active' : ''; ?>">
    <h2><?php esc_html_e( 'Email Settings', 'tainacan-document-checker' ); ?></h2>
    
    <form method="post" action="options.php">
        <?php settings_fields( 'tcd_email_settings' ); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="tcd_email_enabled"><?php esc_html_e( 'Enable Email Notifications', 'tainacan-document-checker' ); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="tcd_email_enabled" name="tcd_email_enabled" value="1" <?php checked( get_option( 'tcd_email_enabled', false ) ); ?> />
                    <p class="description"><?php esc_html_e( 'Send email notifications when documents are missing or invalid.', 'tainacan-document-checker' ); ?></p>
                </td>
            </tr>
            
            <tr class="tcd-email-settings">
                <th scope="row">
                    <label for="tcd_email_html"><?php esc_html_e( 'HTML Emails', 'tainacan-document-checker' ); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="tcd_email_html" name="tcd_email_html" value="1" <?php checked( get_option( 'tcd_email_html', false ) ); ?> />
                    <p class="description"><?php esc_html_e( 'Send emails in HTML format instead of plain text.', 'tainacan-document-checker' ); ?></p>
                </td>
            </tr>
            
            <tr class="tcd-email-settings">
                <th scope="row">
                    <label for="tcd_email_subject"><?php esc_html_e( 'Email Subject (Single)', 'tainacan-document-checker' ); ?></label>
                </th>
                <td>
                    <input type="text" id="tcd_email_subject" name="tcd_email_subject" value="<?php echo esc_attr( get_option( 'tcd_email_subject', 'Document Verification Required - {item_title}' ) ); ?>" class="large-text" />
                    <p class="description"><?php esc_html_e( 'Available placeholders: {item_title}, {item_id}, {site_name}', 'tainacan-document-checker' ); ?></p>
                </td>
            </tr>
            
            <tr class="tcd-email-settings">
                <th scope="row">
                    <label for="tcd_batch_email_subject"><?php esc_html_e( 'Email Subject (Batch)', 'tainacan-document-checker' ); ?></label>
                </th>
                <td>
                    <input type="text" id="tcd_batch_email_subject" name="tcd_batch_email_subject" value="<?php echo esc_attr( get_option( 'tcd_batch_email_subject', 'Documents Missing - {count} Items Need Attention' ) ); ?>" class="large-text" />
                    <p class="description"><?php esc_html_e( 'Available placeholders: {count}, {site_name}', 'tainacan-document-checker' ); ?></p>
                </td>
            </tr>
        </table>
        
        <h3><?php esc_html_e( 'SMTP Configuration', 'tainacan-document-checker' ); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="tcd_smtp_enabled"><?php esc_html_e( 'Enable SMTP', 'tainacan-document-checker' ); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="tcd_smtp_enabled" name="tcd_smtp_enabled" value="1" <?php checked( get_option( 'tcd_smtp_enabled', false ) ); ?> />
                    <p class="description"><?php esc_html_e( 'Use SMTP server instead of default PHP mail function.', 'tainacan-document-checker' ); ?></p>
                </td>
            </tr>
            
            <tr class="tcd-smtp-fields">
                <th scope="row">
                    <label for="tcd_smtp_host"><?php esc_html_e( 'SMTP Host', 'tainacan-document-checker' ); ?></label>
                </th>
                <td>
                    <input type="text" id="tcd_smtp_host" name="tcd_smtp_host" value="<?php echo esc_attr( get_option( 'tcd_smtp_host', '' ) ); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e( 'e.g., smtp.gmail.com', 'tainacan-document-checker' ); ?></p>
                </td>
            </tr>
            
            <tr class="tcd-smtp-fields">
                <th scope="row">
                    <label for="tcd_smtp_port"><?php esc_html_e( 'SMTP Port', 'tainacan-document-checker' ); ?></label>
                </th>
                <td>
                    <input type="number" id="tcd_smtp_port" name="tcd_smtp_port" value="<?php echo esc_attr( get_option( 'tcd_smtp_port', 587 ) ); ?>" class="small-text" />
                    <p class="description"><?php esc_html_e( 'Common ports: 25, 465 (SSL), 587 (TLS)', 'tainacan-document-checker' ); ?></p>
                </td>
            </tr>
            
            <tr class="tcd-smtp-fields">
                <th scope="row">
                    <label for="tcd_smtp_encryption"><?php esc_html_e( 'Encryption', 'tainacan-document-checker' ); ?></label>
                </th>
                <td>
                    <select id="tcd_smtp_encryption" name="tcd_smtp_encryption">
                        <option value="" <?php selected( get_option( 'tcd_smtp_encryption' ), '' ); ?>><?php esc_html_e( 'None', 'tainacan-document-checker' ); ?></option>
                        <option value="ssl" <?php selected( get_option( 'tcd_smtp_encryption' ), 'ssl' ); ?>>SSL</option>
                        <option value="tls" <?php selected( get_option( 'tcd_smtp_encryption' ), 'tls' ); ?>>TLS</option>
                    </select>
                </td>
            </tr>
            
            <tr class="tcd-smtp-fields">
                <th scope="row">
                    <label for="tcd_smtp_auth"><?php esc_html_e( 'SMTP Authentication', 'tainacan-document-checker' ); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="tcd_smtp_auth" name="tcd_smtp_auth" value="1" <?php checked( get_option( 'tcd_smtp_auth', true ) ); ?> />
                    <p class="description"><?php esc_html_e( 'Most SMTP servers require authentication.', 'tainacan-document-checker' ); ?></p>
                </td>
            </tr>
            
            <tr class="tcd-smtp-fields">
                <th scope="row">
                    <label for="tcd_smtp_username"><?php esc_html_e( 'SMTP Username', 'tainacan-document-checker' ); ?></label>
                </th>
                <td>
                    <input type="text" id="tcd_smtp_username" name="tcd_smtp_username" value="<?php echo esc_attr( get_option( 'tcd_smtp_username', '' ) ); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e( 'Your SMTP account username (often your email address).', 'tainacan-document-checker' ); ?></p>
                </td>
            </tr>
            
            <tr class="tcd-smtp-fields">
                <th scope="row">
                    <label for="tcd_smtp_password"><?php esc_html_e( 'SMTP Password', 'tainacan-document-checker' ); ?></label>
                </th>
                <td>
                    <input type="password" id="tcd_smtp_password" name="tcd_smtp_password" value="<?php echo esc_attr( get_option( 'tcd_smtp_password', '' ) ); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e( 'Your SMTP account password or app-specific password.', 'tainacan-document-checker' ); ?></p>
                </td>
            </tr>
            
            <tr class="tcd-smtp-fields">
                <th scope="row">
                    <label for="tcd_smtp_from_email"><?php esc_html_e( 'From Email', 'tainacan-document-checker' ); ?></label>
                </th>
                <td>
                    <input type="email" id="tcd_smtp_from_email" name="tcd_smtp_from_email" value="<?php echo esc_attr( get_option( 'tcd_smtp_from_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e( 'The email address that will appear as sender.', 'tainacan-document-checker' ); ?></p>
                </td>
            </tr>
            
            <tr class="tcd-smtp-fields">
                <th scope="row">
                    <label for="tcd_smtp_from_name"><?php esc_html_e( 'From Name', 'tainacan-document-checker' ); ?></label>
                </th>
                <td>
                    <input type="text" id="tcd_smtp_from_name" name="tcd_smtp_from_name" value="<?php echo esc_attr( get_option( 'tcd_smtp_from_name', get_bloginfo( 'name' ) ) ); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e( 'The name that will appear as sender.', 'tainacan-document-checker' ); ?></p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(); ?>
    </form>
    
    <hr />
    
    <h3><?php esc_html_e( 'Test SMTP Configuration', 'tainacan-document-checker' ); ?></h3>
    <p><?php esc_html_e( 'Send a test email to verify your SMTP settings are working correctly.', 'tainacan-document-checker' ); ?></p>
    
    <div class="tcd-test-email-form">
        <input type="email" id="tcd-test-email" placeholder="<?php esc_attr_e( 'Enter test email address', 'tainacan-document-checker' ); ?>" class="regular-text" />
        <button type="button" id="tcd-test-smtp" class="button button-secondary"><?php esc_html_e( 'Send Test Email', 'tainacan-document-checker' ); ?></button>
    </div>
    
    <hr />
    
    <h3><?php esc_html_e( 'Email Logs', 'tainacan-document-checker' ); ?></h3>
    <button type="button" id="tcd-view-email-logs" class="button button-secondary"><?php esc_html_e( 'View Email Logs', 'tainacan-document-checker' ); ?></button>
    <div id="tcd-email-logs" style="display:none; margin-top: 20px;"></div>
</div>
