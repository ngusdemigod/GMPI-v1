<?php
/**
 * Application Bootstrap
 * Handles initialization, autoloading, and core services
 */

function configureSessionSecurity(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
}

function startApplicationSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        configureSessionSecurity();
        session_start();
    }
}

startApplicationSession();

// Ensure error reporting is logical
error_reporting(E_ALL);
ini_set('display_errors', 0); // Hide in production
ini_set('log_errors', 1);
ini_set('error_log', 'php://stderr');

// Autoload classes
spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/config/' . $class . '.php',
        __DIR__ . '/models/' . $class . '.php'
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

/**
 * Load currency helper functions
 */
require_once __DIR__ . '/helpers/currency_helpers.php';
require_once __DIR__ . '/helpers/system_settings.php';

function hasPendingUserLoginVerification(): bool
{
    return isset($_SESSION['pending_login_verification_user_id']);
}

function isUserFullyAuthenticated(): bool
{
    return isset($_SESSION['user_id']) && !hasPendingUserLoginVerification();
}

function beginPendingUserLoginVerification(array $user): void
{
    session_regenerate_id(true);

    unset(
        $_SESSION['user_id'],
        $_SESSION['user_email'],
        $_SESSION['user_name'],
        $_SESSION['email'],
        $_SESSION['first_name'],
        $_SESSION['last_name']
    );

    $_SESSION['pending_login_verification_user_id'] = (int) $user['user_id'];
    $_SESSION['pending_login_verification_email'] = $user['email'];
    $_SESSION['pending_login_verification_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $_SESSION['pending_login_verification_first_name'] = $user['first_name'] ?? '';
    unset(
        $_SESSION['login_verification_code_sent'],
        $_SESSION['login_verification_failed_attempts'],
        $_SESSION['login_verification_lockout_until']
    );
}

function beginPendingSignupVerification(array $user): void
{
    $_SESSION['pending_verification_user_id'] = (int) $user['user_id'];
    $_SESSION['pending_verification_email'] = $user['email'];
}

function clearPendingSignupVerification(): void
{
    unset($_SESSION['pending_verification_user_id'], $_SESSION['pending_verification_email']);
}

function completeUserLoginVerification(array $user): void
{
    session_regenerate_id(true);

    unset(
        $_SESSION['pending_login_verification_user_id'],
        $_SESSION['pending_login_verification_email'],
        $_SESSION['pending_login_verification_name'],
        $_SESSION['pending_login_verification_first_name'],
        $_SESSION['login_verification_code_sent'],
        $_SESSION['login_verification_failed_attempts'],
        $_SESSION['login_verification_lockout_until']
    );

    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $_SESSION['email'] = $user['email'];
    $_SESSION['first_name'] = $user['first_name'] ?? '';
    $_SESSION['last_name'] = $user['last_name'] ?? '';
}

function requireVerifiedUser(): void
{
    if (hasPendingUserLoginVerification()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? 'index.php';
        header('Location: verify-email.php?mode=login');
        exit;
    }

    if (!isUserFullyAuthenticated()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? 'index.php';
        header('Location: login.php');
        exit;
    }
}

function redirectUserIfAuthenticated(): void
{
    if (hasPendingUserLoginVerification()) {
        header('Location: verify-email.php?mode=login');
        exit;
    }

    if (isUserFullyAuthenticated()) {
        header('Location: index.php');
        exit;
    }
}
