-- ============================================
-- PAYSTACK INTEGRATION MIGRATION
-- Bright Light Ministry Int'l Partnership Portal
-- 
-- Adds fields needed for Paystack payment integration
-- ============================================

-- Add new columns to transactions table if they don't exist
ALTER TABLE transactions 
ADD COLUMN IF NOT EXISTS gateway_response TEXT,
ADD COLUMN IF NOT EXISTS channel VARCHAR(50),
ADD COLUMN IF NOT EXISTS paid_at DATETIME,
ADD COLUMN IF NOT EXISTS gateway_transaction_id VARCHAR(100),
ADD COLUMN IF NOT EXISTS currency VARCHAR(10) DEFAULT 'USD';

-- Create paystack_transactions table for detailed payment records
CREATE TABLE IF NOT EXISTS paystack_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    paystack_reference VARCHAR(100) UNIQUE,
    status ENUM('pending', 'success', 'failed', 'cancelled') DEFAULT 'pending',
    amount INT NOT NULL, -- Amount in kobo/cents
    currency VARCHAR(10) DEFAULT 'USD',
    email VARCHAR(255),
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    gateway_response TEXT,
    channel VARCHAR(50),
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    INDEX idx_paystack_reference (paystack_reference),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create paystack_webhooks table for webhook logging
CREATE TABLE IF NOT EXISTS paystack_webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paystack_event_id VARCHAR(100),
    event_type VARCHAR(50),
    payload TEXT,
    processed BOOLEAN DEFAULT FALSE,
    processed_at DATETIME,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_paystack_event_id (paystack_event_id),
    INDEX idx_processed (processed),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create payment_subscriptions table for recurring donations
CREATE TABLE IF NOT EXISTS payment_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    transaction_id INT NULL,
    paystack_subscription_id VARCHAR(100) UNIQUE,
    status ENUM('pending', 'active', 'paused', 'cancelled', 'expired') DEFAULT 'pending',
    frequency ENUM('weekly', 'monthly', 'annually') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'USD',
    email VARCHAR(255),
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    next_billing_date DATE,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL,
    INDEX idx_paystack_subscription_id (paystack_subscription_id),
    INDEX idx_status (status),
    INDEX idx_user (user_id),
    INDEX idx_next_billing_date (next_billing_date),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add index for faster lookups on transactions table
ALTER TABLE transactions 
ADD INDEX IF NOT EXISTS idx_gateway_reference (transaction_reference),
ADD INDEX IF NOT EXISTS idx_paid_at (paid_at);