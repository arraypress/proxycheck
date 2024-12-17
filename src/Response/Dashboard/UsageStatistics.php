<?php
/**
 * Class UsageStatistics
 *
 * Handles usage statistics response from ProxyCheck.io
 *
 * @package     ArrayPress\ProxyCheck
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\ProxyCheck\Response\Dashboard;

class UsageStatistics extends Base {

	/**
	 * Get used tokens today
	 */
	public function get_used_tokens(): int {
		return $this->get_int( 'Queries Today' );
	}

	/**
	 * Get daily token limit
	 */
	public function get_token_limit(): int {
		return $this->get_int( 'Daily Limit' );
	}

	/**
	 * Get total queries
	 */
	public function get_total_queries(): int {
		return $this->get_int( 'Queries Total' );
	}

	/**
	 * Get plan tier
	 */
	public function get_plan_tier(): string {
		return $this->get_string( 'Plan Tier', 'Unknown' );
	}

	/**
	 * Get available burst tokens
	 */
	public function get_burst_tokens(): int {
		return $this->get_int( 'Burst Tokens Available' );
	}

	/**
	 * Get burst token limit
	 */
	public function get_burst_token_limit(): int {
		return $this->get_int( 'Burst Token Allowance' );
	}

	/**
	 * Get remaining tokens
	 */
	public function get_remaining_tokens(): int {
		return max( 0, $this->get_token_limit() - $this->get_used_tokens() );
	}

	/**
	 * Get usage percentage
	 */
	public function get_usage_percentage(): float {
		$limit = $this->get_token_limit();

		return $limit > 0 ? round( ( $this->get_used_tokens() / $limit ) * 100, 2 ) : 0.0;
	}

	/**
	 * Format the usage statistics
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
	 * }
	 */
	public function format(): array {
		return [
			'used'            => $this->get_used_tokens(),
			'limit'           => $this->get_token_limit(),
			'total'           => $this->get_total_queries(),
			'plan'            => $this->get_plan_tier(),
			'burst_available' => $this->get_burst_tokens(),
			'burst_limit'     => $this->get_burst_token_limit(),
			'percentage'      => $this->get_usage_percentage(),
			'remaining'       => $this->get_remaining_tokens()
		];
	}

}