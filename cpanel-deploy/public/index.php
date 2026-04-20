<?php
/**
 * Local development front controller.
 *
 * This lets the PHP built-in server started with:
 *   php -S localhost:8000 -t public
 * route /paystack/webhook to the sample webhook handler below.
 */

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$normalizedPath = rtrim($requestPath, '/');

if ($normalizedPath === '/paystack/webhook') {
    require __DIR__ . '/paystack_webhook.php';
    return;
}

if ($normalizedPath === '/paystack/webhook/live') {
    require __DIR__ . '/paystack_live_webhook.php';
    return;
}

require __DIR__ . '/../src/index.php';
