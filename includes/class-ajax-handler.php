<?php
/**
 * Ajax Handler Class (Corrigido para JSON)
 *
 * @package TainacanDocumentChecker
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles AJAX requests for document checking with email notifications.
 *
 * @since 1.0.0
 */
class TCD_Ajax_Handler {

	/**
	 * Document checker instance.
	 *
	 * @var TCD_Document_Checker
	 */
	private $document_checker;

	/**
	 * Email handler instance.
	 *
	 * @var TCD_Email_Handler
	 */
	private $email_handler;

	/**
	 * Constructor.
	 *
	 * @param TCD_Document_Checker $document_checker Document checker instance.
	 * @param TCD_Email_Handler    $email_handler Email handler instance.
	 */
	public function __construct( $document_checker, $email_handler ) {
		$this->document_checker = $document_checker;
		$this->email_handler = $email_handler;
	}

	/**
	 * Initialize AJAX handlers.
	 *
	 * @return void
	 */
	public function init() {
		// Single check.
		add_action( 'wp_ajax_tcd_check_single_item', array( $this, 'handle_single_check' ) );
		
		// Batch check.
		add_action( 'wp_ajax_tcd_check_batch', array( $this, 'handle_batch_check' ) );
		
		// Get item history.
		add_action( 'wp_ajax_tcd_get_item_history', array( $this, 'handle_get_history' ) );
		
		// Clear cache.
		add_action( 'wp_ajax_tcd_clear_cache', array( $this, 'handle_clear_cache' ) );
		
		// Send email notifications.
		add_action( 'wp_ajax_tcd_send_notifications', array( $this, 'handle_send_notifications' ) );
		
		// Test connection (for debugging).
		add_action( 'wp_ajax_tcd_test_connection', array( $this, 'handle_test_connection' ) );
	}

	/**
	 * Clean output buffer before JSON response.
	 *
	 * @return void
	 */
	private function clean_output_buffer() {
		// Clean any output that might interfere with JSON
		if ( ob_get_level() ) {
			ob_clean();
		}
		
		// Prevent any additional output
		@ini_set( 'display_errors', 0 );
	}

	/**
	 * Handle test connection request (for debugging).
	 *
	 * @return void
	 */
	public function handle_test_connection() {
		$this->clean_output_buffer();
		
		// Verify nonce.
		if ( ! check_ajax_referer( 'tcd_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'tainacan-document-checker' ) );
		}

		// Check capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'tainacan-document-checker' ) );
		}

		// Log test connection
		if ( get_option( 'tcd_debug_mode', false ) ) {
			error_log( 'TCD Debug - AJAX connection test successful' );
		}

		wp_send_json_success( array(
			'message' => __( 'AJAX connection working correctly.', 'tainacan-document-checker' ),
			'timestamp' => current_time( 'mysql' ),
			'user_id' => get_current_user_id(),
			'ajax_url' => admin_url( 'admin-ajax.php' )
		) );
	}

	/**
	 * Handle single item check.
	 *
	 * @return void
	 */
	public function handle_single_check() {
		$this->clean_output_buffer();
		
		// Verify nonce.
		if ( ! check_ajax_referer( 'tcd_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'tainacan-document-checker' ) );
		}

		// Check capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'tainacan-document-checker' ) );
		}

		// Validate item ID.
		$item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		$send_email = isset( $_POST['send_email'] ) && $_POST['send_email'] === '1';
		
		if ( ! $item_id ) {
			wp_send_json_error( __( 'Invalid item ID.', 'tainacan-document-checker' ) );
		}

		// Debug log
		if ( get_option( 'tcd_debug_mode', false ) ) {
			error_log( 'TCD Debug - Single check: Item ID: ' . $item_id . ', Send email: ' . ( $send_email ? 'Yes' : 'No' ) );
		}

		try {
			// Perform check.
			$result = $this->document_checker->check_item_documents( $item_id );
			
			// Format response.
			if ( 'error' === $result['status'] ) {
				wp_send_json_error( $result['message'] ?? __( 'Check failed.', 'tainacan-document-checker' ) );
			}

			$response_data = $this->format_single_result( $result );

			// Send email notification if requested and emails are enabled
			if ( $send_email && get_option( 'tcd_email_enabled', false ) && 'incomplete' === $result['status'] ) {
				$user_id = get_post_field( 'post_author', $item_id );
				
				if ( $user_id ) {
					$email_sent = $this->email_handler->send_document_notification( $result, (int) $user_id );
					$response_data['email_sent'] = $email_sent;
					$response_data['email_message'] = $email_sent 
						? __( 'Email notification sent successfully.', 'tainacan-document-checker' )
						: __( 'Failed to send email notification.', 'tainacan-document-checker' );
				} else {
					$response_data['email_sent'] = false;
					$response_data['email_message'] = __( 'Could not find user associated with this item.', 'tainacan-document-checker' );
				}
			} elseif ( $send_email && ! get_option( 'tcd_email_enabled', false ) ) {
				$response_data['email_message'] = __( 'Email notifications are not enabled.', 'tainacan-document-checker' );
			}

			wp_send_json_success( $response_data );
			
		} catch ( Exception $e ) {
			if ( get_option( 'tcd_debug_mode', false ) ) {
				error_log( 'TCD Debug - Single check exception: ' . $e->getMessage() );
			}
			wp_send_json_error( __( 'An unexpected error occurred.', 'tainacan-document-checker' ) );
		}
	}

	/**
	 * Handle batch check.
	 *
	 * @return void
	 */
	public function handle_batch_check() {
		$this->clean_output_buffer();
		
		// Verify nonce.
		if ( ! check_ajax_referer( 'tcd_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'tainacan-document-checker' ) );
		}

		// Check capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'tainacan-document-checker' ) );
		}

		// Validate parameters.
		$collection_id = isset( $_POST['collection_id'] ) ? absint( $_POST['collection_id'] ) : 0;
		$page          = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page      = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20;
		$send_emails   = isset( $_POST['send_emails'] ) && $_POST['send_emails'] === '1';
		
		if ( ! $collection_id ) {
			wp_send_json_error( __( 'Invalid collection ID.', 'tainacan-document-checker' ) );
		}

		// Ensure reasonable limits.
		$per_page = min( max( $per_page, 1 ), 100 );

		try {
			// Perform batch check.
			$result = $this->document_checker->check_collection_documents( $collection_id, $page, $per_page );
			
			// Format response.
			if ( isset( $result['status'] ) && 'error' === $result['status'] ) {
				wp_send_json_error( $result['message'] ?? __( 'Batch check failed.', 'tainacan-document-checker' ) );
			}

			$response_data = $this->format_batch_result( $result );

			// Send batch email notifications if requested and on the last page
			if ( $send_emails && get_option( 'tcd_email_enabled', false ) && $result['page'] >= $result['total_pages'] ) {
				$email_stats = $this->email_handler->send_batch_notifications( $result );
				$response_data['email_stats'] = $email_stats;
				$response_data['email_message'] = sprintf(
					/* translators: 1: emails sent, 2: emails failed */
					__( 'Email notifications: %1$d sent, %2$d failed.', 'tainacan-document-checker' ),
					$email_stats['emails_sent'],
					$email_stats['emails_failed']
				);
			} elseif ( $send_emails && ! get_option( 'tcd_email_enabled', false ) ) {
				$response_data['email_message'] = __( 'Email notifications are not enabled.', 'tainacan-document-checker' );
			}

			wp_send_json_success( $response_data );
			
		} catch ( Exception $e ) {
			if ( get_option( 'tcd_debug_mode', false ) ) {
				error_log( 'TCD Debug - Batch check exception: ' . $e->getMessage() );
			}
			wp_send_json_error( __( 'An unexpected error occurred during batch check.', 'tainacan-document-checker' ) );
		}
	}

	/**
	 * Handle get item history request.
	 *
	 * @return void
	 */
	public function handle_get_history() {
		$this->clean_output_buffer();
		
		// Verify nonce.
		if ( ! check_ajax_referer( 'tcd_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'tainacan-document-checker' ) );
		}

		// Check capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'tainacan-document-checker' ) );
		}

		// Validate item ID.
		$item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		
		if ( ! $item_id ) {
			wp_send_json_error( __( 'Invalid item ID.', 'tainacan-document-checker' ) );
		}

		try {
			// Get history.
			$history = $this->document_checker->get_item_history( $item_id );
			
			wp_send_json_success( $this->format_history( $history ) );
			
		} catch ( Exception $e ) {
			if ( get_option( 'tcd_debug_mode', false ) ) {
				error_log( 'TCD Debug - Get history exception: ' . $e->getMessage() );
			}
			wp_send_json_error( __( 'Error retrieving history.', 'tainacan-document-checker' ) );
		}
	}

	/**
	 * Handle clear cache request.
	 *
	 * @return void
	 */
	public function handle_clear_cache() {
		$this->clean_output_buffer();
		
		// Verify nonce.
		if ( ! check_ajax_referer( 'tcd_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'tainacan-document-checker' ) );
		}

		// Check capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'tainacan-document-checker' ) );
		}

		try {
			// Clear all plugin transients.
			$cleared_count = $this->document_checker->clear_all_caches();

			wp_send_json_success( 
				sprintf(
					/* translators: %d: number of cache entries cleared */
					__( 'Cache cleared successfully. %d entries removed.', 'tainacan-document-checker' ),
					$cleared_count
				)
			);
			
		} catch ( Exception $e ) {
			if ( get_option( 'tcd_debug_mode', false ) ) {
				error_log( 'TCD Debug - Clear cache exception: ' . $e->getMessage() );
			}
			wp_send_json_error( __( 'Error clearing cache.', 'tainacan-document-checker' ) );
		}
	}

	/**
	 * Handle send email notifications request.
	 *
	 * @return void
	 */
	public function handle_send_notifications() {
		$this->clean_output_buffer();
		
		// Verify nonce.
		if ( ! check_ajax_referer( 'tcd_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'tainacan-document-checker' ) );
		}

		// Check capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'tainacan-document-checker' ) );
		}

		// Check if emails are enabled
		if ( ! get_option( 'tcd_email_enabled', false ) ) {
			wp_send_json_error( __( 'Email notifications are not enabled.', 'tainacan-document-checker' ) );
		}

		$collection_id = isset( $_POST['collection_id'] ) ? absint( $_POST['collection_id'] ) : 0;
		$item_ids = isset( $_POST['item_ids'] ) && is_array( $_POST['item_ids'] ) ? array_map( 'absint', $_POST['item_ids'] ) : array();
		
		if ( ! $collection_id && empty( $item_ids ) ) {
			wp_send_json_error( __( 'Please specify either a collection ID or item IDs.', 'tainacan-document-checker' ) );
		}

		try {
			$stats = array(
				'emails_sent'   => 0,
				'emails_failed' => 0,
				'users_notified' => array(),
			);

			if ( $collection_id ) {
				// Send notifications for entire collection
				$batch_result = $this->document_checker->check_collection_documents( $collection_id, 1, 100 );
				$stats = $this->email_handler->send_batch_notifications( $batch_result );
			} else {
				// Send notifications for specific items
				foreach ( $item_ids as $item_id ) {
					if ( ! $item_id ) continue;
					
					$result = $this->document_checker->check_item_documents( $item_id );
					
					if ( 'incomplete' === $result['status'] ) {
						$user_id = get_post_field( 'post_author', $item_id );
						
						if ( $user_id ) {
							$sent = $this->email_handler->send_document_notification( $result, (int) $user_id );
							
							if ( $sent ) {
								$stats['emails_sent']++;
								if ( ! in_array( $user_id, $stats['users_notified'], true ) ) {
									$stats['users_notified'][] = $user_id;
								}
							} else {
								$stats['emails_failed']++;
							}
						}
					}
				}
			}

			wp_send_json_success( array(
				'stats' => $stats,
				'message' => sprintf(
					/* translators: 1: emails sent, 2: emails failed, 3: users notified */
					__( 'Email notifications: %1$d sent, %2$d failed. %3$d users notified.', 'tainacan-document-checker' ),
					$stats['emails_sent'],
					$stats['emails_failed'],
					count( $stats['users_notified'] )
				),
			) );
			
		} catch ( Exception $e ) {
			if ( get_option( 'tcd_debug_mode', false ) ) {
				error_log( 'TCD Debug - Send notifications exception: ' . $e->getMessage() );
			}
			wp_send_json_error( __( 'Error sending notifications.', 'tainacan-document-checker' ) );
		}
	}

	/**
	 * Format single check result for response.
	 *
	 * @param array $result Check result.
	 * @return array Formatted result.
	 */
	private function format_single_result( $result ) {
		$formatted = array(
			'status'  => $result['status'] ?? 'error',
			'item_id' => $result['item_id'] ?? 0,
			'html'    => $this->render_single_result_html( $result ),
			'summary' => array(
				'total_attachments' => $result['total_attachments'] ?? 0,
				'found_documents'   => count( $result['found_documents'] ?? array() ),
				'missing_documents' => count( $result['missing_documents'] ?? array() ),
				'invalid_documents' => count( $result['invalid_documents'] ?? array() ),
			),
			'attachment_files' => $result['attachment_files'] ?? array(),
		);

		if ( ! empty( $result['debug'] ) ) {
			$formatted['debug'] = $result['debug'];
		}

		return $formatted;
	}

	/**
	 * Format batch check result for response.
	 *
	 * @param array $result Batch check result.
	 * @return array Formatted result.
	 */
	private function format_batch_result( $result ) {
		// Calculate actual progress based on items processed
		$progress = 0;
		if ( isset( $result['total_pages'] ) && $result['total_pages'] > 0 ) {
			$progress = round( ( ( $result['page'] ?? 1 ) / $result['total_pages'] ) * 100 );
		}

		return array(
			'page'         => $result['page'] ?? 1,
			'total_pages'  => $result['total_pages'] ?? 1,
			'total_items'  => $result['total_items'] ?? 0,
			'has_more'     => ( $result['page'] ?? 1 ) < ( $result['total_pages'] ?? 1 ),
			'summary'      => $result['summary'] ?? array( 'complete' => 0, 'incomplete' => 0, 'error' => 0 ),
			'html'         => $this->render_batch_result_html( $result ),
			'progress'     => $progress,
		);
	}

	/**
	 * Format history for response.
	 *
	 * @param array $history History records.
	 * @return array Formatted history.
	 */
	private function format_history( $history ) {
		return array(
			'count' => count( $history ),
			'html'  => $this->render_history_html( $history ),
		);
	}

	/**
	 * Render single result HTML.
	 *
	 * @param array $result Check result.
	 * @return string HTML output.
	 */
	private function render_single_result_html( $result ) {
		// Start output buffering with error handling
		if ( ob_start() === false ) {
			return '<div class="notice notice-error"><p>' . __( 'Unable to render result.', 'tainacan-document-checker' ) . '</p></div>';
		}
		
		try {
			?>
			<div class="tcd-result <?php echo esc_attr( 'tcd-status-' . ( $result['status'] ?? 'error' ) ); ?>">
				<h3><?php esc_html_e( 'Check Result', 'tainacan-document-checker' ); ?></h3>
				
				<div class="tcd-result-summary">
					<p><strong><?php esc_html_e( 'Item ID:', 'tainacan-document-checker' ); ?></strong> <?php echo esc_html( $result['item_id'] ?? 'Unknown' ); ?></p>
					<p><strong><?php esc_html_e( 'Status:', 'tainacan-document-checker' ); ?></strong> 
						<span class="tcd-status-badge tcd-status-<?php echo esc_attr( $result['status'] ?? 'error' ); ?>"><?php echo esc_html( ucfirst( $result['status'] ?? 'Error' ) ); ?></span>
					</p>
					<p><strong><?php esc_html_e( 'Total Attachments:', 'tainacan-document-checker' ); ?></strong> <?php echo esc_html( $result['total_attachments'] ?? 0 ); ?></p>
				</div>
				
				<?php if ( ! empty( $result['attachment_files'] ) ) : ?>
					<div class="tcd-attachment-files">
						<h4><?php esc_html_e( 'Attachment Files', 'tainacan-document-checker' ); ?></h4>
						<ul>
							<?php foreach ( $result['attachment_files'] as $file ) : ?>
								<li><?php echo esc_html( $file ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
				
				<?php if ( ! empty( $result['found_documents'] ) ) : ?>
					<div class="tcd-found-documents">
						<h4><?php esc_html_e( 'Matched Required Documents', 'tainacan-document-checker' ); ?></h4>
						<ul>
							<?php foreach ( $result['found_documents'] as $doc ) : ?>
								<li class="tcd-doc-found"><?php echo esc_html( $doc ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
				
				<?php if ( ! empty( $result['missing_documents'] ) ) : ?>
					<div class="tcd-missing-documents">
						<h4><?php esc_html_e( 'Missing Documents', 'tainacan-document-checker' ); ?></h4>
						<ul>
							<?php foreach ( $result['missing_documents'] as $doc ) : ?>
								<li class="tcd-doc-missing"><?php echo esc_html( $doc ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
				
				<?php if ( ! empty( $result['invalid_documents'] ) ) : ?>
					<div class="tcd-invalid-documents">
						<h4><?php esc_html_e( 'Invalid Documents', 'tainacan-document-checker' ); ?></h4>
						<ul>
							<?php foreach ( $result['invalid_documents'] as $doc ) : ?>
								<li class="tcd-doc-missing"><?php echo esc_html( $doc ); ?></li>
							<?php endforeach; ?>
						</ul>
						<p class="description"><?php esc_html_e( 'These documents do not match any required document names.', 'tainacan-document-checker' ); ?></p>
					</div>
				<?php endif; ?>
				
				<?php if ( get_option( 'tcd_email_enabled', false ) && ( $result['status'] ?? '' ) === 'incomplete' ) : ?>
					<div class="tcd-email-actions">
						<h4><?php esc_html_e( 'Email Notification', 'tainacan-document-checker' ); ?></h4>
						<button type="button" class="button button-secondary" onclick="window.TCD.sendSingleNotification(<?php echo esc_js( $result['item_id'] ?? 0 ); ?>)">
							<?php esc_html_e( 'Send Email to User', 'tainacan-document-checker' ); ?>
						</button>
						<div id="tcd-email-result-<?php echo esc_attr( $result['item_id'] ?? 0 ); ?>" style="margin-top: 10px;"></div>
					</div>
				<?php endif; ?>
				
				<?php if ( ! empty( $result['debug'] ) ) : ?>
					<div class="tcd-debug">
						<h4><?php esc_html_e( 'Debug Information', 'tainacan-document-checker' ); ?></h4>
						<pre><?php echo esc_html( wp_json_encode( $result['debug'], JSON_PRETTY_PRINT ) ); ?></pre>
					</div>
				<?php endif; ?>
			</div>
			<?php
			
			$content = ob_get_clean();
			return $content !== false ? $content : '<div class="notice notice-error"><p>' . __( 'Error rendering result.', 'tainacan-document-checker' ) . '</p></div>';
			
		} catch ( Exception $e ) {
			ob_end_clean();
			if ( get_option( 'tcd_debug_mode', false ) ) {
				error_log( 'TCD Debug - Render single result exception: ' . $e->getMessage() );
			}
			return '<div class="notice notice-error"><p>' . __( 'Error rendering result.', 'tainacan-document-checker' ) . '</p></div>';
		}
	}

	/**
	 * Render batch result HTML.
	 *
	 * @param array $result Batch check result.
	 * @return string HTML output.
	 */
	private function render_batch_result_html( $result ) {
		if ( ob_start() === false ) {
			return '<div class="notice notice-error"><p>' . __( 'Unable to render batch result.', 'tainacan-document-checker' ) . '</p></div>';
		}
		
		try {
			$summary = $result['summary'] ?? array( 'complete' => 0, 'incomplete' => 0, 'error' => 0 );
			?>
			<div class="tcd-batch-result">
				<h3><?php esc_html_e( 'Batch Check Results', 'tainacan-document-checker' ); ?></h3>
				
				<div class="tcd-batch-summary">
					<p>
						<?php
						printf(
							/* translators: 1: current page, 2: total pages, 3: total items */
							esc_html__( 'Page %1$d of %2$d (Total items: %3$d)', 'tainacan-document-checker' ),
							esc_html( $result['page'] ?? 1 ),
							esc_html( $result['total_pages'] ?? 1 ),
							esc_html( $result['total_items'] ?? 0 )
						);
						?>
					</p>
					
					<div class="tcd-summary-stats">
						<span class="tcd-stat tcd-stat-complete">
							<?php printf( esc_html__( 'Complete: %d', 'tainacan-document-checker' ), esc_html( $summary['complete'] ) ); ?>
						</span>
						<span class="tcd-stat tcd-stat-incomplete">
							<?php printf( esc_html__( 'Incomplete: %d', 'tainacan-document-checker' ), esc_html( $summary['incomplete'] ) ); ?>
						</span>
						<span class="tcd-stat tcd-stat-error">
							<?php printf( esc_html__( 'Errors: %d', 'tainacan-document-checker' ), esc_html( $summary['error'] ) ); ?>
						</span>
					</div>
				</div>
				
				<?php if ( ! empty( $result['items_checked'] ) ) : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Item ID', 'tainacan-document-checker' ); ?></th>
								<th><?php esc_html_e( 'Title', 'tainacan-document-checker' ); ?></th>
								<th><?php esc_html_e( 'Status', 'tainacan-document-checker' ); ?></th>
								<th><?php esc_html_e( 'Issues', 'tainacan-document-checker' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $result['items_checked'] as $item ) : ?>
								<tr>
									<td><?php echo esc_html( $item['id'] ?? 'Unknown' ); ?></td>
									<td><?php echo esc_html( $item['title'] ?? 'Unknown' ); ?></td>
									<td>
										<span class="tcd-status-badge tcd-status-<?php echo esc_attr( $item['status'] ?? 'error' ); ?>">
											<?php echo esc_html( ucfirst( $item['status'] ?? 'Error' ) ); ?>
										</span>
									</td>
									<td>
										<?php 
										$has_issues = false;
										?>
										<?php if ( ! empty( $item['missing_documents'] ) ) : ?>
											<strong><?php esc_html_e( 'Missing:', 'tainacan-document-checker' ); ?></strong> 
											<span style="color: #d63638;"><?php echo esc_html( implode( ', ', $item['missing_documents'] ) ); ?></span>
											<?php $has_issues = true; ?>
										<?php endif; ?>
										
										<?php if ( ! empty( $item['invalid_documents'] ) ) : ?>
											<?php if ( $has_issues ) : ?><br><?php endif; ?>
											<strong><?php esc_html_e( 'Invalid:', 'tainacan-document-checker' ); ?></strong> 
											<span style="color: #d63638;"><?php echo esc_html( implode( ', ', $item['invalid_documents'] ) ); ?></span>
											<?php $has_issues = true; ?>
										<?php endif; ?>
										
										<?php if ( ! $has_issues ) : ?>
											<span style="color: #46b450;">✓ <?php esc_html_e( 'All documents valid', 'tainacan-document-checker' ); ?></span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<div class="notice notice-warning">
						<p><?php esc_html_e( 'No items were processed in this batch.', 'tainacan-document-checker' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
			<?php
			
			$content = ob_get_clean();
			return $content !== false ? $content : '<div class="notice notice-error"><p>' . __( 'Error rendering batch result.', 'tainacan-document-checker' ) . '</p></div>';
			
		} catch ( Exception $e ) {
			ob_end_clean();
			if ( get_option( 'tcd_debug_mode', false ) ) {
				error_log( 'TCD Debug - Render batch result exception: ' . $e->getMessage() );
			}
			return '<div class="notice notice-error"><p>' . __( 'Error rendering batch result.', 'tainacan-document-checker' ) . '</p></div>';
		}
	}

	/**
	 * Render history HTML.
	 *
	 * @param array $history History records.
	 * @return string HTML output.
	 */
	private function render_history_html( $history ) {
		if ( ob_start() === false ) {
			return '<div class="notice notice-error"><p>' . __( 'Unable to render history.', 'tainacan-document-checker' ) . '</p></div>';
		}
		
		try {
			?>
			<div class="tcd-history-result">
				<h3><?php esc_html_e( 'Check History', 'tainacan-document-checker' ); ?></h3>
				
				<?php if ( empty( $history ) ) : ?>
					<p><?php esc_html_e( 'No history found for this item.', 'tainacan-document-checker' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date', 'tainacan-document-checker' ); ?></th>
								<th><?php esc_html_e( 'Status', 'tainacan-document-checker' ); ?></th>
								<th><?php esc_html_e( 'Missing Documents', 'tainacan-document-checker' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $history as $record ) : ?>
								<tr>
									<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $record['check_date'] ?? 'now' ) ) ); ?></td>
									<td>
										<span class="tcd-status-badge tcd-status-<?php echo esc_attr( $record['check_status'] ?? 'error' ); ?>">
											<?php echo esc_html( ucfirst( $record['check_status'] ?? 'Error' ) ); ?>
										</span>
									</td>
									<td>
										<?php 
										$missing_docs = maybe_unserialize( $record['missing_documents'] ?? '' );
										if ( ! empty( $missing_docs ) && is_array( $missing_docs ) ) : ?>
											<?php echo esc_html( implode( ', ', $missing_docs ) ); ?>
										<?php else : ?>
											-
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
			<?php
			
			$content = ob_get_clean();
			return $content !== false ? $content : '<div class="notice notice-error"><p>' . __( 'Error rendering history.', 'tainacan-document-checker' ) . '</p></div>';
			
		} catch ( Exception $e ) {
			ob_end_clean();
			if ( get_option( 'tcd_debug_mode', false ) ) {
				error_log( 'TCD Debug - Render history exception: ' . $e->getMessage() );
			}
			return '<div class="notice notice-error"><p>' . __( 'Error rendering history.', 'tainacan-document-checker' ) . '</p></div>';
		}
	}
}