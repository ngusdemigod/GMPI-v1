<?php
/**
 * Paystack Transaction Verification API
 * Bright Light Ministry Int'l Partnership Portal
 * 
 * Verifies a transaction by reference after user returns from Paystack
 */

// Disable error output to ensure clean JSON response
error_reporting(0);
ini_set('display_errors', '0');

// Send JSON content type header first
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/PaystackService.php';
require_once __DIR__ . '/../../config/database.php';

// Get database connection
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed'
    ]);
    exit();
}

// Check if POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    exit();
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

// Get reference from POST data or URL parameter
$reference = $input['reference'] ?? $_GET['reference'] ?? $_GET['trxref'] ?? '';

if (empty($reference)) {
    echo json_encode([
        'success' => false,
        'error' => 'Missing reference parameter'
    ]);
    exit();
}

// Verify transaction with Paystack
try {
    $paystack = new PaystackService();
    $verification = $paystack->verifyTransaction($reference);
    
    if (isset($verification['success']) && $verification['success']) {
        $data = $verification['data'] ?? $verification;
        
        // Return verification result
        echo json_encode([
            'success' => true,
            'data' => [
                'status' => $data['status'] ?? 'success',
                'amount' => $data['amount'] ? convertFromKobo($data['amount']) : 0,
                'currency' => $data['currency'] ?? 'USD',
                'reference' => $data['reference'] ?? $reference,
                'paid_at' => $data['paid_at'] ?? null,
                'email' => $data['email'] ?? '',
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'gateway' => $data['gateway'] ?? '',
                'channel' => $data['channel'] ?? ''
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $verification['error'] ?? 'Transaction verification failed',
            'data' => [
                'status' => 'unknown',
                'reference' => $reference
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log("Paystack verify error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Transaction verification failed: ' . $e->getMessage()
    ]);
}
