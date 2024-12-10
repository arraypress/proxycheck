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
		if ( isset( $this->data['list'] ) ) {
			$entries = trim( $this->data['list'] );

			return ! empty( $entries ) ? explode( "\n", $entries ) : [];
		}

		return [];
	}

	/**
	 * Get origin count (for CORS responses)
	 *
	 * @return int|null
	 */
	public function get_origin_count(): ?int {
		return $this->data['origin_count'] ?? null;
	}

	/**
	 * Check if list is empty
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		$entries = $this->get_entries();

		return empty( $entries );
	}

	/**
	 * Get count of entries
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->get_entries() );
	}

	/**
	 * Check if operation was successful
	 *
	 * @return bool
	 */
	public function is_successful(): bool {
		return ( $this->data['status'] ?? 'ok' ) === 'ok';
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
	 * Get error or success message
	 *
	 * @return string|null
	 */
	public function get_message(): ?string {
		return $this->data['message'] ?? null;
	}

	/**
	 * Convert the entries to a string
	 *
	 * @return string
	 */
	public function __toString(): string {
		return implode( "\n", $this->get_entries() );
	}

}