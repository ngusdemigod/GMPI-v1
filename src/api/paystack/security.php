<?php
/**
 * Paystack Security Helpers
 * Bright Light Ministry Int'l Partnership Portal
 * 
 * Security functions for Paystack integration including webhook signature validation
 */

// Load environment variables from .env file
require_once __DIR__ . '/../../.env.php';

/**
 * Validate Paystack webhook signature
 * Uses HMAC SHA512 to verify the webhook is from Paystack
 * 
 * @param string $rawData The raw POST data received
 * @param string $signature The X-Paystack-Signature header value
 * @param string $secretKey The Paystack secret key
 * @return bool True if signature is valid, false otherwise
 */
function validateWebhookSignature($rawData, $signature, $secretKey) {
    if (empty($signature) || empty($secretKey)) {
        error_log('Paystack webhook: Missing signature or secret key');
        return false;
    }
    
    // Paystack uses HMAC SHA512 for webhook signatures
    $expectedSignature = hash_hmac('sha512', $rawData, $secretKey);
    
    // Use hash_equals to prevent timing attacks
    return hash_equals($expectedSignature, $signature);
}

/**
 * Generate a unique transaction reference
 * Format: REF-YYYYMMDD-XXXXXXXX
 * 
 * @return string Unique reference string
 */
function generateReference() {
    return 'REF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -8));
}

/**
 * Convert USD amount to kobo (Paystack expects amount in kobo/cents)
 * 
 * @param float $amount Amount in USD
 * @param string $currency Currency code (USD or NGN)
 * @return int Amount in kobo/cents
 */
function convertToKobo($amount, $currency = 'USD') {
    // Paystack expects amount in the smallest currency unit
    // For USD: multiply by 100 (cents)
    // For NGN: multiply by 100 (kobo)
    return intval(floatval($amount) * 100);
}

/**
 * Convert kobo back to main currency unit
 * 
 * @param int $kobo Amount in kobo/cents
 * @param string $currency Currency code (USD or NGN)
 * @return float Amount in main currency unit
 */
function convertFromKobo($kobo, $currency = 'USD') {
    return floatval($kobo) / 100;
}

/**
 * Sanitize and validate email address
 * 
 * @param string $email Email address to validate
 * @return bool True if valid, false otherwise
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Sanitize amount to ensure it's a valid positive number
 * 
 * @param mixed $amount Amount to sanitize
 * @return array Sanitized amount with validation result
 */
function sanitizeAmount($amount) {
    $amount = floatval($amount);
    
    if ($amount <= 0) {
        return ['valid' => false, 'amount' => 0, 'error' => 'Invalid amount'];
    }
    
    return ['valid' => true, 'amount' => $amount, 'error' => null];
}
