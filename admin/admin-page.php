<?php
/**
 * Main admin page template
 *
 * @package TainacanDocumentChecker
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<?php $this->render_tab_navigation(); ?>
	
	<div class="tcd-admin-content">
		<?php $this->render_tab_content(); ?>
	</div>
</div>