<?php
/**
 * ProxyCheck.io Disposable Email Response Class
 *
 * @package     ArrayPress\ProxyCheck
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\ProxyCheck\Response\Client;

/**
 * Class DisposableEmailResponse
 *
 * Handles response data from the ProxyCheck.io API for disposable email checks.
 */
class DisposableEmail extends Base {

	/**
	 * Get the email address that was checked
	 *
	 * @return string|null
	 */
	public function get_email(): ?string {
		return $this->identifier ?? null;
	}

	/**
	 * Check if the email is from a disposable service
	 *
	 * @return bool
	 */
	public function is_disposable(): bool {
		return ( $this->data[ $this->identifier ]['disposable'] ?? null ) === 'yes';
	}

}