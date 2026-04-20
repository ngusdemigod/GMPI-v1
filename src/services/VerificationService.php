<?php
/**
 * VerificationService
 * Orchestrates verification code generation, email sending, and validation
 */

require_once __DIR__ . '/../models/VerificationCode.php';
require_once __DIR__ . '/EmailService.php';

class VerificationService
{
    private VerificationCode $verificationCode;
    private EmailService $emailService;

    public function __construct()
    {
        $this->verificationCode = new VerificationCode();
        $this->emailService = new EmailService();
    }

    /**
     * Generate and send verification code for user signup
     */
    public function sendSignupCode(int $userId, string $email, string $firstName): array
    {
        error_log(sprintf('[VerificationService] sendSignupCode userId=%d email=%s', $userId, $email));
        // Check rate limit
        $rateCheck = $this->verificationCode->canResend($userId, 'signup_verification');
        if (!$rateCheck['allowed']) {
            error_log(sprintf('[VerificationService] signup resend blocked userId=%d error=%s', $userId, $rateCheck['error']));
            return ['success' => false, 'error' => $rateCheck['error']];
        }

        // Generate and store code
        $codeData = $this->verificationCode->create($userId, 'signup_verification', 10);

        // Send email
        $emailSent = $this->emailService->sendSignupVerification(
            $email,
            $firstName,
            $codeData['code'],
            $codeData['expires_in_minutes']
        );

        if (!$emailSent) {
            $providerError = $this->emailService->getLastError();
            error_log(sprintf(
                '[VerificationService] signup email send failed userId=%d email=%s error=%s',
                $userId,
                $email,
                $providerError !== '' ? $providerError : 'unknown'
            ));
            return ['success' => false, 'error' => $providerError !== '' ? $providerError : 'Failed to send verification email. Please try again.'];
        }

        // Log resend
        $this->verificationCode->logResend($userId, 'signup_verification');

        return [
            'success' => true,
            'message' => 'Verification code sent to your email.',
            'expires_in_minutes' => $codeData['expires_in_minutes'],
            'resend_attempts_remaining' => $rateCheck['attempts_remaining'] - 1
        ];
    }

    public function sendUserLoginCode(int $userId, string $email, string $firstName): array
    {
        error_log(sprintf('[VerificationService] sendUserLoginCode userId=%d email=%s', $userId, $email));
        $rateCheck = $this->verificationCode->canResend($userId, 'signup_verification');
        if (!$rateCheck['allowed']) {
            error_log(sprintf('[VerificationService] user login resend blocked userId=%d error=%s', $userId, $rateCheck['error']));
            return ['success' => false, 'error' => $rateCheck['error']];
        }

        $codeData = $this->verificationCode->create($userId, 'signup_verification', 5);

        $emailSent = $this->emailService->sendUserLoginVerification(
            $email,
            $firstName,
            $codeData['code'],
            $codeData['expires_in_minutes']
        );

        if (!$emailSent) {
            $providerError = $this->emailService->getLastError();
            error_log(sprintf(
                '[VerificationService] user login email send failed userId=%d email=%s error=%s',
                $userId,
                $email,
                $providerError !== '' ? $providerError : 'unknown'
            ));
            return ['success' => false, 'error' => $providerError !== '' ? $providerError : 'Failed to send verification email. Please try again.'];
        }

        $this->verificationCode->logResend($userId, 'signup_verification');

        return [
            'success' => true,
            'message' => 'Verification code sent to your email.',
            'expires_in_minutes' => $codeData['expires_in_minutes'],
            'resend_attempts_remaining' => $rateCheck['attempts_remaining'] - 1
        ];
    }

    /**
     * Generate and send verification code for admin login
     */
    public function sendAdminLoginCode(int $userId, string $email, string $firstName): array
    {
        error_log(sprintf('[VerificationService] sendAdminLoginCode userId=%d email=%s', $userId, $email));
        // Check rate limit
        $rateCheck = $this->verificationCode->canResend($userId, 'admin_login_verification');
        if (!$rateCheck['allowed']) {
            error_log(sprintf('[VerificationService] admin login resend blocked userId=%d error=%s', $userId, $rateCheck['error']));
            return ['success' => false, 'error' => $rateCheck['error']];
        }

        // Generate and store code
        $codeData = $this->verificationCode->create($userId, 'admin_login_verification', 5);

        // Send email
        $emailSent = $this->emailService->sendAdminLoginVerification(
            $email,
            $firstName,
            $codeData['code'],
            $codeData['expires_in_minutes']
        );

        if (!$emailSent) {
            $providerError = $this->emailService->getLastError();
            error_log(sprintf(
                '[VerificationService] admin login email send failed userId=%d email=%s error=%s',
                $userId,
                $email,
                $providerError !== '' ? $providerError : 'unknown'
            ));
            return ['success' => false, 'error' => $providerError !== '' ? $providerError : 'Failed to send verification email. Please try again.'];
        }

        // Log resend
        $this->verificationCode->logResend($userId, 'admin_login_verification');

        return [
            'success' => true,
            'message' => 'Verification code sent to your email.',
            'expires_in_minutes' => $codeData['expires_in_minutes'],
            'resend_attempts_remaining' => $rateCheck['attempts_remaining'] - 1
        ];
    }

    /**
     * Verify a submitted code
     */
    public function verifyCode(int $userId, string $code, string $purpose): array
    {
        return $this->verificationCode->verify($userId, $code, $purpose);
    }

    /**
     * Check if user can resend a code
     */
    public function canResend(int $userId, string $purpose): array
    {
        return $this->verificationCode->canResend($userId, $purpose);
    }
}
