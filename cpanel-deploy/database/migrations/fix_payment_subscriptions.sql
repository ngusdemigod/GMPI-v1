-- ============================================
-- FIX PAYSTACK SUBSCRIPTION TABLE
-- Bright Light Ministry Int'l Partnership Portal
-- 
-- Fixes the user_id NOT NULL constraint issue
-- Run this if the payment_subscriptions table already exists
-- ============================================

-- Alter existing payment_subscriptions table to allow NULL user_id
ALTER TABLE payment_subscriptions 
MODIFY COLUMN user_id INT NULL,
MODIFY COLUMN transaction_id INT NULL;

-- Add missing columns for guest donations
ALTER TABLE payment_subscriptions
ADD COLUMN IF NOT EXISTS email VARCHAR(255) AFTER currency,
ADD COLUMN IF NOT EXISTS first_name VARCHAR(100) AFTER email,
ADD COLUMN IF NOT EXISTS last_name VARCHAR(100) AFTER first_name;

-- Add index for email lookups
ALTER TABLE payment_subscriptions
ADD INDEX IF NOT EXISTS idx_email (email);