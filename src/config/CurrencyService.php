<?php
/**
 * Currency Service Class
 * Bright Light Ministry Int'l Partners Portal
 * 
 * Handles currency conversion using ExchangeRate-API
 * with file-based caching and fallback support
 */

require_once __DIR__ . '/currency_config.php';

class CurrencyService {
    
    private $cacheDir;
    private $apiKey;
    private $baseUrl;
    private $timeout;
    private $cacheDuration;
    private $fallbackRates;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->cacheDir = CURRENCY_CACHE_DIR;
        $this->apiKey = CURRENCY_API_KEY;
        $this->baseUrl = CURRENCY_API_URL;
        $this->timeout = CURRENCY_API_TIMEOUT;
        $this->cacheDuration = CURRENCY_CACHE_DURATION;
        $this->fallbackRates = CURRENCY_FALLBACK_RATES;
        
        // Ensure cache directory exists
        $this->ensureCacheDir();
    }
    
    /**
     * Ensure cache directory exists
     */
    private function ensureCacheDir() {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Get exchange rates for a base currency
     * Uses cache if available, otherwise fetches from API
     * 
     * @param string $baseCurrency Base currency code (e.g., 'USD')
     * @return array|false Exchange rates array or false on failure
     */
    public function getExchangeRates($baseCurrency = 'USD') {
        $baseCurrency = strtoupper($baseCurrency);
        
        // Check cache first
        $cached = $this->getFromCache($baseCurrency);
        if ($cached !== false) {
            return $cached;
        }
        
        // Fetch from API
        $rates = $this->fetchFromApi($baseCurrency);
        if ($rates !== false) {
            // Save to cache
            $this->saveToCache($baseCurrency, $rates);
            return $rates;
        }
        
        // Return fallback rates
        error_log("CurrencyService: API failed, using fallback rates for {$baseCurrency}");
        return $this->fallbackRates;
    }
    
    /**
     * Fetch rates from ExchangeRate-API
     * 
     * @param string $baseCurrency Base currency code
     * @return array|false Exchange rates or false on failure
     */
    private function fetchFromApi($baseCurrency) {
        $url = $this->baseUrl . $baseCurrency;
        
        for ($attempt = 0; $attempt < CURRENCY_API_RETRIES; $attempt++) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'BrightLightMinistry/1.0',
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                error_log("CurrencyService: cURL error on attempt " . ($attempt + 1) . ": {$error}");
                continue;
            }
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                if ($data && isset($data['result']) && $data['result'] === 'success') {
                    return $data['conversion_rates'];
                }
            }
            
            if ($httpCode === 429) {
                error_log("CurrencyService: Rate limit exceeded");
                break;
            }
            
            if ($httpCode === 401) {
                error_log("CurrencyService: Invalid API key");
                break;
            }
            
            error_log("CurrencyService: API returned HTTP {$httpCode} on attempt " . ($attempt + 1));
        }
        
        return false;
    }
    
    /**
     * Convert an amount from one currency to another
     * 
     * @param float $amount Amount to convert
     * @param string $fromCurrency Source currency code
     * @param string $toCurrency Target currency code
     * @return float Converted amount
     */
    public function convert($amount, $fromCurrency, $toCurrency) {
        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency);
        
        if ($fromCurrency === $toCurrency) {
            return (float) $amount;
        }
        
        $rates = $this->getExchangeRates($fromCurrency);
        
        if ($rates && isset($rates[$toCurrency])) {
            return (float) $amount * $rates[$toCurrency];
        }
        
        // Fallback: convert through USD if direct rate not available
        if ($fromCurrency !== 'USD' && $toCurrency !== 'USD') {
            $fromRates = $this->getExchangeRates($fromCurrency);
            $toRates = $this->getExchangeRates('USD');
            
            if (isset($fromRates['USD']) && isset($toRates[$toCurrency])) {
                $amountInUsd = $amount * $fromRates['USD'];
                return $amountInUsd * $toRates[$toCurrency];
            }
        }
        
        // Last resort: use fallback rates
        return $this->convertWithFallback($amount, $fromCurrency, $toCurrency);
    }
    
    /**
     * Convert using fallback rates
     * 
     * @param float $amount Amount to convert
     * @param string $fromCurrency Source currency
     * @param string $toCurrency Target currency
     * @return float Converted amount
     */
    private function convertWithFallback($amount, $fromCurrency, $toCurrency) {
        if (!isset($this->fallbackRates[$fromCurrency]) || !isset($this->fallbackRates[$toCurrency])) {
            return (float) $amount;
        }
        
        $fromRate = $this->fallbackRates[$fromCurrency];
        $toRate = $this->fallbackRates[$toCurrency];
        
        // Convert to USD first, then to target currency
        $amountInUsd = $amount / $fromRate;
        return $amountInUsd * $toRate;
    }
    
    /**
     * Get currency symbol
     * 
     * @param string $currency Currency code
     * @return string Currency symbol
     */
    public function getSymbol($currency) {
        $currency = strtoupper($currency);
        $supported = CURRENCY_SUPPORTED;
        return isset($supported[$currency]) ? $supported[$currency]['symbol'] : $currency;
    }
    
    /**
     * Get currency name
     * 
     * @param string $currency Currency code
     * @return string Currency name
     */
    public function getName($currency) {
        $currency = strtoupper($currency);
        $supported = CURRENCY_SUPPORTED;
        return isset($supported[$currency]) ? $supported[$currency]['name'] : $currency;
    }
    
    /**
     * Get currency flag emoji
     * 
     * @param string $currency Currency code
     * @return string Flag emoji
     */
    public function getFlag($currency) {
        $currency = strtoupper($currency);
        $supported = CURRENCY_SUPPORTED;
        return isset($supported[$currency]) ? $supported[$currency]['flag'] : '';
    }
    
    /**
     * Get supported currencies
     * 
     * @return array Supported currencies
     */
    public function getSupportedCurrencies() {
        return CURRENCY_SUPPORTED;
    }
    
    /**
     * Format amount with currency symbol
     * 
     * @param float $amount Amount to format
     * @param string $currency Currency code
     * @param bool $showSymbol Whether to show currency symbol
     * @return string Formatted amount
     */
    public function formatAmount($amount, $currency = 'USD', $showSymbol = true) {
        $currency = strtoupper($currency);
        $symbol = $this->getSymbol($currency);
        $decimalPlaces = abs($amount) >= 1000 ? CURRENCY_DECIMAL_PLACES_LARGE : CURRENCY_DECIMAL_PLACES;
        
        $formatted = number_format($amount, $decimalPlaces);
        
        if ($showSymbol) {
            return $symbol . $formatted;
        }
        
        return $formatted . ' ' . $currency;
    }
    
    /**
     * Get rates from cache
     * 
     * @param string $baseCurrency Base currency code
     * @return array|false Cached rates or false
     */
    private function getFromCache($baseCurrency) {
        $cacheFile = $this->getCacheFilePath($baseCurrency);
        
        if (!file_exists($cacheFile)) {
            return false;
        }
        
        $cacheTime = filemtime($cacheFile);
        $age = time() - $cacheTime;
        
        if ($age > $this->cacheDuration) {
            // Cache expired
            return false;
        }
        
        $content = file_get_contents($cacheFile);
        if ($content === false) {
            return false;
        }
        
        $data = json_decode($content, true);
        if (!$data || !isset($data['rates'])) {
            return false;
        }
        
        return $data['rates'];
    }
    
    /**
     * Save rates to cache
     * 
     * @param string $baseCurrency Base currency code
     * @param array $rates Exchange rates
     * @return bool Success
     */
    private function saveToCache($baseCurrency, $rates) {
        $cacheFile = $this->getCacheFilePath($baseCurrency);
        
        $data = [
            'base' => $baseCurrency,
            'rates' => $rates,
            'timestamp' => time(),
        ];
        
        $result = file_put_contents($cacheFile, json_encode($data), LOCK_EX);
        
        if ($result === false) {
            error_log("CurrencyService: Failed to save cache for {$baseCurrency}");
            return false;
        }
        
        return true;
    }
    
    /**
     * Get cache file path
     * 
     * @param string $baseCurrency Base currency code
     * @return string Cache file path
     */
    private function getCacheFilePath($baseCurrency) {
        $date = date('Y-m-d');
        return $this->cacheDir . "rates_{$baseCurrency}_{$date}.json";
    }
    
    /**
     * Clear all cached rates
     * 
     * @return bool Success
     */
    public function clearCache() {
        if (!is_dir($this->cacheDir)) {
            return true;
        }
        
        $files = glob($this->cacheDir . '*.json');
        foreach ($files as $file) {
            unlink($file);
        }
        
        return true;
    }
    
    /**
     * Get last cache update time
     * 
     * @param string $baseCurrency Base currency code
     * @return string|false Last update time or false
     */
    public function getLastUpdated($baseCurrency = 'USD') {
        $baseCurrency = strtoupper($baseCurrency);
        $cacheFile = $this->getCacheFilePath($baseCurrency);
        
        if (!file_exists($cacheFile)) {
            return false;
        }
        
        return date('Y-m-d H:i:s', filemtime($cacheFile));
    }
    
    /**
     * Check if API is available
     * 
     * @return bool API availability
     */
    public function isApiAvailable() {
        $ch = curl_init($this->baseUrl . 'USD');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_NOBODY => true,
        ]);
        
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    }
}