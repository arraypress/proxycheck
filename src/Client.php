<?php
/**
 * Validate an IP address
 *
 * Checks if a string is a valid IPv4 or IPv6  ProxyCheck.io Client Class
 *
 * A comprehensive utility class for interacting with the ProxyCheck.io API service.
 * Provides methods for checking IPs and email addresses against proxy/VPN usage.
 *
 * @package     ArrayPress/ProxyCheck
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\ProxyCheck;

use ArrayPress\ProxyCheck\Response\IP;
use ArrayPress\ProxyCheck\Response\DisposableEmail;
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
		'vpn'  => 1,    // Enable VPN detection
		'asn'  => 1,    // Include ASN data
		'node' => 0,    // Don't include node information
		'time' => 0,    // Don't include query time
		'inf'  => 1,    // Include basic proxy information
		'risk' => 1,    // Include basic risk score
		'port' => 0,    // Don't check port
		'seen' => 0,    // Don't include last seen
		'days' => 7,    // Default history period
		'tag'  => '',   // No default tag
		'ver'  => 2,    // API version 2
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
	 * Set cache expiration time
	 *
	 * Determines how long cached responses should remain valid.
	 *
	 * @param int $seconds Cache expiration time in seconds
	 *
	 * @return self
	 * @throws \InvalidArgumentException If seconds is negative
	 */
	public function set_cache_expiration( int $seconds ): self {
		if ( $seconds < 0 ) {
			throw new \InvalidArgumentException( 'Cache expiration time cannot be negative' );
		}
		$this->cache_expiration = $seconds;

		return $this;
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
	 * Build query parameters from options
	 *
	 * Merges provided options with default parameters for API requests.
	 *
	 * @param array $options Additional options to override defaults
	 *
	 * @return array
	 */
	private function build_query_params( array $options = [] ): array {
		return array_merge( self::DEFAULT_PARAMS, $options );
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

	/**
	 * Check a single IP address
	 *
	 * Checks if an IP address is associated with a proxy or VPN.
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

		if ( $this->enable_cache ) {
			set_transient( $cache_key, $response, $this->cache_expiration );
		}

		return new IP( $response );
	}

	/**
	 * Check multiple IP addresses in a batch
	 *
	 * Checks multiple IP addresses for proxy/VPN usage in a single request.
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
	 * Check if an email is disposable
	 *
	 * Checks if an email address is from a disposable email service.
	 *
	 * @param string $email Email address to check
	 *
	 * @return DisposableEmail|WP_Error Response object or WP_Error on failure
	 */
	public function check_email( string $email ) {
		if ( ! $this->is_valid_email( $email ) ) {
			return new WP_Error(
				'invalid_email',
				sprintf( __( 'Invalid email address: %s', 'arraypress' ), $email )
			);
		}

		$cache_key = $this->get_cache_key( $email );

		if ( $this->enable_cache ) {
			$cached_data = get_transient( $cache_key );
			if ( false !== $cached_data ) {
				return new DisposableEmail( $cached_data );
			}
		}

		$response = $this->make_get_request( $email );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( $this->enable_cache ) {
			set_transient( $cache_key, $response, $this->cache_expiration );
		}

		return new DisposableEmail( $response );
	}

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

	/**
	 * Validate an email address
	 *
	 * Checks if a string is a valid email address.
	 *
	 * @param string $email Email address to validate
	 *
	 * @return bool True if valid, false otherwise
	 */
	private function is_valid_email( string $email ): bool {
		return filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;
	}

}