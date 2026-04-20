# Paystack Payment Gateway Integration

## Overview

This directory contains the complete Paystack payment gateway integration for the Bright Light Ministry Int'l Partners Portal. The integration supports:

- One-time donations
- Recurring subscriptions
- Multiple currencies (USD, NGN, GHS)
- Email receipts via Resend
- Webhook processing
- Security features (CSRF, webhook validation, rate limiting)

## File Structure

```
src/
├── config/
│   ├── paystack_config.php      # Paystack API configuration
│   ├── resend_config.php        # Resend email API configuration
│   ├── PaystackService.php      # Paystack API service class
│   └── ResendService.php        # Resend email service class
├── api/
│   └── paystack/
│       ├── initialize.php       # Initialize payment endpoint
│       ├── verify.php           # Verify payment endpoint
│       ├── subscription.php     # Create subscription endpoint
│       ├── webhook-receiver.php # Webhook handler
│       └── security.php         # Security middleware
├── components/
│   └── paystack-modal.php       # Payment modal component
├── helpers/
│   └── paystack_js.php          # JavaScript helper functions
└── logs/
    └── security.log             # Security event log (auto-created)
```

## Setup Instructions

### 1. Configure API Keys

Edit `src/config/paystack_config.php`:

```php
define('PAYSTACK_SECRET_KEY', 'sk_live_YOUR_SECRET_KEY_HERE');
define('PAYSTACK_PUBLIC_KEY', 'pk_live_YOUR_PUBLIC_KEY_HERE');
define('PAYSTACK_WEBHOOK_SECRET', 'whsec_xxxxxxxxxxxxxxxxxxxxxxxx');
```

Edit `src/config/resend_config.php`:

```php
define('RESEND_API_KEY', 're_xxxxxxxxxxxxxxxxxxxxxxxx');
define('RESEND_FROM_EMAIL', 'receipts@yourdomain.com');
define('RESEND_FROM_NAME', 'Bright Light Ministry Int\'l');
```

### 2. Database Setup

Run the following SQL to create the required tables:

```sql
-- Paystack transactions table
CREATE TABLE IF NOT EXISTS paystack_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) NOT NULL,
    reference VARCHAR(255) NOT NULL UNIQUE,
    status ENUM('pending', 'success', 'failed') DEFAULT 'pending',
    metadata JSON,
    paid_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

-- Webhook logs table
CREATE TABLE IF NOT EXISTS paystack_webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    reference VARCHAR(255),
    payload JSON,
    processed BOOLEAN DEFAULT FALSE,
    created_at DATETIME NOT NULL
);

-- Payment subscriptions table
CREATE TABLE IF NOT EXISTS payment_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) NOT NULL,
    frequency VARCHAR(20) NOT NULL,
    reference VARCHAR(255) NOT NULL UNIQUE,
    status ENUM('pending', 'active', 'failed', 'expired', 'cancelled') DEFAULT 'pending',
    metadata JSON,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);
```

### 3. Configure Webhook URL

In your Paystack dashboard:

1. Go to Settings > API Keys & Webhooks
2. Add webhook URL: `https://yourdomain.com/api/paystack/webhook-receiver.php`
3. Select events to receive:
   - charge.success
   - charge.failed
   - subscription.success
   - subscription.failure
   - subscription.expired
   - transfer.failed

### 4. Include Components in Your Pages

Add the payment modal to your pages:

```php
<?php include 'components/paystack-modal.php'; ?>
```

Add the JavaScript helper:

```php
<?php include 'helpers/paystack_js.php'; ?>
```

## Usage

### One-Time Payment

```javascript
// Open payment modal
openPaystackModal({
    amount: 100,
    currency: 'USD',
    email: 'donor@example.com',
    category: 'General Offering'
});
```

### Recurring Subscription

```javascript
// Open payment modal with subscription
openPaystackModal({
    amount: 50,
    currency: 'USD',
    email: 'donor@example.com',
    frequency: 'monthly',
    category: 'Building Fund'
});
```

### Using PaystackHelper Directly

```javascript
// Initialize payment
const result = await PaystackHelper.initializePayment({
    email: 'donor@example.com',
    amount: 100,
    currency: 'USD',
    first_name: 'John',
    last_name: 'Doe'
});

if (result.success) {
    // Redirect to Paystack checkout
    window.location.href = result.authorizationUrl;
}

// Verify payment
const verification = await PaystackHelper.verifyPayment('reference_here');
if (verification.success) {
    console.log('Payment verified:', verification.data);
}
```

## API Endpoints

### Initialize Payment

- **URL:** `/api/paystack/initialize.php`
- **Method:** POST
- **Body:**
```json
{
    "email": "donor@example.com",
    "amount": 100.00,
    "currency": "USD",
    "first_name": "John",
    "last_name": "Doe",
    "category": "General Offering",
    "project_id": 1
}
```

### Verify Payment

- **URL:** `/api/paystack/verify.php`
- **Method:** POST
- **Body:**
```json
{
    "reference": "ref_here"
}
```

### Create Subscription

- **URL:** `/api/paystack/subscription.php`
- **Method:** POST
- **Body:**
```json
{
    "email": "donor@example.com",
    "amount": 50.00,
    "currency": "USD",
    "frequency": "monthly",
    "first_name": "John",
    "last_name": "Doe"
}
```

## Security Features

- **CSRF Protection:** Tokens generated and verified for all requests
- **Webhook Validation:** HMAC signature verification for webhooks
- **Input Sanitization:** All inputs are sanitized before processing
- **Rate Limiting:** Prevents abuse with configurable rate limits
- **Secure API Key Storage:** Keys stored in config files, not in code
- **IP Validation:** Client IP detection with proxy support

## Email Receipts

The integration automatically sends email receipts via Resend for:

- Successful donations
- Subscription confirmations
- Failed payments
- Subscription cancellations

## Testing

### Test Mode

Use test API keys for development:

```php
define('PAYSTACK_SECRET_KEY', 'sk_test_YOUR_TEST_KEY_HERE');
define('PAYSTACK_PUBLIC_KEY', 'pk_test_YOUR_TEST_KEY_HERE');
```

### Test Cards

Paystack provides test cards:

- **Success:** 4084084084084081
- **Insufficient Funds:** 4084084084084082
- **Failed:** 4084084084084083

### Webhook Testing

Use Paystack CLI for localhost testing:

```bash
npm install -g @paystack-oss/dev-cli
paystack --help
php -S localhost:8000 -t public
webhook listen localhost:8000/paystack/webhook
```

You can also send a sample event through the CLI:

```bash
webhook ping --event transfer.success --domain test
```

For actual payment confirmation, the main event to handle is `charge.success`.

## Troubleshooting

### Common Issues

1. **Payment initialization fails:**
   - Check API keys are correct
   - Verify email format
   - Ensure amount is positive

2. **Webhook not received:**
   - Verify webhook URL is accessible
   - Check webhook secret is correct
   - Review server logs

3. **Email not sent:**
   - Verify Resend API key
   - Check from email is verified in Resend
   - Review Resend dashboard

## Support

For issues or questions:
- Check Paystack documentation: https://paystack.com/docs
- Check Resend documentation: https://resend.com/docs
- Contact support: support@brightlightministry.org
