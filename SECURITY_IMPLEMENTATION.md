# Security Implementation Documentation
## Bright Light Ministry Int'l Partners Portal

This document describes the comprehensive security implementation for the support form system, following OWASP and industry security best practices.

---

## Table of Contents

1. [Overview](#overview)
2. [Security Features](#security-features)
3. [File Structure](#file-structure)
4. [Usage Guide](#usage-guide)
5. [Security Measures Explained](#security-measures-explained)
6. [Testing](#testing)
7. [Configuration](#configuration)

---

## Overview

This security implementation provides production-ready input sanitization and file upload validation for the support form system. It protects against common web vulnerabilities including:

- **XSS (Cross-Site Scripting)**
- **SQL Injection**
- **CSRF (Cross-Site Request Forgery)**
- **Path Traversal**
- **Malicious File Uploads**
- **Rate Limiting/DoS Protection**
- **Email Header Injection**

---

## Security Features

### 1. Input Sanitization

All text inputs are sanitized using the `Security` class:

| Method | Purpose | Security Measures |
|--------|---------|-------------------|
| `sanitizeString()` | General text input | HTML encoding, null byte removal, script tag removal |
| `sanitizeEmail()` | Email validation | RFC 5322 validation, length check, IDN homograph prevention |
| `sanitizeSubject()` | Subject lines | CRLF removal, control character stripping |
| `sanitizeMessage()` | Message bodies | CRLF normalization, length limiting |
| `sanitizeCategory()` | Whitelist validation | Strict whitelist, SQL injection prevention |
| `sanitizeInteger()` | Numeric inputs | Type casting, range validation |

### 2. File Upload Validation

The `validateFileUpload()` method implements multiple layers of protection:

1. **Extension Whitelist** - Only allowed extensions are accepted
2. **MIME Type Verification** - File content is verified using `finfo`
3. **Extension/MIME Match** - Prevents disguised file uploads
4. **File Size Limits** - Maximum 5MB per file
5. **Content Scanning** - Magic bytes detection for malicious content
6. **Secure Filename Generation** - Random prefix, timestamp, sanitized name
7. **Path Traversal Prevention** - Uses `basename()` to remove path info

### 3. Rate Limiting

Prevents abuse through request throttling:

- Default: 5 requests per 60 seconds per user/IP
- Supports multiple storage backends (session, file, Redis)
- Sliding window algorithm

### 4. CSRF Protection

- Token generated per session
- Verified using `hash_equals()` to prevent timing attacks
- Token regenerated after successful submission

### 5. SQL Injection Prevention

- PDO prepared statements with proper parameter binding
- Type casting for all inputs
- Whitelist validation for categorical data

---

## File Structure

```
src/
├── config/
│   └── Security.php          # Main security utility class
├── handlers/
│   └── SupportFormHandler.php # Example form handler integration
├── models/
│   └── SupportTicket.php     # Model with security integration
└── support.php               # Support form with security measures

tests/
└── SecurityTest.php          # Unit tests for Security class

uploads/
└── tickets/                  # Secure file upload directory
    ├── .htaccess            # Prevents file execution
    └── index.php            # Prevents directory listing
```

---

## Usage Guide

### Basic Input Sanitization

```php
require_once 'config/Security.php';

// Sanitize text input
$cleanName = Security::sanitizeString($_POST['name']);

// Validate email
$email = Security::sanitizeEmail($_POST['email']);
if ($email === false) {
    // Handle invalid email
}

// Sanitize subject (prevents header injection)
$subject = Security::sanitizeSubject($_POST['subject']);

// Whitelist validation for category
$category = Security::sanitizeCategory($_POST['category']);
if ($category === false) {
    // Handle invalid category
}
```

### File Upload Validation

```php
require_once 'config/Security.php';

$uploadDir = __DIR__ . '/uploads/tickets';

// Validate uploaded file
$result = Security::validateFileUpload($_FILES['attachment'], $uploadDir);

if ($result['valid']) {
    // Move file to secure location
    $uploadPath = $result['realPath'] . '/' . $result['safeFilename'];
    move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadPath);
} else {
    // Handle validation error
    echo $result['error'];
}
```

### Rate Limiting

```php
// Check rate limit for user or IP
$identifier = $userId ?? $_SERVER['REMOTE_ADDR'];
$result = Security::checkRateLimit($identifier);

if (!$result['allowed']) {
    http_response_code(429);
    echo "Too many requests. Try again in {$result['retryAfter']} seconds.";
    exit;
}
```

### CSRF Token

```php
// Generate token
$token = Security::generateCsrfToken();

// In form
<input type="hidden" name="csrf_token" value="<?php echo $token; ?>">

// Verify token
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    // Invalid token - reject request
}
```

---

## Security Measures Explained

### XSS Prevention

```php
// HTML entity encoding
$input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');

// Remove script tags (defense in depth)
$input = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $input);

// Remove javascript: protocol
$input = preg_replace('/javascript:/i', '', $input);

// Remove event handlers
$input = preg_replace('/\s*on\w+\s*=/i', '', $input);
```

### SQL Injection Prevention

```php
// Use PDO prepared statements
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
$stmt->bindValue(':email', $email, PDO::PARAM_STR);
$stmt->execute();

// Whitelist validation for categories
$allowed = ['technical', 'payment', 'account'];
if (!in_array($category, $allowed, true)) {
    throw new Exception('Invalid category');
}
```

### File Upload Security

```php
// Extension whitelist (NOT blacklist)
$allowedExtensions = ['pdf', 'jpg', 'png', 'doc', 'docx', 'txt'];
if (!in_array($extension, $allowedExtensions, true)) {
    return false;
}

// MIME type verification
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

// Generate safe filename
$uniqueId = bin2hex(random_bytes(8));
$safeFilename = $uniqueId . '_' . $timestamp . '_' . $originalName;
```

---

## Testing

### Running Unit Tests

```bash
php tests/SecurityTest.php
```

### Test Coverage

The test suite covers:

- XSS prevention in string sanitization
- Email validation (valid, invalid, edge cases)
- Subject sanitization (header injection prevention)
- Message sanitization (CRLF normalization)
- Category whitelist validation
- Integer sanitization with range checking
- File upload validation (valid files, invalid extensions, size limits)
- Path traversal prevention
- Extension/MIME type mismatch detection
- Rate limiting functionality
- Output escaping for different contexts

### Example Test Output

```
========================================
Security Class Unit Tests
========================================

--- Testing sanitizeString() ---
✓ PASS: Script tags should be removed
✓ PASS: javascript: protocol should be removed
✓ PASS: Event handlers should be removed
✓ PASS: HTML special characters should be encoded
...

========================================
Test Summary
========================================
Tests Run: 45
Passed: 45
Failed: 0
========================================
```

---

## Configuration

### Environment Variables

Set the following environment variables for reCAPTCHA:

```bash
RECAPTCHA_SITE_KEY=your-site-key-here
RECAPTCHA_SECRET_KEY=your-secret-key-here
```

### Security Constants

You can customize security settings in `Security.php`:

```php
// Maximum input lengths
const MAX_TEXT_LENGTH = 1000;
const MAX_EMAIL_LENGTH = 254;
const MAX_SUBJECT_LENGTH = 200;
const MAX_MESSAGE_LENGTH = 5000;

// File upload settings
const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'txt'];

// Rate limiting
const RATE_LIMIT_REQUESTS = 5;
const RATE_LIMIT_WINDOW = 60; // seconds
```

### Upload Directory Security

The upload directory includes protection files:

**.htaccess** (Apache):
```apache
Options -ExecCGI
AddHandler disable-all . . . . . . . . .
SetHandler disable-all
php_flag engine off
```

**index.php**:
```php
<?php header("HTTP/1.0 403 Forbidden"); exit("Access denied");
```

---

## Best Practices

1. **Always sanitize user input** before processing or storing
2. **Use prepared statements** for all database queries
3. **Validate on both client and server side** (client-side is for UX only)
4. **Use HTTPS** for all form submissions
5. **Keep dependencies updated** to patch security vulnerabilities
6. **Log security events** for monitoring and incident response
7. **Regular security audits** of the codebase

---

## References

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [OWASP Input Validation Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Input_Validation_Cheat_Sheet.html)
- [OWASP File Upload Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)