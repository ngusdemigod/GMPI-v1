<?php
/**
 * Email Verification Model
 * Handles email verification token management
 * Church Financial Partnership System
 * 
 * Features:
 * - 15-minute code expiration
 * - Cryptographically secure code generation
 * - Rate limiting support
 * - Proper error handling
 */

require_once __DIR__ . '/../config/database.php';

class EmailVerification {
    private $db;
    private $expirationMinutes = 15; // Codes expire after 15 minutes
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Generate a new verification code for a user
     * 
     * @param int $userId User ID
     * @param string $email User email
     * @return string|false Generated code or false on failure
     */
    public function createVerification($userId, $email) {
        try {
            // Generate cryptographically secure 6-character alphanumeric code
            $code = $this->generateVerificationCode();
            
            // Set expiration (15 minutes from now)
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$this->expirationMinutes} minutes"));
            
            // Delete any existing unused verifications for this user
            $this->db->execute(
                "DELETE FROM email_verifications WHERE user_id = :user_id AND used = FALSE",
                ['user_id' => $userId]
            );
            
            // Insert new verification
            $this->db->execute(
                "INSERT INTO email_verifications (user_id, verification_code, expires_at) 
                 VALUES (:user_id, :code, :expires_at)",
                [
                    'user_id' => $userId,
                    'code' => $code,
                    'expires_at' => $expiresAt
                ]
            );
            
            return $code;
        } catch (Exception $e) {
            error_log("EmailVerification createVerification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify a code for a user
     * 
     * @param int $userId User ID
     * @param string $code Verification code
     * @return bool Success status
     */
    public function verifyCode($userId, $code) {
        try {
            // Get the verification record
            $verification = $this->db->fetchOne(
                "SELECT * FROM email_verifications 
                 WHERE user_id = :user_id 
                 AND verification_code = :code 
                 AND used = FALSE 
                 AND expires_at > NOW()",
                [
                    'user_id' => $userId,
                    'code' => $code
                ]
            );
            
            if (!$verification) {
                return false;
            }
            
            // Mark as used
            $this->db->execute(
                "UPDATE email_verifications SET used = TRUE WHERE id = :id",
                ['id' => $verification['id']]
            );
            
            return true;
        } catch (Exception $e) {
            error_log("EmailVerification verifyCode error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if a code is valid (not expired, not used)
     * 
     * @param int $userId User ID
     * @param string $code Verification code
     * @return bool Success status
     */
    public function isValidCode($userId, $code) {
        try {
            $verification = $this->db->fetchOne(
                "SELECT * FROM email_verifications 
                 WHERE user_id = :user_id 
                 AND verification_code = :code 
                 AND used = FALSE 
                 AND expires_at > NOW()",
                [
                    'user_id' => $userId,
                    'code' => $code
                ]
            );
            
            return $verification !== false;
        } catch (Exception $e) {
            error_log("EmailVerification isValidCode error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get verification record by code (for email lookup)
     * 
     * @param string $code Verification code
     * @return array|false Verification data or false
     */
    public function getByCode($code) {
        try {
            return $this->db->fetchOne(
                "SELECT ev.*, u.email, u.first_name, u.last_name 
                 FROM email_verifications ev
                 INNER JOIN users u ON ev.user_id = u.user_id
                 WHERE ev.verification_code = :code 
                 AND ev.used = FALSE 
                 AND ev.expires_at > NOW()",
                ['code' => $code]
            );
        } catch (Exception $e) {
            error_log("EmailVerification getByCode error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user already has a pending verification
     * 
     * @param int $userId User ID
     * @return bool Has pending verification
     */
    public function hasPendingVerification($userId) {
        try {
            $result = $this->db->fetchOne(
                "SELECT COUNT(*) as count 
                 FROM email_verifications 
                 WHERE user_id = :user_id 
                 AND used = FALSE 
                 AND expires_at > NOW()",
                ['user_id' => $userId]
            );
            
            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("EmailVerification hasPendingVerification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean up expired verifications
     */
    public function cleanupExpired() {
        try {
            $this->db->execute(
                "DELETE FROM email_verifications WHERE expires_at < NOW() OR used = TRUE"
            );
            return true;
        } catch (Exception $e) {
            error_log("EmailVerification cleanupExpired error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate a cryptographically secure 6-character alphanumeric verification code
     * Uses uppercase letters (excluding similar-looking characters) and numbers
     * 
     * @return string Generated code
     */
    public function generateVerificationCode() {
        // Characters: A-Z (excluding I, O, 0, 1 to avoid confusion) + 2-9
        // This gives us 24 letters + 8 numbers = 32 characters
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        $length = 6;
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $code;
    }
    
    /**
     * Check if a code has expired
     * 
     * @param int $userId User ID
     * @return bool True if expired, false otherwise
     */
    public function isExpired($userId) {
        try {
            $result = $this->db->fetchOne(
                "SELECT expires_at FROM email_verifications 
                 WHERE user_id = :user_id 
                 AND used = FALSE 
                 ORDER BY created_at DESC LIMIT 1",
                ['user_id' => $userId]
            );
            
            if (!$result) {
                return true; // No verification exists, consider expired
            }
            
            return strtotime($result['expires_at']) < time();
        } catch (Exception $e) {
            error_log("EmailVerification isExpired error: " . $e->getMessage());
            return true;
        }
    }
    
    /**
     * Get time remaining until code expires (in seconds)
     * 
     * @param int $userId User ID
     * @return int Seconds remaining, or 0 if expired/no code
     */
    public function getTimeRemaining($userId) {
        try {
            $result = $this->db->fetchOne(
                "SELECT expires_at FROM email_verifications 
                 WHERE user_id = :user_id 
                 AND used = FALSE 
                 ORDER BY created_at DESC LIMIT 1",
                ['user_id' => $userId]
            );
            
            if (!$result) {
                return 0;
            }
            
            $expiresAt = strtotime($result['expires_at']);
            $remaining = $expiresAt - time();
            
            return $remaining > 0 ? $remaining : 0;
        } catch (Exception $e) {
            error_log("EmailVerification getTimeRemaining error: " . $e->getMessage());
            return 0;
        }
    }
}