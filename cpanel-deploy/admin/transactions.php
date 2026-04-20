<?php
/**
 * Transaction Management Page
 * Church Financial Partnership System
 */

require_once __DIR__ . '/includes/config.php';

// Require authentication
$admin = requireAdmin();

// Set page variables for layout
$pageTitle = 'Transactions';
$pageSubtitle = 'View and manage all financial transactions';
$activePage = 'transactions';

$db = Database::getInstance();

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? null;
$dateTo = $_GET['date_to'] ?? null;
$userFilter = $_GET['user_id'] ?? null;
$projectFilter = $_GET['project_id'] ?? null;
$search = trim($_GET['search'] ?? '');
$page = intval($_GET['page'] ?? 1);
$perPage = ITEMS_PER_PAGE;

// Build query
$whereClauses = ["t.status != 'refunded'"];
$params = [];

if ($statusFilter !== 'all') {
    $whereClauses[] = "t.status = :status";
    $params['status'] = $statusFilter;
}

if ($dateFrom) {
    $whereClauses[] = "t.transaction_date >= :date_from";
    $params['date_from'] = $dateFrom . ' 00:00:00';
}

if ($dateTo) {
    $whereClauses[] = "t.transaction_date <= :date_to";
    $params['date_to'] = $dateTo . ' 23:59:59';
}

if ($userFilter) {
    $whereClauses[] = "t.user_id = :user_id";
    $params['user_id'] = $userFilter;
}

if ($projectFilter) {
    $whereClauses[] = "t.project_id = :project_id";
    $params['project_id'] = $projectFilter;
}

if (!empty($search)) {
    $whereClauses[] = "(t.transaction_reference LIKE :search OR u.email LIKE :search2 OR u.first_name LIKE :search3 OR u.last_name LIKE :search4)";
    $searchParam = '%' . $search . '%';
    $params['search'] = $searchParam;
    $params['search2'] = $searchParam;
    $params['search3'] = $searchParam;
    $params['search4'] = $searchParam;
}

$whereSql = implode(' AND ', $whereClauses);

// Get total count
$totalCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM transactions t
     INNER JOIN users u ON t.user_id = u.user_id
     WHERE $whereSql",
    $params
)['count'];

$totalPages = ceil($totalCount / $perPage);
$offset = ($page - 1) * $perPage;

// Get transactions
$transactions = $db->fetchAll(
    "SELECT t.*, u.first_name, u.last_name, u.email, p.title as project_title, pm.card_type, pm.last_four_digits
     FROM transactions t
     INNER JOIN users u ON t.user_id = u.user_id
     LEFT JOIN projects p ON t.project_id = p.project_id
     LEFT JOIN payment_methods pm ON t.payment_method_id = pm.payment_method_id
     WHERE $whereSql
     ORDER BY t.transaction_date DESC
     LIMIT :limit OFFSET :offset",
    array_merge($params, ['limit' => $perPage, 'offset' => $offset])
);

// Get statistics
$stats = $db->fetchOne(
    "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN t.status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN t.status = 'failed' THEN 1 ELSE 0 END) as failed,
        COALESCE(SUM(CASE WHEN t.status = 'completed' THEN t.amount ELSE 0 END), 0) as total_amount
     FROM transactions t
     WHERE $whereSql",
    $params
);

// Get unique users count
$uniqueUsers = $db->fetchOne(
    "SELECT COUNT(DISTINCT t.user_id) as count FROM transactions t
     WHERE $whereSql",
    $params
)['count'];

// Get active projects for filter
$activeProjects = $db->fetchAll("SELECT project_id, title FROM projects WHERE is_active = TRUE ORDER BY title");

// Get date range stats
$monthlyStats = $db->fetchOne(
    "SELECT 
        COALESCE(SUM(amount), 0) as monthly_total,
        COUNT(*) as monthly_count
     FROM transactions 
     WHERE MONTH(transaction_date) = MONTH(NOW()) 
     AND YEAR(transaction_date) = YEAR(NOW()) 
     AND status = 'completed'"
);

$yearlyStats = $db->fetchOne(
    "SELECT 
        COALESCE(SUM(amount), 0) as yearly_total,
        COUNT(*) as yearly_count
     FROM transactions 
     WHERE YEAR(transaction_date) = YEAR(NOW()) 
     AND status = 'completed'"
);

function transactionsPageUrl(array $overrides = []): string
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
        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: var(--type-body-xs);
            font-weight: 600;
        }
        
        .status-completed { background: rgba(40, 167, 69, 0.1); color: var(--success); }
        .status-pending { background: rgba(255, 193, 7, 0.1); color: #b7950b; }
        .status-failed { background: rgba(220, 53, 69, 0.1); color: var(--danger); }

        .transactions-toolbar {
            display: grid;
            gap: 16px;
        }

        .transactions-toolbar-grid {
            display: grid;
            grid-template-columns: minmax(220px, 1.4fr) repeat(2, minmax(150px, 0.75fr)) minmax(180px, 1fr) auto auto;
            gap: 14px;
            align-items: end;
        }

        .table-scroll {
            overflow-x: auto;
        }

        .transaction-row {
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.2s ease;
        }

        .transaction-row:hover,
        .transaction-row:focus-visible {
            background: rgba(249, 245, 236, 0.92);
            outline: none;
        }

        .transaction-row:focus-visible {
            box-shadow: inset 0 0 0 2px rgba(201, 162, 75, 0.45);
        }

        .transaction-main {
            font-size: var(--type-body-sm);
            font-weight: 600;
            color: var(--text-primary);
        }

        .transaction-sub {
            font-size: var(--type-body-xs);
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .transaction-ref {
            display: inline-flex;
            padding: 8px 10px;
            border-radius: 12px;
            background: rgba(10, 17, 31, 0.04);
            font-family: var(--font-mono);
            font-size: 12px;
            color: var(--text-primary);
        }

        .transaction-amount {
            font-weight: 700;
            color: var(--success);
            white-space: nowrap;
        }

        .payment-method-cell {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 132px;
        }

        .payment-method-main {
            font-size: var(--type-body-xs);
            color: var(--text-primary);
            font-weight: 600;
        }

        .payment-method-sub {
            font-size: var(--type-body-xs-small);
            color: var(--text-muted);
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
            .transactions-toolbar-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .transactions-toolbar-grid {
                grid-template-columns: 1fr;
            }

            .chip-row {
                width: 100%;
            }

            .chip {
                flex: 1 1 calc(50% - 10px);
            }

            .pagination { flex-wrap: wrap; }
        }
    </style>
    <?php
}

// Define page actions for layout
function layoutPageActions() {
    ?>
    <a href="export.php?type=transactions" class="btn btn-secondary">
        <i class="fas fa-download"></i>
        <span class="btn-text">Export CSV</span>
    </a>
    <?php
}

// Start the layout
layoutHeader();
?>

<?php
$statusOptions = [
    'all' => 'All',
    'completed' => 'Completed',
    'pending' => 'Pending',
    'failed' => 'Failed',
];
?>

<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-title">Total Transactions</div>
        <div class="metric-value"><?php echo number_format($stats['total']); ?></div>
        <div class="metric-subtitle">All recorded payments</div>
    </div>
    <div class="metric-card">
        <div class="metric-title">Completed</div>
        <div class="metric-value"><?php echo number_format($stats['completed']); ?></div>
        <div class="metric-subtitle"><?php echo number_format((int) ($monthlyStats['monthly_count'] ?? 0)); ?> completed this month</div>
    </div>
    <div class="metric-card">
        <div class="metric-title">Total Amount</div>
        <div class="metric-value"><?php echo formatMetricCurrency($stats['total_amount']); ?></div>
        <div class="metric-subtitle">Completed transaction revenue</div>
    </div>
    <div class="metric-card">
        <div class="metric-title">Unique Donors</div>
        <div class="metric-value"><?php echo number_format($uniqueUsers); ?></div>
        <div class="metric-subtitle"><?php echo formatCurrency((float) ($yearlyStats['yearly_total'] ?? 0)); ?> processed this year</div>
    </div>
</div>

<div class="filter-chip-strip">
    <div class="chip-row">
        <?php foreach ($statusOptions as $statusValue => $statusLabel): ?>
            <a href="<?php echo e(transactionsPageUrl(['status' => $statusValue])); ?>" class="chip <?php echo $statusFilter === $statusValue ? 'is-active' : ''; ?>">
                <?php echo e($statusLabel); ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="soft-toolbar">
    <form method="GET" action="">
        <div class="transactions-toolbar">
            <div class="transactions-toolbar-grid">
                <input type="hidden" name="status" value="<?php echo e($statusFilter); ?>">
                <div class="soft-field soft-toolbar-grow">
                    <label class="soft-field-label">Search</label>
                    <input type="search" name="search" class="soft-input" value="<?php echo e($search); ?>" placeholder="Search reference, donor name, or email">
                </div>
                <div class="soft-field">
                    <label class="soft-field-label">From date</label>
                    <input type="date" name="date_from" class="soft-input" value="<?php echo e($dateFrom); ?>">
                </div>
                <div class="soft-field">
                    <label class="soft-field-label">To date</label>
                    <input type="date" name="date_to" class="soft-input" value="<?php echo e($dateTo); ?>">
                </div>
                <div class="soft-field">
                    <label class="soft-field-label">Project</label>
                    <select name="project_id" class="soft-select">
                        <option value="">All Projects</option>
                        <?php foreach ($activeProjects as $proj): ?>
                            <option value="<?php echo $proj['project_id']; ?>" <?php echo $projectFilter == $proj['project_id'] ? 'selected' : ''; ?>>
                                <?php echo e($proj['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    <span class="btn-text">Apply</span>
                </button>
                <a href="transactions.php" class="toolbar-reset">Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="table-shell">
    <div class="table-shell-header">
        <div>
            <div class="soft-panel-title">All Transactions</div>
            <div class="soft-panel-subtitle">Track gifts, subscription charges, and reference-based payments in one view.</div>
        </div>
        <span class="soft-meta-pill"><?php echo number_format($totalCount); ?> records</span>
    </div>
    <div class="table-shell-body">
        <?php if (empty($transactions)): ?>
            <div class="empty-soft">
                <div class="empty-soft-icon"><i class="fas fa-file-invoice"></i></div>
                <div class="soft-panel-title" style="font-size: 22px; margin-bottom: 8px;">No transactions found</div>
                <p>Try adjusting your status, date range, or search query.</p>
            </div>
        <?php else: ?>
            <div class="table-scroll">
                <table class="soft-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Donor</th>
                            <th>Project</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr class="transaction-row" tabindex="0" role="button" data-receipt-url="api/receipt.php?ref=<?php echo rawurlencode($transaction['transaction_reference']); ?>" aria-label="Open receipt for <?php echo e($transaction['transaction_reference']); ?>">
                                <td>
                                    <div class="transaction-main" style="font-size: 13px;">
                                        <?php echo formatDate($transaction['transaction_date'], 'M d, Y'); ?>
                                    </div>
                                    <div class="transaction-sub">
                                        <?php echo date('H:i', strtotime($transaction['transaction_date'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="transaction-ref"><?php echo e($transaction['transaction_reference']); ?></span>
                                </td>
                                <td>
                                    <div class="transaction-main">
                                        <?php echo e($transaction['first_name'] . ' ' . $transaction['last_name']); ?>
                                    </div>
                                    <div class="transaction-sub">
                                        <?php echo e($transaction['email']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="transaction-main"><?php echo e($transaction['project_title'] ?? $transaction['category'] ?? 'N/A'); ?></div>
                                    <div class="transaction-sub"><?php echo ucfirst(e($transaction['status'])); ?> payment</div>
                                </td>
                                <td>
                                    <span class="transaction-amount"><?php echo formatCurrency($transaction['amount']); ?></span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $transaction['status']; ?>">
                                        <?php echo ucfirst($transaction['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="payment-method-cell">
                                        <?php if (!empty($transaction['card_type'])): ?>
                                            <span class="payment-method-main">
                                                <i class="fas fa-credit-card"></i>
                                                <?php echo e($transaction['card_type']); ?> •••• <?php echo e($transaction['last_four_digits'] ?: '****'); ?>
                                            </span>
                                            <span class="payment-method-sub">Saved card</span>
                                        <?php elseif (!empty($transaction['payment_method_id'])): ?>
                                            <span class="payment-method-main">
                                                <i class="fas fa-credit-card"></i>
                                                Saved payment method
                                            </span>
                                            <span class="payment-method-sub">Method ID #<?php echo (int) $transaction['payment_method_id']; ?></span>
                                        <?php else: ?>
                                            <span class="payment-method-main">
                                                <i class="fas fa-bolt"></i>
                                                Online payment
                                            </span>
                                            <span class="payment-method-sub">Reference-based transaction</span>
                                        <?php endif; ?>
                                    </div>
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
                    <?php if ($i <= 3 || $i > $totalPages - 2 || abs($i - $page) <= 1): ?>
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
        // Debounce function for search
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        // Real-time search functionality
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            const debouncedSearch = debounce(function() {
                const params = new URLSearchParams(window.location.search);
                params.set('search', searchInput.value.trim());
                params.delete('page'); // Reset to page 1 on search
                window.location.search = params.toString();
            }, 500);
            
            searchInput.addEventListener('input', function() {
                debouncedSearch();
            });
        }
        
        // Real-time date filter functionality
        const dateFromInput = document.querySelector('input[name="date_from"]');
        const dateToInput = document.querySelector('input[name="date_to"]');
        
        if (dateFromInput) {
            dateFromInput.addEventListener('change', function() {
                const params = new URLSearchParams(window.location.search);
                params.set('date_from', this.value);
                params.delete('page');
                window.location.search = params.toString();
            });
        }
        
        if (dateToInput) {
            dateToInput.addEventListener('change', function() {
                const params = new URLSearchParams(window.location.search);
                params.set('date_to', this.value);
                params.delete('page');
                window.location.search = params.toString();
            });
        }
        
        // Real-time project filter functionality
        const projectSelect = document.querySelector('select[name="project_id"]');
        if (projectSelect) {
            projectSelect.addEventListener('change', function() {
                const params = new URLSearchParams(window.location.search);
                params.set('project_id', this.value);
                params.delete('page');
                window.location.search = params.toString();
            });
        }
        
        // Status filter click handler
        document.querySelectorAll('.filter-chip-strip .chip').forEach(chip => {
            chip.addEventListener('click', function(e) {
                e.preventDefault();
                const params = new URLSearchParams(window.location.search);
                params.set('status', this.getAttribute('href').split('status=')[1].split('&')[0]);
                params.delete('page');
                window.location.search = params.toString();
            });
        });

        document.querySelectorAll('.transaction-row').forEach((row) => {
            const openReceipt = function() {
                const receiptUrl = row.dataset.receiptUrl;
                if (!receiptUrl) {
                    return;
                }

                const receiptWindow = window.open(receiptUrl, '_blank', 'noopener');
                if (!receiptWindow) {
                    window.location.href = receiptUrl;
                }
            };

            row.addEventListener('click', function(event) {
                if (event.target.closest('a, button, input, select, textarea')) {
                    return;
                }

                openReceipt();
            });

            row.addEventListener('keydown', function(event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openReceipt();
                }
            });
        });
        
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
