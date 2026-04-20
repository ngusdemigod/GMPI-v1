<?php
/**
 * Admin Logout
 * Church Financial Partnership System
 */

require_once __DIR__ . '/includes/config.php';

// Get session ID before destroying
$sessionId = $_SESSION['admin_session_id'] ?? null;

// Invalidate session in database
if ($sessionId) {
    Auth::invalidateSession($sessionId);
}

// Clear session
session_unset();
session_destroy();

// Log logout
$admin = getAdminUser();
if ($admin) {
    logAdminAction($admin['admin_id'], 'LOGOUT', 'admin', $admin['admin_id'], $admin['email']);
}

// Redirect to login
redirect(ADMIN_URL . '/login.php');