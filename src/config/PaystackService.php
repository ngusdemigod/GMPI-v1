<?php
/**
 * Paystack Payment Service
 * Bright Light Ministry Int'l Partners Portal
 * 
 * Core service for handling Paystack payment operations
 * Uses cURL for API calls (no external library dependency)
 */

// Load environment variables from .env file
require_once __DIR__ . '/../.env.php';

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/paystack_config.php';

class PaystackService {
    private $db;
    private $secretKey;
    private $publicKey;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->secretKey = PAYSTACK_SECRET_KEY;
        $this->publicKey = PAYSTACK_PUBLIC_KEY;
        
        // Validate that secret key is configured
        if (empty($this->secretKey)) {
            error_log("CRITICAL: Paystack secret key is not configured. Check .env file.");
            throw new Exception("Paystack secret key is not configured. Please set PAYSTACK_SECRET_KEY in your .env file.");
        }
    }
    
    /**
     * Initialize a one-time transaction
     * 
     * @param array $data Transaction data
     * @return array Response with authorization URL or error
     */
    public function initializeTransaction($data) {
        try {
            // Validate required fields
            $requiredFields = ['user_id', 'email', 'amount', 'currency'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return ['success' => false, 'error' => "Missing required field: {$field}"];
                }
            }
            
            // Validate amount
            $amount = intval($data['amount']);
            if ($amount < PAYSTACK_MIN_AMOUNT || $amount > PAYSTACK_MAX_AMOUNT) {
                return ['success' => false, 'error' => 'Amount out of range'];
            }
            
            // Generate unique reference
            $reference = 'PAY-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -8));
            
            // Prepare metadata
            $metadata = [
                'structure' => 'transaction',
                'data' => [
                    'category' => $data['category'] ?? 'General',
                    'frequency' => $data['frequency'] ?? 'One-time',
                    'project_id' => $data['project_id'] ?? null,
                    'user_id' => $data['user_id'] ?? null,
                    'notes' => $data['notes'] ?? ''
                ]
            ];
            
            // Create record in main transactions table first (status: pending)
            // This satisfies the NOT NULL constraint on transaction_id in paystack_transactions
            $this->db->execute(
                "INSERT INTO transactions 
                (user_id, project_id, amount, category, frequency, transaction_reference, status, transaction_date, created_at)
                VALUES (:user_id, :project_id, :amount, :category, :frequency, :transaction_reference, 'pending', NOW(), NOW())",
                [
                    'user_id' => $data['user_id'] ?? null,
                    'project_id' => $data['project_id'] ?? null,
                    'amount' => $amount / 100, // Store in main unit
                    'category' => $data['category'] ?? 'General',
                    'frequency' => 'One-time',
                    'transaction_reference' => $reference
                ]
            );
            
            $transactionId = $this->db->lastInsertId();
            
            // Prepare API request
            $apiUrl = PAYSTACK_API_URL . '/transaction/initialize';
            $postData = [
                'email' => $data['email'],
                'amount' => $amount,
                'currency' => $data['currency'] ?? PAYSTACK_DEFAULT_CURRENCY,
                'reference' => $reference,
                'metadata' => json_encode($metadata),
                'callback_url' => $data['callback_url'] ?? ''
            ];
            
            // Make API call
            $response = $this->makeApiCall('POST', $apiUrl, $postData);
            
            if (!isset($response['status']) || !$response['status']) {
                $error = $response['message'] ?? 'Transaction initialization failed';
                // Update transaction status to failed
                $this->db->execute(
                    "UPDATE transactions SET status = 'failed' WHERE transaction_id = :transaction_id",
                    ['transaction_id' => $transactionId]
                );
                return ['success' => false, 'error' => $error];
            }
            
            // Store transaction record in paystack_transactions linking to the main transaction
            $this->storeTransactionRecord([
                'transaction_id' => $transactionId,
                'paystack_reference' => $reference,
                'status' => 'pending',
                'amount' => $amount,
                'currency' => $data['currency'] ?? PAYSTACK_DEFAULT_CURRENCY,
                'email' => $data['email'],
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'metadata' => json_encode($metadata)
            ]);
            
            return [
                'success' => true,
                'data' => [
                    'authorization_url' => $response['data']['authorization_url'],
                    'reference' => $reference,
                    'amount' => $amount
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Paystack initializeTransaction error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Transaction initialization failed'];
        }
    }
    
    /**
     * Initialize a subscription (recurring payment)
     * Uses Paystack's transaction initialize with plan parameter
     * 
     * @param array $data Subscription data
     * @return array Response with authorization URL or error
     */
    public function initializeSubscription($data) {
        try {
            // Validate required fields
            $requiredFields = ['user_id', 'email', 'amount', 'currency', 'frequency'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return ['success' => false, 'error' => "Missing required field: {$field}"];
                }
            }
            
            // Validate frequency
            $validFrequencies = ['weekly', 'monthly', 'annually'];
            if (!in_array($data['frequency'], $validFrequencies)) {
                return ['success' => false, 'error' => 'Invalid frequency'];
            }
            
            // Generate unique reference
            $reference = 'SUB-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -8));
            $transactionFrequency = $this->normalizeTransactionFrequency($data['frequency']);
            
            // Prepare metadata
            $metadata = [
                'structure' => 'subscription',
                'data' => [
                    'category' => $data['category'] ?? 'General',
                    'frequency' => $data['frequency'],
                    'project_id' => $data['project_id'] ?? null,
                    'user_id' => $data['user_id'] ?? null,
                    'notes' => $data['notes'] ?? ''
                ]
            ];

            // Create record in main transactions table first (status: pending)
            $this->db->execute(
                "INSERT INTO transactions 
                (user_id, project_id, amount, category, frequency, transaction_reference, status, transaction_date, created_at)
                VALUES (:user_id, :project_id, :amount, :category, :frequency, :transaction_reference, 'pending', NOW(), NOW())",
                [
                    'user_id' => $data['user_id'] ?? null,
                    'project_id' => $data['project_id'] ?? null,
                    'amount' => intval($data['amount']) / 100,
                    'category' => $data['category'] ?? 'General',
                    'frequency' => $transactionFrequency,
                    'transaction_reference' => $reference
                ]
            );
            
            $transactionId = $this->db->lastInsertId();
            
            $apiUrl = PAYSTACK_API_URL . '/transaction/initialize';
            $postData = [
                'email' => $data['email'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? PAYSTACK_DEFAULT_CURRENCY,
                'reference' => $reference,
                'metadata' => json_encode($metadata),
                'callback_url' => $data['callback_url'] ?? ''
            ];
            
            // Make API call
            $response = $this->makeApiCall('POST', $apiUrl, $postData);
            
            if (!isset($response['status']) || !$response['status']) {
                $error = $response['message'] ?? 'Subscription initialization failed';
                // Update transaction status to failed
                $this->db->execute(
                    "UPDATE transactions SET status = 'failed' WHERE transaction_id = :transaction_id",
                    ['transaction_id' => $transactionId]
                );
                return ['success' => false, 'error' => $error];
            }
            
            // Store subscription record
            $this->storeSubscriptionRecord([
                'user_id' => $data['user_id'] ?? null,
                'transaction_id' => $transactionId,
                'paystack_subscription_id' => $reference,
                'status' => 'pending',
                'frequency' => $data['frequency'],
                'amount' => intval($data['amount']) / 100, // Store in main unit
                'currency' => $data['currency'] ?? PAYSTACK_DEFAULT_CURRENCY,
                'next_billing_date' => $this->calculateNextBillingDate($data['frequency'])
            ]);
            
            return [
                'success' => true,
                'data' => [
                    'authorization_url' => $response['data']['authorization_url'],
                    'reference' => $reference,
                    'amount' => $data['amount']
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Paystack initializeSubscription error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Subscription initialization failed: ' . $e->getMessage()];
        }
    }

    /**
     * Normalize frequency values to match the transactions table ENUM.
     */
    private function normalizeTransactionFrequency($frequency) {
        $map = [
            'one-time' => 'One-time',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'annually' => 'Annually',
        ];

        $key = strtolower((string) $frequency);
        return $map[$key] ?? 'One-time';
    }
    
    /**
     * Verify a transaction
     * 
     * @param string $reference Transaction reference
     * @return array Verification result
     */
    public function verifyTransaction($reference) {
        try {
            $apiUrl = PAYSTACK_API_URL . '/transaction/verify/' . $reference;
            
            // Make API call
            $response = $this->makeApiCall('GET', $apiUrl, []);
            
            if (!isset($response['status']) || !$response['status']) {
                return ['success' => false, 'error' => 'Transaction verification failed'];
            }
            
            $data = $response['data'];
            
            // Update transaction record
            $this->updateTransactionRecord($reference, [
                'status' => $data['status'],
                'email' => $data['email'],
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? ''
            ]);
            
            // If transaction is completed, create transaction record in main table
            if ($data['status'] === 'success') {
                $this->createTransactionFromPaystack($data);
            }
            
            return [
                'success' => true,
                'status' => $data['status'],
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'reference' => $data['reference'],
                'paid_at' => $data['paid_at'] ?? null
            ];
            
        } catch (Exception $e) {
            error_log("Paystack verifyTransaction error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Transaction verification failed'];
        }
    }
    
    /**
     * Verify a subscription
     * 
     * @param string $subscriptionId Paystack subscription ID
     * @return array Verification result
     */
    public function verifySubscription($subscriptionId) {
        try {
            $apiUrl = PAYSTACK_API_URL . '/subscription/' . $subscriptionId;
            
            // Make API call
            $response = $this->makeApiCall('GET', $apiUrl, []);
            
            if (!isset($response['status']) || !$response['status']) {
                return ['success' => false, 'error' => 'Subscription verification failed'];
            }
            
            $data = $response['data'];
            
            // Update subscription record
            $this->updateSubscriptionRecord($subscriptionId, [
                'status' => $data['status'],
                'next_billing_date' => $data['next_billing_date'] ?? null
            ]);
            
            return [
                'success' => true,
                'status' => $data['status'],
                'email' => $data['customer']['email'] ?? '',
                'amount' => $data['amount'],
                'frequency' => $data['frequency'],
                'next_billing_date' => $data['next_billing_date'] ?? null
            ];
            
        } catch (Exception $e) {
            error_log("Paystack verifySubscription error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Subscription verification failed'];
        }
    }
    
    /**
     * Handle webhook event from Paystack
     * 
     * @param array $webhookData Webhook payload
     * @return array Processing result
     */
    public function handleWebhook($webhookData) {
        try {
            // Log webhook event
            $this->logWebhookEvent($webhookData);
            
            $eventType = $webhookData['event'] ?? '';
            $data = $webhookData['data'] ?? [];
            
            switch ($eventType) {
                case 'charge.success':
                    return $this->handleChargeSuccess($data);
                    
                case 'charge.failed':
                    return $this->handleChargeFailed($data);
                    
                case 'subscription.active':
                    return $this->handleSubscriptionActive($data);
                    
                case 'subscription.paused':
                    return $this->handleSubscriptionPaused($data);
                    
                case 'subscription.cancelled':
                    return $this->handleSubscriptionCancelled($data);
                    
                case 'subscription.expired':
                    return $this->handleSubscriptionExpired($data);
                    
                default:
                    return ['success' => true, 'message' => 'Event handled'];
            }
            
        } catch (Exception $e) {
            error_log("Paystack handleWebhook error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Webhook processing failed'];
        }
    }
    
    /**
     * Make API call to Paystack
     * 
     * @param string $method HTTP method
     * @param string $url API URL
     * @param array $postData POST data
     * @return array API response
     */
    private function makeApiCall($method, $url, $postData) {
        // Validate secret key before making API call
        if (empty($this->secretKey)) {
            error_log("ERROR: Attempting to make Paystack API call without secret key");
            throw new Exception("Paystack secret key is not configured");
        }
        
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/json'
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        } elseif ($method === 'GET') {
            if (!empty($postData)) {
                $url .= '?' . http_build_query($postData);
                curl_setopt($ch, CURLOPT_URL, $url);
            }
        }
        
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL error: {$error}");
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 400) {
            throw new Exception("Paystack API error: " . ($decoded['message'] ?? 'Unknown error'));
        }
        
        return $decoded;
    }
    
    /**
     * Store transaction record in paystack_transactions table
     */
    private function storeTransactionRecord($data) {
        try {
            $this->db->execute(
                "INSERT INTO paystack_transactions 
                (transaction_id, paystack_reference, status, amount, currency, email, first_name, last_name, metadata, created_at)
                VALUES (:transaction_id, :paystack_reference, :status, :amount, :currency, :email, :first_name, :last_name, :metadata, NOW())",
                $data
            );
        } catch (Exception $e) {
            error_log("Store transaction record error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update transaction record
     */
    private function updateTransactionRecord($reference, $data) {
        try {
            $this->db->execute(
                "UPDATE paystack_transactions 
                SET status = :status, email = :email, first_name = :first_name, last_name = :last_name, updated_at = NOW()
                WHERE paystack_reference = :paystack_reference",
                array_merge($data, ['paystack_reference' => $reference])
            );
        } catch (Exception $e) {
            error_log("Update transaction record error: " . $e->getMessage());
        }
    }
    
    /**
     * Store subscription record
     */
    private function storeSubscriptionRecord($data) {
        try {
            // Build the INSERT statement dynamically based on provided data
            $columns = [];
            $values = [];
            $placeholders = [];
            
            // Only include columns that have non-null values
            if (isset($data['user_id'])) {
                $columns[] = 'user_id';
                $placeholders[] = ':user_id';
                $values['user_id'] = $data['user_id'];
            }
            if (isset($data['transaction_id'])) {
                $columns[] = 'transaction_id';
                $placeholders[] = ':transaction_id';
                $values['transaction_id'] = $data['transaction_id'];
            }
            if (isset($data['paystack_subscription_id'])) {
                $columns[] = 'paystack_subscription_id';
                $placeholders[] = ':paystack_subscription_id';
                $values['paystack_subscription_id'] = $data['paystack_subscription_id'];
            }
            if (isset($data['status'])) {
                $columns[] = 'status';
                $placeholders[] = ':status';
                $values['status'] = $data['status'];
            }
            if (isset($data['frequency'])) {
                $columns[] = 'frequency';
                $placeholders[] = ':frequency';
                $values['frequency'] = $data['frequency'];
            }
            if (isset($data['amount'])) {
                $columns[] = 'amount';
                $placeholders[] = ':amount';
                $values['amount'] = $data['amount'];
            }
            if (isset($data['currency'])) {
                $columns[] = 'currency';
                $placeholders[] = ':currency';
                $values['currency'] = $data['currency'];
            }
            if (isset($data['next_billing_date'])) {
                $columns[] = 'next_billing_date';
                $placeholders[] = ':next_billing_date';
                $values['next_billing_date'] = $data['next_billing_date'];
            }
            if (isset($data['email'])) {
                $columns[] = 'email';
                $placeholders[] = ':email';
                $values['email'] = $data['email'];
            }
            if (isset($data['first_name'])) {
                $columns[] = 'first_name';
                $placeholders[] = ':first_name';
                $values['first_name'] = $data['first_name'];
            }
            if (isset($data['last_name'])) {
                $columns[] = 'last_name';
                $placeholders[] = ':last_name';
                $values['last_name'] = $data['last_name'];
            }
            
            $columns[] = 'created_at';
            $placeholders[] = 'NOW()';
            
            $sql = "INSERT INTO payment_subscriptions (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            
            $this->db->execute($sql, $values);
        } catch (Exception $e) {
            error_log("Store subscription record error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update subscription record
     */
    private function updateSubscriptionRecord($subscriptionId, $data) {
        try {
            $this->db->execute(
                "UPDATE payment_subscriptions 
                SET status = :status, next_billing_date = :next_billing_date, updated_at = NOW()
                WHERE paystack_subscription_id = :paystack_subscription_id",
                array_merge($data, ['paystack_subscription_id' => $subscriptionId])
            );
        } catch (Exception $e) {
            error_log("Update subscription record error: " . $e->getMessage());
        }
    }
    
    /**
     * Create transaction from Paystack data
     */
    private function createTransactionFromPaystack($data) {
        try {
            $reference = $data['reference'];
            
            // Get the existing transaction_id from our local paystack_transactions table
            $pt = $this->db->fetchOne(
                "SELECT transaction_id FROM paystack_transactions WHERE paystack_reference = :reference",
                ['reference' => $reference]
            );
            
            if (!$pt || empty($pt['transaction_id'])) {
                // Fallback: This might be a direct Paystack transaction not initialized via our app
                // Or initialized before we added pre-creation logic
                $transactionReference = 'TXN-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $metadata = json_decode($data['metadata'] ?? '{}', true);
                $metadataData = $metadata['data'] ?? [];
                
                $this->db->execute(
                    "INSERT INTO transactions 
                    (user_id, project_id, amount, category, frequency, transaction_reference, status, transaction_date, processed_at)
                    VALUES (:user_id, :project_id, :amount, :category, :frequency, :transaction_reference, 'completed', NOW(), NOW())",
                    [
                        'user_id' => $metadataData['user_id'] ?? null,
                        'project_id' => $metadataData['project_id'] ?? null,
                        'amount' => $data['amount'] / 100,
                        'category' => $metadataData['category'] ?? 'General',
                        'frequency' => $this->normalizeTransactionFrequency($metadataData['frequency'] ?? 'One-time'),
                        'transaction_reference' => $transactionReference
                    ]
                );
                $transactionId = $this->db->lastInsertId();
                
                // Update paystack_transactions if it exists but missing transaction_id
                $this->db->execute(
                    "UPDATE paystack_transactions SET transaction_id = :transaction_id WHERE paystack_reference = :reference",
                    ['transaction_id' => $transactionId, 'reference' => $reference]
                );
            } else {
                $transactionId = $pt['transaction_id'];
                // Update the existing transaction status
                $this->db->execute(
                    "UPDATE transactions SET status = 'completed', processed_at = NOW(), transaction_date = NOW() WHERE transaction_id = :transaction_id",
                    ['transaction_id' => $transactionId]
                );
            }
            
            return ['success' => true, 'transaction_id' => $transactionId];
            
        } catch (Exception $e) {
            error_log("Create/Update transaction from Paystack error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Transaction update failed'];
        }
    }
    
    /**
     * Log webhook event
     */
    private function logWebhookEvent($data) {
        try {
            $this->db->execute(
                "INSERT INTO paystack_webhooks 
                (paystack_event_id, event_type, payload, processed, created_at)
                VALUES (:event_id, :event_type, :payload, FALSE, NOW())",
                [
                    'event_id' => $data['id'] ?? uniqid(),
                    'event_type' => $data['event'] ?? 'unknown',
                    'payload' => json_encode($data)
                ]
            );
        } catch (Exception $e) {
            error_log("Log webhook event error: " . $e->getMessage());
        }
    }
    
    /**
     * Handle charge.success event
     */
    private function handleChargeSuccess($data) {
        $reference = $data['reference'];
        
        // Update transaction status with all required named parameters
        $this->updateTransactionRecord($reference, [
            'status'     => 'completed',
            'email'      => $data['customer']['email'] ?? ($data['email'] ?? ''),
            'first_name' => $data['customer']['first_name'] ?? '',
            'last_name'  => $data['customer']['last_name'] ?? ''
        ]);
        
        // Create transaction record
        $this->createTransactionFromPaystack($data);
        
        return ['success' => true, 'message' => 'Charge success processed'];
    }
    
    /**
     * Handle charge.failed event
     */
    private function handleChargeFailed($data) {
        $reference = $data['reference'];
        
        // Update transaction status with all required named parameters
        $this->updateTransactionRecord($reference, [
            'status'     => 'failed',
            'email'      => $data['customer']['email'] ?? ($data['email'] ?? ''),
            'first_name' => $data['customer']['first_name'] ?? '',
            'last_name'  => $data['customer']['last_name'] ?? ''
        ]);
        
        return ['success' => true, 'message' => 'Charge failed processed'];
    }
    
    /**
     * Handle subscription.active event
     */
    private function handleSubscriptionActive($data) {
        $subscriptionId = $data['id'];
        
        // Update subscription status
        $this->updateSubscriptionRecord($subscriptionId, ['status' => 'active']);
        
        return ['success' => true, 'message' => 'Subscription active processed'];
    }
    
    /**
     * Handle subscription.paused event
     */
    private function handleSubscriptionPaused($data) {
        $subscriptionId = $data['id'];
        
        // Update subscription status
        $this->updateSubscriptionRecord($subscriptionId, ['status' => 'paused']);
        
        return ['success' => true, 'message' => 'Subscription paused processed'];
    }
    
    /**
     * Handle subscription.cancelled event
     */
    private function handleSubscriptionCancelled($data) {
        $subscriptionId = $data['id'];
        
        // Update subscription status
        $this->updateSubscriptionRecord($subscriptionId, ['status' => 'cancelled']);
        
        return ['success' => true, 'message' => 'Subscription cancelled processed'];
    }
    
    /**
     * Handle subscription.expired event
     */
    private function handleSubscriptionExpired($data) {
        $subscriptionId = $data['id'];
        
        // Update subscription status
        $this->updateSubscriptionRecord($subscriptionId, ['status' => 'cancelled']);
        
        return ['success' => true, 'message' => 'Subscription expired processed'];
    }
    
    /**
     * Calculate next billing date based on frequency
     */
    private function calculateNextBillingDate($frequency) {
        $now = new DateTime();
        
        switch ($frequency) {
            case 'weekly':
                $now->modify('+7 days');
                break;
            case 'monthly':
                $now->modify('+1 month');
                break;
            case 'annually':
                $now->modify('+1 year');
                break;
        }
        
        return $now->format('Y-m-d');
    }
}
