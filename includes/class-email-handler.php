<?php
/**
 * Email Handler Class (Corrigido)
 *
 * @package TainacanDocumentChecker
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles email notifications for document checking.
 *
 * @since 1.0.0
 */
class TCD_Email_Handler {

	/**
	 * Debug mode flag.
	 *
	 * @var bool
	 */
	private bool $debug_mode;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->debug_mode = (bool) get_option( 'tcd_debug_mode', false );
		
		// Configure SMTP if enabled
		if ( get_option( 'tcd_smtp_enabled', false ) ) {
			add_action( 'phpmailer_init', array( $this, 'configure_smtp' ) );
		}
	}

	/**
	 * Configure SMTP settings for PHPMailer.
	 *
	 * @param PHPMailer $phpmailer PHPMailer instance.
	 * @return void
	 */
	public function configure_smtp( $phpmailer ): void {
		try {
			$phpmailer->isSMTP();
			$phpmailer->Host       = get_option( 'tcd_smtp_host', '' );
			$phpmailer->Port       = get_option( 'tcd_smtp_port', 587 );
			$phpmailer->SMTPSecure = get_option( 'tcd_smtp_encryption', 'tls' );
			$phpmailer->SMTPAuth   = (bool) get_option( 'tcd_smtp_auth', true );
			
			if ( $phpmailer->SMTPAuth ) {
				$phpmailer->Username = get_option( 'tcd_smtp_username', '' );
				$phpmailer->Password = get_option( 'tcd_smtp_password', '' );
			}
			
			// Set from address if configured
			$from_email = get_option( 'tcd_smtp_from_email', '' );
			$from_name  = get_option( 'tcd_smtp_from_name', get_bloginfo( 'name' ) );
			
			if ( ! empty( $from_email ) && is_email( $from_email ) ) {
				$phpmailer->setFrom( $from_email, $from_name );
			}
			
			if ( $this->debug_mode ) {
				$phpmailer->SMTPDebug = 2;
				$phpmailer->Debugoutput = function( $str, $level ) {
					error_log( "TCD SMTP Debug: $str" );
				};
			}
		} catch ( Exception $e ) {
			if ( $this->debug_mode ) {
				error_log( 'TCD SMTP Configuration Error: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Send notification email for document check result.
	 *
	 * @param array $result Document check result.
	 * @param int   $user_id WordPress user ID.
	 * @return bool Success status.
	 */
	public function send_document_notification( array $result, int $user_id ): bool {
		// Check if email notifications are enabled
		if ( ! get_option( 'tcd_email_enabled', false ) ) {
			if ( $this->debug_mode ) {
				error_log( 'TCD Email: Email notifications are disabled' );
			}
			return false;
		}

		// Only send emails for incomplete status
		if ( 'incomplete' !== $result['status'] ) {
			if ( $this->debug_mode ) {
				error_log( 'TCD Email: Item status is not incomplete, skipping email' );
			}
			return false;
		}

		// Get user data
		$user = get_userdata( $user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			if ( $this->debug_mode ) {
				error_log( 'TCD Email: Invalid user or email for ID ' . $user_id );
			}
			return false;
		}

		// Get item information
		$item_info = $this->get_item_info( $result['item_id'] );
		
		// Prepare email content
		$subject = $this->prepare_email_subject( $result, $item_info, $user );
		$message = $this->prepare_email_message( $result, $item_info, $user );
		$headers = $this->prepare_email_headers();

		if ( $this->debug_mode ) {
			error_log( 'TCD Email: Preparing to send to ' . $user->user_email );
			error_log( 'TCD Email: Subject: ' . $subject );
		}

		// Send email
		$sent = wp_mail( $user->user_email, $subject, $message, $headers );

		if ( $this->debug_mode ) {
			error_log( 'TCD Email: Notification sent to ' . $user->user_email . ' - Status: ' . ( $sent ? 'Success' : 'Failed' ) );
			
			// Log additional debug info if failed
			if ( ! $sent ) {
				global $phpmailer;
				if ( isset( $phpmailer ) && ! empty( $phpmailer->ErrorInfo ) ) {
					error_log( 'TCD Email Error: ' . $phpmailer->ErrorInfo );
				}
			}
		}

		// Log email in database
		$this->log_email_sent( $user_id, $result['item_id'], $sent ? 'sent' : 'failed', $subject );

		return $sent;
	}

	/**
	 * Send batch notification emails.
	 *
	 * @param array $batch_result Batch check results.
	 * @return array Email sending statistics.
	 */
	public function send_batch_notifications( array $batch_result ): array {
		$stats = array(
			'emails_sent'   => 0,
			'emails_failed' => 0,
			'users_notified' => array(),
		);

		if ( empty( $batch_result['items_checked'] ) ) {
			if ( $this->debug_mode ) {
				error_log( 'TCD Email: No items to process for batch notifications' );
			}
			return $stats;
		}

		// Group incomplete items by user
		$user_items = $this->group_items_by_user( $batch_result['items_checked'] );

		if ( $this->debug_mode ) {
			error_log( 'TCD Email: Processing ' . count( $user_items ) . ' users for batch notifications' );
		}

		foreach ( $user_items as $user_id => $items ) {
			// Only notify about incomplete items
			$incomplete_items = array_filter( $items, function( $item ) {
				return 'incomplete' === $item['status'];
			});

			if ( empty( $incomplete_items ) ) {
				continue;
			}

			$sent = $this->send_batch_notification_to_user( $user_id, $incomplete_items, $batch_result );
			
			if ( $sent ) {
				$stats['emails_sent']++;
				$stats['users_notified'][] = $user_id;
			} else {
				$stats['emails_failed']++;
			}
		}

		if ( $this->debug_mode ) {
			error_log( 'TCD Email: Batch complete - Sent: ' . $stats['emails_sent'] . ', Failed: ' . $stats['emails_failed'] );
		}

		return $stats;
	}

	/**
	 * Group items by their associated WordPress user.
	 *
	 * @param array $items Items to group.
	 * @return array Items grouped by user ID.
	 */
	private function group_items_by_user( array $items ): array {
		$user_items = array();

		foreach ( $items as $item ) {
			$user_id = $this->get_item_user_id( $item['id'] );
			
			if ( $user_id ) {
				if ( ! isset( $user_items[ $user_id ] ) ) {
					$user_items[ $user_id ] = array();
				}
				$user_items[ $user_id ][] = $item;
			}
		}

		return $user_items;
	}

	/**
	 * Get WordPress user ID associated with a Tainacan item.
	 *
	 * @param int $item_id Tainacan item ID.
	 * @return int|null User ID or null if not found.
	 */
	private function get_item_user_id( int $item_id ): ?int {
		// Try to get the post author (Tainacan items are WordPress posts)
		$post = get_post( $item_id );
		if ( $post && $post->post_author ) {
			return (int) $post->post_author;
		}

		// Alternative: Check if User Registration plugin stores user-item relationships
		$user_id = $this->get_user_from_registration_plugin( $item_id );
		if ( $user_id ) {
			return $user_id;
		}

		// Fallback: Check custom meta
		$user_id = get_post_meta( $item_id, '_tcd_user_id', true );
		if ( $user_id ) {
			return (int) $user_id;
		}

		if ( $this->debug_mode ) {
			error_log( 'TCD Email: Could not find user for item ' . $item_id );
		}

		return null;
	}

	/**
	 * Get user ID from User Registration plugin data.
	 *
	 * @param int $item_id Tainacan item ID.
	 * @return int|null User ID or null if not found.
	 */
	private function get_user_from_registration_plugin( int $item_id ): ?int {
		// Check for common User Registration plugin meta keys
		$possible_meta_keys = array(
			'_ur_user_id',
			'_user_registration_user_id',
			'_tainacan_user_id',
			'_item_author_id',
			'_submitter_id'
		);

		foreach ( $possible_meta_keys as $meta_key ) {
			$user_id = get_post_meta( $item_id, $meta_key, true );
			if ( $user_id && is_numeric( $user_id ) ) {
				return (int) $user_id;
			}
		}

		return null;
	}

	/**
	 * Get item information for email content.
	 *
	 * @param int $item_id Item ID.
	 * @return array Item information.
	 */
	private function get_item_info( int $item_id ): array {
		$post = get_post( $item_id );
		
		$title = $post ? $post->post_title : 'Item #' . $item_id;
		$url = get_permalink( $item_id );
		
		// Fallback URL if permalink fails
		if ( ! $url ) {
			$url = admin_url( 'post.php?post=' . $item_id . '&action=edit' );
		}
		
		return array(
			'title' => $title,
			'url'   => $url,
			'id'    => $item_id,
		);
	}

	/**
	 * Prepare email subject.
	 *
	 * @param array $result Check result.
	 * @param array $item_info Item information.
	 * @param WP_User $user User object.
	 * @return string Email subject.
	 */
	private function prepare_email_subject( array $result, array $item_info, $user ): string {
		$template = get_option( 'tcd_email_subject', __( 'Document Verification Required - {item_title}', 'tainacan-document-checker' ) );
		
		return $this->replace_email_placeholders( $template, $result, $item_info, $user );
	}

	/**
	 * Prepare email message content.
	 *
	 * @param array $result Check result.
	 * @param array $item_info Item information.
	 * @param WP_User $user User object.
	 * @return string Email message.
	 */
	private function prepare_email_message( array $result, array $item_info, $user ): string {
		$template = get_option( 'tcd_email_template', $this->get_default_email_template() );
		
		return $this->replace_email_placeholders( $template, $result, $item_info, $user );
	}

	/**
	 * Send batch notification to a specific user.
	 *
	 * @param int   $user_id User ID.
	 * @param array $items Incomplete items for this user.
	 * @param array $batch_result Full batch result.
	 * @return bool Success status.
	 */
	private function send_batch_notification_to_user( int $user_id, array $items, array $batch_result ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			if ( $this->debug_mode ) {
				error_log( 'TCD Email: Invalid user or email for batch notification, user ID: ' . $user_id );
			}
			return false;
		}

		$subject = get_option( 'tcd_batch_email_subject', __( 'Multiple Documents Require Verification', 'tainacan-document-checker' ) );
		$template = get_option( 'tcd_batch_email_template', $this->get_default_batch_email_template() );
		
		// Prepare batch-specific placeholders
		$items_list = $this->format_items_list( $items );
		$total_items = count( $items );
		
		$placeholders = array(
			'{user_name}'     => $user->display_name ?: $user->user_login,
			'{user_email}'    => $user->user_email,
			'{total_items}'   => $total_items,
			'{items_list}'    => $items_list,
			'{site_name}'     => get_bloginfo( 'name' ),
			'{site_url}'      => get_site_url(),
			'{date}'          => wp_date( get_option( 'date_format' ) ),
			'{time}'          => wp_date( get_option( 'time_format' ) ),
			'{collection_id}' => $batch_result['collection_id'] ?? '',
		);

		$subject = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $subject );
		$message = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $template );
		
		$headers = $this->prepare_email_headers();
		
		if ( $this->debug_mode ) {
			error_log( 'TCD Email: Sending batch notification to ' . $user->user_email . ' for ' . $total_items . ' items' );
		}
		
		$sent = wp_mail( $user->user_email, $subject, $message, $headers );

		// Log batch email
		$this->log_email_sent( $user_id, 0, $sent ? 'sent' : 'failed', $subject, 'batch' );

		return $sent;
	}

	/**
	 * Format items list for batch email.
	 *
	 * @param array $items Items to format.
	 * @return string Formatted items list.
	 */
	private function format_items_list( array $items ): string {
		$list = '';
		
		foreach ( $items as $item ) {
			$item_info = $this->get_item_info( $item['id'] );
			$list .= sprintf(
				"• %s (ID: %d)\n",
				$item_info['title'],
				$item['id']
			);
			
			if ( ! empty( $item['missing_documents'] ) ) {
				$list .= "  " . __( 'Missing:', 'tainacan-document-checker' ) . " " . implode( ', ', $item['missing_documents'] ) . "\n";
			}
			
			if ( ! empty( $item['invalid_documents'] ) ) {
				$list .= "  " . __( 'Invalid:', 'tainacan-document-checker' ) . " " . implode( ', ', $item['invalid_documents'] ) . "\n";
			}
			
			if ( ! empty( $item_info['url'] ) ) {
				$list .= "  " . __( 'Edit:', 'tainacan-document-checker' ) . " " . $item_info['url'] . "\n";
			}
			
			$list .= "\n";
		}
		
		return $list;
	}

	/**
	 * Replace placeholders in email templates.
	 *
	 * @param string  $template Email template.
	 * @param array   $result Check result.
	 * @param array   $item_info Item information.
	 * @param WP_User $user User object.
	 * @return string Processed template.
	 */
	private function replace_email_placeholders( string $template, array $result, array $item_info, $user ): string {
		$placeholders = array(
			'{user_name}'          => $user->display_name ?: $user->user_login,
			'{user_email}'         => $user->user_email,
			'{item_title}'         => $item_info['title'],
			'{item_id}'            => $item_info['id'],
			'{item_url}'           => $item_info['url'],
			'{missing_documents}'  => ! empty( $result['missing_documents'] ) ? implode( ', ', $result['missing_documents'] ) : __( 'None', 'tainacan-document-checker' ),
			'{invalid_documents}'  => ! empty( $result['invalid_documents'] ) ? implode( ', ', $result['invalid_documents'] ) : __( 'None', 'tainacan-document-checker' ),
			'{found_documents}'    => ! empty( $result['found_documents'] ) ? implode( ', ', $result['found_documents'] ) : __( 'None', 'tainacan-document-checker' ),
			'{site_name}'          => get_bloginfo( 'name' ),
			'{site_url}'           => get_site_url(),
			'{date}'               => wp_date( get_option( 'date_format' ) ),
			'{time}'               => wp_date( get_option( 'time_format' ) ),
		);

		$processed_template = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $template );
		
		// Limpar qualquer referência a logos ou imagens problemáticas
		$processed_template = $this->clean_template_content( $processed_template );
		
		return $processed_template;
	}

	/**
	 * Clean template content from problematic references.
	 *
	 * @param string $content Template content.
	 * @return string Cleaned content.
	 */
	private function clean_template_content( string $content ): string {
		// Remover referências a imagens problemáticas
		$content = preg_replace( '/\<img[^>]*UR-Logo\.gif[^>]*\>/i', '', $content );
		$content = preg_replace( '/background-image\s*:\s*url\([^)]*UR-Logo\.gif[^)]*\)/i', '', $content );
		$content = str_replace( 'UR-Logo.gif', '', $content );
		
		// Remover outros logos problemáticos conhecidos
		$content = preg_replace( '/\<img[^>]*user-registration[^>]*logo[^>]*\>/i', '', $content );
		$content = preg_replace( '/\<img[^>]*logo[^>]*user-registration[^>]*\>/i', '', $content );
		
		// Limpar URLs quebradas
		$content = preg_replace( '/src=["\'][^"\']*404[^"\']*["\']/i', 'src=""', $content );
		
		return $content;
	}

	/**
	 * Prepare email headers.
	 *
	 * @return array Email headers.
	 */
	private function prepare_email_headers(): array {
		$headers = array();
		
		// Set content type to HTML if enabled
		if ( get_option( 'tcd_email_html', false ) ) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
		} else {
			$headers[] = 'Content-Type: text/plain; charset=UTF-8';
		}
		
		// Set from address
		$from_email = get_option( 'tcd_smtp_from_email', get_option( 'admin_email' ) );
		$from_name  = get_option( 'tcd_smtp_from_name', get_bloginfo( 'name' ) );
		
		// Validate email address
		if ( is_email( $from_email ) ) {
			$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
		}
		
		return $headers;
	}

	/**
	 * Log email sending attempt.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $item_id Item ID (0 for batch emails).
	 * @param string $status Email status.
	 * @param string $subject Email subject.
	 * @param string $type Email type (single|batch).
	 * @return void
	 */
	private function log_email_sent( int $user_id, int $item_id, string $status, string $subject, string $type = 'single' ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tainacan_document_emails';
		
		$result = $wpdb->insert(
			$table_name,
			array(
				'user_id'    => $user_id,
				'item_id'    => $item_id,
				'email_type' => $type,
				'subject'    => $subject,
				'status'     => $status,
				'sent_date'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( $this->debug_mode && $result === false ) {
			error_log( 'TCD Email: Failed to log email in database - ' . $wpdb->last_error );
		}
	}

	/**
	 * Get default email template.
	 *
	 * @return string Default template.
	 */
	private function get_default_email_template(): string {
		return __( "Hello {user_name},

Your document verification for item '{item_title}' requires attention.

Missing Documents: {missing_documents}
Invalid Documents: {invalid_documents}

Please log in to your account and upload the required documents:
{item_url}

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
	 * Test SMTP configuration.
	 *
	 * @param string $test_email Email address to send test to.
	 * @return array Test result.
	 */
	public function test_smtp_configuration( string $test_email ): array {
		if ( ! is_email( $test_email ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid email address provided.', 'tainacan-document-checker' )
			);
		}

		$subject = __( 'Tainacan Document Checker - SMTP Test', 'tainacan-document-checker' );
		$message = __( 'This is a test email to verify your SMTP configuration is working correctly.', 'tainacan-document-checker' );
		$headers = $this->prepare_email_headers();

		// Clear any previous errors
		global $phpmailer;
		if ( isset( $phpmailer ) ) {
			$phpmailer->clearAllRecipients();
			$phpmailer->clearAttachments();
			$phpmailer->clearCustomHeaders();
		}

		$sent = wp_mail( $test_email, $subject, $message, $headers );

		$result = array(
			'success' => $sent,
			'message' => $sent 
				? __( 'Test email sent successfully!', 'tainacan-document-checker' )
				: __( 'Failed to send test email. Please check your SMTP settings.', 'tainacan-document-checker' )
		);

		// Add error details if available
		if ( ! $sent && isset( $phpmailer ) && ! empty( $phpmailer->ErrorInfo ) ) {
			$result['message'] .= ' Error: ' . $phpmailer->ErrorInfo;
		}

		return $result;
	}

	/**
	 * Get email sending statistics.
	 *
	 * @param int $days Number of days to look back.
	 * @return array Email statistics.
	 */
	public function get_email_stats( int $days = 30 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tainacan_document_emails';
		
		// Check if table exists
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
		
		if ( ! $table_exists ) {
			return array(
				'total_emails' => 0,
				'sent_emails'  => 0,
				'failed_emails' => 0,
				'single_emails' => 0,
				'batch_emails' => 0,
			);
		}

		$date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					COUNT(*) as total_emails,
					SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_emails,
					SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_emails,
					SUM(CASE WHEN email_type = 'single' THEN 1 ELSE 0 END) as single_emails,
					SUM(CASE WHEN email_type = 'batch' THEN 1 ELSE 0 END) as batch_emails
				FROM {$table_name} 
				WHERE sent_date >= %s",
				$date_from
			),
			ARRAY_A
		);

		return $stats ?: array(
			'total_emails' => 0,
			'sent_emails'  => 0,
			'failed_emails' => 0,
			'single_emails' => 0,
			'batch_emails' => 0,
		);
	}
}