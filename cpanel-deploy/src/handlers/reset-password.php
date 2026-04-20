<?php
/**
 * Reset Password Handler (User)
 * Handles password reset with token validation
 * Church Financial Partnership System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/PasswordReset.php';
require_once __DIR__ . '/../models/User.php';

header('Content-Type: application/json');
ob_start();

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
    $passwordReset = new PasswordReset();
    
    // Validate the token
    $resetData = $passwordReset->validateToken($token);
    
    if (!$resetData) {
        respondJson(422, ['success' => false, 'message' => 'Invalid or expired reset link. Please request a new one.']);
    }
    
    // Update the user's password
    $user = new User();
    $result = $user->changePasswordWithToken($newPassword, $resetData['user_id']);
    
    if ($result) {
        // Mark the token as used
        $passwordReset->markTokenAsUsed($token);
        
        // Log the password change
        $db = Database::getInstance();
        $db->execute(
            "INSERT INTO system_logs (user_id, log_level, log_message, ip_address) 
             VALUES (:user_id, :level, :message, :ip)",
            [
                'user_id' => $resetData['user_id'],
                'level' => 'INFO',
                'message' => 'Password reset successfully',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]
        );
        
        respondJson(200, ['success' => true, 'message' => 'Your password has been reset successfully. You can now log in with your new password.']);
    } else {
        respondJson(500, ['success' => false, 'message' => 'Failed to reset password. Please try again.']);
    }
    
} catch (Exception $e) {
    error_log("Reset password error: " . $e->getMessage());
    respondJson(500, ['success' => false, 'message' => 'An error occurred. Please try again later.']);
}
