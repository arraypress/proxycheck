<?php
/**
 * Class DetectionEntry
 *
 * Handles detection response from ProxyCheck.io
 *
 * @package     ArrayPress/ProxyCheck
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\ProxyCheck\Response\Dashboard;

class DetectionEntry extends Base {

	/**
	 * Format the detections data
	 *
	 * @return array Array of formatted detection entries
	 */
	public function format(): array {
		$formatted = [];
		foreach ( $this->data as $key => $detection ) {
			if ( ! is_numeric( $key ) ) {
				continue;
			}

			$formatted[] = [
				'time'     => $detection['time formatted'] ?? '',
				'time_raw' => $detection['time raw'] ?? '',
				'address'  => $detection['address'] ?? '',
				'type'     => $detection['detection type'] ?? '',
				'node'     => $detection['answering node'] ?? '',
				'tag'      => $detection['tag'] ?? '',
				'country'  => $detection['country'] ?? '',
				'port'     => $detection['port'] ?? null
			];
		}

		return $formatted;
	}

	/**
	 * Get detection count
	 *
	 * @return int Number of detections
	 */
	public function get_count(): int {
		return count( array_filter( array_keys( $this->data ), 'is_numeric' ) );
	}

}