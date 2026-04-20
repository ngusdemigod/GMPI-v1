<?php
/**
 * Signup Page with Email Verification
 * Church Financial Partnership System
 */

require_once __DIR__ . '/bootstrap.php';

redirectUserIfAuthenticated();

if (isset($_SESSION['pending_verification_user_id'])) {
    header('Location: verify-email.php');
    exit;
}

// Initialize variables
$error = '';
$success = '';
$step = 'form'; // Legacy values retained for template compatibility.
$userData = null;
$verificationCode = '';

// ============================================
// HANDLE SIGNUP FORM SUBMISSION
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'signup') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($firstName) || empty($lastName)) {
        $error = 'Please enter both first name and last name';
    } elseif (empty($email)) {
        $error = 'Please enter your email address';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif (empty($password)) {
        $error = 'Please enter a password';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        try {
            // Create user (is_verified will be FALSE)
            $userId = User::create($email, $password, $firstName, $lastName);
            
            // Send verification email
            $result = User::sendVerification($userId, $email, $firstName);
            
            if ($result['success']) {
                beginPendingSignupVerification([
                    'user_id' => $userId,
                    'email' => $email,
                ]);
                header('Location: verify-email.php');
                exit;
            } else {
                $errorMsg = $result['message'];
                if (strpos($errorMsg, 'email_verifications') !== false || strpos($errorMsg, 'Table') !== false) {
                    $error = 'Database tables not found. Please run the migration: database/migrations/add_email_verification_tables.sql';
                } else {
                    $error = $errorMsg;
                }
            }
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        } catch (Exception $e) {
            $error = 'An error occurred. Please try again.';
            error_log("Signup error: " . $e->getMessage());
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Bright Light Ministry Int'l Partnership Portal</title>
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
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-weight: 600;
        }
        
        .signup-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }
        
        /* Left Panel - Brand */
        .signup-brand {
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
        
        .signup-brand::before {
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
        
        /* Right Panel - Form */
        .signup-form-container {
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
        
        .form-group.inline {
            display: flex;
            gap: 20px;
        }
        
        .form-group.inline .input-wrapper {
            flex: 1;
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
        
        /* Verification Code Input */
        .verification-input {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 30px 0;
        }
        
        .verification-input input {
            width: 60px;
            height: 70px;
            font-size: 28px;
            text-align: center;
            border: 2px solid rgba(0,0,0,0.1);
            border-radius: var(--radius-sm);
            transition: all 0.2s;
        }
        
        .verification-input input:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }
        
        /* Success Screen */
        .success-screen {
            text-align: center;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            font-size: 40px;
            color: var(--dark);
        }
        
        .success-screen h2 {
            font-size: 28px;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .success-screen p {
            color: var(--text-secondary);
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .info-box {
            background: rgba(212, 175, 55, 0.1);
            border: 1px solid var(--gold);
            border-radius: var(--radius-sm);
            padding: 20px;
            margin: 20px 0;
        }
        
        .info-box p {
            margin: 0;
            color: var(--dark);
            font-size: 14px;
        }
        
        .resend-link {
            color: var(--gold);
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
        }
        
        .resend-link:hover {
            text-decoration: underline;
        }
        
        .timer-display {
            text-align: center;
            color: var(--text-secondary);
            font-size: 14px;
            margin-top: 10px;
        }
        
        .timer-display strong {
            color: var(--danger);
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .signup-brand {
                display: none;
            }
            
            .signup-form-container {
                flex: 1;
                max-width: 600px;
                margin: 0 auto;
            }
        }
        
        @media (max-width: 768px) {
            .signup-form-container {
                padding: 40px 24px;
                min-height: 100vh;
                justify-content: flex-start;
                padding-top: 80px;
            }
            
            .form-header h1 {
                font-size: 32px;
            }
            
            .form-group.inline {
                flex-direction: column;
                gap: 0;
            }
            
            .verification-input {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <!-- Left Panel - Brand -->
        <div class="signup-brand">
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
        
        <!-- Right Panel - Form -->
        <div class="signup-form-container">
            <?php if ($step === 'form'): ?>
                <!-- Signup Form -->
                <div class="form-header">
                    <h1>Create Account</h1>
                    <p>Join our partnership community. <a href="login.php">Already have an account? Sign in</a></p>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" onsubmit="return validateForm()">
                    <input type="hidden" name="action" value="signup">
                    <div class="form-group inline">
                        <div class="input-wrapper">
                            <label class="form-label" for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-input" placeholder="Enter first name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="input-wrapper">
                            <label class="form-label" for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-input" placeholder="Enter last name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" placeholder="Enter your email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-input" placeholder="Create a password (min. 8 characters)" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Confirm your password" required>
                    </div>
                    
                    <button type="submit" class="submit-btn" id="submitBtn">
                        Create Account
                    </button>
                </form>
                
                <div class="form-footer">
                    <p>By creating an account, you agree to our <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></p>
                </div>
                
            <?php elseif ($step === 'sent'): ?>
                <!-- Verification Sent Screen -->
                <div class="form-header">
                    <h1>Check Your Email</h1>
                    <p>We've sent a verification code to <strong><?php echo htmlspecialchars($userData['email']); ?></strong></p>
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
                
                <div class="info-box">
                    <p>Enter the 6-character code from the email to verify your account. The code will expire in 15 minutes.</p>
                </div>
                
                <form method="POST" action="" id="verifyForm">
                    <input type="hidden" name="action" value="verify">
                    <div class="verification-input">
                        <input type="text" name="verification_code" id="code1" maxlength="1" pattern="[A-Z0-9]" required autocomplete="off">
                        <input type="text" name="verification_code" id="code2" maxlength="1" pattern="[A-Z0-9]" required autocomplete="off">
                        <input type="text" name="verification_code" id="code3" maxlength="1" pattern="[A-Z0-9]" required autocomplete="off">
                        <input type="text" name="verification_code" id="code4" maxlength="1" pattern="[A-Z0-9]" required autocomplete="off">
                        <input type="text" name="verification_code" id="code5" maxlength="1" pattern="[A-Z0-9]" required autocomplete="off">
                        <input type="text" name="verification_code" id="code6" maxlength="1" pattern="[A-Z0-9]" required autocomplete="off">
                    </div>
                    
                    <button type="submit" class="submit-btn" id="verifyBtn">
                        Verify Email
                    </button>
                </form>
                
                <p style="text-align: center; margin-top: 20px; color: var(--text-secondary); font-size: 14px;">
                    Didn't receive the code? <span class="resend-link" onclick="resendCode()">Resend</span>
                </p>
                
            <?php elseif ($step === 'verify'): ?>
                <!-- Verification Form (after code sent) -->
                <div class="form-header">
                    <h1>Verify Your Email</h1>
                    <p>We've sent a verification code to <strong><?php echo htmlspecialchars($userData['email']); ?></strong></p>
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
                
                <div class="info-box">
                    <p>Enter the 6-character code from the email to verify your account. The code will expire in 15 minutes.</p>
                </div>
                
                <form method="POST" action="" id="verifyForm">
                    <input type="hidden" name="action" value="verify">
                    <div class="verification-input">
                        <input type="text" name="verification_code" id="code1" maxlength="1" pattern="[A-Z0-9]" required autocomplete="off">
                        <input type="text" name="verification_code" id="code2" maxlength="1" pattern="[A-Z0-9]" required autocomplete="off">
                        <input type="text" name="verification_code" id="code3" maxlength="1" pattern="[A-Z0-9]" required autocomplete="off">
                        <input type="text" name="verification_code" id="code4" maxlength="1" pattern="[A-Z0-9]" required autocomplete="off">
                        <input type="text" name="verification_code" id="code5" maxlength="1" pattern="[A-Z0-9]" required autocomplete="off">
                        <input type="text" name="verification_code" id="code6" maxlength="1" pattern="[A-Z0-9]" required autocomplete="off">
                    </div>
                    
                    <button type="submit" class="submit-btn" id="verifyBtn">
                        Verify Email
                    </button>
                </form>
                
                <p style="text-align: center; margin-top: 20px; color: var(--text-secondary); font-size: 14px;">
                    Didn't receive the code? <span class="resend-link" onclick="resendCode()">Resend</span>
                </p>
                
            <?php elseif ($step === 'verified'): ?>
                <!-- Verified Screen -->
                <div class="form-header">
                    <h1>Account Verified!</h1>
                </div>
                
                <div class="success-screen">
                    <div class="success-icon">✓</div>
                    <h2>Welcome to Bright Light Ministry Int'l!</h2>
                    <p>Your email has been successfully verified. Please sign in to access the Partnership Portal.</p>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="login">
                        <div class="form-group">
                            <label class="form-label" for="login_email">Email Address</label>
                            <input type="email" id="login_email" name="email" class="form-input" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="login_password">Password</label>
                            <input type="password" id="login_password" name="password" class="form-input" required>
                        </div>
                        <button type="submit" class="submit-btn">
                            Sign In
                        </button>
                    </form>
                    
                    <p style="margin-top: 20px;">
                        <a href="login.php" style="color: var(--gold); text-decoration: none; font-weight: 500;">Go to Sign In Page</a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Auto-focus first input
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('input[required]');
            if (firstInput) {
                firstInput.focus();
            }
        });
        
        // Form validation
        function validateForm() {
            const firstName = document.getElementById('first_name').value;
            const lastName = document.getElementById('last_name').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (!firstName || !lastName) {
                alert('Please enter both first name and last name');
                return false;
            }
            
            if (!email || !email.includes('@')) {
                alert('Please enter a valid email address');
                return false;
            }
            
            if (password.length < 8) {
                alert('Password must be at least 8 characters');
                return false;
            }
            
            if (password !== confirmPassword) {
                alert('Passwords do not match');
                return false;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating account...';
            
            return true;
        }
        
        // Verification code input handling
        const codeInputs = document.querySelectorAll('.verification-input input');
        
        codeInputs.forEach((input, index) => {
            input.addEventListener('input', function(e) {
                // Only allow alphanumeric
                this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                
                // Move to next input
                if (this.value.length === 1 && index < codeInputs.length - 1) {
                    codeInputs[index + 1].focus();
                }
            });
            
            input.addEventListener('keydown', function(e) {
                // Handle backspace
                if (e.key === 'Backspace' && this.value === '' && index > 0) {
                    codeInputs[index - 1].focus();
                }
            });
            
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                const pasteData = e.clipboardData.getData('text').toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 6);
                
                for (let i = 0; i < pasteData.length && i < codeInputs.length; i++) {
                    codeInputs[i].value = pasteData[i];
                }
                
                const nextInputIndex = Math.min(pasteData.length, codeInputs.length - 1);
                codeInputs[nextInputIndex].focus();
            });
        });
        
        // Resend code function
        function resendCode() {
            const email = '<?php echo htmlspecialchars($userData['email'] ?? ''); ?>';
            
            if (!confirm('Are you sure you want to resend the verification code?')) {
                return;
            }
            
            const form = document.getElementById('verifyForm');
            const formData = new FormData(form);
            formData.append('email', email);
            formData.append('action', 'resend');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                window.location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to resend verification code. Please try again.');
            });
        }
    </script>
</body>
</html>
