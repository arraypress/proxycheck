<?php
/**
 * ProxyCheck.io Client Class
 *
 * A comprehensive utility class for interacting with the ProxyCheck.io API service.
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
	 * API key for ProxyCheck.io
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Base URL for the ProxyCheck API
	 *
	 * @var string
	 */
	private const API_BASE = 'https://proxycheck.io/v2/';

	/**
	 * Maximum number of IPs per batch request for free users
	 *
	 * This constant represents the limit imposed by ProxyCheck.io
	 * on the number of IP addresses that can be checked in a single
	 * batch request for users on the free tier.
	 *
	 * @var int
	 */
	private const BATCH_MAX_SIZE = 100;

	/**
	 * Maximum number of IPs per batch request for registered users
	 *
	 * This constant represents the limit imposed by ProxyCheck.io
	 * on the number of IP addresses that can be checked in a single
	 * batch request for users with a registered paid account.
	 *
	 * @var int
	 */
	private const BATCH_MAX_SIZE_REGISTERED = 1000;

	/**
	 * Whether to enable response caching
	 *
	 * @var bool
	 */
	private bool $enable_cache;

	/**
	 * Cache expiration time in seconds
	 *
	 * @var int
	 */
	private int $cache_expiration;

	/**
	 * Initialize the ProxyCheck client
	 *
	 * @param string $api_key          API key for ProxyCheck.io
	 * @param bool   $enable_cache     Whether to enable caching (default: true)
	 * @param int    $cache_expiration Cache expiration in seconds (default: 600 for free users)
	 */
	public function __construct( string $api_key = '', bool $enable_cache = true, int $cache_expiration = 600 ) {
		$this->api_key          = $api_key;
		$this->enable_cache     = $enable_cache;
		$this->cache_expiration = $cache_expiration;
	}

	/**
	 * Make a GET request to the ProxyCheck API
	 *
	 * @param string $endpoint API endpoint
	 * @param array  $params   Query parameters
	 * @param array  $args     Additional request arguments
	 *
	 * @return array|WP_Error Response array or WP_Error on failure
	 */
	private function make_get_request( string $endpoint, array $params = [], array $args = [] ) {
		// Add API key if provided
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
	 * @param string $endpoint API endpoint
	 * @param array  $params   Query parameters
	 * @param array  $data     POST data
	 * @param array  $args     Additional request arguments
	 *
	 * @return array|WP_Error Response array or WP_Error on failure
	 */
	private function make_post_request( string $endpoint, array $params = [], array $data = [], array $args = [] ) {
		// Add API key if provided
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

		// Check for HTTP error status codes
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

		// Check for API-specific error status
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
	 * @param string $ip      IP address to check
	 * @param array  $options Additional options for the check
	 *
	 * @return IP|WP_Error IP Response object or WP_Error on failure
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

			// Create individual Response objects for each IP
			foreach ( $batch as $ip ) {
				if ( isset( $response[ $ip ] ) ) {
					try {
						// Create a properly structured single-IP response
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
							sprintf( __( 'Error processing response for IP %s: %s', 'arraypress' ), $ip, $e->getMessage() )
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
				return new  ( $cached_data );
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
	 * Build query parameters from options
	 *
	 * @param array $options Options for the API request
	 *
	 * @return array
	 */
	private function build_query_params( array $options ): array {
		$params = [];

		// Map options to query parameters
		$param_map = [
			'vpn'  => 'vpn',
			'asn'  => 'asn',
			'node' => 'node',
			'time' => 'time',
			'inf'  => 'inf',
			'risk' => 'risk',
			'port' => 'port',
			'seen' => 'seen',
			'days' => 'days',
			'tag'  => 'tag',
			'ver'  => 'ver',
		];

		foreach ( $param_map as $option => $param ) {
			if ( isset( $options[ $option ] ) ) {
				$params[ $param ] = $options[ $option ];
			}
		}

		return $params;
	}

	/**
	 * Validate an IP address
	 *
	 * @param string $ip IP address to validate
	 *
	 * @return bool
	 */
	private function is_valid_ip( string $ip ): bool {
		return filter_var( $ip, FILTER_VALIDATE_IP ) !== false;
	}

	/**
	 * Validate an email address
	 *
	 * @param string $email Email address to validate
	 *
	 * @return bool
	 */
	private function is_valid_email( string $email ): bool {
		return filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;
	}

	/**
	 * Generate cache key
	 *
	 * @param string $value  Value to generate key for
	 * @param array  $params Additional parameters to include in key
	 *
	 * @return string
	 */
	private function get_cache_key( string $value, array $params = [] ): string {
		$key_parts = [ $value ];

		if ( ! empty( $params ) ) {
			$key_parts[] = md5( serialize( $params ) );
		}

		return 'proxycheck_' . md5( implode( '_', $key_parts ) );
	}

	/**
	 * Clear cached data
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
		$pattern = $wpdb->esc_like( '_transient_proxycheck_' ) . '%';

		return $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					$pattern
				)
			) !== false;
	}

}