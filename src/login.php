<?php
/**
 * Login Page
 * Church Financial Partnership System
 */

require_once __DIR__ . '/bootstrap.php';

redirectUserIfAuthenticated();

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
            $user = User::authenticate($email, $password);
            
            if ($user && !empty($user['account_restricted'])) {
                $error = 'Account Restricted, Contact admin';
            } elseif ($user) {
                // Check if user is verified
                if (!$user['is_verified']) {
                    beginPendingSignupVerification($user);
                    header('Location: verify-email.php');
                    exit;
                } else {
                    beginPendingUserLoginVerification($user);
                    header('Location: verify-email.php?mode=login');
                    exit;
                }
            } else {
                $error = 'Invalid email or password';
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
    <title>Sign In - Bright Light Ministry Int'l Partnership Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,200..800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --gold: #D4AF37;
            --gold-light: #F4DF8D;
            --gold-dark: #B8941F;
            --dark: #1a1a2e;
            --dark-light: #16213e;
            --cream: #FDFBF7;
            --cream-light: #FAF8F3;
            --text-primary: #1a1a2e;
            --text-secondary: #6b6b7b;
            --text-muted: #999999;
            --success: #28a745;
            --danger: #dc3545;
            --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.15);
            --radius-sm: 6px;
            --radius-md: 12px;
            --radius-lg: 16px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--cream);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        *::-webkit-scrollbar {
            width: 0;
            height: 0;
            display: none;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-weight: 600;
        }
        
        .login-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }
        
        /* Left Panel - Brand */
        .login-brand {
            flex: 1;
            background: linear-gradient(135deg, var(--dark) 0%, var(--dark-light) 100%);
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
        
        .form-header p {
            color: var(--text-secondary);
            font-size: 16px;
        }
        
        .form-header a {
            color: var(--gold);
            text-decoration: none;
            font-weight: 500;
        }
        
        .form-header a:hover {
            text-decoration: underline;
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
            display:none;
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
            display:none;
        }
        
        .social-login {
            display: none;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .social-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px;
            background: white;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .social-btn:hover {
            background: var(--cream-light);
            border-color: var(--gold);
        }
        
        .social-btn.google {
            color: #DB4437;
        }
        
        .social-btn.apple {
            color: #000;
        }
        
        .form-footer {
            margin-top: 30px;
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid rgba(0,0,0,0.05);
        }
        
        .form-footer p {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .form-footer a {
            color: var(--gold);
            text-decoration: none;
            font-weight: 500;
        }
        
        .form-footer a:hover {
            text-decoration: underline;
        }
        
        /* Responsive - Tablet */
        @media (max-width: 1024px) {
            .login-brand {
                display: none;
            }
            
            .login-form-container {
                flex: 1;
                max-width: 600px;
                margin: 0 auto;
            }
        }
        
        /* Responsive - Mobile */
        @media (max-width: 768px) {
            .login-form-container {
                padding: 40px;
                min-height: 100vh;
                justify-content: flex-start;
                
            }
            
            .form-header {
                margin-bottom: 32px;
            }
            
            .form-header h1 {
                font-size: 32px;
            }
            
            .form-header p {
                font-size: 15px;
            }
            
            .form-group {
                margin-bottom: 20px;
            }
            
            .form-options {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
                margin-bottom: 24px;
            }
            
            .forgot-password {
                align-self: flex-end;
            }
            
            .social-login {
                grid-template-columns: 1fr;
                display:none;
            }
            
            .divider span {
                font-size: 13px;
                display:none;
            }
        }
        
        /* Responsive - Small Mobile */
        @media (max-width: 480px) {
            .login-form-container {
                padding: 40px;
            }
            
            .form-header h1 {
                font-size: 26px;
            }
            
            .form-header p {
                font-size: 14px;
            }
            
            .form-label {
                font-size: 13px;
            }
            
            .form-input {
                padding: 12px 14px;
                font-size: 16px; /* Prevents zoom on iOS */
            }
            
            .submit-btn {
                padding: 14px;
                font-size: 15px;
            }
            
            .social-btn {
                padding: 10px;
                font-size: 13px;
            }
            
            .form-footer p {
                font-size: 12px;
            }
        }
        
        /* Responsive - Extra Small */
        @media (max-width: 360px) {
            .login-form-container {
                padding: 24px 12px;
            }
            
            .form-header h1 {
                font-size: 22px;
            }
            
            .form-header p {
                font-size: 13px;
            }
            
            .form-input,
            .submit-btn,
            .social-btn {
                font-size: 14px;
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
                <h1>Welcome Back</h1>
                <p>Sign in to your partnership account. <a href="signup.php">Don't have an account? Sign up</a></p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" onsubmit="return validateForm()">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-input" 
                        placeholder="Enter your email"
                        value="<?php echo htmlspecialchars($email ?? ''); ?>"
                        required
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
                    Sign In
                </button>
            </form>
            
            <div class="divider">
                <span>or continue with</span>
            </div>
            
            <div class="social-login">
                <button class="social-btn google">
                    <span>🔍</span>
                    Google
                </button>
                <button class="social-btn apple">
                    <span></span>
                    Apple
                </button>
            </div>
            
            <div class="form-footer">
                <p>By signing in, you agree to our <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></p>
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
                emailInput.placeholder = 'david.anderson@email.com';
            }
        });
    </script>
</body>
</html>
