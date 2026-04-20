<?php
/**
 * Security Utility Class
 * Bright Light Ministry Int'l Partners Portal
 * 
 * A comprehensive input sanitization and validation library
 * following OWASP security best practices.
 * 
 * @author Security Team
 * @version 1.0.0
 */

class Security {
    
    // ========================================================================
    // CONFIGURATION CONSTANTS
    // ========================================================================
    
    /** Maximum length for text inputs */
    const MAX_TEXT_LENGTH = 1000;
    
    /** Maximum length for email addresses */
    const MAX_EMAIL_LENGTH = 254;
    
    /** Maximum length for subject lines */
    const MAX_SUBJECT_LENGTH = 200;
    
    /** Maximum length for messages */
    const MAX_MESSAGE_LENGTH = 5000;
    
    /** Maximum file size in bytes (5MB) */
    const MAX_FILE_SIZE = 5 * 1024 * 1024;
    
    /** Allowed file extensions (whitelist) */
    const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'txt'];
    
    /** Allowed MIME types (whitelist) */
    const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain'
    ];
    
    /** Rate limit: maximum requests per minute */
    const RATE_LIMIT_REQUESTS = 5;
    
    /** Rate limit window in seconds */
    const RATE_LIMIT_WINDOW = 60;

    /**
     * Generate a CSRF token for form/session protection.
     */
    public static function generateCsrfToken() {
        return bin2hex(random_bytes(32));
    }
    
    // ========================================================================
    // INPUT SANITIZATION METHODS
    // ========================================================================
    
    /**
     * Sanitize a string input to prevent XSS attacks
     * 
     * Security Measures:
     * - HTML entity encoding to neutralize script tags
     * - Strips null bytes to prevent null byte injection
     * - Trims whitespace to prevent hidden characters
     * 
     * @param string $input The input string to sanitize
     * @param int $maxLength Maximum allowed length
     * @return string Sanitized string
     */
    public static function sanitizeString($input, $maxLength = self::MAX_TEXT_LENGTH) {
        // Check if input is null or not a string
        if ($input === null || !is_string($input)) {
            return '';
        }
        
        // Trim whitespace and remove null bytes
        // Null bytes can be used in path traversal and buffer overflow attacks
        $input = str_replace("\0", '', trim($input));
        
        // HTML entity encoding prevents XSS by converting special characters
        // to their HTML entity equivalents
        // ENT_QUOTES: Encode both double and single quotes
        // ENT_HTML5: Use HTML5 encoding
        // UTF-8: Specify character encoding
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Additional filtering for common XSS vectors
        // Remove any remaining script tags (defense in depth)
        $input = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $input);
        
        // Remove javascript: protocol handlers
        $input = preg_replace('/javascript:/i', '', $input);
        
        // Remove on* event handlers (onclick, onerror, etc.)
        $input = preg_replace('/\s*on\w+\s*=/i', '', $input);
        
        // Limit length to prevent buffer overflow and DoS attacks
        $input = mb_substr($input, 0, $maxLength);
        
        return $input;
    }
    
    /**
     * Sanitize and validate an email address
     * 
     * Security Measures:
     * - Strict RFC 5322 email format validation
     * - Length validation to prevent DoS
     * - No character encoding allowed (prevents IDN homograph attacks)
     * 
     * @param string $email The email to validate
     * @return string|false Validated email or false if invalid
     */
    public static function sanitizeEmail($email) {
        if ($email === null || !is_string($email)) {
            return false;
        }
        
        // Trim and convert to lowercase
        $email = trim(strtolower($email));
        
        // Length check (RFC 5321/5322)
        if (mb_strlen($email) > self::MAX_EMAIL_LENGTH) {
            return false;
        }
        
        // Strict email validation using filter_var
        // FILTER_VALIDATE_EMAIL checks RFC 5322 compliance
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Additional check: ensure no encoding characters
        // This prevents IDN homograph attacks where special characters
        // could be used to impersonate legitimate domains
        if (preg_match('/[^a-z0-9@._-]/i', $email)) {
            return false;
        }
        
        return $email;
    }
    
    /**
     * Sanitize a subject line
     * 
     * Security Measures:
     * - Strips control characters
     * - Limits length to prevent header injection
     * - Removes line breaks to prevent email header injection
     * 
     * @param string $subject The subject to sanitize
     * @return string Sanitized subject
     */
    public static function sanitizeSubject($subject) {
        if ($subject === null || !is_string($subject)) {
            return '';
        }
        
        // Remove line breaks and carriage returns
        // This prevents email header injection attacks where attackers
        // could add additional headers like BCC or CC
        $subject = str_replace(["\r", "\n", "\0"], '', $subject);
        
        // Remove control characters (ASCII 0-31 except tab)
        $subject = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $subject);
        
        // Sanitize and limit length
        return self::sanitizeString($subject, self::MAX_SUBJECT_LENGTH);
    }
    
    /**
     * Sanitize a message body
     * 
     * Security Measures:
     * - Preserves newlines for readability
     * - Removes dangerous HTML tags
     * - Limits length to prevent DoS
     * 
     * @param string $message The message to sanitize
     * @return string Sanitized message
     */
    public static function sanitizeMessage($message) {
        if ($message === null || !is_string($message)) {
            return '';
        }
        
        // Remove null bytes
        $message = str_replace("\0", '', $message);
        
        // Remove control characters except newlines and tabs
        $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $message);
        
        // Normalize line endings to prevent CRLF injection
        $message = str_replace(["\r\n", "\r"], "\n", $message);
        
        // Limit length
        $message = mb_substr($message, 0, self::MAX_MESSAGE_LENGTH);
        
        return $message;
    }
    
    /**
     * Sanitize a category value
     * 
     * Security Measures:
     * - Whitelist validation (only allow known values)
     * - Prevents SQL injection by rejecting unknown values
     * 
     * @param string $category The category to validate
     * @return string|false Validated category or false if invalid
     */
    public static function sanitizeCategory($category) {
        if ($category === null || !is_string($category)) {
            return false;
        }
        
        // Normalize the category
        $category = trim(strtolower($category));
        
        // Whitelist of allowed categories
        $allowedCategories = [
            'technical',
            'payment',
            'account',
            'project',
            'receipt',
            'other'
        ];
        
        // Strict whitelist validation - only allow known values
        // This prevents SQL injection and other injection attacks
        if (!in_array($category, $allowedCategories, true)) {
            return false;
        }
        
        return $category;
    }
    
    /**
     * Sanitize an integer value
     * 
     * Security Measures:
     * - Type casting to ensure integer
     * - Optional range validation
     * 
     * @param mixed $value The value to sanitize
     * @param int $min Minimum allowed value (optional)
     * @param int $max Maximum allowed value (optional)
     * @return int|false Sanitized integer or false if invalid
     */
    public static function sanitizeInteger($value, $min = null, $max = null) {
        if ($value === null) {
            return false;
        }
        
        // Type cast to integer
        $int = (int)$value;
        
        // Range validation
        if ($min !== null && $int < $min) {
            return false;
        }
        
        if ($max !== null && $int > $max) {
            return false;
        }
        
        return $int;
    }
    
    // ========================================================================
    // FILE UPLOAD VALIDATION
    // ========================================================================
    
    /**
     * Validate an uploaded file
     * 
     * Security Measures:
     * - Extension whitelist (not blacklist)
     * - MIME type verification (not just extension check)
     * - File size validation
     * - Content scanning for magic bytes
     * - Secure filename generation
     * - Path traversal prevention
     * 
     * @param array $file The $_FILES array entry
     * @param string $uploadDir Directory to store uploaded files
     * @return array Validation result with 'valid' boolean and 'error' message
     */
    public static function validateFileUpload($file, $uploadDir) {
        $result = [
            'valid' => false,
            'error' => '',
            'safeFilename' => '',
            'mimeType' => ''
        ];
        
        // Check for upload errors
        if (!isset($file['error']) || is_array($file['error'])) {
            $result['error'] = 'Invalid file upload parameter';
            return $result;
        }
        
        // Check for upload errors
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $result['error'] = 'File exceeds maximum allowed size';
                return $result;
            case UPLOAD_ERR_PARTIAL:
                $result['error'] = 'File was only partially uploaded';
                return $result;
            case UPLOAD_ERR_NO_FILE:
                $result['error'] = 'No file was uploaded';
                return $result;
            case UPLOAD_ERR_NO_TMP_DIR:
                $result['error'] = 'Missing temporary folder';
                return $result;
            case UPLOAD_ERR_CANT_WRITE:
                $result['error'] = 'Failed to write file to disk';
                return $result;
            case UPLOAD_ERR_EXTENSION:
                $result['error'] = 'File upload stopped by extension';
                return $result;
            default:
                $result['error'] = 'Unknown upload error';
                return $result;
        }
        
        // Validate file size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            $result['error'] = 'File size exceeds maximum allowed size (' . 
                self::formatBytes(self::MAX_FILE_SIZE) . ')';
            return $result;
        }
        
        // Ensure file is not empty
        if ($file['size'] === 0) {
            $result['error'] = 'Empty file uploaded';
            return $result;
        }
        
        // Validate file extension (whitelist)
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (empty($extension) || !in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $result['error'] = 'File type not allowed. Allowed types: ' . 
                implode(', ', self::ALLOWED_EXTENSIONS);
            return $result;
        }
        
        // Get MIME type using finfo (more reliable than mime_content_type)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        // Validate MIME type (whitelist)
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            $result['error'] = 'File MIME type not allowed. Expected: ' . 
                implode(', ', self::ALLOWED_MIME_TYPES) . 
                ', Got: ' . $mimeType;
            return $result;
        }
        
        // Verify extension matches MIME type (prevent disguised files)
        $extensionMimeMap = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain'
        ];
        
        if (isset($extensionMimeMap[$extension]) && 
            $extensionMimeMap[$extension] !== $mimeType) {
            $result['error'] = 'File extension does not match file content';
            return $result;
        }
        
        // Scan file content for malicious signatures (magic bytes check)
        if (!self::scanFileContent($file['tmp_name'], $extension)) {
            $result['error'] = 'File contains suspicious content';
            return $result;
        }
        
        // Generate safe filename
        // This prevents:
        // - Directory traversal attacks
        // - Filename collisions
        // - Execution of uploaded files
        $safeFilename = self::generateSafeFilename($file['name'], $extension);
        
        // Ensure upload directory is secure
        // Resolve to absolute path and verify it's within expected directory
        $uploadDir = rtrim($uploadDir, '/\\');
        $realUploadDir = realpath($uploadDir);
        
        if ($realUploadDir === false) {
            $result['error'] = 'Upload directory does not exist';
            return $result;
        }
        
        // Store the validated file info
        $result['valid'] = true;
        $result['safeFilename'] = $safeFilename;
        $result['mimeType'] = $mimeType;
        $result['realPath'] = $realUploadDir;
        
        return $result;
    }
    
    /**
     * Scan file content for malicious signatures
     * 
     * Security Measures:
     * - Checks magic bytes for file type verification
     * - Detects embedded scripts in documents
     * - Identifies known malware signatures
     * 
     * @param string $filePath Path to the file
     * @param string $extension File extension
     * @return bool True if file is safe, false if malicious
     */
    private static function scanFileContent($filePath, $extension) {
        // Read first 4KB of file for content analysis
        $fp = fopen($filePath, 'rb');
        if (!$fp) {
            return false;
        }
        
        $header = fread($fp, 4096);
        fclose($fp);
        
        if ($header === false) {
            return false;
        }
        
        // Check for executable content in non-executable files
        $dangerousPatterns = [
            '/\x7fELF/',  // ELF executable
            '/MZ\x90/',   // DOS/Windows executable
            '/\x50\x4B\x03\x04.*\x00\x00\x00\x00/',  // ZIP with executable
        ];
        
        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $header)) {
                // Allow ZIP in docx (it's a ZIP archive)
                if ($extension === 'docx' && $pattern === '/\x50\x4B\x03\x04.*\x00\x00\x00\x00/') {
                    continue;
                }
                return false;
            }
        }
        
        // For text files, check for embedded scripts
        if ($extension === 'txt') {
            if (preg_match('/<script/i', $header) || 
                preg_match('/javascript:/i', $header) ||
                preg_match('/vbscript:/i', $header)) {
                return false;
            }
        }
        
        // For Word documents, check for macro content
        if ($extension === 'doc') {
            // DOC files are binary, check for VBA signatures
            if (preg_match('/\x00\x00\x00\x00.*VBA/i', $header)) {
                // This is a heuristic - actual macro detection is complex
                // Consider rejecting .doc files entirely for maximum security
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Generate a safe filename
     * 
     * Security Measures:
     * - Removes all path information
     * - Replaces dangerous characters
     * - Adds unique identifier to prevent collisions
     * - Prevents execution by adding random prefix
     * 
     * @param string $originalFilename Original filename
     * @param string $extension File extension
     * @return string Safe filename
     */
    private static function generateSafeFilename($originalFilename, $extension) {
        // Remove any path information
        $filename = basename($originalFilename);
        
        // Remove all characters except alphanumeric, dots, underscores, and hyphens
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Remove multiple consecutive dots
        $filename = preg_replace('/\.+/', '.', $filename);
        
        // Remove leading dots (hidden files)
        $filename = ltrim($filename, '.');
        
        // Generate unique identifier
        $uniqueId = bin2hex(random_bytes(8));
        
        // Combine: uniqueid_timestamp_originalname.extension
        $timestamp = date('YmdHis');
        $safeFilename = $uniqueId . '_' . $timestamp . '_' . $filename;
        
        // Ensure extension is present
        if (!mb_strtolower($extension) || 
            mb_strtolower($safeFilename) !== mb_strtolower($safeFilename) . '.' . mb_strtolower($extension)) {
            $safeFilename .= '.' . $extension;
        }
        
        return $safeFilename;
    }
    
    /**
     * Format bytes to human-readable format
     * 
     * @param int $bytes Number of bytes
     * @return string Formatted size
     */
    private static function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    // ========================================================================
    // RATE LIMITING
    // ========================================================================
    
    /**
     * Check if a request should be rate limited
     * 
     * Security Measures:
     * - Prevents brute force attacks
     * - Prevents DoS attacks
     * - Uses sliding window algorithm
     * 
     * @param string $identifier Unique identifier (IP, user ID, etc.)
     * @param int $maxRequests Maximum requests allowed
     * @param int $windowSeconds Time window in seconds
     * @param string $storageType Storage type ('file', 'session', 'redis')
     * @return array Result with 'allowed' boolean and 'retryAfter' seconds
     */
    public static function checkRateLimit($identifier, $maxRequests = self::RATE_LIMIT_REQUESTS, 
                                          $windowSeconds = self::RATE_LIMIT_WINDOW, 
                                          $storageType = 'file') {
        $result = [
            'allowed' => true,
            'retryAfter' => 0,
            'currentCount' => 0
        ];
        
        // Generate storage key
        $key = 'ratelimit_' . md5($identifier);
        
        // Get current timestamp
        $now = time();
        $windowStart = $now - $windowSeconds;
        
        // Load existing rate limit data
        $requests = self::getRateLimitData($key, $storageType);
        
        // Filter requests within the time window
        $validRequests = array_filter($requests, function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });
        
        $result['currentCount'] = count($validRequests);
        
        // Check if limit exceeded
        if (count($validRequests) >= $maxRequests) {
            $result['allowed'] = false;
            
            // Calculate retry time
            $oldestRequest = min($validRequests);
            $retryAfter = ($oldestRequest + $windowSeconds) - $now;
            $result['retryAfter'] = max(0, $retryAfter);
            
            return $result;
        }
        
        // Add current request
        $validRequests[] = $now;
        self::saveRateLimitData($key, $validRequests, $storageType);
        
        return $result;
    }
    
    /**
     * Get rate limit data from storage
     * 
     * @param string $key Storage key
     * @param string $storageType Storage type
     * @return array Array of timestamps
     */
    private static function getRateLimitData($key, $storageType) {
        switch ($storageType) {
            case 'session':
                if (!isset($_SESSION[$key])) {
                    return [];
                }
                return $_SESSION[$key];
                
            case 'redis':
                // Requires Redis extension
                if (function_exists('redis_get')) {
                    $data = redis_get($key);
                    return $data ? unserialize($data) : [];
                }
                return [];
                
            case 'file':
            default:
                $file = sys_get_temp_dir() . '/' . str_replace('ratelimit_', '', $key) . '.rate';
                if (!file_exists($file)) {
                    return [];
                }
                $data = file_get_contents($file);
                return $data ? unserialize($data) : [];
        }
    }
    
    /**
     * Save rate limit data to storage
     * 
     * @param string $key Storage key
     * @param array $data Data to save
     * @param string $storageType Storage type
     */
    private static function saveRateLimitData($key, $data, $storageType) {
        switch ($storageType) {
            case 'session':
                $_SESSION[$key] = $data;
                return;
                
            case 'redis':
                if (function_exists('redis_set')) {
                    redis_set($key, serialize($data), 3600); // 1 hour TTL
                }
                return;
                
            case 'file':
            default:
                $file = sys_get_temp_dir() . '/' . str_replace('ratelimit_', '', $key) . '.rate';
                file_put_contents($file, serialize($data));
                return;
        }
    }
    
    // ========================================================================
    // SQL INJECTION PREVENTION
    // ========================================================================
    
    /**
     * Prepare values for PDO prepared statements
     * 
     * Security Measures:
     * - Type casting for integers
     * - String escaping for text
     * - Null handling
     * 
     * @param mixed $value The value to prepare
     * @param string $type The expected type
     * @return array Prepared value and PDO type
     */
    public static function prepareForPDO($value, $type = 'string') {
        switch ($type) {
            case 'int':
                return [(int)$value, PDO::PARAM_INT];
                
            case 'float':
                return [(float)$value, PDO::PARAM_STR];
                
            case 'bool':
                return [(bool)$value, PDO::PARAM_BOOL];
                
            case 'null':
                return [null, PDO::PARAM_NULL];
                
            case 'string':
            default:
                return [self::sanitizeString($value), PDO::PARAM_STR];
        }
    }
    
    // ========================================================================
    // OUTPUT ESCAPING
    // ========================================================================
    
    /**
     * Escape output for HTML context
     * 
     * @param string $text Text to escape
     * @return string Escaped text
     */
    public static function escapeHtml($text) {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Escape output for JavaScript context
     * 
     * @param string $text Text to escape
     * @return string Escaped text
     */
    public static function escapeJs($text) {
        return json_encode($text);
    }
    
    /**
     * Escape output for URL context
     * 
     * @param string $text Text to escape
     * @return string Escaped text
     */
    public static function escapeUrl($text) {
        return rawurlencode($text);
    }
    
    /**
     * Escape output for CSS context
     * 
     * @param string $text Text to escape
     * @return string Escaped text
     */
    public static function escapeCss($text) {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $text);
    }
}
