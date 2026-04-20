<?php
/**
 * AuthMiddleware
 * Middleware for authentication and authorization checks
 */

class AuthMiddleware
{
    /**
     * Require user to be logged in
     * Redirects to login if not authenticated
     */
    public static function requireLogin(): void
    {
        if (isset($_SESSION['pending_login_verification_user_id'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: /verify-email.php?mode=login');
            exit;
        }

        if (!isset($_SESSION['user_id'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: /login.php');
            exit;
        }
    }

    /**
     * Require user to be an authenticated admin
     * Redirects to admin login if not authenticated
     */
    public static function requireAdmin(): void
    {
        if (!isset($_SESSION['admin_authenticated']) || !isset($_SESSION['admin_user_id'])) {
            header('Location: /admin/login.php');
            exit;
        }

        // Verify admin session is still valid
        try {
            $db = Database::getInstance();
            $user = $db->fetchOne(
                'SELECT u.user_id, u.is_active, u.is_verified 
                 FROM users u 
                 INNER JOIN admin_users au ON u.user_id = au.user_id 
                 WHERE u.user_id = :user_id',
                ['user_id' => $_SESSION['admin_user_id']]
            );

            if (!$user || !$user['is_active'] || !$user['is_verified']) {
                // Clear invalid session
                session_destroy();
                header('Location: /admin/login.php');
                exit;
            }
        } catch (Exception $e) {
            error_log('Admin middleware check failed: ' . $e->getMessage());
            header('Location: /admin/login.php');
            exit;
        }
    }

    /**
     * Require admin to have completed 2FA verification
     * This is used on pages that require full admin authentication
     */
    public static function requireAdminVerified(): void
    {
        // Check for full admin session (post-2FA)
        if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
            // Check if they're in the pending 2FA state
            if (isset($_SESSION['pending_admin_2fa_user_id'])) {
                header('Location: /admin/admin-verify.php');
            } else {
                header('Location: /admin/login.php');
            }
            exit;
        }

        // Verify the admin session hasn't expired (24 hour max session)
        if (isset($_SESSION['admin_login_time'])) {
            $maxSessionTime = 24 * 60 * 60; // 24 hours
            if (time() - $_SESSION['admin_login_time'] > $maxSessionTime) {
                // Session expired, clear and redirect
                unset($_SESSION['admin_authenticated']);
                unset($_SESSION['admin_user_id']);
                unset($_SESSION['admin_login_time']);
                header('Location: /admin/login.php');
                exit;
            }
        }
    }

    /**
     * Check if user is logged in (returns boolean)
     */
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']) && !isset($_SESSION['pending_login_verification_user_id']);
    }

    /**
     * Check if admin is authenticated (returns boolean)
     */
    public static function isAdminAuthenticated(): bool
    {
        return isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
    }

    /**
     * Get current user ID (returns null if not logged in)
     */
    public static function getCurrentUserId(): ?int
    {
        return self::isLoggedIn() ? (int) $_SESSION['user_id'] : null;
    }

    /**
     * Get current admin user ID (returns null if not authenticated)
     */
    public static function getCurrentAdminUserId(): ?int
    {
        return isset($_SESSION['admin_user_id']) ? (int) $_SESSION['admin_user_id'] : null;
    }

    /**
     * Logout current user
     */
    public static function logout(): void
    {
        session_regenerate_id(true);
        $_SESSION = [];
        
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
    }
}
