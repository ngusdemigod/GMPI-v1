# Email Verification System Setup Guide

## Overview

This guide explains how to set up and use the email verification system for the Church Financial Partnership Portal. The system uses Resend.com to send verification codes to users during signup.

## Features

- **15-minute code expiration** - Verification codes expire after 15 minutes
- **6-character alphanumeric codes** - Cryptographically secure codes (excludes confusing characters like I, O, 0, 1)
- **Rate limiting** - 5-minute cooldown between resend requests
- **Complete verification flow** - Signup → Email → Verify → Login on single page
- **Professional email templates** - Branded HTML emails with clear verification codes

## Prerequisites

1. PHP 8.0 or higher
2. MySQL/MariaDB database
3. Resend.com account with API key
4. Valid domain for sending emails

## Installation Steps

### Step 1: Configure Resend.com

1. Sign up at [https://resend.com](https://resend.com)
2. Go to API Keys in your dashboard
3. Create a new API key
4. Copy your API key

### Step 2: Update Environment Variables

Edit your `.env` file in the root directory:

```env
# Resend Configuration
RESEND_API_KEY=re_your_api_key_here
RESEND_FROM_EMAIL=noreply@yourdomain.com
RESEND_FROM_NAME=Bright Light Ministry
```

Replace:
- `re_your_api_key_here` with your actual Resend API key
- `noreply@yourdomain.com` with your verified sender email from Resend
- `Bright Light Ministry` with your organization name

### Step 3: Run Database Migration

The email verification tables are already included in your main schema (`database/schema.sql`). If you need to add them separately, run:

```sql
-- Email Verifications Table
CREATE TABLE IF NOT EXISTS email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    verification_code VARCHAR(10) NOT NULL,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_code (verification_code),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email Verification Requests (Rate Limiting)
CREATE TABLE IF NOT EXISTS email_verification_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Step 4: Verify File Structure

Ensure these files exist:

```
src/
├── signup.php              # Main signup page with verification flow
├── models/
│   └── EmailVerification.php  # Verification code management
├── config/
│   ├── ResendService.php      # Email sending service
│   └── resend_config.php      # Resend configuration
└── .env.php                 # Environment variables loader
```

## How It Works

### User Flow

1. **Signup Form** - User enters name, email, and password
2. **Account Created** - User record created with `is_verified = FALSE`
3. **Verification Email** - 6-digit code sent via Resend
4. **Enter Code** - User enters code on same page
5. **Code Validation** - System validates code (must be 6 chars, not expired, not used)
6. **Account Verified** - User marked as verified, shown login form
7. **Login** - User signs in and accesses the portal

### Code Generation

- Uses `random_int()` for cryptographically secure random numbers
- 6-character alphanumeric code
- Excludes confusing characters: I, O, 0, 1
- Characters used: A-H, J-N, P-Z, 2-9 (32 possible characters)

### Expiration

- Codes expire after **15 minutes**
- Expired codes cannot be used for verification
- Old codes are automatically cleaned up

### Rate Limiting

- Users can request a new code every **5 minutes**
- Prevents abuse and spam
- Rate limit tracked by email address

## API Endpoints

### Signup Endpoint

**URL:** `src/signup.php`

**Method:** POST

**Parameters:**
- `action` - Must be "signup"
- `first_name` - User's first name
- `last_name` - User's last name
- `email` - User's email address
- `password` - Password (min 8 characters)
- `confirm_password` - Password confirmation

**Response:** Redirects to same page with step changes (form → sent → verify → verified)

### Verification Endpoint

**URL:** `src/signup.php`

**Method:** POST

**Parameters:**
- `action` - Must be "verify"
- `verification_code` - 6-character code from email

**Response:** Redirects to same page with step changes

### Resend Code Endpoint

**URL:** `src/signup.php`

**Method:** POST

**Parameters:**
- `action` - Must be "resend"
- `email` - User's email address

**Response:** Redirects to same page with success/error message

## Class Reference

### EmailVerification Class

**File:** `src/models/EmailVerification.php`

#### Methods

| Method | Description | Parameters | Returns |
|--------|-------------|------------|---------|
| `createVerification()` | Generate and store new code | `userId`, `email` | string|false |
| `verifyCode()` | Validate and use code | `userId`, `code` | bool |
| `isValidCode()` | Check if code is valid | `userId`, `code` | bool |
| `getByCode()` | Get verification by code | `code` | array\|false |
| `hasPendingVerification()` | Check for pending code | `userId` | bool |
| `cleanupExpired()` | Remove expired codes | - | bool |
| `generateVerificationCode()` | Generate new code | - | string |
| `isExpired()` | Check if code expired | `userId` | bool |
| `getTimeRemaining()` | Get seconds until expiry | `userId` | int |

### User Model Methods

**File:** `src/models/User.php`

| Method | Description |
|--------|-------------|
| `sendVerification()` | Generate code and send email |
| `verifyEmail()` | Validate code and mark user verified |

## Troubleshooting

### Issue: "Database tables not found"

**Solution:** Run the migration SQL to create `email_verifications` and `email_verification_requests` tables.

### Issue: "Failed to send verification email"

**Possible causes:**
1. Resend API key is incorrect or missing
2. Sender email not verified in Resend
3. Network connectivity issues

**Solution:**
1. Check `.env` file for correct API key
2. Verify sender email in Resend dashboard
3. Check server logs for detailed error messages

### Issue: "Verification code expired"

**Solution:** Click "Resend" to get a new code. Remember, codes expire after 15 minutes.

### Issue: "Please wait 5 minutes before requesting another verification code"

**Solution:** Rate limiting is working correctly. Wait 5 minutes before requesting another code.

## Security Considerations

1. **Password Hashing** - Uses bcrypt with cost factor 12
2. **SQL Injection Prevention** - All queries use parameterized statements
3. **XSS Prevention** - All output uses `htmlspecialchars()`
4. **CSRF Protection** - Consider adding CSRF tokens for production
5. **Rate Limiting** - Prevents brute force attacks on verification codes

## Testing

### Test Signup Flow

1. Navigate to `src/signup.php`
2. Fill in the form with test data
3. Check your email for the verification code
4. Enter the code on the verification page
5. Complete the signup process

### Test Resend Functionality

1. Request a verification code
2. Wait 4 minutes and try to resend (should fail)
3. Wait 5+ minutes and try to resend (should succeed)

### Test Expiration

1. Request a verification code
2. Wait 16 minutes
3. Try to verify (should fail with "expired" message)

## Support

For issues or questions:
- Check the error logs
- Verify Resend API key is correct
- Ensure database tables exist
- Contact technical support

## License

Copyright © Bright Light Ministry Int'l. All rights reserved.