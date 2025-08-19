<?php
/**
 * Document Status Display Template
 *
 * @package TainacanDocumentChecker
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure $result is available
if ( ! isset( $result ) || ! is_array( $result ) ) {
	echo '<p>' . esc_html__( 'Unable to load document status.', 'tainacan-document-checker' ) . '</p>';
	return;
}

$status_class = 'tcd-status-' . esc_attr( $result['status'] );
$status_text = ucfirst( $result['status'] );

// Get dashicons based on status
$status_icon = 'complete' === $result['status'] ? 'dashicons-yes-alt' : 'dashicons-warning';
?>

<div class="tcd-doc-status <?php echo esc_attr( $status_class ); ?>">
	<h3 class="tcd-doc-status-title">
		<?php esc_html_e( 'Document Verification Status', 'tainacan-document-checker' ); ?>
	</h3>
	
	<div class="tcd-status-summary">
		<div class="tcd-status-indicator">
			<span class="dashicons <?php echo esc_attr( $status_icon ); ?>"></span>
			<strong><?php echo esc_html( $status_text ); ?></strong>
		</div>
	</div>
	
	<?php if ( ! empty( $result['found_documents'] ) || ! empty( $result['missing_documents'] ) || ! empty( $result['invalid_documents'] ) ) : ?>
		<div class="tcd-doc-details">
			
			<?php if ( ! empty( $result['found_documents'] ) ) : ?>
				<div class="tcd-doc-section">
					<h4><?php esc_html_e( 'Valid Documents Found', 'tainacan-document-checker' ); ?></h4>
					<ul class="tcd-doc-list">
						<?php foreach ( $result['found_documents'] as $doc ) : ?>
							<li class="tcd-doc-present">
								<span class="dashicons dashicons-yes"></span>
								<?php echo esc_html( $doc ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
			
			<?php if ( ! empty( $result['missing_documents'] ) ) : ?>
				<div class="tcd-doc-section">
					<h4><?php esc_html_e( 'Missing Documents', 'tainacan-document-checker' ); ?></h4>
					<ul class="tcd-doc-list">
						<?php foreach ( $result['missing_documents'] as $doc ) : ?>
							<li class="tcd-doc-missing">
								<span class="dashicons dashicons-no"></span>
								<?php echo esc_html( $doc ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
			
			<?php if ( ! empty( $result['invalid_documents'] ) ) : ?>
				<div class="tcd-doc-section">
					<h4><?php esc_html_e( 'Invalid Documents', 'tainacan-document-checker' ); ?></h4>
					<ul class="tcd-doc-list">
						<?php foreach ( $result['invalid_documents'] as $doc ) : ?>
							<li class="tcd-doc-missing">
								<span class="dashicons dashicons-no"></span>
								<?php echo esc_html( $doc ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
					<p class="description">
						<?php esc_html_e( 'These documents do not match the required document names and should be removed or renamed.', 'tainacan-document-checker' ); ?>
					</p>
				</div>
			<?php endif; ?>
			
		</div>
	<?php endif; ?>
	
	<?php if ( 'incomplete' === $result['status'] ) : ?>
		<div class="tcd-missing-notice">
			<p>
				<strong><?php esc_html_e( 'Action Required:', 'tainacan-document-checker' ); ?></strong>
				<?php 
				if ( ! empty( $result['missing_documents'] ) && ! empty( $result['invalid_documents'] ) ) {
					esc_html_e( 'Please upload the missing documents and remove or rename the invalid ones.', 'tainacan-document-checker' );
				} elseif ( ! empty( $result['missing_documents'] ) ) {
					esc_html_e( 'Please upload the missing documents to complete your submission.', 'tainacan-document-checker' );
				} elseif ( ! empty( $result['invalid_documents'] ) ) {
					esc_html_e( 'Please remove or rename the invalid documents.', 'tainacan-document-checker' );
				}
				?>
			</p>
		</div>
	<?php endif; ?>
	
	<?php if ( 'error' === $result['status'] && ! empty( $result['message'] ) ) : ?>
		<div class="tcd-error-message">
			<p><?php echo esc_html( $result['message'] ); ?></p>
		</div>
	<?php endif; ?>
	
	<div class="tcd-doc-footer">
		<p class="tcd-last-check">
			<?php
			if ( ! empty( $result['check_date'] ) ) {
				printf(
					/* translators: %s: formatted date */
					esc_html__( 'Last checked: %s', 'tainacan-document-checker' ),
					esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $result['check_date'] ) ) )
				);
			} else {
				esc_html_e( 'Recently checked', 'tainacan-document-checker' );
			}
			?>
		</p>
	</div>
</div>