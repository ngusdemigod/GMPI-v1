<?php
/**
 * Admin 2FA Model
 * Handles 2-step verification code generation, validation, and storage
 * Church Financial Partnership System
 */

require_once __DIR__ . '/../config/database.php';

class Admin2FA {
    private $db;
    private const CODE_LENGTH = 6;
    private const CODE_EXPIRY_MINUTES = 15;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Generate a random 6-digit verification code
     */
    public function generateCode() {
        return str_pad(random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }
    
    /**
     * Store a 2FA code for an admin
     */
    public function storeCode($adminId, $code) {
        // Invalidate any existing codes for this admin
        $this->invalidateCode($adminId);
        
        // Calculate expiration time (15 minutes from now)
        $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        $this->db->execute(
            "INSERT INTO admin_2fa (admin_id, secret, expires_at) 
             VALUES (:admin_id, :secret, :expires_at)
             ON DUPLICATE KEY UPDATE 
             secret = :secret, 
             expires_at = :expires_at,
             updated_at = CURRENT_TIMESTAMP",
            [
                'admin_id' => $adminId,
                'secret' => $code,
                'expires_at' => $expiresAt
            ]
        );
        
        return $code;
    }
    
    /**
     * Validate a 2FA code
     */
    public function validateCode($adminId, $code) {
        $record = $this->db->fetchOne(
            "SELECT * FROM admin_2fa 
             WHERE admin_id = :admin_id 
             AND secret = :secret 
             AND used = FALSE 
             AND expires_at > NOW()",
            [
                'admin_id' => $adminId,
                'secret' => $code
            ]
        );
        
        if (!$record) {
            return false;
        }
        
        // Mark the code as used
        $this->db->execute(
            "UPDATE admin_2fa SET used = TRUE WHERE id = :id",
            ['id' => $record['id']]
        );
        
        return true;
    }
    
    /**
     * Check if a code is valid (without marking as used)
     */
    public function checkCode($adminId, $code) {
        $record = $this->db->fetchOne(
            "SELECT * FROM admin_2fa 
             WHERE admin_id = :admin_id 
             AND secret = :secret 
             AND used = FALSE 
             AND expires_at > NOW()",
            [
                'admin_id' => $adminId,
                'secret' => $code
            ]
        );
        
        return $record !== false;
    }
    
    /**
     * Invalidate a code for an admin
     */
    private function invalidateCode($adminId) {
        $this->db->execute(
            "UPDATE admin_2fa SET used = TRUE WHERE admin_id = :admin_id",
            ['admin_id' => $adminId]
        );
    }
    
    /**
     * Get the current secret for an admin
     */
    public function getSecret($adminId) {
        $record = $this->db->fetchOne(
            "SELECT secret, expires_at FROM admin_2fa WHERE admin_id = :admin_id",
            ['admin_id' => $adminId]
        );
        
        return $record;
    }
    
    /**
     * Check if 2FA is enabled for an admin
     */
    public function is2FAEnabled($adminId) {
        $admin = $this->db->fetchOne(
            "SELECT two_factor_enabled FROM admin_users WHERE admin_id = :admin_id",
            ['admin_id' => $adminId]
        );
        
        return $admin && $admin['two_factor_enabled'] == 1;
    }
    
    /**
     * Enable 2FA for an admin
     */
    public function enable2FA($adminId, $secret) {
        $this->db->execute(
            "UPDATE admin_users SET two_factor_enabled = TRUE, two_factor_secret = :secret 
             WHERE admin_id = :admin_id",
            ['admin_id' => $adminId, 'secret' => $secret]
        );
        
        // Also store in admin_2fa table
        $this->db->execute(
            "INSERT INTO admin_2fa (admin_id, secret, verified_at) 
             VALUES (:admin_id, :secret, NOW())
             ON DUPLICATE KEY UPDATE 
             secret = :secret,
             verified_at = NOW(),
             updated_at = CURRENT_TIMESTAMP",
            ['admin_id' => $adminId, 'secret' => $secret]
        );
        
        return true;
    }
    
    /**
     * Disable 2FA for an admin
     */
    public function disable2FA($adminId) {
        $this->db->execute(
            "UPDATE admin_users SET two_factor_enabled = FALSE, two_factor_secret = NULL 
             WHERE admin_id = :admin_id",
            ['admin_id' => $adminId]
        );
        
        // Invalidate all codes
        $this->invalidateCode($adminId);
        
        return true;
    }
    
    /**
     * Generate backup codes for an admin
     */
    public function generateBackupCodes($adminId, $count = 10) {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = $this->generateCode();
        }
        
        $this->db->execute(
            "UPDATE admin_2fa SET backup_codes = :backup_codes WHERE admin_id = :admin_id",
            ['admin_id' => $adminId, 'backup_codes' => json_encode($codes)]
        );
        
        return $codes;
    }
    
    /**
     * Validate a backup code
     */
    public function validateBackupCode($adminId, $code) {
        $record = $this->db->fetchOne(
            "SELECT backup_codes FROM admin_2fa WHERE admin_id = :admin_id",
            ['admin_id' => $adminId]
        );
        
        if (!$record || !$record['backup_codes']) {
            return false;
        }
        
        $codes = json_decode($record['backup_codes'], true);
        
        if (in_array($code, $codes)) {
            // Remove the used backup code
            $codes = array_diff($codes, [$code]);
            $this->db->execute(
                "UPDATE admin_2fa SET backup_codes = :backup_codes WHERE admin_id = :admin_id",
                ['admin_id' => $adminId, 'backup_codes' => json_encode(array_values($codes))]
            );
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete old/expired codes
     */
    public function cleanupExpiredCodes() {
        $this->db->execute(
            "DELETE FROM admin_2fa WHERE expires_at < NOW() OR used = TRUE"
        );
    }
    
    /**
     * Get admin info by 2FA secret
     */
    public function getAdminBySecret($secret) {
        $admin = $this->db->fetchOne(
            "SELECT au.*, u.email 
             FROM admin_users au
             INNER JOIN users u ON au.user_id = u.user_id
             INNER JOIN admin_2fa a2fa ON au.admin_id = a2fa.admin_id
             WHERE a2fa.secret = :secret 
             AND a2fa.used = FALSE 
             AND a2fa.expires_at > NOW()",
            ['secret' => $secret]
        );
        
        return $admin;
    }
}