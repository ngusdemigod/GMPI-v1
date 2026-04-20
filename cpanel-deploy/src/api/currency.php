<?php
/**
 * Currency API Endpoint
 * Bright Light Ministry Int'l Partners Portal
 * 
 * Handles AJAX requests for currency conversion and preference setting
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json');

// Load required files
require_once __DIR__ . '/../config/CurrencyService.php';
require_once __DIR__ . '/../helpers/currency_helpers.php';

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $currencyService = new CurrencyService();
    
    switch ($action) {
        case 'set_currency':
            // Set user's currency preference
            $currency = strtoupper($_POST['currency'] ?? '');
            
            if (setActiveCurrency($currency)) {
                echo json_encode([
                    'success' => true,
                    'currency' => $currency,
                    'symbol' => $currencyService->getSymbol($currency),
                    'name' => $currencyService->getName($currency),
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid currency code',
                ]);
            }
            break;
            
        case 'convert':
            // Convert amount between currencies
            $amount = floatval($_POST['amount'] ?? $_GET['amount'] ?? 0);
            $from = strtoupper($_POST['from'] ?? $_GET['from'] ?? 'USD');
            $to = strtoupper($_POST['to'] ?? $_GET['to'] ?? getActiveCurrency());
            
            $converted = $currencyService->convert($amount, $from, $to);
            
            echo json_encode([
                'success' => true,
                'amount' => $amount,
                'from' => $from,
                'to' => $to,
                'converted' => round($converted, 2),
                'symbol' => $currencyService->getSymbol($to),
                'formatted' => $currencyService->formatAmount($converted, $to),
            ]);
            break;
            
        case 'rates':
            // Get current exchange rates
            $base = strtoupper($_GET['base'] ?? 'USD');
            $rates = $currencyService->getExchangeRates($base);
            
            echo json_encode([
                'success' => true,
                'base' => $base,
                'rates' => $rates,
                'last_updated' => $currencyService->getLastUpdated($base) ?? 'Unknown',
            ]);
            break;
            
        case 'supported':
            // Get supported currencies
            $supported = $currencyService->getSupportedCurrencies();
            
            echo json_encode([
                'success' => true,
                'currencies' => $supported,
                'active' => getActiveCurrency(),
            ]);
            break;
            
        case 'status':
            // Check API status
            $apiAvailable = $currencyService->isApiAvailable();
            
            echo json_encode([
                'success' => true,
                'api_available' => $apiAvailable,
                'cache_method' => CURRENCY_CACHE_METHOD,
                'cache_duration' => CURRENCY_CACHE_DURATION,
                'active_currency' => getActiveCurrency(),
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action. Supported actions: set_currency, convert, rates, supported, status',
            ]);
            break;
    }
    
} catch (Exception $e) {
    error_log("Currency API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
    ]);
}