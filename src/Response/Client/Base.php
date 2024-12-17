<?php
/**
 * ProxyCheck.io Base Client Response Class
 *
 * @package     ArrayPress\ProxyCheck
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\ProxyCheck\Response\Client;

/**
 * Abstract class BaseResponse
 *
 * Base response handler for ProxyCheck.io API responses.
 */
abstract class Base {

	/**
	 * Raw response data from the API
	 *
	 * @var array
	 */
	protected array $data;

	/**
	 * The identifier (IP or email) being checked
	 *
	 * @var string
	 */
	protected string $identifier;

	/**
	 * Block status constants
	 */
	protected const BLOCK_YES = 'yes';
	protected const BLOCK_NO = 'no';
	protected const BLOCK_NA = 'na';


	/**
	 * Initialize the response object
	 *
	 * @param array $data Raw response data from ProxyCheck API
	 */
	public function __construct( array $data ) {

		$this->data = $data;
		// Extract the identifier from the response data
		$this->identifier = array_key_first( array_diff_key( $data, [
			'status'     => 1,
			'message'    => 1,
			'node'       => 1,
			'query time' => 1
		] ) );

	}

	/**
	 * Get raw data array
	 *
	 * @return array
	 */
	public function get_all(): array {
		return $this->data;
	}

	/**
	 * Get node information
	 *
	 * @return string|null
	 */
	public function get_node(): ?string {
		return $this->data['node'] ?? null;
	}

	/**
	 * Get query time
	 *
	 * @return float|null
	 */
	public function get_query_time(): ?float {
		if ( ! isset( $this->data['query time'] ) ) {
			return null;
		}

		return (float) str_replace( 's', '', $this->data['query time'] );
	}

	/**
	 * Get status message if present
	 *
	 * @return string|null
	 */
	public function get_status(): ?string {
		return $this->data['status'] ?? null;
	}

	/**
	 * Get error message if present
	 *
	 * @return string|null
	 */
	public function get_message(): ?string {
		return $this->data['message'] ?? null;
	}

	/**
	 * Check if the query was successful
	 *
	 * @return bool
	 */
	public function is_successful(): bool {
		return ( $this->data['status'] ?? 'ok' ) === 'ok';
	}

	/**
	 * Get block status
	 *
	 * @return bool True if IP/email should be blocked
	 */
	public function should_block(): bool {
		return ( $this->data['block'] ?? self::BLOCK_NO ) === self::BLOCK_YES;
	}

	/**
	 * Get reason for blocking if blocked
	 *
	 * @return string|null Block reason or null if not blocked
	 */
	public function get_block_reason(): ?string {
		return $this->data['block_reason'] ?? null;
	}

	/**
	 * Get detailed block information
	 *
	 * @return array|null Array of block details or null if not available
	 */
	public function get_block_details(): ?array {
		return $this->data['block_details'] ?? null;
	}

}