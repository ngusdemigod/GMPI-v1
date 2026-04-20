<?php
/**
 * Authentication Class
 * JWT-based admin authentication
 * Church Financial Partnership System
 */

class Auth {
    /**
     * Generate JWT token
     */
    public static function generateToken($adminId, $userId, $email, $name) {
        $payload = [
            'iss' => 'church-partnership-admin',
            'aud' => 'church-partnership-admin',
            'sub' => (string)$adminId,
            'admin_id' => $adminId,
            'user_id' => $userId,
            'email' => $email,
            'name' => $name,
            'iat' => time(),
            'exp' => time() + JWT_EXPIRY_SECONDS
        ];
        
        $header = [
            'typ' => 'JWT',
            'alg' => JWT_ALGORITHM
        ];
        
        $headerEncoded = self::urlEncode(json_encode($header));
        $payloadEncoded = self::urlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", JWT_SECRET, true);
        $signatureEncoded = self::urlEncode($signature);
        
        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }
    
    /**
     * Verify and decode JWT token
     */
    public static function verifyToken($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return null;
        }
        
        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;
        
        // Verify signature
        $header = json_decode(self::urlDecode($headerEncoded), true);
        $payload = json_decode(self::urlDecode($payloadEncoded), true);
        $signature = self::urlDecode($signatureEncoded);
        
        $expectedSignature = hash_hmac(
            'sha256',
            "$headerEncoded.$payloadEncoded",
            JWT_SECRET,
            true
        );
        
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }
        
        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }
        
        return $payload;
    }
    
    /**
     * URL-safe base64 encoding
     */
    private static function urlEncode($string) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($string));
    }
    
    /**
     * URL-safe base64 decoding
     */
    private static function urlDecode($string) {
        $padding = 4 - (strlen($string) % 4);
        if ($padding !== 4) {
            $string .= str_repeat('=', $padding);
        }
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $string));
    }
    
    /**
     * Create admin session
     */
    public static function createSession($adminId, $token) {
        $db = Database::getInstance();
        
        $sessionId = generateRandomString(64);
        $tokenHash = hash('sha256', $token);
        $userAgentHash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
        
        $db->execute(
            "INSERT INTO admin_sessions (session_id, admin_id, token_hash, ip_address, user_agent, user_agent_hash, expires_at)
             VALUES (:session_id, :admin_id, :token_hash, :ip, :user_agent, :user_agent_hash, :expires_at)",
            [
                'session_id' => $sessionId,
                'admin_id' => $adminId,
                'token_hash' => $tokenHash,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'user_agent_hash' => $userAgentHash,
                'expires_at' => date('Y-m-d H:i:s', time() + JWT_EXPIRY_SECONDS)
            ]
        );
        
        return $sessionId;
    }
    
    /**
     * Validate session
     */
    public static function validateSession($sessionId) {
        $db = Database::getInstance();
        
        $session = $db->fetchOne(
            "SELECT * FROM admin_sessions WHERE session_id = :session_id AND expires_at > NOW()",
            ['session_id' => $sessionId]
        );
        
        if (!$session) {
            return null;
        }
        
        // Update last activity
        $db->execute(
            "UPDATE admin_sessions SET last_activity_at = NOW() WHERE session_id = :session_id",
            ['session_id' => $sessionId]
        );
        
        return $session;
    }
    
    /**
     * Invalidate session
     */
    public static function invalidateSession($sessionId) {
        $db = Database::getInstance();
        $db->execute(
            "DELETE FROM admin_sessions WHERE session_id = :session_id",
            ['session_id' => $sessionId]
        );
    }
    
    /**
     * Invalidate all sessions for an admin
     */
    public static function invalidateAllSessions($adminId) {
        $db = Database::getInstance();
        $db->execute(
            "DELETE FROM admin_sessions WHERE admin_id = :admin_id",
            ['admin_id' => $adminId]
        );
    }
    
    /**
     * Check if user is admin
     */
    public static function isAdmin($userId) {
        $db = Database::getInstance();
        
        $admin = $db->fetchOne(
            "SELECT 1 FROM admin_users WHERE user_id = :user_id",
            ['user_id' => $userId]
        );
        
        return $admin !== null;
    }
    
    /**
     * Get admin user by user_id
     */
    public static function getAdminByUserId($userId) {
        $db = Database::getInstance();
        
        return $db->fetchOne(
            "SELECT au.*, u.email, u.first_name, u.last_name, u.phone 
             FROM admin_users au
             INNER JOIN users u ON au.user_id = u.user_id
             WHERE au.user_id = :user_id",
            ['user_id' => $userId]
        );
    }
    
    /**
     * Create admin user record
     */
    public static function createAdmin($userId) {
        $db = Database::getInstance();
        
        // Generate JWT secret
        $jwtSecret = generateRandomString(64);
        
        $db->execute(
            "INSERT INTO admin_users (user_id, jwt_secret) VALUES (:user_id, :jwt_secret)",
            ['user_id' => $userId, 'jwt_secret' => $jwtSecret]
        );
        
        return $db->lastInsertId();
    }
    
    /**
     * Remove admin access
     */
    public static function removeAdmin($adminId) {
        $db = Database::getInstance();
        
        // Check if this is the last admin
        $totalAdmins = $db->fetchOne(
            "SELECT COUNT(*) as count FROM admin_users"
        );
        
        if ($totalAdmins['count'] <= 1) {
            throw new Exception('Cannot remove the last admin. At least one admin must remain.');
        }
        
        // Invalidate all sessions
        self::invalidateAllSessions($adminId);
        
        // Remove admin record
        $db->execute(
            "DELETE FROM admin_users WHERE admin_id = :admin_id",
            ['admin_id' => $adminId]
        );
        
        return true;
    }
    
    /**
     * Check if admin can be removed (not the last one)
     */
    public static function canRemoveAdmin($adminId) {
        $db = Database::getInstance();
        
        $totalAdmins = $db->fetchOne(
            "SELECT COUNT(*) as count FROM admin_users"
        );
        
        return $totalAdmins['count'] > 1;
    }
    
    /**
     * Reset password token
     */
    public static function createPasswordResetToken($adminId) {
        $db = Database::getInstance();
        
        $token = generateRandomString(32);
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
        
        $db->execute(
            "UPDATE admin_users 
             SET password_reset_token = :token, password_reset_expires = :expires 
             WHERE admin_id = :admin_id",
            ['token' => $token, 'expires' => $expires, 'admin_id' => $adminId]
        );
        
        return $token;
    }
    
    /**
     * Verify password reset token
     */
    public static function verifyPasswordResetToken($token) {
        $db = Database::getInstance();
        
        $admin = $db->fetchOne(
            "SELECT * FROM admin_users 
             WHERE password_reset_token = :token AND password_reset_expires > NOW()",
            ['token' => $token]
        );
        
        return $admin;
    }
    
    /**
     * Reset password
     */
    public static function resetPassword($adminId, $newPassword) {
        $db = Database::getInstance();
        
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        
        $db->execute(
            "UPDATE admin_users 
             SET password_hash = :password_hash, 
                 password_reset_token = NULL, 
                 password_reset_expires = NULL,
                 last_password_reset = NOW()
             WHERE admin_id = :admin_id",
            ['password_hash' => $passwordHash, 'admin_id' => $adminId]
        );
        
        // Invalidate all sessions
        self::invalidateAllSessions($adminId);
        
        return true;
    }
    
    /**
     * Increment failed login attempts
     */
    public static function incrementFailedLoginAttempts($adminId) {
        $db = Database::getInstance();
        
        $db->execute(
            "UPDATE admin_users 
             SET failed_login_attempts = failed_login_attempts + 1,
                 locked_until = CASE 
                     WHEN failed_login_attempts + 1 >= :max_attempts 
                     THEN DATE_ADD(NOW(), INTERVAL :lockout_duration SECOND)
                     ELSE locked_until 
                 END
             WHERE admin_id = :admin_id",
            ['max_attempts' => MAX_LOGIN_ATTEMPTS, 'lockout_duration' => LOCKOUT_DURATION, 'admin_id' => $adminId]
        );
    }
    
    /**
     * Reset failed login attempts
     */
    public static function resetFailedLoginAttempts($adminId) {
        $db = Database::getInstance();
        
        $db->execute(
            "UPDATE admin_users 
             SET failed_login_attempts = 0, locked_until = NULL 
             WHERE admin_id = :admin_id",
            ['admin_id' => $adminId]
        );
    }
    
    /**
     * Check if account is locked
     */
    public static function isAccountLocked($adminId) {
        $db = Database::getInstance();
        
        $admin = $db->fetchOne(
            "SELECT locked_until, failed_login_attempts 
             FROM admin_users 
             WHERE admin_id = :admin_id",
            ['admin_id' => $adminId]
        );
        
        if (!$admin) {
            return false;
        }
        
        if ($admin['locked_until'] && strtotime($admin['locked_until']) > time()) {
            return true;
        }
        
        // Reset if lock expired
        if ($admin['locked_until'] && strtotime($admin['locked_until']) <= time()) {
            self::resetFailedLoginAttempts($adminId);
        }
        
        return false;
    }
}