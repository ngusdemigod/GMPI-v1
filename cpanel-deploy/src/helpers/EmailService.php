<?php
/**
 * Email Service
 * Handles sending emails via Resend API
 * Church Financial Partnership System
 */

require_once __DIR__ . '/../config/resend_config.php';

class EmailService {
    public function __construct() {
        error_log(sprintf(
            '[ForgotPasswordEmailService] initialized allowPhpMailFallback=%s fromEmail=%s fromName=%s apiUrl=%s key=%s',
            EMAIL_ALLOW_PHP_MAIL_FALLBACK ? 'true' : 'false',
            RESEND_FROM_EMAIL,
            RESEND_FROM_NAME,
            RESEND_API_URL,
            $this->maskSecret(RESEND_API_KEY)
        ));
    }
    
    /**
     * Send an email using Resend API
     */
    public function send($to, $subject, $html, $text = '') {
        if (empty(RESEND_API_KEY)) {
            error_log('[ForgotPasswordEmailService] RESEND_API_KEY is missing; Resend delivery cannot start');
            return $this->sendViaPhpMail($to, $subject, $html, $text, 'Resend is not configured. Set RESEND_API_KEY.');
        }

        if (!function_exists('curl_init')) {
            error_log('[ForgotPasswordEmailService] cURL extension is not available');
            return $this->sendViaPhpMail($to, $subject, $html, $text, 'cURL extension is not installed or enabled on this server.');
        }

        error_log(sprintf(
            '[ForgotPasswordEmailService] send start to=%s subject=%s from=%s',
            $to,
            $subject,
            RESEND_FROM_EMAIL
        ));

        $ch = curl_init();
        
        $data = [
            'from' => sprintf('%s <%s>', RESEND_FROM_NAME, RESEND_FROM_EMAIL),
            'to' => [$to],
            'subject' => $subject,
            'html' => $html,
            'reply_to' => EMAIL_SETTINGS['reply_to'] ?? 'support@brightlightministry.org.ng'
        ];
        
        if (!empty($text)) {
            $data['text'] = $text;
        }
        
        curl_setopt($ch, CURLOPT_URL, RESEND_API_URL . '/emails');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . RESEND_API_KEY,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            error_log(sprintf(
                '[ForgotPasswordEmailService] Resend transport error to=%s error=%s key=%s',
                $to,
                $error,
                $this->maskSecret(RESEND_API_KEY)
            ));
            return $this->sendViaPhpMail($to, $subject, $html, $text, $error);
        }
        
        $result = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            error_log(sprintf(
                '[ForgotPasswordEmailService] Resend success to=%s http=%s body=%s',
                $to,
                $httpCode,
                $response
            ));
            return ['success' => true, 'message' => 'Email sent', 'data' => $result];
        } else {
            $apiMessage = $result['message'] ?? $result['error'] ?? 'Unknown Resend API error';
            error_log(sprintf(
                '[ForgotPasswordEmailService] Resend API error to=%s http=%s from=%s key=%s body=%s',
                $to,
                $httpCode,
                RESEND_FROM_EMAIL,
                $this->maskSecret(RESEND_API_KEY),
                $response
            ));
            return $this->sendViaPhpMail($to, $subject, $html, $text, $apiMessage);
        }
    }

    private function sendViaPhpMail($to, $subject, $html, $text = '', $reason = '') {
        if (!EMAIL_ALLOW_PHP_MAIL_FALLBACK) {
            error_log(sprintf(
                '[ForgotPasswordEmailService] PHP mail fallback disabled to=%s reason=%s',
                $to,
                $reason !== '' ? $reason : 'n/a'
            ));
            return ['success' => false, 'message' => $reason !== '' ? $reason : 'Email delivery failed before reaching the mail provider.'];
        }

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";
        $headers .= "From: " . RESEND_FROM_NAME . " <" . RESEND_FROM_EMAIL . ">\r\n";
        $headers .= "Reply-To: " . (EMAIL_SETTINGS['reply_to'] ?? RESEND_FROM_EMAIL) . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        $result = mail($to, $subject, $html, $headers);

        if ($result) {
            error_log("[ForgotPasswordEmailService] Email sent via PHP mail to {$to}");
            return ['success' => true, 'message' => 'Email sent via PHP mail'];
        }

        error_log("[ForgotPasswordEmailService] PHP mail failed to send to {$to}");
        return ['success' => false, 'message' => $reason !== '' ? $reason : 'Failed to send email'];
    }

    private function maskSecret($secret) {
        $secret = (string) $secret;
        $length = strlen($secret);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($secret, 0, 4) . str_repeat('*', max(0, $length - 8)) . substr($secret, -4);
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($to, $email, $resetLink, $name = '') {
        $subject = 'Reset Your Password - Bright Light Ministry Int\'l';
        
        $html = $this->getPasswordResetTemplate($resetLink, $name);
        $text = $this->getPasswordResetTextTemplate($resetLink);
        
        return $this->send($to, $subject, $html, $text);
    }
    
    /**
     * Send admin password reset email
     */
    public function sendAdminPasswordResetEmail($to, $email, $resetLink, $name = '') {
        $subject = 'Admin Password Reset - Bright Light Ministry Int\'l';
        
        $html = $this->getAdminPasswordResetTemplate($resetLink, $name);
        $text = $this->getAdminPasswordResetTextTemplate($resetLink);
        
        return $this->send($to, $subject, $html, $text);
    }
    
    /**
     * Send 2FA verification code email
     */
    public function send2FACode($to, $email, $code, $name = '') {
        $subject = 'Your 2FA Verification Code - Bright Light Ministry Int\'l';
        
        $html = $this->get2FATemplate($code, $name);
        $text = $this->get2FATextTemplate($code);
        
        return $this->send($to, $subject, $html, $text);
    }
    
    /**
     * Send donation receipt email
     */
    public function sendDonationReceipt($to, $email, $receiptData) {
        $subject = 'Thank you for your generous gift!';
        
        $html = $this->getDonationReceiptTemplate($receiptData);
        $text = $this->getDonationReceiptTextTemplate($receiptData);
        
        return $this->send($to, $subject, $html, $text);
    }

    /**
     * Send password changed notification email
     */
    public function sendPasswordChangedAlert($to, $name = '') {
        $subject = 'Your Password Was Recently Changed - Bright Light Ministry Int\'l';
        
        $html = $this->getPasswordChangedAlertTemplate($name);
        $text = $this->getPasswordChangedAlertTextTemplate();
        
        return $this->send($to, $subject, $html, $text);
    }
    
    /**
     * Password reset email template (HTML)
     */
    private function getPasswordResetTemplate($resetLink, $name = '') {
        $greeting = !empty($name) ? htmlspecialchars($name) : 'Valued Partner';
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #FDFBF7;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" style="max-width: 600px; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0;">
                            <div style="font-family: 'Playfair Display', serif; font-size: 28px; color: #D4AF37; margin-bottom: 10px;">✝</div>
                            <div style="font-family: 'Playfair Display', serif; font-size: 22px; color: white; font-weight: 600;">Bright Light Ministry Int'l</div>
                            <div style="font-family: 'Inter', sans-serif; font-size: 13px; color: rgba(255,255,255,0.7); margin-top: 5px;">Partnership Portal</div>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h1 style="font-family: 'Playfair Display', serif; font-size: 24px; color: #1a1a2e; margin: 0 0 20px 0;">Reset Your Password</h1>
                            <p style="font-size: 16px; color: #6b6b7b; line-height: 1.6; margin: 0 0 20px 0;">
                                Hello {$greeting},
                            </p>
                            <p style="font-size: 16px; color: #6b6b7b; line-height: 1.6; margin: 0 0 20px 0;">
                                We received a request to reset your password. Click the button below to create a new password:
                            </p>
                            <table role="presentation" style="margin: 30px 0;">
                                <tr>
                                    <td align="center" style="background: #D4AF37; padding: 16px 40px; border-radius: 6px; text-decoration: none;">
                                        <a href="{$resetLink}" style="color: #1a1a2e; font-size: 16px; font-weight: 600; text-decoration: none;">Reset Password</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="font-size: 14px; color: #999999; line-height: 1.6; margin: 0 0 20px 0;">
                                Or copy and paste this link into your browser:<br>
                                <a href="{$resetLink}" style="color: #D4AF37; word-break: break-all;">{$resetLink}</a>
                            </p>
                            <div style="background: rgba(212, 175, 55, 0.1); border-left: 4px solid #D4AF37; padding: 15px; border-radius: 6px; margin: 20px 0;">
                                <p style="font-size: 14px; color: #6b6b7b; margin: 0;">
                                    <strong>⏰ Important:</strong> This link will expire in <strong>15 minutes</strong> for your security.
                                </p>
                            </div>
                            <p style="font-size: 14px; color: #999999; line-height: 1.6; margin: 20px 0 0 0;">
                                If you didn't request a password reset, you can safely ignore this email. Your password will remain unchanged.
                            </p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background: #f5f5f5; padding: 30px; text-align: center; border-radius: 0 0 12px 12px;">
                            <p style="font-size: 14px; color: #999999; margin: 0 0 10px 0;">
                                Need help? Contact us at <a href="mailto:support@brightlightministry.org.ng" style="color: #D4AF37;">support@brightlightministry.org.ng</a>
                            </p>
                            <p style="font-size: 12px; color: #cccccc; margin: 0;">
                                © 2024 Bright Light Ministry Int'l. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
    
    /**
     * Password reset email template (Plain text)
     */
    private function getPasswordResetTextTemplate($resetLink) {
        return <<<TEXT
Reset Your Password - Bright Light Ministry Int'l

We received a request to reset your password. Click the link below to create a new password:

{$resetLink}

Important: This link will expire in 15 minutes for your security.

If you didn't request a password reset, you can safely ignore this email. Your password will remain unchanged.

Need help? Contact us at support@brightlightministry.org.ng

© 2024 Bright Light Ministry Int'l. All rights reserved.
TEXT;
    }
    
    /**
     * Admin password reset email template (HTML)
     */
    private function getAdminPasswordResetTemplate($resetLink, $name = '') {
        $greeting = !empty($name) ? htmlspecialchars($name) : 'Administrator';
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Password Reset</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #FDFBF7;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" style="max-width: 600px; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0;">
                            <div style="font-family: 'Playfair Display', serif; font-size: 28px; color: #D4AF37; margin-bottom: 10px;">🔐</div>
                            <div style="font-family: 'Playfair Display', serif; font-size: 22px; color: white; font-weight: 600;">Bright Light Ministry Int'l</div>
                            <div style="font-family: 'Inter', sans-serif; font-size: 13px; color: rgba(255,255,255,0.7); margin-top: 5px;">Admin Portal</div>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h1 style="font-family: 'Playfair Display', serif; font-size: 24px; color: #1a1a2e; margin: 0 0 20px 0;">Admin Password Reset</h1>
                            <p style="font-size: 16px; color: #6b6b7b; line-height: 1.6; margin: 0 0 20px 0;">
                                Hello {$greeting},
                            </p>
                            <p style="font-size: 16px; color: #6b6b7b; line-height: 1.6; margin: 0 0 20px 0;">
                                We received a request to reset your admin password. Click the button below to create a new password:
                            </p>
                            <table role="presentation" style="margin: 30px 0;">
                                <tr>
                                    <td align="center" style="background: #D4AF37; padding: 16px 40px; border-radius: 6px; text-decoration: none;">
                                        <a href="{$resetLink}" style="color: #1a1a2e; font-size: 16px; font-weight: 600; text-decoration: none;">Reset Admin Password</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="font-size: 14px; color: #999999; line-height: 1.6; margin: 0 0 20px 0;">
                                Or copy and paste this link into your browser:<br>
                                <a href="{$resetLink}" style="color: #D4AF37; word-break: break-all;">{$resetLink}</a>
                            </p>
                            <div style="background: rgba(212, 175, 55, 0.1); border-left: 4px solid #D4AF37; padding: 15px; border-radius: 6px; margin: 20px 0;">
                                <p style="font-size: 14px; color: #6b6b7b; margin: 0;">
                                    <strong>⏰ Important:</strong> This link will expire in <strong>15 minutes</strong> for your security.
                                </p>
                            </div>
                            <p style="font-size: 14px; color: #999999; line-height: 1.6; margin: 20px 0 0 0;">
                                If you didn't request a password reset, please contact your system administrator immediately.
                            </p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background: #f5f5f5; padding: 30px; text-align: center; border-radius: 0 0 12px 12px;">
                            <p style="font-size: 14px; color: #999999; margin: 0 0 10px 0;">
                                Need help? Contact us at <a href="mailto:support@brightlightministry.org.ng" style="color: #D4AF37;">support@brightlightministry.org.ng</a>
                            </p>
                            <p style="font-size: 12px; color: #cccccc; margin: 0;">
                                © 2024 Bright Light Ministry Int'l. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
    
    /**
     * Admin password reset email template (Plain text)
     */
    private function getAdminPasswordResetTextTemplate($resetLink) {
        return <<<TEXT
Admin Password Reset - Bright Light Ministry Int'l

We received a request to reset your admin password. Click the link below to create a new password:

{$resetLink}

Important: This link will expire in 15 minutes for your security.

If you didn't request a password reset, please contact your system administrator immediately.

© 2024 Bright Light Ministry Int'l. All rights reserved.
TEXT;
    }
    
    /**
     * 2FA verification code email template (HTML)
     */
    private function get2FATemplate($code, $name = '') {
        $greeting = !empty($name) ? htmlspecialchars($name) : 'Admin';
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2FA Verification Code</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #FDFBF7;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" style="max-width: 600px; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0;">
                            <div style="font-family: 'Playfair Display', serif; font-size: 28px; color: #D4AF37; margin-bottom: 10px;">🔒</div>
                            <div style="font-family: 'Playfair Display', serif; font-size: 22px; color: white; font-weight: 600;">Bright Light Ministry Int'l</div>
                            <div style="font-family: 'Inter', sans-serif; font-size: 13px; color: rgba(255,255,255,0.7); margin-top: 5px;">Partnership Portal</div>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h1 style="font-family: 'Playfair Display', serif; font-size: 24px; color: #1a1a2e; margin: 0 0 20px 0;">Two-Factor Verification</h1>
                            <p style="font-size: 16px; color: #6b6b7b; line-height: 1.6; margin: 0 0 20px 0;">
                                Hello {$greeting},
                            </p>
                            <p style="font-size: 16px; color: #6b6b7b; line-height: 1.6; margin: 0 0 20px 0;">
                                Your verification code is:
                            </p>
                            <div style="text-align: center; margin: 30px 0;">
                                <span style="display: inline-block; background: #F4DF8D; color: #1a1a2e; font-size: 36px; font-weight: 700; padding: 20px 50px; border-radius: 12px; letter-spacing: 8px; font-family: 'Courier New', monospace;">{$code}</span>
                            </div>
                            <div style="background: rgba(212, 175, 55, 0.1); border-left: 4px solid #D4AF37; padding: 15px; border-radius: 6px; margin: 20px 0;">
                                <p style="font-size: 14px; color: #6b6b7b; margin: 0;">
                                    <strong>⏰ Important:</strong> This code will expire in <strong>15 minutes</strong> for your security.
                                </p>
                            </div>
                            <p style="font-size: 14px; color: #999999; line-height: 1.6; margin: 20px 0 0 0;">
                                If you didn't request this code, please contact your system administrator immediately.
                            </p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background: #f5f5f5; padding: 30px; text-align: center; border-radius: 0 0 12px 12px;">
                            <p style="font-size: 14px; color: #999999; margin: 0 0 10px 0;">
                                Need help? Contact us at <a href="mailto:support@brightlightministry.org.ng" style="color: #D4AF37;">support@brightlightministry.org.ng</a>
                            </p>
                            <p style="font-size: 12px; color: #cccccc; margin: 0;">
                                © 2024 Bright Light Ministry Int'l. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
    
    /**
     * 2FA verification code email template (Plain text)
     */
    private function get2FATextTemplate($code) {
        return <<<TEXT
Two-Factor Verification Code - Bright Light Ministry Int'l

Your verification code is: {$code}

Important: This code will expire in 15 minutes for your security.

If you didn't request this code, please contact your system administrator immediately.

© 2024 Bright Light Ministry Int'l. All rights reserved.
TEXT;
    }
    
    /**
     * Donation receipt email template (HTML)
     */
    private function getDonationReceiptTemplate($data) {
        $name = htmlspecialchars($data['name'] ?? 'Valued Partner');
        $amount = number_format($data['amount'], 2);
        $date = date('F j, Y', strtotime($data['date'] ?? date('Y-m-d')));
        $reference = htmlspecialchars($data['reference'] ?? '');
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You for Your Gift</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #FDFBF7;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" style="max-width: 600px; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0;">
                            <div style="font-family: 'Playfair Display', serif; font-size: 28px; color: #D4AF37; margin-bottom: 10px;">🙏</div>
                            <div style="font-family: 'Playfair Display', serif; font-size: 22px; color: white; font-weight: 600;">Bright Light Ministry Int'l</div>
                            <div style="font-family: 'Inter', sans-serif; font-size: 13px; color: rgba(255,255,255,0.7); margin-top: 5px;">Partnership Portal</div>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h1 style="font-family: 'Playfair Display', serif; font-size: 24px; color: #1a1a2e; margin: 0 0 20px 0;">Thank You for Your Generous Gift!</h1>
                            <p style="font-size: 16px; color: #6b6b7b; line-height: 1.6; margin: 0 0 20px 0;">
                                Hello {$name},
                            </p>
                            <p style="font-size: 16px; color: #6b6b7b; line-height: 1.6; margin: 0 0 20px 0;">
                                Thank you for your partnership and generous giving. Your contribution makes a difference in the lives of many.
                            </p>
                            <div style="background: linear-gradient(135deg, #F4DF8D 0%, #D4AF37 100%); padding: 30px; border-radius: 12px; text-align: center; margin: 30px 0;">
                                <div style="font-size: 14px; color: #1a1a2e; margin-bottom: 10px;">Your Contribution</div>
                                <div style="font-family: 'Playfair Display', serif; font-size: 42px; color: #1a1a2e; font-weight: 600;">{$amount}</div>
                            </div>
                            <table role="presentation" style="width: 100%; margin: 30px 0;">
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #eee;">
                                        <span style="color: #999999; font-size: 14px;">Date</span>
                                    </td>
                                    <td align="right" style="padding: 12px 0; border-bottom: 1px solid #eee;">
                                        <span style="color: #1a1a2e; font-size: 16px; font-weight: 500;">{$date}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #eee;">
                                        <span style="color: #999999; font-size: 14px;">Reference</span>
                                    </td>
                                    <td align="right" style="padding: 12px 0; border-bottom: 1px solid #eee;">
                                        <span style="color: #1a1a2e; font-size: 16px; font-weight: 500;">{$reference}</span>
                                    </td>
                                </tr>
                            </table>
                            <p style="font-size: 16px; color: #6b6b7b; line-height: 1.6; margin: 20px 0;">
                                "Each of you should give what you have decided in your heart to give, not reluctantly or under compulsion, for God loves a cheerful giver." — <strong>2 Corinthians 9:7</strong>
                            </p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background: #f5f5f5; padding: 30px; text-align: center; border-radius: 0 0 12px 12px;">
                            <p style="font-size: 14px; color: #999999; margin: 0 0 10px 0;">
                                Need help? Contact us at <a href="mailto:support@brightlightministry.org.ng" style="color: #D4AF37;">support@brightlightministry.org.ng</a>
                            </p>
                            <p style="font-size: 12px; color: #cccccc; margin: 0;">
                                © 2024 Bright Light Ministry Int'l. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
    
    /**
     * Donation receipt email template (Plain text)
     */
    private function getDonationReceiptTextTemplate($data) {
        $name = $data['name'] ?? 'Valued Partner';
        $amount = number_format($data['amount'], 2);
        $date = date('F j, Y', strtotime($data['date'] ?? date('Y-m-d')));
        $reference = $data['reference'] ?? '';
        
        return <<<TEXT
Thank You for Your Generous Gift - Bright Light Ministry Int'l

Hello {$name},

Thank you for your partnership and generous giving. Your contribution makes a difference in the lives of many.

Your Contribution: {$amount}
Date: {$date}
Reference: {$reference}

"Each of you should give what you have decided in your heart to give, not reluctantly or under compulsion, for God loves a cheerful giver." — 2 Corinthians 9:7

Need help? Contact us at support@brightlightministry.org.ng

© 2024 Bright Light Ministry Int'l. All rights reserved.
TEXT;
    }

    /**
     * Password changed alert template (HTML)
     */
    private function getPasswordChangedAlertTemplate($name = '') {
        $greeting = !empty($name) ? htmlspecialchars($name) : 'Valued Partner';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Changed</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #FDFBF7;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" style="max-width: 600px; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0;">
                            <div style="font-family: 'Playfair Display', serif; font-size: 28px; color: #D4AF37; margin-bottom: 10px;">&#9888;</div>
                            <div style="font-family: 'Playfair Display', serif; font-size: 22px; color: white; font-weight: 600;">Bright Light Ministry Int'l</div>
                            <div style="font-family: 'Inter', sans-serif; font-size: 13px; color: rgba(255,255,255,0.7); margin-top: 5px;">Security Notification</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h1 style="font-family: 'Playfair Display', serif; font-size: 24px; color: #1a1a2e; margin: 0 0 20px 0;">Your password was recently changed</h1>
                            <p style="font-size: 16px; color: #6b6b7b; line-height: 1.6; margin: 0 0 20px 0;">Hello {$greeting},</p>
                            <p style="font-size: 16px; color: #6b6b7b; line-height: 1.6; margin: 0 0 20px 0;">
                                This is a security notification to let you know that your account password was recently changed.
                            </p>
                            <div style="background: rgba(220, 53, 69, 0.08); border-left: 4px solid #dc3545; padding: 15px; border-radius: 6px; margin: 20px 0;">
                                <p style="font-size: 14px; color: #6b6b7b; margin: 0;">
                                    If you did not make this change, contact admin immediately.
                                </p>
                            </div>
                            <p style="font-size: 14px; color: #999999; line-height: 1.6; margin: 20px 0 0 0;">
                                Need help? Contact us at <a href="mailto:support@brightlightministry.org.ng" style="color: #D4AF37;">support@brightlightministry.org.ng</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background: #f5f5f5; padding: 30px; text-align: center; border-radius: 0 0 12px 12px;">
                            <p style="font-size: 12px; color: #cccccc; margin: 0;">© 2024 Bright Light Ministry Int'l. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Password changed alert template (Plain text)
     */
    private function getPasswordChangedAlertTextTemplate() {
        return <<<TEXT
Password Changed - Bright Light Ministry Int'l

This is a security notification to let you know that your account password was recently changed.

If you did not make this change, contact admin immediately.

Need help? Contact us at support@brightlightministry.org.ng

© 2024 Bright Light Ministry Int'l. All rights reserved.
TEXT;
    }
}
