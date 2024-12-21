<?php
/**
 * ProxyCheck.io Parameters Trait
 *
 * @package     ArrayPress\ProxyCheck
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\ProxyCheck\Traits;

use WP_Error;

trait Parameters {

	/**
	 * API key for ProxyCheck.io service
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Cache settings
	 *
	 * @var array
	 */
	private array $cache_settings = [
		'enabled'    => true,
		'expiration' => 600,
		'prefix'     => 'proxycheck_'
	];

	/**
	 * Current parameters array to store active settings
	 *
	 * @var array
	 */
	private array $current_params = [
		'vpn'           => 0,    // VPN detection disabled by default
		'asn'           => 0,    // ASN data disabled by default
		'node'          => 0,    // Node information disabled by default
		'time'          => 0,    // Query time disabled by default
		'inf'           => 0,    // Basic proxy information disabled by default
		'risk'          => 0,    // Risk score disabled by default
		'port'          => 0,    // Port check disabled by default
		'seen'          => 0,    // Last seen disabled by default
		'days'          => 7,    // Default history period
		'tag'           => '',   // No default tag
		'ver'           => 2,    // API version 2
		'mask'          => 0,    // Email address masking disabled by default
		'query_tagging' => 0,    // Query tagging disabled by default
	];

	/**
	 * Array of country codes/names to block
	 *
	 * @var array
	 */
	private array $blocked_countries = [];

	/**
	 * Array of country codes/names to allow
	 *
	 * @var array
	 */
	private array $allowed_countries = [];

	/** API Key *******************************************************************/

	/**
	 * Set API key
	 *
	 * @param string $api_key The API key to use
	 *
	 * @return self
	 */
	public function set_api_key( string $api_key ): self {
		$this->api_key = $api_key;

		return $this;
	}

	/**
	 * Get API key
	 *
	 * @return string
	 */
	public function get_api_key(): string {
		return $this->api_key;
	}

	/** Cache Settings **********************************************************/

	/**
	 * Set cache status
	 *
	 * @param bool $enable Whether to enable caching
	 *
	 * @return self
	 */
	public function set_cache_enabled( bool $enable ): self {
		$this->cache_settings['enabled'] = $enable;

		return $this;
	}

	/**
	 * Get cache status
	 *
	 * @return bool
	 */
	public function is_cache_enabled(): bool {
		return $this->cache_settings['enabled'];
	}

	/**
	 * Set cache expiration time
	 *
	 * @param int $seconds Cache expiration time in seconds
	 *
	 * @return self|WP_Error
	 */
	public function set_cache_expiration( int $seconds ) {
		if ( $seconds < 0 ) {
			return new WP_Error(
				'invalid_expiration',
				__( 'Cache expiration time cannot be negative', 'arraypress' )
			);
		}
		$this->cache_settings['expiration'] = $seconds;

		return $this;
	}

	/**
	 * Get cache expiration time
	 *
	 * @return int
	 */
	public function get_cache_expiration(): int {
		return $this->cache_settings['expiration'];
	}

	/**
	 * Set cache prefix
	 *
	 * @param string $prefix Cache key prefix
	 *
	 * @return self
	 */
	public function set_cache_prefix( string $prefix ): self {
		$this->cache_settings['prefix'] = $prefix;

		return $this;
	}

	/**
	 * Get cache prefix
	 *
	 * @return string
	 */
	public function get_cache_prefix(): string {
		return $this->cache_settings['prefix'];
	}

	/** Parameter Methods *******************************************************/

	/**
	 * Get all current parameters
	 *
	 * @return array
	 */
	public function get_parameters(): array {
		return $this->current_params;
	}

	/**
	 * Set VPN detection mode
	 *
	 * @param int $mode VPN detection mode (0-3)
	 *
	 * @return self|WP_Error
	 */
	public function set_vpn( int $mode ) {
		if ( $mode < 0 || $mode > 3 ) {
			return new WP_Error(
				'invalid_vpn_mode',
				__( 'VPN mode must be between 0 and 3', 'arraypress' )
			);
		}
		$this->current_params['vpn'] = $mode;

		return $this;
	}

	/**
	 * Get VPN detection mode
	 *
	 * @return int
	 */
	public function get_vpn(): int {
		return (int) $this->current_params['vpn'];
	}

	/**
	 * Set ASN lookup
	 *
	 * @param bool $enable Whether to enable ASN lookups
	 *
	 * @return self
	 */
	public function set_asn( bool $enable ): self {
		$this->current_params['asn'] = $enable ? 1 : 0;

		return $this;
	}

	/**
	 * Get ASN lookup setting
	 *
	 * @return bool
	 */
	public function get_asn(): bool {
		return (bool) $this->current_params['asn'];
	}

	/**
	 * Set node information parameter
	 *
	 * @param bool $enabled Whether to include node information
	 *
	 * @return self
	 */
	public function set_node( bool $enabled ): self {
		$this->current_params['node'] = $enabled ? 1 : 0;

		return $this;
	}

	/**
	 * Get node information setting
	 *
	 * @return bool
	 */
	public function get_node(): bool {
		return (bool) $this->current_params['node'];
	}

	/**
	 * Set query time parameter
	 *
	 * @param bool $enabled Whether to include query time
	 *
	 * @return self
	 */
	public function set_time( bool $enabled ): self {
		$this->current_params['time'] = $enabled ? 1 : 0;

		return $this;
	}

	/**
	 * Get query time setting
	 *
	 * @return bool
	 */
	public function get_time(): bool {
		return (bool) $this->current_params['time'];
	}

	/**
	 * Set basic proxy information parameter
	 *
	 * @param bool $enabled Whether to include basic proxy information
	 *
	 * @return self
	 */
	public function set_inf( bool $enabled ): self {
		$this->current_params['inf'] = $enabled ? 1 : 0;

		return $this;
	}

	/**
	 * Get basic proxy information setting
	 *
	 * @return bool
	 */
	public function get_inf(): bool {
		return (bool) $this->current_params['inf'];
	}

	/**
	 * Set risk assessment level
	 *
	 * @param int $level Risk assessment level (0-2)
	 *
	 * @return self|WP_Error
	 */
	public function set_risk( int $level ) {
		if ( $level < 0 || $level > 2 ) {
			return new WP_Error(
				'invalid_risk_level',
				__( 'Risk level must be between 0 and 2', 'arraypress' )
			);
		}
		$this->current_params['risk'] = $level;

		return $this;
	}

	/**
	 * Get risk assessment level
	 *
	 * @return int
	 */
	public function get_risk(): int {
		return (int) $this->current_params['risk'];
	}

	/**
	 * Set port checking
	 *
	 * @param bool $enable Whether to enable port checking
	 *
	 * @return self
	 */
	public function set_port( bool $enable ): self {
		$this->current_params['port'] = $enable ? 1 : 0;

		return $this;
	}

	/**
	 * Get port checking setting
	 *
	 * @return bool
	 */
	public function get_port(): bool {
		return (bool) $this->current_params['port'];
	}

	/**
	 * Set time seen data collection
	 *
	 * @param bool $enable Whether to enable time seen data
	 *
	 * @return self
	 */
	public function set_seen( bool $enable ): self {
		$this->current_params['seen'] = $enable ? 1 : 0;

		return $this;
	}

	/**
	 * Get time seen data setting
	 *
	 * @return bool
	 */
	public function get_seen(): bool {
		return (bool) $this->current_params['seen'];
	}

	/**
	 * Set history period
	 *
	 * @param int $days Number of days for historical data
	 *
	 * @return self|WP_Error
	 */
	public function set_days( int $days ) {
		if ( $days < 1 ) {
			return new WP_Error(
				'invalid_days',
				__( 'Days must be greater than 0', 'arraypress' )
			);
		}
		$this->current_params['days'] = $days;

		return $this;
	}

	/**
	 * Get history period setting
	 *
	 * @return int
	 */
	public function get_days(): int {
		return (int) $this->current_params['days'];
	}

	/**
	 * Set query tagging
	 *
	 * @param bool $enable Whether to enable query tagging
	 *
	 * @return self
	 */
	public function set_query_tagging( bool $enable ): self {
		$this->current_params['query_tagging'] = $enable;

		if ( $enable && empty( $this->current_params['tag'] ) ) {
			$this->current_params['tag'] = sanitize_text_field( get_bloginfo( 'name' ) );
		}

		return $this;
	}

	/**
	 * Get query tagging setting
	 *
	 * @return bool
	 */
	public function get_query_tagging(): bool {
		return (bool) ( $this->current_params['query_tagging'] ?? false );
	}

	/**
	 * Set request tag
	 *
	 * @param string $tag Tag to identify the request
	 *
	 * @return self
	 */
	public function set_tag( string $tag ): self {
		$this->current_params['tag'] = $tag;

		return $this;
	}

	/**
	 * Get request tag value
	 *
	 * @return string
	 */
	public function get_tag(): string {
		return $this->current_params['tag'] ?? '';
	}

	/** Country Methods *********************************************************/

	/**
	 * Set countries to block
	 *
	 * @param array $countries Array of country names or ISO codes
	 *
	 * @return self
	 */
	public function set_blocked_countries( array $countries ): self {
		$this->blocked_countries = array_map( 'strtoupper', $countries );

		if ( ! empty( $countries ) ) {
			$this->set_asn( true );
		}

		return $this;
	}

	/**
	 * Get blocked countries
	 *
	 * @return array
	 */
	public function get_blocked_countries(): array {
		return $this->blocked_countries;
	}

	/**
	 * Check if country is blocked
	 *
	 * @param string $country Country code or name to check
	 *
	 * @return bool
	 */
	public function is_country_blocked( string $country ): bool {
		return in_array( strtoupper( $country ), $this->blocked_countries );
	}

	/**
	 * Set countries to allow
	 *
	 * @param array $countries Array of country names or ISO codes
	 *
	 * @return self
	 */
	public function set_allowed_countries( array $countries ): self {
		$this->allowed_countries = array_map( 'strtoupper', $countries );

		return $this;
	}

	/**
	 * Get allowed countries
	 *
	 * @return array
	 */
	public function get_allowed_countries(): array {
		return $this->allowed_countries;
	}

	/**
	 * Check if country is allowed
	 *
	 * @param string $country Country code or name to check
	 *
	 * @return bool
	 */
	public function is_country_allowed( string $country ): bool {
		return in_array( strtoupper( $country ), $this->allowed_countries );
	}

}