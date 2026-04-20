<?php
/**
 * Password Reset Model
 * Handles password reset token generation, validation, and storage
 * Church Financial Partnership System
 */

require_once __DIR__ . '/../config/database.php';

class PasswordReset {
    private $db;
    private const TOKEN_EXPIRY_MINUTES = 15;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->ensurePasswordResetTableExists();
    }
    
    /**
     * Create a new password reset token for a user
     */
    public function createResetToken($userId) {
        // First, invalidate any existing tokens for this user
        $this->invalidateAllTokens($userId);
        
        // Generate a secure random token
        $token = bin2hex(random_bytes(32));
        
        // Calculate expiration time (15 minutes from now)
        $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        // Get user email for the reset link
        $user = $this->db->fetchOne(
            "SELECT email FROM users WHERE user_id = :user_id",
            ['user_id' => $userId]
        );
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Insert the reset token
        $this->db->execute(
            "INSERT INTO password_resets (user_id, token, expires_at) 
             VALUES (:user_id, :token, :expires_at)",
            [
                'user_id' => $userId,
                'token' => $token,
                'expires_at' => $expiresAt
            ]
        );
        
        return [
            'token' => $token,
            'email' => $user['email'],
            'expires_at' => $expiresAt
        ];
    }
    
    /**
     * Validate a password reset token
     */
    public function validateToken($token) {
        $reset = $this->db->fetchOne(
            "SELECT pr.*, u.email, u.user_id 
             FROM password_resets pr
             INNER JOIN users u ON pr.user_id = u.user_id
             WHERE pr.token = :token AND pr.used = FALSE",
            ['token' => $token]
        );
        
        if (!$reset) {
            return null;
        }
        
        // Check if token has expired
        if (strtotime($reset['expires_at']) < time()) {
            // Mark expired token as used to invalidate it
            $this->db->execute(
                "UPDATE password_resets SET used = TRUE WHERE id = :id",
                ['id' => $reset['id']]
            );
            return null;
        }
        
        return $reset;
    }
    
    /**
     * Mark a token as used
     */
    public function markTokenAsUsed($token) {
        $this->db->execute(
            "UPDATE password_resets SET used = TRUE WHERE token = :token",
            ['token' => $token]
        );
    }
    
    /**
     * Invalidate all tokens for a user
     */
    private function invalidateAllTokens($userId) {
        $this->db->execute(
            "UPDATE password_resets SET used = TRUE WHERE user_id = :user_id",
            ['user_id' => $userId]
        );
    }
    
    /**
     * Delete old/expired tokens
     */
    public function cleanupExpiredTokens() {
        $this->db->execute(
            "DELETE FROM password_resets WHERE expires_at < NOW() OR used = TRUE"
        );
    }
    
    /**
     * Get reset token by ID
     */
    public function getTokenById($id) {
        return $this->db->fetchOne(
            "SELECT * FROM password_resets WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * Ensure the password reset table exists for environments that were
     * initialized before this table was added to the schema.
     */
    private function ensurePasswordResetTableExists(): void
    {
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                used BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                INDEX idx_token (token),
                INDEX idx_user_id (user_id),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
