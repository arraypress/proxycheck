# ProxyCheck Library for WordPress

A comprehensive WordPress library for ProxyCheck.io integration, providing proxy & VPN detection, email verification, risk assessment, and complete dashboard management with intelligent caching.

## Features

- 🔍 **IP Analysis**: Detect proxies, VPNs, and assess risk levels
- ✉️ **Email Verification**: Check for disposable email services
- 📊 **Usage Analytics**: Track and manage API usage and quotas
- 📋 **List Management**: Handle whitelists, blocklists, and CORS origins
- 📈 **Statistics**: Access detection history and query analytics
- 🏷️ **Tag Management**: Track and analyze tagged queries
- 💾 **Smart Caching**: Optimize performance and reduce API calls

## Requirements

- PHP 7.4 or later
- WordPress 6.7.1 or later
- ProxyCheck.io API key
- Dashboard API Access enabled (for dashboard features)

## Installation

Install via Composer:

```bash
composer require arraypress/proxycheck
```

## Basic Usage

```php
use ArrayPress\ProxyCheck\Client;

// Initialize with API key
$client = new Client(
    'your-key-here',    // API key
    true,               // Enable caching (optional)
    600,                // Cache duration in seconds (optional)
    'custom_prefix_'    // Cache prefix (optional)
);

// Check single IP
$result = $client->check_ip( '1.1.1.1' );

// Check multiple IPs
$results = $client->check_ips( [ '1.1.1.1', '8.8.8.8' ] );

// Check email
$email = $client->check_email( 'test@example.com', true ); // Second param enables email masking
```

## Configuration Methods

### VPN & Proxy Settings

```php
// Configure VPN detection
$client->set_vpn( true );     // Enable VPN detection
$client->get_vpn();           // Check if VPN detection is enabled

// Configure ASN lookups
$client->set_asn( true );     // Enable ASN data inclusion
$client->get_asn();           // Check if ASN lookups are enabled

// Configure risk assessment
$client->set_risk( 2 );       // Set risk assessment level (0-2)
$client->get_risk();          // Get current risk assessment level

// Configure port checking
$client->set_port( true );    // Enable port checking
$client->get_port();          // Check if port checking is enabled

// Configure time seen data
$client->set_seen( true );    // Enable last seen data
$client->get_seen();          // Check if last seen data is enabled

// Configure history period
$client->set_days( 7 );       // Set history period in days
$client->get_days();          // Get current history period setting
```

## Core Features

### IP & Email Detection

#### Checking IPs

```php
// Basic check with default options
$result = $client->check_ip( '1.1.1.1' );

// Check with custom options
$result = $client->check_ip( '1.1.1.1', [
    'vpn'  => 1,    // Enable VPN detection
    'asn'  => 1,    // Include ASN data
    'risk' => 2,    // Include attack history
    'port' => 1,    // Check port
    'seen' => 1,    // Include last seen
    'days' => 7     // History period
] );

// Batch check multiple IPs
$results = $client->check_ips( [ '1.1.1.1', '8.8.8.8' ] );

// Access results
if ( $result->is_proxy() ) {
    echo "Proxy detected! Type: " . $result->get_type();
    echo "Risk score: " . $result->get_risk_score();
}
```

#### Checking Emails

```php
// Check disposable email
$result = $client->check_email( 'test@example.com' );

// With privacy masking
$result = $client->check_email( 'user@example.com', true ); // Masks as anonymous@example.com

if ( $result->is_disposable() ) {
    echo "Disposable email detected!";
}
```

### Dashboard Management

#### Usage & Statistics

```php
// Get usage information
$usage = $client->get_formatted_usage();
echo "Used today: " . $usage['used'] . " of " . $usage['limit'];
echo "Plan: " . $usage['plan'];

// Quick usage checks
$used = $client->get_used_tokens();
$remaining = $client->get_remaining_tokens();
$is_exceeded = $client->is_token_limit_exceeded();

// Get detailed query statistics
$stats = $client->get_formatted_queries( 7 ); // Last 7 days
print_r( $stats['summary'] );
```

#### List Management

```php
// Whitelist Management
$client->get_whitelist();
$client->add_to_whitelist( '1.1.1.1' );
$client->add_to_whitelist( ['1.1.1.1', '2.2.2.2' ] );
$client->remove_from_whitelist( '1.1.1.1' );
$client->set_whitelist( ['1.1.1.1', '2.2.2.2' ] ); // Replace all
$client->clear_whitelist();

// Blocklist Management
$client->get_blocklist();
$client->add_to_blocklist( '1.1.1.1' );
$client->remove_from_blocklist( '1.1.1.1' );
$client->set_blocklist( ['1.1.1.1', '2.2.2.2' ] );
$client->clear_blocklist();

// CORS Origins Management
$client->get_cors_origins();
$client->add_cors_origins( 'https://example.com' );
$client->remove_cors_origins( 'https://example.com' );
$client->set_cors_origins( [ 'https://example.com', 'https://test.com' ] );
$client->clear_cors_origins();
```

#### Detection Analytics

```php
// Get recent detections
$detections = $client->get_formatted_detections( 100 ); // Last 100 entries

// Get detections with pagination
$detections = $client->get_formatted_detections( 100, 50 ); // 100 entries starting from offset 50

// Force bypass cache for fresh data
$detections = $client->get_formatted_detections( 100, 0, true ); // Bypass cache

// Get tagged queries
$tags = $client->get_formatted_tags( [
    'limit' => 100,
    'days' => 7,
    'addresses' => true
] );
```

## Response Formats

### IP Check Response

```php
// Basic Information
$ip = $result->get_ip();
$is_proxy = $result->is_proxy();
$type = $result->get_type();
$risk = $result->get_risk_score();
$is_vpn = $result->is_vpn();

// Attack History
$attacks = $result->get_attack_history();

// Network Information
$port = $result->get_port();
$seen = $result->get_last_seen();
$operator = $result->get_operator();
$operator_details = $result->get_operator_details(); // Full details including protocols

// Location Information
$continent = $result->get_continent();
$country = $result->get_country();
$region = $result->get_region();
$city = $result->get_city();
$coordinates = $result->get_coordinates();
$timezone = $result->get_timezone();
$currency = $result->get_currency();

// Block Status
$should_block = $result->should_block();
$block_reason = $result->get_block_reason();
$block_details = $result->get_block_details();
```

### Usage Statistics Response

```php
[
    'used' => 1234,              // Queries used today
    'limit' => 5000,             // Daily query limit
    'total' => 50000,            // Total queries made
    'plan' => 'Premium',         // Account tier
    'burst_available' => 100,    // Available burst tokens
    'burst_limit' => 1000,       // Burst token allowance
    'percentage' => 24.68,       // Usage percentage
    'remaining' => 3766          // Remaining queries
]
```

### Query Statistics Response

```php
[
    'period' => 7,               // Days included
    'days' => [                  // Daily statistics
        [
            'day' => 'TODAY',
            'proxies' => 10,
            'vpns' => 5,
            'undetected' => 85,
            'total_queries' => 100
            // ... more metrics
        ]
    ],
    'totals' => [               // Period totals
        'proxies' => 50,
        'vpns' => 25,
        // ... more totals
    ],
    'percentages' => [          // Usage percentages
        'proxies' => 15.5,
        'vpns' => 7.8,
        // ... more percentages
    ],
    'summary' => [              // Overview
        'period_days' => 7,
        'active_days' => 5,
        'total_queries' => 500,
        'detected_threats' => 75,
        'detection_rate' => 15.0,
        'average_daily_queries' => 71.4
    ]
]
```

## Advanced Features

### Error Handling

The library uses WordPress's `WP_Error` for consistent error handling:

```php
$result = $client->check_ip( 'invalid-ip' );

if ( is_wp_error( $result ) ) {
    $code = $result->get_error_code();
    $message = $result->get_error_message();
    echo "Error ($code): $message";
}
```

### Caching

The library implements intelligent caching to optimize performance:

```php
// Configure caching
$client->set_cache_enabled( true );
$client->set_cache_expiration( 3600 );  // 1 hour
$client->set_cache_prefix( 'my_plugin_' );

// Force bypass cache for specific requests
$result = $client->export_detections( 100, 0, true );           // Bypass cache for raw data
$formatted = $client->get_formatted_detections( 100, 0, true ); // Bypass cache for formatted data

// Clear cache
$client->clear_cache();               // All cache
$client->clear_cache( '1.1.1.1' );    // Specific entry
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

## License

Licensed under the GPLv2 or later license.

## Support

- [Documentation](https://proxycheck.io/api/)
- [Dashboard Access](https://proxycheck.io/dashboard/)
- [Issue Tracker](https://github.com/arraypress/proxycheck/issues)