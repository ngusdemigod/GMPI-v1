<?php
/**
 * Admin Members Management Page
 * Church Financial Partnership System
 */

require_once __DIR__ . '/includes/config.php';

// Require authenticated admin access
$admin = requireAdmin();

// Set page variables for layout
$pageTitle = 'Admin Members';
$pageSubtitle = 'Manage administrator access and permissions';
$activePage = 'admin-members';

$db = Database::getInstance();
$action = $_GET['action'] ?? 'list';
$adminId = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    try {
        if ($action === 'grant_admin') {
            $userId = intval($_POST['user_id'] ?? 0);
            
            if (!$userId) {
                throw new Exception('Invalid user selected');
            }
            
            // Check if user already has admin access
            $existingAdmin = $db->fetchOne(
                "SELECT admin_id FROM admin_users WHERE user_id = :user_id",
                ['user_id' => $userId]
            );
            
            if ($existingAdmin) {
                throw new Exception('User already has admin access');
            }
            
            // Check if user exists and is active
            $user = $db->fetchOne(
                "SELECT user_id, email, first_name, last_name FROM users WHERE user_id = :user_id AND is_active = TRUE",
                ['user_id' => $userId]
            );
            
            if (!$user) {
                throw new Exception('User not found or inactive');
            }
            
            // Create admin record
            $newAdminId = Auth::createAdmin($userId);
            
            // Assign admin role
            $adminRole = $db->fetchOne("SELECT role_id FROM user_roles WHERE role_name = 'admin'");
            if ($adminRole) {
                $db->execute(
                    "INSERT INTO user_role_assignments (user_id, role_id) VALUES (:user_id, :role_id)
                     ON DUPLICATE KEY UPDATE role_id = :role_id",
                    ['user_id' => $userId, 'role_id' => $adminRole['role_id']]
                );
            }
            
            logAdminAction($_SESSION['admin_id'], 'ADMIN_GRANT', 'user', $userId, "$user->first_name $user->last_name");
            $success = 'Admin access granted successfully';
            $action = 'list';
        } elseif ($action === 'revoke_admin') {
            $targetAdminId = intval($_POST['admin_id'] ?? 0);
            
            if (!$targetAdminId) {
                throw new Exception('Invalid admin selected');
            }
            
            // Prevent self-revocation
            if ($targetAdminId == $_SESSION['admin_id']) {
                throw new Exception('You cannot revoke your own admin access');
            }
            
            // Check if this is the last admin
            $totalAdmins = $db->fetchOne("SELECT COUNT(*) as count FROM admin_users");
            if ($totalAdmins['count'] <= 1) {
                throw new Exception('Cannot remove the last admin. At least one admin must remain.');
            }
            
            // Get admin details before deletion
            $admin = $db->fetchOne(
                "SELECT au.*, u.email, u.first_name, u.last_name
                 FROM admin_users au
                 INNER JOIN users u ON au.user_id = u.user_id
                 WHERE au.admin_id = :admin_id",
                ['admin_id' => $targetAdminId]
            );
            
            // Remove admin access
            Auth::removeAdmin($targetAdminId);
            
            // Remove admin role
            $adminRole = $db->fetchOne("SELECT role_id FROM user_roles WHERE role_name = 'admin'");
            if ($adminRole) {
                $db->execute(
                    "DELETE FROM user_role_assignments WHERE user_id = :user_id AND role_id = :role_id",
                    ['user_id' => $admin['user_id'], 'role_id' => $adminRole['role_id']]
                );
            }
            
            logAdminAction($_SESSION['admin_id'], 'ADMIN_REVOKE', 'admin', $targetAdminId, "$admin->first_name $admin->last_name");
            $success = 'Admin access revoked successfully';
            $action = 'list';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get all admins
$admins = $db->fetchAll(
    "SELECT au.*, u.email, u.first_name, u.last_name, u.phone, u.created_at as user_created_at,
            (SELECT COUNT(*) FROM admin_sessions WHERE admin_id = au.admin_id AND expires_at > NOW()) as active_sessions,
            au.last_login_ip, au.last_login_agent
     FROM admin_users au
     INNER JOIN users u ON au.user_id = u.user_id
     ORDER BY au.created_at DESC"
);

// Get all users for admin grant dropdown
$allUsers = $db->fetchAll(
    "SELECT user_id, email, first_name, last_name, is_active
     FROM users
     WHERE is_active = TRUE
     ORDER BY first_name, last_name"
);

// Get users who already have admin access
$adminUserIds = array_column($admins, 'user_id');
$eligibleUsers = array_filter($allUsers, fn($u) => !in_array($u['user_id'], $adminUserIds));

// Get audit logs
$auditLogs = $db->fetchAll(
    "SELECT * FROM admin_audit_logs
     ORDER BY created_at DESC
     LIMIT 50"
);

// Get statistics
$totalAdmins = count($admins);
$totalUsers = count($allUsers);
$activeSessions = array_sum(array_column($admins, 'active_sessions'));

// Include layout
require_once __DIR__ . '/includes/layout.php';

// Define custom styles for this page
function layoutCustomStyles() {
    ?>
    <style>
        .admin-card {
            display: flex;
            align-items: center;
            gap: var(--space-4);
            padding: var(--space-4);
            background: var(--cream-light);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-3);
        }
        
        .admin-avatar {
            width: 56px;
            height: 56px;
            background: var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--dark);
            font-size: 20px;
        }
        
        .admin-details {
            flex: 1;
        }
        
        .admin-name {
            font-size: var(--type-body);
            font-weight: 600;
            color: var(--dark);
        }
        
        .admin-email {
            font-size: var(--type-body-sm);
            color: var(--text-secondary);
        }
        
        .admin-meta {
            font-size: var(--type-body-xs);
            color: var(--text-muted);
            margin-top: var(--space-1);
        }
        
        .admin-actions {
            display: flex;
            gap: var(--space-2);
        }
        
        .audit-log-item {
            display: flex;
            gap: var(--space-4);
            padding: var(--space-3);
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .audit-log-item:last-child {
            border-bottom: none;
        }
        
        .audit-log-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }
        
        .audit-log-icon.grant {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }
        
        .audit-log-icon.revoke {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }
        
        .audit-log-icon.login {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
        }
        
        .audit-log-content {
            flex: 1;
        }
        
        .audit-log-action {
            font-size: var(--type-body-sm);
            font-weight: 500;
            color: var(--dark);
        }
        
        .audit-log-details {
            font-size: var(--type-body-xs);
            color: var(--text-secondary);
        }
        
        .audit-log-time {
            font-size: var(--type-body-xs);
            color: var(--text-muted);
            white-space: nowrap;
        }
        
        .form-group {
            margin-bottom: var(--space-5);
        }
        
        .form-label {
            display: block;
            font-size: var(--type-body-sm);
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: var(--space-2);
        }
        
        .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid rgba(0,0,0,0.1);
            border-radius: var(--radius-sm);
            font-size: var(--type-body);
        }
        
        .form-select:focus {
            outline: none;
            border-color: var(--gold);
        }
        
        .admin-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-6);
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .metrics-grid { grid-template-columns: repeat(2, 1fr); }
            .admin-grid { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 768px) {
            .metrics-grid { grid-template-columns: repeat(2, 1fr); gap: var(--space-3); margin-bottom: var(--space-5); }
            .admin-grid { grid-template-columns: 1fr; }
            .admin-card { flex-direction: column; align-items: flex-start; }
            .admin-actions { width: 100%; justify-content: flex-end; margin-top: var(--space-3); }
        }
        
    </style>
    <?php
}

// Start the layout
layoutHeader();
?>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo e($success); ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-error"><?php echo e($error); ?></div>
<?php endif; ?>

<!-- Statistics -->
<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-title">Total Admins</div>
        <div class="metric-value"><?php echo number_format($totalAdmins); ?></div>
        <div class="metric-subtitle">Active administrators</div>
    </div>
    <div class="metric-card">
        <div class="metric-title">Total Users</div>
        <div class="metric-value"><?php echo number_format($totalUsers); ?></div>
        <div class="metric-subtitle">In system</div>
    </div>
    <div class="metric-card">
        <div class="metric-title">Active Sessions</div>
        <div class="metric-value"><?php echo number_format($activeSessions); ?></div>
        <div class="metric-subtitle">Currently logged in</div>
    </div>
    <div class="metric-card">
        <div class="metric-title">Eligible Users</div>
        <div class="metric-value"><?php echo number_format(count($eligibleUsers)); ?></div>
        <div class="metric-subtitle">Can be granted admin</div>
    </div>
</div>

<div class="admin-grid">
    <!-- Admin List -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Current Admins</h2>
        </div>
        <div class="card-body">
            <?php if (empty($admins)): ?>
                <p style="color: var(--text-muted); text-align: center; padding: var(--space-6);">No administrators found</p>
            <?php else: ?>
                <?php foreach ($admins as $adminItem): ?>
                    <div class="admin-card">
                        <div class="admin-avatar">
                            <?php echo strtoupper(substr($adminItem['first_name'], 0, 1) . substr($adminItem['last_name'], 0, 1)); ?>
                        </div>
                        <div class="admin-details">
                            <div class="admin-name"><?php echo e($adminItem['first_name'] . ' ' . $adminItem['last_name']); ?></div>
                            <div class="admin-email"><?php echo e($adminItem['email']); ?></div>
                            <div class="admin-meta">
                                <i class="fas fa-clock"></i> Admin since <?php echo formatDate($adminItem['created_at']); ?>
                                <?php if ($adminItem['active_sessions'] > 0): ?>
                                    <span style="margin-left: var(--space-2);">
                                        <i class="fas fa-circle" style="color: var(--success); font-size: 8px;"></i> 
                                        <?php echo $adminItem['active_sessions']; ?> active session(s)
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="admin-actions">
                            <?php if ($adminItem['admin_id'] != $_SESSION['admin_id']): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to revoke admin access for this user?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="revoke_admin">
                                    <input type="hidden" name="admin_id" value="<?php echo $adminItem['admin_id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fas fa-user-times"></i> Revoke
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="btn btn-secondary btn-sm" style="cursor: default;">
                                    <i class="fas fa-shield-alt"></i> You
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Grant Admin Access -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Grant Admin Access</h2>
        </div>
        <div class="card-body">
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Granting admin access gives full system access. Only grant to trusted personnel.
            </div>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="grant_admin">
                
                <div class="form-group">
                    <label class="form-label" for="user_id">Select User</label>
                    <select name="user_id" id="user_id" class="form-select" required>
                        <option value="">-- Select a user --</option>
                        <?php foreach ($eligibleUsers as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>">
                                <?php echo e($user['first_name'] . ' ' . $user['last_name']); ?> - <?php echo e($user['email']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-user-plus"></i> Grant Admin Access
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Recent Audit Activity -->
<div class="card" style="margin-top: var(--space-6);">
    <div class="card-header">
        <h2 class="card-title">Recent Admin Activity</h2>
        <a href="audit-logs.php" class="btn btn-secondary btn-sm">View All Logs</a>
    </div>
    <div class="card-body">
        <?php if (empty($auditLogs)): ?>
            <p style="color: var(--text-muted); text-align: center; padding: var(--space-6);">No audit logs available</p>
        <?php else: ?>
            <?php foreach ($auditLogs as $log): ?>
                <div class="audit-log-item">
                    <div class="audit-log-icon <?php echo strtolower($log['action_type']); ?>">
                        <i class="fas fa-<?php 
                            echo ($log['action_type'] === 'ADMIN_GRANT' ? 'user-plus' : 
                                ($log['action_type'] === 'ADMIN_REVOKE' ? 'user-times' :
                                ($log['action_type'] === 'LOGIN_SUCCESS' ? 'sign-in-alt' :
                                ($log['action_type'] === 'LOGIN_FAILED' ? 'sign-in-alt' :
                                'shield-alt'))));
                        ?>"></i>
                    </div>
                    <div class="audit-log-content">
                        <div class="audit-log-action">
                            <?php 
                            echo str_replace('_', ' ', strtolower($log['action_type']));
                            if ($log['target_name']):
                                echo ' - ' . e($log['target_name']);
                            endif;
                            ?>
                        </div>
                        <div class="audit-log-details">
                            <?php if ($log['changes']): ?>
                                <small><?php echo e(substr(json_encode($log['changes']), 0, 100)); ?><?php echo strlen(json_encode($log['changes'])) > 100 ? '...' : ''; ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="audit-log-time">
                        <?php echo formatDate($log['created_at'], 'M d, Y H:i'); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
// End the layout
layoutFooter();
?>
