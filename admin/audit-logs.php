<?php
/**
 * Audit Logs Page
 * Church Financial Partnership System
 */

require_once __DIR__ . '/includes/config.php';

// Require authenticated admin access
$admin = requireAdmin();

// Set page variables for layout
$pageTitle = 'Audit Logs';
$pageSubtitle = 'Track all administrative actions and system events';
$activePage = 'audit-logs';

$db = Database::getInstance();

// Get filter parameters
$actionFilter = $_GET['action_type'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? null;
$dateTo = $_GET['date_to'] ?? null;
$adminFilter = $_GET['admin_id'] ?? null;
$search = $_GET['search'] ?? null;
$page = intval($_GET['page'] ?? 1);
$perPage = ITEMS_PER_PAGE;

// Build query
$whereClauses = [];
$params = [];

if ($actionFilter !== 'all') {
    $whereClauses[] = "action_type = :action_type";
    $params['action_type'] = $actionFilter;
}

if ($dateFrom) {
    $whereClauses[] = "created_at >= :date_from";
    $params['date_from'] = $dateFrom . ' 00:00:00';
}

if ($dateTo) {
    $whereClauses[] = "created_at <= :date_to";
    $params['date_to'] = $dateTo . ' 23:59:59';
}

if ($adminFilter) {
    $whereClauses[] = "admin_id = :admin_id";
    $params['admin_id'] = $adminFilter;
}

if ($search) {
    $whereClauses[] = "(action_type LIKE :search OR target_name LIKE :search2 OR changes LIKE :search3)";
    $searchParam = "%$search%";
    $params['search'] = $searchParam;
    $params['search2'] = $searchParam;
    $params['search3'] = "%$search%";
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Get total count
$totalCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM admin_audit_logs $whereSql",
    $params
)['count'];

$totalPages = ceil($totalCount / $perPage);
$offset = ($page - 1) * $perPage;

// Get audit logs
$logs = $db->fetchAll(
    "SELECT al.*, u.email as admin_email, u.first_name as admin_first_name, u.last_name as admin_last_name
     FROM admin_audit_logs al
     LEFT JOIN admin_users au ON al.admin_id = au.admin_id
     LEFT JOIN users u ON au.user_id = u.user_id
     ORDER BY al.created_at DESC
     LIMIT :limit OFFSET :offset",
    array_merge($params, ['limit' => $perPage, 'offset' => $offset])
);

// Get action types for filter
$actionTypes = $db->fetchAll("SELECT DISTINCT action_type FROM admin_audit_logs ORDER BY action_type");

// Get unique admins for filter
$admins = $db->fetchAll(
    "SELECT DISTINCT au.admin_id, u.email, u.first_name, u.last_name
     FROM admin_audit_logs al
     INNER JOIN admin_users au ON al.admin_id = au.admin_id
     INNER JOIN users u ON au.user_id = u.user_id
     ORDER BY u.first_name, u.last_name"
);

// Get statistics
$stats = $db->fetchOne(
    "SELECT 
        COUNT(*) as total,
        COUNT(DISTINCT admin_id) as unique_admins,
        SUM(CASE WHEN action_type = 'LOGIN_SUCCESS' THEN 1 ELSE 0 END) as logins,
        SUM(CASE WHEN action_type = 'LOGIN_FAILED' THEN 1 ELSE 0 END) as failed_logins,
        SUM(CASE WHEN action_type IN ('PROJECT_CREATE', 'PROJECT_UPDATE', 'PROJECT_DELETE') THEN 1 ELSE 0 END) as project_actions,
        SUM(CASE WHEN action_type IN ('USER_CREATE', 'USER_UPDATE', 'USER_DELETE', 'USER_PASSWORD_RESET') THEN 1 ELSE 0 END) as user_actions,
        SUM(CASE WHEN action_type IN ('ADMIN_GRANT', 'ADMIN_REVOKE') THEN 1 ELSE 0 END) as admin_actions
     FROM admin_audit_logs $whereSql",
    $params
);

// Include layout
require_once __DIR__ . '/includes/layout.php';

// Define custom styles for this page
function layoutCustomStyles() {
    ?>
    <style>
        .filter-bar {
            background: white;
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            margin-bottom: var(--space-6);
            box-shadow: var(--shadow-sm);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: var(--space-4);
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: var(--space-2);
        }
        
        .filter-label {
            font-size: var(--type-body-xs);
            font-weight: 500;
            color: var(--text-secondary);
        }
        
        .filter-input, .filter-select {
            padding: 10px 14px;
            border: 1.5px solid rgba(0,0,0,0.1);
            border-radius: var(--radius-sm);
            font-size: var(--type-body);
        }
        
        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: var(--gold);
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: var(--space-3) var(--space-4);
            text-align: left;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        th {
            font-size: var(--type-body-xs-small);
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        tr:hover {
            background: var(--cream-light);
        }
        
        .log-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        
        .log-icon.login {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
        }
        
        .log-icon.success {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }
        
        .log-icon.warning {
            background: rgba(255, 193, 7, 0.1);
            color: #b7950b;
        }
        
        .log-icon.danger {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }
        
        .log-icon.info {
            background: rgba(108, 117, 125, 0.1);
            color: var(--text-muted);
        }
        
        .log-action {
            font-size: var(--type-body-sm);
            font-weight: 600;
            color: var(--dark);
        }
        
        .log-details {
            font-size: var(--type-body-xs);
            color: var(--text-secondary);
        }
        
        .log-time {
            font-size: var(--type-body-xs);
            color: var(--text-muted);
        }
        
        .log-ip {
            font-size: var(--type-body-xs);
            color: var(--text-muted);
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: var(--type-body-xs);
            font-weight: 500;
        }
        
        .status-success {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }
        
        .status-warning {
            background: rgba(255, 193, 7, 0.1);
            color: #b7950b;
        }
        
        .status-danger {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }
        
        .status-info {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: var(--space-2);
            margin-top: var(--space-6);
        }
        
        .page-btn {
            padding: 8px 14px;
            border: 1.5px solid rgba(0,0,0,0.1);
            border-radius: var(--radius-sm);
            background: white;
            cursor: pointer;
            font-size: var(--type-body);
        }
        
        .page-btn:hover:not(:disabled) {
            background: var(--cream-light);
            border-color: var(--gold);
        }
        
        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .page-btn.active {
            background: var(--gold);
            border-color: var(--gold);
            color: var(--dark);
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .metrics-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-grid { grid-template-columns: repeat(3, 1fr); }
        }
        
        @media (max-width: 768px) {
            .metrics-grid { grid-template-columns: repeat(2, 1fr); gap: var(--space-3); margin-bottom: var(--space-5); }
            .filter-grid { grid-template-columns: 1fr; }
            .pagination { flex-wrap: wrap; }
        }
        
    </style>
    <?php
}

// Define page actions for layout
function layoutPageActions() {
    ?>
    <a href="export.php?type=audit" class="btn btn-secondary">
        <i class="fas fa-download"></i>
        <span class="btn-text">Export CSV</span>
    </a>
    <?php
}

// Start the layout
layoutHeader();
?>

<!-- Statistics -->
<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-title">Total Logs</div>
        <div class="metric-value"><?php echo number_format($stats['total']); ?></div>
        <div class="metric-subtitle">All time</div>
    </div>
    <div class="metric-card">
        <div class="metric-title">Unique Admins</div>
        <div class="metric-value"><?php echo number_format($stats['unique_admins']); ?></div>
        <div class="metric-subtitle">Active</div>
    </div>
    <div class="metric-card">
        <div class="metric-title">Successful Logins</div>
        <div class="metric-value"><?php echo number_format($stats['logins']); ?></div>
        <div class="metric-subtitle">Auth events</div>
    </div>
    <div class="metric-card">
        <div class="metric-title">Failed Logins</div>
        <div class="metric-value"><?php echo number_format($stats['failed_logins']); ?></div>
        <div class="metric-subtitle">Security alerts</div>
    </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
    <form method="GET" action="">
        <div class="filter-grid">
            <div class="filter-group">
                <label class="filter-label">Action Type</label>
                <select name="action_type" class="filter-select">
                    <option value="all" <?php echo $actionFilter === 'all' ? 'selected' : ''; ?>>All Actions</option>
                    <?php foreach ($actionTypes as $type): ?>
                        <option value="<?php echo e($type['action_type']); ?>" <?php echo $actionFilter === $type['action_type'] ? 'selected' : ''; ?>>
                            <?php echo e(str_replace('_', ' ', $type['action_type'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">From Date</label>
                <input type="date" name="date_from" class="filter-input" value="<?php echo e($dateFrom); ?>">
            </div>
            <div class="filter-group">
                <label class="filter-label">To Date</label>
                <input type="date" name="date_to" class="filter-input" value="<?php echo e($dateTo); ?>">
            </div>
            <div class="filter-group">
                <label class="filter-label">Admin</label>
                <select name="admin_id" class="filter-select">
                    <option value="">All Admins</option>
                    <?php foreach ($admins as $adm): ?>
                        <option value="<?php echo $adm['admin_id']; ?>" <?php echo $adminFilter == $adm['admin_id'] ? 'selected' : ''; ?>>
                            <?php echo e($adm['first_name'] . ' ' . $adm['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-search"></i>
                    <span class="btn-text">Filter</span>
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Audit Logs -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">All Audit Logs</h2>
        <span style="color: var(--text-secondary); font-size: var(--type-body-sm);"><?php echo number_format($totalCount); ?> total records</span>
    </div>
    <div class="card-body">
        <?php if (empty($logs)): ?>
            <p style="color: var(--text-muted); text-align: center; padding: var(--space-6);">No audit logs found matching your criteria.</p>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Action</th>
                            <th>Admin</th>
                            <th>Target</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): 
                            $iconMap = [
                                'LOGIN_SUCCESS' => 'sign-in-alt',
                                'LOGIN_FAILED' => 'sign-in-alt',
                                'PROJECT_CREATE' => 'plus-circle',
                                'PROJECT_UPDATE' => 'edit',
                                'PROJECT_DELETE' => 'trash',
                                'USER_DELETE' => 'user-times',
                                'ADMIN_REVOKE' => 'user-times',
                                'ADMIN_GRANT' => 'user-plus',
                            ];
                            $icon = $iconMap[$log['action_type']] ?? 'shield-alt';
                            $statusClass = 'status-info';
                            if (strpos($log['action_type'], 'SUCCESS') !== false || strpos($log['action_type'], 'CREATE') !== false) {
                                $statusClass = 'status-success';
                            } elseif (strpos($log['action_type'], 'DELETE') !== false || strpos($log['action_type'], 'REVOKE') !== false) {
                                $statusClass = 'status-danger';
                            } elseif (strpos($log['action_type'], 'FAILED') !== false) {
                                $statusClass = 'status-danger';
                            }
                        ?>
                            <tr>
                                <td>
                                    <div class="log-time">
                                        <i class="fas fa-clock"></i>
                                        <?php echo formatDate($log['created_at'], 'M d, Y H:i'); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: var(--space-3);">
                                        <div class="log-icon <?php echo $statusClass; ?>">
                                            <i class="fas fa-<?php echo $icon; ?>"></i>
                                        </div>
                                        <div>
                                            <div class="log-action"><?php echo str_replace('_', ' ', $log['action_type']); ?></div>
                                            <?php if ($log['target_name']): ?>
                                                <small style="color: var(--text-secondary);"><?php echo e($log['target_name']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($log['admin_first_name']): ?>
                                        <strong><?php echo e($log['admin_first_name'] . ' ' . $log['admin_last_name']); ?></strong><br>
                                        <small style="color: var(--text-secondary);"><?php echo e($log['admin_email']); ?></small>
                                    <?php else: ?>
                                        <small style="color: var(--text-muted);">System</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($log['changes']): ?>
                                        <small style="color: var(--text-secondary);"><?php echo e(substr(json_encode($log['changes']), 0, 60)); ?><?php echo strlen(json_encode($log['changes'])) > 60 ? '...' : ''; ?></small>
                                    <?php else: ?>
                                        <small>
                                            <?php echo e($log['target_type'] ?? 'System'); ?> (ID: <?php echo e($log['target_id'] ?? 'N/A'); ?>)
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($log['ip_address']): ?>
                                        <small class="log-ip"><i class="fas fa-globe"></i> <?php echo e($log['ip_address']); ?></small>
                                    <?php else: ?>
                                        <small style="color: var(--text-muted);">-</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <button class="page-btn" onclick="goToPage(<?php echo $page - 1; ?>)" <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                    <i class="fas fa-chevron-left"></i>
                </button>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i <= 5 || $i > $totalPages - 4 || abs($i - $page) <= 2): ?>
                        <button class="page-btn <?php echo $i === $page ? 'active' : ''; ?>" onclick="goToPage(<?php echo $i; ?>)">
                            <?php echo $i; ?>
                        </button>
                    <?php elseif (abs($i - $page) <= 2): ?>
                        <button class="page-btn" disabled>...</button>
                    <?php endif; ?>
                <?php endfor; ?>
                <button class="page-btn" onclick="goToPage(<?php echo $page + 1; ?>)" <?php echo $page >= $totalPages ? 'disabled' : ''; ?>>
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Define custom scripts for this page
function layoutCustomScripts() {
    ?>
    <script>
        function goToPage(page) {
            const params = new URLSearchParams(window.location.search);
            params.set('page', page);
            window.location.search = params.toString();
        }
    </script>
    <?php
}

// End the layout
layoutFooter();
?>
