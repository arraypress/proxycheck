<?php
/**
 * ProxyCheck.io Dashboard Trait
 *
 * @package     ArrayPress/ProxyCheck
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\ProxyCheck\Traits;

use ArrayPress\ProxyCheck\Response\Dashboard\DetectionEntry;
use ArrayPress\ProxyCheck\Response\Dashboard\ListEntries;
use ArrayPress\ProxyCheck\Response\Dashboard\QueryStatistics;
use ArrayPress\ProxyCheck\Response\Dashboard\TagEntry;
use ArrayPress\ProxyCheck\Response\Dashboard\UsageStatistics;
use WP_Error;

/**
 * Trait Dashboard
 *
 * Handles ProxyCheck.io Dashboard API functionality
 */
trait Dashboard {

	/**
	 * Base URL for the Dashboard API
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
	 * @return DetectionEntry|WP_Error Response object or WP_Error on failure
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

		if ( ! is_wp_error( $response ) ) {
			$response = new DetectionEntry( $response );
			if ( $this->enable_cache ) {
				set_transient( $cache_key, $response, $this->cache_expiration );
			}
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

		return $detections->format();
	}

	/** Export Tags ****************************************************/

	/**
	 * Export tags data with comprehensive response handling
	 *
	 * @param array $options     Options for the export (limit, offset, addresses, days, start, end)
	 * @param bool  $force_check Force bypass cache if true (default: false)
	 *
	 * @return TagEntry|WP_Error Response object or WP_Error on failure
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

		if ( ! is_wp_error( $response ) ) {
			$response = new TagEntry( $response );
			if ( $this->enable_cache ) {
				set_transient( $cache_key, $response, $this->cache_expiration );
			}
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

		return $tags->format();
	}

	/** Export Queries *******************************************************************/

	/**
	 * Export query statistics for the past 30 days
	 *
	 * @param bool $force_check Force bypass cache if true (default: false)
	 *
	 * @return QueryStatistics|WP_Error Response object or WP_Error on failure
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

		if ( ! is_wp_error( $response ) ) {
			$response = new QueryStatistics( $response );
			if ( $this->enable_cache ) {
				set_transient( $cache_key, $response, $this->cache_expiration );
			}
		}

		return $response;
	}

	/**
	 * Get formatted query statistics
	 *
	 * @param int  $days        Number of days to retrieve (default: 30, max: 30)
	 * @param bool $force_check Force bypass cache if true (default: false)
	 *
	 * @return array Formatted query statistics
	 */
	public function get_formatted_queries( int $days = 30, bool $force_check = false ): array {
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

		return $queries->format( $days );
	}

	/** Usage/Tokens *******************************************************************/

	/**
	 * Get token usage information
	 *
	 * @param bool $force_check Force bypass cache if true (default: false)
	 *
	 * @return UsageStatistics|WP_Error Usage statistics or WP_Error on failure
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

		if ( ! is_wp_error( $response ) ) {
			$response = new UsageStatistics( $response );
			if ( $this->enable_cache ) {
				set_transient( $cache_key, $response, $this->cache_expiration );
			}
		}

		return $response;
	}

	/**
	 * Get formatted token usage information
	 *
	 * @param bool $force_check Force bypass cache if true (default: false)
	 *
	 * @return array{
	 *     used: int,
	 *     limit: int,
	 *     total: int,
	 *     plan: string,
	 *     burst_available: int,
	 *     burst_limit: int,
	 *     percentage: float,
	 *     remaining: int
	 * } Formatted usage information
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

		return $usage->format();
	}

	/**
	 * Get the total number of used tokens today
	 *
	 * @param bool $force_check Force bypass cache if true (default: false)
	 *
	 * @return int Number of used tokens
	 */
	public function get_used_tokens( bool $force_check = false ): int {
		$usage = $this->get_usage( $force_check );

		return is_wp_error( $usage ) ? 0 : $usage->get_used_tokens();
	}

	/**
	 * Get the daily token limit
	 *
	 * @param bool $force_check Force bypass cache if true (default: false)
	 *
	 * @return int Daily token limit
	 */
	public function get_token_limit( bool $force_check = false ): int {
		$usage = $this->get_usage( $force_check );

		return is_wp_error( $usage ) ? 0 : $usage->get_token_limit();
	}

	/**
	 * Get usage percentage
	 *
	 * @param bool $force_check Force bypass cache if true (default: false)
	 *
	 * @return float Percentage of token usage
	 */
	public function get_usage_percentage( bool $force_check = false ): float {
		$limit = $this->get_token_limit( $force_check );

		return $limit > 0 ? round( ( $this->get_used_tokens( $force_check ) / $limit ) * 100, 2 ) : 0.0;
	}

	/**
	 * Get remaining available tokens
	 *
	 * @param bool $force_check Force bypass cache if true (default: false)
	 *
	 * @return int Number of remaining tokens
	 */
	public function get_remaining_tokens( bool $force_check = false ): int {
		$usage = $this->get_usage( $force_check );

		return is_wp_error( $usage ) ? 0 : $usage->get_remaining_tokens();
	}

	/**
	 * Get number of available burst tokens
	 *
	 * @param bool $force_check Force bypass cache if true (default: false)
	 *
	 * @return int Number of available burst tokens
	 */
	public function get_burst_tokens( bool $force_check = false ): int {
		$usage = $this->get_usage( $force_check );

		return is_wp_error( $usage ) ? 0 : $usage->get_burst_tokens();
	}

	/**
	 * Get burst token limit
	 *
	 * @param bool $force_check Force bypass cache if true (default: false)
	 *
	 * @return int Number of burst tokens allowed
	 */
	public function get_burst_token_limit( bool $force_check = false ): int {
		$usage = $this->get_usage( $force_check );

		return is_wp_error( $usage ) ? 0 : $usage->get_burst_token_limit();
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

	/**
	 * Get formatted burst token usage string
	 *
	 * Returns a formatted string showing available and total burst tokens
	 * in the format "X / Y available"
	 *
	 * @return string Formatted burst token usage string
	 */
	public function get_formatted_burst_usage(): string {
		return sprintf(
		/* translators: %1$s: available burst tokens, %2$s: total burst tokens */
			esc_html__( '%1$s / %2$s available', 'arraypress' ),
			number_format( $this->get_burst_tokens() ),
			number_format( $this->get_burst_token_limit() )
		);
	}

	/**
	 * Get formatted token usage string
	 *
	 * Returns a formatted string showing used and total tokens
	 * in the format "X / Y queries (Z% used)"
	 *
	 * @return string Formatted token usage string
	 */
	public function get_formatted_token_usage(): string {
		return sprintf(
		/* translators: %1$s: used tokens, %2$s: total tokens, %3$s: usage percentage */
			esc_html__( '%1$s / %2$s queries (%3$s%% used)', 'arraypress' ),
			number_format( $this->get_used_tokens() ),
			number_format( $this->get_token_limit() ),
			number_format( $this->get_usage_percentage(), 1 )
		);
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
	 * Remove IP address from blacklist
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
	protected function manage_list( string $action, ?string $list = null, ?string $data = null ) {
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
	protected function format_list( $items ): string {
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

		$response = isset( $args['method'] ) && $args['method'] === 'POST'
			? wp_remote_post( $url, $args )
			: wp_remote_get( $url, $args );

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