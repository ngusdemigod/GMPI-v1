<?php
/**
 * User Model
 * Handles user authentication and management
 * Church Financial Partnership System
 */

require_once __DIR__ . '/../config/database.php';

class User {
    private $db;
    private $userId;
    private $userData;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Resolve the active user id for model methods.
     */
    private function resolveUserId($userId = null) {
        $resolvedUserId = $userId ?? $this->userId;
        if (!$resolvedUserId) {
            throw new InvalidArgumentException('User ID is required for this operation');
        }
        return (int) $resolvedUserId;
    }
    
    /**
     * Create a new user account
     */
    public static function create($email, $password, $firstName, $lastName, $phone = null) {
        $db = Database::getInstance();
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address');
        }
        
        // Validate password strength
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters');
        }
        
        // Check if email already exists
        $existing = $db->fetchOne(
            "SELECT user_id FROM users WHERE email = :email",
            ['email' => $email]
        );
        
        if ($existing) {
            throw new InvalidArgumentException('Email already registered');
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        // Insert user
        $db->execute(
            "INSERT INTO users (email, password_hash, first_name, last_name, phone, is_verified) 
             VALUES (:email, :password_hash, :first_name, :last_name, :phone, FALSE)",
            [
                'email' => $email,
                'password_hash' => $passwordHash,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone
            ]
        );
        
        $userId = $db->lastInsertId();
        
        // Assign default donor role
        $role = $db->fetchOne("SELECT role_id FROM user_roles WHERE role_name = 'donor'");
        if ($role) {
            $db->execute(
                "INSERT INTO user_role_assignments (user_id, role_id) VALUES (:user_id, :role_id)",
                ['user_id' => $userId, 'role_id' => $role['role_id']]
            );
        }
        
        return $userId;
    }
    
    /**
     * Authenticate user
     */
    public static function authenticate($email, $password) {
        $db = Database::getInstance();
        
        $user = $db->fetchOne(
            "SELECT * FROM users WHERE email = :email",
            ['email' => $email]
        );
        
        if (!$user) {
            return null;
        }

        if (!$user['is_active']) {
            return ['account_restricted' => true];
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }
        
        // Update last login
        $db->execute(
            "UPDATE users SET last_login = NOW() WHERE user_id = :user_id",
            ['user_id' => $user['user_id']]
        );
        
        // Log successful login
        $ipAddress = 'unknown';
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        
        self::logActivity($user['user_id'], 'INFO', 'User logged in successfully', [
            'ip' => $ipAddress
        ]);
        
        return $user;
    }
    
    /**
     * Get user by ID
     */
    public function getById($userId) {
        $this->userId = $userId;
        $this->userData = $this->db->fetchOne(
            "SELECT * FROM users WHERE user_id = :user_id",
            ['user_id' => $userId]
        );
        return $this->userData;
    }
    
    /**
     * Get user's roles
     */
    public function getRoles() {
        return $this->db->fetchAll(
            "SELECT ur.role_name, ur.role_description, ur.permissions 
             FROM user_roles ur
             INNER JOIN user_role_assignments ura ON ur.role_id = ura.role_id
             WHERE ura.user_id = :user_id",
            ['user_id' => $this->userId]
        );
    }
    
    /**
     * Check if user has permission
     */
    public function hasPermission($permission) {
        $roles = $this->getRoles();
        
        foreach ($roles as $role) {
            $permissions = json_decode($role['permissions'], true);
            if (isset($permissions[$permission]) && $permissions[$permission]) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get user's total giving for the year
     */
    public function getYearlyGiving($year, $userId = null) {
        $resolvedUserId = $this->resolveUserId($userId);
        return $this->db->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) as total 
             FROM transactions 
             WHERE user_id = :user_id 
             AND YEAR(transaction_date) = :year 
             AND status = 'completed'",
            ['user_id' => $resolvedUserId, 'year' => $year]
        );
    }
    
    /**
     * Get consecutive months of giving
     */
    public function getConsecutiveMonths($userId = null) {
        $resolvedUserId = $this->resolveUserId($userId);
        $currentMonth = date('Y-m');
        $consecutive = 0;
        
        for ($i = 0; $i < 12; $i++) {
            $checkMonth = date('Y-m', strtotime("-{$i} months"));
            $count = $this->db->fetchOne(
                "SELECT COUNT(*) as count 
                 FROM transactions 
                 WHERE user_id = :user_id 
                 AND DATE_FORMAT(transaction_date, '%Y-%m') = :month 
                 AND status = 'completed'",
                ['user_id' => $resolvedUserId, 'month' => $checkMonth]
            );
            
            if ($checkMonth === $currentMonth && $count['count'] > 0) {
                $consecutive = $count['count'] > 0 ? 1 : 0;
            } elseif ($consecutive === 0 && $count['count'] > 0) {
                $consecutive = 1;
            } elseif ($consecutive > 0 && $count['count'] > 0) {
                $consecutive++;
            } else {
                break;
            }
        }
        
        return $consecutive;
    }
    
    /**
     * Get user's donor tier
     */
    public function getDonorTier($userId = null) {
        $yearlyGiving = $this->getYearlyGiving(date('Y'), $userId);
        $totalAmount = $yearlyGiving['total'] ?? 0;
        
        $tier = $this->db->fetchOne(
            "SELECT * FROM donor_tiers 
             WHERE :amount >= min_amount 
             AND (max_amount IS NULL OR :amount <= max_amount)
             ORDER BY tier_id DESC
             LIMIT 1",
            ['amount' => $totalAmount]
        );
        
        return $tier;
    }
    
    /**
     * Get user's pledges
     */
    public function getPledges($userId = null) {
        $resolvedUserId = $this->resolveUserId($userId);
        return $this->db->fetchAll(
            "SELECT p.*, pr.title as project_title, pr.category 
             FROM pledges p
             LEFT JOIN projects pr ON p.project_id = pr.project_id
             WHERE p.user_id = :user_id AND p.is_active = TRUE
             ORDER BY p.created_at DESC",
            ['user_id' => $resolvedUserId]
        );
    }
    
    /**
     * Get user's recent transactions
     */
    public function getRecentTransactions($limit = 10, $userId = null) {
        $resolvedUserId = $this->resolveUserId($userId);
        return $this->db->fetchAll(
            "SELECT t.*, p.title as project_title, p.icon as project_icon, pm.card_type, pm.last_four_digits
             FROM transactions t
             LEFT JOIN projects p ON t.project_id = p.project_id
             LEFT JOIN payment_methods pm ON t.payment_method_id = pm.payment_method_id
             WHERE t.user_id = :user_id 
             AND t.status = 'completed'
             ORDER BY t.transaction_date DESC
             LIMIT :limit",
            ['user_id' => $resolvedUserId, 'limit' => $limit]
        );
    }
    
    /**
     * Get user's tax receipt for a year
     */
    public function getTaxReceipt($year, $userId = null) {
        $resolvedUserId = $this->resolveUserId($userId);
        return $this->db->fetchOne(
            "SELECT * FROM tax_receipts 
             WHERE user_id = :user_id AND year = :year",
            ['user_id' => $resolvedUserId, 'year' => $year]
        );
    }
    
    /**
     * Log activity
     */
    private static function logActivity($userId, $level, $message, $context = []) {
        $db = Database::getInstance();
        
        try {
            // Safely get IP address
            $ipAddress = 'unknown';
            if (isset($_SERVER['REMOTE_ADDR'])) {
                $ipAddress = $_SERVER['REMOTE_ADDR'];
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
            }
            
            $db->execute(
                "INSERT INTO system_logs (user_id, log_level, log_message, log_context, ip_address) 
                 VALUES (:user_id, :level, :message, :context, :ip)",
                [
                    'user_id' => $userId,
                    'level' => $level,
                    'message' => $message,
                    'context' => json_encode($context),
                    'ip' => $ipAddress
                ]
            );
        } catch (Exception $e) {
            // Log to file if database logging fails
            error_log("Activity log failed: " . $e->getMessage());
        }
    }
    
    /**
     * Update user profile
     */
    public function updateProfile($data) {
        $allowedFields = ['first_name', 'last_name', 'phone', 'address_line1', 'address_line2', 'city', 'state', 'postal_code'];
        $updates = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $updates[] = "{$field} = :{$field}";
            }
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $updates[] = 'updated_at = NOW()';
        
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE user_id = :user_id";
        
        $params = array_merge($data, ['user_id' => $this->userId]);
        
        return $this->db->execute($sql, $params) > 0;
    }
    
    /**
     * Change password
     */
    public function changePassword($oldPassword, $newPassword) {
        if (strlen($newPassword) < 8) {
            throw new InvalidArgumentException('New password must be at least 8 characters');
        }
        
        $user = $this->db->fetchOne(
            "SELECT password_hash FROM users WHERE user_id = :user_id",
            ['user_id' => $this->userId]
        );
        
        if (!password_verify($oldPassword, $user['password_hash'])) {
            throw new InvalidArgumentException('Current password is incorrect');
        }
        
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        
        return $this->db->execute(
            "UPDATE users SET password_hash = :password_hash WHERE user_id = :user_id",
            ['password_hash' => $newHash, 'user_id' => $this->userId]
        ) > 0;
    }
    
    /**
     * Send email verification using the new VerificationService
     * 
     * @param int $userId User ID
     * @param string $email User email
     * @param string $firstName User first name
     * @return array Result with success status and message
     */
    public static function sendVerification($userId, $email, $firstName) {
        try {
            require_once __DIR__ . '/../services/VerificationService.php';
            $verificationService = new VerificationService();
            
            $result = $verificationService->sendSignupCode((int) $userId, $email, $firstName);
            
            if ($result['success']) {
                return ['success' => true, 'message' => 'Verification email sent successfully'];
            }
            
            return ['success' => false, 'message' => $result['error'] ?? 'Failed to send verification email'];
        } catch (Exception $e) {
            error_log("User sendVerification error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred. Please try again.'];
        }
    }
    
    /**
     * Verify user email using the new VerificationService
     * 
     * @param int $userId User ID
     * @param string $code Verification code
     * @return array Result with success status and message
     */
    public static function verifyEmail($userId, $code) {
        try {
            require_once __DIR__ . '/../services/VerificationService.php';
            $verificationService = new VerificationService();
            
            $result = $verificationService->verifyCode((int) $userId, $code, 'signup_verification');
            
            if ($result['success']) {
                // Update user's is_verified to TRUE
                $db = Database::getInstance();
                $db->execute(
                    "UPDATE users SET is_verified = TRUE, updated_at = NOW() WHERE user_id = :user_id",
                    ['user_id' => $userId]
                );
                
                return ['success' => true, 'message' => 'Email verified successfully'];
            }
            
            return ['success' => false, 'message' => $result['error'] ?? 'Invalid or expired verification code'];
        } catch (Exception $e) {
            error_log("User verifyEmail error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred. Please try again.'];
        }
    }
    
    /**
     * Change password using token (for password reset)
     */
    public static function changePasswordWithToken($newPassword, $userId) {
        if (strlen($newPassword) < 8) {
            throw new InvalidArgumentException('New password must be at least 8 characters');
        }
        
        $db = Database::getInstance();
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        
        return $db->execute(
            "UPDATE users SET password_hash = :password_hash, updated_at = NOW() WHERE user_id = :user_id",
            ['password_hash' => $newHash, 'user_id' => $userId]
        ) > 0;
    }
}
