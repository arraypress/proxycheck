<?php
/**
 * ProxyCheck.io IP Response Class
 *
 * @package     ArrayPress/ProxyCheck
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\ProxyCheck\Response;

/**
 * Class Response
 *
 * Handles response data from the ProxyCheck.io API for IP checks.
 */
class IP extends Base {

	/**
	 * Get the IP address that was checked
	 *
	 * @return string|null
	 */
	public function get_ip(): ?string {
		return $this->identifier ?? null;
	}

	/**
	 * Check if the IP is a proxy
	 *
	 * @return bool
	 */
	public function is_proxy(): bool {
		return ( $this->data[ $this->identifier ]['proxy'] ?? null ) === 'yes';
	}

	/**
	 * Get the proxy type (e.g., 'Residential', 'VPN', etc.)
	 *
	 * @return string|null
	 */
	public function get_type(): ?string {
		return $this->data[ $this->identifier ]['type'] ?? null;
	}

	/**
	 * Get the provider name
	 *
	 * @return string|null
	 */
	public function get_provider(): ?string {
		return $this->data[ $this->identifier ]['provider'] ?? null;
	}

	/**
	 * Get the organization name
	 *
	 * @return string|null
	 */
	public function get_organisation(): ?string {
		return $this->data[ $this->identifier ]['organisation'] ?? null;
	}

	/**
	 * Get the risk score (0-100)
	 *
	 * @return int|null
	 */
	public function get_risk_score(): ?int {
		return isset( $this->data[ $this->identifier ]['risk'] ) ? (int) $this->data[ $this->identifier ]['risk'] : null;
	}

	/**
	 * Get the hostname if available
	 *
	 * @return string|null
	 */
	public function get_hostname(): ?string {
		return $this->data[ $this->identifier ]['hostname'] ?? null;
	}

	/**
	 * Get the IP range
	 *
	 * @return string|null
	 */
	public function get_range(): ?string {
		return $this->data[ $this->identifier ]['range'] ?? null;
	}

	/**
	 * Get ASN information
	 *
	 * @return string|null
	 */
	public function get_asn(): ?string {
		return $this->data[ $this->identifier ]['asn'] ?? null;
	}

	/**
	 * Get device information
	 *
	 * @return array|null
	 */
	public function get_devices(): ?array {
		if ( ! isset( $this->data[ $this->identifier ]['devices'] ) ) {
			return null;
		}

		return [
			'address' => (int) ( $this->data[ $this->identifier ]['devices']['address'] ?? 0 ),
			'subnet'  => (int) ( $this->data[ $this->identifier ]['devices']['subnet'] ?? 0 ),
		];
	}

	/**
	 * Get attack history data
	 * Only available when risk=2 flag is used
	 *
	 * @return array|null
	 */
	public function get_attack_history(): ?array {
		$history      = [];
		$attack_types = [
			'login_attempts',
			'registration_attempts',
			'comment_spam',
			'denial_of_service',
			'forum_spam',
			'form_submission',
			'vulnerability_probing'
		];

		foreach ( $attack_types as $type ) {
			if ( isset( $this->data[ $this->identifier ][ $type ] ) ) {
				$history[ $type ] = (int) $this->data[ $this->identifier ][ $type ];
			}
		}

		return ! empty( $history ) ? $history : null;
	}

	/**
	 * Get the port number if detected
	 *
	 * @return int|null
	 */
	public function get_port(): ?int {
		return isset( $this->data[ $this->identifier ]['port'] ) ? (int) $this->data[ $this->identifier ]['port'] : null;
	}

	/**
	 * Get when this proxy was last seen
	 *
	 * @return string|null
	 */
	public function get_last_seen(): ?string {
		return $this->data[ $this->identifier ]['seen'] ?? null;
	}

	/**
	 * Get the operator/ASN information
	 *
	 * @return array|null
	 */
	public function get_operator(): ?array {
		$operator = [];

		if ( isset( $this->data[ $this->identifier ]['provider'] ) ) {
			$operator['name'] = $this->data[ $this->identifier ]['provider'];
		}

		if ( isset( $this->data[ $this->identifier ]['asn'] ) ) {
			$operator['asn'] = $this->data[ $this->identifier ]['asn'];
		}

		return ! empty( $operator ) ? $operator : null;
	}

	/**
	 * Get continent information
	 *
	 * @return array|null
	 */
	public function get_continent(): ?array {
		if ( ! isset( $this->data[ $this->identifier ]['continent'] ) ) {
			return null;
		}

		return [
			'name' => $this->data[ $this->identifier ]['continent'] ?? null,
			'code' => $this->data[ $this->identifier ]['continentcode'] ?? null,
		];
	}

	/**
	 * Get country information
	 *
	 * @return array|null
	 */
	public function get_country(): ?array {
		if ( ! isset( $this->data[ $this->identifier ]['country'] ) ) {
			return null;
		}

		return [
			'name'  => $this->data[ $this->identifier ]['country'] ?? null,
			'code'  => $this->data[ $this->identifier ]['isocode'] ?? null,
			'is_eu' => ( $this->data[ $this->identifier ]['europe'] ?? null ) === 1,
		];
	}

	/**
	 * Get region information
	 *
	 * @return array|null
	 */
	public function get_region(): ?array {
		if ( ! isset( $this->data[ $this->identifier ]['region'] ) ) {
			return null;
		}

		return [
			'name' => $this->data[ $this->identifier ]['region'] ?? null,
			'code' => $this->data[ $this->identifier ]['regioncode'] ?? null,
		];
	}

	/**
	 * Get city name
	 *
	 * @return string|null
	 */
	public function get_city(): ?string {
		return $this->data[ $this->identifier ]['city'] ?? null;
	}

	/**
	 * Get postal/zip code
	 *
	 * @return string|null
	 */
	public function get_postcode(): ?string {
		return $this->data[ $this->identifier ]['postcode'] ?? null;
	}

	/**
	 * Get location coordinates
	 *
	 * @return array|null
	 */
	public function get_coordinates(): ?array {
		if ( ! isset( $this->data[ $this->identifier ]['latitude'] ) || ! isset( $this->data[ $this->identifier ]['longitude'] ) ) {
			return null;
		}

		return [
			'latitude'  => (float) $this->data[ $this->identifier ]['latitude'],
			'longitude' => (float) $this->data[ $this->identifier ]['longitude'],
		];
	}

	/**
	 * Get currency information
	 *
	 * @return array|null
	 */
	public function get_currency(): ?array {
		if ( ! isset( $this->data[ $this->identifier ]['currency'] ) ) {
			return null;
		}

		return [
			'code'   => $this->data[ $this->identifier ]['currency']['code'] ?? null,
			'name'   => $this->data[ $this->identifier ]['currency']['name'] ?? null,
			'symbol' => $this->data[ $this->identifier ]['currency']['symbol'] ?? null,
		];
	}

	/**
	 * Get timezone information
	 *
	 * @return string|null
	 */
	public function get_timezone(): ?string {
		return $this->data[ $this->identifier ]['timezone'] ?? null;
	}

	/**
	 * Determine if this is a VPN
	 * Only available when vpn flag is used
	 *
	 * @return bool|null
	 */
	public function is_vpn(): ?bool {
		$type = $this->get_type();

		return $type !== null ? strtolower( $type ) === 'vpn' : null;
	}

}