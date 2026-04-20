<?php
/**
 * Export Data Page
 * Church Financial Partnership System
 */

require_once __DIR__ . '/includes/config.php';

// Require authentication
$admin = requireAdmin();

$db = Database::getInstance();
$type = $_GET['type'] ?? 'transactions';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="export_' . $type . '_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

if ($type === 'transactions') {
    // Export transactions
    fputcsv($output, ['Transaction ID', 'Date', 'User', 'Email', 'Category', 'Campaign', 'Amount', 'Status', 'Reference', 'Payment Method']);
    
    $transactions = $db->fetchAll(
        "SELECT t.*, u.first_name, u.last_name, u.email, p.title as project_title, pm.card_type, pm.last_four_digits
         FROM transactions t
         INNER JOIN users u ON t.user_id = u.user_id
         LEFT JOIN projects p ON t.project_id = p.project_id
         LEFT JOIN payment_methods pm ON t.payment_method_id = pm.payment_method_id
         ORDER BY t.transaction_date DESC"
    );
    
    foreach ($transactions as $t) {
        fputcsv($output, [
            $t['transaction_id'],
            $t['transaction_date'],
            $t['first_name'] . ' ' . $t['last_name'],
            $t['email'],
            $t['category'],
            $t['campaign_title'] ?? 'N/A',
            $t['amount'],
            $t['status'],
            $t['transaction_reference'],
            ($t['card_type'] ?? '') . ' •••• ' . ($t['last_four_digits'] ?? 'N/A')
        ]);
    }
    
} elseif ($type === 'users') {
    // Export users
    fputcsv($output, ['User ID', 'Name', 'Email', 'Phone', 'Total Given', 'Transactions', 'Pledges', 'Joined', 'Status']);
    
    $users = $db->fetchAll(
        "SELECT u.*, 
                (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = u.user_id AND status = 'completed') as total_given,
                (SELECT COUNT(*) FROM transactions WHERE user_id = u.user_id AND status = 'completed') as transaction_count,
                (SELECT COUNT(*) FROM pledges WHERE user_id = u.user_id AND is_active = TRUE) as pledge_count
         FROM users u
         ORDER BY u.created_at DESC"
    );
    
    foreach ($users as $u) {
        fputcsv($output, [
            $u['user_id'],
            $u['first_name'] . ' ' . $u['last_name'],
            $u['email'],
            $u['phone'] ?? 'N/A',
            $u['total_given'],
            $u['transaction_count'],
            $u['pledge_count'],
            $u['created_at'],
            $u['is_active'] ? 'Active' : 'Inactive'
        ]);
    }
    
} elseif ($type === 'partners') {
    // Export partners
    fputcsv($output, ['User ID', 'Name', 'Email', 'Frequency', 'Total Pledge', 'Remaining', 'Start Date', 'End Date', 'Transactions', 'Total Given']);
    
    $partners = $db->fetchAll(
        "SELECT p.*, u.email, u.first_name, u.last_name,
                (SELECT COUNT(*) FROM transactions t WHERE t.user_id = p.user_id AND t.status = 'completed') as total_transactions,
                (SELECT COALESCE(SUM(amount), 0) FROM transactions t WHERE t.user_id = p.user_id AND t.status = 'completed') as total_given
         FROM pledges p
         INNER JOIN users u ON p.user_id = u.user_id
         WHERE p.is_active = TRUE
         ORDER BY p.created_at DESC"
    );
    
    foreach ($partners as $p) {
        fputcsv($output, [
            $p['user_id'],
            $p['first_name'] . ' ' . $p['last_name'],
            $p['email'],
            $p['frequency'],
            $p['total_amount'],
            $p['remaining_amount'],
            $p['start_date'],
            $p['end_date'] ?? 'Ongoing',
            $p['total_transactions'],
            $p['total_given']
        ]);
    }
    
} elseif ($type === 'projects') {
    // Export campaigns
    fputcsv($output, ['Campaign ID', 'Title', 'Category', 'Goal', 'Raised', 'Partners', 'Start Date', 'End Date', 'Status']);
    
    $campaigns = $db->fetchAll(
        "SELECT *, 
                CASE WHEN goal_amount > 0 THEN (current_amount / goal_amount) * 100 ELSE 0 END as progress_percentage
         FROM campaigns
         ORDER BY display_order"
    );
    
    foreach ($campaigns as $c) {
        fputcsv($output, [
            $c['campaign_id'],
            $c['title'],
            $c['category'],
            $c['goal_amount'],
            $c['current_amount'],
            $c['partner_count'],
            $c['start_date'] ?? 'N/A',
            $c['end_date'] ?? 'N/A',
            $c['is_active'] ? 'Active' : 'Inactive'
        ]);
    }
    
} elseif ($type === 'plans') {
    // Export payment plans
    fputcsv($output, ['Plan ID', 'Name', 'Frequency', 'Amount', 'Active Subscribers', 'Status', 'Max Subscriptions']);
    
    $plans = $db->fetchAll(
        "SELECT *, 
                (SELECT COUNT(*) FROM pledges WHERE frequency = payment_plans.frequency AND is_active = TRUE) as active_subscribers
         FROM payment_plans
         ORDER BY display_order"
    );
    
    foreach ($plans as $p) {
        fputcsv($output, [
            $p['plan_id'],
            $p['name'],
            $p['frequency'],
            $p['amount'],
            $p['active_subscribers'],
            $p['is_paused'] ? 'Paused' : ($p['is_active'] ? 'Active' : 'Inactive'),
            $p['max_subscriptions'] ?? 'Unlimited'
        ]);
    }
    
} elseif ($type === 'audit') {
    // Export audit logs
    fputcsv($output, ['Log ID', 'Date', 'Admin', 'Action', 'Target', 'Changes', 'IP Address']);
    
    $logs = $db->fetchAll(
        "SELECT al.*, u.email as admin_email, u.first_name, u.last_name
         FROM admin_audit_logs al
         LEFT JOIN users u ON al.admin_id = u.user_id
         ORDER BY al.created_at DESC"
    );
    
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['log_id'],
            $log['created_at'],
            $log['first_name'] . ' ' . $log['last_name'] . ' (' . $log['admin_email'] . ')',
            str_replace('_', ' ', $log['action_type']),
            $log['target_name'] ?? ($log['target_type'] ?? 'N/A'),
            $log['changes'] ?? 'N/A',
            $log['ip_address'] ?? 'N/A'
        ]);
    }
}

fclose($output);
exit;
?>