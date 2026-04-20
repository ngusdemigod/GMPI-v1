<?php
/**
 * Paystack Configuration Diagnostic Script
 * Use this to debug Paystack API configuration issues
 */

// Load environment configuration
require_once __DIR__ . '/../.env.php';
require_once __DIR__ . '/paystack_config.php';

echo "=== Paystack Configuration Diagnostic ===\n\n";

// Check if .env file exists
$envFilePath = __DIR__ . '/../../.env';
echo "1. .env File Status:\n";
echo "   Path: " . $envFilePath . "\n";
echo "   Exists: " . (file_exists($envFilePath) ? "YES" : "NO") . "\n";

if (file_exists($envFilePath)) {
    $envContent = file_get_contents($envFilePath);
    $hasPaystackKey = strpos($envContent, 'PAYSTACK_SECRET_KEY=') !== false;
    echo "   Contains PAYSTACK_SECRET_KEY: " . ($hasPaystackKey ? "YES" : "NO") . "\n";
}
echo "\n";

// Check environment variables
echo "2. Environment Variables:\n";
echo "   getenv('PAYSTACK_SECRET_KEY'): " . (getenv('PAYSTACK_SECRET_KEY') ? "SET (length: " . strlen(getenv('PAYSTACK_SECRET_KEY')) . ")" : "NOT SET") . "\n";
echo "   getenv('PAYSTACK_PUBLIC_KEY'): " . (getenv('PAYSTACK_PUBLIC_KEY') ? "SET (length: " . strlen(getenv('PAYSTACK_PUBLIC_KEY')) . ")" : "NOT SET") . "\n";
echo "   \$_ENV['PAYSTACK_SECRET_KEY']: " . ($_ENV['PAYSTACK_SECRET_KEY'] ?? "NOT SET") . "\n";
echo "\n";

// Check constants
echo "3. Defined Constants:\n";
echo "   PAYSTACK_SECRET_KEY defined: " . (defined('PAYSTACK_SECRET_KEY') ? "YES" : "NO") . "\n";
echo "   PAYSTACK_SECRET_KEY value: " . (PAYSTACK_SECRET_KEY ? "SET (length: " . strlen(PAYSTACK_SECRET_KEY) . ")" : "EMPTY") . "\n";
echo "   PAYSTACK_PUBLIC_KEY defined: " . (defined('PAYSTACK_PUBLIC_KEY') ? "YES" : "NO") . "\n";
echo "   PAYSTACK_PUBLIC_KEY value: " . (PAYSTACK_PUBLIC_KEY ? "SET (length: " . strlen(PAYSTACK_PUBLIC_KEY) . ")" : "EMPTY") . "\n";
echo "   PAYSTACK_ENVIRONMENT: " . PAYSTACK_ENVIRONMENT . "\n";
echo "\n";

// Test PaystackService
echo "4. PaystackService Status:\n";
try {
    require_once __DIR__ . '/PaystackService.php';
    $service = new PaystackService();
    echo "   ✓ PaystackService instantiated successfully\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Check API connectivity
echo "5. API Connectivity Test:\n";
if (!empty(PAYSTACK_SECRET_KEY)) {
    echo "   Testing Paystack API connection...\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.paystack.co/transaction');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "   ✗ Connection error: " . $error . "\n";
    } else {
        echo "   HTTP Status: " . $httpCode . "\n";
        if ($httpCode == 200) {
            echo "   ✓ Successfully connected to Paystack API\n";
        } else if ($httpCode == 401) {
            echo "   ✗ Authentication failed. Check your secret key.\n";
        } else if ($httpCode == 400) {
            echo "   ✗ Bad request. This might be normal for the /transaction endpoint without parameters.\n";
        } else {
            echo "   ? Unexpected HTTP status code.\n";
        }
    }
} else {
    echo "   ✗ PAYSTACK_SECRET_KEY is empty. Cannot test API connection.\n";
}
echo "\n";

echo "=== End of Diagnostic ===\n";
