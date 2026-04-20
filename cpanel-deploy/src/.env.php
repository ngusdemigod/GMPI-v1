<?php
/**
 * Environment Variables Configuration
 * Bright Light Ministry Int'l Partnership Portal
 * 
 * This file loads environment variables from the .env file.
 * Searches multiple locations for flexibility (local/Docker).
 */

// Search for .env file in multiple locations
$envFile = null;
$possiblePaths = [
    __DIR__ . '/.env',           // src/.env
    __DIR__ . '/../.env',        // root .env
    '/var/www/html/.env',        // Docker root
    '/var/www/html/src/.env',    // Docker src
    getcwd() . '/.env',          // Current working directory
    getcwd() . '/src/.env',      // Current working directory / src
];

foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $envFile = $path;
        break;
    }
}

// Load environment variables from .env file if found
if ($envFile && file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments and empty lines
        if (strpos(trim($line), '#') === 0 || trim($line) === '') {
            continue;
        }
        
        // Parse KEY=VALUE pairs
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            $value = trim($value, '"\'');
            
            // Set environment variable using putenv() so getenv() can access it
            putenv("{$key}={$value}");
            
            // Also set in $_ENV array for backward compatibility
            $_ENV[$key] = $value;
            
            // Define the constant if not already defined
            if (!defined($key)) {
                define($key, $value);
            }
        }
    }
}

// Currency API Configuration
if (!defined('CURRENCY_API_KEY')) {
    define('CURRENCY_API_KEY', '');
}

// Paystack Configuration
if (!defined('PAYSTACK_SECRET_KEY')) {
    define('PAYSTACK_SECRET_KEY', '');
}

if (!defined('PAYSTACK_PUBLIC_KEY')) {
    define('PAYSTACK_PUBLIC_KEY', '');
}

if (!defined('PAYSTACK_WEBHOOK_SECRET')) {
    define('PAYSTACK_WEBHOOK_SECRET', '');
}

if (!defined('PAYSTACK_ENVIRONMENT')) {
    define('PAYSTACK_ENVIRONMENT', 'test'); // test or live
}

// Resend Configuration
if (!defined('RESEND_API_KEY')) {
    define('RESEND_API_KEY', '');
}
if (!defined('RESEND_FROM_EMAIL')) {
    define('RESEND_FROM_EMAIL', 'noreply@brightlightministry.org');
}
if (!defined('RESEND_FROM_NAME')) {
    define('RESEND_FROM_NAME', 'Bright Light Ministry Int\'l');
}
if (!defined('RESEND_API_URL')) {
    define('RESEND_API_URL', 'https://api.resend.com');
}

// Security Configuration
if (!defined('JWT_SECRET')) {
    define('JWT_SECRET', 'your-secret-key-change-this-in-production');
}

// Session Configuration
if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', 3600); // 1 hour in seconds
}

// Database Configuration (fallback defaults)
if (!defined('DB_HOST')) {
    // Use mysql for Docker, localhost for local development
    $dbHost = getenv('DB_HOST') ?: (getenv('DOCKER_ENV') ? 'mysql' : '127.0.0.1');
    define('DB_HOST', $dbHost);
}
if (!defined('DB_PORT')) {
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'church_partnership');
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: 'church_user');
}
if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'church_password123');
}
