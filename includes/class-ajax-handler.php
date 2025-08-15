<?php
/**
 * AJAX Handler for Tainacan Document Checker
 *
 * @package TainacanDocumentChecker
 */

declare(strict_types=1);

/**
 * Handles AJAX requests for the document checker.
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
		add_action( 'wp_ajax_tcd_check_single_item', array( $this, 'handle_single_check' ) );
		add_action( 'wp_ajax_tcd_check_batch', array( $this, 'handle_batch_check' ) );
		add_action( 'wp_ajax_tcd_get_item_history', array( $this, 'handle_get_history' ) );
		add_action( 'wp_ajax_tcd_clear_cache', array( $this, 'handle_clear_cache' ) );
		add_action( 'wp_ajax_tcd_send_test_email', array( $this, 'handle_test_email' ) );
		add_action( 'wp_ajax_tcd_get_email_logs', array( $this, 'handle_get_email_logs' ) );
	}

	/**
	 * Handle single item check request.
	 *
	 * @return void
	 */
	public function handle_single_check(): void {
		// Verify nonce.
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'tcd_ajax_nonce' ) ) {
			wp_send_json_error( __( 'Invalid nonce.', 'tainacan-document-checker' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'tainacan-document-checker' ) );
		}

		$item_id = absint( $_POST['item_id'] ?? 0 );
		$send_email = isset( $_POST['send_email'] ) && $_POST['send_email'] === 'true';

		if ( ! $item_id ) {
			wp_send_json_error( __( 'Invalid item ID.', 'tainacan-document-checker' ) );
		}

		// Perform the check.
		$result = $this->document_checker->check_item_documents( $item_id );

		if ( isset( $result['error'] ) ) {
			wp_send_json_error( $result['error'] );
		}

		// Send email notification if requested and there are issues
		$email_sent = false;
		if ( $send_email && ( ! empty( $result['missing_documents'] ) || ! empty( $result['invalid_documents'] ) ) ) {
			// Get item information
			$item_info = $this->get_item_info( $item_id );
			$email_sent = $this->email_handler->send_single_notification( $result, $item_info );
		}

		// Prepare response with formatted HTML.
		$response = array(
			'result' => $result,
			'html'   => $this->format_single_result( $result ),
			'email_sent' => $email_sent,
		);

		wp_send_json_success( $response );
	}

	/**
	 * Handle batch check request.
	 *
	 * @return void
	 */
	public function handle_batch_check(): void {
		// Verify nonce.
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'tcd_ajax_nonce' ) ) {
			wp_send_json_error( __( 'Invalid nonce.', 'tainacan-document-checker' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'tainacan-document-checker' ) );
		}

		$collection_id = absint( $_POST['collection_id'] ?? 0 );
		$page = absint( $_POST['page'] ?? 1 );
		$per_page = absint( $_POST['per_page'] ?? 10 );
		$send_email = isset( $_POST['send_email'] ) && $_POST['send_email'] === 'true';

		if ( ! $collection_id ) {
			wp_send_json_error( __( 'Invalid collection ID.', 'tainacan-document-checker' ) );
		}

		// Perform the batch check.
		$result = $this->document_checker->check_collection_documents( $collection_id, $page, $per_page );

		if ( isset( $result['error'] ) ) {
			wp_send_json_error( $result['error'] );
		}

		// Send email notifications if requested and there are incomplete items
		$email_results = array( 'sent' => 0, 'failed' => 0 );
		if ( $send_email && ! empty( $result['incomplete_items'] ) ) {
			// Enrich incomplete items with additional info
			$enriched_items = array();
			foreach ( $result['incomplete_items'] as $item ) {
				$item_info = $this->get_item_info( $item['item_id'] );
				$enriched_items[] = array_merge( $item, $item_info );
			}
			
			$email_results = $this->email_handler->send_batch_notification( $enriched_items, $collection_id );
		}

		// Prepare response with formatted HTML.
		$response = array(
			'result' => $result,
			'html'   => $this->format_batch_result( $result ),
			'email_results' => $email_results,
		);

		wp_send_json_success( $response );
	}

	/**
	 * Handle get history request.
	 *
	 * @return void
	 */
	public function handle_get_history(): void {
		// Verify nonce.
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'tcd_ajax_nonce' ) ) {
			wp_send_json_error( __( 'Invalid nonce.', 'tainacan-document-checker' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'tainacan-document-checker' ) );
		}

		$item_id = absint( $_POST['item_id'] ?? 0 );

		if ( ! $item_id ) {
			wp_send_json_error( __( 'Invalid item ID.', 'tainacan-document-checker' ) );
		}

		// Get history.
		$history = $this->document_checker->get_item_history( $item_id );

		// Format history HTML.
		$html = $this->format_history( $history );

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Handle clear cache request.
	 *
	 * @return void
	 */
	public function handle_clear_cache(): void {
		// Verify nonce.
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'tcd_ajax_nonce' ) ) {
			wp_send_json_error( __( 'Invalid nonce.', 'tainacan-document-checker' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'tainacan-document-checker' ) );
		}

		// Clear all transients with our prefix.
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_tcd_%' 
			OR option_name LIKE '_transient_timeout_tcd_%'"
		);

		wp_send_json_success( array( 'message' => __( 'Cache cleared successfully.', 'tainacan-document-checker' ) ) );
	}

	/**
	 * Handle test email request.
	 *
	 * @return void
	 */
	public function handle_test_email(): void {
		// Verify nonce.
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'tcd_ajax_nonce' ) ) {
			wp_send_json_error( __( 'Invalid nonce.', 'tainacan-document-checker' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'tainacan-document-checker' ) );
		}

		$test_email = sanitize_email( $_POST['email'] ?? '' );

		if ( ! is_email( $test_email ) ) {
			wp_send_json_error( __( 'Invalid email address.', 'tainacan-document-checker' ) );
		}

		$sent = $this->email_handler->send_test_email( $test_email );

		if ( $sent ) {
			wp_send_json_success( array( 'message' => __( 'Test email sent successfully.', 'tainacan-document-checker' ) ) );
		} else {
			wp_send_json_error( __( 'Failed to send test email. Please check your configuration.', 'tainacan-document-checker' ) );
		}
	}

	/**
	 * Handle get email logs request.
	 *
	 * @return void
	 */
	public function handle_get_email_logs(): void {
		// Verify nonce.
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'tcd_ajax_nonce' ) ) {
			wp_send_json_error( __( 'Invalid nonce.', 'tainacan-document-checker' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'tainacan-document-checker' ) );
		}

		$args = array(
			'user_id' => absint( $_POST['user_id'] ?? 0 ),
			'item_id' => absint( $_POST['item_id'] ?? 0 ),
			'status'  => sanitize_text_field( $_POST['status'] ?? '' ),
			'limit'   => absint( $_POST['limit'] ?? 50 ),
			'offset'  => absint( $_POST['offset'] ?? 0 ),
		);

		$logs = $this->email_handler->get_email_logs( $args );

		// Format logs HTML.
		$html = $this->format_email_logs( $logs );

		wp_send_json_success( array( 'html' => $html, 'logs' => $logs ) );
	}

	/**
	 * Get item information from Tainacan.
	 *
	 * @param int $item_id Item ID.
	 * @return array Item information.
	 */
	private function get_item_info( int $item_id ): array {
		// Try to get from WordPress post
		$post = get_post( $item_id );
		if ( $post ) {
			return array(
				'id'    => $item_id,
				'title' => $post->post_title ?: 'Item #' . $item_id,
				'url'   => get_permalink( $item_id ) ?: admin_url( 'post.php?post=' . $item_id . '&action=edit' ),
			);
		}

		// Try via API
		$api_url = get_option( 'tcd_api_url', '' );
		if ( ! empty( $api_url ) ) {
			$response = wp_remote_get( $api_url . '/items/' . $item_id );
			if ( ! is_wp_error( $response ) ) {
				$data = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( $data ) {
					return array(
						'id'    => $item_id,
						'title' => $data['title'] ?? 'Item #' . $item_id,
						'url'   => $data['url'] ?? admin_url( 'post.php?post=' . $item_id . '&action=edit' ),
					);
				}
			}
		}

		// Fallback
		return array(
			'id'    => $item_id,
			'title' => 'Item #' . $item_id,
			'url'   => admin_url( 'post.php?post=' . $item_id . '&action=edit' ),
		);
	}

	/**
	 * Format single check result for display.
	 *
	 * @param array $result Check result.
	 * @return string HTML output.
	 */
	private function format_single_result( array $result ): string {
		ob_start();
		?>
		<div class="tcd-result">
			<h3><?php esc_html_e( 'Check Result', 'tainacan-document-checker' ); ?></h3>
			
			<div class="tcd-result-summary">
				<p><strong><?php esc_html_e( 'Item ID:', 'tainacan-document-checker' ); ?></strong> <?php echo esc_html( $result['item_id'] ); ?></p>
				<p><strong><?php esc_html_e( 'Status:', 'tainacan-document-checker' ); ?></strong> 
					<span class="tcd-status tcd-status-<?php echo esc_attr( $result['status'] ); ?>">
						<?php echo esc_html( ucfirst( $result['status'] ) ); ?>
					</span>
				</p>
				<p><strong><?php esc_html_e( 'Total Attachments:', 'tainacan-document-checker' ); ?></strong> <?php echo esc_html( $result['total_attachments'] ); ?></p>
			</div>

			<?php if ( ! empty( $result['found_documents'] ) ) : ?>
				<div class="tcd-documents-found">
					<h4><?php esc_html_e( 'Found Documents', 'tainacan-document-checker' ); ?></h4>
					<ul>
						<?php foreach ( $result['found_documents'] as $doc ) : ?>
							<li class="tcd-doc-found"><?php echo esc_html( $doc ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $result['missing_documents'] ) ) : ?>
				<div class="tcd-documents-missing">
					<h4><?php esc_html_e( 'Missing Documents', 'tainacan-document-checker' ); ?></h4>
					<ul>
						<?php foreach ( $result['missing_documents'] as $doc ) : ?>
							<li class="tcd-doc-missing"><?php echo esc_html( $doc ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $result['invalid_documents'] ) ) : ?>
				<div class="tcd-documents-invalid">
					<h4><?php esc_html_e( 'Invalid Documents', 'tainacan-document-checker' ); ?></h4>
					<ul>
						<?php foreach ( $result['invalid_documents'] as $doc ) : ?>
							<li class="tcd-doc-invalid"><?php echo esc_html( $doc ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( isset( $result['debug'] ) && get_option( 'tcd_debug_mode', false ) ) : ?>
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
	 * Format batch check result for display.
	 *
	 * @param array $result Batch result.
	 * @return string HTML output.
	 */
	private function format_batch_result( array $result ): string {
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
				<p>
					<span class="tcd-stat tcd-stat-complete">
						<?php
						printf(
							/* translators: %d: number of complete items */
							esc_html__( 'Complete: %d', 'tainacan-document-checker' ),
							count( $result['complete_items'] )
						);
						?>
					</span>
					<span class="tcd-stat tcd-stat-incomplete">
						<?php
						printf(
							/* translators: %d: number of incomplete items */
							esc_html__( 'Incomplete: %d', 'tainacan-document-checker' ),
							count( $result['incomplete_items'] )
						);
						?>
					</span>
					<span class="tcd-stat tcd-stat-error">
						<?php
						printf(
							/* translators: %d: number of errors */
							esc_html__( 'Errors: %d', 'tainacan-document-checker' ),
							count( $result['errors'] )
						);
						?>
					</span>
				</p>
			</div>

			<?php if ( ! empty( $result['incomplete_items'] ) ) : ?>
				<div class="tcd-incomplete-items">
					<h4><?php esc_html_e( 'Incomplete Items', 'tainacan-document-checker' ); ?></h4>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Item ID', 'tainacan-document-checker' ); ?></th>
								<th><?php esc_html_e( 'Missing Documents', 'tainacan-document-checker' ); ?></th>
								<th><?php esc_html_e( 'Invalid Documents', 'tainacan-document-checker' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $result['incomplete_items'] as $item ) : ?>
								<tr>
									<td><?php echo esc_html( $item['item_id'] ); ?></td>
									<td>
										<?php 
										echo ! empty( $item['missing_documents'] ) 
											? esc_html( implode( ', ', $item['missing_documents'] ) )
											: '-';
										?>
									</td>
									<td>
										<?php 
										echo ! empty( $item['invalid_documents'] ) 
											? esc_html( implode( ', ', $item['invalid_documents'] ) )
											: '-';
										?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Format history for display.
	 *
	 * @param array $history History records.
	 * @return string HTML output.
	 */
	private function format_history( array $history ): string {
		if ( empty( $history ) ) {
			return '<p>' . esc_html__( 'No history found for this item.', 'tainacan-document-checker' ) . '</p>';
		}

		ob_start();
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'tainacan-document-checker' ); ?></th>
					<th><?php esc_html_e( 'Status', 'tainacan-document-checker' ); ?></th>
					<th><?php esc_html_e( 'Missing Documents', 'tainacan-document-checker' ); ?></th>
					<th><?php esc_html_e( 'Found Documents', 'tainacan-document-checker' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $history as $record ) : ?>
					<tr>
						<td><?php echo esc_html( $record['check_date'] ); ?></td>
						<td>
							<span class="tcd-status tcd-status-<?php echo esc_attr( $record['check_status'] ); ?>">
								<?php echo esc_html( ucfirst( $record['check_status'] ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $record['missing_documents'] ?: '-' ); ?></td>
						<td><?php echo esc_html( $record['found_documents'] ?: '-' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		return ob_get_clean();
	}

	/**
	 * Format email logs for display.
	 *
	 * @param array $logs Email logs.
	 * @return string HTML output.
	 */
	private function format_email_logs( array $logs ): string {
		if ( empty( $logs ) ) {
			return '<p>' . esc_html__( 'No email logs found.', 'tainacan-document-checker' ) . '</p>';
		}

		ob_start();
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'tainacan-document-checker' ); ?></th>
					<th><?php esc_html_e( 'User', 'tainacan-document-checker' ); ?></th>
					<th><?php esc_html_e( 'Item', 'tainacan-document-checker' ); ?></th>
					<th><?php esc_html_e( 'Type', 'tainacan-document-checker' ); ?></th>
					<th><?php esc_html_e( 'Subject', 'tainacan-document-checker' ); ?></th>
					<th><?php esc_html_e( 'Status', 'tainacan-document-checker' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $log ) : ?>
					<tr>
						<td><?php echo esc_html( $log['sent_date'] ); ?></td>
						<td>
							<?php 
							$user = get_userdata( $log['user_id'] );
							echo $user ? esc_html( $user->display_name ) : 'User #' . esc_html( $log['user_id'] );
							?>
						</td>
						<td>
							<?php echo $log['item_id'] ? 'Item #' . esc_html( $log['item_id'] ) : '-'; ?>
						</td>
						<td><?php echo esc_html( ucfirst( $log['email_type'] ) ); ?></td>
						<td><?php echo esc_html( $log['subject'] ); ?></td>
						<td>
							<span class="tcd-status tcd-status-<?php echo esc_attr( $log['status'] ); ?>">
								<?php echo esc_html( ucfirst( $log['status'] ) ); ?>
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		return ob_get_clean();
	}
}
