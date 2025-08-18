<?php
/**
 * Ajax Handler Class (Updated with Email functionality)
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
	private TCD_Document_Checker $document_checker;

	/**
	 * Email handler instance.
	 *
	 * @var TCD_Email_Handler
	 */
	private TCD_Email_Handler $email_handler;

	/**
	 * Constructor.
	 *
	 * @param TCD_Document_Checker $document_checker Document checker instance.
	 * @param TCD_Email_Handler    $email_handler Email handler instance.
	 */
	public function __construct( TCD_Document_Checker $document_checker, TCD_Email_Handler $email_handler ) {
		$this->document_checker = $document_checker;
		$this->email_handler = $email_handler;
	}

	/**
	 * Initialize AJAX handlers.
	 *
	 * @return void
	 */
	public function init(): void {
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
	}

	/**
	 * Handle single item check.
	 *
	 * @return void
	 */
	public function handle_single_check(): void {
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
		$send_email = isset( $_POST['send_email'] ) && $_POST['send_email'];
		
		if ( ! $item_id ) {
			wp_send_json_error( __( 'Invalid item ID.', 'tainacan-document-checker' ) );
		}

		// Perform check.
		$result = $this->document_checker->check_item_documents( $item_id );
		
		// Format response.
		if ( 'error' === $result['status'] ) {
			wp_send_json_error( $result['message'] );
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
		}

		wp_send_json_success( $response_data );
	}

	/**
	 * Handle batch check.
	 *
	 * @return void
	 */
	public function handle_batch_check(): void {
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
		$send_emails   = isset( $_POST['send_emails'] ) && $_POST['send_emails'];
		
		if ( ! $collection_id ) {
			wp_send_json_error( __( 'Invalid collection ID.', 'tainacan-document-checker' ) );
		}

		// Ensure reasonable limits.
		$per_page = min( max( $per_page, 1 ), 100 );

		// Log the request for debugging
		if ( get_option( 'tcd_debug_mode', false ) ) {
			error_log( 'TCD Debug - Batch check request: Collection ID: ' . $collection_id . ', Page: ' . $page . ', Per Page: ' . $per_page . ', Send Emails: ' . ( $send_emails ? 'Yes' : 'No' ) );
		}

		// Perform batch check.
		$result = $this->document_checker->check_collection_documents( $collection_id, $page, $per_page );
		
		// Format response.
		if ( isset( $result['status'] ) && 'error' === $result['status'] ) {
			wp_send_json_error( $result['message'] ?? __( 'An error occurred during batch check.', 'tainacan-document-checker' ) );
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
		}

		wp_send_json_success( $response_data );
	}

	/**
	 * Handle get item history request.
	 *
	 * @return void
	 */
	public function handle_get_history(): void {
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

		// Get history.
		$history = $this->document_checker->get_item_history( $item_id );
		
		wp_send_json_success( $this->format_history( $history ) );
	}

	/**
	 * Handle clear cache request.
	 *
	 * @return void
	 */
	public function handle_clear_cache(): void {
		// Verify nonce.
		if ( ! check_ajax_referer( 'tcd_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'tainacan-document-checker' ) );
		}

		// Check capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'tainacan-document-checker' ) );
		}

		// Clear all plugin transients.
		$cleared_count = $this->document_checker->clear_all_caches();

		wp_send_json_success( 
			sprintf(
				/* translators: %d: number of cache entries cleared */
				__( 'Cache cleared successfully. %d entries removed.', 'tainacan-document-checker' ),
				$cleared_count
			)
		);
	}

	/**
	 * Handle send email notifications request.
	 *
	 * @return void
	 */
	public function handle_send_notifications(): void {
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
		$item_ids = isset( $_POST['item_ids'] ) ? array_map( 'absint', $_POST['item_ids'] ) : array();
		
		if ( ! $collection_id && empty( $item_ids ) ) {
			wp_send_json_error( __( 'Please specify either a collection ID or item IDs.', 'tainacan-document-checker' ) );
		}

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
				__( 'Email notifications sent: %1$d successful, %2$d failed. %3$d users notified.', 'tainacan-document-checker' ),
				$stats['emails_sent'],
				$stats['emails_failed'],
				count( $stats['users_notified'] )
			),
		) );
	}

	/**
	 * Format single check result for response.
	 *
	 * @param array $result Check result.
	 * @return array Formatted result.
	 */
	private function format_single_result( array $result ): array {
		$formatted = array(
			'status'  => $result['status'],
			'item_id' => $result['item_id'],
			'html'    => $this->render_single_result_html( $result ),
			'summary' => array(
				'total_attachments' => $result['total_attachments'],
				'found_documents'   => count( $result['found_documents'] ),
				'missing_documents' => count( $result['missing_documents'] ),
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
	private function format_batch_result( array $result ): array {
		// Calculate actual progress based on items processed
		$progress = 0;
		if ( $result['total_pages'] > 0 ) {
			$progress = round( ( $result['page'] / $result['total_pages'] ) * 100 );
		}

		// Log debug info
		if ( get_option( 'tcd_debug_mode', false ) ) {
			error_log( 'TCD Debug - Batch result: ' . print_r( array(
				'page' => $result['page'],
				'total_pages' => $result['total_pages'],
				'total_items' => $result['total_items'],
				'items_checked' => count( $result['items_checked'] ?? array() ),
				'summary' => $result['summary'],
			), true ) );
		}

		return array(
			'page'         => $result['page'],
			'total_pages'  => $result['total_pages'],
			'total_items'  => $result['total_items'],
			'has_more'     => $result['page'] < $result['total_pages'],
			'summary'      => $result['summary'],
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
	private function format_history( array $history ): array {
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
	private function render_single_result_html( array $result ): string {
		ob_start();
		?>
		<div class="tcd-result <?php echo esc_attr( 'tcd-status-' . $result['status'] ); ?>">
			<h3><?php esc_html_e( 'Check Result', 'tainacan-document-checker' ); ?></h3>
			
			<div class="tcd-result-summary">
				<p><strong><?php esc_html_e( 'Item ID:', 'tainacan-document-checker' ); ?></strong> <?php echo esc_html( $result['item_id'] ); ?></p>
				<p><strong><?php esc_html_e( 'Status:', 'tainacan-document-checker' ); ?></strong> 
					<span class="tcd-status-badge tcd-status-<?php echo esc_attr( $result['status'] ); ?>"><?php echo esc_html( ucfirst( $result['status'] ) ); ?></span>
				</p>
				<p><strong><?php esc_html_e( 'Total Attachments:', 'tainacan-document-checker' ); ?></strong> <?php echo esc_html( $result['total_attachments'] ); ?></p>
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
					<p class="description"><?php esc_html_e( 'These documents do not match any required document names and should be removed or renamed.', 'tainacan-document-checker' ); ?></p>
				</div>
			<?php endif; ?>
			
			<?php if ( get_option( 'tcd_email_enabled', false ) && 'incomplete' === $result['status'] ) : ?>
				<div class="tcd-email-actions">
					<h4><?php esc_html_e( 'Email Notification', 'tainacan-document-checker' ); ?></h4>
					<button type="button" class="button button-secondary" onclick="TCD.sendSingleNotification(<?php echo esc_js( $result['item_id'] ); ?>)">
						<?php esc_html_e( 'Send Email to User', 'tainacan-document-checker' ); ?>
					</button>
					<div id="tcd-email-result-<?php echo esc_attr( $result['item_id'] ); ?>" style="margin-top: 10px;"></div>
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
		return ob_get_clean();
	}

	/**
	 * Render batch result HTML.
	 *
	 * @param array $result Batch check result.
	 * @return string HTML output.
	 */
	private function render_batch_result_html( array $result ): string {
		ob_start();
		?>
		<div class="tcd-batch-result">
			<h3><?php esc_html_e( 'Batch Check Results', 'tainacan-document-checker' ); ?></h3>
			
			<div class="tcd-batch-summary">
				<p>
					<?php
					printf(
						/* translators: 1: current page, 2: total pages, 3: total items */
						esc_html__( 'Page %1$d of %2$d (Total items: %3$d)', 'tainacan-document-checker' ),
						esc_html( $result['page'] ),
						esc_html( $result['total_pages'] ),
						esc_html( $result['total_items'] )
					);
					?>
				</p>
				
				<div class="tcd-summary-stats">
					<span class="tcd-stat tcd-stat-complete">
						<?php
						printf(
							/* translators: %d: number of complete items */
							esc_html__( 'Complete: %d', 'tainacan-document-checker' ),
							esc_html( $result['summary']['complete'] )
						);
						?>
					</span>
					<span class="tcd-stat tcd-stat-incomplete">
						<?php
						printf(
							/* translators: %d: number of incomplete items */
							esc_html__( 'Incomplete: %d', 'tainacan-document-checker' ),
							esc_html( $result['summary']['incomplete'] )
						);
						?>
					</span>
					<span class="tcd-stat tcd-stat-error">
						<?php
						printf(
							/* translators: %d: number of errors */
							esc_html__( 'Errors: %d', 'tainacan-document-checker' ),
							esc_html( $result['summary']['error'] )
						);
						?>
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
							<th><?php esc_html_e( 'Attachments', 'tainacan-document-checker' ); ?></th>
							<th><?php esc_html_e( 'Missing/Invalid Documents', 'tainacan-document-checker' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $result['items_checked'] as $item ) : ?>
							<tr>
								<td><?php echo esc_html( $item['id'] ); ?></td>
								<td><?php echo esc_html( $item['title'] ); ?></td>
								<td>
									<span class="tcd-status-badge tcd-status-<?php echo esc_attr( $item['status'] ); ?>">
										<?php echo esc_html( ucfirst( $item['status'] ) ); ?>
									</span>
								</td>
								<td>
									<?php if ( ! empty( $item['attachment_files'] ) ) : ?>
										<?php 
										$valid_docs = $item['found_documents'] ?? array();
										$invalid_docs = $item['invalid_documents'] ?? array();
										?>
										<small>
										<?php foreach ( $item['attachment_files'] as $index => $file ) : ?>
											<?php 
											// Verifica se este arquivo é válido ou inválido
											$is_invalid = false;
											foreach ( $invalid_docs as $invalid ) {
												if ( stripos( $file, $invalid ) !== false || $file === $invalid ) {
													$is_invalid = true;
													break;
												}
											}
											?>
											<span style="color: <?php echo $is_invalid ? '#d63638' : '#46b450'; ?>;">
												<?php echo esc_html( $file ); ?>
											</span>
											<?php if ( $index < count( $item['attachment_files'] ) - 1 ) : ?>, <?php endif; ?>
											<?php if ( $index >= 2 && count( $item['attachment_files'] ) > 3 ) : ?>
												... (<?php echo esc_html( count( $item['attachment_files'] ) ); ?> total)
												<?php break; ?>
											<?php endif; ?>
										<?php endforeach; ?>
										</small>
									<?php else : ?>
										<small><?php esc_html_e( 'No attachments', 'tainacan-document-checker' ); ?></small>
									<?php endif; ?>
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
					<p><?php esc_html_e( 'No items were processed in this batch. This might be due to:', 'tainacan-document-checker' ); ?></p>
					<ul>
						<li><?php esc_html_e( '- Invalid collection ID', 'tainacan-document-checker' ); ?></li>
						<li><?php esc_html_e( '- Empty collection', 'tainacan-document-checker' ); ?></li>
						<li><?php esc_html_e( '- API connection issues', 'tainacan-document-checker' ); ?></li>
						<li><?php esc_html_e( '- Incorrect API URL configuration', 'tainacan-document-checker' ); ?></li>
					</ul>
					<p><?php esc_html_e( 'Please check the plugin settings and enable debug mode for more information.', 'tainacan-document-checker' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render history HTML.
	 *
	 * @param array $history History records.
	 * @return string HTML output.
	 */
	private function render_history_html( array $history ): string {
		ob_start();
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
								<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $record['check_date'] ) ) ); ?></td>
								<td>
									<span class="tcd-status-badge tcd-status-<?php echo esc_attr( $record['check_status'] ); ?>">
										<?php echo esc_html( ucfirst( $record['check_status'] ) ); ?>
									</span>
								</td>
								<td>
									<?php if ( ! empty( $record['missing_documents'] ) && is_array( $record['missing_documents'] ) ) : ?>
										<?php echo esc_html( implode( ', ', $record['missing_documents'] ) ); ?>
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
		return ob_get_clean();
	}
}