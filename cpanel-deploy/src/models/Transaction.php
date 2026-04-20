<?php
/**
 * Transaction Model
 * Handles donation processing and transaction management
 * Church Financial Partnership System
 */

require_once __DIR__ . '/../config/database.php';

class Transaction {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Process a new donation
     */
    public function processDonation($userId, $amount, $category, $frequency, $projectId = null, $paymentMethodId = null, $notes = null) {
        $this->db->beginTransaction();
        
        try {
            // Generate unique transaction reference
            $transactionReference = 'TXN-' . date('Ymd') . '-' . str_pad($this->getTodayTransactionCount() + 1, 3, '0', STR_PAD_LEFT);
            
            // Insert transaction
            $this->db->execute(
                "INSERT INTO transactions 
                 (user_id, project_id, amount, category, frequency, payment_method_id, transaction_reference, status, transaction_date, processed_at)
                 VALUES (:user_id, :project_id, :amount, :category, :frequency, :payment_method_id, :transaction_reference, 'completed', NOW(), NOW())",
                [
                    'user_id' => $userId,
                    'project_id' => $projectId,
                    'amount' => $amount,
                    'category' => $category,
                    'frequency' => $frequency,
                    'payment_method_id' => $paymentMethodId,
                    'transaction_reference' => $transactionReference
                ]
            );
            
            $transactionId = $this->db->lastInsertId();
            
            // Update pledge if applicable
            if ($projectId) {
                $this->updatePledgeProgress($userId, $projectId, $amount);
            }
            
            // Update user's yearly giving total
            $this->updateYearlyGiving($userId);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'transactionId' => $transactionId,
                'reference' => $transactionReference
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Transaction processing failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Transaction processing failed'
            ];
        }
    }
    
    /**
     * Get today's transaction count
     */
    private function getTodayTransactionCount() {
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM transactions 
             WHERE DATE(transaction_date) = CURDATE()"
        );
        return $result['count'] ?? 0;
    }
    
    /**
     * Update pledge progress after donation
     */
    private function updatePledgeProgress($userId, $projectId, $amount) {
        $pledge = $this->db->fetchOne(
            "SELECT pledge_id, total_amount, remaining_amount 
             FROM pledges 
             WHERE user_id = :user_id AND project_id = :project_id AND is_active = TRUE",
            ['user_id' => $userId, 'project_id' => $projectId]
        );
        
        if ($pledge) {
            $newRemaining = max(0, $pledge['remaining_amount'] - $amount);
            
            if ($newRemaining == 0) {
                // Pledge fulfilled
                $this->db->execute(
                    "UPDATE pledges SET remaining_amount = 0, is_active = FALSE WHERE pledge_id = :pledge_id",
                    ['pledge_id' => $pledge['pledge_id']]
                );
            } else {
                $this->db->execute(
                    "UPDATE pledges SET remaining_amount = :remaining WHERE pledge_id = :pledge_id",
                    ['remaining' => $newRemaining, 'pledge_id' => $pledge['pledge_id']]
                );
            }
        }
    }
    
    /**
     * Update user's yearly giving total
     */
    private function updateYearlyGiving($userId) {
        // This would typically update a cached total for performance
        // For now, we'll calculate it on demand
    }
    
    /**
     * Get user's transactions
     */
    public function getUserTransactions($userId, $year = null, $limit = null) {
        $yearCondition = $year ? "AND YEAR(transaction_date) = :year" : "";
        $limitClause = '';
        $params = ['user_id' => (int) $userId];
        if ($year) {
            $params['year'] = $year;
        }
        if ($limit !== null) {
            $limitClause = ' LIMIT :limit';
            $params['limit'] = (int) $limit;
        }
        
        return $this->db->fetchAll(
            "SELECT
                t.*,
                p.title AS project_title,
                p.title AS campaign_title,
                p.category AS project_category,
                pm.card_type,
                pm.last_four_digits,
                pt.channel,
                pt.currency AS paystack_currency
             FROM transactions t
             LEFT JOIN projects p ON t.project_id = p.project_id
             LEFT JOIN payment_methods pm ON t.payment_method_id = pm.payment_method_id
             LEFT JOIN paystack_transactions pt ON pt.transaction_id = t.transaction_id
             WHERE t.user_id = :user_id {$yearCondition}
             AND t.status = 'completed'
             ORDER BY t.transaction_date DESC
             {$limitClause}",
            $params
        );
    }
    
    /**
     * Get transaction by reference
     */
    public function getByReference($reference) {
        return $this->db->fetchOne(
            "SELECT * FROM transactions WHERE transaction_reference = :reference",
            ['reference' => $reference]
        );
    }
    
    /**
     * Get transaction statistics
     */
    public function getStatistics($userId = null, $year = null) {
        $userCondition = $userId ? "AND t.user_id = :user_id" : "";
        $yearCondition = $year ? "AND YEAR(t.transaction_date) = :year" : "";
        
        $params = [];
        if ($userId) $params['user_id'] = $userId;
        if ($year) $params['year'] = $year;
        
        $stats = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_transactions,
                COALESCE(SUM(t.amount), 0) as total_amount,
                COALESCE(AVG(t.amount), 0) as average_amount
             FROM transactions t
             WHERE t.status = 'completed' {$userCondition} {$yearCondition}",
            $params
        );
        
        // Get category breakdown
        $categoryBreakdown = $this->db->fetchAll(
            "SELECT category, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
             FROM transactions t
             WHERE t.status = 'completed' {$userCondition} {$yearCondition}
             GROUP BY category",
            $params
        );
        
        return [
            'total' => $stats,
            'byCategory' => $categoryBreakdown
        ];
    }
    
    /**
     * Get donation categories
     */
    public static function getCategories() {
        return ['Tithe', 'Offering', 'Missions', 'Building', 'Scholarship', 'General'];
    }
    
    /**
     * Get preset donation amounts
     */
    public static function getPresetAmounts() {
        return [50, 100, 250, 500, 1000, 2500, 5000];
    }
    
    /**
     * Get donation frequencies
     */
    public static function getFrequencies() {
        return ['One-time', 'Weekly', 'Monthly', 'Annually'];
    }
    
    /**
     * Validate donation amount
     */
    public static function validateAmount($amount) {
        $amount = floatval($amount);
        
        if ($amount <= 0) {
            return ['valid' => false, 'error' => 'Amount must be greater than 0'];
        }
        
        if ($amount > 999999999) {
            return ['valid' => false, 'error' => 'Amount exceeds maximum limit'];
        }
        
        return ['valid' => true, 'amount' => round($amount, 2)];
    }
    
    /**
     * Get recent system transactions (for admin)
     */
    public function getRecentTransactions($limit = 20) {
        return $this->db->fetchAll(
            "SELECT t.*, u.first_name, u.last_name, u.email, p.title as project_title
             FROM transactions t
             LEFT JOIN users u ON t.user_id = u.user_id
             LEFT JOIN projects p ON t.project_id = p.project_id
             WHERE t.status = 'completed'
             ORDER BY t.transaction_date DESC
             LIMIT :limit",
            ['limit' => $limit]
        );
    }
}
