<?php
/**
 * Email Handler for Tainacan Document Checker
 *
 * @package TainacanDocumentChecker
 */

declare(strict_types=1);

/**
 * Handles email notifications for document verification.
 */
class TCD_Email_Handler {

	/**
	 * Send email notification for a single item check.
	 *
	 * @param array $result Check result data.
	 * @param array $item_info Item information.
	 * @return bool True if email sent successfully.
	 */
	public function send_single_notification( array $result, array $item_info ): bool {
		// Check if email is enabled
		if ( ! get_option( 'tcd_email_enabled', false ) ) {
			return false;
		}

		// Only send if there are issues
		if ( empty( $result['missing_documents'] ) && empty( $result['invalid_documents'] ) ) {
			return false;
		}

		// Get item author/owner
		$user_id = $this->get_item_owner( $item_info['id'] );
		if ( ! $user_id ) {
			if ( get_option( 'tcd_debug_mode', false ) ) {
				error_log( 'TCD Email: No user found for item ' . $item_info['id'] );
			}
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return false;
		}

		// Prepare email
		$subject = $this->prepare_subject( 'single', $result, $item_info );
		$message = $this->prepare_message( 'single', $result, $item_info, $user );
		$headers = $this->prepare_headers();

		// Configure SMTP if enabled
		if ( get_option( 'tcd_smtp_enabled', false ) ) {
			add_action( 'phpmailer_init', array( $this, 'configure_smtp' ) );
		}

		// Send email
		$sent = wp_mail( $user->user_email, $subject, $message, $headers );

		// Remove SMTP configuration
		if ( get_option( 'tcd_smtp_enabled', false ) ) {
			remove_action( 'phpmailer_init', array( $this, 'configure_smtp' ) );
		}

		// Log email
		$this->log_email( $user_id, $item_info['id'], 'single', $subject, $sent ? 'sent' : 'failed' );

		return $sent;
	}

	/**
	 * Send batch notification for multiple items.
	 *
	 * @param array $incomplete_items Array of incomplete items.
	 * @param int   $collection_id Collection ID.
	 * @return array Results of email sending.
	 */
	public function send_batch_notification( array $incomplete_items, int $collection_id ): array {
		if ( ! get_option( 'tcd_email_enabled', false ) ) {
			return array( 'sent' => 0, 'failed' => 0 );
		}

		if ( empty( $incomplete_items ) ) {
			return array( 'sent' => 0, 'failed' => 0 );
		}

		// Group items by user
		$items_by_user = $this->group_items_by_user( $incomplete_items );
		
		$results = array( 'sent' => 0, 'failed' => 0 );

		foreach ( $items_by_user as $user_id => $user_items ) {
			$user = get_userdata( $user_id );
			if ( ! $user || ! is_email( $user->user_email ) ) {
				$results['failed']++;
				continue;
			}

			// Prepare batch email
			$subject = $this->prepare_batch_subject( count( $user_items ) );
			$message = $this->prepare_batch_message( $user_items, $user );
			$headers = $this->prepare_headers();

			// Configure SMTP if enabled
			if ( get_option( 'tcd_smtp_enabled', false ) ) {
				add_action( 'phpmailer_init', array( $this, 'configure_smtp' ) );
			}

			// Send email
			$sent = wp_mail( $user->user_email, $subject, $message, $headers );

			// Remove SMTP configuration
			if ( get_option( 'tcd_smtp_enabled', false ) ) {
				remove_action( 'phpmailer_init', array( $this, 'configure_smtp' ) );
			}

			// Log and count
			$this->log_email( $user_id, 0, 'batch', $subject, $sent ? 'sent' : 'failed' );
			
			if ( $sent ) {
				$results['sent']++;
			} else {
				$results['failed']++;
			}
		}

		return $results;
	}

	/**
	 * Get item owner from Tainacan.
	 *
	 * @param int $item_id Item ID.
	 * @return int|null User ID or null if not found.
	 */
	private function get_item_owner( int $item_id ): ?int {
		// First try to get from post author
		$post = get_post( $item_id );
		if ( $post && $post->post_author ) {
			return (int) $post->post_author;
		}

		// Try via Tainacan API
		$api_url = get_option( 'tcd_api_url', '' );
		if ( empty( $api_url ) ) {
			return null;
		}

		$response = wp_remote_get( $api_url . '/items/' . $item_id );
		if ( is_wp_error( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		
		// Check for author in API response
		if ( isset( $data['author_id'] ) ) {
			return (int) $data['author_id'];
		}
		
		if ( isset( $data['created_by'] ) ) {
			return (int) $data['created_by'];
		}

		// Fallback to current user if they have edit permissions
		if ( current_user_can( 'edit_post', $item_id ) ) {
			return get_current_user_id();
		}

		return null;
	}

	/**
	 * Group items by user.
	 *
	 * @param array $items Items array.
	 * @return array Items grouped by user ID.
	 */
	private function group_items_by_user( array $items ): array {
		$grouped = array();
		
		foreach ( $items as $item ) {
			$user_id = $this->get_item_owner( $item['item_id'] );
			if ( $user_id ) {
				if ( ! isset( $grouped[ $user_id ] ) ) {
					$grouped[ $user_id ] = array();
				}
				$grouped[ $user_id ][] = $item;
			}
		}
		
		return $grouped;
	}

	/**
	 * Prepare email subject.
	 *
	 * @param string $type Email type (single/batch).
	 * @param array  $result Check result.
	 * @param array  $item_info Item information.
	 * @return string Prepared subject.
	 */
	private function prepare_subject( string $type, array $result, array $item_info ): string {
		if ( $type === 'single' ) {
			$template = get_option( 'tcd_email_subject', 'Document Verification Required - {item_title}' );
			return str_replace( '{item_title}', $item_info['title'], $template );
		}
		
		return get_option( 'tcd_batch_email_subject', 'Multiple Documents Require Verification' );
	}

	/**
	 * Prepare batch email subject.
	 *
	 * @param int $count Number of items.
	 * @return string Prepared subject.
	 */
	private function prepare_batch_subject( int $count ): string {
		$template = get_option( 'tcd_batch_email_subject', 'Documents Missing - {count} Items Need Attention' );
		return str_replace( '{count}', (string) $count, $template );
	}

	/**
	 * Prepare email message.
	 *
	 * @param string  $type Email type.
	 * @param array   $result Check result.
	 * @param array   $item_info Item information.
	 * @param WP_User $user User object.
	 * @return string Prepared message.
	 */
	private function prepare_message( string $type, array $result, array $item_info, $user ): string {
		$is_html = get_option( 'tcd_email_html', false );
		
		if ( $is_html ) {
			return $this->prepare_html_message( $type, $result, $item_info, $user );
		}
		
		return $this->prepare_text_message( $type, $result, $item_info, $user );
	}

	/**
	 * Prepare text email message.
	 *
	 * @param string  $type Email type.
	 * @param array   $result Check result.
	 * @param array   $item_info Item information.
	 * @param WP_User $user User object.
	 * @return string Text message.
	 */
	private function prepare_text_message( string $type, array $result, array $item_info, $user ): string {
		$template = $this->get_email_template( 'text', $type );
		
		// Replace placeholders
		$template = $this->replace_email_placeholders( $template, $result, $item_info, $user );
		
		return $template;
	}

	/**
	 * Prepare HTML email message.
	 *
	 * @param string  $type Email type.
	 * @param array   $result Check result.
	 * @param array   $item_info Item information.
	 * @param WP_User $user User object.
	 * @return string HTML message.
	 */
	private function prepare_html_message( string $type, array $result, array $item_info, $user ): string {
		$template = $this->get_email_template( 'html', $type );
		
		// Replace placeholders
		$template = $this->replace_email_placeholders( $template, $result, $item_info, $user );
		
		// Wrap in HTML structure
		$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>';
		$html .= '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
		$html .= $template;
		$html .= '</div></body></html>';
		
		return $html;
	}

	/**
	 * Prepare batch email message.
	 *
	 * @param array   $items User's items with issues.
	 * @param WP_User $user User object.
	 * @return string Prepared message.
	 */
	private function prepare_batch_message( array $items, $user ): string {
		$is_html = get_option( 'tcd_email_html', false );
		
		$message = sprintf( __( 'Hello %s,', 'tainacan-document-checker' ), $user->display_name ?: $user->user_login ) . "\n\n";
		$message .= sprintf( __( 'The following items have missing or invalid documents:', 'tainacan-document-checker' ) ) . "\n\n";
		
		if ( $is_html ) {
			$message = '<p>' . nl2br( $message ) . '</p><ul>';
			foreach ( $items as $item ) {
				$message .= '<li>';
				$message .= '<strong>' . esc_html( $item['title'] ?? 'Item #' . $item['item_id'] ) . '</strong><br>';
				if ( ! empty( $item['missing_documents'] ) ) {
					$message .= __( 'Missing:', 'tainacan-document-checker' ) . ' ' . esc_html( implode( ', ', $item['missing_documents'] ) ) . '<br>';
				}
				if ( ! empty( $item['invalid_documents'] ) ) {
					$message .= __( 'Invalid:', 'tainacan-document-checker' ) . ' ' . esc_html( implode( ', ', $item['invalid_documents'] ) ) . '<br>';
				}
				if ( ! empty( $item['url'] ) ) {
					$message .= '<a href="' . esc_url( $item['url'] ) . '">' . __( 'Edit Item', 'tainacan-document-checker' ) . '</a>';
				}
				$message .= '</li>';
			}
			$message .= '</ul>';
		} else {
			foreach ( $items as $item ) {
				$message .= '• ' . ( $item['title'] ?? 'Item #' . $item['item_id'] ) . "\n";
				if ( ! empty( $item['missing_documents'] ) ) {
					$message .= '  ' . __( 'Missing:', 'tainacan-document-checker' ) . ' ' . implode( ', ', $item['missing_documents'] ) . "\n";
				}
				if ( ! empty( $item['invalid_documents'] ) ) {
					$message .= '  ' . __( 'Invalid:', 'tainacan-document-checker' ) . ' ' . implode( ', ', $item['invalid_documents'] ) . "\n";
				}
				if ( ! empty( $item['url'] ) ) {
					$message .= '  ' . __( 'Edit:', 'tainacan-document-checker' ) . ' ' . $item['url'] . "\n";
				}
				$message .= "\n";
			}
		}
		
		$message .= "\n" . __( 'Please update these items as soon as possible.', 'tainacan-document-checker' ) . "\n";
		$message .= "\n" . __( 'Thank you,', 'tainacan-document-checker' ) . "\n";
		$message .= get_bloginfo( 'name' );
		
		return $message;
	}

	/**
	 * Get email template.
	 *
	 * @param string $format Email format (text/html).
	 * @param string $type Email type (single/batch).
	 * @return string Email template.
	 */
	private function get_email_template( string $format, string $type ): string {
		$default_text = "Hello {user_name},\n\n";
		$default_text .= "The item '{item_title}' requires attention:\n\n";
		$default_text .= "Missing documents: {missing_documents}\n";
		$default_text .= "Invalid documents: {invalid_documents}\n\n";
		$default_text .= "Please visit {item_url} to update the item.\n\n";
		$default_text .= "Thank you,\n{site_name}";
		
		$default_html = "<p>Hello {user_name},</p>";
		$default_html .= "<p>The item <strong>{item_title}</strong> requires attention:</p>";
		$default_html .= "<ul>";
		$default_html .= "<li><strong>Missing documents:</strong> {missing_documents}</li>";
		$default_html .= "<li><strong>Invalid documents:</strong> {invalid_documents}</li>";
		$default_html .= "</ul>";
		$default_html .= "<p><a href='{item_url}'>Click here to update the item</a></p>";
		$default_html .= "<p>Thank you,<br>{site_name}</p>";
		
		$option_key = 'tcd_email_template_' . $format . '_' . $type;
		
		if ( $format === 'html' ) {
			return get_option( $option_key, $default_html );
		}
		
		return get_option( $option_key, $default_text );
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
			'{item_title}'         => $item_info['title'] ?? 'Item #' . $item_info['id'],
			'{item_id}'            => $item_info['id'],
			'{item_url}'           => $item_info['url'] ?? admin_url( 'post.php?post=' . $item_info['id'] . '&action=edit' ),
			'{missing_documents}'  => ! empty( $result['missing_documents'] ) ? implode( ', ', $result['missing_documents'] ) : __( 'None', 'tainacan-document-checker' ),
			'{invalid_documents}'  => ! empty( $result['invalid_documents'] ) ? implode( ', ', $result['invalid_documents'] ) : __( 'None', 'tainacan-document-checker' ),
			'{found_documents}'    => ! empty( $result['found_documents'] ) ? implode( ', ', $result['found_documents'] ) : __( 'None', 'tainacan-document-checker' ),
			'{site_name}'          => get_bloginfo( 'name' ),
			'{site_url}'           => get_site_url(),
			'{date}'               => wp_date( get_option( 'date_format' ) ),
			'{time}'               => wp_date( get_option( 'time_format' ) ),
		);

		return str_replace( array_keys( $placeholders ), array_values( $placeholders ), $template );
	}

	/**
	 * Prepare email headers.
	 *
	 * @return array Email headers.
	 */
	private function prepare_headers(): array {
		$headers = array();
		
		// From header
		$from_email = get_option( 'tcd_smtp_from_email', get_option( 'admin_email' ) );
		$from_name = get_option( 'tcd_smtp_from_name', get_bloginfo( 'name' ) );
		
		if ( is_email( $from_email ) ) {
			$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
		}
		
		// Content type
		if ( get_option( 'tcd_email_html', false ) ) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
		} else {
			$headers[] = 'Content-Type: text/plain; charset=UTF-8';
		}
		
		return $headers;
	}

	/**
	 * Configure PHPMailer for SMTP.
	 *
	 * @param PHPMailer $phpmailer PHPMailer instance.
	 * @return void
	 */
	public function configure_smtp( $phpmailer ): void {
		$phpmailer->isSMTP();
		$phpmailer->Host = get_option( 'tcd_smtp_host', '' );
		$phpmailer->Port = (int) get_option( 'tcd_smtp_port', 587 );
		$phpmailer->SMTPSecure = get_option( 'tcd_smtp_encryption', 'tls' );
		
		if ( get_option( 'tcd_smtp_auth', true ) ) {
			$phpmailer->SMTPAuth = true;
			$phpmailer->Username = get_option( 'tcd_smtp_username', '' );
			// Decode password if it's base64 encoded
			$password = get_option( 'tcd_smtp_password', '' );
			if ( ! empty( $password ) && base64_encode( base64_decode( $password ) ) === $password ) {
				$password = base64_decode( $password );
			}
			$phpmailer->Password = $password;
		}
		
		// Debug mode
		if ( get_option( 'tcd_debug_mode', false ) ) {
			$phpmailer->SMTPDebug = 2;
			$phpmailer->Debugoutput = function( $str, $level ) {
				error_log( 'TCD SMTP Debug [' . $level . ']: ' . $str );
			};
		}
	}

	/**
	 * Log email sending attempt.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $item_id Item ID (0 for batch).
	 * @param string $type Email type.
	 * @param string $subject Email subject.
	 * @param string $status Status (sent/failed).
	 * @return void
	 */
	private function log_email( int $user_id, int $item_id, string $type, string $subject, string $status ): void {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'tainacan_document_emails';
		
		$wpdb->insert(
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
		
		if ( get_option( 'tcd_debug_mode', false ) ) {
			error_log( sprintf( 
				'TCD Email Log: %s email to user %d for item %d - Status: %s', 
				$type, 
				$user_id, 
				$item_id, 
				$status 
			) );
		}
	}

	/**
	 * Get email logs.
	 *
	 * @param array $args Query arguments.
	 * @return array Email logs.
	 */
	public function get_email_logs( array $args = array() ): array {
		global $wpdb;
		
		$defaults = array(
			'user_id' => 0,
			'item_id' => 0,
			'status'  => '',
			'limit'   => 50,
			'offset'  => 0,
		);
		
		$args = wp_parse_args( $args, $defaults );
		$table_name = $wpdb->prefix . 'tainacan_document_emails';
		
		$where = array( '1=1' );
		
		if ( $args['user_id'] ) {
			$where[] = $wpdb->prepare( 'user_id = %d', $args['user_id'] );
		}
		
		if ( $args['item_id'] ) {
			$where[] = $wpdb->prepare( 'item_id = %d', $args['item_id'] );
		}
		
		if ( $args['status'] ) {
			$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}
		
		$sql = "SELECT * FROM $table_name WHERE " . implode( ' AND ', $where );
		$sql .= " ORDER BY sent_date DESC";
		$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $args['limit'], $args['offset'] );
		
		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Test email configuration.
	 *
	 * @param string $test_email Email address to send test to.
	 * @return bool True if test email sent successfully.
	 */
	public function send_test_email( string $test_email ): bool {
		if ( ! is_email( $test_email ) ) {
			return false;
		}
		
		$subject = __( 'Test Email - Tainacan Document Checker', 'tainacan-document-checker' );
		$message = __( 'This is a test email from Tainacan Document Checker plugin.', 'tainacan-document-checker' ) . "\n\n";
		$message .= __( 'If you received this email, your email configuration is working correctly.', 'tainacan-document-checker' ) . "\n\n";
		$message .= __( 'Configuration details:', 'tainacan-document-checker' ) . "\n";
		$message .= '- ' . __( 'SMTP Enabled:', 'tainacan-document-checker' ) . ' ' . ( get_option( 'tcd_smtp_enabled', false ) ? 'Yes' : 'No' ) . "\n";
		$message .= '- ' . __( 'HTML Emails:', 'tainacan-document-checker' ) . ' ' . ( get_option( 'tcd_email_html', false ) ? 'Yes' : 'No' ) . "\n";
		$message .= '- ' . __( 'From Email:', 'tainacan-document-checker' ) . ' ' . get_option( 'tcd_smtp_from_email', get_option( 'admin_email' ) ) . "\n";
		
		$headers = $this->prepare_headers();
		
		// Configure SMTP if enabled
		if ( get_option( 'tcd_smtp_enabled', false ) ) {
			add_action( 'phpmailer_init', array( $this, 'configure_smtp' ) );
		}
		
		$sent = wp_mail( $test_email, $subject, $message, $headers );
		
		// Remove SMTP configuration
		if ( get_option( 'tcd_smtp_enabled', false ) ) {
			remove_action( 'phpmailer_init', array( $this, 'configure_smtp' ) );
		}
		
		return $sent;
	}
}
