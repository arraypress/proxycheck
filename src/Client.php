<?php
/**
 * ProxyCheck.io Client Class
 *
 * A comprehensive utility class for interacting with the ProxyCheck.io API service.
 * Provides methods for checking IPs and email addresses against proxy/VPN usage,
 * as well as managing dashboard functionality and bulk operations.
 *
 * Features include:
 * - IP proxy/VPN detection
 * - Email disposable address detection
 * - Dashboard statistics and analytics
 * - List management (whitelist/blacklist)
 * - CORS origins configuration
 * - Query tagging and tracking
 * - Country blocking rules
 * - Caching support
 *
 * @package     ArrayPress\ProxyCheck
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\ProxyCheck;

use ArrayPress\ProxyCheck\Response\Client\IP;
use ArrayPress\ProxyCheck\Response\Client\DisposableEmail;
use ArrayPress\ProxyCheck\Traits\Dashboard;
use Exception;
use WP_Error;

class Client {
	use Dashboard;

	/**
	 * API key for ProxyCheck.io service
	 * Required for premium features and higher rate limits
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Base URL for the ProxyCheck API endpoints
	 * All API requests are made to this base URL
	 *
	 * @var string
	 */
	private const API_BASE = 'https://proxycheck.io/v2/';

	/**
	 * Default query parameters and their values for API requests
	 * These parameters control the behavior and response content of API calls
	 *
	 * @var array
	 */
	private const DEFAULT_PARAMS = [
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
	 * Maximum number of IPs per batch request for free users
	 * This limit is enforced by the ProxyCheck.io API for non-paying users
	 *
	 * @var int
	 */
	private const BATCH_MAX_SIZE = 100;

	/**
	 * Maximum number of IPs per batch request for registered users
	 * This limit is enforced by the ProxyCheck.io API for paying customers
	 *
	 * @var int
	 */
	private const BATCH_MAX_SIZE_REGISTERED = 1000;

	/**
	 * Whether to enable response caching
	 * When enabled, responses are cached to reduce API calls
	 *
	 * @var bool
	 */
	private bool $enable_cache;

	/**
	 * Cache expiration time in seconds
	 * Determines how long cached responses remain valid
	 *
	 * @var int
	 */
	private int $cache_expiration;

	/**
	 * Prefix for cache keys in the WordPress options table
	 * Used to identify and manage cached responses
	 *
	 * @var string
	 */
	private string $cache_prefix;

	/**
	 * Current parameters array to store active settings
	 *
	 * @var array
	 */
	private array $current_params;

	/**
	 * Array of country codes/names to block
	 * If IP is from these countries, block will be set to 'yes'
	 *
	 * @var array
	 */
	private array $blocked_countries = [];

	/**
	 * Array of country codes/names to allow
	 * These countries bypass proxy/VPN blocking
	 *
	 * @var array
	 */
	private array $allowed_countries = [];

	/**
	 * Initialize the ProxyCheck client
	 *
	 * Creates a new instance of the ProxyCheck client with specified configuration.
	 * The client can be initialized with or without an API key, and with optional
	 * cache settings.
	 *
	 * @param string $api_key          The API key for ProxyCheck.io service
	 * @param bool   $enable_cache     Whether to enable response caching
	 * @param int    $cache_expiration Cache expiration time in seconds
	 * @param string $cache_prefix     Prefix for cache keys
	 */
	public function __construct(
		string $api_key = '',
		bool $enable_cache = true,
		int $cache_expiration = 600,
		string $cache_prefix = 'proxycheck_'
	) {
		$this->api_key          = $api_key;
		$this->enable_cache     = $enable_cache;
		$this->cache_expiration = $cache_expiration;
		$this->cache_prefix     = $cache_prefix;
		$this->current_params   = self::DEFAULT_PARAMS;
	}

	/** Initialization & Configuration Methods *******************************************************************/

	/**
	 * Set the API key
	 *
	 * Updates the API key used for authentication with ProxyCheck.io.
	 *
	 * @param string $api_key The API key for ProxyCheck.io
	 *
	 * @return self
	 */
	public function set_api_key( string $api_key ): self {
		$this->api_key = $api_key;

		return $this;
	}

	/**
	 * Get the API key
	 *
	 * Returns the current API key used for authentication with ProxyCheck.io.
	 *
	 * @return string The current API key
	 */
	public function get_api_key(): string {
		return $this->api_key;
	}

	/**
	 * Enable or disable response caching
	 *
	 * Controls whether API responses should be cached to reduce API calls.
	 *
	 * @param bool $enable Whether to enable caching
	 *
	 * @return self
	 */
	public function set_cache_enabled( bool $enable ): self {
		$this->enable_cache = $enable;

		return $this;
	}

	/**
	 * Get the cache enabled status
	 *
	 * Returns whether response caching is currently enabled.
	 *
	 * @return bool True if caching is enabled, false otherwise
	 */
	public function get_cache_enabled(): bool {
		return $this->enable_cache;
	}

	/**
	 * Set cache expiration time
	 *
	 * Determines how long cached responses should remain valid.
	 *
	 * @param int $seconds Cache expiration time in seconds
	 *
	 * @return self|WP_Error Returns self on success, WP_Error on failure
	 */
	public function set_cache_expiration( int $seconds ) {
		if ( $seconds < 0 ) {
			return new WP_Error(
				'invalid_expiration',
				__( 'Cache expiration time cannot be negative', 'arraypress' )
			);
		}
		$this->cache_expiration = $seconds;

		return $this;
	}

	/**
	 * Get the cache expiration time
	 *
	 * Returns the current cache expiration time in seconds.
	 *
	 * @return int Cache expiration time in seconds
	 */
	public function get_cache_expiration(): int {
		return $this->cache_expiration;
	}

	/**
	 * Set cache key prefix
	 *
	 * Sets the prefix used for cache keys in the WordPress options table.
	 *
	 * @param string $prefix Cache key prefix
	 *
	 * @return self
	 */
	public function set_cache_prefix( string $prefix ): self {
		$this->cache_prefix = $prefix;

		return $this;
	}

	/** Parameter Configuration Methods *******************************************************************/

	/**
	 * Set VPN detection mode
	 *
	 * Controls the VPN detection mode for IP checks:
	 * - 0: No VPN check, only proxy check
	 * - 1: VPN and proxy check (proxy overrides VPN)
	 * - 2: Only VPN check, no proxy check
	 * - 3: Both VPN and proxy check (separate results)
	 *
	 * @param int $mode VPN detection mode (0-3)
	 *
	 * @return self|WP_Error Returns self on success, WP_Error on failure
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
	 * Returns the current VPN detection mode:
	 * - 0: No VPN check
	 * - 1: VPN and proxy check (proxy overrides)
	 * - 2: Only VPN check
	 * - 3: Both checks (separate results)
	 *
	 * @return int Current VPN detection mode
	 */
	public function get_vpn(): int {
		return (int) $this->current_params['vpn'];
	}

	/**
	 * Set ASN lookup
	 *
	 * Controls whether to include ASN data in results.
	 * When enabled, includes provider name, ASN, range,
	 * hostname, location data, and currency information.
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
	 * Returns whether ASN data inclusion is enabled. When enabled,
	 * provides provider details, network info, and location data.
	 *
	 * @return bool True if ASN lookups are enabled
	 */
	public function get_asn(): bool {
		return (bool) $this->current_params['asn'];
	}

	/**
	 * Set node information parameter
	 *
	 * Controls whether to include which node within the cluster
	 * answered your API call. Primarily used for diagnostics
	 * with support staff.
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
	 * Returns whether node information is included in responses.
	 * This shows which cluster node answered the API call.
	 *
	 * @return bool True if node information is enabled
	 */
	public function get_node(): bool {
		return (bool) $this->current_params['node'];
	}

	/**
	 * Set query time parameter
	 *
	 * Controls whether to display how long the query took
	 * to be answered by the API, excluding network overhead.
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
	 * Returns whether query time information is included
	 * in the API response. Shows query processing time
	 * excluding network overhead.
	 *
	 * @return bool True if query time is enabled
	 */
	public function get_time(): bool {
		return (bool) $this->current_params['time'];
	}

	/**
	 * Set basic proxy information parameter
	 *
	 * Controls whether to include basic proxy information
	 * in the API response.
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
	 * Returns whether basic proxy information is included
	 * in the API response.
	 *
	 * @return bool True if basic proxy information is enabled
	 */
	public function get_inf(): bool {
		return (bool) $this->current_params['inf'];
	}

	/**
	 * Set risk assessment level
	 *
	 * Sets the risk assessment mode:
	 * - 0: Disabled
	 * - 1: Basic risk score (0-100)
	 * - 2: Detailed risk score with attack history
	 *
	 * @param int $level Risk assessment level (0-2)
	 *
	 * @return self|WP_Error Returns self on success, WP_Error on failure
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
	 * Returns the current risk assessment level:
	 * - 0: Disabled
	 * - 1: Basic risk score
	 * - 2: Detailed risk score
	 *
	 * @return int Current risk assessment level
	 */
	public function get_risk(): int {
		return (int) $this->current_params['risk'];
	}

	/**
	 * Set port checking
	 *
	 * Controls whether to include the port number
	 * where proxy server was detected.
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
	 * Returns whether port checking is enabled. When enabled,
	 * includes the port number where proxy server was detected.
	 *
	 * @return bool True if port checking is enabled
	 */
	public function get_port(): bool {
		return (bool) $this->current_params['port'];
	}

	/**
	 * Set time seen data collection
	 *
	 * Controls whether to include the most recent time
	 * the IP was seen operating as a proxy server.
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
	 * Returns whether last seen data collection is enabled.
	 * When enabled, shows when IP was last seen as proxy.
	 *
	 * @return bool True if time seen data is enabled
	 */
	public function get_seen(): bool {
		return (bool) $this->current_params['seen'];
	}

	/**
	 * Set history period
	 *
	 * Sets the number of days to include in proxy results.
	 * Default is 7 days if not specified.
	 *
	 * @param int $days Number of days for historical data
	 *
	 * @return self|WP_Error Returns self on success, WP_Error on failure
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
	 * Returns the number of days included in proxy results.
	 * Default is 7 days if not previously set.
	 *
	 * @return int Number of days for historical data
	 */
	public function get_days(): int {
		return (int) $this->current_params['days'];
	}

	/**
	 * Set query tagging
	 *
	 * Controls whether to automatically tag queries with site information
	 * when no custom tag is set.
	 *
	 * @param bool $enable Whether to enable query tagging
	 *
	 * @return self
	 */
	public function set_query_tagging( bool $enable ): self {
		$this->current_params['query_tagging'] = $enable;

		// If enabled and no custom tag, use site name
		if ( $enable && empty( $this->current_params['tag'] ) ) {
			$this->current_params['tag'] = sanitize_text_field( get_bloginfo( 'name' ) );
		}

		return $this;
	}

	/**
	 * Get query tagging setting
	 *
	 * Returns whether automatic query tagging is enabled.
	 *
	 * @return bool True if query tagging is enabled
	 */
	public function get_query_tagging(): bool {
		return (bool) ( $this->current_params['query_tagging'] ?? false );
	}

	/**
	 * Set request tag
	 *
	 * Sets a custom tag to identify the request in the
	 * ProxyCheck.io dashboard. Can be sent via POST method.
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
	 * Get the request tag value
	 *
	 * Returns the current tag used for identifying requests
	 * in the ProxyCheck.io dashboard.
	 *
	 * @return string Current request tag
	 */
	public function get_tag(): string {
		return $this->current_params['tag'] ?? '';
	}

	/** Country Management Methods *******************************************************************/

	/**
	 * Set countries to block
	 * Accepts array of country names or ISO codes
	 *
	 * @param array $countries Array of country names or ISO codes
	 *
	 * @return self
	 */
	public function set_blocked_countries( array $countries ): self {
		$this->blocked_countries = array_map( 'strtoupper', $countries );

		// Auto-enable ASN data when using country blocking
		if ( ! empty( $countries ) ) {
			$this->set_asn( true );
		}

		return $this;
	}

	/**
	 * Get blocked countries
	 *
	 * Returns array of currently blocked country codes
	 *
	 * @return array Array of country codes/names that are blocked
	 */
	public function get_blocked_countries(): array {
		return $this->blocked_countries;
	}

	/**
	 * Check if country is blocked
	 *
	 * @param string $country Country code or name to check
	 *
	 * @return bool True if country is in blocked list
	 */
	public function is_country_blocked( string $country ): bool {
		return in_array( strtoupper( $country ), $this->blocked_countries );
	}

	/**
	 * Set countries to allow
	 * These countries bypass proxy/VPN blocking
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
	 * Returns array of countries that bypass proxy/VPN blocking
	 *
	 * @return array Array of country codes/names that are allowed
	 */
	public function get_allowed_countries(): array {
		return $this->allowed_countries;
	}

	/**
	 * Check if country is allowed
	 *
	 * @param string $country Country code or name to check
	 *
	 * @return bool True if country is in allowed list
	 */
	public function is_country_allowed( string $country ): bool {
		return in_array( strtoupper( $country ), $this->allowed_countries );
	}

	/** Request Handling Methods *******************************************************************/

	/**
	 * Process email address for privacy
	 * Masks email username if masking is enabled
	 *
	 * @param string $email Email address to process
	 * @param bool   $mask  Whether to mask the email
	 *
	 * @return string Processed email address
	 */
	private function process_email( string $email, bool $mask = false ): string {
		if ( $mask && strpos( $email, '@' ) !== false ) {
			return 'anonymous@' . explode( '@', $email )[1];
		}

		return $email;
	}

	/**
	 * Build query parameters from options
	 *
	 * Merges provided options with current parameters for API requests.
	 *
	 * @param array $options Additional options to override current params
	 *
	 * @return array
	 */
	private function build_query_params( array $options = [] ): array {
		return array_merge( $this->current_params, $options );
	}

	/**
	 * Make a GET request to the ProxyCheck API
	 *
	 * Sends a GET request to the specified API endpoint with parameters.
	 *
	 * @param string $endpoint API endpoint
	 * @param array  $params   Query parameters
	 * @param array  $args     Additional request arguments
	 *
	 * @return array|WP_Error Response array or WP_Error on failure
	 */
	private function make_get_request( string $endpoint, array $params = [], array $args = [] ) {
		if ( ! empty( $this->api_key ) ) {
			$params['key'] = $this->api_key;
		}

		$url = self::API_BASE . $endpoint;
		if ( ! empty( $params ) ) {
			$url .= '?' . http_build_query( $params );
		}

		$default_args = [
			'timeout' => 15,
			'headers' => [
				'Accept' => 'application/json',
			],
		];

		$args     = wp_parse_args( $args, $default_args );
		$response = wp_remote_get( $url, $args );

		return $this->handle_response( $response );
	}

	/**
	 * Make a POST request to the ProxyCheck API
	 *
	 * Sends a POST request to the specified API endpoint with parameters and data.
	 *
	 * @param string $endpoint API endpoint
	 * @param array  $params   Query parameters
	 * @param array  $data     POST data
	 * @param array  $args     Additional request arguments
	 *
	 * @return array|WP_Error Response array or WP_Error on failure
	 */
	private function make_post_request( string $endpoint, array $params = [], array $data = [], array $args = [] ) {
		if ( ! empty( $this->api_key ) ) {
			$params['key'] = $this->api_key;
		}

		$url = self::API_BASE . $endpoint;
		if ( ! empty( $params ) ) {
			$url .= '?' . http_build_query( $params );
		}

		$default_args = [
			'timeout' => 15,
			'method'  => 'POST',
			'body'    => $data,
			'headers' => [
				'Accept' => 'application/json',
			],
		];

		$args     = wp_parse_args( $args, $default_args );
		$response = wp_remote_post( $url, $args );

		return $this->handle_response( $response );
	}

	/**
	 * Handle API response
	 *
	 * Processes and validates the API response, handling errors appropriately.
	 *
	 * @param array|WP_Error $response API response
	 *
	 * @return array|WP_Error Processed response or WP_Error
	 */
	private function handle_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'api_error',
				sprintf(
					__( 'ProxyCheck API request failed: %s', 'arraypress' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code < 200 || $status_code >= 300 ) {
			return new WP_Error(
				'http_error',
				sprintf(
					__( 'HTTP request failed with status code: %d', 'arraypress' ),
					$status_code
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error(
				'json_error',
				__( 'Failed to parse ProxyCheck API response', 'arraypress' )
			);
		}

		if ( isset( $data['status'] ) && $data['status'] === 'error' ) {
			return new WP_Error(
				'api_error',
				$data['message'] ?? __( 'Unknown API error', 'arraypress' )
			);
		}

		return $data;
	}

	/** Core API Methods *******************************************************************/

	/**
	 * Check a single IP address
	 *
	 * @param string $ip      IP address to check
	 * @param array  $options Additional options for the check
	 *
	 * @return IP|WP_Error Response object or WP_Error on failure
	 */
	public function check_ip( string $ip, array $options = [] ) {
		if ( ! $this->is_valid_ip( $ip ) ) {
			return new WP_Error(
				'invalid_ip',
				sprintf( __( 'Invalid IP address: %s', 'arraypress' ), $ip )
			);
		}

		$cache_key = $this->get_cache_key( $ip, $options );

		if ( $this->enable_cache ) {
			$cached_data = get_transient( $cache_key );
			if ( false !== $cached_data ) {
				return new IP( $cached_data );
			}
		}

		$params   = $this->build_query_params( $options );
		$response = $this->make_get_request( $ip, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Add block status and apply country rules
		$response = $this->add_block_status( $response, $ip );

		if ( $this->enable_cache ) {
			set_transient( $cache_key, $response, $this->cache_expiration );
		}

		return new IP( $response );
	}

	/**
	 * Check multiple IP addresses in a batch
	 *
	 * @param array $ips     Array of IP addresses to check
	 * @param array $options Additional options for the check
	 *
	 * @return array|WP_Error Array of Response objects or WP_Error on failure
	 */
	public function check_ips( array $ips, array $options = [] ) {
		$valid_ips = array_filter( $ips, [ $this, 'is_valid_ip' ] );

		if ( empty( $valid_ips ) ) {
			return new WP_Error(
				'invalid_ips',
				__( 'No valid IPs provided for check', 'arraypress' )
			);
		}

		$batch_size = empty( $this->api_key ) ? self::BATCH_MAX_SIZE : self::BATCH_MAX_SIZE_REGISTERED;
		$batches    = array_chunk( $valid_ips, $batch_size );
		$results    = [];

		foreach ( $batches as $batch ) {
			$params = $this->build_query_params( $options );
			$data   = [ 'ips' => implode( ',', $batch ) ];

			$response = $this->make_post_request( '', $params, $data );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			foreach ( $batch as $ip ) {
				if ( isset( $response[ $ip ] ) ) {
					try {
						$single_response = [
							'status'     => $response['status'] ?? 'ok',
							'node'       => $response['node'] ?? null,
							'query time' => $response['query time'] ?? null,
							$ip          => $response[ $ip ]
						];
						// Add block status for each IP
						$single_response = $this->add_block_status( $single_response, $ip );
						$results[ $ip ]  = new IP( $single_response );
					} catch ( Exception $e ) {
						return new WP_Error(
							'response_error',
							sprintf( __( 'Error processing response for IP %s: %s', 'arraypress' ),
								$ip,
								$e->getMessage() )
						);
					}
				}
			}
		}

		return $results;
	}

	/**
	 * Add block status to response
	 * Determines if IP should be blocked based on proxy/VPN status,
	 * operator details, and risk assessment
	 *
	 * @param array  $response API response
	 * @param string $address  IP or email being checked
	 *
	 * @return array Modified response
	 */
	private function add_block_status( array $response, string $address ): array {
		// Handle email disposable check
		if ( strpos( $address, '@' ) !== false && isset( $response[ $address ]['disposable'] ) ) {
			$response['block']        = $response[ $address ]['disposable'] === 'yes' ? 'yes' : 'no';
			$response['block_reason'] = $response[ $address ]['disposable'] === 'yes' ? 'disposable' : 'na';

			return $response;
		}

		// Initialize block status
		$response['block']        = 'no';
		$response['block_reason'] = 'na';

		$ip_data = $response[ $address ];

		// Check for proxy/VPN status with operator details
		if ( isset( $ip_data['proxy'] ) && $ip_data['proxy'] === 'yes' ) {
			$response['block']        = 'yes';
			$response['block_reason'] = isset( $ip_data['type'] ) &&
			                            $ip_data['type'] === 'VPN' ? 'vpn' : 'proxy';

			// Add operator details if available
			if ( isset( $ip_data['operator'] ) ) {
				$response['block_details'] = [
					'name'       => $ip_data['operator']['name'] ?? '',
					'anonymity'  => $ip_data['operator']['anonymity'] ?? '',
					'popularity' => $ip_data['operator']['popularity'] ?? ''
				];
			}
		}

		// Check risk score
		if ( isset( $ip_data['risk'] ) && $ip_data['risk'] > 70 ) {
			$response['block'] = 'yes';
			// Only update block reason if not already blocked for proxy/VPN
			if ( $response['block_reason'] === 'na' ) {
				$response['block_reason'] = 'high_risk';
			}
		}

		// Apply country rules
		return $this->apply_country_rules( $response, $address );
	}

	/**
	 * Apply country blocking rules to response
	 * Adds block and block_reason fields based on country rules
	 *
	 * @param array  $response API response
	 * @param string $address  IP address being checked
	 *
	 * @return array Modified response
	 */
	private function apply_country_rules( array $response, string $address ): array {
		// Skip if no country info available
		if ( ! isset( $response[ $address ]['country'] ) ) {
			$response['block']        = 'na';
			$response['block_reason'] = 'na';

			return $response;
		}

		// Get country info
		$country = strtoupper( $response[ $address ]['country'] );
		$isocode = strtoupper( $response[ $address ]['isocode'] ?? '' );

		// First check if country is blocked
		if ( $response['block'] === 'no' && ! empty( $this->blocked_countries ) ) {
			if ( in_array( $country, $this->blocked_countries ) ||
			     in_array( $isocode, $this->blocked_countries ) ) {
				$response['block']        = 'yes';
				$response['block_reason'] = 'country';
			}
		}

		// Then check if country is explicitly allowed
		if ( $response['block'] === 'yes' && ! empty( $this->allowed_countries ) ) {
			if ( in_array( $country, $this->allowed_countries ) ||
			     in_array( $isocode, $this->allowed_countries ) ) {
				$response['block']        = 'no';
				$response['block_reason'] = 'na';
			}
		}

		return $response;
	}

	/**
	 * Check if an email is disposable
	 *
	 * @param string $email Email address to check
	 * @param bool   $mask  Whether to mask the email address
	 *
	 * @return DisposableEmail|WP_Error Response object or WP_Error on failure
	 */
	public function check_email( string $email, bool $mask = false ) {
		if ( ! is_email( $email ) ) {
			return new WP_Error(
				'invalid_email',
				sprintf( __( 'Invalid email address: %s', 'arraypress' ), $email )
			);
		}

		// Process email masking
		$processed_email = $this->process_email( $email, $mask );
		$cache_key       = $this->get_cache_key( $processed_email );

		if ( $this->enable_cache ) {
			$cached_data = get_transient( $cache_key );
			if ( false !== $cached_data ) {
				return new DisposableEmail( $cached_data );
			}
		}

		$response = $this->make_get_request( $processed_email );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Add block status for email check
		$response = $this->add_block_status( $response, $processed_email );

		if ( $this->enable_cache ) {
			set_transient( $cache_key, $response, $this->cache_expiration );
		}

		return new DisposableEmail( $response );
	}

	/** Cache Utility Methods *******************************************************************/

	/**
	 * Generate cache key
	 *
	 * Generates a unique cache key for storing API responses.
	 *
	 * @param string $value  Value to generate key for
	 * @param array  $params Additional parameters to include in key
	 *
	 * @return string Generated cache key
	 */
	private function get_cache_key( string $value, array $params = [] ): string {
		$key_parts = [ $value ];

		if ( ! empty( $params ) ) {
			$key_parts[] = md5( serialize( $params ) );
		}

		return $this->cache_prefix . md5( implode( '_', $key_parts ) );
	}

	/**
	 * Clear cached data
	 *
	 * Removes cached API responses from the WordPress options table.
	 * If a specific value is provided, only clears that cache entry.
	 * Otherwise, clears all cached responses.
	 *
	 * @param string|null $value Optional specific value to clear cache for
	 *
	 * @return bool True on success, false on failure
	 */
	public function clear_cache( ?string $value = null ): bool {
		if ( $value !== null ) {
			return delete_transient( $this->get_cache_key( $value ) );
		}

		global $wpdb;
		$pattern = $wpdb->esc_like( '_transient_' . $this->cache_prefix ) . '%';

		return $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					$pattern
				)
			) !== false;
	}

	/**
	 * Validate an IP address
	 *
	 * Checks if a string is a valid IPv4 or IPv6 address.
	 *
	 * @param string $ip IP address to validate
	 *
	 * @return bool True if valid, false otherwise
	 */
	private function is_valid_ip( string $ip ): bool {
		return filter_var( $ip, FILTER_VALIDATE_IP ) !== false;
	}

}