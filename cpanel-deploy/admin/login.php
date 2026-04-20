<?php
/**
 * Admin Login Page
 * Church Financial Partnership System
 */

require_once __DIR__ . '/includes/config.php';

if (isset($_SESSION['pending_admin_2fa_user_id'])) {
    redirect(ADMIN_URL . '/admin-verify.php');
}

// Redirect if already logged in
if (getAdminUser()) {
    redirect(ADMIN_URL . '/index.php');
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        try {
            $db = Database::getInstance();
            
            // Get user by email
            $user = $db->fetchOne(
                "SELECT * FROM users WHERE email = :email AND is_active = TRUE",
                ['email' => $email]
            );
            
            if (!$user) {
                $error = 'Invalid email or password';
                logAdminAction(0, 'LOGIN_FAILED', 'email', 0, $email, ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            } elseif (!password_verify($password, $user['password_hash'])) {
                $error = 'Invalid email or password';
                logAdminAction(0, 'LOGIN_FAILED', 'email', 0, $email, ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            } else {
                // Check if user is admin
                $admin = Auth::getAdminByUserId($user['user_id']);
                
                if (!$admin) {
                    $error = 'You do not have admin access. Please contact your system administrator.';
                } else {
                    // Check if account is locked
                    if (Auth::isAccountLocked($admin['admin_id'])) {
                        $error = 'Account is temporarily locked due to too many failed login attempts. Please try again later.';
                    } else {
                        // Check if email is verified
                        if (!$user['is_verified']) {
                            $error = 'Your email address has not been verified. Please check your email for the verification code.';
                        } else {
                            // Password verified - start 2FA flow
                            // Log password success
                            $db->execute(
                                'INSERT INTO admin_login_attempts (user_id, ip_address, attempt_type, success) 
                                 VALUES (:user_id, :ip_address, "password", TRUE)',
                                [
                                    'user_id' => $user['user_id'],
                                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                                ]
                            );

                            // Store pending 2FA session (not fully authenticated yet)
                            $_SESSION['pending_admin_2fa_user_id'] = (int) $user['user_id'];
                            $_SESSION['pending_admin_2fa_email'] = $user['email'];
                            $_SESSION['pending_admin_2fa_first_name'] = $user['first_name'];
                            $_SESSION['pending_admin_2fa_admin_id'] = (int) $admin['admin_id'];

                            // Reset failed login attempts
                            Auth::resetFailedLoginAttempts($admin['admin_id']);

                            // Redirect to 2FA verification page
                            redirect(ADMIN_URL . '/admin-verify.php');
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'An error occurred. Please try again.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Bright Light Ministry Int'l Partnership Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,200..800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="includes/design-system.css" rel="stylesheet">
    <style>
        /* Page-specific overrides - use design-system.css for typography */
        :root {
            /* Override gold colors for this page */
            --gold: #D4AF37;
            --gold-light: #F4DF8D;
            --gold-dark: #B8941F;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg-primary); color: var(--text-primary); scrollbar-width: none; -ms-overflow-style: none; }

        *::-webkit-scrollbar { width: 0; height: 0; display: none; }
        
        .login-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }
        
        /* Left Panel - Brand */
        .login-brand {
            flex: 1;
            background-color: var(--deep);
            background: linear-gradient(135deg, var(--dark) 0%, var(--deep) 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .login-brand::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.15) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .brand-logo {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 40px;
        }
        
        .brand-logo-icon {
            width: 50px;
            height: 50px;
            background: var(--gold);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: var(--dark);
        }
        
        .brand-logo-text {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 24px;
        }
        
        .brand-logo-text span {
            display: block;
            font-size: 12px;
            font-family: 'Inter', sans-serif;
            color: rgba(255,255,255,0.7);
            font-weight: 400;
        }
        
        .brand-content {
            position: relative;
            z-index: 1;
            background: linear-gradient(180deg, rgba(255,255,255,0.02) 0%, rgba(201, 162, 75, 0.08) 100%);
            border: 1px solid rgba(231, 217, 180, 0.18);
            border-radius: var(--radius-lg);
            padding: 36px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.16);
            backdrop-filter: blur(4px);
        }
        
        .brand-quote {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 28px;
            line-height: 1.6;
            margin-bottom: 30px;
            opacity: 0.95;
        }
        
        .brand-quote cite {
            display: block;
            margin-top: 20px;
            font-size: 16px;
            color: var(--gold);
            font-style: normal;
        }
        
        .brand-stats {
            display: flex;
            gap: 40px;
            margin-top: 60px;
        }
        
        .brand-stat {
            text-align: center;
        }
        
        .brand-stat-number {
            font-size: 36px;
            font-family: 'Bricolage Grotesque', sans-serif;
            color: var(--gold);
        }
        
        .brand-stat-label {
            font-size: 13px;
            color: rgba(255,255,255,0.7);
        }
        
        /* Right Panel - Login Form */
        .login-form-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px;
            background: var(--cream);
        }
        
        .form-header {
            margin-bottom: 40px;
        }
        
        .form-header h1 {
            font-size: 36px;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .form-header .subtitle {
            color: var(--text-secondary);
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .admin-badge {
            background: var(--gold);
            color: var(--dark);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .alert {
            padding: 14px 18px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .form-input {
            width: 100%;
            padding: 14px 18px;
            background: white;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: var(--radius-sm);
            font-size: 16px;
            transition: all 0.2s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }
        
        .form-input::placeholder {
            color: var(--text-muted);
        }
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle input {
            padding-right: 50px;
        }
        
        .password-toggle button {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 14px;
        }
        
        .password-toggle button:hover {
            color: var(--text-primary);
        }
        
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .remember-me input {
            width: 18px;
            height: 18px;
            accent-color: var(--gold);
        }
        
        .remember-me label {
            font-size: 14px;
            color: var(--text-secondary);
            cursor: pointer;
        }
        
        .forgot-password {
            color: var(--gold);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .forgot-password:hover {
            text-decoration: underline;
        }
        
        .submit-btn {
            width: 100%;
            padding: 16px;
            background: var(--gold);
            border: none;
            border-radius: var(--radius-sm);
            color: var(--dark);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .submit-btn:hover {
            background: var(--gold-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(212, 175, 55, 0.3);
        }
        
        .submit-btn:disabled {
            background: var(--text-muted);
            cursor: not-allowed;
            transform: none;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 30px 0;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(0,0,0,0.1);
        }
        
        .divider span {
            padding: 0 15px;
            color: var(--text-muted);
            font-size: 14px;
        }
        
        .security-notice {
            background: rgba(0,0,0,0.03);
            padding: 16px;
            border-radius: var(--radius-sm);
            margin-top: 30px;
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .security-notice strong {
            color: var(--text-primary);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            margin-top: 20px;
            font-size: 14px;
        }
        
        .back-link:hover {
            color: var(--gold);
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .login-container {
                min-height: 100vh;
            }

            .login-brand {
                flex: 0 0 42%;
                padding: 36px 28px;
            }

            .brand-content {
                padding: 28px 24px;
            }

            .brand-quote {
                font-size: 22px;
            }

            .brand-stats {
                gap: 20px;
                margin-top: 40px;
            }

            .brand-stat-number {
                font-size: 28px;
            }
            
            .login-form-container {
                flex: 1 1 58%;
                padding: 48px 32px;
            }
        }
        
        @media (max-width: 768px) {
            .login-brand {
                display: none;
            }

            .login-form-container {
                padding: 40px 24px;
                min-height: 100vh;
                justify-content: flex-start;
                padding-top: 80px;
            }
            
            .form-header h1 {
                font-size: 32px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Left Panel - Brand -->
        <div class="login-brand">
            <div class="brand-content">
                <div class="brand-logo">
                    <div class="brand-logo-icon">✝</div>
                    <div class="brand-logo-text">
                        Bright Light Ministry Int'l
                        <span>Partnership Portal</span>
                    </div>
                </div>
                
                <blockquote class="brand-quote">
                    "Each of you should give what you have decided in your heart to give, not reluctantly or under compulsion, for God loves a cheerful giver."
                    <cite>— 2 Corinthians 9:7</cite>
                </blockquote>
                
                <div class="brand-stats">
                    <div class="brand-stat">
                        <div class="brand-stat-number">1,247</div>
                        <div class="brand-stat-label">Families Fed</div>
                    </div>
                    <div class="brand-stat">
                        <div class="brand-stat-number">58</div>
                        <div class="brand-stat-label">Missionaries</div>
                    </div>
                    <div class="brand-stat">
                        <div class="brand-stat-number">3</div>
                        <div class="brand-stat-label">New Campuses</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Panel - Login Form -->
        <div class="login-form-container">
            <div class="form-header">
                <h1>Admin Login</h1>
                <p class="subtitle">
                    <span class="admin-badge">ADMINISTRATOR ACCESS</span>
                </p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo e($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo e($success); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" onsubmit="return validateForm()">
                <div class="form-group">
                    <label class="form-label" for="email">Admin Email</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-input" 
                        placeholder="admin@gracecathedral.org"
                        value="<?php echo e($email ?? ''); ?>"
                        required
                        autocomplete="email"
                    >
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="password-toggle">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input" 
                            placeholder="Enter your password"
                            required
                            autocomplete="current-password"
                        >
                        <button type="button" onclick="togglePassword()">Show</button>
                    </div>
                </div>
                
                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember">
                        <label for="remember">Remember me</label>
                    </label>
                    <a href="forgot-password.php" class="forgot-password">Forgot password?</a>
                </div>
                
                <button type="submit" class="submit-btn" id="submitBtn">
                    Sign In to Admin Dashboard
                </button>
            </form>
            
            <div class="divider">
                <span>or</span>
            </div>
            
            <a href="../src/login.php" class="back-link">
                ← Back to User Portal
            </a>
            
            <div class="security-notice">
                <strong>Security Notice:</strong> Admin access is restricted to authorized personnel only. 
                All login attempts are logged and monitored for security purposes.
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = passwordInput.parentElement.querySelector('button');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = 'Hide';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = 'Show';
            }
        }
        
        function validateForm() {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                return false;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Signing in...';
            
            return true;
        }
        
        // Auto-fill demo credentials hint
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            if (!emailInput.value) {
                emailInput.placeholder = 'admin@gracecathedral.org';
            }
        });
    </script>
</body>
</html>
