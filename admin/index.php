<?php
/**
 * Admin Dashboard - Main Page
 * Church Financial Partnership System
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/../src/config/CurrencyService.php';

// Require authentication
$admin = requireAdmin();

$currencyService = new CurrencyService();
$statsCurrency = 'NGN';
$statsSymbol = $currencyService->getSymbol($statsCurrency);
$adminLastName = trim((string) ($admin['last_name'] ?? '')) ?: trim((string) ($admin['first_name'] ?? 'Admin'));

// Set page variables for layout
$pageTitle = 'Dashboard Overview';
$pageSubtitle = 'Here\'s what\'s happening today.';
$activePage = 'index';

function dashboardConvertAmount(CurrencyService $currencyService, $amount, string $fromCurrency, string $toCurrency = 'NGN'): float
{
    $amount = (float) $amount;
    $fromCurrency = strtoupper(trim($fromCurrency)) ?: $toCurrency;
    $toCurrency = strtoupper(trim($toCurrency)) ?: 'NGN';

    if ($fromCurrency === $toCurrency) {
        return $amount;
    }

    try {
        return (float) $currencyService->convert($amount, $fromCurrency, $toCurrency);
    } catch (Throwable $e) {
        return $amount;
    }
}

function dashboardFormatNaira(CurrencyService $currencyService, $amount): string
{
    return $currencyService->formatAmount((float) $amount, 'NGN');
}

function dashboardFormatOriginal(CurrencyService $currencyService, $amount, ?string $currency): string
{
    $currency = strtoupper(trim((string) $currency)) ?: 'NGN';
    return $currencyService->formatAmount((float) $amount, $currency);
}

function dashboardFormatMetricAmount(CurrencyService $currencyService, $amount, string $currency = 'NGN'): string
{
    $currency = strtoupper(trim($currency)) ?: 'NGN';
    $metricSymbol = $currency === 'NGN' ? 'N' : ($currency === 'USD' ? '$' : $currency);
    $decimalPlaces = abs((float) $amount) >= 1000 ? CURRENCY_DECIMAL_PLACES_LARGE : CURRENCY_DECIMAL_PLACES;
    $formatted = number_format((float) $amount, $decimalPlaces);

    return '<span class="metric-currency-symbol">' . e($metricSymbol) . '</span><span class="metric-number">' . e($formatted) . '</span>';
}

// Get dashboard statistics
$db = Database::getInstance();

$totalUsers = (int) $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_active = TRUE")['count'];
$activeProjects = (int) $db->fetchOne("SELECT COUNT(*) as count FROM campaigns WHERE is_active = TRUE")['count'];
$pendingTransactions = (int) $db->fetchOne("SELECT COUNT(*) as count FROM transactions WHERE status = 'pending'")['count'];
$activePartners = (int) $db->fetchOne(
    "SELECT COUNT(DISTINCT user_id) as count
     FROM pledges
     WHERE is_active = TRUE
     AND frequency IN ('Weekly', 'Monthly', 'Annually')"
)['count'];

$monthlyTransactionRows = $db->fetchAll(
    "SELECT t.amount, COALESCE(pt.currency, 'NGN') as transaction_currency
     FROM transactions t
     LEFT JOIN paystack_transactions pt ON pt.transaction_id = t.transaction_id
     WHERE MONTH(t.transaction_date) = MONTH(NOW())
     AND YEAR(t.transaction_date) = YEAR(NOW())
     AND t.status = 'completed'"
);

$monthlyRevenue = 0.0;
foreach ($monthlyTransactionRows as $row) {
    $monthlyRevenue += dashboardConvertAmount($currencyService, $row['amount'] ?? 0, $row['transaction_currency'] ?? 'NGN', $statsCurrency);
}

$recentTransactions = $db->fetchAll(
    "SELECT
        t.transaction_id,
        t.amount,
        t.category,
        t.status,
        t.transaction_reference,
        t.transaction_date,
        u.first_name,
        u.last_name,
        u.email,
        p.title as project_title,
        pm.card_type,
        pm.last_four_digits,
        COALESCE(pt.currency, 'NGN') as transaction_currency
     FROM transactions t
     LEFT JOIN users u ON t.user_id = u.user_id
     LEFT JOIN projects p ON t.project_id = p.project_id
     LEFT JOIN payment_methods pm ON t.payment_method_id = pm.payment_method_id
     LEFT JOIN paystack_transactions pt ON pt.transaction_id = t.transaction_id
     WHERE t.status = 'completed'
     ORDER BY t.transaction_date DESC
     LIMIT 4"
);

$activeProjectsWithProgress = $db->fetchAll(
    "SELECT
        campaign_id,
        title,
        description,
        category,
        goal_amount,
        current_amount,
        partner_count,
        icon,
        CASE WHEN goal_amount > 0 THEN (current_amount / goal_amount) * 100 ELSE 0 END as progress_percentage
     FROM campaigns
     WHERE is_active = TRUE
     ORDER BY created_at DESC
     LIMIT 4"
);

$recentUsers = $db->fetchAll(
    "SELECT
        user_id,
        email,
        first_name,
        last_name,
        created_at,
        phone,
        address_line1,
        address_line2,
        city,
        state,
        postal_code,
        country,
        is_active
     FROM users
     WHERE is_active = TRUE
     ORDER BY created_at DESC
     LIMIT 4"
);

foreach ($recentUsers as &$user) {
    $userTransactions = $db->fetchAll(
        "SELECT t.amount, COALESCE(pt.currency, 'NGN') as transaction_currency
         FROM transactions t
         LEFT JOIN paystack_transactions pt ON pt.transaction_id = t.transaction_id
         WHERE t.user_id = :user_id
         AND t.status = 'completed'",
        ['user_id' => $user['user_id']]
    );

    $totalGivenNaira = 0.0;
    foreach ($userTransactions as $transactionRow) {
        $totalGivenNaira += dashboardConvertAmount(
            $currencyService,
            $transactionRow['amount'] ?? 0,
            $transactionRow['transaction_currency'] ?? 'NGN',
            $statsCurrency
        );
    }

    $user['total_given_ngn'] = $totalGivenNaira;
    $user['transaction_count'] = count($userTransactions);
    $user['active_pledges'] = (int) $db->fetchOne(
        "SELECT COUNT(*) as count FROM pledges WHERE user_id = :user_id AND is_active = TRUE",
        ['user_id' => $user['user_id']]
    )['count'];
}
unset($user);

$paymentPlans = $db->fetchAll(
    "SELECT plan_id, name, amount, frequency, is_active, is_paused
     FROM payment_plans
     ORDER BY display_order
     LIMIT 4"
);

$adminRoles = $db->fetchAll(
    "SELECT ur.role_name, ur.role_description, ur.permissions
     FROM user_roles ur
     INNER JOIN user_role_assignments ura ON ur.role_id = ura.role_id
     WHERE ura.user_id = :user_id",
    ['user_id' => $admin['user_id']]
);

$roleNames = array_column($adminRoles, 'role_name');
$plansActiveCount = 0;
$plansPausedCount = 0;
$totalProjectGoal = 0.0;
$totalProjectRaised = 0.0;
$projectsNeedingAttention = 0;

foreach ($paymentPlans as $plan) {
    if (!empty($plan['is_paused'])) {
        $plansPausedCount++;
    } elseif (!empty($plan['is_active'])) {
        $plansActiveCount++;
    }
}

$underperformingProjects = $activeProjectsWithProgress;
usort($underperformingProjects, static function (array $left, array $right): int {
    return ((float) $left['progress_percentage']) <=> ((float) $right['progress_percentage']);
});

foreach ($activeProjectsWithProgress as &$project) {
    $goalAmount = (float) ($project['goal_amount'] ?? 0);
    $currentAmount = (float) ($project['current_amount'] ?? 0);
    $progress = (float) ($project['progress_percentage'] ?? 0);

    $totalProjectGoal += $goalAmount;
    $totalProjectRaised += $currentAmount;

    if ($goalAmount > 0 && $progress < 45) {
        $projectsNeedingAttention++;
    }

    $project['goal_display'] = dashboardFormatNaira($currencyService, $goalAmount);
    $project['raised_display'] = dashboardFormatNaira($currencyService, $currentAmount);
}
unset($project);

$portfolioProgress = $totalProjectGoal > 0 ? ($totalProjectRaised / $totalProjectGoal) * 100 : 0;

$focusItems = [];
if ($pendingTransactions > 0) {
    $focusItems[] = [
        'icon' => 'hourglass-half',
        'title' => number_format($pendingTransactions) . ' pending transactions',
        'body' => 'The finance queue still needs manual review before these donations are fully closed out.',
        'meta' => 'Finance',
    ];
}

if (!empty($underperformingProjects)) {
    $lowestProject = $underperformingProjects[0];
    $focusItems[] = [
        'icon' => 'bullseye',
        'title' => e($lowestProject['title']) . ' needs momentum',
        'body' => number_format((float) $lowestProject['progress_percentage'], 1) . '% of goal reached so far. This is the weakest live project in the current set.',
        'meta' => 'Projects',
    ];
}

if ($plansPausedCount > 0) {
    $focusItems[] = [
        'icon' => 'pause-circle',
        'title' => number_format($plansPausedCount) . ' payment plans are paused',
        'body' => 'Recurring giving products in a paused state usually need follow-up from operations or finance.',
        'meta' => 'Plans',
    ];
}

if (!empty($recentUsers)) {
    $newestUser = $recentUsers[0];
    $focusItems[] = [
        'icon' => 'user-plus',
        'title' => e($newestUser['first_name'] . ' ' . $newestUser['last_name']) . ' joined recently',
        'body' => 'Newest active member on the dashboard. Good candidate for onboarding and early giving follow-up.',
        'meta' => 'Users',
    ];
}

if (empty($focusItems)) {
    $focusItems[] = [
        'icon' => 'check-circle',
        'title' => 'Operations look stable',
        'body' => 'No immediate warning signal was generated from the current dashboard data.',
        'meta' => 'Stable',
    ];
}

// Get support ticket statistics
$openTickets = (int) $db->fetchOne("SELECT COUNT(*) as count FROM support_tickets WHERE status = 'open'")['count'];
$inProgressTickets = (int) $db->fetchOne("SELECT COUNT(*) as count FROM support_tickets WHERE status = 'in_progress'")['count'];
$totalTickets = (int) $db->fetchOne("SELECT COUNT(*) as count FROM support_tickets")['count'];

// Get recent activity logs from admin_audit_logs
$recentActivityLogs = $db->fetchAll(
    "SELECT al.*, u.first_name as admin_first_name, u.last_name as admin_last_name, u.email as admin_email
     FROM admin_audit_logs al
     LEFT JOIN admin_users au ON al.admin_id = au.admin_id
     LEFT JOIN users u ON au.user_id = u.user_id
     ORDER BY al.created_at DESC
     LIMIT 6"
);

// Include layout and start output
require_once __DIR__ . '/includes/layout.php';

function layoutCustomStyles()
{
    ?>
    <style>
        html,
        body {
            scrollbar-width: none;
        }

        html::-webkit-scrollbar,
        body::-webkit-scrollbar,
        #mainContent::-webkit-scrollbar,
        .focus-scroll::-webkit-scrollbar {
            width: 0;
            height: 0;
            display: none;
        }

        #mainContent {
            padding-top: calc(var(--padding-page-desktop) + 18px) !important;
            padding-right: calc(var(--padding-page-desktop) + 12px) !important;
            padding-bottom: calc(var(--padding-page-desktop) + 10px) !important;
            padding-left: calc(var(--padding-page-desktop) + 12px) !important;
            scrollbar-width: none;
        }

        .page-scaffold {
            padding: 18px !important;
        }

        .dashboard-layout {
            display: block;
        }

        .dashboard-primary,
        .dashboard-secondary {
            display: grid;
            gap: var(--space-10);
        }

        .dashboard-primary {
            width: 100%;
        }

        .dashboard-secondary {
            position: sticky;
            top: 20px;
        }

        .dashboard-overview-page .hero-card {
            position: relative;
            overflow: hidden;
            min-height: 176px;
            padding: 24px 24px 22px;
            border-radius: 26px;
            background:
                radial-gradient(ellipse at 78% 0%, rgba(201, 162, 75, 0.24), transparent 52%),
                radial-gradient(ellipse at 0% 100%, rgba(91, 123, 106, 0.2), transparent 54%),
                linear-gradient(135deg, #122137 0%, #0f1b2d 60%, #081220 100%);
            color: #faf6ef;
            box-shadow: 0 18px 48px rgba(10, 17, 31, 0.2);
        }

        .dashboard-overview-page .hero-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(rgba(201, 162, 75, 0.08) 1px, transparent 1px),
                radial-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px);
            background-size: 24px 24px, 48px 48px;
            background-position: 0 0, 12px 12px;
            pointer-events: none;
            opacity: 0.65;
        }

        .dashboard-overview-page .hero-card > * {
            position: relative;
            z-index: 1;
        }

        .dashboard-overview-page .hero-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: clamp(34px, 3.3vw, 48px);
            font-weight: 100;
            line-height: 1.04;
            letter-spacing: 0em;
            color: #fff8ec;
            margin: 0;
        }

        .dashboard-overview-page .hero-copy {
            margin: 0px 0 18px;
            max-width: 460px;
            font-weight: 200; !important;
            font-size: 14px;
            line-height: 1.6;
            color: rgba(250, 246, 239, 0.78);
        }

        .dashboard-overview-page .hero-actions {
            display: flex;
            flex-wrap: nowrap;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 4px;
            scrollbar-width: none;
        }

        .dashboard-overview-page .hero-actions::-webkit-scrollbar {
            display: none;
            width: 0;
            height: 0;
        }

        .dashboard-overview-page .hero-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 14px;
            border-radius: 999px;
            text-decoration: none;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.09);
            color: #fff8ec;
            font-size: 13px;
            font-weight: 600;
            backdrop-filter: blur(8px);
            transition: transform 0.2s ease, background-color 0.2s ease, border-color 0.2s ease;
        }

        .dashboard-overview-page .hero-action-btn:hover {
            transform: translateY(-1px);
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(231, 217, 180, 0.28);
        }

        .metric-rail {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 14px;
        }

        .dashboard-overview-page .metric-card {
            position: relative;
            overflow: hidden;
            min-height: 138px;
            padding: 18px;
            border-radius: 24px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.95), rgba(250, 246, 239, 0.96));
            border: 1px solid rgba(10, 17, 31, 0.06);
            box-shadow: 0 12px 30px rgba(10, 17, 31, 0.08);
        }

        .dashboard-overview-page .metric-card::after {
            content: "";
            position: absolute;
            inset: auto -20% -58% 45%;
            height: 126px;
            background: radial-gradient(circle, rgba(201, 162, 75, 0.16), transparent 70%);
            pointer-events: none;
        }

        .dashboard-overview-page .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 18px;
            position: relative;
            z-index: 1;
        }

        .dashboard-overview-page .metric-title {
            font-size: 11px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: rgba(15, 27, 45, 0.48);
        }

        .dashboard-overview-page .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.45);
        }

        .dashboard-overview-page .metric-value {
            position: relative;
            z-index: 1;
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: clamp(26px, 2.5vw, 36px);
            font-weight: 600;
            line-height: 1;
            letter-spacing: -0.04em;
            color: #0a111f;
            margin-bottom: 10px;
        }

        .dashboard-overview-page .metric-change {
            position: relative;
            z-index: 1;
            font-size: 12px;
            line-height: 1.1;
            color: rgba(15, 27, 45, 0.66);
        }

        .dashboard-row {
            display: grid;
            grid-template-columns: minmax(0, 1.7fr) minmax(220px, 0.72fr) minmax(0, 1fr);
            gap: var(--space-12);
            align-items: start;
        }

        .dashboard-row .transactions-card {
            min-width: 0;
        }

        .dashboard-row .plans-card {
            min-width: 0;
        }

        .dashboard-overview-page .card {
            background: rgba(255, 255, 255, 0.88);
            border: 1px solid rgba(10, 17, 31, 0.06);
            border-radius: 28px;
            box-shadow: 0 14px 36px rgba(10, 17, 31, 0.08);
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .dashboard-overview-page .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            padding: 18px 18px 14px;
            border-bottom: 1px solid rgba(10, 17, 31, 0.06);
        }

        .dashboard-overview-page .card-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 8px;
        }

        .dashboard-overview-page .card-body {
            padding: 18px;
        }

        .dashboard-overview-page .card-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 18px;
            line-height: 1.02;
            letter-spacing: 0em;
            color: #0a111f;
        }

        .dashboard-overview-page .card-subtitle {
            margin-top: 6px;
            font-size: 10px;
            line-height: 1;
            color: rgba(15, 27, 45, 0.64);
        }

        .dashboard-overview-page .section-kicker {
            margin-bottom: 01px;
            font-size: 10px;
            font-weight: 400;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: rgba(15, 27, 45, 0.42);
        }

        .dashboard-overview-page .btn.btn-secondary {
            padding: 1px 10px 1px 10px;
            border-radius: 999px;
            border: 1px solid rgba(10, 17, 31, 0.08);
            background: rgba(250, 246, 239, 0.8);
            color: #0a111f;
            font-size: 12px;
            font-weight: 600;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.65);
        }

        .dashboard-overview-page .btn.btn-secondary:hover {
            background: rgba(231, 217, 180, 0.38);
            border-color: rgba(201, 162, 75, 0.22);
        }

        .dashboard-overview-page .transaction-list,
        .dashboard-overview-page .user-list,
        .dashboard-overview-page .plans-list,
        .dashboard-overview-page .focus-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .dashboard-overview-page .transaction-item,
        .dashboard-overview-page .user-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(10, 17, 31, 0.05);
            transition: transform 0.2s ease, opacity 0.2s ease;
            cursor: pointer;
        }

        .dashboard-overview-page .transaction-item:last-child,
        .dashboard-overview-page .user-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .dashboard-overview-page .transaction-item:first-child,
        .dashboard-overview-page .user-item:first-child {
            padding-top: 0;
        }

        .dashboard-overview-page .transaction-item:hover,
        .dashboard-overview-page .user-item:hover {
            transform: translateX(2px);
        }

        .dashboard-overview-page .transaction-item {
            display: grid;
            grid-template-columns: 42px 92px minmax(160px, 1.35fr) minmax(110px, 0.95fr) auto;
            align-items: center;
            column-gap: 14px;
        }

        .dashboard-overview-page .transaction-icon,
        .dashboard-overview-page .user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .dashboard-overview-page .transaction-icon {
            background: rgba(91, 123, 106, 0.12);
            color: #4f6e5f;
        }

        .dashboard-overview-page .user-avatar {
            background: linear-gradient(145deg, #e7d9b4, #c9a24b);
            color: #0a111f;
            font-size: 13px;
            font-weight: 700;
        }

        .dashboard-overview-page .transaction-details,
        .dashboard-overview-page .user-info,
        .dashboard-overview-page .plan-info {
            flex: 1;
            min-width: 0;
        }

        .dashboard-overview-page .transaction-date-col,
        .dashboard-overview-page .transaction-user-col,
        .dashboard-overview-page .transaction-category-col,
        .dashboard-overview-page .transaction-amount-col {
            min-width: 0;
        }

        .dashboard-overview-page .transaction-user,
        .dashboard-overview-page .user-name,
        .dashboard-overview-page .plan-name {
            font-size: 14px;
            font-weight: 400;
            color: #0a111f;
        }

        .dashboard-overview-page .transaction-project,
        .dashboard-overview-page .user-email,
        .dashboard-overview-page .plan-frequency,
        .dashboard-overview-page .transaction-date {
            font-size: 12px;
            line-height: 1.55;
            font-weight: 300;
            color: rgba(15, 27, 45, 0.62);
        }

        .dashboard-overview-page .transaction-amount,
        .dashboard-overview-page .user-given {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: -0.02em;
            color: #0a111f;
        }

        .dashboard-overview-page .transaction-date {
            text-align: left;
        }

        .dashboard-overview-page .transaction-date-col .transaction-date {
            white-space: nowrap;
        }

        .dashboard-overview-page .transaction-user-col .transaction-user,
        .dashboard-overview-page .transaction-user-col .transaction-email {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dashboard-overview-page .transaction-email {
            font-size: 12px;
            line-height: 1.45;
            font-weight: 300;
            color: rgba(15, 27, 45, 0.62);
            margin-top: 2px;
        }

        .dashboard-overview-page .transaction-category {
            font-size: 13px;
            line-height: 1.45;
            font-weight: 400;
            color: rgba(10, 17, 31, 0.78);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dashboard-overview-page .transaction-amount-col {
            text-align: right;
        }

        .dashboard-overview-page .plan-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 14px 16px;
            border-radius: 20px;
            border: 1px solid rgba(10, 17, 31, 0.07);
            background:
                radial-gradient(circle at top right, rgba(201, 162, 75, 0.12), transparent 34%),
                rgba(250, 246, 239, 0.78);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.65);
        }

        .dashboard-overview-page .plan-item:last-child {
            border-bottom: none;
        }

        .dashboard-overview-page .plan-item:first-child {
            padding-top: 14px;
        }

        .dashboard-overview-page .plan-info {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            flex: 1;
        }

        .dashboard-overview-page .plan-info::before {
            content: "\f5fd";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(201, 162, 75, 0.16);
            color: #9a7530;
            flex-shrink: 0;
        }

        .dashboard-overview-page .plan-frequency {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 7px;
        }

        .dashboard-overview-page .plan-frequency::before,
        .dashboard-overview-page .plan-frequency::after {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(10, 17, 31, 0.08);
            color: rgba(10, 17, 31, 0.72);
        }

        .dashboard-overview-page .plan-frequency {
            font-size: 0;
            color: transparent;
        }

        .dashboard-overview-page .plan-frequency::before {
            content: attr(data-frequency);
        }

        .dashboard-overview-page .plan-frequency::after {
            content: attr(data-amount);
        }

        .dashboard-overview-page .plans-showcase {
            display: grid;
            gap: 12px;
        }

        .dashboard-overview-page .plan-item-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 14px 16px;
            border-radius: 20px;
            border: 1px solid rgba(10, 17, 31, 0.07);
            background:
                radial-gradient(circle at top right, rgba(201, 162, 75, 0.12), transparent 34%),
                rgba(250, 246, 239, 0.78);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.65);
        }

        .dashboard-overview-page .plan-item-main {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            flex: 1;
        }

        .dashboard-overview-page .plan-item-icon {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(201, 162, 75, 0.16);
            color: #9a7530;
            flex-shrink: 0;
        }

        .dashboard-overview-page .plan-item-copy {
            min-width: 0;
            flex: 1;
        }

        .dashboard-overview-page .plan-item-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 7px;
        }

        .dashboard-overview-page .plan-meta-pill,
        .dashboard-overview-page .plan-item-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .dashboard-overview-page .plan-meta-pill {
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(10, 17, 31, 0.08);
            color: rgba(10, 17, 31, 0.72);
        }

        .dashboard-overview-page .plan-item-cta {
            border: 1px solid rgba(10, 17, 31, 0.08);
            background: white;
            color: #0a111f;
            text-decoration: none;
            box-shadow: 0 10px 20px rgba(10, 17, 31, 0.06);
        }

        .dashboard-overview-page .plan-item-cta:hover {
            background: rgba(231, 217, 180, 0.28);
            border-color: rgba(201, 162, 75, 0.2);
        }

        .dashboard-overview-page .plan-status {
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
            align-self: flex-start;
        }

        .dashboard-overview-page .plan-status.active {
            background: rgba(91, 123, 106, 0.12);
            color: #4f6e5f;
        }

        .dashboard-overview-page .plan-status.paused {
            background: rgba(201, 162, 75, 0.16);
            color: #9a7530;
        }

        .dashboard-overview-page .activity-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .dashboard-overview-page .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(10, 17, 31, 0.05);
        }

        .dashboard-overview-page .activity-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .dashboard-overview-page .activity-item:first-child {
            padding-top: 0;
        }

        .dashboard-overview-page .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }

        .dashboard-overview-page .activity-icon.login { background: rgba(23, 162, 184, 0.1); color: #17a2b8; }
        .dashboard-overview-page .activity-icon.create { background: rgba(40, 167, 69, 0.1); color: #28a745; }
        .dashboard-overview-page .activity-icon.update { background: rgba(255, 193, 7, 0.1); color: #b7950b; }
        .dashboard-overview-page .activity-icon.delete { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
        .dashboard-overview-page .activity-icon.default { background: rgba(108, 117, 125, 0.1); color: #6c757d; }

        .dashboard-overview-page .activity-details {
            flex: 1;
            min-width: 0;
        }

        .dashboard-overview-page .activity-title {
            font-size: 13px;
            font-weight: 400;
            color: #0a111f;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dashboard-overview-page .activity-meta {
            font-size: 11px;
            line-height: 1.45;
            font-weight: 300;
            color: rgba(15, 27, 45, 0.62);
            margin-top: 2px;
        }

        .dashboard-overview-page .rail-card {
            background:
                radial-gradient(ellipse at top right, rgba(201, 162, 75, 0.18), transparent 48%),
                linear-gradient(180deg, #122137 0%, #0f1b2d 100%);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 26px;
            box-shadow: 0 18px 48px rgba(10, 17, 31, 0.18);
            color: #faf6ef;
            overflow: hidden;
        }

        .dashboard-overview-page .rail-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            padding: 18px 18px 0;
        }

        .dashboard-overview-page .rail-card-body {
            padding: 16px 18px 18px;
        }

        .dashboard-overview-page .rail-card .section-kicker,
        .dashboard-overview-page .rail-card .card-subtitle {
            color: rgba(250, 246, 239, 0.68);
        }

        .dashboard-overview-page .rail-card .card-title,
        .dashboard-overview-page .summary-value,
        .dashboard-overview-page .focus-title {
            color: #fff8ec;
            font-weight: 400;

            
        }

        .dashboard-overview-page .summary-toolbar {
            display: flex;
            justify-content: left;
            gap: 8px;
            margin-bottom: 14px;
        }

        .dashboard-overview-page .summary-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 12px;
            border-radius: 999px;
            text-decoration: none;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #fff8ec;
            font-size: 12px;
            font-weight: 600;
        }

        .dashboard-overview-page .summary-value {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 54px;
            font-weight: 200;
            line-height: 1;
            letter-spacing: 0.0em;
            margin-bottom: 10px;
        }

        .dashboard-overview-page .summary-meta {
            display: grid;
            gap: 6px;
            margin-bottom: 12px;
            font-size: 13px;
            line-height: 1.55;
            color: rgba(250, 246, 239, 0.76);
        }

        .dashboard-overview-page .hero-progress-track {
            height: 9px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.1);
            overflow: hidden;
        }

        .dashboard-overview-page .hero-progress-fill {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #c9a24b, #e7d9b4);
        }

        .dashboard-overview-page .focus-scroll {
            max-height: 360px;
            overflow-y: auto;
            scrollbar-width: none;
        }

        .dashboard-overview-page .focus-list {
            display: grid;
            gap: 10px;
        }

        .dashboard-overview-page .focus-item {
            display: flex;
            gap: 10px;
            padding: 12px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        .dashboard-overview-page .focus-icon {
            width: 34px;
            height: 34px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(231, 217, 180, 0.14);
            color: #e7d9b4;
            flex-shrink: 0;
        }

        .dashboard-overview-page .focus-title {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .dashboard-overview-page .focus-body {
            font-size: 12px;
            line-height: 1.6;
            color: rgba(250, 246, 239, 0.72);
        }

        .dashboard-overview-page .focus-meta {
            display: inline-flex;
            margin-top: 6px;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 10px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            background: rgba(231, 217, 180, 0.12);
            color: #e7d9b4;
        }

        #transactionModal,
        #userModal {
            display: none;
            position: fixed;
            inset: 0;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(10, 17, 31, 0.56);
            backdrop-filter: blur(8px);
            z-index: 2400;
        }

        #transactionModal.active,
        #userModal.active {
            display: flex;
        }

        #transactionModal .modal,
        #userModal .modal {
            width: min(680px, 100%);
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(250, 246, 239, 0.98));
            border: 1px solid rgba(10, 17, 31, 0.08);
            border-radius: 28px;
            box-shadow: 0 30px 80px rgba(10, 17, 31, 0.28);
        }

        #transactionModal .modal-header,
        #userModal .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 20px 22px 16px;
            border-bottom: 1px solid rgba(10, 17, 31, 0.06);
        }

        #transactionModal .modal-title,
        #userModal .modal-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 28px;
            font-weight: 600;
            line-height: 1.05;
            letter-spacing: -0.03em;
            color: #0a111f;
            margin: 0;
        }

        #transactionModal .modal-close,
        #userModal .modal-close {
            width: 38px;
            height: 38px;
            border: none;
            border-radius: 12px;
            background: rgba(10, 17, 31, 0.05);
            color: rgba(10, 17, 31, 0.7);
            font-size: 24px;
            line-height: 1;
            cursor: pointer;
        }

        #transactionModal .modal-body,
        #userModal .modal-body {
            padding: 22px;
        }

        #transactionModal .receipt-details,
        #userModal .user-profile-details {
            background: rgba(244, 238, 223, 0.82);
            border: 1px solid rgba(10, 17, 31, 0.05);
            border-radius: 22px;
            padding: 18px;
        }

        #transactionModal .receipt-actions {
            display: flex;
            gap: 12px;
            margin-top: 18px;
        }

        #transactionModal .receipt-row {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(10, 17, 31, 0.06);
        }

        #transactionModal .receipt-row:last-child {
            border-bottom: none;
        }

        #transactionModal .receipt-label,
        #userModal .profile-label {
            font-size: 13px;
            color: rgba(15, 27, 45, 0.62);
        }

        #transactionModal .receipt-value,
        #userModal .profile-value {
            font-size: 14px;
            font-weight: 600;
            color: #0a111f;
            text-align: right;
        }

        #transactionModal .receipt-total {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            margin-top: 14px;
            padding-top: 14px;
            border-top: 2px solid rgba(201, 162, 75, 0.45);
        }

        #transactionModal .receipt-total-label,
        #transactionModal .receipt-total-amount {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 18px;
            font-weight: 600;
            color: #0a111f;
        }

        #userModal .profile-section {
            margin-bottom: 20px;
        }

        #userModal .profile-section:last-child {
            margin-bottom: 0;
        }

        #userModal .profile-section-title {
            margin-bottom: 10px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: rgba(15, 27, 45, 0.46);
        }

        #userModal .profile-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        #userModal .profile-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        #userModal .profile-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 16px;
        }

        #userModal .profile-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 14px;
            border-radius: 999px;
            border: 1px solid rgba(10, 17, 31, 0.08);
            background: rgba(255, 255, 255, 0.9);
            color: #0a111f;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
        }

        #userModal .profile-action-btn.pause {
            background: rgba(201, 162, 75, 0.14);
            color: #8e6b2c;
        }

        #userModal .profile-action-btn.delete {
            background: rgba(220, 53, 69, 0.1);
            color: #b22739;
        }

        @media (max-width: 1280px) {
            .dashboard-layout {
                grid-template-columns: 1fr 300px;
            }

            .dashboard-row {
                grid-template-columns: minmax(0, 1.35fr) minmax(200px, 0.8fr);
            }

            .dashboard-row .users-card {
                grid-column: 1 / -1;
            }

            .metric-rail {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 1120px) {
            .dashboard-layout {
                display: block;
            }

            .dashboard-secondary {
                position: static;
            }
        }

        @media (max-width: 768px) {
            #mainContent {
                padding: 10px !important;
            }

            .dashboard-overview-page .hero-actions {
                gap: 8px;
            }

            .dashboard-overview-page .hero-action-btn {
                flex: 0 0 auto;
                white-space: nowrap;
            }

            .dashboard-overview-page .summary-toolbar {
                flex-wrap: nowrap;
                overflow-x: auto;
                scrollbar-width: none;
            }

            .dashboard-overview-page .summary-toolbar::-webkit-scrollbar {
                display: none;
                width: 0;
                height: 0;
            }

            #transactionModal,
            #userModal {
                padding: 12px;
            }

            #transactionModal .receipt-actions,
            #userModal .profile-grid,
            #userModal .profile-actions {
                grid-template-columns: 1fr;
                flex-direction: column;
            }

            .metric-rail {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .dashboard-row,
            .profile-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-overview-page .transaction-item {
                grid-template-columns: 42px 1fr;
                row-gap: 4px;
            }

            .dashboard-overview-page .transaction-date-col,
            .dashboard-overview-page .transaction-user-col,
            .dashboard-overview-page .transaction-category-col,
            .dashboard-overview-page .transaction-amount-col {
                grid-column: 2;
            }

            .dashboard-overview-page .transaction-amount-col {
                text-align: left;
            }

            .dashboard-overview-page .plan-item-card {
                flex-direction: column;
                align-items: stretch;
            }

            .dashboard-overview-page .plan-item-main {
                width: 100%;
            }

            .dashboard-overview-page .plan-status {
                align-self: flex-start;
            }
        }
    </style>
    <?php
}

function layoutPageActions()
{
    // Actions moved into the hero summary card.
}

layoutHeader();
?>

<div class="dashboard-layout dashboard-overview-page">
    <section class="dashboard-primary">
        <article class="hero-card">
            <h1 class="hero-title">Hello, <?php echo e($adminLastName); ?></h1>
            <p class="hero-copy">Here's what's happening today.</p>
            <div class="hero-actions">
                <a class="hero-action-btn" href="projects.php?action=create"><i class="fas fa-plus"></i> Add Project</a>
                <a class="hero-action-btn" href="users.php?action=create"><i class="fas fa-user-plus"></i> Add User</a>
                <a class="hero-action-btn" href="plans.php?action=create"><i class="fas fa-layer-group"></i> Add Plan</a>
            </div>
        </article>

        <section class="metric-rail">
            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-title">Active members</span>
                    <div class="metric-icon" style="background: rgba(26, 26, 46, 0.1); color: var(--dark);">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="metric-value"><?php echo number_format($totalUsers); ?></div>
                <div class="metric-change">Members.</div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-title">Live projects</span>
                    <div class="metric-icon" style="background: rgba(212, 175, 55, 0.1); color: var(--gold-dark);">
                        <i class="fas fa-church"></i>
                    </div>
                </div>
                <div class="metric-value"><?php echo number_format($activeProjects); ?></div>
                <div class="metric-change">Recent projects.</div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-title"><?php echo e(date('F')); ?> revenue</span>
                    <div class="metric-icon" style="background: rgba(40, 167, 69, 0.1); color: var(--success);">
                        <i class="fas fa-naira-sign"></i>
                    </div>
                </div>
                <div class="metric-value"><?php echo dashboardFormatMetricAmount($currencyService, $monthlyRevenue, $statsCurrency); ?></div>
                <div class="metric-change">Givings this month </div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-title">Recurring partners</span>
                    <div class="metric-icon" style="background: rgba(91, 123, 106, 0.12); color: #3f6251;">
                        <i class="fas fa-hands-helping"></i>
                    </div>
                </div>
                <div class="metric-value"><?php echo number_format($activePartners); ?></div>
                <div class="metric-change"><?php echo number_format($plansActiveCount); ?> active plans, <?php echo number_format($plansPausedCount); ?> paused.</div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-title">Open tickets</span>
                    <div class="metric-icon" style="background: rgba(220, 53, 69, 0.1); color: var(--danger);">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                </div>
                <div class="metric-value"><?php echo number_format($openTickets); ?></div>
                <div class="metric-change"><?php echo number_format($inProgressTickets); ?> in progress, <?php echo number_format($totalTickets); ?> total.</div>
            </div>
        </section>

        <section class="dashboard-row">
        <article class="card transactions-card">
            <div class="card-header">
                <div>
                    <div class="section-kicker">Recent giving flow</div>
                    <h2 class="card-title">Latest transactions</h2>
                </div>
                <div class="card-actions">
                    <a href="export.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-download"></i>
                        <span>Export</span>
                    </a>
                    <a href="transactions.php" class="btn btn-secondary btn-sm">View All</a>
                </div>
            </div>
            <div class="card-body">
                <ul class="transaction-list">
                    <?php foreach ($recentTransactions as $transaction): ?>
                        <li class="transaction-item" data-ref="<?php echo e($transaction['transaction_reference']); ?>">
                            <div class="transaction-icon"><i class="fas fa-arrow-down"></i></div>
                            <div class="transaction-date-col">
                                <div class="transaction-date"><?php echo e(formatDate($transaction['transaction_date'])); ?></div>
                            </div>
                            <div class="transaction-user-col">
                                <div class="transaction-user">
                                    <?php echo e(trim(($transaction['first_name'] ?? '') . ' ' . ($transaction['last_name'] ?? '')) ?: 'Anonymous giver'); ?>
                                </div>
                                <div class="transaction-email"><?php echo e($transaction['email'] ?? ''); ?></div>
                            </div>
                            <div class="transaction-category-col">
                                <div class="transaction-category">
                                    <?php echo e($transaction['project_title'] ?? $transaction['category'] ?? 'General giving'); ?>
                                </div>
                            </div>
                            <div class="transaction-amount-col">
                                <div class="transaction-amount">
                                    <?php echo e(dashboardFormatOriginal($currencyService, $transaction['amount'] ?? 0, $transaction['transaction_currency'] ?? 'NGN')); ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </article>

        <article class="card plans-card">
            <div class="card-header">
                <div>
                    <div class="section-kicker">Recurring products</div>
                    <h2 class="card-title">Payment plans</h2>
                </div>
                <a href="plans.php" class="btn btn-secondary btn-sm">Manage</a>
            </div>
            <div class="card-body">
                <ul class="plans-list plans-showcase">
                    <?php foreach ($paymentPlans as $plan): ?>
                        <li class="plan-item">
                            <div class="plan-info">
                                <div class="plan-name"><?php echo e($plan['name']); ?></div>
                                <div class="plan-frequency" data-frequency="<?php echo e($plan['frequency']); ?>" data-amount="<?php echo e(dashboardFormatNaira($currencyService, $plan['amount'] ?? 0)); ?>">
                                    <?php echo e($plan['frequency']); ?> · <?php echo e(dashboardFormatNaira($currencyService, $plan['amount'] ?? 0)); ?>
                                </div>
                            </div>
                            <span class="plan-status <?php echo !empty($plan['is_paused']) ? 'paused' : 'active'; ?>">
                                <?php echo !empty($plan['is_paused']) ? 'Paused' : 'Active'; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </article>

        <article class="card activity-card">
            <div class="card-header">
                <div>
                    <div class="section-kicker">System trail</div>
                    <h2 class="card-title">Recent activity logs</h2>
                </div>
            </div>
            <div class="card-body">
                <ul class="activity-list">
                    <?php if (empty($recentActivityLogs)): ?>
                        <li class="activity-item">
                            <div class="activity-details">
                                <div class="activity-title">No recent activity found</div>
                            </div>
                        </li>
                    <?php else: ?>
                        <?php foreach ($recentActivityLogs as $log): ?>
                            <?php
                            $actionType = $log['action_type'] ?? '';
                            $iconClass = 'default';
                            $icon = 'shield-alt';
                            if (strpos($actionType, 'LOGIN') !== false) {
                                $iconClass = 'login';
                                $icon = 'sign-in-alt';
                            } elseif (strpos($actionType, 'CREATE') !== false) {
                                $iconClass = 'create';
                                $icon = 'plus-circle';
                            } elseif (strpos($actionType, 'UPDATE') !== false) {
                                $iconClass = 'update';
                                $icon = 'edit';
                            } elseif (strpos($actionType, 'DELETE') !== false || strpos($actionType, 'REVOKE') !== false) {
                                $iconClass = 'delete';
                                $icon = 'trash';
                            }
                            $adminName = trim(($log['admin_first_name'] ?? '') . ' ' . ($log['admin_last_name'] ?? '')) ?: ($log['admin_email'] ?? 'System');
                            $targetName = $log['target_name'] ?? str_replace('_', ' ', $actionType);
                            ?>
                            <li class="activity-item">
                                <div class="activity-icon <?php echo $iconClass; ?>">
                                    <i class="fas fa-<?php echo $icon; ?>"></i>
                                </div>
                                <div class="activity-details">
                                    <div class="activity-title"><?php echo e($targetName); ?></div>
                                    <div class="activity-meta">
                                        <?php echo e($adminName); ?> &middot; <?php echo e(formatDate($log['created_at'])); ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </article>
        </section>
    </section>
</div>

<!-- Transaction Receipt Modal -->
<div class="modal-overlay" id="transactionModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Transaction Receipt</h3>
            <button class="modal-close" onclick="closeTransactionModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="receipt-details" id="receiptContent"></div>
            <div class="receipt-actions">
                <button class="btn btn-primary" onclick="downloadReceipt()">
                    <i class="fas fa-download"></i> Download Receipt
                </button>
                <button class="btn btn-secondary" onclick="closeTransactionModal()">Close</button>
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
            <div class="user-profile-details" id="userProfileContent"></div>
            <div style="text-align: right; margin-top: 20px;">
                <button class="btn btn-secondary" onclick="closeUserModal()">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
function layoutCustomScripts()
{
    global $recentTransactions, $recentUsers, $currencyService;
    ?>
    <script>
        function formatCurrency(amount, currency = 'NGN') {
            const symbols = { NGN: '₦', USD: '$' };
            const code = String(currency || 'NGN').toUpperCase();
            const symbol = symbols[code] || code + ' ';
            return symbol + Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function openProjectModal() {
            window.location.href = 'projects.php?action=create';
        }

        window.transactionData = <?php echo json_encode($recentTransactions, JSON_UNESCAPED_UNICODE); ?>;
        window.userData = <?php echo json_encode($recentUsers, JSON_UNESCAPED_UNICODE); ?>;
        window.statsCurrency = <?php echo json_encode($statsCurrency); ?>;

        document.querySelectorAll('.transaction-item').forEach(function(item) {
            item.addEventListener('click', function() {
                const ref = this.getAttribute('data-ref');
                fetchTransactionDetails(ref);
            });
        });

        document.querySelectorAll('.user-item').forEach(function(item) {
            item.addEventListener('click', function() {
                const userId = parseInt(this.getAttribute('data-user-id'), 10);
                const user = window.userData.find(function(entry) {
                    return parseInt(entry.user_id, 10) === userId;
                });
                if (user) {
                    showUserProfile(user);
                }
            });
        });

        function fetchTransactionDetails(ref) {
            fetch('./api/get-transaction.php?ref=' + encodeURIComponent(ref))
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    showTransactionReceipt(data);
                })
                .catch(function(error) {
                    console.error('Error fetching transaction:', error);
                    alert('Error loading transaction details: ' + error.message);
                });
        }

        function showTransactionReceipt(transaction) {
            window.currentTransaction = transaction;
            const modal = document.getElementById('transactionModal');
            const receiptContent = document.getElementById('receiptContent');
            const currencyCode = String(transaction.transaction_currency || transaction.currency || 'NGN').toUpperCase();
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
                    <span class="receipt-value">${transaction.formatted_date || new Date(transaction.transaction_date).toLocaleDateString()}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Time</span>
                    <span class="receipt-value">${transaction.formatted_time || new Date(transaction.transaction_date).toLocaleTimeString()}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Payer</span>
                    <span class="receipt-value">${(transaction.first_name || '') + ' ' + (transaction.last_name || '')}</span>
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
                    <span class="receipt-total-amount">${transaction.formatted_amount || formatCurrency(transaction.amount, currencyCode)}</span>
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
            window.open('api/receipt.php?ref=' + encodeURIComponent(ref), '_blank');
        }

        function showUserProfile(user) {
            const modal = document.getElementById('userModal');
            const userProfileContent = document.getElementById('userProfileContent');
            const fullName = (user.first_name || '') + ' ' + (user.last_name || '');
            const initials = ((user.first_name || '').charAt(0) + (user.last_name || '').charAt(0)).toUpperCase();

            userProfileContent.innerHTML = `
                <div style="text-align: center; margin-bottom: 24px;">
                    <div class="user-avatar" style="width: 80px; height: 80px; font-size: 28px; margin: 0 auto 12px;">${initials}</div>
                    <h3 style="font-size: 20px; margin-bottom: 4px;">${fullName.trim()}</h3>
                    <p style="color: var(--text-secondary);">${user.email || ''}</p>
                </div>

                <div class="profile-section">
                    <div class="profile-section-title">Account Summary</div>
                    <div class="profile-grid">
                        <div class="profile-item">
                            <span class="profile-label">Total Given</span>
                            <span class="profile-value" style="color: var(--success); font-size: 18px;">${formatCurrency(user.total_given_ngn, window.statsCurrency)}</span>
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
                            ${(user.city || '')}${user.city && user.state ? ', ' : ''}${user.state || ''} ${user.postal_code || ''}<br>
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

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                closeTransactionModal();
                closeUserModal();
            }
        });
    </script>
    <?php
}

layoutFooter();
?>
