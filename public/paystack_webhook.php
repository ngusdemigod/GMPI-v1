<?php
/**
 * Beginner-friendly Paystack webhook example for local testing.
 *
 * This file is designed for:
 *   http://localhost:8000/paystack/webhook
 *
 * It keeps the logic simple so you can replace the sample transaction update
 * section with your real database code later.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../src/config/paystack_config.php';

/**
 * Send a JSON response and stop script execution.
 */
function respondJson(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

/**
 * Write a debug log line.
 *
 * For local development we log to a file under /public/logs.
 * In production you would usually send this to your app logger instead.
 */
function writeWebhookDebugLog(string $message): void
{
    $logDirectory = __DIR__ . '/logs';
    if (!is_dir($logDirectory)) {
        mkdir($logDirectory, 0775, true);
    }

    $logFile = $logDirectory . '/paystack-webhook.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, sprintf("[%s] %s\n", $timestamp, $message), FILE_APPEND);
}

// 1. Accept only POST requests.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respondJson(405, [
        'success' => false,
        'message' => 'Method not allowed. Paystack webhooks must use POST.',
    ]);
}

// 2. Read the raw JSON payload exactly as Paystack sent it.
$payload = file_get_contents('php://input');
if ($payload === false || $payload === '') {
    writeWebhookDebugLog('Empty webhook payload received.');
    respondJson(400, [
        'success' => false,
        'message' => 'Empty request body.',
    ]);
}

// Optional local debugging: log the raw payload safely.
writeWebhookDebugLog('Incoming payload: ' . $payload);

// 3. Read the Paystack signature header.
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
if ($signature === '') {
    writeWebhookDebugLog('Missing x-paystack-signature header.');
    respondJson(401, [
        'success' => false,
        'message' => 'Missing signature header.',
    ]);
}

// 4. Use your Paystack test secret key for local development.
$secretKey = PAYSTACK_SECRET_KEY;
if ($secretKey === '') {
    writeWebhookDebugLog('PAYSTACK_SECRET_KEY is missing.');
    respondJson(500, [
        'success' => false,
        'message' => 'Paystack secret key is not configured.',
    ]);
}

// 5. Verify the webhook signature.
$expectedSignature = hash_hmac('sha512', $payload, $secretKey);
if (!hash_equals($expectedSignature, $signature)) {
    writeWebhookDebugLog('Invalid signature. Webhook rejected.');
    respondJson(401, [
        'success' => false,
        'message' => 'Invalid signature.',
    ]);
}

// 6. Decode JSON safely.
$event = json_decode($payload, true);
if (!is_array($event)) {
    writeWebhookDebugLog('JSON decode failed: ' . json_last_error_msg());
    respondJson(400, [
        'success' => false,
        'message' => 'Invalid JSON payload.',
    ]);
}

$eventName = $event['event'] ?? '';
writeWebhookDebugLog('Verified webhook event: ' . $eventName);

// 7. Handle the webhook event you care about most.
if ($eventName === 'charge.success') {
    $reference = $event['data']['reference'] ?? '';

    if ($reference === '') {
        writeWebhookDebugLog('charge.success received without a transaction reference.');
        respondJson(400, [
            'success' => false,
            'message' => 'Missing transaction reference.',
        ]);
    }

    /**
     * Sample transaction update section.
     *
     * Replace this block with your real database code.
     *
     * Example pseudo-logic:
     * 1. Find your payment record by Paystack reference.
     * 2. If the record does not exist, log it and stop.
     * 3. If it exists and is already paid, do nothing.
     * 4. If it exists and is still pending, mark it as paid.
     * 5. Save a log entry for auditing/debugging.
     */

    // Example placeholder variables.
    $paymentRecordExists = true;
    $paymentAlreadyMarkedPaid = false;

    if (!$paymentRecordExists) {
        writeWebhookDebugLog('No local payment record found for reference: ' . $reference);
        respondJson(200, [
            'success' => true,
            'message' => 'Webhook received. No matching payment record found.',
        ]);
    }

    if ($paymentAlreadyMarkedPaid) {
        writeWebhookDebugLog('Payment already marked as paid for reference: ' . $reference);
        respondJson(200, [
            'success' => true,
            'message' => 'Webhook received. Payment was already processed.',
        ]);
    }

    // Replace these comments with your actual database update code.
    // Example:
    // $payment = findPaymentByReference($reference);
    // markPaymentAsPaid($payment['id']);
    // savePaymentLog($payment['id'], 'charge.success received from Paystack');

    writeWebhookDebugLog('Sample update completed for reference: ' . $reference);

    respondJson(200, [
        'success' => true,
        'message' => 'charge.success webhook processed successfully.',
        'reference' => $reference,
    ]);
}

// 8. Acknowledge other verified events even if you do not process them yet.
respondJson(200, [
    'success' => true,
    'message' => 'Webhook received and verified.',
    'event' => $eventName,
]);
