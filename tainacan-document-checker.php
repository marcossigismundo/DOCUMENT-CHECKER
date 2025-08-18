<?php
/**
 * Plugin Name: Tainacan Document Checker
 * Plugin URI: https://github.com/your-org/tainacan-document-checker
 * Description: Verifies that required documents are attached to Tainacan collection items and sends email notifications
 * Version: 1.1.0
 * Author: Your Name
 * Author URI: https://your-website.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tainacan-document-checker
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package TainacanDocumentChecker
 */

declare(strict_types=1);

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'TCD_VERSION', '1.1.0' );
define( 'TCD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TCD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TCD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader.
require_once TCD_PLUGIN_DIR . 'includes/class-document-checker.php';
require_once TCD_PLUGIN_DIR . 'includes/class-admin.php';
require_once TCD_PLUGIN_DIR . 'includes/class-ajax-handler.php';
require_once TCD_PLUGIN_DIR . 'includes/class-email-handler.php';

/**
 * Plugin activation hook.
 *
 * @return void
 */
function tcd_activate(): void {
	tcd_create_database_tables();
	tcd_set_default_options();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'tcd_activate' );

/**
 * Plugin deactivation hook.
 *
 * @return void
 */
function tcd_deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'tcd_deactivate' );

/**
 * Create database tables for document check history and email logs.
 *
 * @return void
 */
function tcd_create_database_tables(): void {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	// Document checks table
	$table_name = $wpdb->prefix . 'tainacan_document_checks';
	$sql = "CREATE TABLE $table_name (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		item_id bigint(20) unsigned NOT NULL,
		collection_id bigint(20) unsigned NOT NULL,
		check_type varchar(20) NOT NULL DEFAULT 'individual',
		check_status varchar(20) NOT NULL,
		missing_documents text DEFAULT NULL,
		found_documents text DEFAULT NULL,
		check_date datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY item_id (item_id),
		KEY collection_id (collection_id),
		KEY check_date (check_date)
	) $charset_collate;";

	// Email logs table
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
	add_option( 'tcd_cache_duration', 300 ); // 5 minutes.
	
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
 * @return string
 */
function tcd_shortcode_doc_status( $atts ): string {
	$atts = shortcode_atts(
		array(
			'item_id' => 0,
		),
		$atts,
		'tainacan_doc_status'
	);

	$item_id = absint( $atts['item_id'] );
	
	if ( ! $item_id ) {
		return '<p>' . esc_html__( 'No item ID provided.', 'tainacan-document-checker' ) . '</p>';
	}

	// Get the document checker instance.
	$document_checker = new TCD_Document_Checker();
	
	// Check if we have cached results.
	$cache_key     = 'tcd_item_' . $item_id;
	$cached_result = get_transient( $cache_key );
	
	if ( false === $cached_result ) {
		// Perform the check.
		$result = $document_checker->check_item_documents( $item_id );
		
		// Cache for 5 minutes.
		set_transient( $cache_key, $result, 300 );
	} else {
		$result = $cached_result;
	}
	
	// Load the template.
	ob_start();
	include TCD_PLUGIN_DIR . 'public/doc-status.php';
	return ob_get_clean();
}

/**
 * Enqueue admin scripts and styles.
 *
 * @param string $hook Current admin page hook.
 * @return void
 */
function tcd_enqueue_admin_assets( string $hook ): void {
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
add_action( 'admin_enqueue_scripts', 'tcd_enqueue_admin_assets' );

/**
 * Enqueue frontend scripts and styles.
 *
 * @return void
 */
function tcd_enqueue_frontend_assets(): void {
	if ( ! is_singular() ) {
		return;
	}

	global $post;
	
	if ( $post && has_shortcode( $post->post_content, 'tainacan_doc_status' ) ) {
		wp_enqueue_style(
			'tcd-frontend',
			TCD_PLUGIN_URL . 'assets/css/frontend-style.css',
			array(),
			TCD_VERSION
		);
	}
}
add_action( 'wp_enqueue_scripts', 'tcd_enqueue_frontend_assets' );

/**
 * Hook into Tainacan item updates to clear cache and potentially send notifications.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function tcd_handle_item_update( int $post_id ): void {
	// Check if this is a Tainacan item
	$post_type = get_post_type( $post_id );
	if ( ! preg_match( '/^tnc_col_\d+_item$/', $post_type ) ) {
		return;
	}

	// Clear cache for this item
	$document_checker = new TCD_Document_Checker();
	$document_checker->clear_item_cache( $post_id );

	// If auto-check is enabled, perform document check and send notification
	if ( get_option( 'tcd_auto_check_on_update', false ) ) {
		$result = $document_checker->check_item_documents( $post_id );
		
		// Send email notification if enabled and item is incomplete
		if ( get_option( 'tcd_email_enabled', false ) && 'incomplete' === $result['status'] ) {
			$email_handler = new TCD_Email_Handler();
			$user_id = get_post_field( 'post_author', $post_id );
			
			if ( $user_id ) {
				$email_handler->send_document_notification( $result, (int) $user_id );
			}
		}
	}
}
add_action( 'save_post', 'tcd_handle_item_update' );

/**
 * Add custom cron schedules for email notifications.
 *
 * @param array $schedules Existing schedules.
 * @return array Modified schedules.
 */
function tcd_add_cron_schedules( array $schedules ): array {
	$schedules['tcd_weekly'] = array(
		'interval' => WEEK_IN_SECONDS,
		'display'  => __( 'Weekly', 'tainacan-document-checker' ),
	);
	
	$schedules['tcd_daily'] = array(
		'interval' => DAY_IN_SECONDS,
		'display'  => __( 'Daily', 'tainacan-document-checker' ),
	);
	
	return $schedules;
}
add_filter( 'cron_schedules', 'tcd_add_cron_schedules' );

/**
 * Schedule recurring email notifications (optional feature).
 *
 * @return void
 */
function tcd_schedule_recurring_emails(): void {
	if ( ! wp_next_scheduled( 'tcd_send_recurring_notifications' ) ) {
		$frequency = get_option( 'tcd_email_frequency', 'weekly' );
		wp_schedule_event( time(), "tcd_$frequency", 'tcd_send_recurring_notifications' );
	}
}
add_action( 'wp', 'tcd_schedule_recurring_emails' );

/**
 * Send recurring email notifications for incomplete items.
 *
 * @return void
 */
function tcd_send_recurring_notifications(): void {
	if ( ! get_option( 'tcd_recurring_emails_enabled', false ) ) {
		return;
	}

	global $wpdb;
	
	// Get items that are still incomplete after X days
	$days_threshold = get_option( 'tcd_recurring_email_days', 7 );
	$date_threshold = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_threshold} days" ) );
	
	$table_name = $wpdb->prefix . 'tainacan_document_checks';
	$incomplete_items = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DISTINCT item_id, collection_id 
			FROM {$table_name} 
			WHERE check_status = 'incomplete' 
			AND check_date >= %s 
			AND item_id NOT IN (
				SELECT DISTINCT item_id 
				FROM {$table_name} 
				WHERE check_status = 'complete' 
				AND check_date > %s
			)",
			$date_threshold,
			$date_threshold
		),
		ARRAY_A
	);

	if ( empty( $incomplete_items ) ) {
		return;
	}

	$document_checker = new TCD_Document_Checker();
	$email_handler = new TCD_Email_Handler();
	
	foreach ( $incomplete_items as $item_data ) {
		$item_id = (int) $item_data['item_id'];
		
		// Re-check the item to get current status
		$result = $document_checker->check_item_documents( $item_id );
		
		// Only send email if still incomplete
		if ( 'incomplete' === $result['status'] ) {
			$user_id = get_post_field( 'post_author', $item_id );
			
			if ( $user_id ) {
				$email_handler->send_document_notification( $result, (int) $user_id );
			}
		}
	}
}
add_action( 'tcd_send_recurring_notifications', 'tcd_send_recurring_notifications' );

/**
 * Debug function to check AJAX URL.
 *
 * @return void
 */
function tcd_debug_ajax_url(): void {
	if ( get_option( 'tcd_debug_mode', false ) && is_admin() ) {
		$ajax_url = admin_url( 'admin-ajax.php' );
		error_log( 'TCD Debug - AJAX URL: ' . $ajax_url );
		error_log( 'TCD Debug - AJAX URL exists: ' . ( file_exists( ABSPATH . 'wp-admin/admin-ajax.php' ) ? 'Yes' : 'No' ) );
	}
}
add_action( 'admin_init', 'tcd_debug_ajax_url' );

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

	// Check if email is enabled but SMTP is not configured
	if ( get_option( 'tcd_email_enabled', false ) && ! get_option( 'tcd_smtp_enabled', false ) ) {
		$from_email = get_option( 'admin_email' );
		if ( ! is_email( $from_email ) ) {
			?>
			<div class="notice notice-warning">
				<p>
					<?php esc_html_e( 'Email notifications are enabled but no valid sender email is configured. Please configure SMTP settings or check your WordPress admin email.', 'tainacan-document-checker' ); ?>
				</p>
			</div>
			<?php
		}
	}

	// Check if debug mode is enabled
	if ( get_option( 'tcd_debug_mode', false ) ) {
		?>
		<div class="notice notice-info">
			<p>
				<?php esc_html_e( 'Debug mode is enabled. Check your error logs for detailed information.', 'tainacan-document-checker' ); ?>
			</p>
		</div>
		<?php
	}
}
add_action( 'admin_notices', 'tcd_admin_notices' );

/**
 * Handle missing UR-Logo.gif requests.
 *
 * @return void
 */
function tcd_handle_missing_assets(): void {
	// Interceptar requisições para arquivos que causam 404
	if ( isset( $_SERVER['REQUEST_URI'] ) && 
		 ( strpos( $_SERVER['REQUEST_URI'], 'UR-Logo.gif' ) !== false ||
		   strpos( $_SERVER['REQUEST_URI'], 'user-registration' ) !== false ) ) {
		
		// Log para debug
		if ( get_option( 'tcd_debug_mode', false ) ) {
			error_log( 'TCD Debug - Intercepted missing asset request: ' . $_SERVER['REQUEST_URI'] );
		}
		
		// Retornar uma imagem GIF transparente de 1x1 pixel
		header( 'Content-Type: image/gif' );
		header( 'Content-Length: 43' );
		header( 'Cache-Control: public, max-age=3600' );
		
		// GIF transparente de 1x1 pixel
		echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
		exit;
	}
}
add_action( 'init', 'tcd_handle_missing_assets', 1 );

/**
 * Remove problematic asset references from admin pages.
 *
 * @return void
 */
function tcd_clean_admin_assets(): void {
	$screen = get_current_screen();
	if ( ! $screen || 'toplevel_page_tainacan-document-checker' !== $screen->id ) {
		return;
	}
	
	?>
	<style>
	/* Hide problematic images and elements */
	img[src*="UR-Logo.gif"],
	img[src*="user-registration"][src*="logo"],
	.ur-logo,
	.user-registration-logo {
		display: none !important;
	}
	
	/* Clean backgrounds */
	*[style*="UR-Logo.gif"] {
		background-image: none !important;
	}
	</style>
	
	<script>
	jQuery(document).ready(function($) {
		// Remove broken image references
		$('img').each(function() {
			var src = $(this).attr('src');
			if (src && (src.indexOf('UR-Logo.gif') > -1 || src.indexOf('404') > -1)) {
				$(this).remove();
			}
		});
		
		// Handle image load errors
		$(document).on('error', 'img', function() {
			var src = $(this).attr('src');
			if (src && (src.indexOf('UR-Logo.gif') > -1 || src.indexOf('user-registration') > -1)) {
				$(this).remove();
			}
		});
		
		// Clean background images
		$('*').each(function() {
			var bg = $(this).css('background-image');
			if (bg && bg.indexOf('UR-Logo.gif') > -1) {
				$(this).css('background-image', 'none');
			}
		});
	});
	</script>
	<?php
}
add_action( 'admin_head', 'tcd_clean_admin_assets' );

/**
 * Clean up plugin data on uninstall.
 *
 * @return void
 */
function tcd_uninstall(): void {
	if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
		return;
	}

	global $wpdb;

	// Remove database tables
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tainacan_document_checks" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tainacan_document_emails" );

	// Remove options
	$options = array(
		'tcd_required_documents',
		'tcd_api_url',
		'tcd_collection_id',
		'tcd_debug_mode',
		'tcd_cache_duration',
		'tcd_auto_clear_cache',
		'tcd_email_enabled',
		'tcd_email_html',
		'tcd_smtp_enabled',
		'tcd_smtp_host',
		'tcd_smtp_port',
		'tcd_smtp_encryption',
		'tcd_smtp_auth',
		'tcd_smtp_username',
		'tcd_smtp_password',
		'tcd_smtp_from_email',
		'tcd_smtp_from_name',
		'tcd_email_subject',
		'tcd_batch_email_subject',
		'tcd_email_template',
		'tcd_batch_email_template',
		'tcd_recurring_emails_enabled',
		'tcd_email_frequency',
		'tcd_recurring_email_days',
		'tcd_auto_check_on_update',
		'tcd_db_version',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Clear scheduled events
	wp_clear_scheduled_hook( 'tcd_send_recurring_notifications' );

	// Clear transients
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tcd_%' OR option_name LIKE '_transient_timeout_tcd_%'" );
}
register_uninstall_hook( __FILE__, 'tcd_uninstall' );