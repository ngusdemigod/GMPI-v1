<?php
/**
 * EmailService
 * Handles sending verification emails using PHPMailer or native mail
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/resend_config.php';

class EmailService
{
    private $db;
    private $fromEmail;
    private $fromName;
    private $useResend;
    private $resendApiKey;
    private $allowPhpMailFallback;
    private $lastError;
    private $lastProviderResponse;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->fromEmail = defined('RESEND_FROM_EMAIL') ? RESEND_FROM_EMAIL : 'noreply@brightlightministry.org';
        $this->fromName = defined('RESEND_FROM_NAME') ? RESEND_FROM_NAME : 'Bright Light Ministry Int\'l';
        $this->useResend = defined('RESEND_API_KEY') && !empty(RESEND_API_KEY);
        $this->resendApiKey = defined('RESEND_API_KEY') ? RESEND_API_KEY : '';
        $this->allowPhpMailFallback = defined('EMAIL_ALLOW_PHP_MAIL_FALLBACK') && EMAIL_ALLOW_PHP_MAIL_FALLBACK;
        $this->lastError = '';
        $this->lastProviderResponse = [];

        error_log(sprintf(
            '[EmailService] initialized useResend=%s allowPhpMailFallback=%s fromEmail=%s fromName=%s',
            $this->useResend ? 'true' : 'false',
            $this->allowPhpMailFallback ? 'true' : 'false',
            $this->fromEmail,
            $this->fromName
        ));
    }

    /**
     * Send signup verification email
     */
    public function sendSignupVerification(string $to, string $firstName, string $code, int $expiresInMinutes): bool
    {
        $subject = 'Verify your email address - Bright Light Ministry Int\'l';
        $body = $this->buildVerificationEmailTemplate(
            $firstName,
            $code,
            $expiresInMinutes,
            'signup'
        );

        return $this->send($to, $subject, $body);
    }

    public function sendUserLoginVerification(string $to, string $firstName, string $code, int $expiresInMinutes): bool
    {
        $subject = 'Your Login Verification Code - Bright Light Ministry Int\'l';
        $body = $this->buildVerificationEmailTemplate(
            $firstName,
            $code,
            $expiresInMinutes,
            'user_login'
        );

        return $this->send($to, $subject, $body);
    }

    /**
     * Send admin login verification email
     */
    public function sendAdminLoginVerification(string $to, string $firstName, string $code, int $expiresInMinutes): bool
    {
        $subject = 'Your Admin Login Verification Code';
        $body = $this->buildVerificationEmailTemplate(
            $firstName,
            $code,
            $expiresInMinutes,
            'admin_login'
        );

        return $this->send($to, $subject, $body);
    }

    /**
     * Build HTML email template for verification codes
     */
    private function buildVerificationEmailTemplate(string $firstName, string $code, int $expiresInMinutes, string $type): string
    {
        if ($type === 'signup') {
            $title = 'Welcome! Verify Your Email';
            $message = 'Thank you for signing up for the Bright Light Ministry Int\'l Partnership Portal. To complete your registration, please use the verification code below:';
            $warningText = 'If you did not create an account, please ignore this email.';
        } elseif ($type === 'user_login') {
            $title = 'Login Verification Required';
            $message = 'We received a sign-in request for your Bright Light Ministry Int\'l Partnership Portal account. Use the verification code below before access is granted:';
            $warningText = 'If you did not attempt to log in, please ignore this email and reset your password immediately.';
        } else {
            $title = 'Admin Login Verification';
            $message = 'Someone (hopefully you) is trying to log in to the admin dashboard. Please use the verification code below to complete your login:';
            $warningText = 'If you did not attempt to log in, please ignore this email and contact support immediately.';
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f4f4f4; }
        .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; }
        .email-header { background: linear-gradient(135deg, #122137 0%, #0F1B2D 100%); padding: 40px 30px; text-align: center; }
        .email-header h1 { margin: 0; color: #C9A24B; font-size: 28px; font-family: Georgia, serif; }
        .email-header p { margin: 10px 0 0 0; color: #E7D9B4; font-size: 14px; }
        .email-body { padding: 40px 30px; }
        .email-body h2 { color: #122137; margin: 0 0 20px 0; font-size: 22px; }
        .email-body p { color: #555555; line-height: 1.6; margin: 0 0 16px 0; }
        .code-box { background: #f8f9fa; border: 2px dashed #C9A24B; border-radius: 8px; padding: 20px; text-align: center; margin: 24px 0; }
        .code { font-size: 36px; font-weight: bold; color: #122137; letter-spacing: 8px; font-family: monospace; }
        .expiry { color: #dc3545; font-size: 14px; font-weight: 600; margin-top: 8px; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 12px; margin: 20px 0; font-size: 13px; color: #856404; }
        .email-footer { background: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #e9ecef; }
        .email-footer p { color: #999999; font-size: 11px; margin: 0; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>Bright Light Ministry Int'l</h1>
            <p>Partnership Portal</p>
        </div>
        <div class="email-body">
            <h2>{$title}</h2>
            <p>Hello {$firstName},</p>
            <p>{$message}</p>
            <div class="code-box">
                <div class="code">{$code}</div>
                <div class="expiry">This code expires in {$expiresInMinutes} minutes</div>
            </div>
            <div class="warning">
                <strong>Security Notice:</strong> {$warningText}
            </div>
            <p>If you have any questions, please contact us at support@brightlightministry.org.</p>
        </div>
        <div class="email-footer">
            <p>Bright Light Ministry Int'l | support@brightlightministry.org</p>
            <p>&copy; " . date('Y') . " Bright Light Ministry Int'l. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Send email via Resend API or fallback to PHP mail
     */
    private function send(string $to, string $subject, string $body): bool
    {
        $this->lastError = '';
        $this->lastProviderResponse = [];

        error_log(sprintf(
            '[EmailService] send start to=%s subject=%s useResend=%s',
            $to,
            $subject,
            $this->useResend ? 'true' : 'false'
        ));

        if (!$this->useResend) {
            $this->lastError = 'Resend is not configured. Set RESEND_API_KEY to enable email delivery.';
            error_log(sprintf('[EmailService] %s', $this->lastError));
            return $this->sendViaPhpMail($to, $subject, $body);
        }

        if ($this->sendViaResend($to, $subject, $body)) {
            return true;
        }

        return $this->sendViaPhpMail($to, $subject, $body);
    }

    /**
     * Send email using Resend API
     */
    private function sendViaResend(string $to, string $subject, string $body): bool
    {
        $url = defined('RESEND_API_URL') ? rtrim(RESEND_API_URL, '/') . '/emails' : 'https://api.resend.com/emails';
        $headers = [
            'Authorization: Bearer ' . $this->resendApiKey,
            'Content-Type: application/json'
        ];

        $data = [
            'from' => $this->fromName . ' <' . $this->fromEmail . '>',
            'to' => [$to],
            'subject' => $subject,
            'html' => $body
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $curlError !== '') {
            $this->lastError = $curlError !== '' ? $curlError : 'unknown transport error';
            $this->lastProviderResponse = [
                'provider' => 'resend',
                'http_code' => $httpCode,
                'body' => $response,
            ];
            error_log(sprintf(
                '[EmailService] Resend transport error to=%s url=%s error=%s key=%s',
                $to,
                $url,
                $this->lastError,
                $this->maskSecret($this->resendApiKey)
            ));
            return false;
        }

        $decoded = json_decode($response, true);
        $this->lastProviderResponse = [
            'provider' => 'resend',
            'http_code' => $httpCode,
            'body' => $decoded ?? $response,
        ];

        if ($httpCode >= 200 && $httpCode < 300) {
            error_log(sprintf(
                '[EmailService] Resend success to=%s http=%s body=%s',
                $to,
                $httpCode,
                $response
            ));
            return true;
        }

        $this->lastError = $decoded['message'] ?? $decoded['error'] ?? "Resend rejected the request with HTTP {$httpCode}.";
        error_log(sprintf(
            '[EmailService] Resend API error to=%s http=%s from=%s key=%s body=%s',
            $to,
            $httpCode,
            $this->fromEmail,
            $this->maskSecret($this->resendApiKey),
            $response
        ));
        return false;
    }

    /**
     * Send email using PHP mail function (fallback)
     */
    private function sendViaPhpMail(string $to, string $subject, string $body): bool
    {
        if (!$this->allowPhpMailFallback) {
            error_log(sprintf(
                '[EmailService] PHP mail fallback disabled to=%s lastError=%s',
                $to,
                $this->lastError !== '' ? $this->lastError : 'n/a'
            ));
            return false;
        }

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";
        $headers .= "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $headers .= "Reply-To: {$this->fromEmail}\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        $result = mail($to, $subject, $body, $headers);

        if ($result) {
            error_log("[EmailService] Email sent via PHP mail to {$to}");
        } else {
            error_log("[EmailService] PHP mail failed to send to {$to}");
            if ($this->lastError === '') {
                $this->lastError = 'PHP mail fallback failed to send the email.';
            }
        }

        return $result;
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function getLastProviderResponse(): array
    {
        return $this->lastProviderResponse;
    }

    /**
     * Send milestone reached notification to project participants
     */
    public function sendMilestoneReached(string $to, array $milestoneData, string $firstName, string $lastName): bool
    {
        $subject = 'Milestone Reached: ' . $milestoneData['project_title'] . ' - Bright Light Ministry Int\'l';
        $body = $this->buildMilestoneEmailTemplate($milestoneData, $firstName, $lastName, 'participant');
        return $this->send($to, $subject, $body);
    }

    /**
     * Send milestone reached notification to admins
     */
    public function sendMilestoneAdminNotification(string $to, array $milestoneData): bool
    {
        $subject = 'Milestone Reached: ' . $milestoneData['project_title'] . ' - Admin Notification';
        $body = $this->buildMilestoneEmailTemplate($milestoneData, 'Admin', '', 'admin');
        return $this->send($to, $subject, $body);
    }

    /**
     * Build HTML email template for milestone notifications
     */
    private function buildMilestoneEmailTemplate(array $milestoneData, string $firstName, string $lastName, string $recipientType): string
    {
        $greeting = $recipientType === 'admin' ? 'Hello Admin,' : "Dear {$firstName} {$lastName},";
        $message = $recipientType === 'admin' 
            ? 'A financial milestone has been reached in one of your projects. Here are the details:'
            : 'We are excited to share that a milestone has been reached in a project you have supported. Thank you for your generous contributions!';

        $progressPercentage = $milestoneData['progress_percentage'] ?? 100;
        $targetAmount = number_format($milestoneData['target_amount'], 2);
        $currentAmount = number_format($milestoneData['current_amount'], 2);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f4f4f4; }
        .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; }
        .email-header { background: linear-gradient(135deg, #122137 0%, #0F1B2D 100%); padding: 40px 30px; text-align: center; }
        .email-header h1 { margin: 0; color: #C9A24B; font-size: 28px; font-family: Georgia, serif; }
        .email-header p { margin: 10px 0 0 0; color: #E7D9B4; font-size: 14px; }
        .email-body { padding: 40px 30px; }
        .email-body h2 { color: #122137; margin: 0 0 20px 0; font-size: 22px; }
        .email-body p { color: #555555; line-height: 1.6; margin: 0 0 16px 0; }
        .milestone-box { background: #f8f9fa; border: 2px solid #C9A24B; border-radius: 8px; padding: 20px; text-align: center; margin: 24px 0; }
        .milestone-title { font-size: 20px; font-weight: bold; color: #122137; margin-bottom: 10px; }
        .milestone-amount { font-size: 24px; font-weight: bold; color: #C9A24B; }
        .progress-bar { background: #e9ecef; border-radius: 4px; height: 20px; margin: 16px 0; overflow: hidden; }
        .progress-fill { background: linear-gradient(90deg, #C9A24B, #E7D9B4); height: 100%; border-radius: 4px; transition: width 0.5s ease; }
        .stats { display: flex; justify-content: space-between; margin: 16px 0; }
        .stat-item { text-align: center; flex: 1; }
        .stat-value { font-size: 18px; font-weight: bold; color: #122137; }
        .stat-label { font-size: 12px; color: #666; margin-top: 4px; }
        .email-footer { background: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #e9ecef; }
        .email-footer p { color: #999999; font-size: 11px; margin: 0; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>Bright Light Ministry Int'l</h1>
            <p>Partnership Portal</p>
        </div>
        <div class="email-body">
            <h2>Milestone Reached!</h2>
            <p>{$greeting}</p>
            <p>{$message}</p>
            <div class="milestone-box">
                <div class="milestone-title">{$milestoneData['title']}</div>
                <div class="milestone-amount">{$targetAmount}</div>
                <p style="color: #666; margin: 8px 0 0 0; font-size: 14px;">{$milestoneData['description']}</p>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: {$progressPercentage}%"></div>
            </div>
            <div class="stats">
                <div class="stat-item">
                    <div class="stat-value">{$currentAmount}</div>
                    <div class="stat-label">Raised</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">{$progressPercentage}%</div>
                    <div class="stat-label">Progress</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">{$milestoneData['project_title']}</div>
                    <div class="stat-label">Project</div>
                </div>
            </div>
            <p>Thank you for being a valued partner in this mission. Your generosity continues to make a difference.</p>
        </div>
        <div class="email-footer">
            <p>Bright Light Ministry Int'l | support@brightlightministry.org</p>
            <p>&copy; " . date('Y') . " Bright Light Ministry Int'l. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function maskSecret(string $secret): string
    {
        $length = strlen($secret);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($secret, 0, 4) . str_repeat('*', max(0, $length - 8)) . substr($secret, -4);
    }
}
