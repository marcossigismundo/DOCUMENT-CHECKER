<?php
/**
 * PHPUnit bootstrap file
 *
 * @package TainacanDocumentChecker
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( dirname( __FILE__ ) ) . '/tainacan-document-checker.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// Create custom table for tests.
global $wpdb;

$table_name      = $wpdb->prefix . 'tainacan_document_checks';
$charset_collate = $wpdb->get_charset_collate();

$sql = "CREATE TABLE IF NOT EXISTS $table_name (
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

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta( $sql );