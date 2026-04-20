<?php
/**
 * Admin Dashboard Configuration
 * Church Financial Partnership System
 */

function configureAdminSessionSecurity(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
}

function startAdminSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        configureAdminSessionSecurity();
        session_start();
    }
}

startAdminSession();

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php://stderr');

// Timezone
date_default_timezone_set('UTC');

// ============================================
// DATABASE CONFIGURATION
// ============================================
define('DB_HOST', getenv('DB_HOST') ?: 'mysql');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'church_partnership');
define('DB_USER', getenv('DB_USER') ?: 'church_user');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'church_password123');

// ============================================
// JWT CONFIGURATION
// ============================================
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'admin-jwt-secret-key-change-in-production-' . md5(uniqid()));
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRY_SECONDS', 86400); // 24 hours
define('JWT_REFRESH_EXPIRY_SECONDS', 604800); // 7 days

// ============================================
// SESSION CONFIGURATION
// ============================================
define('SESSION_LIFETIME', 86400); // 24 hours
define('SESSION_REFRESH_INTERVAL', 3600); // Refresh every hour

// ============================================
// SECURITY CONFIGURATION
// ============================================
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes
define('CSRF_TOKEN_LENGTH', 32);

// ============================================
// ADMIN CONFIGURATION
// ============================================
define('ADMIN_PATH', __DIR__ . '/../');
// Dynamic Base URL detection for better subfolder support
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';
$base_path = strpos($script_name, '/admin/') !== false ? substr($script_name, 0, strpos($script_name, '/admin/')) : '';
define('ADMIN_URL', $base_path . '/admin');
define('UPLOAD_MAX_SIZE', 5242880); // 5MB

// ============================================
// PERMISSION TIERS
// ============================================
define('PERMISSIONS', [
    'super_admin' => [
        'dashboard', 'users', 'projects', 'transactions', 
        'plans', 'partners', 'admin_members', 'settings', 
        'audit_logs', 'reports', 'export'
    ],
    'project_admin' => [
        'dashboard', 'projects', 'transactions', 'reports'
    ],
    'finance_admin' => [
        'dashboard', 'users', 'transactions', 'plans', 
        'partners', 'reports', 'export'
    ]
]);

// ============================================
// PAGINATION
// ============================================
define('ITEMS_PER_PAGE', 25);
define('RECENT_TRANSACTIONS_LIMIT', 10);
define('RECENT_USERS_LIMIT', 10);

// ============================================
// AUTOLOAD CLASSES
// ============================================
spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../models/' . $class . '.php',
        __DIR__ . '/models/' . $class . '.php',
        __DIR__ . '/../includes/' . $class . '.php'
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// ============================================
// DATABASE CONNECTION
// ============================================
require_once __DIR__ . '/database.php';

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Generate a random string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateRandomString(CSRF_TOKEN_LENGTH);
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Redirect to a URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Escape HTML output
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency
 */
function formatCurrency($amount, $currency = 'USD') {
    $currency = strtoupper(trim((string) $currency)) ?: 'USD';

    $labels = [
        'USD' => '$',
        'NGN' => 'N',
    ];

    $prefix = $labels[$currency] ?? ($currency . ' ');

    return $prefix . number_format((float) $amount, 2);
}

/**
 * Format currency specifically for admin metric cards.
 */
function formatMetricCurrency($amount, $currency = 'USD') {
    $currency = strtoupper(trim((string) $currency)) ?: 'USD';

    $labels = [
        'USD' => '$',
        'NGN' => 'N',
    ];

    $prefix = $labels[$currency] ?? $currency;
    $formatted = number_format((float) $amount, 2);

    return '<span class="metric-currency-symbol">' . e($prefix) . '</span><span class="metric-number">' . e($formatted) . '</span>';
}

/**
 * Format date
 */
function formatDate($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

/**
 * Get admin user from session
 */
function getAdminUser() {
    $adminId = $_SESSION['admin_id'] ?? null;

    if ($adminId) {
        $db = Database::getInstance();
        return $db->fetchOne(
            "SELECT au.*, u.email, u.first_name, u.last_name, u.phone 
             FROM admin_users au
             INNER JOIN users u ON au.user_id = u.user_id
             WHERE au.admin_id = :admin_id",
            ['admin_id' => $adminId]
        );
    }
    return null;
}

/**
 * Check if user is admin
 */
function requireAdmin() {
    if (isset($_SESSION['pending_admin_2fa_user_id'])) {
        redirect(ADMIN_URL . '/admin-verify.php');
    }

    if (
        !isset($_SESSION['admin_authenticated']) ||
        $_SESSION['admin_authenticated'] !== true ||
        !isset($_SESSION['admin_id']) ||
        !isset($_SESSION['admin_user_id'])
    ) {
        redirect(ADMIN_URL . '/login.php');
    }

    $admin = getAdminUser();
    if (!$admin) {
        redirect(ADMIN_URL . '/login.php');
    }

    return $admin;
}

/**
 * Check if admin has permission
 */
function hasPermission($permission) {
    $admin = getAdminUser();
    if (!$admin) return false;
    
    $db = Database::getInstance();
    $roles = $db->fetchAll(
        "SELECT ur.role_name, ur.permissions 
         FROM user_roles ur
         INNER JOIN user_role_assignments ura ON ur.role_id = ura.role_id
         WHERE ura.user_id = :user_id",
        ['user_id' => $admin['user_id']]
    );
    
    foreach ($roles as $role) {
        $permissions = json_decode($role['permissions'], true);
        if (isset($permissions[$permission]) && $permissions[$permission]) {
            return true;
        }
    }
    
    return false;
}

/**
 * Log admin action
 */
function logAdminAction($adminId, $actionType, $targetType = null, $targetId = null, $targetName = null, $changes = null) {
    $db = Database::getInstance();
    
    try {
        $db->execute(
            "INSERT INTO admin_audit_logs (admin_id, action_type, target_type, target_id, target_name, changes, ip_address, user_agent)
             VALUES (:admin_id, :action_type, :target_type, :target_id, :target_name, :changes, :ip, :user_agent)",
            [
                'admin_id' => $adminId,
                'action_type' => $actionType,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'target_name' => $targetName,
                'changes' => $changes ? json_encode($changes) : null,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]
        );
    } catch (Exception $e) {
        error_log("Audit log failed: " . $e->getMessage());
    }
}

/**
 * Sanitize input
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Get exchange rate (USD to NGN)
 * In production, this should fetch from a real API
 */
function getExchangeRate() {
    // Default exchange rate - can be configured or fetched from API
    return 1500.00; // 1 USD = 1500 NGN (example rate)
}

/**
 * Convert USD to NGN
 */
function usdToNgn($usdAmount) {
    $exchangeRate = getExchangeRate();
    return $usdAmount * $exchangeRate;
}
