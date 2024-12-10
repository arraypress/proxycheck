<?php
/**
 * ProxyCheck.io Base Response Class
 *
 * @package     ArrayPress/ProxyCheck
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\ProxyCheck\Response;

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

}