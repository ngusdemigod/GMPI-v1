<?php
/**
 * Resend Email Service Configuration
 * Church Financial Partnership System
 * 
 * To get your API key:
 * 1. Sign up at https://resend.com
 * 2. Go to API Keys in your dashboard
 * 3. Create a new API key
 * 4. Set RESEND_API_KEY below
 */

// Load environment variables from .env file
require_once __DIR__ . '/../.env.php';

// Resend API Configuration
if (!defined('RESEND_API_KEY')) {
    define('RESEND_API_KEY', getenv('RESEND_API_KEY') ?: '');
}
if (!defined('RESEND_API_URL')) {
    define('RESEND_API_URL', 'https://api.resend.com');
}

// Email Sender Configuration
if (!defined('RESEND_FROM_EMAIL')) {
    define('RESEND_FROM_EMAIL', getenv('RESEND_FROM_EMAIL') ?: 'noreply@yourdomain.com');
}
if (!defined('RESEND_FROM_NAME')) {
    define('RESEND_FROM_NAME', getenv('RESEND_FROM_NAME') ?: 'Your Organization');
}

if (!defined('EMAIL_ALLOW_PHP_MAIL_FALLBACK')) {
    $fallbackSetting = strtolower((string) (getenv('EMAIL_ALLOW_PHP_MAIL_FALLBACK') ?: 'false'));
    define('EMAIL_ALLOW_PHP_MAIL_FALLBACK', in_array($fallbackSetting, ['1', 'true', 'yes', 'on'], true));
}

// Email Settings
define('EMAIL_SETTINGS', [
    'reply_to' => 'support@brightlightministry.org.ng',
    'bcc_admin' => true,
    'log_emails' => true,
    'allow_php_mail_fallback' => EMAIL_ALLOW_PHP_MAIL_FALLBACK,
]);
