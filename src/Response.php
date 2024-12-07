<?php
/**
 * ProxyCheck.io Response Class
 *
 * @package     ArrayPress/ProxyCheck
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\ProxyCheck;

/**
 * Class Response
 *
 * Handles response data from the ProxyCheck.io API for IP checks.
 */
class Response {

	/**
	 * Raw response data from the API
	 *
	 * @var array
	 */
	private array $data;

	/**
	 * The IP address being checked
	 *
	 * @var string
	 */
	private string $ip;

	/**
	 * Initialize the response object
	 *
	 * @param array $data Raw response data from ProxyCheck API
	 */
	public function __construct( array $data ) {
		$this->data = $data;
		// Extract the IP from the response data
		$this->ip = array_key_first( array_diff_key( $data, [ 'status'     => 1,
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
	 * Get the IP address that was checked
	 *
	 * @return string|null
	 */
	public function get_ip(): ?string {
		return $this->ip ?? null;
	}

	/**
	 * Check if the IP is a proxy
	 *
	 * @return bool
	 */
	public function is_proxy(): bool {
		return ( $this->data[ $this->ip ]['proxy'] ?? null ) === 'yes';
	}

	/**
	 * Get the proxy type (e.g., 'Residential', 'VPN', etc.)
	 *
	 * @return string|null
	 */
	public function get_type(): ?string {
		return $this->data[ $this->ip ]['type'] ?? null;
	}

	/**
	 * Get the provider name
	 *
	 * @return string|null
	 */
	public function get_provider(): ?string {
		return $this->data[ $this->ip ]['provider'] ?? null;
	}

	/**
	 * Get the organization name
	 *
	 * @return string|null
	 */
	public function get_organisation(): ?string {
		return $this->data[ $this->ip ]['organisation'] ?? null;
	}

	/**
	 * Get the risk score (0-100)
	 *
	 * @return int|null
	 */
	public function get_risk_score(): ?int {
		return isset( $this->data[ $this->ip ]['risk'] ) ? (int) $this->data[ $this->ip ]['risk'] : null;
	}

	/**
	 * Get the hostname if available
	 *
	 * @return string|null
	 */
	public function get_hostname(): ?string {
		return $this->data[ $this->ip ]['hostname'] ?? null;
	}

	/**
	 * Get the IP range
	 *
	 * @return string|null
	 */
	public function get_range(): ?string {
		return $this->data[ $this->ip ]['range'] ?? null;
	}

	/**
	 * Get ASN information
	 *
	 * @return string|null
	 */
	public function get_asn(): ?string {
		return $this->data[ $this->ip ]['asn'] ?? null;
	}

	/**
	 * Get device information
	 *
	 * @return array|null
	 */
	public function get_devices(): ?array {
		if ( ! isset( $this->data[ $this->ip ]['devices'] ) ) {
			return null;
		}

		return [
			'address' => (int) ( $this->data[ $this->ip ]['devices']['address'] ?? 0 ),
			'subnet'  => (int) ( $this->data[ $this->ip ]['devices']['subnet'] ?? 0 ),
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
			if ( isset( $this->data[ $this->ip ][ $type ] ) ) {
				$history[ $type ] = (int) $this->data[ $this->ip ][ $type ];
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
		return isset( $this->data[ $this->ip ]['port'] ) ? (int) $this->data[ $this->ip ]['port'] : null;
	}

	/**
	 * Get when this proxy was last seen
	 *
	 * @return string|null
	 */
	public function get_last_seen(): ?string {
		return $this->data[ $this->ip ]['seen'] ?? null;
	}

	/**
	 * Get the operator/ASN information
	 *
	 * @return array|null
	 */
	public function get_operator(): ?array {
		$operator = [];

		if ( isset( $this->data[ $this->ip ]['provider'] ) ) {
			$operator['name'] = $this->data[ $this->ip ]['provider'];
		}

		if ( isset( $this->data[ $this->ip ]['asn'] ) ) {
			$operator['asn'] = $this->data[ $this->ip ]['asn'];
		}

		return ! empty( $operator ) ? $operator : null;
	}

	/**
	 * Get continent information
	 *
	 * @return array|null
	 */
	public function get_continent(): ?array {
		if ( ! isset( $this->data[ $this->ip ]['continent'] ) ) {
			return null;
		}

		return [
			'name' => $this->data[ $this->ip ]['continent'] ?? null,
			'code' => $this->data[ $this->ip ]['continentcode'] ?? null,
		];
	}

	/**
	 * Get country information
	 *
	 * @return array|null
	 */
	public function get_country(): ?array {
		if ( ! isset( $this->data[ $this->ip ]['country'] ) ) {
			return null;
		}

		return [
			'name'  => $this->data[ $this->ip ]['country'] ?? null,
			'code'  => $this->data[ $this->ip ]['isocode'] ?? null,
			'is_eu' => ( $this->data[ $this->ip ]['europe'] ?? null ) === 1,
		];
	}

	/**
	 * Get region information
	 *
	 * @return array|null
	 */
	public function get_region(): ?array {
		if ( ! isset( $this->data[ $this->ip ]['region'] ) ) {
			return null;
		}

		return [
			'name' => $this->data[ $this->ip ]['region'] ?? null,
			'code' => $this->data[ $this->ip ]['regioncode'] ?? null,
		];
	}

	/**
	 * Get city name
	 *
	 * @return string|null
	 */
	public function get_city(): ?string {
		return $this->data[ $this->ip ]['city'] ?? null;
	}

	/**
	 * Get postal/zip code
	 *
	 * @return string|null
	 */
	public function get_postcode(): ?string {
		return $this->data[ $this->ip ]['postcode'] ?? null;
	}

	/**
	 * Get location coordinates
	 *
	 * @return array|null
	 */
	public function get_coordinates(): ?array {
		if ( ! isset( $this->data[ $this->ip ]['latitude'] ) || ! isset( $this->data[ $this->ip ]['longitude'] ) ) {
			return null;
		}

		return [
			'latitude'  => (float) $this->data[ $this->ip ]['latitude'],
			'longitude' => (float) $this->data[ $this->ip ]['longitude'],
		];
	}

	/**
	 * Get currency information
	 *
	 * @return array|null
	 */
	public function get_currency(): ?array {
		if ( ! isset( $this->data[ $this->ip ]['currency'] ) ) {
			return null;
		}

		return [
			'code'   => $this->data[ $this->ip ]['currency']['code'] ?? null,
			'name'   => $this->data[ $this->ip ]['currency']['name'] ?? null,
			'symbol' => $this->data[ $this->ip ]['currency']['symbol'] ?? null,
		];
	}

	/**
	 * Get timezone information
	 *
	 * @return string|null
	 */
	public function get_timezone(): ?string {
		return $this->data[ $this->ip ]['timezone'] ?? null;
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
	 * Get node information
	 *
	 * @return string|null
	 */
	public function get_node(): ?string {
		return $this->data['node'] ?? null;
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