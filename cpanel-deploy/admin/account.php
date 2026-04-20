<?php
/**
 * Admin Account Management
 * Manage the currently logged-in administrator profile and password.
 */

require_once __DIR__ . '/includes/config.php';

$admin = requireAdmin();
$db = Database::getInstance();

$pageTitle = 'Account Management';
$pageSubtitle = 'Update your profile details and keep your administrator credentials secure.';
$activePage = 'account';

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

            if ($firstName === '' || $lastName === '' || $email === '') {
                throw new Exception('First name, last name, and email are required.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Please enter a valid email address.');
            }

            $emailOwner = $db->fetchOne(
                "SELECT user_id FROM users WHERE email = :email AND user_id != :user_id LIMIT 1",
                [
                    'email' => $email,
                    'user_id' => $admin['user_id'],
                ]
            );

            if ($emailOwner) {
                throw new Exception('That email address is already assigned to another account.');
            }

            $db->execute(
                "UPDATE users
                 SET first_name = :first_name,
                     last_name = :last_name,
                     email = :email,
                     phone = :phone
                 WHERE user_id = :user_id",
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => $phone,
                    'user_id' => $admin['user_id'],
                ]
            );

            logAdminAction($admin['admin_id'], 'ADMIN_ACCOUNT_UPDATE', 'admin', $admin['admin_id'], $firstName . ' ' . $lastName);
            $admin = getAdminUser();
            $successMessage = 'Your account profile has been updated.';
        }

        if ($action === 'update_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                throw new Exception('All password fields are required.');
            }

            if ($newPassword !== $confirmPassword) {
                throw new Exception('The new password confirmation does not match.');
            }

            if (strlen($newPassword) < 8) {
                throw new Exception('The new password must be at least 8 characters long.');
            }

            $currentUserRecord = $db->fetchOne(
                "SELECT password_hash FROM users WHERE user_id = :user_id LIMIT 1",
                ['user_id' => $admin['user_id']]
            );

            if (!$currentUserRecord || !password_verify($currentPassword, $currentUserRecord['password_hash'])) {
                throw new Exception('Your current password is incorrect.');
            }

            $db->execute(
                "UPDATE users SET password_hash = :password_hash WHERE user_id = :user_id",
                [
                    'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
                    'user_id' => $admin['user_id'],
                ]
            );

            logAdminAction($admin['admin_id'], 'ADMIN_PASSWORD_UPDATE', 'admin', $admin['admin_id'], $admin['email']);
            $successMessage = 'Your password has been updated.';
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

require_once __DIR__ . '/includes/layout.php';

function layoutCustomStyles() {
    ?>
    <style>
        .account-shell {
            display: grid;
            gap: var(--space-6);
        }

        .account-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
            gap: var(--space-6);
            align-items: start;
        }

        .account-hero {
            display: flex;
            align-items: center;
            gap: var(--space-5);
            padding: var(--space-6);
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: var(--shadow-sm);
            margin-bottom: var(--space-6);
        }

        .account-avatar {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            background: linear-gradient(145deg, var(--gold), var(--gold-dark));
            color: var(--dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 700;
            flex-shrink: 0;
            box-shadow: 0 16px 30px rgba(169, 134, 58, 0.22);
        }

        .account-hero h2 {
            margin-bottom: var(--space-2);
        }

        .account-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: var(--space-4);
            margin-top: var(--space-5);
        }

        .meta-pill {
            padding: var(--space-4);
            border-radius: var(--radius-md);
            background: rgba(255, 255, 255, 0.68);
            border: 1px solid rgba(15, 27, 45, 0.08);
        }

        .meta-label {
            font-size: var(--type-body-xs-small);
            text-transform: uppercase;
            letter-spacing: var(--tracking-wider);
            color: var(--text-secondary);
            margin-bottom: 6px;
        }

        .meta-value {
            font-size: var(--type-body-sm);
            font-weight: 600;
            color: var(--text-primary);
        }

        .account-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .account-section-header {
            margin-bottom: var(--space-6);
        }

        .account-section-title {
            font-size: var(--type-h5-size);
            font-weight: 600;
            color: var(--dark);
            margin-bottom: var(--space-3);
            padding-bottom: var(--space-3);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .account-section-copy {
            color: var(--text-secondary);
            margin: 0;
            font-size: var(--type-body-sm);
            line-height: 1.6;
        }

        .account-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: var(--space-4);
        }

        .account-form-actions {
            display: flex;
            justify-content: flex-end;
            gap: var(--space-3);
            margin-top: var(--space-8);
            flex-wrap: wrap;
        }

        .security-list {
            display: grid;
            gap: var(--space-4);
        }

        .security-item {
            display: flex;
            gap: var(--space-4);
            padding: var(--space-5);
            border-radius: var(--radius-md);
            background: #FAFBFC;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .security-item i {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(201, 162, 75, 0.16);
            color: var(--gold-dark);
            flex-shrink: 0;
        }

        .security-item strong {
            display: block;
            margin-bottom: 4px;
            color: var(--text-primary);
        }

        .security-item p {
            color: var(--text-secondary);
            margin: 0;
        }

        @media (max-width: 992px) {
            .account-grid {
                grid-template-columns: 1fr;
            }

            .account-form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .account-hero {
                flex-direction: column;
                align-items: flex-start;
            }

            .account-meta {
                grid-template-columns: 1fr;
            }
        }

        .account-form-card-note {
            margin: 0 0 var(--space-5);
            color: var(--text-secondary);
            font-size: var(--type-body-sm);
            line-height: 1.6;
        }
    </style>
    <?php
}

layoutHeader();
?>

<div class="account-shell">
    <div class="account-hero">
        <div class="account-avatar">
            <?php echo strtoupper(substr($admin['first_name'], 0, 1) . substr($admin['last_name'], 0, 1)); ?>
        </div>
        <div>
            <h2><?php echo e($admin['first_name'] . ' ' . $admin['last_name']); ?></h2>
            <p class="text-secondary">Manage the details tied to your administrator session.</p>
            <div class="account-meta">
                <div class="meta-pill">
                    <div class="meta-label">Email</div>
                    <div class="meta-value"><?php echo e($admin['email']); ?></div>
                </div>
                <div class="meta-pill">
                    <div class="meta-label">Phone</div>
                    <div class="meta-value"><?php echo e($admin['phone'] ?: 'Not set'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="account-grid">
        <section class="account-section">
            <div class="account-section-header">
                <h3 class="account-section-title">
                    <i class="fas fa-id-badge" style="color: var(--gold);"></i>
                    <span>Profile Details</span>
                </h3>
                <p class="account-section-copy">Keep your administrator identity accurate so notifications and audit records stay correct.</p>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update_profile">
                <p class="account-form-card-note">These fields follow the same profile and contact treatment used throughout the dashboard settings surfaces.</p>

                <div class="account-form-grid">
                    <div class="form-group">
                        <label class="form-label" for="first_name">First Name</label>
                        <input class="form-input" type="text" id="first_name" name="first_name" value="<?php echo e($admin['first_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="last_name">Last Name</label>
                        <input class="form-input" type="text" id="last_name" name="last_name" value="<?php echo e($admin['last_name']); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input class="form-input" type="email" id="email" name="email" value="<?php echo e($admin['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number</label>
                    <input class="form-input" type="text" id="phone" name="phone" value="<?php echo e($admin['phone']); ?>" placeholder="+234 000 000 0000">
                </div>

                <div class="account-form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <span>Save Profile</span>
                    </button>
                </div>
            </form>
        </section>

        <section class="account-section">
            <div class="account-section-header">
                <h3 class="account-section-title">
                    <i class="fas fa-shield-alt" style="color: var(--info);"></i>
                    <span>Security</span>
                </h3>
                <p class="account-section-copy">Use a unique password and rotate it when you suspect exposure.</p>
            </div>

            <div class="security-list">
                <div class="security-item">
                    <i class="fas fa-user-shield"></i>
                    <div>
                        <strong>Administrator Access</strong>
                        <p>Your actions are tracked through the admin audit log for accountability.</p>
                    </div>
                </div>
                <div class="security-item">
                    <i class="fas fa-key"></i>
                    <div>
                        <strong>Password Change</strong>
                        <p>Confirm your current password before setting a new one.</p>
                    </div>
                </div>
            </div>

            <form method="POST" style="margin-top: var(--space-8);">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update_password">

                <div class="form-group">
                    <label class="form-label" for="current_password">Current Password</label>
                    <input class="form-input" type="password" id="current_password" name="current_password" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="new_password">New Password</label>
                    <input class="form-input" type="password" id="new_password" name="new_password" required minlength="8">
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm New Password</label>
                    <input class="form-input" type="password" id="confirm_password" name="confirm_password" required minlength="8">
                </div>

                <div class="account-form-actions">
                    <button type="submit" class="btn btn-secondary">
                        <i class="fas fa-lock"></i>
                        <span>Update Password</span>
                    </button>
                </div>
            </form>
        </section>
    </div>
</div>

<?php
layoutFooter();
?>
