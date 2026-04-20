-- ============================================
-- FIX: Allow NULL for user_id in transactions table
-- ============================================
-- This migration fixes the issue where guest donations (without user_id)
-- were failing due to the NOT NULL constraint on user_id in the transactions table.
-- ============================================

-- Alter the transactions table to allow NULL for user_id
ALTER TABLE transactions 
MODIFY COLUMN user_id INT DEFAULT NULL;

-- Also update the foreign key constraint to use ON DELETE SET NULL
-- (This should already be the case, but ensuring consistency)
ALTER TABLE transactions
DROP FOREIGN KEY transactions_ibfk_1,
ADD CONSTRAINT transactions_ibfk_1 
FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL;