-- ============================================
-- Church Financial Partnership System
-- Bright Light Ministry Int'l Partnership Portal
-- Project Milestones Table Migration
-- ============================================

-- ============================================
-- PROJECT MILESTONES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS project_milestones (
    milestone_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    target_amount DECIMAL(15,2) NOT NULL,
    current_amount DECIMAL(15,2) DEFAULT 0,
    notification_sent BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
    INDEX idx_project_id (project_id),
    INDEX idx_active (is_active),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INSERT SAMPLE MILESTONES FOR TESTING
-- ============================================
-- Sample milestones for House of Prayer Expansion (project_id = 1)
INSERT INTO project_milestones (project_id, title, description, target_amount, current_amount, notification_sent, is_active, display_order) VALUES
(1, 'Foundation Completion', 'Complete the foundation work for the new sanctuary expansion', 500000.00, 450000.00, TRUE, TRUE, 1),
(1, 'Structural Framework', 'Complete the structural framework and columns', 800000.00, 320000.00, FALSE, TRUE, 2),
(1, 'Roof Installation', 'Install the roof and weatherproofing', 600000.00, 0.00, FALSE, TRUE, 3),
(1, 'Interior Finishing', 'Complete interior finishing including seating', 600000.00, 0.00, FALSE, TRUE, 4);

-- Sample milestones for Kenya Church Plant (project_id = 2)
INSERT INTO project_milestones (project_id, title, description, target_amount, current_amount, notification_sent, is_active, display_order) VALUES
(2, 'Land Acquisition', 'Purchase land for the new church sanctuary', 30000.00, 30000.00, TRUE, TRUE, 1),
(2, 'Well Construction', 'Build a clean water well for the community', 25000.00, 15000.00, FALSE, TRUE, 2),
(2, 'Foundation Work', 'Complete foundation and groundwork', 25000.00, 10000.00, FALSE, TRUE, 3),
(2, 'Sanctuary Completion', 'Complete the sanctuary building', 40000.00, 7400.00, FALSE, TRUE, 4);

-- Sample milestones for Thanksgiving Baskets (project_id = 3)
INSERT INTO project_milestones (project_id, title, description, target_amount, current_amount, notification_sent, is_active, display_order) VALUES
(3, 'Food Procurement', 'Purchase food items for 1,500 families', 30000.00, 28000.00, TRUE, TRUE, 1),
(3, 'Packaging & Distribution', 'Pack and distribute baskets to families', 20000.00, 15000.00, FALSE, TRUE, 2);