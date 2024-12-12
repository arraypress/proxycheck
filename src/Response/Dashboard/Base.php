<?php
/**
 * ProxyCheck.io Base Dashboard Response Class
 *
 * Provides common functionality for all dashboard response classes.
 *
 * @package     ArrayPress/ProxyCheck
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\ProxyCheck\Response\Dashboard;

/**
 * Abstract class BaseResponse
 *
 * Base response handler for ProxyCheck.io API responses.
 */
abstract class Base {

	/**
	 * Raw response data
	 */
	protected array $data;

	/**
	 * Constructor
	 *
	 * @param array $data Raw response data
	 */
	public function __construct( array $data ) {
		$this->data = $data;
	}

	/**
	 * Get raw response data
	 *
	 * @return array
	 */
	public function get_raw_data(): array {
		return $this->data;
	}

	/**
	 * Format the response data for consistent output
	 *
	 * @return array
	 */
	abstract public function format(): array;

	/**
	 * Get response status if available
	 *
	 * @return string|null
	 */
	public function get_status(): ?string {
		return $this->data['status'] ?? null;
	}

	/**
	 * Get response message if available
	 *
	 * @return string|null
	 */
	public function get_message(): ?string {
		return $this->data['message'] ?? null;
	}

	/**
	 * Check if the response indicates success
	 *
	 * @return bool
	 */
	public function is_successful(): bool {
		return ( $this->get_status() === 'ok' );
	}

	/**
	 * Safely get integer value from data
	 *
	 * @param string $key     The key to retrieve
	 * @param int    $default Default value if not found
	 *
	 * @return int
	 */
	protected function get_int( string $key, int $default = 0 ): int {
		return (int) ( $this->data[ $key ] ?? $default );
	}

	/**
	 * Safely get nested integer value from data
	 *
	 * @param string $parent  Parent key
	 * @param string $key     The key to retrieve
	 * @param int    $default Default value if not found
	 *
	 * @return int
	 */
	protected function get_nested_int( string $parent, string $key, int $default = 0 ): int {
		return (int) ( $this->data[ $parent ][ $key ] ?? $default );
	}

	/**
	 * Safely get string value from data
	 *
	 * @param string $key     The key to retrieve
	 * @param string $default Default value if not found
	 *
	 * @return string
	 */
	protected function get_string( string $key, string $default = '' ): string {
		return (string) ( $this->data[ $key ] ?? $default );
	}

	/**
	 * Safely get array value from data
	 *
	 * @param string $key     The key to retrieve
	 * @param array  $default Default value if not found
	 *
	 * @return array
	 */
	protected function get_array( string $key, array $default = [] ): array {
		return is_array( $this->data[ $key ] ?? null ) ? $this->data[ $key ] : $default;
	}

}