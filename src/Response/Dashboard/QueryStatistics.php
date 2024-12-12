<?php
/**
 * Class QueryStatistics
 *
 * Handles query statistics response from ProxyCheck.io
 *
 * @package     ArrayPress/ProxyCheck
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\ProxyCheck\Response\Dashboard;

class QueryStatistics extends Base {

	/**
	 * Get today's statistics
	 *
	 * @return array|null
	 */
	public function get_today_stats(): ?array {
		if ( ! isset( $this->data['TODAY'] ) ) {
			return null;
		}

		return [
			'proxies'           => $this->get_nested_int( 'TODAY', 'proxies' ),
			'vpns'              => $this->get_nested_int( 'TODAY', 'vpns' ),
			'undetected'        => $this->get_nested_int( 'TODAY', 'undetected' ),
			'refused_queries'   => $this->get_nested_int( 'TODAY', 'refused queries' ),
			'disposable_emails' => $this->get_nested_int( 'TODAY', 'disposable emails' ),
			'reusable_emails'   => $this->get_nested_int( 'TODAY', 'reusable emails' ),
			'custom_rules'      => $this->get_nested_int( 'TODAY', 'custom rules' ),
			'blacklisted'       => $this->get_nested_int( 'TODAY', 'blacklisted' ),
			'total_queries'     => $this->get_nested_int( 'TODAY', 'total queries' )
		];
	}

	/**
	 * Format the statistics data
	 *
	 * @param int $days Number of days to include
	 *
	 * @return array Formatted statistics
	 */
	public function format( int $days = 30 ): array {
		$queries   = array_slice( $this->data, 0, $days, true );
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
			}

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
			'today'       => $this->get_today_stats(),
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

}