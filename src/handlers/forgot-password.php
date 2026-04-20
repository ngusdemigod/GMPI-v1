<?php
/**
 * Forgot Password Handler (User)
 * Handles password reset requests
 * Church Financial Partnership System
 */

// Set JSON header first
header('Content-Type: application/json');

// Start output buffering to catch any accidental output
ob_start();
ini_set('log_errors', 1);
ini_set('error_log', 'php://stderr');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/resend_config.php';
require_once __DIR__ . '/../models/PasswordReset.php';
require_once __DIR__ . '/../helpers/EmailService.php';

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

// Get the email from the request
$email = trim($_POST['email'] ?? '');

if (empty($email)) {
    respondJson(422, ['success' => false, 'message' => 'Please enter your email address']);
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respondJson(422, ['success' => false, 'message' => 'Please enter a valid email address']);
}

try {
    $db = Database::getInstance();
    
    // Check if user exists with this email
    $user = $db->fetchOne(
        "SELECT user_id, email, first_name, last_name FROM users WHERE email = :email AND is_active = TRUE",
        ['email' => $email]
    );
    
    // Always return success to prevent email enumeration
    $successMessage = 'If an account exists with that email, a password reset link will be sent if delivery succeeds.';
    
    if ($user) {
        error_log(sprintf('[UserForgotPassword] account found userId=%d email=%s', $user['user_id'], $user['email']));
        // Create password reset token
        $passwordReset = new PasswordReset();
        $resetData = $passwordReset->createResetToken($user['user_id']);
        
        // Generate reset link
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $scriptDirectory = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/src/handlers/forgot-password.php'));
        $basePath = rtrim(str_replace('\\', '/', $scriptDirectory), '/');
        $resetLink = sprintf(
            '%s://%s%s/reset-password.php?token=%s',
            $scheme,
            $_SERVER['HTTP_HOST'],
            $basePath,
            urlencode($resetData['token'])
        );
        
        // Send email using Resend
        $emailService = new EmailService();
        
        $emailResult = $emailService->sendPasswordResetEmail(
            $resetData['email'],
            $resetData['email'],
            $resetLink,
            $user['first_name'] . ' ' . $user['last_name']
        );
        
        if ($emailResult['success']) {
            error_log(sprintf('[UserForgotPassword] reset email sent userId=%d email=%s', $user['user_id'], $user['email']));
            // Log the password reset request without breaking the response.
            try {
                $db->execute(
                    "INSERT INTO system_logs (user_id, log_level, log_message, ip_address) 
                     VALUES (:user_id, :level, :message, :ip)",
                    [
                        'user_id' => $user['user_id'],
                        'level' => 'INFO',
                        'message' => 'Password reset requested',
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]
                );
            } catch (Throwable $logError) {
                error_log('[UserForgotPassword] failed to write system log: ' . $logError->getMessage());
            }
        } else {
            error_log(sprintf('[UserForgotPassword] reset email failed userId=%d email=%s message=%s', $user['user_id'], $user['email'], $emailResult['message'] ?? 'unknown'));

            $host = $_SERVER['HTTP_HOST'] ?? '';
            $isLocalHost = preg_match('/(^|\.)localhost(?::\d+)?$/i', $host) || preg_match('/(^|\.)127\.0\.0\.1(?::\d+)?$/', $host);

            if ($isLocalHost) {
                respondJson(200, [
                    'success' => true,
                    'message' => 'Email delivery failed in the current environment. Use the reset link below to continue locally.',
                    'reset_link' => $resetLink,
                ]);
            }
        }
    }
    
    respondJson(200, ['success' => true, 'message' => $successMessage]);
    
} catch (Throwable $e) {
    error_log("Forgot password error: " . $e->getMessage());
    respondJson(500, ['success' => false, 'message' => 'An error occurred. Please try again later.']);
}
