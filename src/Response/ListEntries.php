<?php
/**
 * ProxyCheck.io List Entries Response Class
 *
 * @package     ArrayPress/ProxyCheck
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\ProxyCheck\Response;

/**
 * Class ListEntries
 *
 * Handles response data from the ProxyCheck.io API for list operations.
 */
class ListEntries {

	/**
	 * Raw response data from the API
	 *
	 * @var array
	 */
	private array $data;

	/**
	 * Initialize the response object
	 *
	 * @param array $data Raw response data from ProxyCheck API
	 */
	public function __construct( array $data ) {
		$this->data = $data;
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
	 * Get list entries
	 *
	 * @return array Array of entries in the list
	 */
	public function get_entries(): array {
		// Check for empty list message
		if ( isset( $this->data['message'] ) && $this->data['message'] === 'This list is empty.' ) {
			return [];
		}

		// Check for addresses array
		if ( isset( $this->data['addresses'] ) && is_array( $this->data['addresses'] ) ) {
			return $this->data['addresses'];
		}

		// Check for Raw array (alternative format)
		if ( isset( $this->data['Raw'] ) && is_array( $this->data['Raw'] ) ) {
			return array_filter( $this->data['Raw'] ); // Remove any empty entries
		}

		return [];
	}

	/**
	 * Convert the entries to a string
	 *
	 * @return string
	 */
	public function __toString(): string {
		$entries = $this->get_entries();

		return ! empty( $entries ) ? implode( "\n", $entries ) : '';
	}

	/**
	 * Check if list is empty
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return empty( $this->get_entries() );
	}

	/**
	 * Get status message
	 *
	 * @return string|null
	 */
	public function get_status(): ?string {
		return $this->data['status'] ?? null;
	}

	/**
	 * Get message
	 *
	 * @return string|null
	 */
	public function get_message(): ?string {
		return $this->data['message'] ?? null;
	}

	/**
	 * Check if operation was successful
	 *
	 * @return bool
	 */
	public function is_successful(): bool {
		return ( $this->data['status'] ?? '' ) === 'ok';
	}

}