<?php
/**
 * Document Checker Core Service Class
 *
 * @package TainacanDocumentChecker
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core service class for document verification.
 *
 * @since 1.0.0
 */
class TCD_Document_Checker {

	/**
	 * Tainacan API base URL.
	 *
	 * @var string
	 */
	private string $api_url;

	/**
	 * List of required document names.
	 *
	 * @var array
	 */
	private array $required_documents;

	/**
	 * Debug mode flag.
	 *
	 * @var bool
	 */
	private bool $debug_mode;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api_url            = get_option( 'tcd_api_url', get_site_url() . '/wp-json/tainacan/v2' );
		$this->required_documents = get_option( 'tcd_required_documents', array(
			'comprovante_endereco',
			'documento_identidade',
			'documento_responsavel',
		) );
		$this->debug_mode         = (bool) get_option( 'tcd_debug_mode', false );
		
		// Log para debug
		if ( $this->debug_mode ) {
			error_log( 'TCD Debug - Required documents loaded: ' . print_r( $this->required_documents, true ) );
		}
	}

	/**
	 * Check documents for a single item.
	 *
	 * @param int $item_id Item ID.
	 * @return array Check results.
	 */
	public function check_item_documents( int $item_id ): array {
		$attachments = $this->fetch_item_attachments( $item_id );
		
		if ( is_wp_error( $attachments ) ) {
			return array(
				'status'  => 'error',
				'message' => $attachments->get_error_message(),
				'item_id' => $item_id,
			);
		}

		$found_documents   = array();
		$missing_documents = array();
		$attachment_files  = array();
		$attachment_details = array();
		$invalid_documents = array();

		// Ensure attachments is an array
		if ( ! is_array( $attachments ) ) {
			$attachments = array();
		}

		// Debug: log attachment count
		if ( $this->debug_mode ) {
			error_log( 'TCD Debug - Item ' . $item_id . ' has ' . count( $attachments ) . ' attachments' );
			error_log( 'TCD Debug - Required documents: ' . implode( ', ', $this->required_documents ) );
		}

		// Se não há anexos mas há documentos requeridos, marque como incompleto
		if ( empty( $attachments ) && ! empty( $this->required_documents ) ) {
			return array(
				'status'            => 'incomplete',
				'item_id'           => $item_id,
				'found_documents'   => array(),
				'missing_documents' => $this->required_documents,
				'invalid_documents' => array(),
				'attachment_files'  => array(),
				'total_attachments' => 0,
				'check_date'        => current_time( 'mysql' ),
			);
		}

		// Analyze attachments.
		foreach ( $attachments as $attachment ) {
			// Skip if not array
			if ( ! is_array( $attachment ) ) {
				continue;
			}
			
			// Extract all possible filename sources
			$filename_sources = array();
			
			if ( ! empty( $attachment['filename'] ) ) {
				$filename_sources[] = $attachment['filename'];
			}
			if ( ! empty( $attachment['title'] ) ) {
				$filename_sources[] = $attachment['title'];
			}
			if ( ! empty( $attachment['name'] ) ) {
				$filename_sources[] = $attachment['name'];
			}
			if ( ! empty( $attachment['guid'] ) ) {
				$filename_sources[] = basename( $attachment['guid'] );
			}
			if ( ! empty( $attachment['url'] ) ) {
				$filename_sources[] = basename( $attachment['url'] );
			}
			
			// Try WordPress attachment fields
			if ( ! empty( $attachment['post_title'] ) ) {
				$filename_sources[] = $attachment['post_title'];
			}
			if ( ! empty( $attachment['post_name'] ) ) {
				$filename_sources[] = $attachment['post_name'];
			}
			
			// Store attachment info for display
			$main_filename = ! empty( $filename_sources ) ? $filename_sources[0] : 'Unknown';
			$attachment_files[] = $main_filename;
			
			// Debug attachment structure
			if ( $this->debug_mode ) {
				$attachment_details[] = array(
					'id' => $attachment['id'] ?? 'no-id',
					'filename' => $attachment['filename'] ?? 'no-filename',
					'title' => $attachment['title'] ?? 'no-title',
					'name' => $attachment['name'] ?? 'no-name',
					'post_title' => $attachment['post_title'] ?? 'no-post-title',
					'guid' => $attachment['guid'] ?? 'no-guid',
					'url' => $attachment['url'] ?? 'no-url',
					'all_keys' => array_keys( $attachment ),
				);
			}
			
			// Check if this attachment matches any required document
			$matched_document = null;
			foreach ( $this->required_documents as $required_doc ) {
				foreach ( $filename_sources as $source ) {
					if ( $this->document_matches( $source, $required_doc ) ) {
						$matched_document = $required_doc;
						if ( ! in_array( $required_doc, $found_documents, true ) ) {
							$found_documents[] = $required_doc;
						}
						
						if ( $this->debug_mode ) {
							error_log( 'TCD Debug - Found document "' . $required_doc . '" in source: ' . $source );
						}
						break 2; // Sai dos dois loops
					}
				}
			}
			
			// Se o anexo não corresponde a nenhum documento requerido, é inválido
			if ( $matched_document === null && ! empty( $this->required_documents ) ) {
				$invalid_documents[] = $main_filename;
				if ( $this->debug_mode ) {
					error_log( 'TCD Debug - Invalid document found: ' . $main_filename . ' (does not match any required document)' );
				}
			}
		}

		// Check for missing documents.
		foreach ( $this->required_documents as $required_doc ) {
			if ( ! in_array( $required_doc, $found_documents, true ) ) {
				$missing_documents[] = $required_doc;
			}
		}

		// Status é incompleto se há documentos faltando OU documentos inválidos
		$status = ( empty( $missing_documents ) && empty( $invalid_documents ) ) ? 'complete' : 'incomplete';

		$result = array(
			'status'            => $status,
			'item_id'           => $item_id,
			'found_documents'   => $found_documents,
			'missing_documents' => $missing_documents,
			'invalid_documents' => $invalid_documents,
			'attachment_files'  => $attachment_files,
			'total_attachments' => count( $attachments ),
			'check_date'        => current_time( 'mysql' ),
		);

		if ( $this->debug_mode ) {
			$result['debug'] = array(
				'api_url'            => $this->api_url . '/items/' . $item_id . '/attachments',
				'required_documents' => $this->required_documents,
				'attachment_details' => $attachment_details,
				'raw_attachments'    => $attachments,
			);
		}

		// Save to database.
		$this->save_check_result( $result );

		return $result;
	}

	/**
	 * Check if a filename matches a required document name.
	 * More flexible matching to handle variations.
	 *
	 * @param string $filename Filename to check.
	 * @param string $required_doc Required document name.
	 * @return bool True if matches.
	 */
	private function document_matches( string $filename, string $required_doc ): bool {
		// Clean up the filename
		$filename = strtolower( trim( $filename ) );
		
		// Remove file extension if present
		$filename_no_ext = pathinfo( $filename, PATHINFO_FILENAME );
		
		// Clean up required document name
		$required_doc_clean = strtolower( trim( $required_doc ) );
		
		// Direct match (with or without extension)
		if ( $filename === $required_doc_clean || $filename_no_ext === $required_doc_clean ) {
			return true;
		}
		
		// Check if the required document name is contained in the filename
		if ( strpos( $filename, $required_doc_clean ) !== false ) {
			return true;
		}
		
		// Check if the filename without extension contains the required document
		if ( strpos( $filename_no_ext, $required_doc_clean ) !== false ) {
			return true;
		}
		
		// Handle underscores vs hyphens
		$required_with_hyphen = str_replace( '_', '-', $required_doc_clean );
		$required_with_underscore = str_replace( '-', '_', $required_doc_clean );
		
		if ( strpos( $filename, $required_with_hyphen ) !== false || 
		     strpos( $filename, $required_with_underscore ) !== false ||
		     strpos( $filename_no_ext, $required_with_hyphen ) !== false ||
		     strpos( $filename_no_ext, $required_with_underscore ) !== false ) {
			return true;
		}
		
		// Handle spaces
		$required_with_space = str_replace( array( '_', '-' ), ' ', $required_doc_clean );
		if ( strpos( $filename, $required_with_space ) !== false ||
		     strpos( $filename_no_ext, $required_with_space ) !== false ) {
			return true;
		}
		
		// Remove all special characters and compare
		$filename_clean = preg_replace( '/[^a-z0-9]/', '', $filename_no_ext );
		$required_clean = preg_replace( '/[^a-z0-9]/', '', $required_doc_clean );
		
		if ( strpos( $filename_clean, $required_clean ) !== false ) {
			return true;
		}
		
		return false;
	}

	/**
	 * Check documents for all items in a collection.
	 *
	 * @param int $collection_id Collection ID.
	 * @param int $page Current page.
	 * @param int $per_page Items per page.
	 * @return array Batch check results.
	 */
	public function check_collection_documents( int $collection_id, int $page = 1, int $per_page = 20 ): array {
		$cache_key = 'tcd_batch_' . $collection_id . '_' . $page . '_' . $per_page;
		$cached    = get_transient( $cache_key );

		// Only use cache if cache duration is greater than 0
		$cache_duration = get_option( 'tcd_cache_duration', 300 );
		if ( false !== $cached && $cache_duration > 0 ) {
			return $cached;
		}

		$items = $this->fetch_collection_items( $collection_id, $page, $per_page );

		if ( is_wp_error( $items ) ) {
			return array(
				'status'  => 'error',
				'message' => $items->get_error_message(),
			);
		}

		// Log the complete response structure
		if ( $this->debug_mode ) {
			error_log( 'TCD Debug - Complete items response: ' . print_r( $items, true ) );
		}

		$results = array(
			'collection_id'   => $collection_id,
			'page'            => $page,
			'per_page'        => $per_page,
			'total_items'     => $items['total'] ?? 0,
			'total_pages'     => $items['total_pages'] ?? 0,
			'items_checked'   => array(),
			'summary'         => array(
				'complete'   => 0,
				'incomplete' => 0,
				'error'      => 0,
			),
			'check_date'      => current_time( 'mysql' ),
		);

		// Check if items exist and are not empty
		if ( empty( $items['items'] ) ) {
			if ( $this->debug_mode ) {
				error_log( 'TCD Debug - No items found in response' );
			}
			return $results;
		}

		foreach ( $items['items'] as $item ) {
			// Try different ID formats
			$item_id = null;
			
			// Check for different possible ID keys
			if ( isset( $item['id'] ) ) {
				$item_id = (int) $item['id'];
			} elseif ( isset( $item['ID'] ) ) {
				$item_id = (int) $item['ID'];
			} elseif ( isset( $item['item_id'] ) ) {
				$item_id = (int) $item['item_id'];
			} elseif ( is_numeric( $item ) ) {
				// Sometimes APIs return just an array of IDs
				$item_id = (int) $item;
				$item = array( 'id' => $item_id );
			}
			
			if ( ! $item_id ) {
				if ( $this->debug_mode ) {
					error_log( 'TCD Debug - Could not extract ID from item: ' . print_r( $item, true ) );
				}
				$results['summary']['error']++;
				continue;
			}
			
			// Extract title with multiple fallbacks
			$item_title = 'Item #' . $item_id;
			if ( isset( $item['title'] ) ) {
				if ( is_array( $item['title'] ) ) {
					$item_title = $item['title']['rendered'] ?? $item['title']['raw'] ?? $item_title;
				} else {
					$item_title = $item['title'];
				}
			} elseif ( isset( $item['name'] ) ) {
				$item_title = $item['name'];
			}
			
			if ( $this->debug_mode ) {
				error_log( 'TCD Debug - Processing item ' . $item_id . ' - ' . $item_title );
			}
			
			$item_result = $this->check_item_documents( $item_id );
			
			$results['items_checked'][] = array(
				'id'                => $item_id,
				'title'             => $item_title,
				'status'            => $item_result['status'],
				'missing_documents' => $item_result['missing_documents'] ?? array(),
				'invalid_documents' => $item_result['invalid_documents'] ?? array(),
				'found_documents'   => $item_result['found_documents'] ?? array(),
				'attachment_files'  => $item_result['attachment_files'] ?? array(),
			);

			if ( 'complete' === $item_result['status'] ) {
				$results['summary']['complete']++;
			} elseif ( 'incomplete' === $item_result['status'] ) {
				$results['summary']['incomplete']++;
			} else {
				$results['summary']['error']++;
			}
		}

		// Cache results if cache is enabled
		if ( $cache_duration > 0 ) {
			set_transient( $cache_key, $results, $cache_duration );
		}

		return $results;
	}

	/**
	 * Fetch attachments for an item via Tainacan API.
	 *
	 * @param int $item_id Item ID.
	 * @return array|WP_Error Array of attachments or error.
	 */
	private function fetch_item_attachments( int $item_id ) {
		$url = $this->api_url . '/items/' . $item_id . '/attachments';

		if ( $this->debug_mode ) {
			error_log( 'TCD Debug - Fetching attachments from: ' . $url );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( null === $data ) {
			return new WP_Error( 'json_decode_error', __( 'Failed to decode API response', 'tainacan-document-checker' ) );
		}

		// Handle API errors
		if ( ! is_array( $data ) ) {
			if ( is_string( $data ) && 'rest_forbidden' === $data ) {
				return new WP_Error( 'rest_forbidden', __( 'Access forbidden. The attachments may be private or restricted.', 'tainacan-document-checker' ) );
			}
			return new WP_Error( 'invalid_response', __( 'Invalid API response format', 'tainacan-document-checker' ) );
		}

		// Handle error responses
		if ( isset( $data['code'] ) && isset( $data['message'] ) ) {
			return new WP_Error( $data['code'], $data['message'] );
		}

		if ( $this->debug_mode && ! empty( $data ) ) {
			error_log( 'TCD Debug - Received ' . count( $data ) . ' attachments for item ' . $item_id );
			if ( count( $data ) > 0 ) {
				error_log( 'TCD Debug - First attachment structure: ' . print_r( $data[0], true ) );
			}
		}

		return $data;
	}

	/**
	 * Fetch items from a collection via Tainacan API.
	 *
	 * @param int $collection_id Collection ID.
	 * @param int $page Page number.
	 * @param int $per_page Items per page.
	 * @return array|WP_Error Array with items and pagination data or error.
	 */
	private function fetch_collection_items( int $collection_id, int $page = 1, int $per_page = 20 ) {
		$url = add_query_arg(
			array(
				'paged'      => $page,
				'perpage'    => $per_page,
				'fetch_only' => 'title,id',
				'orderby'    => 'id',
				'order'      => 'ASC',
			),
			$this->api_url . '/collection/' . $collection_id . '/items'
		);

		if ( $this->debug_mode ) {
			error_log( 'TCD Debug - Fetching items from URL: ' . $url );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$headers = wp_remote_retrieve_headers( $response );
		$body    = wp_remote_retrieve_body( $response );
		$items   = json_decode( $body, true );

		if ( null === $items ) {
			return new WP_Error( 'json_decode_error', __( 'Failed to decode API response', 'tainacan-document-checker' ) );
		}

		// Debug API response structure
		if ( $this->debug_mode ) {
			error_log( 'TCD Debug - API Response structure: ' . print_r( array(
				'type' => gettype( $items ),
				'is_array' => is_array( $items ),
				'count' => is_array( $items ) ? count( $items ) : 'not array',
				'first_item' => is_array( $items ) && ! empty( $items ) ? array_slice( $items, 0, 1 ) : 'empty',
				'headers' => array(
					'X-WP-Total' => $headers['X-WP-Total'] ?? 'not found',
					'X-WP-TotalPages' => $headers['X-WP-TotalPages'] ?? 'not found',
				),
			), true ) );
		}

		// Handle different response structures from Tainacan API
		$total_items = 0;
		$total_pages = 0;
		$items_array = array();

		// Check if response is wrapped
		if ( is_array( $items ) && isset( $items['items'] ) ) {
			$items_array = $items['items'];
			$total_items = $items['total'] ?? count( $items_array );
			$total_pages = $items['total_pages'] ?? ceil( $total_items / $per_page );
		} 
		// Check if it's a direct array of items
		elseif ( is_array( $items ) ) {
			$items_array = $items;
			// Try to get totals from headers first
			$total_items = isset( $headers['X-WP-Total'] ) ? (int) $headers['X-WP-Total'] : count( $items );
			$total_pages = isset( $headers['X-WP-TotalPages'] ) ? (int) $headers['X-WP-TotalPages'] : ceil( $total_items / $per_page );
		}

		return array(
			'items'       => $items_array,
			'total'       => $total_items,
			'total_pages' => $total_pages,
		);
	}

	/**
	 * Save check result to database.
	 *
	 * @param array $result Check result data.
	 * @return bool Success status.
	 */
	private function save_check_result( array $result ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tainacan_document_checks';

		// Extract collection ID from item if not provided
		$collection_id = $result['collection_id'] ?? 0;
		if ( ! $collection_id && isset( $result['item_id'] ) ) {
			$post_type = get_post_type( $result['item_id'] );
			if ( preg_match( '/tnc_col_(\d+)_item/', $post_type, $matches ) ) {
				$collection_id = (int) $matches[1];
			}
		}

		$data = array(
			'item_id'          => $result['item_id'],
			'collection_id'    => $collection_id,
			'check_type'       => $result['check_type'] ?? 'individual',
			'check_status'     => $result['status'],
			'missing_documents' => maybe_serialize( $result['missing_documents'] ),
			'found_documents'   => maybe_serialize( $result['found_documents'] ),
			'check_date'       => $result['check_date'],
		);

		$format = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' );

		return false !== $wpdb->insert( $table_name, $data, $format );
	}

	/**
	 * Get check history for an item.
	 *
	 * @param int $item_id Item ID.
	 * @param int $limit Number of records to retrieve.
	 * @return array Check history.
	 */
	public function get_item_history( int $item_id, int $limit = 10 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tainacan_document_checks';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE item_id = %d ORDER BY check_date DESC LIMIT %d",
				$item_id,
				$limit
			),
			ARRAY_A
		);

		if ( ! $results ) {
			return array();
		}

		// Unserialize document arrays.
		foreach ( $results as &$result ) {
			$result['missing_documents'] = maybe_unserialize( $result['missing_documents'] );
			$result['found_documents']   = maybe_unserialize( $result['found_documents'] );
		}

		return $results;
	}

	/**
	 * Clear cache for a specific item.
	 *
	 * @param int $item_id Item ID.
	 * @return void
	 */
	public function clear_item_cache( int $item_id ): void {
		// Clear single item cache
		delete_transient( 'tcd_item_' . $item_id );
		
		// Clear batch caches that might contain this item
		global $wpdb;
		
		// Get all batch cache transients
		$transients = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_tcd_batch_%'"
		);
		
		foreach ( $transients as $transient ) {
			// Extract collection ID from transient name
			if ( preg_match( '/_transient_tcd_batch_(\d+)_/', $transient, $matches ) ) {
				$collection_id = $matches[1];
				
				// Check if this item belongs to this collection
				$post_type = get_post_type( $item_id );
				if ( strpos( $post_type, 'tnc_col_' . $collection_id . '_item' ) !== false ) {
					delete_option( $transient );
					
					// Also delete the timeout transient
					$timeout_transient = str_replace( '_transient_', '_transient_timeout_', $transient );
					delete_option( $timeout_transient );
				}
			}
		}
	}

	/**
	 * Clear all plugin caches.
	 *
	 * @return int Number of transients cleared.
	 */
	public function clear_all_caches(): int {
		global $wpdb;
		
		// Get all plugin transients
		$transients = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_tcd_%' 
			OR option_name LIKE '_transient_timeout_tcd_%'"
		);
		
		$count = 0;
		foreach ( $transients as $transient ) {
			if ( delete_option( $transient ) ) {
				$count++;
			}
		}
		
		return $count;
	}

	/**
	 * Get cache statistics.
	 *
	 * @return array Cache statistics.
	 */
	public function get_cache_stats(): array {
		global $wpdb;
		
		// Count cached items
		$item_caches = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_tcd_item_%'"
		);
		
		$batch_caches = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_tcd_batch_%'"
		);
		
		// Calculate total cache size
		$cache_size = $wpdb->get_var(
			"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_tcd_%'"
		);
		
		return array(
			'item_caches'  => (int) $item_caches,
			'batch_caches' => (int) $batch_caches,
			'total_caches' => (int) $item_caches + (int) $batch_caches,
			'cache_size'   => $cache_size ? size_format( $cache_size ) : '0 B',
		);
	}
}