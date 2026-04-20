<?php
/**
 * Reset Password Handler (Admin)
 * Handles admin password reset with token validation
 * Church Financial Partnership System
 */

header('Content-Type: application/json');
ob_start();

require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/models/AdminPasswordReset.php';
require_once __DIR__ . '/../../src/models/User.php';

function respondJson(int $statusCode, array $payload): void
{
    if (ob_get_level() > 0) {
        ob_clean();
    }

    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson(405, ['success' => false, 'message' => 'Invalid request method']);
}

// Get the token and new password from the request
$token = trim($_POST['token'] ?? '');
$newPassword = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (empty($token)) {
    respondJson(422, ['success' => false, 'message' => 'Invalid reset link']);
}

if (empty($newPassword)) {
    respondJson(422, ['success' => false, 'message' => 'Please enter a new password']);
}

if (strlen($newPassword) < 8) {
    respondJson(422, ['success' => false, 'message' => 'Password must be at least 8 characters']);
}

if ($newPassword !== $confirmPassword) {
    respondJson(422, ['success' => false, 'message' => 'Passwords do not match']);
}

try {
    $adminPasswordReset = new AdminPasswordReset();
    
    // Validate the token
    $resetData = $adminPasswordReset->validateToken($token);
    
    if (!$resetData) {
        respondJson(422, ['success' => false, 'message' => 'Invalid or expired reset link. Please request a new one.']);
    }
    
    // Update the admin's password
    $db = Database::getInstance();
    $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    
    $result = $db->execute(
        "UPDATE users SET password_hash = :password_hash, updated_at = NOW() WHERE user_id = :user_id",
        ['password_hash' => $newHash, 'user_id' => $resetData['user_id']]
    );
    
    if ($result) {
        // Mark the token as used
        $adminPasswordReset->markTokenAsUsed($token);
        
        // Update last_password_reset
        $db->execute(
            "UPDATE admin_users SET last_password_reset = NOW() WHERE admin_id = :admin_id",
            ['admin_id' => $resetData['admin_id']]
        );
        
        // Log the password change
        $db->execute(
            "INSERT INTO admin_audit_logs (admin_id, action_type, target_type, target_name, ip_address) 
             VALUES (:admin_id, :action, :target, :name, :ip)",
            [
                'admin_id' => $resetData['admin_id'],
                'action' => 'PASSWORD_RESET_SUCCESS',
                'target' => 'admin_user',
                'name' => $resetData['email'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]
        );
        
        respondJson(200, ['success' => true, 'message' => 'Your password has been reset successfully. You can now log in with your new password.']);
    } else {
        respondJson(500, ['success' => false, 'message' => 'Failed to reset password. Please try again.']);
    }
    
} catch (Exception $e) {
    error_log("Admin reset password error: " . $e->getMessage());
    respondJson(500, ['success' => false, 'message' => 'An error occurred. Please try again later.']);
}
