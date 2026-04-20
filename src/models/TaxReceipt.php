<?php
/**
 * Tax Receipt Model
 * Handles tax-deductible receipt generation and management
 * Church Financial Partnership System
 */

require_once __DIR__ . '/../config/database.php';

class TaxReceipt {
    private $db;
    private $organizationName = 'Bright Light Ministry Int\'l';
    private $taxId = '';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Generate annual tax receipt for a user
     */
    public function generateReceipt($userId, $year) {
        $this->db->beginTransaction();
        
        try {
            $totalGiving = $this->db->fetchOne(
                "SELECT COALESCE(SUM(amount), 0) as total 
                 FROM transactions 
                 WHERE user_id = :user_id 
                 AND YEAR(transaction_date) = :year 
                 AND status = 'completed'",
                ['user_id' => $userId, 'year' => $year]
            );
            
            if ($totalGiving['total'] <= 0) {
                $this->db->rollback();
                return ['success' => false, 'error' => 'No qualifying donations found'];
            }
            
            $existing = $this->db->fetchOne(
                "SELECT receipt_id FROM tax_receipts WHERE user_id = :user_id AND year = :year",
                ['user_id' => $userId, 'year' => $year]
            );
            
            if ($existing) {
                $this->db->execute(
                    "UPDATE tax_receipts SET total_amount = :total, issue_date = CURDATE() WHERE receipt_id = :receipt_id",
                    ['total' => $totalGiving['total'], 'receipt_id' => $existing['receipt_id']]
                );
                $receiptId = $existing['receipt_id'];
            } else {
                $receiptNumber = 'TR-' . $year . '-' . str_pad($this->getNextReceiptNumber($year), 5, '0', STR_PAD_LEFT);
                
                $this->db->execute(
                    "INSERT INTO tax_receipts (user_id, year, total_amount, receipt_number, issue_date)
                     VALUES (:user_id, :year, :total, :receipt_number, CURDATE())",
                    [
                        'user_id' => $userId,
                        'year' => $year,
                        'total' => $totalGiving['total'],
                        'receipt_number' => $receiptNumber
                    ]
                );
                
                $receiptId = $this->db->lastInsertId();
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'receiptId' => $receiptId,
                'receiptNumber' => $existing ? $existing['receipt_id'] : $receiptNumber,
                'totalAmount' => $totalGiving['total']
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Tax receipt generation failed: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to generate receipt'];
        }
    }
    
    private function getNextReceiptNumber($year) {
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM tax_receipts WHERE year = :year",
            ['year' => $year]
        );
        return $result['count'] + 1;
    }
    
    public function getById($receiptId) {
        return $this->db->fetchOne(
            "SELECT tr.*, u.first_name, u.last_name, u.email, u.address_line1, u.address_line2, u.city, u.state, u.postal_code
             FROM tax_receipts tr
             LEFT JOIN users u ON tr.user_id = u.user_id
             WHERE tr.receipt_id = :receipt_id",
            ['receipt_id' => $receiptId]
        );
    }
    
    public function getByNumber($receiptNumber) {
        return $this->db->fetchOne(
            "SELECT tr.*, u.first_name, u.last_name, u.email, u.address_line1, u.address_line2, u.city, u.state, u.postal_code
             FROM tax_receipts tr
             LEFT JOIN users u ON tr.user_id = u.user_id
             WHERE tr.receipt_number = :receipt_number",
            ['receipt_number' => $receiptNumber]
        );
    }
    
    public function getUserReceipts($userId) {
        return $this->db->fetchAll(
            "SELECT * FROM tax_receipts WHERE user_id = :user_id ORDER BY year DESC",
            ['user_id' => $userId]
        );
    }
    
    public function getYearReceipts($year) {
        return $this->db->fetchAll(
            "SELECT tr.*, u.first_name, u.last_name, u.email
             FROM tax_receipts tr
             LEFT JOIN users u ON tr.user_id = u.user_id
             WHERE tr.year = :year
             ORDER BY tr.issue_date DESC",
            ['year' => $year]
        );
    }
    
    public function getReceiptWithDetails($receiptId) {
        $receipt = $this->getById($receiptId);
        
        if (!$receipt) {
            return null;
        }
        
        $transactions = $this->db->fetchAll(
            "SELECT category, COUNT(*) as count, SUM(amount) as total
             FROM transactions
             WHERE user_id = :user_id AND YEAR(transaction_date) = :year AND status = 'completed'
             GROUP BY category
             ORDER BY total DESC",
            ['user_id' => $receipt['user_id'], 'year' => $receipt['year']]
        );
        
        return [
            'receipt' => $receipt,
            'transactions' => $transactions
        ];
    }
    
    public function generateReceiptHTML($receiptId) {
        $receipt = $this->getReceiptWithDetails($receiptId);
        
        if (!$receipt) {
            return null;
        }
        
        $r = $receipt['receipt'];
        $transactions = $receipt['transactions'];
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Tax Receipt - ' . htmlspecialchars($r['receipt_number']) . '</title>
    <style>
        body { font-family: Georgia, serif; max-width: 800px; margin: 0 auto; padding: 40px; color: #333; }
        .header { text-align: center; border-bottom: 3px solid #D4AF37; padding-bottom: 20px; margin-bottom: 30px; }
        .org-name { font-size: 28px; color: #1a1a2e; margin-bottom: 5px; }
        .tax-id { font-size: 14px; color: #666; }
        .receipt-title { text-align: center; font-size: 24px; color: #D4AF37; margin: 20px 0; }
        .recipient { margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #D4AF37; }
        .amount-box { text-align: center; padding: 30px; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: #D4AF37; margin: 30px 0; border-radius: 8px; }
        .amount-label { font-size: 14px; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px; }
        .amount-value { font-size: 48px; font-weight: bold; }
        .breakdown { margin: 30px 0; }
        .breakdown table { width: 100%; border-collapse: collapse; }
        .breakdown th { text-align: left; padding: 12px; background: #f0f0f0; border-bottom: 2px solid #D4AF37; }
        .breakdown td { padding: 12px; border-bottom: 1px solid #eee; }
        .breakdown .amount { text-align: right; font-weight: bold; }
        .disclaimer { font-size: 12px; color: #666; margin-top: 40px; padding: 20px; background: #f9f9f9; border-radius: 4px; }
        .footer { text-align: center; margin-top: 40px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class="header">
        <div class="org-name">' . $this->organizationName . '</div>
    </div>
    <div class="receipt-title">Tax Contribution Statement</div>
    <div class="recipient">
        <strong>Received from:</strong><br>
        ' . htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) . '<br>
        ' . htmlspecialchars($r['email']) . '
    </div>
    <div class="amount-box">
        <div class="amount-label">Total Contributions for ' . $r['year'] . '</div>
        <div class="amount-value">$' . number_format($r['total_amount'], 2) . '</div>
    </div>
    <div class="breakdown">
        <h3>Contribution Breakdown by Category</h3>
        <table>
            <thead>
                <tr><th>Category</th><th>Number of Gifts</th><th class="amount">Amount</th></tr>
            </thead>
            <tbody>';
        
        foreach ($transactions as $t) {
            $html .= '<tr><td>' . htmlspecialchars($t['category']) . '</td><td>' . $t['count'] . '</td><td class="amount">$' . number_format($t['total'], 2) . '</td></tr>';
        }
        
        $totalCount = array_sum(array_column($transactions, 'count'));
        $html .= '<tfoot><tr style="background: #D4AF37; color: white;"><td><strong>Total</strong></td><td><strong>' . $totalCount . '</strong></td><td class="amount"><strong>$' . number_format($r['total_amount'], 2) . '</strong></td></tr></tfoot>';
        $html .= '</tbody></table></div>
    <div class="disclaimer">
        <p><strong>Important:</strong> This document is provided for tax purposes only. No goods or services were provided in exchange for this contribution. Under IRS rules, contributions are tax-deductible to the extent allowed by law.</p>
        <p>This is a computer-generated receipt. No signature is required.</p>
    </div>
    <div class="footer">
        <p>Receipt Number: ' . $r['receipt_number'] . ' | Generated on: ' . date('F j, Y') . '</p>
        <p>' . $this->organizationName . '</p>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    public function sendReceiptEmail($receiptId) {
        $receipt = $this->getById($receiptId);
        
        if (!$receipt) {
            return false;
        }
        
        error_log("Receipt email would be sent to: " . $receipt['email']);
        
        $this->db->execute(
            "UPDATE tax_receipts SET is_sent = TRUE, sent_at = NOW() WHERE receipt_id = :receipt_id",
            ['receipt_id' => $receiptId]
        );
        
        return true;
    }
    
    public function getOrganizationSettings() {
        return [
            'name' => $this->organizationName,
            'taxId' => $this->taxId
        ];
    }
}