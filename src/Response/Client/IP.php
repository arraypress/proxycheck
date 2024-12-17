<?php
/**
 * ProxyCheck.io IP Response Class
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
 * Class Response
 *
 * Handles response data from the ProxyCheck.io API for IP checks.
 */
class IP extends Base {

	/**
	 * Base URLs for various mapping services
	 */
	private const MAP_URLS = [
		'google' => 'https://www.google.com/maps/search/?api=1&query=',
		'apple'  => 'https://maps.apple.com/?q=',
		'bing'   => 'https://www.bing.com/maps?cp=',
		'osm'    => 'https://www.openstreetmap.org/?mlat='
	];

	/**
	 * Default risk threshold for high risk determination
	 */
	private const RISK_HIGH_THRESHOLD = 70;

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
	 * Get formatted continent string
	 *
	 * Returns a formatted string containing continent information
	 * in the format: "Name (Code)" e.g., "North America (NA)"
	 *
	 * @return string|null Formatted continent string or null if no continent info
	 */
	public function get_formatted_continent(): ?string {
		$continent = $this->get_continent();
		if ( ! $continent ) {
			return null;
		}

		$parts = [];
		if ( ! empty( $continent['name'] ) ) {
			$parts[] = $continent['name'];
		}

		if ( ! empty( $continent['code'] ) ) {
			$parts[] = "({$continent['code']})";
		}

		return empty( $parts ) ? null : implode( ' ', $parts );
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
	 * Get formatted country string
	 *
	 * Returns a formatted string containing country information
	 * in the format: "Name (Code) 🇪🇺" e.g., "Germany (DE) 🇪🇺"
	 *
	 * @return string|null Formatted country string or null if no country info
	 */
	public function get_formatted_country(): ?string {
		$country = $this->get_country();
		if ( ! $country ) {
			return null;
		}

		$parts = [];
		if ( ! empty( $country['name'] ) ) {
			$parts[] = $country['name'];
		}

		if ( ! empty( $country['code'] ) ) {
			$parts[] = "({$country['code']})";
		}

		if ( ! empty( $country['is_eu'] ) ) {
			$parts[] = "🇪🇺";
		}

		return empty( $parts ) ? null : implode( ' ', $parts );
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
	 * Get formatted region string
	 *
	 * Returns a formatted string containing region information
	 * in the format: "Name (Code)" e.g., "California (CA)"
	 *
	 * @return string|null Formatted region string or null if no region info
	 */
	public function get_formatted_region(): ?string {
		$region = $this->get_region();
		if ( ! $region ) {
			return null;
		}

		$parts = [];
		if ( ! empty( $region['name'] ) ) {
			$parts[] = $region['name'];
		}

		if ( ! empty( $region['code'] ) ) {
			$parts[] = "({$region['code']})";
		}

		return empty( $parts ) ? null : implode( ' ', $parts );
	}

	/**
	 * Get formatted full address string
	 *
	 * Combines city, region, country, and postcode into a single formatted address
	 * e.g., "Los Angeles, California (CA), United States (US) 90001"
	 *
	 * @return string|null Formatted address string or null if no address info
	 */
	public function get_formatted_address(): ?string {
		$parts = [];

		if ( $city = $this->get_city() ) {
			$parts[] = $city;
		}

		if ( $region = $this->get_formatted_region() ) {
			$parts[] = $region;
		}

		if ( $country = $this->get_formatted_country() ) {
			$parts[] = $country;
		}

		if ( $postcode = $this->get_postcode() ) {
			$parts[] = $postcode;
		}

		return empty( $parts ) ? null : implode( ', ', $parts );
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
	 * Get formatted coordinates string
	 *
	 * Returns coordinates in standard lat/long format
	 * e.g., "51.5074°N, 0.1278°W"
	 *
	 * @return string|null Formatted coordinates string or null if no coordinates
	 */
	public function get_formatted_coordinates(): ?string {
		$coordinates = $this->get_coordinates();
		if ( ! $coordinates ) {
			return null;
		}

		$lat  = $coordinates['latitude'];
		$long = $coordinates['longitude'];

		$lat_direction  = $lat >= 0 ? '°N' : '°S';
		$long_direction = $long >= 0 ? '°E' : '°W';

		return abs( $lat ) . $lat_direction . ', ' . abs( $long ) . $long_direction;
	}

	/**
	 * Get Google Maps URL for the coordinates
	 *
	 * @return string|null URL to view location on Google Maps
	 */
	public function get_google_maps_url(): ?string {
		$coordinates = $this->get_coordinates();
		if ( ! $coordinates ) {
			return null;
		}

		return self::MAP_URLS['google'] .
		       esc_attr( $coordinates['latitude'] ) . ',' .
		       esc_attr( $coordinates['longitude'] );
	}

	/**
	 * Get Apple Maps URL for the coordinates
	 *
	 * @return string|null URL to view location on Apple Maps
	 */
	public function get_apple_maps_url(): ?string {
		$coordinates = $this->get_coordinates();
		if ( ! $coordinates ) {
			return null;
		}

		return self::MAP_URLS['apple'] .
		       esc_attr( $coordinates['latitude'] ) . ',' .
		       esc_attr( $coordinates['longitude'] );
	}

	/**
	 * Get Bing Maps URL for the coordinates
	 *
	 * @return string|null URL to view location on Bing Maps
	 */
	public function get_bing_maps_url(): ?string {
		$coordinates = $this->get_coordinates();
		if ( ! $coordinates ) {
			return null;
		}

		return self::MAP_URLS['bing'] .
		       esc_attr( $coordinates['latitude'] ) . '~' .
		       esc_attr( $coordinates['longitude'] );
	}

	/**
	 * Get OpenStreetMap URL for the coordinates
	 *
	 * @return string|null URL to view location on OpenStreetMap
	 */
	public function get_openstreetmap_url(): ?string {
		$coordinates = $this->get_coordinates();
		if ( ! $coordinates ) {
			return null;
		}

		return self::MAP_URLS['osm'] .
		       esc_attr( $coordinates['latitude'] ) .
		       '&mlon=' . esc_attr( $coordinates['longitude'] ) .
		       '&zoom=12';
	}

	/**
	 * Get all map URLs for the coordinates
	 *
	 * @return array Array of map service URLs
	 */
	public function get_map_urls(): array {
		return [
			'google' => $this->get_google_maps_url(),
			'apple'  => $this->get_apple_maps_url(),
			'bing'   => $this->get_bing_maps_url(),
			'osm'    => $this->get_openstreetmap_url()
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
	 * Get formatted currency string
	 *
	 * Returns a formatted string containing available currency information
	 * in the format: "Symbol Code (Name)" e.g., "$ USD (United States Dollar)"
	 *
	 * @param string $separator Separator between currency parts (default: ' ')
	 *
	 * @return string|null Formatted currency string or null if no currency info
	 */
	public function get_formatted_currency( string $separator = ' ' ): ?string {
		$currency = $this->get_currency();
		if ( ! $currency ) {
			return null;
		}

		$currency_parts = [];

		if ( ! empty( $currency['symbol'] ) ) {
			$currency_parts[] = $currency['symbol'];
		}

		if ( ! empty( $currency['code'] ) ) {
			$currency_parts[] = $currency['code'];
		}

		if ( ! empty( $currency['name'] ) ) {
			$currency_parts[] = "({$currency['name']})";
		}

		return empty( $currency_parts ) ? null : implode( $separator, $currency_parts );
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

	/**
	 * Get operator details including anonymity, popularity and protocols
	 *
	 * @return array|null Array of operator details or null if not available
	 */
	public function get_operator_details(): ?array {
		if ( ! isset( $this->data[ $this->identifier ]['operator'] ) ) {
			return null;
		}

		$operator = $this->data[ $this->identifier ]['operator'];

		return [
			'name'       => $operator['name'] ?? null,
			'url'        => $operator['url'] ?? null,
			'anonymity'  => $operator['anonymity'] ?? null,
			'popularity' => $operator['popularity'] ?? null,
			'protocols'  => $operator['protocols'] ?? [],
			'policies'   => $operator['policies'] ?? []
		];
	}

	/**
	 * Get VPN operator policies if available
	 *
	 * @return array|null Array of operator policies or null if not available
	 */
	public function get_operator_policies(): ?array {
		return $this->data[ $this->identifier ]['operator']['policies'] ?? null;
	}

	/**
	 * Get formatted operator string
	 *
	 * Combines provider name and ASN information
	 * e.g., "Amazon Web Services (AS16509)"
	 *
	 * @return string|null Formatted operator string or null if no operator info
	 */
	public function get_formatted_operator(): ?string {
		$operator = $this->get_operator();
		if ( ! $operator ) {
			return null;
		}

		$parts = [];
		if ( ! empty( $operator['name'] ) ) {
			$parts[] = $operator['name'];
		}

		if ( ! empty( $operator['asn'] ) ) {
			$parts[] = "(${operator['asn']})";
		}

		return empty( $parts ) ? null : implode( ' ', $parts );
	}

	/**
	 * Get formatted attack history string
	 *
	 * Returns a human-readable summary of attack history
	 * e.g., "Login Attempts: 5, Comment Spam: 3"
	 *
	 * @return string|null Formatted attack history or null if no history
	 */
	public function get_formatted_attack_history(): ?string {
		$history = $this->get_attack_history();
		if ( ! $history ) {
			return null;
		}

		$parts = [];
		foreach ( $history as $type => $count ) {
			$readable_type = ucwords( str_replace( '_', ' ', $type ) );
			$parts[]       = "$readable_type: $count";
		}

		return empty( $parts ) ? null : implode( ', ', $parts );
	}

	/**
	 * Get formatted devices string
	 *
	 * Returns a human-readable summary of device counts
	 * e.g., "Address: 145, Subnet: 1024"
	 *
	 * @return string|null Formatted devices string or null if no device info
	 */
	public function get_formatted_devices(): ?string {
		$devices = $this->get_devices();
		if ( ! $devices ) {
			return null;
		}

		$parts = [];
		foreach ( $devices as $type => $count ) {
			$readable_type = ucfirst( $type );
			$parts[]       = "$readable_type: $count";
		}

		return empty( $parts ) ? null : implode( ', ', $parts );
	}

	/**
	 * Check if this is a high risk IP
	 *
	 * @param int $threshold Risk score threshold (default: 70)
	 *
	 * @return bool True if risk score exceeds threshold
	 */
	public function is_high_risk( int $threshold = self::RISK_HIGH_THRESHOLD ): bool {
		return ( $this->get_risk_score() ?? 0 ) > $threshold;
	}

}