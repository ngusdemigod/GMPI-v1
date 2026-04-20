<?php
/**
 * Create Admin User Script
 * Creates a new admin user with the specified credentials
 */

// Define database constants directly
define('DB_HOST', getenv('DB_HOST') ?: 'mysql');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'church_partnership');
define('DB_USER', getenv('DB_USER') ?: 'church_user');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'church_password123');

require_once __DIR__ . '/admin/includes/database.php';

// Configuration
$email = 'info@brightlightministry.org.ng';
$password = 'Brightlight@2026';
$firstName = 'Bright';
$lastName = 'Light';
$phone = '+234 800 BRIGHT';

try {
    $db = Database::getInstance();
    
    // Check if user already exists
    $existingUser = $db->fetchOne(
        "SELECT user_id FROM users WHERE email = :email",
        ['email' => $email]
    );
    
    if ($existingUser) {
        echo "User with email $email already exists (user_id: {$existingUser['user_id']})\n";
        $userId = $existingUser['user_id'];
    } else {
        // Create new user
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        $db->execute(
            "INSERT INTO users (email, password_hash, first_name, last_name, phone, is_active, is_verified)
             VALUES (:email, :password_hash, :first_name, :last_name, :phone, TRUE, TRUE)",
            [
                'email' => $email,
                'password_hash' => $passwordHash,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone
            ]
        );
        
        $userId = $db->lastInsertId();
        echo "New user created with user_id: $userId\n";
    }
    
    // Check if admin user already exists
    $existingAdmin = $db->fetchOne(
        "SELECT admin_id FROM admin_users WHERE user_id = :user_id",
        ['user_id' => $userId]
    );
    
    if ($existingAdmin) {
        echo "Admin user already exists for this account (admin_id: {$existingAdmin['admin_id']})\n";
    } else {
        // Generate JWT secret and other admin fields
        $jwtSecret = bin2hex(random_bytes(32));
        
        $db->execute(
            "INSERT INTO admin_users (user_id, jwt_secret, two_factor_enabled)
             VALUES (:user_id, :jwt_secret, FALSE)",
            [
                'user_id' => $userId,
                'jwt_secret' => $jwtSecret
            ]
        );
        
        $adminId = $db->lastInsertId();
        echo "Admin user created with admin_id: $adminId\n";
        echo "JWT Secret: $jwtSecret\n";
    }
    
    // Assign admin role
    $adminRole = $db->fetchOne("SELECT role_id FROM user_roles WHERE role_name = 'admin'");
    
    if ($adminRole) {
        // Use INSERT ... ON DUPLICATE KEY UPDATE to avoid errors if already assigned
        $db->execute(
            "INSERT INTO user_role_assignments (user_id, role_id)
             VALUES (:user_id, :role_id)
             ON DUPLICATE KEY UPDATE role_id = VALUES(role_id)",
            [
                'user_id' => $userId,
                'role_id' => $adminRole['role_id']
            ]
        );
        echo "Admin role assigned to user\n";
    } else {
        echo "Warning: Admin role not found in user_roles table\n";
    }
    
    echo "\n========================================\n";
    echo "Admin user created successfully!\n";
    echo "========================================\n";
    echo "Email: $email\n";
    echo "Password: $password\n";
    echo "User ID: $userId\n";
    echo "Admin ID: " . ($existingAdmin ? $existingAdmin['admin_id'] : $adminId) . "\n";
    echo "========================================\n";
    echo "\nYou can now login at: http://localhost/admin/login.php\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}