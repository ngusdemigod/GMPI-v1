<?php
/**
 * Partners Page - Active Recurring Contributors
 * Church Financial Partnership System
 */

require_once __DIR__ . '/includes/config.php';

// Require authentication
$admin = requireAdmin();

// Set page variables for layout
$pageTitle = 'Active Partners';
$pageSubtitle = 'View and manage recurring payment contributors';
$activePage = 'partners';

$db = Database::getInstance();

// Get filter parameters
$frequencyFilter = $_GET['frequency'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$minAmount = $_GET['min_amount'] ?? null;
$page = intval($_GET['page'] ?? 1);
$perPage = ITEMS_PER_PAGE;

// Build query
$whereClauses = ["p.is_active = TRUE"];
$params = [];

if ($frequencyFilter !== 'all') {
    $whereClauses[] = "p.frequency = :frequency";
    $params['frequency'] = $frequencyFilter;
}

if ($statusFilter === 'active') {
    $whereClauses[] = "(p.remaining_amount > 0 AND (p.end_date IS NULL OR p.end_date > NOW()))";
} elseif ($statusFilter === 'completed') {
    $whereClauses[] = "(p.remaining_amount = 0 OR p.end_date < NOW())";
}

if ($minAmount !== null && $minAmount > 0) {
    $whereClauses[] = "p.total_amount >= :min_amount";
    $params['min_amount'] = floatval($minAmount);
}

$whereSql = implode(' AND ', $whereClauses);

// Get total count
$totalCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM pledges p
     INNER JOIN users u ON p.user_id = u.user_id
     WHERE $whereSql",
    $params
)['count'];

$totalPages = ceil($totalCount / $perPage);
$offset = ($page - 1) * $perPage;

// Get partners with their pledges
$partners = $db->fetchAll(
    "SELECT p.*, u.email, u.first_name, u.last_name, u.phone,
            (SELECT COUNT(*) FROM transactions t WHERE t.user_id = p.user_id AND t.status = 'completed') as total_transactions,
            (SELECT COALESCE(SUM(amount), 0) FROM transactions t WHERE t.user_id = p.user_id AND t.status = 'completed') as total_given
     FROM pledges p
     INNER JOIN users u ON p.user_id = u.user_id
     WHERE $whereSql
     ORDER BY p.created_at DESC
     LIMIT :limit OFFSET :offset",
    array_merge($params, ['limit' => $perPage, 'offset' => $offset])
);

// Get statistics
$stats = $db->fetchOne(
    "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN p.remaining_amount > 0 AND (p.end_date IS NULL OR p.end_date > NOW()) THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN p.remaining_amount = 0 OR p.end_date < NOW() THEN 1 ELSE 0 END) as completed,
        COALESCE(SUM(p.total_amount), 0) as total_commitment,
        COALESCE(SUM(p.remaining_amount), 0) as remaining_commitment
     FROM pledges p
     INNER JOIN users u ON p.user_id = u.user_id
     WHERE $whereSql",
    $params
);

// Get top contributors
$topContributors = $db->fetchAll(
    "SELECT 
            MIN(u.user_id) as user_id,
            MAX(u.email) as email,
            MAX(u.first_name) as first_name,
            MAX(u.last_name) as last_name,
            MAX(COALESCE(tx.total_given, 0)) as total_given,
            MAX(COALESCE(ap.active_pledges, 0)) as active_pledges
     FROM users u
     LEFT JOIN (
        SELECT user_id, COALESCE(SUM(amount), 0) as total_given
        FROM transactions
        WHERE status = 'completed'
        GROUP BY user_id
     ) tx ON tx.user_id = u.user_id
     LEFT JOIN (
        SELECT user_id, COUNT(*) as active_pledges
        FROM pledges
        WHERE is_active = TRUE
        GROUP BY user_id
     ) ap ON ap.user_id = u.user_id
     WHERE u.is_active = TRUE
     GROUP BY LOWER(TRIM(u.email))
     ORDER BY total_given DESC, user_id ASC
     LIMIT 10"
);

// Get upcoming renewals (next 30 days)
$upcomingRenewals = $db->fetchAll(
    "SELECT p.*, u.first_name, u.last_name
     FROM pledges p
     INNER JOIN users u ON p.user_id = u.user_id
     WHERE p.is_active = TRUE
     AND p.end_date IS NOT NULL
     AND p.end_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)
     ORDER BY p.end_date ASC
     LIMIT 10"
);

$partnerDirectory = array_map(
    static function (array $partner): array {
        return [
            'user_id' => (int) ($partner['user_id'] ?? 0),
            'name' => trim((string) (($partner['first_name'] ?? '') . ' ' . ($partner['last_name'] ?? ''))),
            'email' => (string) ($partner['email'] ?? ''),
            'phone' => (string) (($partner['phone'] ?? '') ?: 'No phone on file'),
            'frequency' => (string) ($partner['frequency'] ?? ''),
            'start_date' => !empty($partner['start_date']) ? formatDate($partner['start_date']) : 'N/A',
            'end_date' => !empty($partner['end_date']) ? formatDate($partner['end_date']) : 'Open-ended commitment',
            'total_pledge' => formatCurrency((float) ($partner['total_amount'] ?? 0)),
            'remaining' => formatCurrency((float) ($partner['remaining_amount'] ?? 0)),
            'transactions' => number_format((int) ($partner['total_transactions'] ?? 0)),
            'total_given' => formatCurrency((float) ($partner['total_given'] ?? 0)),
        ];
    },
    $partners
);

function partnersPageUrl(array $overrides = []): string
{
    $query = $_GET;
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
            continue;
        }
        $query[$key] = $value;
    }

    unset($query['page']);

    $built = http_build_query($query);
    return '?' . $built;
}

// Include layout
require_once __DIR__ . '/includes/layout.php';

// Define custom styles for this page
function layoutCustomStyles() {
    ?>
    <style>
        .partners-layout-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.72fr) minmax(320px, 0.88fr);
            gap: var(--space-6);
            align-items: start;
        }

        .partners-main {
            display: grid;
            gap: var(--space-6);
            min-width: 0;
        }

        .partners-toolbar {
            display: grid;
            gap: 16px;
        }

        .partners-toolbar-actions {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(180px, 220px) auto auto;
            gap: 14px;
            align-items: end;
        }

        .partner-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: var(--type-body-xs);
            font-weight: 600;
        }
        
        .badge-weekly {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
        }
        
        .badge-monthly {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }
        
        .badge-annually {
            background: rgba(212, 175, 55, 0.1);
            color: var(--gold-dark);
        }
        
        .badge-one-time {
            background: rgba(18, 33, 55, 0.08);
            color: var(--deep);
        }

        .partner-table-wrap {
            overflow-x: auto;
        }

        .partner-row {
            cursor: pointer;
        }

        .partner-row td {
            transition: background-color 0.2s ease;
        }

        .partner-row:hover td {
            background: rgba(250, 246, 239, 0.92);
        }

        .partner-primary {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .partner-avatar {
            width: 44px;
            height: 44px;
            border-radius: 16px;
            background: linear-gradient(145deg, var(--gold), #e2c57d);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            box-shadow: inset 0 -8px 14px rgba(0, 0, 0, 0.08);
            flex-shrink: 0;
        }

        .partner-name {
            font-size: 15px;
            font-weight: 600;
            color: var(--dark);
        }

        .partner-email {
            font-size: var(--type-body-xs);
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .partner-inline-meta {
            font-size: var(--type-body-xs);
            color: var(--text-secondary);
        }

        .partner-amount {
            font-weight: 700;
            color: var(--text-primary);
            white-space: nowrap;
        }

        .partner-success {
            color: var(--success);
        }

        .partner-sheet {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            width: min(420px, 92vw);
            padding: 18px;
            background: rgba(10, 17, 31, 0.44);
            backdrop-filter: blur(6px);
            display: flex;
            justify-content: flex-end;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            z-index: 1200;
        }

        .partner-sheet.is-open {
            opacity: 1;
            pointer-events: auto;
        }

        .partner-sheet-panel {
            width: 100%;
            height: 100%;
            border-radius: 28px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(250, 246, 239, 0.98));
            border: 1px solid rgba(10, 17, 31, 0.08);
            box-shadow: 0 22px 60px rgba(10, 17, 31, 0.22);
            padding: 22px;
            transform: translateX(18px);
            transition: transform 0.2s ease;
            overflow-y: auto;
        }

        .partner-sheet.is-open .partner-sheet-panel {
            transform: translateX(0);
        }

        .sheet-close {
            width: 40px;
            height: 40px;
            border-radius: 14px;
            border: 1px solid rgba(10, 17, 31, 0.08);
            background: rgba(10, 17, 31, 0.03);
            color: var(--text-primary);
            cursor: pointer;
        }

        .sheet-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 22px;
        }

        .sheet-persona {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .sheet-avatar {
            width: 58px;
            height: 58px;
            border-radius: 20px;
            background: linear-gradient(145deg, var(--gold), #e2c57d);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-heading);
            font-size: 22px;
            font-weight: 700;
        }

        .sheet-name {
            font-family: var(--font-heading);
            font-size: 24px;
            font-weight: 600;
        }

        .sheet-email {
            color: var(--text-secondary);
            margin-top: 6px;
            font-size: var(--type-body-sm);
        }

        .sheet-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 18px;
        }

        .sheet-stat {
            padding: 16px;
            border-radius: 22px;
            background: rgba(10, 17, 31, 0.04);
        }

        .sheet-stat-label {
            font-size: var(--type-body-xs-small);
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--text-tertiary);
            margin-bottom: 8px;
        }

        .sheet-stat-value {
            font-family: var(--font-heading);
            font-size: 22px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .sheet-footnote {
            margin-top: 20px;
            padding: 16px 18px;
            border-radius: 22px;
            background: rgba(201, 162, 75, 0.08);
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .leaderboard-hero {
            background:
                radial-gradient(circle at top right, rgba(201, 162, 75, 0.18), transparent 34%),
                linear-gradient(180deg, #1a2740 0%, #0f1828 100%);
            border: 1px solid rgba(255, 255, 255, 0.06);
            color: #f4efe4;
            position: sticky;
            top: 16px;
        }

        .leaderboard-hero .soft-panel-title,
        .leaderboard-hero .subtle-kicker,
        .leaderboard-hero .contributor-name,
        .leaderboard-hero .contributor-amount {
            color: #fff7e0;
        }

        .leaderboard-hero .soft-panel-title {
            font-family: var(--font-heading);
            font-style: normal;
        }

        .leaderboard-hero .subtle-kicker {
            color: rgba(255, 244, 214, 0.68);
        }

        .leaderboard-hero .contributor-meta {
            color: rgba(255, 255, 255, 0.64);
        }

        .leaderboard-hero .top-contributor-item {
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .top-contributor-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid rgba(10, 17, 31, 0.06);
        }

        .top-contributor-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .contributor-name {
            font-size: var(--type-body-sm);
            font-weight: 600;
            color: var(--text-primary);
            font-family: var(--font-heading);
            font-style: normal;
        }

        .contributor-meta {
            color: var(--text-secondary);
            font-size: var(--type-body-xs);
            margin-top: 4px;
        }

        .contributor-rank {
            min-width: 42px;
            font-family: var(--font-heading);
            font-style: italic;
            font-size: 28px;
            line-height: 1;
            color: var(--gold-light);
            flex-shrink: 0;
        }

        .contributor-copy {
            flex: 1;
            min-width: 0;
        }

        .contributor-amount {
            font-family: var(--font-heading);
            font-style: normal;
            font-weight: 700;
            color: #f3d58c;
            text-align: right;
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
            border: 1px solid rgba(0,0,0,0.1);
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
            .partners-layout-grid {
                grid-template-columns: 1fr;
            }

            .partners-toolbar-actions {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .partners-toolbar-actions {
                grid-template-columns: 1fr;
            }

            .pagination { flex-wrap: wrap; }

            .sheet-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <?php
}

// Define page actions for layout
function layoutPageActions() {
    ?>
    <a href="export.php?type=partners" class="btn btn-secondary">
        <i class="fas fa-download"></i>
        <span class="btn-text">Export CSV</span>
    </a>
    <?php
}

// Start the layout
layoutHeader();
?>

<?php
$frequencyOptions = [
    'all' => 'All',
    'Weekly' => 'Weekly',
    'Monthly' => 'Monthly',
    'Annually' => 'Annually',
];
$statusOptions = [
    'all' => 'All statuses',
    'active' => 'Active',
    'completed' => 'Completed',
];
?>

<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-title">Total Partners</div>
        <div class="metric-value"><?php echo number_format($stats['total']); ?></div>
        <div class="metric-subtitle">Recurring contributors in view</div>
    </div>
    <div class="metric-card">
        <div class="metric-title">Active Commitments</div>
        <div class="metric-value"><?php echo number_format($stats['active']); ?></div>
        <div class="metric-subtitle"><?php echo number_format(count($upcomingRenewals)); ?> ending in the next 30 days</div>
    </div>
    <div class="metric-card">
        <div class="metric-title">Total Commitment</div>
        <div class="metric-value"><?php echo formatMetricCurrency($stats['total_commitment']); ?></div>
        <div class="metric-subtitle">Committed across active plans</div>
    </div>
    <div class="metric-card">
        <div class="metric-title">Remaining</div>
        <div class="metric-value"><?php echo formatMetricCurrency($stats['remaining_commitment']); ?></div>
        <div class="metric-subtitle">Still expected from partners</div>
    </div>
</div>

<div class="filter-chip-strip">
    <div class="soft-field">
        <label class="soft-field-label">Status</label>
        <div class="chip-row">
            <?php foreach ($statusOptions as $statusValue => $statusLabel): ?>
                <a href="<?php echo e(partnersPageUrl(['status' => $statusValue])); ?>" class="chip <?php echo $statusFilter === $statusValue ? 'is-dark' : ''; ?>">
                    <?php echo e($statusLabel); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="partners-layout-grid">
    <div class="partners-main">
        <div class="soft-toolbar">
            <form method="GET" action="">
                <div class="partners-toolbar">
                    <div class="partners-toolbar-actions">
                        <input type="hidden" name="frequency" value="<?php echo e($frequencyFilter); ?>">
                        <input type="hidden" name="status" value="<?php echo e($statusFilter); ?>">
                        <div class="soft-field">
                            <label class="soft-field-label">Frequency</label>
                            <div class="chip-row">
                                <?php foreach ($frequencyOptions as $frequencyValue => $frequencyLabel): ?>
                                    <a href="<?php echo e(partnersPageUrl(['frequency' => $frequencyValue])); ?>" class="chip <?php echo $frequencyFilter === $frequencyValue ? 'is-active' : ''; ?>">
                                        <?php echo e($frequencyLabel); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="soft-field">
                            <label class="soft-field-label">Minimum amount</label>
                            <input type="number" name="min_amount" class="soft-input" placeholder="0.00" value="<?php echo e($minAmount); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sliders"></i>
                            <span class="btn-text">Apply</span>
                        </button>
                        <a href="partners.php" class="toolbar-reset">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-shell">
            <div class="table-shell-header">
                <div>
                    <div class="subtle-kicker">Recurring giving</div>
                    <div class="soft-panel-title">Active Partners</div>
                    <div class="soft-panel-subtitle">Review recurring commitments in a table and open full partner details from any row.</div>
                </div>
                <span class="soft-meta-pill"><?php echo number_format($totalCount); ?> records</span>
            </div>
            <div class="table-shell-body">
                <?php if (empty($partners)): ?>
                    <div class="empty-soft">
                        <div class="empty-soft-icon"><i class="fas fa-users"></i></div>
                        <div class="soft-panel-title" style="font-size: 22px; margin-bottom: 8px;">No partners found</div>
                        <p>Adjust the filters to broaden the recurring partner list.</p>
                    </div>
                <?php else: ?>
                    <div class="partner-table-wrap">
                        <table class="soft-table">
                            <thead>
                                <tr>
                                    <th>Partner</th>
                                    <th>Frequency</th>
                                    <th>Total pledge</th>
                                    <th>Remaining</th>
                                    <th>Transactions</th>
                                    <th>Given so far</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($partners as $partner): ?>
                                    <?php $badgeClass = 'badge-' . strtolower((string) $partner['frequency']); ?>
                                    <tr class="partner-row" onclick="openPartnerProfile(<?php echo (int) $partner['user_id']; ?>)">
                                        <td>
                                            <div class="partner-primary">
                                                <div class="partner-avatar">
                                                    <?php echo strtoupper(substr((string) $partner['first_name'], 0, 1) . substr((string) $partner['last_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="partner-name"><?php echo e(trim($partner['first_name'] . ' ' . $partner['last_name'])); ?></div>
                                                    <div class="partner-email"><?php echo e($partner['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="partner-badge <?php echo $badgeClass; ?>">
                                                <?php echo e($partner['frequency']); ?>
                                            </span>
                                        </td>
                                        <td><span class="partner-amount"><?php echo formatCurrency((float) $partner['total_amount']); ?></span></td>
                                        <td><span class="partner-inline-meta"><?php echo formatCurrency((float) $partner['remaining_amount']); ?></span></td>
                                        <td><span class="partner-inline-meta"><?php echo number_format((int) $partner['total_transactions']); ?></span></td>
                                        <td><span class="partner-amount partner-success"><?php echo formatCurrency((float) $partner['total_given']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

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
    </div>

    <aside class="soft-panel leaderboard-hero">
        <div class="soft-panel-header">
            <div>
                <div class="subtle-kicker">Leaderboard</div>
                <div class="soft-panel-title">Top Contributors</div>
            </div>
            <span class="soft-meta-pill" style="background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.78);">Top 10</span>
        </div>
        <div class="soft-panel-body">
            <?php if (empty($topContributors)): ?>
                <div class="empty-soft" style="background: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.08); color: rgba(255,255,255,0.82);">
                    <div class="empty-soft-icon" style="background: rgba(255,255,255,0.08); color: var(--gold);"><i class="fas fa-trophy"></i></div>
                    <div class="soft-panel-title" style="font-size: 22px; margin-bottom: 8px; color: #fff;">No contributors yet</div>
                    <p style="color: rgba(255,255,255,0.72);">Top partner activity will appear here when recurring giving starts landing.</p>
                </div>
            <?php else: ?>
                <?php foreach ($topContributors as $index => $contributor): ?>
                    <div class="top-contributor-item">
                        <div class="contributor-rank"><?php echo str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT); ?></div>
                        <div class="contributor-copy">
                            <div class="contributor-name"><?php echo e(trim($contributor['first_name'] . ' ' . $contributor['last_name'])); ?></div>
                            <div class="contributor-meta"><?php echo e($contributor['email']); ?></div>
                        </div>
                        <div>
                            <div class="contributor-amount"><?php echo formatCurrency((float) $contributor['total_given']); ?></div>
                            <div class="contributor-meta"><?php echo (int) $contributor['active_pledges']; ?> active pledge(s)</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>
</div>

<div class="partner-sheet" id="partnerSheet" aria-hidden="true">
    <div class="partner-sheet-panel">
        <div class="sheet-header">
            <div class="sheet-persona">
                <div class="sheet-avatar" id="sheetAvatar">--</div>
                <div>
                    <div class="sheet-name" id="sheetName">Partner name</div>
                    <div class="sheet-email" id="sheetEmail">email@example.com</div>
                </div>
            </div>
            <button type="button" class="sheet-close" onclick="closePartnerProfile()" aria-label="Close profile">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="sheet-grid">
            <div class="sheet-stat">
                <div class="sheet-stat-label">Frequency</div>
                <div class="sheet-stat-value" id="sheetFrequency">-</div>
            </div>
            <div class="sheet-stat">
                <div class="sheet-stat-label">Phone</div>
                <div class="sheet-stat-value" id="sheetPhone">-</div>
            </div>
            <div class="sheet-stat">
                <div class="sheet-stat-label">Total pledge</div>
                <div class="sheet-stat-value" id="sheetTotalPledge">-</div>
            </div>
            <div class="sheet-stat">
                <div class="sheet-stat-label">Remaining</div>
                <div class="sheet-stat-value" id="sheetRemaining">-</div>
            </div>
            <div class="sheet-stat">
                <div class="sheet-stat-label">Transactions</div>
                <div class="sheet-stat-value" id="sheetTransactions">-</div>
            </div>
            <div class="sheet-stat">
                <div class="sheet-stat-label">Given so far</div>
                <div class="sheet-stat-value" id="sheetTotalGiven">-</div>
            </div>
        </div>

        <div class="sheet-footnote">
            <strong id="sheetStartDate">Started -</strong><br>
            <span id="sheetEndDate">Ends -</span>
        </div>
    </div>
</div>

<?php
// Define custom scripts for this page
function layoutCustomScripts() {
    global $partnerDirectory;
    ?>
    <script>
        const partnerDirectory = <?php echo json_encode($partnerDirectory); ?>;

        function goToPage(page) {
            const params = new URLSearchParams(window.location.search);
            params.set('page', page);
            window.location.search = params.toString();
        }

        function openPartnerProfile(userId) {
            const partner = partnerDirectory.find((entry) => Number(entry.user_id) === Number(userId));
            if (!partner) return;

            const initials = (partner.name || '')
                .split(' ')
                .filter(Boolean)
                .map((part) => part.charAt(0))
                .join('')
                .slice(0, 2)
                .toUpperCase();

            document.getElementById('sheetAvatar').textContent = initials || '--';
            document.getElementById('sheetName').textContent = partner.name || 'Unnamed partner';
            document.getElementById('sheetEmail').textContent = partner.email || 'No email on file';
            document.getElementById('sheetFrequency').textContent = partner.frequency || '-';
            document.getElementById('sheetPhone').textContent = partner.phone || '-';
            document.getElementById('sheetTotalPledge').textContent = partner.total_pledge || '-';
            document.getElementById('sheetRemaining').textContent = partner.remaining || '-';
            document.getElementById('sheetTransactions').textContent = partner.transactions || '0';
            document.getElementById('sheetTotalGiven').textContent = partner.total_given || '-';
            document.getElementById('sheetStartDate').textContent = 'Started ' + (partner.start_date || '-');
            document.getElementById('sheetEndDate').textContent = partner.end_date === 'Open-ended commitment'
                ? partner.end_date
                : 'Ends ' + (partner.end_date || '-');

            const sheet = document.getElementById('partnerSheet');
            sheet.classList.add('is-open');
            sheet.setAttribute('aria-hidden', 'false');
        }

        function closePartnerProfile() {
            const sheet = document.getElementById('partnerSheet');
            sheet.classList.remove('is-open');
            sheet.setAttribute('aria-hidden', 'true');
        }

        document.getElementById('partnerSheet')?.addEventListener('click', function (event) {
            if (event.target === this) closePartnerProfile();
        });
    </script>
    <?php
}

// End the layout
layoutFooter();
?>


