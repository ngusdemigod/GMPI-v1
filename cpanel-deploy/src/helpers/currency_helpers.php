<?php
/**
 * Currency Helper Functions
 * Bright Light Ministry Int'l Partners Portal
 * 
 * Global helper functions for currency formatting and conversion
 */

// Ensure CurrencyService is loaded
if (!class_exists('CurrencyService')) {
    require_once __DIR__ . '/../config/CurrencyService.php';
}

/**
 * Get the active currency from session
 * 
 * @return string Active currency code
 */
function getActiveCurrency() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['currency'] ?? CURRENCY_DEFAULT_BASE;
}

/**
 * Set the active currency in session
 * 
 * @param string $currency Currency code
 * @return bool Success
 */
function setActiveCurrency($currency) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $supported = CURRENCY_SUPPORTED;
    $currency = strtoupper($currency);
    
    if (isset($supported[$currency])) {
        $_SESSION['currency'] = $currency;
        return true;
    }
    
    return false;
}

/**
 * Convert amount from USD to active currency
 * 
 * @param float $amount Amount in USD
 * @param string|null $toCurrency Target currency (defaults to session currency)
 * @return float Converted amount
 */
function convertFromUSD($amount, $toCurrency = null) {
    $currencyService = new CurrencyService();
    $toCurrency = $toCurrency ?? getActiveCurrency();
    return $currencyService->convert($amount, 'USD', $toCurrency);
}

/**
 * Convert amount between any two currencies
 * 
 * @param float $amount Amount to convert
 * @param string $fromCurrency Source currency
 * @param string|null $toCurrency Target currency (defaults to session currency)
 * @return float Converted amount
 */
function convertCurrency($amount, $fromCurrency, $toCurrency = null) {
    $currencyService = new CurrencyService();
    $toCurrency = $toCurrency ?? getActiveCurrency();
    return $currencyService->convert($amount, $fromCurrency, $toCurrency);
}

/**
 * Format amount with active currency symbol
 * 
 * Stored amounts are treated as base-currency values from the backend.
 *
 * @param float $amount Amount to format (in base currency, NGN by default)
 * @param bool $converted Whether to convert to active currency
 * @param string|null $currency Override active currency
 * @return string Formatted amount
 */
function formatCurrency($amount, $converted = true, $currency = null) {
    $currencyService = new CurrencyService();
    $activeCurrency = $currency ?? getActiveCurrency();
    $baseCurrency = strtoupper((string) (defined('CURRENCY_DEFAULT_BASE') ? CURRENCY_DEFAULT_BASE : 'NGN')) ?: 'NGN';
    
    if ($converted) {
        if (strtoupper((string) $activeCurrency) !== $baseCurrency) {
            $amount = $currencyService->convert($amount, $baseCurrency, $activeCurrency);
        }
    }
    
    return $currencyService->formatAmount($amount, $activeCurrency);
}

/**
 * Get currency symbol for a currency code
 * 
 * @param string $currency Currency code
 * @return string Currency symbol
 */
function getCurrencySymbol($currency = null) {
    $currencyService = new CurrencyService();
    $currency = $currency ?? getActiveCurrency();
    return $currencyService->getSymbol($currency);
}

/**
 * Get the compact UI label for a currency code.
 *
 * @param string|null $currency Currency code
 * @return string Currency UI label
 */
function getCurrencyUiLabel($currency = null) {
    $currency = strtoupper(trim((string) ($currency ?? getActiveCurrency())));

    $labels = [
        'USD' => '$',
        'NGN' => 'N',
    ];

    return $labels[$currency] ?? $currency;
}

/**
 * Get currency name for a currency code
 * 
 * @param string $currency Currency code
 * @return string Currency name
 */
function getCurrencyName($currency = null) {
    $currencyService = new CurrencyService();
    $currency = $currency ?? getActiveCurrency();
    return $currencyService->getName($currency);
}

/**
 * Get exchange rate display string
 * 
 * @return string Exchange rate display (e.g., "$1 = ₦1,400")
 */
function getExchangeRateDisplay() {
    $currencyService = new CurrencyService();
    $activeCurrency = getActiveCurrency();
    $baseCurrency = strtoupper((string) (defined('CURRENCY_DEFAULT_BASE') ? CURRENCY_DEFAULT_BASE : 'NGN')) ?: 'NGN';
    
    if ($activeCurrency === $baseCurrency) {
        return '';
    }
    
    $rate = $currencyService->convert(1, $baseCurrency, $activeCurrency);
    $baseSymbol = $currencyService->getSymbol($baseCurrency);
    $targetSymbol = $currencyService->getSymbol($activeCurrency);
    
    return "{$baseSymbol}1 = {$targetSymbol}" . number_format($rate, 0);
}

/**
 * Get all supported currencies as options for select element
 * 
 * @param string|null $selected Currently selected currency
 * @return string HTML options
 */
function getCurrencyOptions($selected = null) {
    $currencyService = new CurrencyService();
    $supported = $currencyService->getSupportedCurrencies();
    $selected = $selected ?? getActiveCurrency();
    
    $options = '';
    foreach ($supported as $code => $info) {
        $isSelected = ($code === $selected) ? ' selected' : '';
        $displayLabel = htmlspecialchars(getCurrencyUiLabel($code), ENT_QUOTES, 'UTF-8');
        $options .= "<option value=\"{$code}\" data-display-label=\"{$displayLabel}\"{$isSelected}>{$displayLabel}</option>";
    }
    
    return $options;
}
