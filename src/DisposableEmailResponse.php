<?php
/**
 * ProxyCheck.io Disposable Email Response Class
 *
 * @package     ArrayPress/ProxyCheck
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\ProxyCheck;

/**
 * Class DisposableEmailResponse
 *
 * Handles response data from the ProxyCheck.io API for disposable email checks.
 */
class DisposableEmailResponse {

	/**
	 * Raw response data from the API
	 *
	 * @var array
	 */
	private array $data;

	/**
	 * The email address being checked
	 *
	 * @var string
	 */
	private string $email;

	/**
	 * Initialize the response object
	 *
	 * @param array $data Raw response data from ProxyCheck API
	 */
	public function __construct( array $data ) {
		$this->data = $data;
		// Extract the email from the response data
		$this->email = array_key_first( array_diff_key( $data, [ 'status' => 1, 'message' => 1 ] ) );
	}

	/**
	 * Get raw data array
	 *
	 * @return array
	 */
	public function get_all(): array {
		return $this->data;
	}

	/**
	 * Get the email address that was checked
	 *
	 * @return string|null
	 */
	public function get_email(): ?string {
		return $this->email ?? null;
	}

	/**
	 * Check if the email is from a disposable service
	 *
	 * @return bool
	 */
	public function is_disposable(): bool {
		return ( $this->data[ $this->email ]['disposable'] ?? null ) === 'yes';
	}

	/**
	 * Get status message if present
	 *
	 * @return string|null
	 */
	public function get_status(): ?string {
		return $this->data['status'] ?? null;
	}

	/**
	 * Get error message if present
	 *
	 * @return string|null
	 */
	public function get_message(): ?string {
		return $this->data['message'] ?? null;
	}

	/**
	 * Check if the query was successful
	 *
	 * @return bool
	 */
	public function is_successful(): bool {
		return ( $this->data['status'] ?? 'ok' ) === 'ok';
	}

}