<?php
/**
 * Admin page template
 *
 * @package TainacanDocumentChecker
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get active tab.
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'single';
$tabs = $this->get_admin_tabs();
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Tainacan Document Checker', 'tainacan-document-checker' ); ?></h1>
	
	<!-- Tabs Navigation -->
	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
			<a href="?page=tainacan-document-checker&tab=<?php echo esc_attr( $tab_key ); ?>" 
			   class="nav-tab tcd-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>" 
			   data-tab="<?php echo esc_attr( $tab_key ); ?>">
				<?php echo esc_html( $tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="tcd-admin-content">
		<!-- Single Check Tab -->
		<div class="tcd-tab-content tcd-single <?php echo $active_tab === 'single' ? 'active' : ''; ?>">
			<h2><?php esc_html_e( 'Check Single Item', 'tainacan-document-checker' ); ?></h2>
			<form id="tcd-check-single" method="post">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="tcd-item-id"><?php esc_html_e( 'Item ID', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="number" id="tcd-item-id" name="item_id" required class="regular-text" />
							<p class="description"><?php esc_html_e( 'Enter the Tainacan item ID to check.', 'tainacan-document-checker' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="tcd-send-email-single"><?php esc_html_e( 'Send Email', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="checkbox" id="tcd-send-email-single" name="send_email" value="1" />
							<label for="tcd-send-email-single"><?php esc_html_e( 'Send email notification if documents are missing', 'tainacan-document-checker' ); ?></label>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Check Item', 'tainacan-document-checker' ); ?></button>
				</p>
			</form>
			<div id="tcd-single-result"></div>
		</div>

		<!-- Batch Check Tab -->
		<div class="tcd-tab-content tcd-batch <?php echo $active_tab === 'batch' ? 'active' : ''; ?>">
			<h2><?php esc_html_e( 'Check Collection', 'tainacan-document-checker' ); ?></h2>
			<form id="tcd-check-batch" method="post">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="tcd-collection-id"><?php esc_html_e( 'Collection ID', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="number" id="tcd-collection-id" name="collection_id" required class="regular-text" value="<?php echo esc_attr( get_option( 'tcd_collection_id', '' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Enter the Tainacan collection ID to check all items.', 'tainacan-document-checker' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="tcd-per-page"><?php esc_html_e( 'Items per page', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="number" id="tcd-per-page" name="per_page" value="10" min="1" max="100" class="small-text" />
							<p class="description"><?php esc_html_e( 'Number of items to check per batch (1-100).', 'tainacan-document-checker' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="tcd-send-email-batch"><?php esc_html_e( 'Send Emails', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="checkbox" id="tcd-send-email-batch" name="send_email" value="1" />
							<label for="tcd-send-email-batch"><?php esc_html_e( 'Send email notifications for incomplete items', 'tainacan-document-checker' ); ?></label>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Check Collection', 'tainacan-document-checker' ); ?></button>
				</p>
			</form>
			<div id="tcd-batch-progress" style="display:none;">
				<div class="progress-bar-wrapper">
					<div class="progress-bar" style="width:0%;"></div>
				</div>
			</div>
			<div id="tcd-batch-result"></div>
		</div>

		<!-- History Tab -->
		<div class="tcd-tab-content tcd-history <?php echo $active_tab === 'history' ? 'active' : ''; ?>">
			<h2><?php esc_html_e( 'Check History', 'tainacan-document-checker' ); ?></h2>
			<form id="tcd-get-history" method="post">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="tcd-history-item-id"><?php esc_html_e( 'Item ID', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="number" id="tcd-history-item-id" name="item_id" required class="regular-text" />
							<p class="description"><?php esc_html_e( 'Enter the item ID to view check history.', 'tainacan-document-checker' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-secondary"><?php esc_html_e( 'Get History', 'tainacan-document-checker' ); ?></button>
				</p>
			</form>
			<div id="tcd-history-result"></div>
		</div>

		<!-- Documents Tab -->
		<div class="tcd-tab-content tcd-documents <?php echo $active_tab === 'documents' ? 'active' : ''; ?>">
			<?php
			// Handle save
			if ( isset( $_POST['tcd_save_documents_nonce'] ) && wp_verify_nonce( $_POST['tcd_save_documents_nonce'], 'tcd_save_documents_nonce' ) ) {
				$documents = isset( $_POST['tcd_required_documents'] ) ? array_map( 'sanitize_text_field', $_POST['tcd_required_documents'] ) : array();
				$documents = array_filter( $documents );
				$documents = array_unique( $documents );
				
				update_option( 'tcd_required_documents', $documents );
				
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Document names saved successfully!', 'tainacan-document-checker' ) . '</p></div>';
			}
			
			$documents = get_option( 'tcd_required_documents', array() );
			if ( empty( $documents ) ) {
				$documents = array( '' );
			}
			?>
			<h2><?php esc_html_e( 'Manage Required Document Names', 'tainacan-document-checker' ); ?></h2>
			
			<form id="tcd-documents-form" method="post" action="">
				<?php wp_nonce_field( 'tcd_save_documents_nonce' ); ?>
				
				<div id="tcd-documents-list">
					<h3><?php esc_html_e( 'Required Documents', 'tainacan-document-checker' ); ?></h3>
					<p class="description"><?php esc_html_e( 'List of document names that must be attached to each item.', 'tainacan-document-checker' ); ?></p>
					
					<div id="tcd-documents-container">
						<?php foreach ( $documents as $doc ) : ?>
							<div class="tcd-document-row">
								<input type="text" name="tcd_required_documents[]" value="<?php echo esc_attr( $doc ); ?>" placeholder="<?php esc_attr_e( 'Enter document name', 'tainacan-document-checker' ); ?>" />
								<button type="button" class="button tcd-remove-document"><?php esc_html_e( 'Remove', 'tainacan-document-checker' ); ?></button>
							</div>
						<?php endforeach; ?>
					</div>
					
					<p>
						<button type="button" id="tcd-add-document" class="button button-secondary"><?php esc_html_e( 'Add Document', 'tainacan-document-checker' ); ?></button>
					</p>
				</div>
				
				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Documents', 'tainacan-document-checker' ); ?></button>
				</p>
			</form>
		</div>

		<!-- Email Tab -->
		<div class="tcd-tab-content tcd-email <?php echo $active_tab === 'email' ? 'active' : ''; ?>">
			<h2><?php esc_html_e( 'Email Settings', 'tainacan-document-checker' ); ?></h2>
			
			<form method="post" action="options.php">
				<?php settings_fields( 'tcd_email_settings' ); ?>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="tcd_email_enabled"><?php esc_html_e( 'Enable Email Notifications', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="checkbox" id="tcd_email_enabled" name="tcd_email_enabled" value="1" <?php checked( get_option( 'tcd_email_enabled', false ) ); ?> />
							<p class="description"><?php esc_html_e( 'Send email notifications when documents are missing or invalid.', 'tainacan-document-checker' ); ?></p>
						</td>
					</tr>
					
					<tr class="tcd-email-settings">
						<th scope="row">
							<label for="tcd_email_html"><?php esc_html_e( 'HTML Emails', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="checkbox" id="tcd_email_html" name="tcd_email_html" value="1" <?php checked( get_option( 'tcd_email_html', false ) ); ?> />
							<p class="description"><?php esc_html_e( 'Send emails in HTML format instead of plain text.', 'tainacan-document-checker' ); ?></p>
						</td>
					</tr>
				</table>
				
				<h3><?php esc_html_e( 'SMTP Configuration', 'tainacan-document-checker' ); ?></h3>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="tcd_smtp_enabled"><?php esc_html_e( 'Enable SMTP', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="checkbox" id="tcd_smtp_enabled" name="tcd_smtp_enabled" value="1" <?php checked( get_option( 'tcd_smtp_enabled', false ) ); ?> />
						</td>
					</tr>
					
					<tr class="tcd-smtp-fields">
						<th scope="row">
							<label for="tcd_smtp_host"><?php esc_html_e( 'SMTP Host', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="text" id="tcd_smtp_host" name="tcd_smtp_host" value="<?php echo esc_attr( get_option( 'tcd_smtp_host', '' ) ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'e.g., smtp.gmail.com', 'tainacan-document-checker' ); ?></p>
						</td>
					</tr>
					
					<tr class="tcd-smtp-fields">
						<th scope="row">
							<label for="tcd_smtp_port"><?php esc_html_e( 'SMTP Port', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="number" id="tcd_smtp_port" name="tcd_smtp_port" value="<?php echo esc_attr( get_option( 'tcd_smtp_port', 587 ) ); ?>" class="small-text" />
						</td>
					</tr>
					
					<tr class="tcd-smtp-fields">
						<th scope="row">
							<label for="tcd_smtp_username"><?php esc_html_e( 'SMTP Username', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="text" id="tcd_smtp_username" name="tcd_smtp_username" value="<?php echo esc_attr( get_option( 'tcd_smtp_username', '' ) ); ?>" class="regular-text" />
						</td>
					</tr>
					
					<tr class="tcd-smtp-fields">
						<th scope="row">
							<label for="tcd_smtp_password"><?php esc_html_e( 'SMTP Password', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="password" id="tcd_smtp_password" name="tcd_smtp_password" value="<?php echo esc_attr( get_option( 'tcd_smtp_password', '' ) ); ?>" class="regular-text" />
						</td>
					</tr>
					
					<tr class="tcd-smtp-fields">
						<th scope="row">
							<label for="tcd_smtp_from_email"><?php esc_html_e( 'From Email', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="email" id="tcd_smtp_from_email" name="tcd_smtp_from_email" value="<?php echo esc_attr( get_option( 'tcd_smtp_from_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text" />
						</td>
					</tr>
				</table>
				
				<?php submit_button(); ?>
			</form>
			
			<hr />
			
			<h3><?php esc_html_e( 'Test SMTP Configuration', 'tainacan-document-checker' ); ?></h3>
			<div class="tcd-test-email-form">
				<input type="email" id="tcd-test-email" placeholder="<?php esc_attr_e( 'Enter test email address', 'tainacan-document-checker' ); ?>" class="regular-text" />
				<button type="button" id="tcd-test-smtp" class="button button-secondary"><?php esc_html_e( 'Send Test Email', 'tainacan-document-checker' ); ?></button>
			</div>
			
			<hr />
			
			<h3><?php esc_html_e( 'Email Logs', 'tainacan-document-checker' ); ?></h3>
			<button type="button" id="tcd-view-email-logs" class="button button-secondary"><?php esc_html_e( 'View Email Logs', 'tainacan-document-checker' ); ?></button>
			<div id="tcd-email-logs" style="display:none; margin-top: 20px;"></div>
		</div>

		<!-- Settings Tab -->
		<div class="tcd-tab-content tcd-settings <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">
			<h2><?php esc_html_e( 'Settings', 'tainacan-document-checker' ); ?></h2>
			
			<form method="post" action="options.php">
				<?php settings_fields( 'tcd_settings' ); ?>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="tcd_api_url"><?php esc_html_e( 'Tainacan API URL', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="url" id="tcd_api_url" name="tcd_api_url" value="<?php echo esc_attr( get_option( 'tcd_api_url', '' ) ); ?>" class="large-text" />
							<p class="description"><?php esc_html_e( 'The base URL for Tainacan API (e.g., https://yoursite.com/wp-json/tainacan/v2)', 'tainacan-document-checker' ); ?></p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="tcd_collection_id"><?php esc_html_e( 'Default Collection ID', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="number" id="tcd_collection_id" name="tcd_collection_id" value="<?php echo esc_attr( get_option( 'tcd_collection_id', '' ) ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Default collection ID for batch checks.', 'tainacan-document-checker' ); ?></p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="tcd_cache_duration"><?php esc_html_e( 'Cache Duration', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="number" id="tcd_cache_duration" name="tcd_cache_duration" value="<?php echo esc_attr( get_option( 'tcd_cache_duration', 300 ) ); ?>" class="small-text" />
							<span><?php esc_html_e( 'seconds', 'tainacan-document-checker' ); ?></span>
							<p class="description"><?php esc_html_e( 'How long to cache check results.', 'tainacan-document-checker' ); ?></p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="tcd_debug_mode"><?php esc_html_e( 'Debug Mode', 'tainacan-document-checker' ); ?></label>
						</th>
						<td>
							<input type="checkbox" id="tcd_debug_mode" name="tcd_debug_mode" value="1" <?php checked( get_option( 'tcd_debug_mode', false ) ); ?> />
							<label for="tcd_debug_mode"><?php esc_html_e( 'Enable debug logging', 'tainacan-document-checker' ); ?></label>
						</td>
					</tr>
				</table>
				
				<?php submit_button(); ?>
			</form>
			
			<hr />
			
			<h3><?php esc_html_e( 'Cache Management', 'tainacan-document-checker' ); ?></h3>
			<p>
				<button type="button" id="tcd-clear-cache" class="button button-secondary"><?php esc_html_e( 'Clear Cache', 'tainacan-document-checker' ); ?></button>
			</p>
		</div>
	</div>
</div>
