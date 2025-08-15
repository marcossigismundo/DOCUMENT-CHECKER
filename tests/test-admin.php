<?php
/**
 * Admin Tests
 *
 * @package TainacanDocumentChecker\Tests
 */

declare(strict_types=1);

namespace TainacanDocumentChecker\Tests;

use TainacanDocumentChecker\Core\Admin;
use WP_UnitTestCase;

/**
 * Test Admin functionality.
 */
class Test_Admin extends WP_UnitTestCase {

	/**
	 * Admin instance.
	 *
	 * @var Admin
	 */
	private Admin $admin;

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	private int $admin_user_id;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Create admin user.
		$this->admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_user_id );
		
		// Initialize admin class.
		$this->admin = new Admin();
		$this->admin->init();
	}

	/**
	 * Test admin menu registration.
	 */
	public function test_admin_menu_registration() {
		global $menu;
		
		// Trigger admin_menu action.
		do_action( 'admin_menu' );
		
		// Check if menu exists.
		$menu_exists = false;
		foreach ( $menu as $menu_item ) {
			if ( 'tainacan-document-checker' === $menu_item[2] ) {
				$menu_exists = true;
				break;
			}
		}
		
		$this->assertTrue( $menu_exists, 'Admin menu should be registered' );
	}

	/**
	 * Test settings registration.
	 */
	public function test_settings_registration() {
		// Trigger admin_init action.
		do_action( 'admin_init' );
		
		// Check if settings are registered.
		global $wp_registered_settings;
		
		$this->assertArrayHasKey( 'tcd_api_url', $wp_registered_settings );
		$this->assertArrayHasKey( 'tcd_collection_id', $wp_registered_settings );
		$this->assertArrayHasKey( 'tcd_required_documents', $wp_registered_settings );
		$this->assertArrayHasKey( 'tcd_debug_mode', $wp_registered_settings );
		$this->assertArrayHasKey( 'tcd_cache_duration', $wp_registered_settings );
	}

	/**
	 * Test sanitize_documents_list method.
	 */
	public function test_sanitize_documents_list() {
		$input = array(
			'valid_document',
			'Another Valid Document',
			'document-with-dashes',
			'document_with_underscores',
			'   trimmed_document   ',
			'', // Empty string.
			'duplicate',
			'duplicate',
		);
		
		$expected = array(
			'valid_document',
			'Another-Valid-Document',
			'document-with-dashes',
			'document_with_underscores',
			'trimmed_document',
			'duplicate',
		);
		
		$result = $this->admin->sanitize_documents_list( $input );
		
		$this->assertEquals( $expected, array_values( $result ) );
	}

	/**
	 * Test sanitize_documents_list with invalid input.
	 */
	public function test_sanitize_documents_list_invalid_input() {
		$this->assertEquals( array(), $this->admin->sanitize_documents_list( null ) );
		$this->assertEquals( array(), $this->admin->sanitize_documents_list( 'string' ) );
		$this->assertEquals( array(), $this->admin->sanitize_documents_list( 123 ) );
	}

	/**
	 * Test get_admin_tabs method.
	 */
	public function test_get_admin_tabs() {
		$tabs = $this->admin->get_admin_tabs();
		
		$this->assertIsArray( $tabs );
		$this->assertArrayHasKey( 'single', $tabs );
		$this->assertArrayHasKey( 'batch', $tabs );
		$this->assertArrayHasKey( 'history', $tabs );
		$this->assertArrayHasKey( 'settings', $tabs );
		$this->assertArrayHasKey( 'documents', $tabs );
		
		$this->assertEquals( 5, count( $tabs ) );
	}

	/**
	 * Test render_admin_page without permission.
	 */
	public function test_render_admin_page_no_permission() {
		// Create non-admin user.
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );
		
		// Expect wp_die to be called.
		$this->expectException( 'WPDieException' );
		
		$this->admin->render_admin_page();
	}

	/**
	 * Test option sanitization callbacks.
	 */
	public function test_option_sanitization() {
		// Test API URL sanitization.
		$url = 'https://example.com/wp-json/tainacan/v2';
		$this->assertEquals( $url, esc_url_raw( $url ) );
		
		// Test collection ID sanitization.
		$this->assertEquals( 123, absint( '123' ) );
		$this->assertEquals( 0, absint( 'abc' ) );
		$this->assertEquals( 0, absint( -123 ) );
		
		// Test debug mode sanitization.
		$this->assertTrue( rest_sanitize_boolean( true ) );
		$this->assertTrue( rest_sanitize_boolean( 'true' ) );
		$this->assertTrue( rest_sanitize_boolean( '1' ) );
		$this->assertFalse( rest_sanitize_boolean( false ) );
		$this->assertFalse( rest_sanitize_boolean( 'false' ) );
		$this->assertFalse( rest_sanitize_boolean( '0' ) );
		
		// Test cache duration sanitization.
		$this->assertEquals( 300, absint( 300 ) );
		$this->assertEquals( 0, absint( -100 ) );
	}

	/**
	 * Test default option values.
	 */
	public function test_default_option_values() {
		// Get default values.
		$default_docs = get_option( 'tcd_required_documents' );
		$default_api  = get_option( 'tcd_api_url' );
		$default_debug = get_option( 'tcd_debug_mode' );
		$default_cache = get_option( 'tcd_cache_duration' );
		
		// Check defaults.
		$this->assertIsArray( $default_docs );
		$this->assertContains( 'comprovante_endereco', $default_docs );
		$this->assertContains( 'documento_identidade', $default_docs );
		$this->assertContains( 'documento_responsavel', $default_docs );
		
		$this->assertStringContainsString( '/wp-json/tainacan/v2', $default_api );
		$this->assertFalse( $default_debug );
		$this->assertEquals( 300, $default_cache );
	}
}