<?php

declare( strict_types=1 );

namespace ArrayPress\ProxyCheck\Traits;

use ArrayPress\ProxyCheck\Response\ListEntries;
use WP_Error;

/**
 * Trait Dashboard
 *
 * Handles ProxyCheck.io Dashboard API functionality
 */
trait Dashboard {

	/**
	 * Base URL for the Dashboard API
	 *
	 * @var string
	 */
	private string $dashboard_api_base = 'https://proxycheck.io/dashboard/';

	/** Export Detections ****************************************************/

	/**
	 * Export recent positive detections
	 *
	 * @param int $limit  Number of entries to return (default: 100)
	 * @param int $offset Offset for pagination (default: 0)
	 *
	 * @return array|WP_Error Response array or WP_Error on failure
	 */
	public function export_detections( int $limit = 100, int $offset = 0 ) {
		$cache_key = $this->get_cache_key( 'detections', [ 'limit' => $limit, 'offset' => $offset ] );

		if ( $this->enable_cache ) {
			$cached_data = get_transient( $cache_key );
			if ( false !== $cached_data ) {
				return $cached_data;
			}
		}

		$params = [
			'json'   => 1,
			'limit'  => $limit,
			'offset' => $offset
		];

		$response = $this->make_dashboard_request( 'export/detections/', $params );

		if ( ! is_wp_error( $response ) && $this->enable_cache ) {
			set_transient( $cache_key, $response, $this->cache_expiration );
		}

		return $response;
	}

	/**
	 * Get formatted detections
	 *
	 * @param int $limit  Number of entries to return (default: 100)
	 * @param int $offset Offset for pagination (default: 0)
	 *
	 * @return array Array of formatted detection entries
	 */
	public function get_formatted_detections( int $limit = 100, int $offset = 0 ): array {
		$detections = $this->export_detections( $limit, $offset );

		if ( is_wp_error( $detections ) ) {
			return [];
		}

		$formatted = [];
		foreach ( $detections as $key => $detection ) {
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

	/** Export Tags ****************************************************/

	/**
	 * Export tags data
	 *
	 * @param array $options Options for the export
	 *                       - limit: int (default: 100)
	 *                       - offset: int (default: 0)
	 *                       - addresses: bool (default: false)
	 *                       - days: int|null
	 *                       - start: int|null (unix timestamp)
	 *                       - end: int|null (unix timestamp)
	 *
	 * @return array|WP_Error Response array or WP_Error on failure
	 */
	public function export_tags( array $options = [] ) {
		$cache_key = $this->get_cache_key( 'tags', $options );

		if ( $this->enable_cache ) {
			$cached_data = get_transient( $cache_key );
			if ( false !== $cached_data ) {
				return $cached_data;
			}
		}

		$defaults = [
			'limit'     => 100,
			'offset'    => 0,
			'addresses' => false,
			'days'      => null,
			'start'     => null,
			'end'       => null
		];

		$options = wp_parse_args( $options, $defaults );
		$params  = array_filter( $options, function ( $value ) {
			return $value !== null;
		} );

		if ( isset( $params['addresses'] ) ) {
			$params['addresses'] = (int) $params['addresses'];
		}

		$response = $this->make_dashboard_request( 'export/tags/', $params );

		if ( ! is_wp_error( $response ) && $this->enable_cache ) {
			set_transient( $cache_key, $response, $this->cache_expiration );
		}

		return $response;
	}

	/**
	 * Get formatted tags data
	 *
	 * @param array $options Options for the export
	 *
	 * @return array Formatted tags data
	 */
	public function get_formatted_tags( array $options = [] ): array {
		$tags = $this->export_tags( $options );

		if ( is_wp_error( $tags ) ) {
			return [];
		}

		return array_map( function ( $data ) {
			return [
				'total'     => $data['types']['total'] ?? 0,
				'proxy'     => $data['types']['proxy'] ?? 0,
				'vpn'       => $data['types']['vpn'] ?? 0,
				'rule'      => $data['types']['rule'] ?? 0,
				'addresses' => $data['addresses'] ?? []
			];
		}, $tags );
	}

	/** Detections (History) ****************************************************/

	/**
	 * Get token usage information from the API
	 *
	 * @return array|WP_Error Token usage info or WP_Error on failure
	 */
	public function get_tokens() {
		$cache_key = $this->get_cache_key( 'tokens' );

		if ( $this->enable_cache ) {
			$cached_data = get_transient( $cache_key );
			if ( false !== $cached_data ) {
				return $cached_data;
			}
		}

		$response = $this->make_dashboard_request( 'export/usage/' );

		if ( ! is_wp_error( $response ) && $this->enable_cache ) {
			set_transient( $cache_key, $response, $this->cache_expiration );
		}

		return $response;
	}

	/**
	 * Get formatted token information
	 *
	 * @return array Formatted token information
	 */
	public function get_formatted_tokens(): array {
		$tokens = $this->get_tokens();

		if ( is_wp_error( $tokens ) ) {
			return [
				'used'            => 0,
				'limit'           => 0,
				'total'           => 0,
				'plan'            => 'Unknown',
				'burst_available' => 0,
				'burst_limit'     => 0,
				'percentage'      => 0.0,
				'remaining'       => 0
			];
		}

		$used  = (int) ( $tokens['Queries Today'] ?? 0 );
		$limit = (int) ( $tokens['Daily Limit'] ?? 0 );

		return [
			'used'            => $used,
			'limit'           => $limit,
			'total'           => (int) ( $tokens['Queries Total'] ?? 0 ),
			'plan'            => $tokens['Plan Tier'] ?? 'Unknown',
			'burst_available' => (int) ( $tokens['Burst Tokens Available'] ?? 0 ),
			'burst_limit'     => (int) ( $tokens['Burst Token Allowance'] ?? 0 ),
			'percentage'      => $limit > 0 ? round( ( $used / $limit ) * 100, 2 ) : 0.0,
			'remaining'       => max( 0, $limit - $used )
		];
	}

	/**
	 * Get the total number of used tokens today
	 *
	 * @return int Number of used tokens
	 */
	public function get_used_tokens(): int {
		$tokens = $this->get_tokens();

		return is_wp_error( $tokens ) ? 0 : (int) ( $tokens['Queries Today'] ?? 0 );
	}

	/**
	 * Get the daily token limit
	 *
	 * @return int Daily token limit
	 */
	public function get_token_limit(): int {
		$tokens = $this->get_tokens();

		return is_wp_error( $tokens ) ? 0 : (int) ( $tokens['Daily Limit'] ?? 0 );
	}

	/**
	 * Get remaining available tokens
	 *
	 * @return int Number of remaining tokens
	 */
	public function get_remaining_tokens(): int {
		$tokens = $this->get_tokens();

		if ( is_wp_error( $tokens ) ) {
			return 0;
		}

		$used  = (int) ( $tokens['Queries Today'] ?? 0 );
		$limit = (int) ( $tokens['Daily Limit'] ?? 0 );

		return max( 0, $limit - $used );
	}

	/**
	 * Get number of available burst tokens
	 *
	 * @return int Number of available burst tokens
	 */
	public function get_burst_tokens(): int {
		$tokens = $this->get_tokens();

		return is_wp_error( $tokens ) ? 0 : (int) ( $tokens['Burst Tokens Available'] ?? 0 );
	}

	/**
	 * Check if token limit is exceeded
	 *
	 * @return bool True if limit exceeded
	 */
	public function is_token_limit_exceeded(): bool {
		return $this->get_remaining_tokens() <= 0;
	}

	/**
	 * Export account usage statistics
	 *
	 * @return array|WP_Error Response array or WP_Error on failure
	 */
	public function export_usage() {
		return $this->make_dashboard_request( 'export/usage/' );
	}

	/**
	 * Export query statistics for the past 30 days
	 *
	 * @return array|WP_Error Response array or WP_Error on failure
	 */
	public function export_queries() {
		$params = [ 'json' => 1 ];

		return $this->make_dashboard_request( 'export/queries/', $params );
	}

	/**
	 * Manage custom lists
	 *
	 * @param string      $action Action to perform (print|add|remove|set|clear|erase|forcedl)
	 * @param string|null $list   List name (optional)
	 * @param string|null $data   Data for add/remove/set actions (optional)
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function manage_list( string $action, ?string $list = null, ?string $data = null ) {
		$valid_actions = [ 'print', 'add', 'remove', 'set', 'clear', 'erase', 'forcedl' ];

		if ( ! in_array( $action, $valid_actions ) ) {
			return new WP_Error(
				'invalid_action',
				sprintf( __( 'Invalid list action: %s', 'arraypress' ), $action )
			);
		}

		// Adjust endpoint to match their format
		$endpoint = $list . '/list/';

		if ( $action !== 'print' ) {
			$endpoint = $list . '/' . $action . '/';  // e.g., 'blacklist/add/'
		}

		$params = [ 'json' => 1 ];
		$args   = [];

		if ( $data !== null && in_array( $action, [ 'add', 'remove', 'set' ] ) ) {
			// Their API expects POST data in a specific format
			if ( $action === 'add' || $action === 'remove' ) {
				$args = [
					'method' => 'POST',
					'body'   => [
						'action' => $action,
						'data'   => $data
					]
				];
			} else {
				$args = [
					'method' => 'POST',
					'body'   => [ 'data' => $data ]
				];
			}
		}

		$response = $this->make_dashboard_request( $endpoint, $params, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( isset( $response['status'] ) && $response['status'] === 'denied' ) {
			return new WP_Error(
				'api_access_denied',
				$response['message'] ?? __( 'API access denied. Please enable Dashboard API Access in your proxycheck.io dashboard.', 'arraypress' )
			);
		}

		return new ListEntries( $response );
	}

	/**
	 * Manage CORS origins
	 *
	 * @param string      $action Action to perform (list|add|remove|set|clear)
	 * @param string|null $data   Data for add/remove/set actions (optional)
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function manage_cors( string $action, ?string $data = null ) {
		$valid_actions = [ 'list', 'add', 'remove', 'set', 'clear' ];

		if ( ! in_array( $action, $valid_actions ) ) {
			return new WP_Error(
				'invalid_action',
				sprintf( __( 'Invalid CORS action: %s', 'arraypress' ), $action )
			);
		}

		$endpoint = 'cors/' . $action . '/';
		$params   = [ 'json' => 1 ];
		$args     = [];

		if ( $data !== null && in_array( $action, [ 'add', 'remove', 'set' ] ) ) {
			$args = [
				'method' => 'POST',
				'body'   => [ 'data' => $data ]
			];
		}

		$response = $this->make_dashboard_request( $endpoint, $params, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return new ListEntries( $response );
	}

	/**
	 * Add IP address to whitelist
	 *
	 * @param string|array $ips Single IP or array of IPs to add
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function add_to_whitelist( $ips ) {
		$formatted_ips = $this->format_ip_list( $ips );

		return $this->manage_list( 'add', 'whitelist', $formatted_ips );
	}

	/**
	 * Remove IP address from whitelist
	 *
	 * @param string|array $ips Single IP or array of IPs to remove
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function remove_from_whitelist( $ips ) {
		$formatted_ips = $this->format_ip_list( $ips );

		return $this->manage_list( 'remove', 'whitelist', $formatted_ips );
	}

	/**
	 * Add IP address to blocklist
	 *
	 * @param string|array $ips Single IP or array of IPs to add
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function add_to_blocklist( $ips ) {
		$formatted_ips = $this->format_ip_list( $ips );

		return $this->manage_list( 'add', 'blacklist', $formatted_ips );
	}

	/**
	 * Remove IP address from blocklist
	 *
	 * @param string|array $ips Single IP or array of IPs to remove
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function remove_from_blocklist( $ips ) {
		$formatted_ips = $this->format_ip_list( $ips );

		return $this->manage_list( 'remove', 'blacklist', $formatted_ips );
	}

	/**
	 * Get whitelist entries
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function get_whitelist() {
		return $this->manage_list( 'print', 'whitelist' );
	}

	/**
	 * Get blocklist entries
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function get_blocklist() {
		return $this->manage_list( 'print', 'blacklist' );
	}


	/**
	 * Clear entire whitelist
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function clear_whitelist() {
		return $this->manage_list( 'clear', 'whitelist' );
	}

	/**
	 * Clear entire blocklist
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function clear_blocklist() {
		return $this->manage_list( 'clear', 'blacklist' );
	}

	/**
	 * Set whitelist entries (replaces existing entries)
	 *
	 * @param string|array $ips IPs to set as the whitelist
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function set_whitelist( $ips ) {
		$formatted_ips = $this->format_ip_list( $ips );

		return $this->manage_list( 'set', 'whitelist', $formatted_ips );
	}

	/**
	 * Set blocklist entries (replaces existing entries)
	 *
	 * @param string|array $ips IPs to set as the blocklist
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function set_blocklist( $ips ) {
		$formatted_ips = $this->format_ip_list( $ips );

		return $this->manage_list( 'set', 'blacklist', $formatted_ips );
	}

	/**
	 * Format IP addresses for list management
	 *
	 * @param string|array $ips Single IP or array of IPs
	 *
	 * @return string Formatted IP list
	 */
	private function format_ip_list( $ips ): string {
		if ( is_array( $ips ) ) {
			$ips = array_map( 'trim', $ips );

			return implode( "\n", array_filter( $ips ) );
		}

		return trim( $ips );
	}

	/**
	 * Make a request to the Dashboard API
	 *
	 * @param string $endpoint API endpoint
	 * @param array  $params   Query parameters
	 * @param array  $args     Additional request arguments
	 *
	 * @return array|WP_Error Response array or WP_Error on failure
	 */
	private function make_dashboard_request( string $endpoint, array $params = [], array $args = [] ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error(
				'missing_api_key',
				__( 'API key is required for dashboard API requests', 'arraypress' )
			);
		}

		$params['key'] = $this->api_key;

		$url = $this->dashboard_api_base . ltrim( $endpoint, '/' );
		if ( ! empty( $params ) ) {
			$url .= '?' . http_build_query( $params );
		}

		$default_args = [
			'timeout' => 15,
			'headers' => [
				'Accept' => 'application/json',
			],
		];

		$args = wp_parse_args( $args, $default_args );

		if ( isset( $args['method'] ) && $args['method'] === 'POST' ) {
			$response = wp_remote_post( $url, $args );
		} else {
			$response = wp_remote_get( $url, $args );
		}

		return $this->handle_response( $response );
	}

	/**
	 * Handle API response
	 *
	 * @param array|WP_Error $response API response
	 *
	 * @return array|WP_Error Processed response or WP_Error
	 */
	abstract protected function handle_response( $response );

}