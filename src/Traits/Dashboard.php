<?php
/**
 * Dashboard API Trait
 *
 * Provides comprehensive access to ProxyCheck.io's Dashboard API functionality including:
 * - Usage statistics and token management
 * - Detection exports and analytics
 * - Tag management and reporting
 * - Whitelist/Blocklist management
 * - CORS origins configuration
 *
 * Requires API key and Dashboard API Access to be enabled in proxycheck.io dashboard.
 * All API responses are cached by default to optimize performance and reduce API calls.
 *
 * @package     ArrayPress/ProxyCheck
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

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
	 * @param int  $limit       Number of entries to return (default: 100)
	 * @param int  $offset      Offset for pagination (default: 0)
	 * @param bool $force_check Force bypass cache if true (default: false)
	 *
	 * @return array|WP_Error Response array or WP_Error on failure
	 */
	public function export_detections( int $limit = 100, int $offset = 0, bool $force_check = false ) {
		$cache_key = $this->get_cache_key( 'detections', [ 'limit' => $limit, 'offset' => $offset ] );

		if ( $this->enable_cache && ! $force_check ) {
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
	 * @param int  $limit       Number of entries to return (default: 100)
	 * @param int  $offset      Offset for pagination (default: 0)
	 * @param bool $force_check Force bypass cache if true (default: false)
	 *
	 * @return array Array of formatted detection entries
	 */
	public function get_formatted_detections( int $limit = 100, int $offset = 0, bool $force_check = false ): array {
		$detections = $this->export_detections( $limit, $offset, $force_check );

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
	 * @param array $options     Options for the export
	 *                           - limit: int (default: 100)
	 *                           - offset: int (default: 0)
	 *                           - addresses: bool (default: false)
	 *                           - days: int|null
	 *                           - start: int|null (unix timestamp)
	 *                           - end: int|null (unix timestamp)
	 * @param bool  $force_check Force bypass cache if true (default: false)
	 *
	 * @return array|WP_Error Response array or WP_Error on failure
	 */
	public function export_tags( array $options = [], bool $force_check = false ) {
		$cache_key = $this->get_cache_key( 'tags', $options );

		if ( $this->enable_cache && ! $force_check ) {
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
	 * @param array $options     Options for the export
	 * @param bool  $force_check Force bypass cache if true (default: false)
	 *
	 * @return array Formatted tags data
	 */
	public function get_formatted_tags( array $options = [], bool $force_check = false ): array {
		$tags = $this->export_tags( $options, $force_check );

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

	/** Export Queries *******************************************************************/

	/**
	 * Export query statistics for the past 30 days
	 *
	 * Retrieves statistical data about API queries made in the last 30 days.
	 *
	 * @param bool $force_check Force bypass cache if true (default: false)
	 *
	 * @return array|WP_Error Response array or WP_Error on failure
	 */
	public function export_queries( bool $force_check = false ) {
		$cache_key = $this->get_cache_key( 'queries_30day' );

		if ( $this->enable_cache && ! $force_check ) {
			$cached_data = get_transient( $cache_key );
			if ( false !== $cached_data ) {
				return $cached_data;
			}
		}

		$params   = [ 'json' => 1 ];
		$response = $this->make_dashboard_request( 'export/queries/', $params );

		if ( ! is_wp_error( $response ) && $this->enable_cache ) {
			set_transient( $cache_key, $response, $this->cache_expiration );
		}

		return $response;
	}

	/**
	 * Get formatted query statistics
	 *
	 * Returns formatted statistical data about API queries for the specified period,
	 * including daily totals and detection types.
	 *
	 * @param int  $days        Number of days to retrieve (default: 30, max: 30)
	 * @param bool $force_check Force bypass cache if true (default: false)
	 *
	 * @return array Formatted query statistics
	 */
	public function get_formatted_queries( int $days = 30, bool $force_check = false ): array {
		// Ensure days is within valid range
		$days = min( max( $days, 1 ), 30 );

		$queries = $this->export_queries( $force_check );

		if ( is_wp_error( $queries ) ) {
			return [
				'period' => $days,
				'days'   => [],
				'totals' => [
					'proxies'           => 0,
					'vpns'              => 0,
					'undetected'        => 0,
					'disposable_emails' => 0,
					'reusable_emails'   => 0,
					'refused_queries'   => 0,
					'custom_rules'      => 0,
					'blacklisted'       => 0,
					'total_queries'     => 0
				],
				'today'  => null
			];
		}

		// Extract today's data if available
		$today_data = isset( $queries['TODAY'] ) ? [
			'proxies'           => (int) ( $queries['TODAY']['proxies'] ?? 0 ),
			'vpns'              => (int) ( $queries['TODAY']['vpns'] ?? 0 ),
			'undetected'        => (int) ( $queries['TODAY']['undetected'] ?? 0 ),
			'refused_queries'   => (int) ( $queries['TODAY']['refused queries'] ?? 0 ),
			'disposable_emails' => (int) ( $queries['TODAY']['disposable emails'] ?? 0 ),
			'reusable_emails'   => (int) ( $queries['TODAY']['reusable emails'] ?? 0 ),
			'custom_rules'      => (int) ( $queries['TODAY']['custom rules'] ?? 0 ),
			'blacklisted'       => (int) ( $queries['TODAY']['blacklisted'] ?? 0 ),
			'total_queries'     => (int) ( $queries['TODAY']['total queries'] ?? 0 )
		] : null;

		// Take only the requested number of days
		$queries = array_slice( $queries, 0, $days, true );

		$days_data = [];
		$totals    = [
			'proxies'           => 0,
			'vpns'              => 0,
			'undetected'        => 0,
			'disposable_emails' => 0,
			'reusable_emails'   => 0,
			'refused_queries'   => 0,
			'custom_rules'      => 0,
			'blacklisted'       => 0,
			'total_queries'     => 0
		];

		foreach ( $queries as $day => $stats ) {
			if ( $day === 'TODAY' ) {
				continue;
			} // Skip TODAY as it's handled separately

			$formatted_stats = [
				'day'               => $day,
				'proxies'           => (int) ( $stats['proxies'] ?? 0 ),
				'vpns'              => (int) ( $stats['vpns'] ?? 0 ),
				'undetected'        => (int) ( $stats['undetected'] ?? 0 ),
				'disposable_emails' => (int) ( $stats['disposable emails'] ?? 0 ),
				'reusable_emails'   => (int) ( $stats['reusable emails'] ?? 0 ),
				'refused_queries'   => (int) ( $stats['refused queries'] ?? 0 ),
				'custom_rules'      => (int) ( $stats['custom rules'] ?? 0 ),
				'blacklisted'       => (int) ( $stats['blacklisted'] ?? 0 ),
				'total_queries'     => (int) ( $stats['total queries'] ?? 0 )
			];

			$days_data[] = $formatted_stats;

			foreach ( $formatted_stats as $key => $value ) {
				if ( $key !== 'day' ) {
					$totals[ $key ] += $value;
				}
			}
		}

		$percentages = [];
		if ( $totals['total_queries'] > 0 ) {
			foreach ( $totals as $key => $value ) {
				if ( $key !== 'total_queries' ) {
					$percentages[ $key ] = round( ( $value / $totals['total_queries'] ) * 100, 2 );
				}
			}
		}

		return [
			'period'      => $days,
			'days'        => $days_data,
			'totals'      => $totals,
			'percentages' => $percentages,
			'today'       => $today_data,
			'summary'     => [
				'period_days'           => $days,
				'active_days'           => count( array_filter( $days_data, fn( $day ) => $day['total_queries'] > 0 ) ),
				'total_queries'         => $totals['total_queries'],
				'detected_threats'      => $totals['proxies'] + $totals['vpns'] + $totals['disposable_emails'],
				'detection_rate'        => $totals['total_queries'] > 0 ?
					round( ( ( $totals['proxies'] + $totals['vpns'] + $totals['disposable_emails'] ) /
					         $totals['total_queries'] ) * 100, 2 ) : 0,
				'average_daily_queries' => round( $totals['total_queries'] / $days, 2 )
			]
		];
	}

	/** Usage/Tokens *******************************************************************/

	/**
	 * Get token usage information from the API
	 *
	 * @param bool $force_check Force bypass cache if true (default: false)
	 *
	 * @return array|WP_Error Token usage info or WP_Error on failure
	 */
	public function get_usage( bool $force_check = false ) {
		$cache_key = $this->get_cache_key( 'usage' );

		if ( $this->enable_cache && ! $force_check ) {
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
	 * Get formatted token usage information
	 *
	 * @param bool $force_check Force bypass cache if true (default: false)
	 *
	 * @return array Formatted usage information
	 */
	public function get_formatted_usage( bool $force_check = false ): array {
		$usage = $this->get_usage( $force_check );

		if ( is_wp_error( $usage ) ) {
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

		$used  = (int) ( $usage['Queries Today'] ?? 0 );
		$limit = (int) ( $usage['Daily Limit'] ?? 0 );

		return [
			'used'            => $used,
			'limit'           => $limit,
			'total'           => (int) ( $usage['Queries Total'] ?? 0 ),
			'plan'            => $usage['Plan Tier'] ?? 'Unknown',
			'burst_available' => (int) ( $usage['Burst Tokens Available'] ?? 0 ),
			'burst_limit'     => (int) ( $usage['Burst Token Allowance'] ?? 0 ),
			'percentage'      => $limit > 0 ? round( ( $used / $limit ) * 100, 2 ) : 0.0,
			'remaining'       => max( 0, $limit - $used )
		];
	}

	/**
	 * Get the total number of used tokens today
	 *
	 * @param bool $force_check Force bypass cache if true (default: false)
	 *
	 * @return int Number of used tokens
	 */
	public function get_used_tokens( bool $force_check = false ): int {
		$tokens = $this->get_usage( $force_check );

		return is_wp_error( $tokens ) ? 0 : (int) ( $tokens['Queries Today'] ?? 0 );
	}

	/**
	 * Get the daily token limit
	 *
	 * @param bool $force_check Force bypass cache if true (default: false)
	 *
	 * @return int Daily token limit
	 */
	public function get_token_limit( bool $force_check = false ): int {
		$tokens = $this->get_usage( $force_check );

		return is_wp_error( $tokens ) ? 0 : (int) ( $tokens['Daily Limit'] ?? 0 );
	}

	/**
	 * Get remaining available tokens
	 *
	 * @param bool $force_check Force bypass cache if true (default: false)
	 *
	 * @return int Number of remaining tokens
	 */
	public function get_remaining_tokens( bool $force_check = false ): int {
		$tokens = $this->get_usage( $force_check );

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
	 * @param bool $force_check Force bypass cache if true (default: false)
	 *
	 * @return int Number of available burst tokens
	 */
	public function get_burst_tokens( bool $force_check = false ): int {
		$tokens = $this->get_usage( $force_check );

		return is_wp_error( $tokens ) ? 0 : (int) ( $tokens['Burst Tokens Available'] ?? 0 );
	}

	/**
	 * Check if token limit is exceeded
	 *
	 * @param bool $force_check Force bypass cache if true (default: false)
	 *
	 * @return bool True if limit exceeded
	 */
	public function is_token_limit_exceeded( bool $force_check = false ): bool {
		return $this->get_remaining_tokens( $force_check ) <= 0;
	}

	/** Blacklist Management *******************************************************/

	/**
	 * Get blacklist entries
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function get_blacklist() {
		return $this->manage_list( 'print', 'blacklist' );
	}

	/**
	 * Set blacklist entries (replaces existing entries)
	 *
	 * @param string|array $ips IPs to set as the blocklist
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function set_blacklist( $ips ) {
		$formatted_ips = $this->format_list( $ips );

		return $this->manage_list( 'set', 'blacklist', $formatted_ips );
	}

	/**
	 * Add IP address to blacklist
	 *
	 * @param string|array $ips Single IP or array of IPs to add
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function add_to_blacklist( $ips ) {
		$formatted_ips = $this->format_list( $ips );

		return $this->manage_list( 'add', 'blacklist', $formatted_ips );
	}

	/**
	 * Remove IP address from blocklist
	 *
	 * @param string|array $ips Single IP or array of IPs to remove
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function remove_from_blacklist( $ips ) {
		$formatted_ips = $this->format_list( $ips );

		return $this->manage_list( 'remove', 'blacklist', $formatted_ips );
	}

	/**
	 * Clear entire blacklist
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function clear_blacklist() {
		return $this->manage_list( 'clear', 'blacklist' );
	}

	/** Whitelist Management *******************************************************/

	/**
	 * Get whitelist entries
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function get_whitelist() {
		return $this->manage_list( 'print', 'whitelist' );
	}

	/**
	 * Set whitelist entries (replaces existing entries)
	 *
	 * @param string|array $ips IPs to set as the whitelist
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function set_whitelist( $ips ) {
		$formatted_ips = $this->format_list( $ips );

		return $this->manage_list( 'set', 'whitelist', $formatted_ips );
	}

	/**
	 * Add IP address to whitelist
	 *
	 * @param string|array $ips Single IP or array of IPs to add
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function add_to_whitelist( $ips ) {
		$formatted_ips = $this->format_list( $ips );

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
		$formatted_ips = $this->format_list( $ips );

		return $this->manage_list( 'remove', 'whitelist', $formatted_ips );
	}

	/**
	 * Clear entire whitelist
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function clear_whitelist() {
		return $this->manage_list( 'clear', 'whitelist' );
	}

	/** CORS Origins Management *******************************************************/

	/**
	 * Get CORS origins list
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function get_cors_origins() {
		return $this->manage_list( 'list', 'cors' );
	}

	/**
	 * Set CORS origins (replaces existing entries)
	 *
	 * @param string|array $origins Origins to set
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function set_cors_origins( $origins ) {
		$formatted_origins = $this->format_list( $origins );

		return $this->manage_list( 'set', 'cors', $formatted_origins );
	}

	/**
	 * Add CORS origins
	 *
	 * @param string|array $origins Origins to add
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function add_cors_origins( $origins ) {
		$formatted_origins = $this->format_list( $origins );

		return $this->manage_list( 'add', 'cors', $formatted_origins );
	}

	/**
	 * Remove CORS origins
	 *
	 * @param string|array $origins Origins to remove
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function remove_cors_origins( $origins ) {
		$formatted_origins = $this->format_list( $origins );

		return $this->manage_list( 'remove', 'cors', $formatted_origins );
	}

	/**
	 * Clear all CORS origins
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function clear_cors_origins() {
		return $this->manage_list( 'clear', 'cors' );
	}

	/** List Helpers *******************************************************************/

	/**
	 * Manage custom lists and CORS origins
	 *
	 * @param string      $action Action to perform (print|add|remove|set|clear|erase|forcedl)
	 * @param string|null $list   List name (whitelist|blacklist|cors) or null
	 * @param string|null $data   Data for add/remove/set actions (optional)
	 *
	 * @return ListEntries|WP_Error Response object or WP_Error on failure
	 */
	public function manage_list( string $action, ?string $list = null, ?string $data = null ) {
		$valid_actions = [ 'print', 'add', 'remove', 'set', 'clear', 'erase', 'forcedl' ];

		// Handle 'list' action for CORS (equivalent to 'print')
		if ( $list === 'cors' && $action === 'list' ) {
			$action = 'print';
		}

		if ( ! in_array( $action, $valid_actions ) ) {
			return new WP_Error(
				'invalid_action',
				sprintf( __( 'Invalid list action: %s', 'arraypress' ), $action )
			);
		}

		// Build endpoint based on list type
		if ( $list === 'cors' ) {
			$endpoint = 'cors/' . ( $action === 'print' ? 'list' : $action ) . '/';
		} else {
			$endpoint = $action === 'print' ?
				$list . '/list/' :
				$list . '/' . $action . '/';
		}

		$params = [ 'json' => 1 ];
		$args   = [];

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

		if ( isset( $response['status'] ) && $response['status'] === 'denied' ) {
			return new WP_Error(
				'api_access_denied',
				$response['message'] ?? __( 'API access denied. Please enable Dashboard API Access in your proxycheck.io dashboard.', 'arraypress' )
			);
		}

		return new ListEntries( $response );
	}

	/**
	 * Format list entries
	 *
	 * @param string|array $items Items to format
	 *
	 * @return string Formatted list
	 */
	private function format_list( $items ): string {
		if ( is_array( $items ) ) {
			$items = array_map( 'trim', $items );

			return implode( "\n", array_filter( $items ) );
		}

		return trim( $items );
	}

	/** Request Methods *******************************************************************/

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