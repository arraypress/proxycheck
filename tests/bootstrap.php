<?php
/**
 * Test bootstrap.
 *
 * The transport is the seam: a test says what the API returns, and the
 * assertions are about what the library does with it. WordPress itself is
 * stubbed rather than loaded.
 *
 * @package ArrayPress\ProxyCheck
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

/**
 * What the next HTTP call returns, what has been asked, and what is cached.
 *
 * @var array
 */
$GLOBALS['pc'] = [
	'response'   => null,
	'requests'   => [],
	'transients' => [],
];

/**
 * Reset everything a test set up.
 */
function pc_reset(): void {
	$GLOBALS['pc'] = [
		'response'   => null,
		'requests'   => [],
		'transients' => [],
	];
}

/**
 * Queue the response the next request receives.
 *
 * @param mixed $response A WP_Error, or an array with 'code' and 'body'.
 */
function pc_will_return( $response ): void {
	$GLOBALS['pc']['response'] = $response;
}

/**
 * A successful ProxyCheck body for one address.
 *
 * @param string $address The address queried.
 * @param array  $data    The per-address block.
 *
 * @return array
 */
function pc_ok( string $address, array $data = [] ): array {
	return [
		'code' => 200,
		'body' => (string) json_encode( [ 'status' => 'ok', $address => $data ] ),
	];
}

/**
 * How many requests have been made.
 *
 * @return int
 */
function pc_request_count(): int {
	return count( $GLOBALS['pc']['requests'] );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {

		public string $code;
		public string $message;
		public array $data;

		public function __construct( string $code = '', string $message = '', $data = [] ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = (array) $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		return $text;
	}
}

if ( ! function_exists( 'is_email' ) ) {
	function is_email( $email ) {
		return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( array $args, string $url ) {
		return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $args );
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = [] ) {
		return array_merge( $defaults, (array) $args );
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( string $url, array $args = [] ) {
		$GLOBALS['pc']['requests'][] = $url;

		return $GLOBALS['pc']['response'] ?? [ 'code' => 200, 'body' => '{"status":"ok"}' ];
	}
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( string $url, array $args = [] ) {
		$GLOBALS['pc']['requests'][] = $url;

		return $GLOBALS['pc']['response'] ?? [ 'code' => 200, 'body' => '{"status":"ok"}' ];
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		return is_array( $response ) ? ( $response['code'] ?? 200 ) : 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		return is_array( $response ) ? ( $response['body'] ?? '' ) : '';
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( string $key ) {
		$entry = $GLOBALS['pc']['transients'][ $key ] ?? null;

		if ( $entry === null ) {
			return false;
		}

		if ( $entry['expires'] !== 0 && $entry['expires'] < time() ) {
			unset( $GLOBALS['pc']['transients'][ $key ] );

			return false;
		}

		return $entry['value'];
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( string $key, $value, int $expiration = 0 ): bool {
		$GLOBALS['pc']['transients'][ $key ] = [
			'value'   => $value,
			'expires' => $expiration > 0 ? time() + $expiration : 0,
		];

		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( string $key ): bool {
		unset( $GLOBALS['pc']['transients'][ $key ] );

		return true;
	}
}
