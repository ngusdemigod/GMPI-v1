<?php
/**
 * Admin 2FA Verification Handler
 * Handles 2FA code verification for admin login
 * Church Financial Partnership System
 */

require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/resend_config.php';
require_once __DIR__ . '/../../src/models/Admin2FA.php';
require_once __DIR__ . '/../../src/helpers/EmailService.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'send_code':
        send2FACode();
        break;
    case 'verify_code':
        verify2FACode();
        break;
    case 'enable_2fa':
        enable2FA();
        break;
    case 'disable_2fa':
        disable2FA();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Send 2FA code to admin email
 */
function send2FACode() {
    $email = trim($_POST['email'] ?? '');
    $adminId = $_POST['admin_id'] ?? '';
    
    if (empty($email) || empty($adminId)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    try {
        $db = Database::getInstance();
        
        // Get admin info
        $admin = $db->fetchOne(
            "SELECT au.admin_id, u.email, u.first_name, u.last_name 
             FROM admin_users au
             INNER JOIN users u ON au.user_id = u.user_id
             WHERE au.admin_id = :admin_id AND u.email = :email",
            ['admin_id' => $adminId, 'email' => $email]
        );
        
        if (!$admin) {
            echo json_encode(['success' => false, 'message' => 'Admin not found']);
            exit;
        }
        
        // Generate and store 2FA code
        $admin2FA = new Admin2FA();
        $code = $admin2FA->storeCode($admin['admin_id'], $admin2FA->generateCode());
        
        // Send email with code
        $emailService = new EmailService();
        $emailResult = $emailService->send2FACode(
            $admin['email'],
            $admin['email'],
            $code,
            $admin['first_name'] . ' ' . $admin['last_name']
        );
        
        if ($emailResult['success']) {
            echo json_encode(['success' => true, 'message' => 'Verification code sent to your email']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send verification code']);
        }
        
    } catch (Exception $e) {
        error_log("2FA send code error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
    }
}

/**
 * Verify 2FA code
 */
function verify2FACode() {
    $adminId = $_POST['admin_id'] ?? '';
    $code = trim($_POST['code'] ?? '');
    
    if (empty($adminId) || empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    try {
        $admin2FA = new Admin2FA();
        
        if ($admin2FA->validateCode($adminId, $code)) {
            // Start admin session
            session_start();
            $_SESSION['admin_id'] = $adminId;
            $_SESSION['admin_2fa_verified'] = true;
            $_SESSION['admin_2fa_verified_at'] = time();
            
            echo json_encode(['success' => true, 'message' => 'Verification successful']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code']);
        }
        
    } catch (Exception $e) {
        error_log("2FA verify code error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
    }
}

/**
 * Enable 2FA for admin
 */
function enable2FA() {
    $adminId = $_POST['admin_id'] ?? '';
    
    if (empty($adminId)) {
        echo json_encode(['success' => false, 'message' => 'Missing admin ID']);
        exit;
    }
    
    try {
        $admin2FA = new Admin2FA();
        $secret = bin2hex(random_bytes(32));
        
        $admin2FA->enable2FA($adminId, $secret);
        
        echo json_encode(['success' => true, 'message' => '2FA enabled successfully']);
        
    } catch (Exception $e) {
        error_log("2FA enable error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
    }
}

/**
 * Disable 2FA for admin
 */
function disable2FA() {
    $adminId = $_POST['admin_id'] ?? '';
    
    if (empty($adminId)) {
        echo json_encode(['success' => false, 'message' => 'Missing admin ID']);
        exit;
    }
    
    try {
        $admin2FA = new Admin2FA();
        $admin2FA->disable2FA($adminId);
        
        echo json_encode(['success' => true, 'message' => '2FA disabled successfully']);
        
    } catch (Exception $e) {
        error_log("2FA disable error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
    }
}