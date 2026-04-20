<?php
/**
 * Create Admin User Script
 * Run this once to create the initial admin account
 * Usage: docker-compose exec php php create_admin.php
 */

require_once __DIR__ . '/config/database.php';

$email = 'admin@gracecathedral.org';
$password = 'admin123'; // Change this after first login
$firstName = 'Admin';
$lastName = 'User';
$phone = '555-0000';

try {
    $db = Database::getInstance();
    
    // Check if user already exists
    $existing = $db->fetchOne(
        "SELECT user_id FROM users WHERE email = :email",
        ['email' => $email]
    );
    
    if ($existing) {
        echo "User already exists with ID: " . $existing['user_id'] . "\n";
        $userId = $existing['user_id'];
    } else {
        // Hash the password
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        // Insert user
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
        echo "User created with ID: " . $userId . "\n";
    }
    
    // Check if admin record exists
    $adminExists = $db->fetchOne(
        "SELECT admin_id FROM admin_users WHERE user_id = :user_id",
        ['user_id' => $userId]
    );
    
    if (!$adminExists) {
        // Generate JWT secret
        $jwtSecret = 'admin-jwt-secret-key-change-in-production-' . md5(uniqid());
        
        // Create admin record
        $db->execute(
            "INSERT INTO admin_users (user_id, jwt_secret) VALUES (:user_id, :jwt_secret)",
            ['user_id' => $userId, 'jwt_secret' => $jwtSecret]
        );
        echo "Admin record created\n";
    } else {
        echo "Admin record already exists\n";
    }
    
    // Assign admin role
    $adminRole = $db->fetchOne("SELECT role_id FROM user_roles WHERE role_name = 'admin'");
    if ($adminRole) {
        $db->execute(
            "INSERT INTO user_role_assignments (user_id, role_id) VALUES (:user_id, :role_id)
             ON DUPLICATE KEY UPDATE role_id = :role_id",
            ['user_id' => $userId, 'role_id' => $adminRole['role_id']]
        );
        echo "Admin role assigned\n";
    }
    
    echo "\n========================================\n";
    echo "Admin account created successfully!\n";
    echo "Email: $email\n";
    echo "Password: $password\n";
    echo "========================================\n";
    echo "\nIMPORTANT: Change the password after first login!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>