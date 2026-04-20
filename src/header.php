<?php
/**
 * Unified Header Component
 * Bright Light Ministry Int'l Partners Portal
 * Include this file in all pages for consistent navigation
 */

// Load currency helpers
require_once __DIR__ . '/config/CurrencyService.php';
require_once __DIR__ . '/helpers/currency_helpers.php';
require_once __DIR__ . '/helpers/system_settings.php';

// Get active currency
$activeCurrency = getActiveCurrency();
$currencyService = new CurrencyService();
$portalSettings = getPortalSettings();
$churchName = $portalSettings['church_name'];
$portalSubtitle = $portalSettings['portal_subtitle'];
?>
<style>
* {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

*::-webkit-scrollbar {
    width: 0;
    height: 0;
    display: none;
}

/* NAV */
.nav {
    position: sticky;
    top: 0;
    z-index: 40;
    backdrop-filter: blur(12px);
    background: rgba(250, 246, 239, 0.8);
    border-bottom: 1px solid rgba(10, 17, 31, 0.1);
}

.nav-content {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 20px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 24px;
}

@media (min-width: 1024px) {
    .nav-content {
        padding: 0 40px;
    }
}

.nav-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    min-width: 0;
    flex: 1 1 auto;
}

.nav-logo-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--deep);
    display: flex;
    align-items: center;
    justify-content: center;
}

.nav-logo-icon svg {
    width: 20px;
    height: 20px;
    color: var(--gold);
}

.nav-logo-copy {
    min-width: 0;
}

.nav-logo-text {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-weight: 500;
    font-size: clamp(0.92rem, 2vw, 1.125rem);
    line-height: 1.05;
    color: var(--ink);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: min(48vw, 280px);
}

.nav-logo-subtitle {
    font-size: 11px;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.nav-links {
    display: none;
    align-items: center;
    gap: 32px;
}

@media (min-width: 768px) {
    .nav-links {
        display: flex;
    }
}

.nav-links a {
    color: rgba(10, 17, 31, 0.7);
    text-decoration: none;
    font-size: 14px;
    transition: color 0.2s;
}

.nav-links a:hover {
    color: var(--ink);
}

.nav-links a.active {
    color: var(--ink);
    font-weight: 500;
}

.nav-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

.nav-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 1px solid rgba(10, 17, 31, 0.15);
    display: none;
    align-items: center;
    justify-content: center;
    background: none;
    cursor: pointer;
    transition: background 0.2s;
}

.nav-btn:hover {
    background: rgba(10, 17, 31, 0.05);
}

@media (min-width: 640px) {
    .nav-btn {
        display: flex;
    }
}

.nav-btn svg {
    width: 16px;
    height: 16px;
    color: var(--ink);
}

.nav-user {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 12px 4px 4px;
    border-radius: var(--radius-full);
    border: 1px solid rgba(10, 17, 31, 0.15);
}

.nav-user-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--gold), var(--sage));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 11px;
    font-weight: 600;
}

.nav-user-name {
    font-size: 14px;
    display: none;
}

@media (min-width: 640px) {
    .nav-user-name {
        display: block;
    }
}

/* Logout Button */
.logout-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: var(--radius-full);
    border: 1px solid rgba(10, 17, 31, 0.15);
    background: transparent;
    color: var(--ink);
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.logout-btn:hover {
    background: rgba(10, 17, 31, 0.05);
    border-color: rgba(10, 17, 31, 0.25);
}

.logout-btn svg {
    width: 16px;
    height: 16px;
}

/* Currency Selector */
.currency-selector {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 12px 4px 8px;
    border-radius: var(--radius-full);
    border: 1px solid rgba(10, 17, 31, 0.15);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.92), rgba(231, 217, 180, 0.55));
    box-shadow: 0 10px 24px rgba(10, 17, 31, 0.06);
    cursor: pointer;
    transition: all 0.2s;
    font-size: 13px;
    color: var(--ink);
    min-height: 40px;
}

.currency-selector:hover {
    background: linear-gradient(135deg, rgba(255, 255, 255, 1), rgba(231, 217, 180, 0.72));
    border-color: rgba(10, 17, 31, 0.22);
    box-shadow: 0 12px 28px rgba(10, 17, 31, 0.1);
}

.currency-selector-shell {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.currency-badge {
    width: 24px;
    height: 24px;
    border-radius: 999px;
    background: rgba(10, 17, 31, 0.08);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    flex-shrink: 0;
}

.currency-labels {
    display: flex;
    flex-direction: column;
    line-height: 1;
    min-width: 0;
}

.currency-label-caption {
    font-size: 10px;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: rgba(10, 17, 31, 0.48);
    margin-bottom: 2px;
}

.currency-label-value {
    font-weight: 600;
    color: var(--ink);
}

.currency-selector select {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    border: none;
    cursor: pointer;
}

.currency-selector {
    position: relative;
}

.currency-selector select option {
    background: white;
    color: var(--ink);
}

.currency-chevron {
    width: 14px;
    height: 14px;
    color: rgba(10, 17, 31, 0.65);
    flex-shrink: 0;
}

@media (max-width: 640px) {
    .nav-content {
        gap: 8px;
        padding: 0 14px;
    }

    .nav-logo {
        gap: 10px;
    }

    .nav-logo-icon {
        width: 32px;
        height: 32px;
    }

    .nav-logo-text {
        max-width: min(44vw, 180px);
    }

    .nav-logo-subtitle {
        font-size: 10px;
        letter-spacing: 0.15em;
    }

    .currency-selector {
        padding: 4px 10px 4px 6px;
        min-height: 36px;
    }

    .currency-label-caption {
        display: none;
    }

    .currency-label-value {
        font-size: 12px;
    }

    .logout-btn {
        padding: 8px 12px;
    }
}
</style>
<!-- NAV -->
<nav class="nav">
    <div class="nav-content">
        <a href="index.php" class="nav-logo">
            <div class="nav-logo-icon" style="background: white; padding: 4px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <img src="../admin/BRIGHT-LIGHT-logo.png" alt="Church Logo" style="width: 28px; height: 28px; object-fit: contain; border-radius: 4px;">
            </div>
            <div class="nav-logo-copy">
                <div class="nav-logo-text"><?php echo htmlspecialchars($churchName); ?></div>
                <div class="nav-logo-subtitle"><?php echo htmlspecialchars($portalSubtitle); ?></div>
            </div>
        </a>
    
        <div class="nav-links">
            <a href="index.php" <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'class="active"' : ''; ?>>Give</a>
            <a href="projects.php" <?php echo basename($_SERVER['PHP_SELF']) === 'projects.php' ? 'class="active"' : ''; ?>>Projects</a>
            <a href="history.php" <?php echo basename($_SERVER['PHP_SELF']) === 'history.php' ? 'class="active"' : ''; ?>>My History</a>
            <a href="support.php" <?php echo basename($_SERVER['PHP_SELF']) === 'support.php' ? 'class="active"' : ''; ?>>Support</a>
        </div>
        
        <div class="nav-actions">
            <!-- Currency Selector -->
            <div class="currency-selector" id="currencySelector">
                <div class="currency-selector-shell">
                    <span class="currency-badge"><?php echo $currencyService->getFlag($activeCurrency); ?></span>
                    <span class="currency-labels">
                        <span class="currency-label-caption">Currency</span>
                        <span class="currency-label-value" id="currencySelectLabel">
                            <?php echo htmlspecialchars(getCurrencyUiLabel($activeCurrency)); ?>
                        </span>
                    </span>
                </div>
                <select id="currencySelect" aria-label="Select currency">
                    <?php echo getCurrencyOptions($activeCurrency); ?>
                </select>
                <svg class="currency-chevron" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M6 9l6 6 6-6"/>
                </svg>
            </div>
            
            <?php if (isset($user) && $user): ?>
            <a href="logout.php" class="logout-btn">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                <span class="nav-user-name">Logout</span>
            </a>
            <?php else: ?>
            <a href="login.php" class="logout-btn">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 003 3h6a3 3 0 003-3V7a3 3 0 00-3-3H8a3 3 0 00-3 3v1"/>
                </svg>
                <span class="nav-user-name">Login</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<script>
// Currency selector JavaScript
(function() {
    const currencySelect = document.getElementById('currencySelect');
    const currencySelectLabel = document.getElementById('currencySelectLabel');
    if (!currencySelect) return;

    const updateCurrencyLabel = function() {
        const selectedOption = currencySelect.options[currencySelect.selectedIndex];
        if (!selectedOption || !currencySelectLabel) {
            return;
        }

        currencySelectLabel.textContent =
            selectedOption.dataset.displayLabel || selectedOption.textContent.trim();
    };

    updateCurrencyLabel();
    
    currencySelect.addEventListener('change', function() {
        const selectedCurrency = this.value;
        updateCurrencyLabel();
        
        // Send AJAX request to set currency
        fetch('api/currency.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=set_currency&currency=' + encodeURIComponent(selectedCurrency)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload page to show converted amounts
                window.location.reload();
            } else {
                console.error('Failed to set currency:', data.message);
            }
        })
        .catch(error => {
            console.error('Error setting currency:', error);
        });
    });
})();
</script>
