<?php
/**
 * Email Verification Page
 * Handles user signup email verification and user login verification.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/services/VerificationService.php';

$verificationService = new VerificationService();
$errorMessage = '';
$successMessage = '';
$email = '';
$showForm = true;
$mode = $_GET['mode'] ?? 'signup';
$isLoginVerification = $mode === 'login';
$hasPendingSignupVerification = isset($_SESSION['pending_verification_user_id'], $_SESSION['pending_verification_email']);

if (!$isLoginVerification && isUserFullyAuthenticated()) {
    header('Location: index.php');
    exit;
}

if ($isLoginVerification && !hasPendingUserLoginVerification()) {
    if (isUserFullyAuthenticated()) {
        header('Location: index.php');
    } else {
        header('Location: login.php');
    }
    exit;
}

if (isset($_GET['cancel']) && $_GET['cancel'] === '1') {
    unset(
        $_SESSION['pending_verification_user_id'],
        $_SESSION['pending_verification_email'],
        $_SESSION['pending_login_verification_user_id'],
        $_SESSION['pending_login_verification_email'],
        $_SESSION['pending_login_verification_first_name'],
        $_SESSION['login_verification_code_sent'],
        $_SESSION['login_verification_failed_attempts'],
        $_SESSION['login_verification_lockout_until']
    );
    header('Location: login.php');
    exit;
}

if (!$isLoginVerification && !$hasPendingSignupVerification) {
    header('Location: signup.php');
    exit;
}

if ($isLoginVerification) {
    $userId = (int) ($_SESSION['pending_login_verification_user_id'] ?? 0);
    $email = $_SESSION['pending_login_verification_email'] ?? '';
    $firstName = $_SESSION['pending_login_verification_first_name'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_SESSION['login_verification_code_sent'])) {
        try {
            $result = $verificationService->sendUserLoginCode($userId, $email, $firstName);

            if ($result['success']) {
                $_SESSION['login_verification_code_sent'] = true;
            } else {
                $errorMessage = $result['error'];
            }
        } catch (Exception $e) {
            error_log('Login verification send error: ' . $e->getMessage());
            $errorMessage = 'Failed to send verification code. Please try again.';
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend') {
        try {
            $result = $verificationService->sendUserLoginCode($userId, $email, $firstName);

            if ($result['success']) {
                $successMessage = $result['message'];
                $_SESSION['login_verification_code_sent'] = true;
            } else {
                $errorMessage = $result['error'];
            }
        } catch (Exception $e) {
            error_log('Login verification resend error: ' . $e->getMessage());
            $errorMessage = 'An error occurred. Please try again.';
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify') {
        $code = trim($_POST['code'] ?? '');
        $failedAttempts = (int) ($_SESSION['login_verification_failed_attempts'] ?? 0);
        $lockoutUntil = $_SESSION['login_verification_lockout_until'] ?? null;

        if (empty($code) || !preg_match('/^\d{6}$/', $code)) {
            $errorMessage = 'Please enter a valid 6-digit code.';
        } elseif ($lockoutUntil && time() < $lockoutUntil) {
            $remaining = ceil(($lockoutUntil - time()) / 60);
            $errorMessage = "Too many failed attempts. Please wait {$remaining} minutes.";
        } else {
            try {
                $result = $verificationService->verifyCode($userId, $code, 'signup_verification');

                if ($result['success']) {
                    $db = Database::getInstance();
                    $user = $db->fetchOne(
                        'SELECT user_id, email, first_name, last_name FROM users WHERE user_id = :user_id AND is_active = TRUE',
                        ['user_id' => $userId]
                    );

                    if (!$user) {
                        throw new RuntimeException('User account is no longer available.');
                    }

                    completeUserLoginVerification($user);
                    $redirectTarget = $_SESSION['redirect_after_login'] ?? 'index.php';
                    unset($_SESSION['redirect_after_login']);
                    header('Location: ' . $redirectTarget);
                    exit;
                }

                $failedAttempts++;
                $_SESSION['login_verification_failed_attempts'] = $failedAttempts;

                if ($failedAttempts >= 5) {
                    $_SESSION['login_verification_lockout_until'] = time() + (15 * 60);
                    $errorMessage = 'Too many failed attempts. Account locked for 15 minutes.';
                } else {
                    $remaining = 5 - $failedAttempts;
                    $errorMessage = ($result['error'] ?? 'Invalid verification code.') . " ({$remaining} attempts remaining)";
                }
            } catch (Exception $e) {
                error_log('Login verification error: ' . $e->getMessage());
                $errorMessage = 'An error occurred. Please try again.';
            }
        }
    }
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend') {
        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'Please enter a valid email address.';
        } else {
            try {
                $db = Database::getInstance();
                $user = $db->fetchOne(
                    'SELECT user_id, first_name, is_verified FROM users WHERE email = :email',
                    ['email' => $email]
                );

                if (!$user) {
                    $errorMessage = 'No account found with that email address.';
                } elseif ($user['is_verified']) {
                    $errorMessage = 'This email is already verified. Please sign in.';
                } else {
                    $result = $verificationService->sendSignupCode(
                        (int) $user['user_id'],
                        $email,
                        $user['first_name']
                    );

                    if ($result['success']) {
                        $successMessage = $result['message'];
                        $_SESSION['pending_verification_email'] = $email;
                        $_SESSION['pending_verification_user_id'] = (int) $user['user_id'];
                    } else {
                        $errorMessage = $result['error'];
                    }
                }
            } catch (Exception $e) {
                error_log('Verification resend error: ' . $e->getMessage());
                $errorMessage = 'An error occurred. Please try again.';
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify') {
        $code = trim($_POST['code'] ?? '');
        $userId = (int) ($_SESSION['pending_verification_user_id'] ?? 0);
        $email = $_SESSION['pending_verification_email'] ?? '';

        if (empty($code) || !preg_match('/^\d{6}$/', $code)) {
            $errorMessage = 'Please enter a valid 6-digit code.';
        } elseif ($userId <= 0) {
            $errorMessage = 'No pending verification found. Please request a new code.';
        } else {
            try {
                $result = $verificationService->verifyCode($userId, $code, 'signup_verification');

                if ($result['success']) {
                    $db = Database::getInstance();
                    $db->getConnection()->prepare(
                        'UPDATE users SET is_verified = TRUE WHERE user_id = :user_id'
                    )->execute(['user_id' => $userId]);

                    clearPendingSignupVerification();

                    $successMessage = 'Your email has been verified successfully! You can now sign in.';
                    $showForm = false;
                    header('Refresh: 3; URL=login.php');
                } else {
                    $errorMessage = $result['error'];
                }
            } catch (Exception $e) {
                error_log('Verification error: ' . $e->getMessage());
                $errorMessage = 'An error occurred. Please try again.';
            }
        }
    }

    if (isset($_SESSION['pending_verification_email']) && $showForm) {
        $email = $_SESSION['pending_verification_email'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isLoginVerification ? 'Verify Your Login - Bright Light Ministry Int\'l' : 'Verify Your Email - Bright Light Ministry Int\'l'; ?></title>
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
            background: linear-gradient(135deg, var(--gold), var(--goldsoft));
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }

        .verify-icon svg {
            width: 32px;
            height: 32px;
            color: var(--deep);
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
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="verify-header">
            <div class="verify-icon">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <h1 class="verify-title"><?php echo $isLoginVerification ? 'Verify Your Login' : 'Verify Your Email'; ?></h1>
            <p class="verify-subtitle">
                <?php if ($showForm && !empty($email)): ?>
                    We've sent a 6-digit code to <strong><?php echo htmlspecialchars($email); ?></strong>
                    <?php echo $isLoginVerification ? ' before platform access is granted.' : ' to verify your account.'; ?>
                <?php else: ?>
                    Enter the 6-digit code sent to your email
                <?php endif; ?>
            </p>
        </div>

        <?php if ($successMessage): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
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

                <button type="submit" class="btn btn-primary"><?php echo $isLoginVerification ? 'Verify Login' : 'Verify Email'; ?></button>
            </form>

            <div class="resend-section">
                <p class="resend-text">Didn't receive a code?</p>
                <form method="POST">
                    <input type="hidden" name="action" value="resend">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <button type="submit" class="btn btn-secondary">Resend Code</button>
                </form>
            </div>
        <?php else: ?>
            <div style="text-align: center;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#5B7B6A" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <p style="margin-top: 16px; color: var(--sage); font-weight: 500;">
                    Redirecting to sign in...
                </p>
            </div>
        <?php endif; ?>

        <a href="verify-email.php?cancel=1" class="back-link">&larr; Back to Sign In</a>
    </div>

    <script>
        const codeInput = document.getElementById('code');
        if (codeInput) {
            codeInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
            });
        }
    </script>
</body>
</html>
