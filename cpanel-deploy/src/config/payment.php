<?php
/**
 * Payment Configuration
 */

// Load environment variables from .env file
require_once __DIR__ . '/.env.php';

return [
    'paystack' => [
        'public_key' => getenv('PAYSTACK_PUBLIC_KEY') ?: '',
        'secret_key' => getenv('PAYSTACK_SECRET_KEY') ?: '',
    ]
];