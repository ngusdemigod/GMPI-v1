<?php
/**
 * API Endpoint: Get Project Transactions
 * Returns all transactions for a given project
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';

// Require authentication
requireAdmin();

$projectId = intval($_GET['project_id'] ?? 0);

if ($projectId <= 0) {
    echo json_encode(['error' => 'Invalid project ID']);
    exit;
}

$db = Database::getInstance();

// Verify project exists
$project = $db->fetchOne("SELECT title FROM projects WHERE project_id = :id", ['id' => $projectId]);
if (!$project) {
    echo json_encode(['error' => 'Project not found']);
    exit;
}

// Get transactions for this project
$transactions = $db->fetchAll(
    "SELECT 
        t.transaction_id,
        t.amount,
        t.status,
        t.transaction_reference,
        t.transaction_date,
        t.category,
        COALESCE(pt.currency, 'NGN') as currency,
        u.first_name,
        u.last_name,
        u.email
     FROM transactions t
     LEFT JOIN users u ON t.user_id = u.user_id
     LEFT JOIN paystack_transactions pt ON pt.transaction_id = t.transaction_id
     WHERE t.project_id = :project_id
     ORDER BY t.transaction_date DESC",
    ['project_id' => $projectId]
);

// Format transactions
$formattedTransactions = [];
$totalAmount = 0;
$primaryCurrency = 'NGN';

foreach ($transactions as $txn) {
    $userName = trim(($txn['first_name'] ?? '') . ' ' . ($txn['last_name'] ?? '')) ?: ($txn['email'] ?? 'Anonymous');
    $formattedTransactions[] = [
        'transaction_id' => $txn['transaction_id'],
        'amount' => floatval($txn['amount']),
        'status' => $txn['status'],
        'transaction_reference' => $txn['transaction_reference'],
        'transaction_date' => $txn['transaction_date'],
        'category' => $txn['category'],
        'currency' => $txn['currency'] ?? 'NGN',
        'user_name' => $userName,
    ];
    $totalAmount += floatval($txn['amount']);
    if (empty($primaryCurrency) || $primaryCurrency === 'NGN') {
        $primaryCurrency = $txn['currency'] ?? 'NGN';
    }
}

echo json_encode([
    'transactions' => $formattedTransactions,
    'total_amount' => $totalAmount,
    'total_currency' => $primaryCurrency,
    'project_title' => $project['title'],
]);