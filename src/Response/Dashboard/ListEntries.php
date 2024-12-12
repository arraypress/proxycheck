<?php
/**
 * Class ListEntries
 *
 * Handles response data from the ProxyCheck.io API for list operations.
 *
 * @package     ArrayPress/ProxyCheck
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\ProxyCheck\Response\Dashboard;

class ListEntries extends Base {
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
	 * Format the list entries
	 *
	 * @return array Array of formatted entries
	 */
	public function format(): array {
		return [
			'entries' => $this->get_entries(),
			'count'   => count( $this->get_entries() ),
			'status'  => $this->get_status(),
			'message' => $this->get_message()
		];
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

}