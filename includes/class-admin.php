<?php
/**
 * Admin class - Email Settings Registration
 * Add this method to your TCD_Admin class
 */

/**
 * Register email settings.
 * Call this method inside the init() method of TCD_Admin class
 *
 * @return void
 */
public function register_email_settings(): void {
    // Register email settings section
    register_setting( 'tcd_email_settings', 'tcd_email_enabled', 'rest_sanitize_boolean' );
    register_setting( 'tcd_email_settings', 'tcd_email_html', 'rest_sanitize_boolean' );
    register_setting( 'tcd_email_settings', 'tcd_email_subject', 'sanitize_text_field' );
    register_setting( 'tcd_email_settings', 'tcd_batch_email_subject', 'sanitize_text_field' );
    
    // Register SMTP settings
    register_setting( 'tcd_email_settings', 'tcd_smtp_enabled', 'rest_sanitize_boolean' );
    register_setting( 'tcd_email_settings', 'tcd_smtp_host', 'sanitize_text_field' );
    register_setting( 'tcd_email_settings', 'tcd_smtp_port', 'absint' );
    register_setting( 'tcd_email_settings', 'tcd_smtp_encryption', function( $value ) {
        return in_array( $value, array( '', 'ssl', 'tls' ), true ) ? $value : 'tls';
    });
    register_setting( 'tcd_email_settings', 'tcd_smtp_auth', 'rest_sanitize_boolean' );
    register_setting( 'tcd_email_settings', 'tcd_smtp_username', 'sanitize_text_field' );
    register_setting( 'tcd_email_settings', 'tcd_smtp_password', function( $value ) {
        // Don't re-encrypt if it's already encrypted
        if ( empty( $value ) ) {
            return '';
        }
        // Simple obfuscation - in production, use proper encryption
        return base64_encode( $value );
    });
    register_setting( 'tcd_email_settings', 'tcd_smtp_from_email', 'sanitize_email' );
    register_setting( 'tcd_email_settings', 'tcd_smtp_from_name', 'sanitize_text_field' );
}

/**
 * Update the get_admin_tabs method to include Email tab
 *
 * @return array Admin tabs.
 */
public function get_admin_tabs(): array {
    return array(
        'single'    => __( 'Single Check', 'tainacan-document-checker' ),
        'batch'     => __( 'Batch Check', 'tainacan-document-checker' ),
        'history'   => __( 'History', 'tainacan-document-checker' ),
        'documents' => __( 'Documents', 'tainacan-document-checker' ),
        'email'     => __( 'Email', 'tainacan-document-checker' ),
        'settings'  => __( 'Settings', 'tainacan-document-checker' ),
    );
}

/**
 * Update the init method to call register_email_settings
 * Add this line inside the existing init() method:
 */
public function init(): void {
    add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
    add_action( 'admin_init', array( $this, 'register_settings' ) );
    add_action( 'admin_init', array( $this, 'register_email_settings' ) ); // Add this line
    add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
}
