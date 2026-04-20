<?php
/**
 * Admin Forgot Password Page
 * Church Financial Partnership System
 */

require_once __DIR__ . '/includes/config.php';

// Redirect if already logged in
if (getAdminUser()) {
    redirect(ADMIN_URL . '/index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Forgot Password - Bright Light Ministry Int'l Partnership Portal</title>
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
        
        /* Right Panel - Form */
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
        
        .back-link {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 24px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link:hover {
            color: var(--gold);
        }
        
        .info-box {
            background: rgba(212, 175, 55, 0.1);
            border-left: 4px solid var(--gold);
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 24px;
        }
        
        .info-box p {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0;
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
                padding: 40px 24px;
                min-height: 100vh;
                justify-content: flex-start;
                padding-top: 80px;
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
        }
        
        /* Responsive - Small Mobile */
        @media (max-width: 480px) {
            .login-form-container {
                padding: 30px 16px;
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
                font-size: 16px;
            }
            
            .submit-btn {
                padding: 14px;
                font-size: 15px;
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
        
        <!-- Right Panel - Forgot Password Form -->
        <div class="login-form-container">
            <div class="form-header">
                <h1>Admin Forgot Password?</h1>
                <p>Enter your admin email address and we'll send you a link to reset your password.</p>
            </div>
            
            <div id="alertContainer"></div>
            
            <div class="info-box">
                <p>🔒 The reset link will expire in <strong>15 minutes</strong> for your security.</p>
            </div>
            
            <form id="forgotPasswordForm" method="POST">
                <div class="form-group">
                    <label class="form-label" for="email">Admin Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-input" 
                        placeholder="Enter your admin email"
                        required
                    >
                </div>
                
                <button type="submit" class="submit-btn" id="submitBtn">
                    Send Reset Link
                </button>
            </form>
            
            <a href="login.php" class="back-link">
                ← Back to Admin Sign In
            </a>
        </div>
    </div>
    
    <script>
        const form = document.getElementById('forgotPasswordForm');
        const submitBtn = document.getElementById('submitBtn');
        const alertContainer = document.getElementById('alertContainer');
        
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value.trim();
            
            if (!email) {
                showAlert('Please enter your email address', 'error');
                return;
            }
            
            // Disable button and show loading state
            submitBtn.disabled = true;
            submitBtn.textContent = 'Sending...';
            
            try {
                const formData = new FormData();
                formData.append('email', email);
                
                const response = await fetch('handlers/forgot-password.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert(result.message, 'success', result.reset_link || '');
                    form.reset();
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('An error occurred. Please try again later.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Send Reset Link';
            }
        });
        
        function showAlert(message, type, resetLink = '') {
            const resetLinkMarkup = resetLink
                ? `<div style="margin-top: 12px; word-break: break-all;"><a href="${resetLink}" style="color: inherit; font-weight: 600; text-decoration: underline;">${resetLink}</a></div>`
                : '';

            alertContainer.innerHTML = `
                <div class="alert alert-${type}">
                    ${message}
                    ${resetLinkMarkup}
                </div>
            `;
        }
    </script>
</body>
</html>
