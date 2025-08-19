<?php
/**
 * Frontend document status display template
 *
 * @package TainacanDocumentChecker
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Variables available: $result, $item_id.
$status_class = 'complete' === $result['status'] ? 'tcd-status-complete' : 'tcd-status-incomplete';
?>

<div class="tcd-doc-status <?php echo esc_attr( $status_class ); ?>">
	<h3 class="tcd-doc-status-title">
		<?php esc_html_e( 'Document Verification Status', 'tainacan-document-checker' ); ?>
	</h3>
	
	<div class="tcd-doc-status-content">
		<?php if ( 'error' === $result['status'] ) : ?>
			<p class="tcd-error-message">
				<?php echo esc_html( $result['message'] ); ?>
			</p>
		<?php else : ?>
			<div class="tcd-status-summary">
				<div class="tcd-status-indicator">
					<?php if ( 'complete' === $result['status'] ) : ?>
						<span class="dashicons dashicons-yes-alt"></span>
						<span><?php esc_html_e( 'All documents present', 'tainacan-document-checker' ); ?></span>
					<?php else : ?>
						<span class="dashicons dashicons-warning"></span>
						<span><?php esc_html_e( 'Missing documents', 'tainacan-document-checker' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			
			<div class="tcd-doc-details">
				<div class="tcd-doc-section">
					<h4><?php esc_html_e( 'Required Documents', 'tainacan-document-checker' ); ?></h4>
					<ul class="tcd-doc-list">
						<?php
						$required_docs = get_option( 'tcd_required_documents', array() );
						foreach ( $required_docs as $doc ) :
							$is_present = in_array( $doc, $result['found_documents'], true );
							$doc_class  = $is_present ? 'tcd-doc-present' : 'tcd-doc-missing';
							?>
							<li class="<?php echo esc_attr( $doc_class ); ?>">
								<span class="dashicons <?php echo $is_present ? 'dashicons-yes' : 'dashicons-no-alt'; ?>"></span>
								<?php echo esc_html( str_replace( '_', ' ', ucfirst( $doc ) ) ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				
				<?php if ( ! empty( $result['missing_documents'] ) ) : ?>
					<div class="tcd-doc-section tcd-missing-notice">
						<p>
							<strong><?php esc_html_e( 'Action Required:', 'tainacan-document-checker' ); ?></strong>
							<?php esc_html_e( 'Please upload the missing documents to complete the verification process.', 'tainacan-document-checker' ); ?>
						</p>
					</div>
				<?php endif; ?>
			</div>
			
			<div class="tcd-doc-footer">
				<p class="tcd-last-check">
					<?php
					printf(
						/* translators: %s: last check date */
						esc_html__( 'Last verified: %s', 'tainacan-document-checker' ),
						esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $result['check_date'] ) ) )
					);
					?>
				</p>
			</div>
		<?php endif; ?>
	</div>
</div>