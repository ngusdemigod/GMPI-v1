<?php
/**
 * Paystack Payment Gateway Configuration
 * Bright Light Ministry Int'l Partnership Portal
 * 
 * Paystack API configuration with test/live environment support
 */

// Load environment variables from .env file
require_once __DIR__ . '/../.env.php';

// Paystack API Configuration
// Use constants if already defined by .env.php, otherwise use environment variables or defaults
if (!defined('PAYSTACK_SECRET_KEY')) {
    define('PAYSTACK_SECRET_KEY', $_ENV['PAYSTACK_SECRET_KEY'] ?? getenv('PAYSTACK_SECRET_KEY') ?? '');
}
if (!defined('PAYSTACK_PUBLIC_KEY')) {
    define('PAYSTACK_PUBLIC_KEY', $_ENV['PAYSTACK_PUBLIC_KEY'] ?? getenv('PAYSTACK_PUBLIC_KEY') ?? '');
}
if (!defined('PAYSTACK_WEBHOOK_SECRET')) {
    define('PAYSTACK_WEBHOOK_SECRET', $_ENV['PAYSTACK_WEBHOOK_SECRET'] ?? getenv('PAYSTACK_WEBHOOK_SECRET') ?? '');
}
if (!defined('PAYSTACK_ENVIRONMENT')) {
    define('PAYSTACK_ENVIRONMENT', $_ENV['PAYSTACK_ENVIRONMENT'] ?? getenv('PAYSTACK_ENVIRONMENT') ?? 'test'); // 'test' or 'live'
}

// Paystack API Base URL (same for both test and live)
define('PAYSTACK_API_URL', 'https://api.paystack.co');

// Webhook URL for this application - should be set in .env or use default
if (!defined('PAYSTACK_WEBHOOK_URL')) {
    define('PAYSTACK_WEBHOOK_URL', $_ENV['PAYSTACK_WEBHOOK_URL'] ?? getenv('PAYSTACK_WEBHOOK_URL') ?? 'https://partnership.brightlightministry.org.ng/paystack/webhook/live');
}

// Transaction settings
define('PAYSTACK_MIN_AMOUNT', 50); // Minimum amount in kobo (50 kobo = ₦0.50)
define('PAYSTACK_MAX_AMOUNT', 50000000); // Maximum amount in kobo (₦50,000)

// Currency settings
define('PAYSTACK_SUPPORTED_CURRENCIES', ['USD', 'NGN']);
define('PAYSTACK_DEFAULT_CURRENCY', 'USD');

// Subscription settings
define('PAYSTACK_SUBSCRIPTION_PLANS', [
    'weekly' => 'weekly',
    'monthly' => 'monthly',
    'annually' => 'annually'
]);
