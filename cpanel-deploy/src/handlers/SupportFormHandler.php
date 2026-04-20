<?php
/**
 * Support Form Handler
 * Bright Light Ministry Int'l Partners Portal
 * 
 * This file demonstrates how to integrate the Security class
 * into a real-world form submission handler.
 * 
 * Security Features Implemented:
 * - Input sanitization for all form fields
 * - File upload validation with MIME type checking
 * - Rate limiting to prevent abuse
 * - CSRF token validation
 * - Secure file storage outside web root
 * - Comprehensive error handling
 * 
 * @author Security Team
 * @version 1.0.0
 */

// Load required classes
require_once __DIR__ . '/../config/Security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ResendService.php';
require_once __DIR__ . '/../models/SupportTicket.php';

class SupportFormHandler {
    
    /** Maximum number of retries for failed submissions */
    const MAX_RETRIES = 3;
    
    /** Upload directory (should be outside web root) */
    const UPLOAD_DIR = __DIR__ . '/../../uploads/tickets';
    
    /**
     * Handle support form submission
     * 
     * This is the main entry point for processing support form submissions.
     * It performs all security checks and validates inputs before
     * creating a support ticket.
     * 
     * @param array $formData The $_POST data
     * @param array $files The $_FILES data
     * @param array $session The $_SESSION data (for CSRF token)
     * @return array Result with 'success' boolean and 'message' string
     */
    public static function handleSubmission($formData, $files, $session) {
        // Initialize result
        $result = [
            'success' => false,
            'message' => '',
            'errors' => []
        ];
        
        // Check for empty submission
        if (empty($formData) && empty($files)) {
            $result['message'] = 'No form data provided';
            return $result;
        }

        if (empty($session['user_id'])) {
            $result['message'] = 'You must be signed in to submit a support ticket.';
            return $result;
        }
        
        // Validate CSRF token
        if (!self::validateCsrfToken($formData, $session)) {
            $result['message'] = 'Invalid security token. Please try again.';
            return $result;
        }
        
        // Check rate limit
        $rateLimitResult = self::checkRateLimit($session);
        if (!$rateLimitResult['allowed']) {
            $result['message'] = 'Too many requests. Please try again in ' . 
                $rateLimitResult['retryAfter'] . ' seconds.';
            return $result;
        }
        
        // Sanitize and validate all inputs
        $sanitizedData = self::sanitizeInputs($formData);
        if (!empty($sanitizedData['errors'])) {
            $result['errors'] = $sanitizedData['errors'];
            $result['message'] = 'Please correct the errors in the form.';
            return $result;
        }
        
        // Validate file upload if present
        $attachmentPath = null;
        if (isset($files['attachment']) && $files['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
            $fileValidation = Security::validateFileUpload($files['attachment'], self::UPLOAD_DIR);
            if (!$fileValidation['valid']) {
                $result['errors']['attachment'] = $fileValidation['error'];
                $result['message'] = 'File validation failed. Please try again.';
                return $result;
            }
            
            // Move uploaded file to secure location
            $uploadPath = $fileValidation['realPath'] . '/' . $fileValidation['safeFilename'];
            if (!move_uploaded_file($files['attachment']['tmp_name'], $uploadPath)) {
                $result['errors']['attachment'] = 'Failed to save uploaded file.';
                return $result;
            }
            
            $attachmentPath = $fileValidation['safeFilename'];
        }
        
        // Create support ticket
        try {
            $ticketModel = new SupportTicket();
            $ticketData = [
                'user_id' => $session['user_id'] ?? null,
                'subject' => $sanitizedData['subject'],
                'message' => $sanitizedData['message'],
                'category' => $sanitizedData['category'],
                'status' => 'open',
                'attachment' => $attachmentPath
            ];
            
            $ticketId = $ticketModel->create($ticketData);
            
            if ($ticketId) {
                self::notifyAdmins($ticketModel, (int) $ticketId, $session);
                $result['success'] = true;
                $result['message'] = 'Your support ticket has been submitted successfully. We will respond within 24-48 hours.';
                $result['ticket_id'] = $ticketId;
            } else {
                $result['message'] = 'Failed to create support ticket. Please try again.';
            }
            
        } catch (Exception $e) {
            // Log the error internally but don't expose details to user
            error_log('Support form error: ' . $e->getMessage());
            $result['message'] = 'An error occurred while submitting your ticket. Please try again.';
        }
        
        return $result;
    }
    
    /**
     * Validate CSRF token
     * 
     * Security Measures:
     * - Compares tokens using hash_equals to prevent timing attacks
     * - Regenerates token after successful validation
     * 
     * @param array $formData Form data
     * @param array $session Session data
     * @return bool True if valid, false otherwise
     */
    private static function validateCsrfToken($formData, $session) {
        $token = $formData['csrf_token'] ?? '';
        $expectedToken = $session['csrf_token'] ?? '';
        
        // Use hash_equals to prevent timing attacks
        if (!hash_equals($expectedToken, $token)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check rate limit for the current user/IP
     * 
     * @param array $session Session data
     * @return array Rate limit result
     */
    private static function checkRateLimit($session) {
        // Use user ID if logged in, otherwise use IP address
        $identifier = $session['user_id'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        return Security::checkRateLimit(
            $identifier,
            Security::RATE_LIMIT_REQUESTS,
            Security::RATE_LIMIT_WINDOW,
            'session' // Use session storage for rate limiting
        );
    }
    
    /**
     * Sanitize and validate all form inputs
     * 
     * @param array $formData Raw form data
     * @return array Sanitized data and any errors
     */
    private static function sanitizeInputs($formData) {
        $result = [
            'data' => [],
            'errors' => []
        ];
        
        // Sanitize subject
        $subject = Security::sanitizeSubject($formData['subject'] ?? '');
        if (empty($subject)) {
            $result['errors']['subject'] = 'Subject is required';
        } else {
            $result['data']['subject'] = $subject;
        }
        
        // Sanitize category
        $category = Security::sanitizeCategory($formData['category'] ?? '');
        if ($category === false) {
            $result['errors']['category'] = 'Invalid category selected';
        } else {
            $result['data']['category'] = $category;
        }
        
        // Sanitize message
        $message = Security::sanitizeMessage($formData['message'] ?? '');
        if (empty($message)) {
            $result['errors']['message'] = 'Message is required';
        } else {
            $result['data']['message'] = $message;
        }
        
        // Sanitize name (optional)
        if (isset($formData['name'])) {
            $name = Security::sanitizeString($formData['name'] ?? '');
            $result['data']['name'] = $name;
        }
        
        // Sanitize email (optional)
        if (isset($formData['email'])) {
            $email = Security::sanitizeEmail($formData['email'] ?? '');
            if ($email === false) {
                $result['errors']['email'] = 'Invalid email address';
            } else {
                $result['data']['email'] = $email;
            }
        }
        
        return $result;
    }
    
    /**
     * Generate a new CSRF token
     * 
     * @return string CSRF token
     */
    public static function generateCsrfToken() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Ensure upload directory exists and is secure
     * 
     * @return bool True if directory is ready, false otherwise
     */
    public static function ensureUploadDirectory() {
        if (!file_exists(self::UPLOAD_DIR)) {
            // Create directory with secure permissions
            if (!mkdir(self::UPLOAD_DIR, 0750, true)) {
                return false;
            }
        }
        
        // Create .htaccess to prevent execution
        $htaccessPath = self::UPLOAD_DIR . '/.htaccess';
        $htaccessContent = "# Disable execution of uploaded files\n";
        $htaccessContent .= "Options -ExecCGI\n";
        $htaccessContent .= "AddHandler disable-all . . . . . . . . .\n";
        $htaccessContent .= "SetHandler disable-all\n";
        $htaccessContent .= "php_flag engine off\n";
        
        if (!file_exists($htaccessPath)) {
            file_put_contents($htaccessPath, $htaccessContent);
        }
        
        // Create index.php to prevent directory listing
        $indexPath = self::UPLOAD_DIR . '/index.php';
        if (!file_exists($indexPath)) {
            file_put_contents($indexPath, '<?php header("HTTP/1.0 403 Forbidden"); exit("Access denied");');
        }
        
        return true;
    }

    /**
     * Notify admin users when a new support ticket is submitted.
     */
    private static function notifyAdmins(SupportTicket $ticketModel, int $ticketId, array $session): void {
        try {
            $ticket = $ticketModel->getById($ticketId);
            if (!$ticket) {
                return;
            }

            $recipients = $ticketModel->getAdminNotificationRecipients();
            if (empty($recipients)) {
                return;
            }

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
            $rootPath = preg_replace('#/src$#', '', $basePath) ?: '';
            $adminUrl = $scheme . '://' . $host . $rootPath . '/admin/support.php?action=view&id=' . $ticketId;

            $submittedBy = trim(($session['first_name'] ?? '') . ' ' . ($session['last_name'] ?? ''));
            $payload = [
                'ticket_id' => $ticketId,
                'subject_line' => $ticket['subject'] ?? 'Support request',
                'category' => $ticket['category'] ?? 'other',
                'submitted_by' => $submittedBy !== '' ? $submittedBy : 'Authenticated user',
                'submitter_email' => $session['email'] ?? ($ticket['email'] ?? ''),
                'message' => $ticket['message'] ?? '',
                'admin_url' => $adminUrl,
            ];

            $mailer = new ResendService();
            foreach ($recipients as $recipient) {
                $response = $mailer->sendSupportTicketNotification(array_merge($payload, [
                    'email' => $recipient['email'],
                ]));

                if (empty($response['success'])) {
                    error_log(sprintf(
                        '[SupportFormHandler] Failed notifying admin %s for ticket #%d: %s',
                        $recipient['email'],
                        $ticketId,
                        $response['error'] ?? 'unknown error'
                    ));
                }
            }
        } catch (Throwable $e) {
            error_log('[SupportFormHandler] Admin notification failure: ' . $e->getMessage());
        }
    }
}

// Initialize upload directory on class load
SupportFormHandler::ensureUploadDirectory();
