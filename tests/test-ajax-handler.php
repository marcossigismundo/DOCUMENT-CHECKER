<?php
/**
 * Ajax Handler Tests
 *
 * @package TainacanDocumentChecker\Tests
 */

declare(strict_types=1);

namespace TainacanDocumentChecker\Tests;

use TainacanDocumentChecker\Core\Ajax_Handler;
use TainacanDocumentChecker\Core\Document_Checker;
use WP_Ajax_UnitTestCase;

/**
 * Test Ajax Handler functionality.
 */
class Test_Ajax_Handler extends WP_Ajax_UnitTestCase {

	/**
	 * Ajax handler instance.
	 *
	 * @var Ajax_Handler
	 */
	private Ajax_Handler $ajax_handler;

	/**
	 * Document checker instance.
	 *
	 * @var Document_Checker
	 */
	private Document_Checker $document_checker;

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
		
		// Initialize classes.
		$this->document_checker = new Document_Checker();
		$this->ajax_handler     = new Ajax_Handler( $this->document_checker );
		$this->ajax_handler->init();
		
		// Set up nonce.
		$_REQUEST['nonce'] = wp_create_nonce( 'tcd_ajax_nonce' );
	}

	/**
	 * Test single check ajax handler.
	 */
	public function test_handle_single_check_success() {
		// Mock successful API response.
		add_filter( 'pre_http_request', array( $this, 'mock_successful_api_response' ), 10, 3 );
		
		// Set POST data.
		$_POST['item_id'] = '123';
		$_POST['nonce']   = $_REQUEST['nonce'];
		
		// Make ajax call.
		try {
			$this->_handleAjax( 'tcd_check_single_item' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected exception.
		}
		
		// Get response.
		$response = json_decode( $this->_last_response, true );
		
		// Assert response.
		$this->assertTrue( $response['success'] );
		$this->assertEquals( 'complete', $response['data']['status'] );
		$this->assertEquals( 123, $response['data']['item_id'] );
		$this->assertArrayHasKey( 'html', $response['data'] );
		$this->assertArrayHasKey( 'summary', $response['data'] );
		
		remove_filter( 'pre_http_request', array( $this, 'mock_successful_api_response' ), 10 );
	}

	/**
	 * Test single check with invalid nonce.
	 */
	public function test_handle_single_check_invalid_nonce() {
		// Set invalid nonce.
		$_POST['nonce']   = 'invalid_nonce';
		$_POST['item_id'] = '123';
		
		// Make ajax call.
		try {
			$this->_handleAjax( 'tcd_check_single_item' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected exception.
		}
		
		// Get response.
		$response = json_decode( $this->_last_response, true );
		
		// Assert response.
		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'Security check failed.', $response['data'] );
	}

	/**
	 * Test single check without permission.
	 */
	public function test_handle_single_check_no_permission() {
		// Switch to non-admin user.
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );
		
		// Set POST data.
		$_POST['item_id'] = '123';
		$_POST['nonce']   = wp_create_nonce( 'tcd_ajax_nonce' );
		
		// Make ajax call.
		try {
			$this->_handleAjax( 'tcd_check_single_item' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected exception.
		}
		
		// Get response.
		$response = json_decode( $this->_last_response, true );
		
		// Assert response.
		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'Insufficient permissions.', $response['data'] );
	}

	/**
	 * Test single check with invalid item ID.
	 */
	public function test_handle_single_check_invalid_item_id() {
		// Set invalid item ID.
		$_POST['item_id'] = '';
		$_POST['nonce']   = $_REQUEST['nonce'];
		
		// Make ajax call.
		try {
			$this->_handleAjax( 'tcd_check_single_item' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected exception.
		}
		
		// Get response.
		$response = json_decode( $this->_last_response, true );
		
		// Assert response.
		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'Invalid item ID.', $response['data'] );
	}

	/**
	 * Test batch check ajax handler.
	 */
	public function test_handle_batch_check_success() {
		// Mock successful API response.
		add_filter( 'pre_http_request', array( $this, 'mock_batch_api_response' ), 10, 3 );
		
		// Set POST data.
		$_POST['collection_id'] = '1';
		$_POST['page']          = '1';
		$_POST['per_page']      = '10';
		$_POST['nonce']         = $_REQUEST['nonce'];
		
		// Make ajax call.
		try {
			$this->_handleAjax( 'tcd_check_batch' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected exception.
		}
		
		// Get response.
		$response = json_decode( $this->_last_response, true );
		
		// Assert response.
		$this->assertTrue( $response['success'] );
		$this->assertEquals( 1, $response['data']['page'] );
		$this->assertArrayHasKey( 'total_pages', $response['data'] );
		$this->assertArrayHasKey( 'summary', $response['data'] );
		$this->assertArrayHasKey( 'html', $response['data'] );
		$this->assertArrayHasKey( 'progress', $response['data'] );
		
		remove_filter( 'pre_http_request', array( $this, 'mock_batch_api_response' ), 10 );
	}

	/**
	 * Test clear cache ajax handler.
	 */
	public function test_handle_clear_cache() {
		// Create test transient.
		set_transient( 'tcd_test_transient', 'test_value', 300 );
		
		// Set POST data.
		$_POST['nonce'] = $_REQUEST['nonce'];
		
		// Make ajax call.
		try {
			$this->_handleAjax( 'tcd_clear_cache' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected exception.
		}
		
		// Get response.
		$response = json_decode( $this->_last_response, true );
		
		// Assert response.
		$this->assertTrue( $response['success'] );
		$this->assertEquals( 'Cache cleared successfully.', $response['data'] );
		
		// Verify transient was deleted.
		$this->assertFalse( get_transient( 'tcd_test_transient' ) );
	}

	/**
	 * Mock successful API response for single item.
	 *
	 * @param false|array $preempt Whether to preempt the request.
	 * @param array       $parsed_args Request arguments.
	 * @param string      $url Request URL.
	 * @return array Mocked response.
	 */
	public function mock_successful_api_response( $preempt, $parsed_args, $url ) {
		if ( strpos( $url, '/items/123/attachments' ) !== false ) {
			return array(
				'response' => array(
					'code' => 200,
				),
				'body' => json_encode( array(
					array(
						'id'       => 1,
						'filename' => 'comprovante_endereco.pdf',
						'title'    => 'Comprovante de Endereço',
					),
					array(
						'id'       => 2,
						'filename' => 'documento_identidade.pdf',
						'title'    => 'Documento de Identidade',
					),
					array(
						'id'       => 3,
						'filename' => 'documento_responsavel.pdf',
						'title'    => 'Documento do Responsável',
					),
				) ),
			);
		}
		return $preempt;
	}

	/**
	 * Mock batch API response.
	 *
	 * @param false|array $preempt Whether to preempt the request.
	 * @param array       $parsed_args Request arguments.
	 * @param string      $url Request URL.
	 * @return array Mocked response.
	 */
	public function mock_batch_api_response( $preempt, $parsed_args, $url ) {
		if ( strpos( $url, '/collection/1/items' ) !== false ) {
			return array(
				'response' => array(
					'code' => 200,
				),
				'headers' => array(
					'X-WP-Total'      => 25,
					'X-WP-TotalPages' => 3,
				),
				'body' => json_encode( array(
					array(
						'id'    => 1,
						'title' => 'Test Item 1',
					),
					array(
						'id'    => 2,
						'title' => 'Test Item 2',
					),
				) ),
			);
		} elseif ( strpos( $url, '/items/' ) !== false && strpos( $url, '/attachments' ) !== false ) {
			// Return mock attachments for any item.
			return array(
				'response' => array(
					'code' => 200,
				),
				'body' => json_encode( array(
					array(
						'id'       => 1,
						'filename' => 'comprovante_endereco.pdf',
						'title'    => 'Comprovante de Endereço',
					),
					array(
						'id'       => 2,
						'filename' => 'documento_identidade.pdf',
						'title'    => 'Documento de Identidade',
					),
					array(
						'id'       => 3,
						'filename' => 'documento_responsavel.pdf',
						'title'    => 'Documento do Responsável',
					),
				) ),
			);
		}
		return $preempt;
	}

	/**
	 * Clean up after tests.
	 */
	public function tearDown(): void {
		parent::tearDown();
		
		// Clean up globals.
		unset( $_POST['item_id'] );
		unset( $_POST['collection_id'] );
		unset( $_POST['page'] );
		unset( $_POST['per_page'] );
		unset( $_POST['nonce'] );
		unset( $_REQUEST['nonce'] );
	}
}