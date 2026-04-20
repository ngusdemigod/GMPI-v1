-- ============================================
-- Migration: Add 'Project Support' category to transactions table
-- Date: 2026-04-19
-- Description: Adds 'Project Support' as a valid category for project donations
-- ============================================

-- Modify the transactions table to include 'Project Support' in the category ENUM
ALTER TABLE transactions 
MODIFY COLUMN category ENUM('Tithe', 'Offering', 'Missions', 'Building', 'Scholarship', 'General', 'Project Support') NOT NULL;