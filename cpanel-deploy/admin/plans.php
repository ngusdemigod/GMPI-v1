<?php
/**
 * Payment Plans Management Page
 * Church Financial Partnership System
 * 
 * Two-column layout:
 * - Left: List of plans
 * - Right: Selected plan details with subscribers
 */

require_once __DIR__ . '/includes/config.php';

// Require authentication
$admin = requireAdmin();

// Set page variables for layout
$pageTitle = 'Payment Plans';
$pageSubtitle = 'Manage recurring payment plans and subscription tiers';
$activePage = 'plans';

$db = Database::getInstance();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    try {
        $action = $_POST['form_action'] ?? '';
        $planId = intval($_POST['plan_id'] ?? 0);
        
        if ($action === 'create' || $action === 'edit') {
            $name = sanitizeInput($_POST['name'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $amount = floatval($_POST['amount'] ?? 0);
            $minAmount = floatval($_POST['min_amount'] ?? 0);
            $maxAmount = $_POST['max_amount'] !== '' ? floatval($_POST['max_amount']) : null;
            $frequency = sanitizeInput($_POST['frequency'] ?? '');
            $paystackPlanCode = sanitizeInput($_POST['paystack_plan_code'] ?? '');
            $isActive = isset($_POST['is_active']) ? true : false;
            $isPaused = isset($_POST['is_paused']) ? true : false;
            $maxSubscriptions = $_POST['max_subscriptions'] !== '' ? intval($_POST['max_subscriptions']) : null;
            $displayOrder = intval($_POST['display_order'] ?? 0);
            $icon = sanitizeInput($_POST['icon'] ?? 'star');
            $colorCode = sanitizeInput($_POST['color_code'] ?? '#D4AF37');
            
            if (empty($name) || empty($frequency)) {
                throw new Exception('Plan name and frequency are required');
            }
            
            if ($action === 'create') {
                $db->execute(
                    "INSERT INTO payment_plans (name, description, amount, min_amount, max_amount, currency, frequency, paystack_plan_code, is_active, is_paused, max_subscriptions, display_order, icon, color_code)
                     VALUES (:name, :description, :amount, :min_amount, :max_amount, 'USD', :frequency, :paystack_plan_code, :is_active, :is_paused, :max_subscriptions, :display_order, :icon, :color_code)",
                    [
                        'name' => $name,
                        'description' => $description,
                        'amount' => $amount,
                        'min_amount' => $minAmount,
                        'max_amount' => $maxAmount,
                        'frequency' => $frequency,
                        'paystack_plan_code' => $paystackPlanCode,
                        'is_active' => $isActive,
                        'is_paused' => $isPaused,
                        'max_subscriptions' => $maxSubscriptions,
                        'display_order' => $displayOrder,
                        'icon' => $icon,
                        'color_code' => $colorCode
                    ]
                );
                
                $planId = $db->lastInsertId();
                logAdminAction($admin['admin_id'], 'PLAN_CREATE', 'plan', $planId, $name);
                
                $success = 'Payment plan created successfully';
            } else {
                $db->execute(
                    "UPDATE payment_plans 
                     SET name = :name, description = :description, amount = :amount, min_amount = :min_amount, max_amount = :max_amount, frequency = :frequency,
                         paystack_plan_code = :paystack_plan_code, is_active = :is_active, is_paused = :is_paused,
                         max_subscriptions = :max_subscriptions, display_order = :display_order, icon = :icon, color_code = :color_code
                     WHERE plan_id = :plan_id",
                    [
                        'name' => $name,
                        'description' => $description,
                        'amount' => $amount,
                        'min_amount' => $minAmount,
                        'max_amount' => $maxAmount,
                        'frequency' => $frequency,
                        'paystack_plan_code' => $paystackPlanCode,
                        'is_active' => $isActive,
                        'is_paused' => $isPaused,
                        'max_subscriptions' => $maxSubscriptions,
                        'display_order' => $displayOrder,
                        'icon' => $icon,
                        'color_code' => $colorCode,
                        'plan_id' => $planId
                    ]
                );
                
                logAdminAction($admin['admin_id'], 'PLAN_UPDATE', 'plan', $planId, $name);
                
                $success = 'Payment plan updated successfully';
            }
        } elseif ($action === 'delete') {
            $plan = $db->fetchOne("SELECT name FROM payment_plans WHERE plan_id = :id", ['id' => $planId]);
            if ($plan) {
                $subscribers = $db->fetchOne(
                    "SELECT COUNT(*) as count FROM pledges WHERE frequency = (SELECT frequency FROM payment_plans WHERE plan_id = :id)",
                    ['id' => $planId]
                );
                
                if ($subscribers['count'] > 0) {
                    throw new Exception('Cannot delete plan with active subscribers. Pause the plan instead.');
                }
                
                $db->execute("DELETE FROM payment_plans WHERE plan_id = :id", ['id' => $planId]);
                logAdminAction($admin['admin_id'], 'PLAN_DELETE', 'plan', $planId, $plan['name']);
                $success = 'Payment plan deleted successfully';
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get plans list
$plans = $db->fetchAll(
    "SELECT *, 
            (SELECT COUNT(*) FROM pledges WHERE frequency = payment_plans.frequency AND is_active = TRUE) as active_subscribers
     FROM payment_plans
     ORDER BY display_order, plan_id"
);

// Get selected plan ID (from URL or first plan)
$selectedPlanId = intval($_GET['id'] ?? ($plans[0]['plan_id'] ?? 0));

// Get selected plan data
$selectedPlan = null;
$planSubscribers = [];
if ($selectedPlanId) {
    $selectedPlan = $db->fetchOne(
        "SELECT * FROM payment_plans WHERE plan_id = :id",
        ['id' => $selectedPlanId]
    );
    
    if ($selectedPlan) {
        $planSubscribers = $db->fetchAll(
            "SELECT u.user_id, u.email, u.first_name, u.last_name, p.total_amount, p.frequency, p.start_date, p.is_active
             FROM pledges p
             INNER JOIN users u ON p.user_id = u.user_id
             WHERE p.frequency = :frequency
             ORDER BY p.created_at DESC
             LIMIT 50",
            ['frequency' => $selectedPlan['frequency']]
        );
    }
}

// Get plan statistics
$totalPlans = count($plans);
$activePlans = count(array_filter($plans, fn($p) => $p['is_active']));
$pausedPlans = count(array_filter($plans, fn($p) => $p['is_paused']));
$totalSubscribers = array_sum(array_column($plans, 'active_subscribers'));

// Include layout
require_once __DIR__ . '/includes/layout.php';

// Define custom styles for this page
function layoutCustomStyles() {
    ?>
    <style>
        .plans-layout {
            display: grid;
            grid-template-columns: 320px minmax(0, 1fr);
            gap: var(--space-6);
            align-items: start;
        }
        
        .plans-list {
            background: rgba(255, 255, 255, 0.96);
            border-radius: 28px;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(10, 17, 31, 0.08);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .plans-list-header {
            padding: 20px 20px 16px;
            border-bottom: 1px solid rgba(10, 17, 31, 0.07);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .plans-list-header h3 {
            font-size: var(--type-h5-size);
            font-weight: 600;
            color: var(--dark);
        }
        
        .plans-list-scroll {
            overflow-y: auto;
            flex: 1;
            padding: 10px;
        }
        
        .plan-list-item {
            padding: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: var(--space-3);
            border-radius: 22px;
            text-decoration: none;
            margin-bottom: 8px;
        }
        
        .plan-list-item:hover {
            background: rgba(250, 246, 239, 0.9);
        }
        
        .plan-list-item.active {
            background: linear-gradient(135deg, rgba(244, 238, 223, 0.95), rgba(255, 255, 255, 0.98));
            box-shadow: inset 3px 0 0 var(--gold);
        }
        
        .plan-list-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        
        .plan-list-info {
            flex: 1;
            min-width: 0;
        }
        
        .plan-list-name {
            font-weight: 600;
            font-size: 15px;
            color: var(--dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .plan-list-amount {
            font-size: var(--type-body-xs);
            color: var(--text-secondary);
            margin-top: 4px;
        }
        
        .plan-list-badge {
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .plan-list-badge.active {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }
        
        .plan-list-badge.paused {
            background: rgba(255, 193, 7, 0.1);
            color: #b7950b;
        }
        
        .plan-list-badge.inactive {
            background: rgba(108, 117, 125, 0.1);
            color: var(--text-muted);
        }
        
        .plan-details {
            display: flex;
            flex-direction: column;
            gap: var(--space-6);
        }
        
        .plan-detail-card {
            background: rgba(255, 255, 255, 0.96);
            border-radius: 30px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            border: 1px solid rgba(10, 17, 31, 0.08);
        }
        
        .plan-detail-header {
            padding: 24px 26px 18px;
            border-bottom: 1px solid rgba(10, 17, 31, 0.07);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .plan-detail-title {
            font-size: var(--type-h4-size);
            font-weight: 700;
            color: var(--dark);
            margin-bottom: var(--space-2);
        }
        
        .plan-detail-description {
            color: var(--text-secondary);
            font-size: var(--type-body-sm);
        }
        
        .plan-amount-hero {
            padding: 20px 26px 24px;
            display: flex;
            align-items: baseline;
            gap: var(--space-3);
        }

        .plan-detail-amount {
            font-size: clamp(34px, 4vw, 48px);
            font-weight: 700;
            color: var(--gold-dark);
        }
        
        .plan-detail-frequency {
            font-size: var(--type-body);
            color: var(--text-muted);
        }
        
        .plan-detail-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0;
            padding: 0 10px 10px;
            background: linear-gradient(180deg, rgba(250, 246, 239, 0.96), rgba(244, 238, 223, 0.92));
        }
        
        .plan-detail-stat {
            text-align: center;
            padding: 18px 12px;
        }
        
        .plan-detail-stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
        }
        
        .plan-detail-stat-label {
            font-size: var(--type-body-xs-small);
            color: var(--text-tertiary);
            text-transform: uppercase;
            letter-spacing: 0.14em;
            margin-top: 6px;
        }
        
        .plan-detail-actions {
            padding: 18px 24px 22px;
            display: flex;
            gap: var(--space-3);
        }
        
        .subscribers-card {
            background: rgba(255, 255, 255, 0.96);
            border-radius: 28px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            border: 1px solid rgba(10, 17, 31, 0.08);
        }
        
        .subscribers-header {
            padding: 20px 22px 16px;
            border-bottom: 1px solid rgba(10, 17, 31, 0.07);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .subscribers-header h3 {
            font-size: var(--type-h5-size);
            font-weight: 600;
            color: var(--dark);
        }
        
        .subscribers-count {
            background: rgba(250, 246, 239, 0.9);
            padding: 7px 12px;
            border-radius: 999px;
            font-size: var(--type-body-xs);
            font-weight: 600;
            color: var(--text-secondary);
        }
        
        .subscriber-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid rgba(10, 17, 31, 0.06);
            transition: background 0.2s;
        }
        
        .subscriber-item:hover {
            background: rgba(250, 246, 239, 0.84);
        }
        
        .subscriber-info {
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }
        
        .subscriber-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: var(--gold);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: var(--type-body-sm);
        }
        
        .subscriber-name {
            font-weight: 600;
            font-size: var(--type-body-sm);
            color: var(--dark);
        }
        
        .subscriber-email {
            font-size: var(--type-body-xs);
            color: var(--text-muted);
        }
        
        .subscriber-amount {
            text-align: right;
        }
        
        .subscriber-amount-value {
            font-weight: 600;
            color: var(--success);
        }
        
        .subscriber-amount-date {
            font-size: var(--type-body-xs);
            color: var(--text-muted);
        }
        
        .empty-state {
            padding: var(--space-10);
            text-align: center;
            color: var(--text-muted);
        }
        
        .empty-state-icon {
            width: 64px;
            height: 64px;
            border-radius: 20px;
            background: var(--cream);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--space-4);
            font-size: 24px;
            color: var(--text-muted);
        }
        
        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: var(--space-5);
            border-bottom: 1px solid rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            font-size: var(--type-h5-size);
            font-weight: 600;
        }
        
        .modal-body {
            padding: var(--space-5);
        }
        
        .form-group {
            margin-bottom: var(--space-4);
        }
        
        .form-label {
            display: block;
            font-size: var(--type-body-sm);
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: var(--space-2);
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: var(--radius-sm);
            font-size: var(--type-body);
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }
        
        .form-textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-4);
        }
        
        .form-checkbox {
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .plans-layout {
                grid-template-columns: 1fr;
            }
            
            .plan-detail-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 576px) {
            .plan-detail-header,
            .plan-amount-hero,
            .plan-detail-actions,
            .subscribers-header,
            .subscriber-item {
                padding-left: 18px;
                padding-right: 18px;
            }

            .plan-detail-stats {
                grid-template-columns: 1fr 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }

            .plan-detail-actions {
                flex-direction: column;
            }

            .subscriber-item {
                align-items: flex-start;
                gap: 12px;
                flex-direction: column;
            }
        }
    </style>
    <?php
}

// Define page actions for layout
function layoutPageActions() {
    ?>
    <button onclick="openCreateModal()" class="btn btn-primary">
        <i class="fas fa-plus"></i>
        <span class="btn-text">New Plan</span>
    </button>
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

<div class="plans-layout">
    <div class="plans-list">
        <div class="plans-list-header">
            <div>
                <div class="subtle-kicker">Library</div>
                <h3>Payment Plans</h3>
            </div>
            <span class="subscribers-count"><?php echo $totalPlans; ?> plans</span>
        </div>
        <div class="plans-list-scroll">
            <?php if (empty($plans)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <p>No payment plans yet</p>
                    <button onclick="openCreateModal()" class="btn btn-primary btn-sm" style="margin-top: var(--space-4);">
                        <i class="fas fa-plus"></i> Create Plan
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($plans as $plan): 
                    $statusClass = $plan['is_paused'] ? 'paused' : ($plan['is_active'] ? 'active' : 'inactive');
                    $statusText = $plan['is_paused'] ? 'Paused' : ($plan['is_active'] ? 'Active' : 'Inactive');
                    $isActive = $plan['plan_id'] == $selectedPlanId;
                ?>
                    <a href="?id=<?php echo $plan['plan_id']; ?>" class="plan-list-item <?php echo $isActive ? 'active' : ''; ?>">
                        <div class="plan-list-icon" style="background: <?php echo e($plan['color_code'] ?? '#D4AF37'); ?>20; color: <?php echo e($plan['color_code'] ?? '#D4AF37'); ?>;">
                            <i class="fas fa-<?php echo e($plan['icon'] ?? 'star'); ?>"></i>
                        </div>
                        <div class="plan-list-info">
                            <div class="plan-list-name"><?php echo e($plan['name']); ?></div>
                            <div class="plan-list-amount"><?php echo formatCurrency($plan['amount']); ?> / <?php echo e($plan['frequency']); ?></div>
                        </div>
                        <span class="plan-list-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="plan-details">
        <?php if ($selectedPlan): ?>
            <div class="plan-detail-card">
                <div class="plan-detail-header">
                    <div>
                        <div class="plan-detail-title"><?php echo e($selectedPlan['name']); ?></div>
                        <div class="plan-detail-description"><?php echo e($selectedPlan['description'] ?: 'No description'); ?></div>
                    </div>
                    <span class="plan-list-badge <?php echo $selectedPlan['is_paused'] ? 'paused' : ($selectedPlan['is_active'] ? 'active' : 'inactive'); ?>">
                        <?php echo $selectedPlan['is_paused'] ? 'Paused' : ($selectedPlan['is_active'] ? 'Active' : 'Inactive'); ?>
                    </span>
                </div>
                
                <div class="plan-amount-hero">
                    <span class="plan-detail-amount"><?php echo formatCurrency($selectedPlan['amount']); ?></span>
                    <span class="plan-detail-frequency">per <?php echo strtolower(e($selectedPlan['frequency'])); ?></span>
                </div>
                
                <div class="plan-detail-stats">
                    <div class="plan-detail-stat">
                        <div class="plan-detail-stat-value"><?php echo number_format($selectedPlan['active_subscribers']); ?></div>
                        <div class="plan-detail-stat-label">Subscribers</div>
                    </div>
                    <div class="plan-detail-stat">
                        <div class="plan-detail-stat-value"><?php echo $selectedPlan['is_paused'] ? 'Yes' : 'No'; ?></div>
                        <div class="plan-detail-stat-label">Paused</div>
                    </div>
                    <div class="plan-detail-stat">
                        <div class="plan-detail-stat-value"><?php echo $selectedPlan['max_subscriptions'] ? number_format($selectedPlan['max_subscriptions']) : '∞'; ?></div>
                        <div class="plan-detail-stat-label">Max Limit</div>
                    </div>
                    <div class="plan-detail-stat">
                        <div class="plan-detail-stat-value"><?php echo e($selectedPlan['frequency']); ?></div>
                        <div class="plan-detail-stat-label">Frequency</div>
                    </div>
                </div>
                
                <div class="plan-detail-actions">
                    <button onclick="openEditModal(<?php echo $selectedPlan['plan_id']; ?>)" class="btn btn-secondary">
                        <i class="fas fa-edit"></i> Edit Plan
                    </button>
                    <button onclick="confirmDelete(<?php echo $selectedPlan['plan_id']; ?>, '<?php echo e($selectedPlan['name']); ?>')" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
            
            <!-- Subscribers Card -->
            <div class="subscribers-card">
                <div class="subscribers-header">
                    <div>
                        <div class="subtle-kicker">Members</div>
                        <h3>Subscribers</h3>
                    </div>
                    <span class="subscribers-count"><?php echo count($planSubscribers); ?> users</span>
                </div>
                
                <?php if (empty($planSubscribers)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <p>No subscribers on this plan yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($planSubscribers as $subscriber): 
                        $initials = strtoupper(substr($subscriber['first_name'], 0, 1) . substr($subscriber['last_name'], 0, 1));
                    ?>
                        <div class="subscriber-item">
                            <div class="subscriber-info">
                                <div class="subscriber-avatar"><?php echo $initials; ?></div>
                                <div>
                                    <div class="subscriber-name"><?php echo e($subscriber['first_name'] . ' ' . $subscriber['last_name']); ?></div>
                                    <div class="subscriber-email"><?php echo e($subscriber['email']); ?></div>
                                </div>
                            </div>
                            <div class="subscriber-amount">
                                <div class="subscriber-amount-value"><?php echo formatCurrency($subscriber['total_amount']); ?></div>
                                <div class="subscriber-amount-date">Since <?php echo formatDate($subscriber['start_date']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="plan-detail-card">
                <div class="empty-state" style="padding: var(--space-16);">
                    <div class="empty-state-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <h3 style="margin-bottom: var(--space-2);">No Plan Selected</h3>
                    <p>Select a plan from the list to view details</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create/Edit Plan Modal -->
<div id="planModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Create Payment Plan</h3>
            <button onclick="closeModal()" class="text-ink/50 hover:text-ink transition" style="background: none; border: none; cursor: pointer; font-size: 20px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="planForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="form_action" id="formAction" value="create">
                <input type="hidden" name="plan_id" id="planId" value="">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="name">Plan Name *</label>
                        <input type="text" id="name" name="name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="frequency">Frequency *</label>
                        <select id="frequency" name="frequency" class="form-select" required>
                            <option value="One-time">One-time</option>
                            <option value="Weekly">Weekly</option>
                            <option value="Monthly">Monthly</option>
                            <option value="Annually">Annually</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="description">Description</label>
                    <textarea id="description" name="description" class="form-textarea"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="amount">Amount ($) *</label>
                        <input type="number" id="amount" name="amount" class="form-input" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="display_order">Display Order</label>
                        <input type="number" id="display_order" name="display_order" class="form-input" value="0">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="min_amount">Minimum Amount ($)</label>
                        <input type="number" id="min_amount" name="min_amount" class="form-input" step="0.01" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="max_amount">Maximum Amount ($)</label>
                        <input type="number" id="max_amount" name="max_amount" class="form-input" step="0.01" placeholder="Unlimited">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="paystack_plan_code">Paystack Plan Code</label>
                        <input type="text" id="paystack_plan_code" name="paystack_plan_code" class="form-input" placeholder="plan_...">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="icon">Icon</label>
                        <select id="icon" name="icon" class="form-select">
                            <option value="star">Star</option>
                            <option value="calendar">Calendar</option>
                            <option value="crown">Crown</option>
                            <option value="gift">Gift</option>
                            <option value="heart">Heart</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="color_code">Color</label>
                        <input type="color" id="color_code" name="color_code" class="form-input" value="#D4AF37" style="height: 44px; padding: 4px;">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="max_subscriptions">Max Subscriptions</label>
                        <input type="number" id="max_subscriptions" name="max_subscriptions" class="form-input" placeholder="Unlimited">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-checkbox">
                            <input type="checkbox" name="is_active" checked>
                            <span>Active</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-checkbox">
                            <input type="checkbox" name="is_paused">
                            <span>Paused</span>
                        </label>
                    </div>
                </div>
                
                <div style="display: flex; gap: var(--space-3); margin-top: var(--space-6);">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-save"></i> Save Plan
                    </button>
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Delete Plan</h3>
            <button onclick="closeDeleteModal()" style="background: none; border: none; cursor: pointer; font-size: 20px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom: var(--space-5);">Are you sure you want to delete <strong id="deletePlanName"></strong>? This action cannot be undone.</p>
            <form method="POST" style="display: flex; gap: var(--space-3);">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="form_action" value="delete">
                <input type="hidden" name="plan_id" id="deletePlanId" value="">
                <button type="submit" class="btn btn-danger" style="flex: 1;">
                    <i class="fas fa-trash"></i> Delete
                </button>
                <button type="button" onclick="closeDeleteModal()" class="btn btn-secondary">
                    Cancel
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Plan data for editing
const plansData = <?php echo json_encode($plans); ?>;

function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Create Payment Plan';
    document.getElementById('formAction').value = 'create';
    document.getElementById('planId').value = '';
    document.getElementById('planForm').reset();
    document.getElementById('color_code').value = '#D4AF37';
    document.getElementById('display_order').value = '0';
    document.getElementById('min_amount').value = '0';
    document.querySelector('input[name="is_active"]').checked = true;
    document.querySelector('input[name="is_paused"]').checked = false;
    document.getElementById('planModal').classList.add('active');
}

function openEditModal(planId) {
    const plan = plansData.find(p => p.plan_id == planId);
    if (!plan) return;
    
    document.getElementById('modalTitle').textContent = 'Edit Payment Plan';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('planId').value = planId;
    
    document.getElementById('name').value = plan.name;
    document.getElementById('frequency').value = plan.frequency;
    document.getElementById('description').value = plan.description || '';
    document.getElementById('amount').value = plan.amount;
    document.getElementById('display_order').value = plan.display_order;
    document.getElementById('min_amount').value = plan.min_amount || 0;
    document.getElementById('max_amount').value = plan.max_amount || '';
    document.getElementById('paystack_plan_code').value = plan.paystack_plan_code || '';
    document.getElementById('icon').value = plan.icon || 'star';
    document.getElementById('color_code').value = plan.color_code || '#D4AF37';
    document.getElementById('max_subscriptions').value = plan.max_subscriptions || '';
    document.querySelector('input[name="is_active"]').checked = plan.is_active == 1;
    document.querySelector('input[name="is_paused"]').checked = plan.is_paused == 1;
    
    document.getElementById('planModal').classList.add('active');
}

function closeModal() {
    document.getElementById('planModal').classList.remove('active');
}

function confirmDelete(planId, planName) {
    document.getElementById('deletePlanId').value = planId;
    document.getElementById('deletePlanName').textContent = planName;
    document.getElementById('deleteModal').classList.add('active');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
}

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});
</script>

<?php
// End the layout
layoutFooter();
?>
