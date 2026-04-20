<?php
/**
 * Currency Switch API
 * Updates user currency preference in session and database
 */

require_once __DIR__ . '/../../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$currency = $input['currency'] ?? null;

if (!$currency) {
    echo json_encode(['success' => false, 'error' => 'Currency not specified']);
    exit;
}

// Validate currency
$currencyService = new CurrencyService();
if (!array_key_exists($currency, $currencyService->getCurrencies())) {
    echo json_encode(['success' => false, 'error' => 'Invalid currency']);
    exit;
}

// Update session
$_SESSION['currency'] = $currency;

// Update database if logged in
if (isset($_SESSION['user_id'])) {
    try {
        $db = getDbConnection();
        $stmt = $db->prepare("UPDATE users SET preferred_currency = ? WHERE id = ?");
        $stmt->execute([$currency, $_SESSION['user_id']]);
    } catch (Exception $e) {
        // Log error but continue (session update is enough for immediate reflect)
        error_log("Failed to update user currency in DB: " . $e->getMessage());
    }
}

echo json_encode(['success' => true]);
