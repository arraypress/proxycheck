<?php
/**
 * The ProxyCheck client.
 *
 * This runs on a checkout with a 15-second timeout, so what matters as much as
 * the verdict is that a dead endpoint is asked once rather than once per
 * visitor.
 *
 * @package ArrayPress\ProxyCheck
 */

declare( strict_types=1 );

namespace ArrayPress\ProxyCheck\Tests;

use ArrayPress\ProxyCheck\Client;
use ArrayPress\ProxyCheck\Response\Client\DisposableEmail;
use ArrayPress\ProxyCheck\Response\Client\IP;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Class ClientTest
 */
final class ClientTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		pc_reset();
	}

	protected function tearDown(): void {
		pc_reset();

		parent::tearDown();
	}

	public function test_a_lookup_returns_an_ip_response(): void {
		pc_will_return( pc_ok( '1.2.3.4', [ 'proxy' => 'yes' ] ) );

		$this->assertInstanceOf( IP::class, ( new Client( 'key' ) )->check_ip( '1.2.3.4' ) );
	}

	public function test_an_invalid_ip_is_refused_without_a_request(): void {
		$result = ( new Client( 'key' ) )->check_ip( 'not-an-ip' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_ip', $result->get_error_code() );
		$this->assertSame( 0, pc_request_count(), 'nothing should reach the network' );
	}

	public function test_an_invalid_email_is_refused_without_a_request(): void {
		$result = ( new Client( 'key' ) )->check_email( 'nope' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_email', $result->get_error_code() );
		$this->assertSame( 0, pc_request_count() );
	}

	public function test_a_repeat_lookup_is_served_from_cache(): void {
		pc_will_return( pc_ok( '1.2.3.4', [ 'proxy' => 'yes' ] ) );

		$client = new Client( 'key' );
		$client->check_ip( '1.2.3.4' );
		$client->check_ip( '1.2.3.4' );

		$this->assertSame( 1, pc_request_count() );
	}

	/** ---------------------------------------------------------------------
	 * Failure handling
	 * -------------------------------------------------------------------- */

	/**
	 * The regression this file exists for.
	 *
	 * Without a negative cache an outage costs every visitor the full
	 * 15-second timeout, one after another, on the checkout.
	 */
	public function test_a_failed_lookup_is_not_immediately_retried(): void {
		pc_will_return( new WP_Error( 'http_request_failed', 'Connection timed out' ) );

		$client = new Client( 'key' );
		$client->check_ip( '1.2.3.4' );
		$second = $client->check_ip( '1.2.3.4' );

		$this->assertSame( 1, pc_request_count(), 'the dead endpoint must not be hit twice' );
		$this->assertInstanceOf( WP_Error::class, $second );
		$this->assertSame( 'proxycheck_recent_failure', $second->get_error_code() );
	}

	/**
	 * An HTTP error is a failure too. ProxyCheck answers 429 when the daily
	 * allowance is gone, and retrying that per request burns the next day's as
	 * fast as it is granted.
	 */
	public function test_an_http_error_is_not_immediately_retried(): void {
		pc_will_return( [ 'code' => 429, 'body' => '{"status":"denied"}' ] );

		$client = new Client( 'key' );
		$client->check_ip( '1.2.3.4' );
		$client->check_ip( '1.2.3.4' );

		$this->assertSame( 1, pc_request_count() );
	}

	/**
	 * One bad lookup must not blind the caller to every other address.
	 */
	public function test_a_failure_is_remembered_per_lookup(): void {
		pc_will_return( new WP_Error( 'http_request_failed', 'Connection timed out' ) );
		$client = new Client( 'key' );
		$client->check_ip( '1.2.3.4' );

		pc_will_return( pc_ok( '5.6.7.8', [ 'proxy' => 'no' ] ) );

		$this->assertInstanceOf(
			IP::class,
			$client->check_ip( '5.6.7.8' ),
			'a different address must still be looked up'
		);
	}

	/**
	 * The IP and email endpoints are separate lookups and must not share a
	 * failure -- a dead IP check should not suppress email checks.
	 */
	public function test_an_ip_failure_does_not_suppress_an_email_check(): void {
		pc_will_return( new WP_Error( 'http_request_failed', 'Connection timed out' ) );
		$client = new Client( 'key' );
		$client->check_ip( '1.2.3.4' );

		pc_will_return( pc_ok( 'buyer@example.com', [ 'disposable' => 'no' ] ) );

		$this->assertInstanceOf( DisposableEmail::class, $client->check_email( 'buyer@example.com' ) );
	}

	public function test_an_email_lookup_also_remembers_its_failures(): void {
		pc_will_return( new WP_Error( 'http_request_failed', 'Connection timed out' ) );

		$client = new Client( 'key' );
		$client->check_email( 'buyer@example.com' );
		$client->check_email( 'buyer@example.com' );

		$this->assertSame( 1, pc_request_count() );
	}

	public function test_failure_caching_can_be_switched_off(): void {
		pc_will_return( new WP_Error( 'http_request_failed', 'Connection timed out' ) );

		$client = ( new Client( 'key' ) )->set_failure_ttl( 0 );
		$client->check_ip( '1.2.3.4' );
		$client->check_ip( '1.2.3.4' );

		$this->assertSame( 2, pc_request_count() );
	}

	/**
	 * With caching off there is nowhere to record a failure, so every lookup
	 * has to go out. The guard must not silently suppress them.
	 */
	public function test_failures_are_not_suppressed_when_caching_is_off(): void {
		pc_will_return( new WP_Error( 'http_request_failed', 'Connection timed out' ) );

		$client = new Client( 'key', false );
		$client->check_ip( '1.2.3.4' );
		$client->check_ip( '1.2.3.4' );

		$this->assertSame( 2, pc_request_count() );
	}

	public function test_the_failure_window_is_configurable(): void {
		$client = new Client( 'key' );

		$this->assertSame( 60, $client->get_failure_ttl(), 'a sensible default' );
		$this->assertSame( 30, $client->set_failure_ttl( 30 )->get_failure_ttl() );
		$this->assertSame( 0, $client->set_failure_ttl( -5 )->get_failure_ttl(), 'never negative' );
	}
}
