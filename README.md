# ProxyCheck

Ask ProxyCheck.io whether an IP is a proxy, VPN or hosting provider, and
whether an email address is disposable.

## What it does

Most fraud on a small store arrives from a datacentre IP or a throwaway
inbox. ProxyCheck.io answers both, and this wraps its API in something you
can call from a checkout without thinking about HTTP: a response object with
named methods, and a cache so the same visitor is not looked up on every page.

## Features

* Tell whether an IP is a proxy, VPN, Tor exit or hosting provider
* Get a risk score, and the attack history behind it
* Check several addresses in one request rather than one at a time
* Tell whether an email address is disposable
* Read the country, city and network the address belongs to
* Cache answers for as long as you choose, and clear one address on demand

## Installation

```bash
composer require arraypress/wp-proxycheck
```

## Quick start

Refuse a datacentre IP at checkout, and let everything else through:

```php
use ArrayPress\ProxyCheck\Client;

$client = new Client( $api_key );
$result = $client->check_ip( $ip );

if ( is_wp_error( $result ) ) {
	return; // An API failure is not a reason to lose the order.
}

if ( $result->is_proxy() || $result->is_vpn() ) {
	$order->flag_for_review( $result->get_risk_score() );
}
```

And the email, before the account is created:

```php
if ( $client->check_email( $email )->is_disposable() ) {
	// ...
}
```

## What it does not do

It does not tell you somebody is a fraudster. A VPN is a privacy tool as
often as it is a red flag, and plenty of legitimate customers use one — the
score belongs in a decision alongside other signals, not on its own.

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later
* A ProxyCheck.io API key (the free tier is generous)

## License

GPL-2.0-or-later
