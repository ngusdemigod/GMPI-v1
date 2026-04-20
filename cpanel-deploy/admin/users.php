<?php
/**
 * User Management Page
 * Church Financial Partnership System
 */

ob_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/../src/config/CurrencyService.php';
require_once __DIR__ . '/../src/helpers/EmailService.php';

$admin = requireAdmin();

$pageTitle = 'User Management';
$pageSubtitle = 'Manage user accounts, view contributions, and oversee partnerships';
$activePage = 'users';

$db = Database::getInstance();
$currencyService = new CurrencyService();
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['action'] ?? '') : ($_GET['action'] ?? 'list');
$userId = $_POST['id'] ?? ($_GET['id'] ?? null);
$selectedUserId = $_GET['selected'] ?? ($_GET['id'] ?? null);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    try {
        if ($action === 'edit_profile') {
            $firstName = sanitizeInput($_POST['first_name'] ?? '');
            $lastName = sanitizeInput($_POST['last_name'] ?? '');
            $phone = sanitizeInput($_POST['phone'] ?? '');
            $addressLine1 = sanitizeInput($_POST['address_line1'] ?? '');
            $addressLine2 = sanitizeInput($_POST['address_line2'] ?? '');
            $city = sanitizeInput($_POST['city'] ?? '');
            $state = sanitizeInput($_POST['state'] ?? '');
            $postalCode = sanitizeInput($_POST['postal_code'] ?? '');

            $db->execute(
                "UPDATE users SET
                 first_name = :first_name, last_name = :last_name, phone = :phone,
                 address_line1 = :address_line1, address_line2 = :address_line2,
                 city = :city, state = :state, postal_code = :postal_code
                 WHERE user_id = :user_id",
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'phone' => $phone,
                    'address_line1' => $addressLine1,
                    'address_line2' => $addressLine2,
                    'city' => $city,
                    'state' => $state,
                    'postal_code' => $postalCode,
                    'user_id' => $userId,
                ]
            );

            logAdminAction($admin['admin_id'], 'USER_PROFILE_UPDATE', 'user', $userId, "Updated profile for user ID: $userId");
            header('Location: users.php?selected=' . $userId . '&success=profile_updated');
            exit;
        } elseif ($action === 'toggle_status') {
            $isActive = $_POST['is_active'] === '1';
            $db->execute(
                "UPDATE users SET is_active = :is_active WHERE user_id = :user_id",
                ['is_active' => $isActive, 'user_id' => $userId]
            );

            logAdminAction($admin['admin_id'], 'USER_STATUS_CHANGE', 'user', $userId, 'Set is_active to: ' . ($isActive ? 'true' : 'false'));
            header('Location: users.php?selected=' . $userId . '&success=status_updated');
            exit;
        } elseif ($action === 'reset_password') {
            $newPassword = $_POST['new_password'] ?? '';
            if (strlen($newPassword) < 8) {
                throw new Exception('Password must be at least 8 characters');
            }

            $passwordTarget = $db->fetchOne(
                "SELECT email, first_name, last_name FROM users WHERE user_id = :user_id",
                ['user_id' => $userId]
            );
            
            $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->execute(
                "UPDATE users SET password_hash = :password_hash WHERE user_id = :user_id",
                ['password_hash' => $passwordHash, 'user_id' => $userId]
            );

            if ($passwordTarget && !empty($passwordTarget['email'])) {
                try {
                    $emailService = new EmailService();
                    $emailService->sendPasswordChangedAlert(
                        $passwordTarget['email'],
                        trim(($passwordTarget['first_name'] ?? '') . ' ' . ($passwordTarget['last_name'] ?? ''))
                    );
                } catch (Throwable $mailError) {
                    error_log('Admin password change notification failed: ' . $mailError->getMessage());
                }
            }
            
            logAdminAction($admin['admin_id'], 'USER_PASSWORD_RESET', 'user', $userId, 'Password reset by admin');
            header('Location: users.php?selected=' . $userId . '&success=password_reset');
            exit;
        } elseif ($action === 'delete') {
            $deleteTarget = $db->fetchOne("SELECT first_name, last_name FROM users WHERE user_id = :id", ['id' => $userId]);
            if ($deleteTarget) {
                $db->execute("DELETE FROM users WHERE user_id = :id", ['id' => $userId]);
                logAdminAction($admin['admin_id'], 'USER_DELETE', 'user', $userId, $deleteTarget['first_name'] . ' ' . $deleteTarget['last_name']);
                header('Location: users.php?success=deleted');
                exit;
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$users = $db->fetchAll(
    "SELECT u.*,
            (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = u.user_id AND status = 'completed') AS total_given,
            (SELECT COALESCE(SUM(t.amount), 0)
             FROM transactions t
             LEFT JOIN paystack_transactions pt ON pt.transaction_id = t.transaction_id
             WHERE t.user_id = u.user_id AND t.status = 'completed' AND COALESCE(pt.currency, 'NGN') = 'NGN') AS total_given_ngn,
            (SELECT COALESCE(SUM(t.amount), 0)
             FROM transactions t
             LEFT JOIN paystack_transactions pt ON pt.transaction_id = t.transaction_id
             WHERE t.user_id = u.user_id AND t.status = 'completed' AND COALESCE(pt.currency, 'NGN') = 'USD') AS total_given_usd,
            (SELECT COUNT(*) FROM transactions WHERE user_id = u.user_id AND status = 'completed') AS transaction_count,
            (SELECT COUNT(*) FROM pledges WHERE user_id = u.user_id AND is_active = TRUE) AS active_pledges
     FROM users u
     ORDER BY u.created_at DESC"
);

$totalUsers = count($users);
$totalGivenNgn = array_sum(array_column($users, 'total_given_ngn'));
$totalGivenUsd = array_sum(array_column($users, 'total_given_usd'));
$totalTransactions = array_sum(array_column($users, 'transaction_count'));

$selectedUser = null;
if ($selectedUserId) {
    foreach ($users as $listUser) {
        if ((string) $listUser['user_id'] === (string) $selectedUserId) {
            $selectedUser = $listUser;
            break;
        }
    }
}

$user = $selectedUser;

$userDirectoryData = array_map(function ($listUser) {
    $initials = strtoupper(substr((string) $listUser['first_name'], 0, 1) . substr((string) $listUser['last_name'], 0, 1));
    $addressParts = array_filter([
        $listUser['address_line1'] ?? '',
        $listUser['address_line2'] ?? '',
        trim(($listUser['city'] ?? '') . ' ' . ($listUser['state'] ?? '')),
        $listUser['postal_code'] ?? '',
        $listUser['country'] ?? '',
    ], function ($value) {
        return trim((string) $value) !== '';
    });

    return [
        'id' => (string) $listUser['user_id'],
        'first_name' => (string) ($listUser['first_name'] ?? ''),
        'last_name' => (string) ($listUser['last_name'] ?? ''),
        'full_name' => trim((string) ($listUser['first_name'] ?? '') . ' ' . (string) ($listUser['last_name'] ?? '')),
        'email' => (string) ($listUser['email'] ?? ''),
        'phone' => (string) (($listUser['phone'] ?? '') ?: 'N/A'),
        'address' => $addressParts ? implode(', ', $addressParts) : 'N/A',
        'total_given' => formatCurrency((float) $listUser['total_given']),
        'transaction_count' => number_format((int) $listUser['transaction_count']),
        'active_pledges' => number_format((int) $listUser['active_pledges']),
        'status' => !empty($listUser['is_active']) ? 'Active' : 'Deactivated',
        'toggle_status_value' => !empty($listUser['is_active']) ? '0' : '1',
        'toggle_status_label' => !empty($listUser['is_active']) ? 'Pause Account' : 'Activate Account',
        'member_since' => formatDate($listUser['created_at']),
        'last_login' => !empty($listUser['last_login']) ? formatDate($listUser['last_login']) : 'Never',
        'initials' => $initials ?: 'U',
        'address_line1' => (string) ($listUser['address_line1'] ?? ''),
        'address_line2' => (string) ($listUser['address_line2'] ?? ''),
        'city' => (string) ($listUser['city'] ?? ''),
        'state' => (string) ($listUser['state'] ?? ''),
        'postal_code' => (string) ($listUser['postal_code'] ?? ''),
    ];
}, $users);

require_once __DIR__ . '/includes/layout.php';

function layoutCustomStyles()
{
    ?>
    <style>
        .page-scaffold {
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            overflow-x: hidden;
            min-height: 0;
        }
        
        .main-content{
            padding:30px;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--space-6);
            margin-bottom: var(--space-8);
            flex-shrink: 0;
        }

        .metric-card {
            background: linear-gradient(180deg, rgba(255,255,255,0.95), rgba(250,246,239,0.96));
            border: 1px solid rgba(10, 17, 31, 0.06);
            box-shadow: 0 12px 30px rgba(10, 17, 31, 0.08);
            border-radius: 24px;
            overflow: hidden;
        }

        .metric-card .metric-title {
            
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: rgba(15, 27, 45, 0.48);
        }

        .metric-card .metric-value {
            
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: clamp(24px, 2.2vw, 34px);
            font-weight: 600;
            line-height: 1;
            letter-spacing: -0.04em;
            color: rgba(10, 17, 31, 0.88);
        }

        .metric-currency-symbol {
            color: var(--gold);
            margin-right: 4px;
        }

        .users-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(300px, 25vw);
            gap: var(--space-6);
            align-items: start;
            flex: 1;
            min-height: 0;
        }

        .table-container {
            overflow: auto;
            min-height: 0;
        }

        .users-table-card {
            border-radius: 28px;
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .users-table-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 18px 18px 14px;
            border-bottom: 1px solid rgba(10, 17, 31, 0.06);
        }

        .users-table-card .card-body {
            padding: 0;
            flex: 1;
            min-height: 0;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th,
        .users-table td {
            padding: var(--space-3) var(--space-4);
            text-align: left;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            vertical-align: middle;
            background: transparent;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .users-table th {
            font-size: var(--type-body-xs-small);
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-secondary);
            font-weight: 500;
            padding-top: 16px;
            padding-bottom: 16px;
        }

        .users-table th:first-child,
        .users-table td:first-child {
            padding-left: 24px;
        }

        .users-table th:last-child,
        .users-table td:last-child {
            padding-right: 24px;
        }

        .users-table th:nth-child(3),
        .users-table td:nth-child(3) {
            text-align: right;
        }

        .users-table tbody tr {
            cursor: pointer;
            transition: background 0.2s ease, box-shadow 0.2s ease;
        }

        .users-table tbody tr:hover {
            background: var(--cream-light);
        }

        .users-table tbody tr.is-selected {
            background: rgba(212, 175, 55, 0.08);
            box-shadow: inset 3px 0 0 var(--gold);
        }

        .users-table tbody td {
            font-size: var(--type-body-sm);
            font-weight: 500;
            color: rgba(10, 17, 31, 0.7);
        }

        .amount-positive {
            color: var(--success);
            font-weight: 700;
        }

        .amount-zero {
            color: var(--text-muted);
            font-weight: 600;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: var(--type-body-xs);
            font-weight: 500;
        }

        .status-active {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .status-inactive {
            background: rgba(108, 117, 125, 0.1);
            color: var(--text-muted);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--dark);
            font-size: var(--type-body-sm);
            flex-shrink: 0;
        }

        .user-identity {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .user-identity-text {
            min-width: 0;
        }

        .user-name {
            font-size: var(--type-body-sm);
            font-weight: 600;
            color: rgba(10, 17, 31, 0.84);
            line-height: 1.25;
        }

        .user-email-inline {
            margin-top: 4px;
            font-size: var(--type-body-xs);
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-details-shell {
            height: 100%;
            min-height: 0;
        }

        .user-details-card {
            background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(250,246,239,0.96));
            border: 1px solid rgba(10, 17, 31, 0.06);
            border-radius: 32px;
            box-shadow: 0 18px 42px rgba(10, 17, 31, 0.08);
            padding: 34px 26px 26px;
            height: 100%;
            min-height: 0;
            overflow: auto;
        }

        .user-details-empty,
        .user-details-content {
            display: none;
        }

        .user-details-card.is-empty .user-details-empty,
        .user-details-card.is-active .user-details-content {
            display: block;
        }

        .user-details-empty {
            padding: 84px 0;
            text-align: center;
            color: var(--text-secondary);
        }

        .user-detail-badge {
            width: 64px;
            height: 64px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: linear-gradient(180deg, #e4c86e, #d3a93c);
            color: #182345;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            box-shadow: 0 14px 24px rgba(212, 175, 55, 0.2);
        }

        .user-detail-title {
            margin: 0;
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 32px;
            line-height: 1.02;
            letter-spacing: -0.04em;
            text-align: center;
            color: rgba(10, 17, 31, 0.94);
        }

        .user-detail-subtitle {
            max-width: 320px;
            margin: 14px auto 18px;
            text-align: center;
            font-size: var(--type-body-sm);
            line-height: 1.65;
            color: var(--text-secondary);
        }

        .user-detail-close {
            display: none;
            position: absolute;
            top: 18px;
            right: 18px;
            width: 42px;
            height: 42px;
            border: none;
            border-radius: 50%;
            align-items: center;
            justify-content: center;
            background: rgba(10, 17, 31, 0.06);
            color: rgba(10, 17, 31, 0.78);
            font-size: 26px;
            line-height: 1;
            cursor: pointer;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .user-detail-close:hover {
            background: rgba(10, 17, 31, 0.12);
            color: rgba(10, 17, 31, 0.96);
        }

        .user-detail-cta-group {
            display: flex;
            flex-wrap: nowrap;
            gap: 10px;
            margin: 0 0 24px;
            overflow-x: auto;
            padding-bottom: 4px;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .user-detail-cta-group::-webkit-scrollbar {
            width: 0;
            height: 0;
            display: none;
        }

        .user-detail-field {
            margin-bottom: 18px;
        }

        .user-detail-label {
            display: block;
            margin-bottom: 8px;
            font-size: var(--type-body-sm);
            font-weight: 500;
            color: rgba(10, 17, 31, 0.84);
        }

        .user-detail-value {
            padding: 16px 18px;
            border: 1px solid rgba(212, 175, 55, 0.72);
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.94);
            font-size: var(--type-body);
            font-weight: 500;
            color: rgba(10, 17, 31, 0.68);
            line-height: 1.55;
            word-break: break-word;
        }

        .user-detail-value.is-plain {
            padding: 0;
            border: none;
            border-radius: 0;
            background: transparent;
            font-size: var(--type-body-sm);
            line-height: 1.7;
        }

        .user-detail-stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin: 22px 0;
        }

        .user-detail-stat {
            padding: 14px 16px;
            border-radius: 18px;
            background: rgba(249, 245, 236, 0.9);
            border: 1px solid rgba(10, 17, 31, 0.05);
        }

        .user-detail-stat-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: rgba(15, 27, 45, 0.46);
        }

        .user-detail-stat-value {
            margin-top: 8px;
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 22px;
            font-weight: 600;
            letter-spacing: -0.04em;
            color: rgba(10, 17, 31, 0.9);
        }

        .user-detail-primary,
        .user-detail-secondary {
            flex: 1 0 150px;
            justify-content: center;
            border-radius: 999px;
            padding: 14px 18px;
            min-width: 0;
            min-height: 52px;
            white-space: nowrap;
            font-size: 12px;
        }

        .user-detail-cta-group form {
            flex: 1 0 170px;
            min-width: 0;
        }

        .user-detail-cta-group form .btn {
            width: 100%;
        }

        .user-detail-primary {
            background: #182345;
            color: #fff;
            border: none;
            box-shadow: none;
        }

        .user-detail-divider {
            height: 1px;
            margin: 22px 0;
            background: rgba(10, 17, 31, 0.08);
        }

        .user-detail-secondary-note {
            text-align: center;
            color: var(--text-secondary);
            font-size: var(--type-body-sm);
            margin-bottom: 18px;
        }

        .user-detail-secondary {
            background: transparent;
            color: var(--gold-dark);
            border: 1px solid rgba(212, 175, 55, 0.9);
        }

        .user-detail-secondary.account-toggle-btn {
            padding: 12px 16px;
            font-size: var(--type-body-xs);
        }

        .user-detail-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            margin-top: 18px;
            color: var(--text-secondary);
            background: transparent;
            border: none;
            font-size: var(--type-body-sm);
        }

        .user-detail-actions {
            display: grid;
            gap: 12px;
        }

        .user-detail-danger {
            margin-top: 20px;
            padding: 14px;
            border-radius: 20px;
            background: rgba(220, 53, 69, 0.08);
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .user-detail-danger .user-detail-back {
            margin-top: 0;
            color: #b42318;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: var(--space-5);
        }

        .form-label {
            display: block;
            font-size: var(--type-body-sm);
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: var(--space-2);
        }

        .form-input,
        .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: var(--radius-sm);
            font-size: var(--type-body);
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-5);
        }

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
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
            padding: var(--space-5) var(--space-6);
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .modal-title {
            font-size: var(--type-h4-size);
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
            padding: var(--space-6);
        }

        .reset-password-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: var(--space-3);
            margin-top: var(--space-6);
        }

        .reset-password-actions-right {
            display: flex;
            gap: var(--space-3);
            align-items: center;
            margin-left: auto;
        }

        @media (max-width: 992px) {
            .page-scaffold {
                overflow-y: auto;
            }

            .metrics-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .users-layout {
                grid-template-columns: 1fr;
                flex: initial;
                gap: var(--space-5);
            }

            .user-details-shell {
                position: static;
            }

            .user-details-card {
                min-height: 0;
                height: auto;
                overflow: visible;
            }
        }

        @media (max-width: 992px) and (orientation: portrait) {
            .page-scaffold {
                overflow: visible;
            }

            .user-details-shell {
                display: none;
                position: fixed;
                inset: 0;
                z-index: 2100;
                padding: 20px 16px;
                background: rgba(7, 14, 24, 0.52);
                align-items: flex-start;
                justify-content: center;
                overflow-y: auto;
            }

            .user-details-shell.is-open {
                display: flex;
            }

            .user-details-card {
                position: relative;
                width: min(100%, 460px);
                max-height: calc(100vh - 40px);
                margin: 0 auto;
                overflow: auto;
                box-shadow: 0 24px 60px rgba(10, 17, 31, 0.24);
            }

            .user-detail-close {
                display: inline-flex;
            }
        }

        @media (max-width: 768px) {
            .metrics-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--space-3);
                margin-bottom: var(--space-5);
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .modal {
                width: 95%;
                max-height: 95vh;
                border-radius: var(--radius-md);
            }

            .users-table {
                min-width: 680px;
            }

            .user-details-card {
                padding: 28px 20px 22px;
            }

            .user-detail-title {
                font-size: 28px;
            }

            .user-detail-stats {
                grid-template-columns: 1fr 1fr;
            }

            .user-detail-cta-group {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 8px;
                overflow: visible;
                padding-bottom: 0;
            }

            .user-detail-primary,
            .user-detail-secondary {
                flex: none;
                width: 100%;
                padding: 12px 8px;
                min-height: 44px;
                font-size: 10px;
                line-height: 1.2;
                letter-spacing: -0.01em;
            }

            .user-detail-cta-group form {
                flex: none;
                width: 100%;
                min-width: 0;
            }

            .user-detail-secondary.account-toggle-btn {
                padding: 12px 8px;
                font-size: 10px;
            }
        }

        @media (max-width: 576px) {
            .metrics-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .users-layout {
                gap: var(--space-4);
            }

            .users-table {
                min-width: 620px;
            }

            .user-details-card {
                border-radius: 24px;
                padding: 24px 18px 20px;
            }

            .user-detail-badge {
                width: 58px;
                height: 58px;
                font-size: 20px;
                margin-bottom: 16px;
            }

            .user-detail-title {
                font-size: 24px;
            }

            .user-detail-stats {
                grid-template-columns: 1fr;
            }

            .user-detail-primary,
            .user-detail-secondary {
                padding: 11px 6px;
                min-height: 42px;
                font-size: 9px;
            }

            .user-detail-cta-group {
                display: grid;
            }

            .user-detail-cta-group form {
                width: 100%;
            }

            .reset-password-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .reset-password-actions-right {
                width: 100%;
                margin-left: 0;
                justify-content: space-between;
            }
        }
    </style>
    <?php
}

function layoutPageActions()
{
    ?>
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-home"></i>
        <span class="btn-text">Dashboard</span>
    </a>
    <?php
}

layoutHeader();
?>

<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-title">Total Users</div>
        <div class="metric-value"><?php echo number_format($totalUsers); ?></div>
    </div>
    <div class="metric-card">
        <div class="metric-title">Total &#8358; Given</div>
        <div class="metric-value"><?php echo formatMetricCurrency((float) $totalGivenNgn, 'NGN'); ?></div>
    </div>
    <div class="metric-card">
        <div class="metric-title">Total $ Given</div>
        <div class="metric-value"><?php echo formatMetricCurrency((float) $totalGivenUsd, 'USD'); ?></div>
    </div>
    <div class="metric-card">
        <div class="metric-title">Total Transactions</div>
        <div class="metric-value"><?php echo number_format($totalTransactions); ?></div>
    </div>
</div>

<div class="users-layout">
    <div class="card users-table-card">
        <div class="card-header">
            <div>
                <div class="section-kicker">Member directory</div>
                <h2 class="card-title">All Users</h2>
            </div>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Total Given</th>
                            <th>Transactions</th>
                            <th>Pledges</th>
                            <th>Status</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $listUser): ?>
                            <?php $hasGiven = ((float) $listUser['total_given']) > 0; ?>
            <tr data-user-id="<?php echo e($listUser['user_id']); ?>" class="<?php echo $selectedUser && (string) $selectedUser['user_id'] === (string) $listUser['user_id'] ? 'is-selected' : ''; ?>" onclick="selectUser('<?php echo e($listUser['user_id']); ?>')">
                                <td>
                                    <div class="user-identity">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($listUser['first_name'], 0, 1) . substr($listUser['last_name'], 0, 1)); ?>
                                        </div>
                                        <div class="user-identity-text">
                                            <div class="user-name"><?php echo e($listUser['first_name'] . ' ' . $listUser['last_name']); ?></div>
                                            <div class="user-email-inline"><?php echo e($listUser['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="<?php echo $hasGiven ? 'amount-positive' : 'amount-zero'; ?>"><?php echo formatCurrency((float) $listUser['total_given']); ?></td>
                                <td><?php echo number_format((int) $listUser['transaction_count']); ?></td>
                                <td><?php echo number_format((int) $listUser['active_pledges']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $listUser['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $listUser['is_active'] ? 'Active' : 'Deactivated'; ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($listUser['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <aside class="user-details-shell" id="userDetailsShell">
        <div class="user-details-card <?php echo $selectedUser ? 'is-active' : 'is-empty'; ?>" id="userDetailsCard">
            <button class="user-detail-close" type="button" aria-label="Close user details" onclick="closeUserDetailsPopup()">&times;</button>
            <div class="user-details-empty" id="userDetailsEmpty">
                <div class="user-detail-badge">--</div>
                <h3 class="user-detail-title">User Details</h3>
                <p class="user-detail-subtitle">Click a user to view details.</p>
            </div>

            <div class="user-details-content" id="userDetailsContent">
                <div class="user-detail-badge" id="detailBadge"><?php echo $selectedUser ? e(strtoupper(substr($selectedUser['first_name'], 0, 1) . substr($selectedUser['last_name'], 0, 1))) : '--'; ?></div>
                <h3 class="user-detail-title" id="detailName"><?php echo $selectedUser ? e(trim($selectedUser['first_name'] . ' ' . $selectedUser['last_name'])) : ''; ?></h3>
                <p class="user-detail-subtitle" id="detailSubtitle"><?php echo $selectedUser ? e($selectedUser['email']) : ''; ?></p>

                <div class="user-detail-cta-group">
                    <button class="btn user-detail-primary" type="button" onclick="openEditProfileModal()">Edit Profile</button>
                    <button class="btn user-detail-secondary" type="button" onclick="openResetPasswordModal()">Reset Password</button>
                    <form method="POST" id="toggleStatusForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="id" id="toggleStatusUserId" value="<?php echo $selectedUser ? e($selectedUser['user_id']) : ''; ?>">
                        <input type="hidden" name="is_active" id="toggleStatusValue" value="<?php echo $selectedUser ? ($selectedUser['is_active'] ? '0' : '1') : ''; ?>">
                        <button type="submit" class="btn user-detail-secondary account-toggle-btn" id="toggleStatusButton"><?php echo $selectedUser ? ($selectedUser['is_active'] ? 'Pause Account' : 'Activate Account') : 'Pause Account'; ?></button>
                    </form>
                </div>

                <div class="user-detail-field">
                    <span class="user-detail-label">Contact Information</span>
                    <div class="user-detail-value is-plain" id="detailContact"><?php echo $selectedUser ? e(($selectedUser['phone'] ?: 'N/A')) : ''; ?></div>
                </div>

                <div class="user-detail-field">
                    <span class="user-detail-label">Address</span>
                    <div class="user-detail-value is-plain" id="detailAddress"><?php echo $selectedUser ? e(($userDirectoryData[array_search($selectedUser['user_id'], array_column($userDirectoryData, 'id'))]['address'] ?? 'N/A')) : ''; ?></div>
                </div>

                <div class="user-detail-stats">
                    <div class="user-detail-stat">
                        <div class="user-detail-stat-label">Total Given</div>
                        <div class="user-detail-stat-value" id="detailTotalGiven"><?php echo $selectedUser ? formatCurrency((float) $selectedUser['total_given']) : ''; ?></div>
                    </div>
                    <div class="user-detail-stat">
                        <div class="user-detail-stat-label">Transactions</div>
                        <div class="user-detail-stat-value" id="detailTransactions"><?php echo $selectedUser ? number_format((int) $selectedUser['transaction_count']) : ''; ?></div>
                    </div>
                    <div class="user-detail-stat">
                        <div class="user-detail-stat-label">Active Pledges</div>
                        <div class="user-detail-stat-value" id="detailPledges"><?php echo $selectedUser ? number_format((int) $selectedUser['active_pledges']) : ''; ?></div>
                    </div>
                    <div class="user-detail-stat">
                        <div class="user-detail-stat-label">Status</div>
                        <div class="user-detail-stat-value" id="detailStatus"><?php echo $selectedUser ? ($selectedUser['is_active'] ? 'Active' : 'Inactive') : ''; ?></div>
                    </div>
                </div>

                <div class="user-detail-field">
                    <span class="user-detail-label">Member Since</span>
                    <div class="user-detail-value is-plain" id="detailMemberSince"><?php echo $selectedUser ? formatDate($selectedUser['created_at']) : ''; ?></div>
                </div>

                <div class="user-detail-field">
                    <span class="user-detail-label">Last Login</span>
                    <div class="user-detail-value is-plain" id="detailLastLogin"><?php echo $selectedUser ? ($selectedUser['last_login'] ? formatDate($selectedUser['last_login']) : 'Never') : ''; ?></div>
                </div>

                <div class="user-detail-actions">
                    <div class="user-detail-divider"></div>
                    <div class="user-detail-secondary-note">Manage access and account settings for this member.</div>
                    <div class="user-detail-danger">
                        <form method="POST" id="deleteUserForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" id="deleteUserId" value="<?php echo $selectedUser ? e($selectedUser['user_id']) : ''; ?>">
                            <button type="submit" class="user-detail-back" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">&larr; Delete User</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</div>

<div class="modal-overlay" id="editProfileModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Edit Profile</h3>
            <button class="modal-close" onclick="closeEditProfileModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="edit_profile">
                <input type="hidden" name="id" id="editUserId" value="<?php echo $selectedUser ? e($selectedUser['user_id']) : ''; ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" id="editFirstName" class="form-input" value="<?php echo $selectedUser ? e($selectedUser['first_name']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" id="editLastName" class="form-input" value="<?php echo $selectedUser ? e($selectedUser['last_name']) : ''; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" id="editPhone" class="form-input" value="<?php echo $selectedUser ? e($selectedUser['phone'] ?? '') : ''; ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Address Line 1</label>
                    <input type="text" name="address_line1" id="editAddressLine1" class="form-input" value="<?php echo $selectedUser ? e($selectedUser['address_line1'] ?? '') : ''; ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Address Line 2</label>
                    <input type="text" name="address_line2" id="editAddressLine2" class="form-input" value="<?php echo $selectedUser ? e($selectedUser['address_line2'] ?? '') : ''; ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">City</label>
                        <input type="text" name="city" id="editCity" class="form-input" value="<?php echo $selectedUser ? e($selectedUser['city'] ?? '') : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">State</label>
                        <input type="text" name="state" id="editState" class="form-input" value="<?php echo $selectedUser ? e($selectedUser['state'] ?? '') : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Postal Code</label>
                    <input type="text" name="postal_code" id="editPostalCode" class="form-input" value="<?php echo $selectedUser ? e($selectedUser['postal_code'] ?? '') : ''; ?>">
                </div>

                <div style="display: flex; gap: var(--space-3); justify-content: flex-end; margin-top: var(--space-6);">
                    <button type="button" class="btn btn-secondary" onclick="closeEditProfileModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal-overlay" id="resetPasswordModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Reset Password</h3>
            <button class="modal-close" onclick="closeResetPasswordModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="id" id="resetUserId" value="<?php echo $selectedUser ? e($selectedUser['user_id']) : ''; ?>">

                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-input" required minlength="8" placeholder="Minimum 8 characters">
                </div>

                <div class="reset-password-actions">
                    <button type="button" class="btn btn-secondary" onclick="sendResetLink()">Send Reset Link</button>
                    <div class="reset-password-actions-right">
                        <button type="button" class="btn btn-secondary" onclick="closeResetPasswordModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Reset Password</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
function layoutCustomScripts()
{
    global $userDirectoryData;
    ?>
    <script>
        const usersDirectory = <?php echo json_encode(array_values($userDirectoryData), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const usersMap = Object.fromEntries(usersDirectory.map((user) => [String(user.id), user]));
        const userDetailsShell = document.getElementById('userDetailsShell');
        const userDetailsCard = document.getElementById('userDetailsCard');
        const userDetailsPopupQuery = window.matchMedia('(max-width: 992px) and (orientation: portrait)');

        function isUserDetailsPopupMode() {
            return userDetailsPopupQuery.matches;
        }

        function setUserDetailsPopupState(isOpen) {
            if (!userDetailsShell) {
                return;
            }

            const shouldOpen = Boolean(isOpen) && isUserDetailsPopupMode();
            userDetailsShell.classList.toggle('is-open', shouldOpen);
            document.body.classList.toggle('user-details-modal-open', shouldOpen);
        }

        function openUserDetailsPopup() {
            setUserDetailsPopupState(true);
        }

        function closeUserDetailsPopup() {
            setUserDetailsPopupState(false);
        }

        function syncUserDetailsPopupMode() {
            const hasSelectedUser = userDetailsCard && userDetailsCard.classList.contains('is-active');
            setUserDetailsPopupState(hasSelectedUser);
        }

        function openEditProfileModal() {
            const editUserId = document.getElementById('editUserId');
            if (!editUserId.value) {
                return;
            }
            document.getElementById('editProfileModal').classList.add('active');
        }

        function closeEditProfileModal() {
            document.getElementById('editProfileModal').classList.remove('active');
        }

        function openResetPasswordModal() {
            const resetUserId = document.getElementById('resetUserId');
            if (!resetUserId.value) {
                return;
            }
            document.getElementById('resetPasswordModal').classList.add('active');
        }

        function closeResetPasswordModal() {
            document.getElementById('resetPasswordModal').classList.remove('active');
        }

        function fillUserDetails(user) {
            document.getElementById('detailBadge').textContent = user.initials;
            document.getElementById('detailName').textContent = user.full_name;
            document.getElementById('detailSubtitle').textContent = user.email;
            document.getElementById('detailContact').textContent = user.phone;
            document.getElementById('detailAddress').textContent = user.address;
            document.getElementById('detailTotalGiven').textContent = user.total_given;
            document.getElementById('detailTransactions').textContent = user.transaction_count;
            document.getElementById('detailPledges').textContent = user.active_pledges;
            document.getElementById('detailStatus').textContent = user.status;
            document.getElementById('detailMemberSince').textContent = user.member_since;
            document.getElementById('detailLastLogin').textContent = user.last_login;

            document.getElementById('toggleStatusUserId').value = user.id;
            document.getElementById('toggleStatusValue').value = user.toggle_status_value;
            document.getElementById('toggleStatusButton').textContent = user.toggle_status_label;
            document.getElementById('deleteUserId').value = user.id;
            document.getElementById('editUserId').value = user.id;
            document.getElementById('resetUserId').value = user.id;

            document.getElementById('editFirstName').value = user.first_name;
            document.getElementById('editLastName').value = user.last_name;
            document.getElementById('editPhone').value = user.phone === 'N/A' ? '' : user.phone;
            document.getElementById('editAddressLine1').value = user.address_line1;
            document.getElementById('editAddressLine2').value = user.address_line2;
            document.getElementById('editCity').value = user.city;
            document.getElementById('editState').value = user.state;
            document.getElementById('editPostalCode').value = user.postal_code;

            const card = document.getElementById('userDetailsCard');
            card.classList.remove('is-empty');
            card.classList.add('is-active');
        }

        async function sendResetLink() {
            const detailSubtitle = document.getElementById('detailSubtitle');
            const email = detailSubtitle ? detailSubtitle.textContent.trim() : '';

            if (!email) {
                alert('Select a user first.');
                return;
            }

            const formData = new FormData();
            formData.append('email', email);

            try {
                const response = await fetch('../src/handlers/forgot-password.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                alert(result.message || 'Reset link request processed.');
            } catch (error) {
                alert('Unable to send reset link right now.');
            }
        }

        function selectUser(userId) {
            const user = usersMap[String(userId)];
            if (!user) {
                return;
            }

            fillUserDetails(user);
            document.querySelectorAll('.users-table tbody tr').forEach((row) => {
                row.classList.toggle('is-selected', row.dataset.userId === String(userId));
            });

            const nextUrl = new URL(window.location.href);
            nextUrl.searchParams.set('selected', userId);
            nextUrl.searchParams.delete('action');
            nextUrl.searchParams.delete('id');
            window.history.replaceState({}, '', nextUrl.toString());

            if (isUserDetailsPopupMode()) {
                openUserDetailsPopup();
            }
        }

        document.addEventListener('click', function (event) {
            if (event.target === userDetailsShell) {
                closeUserDetailsPopup();
            }

            if (event.target.classList.contains('modal-overlay')) {
                closeEditProfileModal();
                closeResetPasswordModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && userDetailsShell && userDetailsShell.classList.contains('is-open')) {
                closeUserDetailsPopup();
            }
        });

        if (typeof userDetailsPopupQuery.addEventListener === 'function') {
            userDetailsPopupQuery.addEventListener('change', syncUserDetailsPopupMode);
        } else if (typeof userDetailsPopupQuery.addListener === 'function') {
            userDetailsPopupQuery.addListener(syncUserDetailsPopupMode);
        }

        syncUserDetailsPopupMode();
    </script>
    <?php
}

layoutFooter();
?>
