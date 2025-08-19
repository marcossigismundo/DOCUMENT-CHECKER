<?php
/**
 * Admin UI Controller Class
 *
 * @package TainacanDocumentChecker
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI controller for menu registration and settings.
 *
 * @since 1.0.0
 */
class TCD_Admin {

	/**
	 * Current admin tab.
	 *
	 * @var string
	 */
	private string $current_tab;

	/**
	 * Email handler instance.
	 *
	 * @var TCD_Email_Handler
	 */
	private TCD_Email_Handler $email_handler;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->email_handler = new TCD_Email_Handler();
	}

	/**
	 * Initialize admin functionality.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_tcd_test_smtp', array( $this, 'handle_smtp_test' ) );
	}

	/**
	 * Register admin menu.
	 *
	 * @return void
	 */
	public function register_admin_menu(): void {
		add_menu_page(
			__( 'Tainacan Document Checker', 'tainacan-document-checker' ),
			__( 'Document Checker', 'tainacan-document-checker' ),
			'manage_options',
			'tainacan-document-checker',
			array( $this, 'render_admin_page' ),
			'dashicons-media-document',
			80
		);
	}

	/**
	 * Enqueue admin styles and scripts.
	 * 
	 * @param string $hook_suffix The current admin page.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ): void {
		// Only load on our plugin pages
		if ( 'toplevel_page_tainacan-document-checker' !== $hook_suffix ) {
			return;
		}

		// Enqueue styles
		wp_enqueue_style(
			'tcd-admin',
			TCD_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			TCD_VERSION
		);

		// Enqueue scripts
		wp_enqueue_script(
			'tcd-admin',
			TCD_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			TCD_VERSION,
			true
		);

		// Localize script with AJAX data
		wp_localize_script(
			'tcd-admin',
			'tcd_ajax',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'tcd_ajax_nonce' ),
				'strings'  => array(
					'checking'           => __( 'Checking...', 'tainacan-document-checker' ),
					'check_complete'     => __( 'Check complete', 'tainacan-document-checker' ),
					'error'              => __( 'An error occurred', 'tainacan-document-checker' ),
					'item_required'      => __( 'Please enter an item ID', 'tainacan-document-checker' ),
					'collection_required'=> __( 'Please enter a collection ID', 'tainacan-document-checker' ),
					'cache_cleared'      => __( 'Cache cleared successfully', 'tainacan-document-checker' ),
					'email_sent'         => __( 'Email notification sent successfully', 'tainacan-document-checker' ),
					'check_documents'    => __( 'Check Documents', 'tainacan-document-checker' ),
					'document_name'      => __( 'Document name', 'tainacan-document-checker' ),
				),
				'debug' => (bool) get_option( 'tcd_debug_mode', false ),
			)
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		// API Settings.
		register_setting(
			'tcd_settings',
			'tcd_api_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => get_site_url() . '/wp-json/tainacan/v2',
			)
		);

		register_setting(
			'tcd_settings',
			'tcd_collection_id',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			)
		);

		// Document Settings.
		register_setting(
			'tcd_settings',
			'tcd_required_documents',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_documents_list' ),
				'default'           => array(
					'comprovante_endereco',
					'documento_identidade',
					'documento_responsavel',
				),
			)
		);

		// General Settings.
		register_setting(
			'tcd_settings',
			'tcd_debug_mode',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		register_setting(
			'tcd_settings',
			'tcd_cache_duration',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 300,
			)
		);

		register_setting(
			'tcd_settings',
			'tcd_auto_clear_cache',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);

		// Email Settings
		$this->register_email_settings();
	}

	/**
	 * Register email-specific settings.
	 *
	 * @return void
	 */
	private function register_email_settings(): void {
		// Email General Settings
		register_setting(
			'tcd_email_settings',
			'tcd_email_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		register_setting(
			'tcd_email_settings',
			'tcd_email_html',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		// SMTP Settings
		register_setting(
			'tcd_email_settings',
			'tcd_smtp_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		register_setting(
			'tcd_email_settings',
			'tcd_smtp_host',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			'tcd_email_settings',
			'tcd_smtp_port',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 587,
			)
		);

		register_setting(
			'tcd_email_settings',
			'tcd_smtp_encryption',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_smtp_encryption' ),
				'default'           => 'tls',
			)
		);

		register_setting(
			'tcd_email_settings',
			'tcd_smtp_auth',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);

		register_setting(
			'tcd_email_settings',
			'tcd_smtp_username',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			'tcd_email_settings',
			'tcd_smtp_password',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_smtp_password' ),
				'default'           => '',
			)
		);

		register_setting(
			'tcd_email_settings',
			'tcd_smtp_from_email',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
				'default'           => get_option( 'admin_email' ),
			)
		);

		register_setting(
			'tcd_email_settings',
			'tcd_smtp_from_name',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => get_bloginfo( 'name' ),
			)
		);

		// Email Templates
		register_setting(
			'tcd_email_settings',
			'tcd_email_subject',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => __( 'Document Verification Required - {item_title}', 'tainacan-document-checker' ),
			)
		);

		register_setting(
			'tcd_email_settings',
			'tcd_email_template',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
				'default'           => $this->get_default_email_template(),
			)
		);

		register_setting(
			'tcd_email_settings',
			'tcd_batch_email_subject',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => __( 'Multiple Documents Require Verification', 'tainacan-document-checker' ),
			)
		);

		register_setting(
			'tcd_email_settings',
			'tcd_batch_email_template',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
				'default'           => $this->get_default_batch_email_template(),
			)
		);
	}

	/**
	 * Sanitize SMTP encryption value.
	 *
	 * @param string $value Input value.
	 * @return string Sanitized value.
	 */
	public function sanitize_smtp_encryption( $value ): string {
		$valid = array( '', 'ssl', 'tls' );
		return in_array( $value, $valid, true ) ? $value : 'tls';
	}

	/**
	 * Sanitize SMTP password.
	 *
	 * @param string $value Input value.
	 * @return string Sanitized value.
	 */
	public function sanitize_smtp_password( $value ): string {
		// If empty, keep the existing password
		if ( empty( $value ) || '••••••••' === $value ) {
			return get_option( 'tcd_smtp_password', '' );
		}
		return $value;
	}

	/**
	 * Get default email template.
	 *
	 * @return string Default template.
	 */
	private function get_default_email_template(): string {
		return __( "Hello {user_name},

This is a notification that the following item requires document verification:

Item: {item_title}
Item ID: {item_id}
Item URL: {item_url}

Missing Documents:
{missing_documents}

Invalid Documents:
{invalid_documents}

Please log in to your account and upload the required documents.

If you have any questions, please contact our support team.

Best regards,
{site_name}
{site_url}", 'tainacan-document-checker' );
	}

	/**
	 * Get default batch email template.
	 *
	 * @return string Default batch template.
	 */
	private function get_default_batch_email_template(): string {
		return __( "Hello {user_name},

You have {total_items} items that require document verification:

{items_list}

Please log in to your account and upload the required documents.

If you have any questions, please contact our support team.

Best regards,
{site_name}
{site_url}", 'tainacan-document-checker' );
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
		foreach ( $input as $doc ) {
			$doc = sanitize_file_name( trim( $doc ) );
			if ( ! empty( $doc ) ) {
				$sanitized[] = $doc;
			}
		}

		return array_unique( $sanitized );
	}

	/**
	 * Handle SMTP test AJAX request.
	 *
	 * @return void
	 */
	public function handle_smtp_test(): void {
		// Verify nonce.
		if ( ! check_ajax_referer( 'tcd_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'tainacan-document-checker' ) );
		}

		// Check capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'tainacan-document-checker' ) );
		}

		$test_email = isset( $_POST['test_email'] ) ? sanitize_email( $_POST['test_email'] ) : '';
		
		if ( empty( $test_email ) ) {
			wp_send_json_error( __( 'Please provide a valid email address.', 'tainacan-document-checker' ) );
		}

		$result = $this->email_handler->test_smtp_configuration( $test_email );
		
		if ( $result['success'] ) {
			wp_send_json_success( $result['message'] );
		} else {
			wp_send_json_error( $result['message'] );
		}
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

		$this->current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'single'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		include TCD_PLUGIN_DIR . 'admin/admin-page.php';
	}

	/**
	 * Get available admin tabs.
	 *
	 * @return array Tab configuration.
	 */
	public function get_admin_tabs(): array {
		return array(
			'single'    => __( 'Single Check', 'tainacan-document-checker' ),
			'batch'     => __( 'Batch Check', 'tainacan-document-checker' ),
			'history'   => __( 'History', 'tainacan-document-checker' ),
			'settings'  => __( 'Settings', 'tainacan-document-checker' ),
			'documents' => __( 'Manage Document Names', 'tainacan-document-checker' ),
			'email'     => __( 'Email Configuration', 'tainacan-document-checker' ),
		);
	}

	/**
	 * Render tab navigation.
	 *
	 * @return void
	 */
	public function render_tab_navigation(): void {
		$tabs = $this->get_admin_tabs();
		?>
		<nav class="nav-tab-wrapper">
			<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_key ) ); ?>" 
				   class="nav-tab <?php echo esc_attr( $this->current_tab === $tab_key ? 'nav-tab-active' : '' ); ?>">
					<?php echo esc_html( $tab_label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Render tab content.
	 *
	 * @return void
	 */
	public function render_tab_content(): void {
		switch ( $this->current_tab ) {
			case 'single':
				$this->render_single_check_tab();
				break;
			case 'batch':
				$this->render_batch_check_tab();
				break;
			case 'history':
				$this->render_history_tab();
				break;
			case 'settings':
				$this->render_settings_tab();
				break;
			case 'documents':
				$this->render_documents_tab();
				break;
			case 'email':
				$this->render_email_tab();
				break;
			default:
				$this->render_single_check_tab();
		}
	}

	/**
	 * Render single check tab.
	 *
	 * @return void
	 */
	private function render_single_check_tab(): void {
		$email_enabled = get_option( 'tcd_email_enabled', false );
		?>
		<div class="tcd-tab-content tcd-single-check">
			<h2><?php esc_html_e( 'Single Item Document Check', 'tainacan-document-checker' ); ?></h2>
			
			<form id="tcd-single-check-form" class="tcd-form">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="tcd-item-id"><?php esc_html_e( 'Item ID', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="number" id="tcd-item-id" name="item_id" class="regular-text" required>
							<p class="description"><?php esc_html_e( 'Enter the ID of the Tainacan item to check.', 'tainacan-document-checker' ); ?></p>
						</td>
					</tr>
					<?php if ( $email_enabled ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Email Notification', 'tainacan-document-checker' ); ?></th>
						<td>
							<label for="tcd-send-email-single">
								<input type="checkbox" id="tcd-send-email-single" name="send_email" value="1">
								<?php esc_html_e( 'Send email notification if documents are missing/invalid', 'tainacan-document-checker' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Email will be sent to the user associated with this item.', 'tainacan-document-checker' ); ?></p>
						</td>
					</tr>
					<?php endif; ?>
				</table>
				
				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Check Documents', 'tainacan-document-checker' ); ?>
					</button>
				</p>
			</form>
			
			<div id="tcd-single-result" class="tcd-result-container" style="display: none;"></div>
		</div>
		<?php
	}

	/**
	 * Render batch check tab.
	 *
	 * @return void
	 */
	private function render_batch_check_tab(): void {
		$collection_id = get_option( 'tcd_collection_id', 0 );
		$cache_duration = get_option( 'tcd_cache_duration', 300 );
		$email_enabled = get_option( 'tcd_email_enabled', false );
		?>
		<div class="tcd-tab-content tcd-batch-check">
			<h2><?php esc_html_e( 'Batch Document Check', 'tainacan-document-checker' ); ?></h2>
			
			<?php if ( $cache_duration > 0 ) : ?>
				<div class="notice notice-info">
					<p>
						<?php
						printf(
							/* translators: %d: cache duration in seconds */
							esc_html__( 'Results are cached for %d seconds. Clear cache to see new items immediately.', 'tainacan-document-checker' ),
							$cache_duration
						);
						?>
						<button type="button" id="tcd-clear-cache-btn" class="button button-small" style="margin-left: 10px;">
							<?php esc_html_e( 'Clear Cache Now', 'tainacan-document-checker' ); ?>
						</button>
					</p>
				</div>
			<?php endif; ?>
			
			<form id="tcd-batch-check-form" class="tcd-form">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="tcd-collection-id"><?php esc_html_e( 'Collection ID', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="number" id="tcd-collection-id" name="collection_id" 
							       value="<?php echo esc_attr( $collection_id ); ?>" class="regular-text" required>
							<p class="description"><?php esc_html_e( 'Enter the ID of the Tainacan collection to check.', 'tainacan-document-checker' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="tcd-per-page"><?php esc_html_e( 'Items per page', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="number" id="tcd-per-page" name="per_page" value="20" min="1" max="100" class="small-text">
							<p class="description"><?php esc_html_e( 'Number of items to process per batch (1-100).', 'tainacan-document-checker' ); ?></p>
						</td>
					</tr>
					<?php if ( $email_enabled ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Email Notifications', 'tainacan-document-checker' ); ?></th>
						<td>
							<label for="tcd-send-email-batch">
								<input type="checkbox" id="tcd-send-email-batch" name="send_email" value="1">
								<?php esc_html_e( 'Send email notifications for items with missing/invalid documents', 'tainacan-document-checker' ); ?>
							</label>
						</td>
					</tr>
					<?php endif; ?>
				</table>
				
				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Start Batch Check', 'tainacan-document-checker' ); ?>
					</button>
				</p>
			</form>
			
			<div id="tcd-batch-progress" style="display: none;">
				<div class="tcd-progress-bar" style="width: 0%;">
					<span class="tcd-progress-text">0%</span>
				</div>
			</div>
			
			<div id="tcd-batch-result" class="tcd-result-container" style="display: none;"></div>
		</div>
		<?php
	}

	/**
	 * Render history tab.
	 *
	 * @return void
	 */
	private function render_history_tab(): void {
		$document_checker = new TCD_Document_Checker();
		$history = $document_checker->get_all_history( 100 );
		?>
		<div class="tcd-tab-content tcd-history">
			<h2><?php esc_html_e( 'Check History', 'tainacan-document-checker' ); ?></h2>
			
			<?php if ( empty( $history ) ) : ?>
				<p><?php esc_html_e( 'No document checks have been performed yet.', 'tainacan-document-checker' ); ?></p>
			<?php else : ?>
				<table class="widefat tcd-history-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'tainacan-document-checker' ); ?></th>
							<th><?php esc_html_e( 'Item ID', 'tainacan-document-checker' ); ?></th>
							<th><?php esc_html_e( 'Status', 'tainacan-document-checker' ); ?></th>
							<th><?php esc_html_e( 'Missing Documents', 'tainacan-document-checker' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $history as $record ) : ?>
							<tr>
								<td><?php echo esc_html( $record['check_date'] ); ?></td>
								<td><?php echo esc_html( $record['item_id'] ); ?></td>
								<td>
									<span class="tcd-status-<?php echo esc_attr( $record['status'] ); ?>">
										<?php echo esc_html( ucfirst( $record['status'] ) ); ?>
									</span>
								</td>
								<td>
									<?php
									if ( ! empty( $record['missing_documents'] ) ) {
										echo esc_html( implode( ', ', $record['missing_documents'] ) );
									} else {
										echo '—';
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render settings tab.
	 *
	 * @return void
	 */
	private function render_settings_tab(): void {
		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error(
				'tcd_messages',
				'tcd_message',
				__( 'Settings saved successfully!', 'tainacan-document-checker' ),
				'updated'
			);
		}
		
		settings_errors( 'tcd_messages' );
		?>
		<div class="tcd-tab-content tcd-settings">
			<h2><?php esc_html_e( 'Plugin Settings', 'tainacan-document-checker' ); ?></h2>
			
			<form method="post" action="options.php">
				<?php settings_fields( 'tcd_settings' ); ?>
				
				<h3><?php esc_html_e( 'API Configuration', 'tainacan-document-checker' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="tcd_api_url"><?php esc_html_e( 'Tainacan API URL', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="url" id="tcd_api_url" name="tcd_api_url" 
							       value="<?php echo esc_attr( get_option( 'tcd_api_url' ) ); ?>" class="large-text">
							<p class="description"><?php esc_html_e( 'Base URL for Tainacan REST API (e.g., https://yoursite.com/wp-json/tainacan/v2)', 'tainacan-document-checker' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="tcd_collection_id"><?php esc_html_e( 'Default Collection ID', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="number" id="tcd_collection_id" name="tcd_collection_id" 
							       value="<?php echo esc_attr( get_option( 'tcd_collection_id', 0 ) ); ?>" class="small-text">
							<p class="description"><?php esc_html_e( 'Default collection ID for batch checks.', 'tainacan-document-checker' ); ?></p>
						</td>
					</tr>
				</table>
				
				<h3><?php esc_html_e( 'General Settings', 'tainacan-document-checker' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="tcd_cache_duration"><?php esc_html_e( 'Cache Duration', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="number" id="tcd_cache_duration" name="tcd_cache_duration" 
							       value="<?php echo esc_attr( get_option( 'tcd_cache_duration', 300 ) ); ?>" 
							       min="0" max="3600" class="small-text">
							<span><?php esc_html_e( 'seconds', 'tainacan-document-checker' ); ?></span>
							<p class="description"><?php esc_html_e( 'How long to cache batch check results (0-3600 seconds).', 'tainacan-document-checker' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Debug Mode', 'tainacan-document-checker' ); ?></th>
						<td>
							<label for="tcd_debug_mode">
								<input type="checkbox" id="tcd_debug_mode" name="tcd_debug_mode" value="1" 
								       <?php checked( get_option( 'tcd_debug_mode', false ) ); ?>>
								<?php esc_html_e( 'Enable debug mode', 'tainacan-document-checker' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Display raw attachment data and API URLs for troubleshooting.', 'tainacan-document-checker' ); ?></p>
						</td>
					</tr>
				</table>
				
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render documents management tab.
	 *
	 * @return void
	 */
	private function render_documents_tab(): void {
		if ( isset( $_POST['tcd_required_documents'] ) && check_admin_referer( 'tcd_save_documents' ) ) {
			$documents = $this->sanitize_documents_list( $_POST['tcd_required_documents'] );
			update_option( 'tcd_required_documents', $documents );
			
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Document names saved successfully!', 'tainacan-document-checker' ) . '</p></div>';
		}
		
		$required_documents = get_option( 'tcd_required_documents', array() );
		?>
		<div class="tcd-tab-content tcd-documents">
			<h2><?php esc_html_e( 'Manage Required Document Names', 'tainacan-document-checker' ); ?></h2>
			
			<form method="post" action="">
				<?php wp_nonce_field( 'tcd_save_documents' ); ?>
				
				<p><?php esc_html_e( 'List of document names that must be attached to each item. These names will be matched against attachment filenames, titles, alt text, and captions.', 'tainacan-document-checker' ); ?></p>
				
				<div id="tcd-documents-list">
					<?php foreach ( $required_documents as $doc ) : ?>
						<div class="tcd-document-item">
							<span class="dashicons dashicons-move"></span>
							<input type="text" name="tcd_required_documents[]" value="<?php echo esc_attr( $doc ); ?>" placeholder="<?php esc_attr_e( 'Document name', 'tainacan-document-checker' ); ?>" required>
							<button type="button" class="button tcd-remove-document">
								<span class="dashicons dashicons-no"></span>
							</button>
						</div>
					<?php endforeach; ?>
				</div>
				
				<p>
					<button type="button" id="tcd-add-document-btn" class="button">
						<span class="dashicons dashicons-plus"></span>
						<?php esc_html_e( 'Add Document', 'tainacan-document-checker' ); ?>
					</button>
				</p>
				
				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save Document Names', 'tainacan-document-checker' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render email configuration tab.
	 *
	 * @return void
	 */
	private function render_email_tab(): void {
		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error(
				'tcd_email_messages',
				'tcd_email_message',
				__( 'Email settings saved successfully!', 'tainacan-document-checker' ),
				'updated'
			);
		}
		
		settings_errors( 'tcd_email_messages' );
		
		// Get email statistics
		$email_stats = $this->email_handler->get_email_stats( 30 );
		?>
		<div class="tcd-tab-content tcd-email-settings">
			<h2><?php esc_html_e( 'Email Configuration', 'tainacan-document-checker' ); ?></h2>
			
			<!-- Email Statistics -->
			<div class="tcd-email-stats" style="background: #f9f9f9; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
				<h3><?php esc_html_e( 'Email Statistics (Last 30 Days)', 'tainacan-document-checker' ); ?></h3>
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
					<div>
						<strong><?php echo esc_html( $email_stats['total_emails'] ); ?></strong><br>
						<small><?php esc_html_e( 'Total Emails', 'tainacan-document-checker' ); ?></small>
					</div>
					<div>
						<strong style="color: #46b450;"><?php echo esc_html( $email_stats['sent_emails'] ); ?></strong><br>
						<small><?php esc_html_e( 'Sent Successfully', 'tainacan-document-checker' ); ?></small>
					</div>
					<div>
						<strong style="color: #d63638;"><?php echo esc_html( $email_stats['failed_emails'] ); ?></strong><br>
						<small><?php esc_html_e( 'Failed', 'tainacan-document-checker' ); ?></small>
					</div>
					<div>
						<strong><?php echo esc_html( $email_stats['single_emails'] ); ?></strong><br>
						<small><?php esc_html_e( 'Single Notifications', 'tainacan-document-checker' ); ?></small>
					</div>
					<div>
						<strong><?php echo esc_html( $email_stats['batch_emails'] ); ?></strong><br>
						<small><?php esc_html_e( 'Batch Notifications', 'tainacan-document-checker' ); ?></small>
					</div>
				</div>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'tcd_email_settings' ); ?>
				
				<!-- General Email Settings -->
				<h3><?php esc_html_e( 'General Email Settings', 'tainacan-document-checker' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Email Notifications', 'tainacan-document-checker' ); ?></th>
						<td>
							<label for="tcd_email_enabled">
								<input type="checkbox" id="tcd_email_enabled" name="tcd_email_enabled" value="1" 
								       <?php checked( get_option( 'tcd_email_enabled', false ) ); ?>>
								<?php esc_html_e( 'Send email notifications when documents are missing or invalid', 'tainacan-document-checker' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Email Format', 'tainacan-document-checker' ); ?></th>
						<td>
							<label for="tcd_email_html">
								<input type="checkbox" id="tcd_email_html" name="tcd_email_html" value="1" 
								       <?php checked( get_option( 'tcd_email_html', false ) ); ?>>
								<?php esc_html_e( 'Send HTML emails (unchecked = plain text)', 'tainacan-document-checker' ); ?>
							</label>
						</td>
					</tr>
				</table>
				
				<!-- SMTP Settings -->
				<h3><?php esc_html_e( 'SMTP Configuration', 'tainacan-document-checker' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable SMTP', 'tainacan-document-checker' ); ?></th>
						<td>
							<label for="tcd_smtp_enabled">
								<input type="checkbox" id="tcd_smtp_enabled" name="tcd_smtp_enabled" value="1" 
								       <?php checked( get_option( 'tcd_smtp_enabled', false ) ); ?>>
								<?php esc_html_e( 'Use SMTP for sending emails', 'tainacan-document-checker' ); ?>
							</label>
						</td>
					</tr>
					<tr class="tcd-smtp-setting">
						<th scope="row">
							<label for="tcd_smtp_host"><?php esc_html_e( 'SMTP Host', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="text" id="tcd_smtp_host" name="tcd_smtp_host" 
							       value="<?php echo esc_attr( get_option( 'tcd_smtp_host', '' ) ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'e.g., smtp.gmail.com', 'tainacan-document-checker' ); ?></p>
						</td>
					</tr>
					<tr class="tcd-smtp-setting">
						<th scope="row">
							<label for="tcd_smtp_port"><?php esc_html_e( 'SMTP Port', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="number" id="tcd_smtp_port" name="tcd_smtp_port" 
							       value="<?php echo esc_attr( get_option( 'tcd_smtp_port', 587 ) ); ?>" class="small-text">
							<p class="description"><?php esc_html_e( 'Common ports: 25, 465 (SSL), 587 (TLS)', 'tainacan-document-checker' ); ?></p>
						</td>
					</tr>
					<tr class="tcd-smtp-setting">
						<th scope="row">
							<label for="tcd_smtp_encryption"><?php esc_html_e( 'Encryption', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<select id="tcd_smtp_encryption" name="tcd_smtp_encryption">
								<option value="" <?php selected( get_option( 'tcd_smtp_encryption', 'tls' ), '' ); ?>><?php esc_html_e( 'None', 'tainacan-document-checker' ); ?></option>
								<option value="tls" <?php selected( get_option( 'tcd_smtp_encryption', 'tls' ), 'tls' ); ?>><?php esc_html_e( 'TLS', 'tainacan-document-checker' ); ?></option>
								<option value="ssl" <?php selected( get_option( 'tcd_smtp_encryption', 'tls' ), 'ssl' ); ?>><?php esc_html_e( 'SSL', 'tainacan-document-checker' ); ?></option>
							</select>
						</td>
					</tr>
					<tr class="tcd-smtp-setting">
						<th scope="row"><?php esc_html_e( 'SMTP Authentication', 'tainacan-document-checker' ); ?></th>
						<td>
							<label for="tcd_smtp_auth">
								<input type="checkbox" id="tcd_smtp_auth" name="tcd_smtp_auth" value="1" 
								       <?php checked( get_option( 'tcd_smtp_auth', true ) ); ?>>
								<?php esc_html_e( 'Use SMTP authentication', 'tainacan-document-checker' ); ?>
							</label>
						</td>
					</tr>
					<tr class="tcd-smtp-setting tcd-smtp-auth-setting">
						<th scope="row">
							<label for="tcd_smtp_username"><?php esc_html_e( 'SMTP Username', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="text" id="tcd_smtp_username" name="tcd_smtp_username" 
							       value="<?php echo esc_attr( get_option( 'tcd_smtp_username', '' ) ); ?>" class="regular-text">
						</td>
					</tr>
					<tr class="tcd-smtp-setting tcd-smtp-auth-setting">
						<th scope="row">
							<label for="tcd_smtp_password"><?php esc_html_e( 'SMTP Password', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="password" id="tcd_smtp_password" name="tcd_smtp_password" 
							       value="<?php echo esc_attr( get_option( 'tcd_smtp_password' ) ? '••••••••' : '' ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Leave blank to keep current password unchanged.', 'tainacan-document-checker' ); ?></p>
						</td>
					</tr>
					<tr class="tcd-smtp-setting">
						<th scope="row">
							<label for="tcd_smtp_from_email"><?php esc_html_e( 'From Email', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="email" id="tcd_smtp_from_email" name="tcd_smtp_from_email" 
							       value="<?php echo esc_attr( get_option( 'tcd_smtp_from_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text">
						</td>
					</tr>
					<tr class="tcd-smtp-setting">
						<th scope="row">
							<label for="tcd_smtp_from_name"><?php esc_html_e( 'From Name', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="text" id="tcd_smtp_from_name" name="tcd_smtp_from_name" 
							       value="<?php echo esc_attr( get_option( 'tcd_smtp_from_name', get_bloginfo( 'name' ) ) ); ?>" class="regular-text">
						</td>
					</tr>
				</table>
				
				<!-- SMTP Test -->
				<div class="tcd-smtp-test" style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; margin: 20px 0;">
					<h4><?php esc_html_e( 'Test SMTP Configuration', 'tainacan-document-checker' ); ?></h4>
					<p><?php esc_html_e( 'Send a test email to verify your SMTP settings are working correctly.', 'tainacan-document-checker' ); ?></p>
					<div style="display: flex; gap: 10px; align-items: center;">
						<input type="email" id="tcd_test_email" placeholder="<?php esc_attr_e( 'Enter test email address', 'tainacan-document-checker' ); ?>" class="regular-text">
						<button type="button" id="tcd-smtp-test-btn" class="button"><?php esc_html_e( 'Send Test Email', 'tainacan-document-checker' ); ?></button>
					</div>
					<div id="tcd-smtp-test-result" style="margin-top: 10px;"></div>
				</div>
				
				<!-- Email Templates -->
				<h3><?php esc_html_e( 'Email Templates', 'tainacan-document-checker' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Available placeholders:', 'tainacan-document-checker' ); ?>
					<code>{user_name}</code>, <code>{user_email}</code>, <code>{item_title}</code>, <code>{item_id}</code>, <code>{item_url}</code>, 
					<code>{missing_documents}</code>, <code>{invalid_documents}</code>, <code>{found_documents}</code>, 
					<code>{site_name}</code>, <code>{site_url}</code>, <code>{date}</code>, <code>{time}</code>
				</p>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="tcd_email_subject"><?php esc_html_e( 'Single Item Email Subject', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="text" id="tcd_email_subject" name="tcd_email_subject" 
							       value="<?php echo esc_attr( get_option( 'tcd_email_subject', __( 'Document Verification Required - {item_title}', 'tainacan-document-checker' ) ) ); ?>" 
							       class="large-text">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="tcd_email_template"><?php esc_html_e( 'Single Item Email Template', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<textarea id="tcd_email_template" name="tcd_email_template" rows="10" class="large-text"><?php echo esc_textarea( get_option( 'tcd_email_template', $this->get_default_email_template() ) ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="tcd_batch_email_subject"><?php esc_html_e( 'Batch Email Subject', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="text" id="tcd_batch_email_subject" name="tcd_batch_email_subject" 
							       value="<?php echo esc_attr( get_option( 'tcd_batch_email_subject', __( 'Multiple Documents Require Verification', 'tainacan-document-checker' ) ) ); ?>" 
							       class="large-text">
							<p class="description"><?php esc_html_e( 'Additional placeholders for batch emails: {total_items}, {items_list}', 'tainacan-document-checker' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="tcd_batch_email_template"><?php esc_html_e( 'Batch Email Template', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<textarea id="tcd_batch_email_template" name="tcd_batch_email_template" rows="10" class="large-text"><?php echo esc_textarea( get_option( 'tcd_batch_email_template', $this->get_default_batch_email_template() ) ); ?></textarea>
						</td>
					</tr>
				</table>
				
				<?php submit_button(); ?>
			</form>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			// Show/hide SMTP settings
			function toggleSmtpSettings() {
				if ($('#tcd_smtp_enabled').is(':checked')) {
					$('.tcd-smtp-setting').show();
				} else {
					$('.tcd-smtp-setting').hide();
				}
			}
			
			function toggleSmtpAuthSettings() {
				if ($('#tcd_smtp_auth').is(':checked')) {
					$('.tcd-smtp-auth-setting').show();
				} else {
					$('.tcd-smtp-auth-setting').hide();
				}
			}
			
			$('#tcd_smtp_enabled').change(toggleSmtpSettings);
			$('#tcd_smtp_auth').change(toggleSmtpAuthSettings);
			
			toggleSmtpSettings();
			toggleSmtpAuthSettings();
			
			// SMTP Test
			$('#tcd-smtp-test-btn').click(function() {
				var $btn = $(this);
				var $result = $('#tcd-smtp-test-result');
				var testEmail = $('#tcd_test_email').val();
				
				if (!testEmail) {
					$result.html('<div class="notice notice-error"><p><?php esc_html_e( 'Please enter a test email address.', 'tainacan-document-checker' ); ?></p></div>');
					return;
				}
				
				$btn.prop('disabled', true).text('<?php esc_html_e( 'Sending...', 'tainacan-document-checker' ); ?>');
				$result.html('');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'tcd_test_smtp',
						test_email: testEmail,
						nonce: '<?php echo wp_create_nonce( 'tcd_ajax_nonce' ); ?>'
					},
					success: function(response) {
						if (response.success) {
							$result.html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
						} else {
							$result.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
						}
					},
					error: function() {
						$result.html('<div class="notice notice-error"><p><?php esc_html_e( 'An error occurred while testing SMTP.', 'tainacan-document-checker' ); ?></p></div>');
					},
					complete: function() {
						$btn.prop('disabled', false).text('<?php esc_html_e( 'Send Test Email', 'tainacan-document-checker' ); ?>');
					}
				});
			});
		});
		</script>
		<?php
	}
}
