<?php
/**
 * Paystack Payment Initialization API
 * Bright Light Ministry Int'l Partnership Portal
 * 
 * Initiates a Paystack payment transaction for one-time donations
 */

// Disable error output to ensure clean JSON response
error_reporting(0);
ini_set('display_errors', '0');

// Send JSON content type header first
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function respondJson(int $statusCode, array $payload): void {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit();
}

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../config/PaystackService.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/security.php';

// Get database connection
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    respondJson(500, [
        'success' => false,
        'error' => 'Database connection failed'
    ]);
}

// Check if POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson(405, [
        'success' => false,
        'error' => 'Method not allowed'
    ]);
}

// Server-side session check - user must be logged in
if (empty($_SESSION['user_id'])) {
    respondJson(401, [
        'success' => false,
        'error' => 'Your session has expired. Please log in to continue.',
        'code' => 'AUTH_REQUIRED'
    ]);
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['email', 'amount', 'currency'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        respondJson(422, [
            'success' => false,
            'error' => "Missing required field: {$field}"
        ]);
    }
}

$email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
$amount = floatval($input['amount']);
$currency = strtoupper($input['currency']);

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respondJson(422, [
        'success' => false,
        'error' => 'Invalid email address'
    ]);
}

// Validate amount
if ($amount <= 0) {
    respondJson(422, [
        'success' => false,
        'error' => 'Invalid amount'
    ]);
}

// Convert to smallest currency unit (cents for USD, kobo for NGN)
$amountInSmallestUnit = convertToKobo($amount, $currency);

// Get optional fields
$reference = $input['reference'] ?? generateReference();
$projectId = $input['project_id'] ?? null;
$category = $input['category'] ?? 'Donation';
$description = $input['description'] ?? 'Donation to Bright Light Ministry Int\'l';

// Prepare metadata for Paystack
$metadata = [
    'structure' => 'transaction',
    'data' => [
        'first_name' => $input['first_name'] ?? '',
        'last_name' => $input['last_name'] ?? '',
        'phone' => $input['phone'] ?? '',
        'project_id' => $projectId,
        'category' => $category,
        'portal' => 'web',
        'description' => $description
    ]
];

// Initialize Paystack transaction
try {
    $paystack = new PaystackService();
    
    $response = $paystack->initializeTransaction([
        'user_id' => (int) $_SESSION['user_id'],
        'email' => $email,
        'amount' => $amountInSmallestUnit,
        'currency' => $currency,
        'reference' => $reference,
        'metadata' => $metadata,
        'description' => $description,
        'project_id' => $projectId,
        'category' => $category
    ]);
    
    if (isset($response['success']) && $response['success'] === true && isset($response['data'])) {
        $data = $response['data'];
        $authorizationUrl = $data['authorization_url'] ?? '';
        $reference = $data['reference'] ?? $reference;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'authorization_url' => $authorizationUrl,
                'reference' => $reference,
                'amount' => $amount,
                'currency' => $currency
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $response['error'] ?? 'Failed to initialize payment'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Paystack initialize error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Payment initialization failed: ' . $e->getMessage()
    ]);
}
