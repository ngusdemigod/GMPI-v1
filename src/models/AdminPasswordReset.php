<?php
/**
 * Admin Password Reset Model
 * Handles admin password reset token generation, validation, and storage
 * Church Financial Partnership System
 */

require_once __DIR__ . '/../config/database.php';

class AdminPasswordReset {
    private $db;
    private const TOKEN_EXPIRY_MINUTES = 15;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->ensureAdminPasswordResetTableExists();
    }
    
    /**
     * Create a new password reset token for an admin
     */
    public function createResetToken($adminId) {
        // First, invalidate any existing tokens for this admin
        $this->invalidateAllTokens($adminId);
        
        // Generate a secure random token
        $token = bin2hex(random_bytes(32));
        
        // Calculate expiration time (15 minutes from now)
        $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        // Get admin email for the reset link
        $admin = $this->db->fetchOne(
            "SELECT au.admin_id, au.user_id, u.email 
             FROM admin_password_resets apr
             INNER JOIN admin_users au ON apr.admin_id = au.admin_id
             INNER JOIN users u ON au.user_id = u.user_id
             WHERE apr.admin_id = :admin_id AND apr.used = FALSE
             ORDER BY apr.created_at DESC
             LIMIT 1",
            ['admin_id' => $adminId]
        );
        
        if (!$admin) {
            // Try to get admin directly
            $admin = $this->db->fetchOne(
                "SELECT au.admin_id, au.user_id, u.email 
                 FROM admin_users au
                 INNER JOIN users u ON au.user_id = u.user_id
                 WHERE au.admin_id = :admin_id",
                ['admin_id' => $adminId]
            );
        }
        
        if (!$admin) {
            throw new Exception('Admin not found');
        }
        
        // Insert the reset token
        $this->db->execute(
            "INSERT INTO admin_password_resets (admin_id, token, expires_at) 
             VALUES (:admin_id, :token, :expires_at)",
            [
                'admin_id' => $adminId,
                'token' => $token,
                'expires_at' => $expiresAt
            ]
        );
        
        return [
            'token' => $token,
            'email' => $admin['email'],
            'admin_id' => $admin['admin_id'],
            'user_id' => $admin['user_id'],
            'expires_at' => $expiresAt
        ];
    }
    
    /**
     * Validate a password reset token
     */
    public function validateToken($token) {
        $reset = $this->db->fetchOne(
            "SELECT apr.*, u.email, au.admin_id, au.user_id 
             FROM admin_password_resets apr
             INNER JOIN admin_users au ON apr.admin_id = au.admin_id
             INNER JOIN users u ON au.user_id = u.user_id
             WHERE apr.token = :token AND apr.used = FALSE",
            ['token' => $token]
        );
        
        if (!$reset) {
            return null;
        }
        
        // Check if token has expired
        if (strtotime($reset['expires_at']) < time()) {
            // Mark expired token as used to invalidate it
            $this->db->execute(
                "UPDATE admin_password_resets SET used = TRUE WHERE id = :id",
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
            "UPDATE admin_password_resets SET used = TRUE WHERE token = :token",
            ['token' => $token]
        );
    }
    
    /**
     * Invalidate all tokens for an admin
     */
    private function invalidateAllTokens($adminId) {
        $this->db->execute(
            "UPDATE admin_password_resets SET used = TRUE WHERE admin_id = :admin_id",
            ['admin_id' => $adminId]
        );
    }
    
    /**
     * Delete old/expired tokens
     */
    public function cleanupExpiredTokens() {
        $this->db->execute(
            "DELETE FROM admin_password_resets WHERE expires_at < NOW() OR used = TRUE"
        );
    }
    
    /**
     * Get reset token by ID
     */
    public function getTokenById($id) {
        return $this->db->fetchOne(
            "SELECT * FROM admin_password_resets WHERE id = :id",
            ['id' => $id]
        );
    }
    
    /**
     * Get admin by token
     */
    public function getAdminByToken($token) {
        return $this->db->fetchOne(
            "SELECT au.*, u.email 
             FROM admin_users au
             INNER JOIN users u ON au.user_id = u.user_id
             INNER JOIN admin_password_resets apr ON au.admin_id = apr.admin_id
             WHERE apr.token = :token AND apr.used = FALSE AND apr.expires_at > NOW()",
            ['token' => $token]
        );
    }

    /**
     * Ensure the admin password reset table exists in environments that were
     * initialized before this table was added to the schema.
     */
    private function ensureAdminPasswordResetTableExists(): void
    {
        $this->db->execute(
            "CREATE TABLE IF NOT EXISTS admin_password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                used BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (admin_id) REFERENCES admin_users(admin_id) ON DELETE CASCADE,
                INDEX idx_token (token),
                INDEX idx_admin_id (admin_id),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
