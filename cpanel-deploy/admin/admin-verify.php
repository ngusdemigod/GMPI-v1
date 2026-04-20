<?php
/**
 * Admin Login Verification Page (2FA via Email)
 * Second step of admin login - verifies email code before granting dashboard access
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/../src/services/VerificationService.php';

$verificationService = new VerificationService();
$errorMessage = '';
$successMessage = '';
$showForm = true;

if (isset($_GET['cancel']) && $_GET['cancel'] === '1') {
    unset(
        $_SESSION['pending_admin_2fa_user_id'],
        $_SESSION['pending_admin_2fa_email'],
        $_SESSION['pending_admin_2fa_first_name'],
        $_SESSION['pending_admin_2fa_admin_id'],
        $_SESSION['admin_2fa_code_sent'],
        $_SESSION['admin_2fa_expires'],
        $_SESSION['admin_2fa_failed_attempts'],
        $_SESSION['admin_2fa_lockout_until']
    );

    header('Location: login.php');
    exit;
}

// Check if user has completed password authentication
if (!isset($_SESSION['pending_admin_2fa_user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['pending_admin_2fa_user_id'];
$email = $_SESSION['pending_admin_2fa_email'] ?? '';
$firstName = $_SESSION['pending_admin_2fa_first_name'] ?? '';
$adminId = (int) ($_SESSION['pending_admin_2fa_admin_id'] ?? 0);

// Check if this is the first visit (send code)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_SESSION['admin_2fa_code_sent'])) {
    try {
        $result = $verificationService->sendAdminLoginCode($userId, $email, $firstName);

        if ($result['success']) {
            $_SESSION['admin_2fa_code_sent'] = true;
            $_SESSION['admin_2fa_expires'] = $result['expires_in_minutes'];
        } else {
            $errorMessage = $result['error'];
        }
    } catch (Exception $e) {
        error_log('Admin 2FA send error: ' . $e->getMessage());
        $errorMessage = 'Failed to send verification code. Please try again.';
    }
}

// Handle resend request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resend') {
    try {
        $result = $verificationService->sendAdminLoginCode($userId, $email, $firstName);

        if ($result['success']) {
            $successMessage = $result['message'];
            $_SESSION['admin_2fa_code_sent'] = true;
        } else {
            $errorMessage = $result['error'];
        }
    } catch (Exception $e) {
        error_log('Admin 2FA resend error: ' . $e->getMessage());
        $errorMessage = 'An error occurred. Please try again.';
    }
}

// Handle code verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify') {
    $code = trim($_POST['code'] ?? '');

    if (empty($code) || !preg_match('/^\d{6}$/', $code)) {
        $errorMessage = 'Please enter a valid 6-digit code.';
    } else {
        // Check brute force - max 5 failed attempts
        $failedAttempts = $_SESSION['admin_2fa_failed_attempts'] ?? 0;
        $lockoutUntil = $_SESSION['admin_2fa_lockout_until'] ?? null;

        if ($lockoutUntil && time() < $lockoutUntil) {
            $remaining = ceil(($lockoutUntil - time()) / 60);
            $errorMessage = "Too many failed attempts. Please wait {$remaining} minutes.";
        } else {
            try {
                $result = $verificationService->verifyCode($userId, $code, 'admin_login_verification');

                if ($result['success']) {
                    // Verification successful - create full admin session
                    session_regenerate_id(true);

                    // Clear pending session
                    unset($_SESSION['pending_admin_2fa_user_id']);
                    unset($_SESSION['pending_admin_2fa_email']);
                    unset($_SESSION['pending_admin_2fa_first_name']);
                    unset($_SESSION['pending_admin_2fa_admin_id']);
                    unset($_SESSION['admin_2fa_code_sent']);
                    unset($_SESSION['admin_2fa_failed_attempts']);
                    unset($_SESSION['admin_2fa_lockout_until']);

                    // Create admin session
                    $_SESSION['admin_authenticated'] = true;
                    $_SESSION['admin_id'] = $adminId;
                    $_SESSION['admin_user_id'] = $userId;
                    $_SESSION['admin_login_time'] = time();

                    // Log successful login
                    $db = Database::getInstance();
                    $db->getConnection()->prepare(
                        'INSERT INTO admin_login_attempts (user_id, ip_address, attempt_type, success) 
                         VALUES (:user_id, :ip_address, "verification_code", TRUE)'
                    )->execute([
                        'user_id' => $userId,
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                    ]);

                    // Redirect to admin dashboard
                    header('Location: index.php');
                    exit;
                } else {
                    // Track failed attempts
                    $failedAttempts++;
                    $_SESSION['admin_2fa_failed_attempts'] = $failedAttempts;

                    if ($failedAttempts >= 5) {
                        // Lock out for 15 minutes
                        $_SESSION['admin_2fa_lockout_until'] = time() + (15 * 60);
                        $errorMessage = 'Too many failed attempts. Account locked for 15 minutes.';
                    } else {
                        $remaining = 5 - $failedAttempts;
                        $errorMessage = $result['error'] . " ({$remaining} attempts remaining)";
                    }
                }
            } catch (Exception $e) {
                error_log('Admin 2FA verify error: ' . $e->getMessage());
                $errorMessage = 'An error occurred. Please try again.';
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Verification - Bright Light Ministry Int'l</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,200..800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --cream: #FAF6EF;
            --ink: #0A111F;
            --gold: #C9A24B;
            --goldsoft: #E7D9B4;
            --deep: #16213E;
            --sage: #5B7B6A;
            --text-muted: rgba(15, 27, 45, 0.6);
            --radius-lg: 16px;
            --radius-xl: 20px;
            --radius-2xl: 24px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--cream);
            color: var(--ink);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .verify-container {
            max-width: 440px;
            width: 100%;
            background: white;
            border-radius: var(--radius-2xl);
            padding: 40px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }

        .verify-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .verify-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--deep), #1a2d4a);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }

        .verify-icon svg {
            width: 32px;
            height: 32px;
            color: var(--gold);
        }

        .verify-badge {
            display: inline-block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: var(--gold);
            background: rgba(201, 162, 75, 0.1);
            padding: 4px 12px;
            border-radius: 999px;
            margin-bottom: 12px;
        }

        .verify-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .verify-subtitle {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .user-info {
            background: var(--cream);
            border-radius: var(--radius-lg);
            padding: 12px 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--gold), var(--goldsoft));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--deep);
            font-weight: 600;
            font-size: 14px;
        }

        .user-details {
            flex: 1;
        }

        .user-name {
            font-weight: 500;
            font-size: 14px;
        }

        .user-email {
            font-size: 12px;
            color: var(--text-muted);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: var(--radius-lg);
            font-size: 16px;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(201, 162, 75, 0.1);
        }

        .code-input {
            text-align: center;
            font-size: 28px;
            letter-spacing: 12px;
            font-family: monospace;
            font-weight: 600;
        }

        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: var(--radius-lg);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .btn-primary {
            background: var(--deep);
            color: white;
        }

        .btn-primary:hover {
            background: #1a2d4a;
        }

        .btn-secondary {
            background: transparent;
            color: var(--gold);
            border: 1px solid var(--gold);
        }

        .btn-secondary:hover {
            background: rgba(201, 162, 75, 0.1);
        }

        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-lg);
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: rgba(91, 123, 106, 0.1);
            color: var(--sage);
            border: 1px solid rgba(91, 123, 106, 0.2);
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .resend-section {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid rgba(0,0,0,0.08);
        }

        .resend-text {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 12px;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
        }

        .back-link:hover {
            color: var(--ink);
        }

        .expiry-notice {
            background: rgba(201, 162, 75, 0.1);
            border: 1px solid rgba(201, 162, 75, 0.2);
            border-radius: var(--radius-lg);
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #8B7355;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .expiry-notice svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="verify-header">
            <div class="verify-icon">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0110 0v4"/>
                </svg>
            </div>
            <span class="verify-badge">Admin Portal</span>
            <h1 class="verify-title">Two-Step Verification</h1>
            <p class="verify-subtitle">
                Enter the 6-digit code sent to your email
            </p>
        </div>

        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($firstName, 0, 1) . substr($firstName, 1, 1)); ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($firstName); ?></div>
                <div class="user-email"><?php echo htmlspecialchars($email); ?></div>
            </div>
        </div>

        <?php if ($successMessage): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['admin_2fa_code_sent'])): ?>
            <div class="expiry-notice">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
                Code expires in <?php echo (int) ($_SESSION['admin_2fa_expires'] ?? 5); ?> minutes
            </div>
        <?php endif; ?>

        <?php if ($showForm): ?>
            <form method="POST">
                <input type="hidden" name="action" value="verify">

                <div class="form-group">
                    <label class="form-label" for="code">Verification Code</label>
                    <input type="text" id="code" name="code" class="form-input code-input"
                           maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                           placeholder="000000" required autocomplete="one-time-code"
                           autofocus>
                </div>

                <button type="submit" class="btn btn-primary">Verify & Access Dashboard</button>
            </form>

            <div class="resend-section">
                <p class="resend-text">Didn't receive a code?</p>
                <form method="POST">
                    <input type="hidden" name="action" value="resend">
                    <button type="submit" class="btn btn-secondary">Resend Code</button>
                </form>
            </div>
        <?php endif; ?>

        <a href="admin-verify.php?cancel=1" class="back-link">&larr; Back to Admin Login</a>
    </div>

    <script>
        // Auto-format code input
        const codeInput = document.getElementById('code');
        if (codeInput) {
            codeInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
            });
        }
    </script>
</body>
</html>
