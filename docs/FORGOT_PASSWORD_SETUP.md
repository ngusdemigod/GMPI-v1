# Forgot Password System Setup Guide

This guide will walk you through setting up the forgot password system for both user and admin login pages using Resend as the email service.

## Overview

The forgot password system includes:

1. **User Portal** (`/src/`)
   - `forgot-password.php` - Request password reset
   - `reset-password.php` - Reset password with token
   - `handlers/forgot-password.php` - Handle reset request
   - `handlers/reset-password.php` - Process password reset

2. **Admin Portal** (`/admin/`)
   - `forgot-password.php` - Request password reset
   - `reset-password.php` - Reset password with token
   - `handlers/forgot-password.php` - Handle reset request
   - `handlers/reset-password.php` - Process password reset

3. **Supporting Files**
   - `src/models/PasswordReset.php` - User password reset model
   - `src/models/AdminPasswordReset.php` - Admin password reset model
   - `src/models/Admin2FA.php` - Admin 2FA model
   - `src/helpers/EmailService.php` - Email service using Resend
   - `src/config/resend_config.php` - Resend configuration

## Step 1: Set Up Resend Account

1. **Sign up for Resend**
   - Go to [https://resend.com](https://resend.com)
   - Create an account or sign in

2. **Get Your API Key**
   - Navigate to **API Keys** in your dashboard
   - Click **Create API Key**
   - Give it a name (e.g., "Church Partnership System")
   - Copy the API key (starts with `re_`)

3. **Verify Your Domain** (Recommended for production)
   - Go to **Domains** in your dashboard
   - Add your domain
   - Update your DNS records as instructed

## Step 2: Configure the System

1. **Update Resend Configuration**
   
   Edit `src/config/resend_config.php`:
   
   ```php
   // Option 1: Set environment variables (recommended)
   // In your .env file or server environment:
   // RESEND_API_KEY=re_your_actual_api_key
   // RESEND_FROM_EMAIL=noreply@yourdomain.com
   // RESEND_FROM_NAME=Your Church Name
   
   // Option 2: Directly edit the file (for development)
   define('RESEND_API_KEY', 're_your_actual_api_key');
   define('RESEND_FROM_EMAIL', 'noreply@yourdomain.com');
   define('RESEND_FROM_NAME', 'Your Church Name');
   ```

2. **Update Database Schema**
   
   The required tables are already in `database/schema.sql`:
   - `password_resets` - For user password resets
   - `admin_password_resets` - For admin password resets
   - `admin_2fa` - For admin 2FA codes
   
   Run the schema if you haven't already:
   ```bash
   mysql -u username -p database_name < database/schema.sql
   ```

## Step 3: Test the System

1. **User Forgot Password**
   - Go to `http://yourdomain/src/login.php`
   - Click "Forgot password?"
   - Enter your email address
   - Check your inbox for the reset email
   - Click the reset link and set a new password

2. **Admin Forgot Password**
   - Go to `http://yourdomain/admin/login.php`
   - Click "Forgot password?"
   - Enter your admin email
   - Check your inbox for the reset email
   - Click the reset link and set a new password

## Step 4: Security Features

The system includes several security features:

1. **Token Expiration**: Reset tokens expire after 15 minutes
2. **One-Time Use**: Tokens can only be used once
3. **Email Enumeration Prevention**: The system always returns a success message, even if the email doesn't exist
4. **Password Hashing**: Passwords are hashed using bcrypt with cost 12
5. **Audit Logging**: All password reset attempts are logged

## File Structure

```
church-partnership/
├── src/
│   ├── config/
│   │   └── resend_config.php          # Resend configuration
│   ├── handlers/
│   │   ├── forgot-password.php        # User forgot password handler
│   │   └── reset-password.php         # User reset password handler
│   ├── helpers/
│   │   └── EmailService.php           # Email service class
│   ├── models/
│   │   ├── PasswordReset.php          # User password reset model
│   │   └── User.php                   # User model (with reset method)
│   ├── login.php                      # User login page
│   ├── forgot-password.php            # User forgot password page
│   └── reset-password.php             # User reset password page
│
├── admin/
│   ├── handlers/
│   │   ├── forgot-password.php        # Admin forgot password handler
│   │   ├── reset-password.php         # Admin reset password handler
│   │   └── 2fa-verify.php             # Admin 2FA verification handler
│   ├── login.php                      # Admin login page
│   ├── forgot-password.php            # Admin forgot password page
│   └── reset-password.php             # Admin reset password page
│
└── database/
    └── schema.sql                     # Database schema with reset tables
```

## Troubleshooting

### Emails Not Sending

1. **Check API Key**: Ensure your Resend API key is correct
2. **Check From Email**: Make sure your from email is verified in Resend
3. **Check Logs**: Look at `error_log` for any PHP errors
4. **Test API**: Test your API key with curl:
   ```bash
   curl -X POST "https://api.resend.com/emails" \
     -H "Authorization: Bearer re_your_api_key" \
     -H "Content-Type: application/json" \
     -d '{
       "from": "onboarding@resend.dev",
       "to": ["your@email.com"],
       "subject": "Test",
       "html": "<p>Hello</p>"
     }'
   ```

### Reset Link Not Working

1. **Check URL**: Ensure the reset link URL matches your domain
2. **Check Token**: Verify the token is being stored in the database
3. **Check Expiration**: Tokens expire after 15 minutes

### Database Errors

1. **Check Tables**: Ensure `password_resets` and `admin_password_resets` tables exist
2. **Check Foreign Keys**: Ensure the foreign key constraints are satisfied

## Environment Variables

For production, set these environment variables:

| Variable | Description | Example |
|----------|-------------|---------|
| `RESEND_API_KEY` | Your Resend API key | `re_abc123...` |
| `RESEND_FROM_EMAIL` | Sender email address | `noreply@yourdomain.com` |
| `RESEND_FROM_NAME` | Sender name | `Bright Light Ministry` |

## API Endpoints

### User Forgot Password
- **URL**: `/src/handlers/forgot-password.php`
- **Method**: POST
- **Parameters**: `email`
- **Response**: JSON with success/error message

### User Reset Password
- **URL**: `/src/handlers/reset-password.php`
- **Method**: POST
- **Parameters**: `token`, `password`, `confirm_password`
- **Response**: JSON with success/error message

### Admin Forgot Password
- **URL**: `/admin/handlers/forgot-password.php`
- **Method**: POST
- **Parameters**: `email`
- **Response**: JSON with success/error message

### Admin Reset Password
- **URL**: `/admin/handlers/reset-password.php`
- **Method**: POST
- **Parameters**: `token`, `password`, `confirm_password`
- **Response**: JSON with success/error message