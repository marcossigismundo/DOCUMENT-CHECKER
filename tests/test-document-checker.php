<?php
/**
 * Document Checker Tests
 *
 * @package TainacanDocumentChecker\Tests
 */

declare(strict_types=1);

namespace TainacanDocumentChecker\Tests;

use TainacanDocumentChecker\Core\Document_Checker;
use WP_UnitTestCase;

/**
 * Test Document Checker functionality.
 */
class Test_Document_Checker extends WP_UnitTestCase {

	/**
	 * Document checker instance.
	 *
	 * @var Document_Checker
	 */
	private Document_Checker $document_checker;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->document_checker = new Document_Checker();
		
		// Set default options.
		update_option( 'tcd_required_documents', array(
			'comprovante_endereco',
			'documento_identidade',
			'documento_responsavel',
		) );
		update_option( 'tcd_api_url', 'http://example.com/wp-json/tainacan/v2' );
		update_option( 'tcd_debug_mode', false );
	}

	/**
	 * Test check_item_documents with valid item.
	 */
	public function test_check_item_documents_valid() {
		// Mock API response.
		add_filter( 'pre_http_request', array( $this, 'mock_api_response_valid' ), 10, 3 );
		
		$result = $this->document_checker->check_item_documents( 123 );
		
		$this->assertEquals( 'complete', $result['status'] );
		$this->assertEquals( 123, $result['item_id'] );
		$this->assertContains( 'comprovante_endereco', $result['found_documents'] );
		$this->assertEmpty( $result['missing_documents'] );
		
		remove_filter( 'pre_http_request', array( $this, 'mock_api_response_valid' ), 10 );
	}

	/**
	 * Test check_item_documents with missing documents.
	 */
	public function test_check_item_documents_incomplete() {
		// Mock API response.
		add_filter( 'pre_http_request', array( $this, 'mock_api_response_incomplete' ), 10, 3 );
		
		$result = $this->document_checker->check_item_documents( 456 );
		
		$this->assertEquals( 'incomplete', $result['status'] );
		$this->assertEquals( 456, $result['item_id'] );
		$this->assertContains( 'documento_identidade', $result['missing_documents'] );
		
		remove_filter( 'pre_http_request', array( $this, 'mock_api_response_incomplete' ), 10 );
	}

	/**
	 * Test check_item_documents with API error.
	 */
	public function test_check_item_documents_error() {
		// Mock API error.
		add_filter( 'pre_http_request', array( $this, 'mock_api_error' ), 10, 3 );
		
		$result = $this->document_checker->check_item_documents( 789 );
		
		$this->assertEquals( 'error', $result['status'] );
		$this->assertArrayHasKey( 'message', $result );
		
		remove_filter( 'pre_http_request', array( $this, 'mock_api_error' ), 10 );
	}

	/**
	 * Test get_item_history.
	 */
	public function test_get_item_history() {
		global $wpdb;
		
		// Insert test data.
		$table_name = $wpdb->prefix . 'tainacan_document_checks';
		$wpdb->insert(
			$table_name,
			array(
				'item_id'           => 123,
				'collection_id'     => 1,
				'check_type'        => 'individual',
				'check_status'      => 'complete',
				'missing_documents' => serialize( array() ),
				'found_documents'   => serialize( array( 'comprovante_endereco', 'documento_identidade' ) ),
				'check_date'        => current_time( 'mysql' ),
			)
		);
		
		$history = $this->document_checker->get_item_history( 123 );
		
		$this->assertNotEmpty( $history );
		$this->assertEquals( 123, $history[0]['item_id'] );
		$this->assertEquals( 'complete', $history[0]['check_status'] );
	}

	/**
	 * Mock valid API response.
	 *
	 * @param false|array $preempt Whether to preempt the request.
	 * @param array       $parsed_args Request arguments.
	 * @param string      $url Request URL.
	 * @return array Mocked response.
	 */
	public function mock_api_response_valid( $preempt, $parsed_args, $url ) {
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
						'alt_text' => '',
						'caption'  => '',
					),
					array(
						'id'       => 2,
						'filename' => 'documento_identidade.pdf',
						'title'    => 'Documento de Identidade',
						'alt_text' => '',
						'caption'  => '',
					),
					array(
						'id'       => 3,
						'filename' => 'documento_responsavel.pdf',
						'title'    => 'Documento do Responsável',
						'alt_text' => '',
						'caption'  => '',
					),
				) ),
			);
		}
		return $preempt;
	}

	/**
	 * Mock incomplete API response.
	 *
	 * @param false|array $preempt Whether to preempt the request.
	 * @param array       $parsed_args Request arguments.
	 * @param string      $url Request URL.
	 * @return array Mocked response.
	 */
	public function mock_api_response_incomplete( $preempt, $parsed_args, $url ) {
		if ( strpos( $url, '/items/456/attachments' ) !== false ) {
			return array(
				'response' => array(
					'code' => 200,
				),
				'body' => json_encode( array(
					array(
						'id'       => 1,
						'filename' => 'comprovante_endereco.pdf',
						'title'    => 'Comprovante de Endereço',
						'alt_text' => '',
						'caption'  => '',
					),
					array(
						'id'       => 3,
						'filename' => 'documento_responsavel.pdf',
						'title'    => 'Documento do Responsável',
						'alt_text' => '',
						'caption'  => '',
					),
				) ),
			);
		}
		return $preempt;
	}

	/**
	 * Mock API error.
	 *
	 * @param false|array $preempt Whether to preempt the request.
	 * @param array       $parsed_args Request arguments.
	 * @param string      $url Request URL.
	 * @return WP_Error Mocked error.
	 */
	public function mock_api_error( $preempt, $parsed_args, $url ) {
		if ( strpos( $url, '/items/789/attachments' ) !== false ) {
			return new \WP_Error( 'http_request_failed', 'Connection timeout' );
		}
		return $preempt;
	}

	/**
	 * Test transient caching.
	 */
	public function test_transient_caching() {
		// Mock API response.
		add_filter( 'pre_http_request', array( $this, 'mock_api_response_valid' ), 10, 3 );
		
		// First call - should hit API.
		$result1 = $this->document_checker->check_collection_documents( 1, 1, 10 );
		
		// Second call - should use cache.
		$result2 = $this->document_checker->check_collection_documents( 1, 1, 10 );
		
		$this->assertEquals( $result1, $result2 );
		
		// Verify transient exists.
		$cache_key = 'tcd_batch_1_1_10';
		$cached = get_transient( $cache_key );
		$this->assertNotFalse( $cached );
		
		remove_filter( 'pre_http_request', array( $this, 'mock_api_response_valid' ), 10 );
	}
}