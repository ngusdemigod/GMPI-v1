<?php
/**
 * Create admin user: igbaniangus@gmail.com
 * Password: password123
 */

require_once __DIR__ . '/src/bootstrap.php';

$email = 'igbaniangus@gmail.com';
$password = 'password123';
$firstName = 'Igbanian';
$lastName = 'Gus';

try {
    $db = Database::getInstance();
    
    // Check if user already exists
    $existing = $db->fetchOne('SELECT user_id FROM users WHERE email = :email', ['email' => $email]);
    
    if ($existing) {
        echo "User already exists with ID: " . $existing['user_id'] . "\n";
        $userId = $existing['user_id'];
    } else {
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        // Insert user with is_verified = TRUE and is_active = TRUE
        $db->execute(
            "INSERT INTO users (email, password_hash, first_name, last_name, is_verified, is_active) 
             VALUES (:email, :password_hash, :first_name, :last_name, TRUE, TRUE)",
            [
                'email' => $email,
                'password_hash' => $passwordHash,
                'first_name' => $firstName,
                'last_name' => $lastName
            ]
        );
        
        $userId = $db->lastInsertId();
        echo "Created user with ID: $userId\n";
    }
    
    // Check if admin entry exists
    $existingAdmin = $db->fetchOne('SELECT admin_id FROM admin_users WHERE user_id = :user_id', ['user_id' => $userId]);
    
    if (!$existingAdmin) {
        // Generate JWT secret
        $jwtSecret = bin2hex(random_bytes(32));
        
        // Create admin entry
        $db->execute(
            "INSERT INTO admin_users (user_id, jwt_secret) VALUES (:user_id, :jwt_secret)",
            ['user_id' => $userId, 'jwt_secret' => $jwtSecret]
        );
        
        $adminId = $db->lastInsertId();
        echo "Created admin entry with ID: $adminId\n";
    } else {
        echo "Admin entry already exists\n";
    }
    
    // Assign admin role
    $adminRole = $db->fetchOne("SELECT role_id FROM user_roles WHERE role_name = 'admin'");
    if ($adminRole) {
        $existingAssignment = $db->fetchOne(
            "SELECT assignment_id FROM user_role_assignments WHERE user_id = :user_id AND role_id = :role_id",
            ['user_id' => $userId, 'role_id' => $adminRole['role_id']]
        );
        
        if (!$existingAssignment) {
            $db->execute(
                "INSERT INTO user_role_assignments (user_id, role_id) VALUES (:user_id, :role_id)",
                ['user_id' => $userId, 'role_id' => $adminRole['role_id']]
            );
            echo "Assigned admin role\n";
        } else {
            echo "Admin role already assigned\n";
        }
    }
    
    echo "\nAdmin user created successfully!\n";
    echo "Email: $email\n";
    echo "Password: $password\n";
    echo "You can now log in at: /admin/login.php\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}