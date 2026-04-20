<?php
/**
 * Quick Give Donation Widget
 * Bright Light Ministry Int'l Partnership Portal
 * Redesigned to match reference visual design
 */

// Handle form submission
$categories = ['Tithe', 'Offering', 'Missions'];
$frequencies = ['One-time', 'Weekly', 'Monthly', 'Annually'];
$presets = ['50', '100', '250', '500', '1000', 'Custom'];

$active_category = $_POST['category'] ?? 'Tithe';
$active_amount = $_POST['amount'] ?? '250';
$active_frequency = $_POST['frequency'] ?? 'Weekly';
$active_currency = $_POST['currency'] ?? 'USD';

// Load currency helpers
require_once __DIR__ . '/config/CurrencyService.php';
require_once __DIR__ . '/helpers/currency_helpers.php';
require_once __DIR__ . '/helpers/system_settings.php';

// Get active currency
$activeCurrency = getActiveCurrency();
$currencyService = new CurrencyService();

// Calculate display amount based on currency
$display_amount = convertFromUSD($active_amount, $activeCurrency);
$currency_symbol = $currencyService->getSymbol($activeCurrency);
$currency_code = getCurrencyUiLabel($activeCurrency);

// Handle form submission
$submission_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'give') {
    $submission_message = 'Thank you for your generous gift! Receipt sent to your email.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Give - Bright Light Ministry Int'l</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            padding: 20px 16px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0A111F;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .give-card {
            background: linear-gradient(180deg, #16213E 0%, #0A111F 100%);
            max-width: 400px;
            width: 100%;
            border-radius: 24px;
            padding: 32px 24px 28px;
            position: relative;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
        }
        
        .give-header {
            margin-bottom: 24px;
        }
        
        .give-label {
            text-transform: uppercase;
            color: #C9A24B;
            font-size: 12px;
            letter-spacing: 0.15em;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .give-title {
            font-family: 'EB Garamond', serif;
            font-size: 32px;
            font-weight: 500;
            color: #ffffff;
            line-height: 1.2;
        }
        
        .secure-badge {
            position: absolute;
            top: 28px;
            right: 24px;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            padding: 8px 12px;
            color: #C9A24B;
            background: rgba(201, 162, 75, 0.08);
        }
        
        .currency-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .currency-select {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #fff;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            cursor: pointer;
            outline: none;
            font-weight: 600;
        }
        
        .currency-select option {
            background: #1a1a2e;
            color: #fff;
        }
        
        .exchange-rate {
            background: rgba(201, 162, 75, 0.1);
            color: #C9A24B;
            font-size: 12px;
            padding: 6px 10px;
            border-radius: 8px;
            font-weight: 500;
        }
        
        /* Category Tabs - Pill Container */
        .category-container {
            background: rgba(255, 255, 255, 0.06);
            border-radius: 20px;
            padding: 4px;
            display: flex;
            gap: 4px;
            margin-bottom: 28px;
        }
        
        .category-tab {
            flex: 1;
            border-radius: 16px;
            padding: 10px 12px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
            border: none;
            background: transparent;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .category-tab:hover {
            color: #fff;
        }
        
        .category-tab.active {
            background: #C9A24B;
            color: #0A111F;
            font-weight: 600;
        }
        
        /* Amount Display */
        .amount-display {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            margin: 24px 0;
        }
        
        .amount-left {
            display: flex;
            align-items: baseline;
        }
        
        .amount-symbol {
            font-family: 'EB Garamond', serif;
            font-size: 36px;
            color: #C9A24B;
            line-height: 1;
            margin-right: 2px;
        }
        
        .amount-value {
            font-family: 'EB Garamond', serif;
            font-size: 56px;
            font-weight: 400;
            color: #ffffff;
            line-height: 1;
            letter-spacing: -1px;
        }
        
        .amount-currency {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.5);
            font-weight: 400;
        }
        
        /* Preset Buttons */
        .preset-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 8px;
        }
        
        .preset-grid .preset-btn:nth-child(5),
        .preset-grid .preset-btn:nth-child(6) {
            grid-column: span 1;
        }
        
        .preset-row-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-bottom: 24px;
        }
        
        .preset-btn {
            border-radius: 12px;
            padding: 12px 8px;
            height: 44px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
        }
        
        .preset-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        .preset-btn.active {
            background: #C9A24B;
            color: #0A111F;
            border-color: #C9A24B;
            font-weight: 600;
        }
        
        /* Frequency Grid */
        .frequency-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-bottom: 24px;
        }
        
        .frequency-btn {
            border-radius: 12px;
            height: 48px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
        }
        
        .frequency-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        .frequency-btn.active {
            background: #C9A24B;
            color: #0A111F;
            border-color: #C9A24B;
            font-weight: 600;
        }
        
        /* Payment Method */
        .payment-method {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 24px;
        }
        
        .payment-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .card-type {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 4px;
            letter-spacing: 0.5px;
        }
        
        .card-details {
            display: flex;
            flex-direction: column;
        }
        
        .card-number {
            color: #fff;
            font-size: 14px;
            font-weight: 500;
            letter-spacing: 1px;
        }
        
        .card-expires {
            color: rgba(255, 255, 255, 0.4);
            font-size: 12px;
        }
        
        .change-link {
            color: #C9A24B;
            font-size: 13px;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .change-link:hover {
            color: #f0c96a;
        }
        
        /* CTA Button */
        .give-btn {
            width: 100%;
            background: #C9A24B;
            color: #0A111F;
            font-size: 16px;
            font-weight: 600;
            height: 52px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }
        
        .give-btn:hover {
            background: #e8c84b;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.3);
        }
        
        .give-btn:active {
            transform: translateY(0);
        }
        
        /* Footer */
        .give-footer {
            text-align: center;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.4);
            margin-top: 16px;
            line-height: 1.5;
        }
        
        .success-message {
            background: rgba(46, 204, 113, 0.1);
            border: 1px solid rgba(46, 204, 113, 0.3);
            color: #2ecc71;
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 14px;
            text-align: center;
        }
        
        /* Responsive */
        @media (max-width: 420px) {
            .give-card {
                padding: 28px 20px 24px !important;
                border-radius: 20px;
            }
            
            .give-title {
                font-size: 26px;
            }
            
            .amount-symbol {
                font-size: 32px;
            }
            
            .amount-value {
                font-size: 48px;
            }
            
            .secure-badge {
                top: 24px;
                right: 20px;
                font-size: 10px;
                padding: 6px 10px;
            }
            
            .preset-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 360px) {
            body {
                padding: 12px 8px;
            }
            
            .give-title {
                font-size: 24px;
            }
            
            .amount-symbol {
                font-size: 28px;
            }
            
            .amount-value {
                font-size: 42px;
            }
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
    <form method="POST" class="give-card">
        <div class="secure-badge">
            <span>🔒</span>
            <span>Secure • 256-bit encryption</span>
        </div>
        
        <div class="give-header">
            <div class="give-label">Quick Give</div>
            <div class="give-title">Sow a seed today</div>
        </div>
        
        <?php if ($submission_message): ?>
        <div class="success-message">
            <?= htmlspecialchars($submission_message) ?>
        </div>
        <?php endif; ?>
        
        <div class="currency-row">
            <select name="currency" class="currency-select" id="currencySelect">
                <option value="USD" <?= $activeCurrency === 'USD' ? 'selected' : '' ?>>$</option>
                <option value="NGN" <?= $activeCurrency === 'NGN' ? 'selected' : '' ?>>N</option>
            </select>
            <span class="exchange-rate" id="exchangeRateDisplay"></span>
        </div>
        
        <div class="category-container">
            <?php foreach ($categories as $cat): ?>
                <button 
                    type="submit" 
                    name="category" 
                    value="<?= htmlspecialchars($cat) ?>" 
                    class="category-tab <?= $cat === $active_category ? 'active' : '' ?>">
                    <?= htmlspecialchars($cat) ?>
                </button>
            <?php endforeach; ?>
        </div>
        
        <div class="amount-display">
            <div class="amount-left">
                <span class="amount-symbol"><?= htmlspecialchars($currency_symbol) ?></span>
                <span class="amount-value" id="displayAmount"><?= htmlspecialchars($display_amount) ?></span>
            </div>
            <span class="amount-currency"><?= htmlspecialchars($currency_code) ?></span>
        </div>
        
        <!-- USD Preset Buttons -->
        <div class="preset-grid usd-presets" style="<?= $activeCurrency === 'NGN' ? 'display: none;' : '' ?>">
            <button type="submit" name="amount" value="50" class="preset-btn <?= $active_amount === '50' ? 'active' : '' ?>"><?= htmlspecialchars($currency_symbol) ?>50</button>
            <button type="submit" name="amount" value="100" class="preset-btn <?= $active_amount === '100' ? 'active' : '' ?>"><?= htmlspecialchars($currency_symbol) ?>100</button>
            <button type="submit" name="amount" value="250" class="preset-btn <?= $active_amount === '250' ? 'active' : '' ?>"><?= htmlspecialchars($currency_symbol) ?>250</button>
            <button type="submit" name="amount" value="500" class="preset-btn <?= $active_amount === '500' ? 'active' : '' ?>"><?= htmlspecialchars($currency_symbol) ?>500</button>
        </div>
        <div class="preset-row-2 usd-presets" style="<?= $activeCurrency === 'NGN' ? 'display: none;' : '' ?>">
            <button type="submit" name="amount" value="1000" class="preset-btn <?= $active_amount === '1000' ? 'active' : '' ?>"><?= htmlspecialchars($currency_symbol) ?>1,000</button>
            <button type="submit" name="amount" value="Custom" class="preset-btn <?= $active_amount === 'Custom' ? 'active' : '' ?>">Custom</button>
        </div>
        
        <!-- NGN Preset Buttons -->
        <div class="preset-grid ngn-presets" style="<?= $activeCurrency === 'USD' ? 'display: none;' : '' ?>">
            <button type="submit" name="amount" value="75000" class="preset-btn <?= $active_amount === '75000' ? 'active' : '' ?>"><?= htmlspecialchars($currency_symbol) ?>75,000</button>
            <button type="submit" name="amount" value="150000" class="preset-btn <?= $active_amount === '150000' ? 'active' : '' ?>"><?= htmlspecialchars($currency_symbol) ?>150,000</button>
            <button type="submit" name="amount" value="375000" class="preset-btn <?= $active_amount === '375000' ? 'active' : '' ?>"><?= htmlspecialchars($currency_symbol) ?>375,000</button>
            <button type="submit" name="amount" value="750000" class="preset-btn <?= $active_amount === '750000' ? 'active' : '' ?>"><?= htmlspecialchars($currency_symbol) ?>750,000</button>
        </div>
        <div class="preset-row-2 ngn-presets" style="<?= $activeCurrency === 'USD' ? 'display: none;' : '' ?>">
            <button type="submit" name="amount" value="1500000" class="preset-btn <?= $active_amount === '1500000' ? 'active' : '' ?>"><?= htmlspecialchars($currency_symbol) ?>1,500,000</button>
            <button type="submit" name="amount" value="Custom" class="preset-btn <?= $active_amount === 'Custom' ? 'active' : '' ?>">Custom</button>
        </div>
        
        <div class="frequency-grid">
            <?php foreach ($frequencies as $f): ?>
                <button 
                    type="submit" 
                    name="frequency" 
                    value="<?= htmlspecialchars($f) ?>" 
                    class="frequency-btn <?= $f === $active_frequency ? 'active' : '' ?>">
                    <?= htmlspecialchars($f) ?>
                </button>
            <?php endforeach; ?>
        </div>
        
        <div class="payment-method">
            <div class="payment-info">
                <span class="card-type">VISA</span>
                <div class="card-details">
                    <span class="card-number">•••• 4829</span>
                    <span class="card-expires">Expires 09/27</span>
                </div>
            </div>
            <a href="#" class="change-link">Change</a>
        </div>
        
        <button type="submit" name="action" value="give" class="give-btn">
            Give <span id="giveAmount"><?= htmlspecialchars($currency_symbol . $display_amount) ?></span> Now &rarr;
        </button>
        
        <p class="give-footer">
            <?php
            $donatePortalSettings = getPortalSettings();
            echo htmlspecialchars($donatePortalSettings['tax_note'] ?? 'Your gift is tax-deductible. Receipt emailed instantly.');
            ?>
        </p>
    </form>

<script>
// Currency presets data
const currencyPresets = {
    USD: {
        symbol: '$',
        displayCode: '$',
        amounts: [
            { value: '50', label: '$50' },
            { value: '100', label: '$100' },
            { value: '250', label: '$250' },
            { value: '500', label: '$500' },
            { value: '1000', label: '$1,000' },
            { value: 'Custom', label: 'Custom' }
        ]
    },
    NGN: {
        symbol: '₦',
        displayCode: 'N',
        amounts: [
            { value: '75000', label: '₦75,000' },
            { value: '150000', label: '₦150,000' },
            { value: '375000', label: '₦375,000' },
            { value: '750000', label: '₦750,000' },
            { value: '1500000', label: '₦1,500,000' },
            { value: 'Custom', label: 'Custom' }
        ]
    }
};

// Currency change handler
document.getElementById('currencySelect').addEventListener('change', function() {
    const selectedCurrency = this.value;
    const usdPresets = document.querySelectorAll('.usd-presets');
    const ngnPresets = document.querySelectorAll('.ngn-presets');
    
    // Toggle visibility based on selected currency
    if (selectedCurrency === 'NGN') {
        usdPresets.forEach(el => el.style.display = 'none');
        ngnPresets.forEach(el => el.style.display = '');
    } else {
        usdPresets.forEach(el => el.style.display = '');
        ngnPresets.forEach(el => el.style.display = 'none');
    }
    
    // Update the amount display and button labels
    const preset = currencyPresets[selectedCurrency];
    const symbol = preset.symbol;
    
    // Update amount display
    document.getElementById('displayAmount').textContent = '250';
    document.querySelector('.amount-symbol').textContent = symbol;
    document.querySelector('.amount-currency').textContent = preset.displayCode;
    document.getElementById('giveAmount').textContent = symbol + '250';
    
    // Update exchange rate display
    const exchangeRateDisplay = document.getElementById('exchangeRateDisplay');
    if (selectedCurrency === 'NGN') {
        exchangeRateDisplay.textContent = '$1 = ₦1,500';
    } else {
        exchangeRateDisplay.textContent = '';
    }
    
    // Fetch converted amount from API
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
        }
    });
});

// Update exchange rate display on load
const exchangeRateDisplay = document.getElementById('exchangeRateDisplay');
if (exchangeRateDisplay) {
    const symbol = '<?php echo $currencyService->getSymbol('USD'); ?>';
    const targetSymbol = '<?php echo $currencyService->getSymbol($activeCurrency); ?>';
    if ('<?php echo $activeCurrency; ?>' !== 'USD') {
        exchangeRateDisplay.textContent = symbol + '1 = ' + targetSymbol + ' ' + '<?php echo number_format(convertFromUSD(1, $activeCurrency), 0); ?>';
    } else {
        exchangeRateDisplay.textContent = '';
    }
}
</script>
</body>
</html>
