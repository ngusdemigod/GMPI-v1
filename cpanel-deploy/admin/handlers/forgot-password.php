<?php
/**
 * Forgot Password Handler (Admin)
 * Handles admin password reset requests
 * Church Financial Partnership System
 */

header('Content-Type: application/json');

// Start output buffering to prevent accidental output from corrupting JSON
ob_start();
ini_set('log_errors', 1);
ini_set('error_log', 'php://stderr');

require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/resend_config.php';
require_once __DIR__ . '/../../src/models/AdminPasswordReset.php';
require_once __DIR__ . '/../../src/helpers/EmailService.php';

function respondJson(int $statusCode, array $payload): void
{
    if (ob_get_level() > 0) {
        ob_clean();
    }

    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function isLocalDevelopmentRequest(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $serverName = strtolower((string) ($_SERVER['SERVER_NAME'] ?? ''));
    $candidates = [$host, $serverName];

    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }

        if (
            str_contains($candidate, 'localhost') ||
            str_contains($candidate, '127.0.0.1') ||
            str_contains($candidate, '::1')
        ) {
            return true;
        }
    }

    return false;
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
    
    // Check if admin exists with this email
    $admin = $db->fetchOne(
        "SELECT au.admin_id, u.email, u.first_name, u.last_name 
         FROM admin_users au
         INNER JOIN users u ON au.user_id = u.user_id
         WHERE u.email = :email AND u.is_active = TRUE",
        ['email' => $email]
    );
    
    // Always return a generic success when no account exists to avoid enumeration.
    $successMessage = 'If an admin account exists with that email, a password reset link will be sent if delivery succeeds.';
    
    if ($admin) {
        error_log(sprintf('[AdminForgotPassword] account found adminId=%d email=%s', $admin['admin_id'], $admin['email']));
        // Create password reset token
        $adminPasswordReset = new AdminPasswordReset();
        $resetData = $adminPasswordReset->createResetToken($admin['admin_id']);
        
        // Generate reset link
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $scriptDirectory = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/handlers/forgot-password.php'));
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
        
        $emailResult = $emailService->sendAdminPasswordResetEmail(
            $resetData['email'],
            $resetData['email'],
            $resetLink,
            $admin['first_name'] . ' ' . $admin['last_name']
        );
        
        if ($emailResult['success']) {
            error_log(sprintf('[AdminForgotPassword] reset email sent adminId=%d email=%s', $admin['admin_id'], $admin['email']));
            // Log the password reset request without breaking the response.
            try {
                $db->execute(
                    "INSERT INTO admin_audit_logs (admin_id, action_type, target_type, target_name, ip_address) 
                     VALUES (:admin_id, :action, :target, :name, :ip)",
                    [
                        'admin_id' => $admin['admin_id'],
                        'action' => 'PASSWORD_RESET_REQUESTED',
                        'target' => 'admin_user',
                        'name' => $admin['first_name'] . ' ' . $admin['last_name'],
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]
                );
            } catch (Throwable $logError) {
                error_log('[AdminForgotPassword] failed to write audit log: ' . $logError->getMessage());
            }

            respondJson(200, ['success' => true, 'message' => 'Password reset email sent. Check your inbox for the admin reset link.']);
        } else {
            error_log(sprintf('[AdminForgotPassword] reset email failed adminId=%d email=%s message=%s', $admin['admin_id'], $admin['email'], $emailResult['message'] ?? 'unknown'));

            if (isLocalDevelopmentRequest()) {
                respondJson(200, [
                    'success' => true,
                    'message' => 'Email delivery failed in the current environment. Use the reset link below to continue locally.',
                    'reset_link' => $resetLink,
                    'delivery_failed' => true
                ]);
            }

            respondJson(503, [
                'success' => false,
                'message' => 'Unable to send the admin password reset email right now. Please try again later or contact support.'
            ]);
        }
    }
    
    respondJson(200, ['success' => true, 'message' => $successMessage]);
    
} catch (Throwable $e) {
    error_log("Admin forgot password error: " . $e->getMessage());
    respondJson(500, ['success' => false, 'message' => 'An error occurred. Please try again later.']);
}
