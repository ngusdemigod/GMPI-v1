<?php
/**
 * Admin Settings Page
 * Church Financial Partnership System
 */

require_once __DIR__ . '/includes/config.php';

// Require authentication
$admin = requireAdmin();

// Set page variables for layout
$pageTitle = 'Settings';
$pageSubtitle = 'Configure system settings and preferences';
$activePage = 'settings';

$db = Database::getInstance();

// Handle form submission
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'recaptcha_site_key' => trim($_POST['recaptcha_site_key'] ?? ''),
        'recaptcha_secret_key' => trim($_POST['recaptcha_secret_key'] ?? ''),
        'support_email' => trim($_POST['support_email'] ?? ''),
        'support_phone' => trim($_POST['support_phone'] ?? ''),
        'auto_resolve_days' => (int)($_POST['auto_resolve_days'] ?? 7),
        'email_notifications' => isset($_POST['email_notifications']) ? '1' : '0',
        'church_name' => trim($_POST['church_name'] ?? ''),
        'portal_subtitle' => trim($_POST['portal_subtitle'] ?? ''),
        'church_tagline' => trim($_POST['church_tagline'] ?? ''),
        'tax_note' => trim($_POST['tax_note'] ?? ''),
        'bible_verse_text' => trim($_POST['bible_verse_text'] ?? ''),
        'bible_verse_ref' => trim($_POST['bible_verse_ref'] ?? ''),
        'footer_privacy_url' => trim($_POST['footer_privacy_url'] ?? ''),
        'footer_terms_url' => trim($_POST['footer_terms_url'] ?? ''),
        'footer_contact_url' => trim($_POST['footer_contact_url'] ?? ''),
        'impact_families_fed' => trim($_POST['impact_families_fed'] ?? ''),
        'impact_missionaries_sent' => trim($_POST['impact_missionaries_sent'] ?? ''),
        'impact_campuses_planted' => trim($_POST['impact_campuses_planted'] ?? ''),
        'impact_lives_touched' => trim($_POST['impact_lives_touched'] ?? ''),
    ];

    $settingTypes = [
        'recaptcha_site_key' => 'text',
        'recaptcha_secret_key' => 'text',
        'support_email' => 'text',
        'support_phone' => 'text',
        'auto_resolve_days' => 'number',
        'email_notifications' => 'boolean',
        'church_name' => 'text',
        'portal_subtitle' => 'text',
        'church_tagline' => 'text',
        'tax_note' => 'text',
        'bible_verse_text' => 'text',
        'bible_verse_ref' => 'text',
        'footer_privacy_url' => 'text',
        'footer_terms_url' => 'text',
        'footer_contact_url' => 'text',
        'impact_families_fed' => 'number',
        'impact_missionaries_sent' => 'number',
        'impact_campuses_planted' => 'number',
        'impact_lives_touched' => 'text',
    ];
    
    try {
        $db->beginTransaction();

        foreach ($settings as $key => $value) {
            $sql = "INSERT INTO system_settings (setting_key, setting_value, setting_type) 
                    VALUES (:setting_key, :insert_value, :setting_type) 
                    ON DUPLICATE KEY UPDATE 
                        setting_value = :update_value,
                        setting_type = :update_type";
            
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->execute([
                ':setting_key' => $key,
                ':insert_value' => (string) $value,
                ':setting_type' => $settingTypes[$key] ?? 'text',
                ':update_value' => (string) $value,
                ':update_type' => $settingTypes[$key] ?? 'text',
            ]);
        }

        $db->commit();
        $successMessage = 'Settings saved successfully!';
    } catch (Exception $e) {
        if ($db->getConnection()->inTransaction()) {
            $db->rollback();
        }
        error_log("Settings error: " . $e->getMessage());
        $errorMessage = 'An error occurred while saving settings.';
    }
}

// Get current settings
$settings = [];
$result = $db->getConnection()->query("SELECT * FROM system_settings");
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Include layout
require_once __DIR__ . '/includes/layout.php';

// Define custom styles for this page
function layoutCustomStyles() {
    ?>
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: var(--space-6);
        }
        
        .settings-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            box-shadow: var(--shadow-sm);
        }
        
        .settings-section-title {
            font-size: var(--type-h5-size);
            font-weight: 600;
            color: var(--dark);
            margin-bottom: var(--space-4);
            padding-bottom: var(--space-3);
            border-bottom: 1px solid rgba(0,0,0,0.05);
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
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 16px;
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
            min-height: 100px;
            resize: vertical;
        }
        
        .form-hint {
            font-size: var(--type-body-xs);
            color: var(--text-muted);
            margin-top: var(--space-1);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .settings-grid { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 768px) {
            .settings-grid { grid-template-columns: 1fr; }
        }
    </style>
    <?php
}

// Start the layout
layoutHeader();
?>

<form method="POST">
    <div class="settings-grid">
        <!-- reCAPTCHA Settings -->
        <div class="settings-section">
            <h3 class="settings-section-title">
                <i class="fas fa-shield-alt" style="color: var(--gold); margin-right: var(--space-2);"></i>
                reCAPTCHA Configuration
            </h3>
            
            <div class="form-group">
                <label class="form-label" for="recaptcha_site_key">Site Key</label>
                <input type="text" id="recaptcha_site_key" name="recaptcha_site_key" class="form-input" 
                       value="<?php echo e($settings['recaptcha_site_key'] ?? ''); ?>" 
                       placeholder="6Lc...">
                <p class="form-hint">Your Google reCAPTCHA v2 site key</p>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="recaptcha_secret_key">Secret Key</label>
                <input type="password" id="recaptcha_secret_key" name="recaptcha_secret_key" class="form-input" 
                       value="<?php echo e($settings['recaptcha_secret_key'] ?? ''); ?>" 
                       placeholder="6Lc...">
                <p class="form-hint">Your Google reCAPTCHA v2 secret key</p>
            </div>
        </div>
        
        <!-- Support Settings -->
        <div class="settings-section">
            <h3 class="settings-section-title">
                <i class="fas fa-headset" style="color: var(--info); margin-right: var(--space-2);"></i>
                Support Configuration
            </h3>
            
            <div class="form-group">
                <label class="form-label" for="support_email">Support Email</label>
                <input type="email" id="support_email" name="support_email" class="form-input" 
                       value="<?php echo e($settings['support_email'] ?? ''); ?>" 
                       placeholder="support@church.org">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="support_phone">Support Phone</label>
                <input type="text" id="support_phone" name="support_phone" class="form-input" 
                       value="<?php echo e($settings['support_phone'] ?? ''); ?>" 
                       placeholder="+1 (555) 123-4567">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="auto_resolve_days">Auto-Resolve Days</label>
                <input type="number" id="auto_resolve_days" name="auto_resolve_days" class="form-input" 
                       value="<?php echo e($settings['auto_resolve_days'] ?? '7'); ?>" 
                       min="1" max="30">
                <p class="form-hint">Number of days before automatically resolving open tickets</p>
            </div>
        </div>
        
        <!-- Notification Settings -->
        <div class="settings-section">
            <h3 class="settings-section-title">
                <i class="fas fa-bell" style="color: var(--warning); margin-right: var(--space-2);"></i>
                Notifications
            </h3>
            
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" id="email_notifications" name="email_notifications" 
                           <?php echo ($settings['email_notifications'] ?? '1') === '1' ? 'checked' : ''; ?>>
                    <label for="email_notifications" class="form-label" style="margin-bottom: 0;">
                        Enable Email Notifications
                    </label>
                </div>
                <p class="form-hint">Send email notifications for new support tickets and responses</p>
            </div>
        </div>
        
        <!-- Portal Display Settings -->
        <div class="settings-section">
            <h3 class="settings-section-title">
                <i class="fas fa-palette" style="color: var(--gold); margin-right: var(--space-2);"></i>
                Portal Display Settings
            </h3>
            
            <div class="form-group">
                <label class="form-label" for="church_name">Church Name</label>
                <input type="text" id="church_name" name="church_name" class="form-input" 
                       value="<?php echo e($settings['church_name'] ?? ''); ?>" 
                       placeholder="Bright Light Ministry Int'l">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="portal_subtitle">Portal Subtitle</label>
                <input type="text" id="portal_subtitle" name="portal_subtitle" class="form-input" 
                       value="<?php echo e($settings['portal_subtitle'] ?? ''); ?>" 
                       placeholder="Partners Portal">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="church_tagline">Church Tagline</label>
                <textarea id="church_tagline" name="church_tagline" class="form-textarea" 
                          placeholder="Together, we are advancing the gospel with every seed sown."><?php echo e($settings['church_tagline'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="tax_note">Tax Deductible Note</label>
                <textarea id="tax_note" name="tax_note" class="form-textarea" 
                          placeholder="Your gift may be tax-deductible where applicable."><?php echo e($settings['tax_note'] ?? ''); ?></textarea>
            </div>
        </div>
        
        <!-- Bible Verse Settings -->
        <div class="settings-section">
            <h3 class="settings-section-title">
                <i class="fas fa-book-open" style="color: var(--success); margin-right: var(--space-2);"></i>
                Homepage Bible Verse
            </h3>
            
            <div class="form-group">
                <label class="form-label" for="bible_verse_text">Verse Text</label>
                <textarea id="bible_verse_text" name="bible_verse_text" class="form-textarea" rows="4"
                          placeholder="Each of you should give what you have decided in your heart to give..."><?php echo e($settings['bible_verse_text'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="bible_verse_ref">Verse Reference</label>
                <input type="text" id="bible_verse_ref" name="bible_verse_ref" class="form-input" 
                       value="<?php echo e($settings['bible_verse_ref'] ?? ''); ?>" 
                       placeholder="2 Corinthians 9:7">
            </div>
        </div>
        
        <!-- Footer Links -->
        <div class="settings-section">
            <h3 class="settings-section-title">
                <i class="fas fa-link" style="color: var(--info); margin-right: var(--space-2);"></i>
                Footer Links
            </h3>
            
            <div class="form-group">
                <label class="form-label" for="footer_privacy_url">Privacy Policy URL</label>
                <input type="text" id="footer_privacy_url" name="footer_privacy_url" class="form-input" 
                       value="<?php echo e($settings['footer_privacy_url'] ?? ''); ?>" 
                       placeholder="/privacy">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="footer_terms_url">Terms of Service URL</label>
                <input type="text" id="footer_terms_url" name="footer_terms_url" class="form-input" 
                       value="<?php echo e($settings['footer_terms_url'] ?? ''); ?>" 
                       placeholder="/terms">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="footer_contact_url">Contact Page URL</label>
                <input type="text" id="footer_contact_url" name="footer_contact_url" class="form-input" 
                       value="<?php echo e($settings['footer_contact_url'] ?? ''); ?>" 
                       placeholder="/contact">
            </div>
        </div>
        
        <!-- Impact Metrics -->
        <div class="settings-section">
            <h3 class="settings-section-title">
                <i class="fas fa-chart-bar" style="color: var(--gold); margin-right: var(--space-2);"></i>
                Impact Metrics
            </h3>
            <p class="form-hint" style="margin-bottom: var(--space-4);">These numbers are displayed on the public homepage to show your collective impact.</p>
            
            <div class="form-group">
                <label class="form-label" for="impact_families_fed">Families Fed</label>
                <input type="number" id="impact_families_fed" name="impact_families_fed" class="form-input" 
                       value="<?php echo e($settings['impact_families_fed'] ?? '1247'); ?>" 
                       min="0" placeholder="1247">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="impact_missionaries_sent">Missionaries Sent</label>
                <input type="number" id="impact_missionaries_sent" name="impact_missionaries_sent" class="form-input" 
                       value="<?php echo e($settings['impact_missionaries_sent'] ?? '38'); ?>" 
                       min="0" placeholder="38">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="impact_campuses_planted">Campuses Planted</label>
                <input type="number" id="impact_campuses_planted" name="impact_campuses_planted" class="form-input" 
                       value="<?php echo e($settings['impact_campuses_planted'] ?? '3'); ?>" 
                       min="0" placeholder="3">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="impact_lives_touched">Lives Touched</label>
                <input type="text" id="impact_lives_touched" name="impact_lives_touched" class="form-input" 
                       value="<?php echo e($settings['impact_lives_touched'] ?? '12K+'); ?>" 
                       placeholder="12K+">
                <p class="form-hint">Can include text like "12K+" or "500+"</p>
            </div>
        </div>
        
        <!-- System Information -->
        <div class="settings-section">
            <h3 class="settings-section-title">
                <i class="fas fa-info-circle" style="color: var(--success); margin-right: var(--space-2);"></i>
                System Information
            </h3>
            
            <div class="detail-row" style="display: flex; justify-content: space-between; padding: var(--space-3) 0; border-bottom: 1px solid rgba(0,0,0,0.05);">
                <span class="detail-label" style="font-size: var(--type-body-sm); color: var(--text-secondary);">PHP Version</span>
                <span class="detail-value" style="font-size: var(--type-body-sm); font-weight: 500; color: var(--dark);"><?php echo phpversion(); ?></span>
            </div>
            <div class="detail-row" style="display: flex; justify-content: space-between; padding: var(--space-3) 0; border-bottom: 1px solid rgba(0,0,0,0.05);">
                <span class="detail-label" style="font-size: var(--type-body-sm); color: var(--text-secondary);">Database</span>
                <span class="detail-value" style="font-size: var(--type-body-sm); font-weight: 500; color: var(--dark);">MySQL</span>
            </div>
            <div class="detail-row" style="display: flex; justify-content: space-between; padding: var(--space-3) 0; border-bottom: 1px solid rgba(0,0,0,0.05);">
                <span class="detail-label" style="font-size: var(--type-body-sm); color: var(--text-secondary);">Timezone</span>
                <span class="detail-value" style="font-size: var(--type-body-sm); font-weight: 500; color: var(--dark);"><?php echo date_default_timezone_get(); ?></span>
            </div>
            <div class="detail-row" style="display: flex; justify-content: space-between; padding: var(--space-3) 0;">
                <span class="detail-label" style="font-size: var(--type-body-sm); color: var(--text-secondary);">Current Time</span>
                <span class="detail-value" style="font-size: var(--type-body-sm); font-weight: 500; color: var(--dark);"><?php echo date('Y-m-d H:i:s'); ?></span>
            </div>
        </div>
    </div>
    
    <div style="margin-top: var(--space-6); display: flex; gap: var(--space-3); justify-content: flex-end;">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i>
            <span class="btn-text">Save Settings</span>
        </button>
    </div>
</form>

<?php
// End the layout
layoutFooter();
?>
