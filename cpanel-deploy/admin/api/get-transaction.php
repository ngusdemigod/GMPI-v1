<?php
/**
 * Get Transaction Details API
 * Church Financial Partnership System
 */

require_once __DIR__ . '/../../src/config/CurrencyService.php';

// Set JSON header first
header('Content-Type: application/json');

// Get transaction reference
$ref = $_GET['ref'] ?? '';

if (empty($ref)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid transaction reference']);
    exit;
}

// Get transaction data
try {
    require_once __DIR__ . '/../includes/config.php';

    $currencyService = new CurrencyService();
    $db = Database::getInstance();
    $transaction = $db->fetchOne(
        "SELECT t.*, u.first_name, u.last_name, u.email, u.phone, p.title as project_title,
                pm.card_type, pm.last_four_digits, COALESCE(pt.currency, 'NGN') as transaction_currency
         FROM transactions t
         INNER JOIN users u ON t.user_id = u.user_id
         LEFT JOIN projects p ON t.project_id = p.project_id
         LEFT JOIN payment_methods pm ON t.payment_method_id = pm.payment_method_id
         LEFT JOIN paystack_transactions pt ON pt.transaction_id = t.transaction_id
         WHERE t.transaction_reference = :ref",
        ['ref' => $ref]
    );

    if (!$transaction) {
        http_response_code(404);
        echo json_encode(['error' => 'Transaction not found']);
        exit;
    }

    $displayCurrency = strtoupper(trim((string) ($transaction['transaction_currency'] ?? 'NGN'))) ?: 'NGN';
    $transaction['formatted_amount'] = $currencyService->formatAmount((float) $transaction['amount'], $displayCurrency);
    $transaction['formatted_date'] = date('F d, Y', strtotime($transaction['transaction_date']));
    $transaction['formatted_time'] = date('g:i A', strtotime($transaction['transaction_date']));

    http_response_code(200);
    echo json_encode($transaction);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
