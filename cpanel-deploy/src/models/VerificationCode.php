<?php
/**
 * VerificationCode Model
 * Handles storage and retrieval of verification codes
 */

require_once __DIR__ . '/../config/database.php';

class VerificationCode
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Generate a cryptographically secure 6-digit code
     */
    public static function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Hash a verification code using password_hash
     */
    public static function hashCode(string $code): string
    {
        return password_hash($code, PASSWORD_DEFAULT);
    }

    /**
     * Verify a code against its hash
     */
    public static function verifyCode(string $code, string $hash): bool
    {
        return password_verify($code, $hash);
    }

    /**
     * Create a new verification code record
     */
    public function create(int $userId, string $purpose, int $expirationMinutes = 10): array
    {
        $code = self::generateCode();
        $codeHash = self::hashCode($code);
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expirationMinutes} minutes"));
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Invalidate all previous unused codes for this user and purpose
        $this->db->getConnection()->prepare(
            'UPDATE verification_codes SET used = TRUE, used_at = NOW() 
             WHERE user_id = :user_id AND purpose = :purpose AND used = FALSE'
        )->execute(['user_id' => $userId, 'purpose' => $purpose]);

        $this->db->getConnection()->prepare(
            'INSERT INTO verification_codes (user_id, code_hash, purpose, expires_at, ip_address) 
             VALUES (:user_id, :code_hash, :purpose, :expires_at, :ip_address)'
        )->execute([
            'user_id' => $userId,
            'code_hash' => $codeHash,
            'purpose' => $purpose,
            'expires_at' => $expiresAt,
            'ip_address' => $ipAddress
        ]);

        return [
            'code' => $code,
            'expires_at' => $expiresAt,
            'expires_in_minutes' => $expirationMinutes
        ];
    }

    /**
     * Verify a submitted code for a user
     */
    public function verify(int $userId, string $code, string $purpose): array
    {
        // Get the latest unused code for this user and purpose
        $stmt = $this->db->getConnection()->prepare(
            'SELECT id, code_hash, expires_at, used 
             FROM verification_codes 
             WHERE user_id = :user_id AND purpose = :purpose AND used = FALSE 
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId, 'purpose' => $purpose]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            return ['success' => false, 'error' => 'No verification code found. Please request a new one.'];
        }

        // Check expiration
        if (strtotime($record['expires_at']) < time()) {
            return ['success' => false, 'error' => 'Verification code has expired. Please request a new one.'];
        }

        // Verify the code
        if (!self::verifyCode($code, $record['code_hash'])) {
            return ['success' => false, 'error' => 'Invalid verification code. Please try again.'];
        }

        // Mark as used
        $this->db->getConnection()->prepare(
            'UPDATE verification_codes SET used = TRUE, used_at = NOW() WHERE id = :id'
        )->execute(['id' => $record['id']]);

        return ['success' => true, 'message' => 'Verification successful.'];
    }

    /**
     * Check resend rate limit (max 3 resends within 15 minutes)
     */
    public function canResend(int $userId, string $purpose): array
    {
        $fifteenMinutesAgo = date('Y-m-d H:i:s', strtotime('-15 minutes'));

        $stmt = $this->db->getConnection()->prepare(
            'SELECT COUNT(*) as count FROM verification_resend_log 
             WHERE user_id = :user_id AND purpose = :purpose AND created_at > :time_limit'
        );
        $stmt->execute([
            'user_id' => $userId,
            'purpose' => $purpose,
            'time_limit' => $fifteenMinutesAgo
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $count = (int) $result['count'];
        $maxAttempts = 3;

        if ($count >= $maxAttempts) {
            $waitTime = 15 - (int) ((time() - strtotime($fifteenMinutesAgo)) / 60);
            return [
                'allowed' => false,
                'error' => "Too many resend requests. Please wait {$waitTime} minutes before requesting again.",
                'attempts_remaining' => 0
            ];
        }

        return [
            'allowed' => true,
            'attempts_remaining' => $maxAttempts - $count
        ];
    }

    /**
     * Log a resend request
     */
    public function logResend(int $userId, string $purpose): void
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $this->db->getConnection()->prepare(
            'INSERT INTO verification_resend_log (user_id, ip_address, purpose) 
             VALUES (:user_id, :ip_address, :purpose)'
        )->execute([
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'purpose' => $purpose
        ]);
    }

    /**
     * Clean up expired codes (run via cron)
     */
    public function cleanupExpired(): int
    {
        $stmt = $this->db->getConnection()->prepare(
            'DELETE FROM verification_codes WHERE expires_at < NOW() AND used = FALSE'
        );
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Clean up old resend logs
     */
    public function cleanupResendLogs(): int
    {
        $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $stmt = $this->db->getConnection()->prepare(
            'DELETE FROM verification_resend_log WHERE created_at < :time'
        );
        $stmt->execute(['time' => $oneHourAgo]);
        return $stmt->rowCount();
    }
}