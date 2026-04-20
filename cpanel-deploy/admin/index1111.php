<?php
/**
 * Admin Dashboard - Main Page
 * Church Financial Partnership System
 */

require_once __DIR__ . '/includes/config.php';

// Require authentication
$admin = requireAdmin();

// Get dashboard statistics
$db = Database::getInstance();

// Total users
$totalUsers = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_active = TRUE")['count'];

// Active campaigns (using campaigns table from existing schema)
$activeProjects = $db->fetchOne("SELECT COUNT(*) as count FROM campaigns WHERE is_active = TRUE")['count'];

// Monthly revenue (current month)
$monthlyRevenue = $db->fetchOne(
    "SELECT COALESCE(SUM(amount), 0) as total 
     FROM transactions 
     WHERE MONTH(transaction_date) = MONTH(NOW()) 
     AND YEAR(transaction_date) = YEAR(NOW()) 
     AND status = 'completed'"
)['total'];

// Pending transactions
$pendingTransactions = $db->fetchOne(
    "SELECT COUNT(*) as count FROM transactions WHERE status = 'pending'"
)['count'];

// Active partners (users with recurring subscriptions)
$activePartners = $db->fetchOne(
    "SELECT COUNT(DISTINCT user_id) as count 
     FROM pledges 
     WHERE is_active = TRUE 
     AND frequency IN ('Weekly', 'Monthly', 'Annually')"
)['count'];

// Recent transactions
$recentTransactions = $db->fetchAll(
    "SELECT t.*, u.first_name, u.last_name, p.title as project_title, pm.card_type, pm.last_four_digits
     FROM transactions t
     LEFT JOIN users u ON t.user_id = u.user_id
     LEFT JOIN projects p ON t.project_id = p.project_id
     LEFT JOIN payment_methods pm ON t.payment_method_id = pm.payment_method_id
     WHERE t.status = 'completed'
     ORDER BY t.transaction_date DESC
     LIMIT 10"
);

// Active campaigns with progress
$activeProjectsWithProgress = $db->fetchAll(
    "SELECT *, 
            CASE WHEN goal_amount > 0 THEN (current_amount / goal_amount) * 100 ELSE 0 END as progress_percentage
     FROM campaigns 
     WHERE is_active = TRUE 
     ORDER BY display_order, created_at DESC
     LIMIT 6"
);

// Recent users
$recentUsers = $db->fetchAll(
    "SELECT user_id, email, first_name, last_name, created_at, phone, address_line1, address_line2, city, state, postal_code, country,
            (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = u.user_id AND status = 'completed') as total_given,
            (SELECT COUNT(*) FROM transactions WHERE user_id = u.user_id AND status = 'completed') as transaction_count,
            (SELECT COUNT(*) FROM pledges WHERE user_id = u.user_id AND is_active = TRUE) as active_pledges,
            is_active
     FROM users u
     WHERE is_active = TRUE
     ORDER BY created_at DESC
     LIMIT 10"
);

// Payment plans summary
$paymentPlans = $db->fetchAll(
    "SELECT plan_id, name, amount, frequency, is_active, is_paused
     FROM payment_plans
     ORDER BY display_order"
);

// Get admin roles
$adminRoles = $db->fetchAll(
    "SELECT ur.role_name, ur.role_description, ur.permissions 
     FROM user_roles ur
     INNER JOIN user_role_assignments ura ON ur.role_id = ura.role_id
     WHERE ura.user_id = :user_id",
    ['user_id' => $admin['user_id']]
);

$roleNames = array_column($adminRoles, 'role_name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Grace Cathedral Partnership Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --gold: #D4AF37;
            --gold-light: #F4DF8D;
            --gold-dark: #B8941F;
            --dark: #1a1a2e;
            --dark-light: #16213e;
            --cream: #FDFBF7;
            --cream-light: #FAF8F3;
            --text-primary: #1a1a2e;
            --text-secondary: #6b6b7b;
            --text-muted: #999999;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.15);
            --radius-sm: 6px;
            --radius-md: 12px;
            --radius-lg: 16px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--cream);
            color: var(--text-primary);
            min-height: 100vh;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Playfair Display', serif;
            font-weight: 600;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 280px;
            background: var(--dark);
            color: white;
            padding: 24px 0;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 0 24px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .sidebar-logo-icon {
            width: 40px;
            height: 40px;
            background: var(--gold);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--dark);
        }
        
        .sidebar-logo-text {
            font-family: 'Playfair Display', serif;
            font-size: 18px;
        }
        
        .sidebar-logo-text span {
            display: block;
            font-size: 11px;
            font-family: 'Inter', sans-serif;
            color: rgba(255,255,255,0.6);
            font-weight: 400;
        }
        
        .admin-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 24px;
            background: rgba(255,255,255,0.05);
            border-radius: var(--radius-md);
            margin: 16px 12px;
        }
        
        .admin-avatar {
            width: 40px;
            height: 40px;
            background: var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--dark);
        }
        
        .admin-details {
            flex: 1;
            min-width: 0;
        }
        
        .admin-name {
            font-size: 14px;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .admin-email {
            font-size: 12px;
            color: rgba(255,255,255,0.5);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .admin-roles {
            padding: 0 12px;
        }
        
        .role-badge {
            display: inline-block;
            background: rgba(212, 175, 55, 0.2);
            color: var(--gold-light);
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            margin: 2px;
        }
        
        .nav-menu {
            list-style: none;
            padding: 0 12px;
        }
        
        .nav-item {
            margin: 4px 0;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            border-radius: var(--radius-md);
            transition: all 0.2s;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .nav-link i {
            width: 20px;
            text-align: center;
        }
        
        .nav-section {
            padding: 16px 12px 8px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.4);
        }
        
        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 16px 12px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-footer .nav-link {
            color: rgba(255,255,255,0.5);
        }
        
        .sidebar-footer .nav-link:hover {
            color: var(--danger);
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 32px;
            min-height: 100vh;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }
        
        .page-title h1 {
            font-size: 28px;
            color: var(--dark);
            margin-bottom: 4px;
        }
        
        .page-title p {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .page-actions {
            display: flex;
            gap: 12px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: var(--gold);
            color: var(--dark);
        }
        
        .btn-primary:hover {
            background: var(--gold-dark);
        }
        
        .btn-secondary {
            background: white;
            color: var(--text-primary);
            border: 1px solid rgba(0,0,0,0.1);
        }
        
        .btn-secondary:hover {
            background: var(--cream-light);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        /* Metrics Cards */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .metric-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow-sm);
            transition: all 0.2s;
        }
        
        .metric-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .metric-icon.users {
            background: rgba(26, 26, 46, 0.1);
            color: var(--dark);
        }
        
        .metric-icon.projects {
            background: rgba(212, 175, 55, 0.1);
            color: var(--gold-dark);
        }
        
        .metric-icon.revenue {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }
        
        .metric-icon.pending {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }
        
        .metric-title {
            font-size: 13px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .metric-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--dark);
        }
        
        .metric-change {
            font-size: 12px;
            margin-top: 8px;
        }
        
        .metric-change.positive {
            color: var(--success);
        }
        
        .metric-change.negative {
            color: var(--danger);
        }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        
        .card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .card-title {
            font-size: 18px;
            color: var(--dark);
        }
        
        .card-body {
            padding: 24px;
        }
        
        /* Project Cards */
        .project-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        
        .project-card {
            background: var(--cream-light);
            border-radius: var(--radius-md);
            padding: 20px;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        
        .project-icon {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .project-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .badge-urgent {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }
        
        .badge-missions {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }
        
        .badge-seasonal {
            background: rgba(255, 193, 7, 0.1);
            color: #b7950b;
        }
        
        .badge-scholarship {
            background: rgba(23, 162, 184, 0.1);
            color: #0c7986;
        }
        
        .project-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .project-description {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 16px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .project-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 13px;
        }
        
        .project-stat {
            color: var(--text-secondary);
        }
        
        .project-stat strong {
            color: var(--dark);
        }
        
        .progress-bar {
            height: 8px;
            background: rgba(0,0,0,0.05);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--gold) 0%, var(--gold-light) 100%);
            border-radius: 4px;
            transition: width 0.3s;
        }
        
        /* Transaction List */
        .transaction-list {
            list-style: none;
        }
        
        .transaction-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .transaction-item:last-child {
            border-bottom: none;
        }
        
        .transaction-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        
        .transaction-icon.success {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }
        
        .transaction-icon.info {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
        }
        
        .transaction-details {
            flex: 1;
            min-width: 0;
        }
        
        .transaction-user {
            font-size: 14px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .transaction-project {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .transaction-amount {
            font-size: 16px;
            font-weight: 600;
            color: var(--success);
        }
        
        .transaction-date {
            font-size: 12px;
            color: var(--text-muted);
        }
        
        /* User List */
        .user-list {
            list-style: none;
        }
        
        .user-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .user-item:last-child {
            border-bottom: none;
        }
        
        .user-avatar {
            width: 36px;
            height: 36px;
            background: var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--dark);
            font-size: 13px;
        }
        
        .user-info {
            flex: 1;
            min-width: 0;
        }
        
        .user-name {
            font-size: 14px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .user-email {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .user-given {
            font-size: 13px;
            font-weight: 600;
            color: var(--success);
        }
        
        /* Plans List */
        .plans-list {
            list-style: none;
        }
        
        .plan-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .plan-item:last-child {
            border-bottom: none;
        }
        
        .plan-info {
            flex: 1;
        }
        
        .plan-name {
            font-size: 14px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .plan-frequency {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .plan-amount {
            font-size: 16px;
            font-weight: 600;
            color: var(--gold-dark);
        }
        
        .plan-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .plan-status.active {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }
        
        .plan-status.paused {
            background: rgba(255, 193, 7, 0.1);
            color: #b7950b;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .metrics-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .metrics-grid {
                grid-template-columns: 1fr;
            }
            
            .project-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal {
            background: white;
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
        }
        
        .modal-close:hover {
            color: var(--text-primary);
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .receipt-details {
            background: var(--cream-light);
            border-radius: var(--radius-md);
            padding: 20px;
        }
        
        .receipt-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .receipt-row:last-child {
            border-bottom: none;
        }
        
        .receipt-label {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .receipt-value {
            font-weight: 600;
            color: var(--dark);
        }
        
        .receipt-total {
            display: flex;
            justify-content: space-between;
            padding: 16px 0;
            margin-top: 16px;
            border-top: 2px solid var(--gold);
        }
        
        .receipt-total-label {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .receipt-total-amount {
            font-size: 24px;
            font-weight: 700;
            color: var(--success);
        }
        
        .receipt-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        
        .user-profile-details {
            background: var(--cream-light);
            border-radius: var(--radius-md);
            padding: 20px;
        }
        
        .profile-section {
            margin-bottom: 24px;
        }
        
        .profile-section-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }
        
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .profile-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .profile-label {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .profile-value {
            font-size: 14px;
            color: var(--dark);
            font-weight: 500;
        }
        
        .profile-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 16px;
        }
        
        .profile-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        
        .profile-action-btn.edit {
            background: white;
            color: var(--text-primary);
            border: 1px solid rgba(0,0,0,0.1);
        }
        
        .profile-action-btn.edit:hover {
            background: var(--cream-light);
        }
        
        .profile-action-btn.pause {
            background: rgba(255, 193, 7, 0.1);
            color: #b7950b;
        }
        
        .profile-action-btn.pause:hover {
            background: rgba(255, 193, 7, 0.2);
        }
        
        .profile-action-btn.reset {
            background: rgba(23, 162, 184, 0.1);
            color: #0c7986;
        }
        
        .profile-action-btn.reset:hover {
            background: rgba(23, 162, 184, 0.2);
        }
        
        .profile-action-btn.delete {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }
        
        .profile-action-btn.delete:hover {
            background: rgba(220, 53, 69, 0.2);
        }
        
        .transaction-item {
            cursor: pointer;
        }
        
        .transaction-item:hover {
            background: var(--cream-light);
        }
        
        .user-item {
            cursor: pointer;
        }
        
        .user-item:hover {
            background: var(--cream-light);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <div class="sidebar-logo-icon">✝</div>
                <div class="sidebar-logo-text">
                    Bright Light Ministry Int'l
                    <span>Admin Dashboard</span>
                </div>
            </div>
            
            <div class="admin-info">
                <div class="admin-avatar">
                    <?php echo strtoupper(substr($admin['first_name'], 0, 1) . substr($admin['last_name'], 0, 1)); ?>
                </div>
                <div class="admin-details">
                    <div class="admin-name"><?php echo e($admin['first_name'] . ' ' . $admin['last_name']); ?></div>
                    <div class="admin-email"><?php echo e($admin['email']); ?></div>
                </div>
            </div>
            
            <div class="admin-roles">
                <?php foreach ($adminRoles as $role): ?>
                    <span class="role-badge"><?php echo e($role['role_name']); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        
        <nav>
            <ul class="nav-menu">
                <li class="nav-section">Dashboard</li>
                <li class="nav-item">
                    <a href="index.php" class="nav-link active">
                        <i class="fas fa-home"></i>
                        <span>Overview</span>
                    </a>
                </li>
                
                <li class="nav-section">Management</li>
                <li class="nav-item">
                    <a href="projects.php" class="nav-link">
                        <i class="fas fa-project-diagram"></i>
                        <span>Projects</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="users.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span>Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="transactions.php" class="nav-link">
                        <i class="fas fa-exchange-alt"></i>
                        <span>Transactions</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="plans.php" class="nav-link">
                        <i class="fas fa-tags"></i>
                        <span>Payment Plans</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="partners.php" class="nav-link">
                        <i class="fas fa-handshake"></i>
                        <span>Partners</span>
                    </a>
                </li>
                
                <li class="nav-section">Administration</li>
                <li class="nav-item">
                    <a href="support.php" class="nav-link">
                        <i class="fas fa-headset"></i>
                        <span>Support Tickets</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin-members.php" class="nav-link">
                        <i class="fas fa-user-shield"></i>
                        <span>Admin Members</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="audit-logs.php" class="nav-link">
                        <i class="fas fa-history"></i>
                        <span>Audit Logs</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="settings.php" class="nav-link">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </li>
                
                <li class="sidebar-footer">
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a href="logout.php" class="nav-link">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <div class="page-title">
                <h1>Dashboard Overview</h1>
                <p>Welcome back, <?php echo e($admin['first_name']); ?>. Here's what's happening today.</p>
            </div>
            <div class="page-actions">
                <a href="export.php" class="btn btn-secondary">
                    <i class="fas fa-download"></i>
                    Export Data
                </a>
                <button class="btn btn-primary" onclick="openProjectModal()">
                    <i class="fas fa-plus"></i>
                    New Project
                </button>
            </div>
        </div>
        
        <!-- Metrics Grid -->
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-title">Total Users</span>
                    <div class="metric-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="metric-value"><?php echo number_format($totalUsers); ?></div>
                <div class="metric-change positive">
                    <i class="fas fa-arrow-up"></i> Active members
                </div>
            </div>
            
            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-title">Active Projects</span>
                    <div class="metric-icon projects">
                        <i class="fas fa-project-diagram"></i>
                    </div>
                </div>
                <div class="metric-value"><?php echo number_format($activeProjects); ?></div>
                <div class="metric-change">
                    <i class="fas fa-check-circle"></i> Currently running
                </div>
            </div>
            
            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-title">Monthly Revenue</span>
                    <div class="metric-icon revenue">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
                <div class="metric-value"><?php echo formatCurrency($monthlyRevenue); ?></div>
                <div class="metric-change positive">
                    <i class="fas fa-arrow-up"></i> This month
                </div>
            </div>
            
            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-title">Pending Transactions</span>
                    <div class="metric-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="metric-value"><?php echo number_format($pendingTransactions); ?></div>
                <div class="metric-change">
                    <i class="fas fa-exclamation-circle"></i> Requires attention
                </div>
            </div>
        </div>
        
        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Active Projects -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Active Projects</h2>
                    <a href="projects.php" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                        View All
                    </a>
                </div>
                <div class="card-body">
                    <div class="project-grid">
                        <?php foreach ($activeProjectsWithProgress as $project): 
                            $progress = $project['progress_percentage'];
                        ?>
                            <div class="project-card">
                                <div class="project-header">
                                    <div class="project-icon">
                                        <i class="fas fa-<?php echo e($project['icon'] ?? 'church'); ?>"></i>
                                    </div>
                                    <span class="project-badge badge-<?php echo e(strtolower($project['category'])); ?>">
                                        <?php echo e($project['category']); ?>
                                    </span>
                                </div>
                                <h3 class="project-title"><?php echo e($project['title']); ?></h3>
                                <p class="project-description"><?php echo e(substr($project['description'], 0, 100)); ?>...</p>
                                <div class="project-stats">
                                    <span class="project-stat"><strong><?php echo formatCurrency($project['current_amount']); ?></strong> raised</span>
                                    <span class="project-stat"><?php echo number_format($project['partner_count']); ?> partners</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo min($progress, 100); ?>%"></div>
                                </div>
                                <div style="text-align: right; font-size: 12px; color: var(--text-secondary); margin-top: 8px;">
                                    <?php echo number_format($progress, 1); ?>% of <?php echo formatCurrency($project['goal_amount']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Right Column -->
            <div>
                <!-- Recent Transactions -->
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-header">
                        <h2 class="card-title">Recent Transactions</h2>
                        <a href="transactions.php" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                            View All
                        </a>
                    </div>
                    <div class="card-body">
                        <ul class="transaction-list">
                            <?php foreach ($recentTransactions as $transaction): ?>
                                <li class="transaction-item" data-ref="<?php echo e($transaction['transaction_reference']); ?>">
                                    <div class="transaction-icon success">
                                        <i class="fas fa-arrow-down"></i>
                                    </div>
                                    <div class="transaction-details">
                                        <div class="transaction-user">
                                            <?php echo e($transaction['first_name'] . ' ' . $transaction['last_name']); ?>
                                        </div>
                                        <div class="transaction-project">
                                            <?php echo e($transaction['project_title'] ?? $transaction['category']); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="transaction-amount">
                                            +<?php echo formatCurrency($transaction['amount']); ?>
                                        </div>
                                        <div class="transaction-date">
                                            <?php echo formatDate($transaction['transaction_date']); ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                
                <!-- Payment Plans -->
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-header">
                        <h2 class="card-title">Payment Plans</h2>
                        <a href="plans.php" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                            Manage
                        </a>
                    </div>
                    <div class="card-body">
                        <ul class="plans-list">
                            <?php foreach ($paymentPlans as $plan): ?>
                                <li class="plan-item">
                                    <div class="plan-info">
                                        <div class="plan-name"><?php echo e($plan['name']); ?></div>
                                        <div class="plan-frequency"><?php echo e($plan['frequency']); ?> • <?php echo formatCurrency($plan['amount']); ?></div>
                                    </div>
                                    <span class="plan-status <?php echo $plan['is_paused'] ? 'paused' : 'active'; ?>">
                                        <?php echo $plan['is_paused'] ? 'Paused' : 'Active'; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                
                <!-- Recent Users -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Recent Users</h2>
                        <a href="users.php" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                            View All
                        </a>
                    </div>
                    <div class="card-body">
                        <ul class="user-list">
                            <?php foreach ($recentUsers as $user): ?>
                                <li class="user-item" data-user-id="<?php echo $user['user_id']; ?>">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                    </div>
                                    <div class="user-info">
                                        <div class="user-name">
                                            <?php echo e($user['first_name'] . ' ' . $user['last_name']); ?>
                                        </div>
                                        <div class="user-email">
                                            <?php echo e($user['email']); ?>
                                        </div>
                                    </div>
                                    <div class="user-given">
                                        <?php echo formatCurrency($user['total_given']); ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Transaction Receipt Modal -->
    <div class="modal-overlay" id="transactionModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Transaction Receipt</h3>
                <button class="modal-close" onclick="closeTransactionModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="receipt-details" id="receiptContent">
                </div>
                <div class="receipt-actions">
                    <button class="btn btn-primary" onclick="downloadReceipt()">
                        <i class="fas fa-download"></i> Download Receipt
                    </button>
                    <button class="btn btn-secondary" onclick="closeTransactionModal()">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- User Profile Modal -->
    <div class="modal-overlay" id="userModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">User Profile</h3>
                <button class="modal-close" onclick="closeUserModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="user-profile-details" id="userProfileContent">
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button class="btn btn-secondary" onclick="closeUserModal()">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Format currency function
        function formatCurrency(amount, currency = 'USD') {
            return currency + ' ' + parseFloat(amount).toFixed(2);
        }
        
        // Project Modal Functions
        function openProjectModal() {
            window.location.href = 'projects.php?action=create';
        }
        
        // Store transaction and user data
        window.transactionData = <?php echo json_encode($recentTransactions); ?>;
        window.userData = <?php echo json_encode($recentUsers); ?>;
        
        // Set up click handlers for transactions
        document.querySelectorAll('.transaction-item').forEach(function(item) {
            item.addEventListener('click', function() {
                const ref = this.getAttribute('data-ref');
                fetchTransactionDetails(ref);
            });
        });
        
        // Set up click handlers for users
        document.querySelectorAll('.user-item').forEach(function(item) {
            item.addEventListener('click', function() {
                const userId = parseInt(this.getAttribute('data-user-id'));
                const user = window.userData.find(u => u.user_id === userId);
                if (user) {
                    showUserProfile(user);
                }
            });
        });
        
        // Transaction Modal Functions
        function fetchTransactionDetails(ref) {
            fetch('./api/get-transaction.php?ref=' + encodeURIComponent(ref))
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    showTransactionReceipt(data);
                })
                .catch(error => {
                    console.error('Error fetching transaction:', error);
                    alert('Error loading transaction details: ' + error.message);
                });
        }
        
        function showTransactionReceipt(transaction) {
            window.currentTransaction = transaction;
            const modal = document.getElementById('transactionModal');
            const receiptContent = document.getElementById('receiptContent');
            
            const cardInfo = transaction.card_type 
                ? transaction.card_type + ' •••• ' + transaction.last_four_digits
                : 'N/A';
            
            receiptContent.innerHTML = `
                <div class="receipt-row">
                    <span class="receipt-label">Transaction Reference</span>
                    <span class="receipt-value">${transaction.transaction_reference || 'N/A'}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Date</span>
                    <span class="receipt-value">${transaction.formatted_date || transaction.formatted_date || new Date(transaction.transaction_date).toLocaleDateString()}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Time</span>
                    <span class="receipt-value">${transaction.formatted_time || new Date(transaction.transaction_date).toLocaleTimeString()}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Payer</span>
                    <span class="receipt-value">${transaction.first_name} ${transaction.last_name}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Email</span>
                    <span class="receipt-value">${transaction.email || 'N/A'}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Phone</span>
                    <span class="receipt-value">${transaction.phone || 'N/A'}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Project/Category</span>
                    <span class="receipt-value">${transaction.project_title || transaction.category || 'N/A'}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Payment Method</span>
                    <span class="receipt-value">${cardInfo}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Status</span>
                    <span class="receipt-value" style="color: var(--success);">${transaction.status}</span>
                </div>
                <div class="receipt-total">
                    <span class="receipt-total-label">Total Amount</span>
                    <span class="receipt-total-amount">${transaction.formatted_amount || '$' + parseFloat(transaction.amount).toFixed(2)}</span>
                </div>
                <div class="receipt-total" style="border-top: 1px solid rgba(0,0,0,0.1);">
                    <span class="receipt-total-label">Amount in NGN</span>
                    <span class="receipt-total-amount">${transaction.formatted_amount_ngn || '₦' + parseFloat(transaction.amount_ngn || 0).toFixed(2)}</span>
                </div>
            `;
            
            modal.classList.add('active');
        }
        
        function closeTransactionModal() {
            document.getElementById('transactionModal').classList.remove('active');
        }
        
        function downloadReceipt() {
            const transaction = window.currentTransaction || {};
            const ref = transaction.transaction_reference || 'transaction';
            
            // Open receipt PDF in new window
            window.open('api/receipt.php?ref=' + encodeURIComponent(ref), '_blank');
        }
        
        function viewReceipt(transaction) {
            const ref = transaction.transaction_reference || 'transaction';
            // Open receipt PDF in new window
            window.open('api/receipt.php?ref=' + encodeURIComponent(ref), '_blank');
        }
        
        // User Profile Modal Functions
        function showUserProfile(user) {
            const modal = document.getElementById('userModal');
            const userProfileContent = document.getElementById('userProfileContent');
            
            const fullName = user.first_name + ' ' + user.last_name;
            const initials = (user.first_name[0] || '') + (user.last_name[0] || '');
            
            userProfileContent.innerHTML = `
                <div style="text-align: center; margin-bottom: 24px;">
                    <div class="user-avatar" style="width: 80px; height: 80px; font-size: 28px; margin: 0 auto 12px;">${initials.toUpperCase()}</div>
                    <h3 style="font-size: 20px; margin-bottom: 4px;">${fullName}</h3>
                    <p style="color: var(--text-secondary);">${user.email}</p>
                </div>
                
                <div class="profile-section">
                    <div class="profile-section-title">Account Summary</div>
                    <div class="profile-grid">
                        <div class="profile-item">
                            <span class="profile-label">Total Given</span>
                            <span class="profile-value" style="color: var(--success); font-size: 18px;">${formatCurrency(user.total_given)}</span>
                        </div>
                        <div class="profile-item">
                            <span class="profile-label">Transactions</span>
                            <span class="profile-value">${user.transaction_count || 0}</span>
                        </div>
                        <div class="profile-item">
                            <span class="profile-label">Active Pledges</span>
                            <span class="profile-value">${user.active_pledges || 0}</span>
                        </div>
                        <div class="profile-item">
                            <span class="profile-label">Status</span>
                            <span class="profile-value" style="color: ${user.is_active ? 'var(--success)' : 'var(--text-muted)'}">${user.is_active ? 'Active' : 'Inactive'}</span>
                        </div>
                    </div>
                </div>
                
                <div class="profile-section">
                    <div class="profile-section-title">Contact Information</div>
                    <div class="profile-grid">
                        <div class="profile-item">
                            <span class="profile-label">Phone</span>
                            <span class="profile-value">${user.phone || 'N/A'}</span>
                        </div>
                        <div class="profile-item">
                            <span class="profile-label">Member Since</span>
                            <span class="profile-value">${new Date(user.created_at).toLocaleDateString()}</span>
                        </div>
                    </div>
                    <div style="margin-top: 12px;">
                        <span class="profile-label">Address</span>
                        <span class="profile-value" style="font-size: 13px; line-height: 1.6;">
                            ${user.address_line1 || ''}<br>
                            ${user.address_line2 || ''}<br>
                            ${user.city || ''}, ${user.state || ''} ${user.postal_code || ''}<br>
                            ${user.country || ''}
                        </span>
                    </div>
                </div>
                
                <div class="profile-section">
                    <div class="profile-section-title">Account Actions</div>
                    <div class="profile-actions">
                        <a href="users.php?action=view&id=${user.user_id}" class="profile-action-btn edit">
                            <i class="fas fa-edit"></i> Edit Profile
                        </a>
                        <a href="users.php?action=reset_password&id=${user.user_id}" class="profile-action-btn reset">
                            <i class="fas fa-key"></i> Reset Password
                        </a>
                        <form method="POST" action="users.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to ${user.is_active ? 'pause' : 'activate'} this user?')">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="id" value="${user.user_id}">
                            <input type="hidden" name="csrf_token" value="${window.csrfToken || ''}">
                            <button type="submit" class="profile-action-btn pause">
                                <i class="fas fa-${user.is_active ? 'pause' : 'play'}"></i>
                                ${user.is_active ? 'Pause' : 'Activate'}
                            </button>
                        </form>
                        <a href="users.php?action=delete&id=${user.user_id}" class="profile-action-btn delete" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>
                </div>
            `;
            
            modal.classList.add('active');
        }
        
        function closeUserModal() {
            document.getElementById('userModal').classList.remove('active');
        }
        
        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                closeTransactionModal();
                closeUserModal();
            }
        });
        
        // Auto-logout warning (30 minutes before session expires)
        setTimeout(function() {
            // Session management could be enhanced here
        }, 1740000); // 29 minutes
    </script>
</body>
</html>
