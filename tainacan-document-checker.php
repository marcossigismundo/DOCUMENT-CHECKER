<?php
/**
 * Plugin Name: Tainacan Document Checker
 * Plugin URI: https://github.com/your-org/tainacan-document-checker
 * Description: Verifies that required documents are attached to Tainacan collection items
 * Version: 1.0.1
 * Author: Your Name
 * Author URI: https://your-website.com
 * Text Domain: tainacan-document-checker
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package TainacanDocumentChecker
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'TCD_VERSION', '1.0.1' );
define( 'TCD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TCD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TCD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Include required files.
require_once TCD_PLUGIN_DIR . 'includes/class-document-checker.php';
require_once TCD_PLUGIN_DIR . 'includes/class-admin.php';
require_once TCD_PLUGIN_DIR . 'includes/class-ajax-handler.php';
require_once TCD_PLUGIN_DIR . 'includes/class-email-handler.php';

// Activation hook.
register_activation_hook( __FILE__, 'tcd_activate' );

/**
 * Plugin activation callback.
 *
 * @return void
 */
function tcd_activate(): void {
	tcd_create_tables();
	tcd_set_default_options();
	
	// Adicionar verificação de SSL
	tcd_check_ssl_support();
	
	// Flush rewrite rules
	flush_rewrite_rules();
}

/**
 * Check SSL support and configure accordingly
 *
 * @return void
 */
function tcd_check_ssl_support(): void {
	// Check if running on localhost
	$is_localhost = false;
	$site_url = get_site_url();
	
	if ( strpos( $site_url, 'localhost' ) !== false || 
	     strpos( $site_url, '127.0.0.1' ) !== false ||
	     strpos( $site_url, '::1' ) !== false ) {
		$is_localhost = true;
	}
	
	// Auto-configure SSL verification based on environment
	if ( $is_localhost && get_option( 'tcd_ssl_verify' ) === false ) {
		update_option( 'tcd_ssl_verify', false );
		
		// Log this change
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'TCD: SSL verification disabled for localhost environment' );
		}
	}
	
	// Check cURL availability
	if ( ! function_exists( 'curl_version' ) ) {
		// Add admin notice about missing cURL
		add_action( 'admin_notices', function() {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'Tainacan Document Checker:', 'tainacan-document-checker' ); ?></strong>
					<?php esc_html_e( 'cURL extension is not installed. This plugin requires cURL to communicate with the Tainacan API.', 'tainacan-document-checker' ); ?>
				</p>
			</div>
			<?php
		} );
	}
}

/**
 * Create database tables.
 *
 * @return void
 */
function tcd_create_tables(): void {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$table_name      = $wpdb->prefix . 'tainacan_document_checks';

	$sql = "CREATE TABLE $table_name (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		item_id bigint(20) unsigned NOT NULL,
		status varchar(20) NOT NULL,
		found_documents text,
		missing_documents text,
		check_date datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY item_id (item_id),
		KEY check_date (check_date),
		KEY status (status)
	) $charset_collate;";

	// Email tracking table
	$email_table = $wpdb->prefix . 'tainacan_document_emails';
	$email_sql = "CREATE TABLE $email_table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		item_id bigint(20) unsigned NOT NULL DEFAULT 0,
		email_type varchar(20) NOT NULL DEFAULT 'single',
		subject varchar(255) NOT NULL,
		status varchar(20) NOT NULL,
		sent_date datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY user_id (user_id),
		KEY item_id (item_id),
		KEY sent_date (sent_date),
		KEY status (status)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
	dbDelta( $email_sql );

	update_option( 'tcd_db_version', '1.1.0' );
}

/**
 * Set default plugin options.
 *
 * @return void
 */
function tcd_set_default_options(): void {
	$default_documents = array(
		'comprovante_endereco',
		'documento_identidade',
		'documento_responsavel',
	);

	add_option( 'tcd_required_documents', $default_documents );
	add_option( 'tcd_api_url', get_site_url() . '/wp-json/tainacan/v2' );
	add_option( 'tcd_debug_mode', false );
	add_option( 'tcd_cache_duration', 300 ); // 5 minutes
	
	// SSL and connection options
	add_option( 'tcd_ssl_verify', true );
	add_option( 'tcd_http_timeout', 30 );
	
	// Email default options
	add_option( 'tcd_email_enabled', false );
	add_option( 'tcd_email_html', false );
	add_option( 'tcd_smtp_enabled', false );
	add_option( 'tcd_smtp_host', '' );
	add_option( 'tcd_smtp_port', 587 );
	add_option( 'tcd_smtp_encryption', 'tls' );
	add_option( 'tcd_smtp_auth', true );
	add_option( 'tcd_smtp_username', '' );
	add_option( 'tcd_smtp_password', '' );
	add_option( 'tcd_smtp_from_email', get_option( 'admin_email' ) );
	add_option( 'tcd_smtp_from_name', get_bloginfo( 'name' ) );
	add_option( 'tcd_email_subject', __( 'Document Verification Required - {item_title}', 'tainacan-document-checker' ) );
	add_option( 'tcd_batch_email_subject', __( 'Multiple Documents Require Verification', 'tainacan-document-checker' ) );
}

/**
 * Load plugin text domain.
 *
 * @return void
 */
function tcd_load_textdomain(): void {
	load_plugin_textdomain(
		'tainacan-document-checker',
		false,
		dirname( TCD_PLUGIN_BASENAME ) . '/languages'
	);
}
add_action( 'plugins_loaded', 'tcd_load_textdomain' );

/**
 * Initialize plugin components.
 *
 * @return void
 */
function tcd_init(): void {
	// Check SSL support on init
	tcd_check_ssl_support();
	
	// Initialize core components.
	$document_checker = new TCD_Document_Checker();
	$email_handler = new TCD_Email_Handler();
	
	// Initialize admin if in admin area.
	if ( is_admin() ) {
		$admin = new TCD_Admin();
		$admin->init();
		
		$ajax_handler = new TCD_Ajax_Handler( $document_checker, $email_handler );
		$ajax_handler->init();
	}
	
	// Register shortcode.
	add_shortcode( 'tainacan_doc_status', 'tcd_shortcode_doc_status' );
}
add_action( 'init', 'tcd_init' );

/**
 * Shortcode callback for document status display.
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML output.
 */
function tcd_shortcode_doc_status( $atts ): string {
	$atts = shortcode_atts(
		array(
			'item_id' => 0,
		),
		$atts,
		'tainacan_doc_status'
	);

	// Get item ID from shortcode or query parameter.
	$item_id = absint( $atts['item_id'] );
	if ( ! $item_id && isset( $_GET['item_id'] ) ) {
		$item_id = absint( $_GET['item_id'] );
	}

	if ( ! $item_id ) {
		return '<p>' . esc_html__( 'No item ID provided.', 'tainacan-document-checker' ) . '</p>';
	}

	// Get document check status.
	$document_checker = new TCD_Document_Checker();
	$status           = $document_checker->get_item_history( $item_id );

	// Load the template.
	ob_start();
	include TCD_PLUGIN_DIR . 'public/doc-status.php';
	return ob_get_clean();
}

/**
 * Add plugin action links.
 *
 * @param array $links Existing links.
 * @return array Modified links.
 */
function tcd_plugin_action_links( $links ) {
	$settings_link = '<a href="' . admin_url( 'admin.php?page=tainacan-document-checker' ) . '">' . 
	                __( 'Settings', 'tainacan-document-checker' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . TCD_PLUGIN_BASENAME, 'tcd_plugin_action_links' );

/**
 * Add admin notices for common issues.
 *
 * @return void
 */
function tcd_admin_notices(): void {
	// Check if on plugin page
	$screen = get_current_screen();
	if ( ! $screen || 'toplevel_page_tainacan-document-checker' !== $screen->id ) {
		return;
	}

	// Check for SSL issues in error log
	$recent_errors = get_option( 'tcd_recent_errors', array() );
	if ( ! empty( $recent_errors ) ) {
		foreach ( $recent_errors as $error ) {
			if ( strpos( $error, 'SSL certificate problem' ) !== false || 
			     strpos( $error, 'cURL error 60' ) !== false ) {
				?>
				<div class="notice notice-error is-dismissible">
					<p>
						<strong><?php esc_html_e( 'SSL Error Detected:', 'tainacan-document-checker' ); ?></strong>
						<?php esc_html_e( 'The plugin is experiencing SSL certificate verification issues. Please check the SSL settings in the plugin configuration.', 'tainacan-document-checker' ); ?>
						<a href="<?php echo admin_url( 'admin.php?page=tainacan-document-checker&tab=settings#ssl-settings' ); ?>">
							<?php esc_html_e( 'Go to Settings', 'tainacan-document-checker' ); ?>
						</a>
					</p>
				</div>
				<?php
				break;
			}
		}
	}

	// Check if debug mode is enabled
	if ( get_option( 'tcd_debug_mode', false ) ) {
		?>
		<div class="notice notice-info is-dismissible">
			<p>
				<?php esc_html_e( 'Debug mode is enabled. Detailed information will be logged for troubleshooting.', 'tainacan-document-checker' ); ?>
			</p>
		</div>
		<?php
	}
}
add_action( 'admin_notices', 'tcd_admin_notices' );

/**
 * Log errors for debugging
 *
 * @param string $error Error message
 * @return void
 */
function tcd_log_error( $error ) {
	if ( get_option( 'tcd_debug_mode', false ) ) {
		error_log( 'TCD Error: ' . $error );
		
		// Store recent errors for admin notices
		$recent_errors = get_option( 'tcd_recent_errors', array() );
		$recent_errors[] = $error;
		
		// Keep only last 10 errors
		if ( count( $recent_errors ) > 10 ) {
			$recent_errors = array_slice( $recent_errors, -10 );
		}
		
		update_option( 'tcd_recent_errors', $recent_errors );
	}
}

// Deactivation hook
register_deactivation_hook( __FILE__, 'tcd_deactivate' );

/**
 * Plugin deactivation callback.
 *
 * @return void
 */
function tcd_deactivate(): void {
	// Clear scheduled events if any
	wp_clear_scheduled_hook( 'tcd_daily_check' );
	
	// Clear transients
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tcd_%'" );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_tcd_%'" );
	
	// Clear error logs
	delete_option( 'tcd_recent_errors' );
}
