<?php
/**
 * Admin Interface Class
 *
 * @package TainacanDocumentChecker
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin interface and settings management.
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
	 * Document checker instance.
	 *
	 * @var TCD_Document_Checker
	 */
	private TCD_Document_Checker $document_checker;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->document_checker = new TCD_Document_Checker();
		$this->current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'single';
	}

	/**
	 * Initialize admin hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_tcd_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_tcd_save_documents', array( $this, 'save_documents' ) );
		
		// Add AJAX handler for connection test
		add_action( 'wp_ajax_tcd_test_api_connection', array( $this, 'test_api_connection' ) );
	}

	/**
	 * Add admin menu pages.
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
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( 'toplevel_page_tainacan-document-checker' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'tcd-admin',
			TCD_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			TCD_VERSION
		);

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
					'checking'       => __( 'Checking...', 'tainacan-document-checker' ),
					'check_complete' => __( 'Check complete', 'tainacan-document-checker' ),
					'error'          => __( 'An error occurred', 'tainacan-document-checker' ),
				),
			)
		);
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<?php $this->render_tabs(); ?>
			
			<div class="tcd-tab-content">
				<?php
				switch ( $this->current_tab ) {
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
					case 'single':
					default:
						$this->render_single_check_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render navigation tabs.
	 *
	 * @return void
	 */
	private function render_tabs(): void {
		$tabs = array(
			'single'    => __( 'Single Check', 'tainacan-document-checker' ),
			'batch'     => __( 'Batch Check', 'tainacan-document-checker' ),
			'history'   => __( 'History', 'tainacan-document-checker' ),
			'settings'  => __( 'Settings', 'tainacan-document-checker' ),
			'documents' => __( 'Manage Document Names', 'tainacan-document-checker' ),
		);

		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $tab => $label ) {
			$active = ( $this->current_tab === $tab ) ? ' nav-tab-active' : '';
			$url    = add_query_arg( 'tab', $tab, admin_url( 'admin.php?page=tainacan-document-checker' ) );
			printf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url( $url ),
				esc_attr( $active ),
				esc_html( $label )
			);
		}
		echo '</nav>';
	}

	/**
	 * Render single check tab.
	 *
	 * @return void
	 */
	private function render_single_check_tab(): void {
		?>
		<div class="tcd-single-check">
			<h2><?php esc_html_e( 'Single Item Document Check', 'tainacan-document-checker' ); ?></h2>
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="tcd_item_id"><?php esc_html_e( 'Item ID', 'tainacan-document-checker' ); ?></label>
					</th>
					<td>
						<input type="number" id="tcd_item_id" name="tcd_item_id" class="regular-text" min="1">
						<p class="description"><?php esc_html_e( 'Enter the ID of the Tainacan item to check.', 'tainacan-document-checker' ); ?></p>
					</td>
				</tr>
			</table>
			
			<p class="submit">
				<button type="button" id="tcd-check-single" class="button button-primary">
					<?php esc_html_e( 'Check Documents', 'tainacan-document-checker' ); ?>
				</button>
			</p>
			
			<div id="tcd-single-result" style="display:none;"></div>
		</div>
		<?php
	}

	/**
	 * Render batch check tab.
	 *
	 * @return void
	 */
	private function render_batch_check_tab(): void {
		?>
		<div class="tcd-batch-check">
			<h2><?php esc_html_e( 'Batch Document Check', 'tainacan-document-checker' ); ?></h2>
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="tcd_collection_id"><?php esc_html_e( 'Collection ID', 'tainacan-document-checker' ); ?></label>
					</th>
					<td>
						<input type="number" id="tcd_collection_id" name="tcd_collection_id" 
						       value="<?php echo esc_attr( get_option( 'tcd_default_collection_id', '' ) ); ?>" 
						       class="regular-text" min="1">
						<p class="description"><?php esc_html_e( 'Enter the ID of the Tainacan collection to check.', 'tainacan-document-checker' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="tcd_per_page"><?php esc_html_e( 'Items per page', 'tainacan-document-checker' ); ?></label>
					</th>
					<td>
						<input type="number" id="tcd_per_page" name="tcd_per_page" value="20" class="small-text" min="1" max="100">
						<p class="description"><?php esc_html_e( 'Number of items to process per batch (1-100).', 'tainacan-document-checker' ); ?></p>
					</td>
				</tr>
			</table>
			
			<p class="submit">
				<button type="button" id="tcd-check-batch" class="button button-primary">
					<?php esc_html_e( 'Start Batch Check', 'tainacan-document-checker' ); ?>
				</button>
			</p>
			
			<div id="tcd-batch-progress" style="display:none;">
				<h3><?php esc_html_e( 'Processing...', 'tainacan-document-checker' ); ?></h3>
				<progress id="tcd-progress-bar" max="100" value="0"></progress>
				<p id="tcd-progress-text"></p>
			</div>
			
			<div id="tcd-batch-result" style="display:none;"></div>
		</div>
		<?php
	}

	/**
	 * Render history tab.
	 *
	 * @return void
	 */
	private function render_history_tab(): void {
		$history = $this->document_checker->get_all_history( 100 );
		?>
		<div class="tcd-history">
			<h2><?php esc_html_e( 'Check History', 'tainacan-document-checker' ); ?></h2>
			
			<?php if ( empty( $history ) ) : ?>
				<p><?php esc_html_e( 'No document checks have been performed yet.', 'tainacan-document-checker' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
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
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Settings saved.', 'tainacan-document-checker' ); ?></p>
			</div>
			<?php
		}
		?>
		<div class="tcd-settings">
			<h2><?php esc_html_e( 'Plugin Settings', 'tainacan-document-checker' ); ?></h2>
			
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'tcd_save_settings', 'tcd_settings_nonce' ); ?>
				<input type="hidden" name="action" value="tcd_save_settings">
				
				<h3><?php esc_html_e( 'API Configuration', 'tainacan-document-checker' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="tcd_api_url"><?php esc_html_e( 'Tainacan API URL', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="url" id="tcd_api_url" name="tcd_api_url" 
							       value="<?php echo esc_attr( get_option( 'tcd_api_url', get_site_url() . '/wp-json/tainacan/v2' ) ); ?>" 
							       class="regular-text">
							<p class="description"><?php esc_html_e( 'Base URL for Tainacan REST API (e.g., https://yoursite.com/wp-json/tainacan/v2)', 'tainacan-document-checker' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="tcd_default_collection_id"><?php esc_html_e( 'Default Collection ID', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="number" id="tcd_default_collection_id" name="tcd_default_collection_id" 
							       value="<?php echo esc_attr( get_option( 'tcd_default_collection_id', '' ) ); ?>" 
							       class="regular-text" min="1">
							<p class="description"><?php esc_html_e( 'Default collection ID for batch checks.', 'tainacan-document-checker' ); ?></p>
						</td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'SSL/HTTPS Configuration', 'tainacan-document-checker' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="tcd_ssl_verify"><?php esc_html_e( 'SSL Verification', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" id="tcd_ssl_verify" name="tcd_ssl_verify" value="1" 
								       <?php checked( get_option( 'tcd_ssl_verify', true ) ); ?>>
								<?php esc_html_e( 'Enable SSL certificate verification', 'tainacan-document-checker' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Disable this option only for development/localhost environments. Keep enabled for production.', 'tainacan-document-checker' ); ?>
							</p>
							<?php 
							$api_url = get_option( 'tcd_api_url' );
							if ( strpos( $api_url, 'localhost' ) !== false || 
							     strpos( $api_url, '127.0.0.1' ) !== false ) : ?>
								<div class="notice notice-warning inline">
									<p>
										<strong><?php esc_html_e( 'Localhost Detected:', 'tainacan-document-checker' ); ?></strong>
										<?php esc_html_e( 'Your API URL appears to be pointing to localhost. Consider disabling SSL verification for this environment.', 'tainacan-document-checker' ); ?>
									</p>
								</div>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="tcd_http_timeout"><?php esc_html_e( 'HTTP Timeout', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="number" id="tcd_http_timeout" name="tcd_http_timeout" 
							       value="<?php echo esc_attr( get_option( 'tcd_http_timeout', 30 ) ); ?>" 
							       min="5" max="300" step="5" class="small-text">
							<span><?php esc_html_e( 'seconds', 'tainacan-document-checker' ); ?></span>
							<p class="description">
								<?php esc_html_e( 'Maximum time to wait for API responses (5-300 seconds).', 'tainacan-document-checker' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Connection Test', 'tainacan-document-checker' ); ?>
						</th>
						<td>
							<button type="button" id="tcd-test-connection" class="button button-secondary">
								<?php esc_html_e( 'Test API Connection', 'tainacan-document-checker' ); ?>
							</button>
							<span id="tcd-test-result" style="margin-left: 10px;"></span>
							<p class="description">
								<?php esc_html_e( 'Test the connection to the Tainacan API with current settings.', 'tainacan-document-checker' ); ?>
							</p>
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
							       class="small-text" min="0" max="3600">
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

				<h3><?php esc_html_e( 'System Information', 'tainacan-document-checker' ); ?></h3>
				<div class="tcd-system-info" style="background: #f0f0f1; padding: 10px; border-radius: 3px;">
					<p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
					<p><strong>cURL Version:</strong> <?php 
						if ( function_exists( 'curl_version' ) ) {
							$curl_version = curl_version();
							echo esc_html( $curl_version['version'] . ' / SSL: ' . $curl_version['ssl_version'] );
						} else {
							echo 'Not available';
						}
					?></p>
					<p><strong>WordPress Version:</strong> <?php echo get_bloginfo( 'version' ); ?></p>
					<p><strong>Site URL:</strong> <?php echo esc_url( get_site_url() ); ?></p>
					<p><strong>API URL:</strong> <?php echo esc_url( get_option( 'tcd_api_url' ) ); ?></p>
				</div>
				
				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" 
					       value="<?php esc_attr_e( 'Save Changes', 'tainacan-document-checker' ); ?>">
				</p>
			</form>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#tcd-test-connection').on('click', function() {
				var $button = $(this);
				var $result = $('#tcd-test-result');
				
				$button.prop('disabled', true);
				$result.html('<span class="spinner is-active" style="float: none;"></span> Testing...');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'tcd_test_api_connection',
						nonce: '<?php echo wp_create_nonce( 'tcd_test_connection' ); ?>'
					},
					success: function(response) {
						if (response.success) {
							$result.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
						} else {
							$result.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
						}
					},
					error: function() {
						$result.html('<span style="color: red;">✗ Connection test failed</span>');
					},
					complete: function() {
						$button.prop('disabled', false);
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Render documents management tab.
	 *
	 * @return void
	 */
	private function render_documents_tab(): void {
		$documents = get_option( 'tcd_required_documents', array() );
		?>
		<div class="tcd-documents">
			<h2><?php esc_html_e( 'Manage Required Document Names', 'tainacan-document-checker' ); ?></h2>
			
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'tcd_save_documents', 'tcd_documents_nonce' ); ?>
				<input type="hidden" name="action" value="tcd_save_documents">
				
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Required Documents', 'tainacan-document-checker' ); ?></th>
						<td>
							<div id="tcd-documents-list">
								<?php foreach ( $documents as $index => $document ) : ?>
									<div class="tcd-document-row" style="margin-bottom: 10px;">
										<input type="text" name="tcd_documents[]" 
										       value="<?php echo esc_attr( $document ); ?>" 
										       class="regular-text">
										<button type="button" class="button tcd-remove-document">
											<?php esc_html_e( 'Remove', 'tainacan-document-checker' ); ?>
										</button>
									</div>
								<?php endforeach; ?>
							</div>
							
							<button type="button" id="tcd-add-document" class="button">
								<?php esc_html_e( 'Add Document', 'tainacan-document-checker' ); ?>
							</button>
							
							<p class="description">
								<?php esc_html_e( 'List of document names that must be attached to each item. These names will be matched against attachment filenames.', 'tainacan-document-checker' ); ?>
							</p>
						</td>
					</tr>
				</table>
				
				<p class="submit">
					<input type="submit" name="submit" class="button button-primary" 
					       value="<?php esc_attr_e( 'Save Document Names', 'tainacan-document-checker' ); ?>">
				</p>
			</form>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			$('#tcd-add-document').on('click', function() {
				var newRow = '<div class="tcd-document-row" style="margin-bottom: 10px;">' +
							'<input type="text" name="tcd_documents[]" class="regular-text">' +
							'<button type="button" class="button tcd-remove-document">Remove</button>' +
							'</div>';
				$('#tcd-documents-list').append(newRow);
			});
			
			$(document).on('click', '.tcd-remove-document', function() {
				$(this).closest('.tcd-document-row').remove();
			});
		});
		</script>
		<?php
	}

	/**
	 * Save settings.
	 *
	 * @return void
	 */
	public function save_settings(): void {
		if ( ! isset( $_POST['tcd_settings_nonce'] ) || 
		     ! wp_verify_nonce( $_POST['tcd_settings_nonce'], 'tcd_save_settings' ) ) {
			wp_die( 'Security check failed' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		// API Settings
		update_option( 'tcd_api_url', esc_url_raw( $_POST['tcd_api_url'] ?? '' ) );
		update_option( 'tcd_default_collection_id', absint( $_POST['tcd_default_collection_id'] ?? 0 ) );
		
		// SSL Settings
		update_option( 'tcd_ssl_verify', isset( $_POST['tcd_ssl_verify'] ) );
		update_option( 'tcd_http_timeout', absint( $_POST['tcd_http_timeout'] ?? 30 ) );
		
		// General Settings
		update_option( 'tcd_cache_duration', absint( $_POST['tcd_cache_duration'] ?? 300 ) );
		update_option( 'tcd_debug_mode', isset( $_POST['tcd_debug_mode'] ) );

		wp_redirect( add_query_arg( 
			array( 
				'page' => 'tainacan-document-checker',
				'tab' => 'settings',
				'settings-updated' => 'true'
			), 
			admin_url( 'admin.php' ) 
		) );
		exit;
	}

	/**
	 * Save document names.
	 *
	 * @return void
	 */
	public function save_documents(): void {
		if ( ! isset( $_POST['tcd_documents_nonce'] ) || 
		     ! wp_verify_nonce( $_POST['tcd_documents_nonce'], 'tcd_save_documents' ) ) {
			wp_die( 'Security check failed' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		$documents = array();
		if ( isset( $_POST['tcd_documents'] ) && is_array( $_POST['tcd_documents'] ) ) {
			foreach ( $_POST['tcd_documents'] as $document ) {
				$document = sanitize_text_field( $document );
				if ( ! empty( $document ) ) {
					$documents[] = $document;
				}
			}
		}

		update_option( 'tcd_required_documents', $documents );

		wp_redirect( add_query_arg( 
			array( 
				'page' => 'tainacan-document-checker',
				'tab' => 'documents',
				'settings-updated' => 'true'
			), 
			admin_url( 'admin.php' ) 
		) );
		exit;
	}

	/**
	 * Test API connection (AJAX handler).
	 *
	 * @return void
	 */
	public function test_api_connection(): void {
		check_ajax_referer( 'tcd_test_connection', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'tainacan-document-checker' ) ) );
		}
		
		$api_url = get_option( 'tcd_api_url' );
		$ssl_verify = get_option( 'tcd_ssl_verify', true );
		$timeout = get_option( 'tcd_http_timeout', 30 );
		
		// Test the connection
		$test_url = $api_url . '/collections';
		
		$args = array(
			'timeout'     => $timeout,
			'redirection' => 5,
			'httpversion' => '1.1',
			'sslverify'   => $ssl_verify,
			'headers'     => array(
				'Accept' => 'application/json',
			),
		);
		
		$response = wp_remote_get( $test_url, $args );
		
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			
			// Provide specific guidance based on error
			if ( strpos( $error_message, 'SSL certificate problem' ) !== false ||
			     strpos( $error_message, 'cURL error 60' ) !== false ) {
				$error_message .= '. ' . __( 'Try disabling SSL verification in settings.', 'tainacan-document-checker' );
			}
			
			wp_send_json_error( array( 
				'message' => sprintf( __( 'Connection failed: %s', 'tainacan-document-checker' ), $error_message )
			) );
		}
		
		$status_code = wp_remote_retrieve_response_code( $response );
		
		if ( $status_code >= 200 && $status_code < 300 ) {
			wp_send_json_success( array( 
				'message' => sprintf( __( 'Connection successful! (HTTP %d)', 'tainacan-document-checker' ), $status_code )
			) );
		} else {
			wp_send_json_error( array( 
				'message' => sprintf( __( 'API returned HTTP %d', 'tainacan-document-checker' ), $status_code )
			) );
		}
	}
}
