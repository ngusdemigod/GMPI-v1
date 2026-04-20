-- ============================================
-- Church Financial Partnership System Database Schema
-- Bright Light Ministry Int'l Partnership Portal
-- Unified Database Schema (Merged)
-- ============================================

-- Disable foreign key checks for clean drop/create
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- DROP EXISTING TABLES (in reverse dependency order)
-- ============================================
DROP TABLE IF EXISTS support_ticket_responses;
DROP TABLE IF EXISTS support_ticket_attachments;
DROP TABLE IF EXISTS support_tickets;
DROP TABLE IF EXISTS system_settings;
DROP TABLE IF EXISTS system_logs;
DROP TABLE IF EXISTS verification_resend_log;
DROP TABLE IF EXISTS verification_codes;
DROP TABLE IF EXISTS admin_login_attempts;
DROP TABLE IF EXISTS admin_audit_logs;
DROP TABLE IF EXISTS admin_sessions;
DROP TABLE IF EXISTS admin_users;
DROP TABLE IF EXISTS campaign_tags;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS payment_plans;
DROP TABLE IF EXISTS tax_receipts;
DROP TABLE IF EXISTS project_participants;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS pledges;
DROP TABLE IF EXISTS campaigns;
DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS payment_methods;
DROP TABLE IF EXISTS user_donor_tier_history;
DROP TABLE IF EXISTS donor_tiers;
DROP TABLE IF EXISTS user_role_assignments;
DROP TABLE IF EXISTS user_roles;
DROP TABLE IF EXISTS users;

-- ============================================
-- CORE TABLES
-- ============================================

-- ============================================
-- USERS TABLE
-- ============================================
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address_line1 VARCHAR(255),
    address_line2 VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100) DEFAULT 'USA',
    is_active BOOLEAN DEFAULT TRUE,
    is_verified BOOLEAN DEFAULT FALSE,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- USER ROLES TABLE
-- ============================================
CREATE TABLE user_roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    role_description TEXT,
    permissions JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- USER ROLE ASSIGNMENTS
-- ============================================
CREATE TABLE user_role_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES user_roles(role_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_role (user_id, role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- EMAIL VERIFICATION TABLES
-- ============================================
CREATE TABLE verification_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    purpose ENUM('signup_verification', 'admin_login_verification') NOT NULL,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    used_at DATETIME DEFAULT NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_purpose (purpose),
    INDEX idx_expires (expires_at),
    INDEX idx_used (used)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE verification_resend_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    purpose ENUM('signup_verification', 'admin_login_verification') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_ip (ip_address),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempt_type ENUM('password', 'verification_code') NOT NULL,
    success BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_ip (ip_address),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- USER-RELATED TABLES
-- ============================================

-- ============================================
-- PAYMENT METHODS TABLE
-- ============================================
CREATE TABLE payment_methods (
    payment_method_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    card_type VARCHAR(20),
    last_four_digits VARCHAR(4),
    expiry_month INT,
    expiry_year INT,
    cardholder_name VARCHAR(100),
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DONOR TIERS TABLE
-- ============================================
CREATE TABLE donor_tiers (
    tier_id INT AUTO_INCREMENT PRIMARY KEY,
    tier_name VARCHAR(100) NOT NULL,
    min_amount DECIMAL(15,2) NOT NULL,
    max_amount DECIMAL(15,2),
    benefits TEXT,
    color_code VARCHAR(7),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- USER DONOR TIER HISTORY
-- ============================================
CREATE TABLE user_donor_tier_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tier_id INT NOT NULL,
    entered_at DATE,
    exited_at DATE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (tier_id) REFERENCES donor_tiers(tier_id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PROJECT/CAMPAIGN TABLES
-- ============================================

-- ============================================
-- PROJECTS TABLE (Legacy/General Projects)
-- ============================================
CREATE TABLE projects (
    project_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category ENUM('Urgent', 'Missions', 'Seasonal', 'Scholarship', 'Building', 'General') NOT NULL,
    goal_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    current_amount DECIMAL(15,2) DEFAULT 0,
    partner_count INT DEFAULT 0,
    start_date DATE,
    end_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    icon VARCHAR(50),
    milestones JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_active (is_active),
    INDEX idx_end_date (end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CAMPAIGNS TABLE (Enhanced Campaigns)
-- ============================================
CREATE TABLE campaigns (
    campaign_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE,
    description TEXT,
    short_description VARCHAR(500),
    category VARCHAR(100),
    campaign_type ENUM('one-time', 'ongoing', 'pledge', 'matching', 'emergency') DEFAULT 'one-time',
    goal_amount DECIMAL(15,2) DEFAULT 0,
    starting_amount DECIMAL(15,2) DEFAULT 0,
    current_amount DECIMAL(15,2) DEFAULT 0,
    start_date DATE,
    end_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    icon VARCHAR(50) DEFAULT 'building',
    accent_color ENUM('dark', 'gold', 'sage', 'rose', 'info') DEFAULT 'dark',
    visibility ENUM('public', 'members', 'invite', 'draft') DEFAULT 'public',
    is_featured BOOLEAN DEFAULT FALSE,
    allow_anonymous BOOLEAN DEFAULT TRUE,
    min_gift DECIMAL(15,2) DEFAULT 0,
    max_gift DECIMAL(15,2) DEFAULT 0,
    is_tax_deductible BOOLEAN DEFAULT TRUE,
    is_matching_enabled BOOLEAN DEFAULT FALSE,
    match_ratio VARCHAR(20) DEFAULT '1:1',
    match_cap DECIMAL(15,2) DEFAULT 0,
    match_sponsor VARCHAR(255),
    campaign_manager VARCHAR(255),
    launch_message TEXT,
    scripture VARCHAR(100),
    scripture_text TEXT,
    notify_at_launch BOOLEAN DEFAULT FALSE,
    notify_milestones BOOLEAN DEFAULT FALSE,
    notify_reminders BOOLEAN DEFAULT FALSE,
    cover_image VARCHAR(500),
    video_url VARCHAR(500),
    social_title VARCHAR(255),
    social_desc VARCHAR(500),
    partner_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_active (is_active),
    INDEX idx_featured (is_featured),
    INDEX idx_visibility (visibility),
    INDEX idx_end_date (end_date),
    INDEX idx_campaign_type (campaign_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TAGS TABLE
-- ============================================
CREATE TABLE tags (
    tag_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    color VARCHAR(7) DEFAULT '#6c757d',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_color (color)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CAMPAIGN TAGS TABLE (Many-to-Many)
-- ============================================
CREATE TABLE campaign_tags (
    campaign_id INT NOT NULL,
    tag_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (campaign_id, tag_id),
    FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(tag_id) ON DELETE CASCADE,
    INDEX idx_tag (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TRANSACTION TABLES
-- ============================================

-- ============================================
-- PLEDGES TABLE
-- ============================================
CREATE TABLE pledges (
    pledge_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    project_id INT,
    pledge_title VARCHAR(255),
    total_amount DECIMAL(15,2) NOT NULL,
    remaining_amount DECIMAL(15,2) NOT NULL,
    frequency ENUM('One-time', 'Weekly', 'Monthly', 'Annually') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TRANSACTIONS TABLE
-- ============================================
CREATE TABLE transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    project_id INT,
    pledge_id INT,
    amount DECIMAL(15,2) NOT NULL,
    category ENUM('Tithe', 'Offering', 'Missions', 'Building', 'Scholarship', 'General') NOT NULL,
    frequency ENUM('One-time', 'Weekly', 'Monthly', 'Annually') DEFAULT 'One-time',
    payment_method_id INT,
    transaction_reference VARCHAR(100) UNIQUE,
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    transaction_date DATETIME NOT NULL,
    processed_at DATETIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE SET NULL,
    FOREIGN KEY (pledge_id) REFERENCES pledges(pledge_id) ON DELETE SET NULL,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(payment_method_id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_project (project_id),
    INDEX idx_date (transaction_date),
    INDEX idx_status (status),
    INDEX idx_reference (transaction_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PROJECT PARTICIPANTS TABLE
-- ============================================
CREATE TABLE project_participants (
    participant_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    total_contributed DECIMAL(15,2) DEFAULT 0,
    contribution_count INT DEFAULT 0,
    first_contribution_date DATE,
    last_contribution_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_project_user (project_id, user_id),
    INDEX idx_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TAX RECEIPTS TABLE
-- ============================================
CREATE TABLE tax_receipts (
    receipt_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    year INT NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL,
    receipt_number VARCHAR(50) UNIQUE,
    issue_date DATE,
    is_sent BOOLEAN DEFAULT FALSE,
    sent_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_year (user_id, year),
    INDEX idx_receipt_number (receipt_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ADMIN TABLES
-- ============================================

-- ============================================
-- ADMIN USERS TABLE
-- ============================================
CREATE TABLE admin_users (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    jwt_secret VARCHAR(255) NOT NULL,
    two_factor_secret VARCHAR(255),
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    last_password_reset DATETIME,
    password_reset_token VARCHAR(255),
    password_reset_expires DATETIME,
    failed_login_attempts INT DEFAULT 0,
    locked_until DATETIME,
    last_login_ip VARCHAR(45),
    last_login_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_locked (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ADMIN SESSIONS TABLE
-- ============================================
CREATE TABLE admin_sessions (
    session_id VARCHAR(255) PRIMARY KEY,
    admin_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    user_agent_hash VARCHAR(64),
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin_users(admin_id) ON DELETE CASCADE,
    INDEX idx_admin (admin_id),
    INDEX idx_expires (expires_at),
    INDEX idx_token_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ADMIN AUDIT LOGS TABLE
-- ============================================
CREATE TABLE admin_audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    target_type VARCHAR(50),
    target_id INT,
    target_name VARCHAR(255),
    changes JSON,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin_users(admin_id) ON DELETE CASCADE,
    INDEX idx_admin (admin_id),
    INDEX idx_action (action_type),
    INDEX idx_target (target_type, target_id),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PAYMENT PLANS TABLE
-- ============================================
CREATE TABLE payment_plans (
    plan_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    amount DECIMAL(15,2) NOT NULL,
    min_amount DECIMAL(15,2) DEFAULT 0,
    max_amount DECIMAL(15,2) DEFAULT NULL,
    currency VARCHAR(10) DEFAULT 'USD',
    frequency ENUM('One-time', 'Weekly', 'Monthly', 'Annually') NOT NULL,
    paystack_plan_code VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    is_paused BOOLEAN DEFAULT FALSE,
    max_subscriptions INT DEFAULT NULL,
    display_order INT DEFAULT 0,
    icon VARCHAR(50),
    color_code VARCHAR(7),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_frequency (frequency),
    INDEX idx_paused (is_paused)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PAYSTACK PAYMENT TABLES
-- ============================================

-- ============================================
-- PAYSTACK TRANSACTIONS TABLE
-- ============================================
CREATE TABLE paystack_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    paystack_reference VARCHAR(100) UNIQUE,
    paystack_subscription_id VARCHAR(100) DEFAULT NULL,
    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    amount INT NOT NULL,
    currency VARCHAR(10) DEFAULT 'USD',
    email VARCHAR(255),
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id) ON DELETE CASCADE,
    INDEX idx_transaction (transaction_id),
    INDEX idx_paystack_reference (paystack_reference),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PAYSTACK WEBHOOKS TABLE
-- ============================================
CREATE TABLE paystack_webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paystack_event_id VARCHAR(100) UNIQUE,
    event_type VARCHAR(50) NOT NULL,
    payload JSON NOT NULL,
    processed BOOLEAN DEFAULT FALSE,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_paystack_event_id (paystack_event_id),
    INDEX idx_event_type (event_type),
    INDEX idx_processed (processed),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PAYMENT SUBSCRIPTIONS TABLE
-- ============================================
CREATE TABLE payment_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    transaction_id INT,
    paystack_subscription_id VARCHAR(100) UNIQUE,
    plan_id INT DEFAULT NULL,
    status ENUM('active', 'paused', 'cancelled') DEFAULT 'active',
    frequency ENUM('weekly', 'monthly', 'annually') NOT NULL,
    amount INT NOT NULL,
    currency VARCHAR(10) DEFAULT 'USD',
    next_billing_date DATE,
    last_billing_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id) ON DELETE SET NULL,
    FOREIGN KEY (plan_id) REFERENCES payment_plans(plan_id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_paystack_subscription_id (paystack_subscription_id),
    INDEX idx_status (status),
    INDEX idx_next_billing_date (next_billing_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SUPPORT TABLES
-- ============================================

-- ============================================
-- SUPPORT TICKETS TABLE
-- ============================================
CREATE TABLE support_tickets (
    ticket_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    category VARCHAR(50) NOT NULL,
    status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SUPPORT TICKET ATTACHMENTS TABLE
-- ============================================
CREATE TABLE support_ticket_attachments (
    attachment_id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT DEFAULT 0,
    file_type VARCHAR(100) DEFAULT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(ticket_id) ON DELETE CASCADE,
    INDEX idx_ticket_id (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SUPPORT TICKET RESPONSES TABLE
-- ============================================
CREATE TABLE support_ticket_responses (
    response_id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    admin_id INT DEFAULT NULL,
    response TEXT NOT NULL,
    is_internal BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(ticket_id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admin_users(admin_id) ON DELETE SET NULL,
    INDEX idx_ticket_id (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SYSTEM SETTINGS TABLE
-- ============================================
CREATE TABLE system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
    description VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PASSWORD RESETS TABLE (User)
-- ============================================
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ADMIN PASSWORD RESETS TABLE
-- ============================================
CREATE TABLE admin_password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin_users(admin_id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_admin_id (admin_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ADMIN 2FA TABLE
-- ============================================
CREATE TABLE admin_2fa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL UNIQUE,
    secret VARCHAR(255) NOT NULL,
    backup_codes JSON,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin_users(admin_id) ON DELETE CASCADE,
    INDEX idx_admin_id (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SYSTEM LOGS TABLE
-- ============================================
CREATE TABLE system_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    log_level ENUM('DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL') NOT NULL,
    log_message TEXT NOT NULL,
    log_context JSON,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_level (log_level),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SEED DATA
-- ============================================

-- ============================================
-- INSERT DEFAULT USER ROLES
-- ============================================
INSERT INTO user_roles (role_name, role_description, permissions) VALUES
('admin', 'Administrator - Full system access', '{"dashboard": true, "users": true, "projects": true, "campaigns": true, "transactions": true, "plans": true, "partners": true, "admin_members": true, "settings": true, "audit_logs": true, "reports": true}'),
('staff', 'Staff Member - Limited access', '{"dashboard": true, "users": false, "projects": true, "campaigns": true, "transactions": true, "plans": false, "partners": false, "admin_members": false, "settings": false, "audit_logs": false, "reports": false}'),
('donor', 'Donor - Self management only', '{"dashboard": true, "users": false, "projects": false, "campaigns": false, "transactions": true, "plans": false, "partners": false, "admin_members": false, "settings": true, "audit_logs": false, "reports": false}'),
('project_admin', 'Project Administrator - Campaign and transaction access', '{"dashboard": true, "users": false, "projects": true, "campaigns": true, "transactions": true, "plans": false, "partners": false, "admin_members": false, "settings": false, "audit_logs": false, "reports": false}'),
('finance_admin', 'Finance Administrator - User and transaction access', '{"dashboard": true, "users": true, "projects": false, "campaigns": false, "transactions": true, "plans": true, "partners": true, "admin_members": false, "settings": false, "audit_logs": true, "reports": true}');

-- ============================================
-- INSERT DEFAULT DONOR TIERS
-- ============================================
INSERT INTO donor_tiers (tier_name, min_amount, max_amount, benefits, color_code) VALUES
('Bronze', 0, 999, 'Annual giving statement', '#CD7F32'),
('Silver', 1000, 4999, 'Annual giving statement, priority support', '#C0C0C0'),
('Gold', 5000, 9999, 'Annual giving statement, priority support, exclusive events', '#FFD700'),
('Platinum', 10000, 49999, 'All Gold benefits, annual recognition dinner', '#E5E4E2'),
('Diamond', 50000, NULL, 'All Platinum benefits, personal stewardship advisor', '#B9F2FF');

-- ============================================
-- INSERT DEFAULT PAYMENT PLANS
-- ============================================
INSERT INTO payment_plans (name, description, amount, min_amount, max_amount, currency, frequency, is_active, display_order, icon, color_code) VALUES
('Bronze Tier', 'Basic monthly support', 25.00, 10.00, 99.00, 'USD', 'Monthly', TRUE, 1, 'star', '#CD7F32'),
('Silver Tier', 'Enhanced monthly giving', 50.00, 100.00, 499.00, 'USD', 'Monthly', TRUE, 2, 'star', '#C0C0C0'),
('Gold Tier', 'Premium monthly partnership', 100.00, 500.00, 999.00, 'USD', 'Monthly', TRUE, 3, 'star', '#FFD700'),
('Platinum Tier', 'Executive monthly support', 250.00, 1000.00, 4999.00, 'USD', 'Monthly', TRUE, 4, 'star', '#E5E4E2'),
('Diamond Tier', 'Visionary monthly commitment', 500.00, 5000.00, NULL, 'USD', 'Monthly', TRUE, 5, 'star', '#B9F2FF'),
('Weekly Tithe', 'Weekly giving plan', 25.00, 10.00, 99.00, 'USD', 'Weekly', TRUE, 6, 'calendar', '#D4AF37'),
('Annual Pledge', 'Annual commitment plan', 3000.00, 1000.00, NULL, 'USD', 'Annually', TRUE, 7, 'calendar', '#1a1a2e');

-- ============================================
-- INSERT DEFAULT TAGS
-- ============================================
INSERT INTO tags (name, color, description) VALUES
('Urgent', '#dc3545', 'Time-sensitive campaigns requiring immediate attention'),
('Missions', '#28a745', 'Mission and outreach initiatives'),
('Seasonal', '#ffc107', 'Seasonal campaigns and events'),
('Scholarship', '#17a2b8', 'Educational support programs'),
('Building', '#6f42c1', 'Facility and construction campaigns'),
('General', '#6c757d', 'General giving and unrestricted funds');

-- ============================================
-- INSERT DEFAULT SYSTEM SETTINGS
-- ============================================
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('church_name', 'Bright Light Ministry Int''l', 'text', 'Public church name displayed across the donor portal'),
('portal_subtitle', 'Partners Portal', 'text', 'Short subtitle displayed in the donor portal header'),
('church_tagline', 'Together, we are advancing the gospel with every seed sown.', 'text', 'Public donor portal tagline'),
('tax_note', 'Bright Light Ministry Int''l is a registered ministry. Your gift may be tax-deductible where applicable.', 'text', 'Public giving note shown near donation actions'),
('impact_families_fed', '1247', 'number', 'Impact metric shown on the donor home page'),
('impact_missionaries_sent', '38', 'number', 'Impact metric shown on the donor home page'),
('impact_campuses_planted', '3', 'number', 'Impact metric shown on the donor home page'),
('impact_lives_touched', '12K+', 'text', 'Impact metric shown on the donor home page'),
('recaptcha_site_key', '', 'text', 'Google reCAPTCHA Site Key'),
('recaptcha_secret_key', '', 'text', 'Google reCAPTCHA Secret Key'),
('support_email', 'support@brightlightministry.org', 'text', 'Support email address'),
('support_phone', '+234 800 SUPPORT', 'text', 'Support phone number'),
('auto_resolve_days', '7', 'number', 'Days after which tickets are auto-resolved'),
('email_notifications', '1', 'boolean', 'Enable email notifications for new tickets');

-- ============================================
-- INSERT SAMPLE PROJECTS
-- ============================================
INSERT INTO projects (title, description, category, goal_amount, current_amount, partner_count, start_date, end_date, is_active, display_order, icon) VALUES
('House of Prayer Expansion', 'Adding 800 seats & a new youth wing to our main sanctuary.', 'Building', 2500000.00, 1840000.00, 412, '2024-01-01', '2024-12-31', TRUE, 1, 'church'),
('Kenya Church Plant', 'Building a sanctuary & well in Kisumu, serving 3 villages.', 'Missions', 120000.00, 62400.00, 189, '2024-03-01', '2025-02-14', TRUE, 2, 'globe'),
('Thanksgiving Baskets', 'Provide meals for 1,500 families across our city this holiday.', 'Seasonal', 50000.00, 43000.00, 624, '2024-11-01', '2024-11-27', TRUE, 3, 'star'),
('Bible College Tuition', 'Sponsor 40 students pursuing full-time ministry training.', 'Scholarship', 80000.00, 28900.00, 97, '2024-01-15', '2025-06-30', TRUE, 4, 'graduation-cap');

-- ============================================
-- INSERT SAMPLE CAMPAIGNS
-- ============================================
INSERT INTO campaigns (title, slug, description, short_description, category, campaign_type, goal_amount, starting_amount, current_amount, start_date, end_date, is_active, display_order, icon, accent_color, visibility, is_featured, allow_anonymous, min_gift, max_gift, is_tax_deductible, is_matching_enabled, match_ratio, match_cap, match_sponsor, campaign_manager, launch_message, scripture, scripture_text, notify_at_launch, notify_milestones, notify_reminders, cover_image, video_url, social_title, social_desc, partner_count) VALUES
('House of Prayer Expansion', 'house-of-prayer-expansion', 'Adding 800 seats & a new youth wing to our main sanctuary.', '800 new seats & youth wing', 'Building', 'one-time', 2500000.00, 0, 1840000.00, '2024-01-01', '2024-12-31', TRUE, 1, 'church', 'dark', 'public', TRUE, TRUE, 10, 0, TRUE, FALSE, '1:1', 0, '', 'Pastor Nathaniel Williams', 'Join us in expanding our House of Prayer!', 'Mark 11:24', 'Therefore I tell you, whatever you ask for in prayer, believe that you have received it, and it will be yours.', FALSE, TRUE, TRUE, NULL, NULL, 'House of Prayer Expansion', 'Help us expand our sanctuary to serve more people', 412),
('Kenya Church Plant', 'kenya-church-plant', 'Building a sanctuary & well in Kisumu, serving 3 villages.', 'Sanctuary & well in Kisumu', 'Missions', 'one-time', 120000.00, 0, 62400.00, '2024-03-01', '2025-02-14', TRUE, 2, 'globe', 'gold', 'public', TRUE, TRUE, 10, 0, TRUE, FALSE, '1:1', 0, '', 'Missions Department', 'Plant a church in Kenya and serve 3 villages.', 'Isaiah 61:1', 'The Spirit of the Sovereign Lord is on me, because the Lord has anointed me to proclaim good news to the poor.', FALSE, TRUE, TRUE, NULL, NULL, 'Kenya Church Plant', 'Building a sanctuary & well in Kisumu', 189),
('Thanksgiving Baskets', 'thanksgiving-baskets', 'Provide meals for 1,500 families across our city this holiday.', 'Meals for 1,500 families', 'Seasonal', 'one-time', 50000.00, 0, 43000.00, '2024-11-01', '2024-11-27', TRUE, 3, 'star', 'rose', 'public', FALSE, TRUE, 10, 0, TRUE, FALSE, '1:1', 0, '', 'Outreach Team', 'Provide Thanksgiving meals to families in need.', 'Luke 10:25-37', 'The Parable of the Good Samaritan - showing love to our neighbors.', FALSE, TRUE, TRUE, NULL, NULL, 'Thanksgiving Baskets', 'Provide meals for 1,500 families', 624),
('Bible College Tuition', 'bible-college-tuition', 'Sponsor 40 students pursuing full-time ministry training.', 'Sponsor 40 ministry students', 'Scholarship', 'ongoing', 80000.00, 0, 28900.00, '2024-01-15', '2025-06-30', TRUE, 4, 'book', 'sage', 'public', FALSE, TRUE, 50, 0, TRUE, FALSE, '1:1', 0, '', 'Finance Team', 'Support future leaders in ministry training.', 'Jeremiah 29:11', 'For I know the plans I have for you, declares the Lord, plans to prosper you and not to harm you, plans to give you hope and a future.', FALSE, TRUE, TRUE, NULL, NULL, 'Bible College Tuition', 'Sponsor 40 students in ministry training', 97);

-- ============================================
-- INSERT SAMPLE USER (password: password123)
-- ============================================
INSERT INTO users (email, password_hash, first_name, last_name, phone, is_verified) VALUES
('david.anderson@email.com', '$2y$10$kQmvSw3WRoKSkVwEY42a.u8CjJj6pe24cnfFxYYKnPh/QzRug9Lma', 'David', 'Anderson', '555-0123', TRUE);

-- ============================================
-- INSERT SAMPLE PAYMENT METHOD
-- ============================================
INSERT INTO payment_methods (user_id, card_type, last_four_digits, expiry_month, expiry_year, cardholder_name, is_default) VALUES
(1, 'Visa', '4829', 9, 2027, 'David Anderson', TRUE);

-- ============================================
-- INSERT SAMPLE PLEDGE
-- ============================================
INSERT INTO pledges (user_id, pledge_title, total_amount, remaining_amount, frequency, start_date, end_date, is_active) VALUES
(1, '2024 Faith Pledge', 6000.00, 1180.00, 'Monthly', '2024-01-01', '2024-12-31', TRUE);

-- ============================================
-- INSERT SAMPLE TRANSACTIONS
-- ============================================
INSERT INTO transactions (user_id, project_id, amount, category, frequency, transaction_reference, status, transaction_date, processed_at) VALUES
(1, NULL, 250.00, 'Tithe', 'Weekly', 'TXN-20241110-001', 'completed', '2024-11-10 10:30:00', '2024-11-10 10:30:05'),
(1, 2, 500.00, 'Missions', 'One-time', 'TXN-20241105-002', 'completed', '2024-11-05 14:20:00', '2024-11-05 14:20:03'),
(1, NULL, 1000.00, 'Building', 'One-time', 'TXN-20241103-003', 'completed', '2024-11-03 09:15:00', '2024-11-03 09:15:02'),
(1, NULL, 250.00, 'Tithe', 'Weekly', 'TXN-20241027-004', 'completed', '2024-10-27 10:30:00', '2024-10-27 10:30:05');

-- ============================================
-- INSERT SAMPLE PROJECT PARTICIPANTS
-- ============================================
INSERT INTO project_participants (project_id, user_id, total_contributed, contribution_count, first_contribution_date, last_contribution_date) VALUES
(1, 1, 1000.00, 1, '2024-11-03', '2024-11-03'),
(2, 1, 500.00, 1, '2024-11-05', '2024-11-05');

-- ============================================
-- INSERT SAMPLE TAX RECEIPT
-- ============================================
INSERT INTO tax_receipts (user_id, year, total_amount, receipt_number, issue_date) VALUES
(1, 2023, 12500.00, 'TR-2023-00001', '2024-01-15');

-- ============================================
-- EMAIL VERIFICATION TABLES
-- ============================================

-- ============================================
-- EMAIL VERIFICATIONS TABLE
-- ============================================
CREATE TABLE email_verifications (
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

-- ============================================
-- EMAIL VERIFICATION REQUESTS (Rate Limiting)
-- ============================================
CREATE TABLE email_verification_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INSERT SAMPLE SYSTEM LOG
-- ============================================
INSERT INTO system_logs (user_id, log_level, log_message, ip_address) VALUES
(1, 'INFO', 'User logged in successfully', '192.168.1.100');

-- ============================================
-- INSERT SAMPLE SUPPORT TICKETS
-- ============================================
INSERT INTO support_tickets (user_id, subject, message, category, status, priority) VALUES
(1, 'Payment not going through', 'I am trying to make a donation but the payment keeps failing.', 'payment', 'open', 'high'),
(2, 'Question about tax receipt', 'How do I get my tax receipt for last year?', 'receipt', 'open', 'normal');

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;
