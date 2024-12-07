# ProxyCheck Library for WordPress

A WordPress library for ProxyCheck.io API integration providing proxy & VPN detection, disposable email verification, and risk assessment with smart caching.

## Installation

Install via Composer:

```bash
composer require arraypress/proxycheck
```

## Requirements

- PHP 7.4 or later
- WordPress 6.2.2 or later
- ProxyCheck.io API key

## Basic Usage

```php
use ArrayPress\ProxyCheck\Client;

// Initialize with your API key
$client = new Client('your-key-here');

// Check a single IP address
$check = $client->check_ip('1.1.1.1');

// Check with additional options
$check = $client->check_ip('1.1.1.1', [
    'vpn'  => 1,    // Enable VPN detection
    'asn'  => 1,    // Include ASN data
    'risk' => 1     // Include risk scoring
]);

// Check multiple IPs
$ips = ['1.1.1.1', '8.8.8.8'];
$results = $client->check_ips($ips);

// Check if an email is disposable
$email_check = $client->check_email('test@example.com');
```

## Available Methods

### Client Methods

```php
// Initialize client with options
$client = new Client(
    'your-key-here',     // API key
    true,                // Enable caching (optional, default: true)
    600                  // Cache duration in seconds (optional, default: 600)
);

// Check single IP
$check = $client->check_ip('1.1.1.1');

// Check multiple IPs
$checks = $client->check_ips(['1.1.1.1', '8.8.8.8']);

// Check disposable email
$email = $client->check_email('test@example.com');

// Cache management
$client->clear_cache('1.1.1.1');  // Clear specific IP
$client->clear_cache();           // Clear all cached data
```

### Response Methods for IP Checks

```php
// Get IP address
$ip = $check->get_ip();
// Returns: "1.1.1.1"

// Check if it's a proxy
$is_proxy = $check->is_proxy();
// Returns: true/false

// Get proxy type
$type = $check->get_type();
// Returns: "VPN", "TOR", "SOCKS4", etc.

// Get risk score (0-100)
$risk = $check->get_risk_score();
// Returns: 25

// Get attack history (requires risk=2 flag)
$attacks = $check->get_attack_history();
// Returns: [
//     'login_attempts' => 5,
//     'comment_spam' => 2,
//     ...
// ]

// Get proxy port
$port = $check->get_port();
// Returns: 8080

// Get last seen timestamp
$seen = $check->get_last_seen();
// Returns: "2024-01-15 14:30:00"

// Get operator/ASN info
$operator = $check->get_operator();
// Returns: [
//     'name' => 'Cloudflare, Inc.',
//     'asn'  => 'AS13335'
// ]

// Get location information
$continent = $check->get_continent();
// Returns: "North America"

$country = $check->get_country();
// Returns: [
//     'name'  => 'United States',
//     'code'  => 'US',
//     'is_eu' => false
// ]

$region = $check->get_region();
// Returns: [
//     'name' => 'California',
//     'code' => 'CA'
// ]

$city = $check->get_city();
// Returns: "Los Angeles"

// Get currency information
$currency = $check->get_currency();
// Returns: [
//     'code'   => 'USD',
//     'name'   => 'US Dollar',
//     'symbol' => '$'
// ]

// Get timezone
$timezone = $check->get_timezone();
// Returns: "America/Los_Angeles"

// Check VPN status (requires vpn flag)
$is_vpn = $check->is_vpn();
// Returns: true/false

// Get response status
$status = $check->get_status();
// Returns: "ok", "warning", "denied", or "error"

// Get error message
$message = $check->get_message();
// Returns: error message if present

// Check if query was successful
$success = $check->is_successful();
// Returns: true/false
```

### Response Methods for Email Checks

```php
// Get checked email address
$email = $check->get_email();
// Returns: "test@example.com"

// Check if email is disposable
$is_disposable = $check->is_disposable();
// Returns: true/false

// Get response status
$status = $check->get_status();
// Returns: "ok", "warning", "denied", or "error"

// Get error message
$message = $check->get_message();
// Returns: error message if present

// Check if query was successful
$success = $check->is_successful();
// Returns: true/false
```

## Response Format Examples

### IP Check Response

```php
[
    'status' => 'ok',
    '1.1.1.1' => [
        'proxy'     => 'yes',
        'type'      => 'VPN',
        'risk'      => 25,
        'port'      => 8080,
        'seen'      => '2024-01-15 14:30:00',
        'provider'  => 'Cloudflare, Inc.',
        'continent' => 'North America',
        'country'   => 'United States',
        'isocode'   => 'US',
        'region'    => 'California',
        'regioncode'=> 'CA',
        'city'      => 'Los Angeles',
        'timezone'  => 'America/Los_Angeles',
        'currency'  => [
            'code'   => 'USD',
            'name'   => 'US Dollar',
            'symbol' => '$'
        ],
        'operator'  => [
            'name' => 'Cloudflare, Inc.',
            'asn'  => 'AS13335'
        ]
    ]
]
```

### Email Check Response

```php
[
    'status' => 'ok',
    'test@example.com' => [
        'disposable' => 'yes'
    ]
]
```

### Batch Processing Response

```php
$results = $client->check_ips(['1.1.1.1', '8.8.8.8']);
// Returns:
[
    '1.1.1.1' => Response Object,
    '8.8.8.8' => Response Object
]
```

## Error Handling

The library uses WordPress's `WP_Error` for error handling:

```php
$check = $client->check_ip('invalid-ip');

if (is_wp_error($check)) {
    echo $check->get_error_message();
    // Output: "Invalid IP address: invalid-ip"
}
```

Common error cases:
- Invalid IP address
- Invalid email address
- Invalid API key
- API request failure
- Query limit exceeded
- Invalid response format

## Query Flags

The API supports various query flags for customizing responses:

```php
$options = [
    'vpn'  => 1,     // VPN detection (0 = off, 1 = on with proxy priority, 2 = VPN only, 3 = both)
    'asn'  => 1,     // Include ASN data
    'node' => 1,     // Show which node answered the query
    'time' => 1,     // Show query processing time
    'risk' => 1,     // Include risk score (1 = score only, 2 = score with attack history)
    'port' => 1,     // Show detected port number
    'seen' => 1,     // Show when proxy was last seen
    'days' => 7,     // Days of historical data to check (default: 7)
    'tag'  => 'msg'  // Custom tag for query
];

$check = $client->check_ip('1.1.1.1', $options);
```

## Contributions

Contributions to this library are highly appreciated. Raise issues on GitHub or submit pull requests for bug fixes or new features. Share feedback and suggestions for improvements.

## License: GPLv2 or later

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.