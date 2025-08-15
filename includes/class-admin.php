<?php
/**
 * Admin functionality for Tainacan Document Checker
 *
 * @package TainacanDocumentChecker
 */

declare(strict_types=1);

/**
 * Admin class for the plugin.
 */
class TCD_Admin {

	/**
	 * Initialize admin functionality.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'register_email_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			__( 'Document Checker', 'tainacan-document-checker' ),
			__( 'Document Checker', 'tainacan-document-checker' ),
			'manage_options',
			'tainacan-document-checker',
			array( $this, 'render_admin_page' ),
			'dashicons-yes-alt',
			30
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting( 'tcd_settings', 'tcd_api_url', 'esc_url_raw' );
		register_setting( 'tcd_settings', 'tcd_collection_id', 'absint' );
		register_setting( 'tcd_settings', 'tcd_required_documents', array( $this, 'sanitize_documents_list' ) );
		register_setting( 'tcd_settings', 'tcd_debug_mode', 'rest_sanitize_boolean' );
		register_setting( 'tcd_settings', 'tcd_cache_duration', 'absint' );
	}

	/**
	 * Register email settings.
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
	 * Sanitize documents list.
	 *
	 * @param mixed $input Input value.
	 * @return array Sanitized documents list.
	 */
	public function sanitize_documents_list( $input ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $input as $document ) {
			$document = sanitize_text_field( $document );
			if ( ! empty( $document ) ) {
				$sanitized[] = $document;
			}
		}

		return array_unique( $sanitized );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( 'toplevel_page_tainacan-document-checker' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'tcd-admin',
			TCD_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			TCD_VERSION,
			true
		);

		wp_localize_script(
			'tcd-admin',
			'tcd_ajax',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'tcd_ajax_nonce' ),
				'strings'  => array(
					'checking'         => __( 'Checking...', 'tainacan-document-checker' ),
					'check_complete'   => __( 'Check complete', 'tainacan-document-checker' ),
					'error'           => __( 'An error occurred', 'tainacan-document-checker' ),
					'clearing_cache'  => __( 'Clearing cache...', 'tainacan-document-checker' ),
					'clear_cache'     => __( 'Clear Cache', 'tainacan-document-checker' ),
					'cache_cleared'   => __( 'Cache cleared successfully', 'tainacan-document-checker' ),
					'sending_emails'  => __( 'Sending emails...', 'tainacan-document-checker' ),
					'emails_sent'     => __( 'Email notifications sent', 'tainacan-document-checker' ),
				),
			)
		);

		wp_enqueue_style(
			'tcd-admin',
			TCD_PLUGIN_URL . 'assets/css/admin-style.css',
			array(),
			TCD_VERSION
		);
	}

	/**
	 * Get admin tabs.
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
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'tainacan-document-checker' ) );
		}

		include TCD_PLUGIN_DIR . 'admin/admin-page.php';
	}
}
