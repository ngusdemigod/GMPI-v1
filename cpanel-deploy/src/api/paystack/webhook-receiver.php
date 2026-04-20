<?php
declare(strict_types=1);
/**
 * Paystack Webhook Receiver
 * Bright Light Ministry Int'l Partnership Portal
 * 
 * Receives and processes webhook events from Paystack
 * Validates webhook signature using HMAC SHA512
 * Verifies transactions server-side before updating database
 */

// Disable error output to ensure clean JSON response
error_reporting(0);
ini_set('display_errors', '0');

// Send JSON content type header first
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../config/PaystackService.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/ResendService.php';
require_once __DIR__ . '/../../config/paystack_config.php';
require_once __DIR__ . '/security.php';

function respondJson(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respondJson(405, ['status' => 'error', 'message' => 'Method not allowed']);
}

// Get raw POST data
$rawData = file_get_contents('php://input');
if ($rawData === false || $rawData === '') {
    respondJson(400, ['status' => 'error', 'message' => 'Empty request body']);
}

// Verify webhook signature using HMAC SHA512
// This ensures the webhook is genuinely from Paystack
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
if ($signature === '' && function_exists('getallheaders')) {
    $headers = getallheaders();
    foreach ($headers as $name => $value) {
        if (strtolower((string) $name) === 'x-paystack-signature') {
            $signature = (string) $value;
            break;
        }
    }
}

if (!validateWebhookSignature($rawData, $signature, PAYSTACK_SECRET_KEY)) {
    error_log('Paystack webhook: Invalid signature received');
    respondJson(401, ['status' => 'error', 'message' => 'Invalid signature']);
}

$data = json_decode($rawData, true);
if (!is_array($data)) {
    error_log('Paystack webhook: Invalid JSON payload received');
    respondJson(400, ['status' => 'error', 'message' => 'Invalid JSON payload']);
}

// Get database connection after validation so invalid requests fail fast.
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
} catch (Throwable $e) {
    error_log('Paystack webhook: Database connection failed - ' . $e->getMessage());
    respondJson(500, ['status' => 'error', 'message' => 'Database connection failed']);
}

// Get Resend service for email notifications
$resendService = new ResendService();

$event = $data['event'] ?? '';
$reference = $data['data']['reference'] ?? '';
$status = $data['data']['status'] ?? '';

// Log webhook for debugging
error_log("Paystack webhook received: {$event} - {$reference} - {$status}");

try {
    switch ($event) {
        case 'charge.success':
            handleSuccessfulPayment($pdo, $resendService, $data);
            break;

        case 'charge.failed':
            handleFailedPayment($pdo, $data);
            break;

        case 'subscription.success':
        case 'subscription.active':
            handleSubscriptionSuccess($pdo, $data);
            break;

        case 'subscription.failure':
        case 'subscription.paused':
            handleSubscriptionFailure($pdo, $data);
            break;

        case 'subscription.expired':
        case 'subscription.cancelled':
            handleSubscriptionExpired($pdo, $data);
            break;

        default:
            error_log("Unhandled webhook event: {$event}");
            break;
    }

    respondJson(200, ['status' => 'success']);
} catch (Throwable $e) {
    error_log("Webhook handler error: " . $e->getMessage());
    respondJson(500, ['status' => 'error', 'message' => $e->getMessage()]);
}

/**
 * Handle successful payment
 * Verifies transaction with Paystack before updating database
 */
function handleSuccessfulPayment($pdo, $resendService, $data) {
    $transactionData = $data['data'];
    $reference = $transactionData['reference'];
    $amount = convertFromKobo($transactionData['amount'] ?? 0);
    $currency = $transactionData['currency'] ?? 'USD';
    $email = $transactionData['email'] ?? '';
    $metadata = json_decode($transactionData['metadata'] ?? '{}', true);
    $paidAt = $transactionData['paid_at'] ?? date('Y-m-d H:i:s');
    $gatewayResponse = $transactionData['gateway_response'] ?? '';
    $channel = $transactionData['channel'] ?? '';
    $gatewayTransactionId = $transactionData['gateway'] ?? '';
    
    // CRITICAL: Verify transaction with Paystack server-side
    // Never trust webhook payload alone - always verify
    $paystack = new PaystackService();
    $verification = $paystack->verifyTransaction($reference);
    
    if (!isset($verification['success']) || !$verification['success']) {
        error_log("Webhook verification failed for reference: {$reference}");
        return; // Don't process if verification fails
    }
    
    // Extract metadata
    $metadataData = is_array($metadata) ? $metadata : [];
    $metadataObj = isset($metadataData['data']) ? $metadataData['data'] : $metadataData;
    $firstName = $metadataObj['first_name'] ?? 'Partner';
    $category = $metadataObj['category'] ?? 'Donation';
    $projectId = $metadataObj['project_id'] ?? null;
    $description = $metadataObj['description'] ?? $category;
    
    // Get or create transaction record
    try {
        // Check if transaction exists in paystack_transactions table
        $stmt = $pdo->prepare("SELECT id, transaction_id FROM paystack_transactions WHERE paystack_reference = ?");
        $stmt->execute([$reference]);
        $existingRecord = $stmt->fetch();
        
        if ($existingRecord) {
            // Update existing record
            $updateStmt = $pdo->prepare("
                UPDATE paystack_transactions 
                SET status = 'success', 
                    paid_at = ?, 
                    gateway_response = ?,
                    channel = ?,
                    gateway_transaction_id = ?,
                    updated_at = NOW()
                WHERE paystack_reference = ?
            ");
            $updateStmt->execute([
                $paidAt,
                $gatewayResponse,
                $channel,
                $gatewayTransactionId,
                $reference
            ]);
            
            // Update main transactions table
            $updateTxStmt = $pdo->prepare("
                UPDATE transactions 
                SET status = 'completed',
                    gateway_response = ?,
                    channel = ?,
                    paid_at = ?,
                    gateway_transaction_id = ?,
                    updated_at = NOW()
                WHERE transaction_reference = ?
            ");
            $updateTxStmt->execute([
                $gatewayResponse,
                $channel,
                $paidAt,
                $gatewayTransactionId,
                $reference
            ]);
        } else {
            // Create new transaction record
            $txStmt = $pdo->prepare("
                INSERT INTO transactions 
                (user_id, project_id, amount, category, frequency, transaction_reference, status, gateway_response, channel, paid_at, gateway_transaction_id, currency, created_at)
                VALUES 
                (NULL, ?, ?, ?, 'One-time', ?, 'completed', ?, ?, ?, ?, ?, NOW())
            ");
            $txStmt->execute([
                $projectId,
                $amount,
                $category,
                $reference,
                $gatewayResponse,
                $channel,
                $paidAt,
                $gatewayTransactionId,
                $currency
            ]);
            
            $transactionId = $pdo->lastInsertId();
            
            // Store in paystack_transactions table
            $paystackStmt = $pdo->prepare("
                INSERT INTO paystack_transactions 
                (transaction_id, paystack_reference, status, amount, currency, email, first_name, last_name, gateway_response, channel, metadata, created_at)
                VALUES 
                (?, ?, 'success', ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $paystackStmt->execute([
                $transactionId,
                $reference,
                $transactionData['amount'] ?? 0,
                $currency,
                $email,
                $metadataObj['first_name'] ?? '',
                $metadataObj['last_name'] ?? '',
                $gatewayResponse,
                $channel,
                json_encode($metadataObj)
            ]);
        }
        
        // Send receipt email
        if (!empty($email)) {
            try {
                $emailResult = $resendService->sendDonationReceipt([
                    'email' => $email,
                    'first_name' => $firstName,
                    'amount' => $amount,
                    'currency' => $currency,
                    'category' => $category,
                    'reference' => $reference,
                    'date' => $paidAt,
                    'project' => $projectId,
                    'description' => $description
                ]);
                if (!empty($emailResult['success'])) {
                    error_log("Receipt email sent for reference: {$reference}");
                } else {
                    error_log(sprintf(
                        'Receipt email failed for reference: %s error=%s',
                        $reference,
                        $emailResult['error'] ?? 'unknown'
                    ));
                }
            } catch (Exception $e) {
                error_log("Failed to send receipt email: " . $e->getMessage());
            }
        }
        
        error_log("Successful payment processed: {$reference}");
        
    } catch (Exception $e) {
        error_log("Error processing successful payment: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Handle failed payment
 */
function handleFailedPayment($pdo, $data) {
    $transactionData = $data['data'];
    $reference = $transactionData['reference'];
    
    // Update transaction status
    try {
        $stmt = $pdo->prepare("
            UPDATE paystack_transactions 
            SET status = 'failed', updated_at = NOW()
            WHERE paystack_reference = ?
        ");
        $stmt->execute([$reference]);
        
        // Also update main transactions table
        $txStmt = $pdo->prepare("
            UPDATE transactions 
            SET status = 'failed', updated_at = NOW()
            WHERE transaction_reference = ?
        ");
        $txStmt->execute([$reference]);
        
        error_log("Failed payment recorded: {$reference}");
    } catch (Exception $e) {
        error_log("Error in failed payment handler: " . $e->getMessage());
    }
}

/**
 * Handle subscription success/active
 */
function handleSubscriptionSuccess($pdo, $data) {
    $subscriptionData = $data['data'];
    $reference = $subscriptionData['id'] ?? $subscriptionData['reference'] ?? '';
    $email = $subscriptionData['customer']['email'] ?? $subscriptionData['email'] ?? '';
    $metadata = json_decode($subscriptionData['metadata'] ?? '{}', true);
    $metadataObj = isset($metadata['data']) ? $metadata['data'] : $metadata;
    $frequency = $subscriptionData['frequency'] ?? 'monthly';
    $amount = convertFromKobo($subscriptionData['amount'] ?? 0);
    $currency = $subscriptionData['currency'] ?? 'USD';
    $firstName = $metadataObj['first_name'] ?? '';
    $lastName = $metadataObj['last_name'] ?? '';
    
    // Store or update subscription
    try {
        // Check if subscription exists
        $stmt = $pdo->prepare("SELECT id FROM payment_subscriptions WHERE paystack_subscription_id = ?");
        $stmt->execute([$reference]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing subscription
            $updateStmt = $pdo->prepare("
                UPDATE payment_subscriptions 
                SET status = 'active',
                    next_billing_date = ?,
                    email = ?,
                    first_name = ?,
                    last_name = ?,
                    updated_at = NOW()
                WHERE paystack_subscription_id = ?
            ");
            $nextBilling = isset($subscriptionData['next_billing_date']) 
                ? $subscriptionData['next_billing_date'] 
                : date('Y-m-d', strtotime('+1 month'));
            $updateStmt->execute([
                $nextBilling,
                $email,
                $firstName,
                $lastName,
                $reference
            ]);
        } else {
            // Create new subscription (user_id is NULL for guest donations)
            $insertStmt = $pdo->prepare("
                INSERT INTO payment_subscriptions 
                (paystack_subscription_id, status, frequency, amount, currency, email, first_name, last_name, next_billing_date, metadata, created_at)
                VALUES 
                (?, 'active', ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $nextBilling = isset($subscriptionData['next_billing_date']) 
                ? $subscriptionData['next_billing_date'] 
                : date('Y-m-d', strtotime('+1 month'));
            $insertStmt->execute([
                $reference,
                $frequency,
                $amount,
                $currency,
                $email,
                $firstName,
                $lastName,
                $nextBilling,
                json_encode($metadataObj)
            ]);
        }
        
        error_log("Subscription created/updated: {$reference}");
    } catch (Exception $e) {
        error_log("Error in subscription success handler: " . $e->getMessage());
    }
}

/**
 * Handle subscription failure/paused
 */
function handleSubscriptionFailure($pdo, $data) {
    $subscriptionData = $data['data'];
    $reference = $subscriptionData['id'] ?? $subscriptionData['reference'] ?? '';
    
    try {
        $stmt = $pdo->prepare("
            UPDATE payment_subscriptions 
            SET status = 'paused',
                updated_at = NOW()
            WHERE paystack_subscription_id = ?
        ");
        $stmt->execute([$reference]);
        
        error_log("Subscription paused: {$reference}");
    } catch (Exception $e) {
        error_log("Error in subscription failure handler: " . $e->getMessage());
    }
}

/**
 * Handle subscription expired/cancelled
 */
function handleSubscriptionExpired($pdo, $data) {
    $subscriptionData = $data['data'];
    $reference = $subscriptionData['id'] ?? $subscriptionData['reference'] ?? '';
    
    try {
        $stmt = $pdo->prepare("
            UPDATE payment_subscriptions 
            SET status = 'cancelled',
                updated_at = NOW()
            WHERE paystack_subscription_id = ?
        ");
        $stmt->execute([$reference]);
        
        error_log("Subscription cancelled: {$reference}");
    } catch (Exception $e) {
        error_log("Error in subscription expired handler: " . $e->getMessage());
    }
}
