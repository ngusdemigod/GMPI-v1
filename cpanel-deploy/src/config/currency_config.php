<?php
/**
 * Currency Converter Configuration
 * Bright Light Ministry Int'l Partners Portal
 * 
 * Configuration for ExchangeRate-API integration
 */

// Load environment variables from .env file
require_once __DIR__ . '/../.env.php';

// API Configuration
if (!defined('CURRENCY_API_KEY')) {
    define('CURRENCY_API_KEY', getenv('CURRENCY_API_KEY') ?: '');
}
if (!defined('CURRENCY_API_URL')) {
    define('CURRENCY_API_URL', 'https://v6.exchangerate-api.com/v6/' . CURRENCY_API_KEY . '/latest/');
}
if (!defined('CURRENCY_API_URL_PAIR')) {
    define('CURRENCY_API_URL_PAIR', 'https://v6.exchangerate-api.com/v6/' . CURRENCY_API_KEY . '/pair/');
}

// Default Settings
if (!defined('CURRENCY_DEFAULT_BASE'))    { define('CURRENCY_DEFAULT_BASE', 'NGN'); }
if (!defined('CURRENCY_CACHE_DURATION'))  { define('CURRENCY_CACHE_DURATION', 28800); } // 8 hours in seconds
if (!defined('CURRENCY_CACHE_METHOD'))    { define('CURRENCY_CACHE_METHOD', 'file'); } // 'file' or 'database'
if (!defined('CURRENCY_CACHE_DIR'))       { define('CURRENCY_CACHE_DIR', __DIR__ . '/../cache/currency_rates/'); }

// Supported Currencies (USD and NGN only)
if (!defined('CURRENCY_SUPPORTED')) {
    define('CURRENCY_SUPPORTED', [
        'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'flag' => '🇺🇸'],
        'NGN' => ['name' => 'Nigerian Naira', 'symbol' => '₦', 'flag' => '🇳🇬'],
    ]);
}

// Fallback exchange rates (used when API is unavailable)
// These should be updated periodically
if (!defined('CURRENCY_FALLBACK_RATES')) {
    define('CURRENCY_FALLBACK_RATES', [
        'USD' => 1.0,
        'NGN' => 1400.0,
        'EUR' => 0.92,
        'GBP' => 0.79,
    ]);
}

// API Request Settings
if (!defined('CURRENCY_API_TIMEOUT'))          { define('CURRENCY_API_TIMEOUT', 5); }          // seconds
if (!defined('CURRENCY_API_RETRIES'))          { define('CURRENCY_API_RETRIES', 2); }

// Display Settings
if (!defined('CURRENCY_DECIMAL_PLACES'))       { define('CURRENCY_DECIMAL_PLACES', 2); }
if (!defined('CURRENCY_DECIMAL_PLACES_LARGE')) { define('CURRENCY_DECIMAL_PLACES_LARGE', 0); } // For large amounts (thousands+)