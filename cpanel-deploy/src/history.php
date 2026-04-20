<?php
/**
 * My History Page
 * Displays transaction history for the authenticated user.
 */

require_once __DIR__ . '/bootstrap.php';

requireVerifiedUser();

$userId = (int) $_SESSION['user_id'];
$activeCurrency = getActiveCurrency();
$currencyService = new CurrencyService();
$portalSettings = getPortalSettings();
$churchName = $portalSettings['church_name'];
$portalSubtitle = $portalSettings['portal_subtitle'];

$transactions = [];
$statistics = ['total' => 0, 'count' => 0, 'thisMonth' => 0];
$year = isset($_GET['year']) ? max(2000, (int) $_GET['year']) : (int) date('Y');

try {
    $transactionModel = new Transaction();
    $transactions = $transactionModel->getUserTransactions($userId, $year);

    foreach ($transactions as $tx) {
        $amount = (float) ($tx['amount'] ?? 0);
        $statistics['total'] += $amount;
        $statistics['count']++;

        $txDate = new DateTime($tx['transaction_date']);
        if ($txDate->format('Y-m') === date('Y-m')) {
            $statistics['thisMonth'] += $amount;
        }
    }
} catch (Exception $e) {
    error_log('Error fetching transactions: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>My Giving History - <?php echo htmlspecialchars($churchName . ' ' . $portalSubtitle); ?></title>

<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        cream: '#FAF6EF',
        ink: '#0F1B2D',
        gold: '#C9A24B',
        goldsoft: '#E7D9B4',
        deep: '#122137',
        sage: '#5B7B6A',
      },
      fontFamily: {
        serif: ['"Bricolage Grotesque"', 'ui-serif'],
        sans: ['"Inter"', 'ui-sans-serif'],
      }
    }
  }
}
</script>

<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,200..800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
:root {
  --cream: #FAF6EF;
  --ink: #0F1B2D;
  --gold: #C9A24B;
  --goldsoft: #E7D9B4;
  --deep: #122137;
  --sage: #5B7B6A;
  --text-muted: rgba(15, 27, 45, 0.6);
}

body { font-family: 'Inter', sans-serif; background: var(--cream); }
.font-serif { font-family: 'Bricolage Grotesque', sans-serif; }
.transaction-row { transition: background 0.2s ease, transform 0.2s ease; }
.transaction-row:hover { background: rgba(250, 246, 239, 0.7); transform: translateY(-1px); }
.transactions-header { display: none; }
.transaction-card {
  display: grid;
  gap: 14px;
  padding: 18px 20px;
  border-bottom: 1px solid rgba(15, 27, 45, 0.06);
}
.transaction-card:last-child { border-bottom: none; }
.transaction-detail-label {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: rgba(15, 27, 45, 0.45);
  margin-bottom: 4px;
}
.transaction-detail-value {
  font-size: 14px;
  color: rgba(15, 27, 45, 0.78);
}
.transaction-main {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}
.transaction-main-amount { text-align: right; flex-shrink: 0; }
.transaction-amount {
  font-family: 'Bricolage Grotesque', sans-serif;
  font-size: 22px;
  line-height: 1;
  color: var(--ink);
}
.transaction-mobile-details {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

@media (max-width: 767px) {
  main {
    padding-left: 16px !important;
    padding-right: 16px !important;
    padding-top: 24px !important;
    padding-bottom: 32px !important;
  }
}

@media (min-width: 768px) {
  .transactions-header { display: grid; }
}
</style>
</head>

<body class="min-h-screen text-ink">
<?php include 'header.php'; ?>

<main class="max-w-7xl mx-auto px-6 py-10">
  <section class="mb-10">
    <h1 class="font-serif text-4xl md:text-5xl">Your Giving History</h1>
    <p class="text-ink/60 mt-2">Every completed transaction tied to your authenticated account appears here.</p>
  </section>

  <div class="grid gap-4 md:grid-cols-3 mb-8">
    <div class="bg-white p-5 rounded-2xl border border-ink/10 shadow-sm">
      <p class="text-xs text-ink/50 uppercase tracking-wide">Total Given</p>
      <h3 class="font-serif text-2xl mt-1">
        <?php echo htmlspecialchars($currencyService->formatAmount($statistics['total'], $activeCurrency)); ?>
      </h3>
    </div>

    <div class="bg-white p-5 rounded-2xl border border-ink/10 shadow-sm">
      <p class="text-xs text-ink/50 uppercase tracking-wide">Transactions</p>
      <h3 class="font-serif text-2xl mt-1"><?php echo (int) $statistics['count']; ?></h3>
    </div>

    <div class="bg-white p-5 rounded-2xl border border-ink/10 shadow-sm">
      <p class="text-xs text-ink/50 uppercase tracking-wide">This Month</p>
      <h3 class="font-serif text-2xl mt-1">
        <?php echo htmlspecialchars($currencyService->formatAmount($statistics['thisMonth'], $activeCurrency)); ?>
      </h3>
    </div>
  </div>

  <div class="bg-white border border-ink/10 rounded-2xl p-4 flex flex-wrap gap-3 items-center justify-between mb-6">
    <input
      id="search"
      type="text"
      placeholder="Search transactions..."
      class="px-4 py-2 rounded-xl border border-ink/10 text-sm w-full md:w-64 focus:outline-none focus:border-gold"
    />

    <div class="flex gap-2 flex-wrap">
      <button class="filter px-4 py-2 border border-ink/10 rounded-full text-sm bg-gold text-white" data-type="all">All</button>
      <button class="filter px-4 py-2 border border-ink/10 rounded-full text-sm hover:bg-cream/50" data-type="Tithe">Tithe</button>
      <button class="filter px-4 py-2 border border-ink/10 rounded-full text-sm hover:bg-cream/50" data-type="Offering">Offering</button>
      <button class="filter px-4 py-2 border border-ink/10 rounded-full text-sm hover:bg-cream/50" data-type="Missions">Missions</button>
      <button class="filter px-4 py-2 border border-ink/10 rounded-full text-sm hover:bg-cream/50" data-type="Building">Building</button>
      <button class="filter px-4 py-2 border border-ink/10 rounded-full text-sm hover:bg-cream/50" data-type="Scholarship">Scholarship</button>
    </div>

    <div class="flex gap-2 items-center flex-wrap">
      <input id="date" type="date" class="px-3 py-2 border border-ink/10 rounded-lg text-sm"/>
      <select id="yearFilter" class="px-3 py-2 border border-ink/10 rounded-lg text-sm">
        <?php for ($offset = 0; $offset <= 2; $offset++): ?>
          <?php $optionYear = (int) date('Y') - $offset; ?>
          <option value="<?php echo $optionYear; ?>" <?php echo $optionYear === $year ? 'selected' : ''; ?>>
            <?php echo $optionYear; ?>
          </option>
        <?php endfor; ?>
      </select>
    </div>
  </div>

  <div class="bg-white rounded-3xl border border-ink/10 overflow-hidden shadow-sm">
    <div class="transactions-header grid-cols-12 px-6 py-4 text-xs uppercase text-ink/50 border-b border-ink/10">
      <span class="col-span-3">Type</span>
      <span class="col-span-3">Date</span>
      <span class="col-span-3">Method</span>
      <span class="col-span-3 text-right">Amount</span>
    </div>
    <div id="transactions"></div>
  </div>

  <div id="emptyState" class="hidden text-center py-16">
    <svg class="w-16 h-16 mx-auto text-ink/20 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
    </svg>
    <p class="text-ink/50 text-lg">No transactions found.</p>
    <p class="text-ink/40 text-sm mt-2">Try adjusting your filters or complete a new donation.</p>
  </div>

  <div id="receiptModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl">
      <div class="sticky top-0 bg-white border-b border-ink/10 px-6 py-4 flex justify-between items-center rounded-t-3xl">
        <h3 class="font-serif text-xl">Transaction Receipt</h3>
        <button id="closeModal" class="text-ink/50 hover:text-ink transition-colors">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <div id="receiptContent" class="p-6"></div>

      <div class="sticky bottom-0 bg-cream/50 border-t border-ink/10 px-6 py-4 flex gap-3 rounded-b-3xl">
        <button id="printReceipt" class="flex-1 bg-gold text-deep px-4 py-2 rounded-xl font-medium hover:bg-goldsoft transition-colors">
          Print Receipt
        </button>
        <button id="downloadReceipt" class="flex-1 bg-white border border-ink/10 text-ink px-4 py-2 rounded-xl font-medium hover:bg-cream/50 transition-colors">
          Download PDF
        </button>
      </div>
    </div>
  </div>

  <style id="print-styles">
  @media print {
    body * { visibility: hidden; }
    #receiptModal { position: static; background: white; }
    #receiptModal * { visibility: visible; }
    #receiptContent, #receiptContent * { visibility: visible; }
    #receiptContent { position: absolute; left: 0; top: 0; width: 100%; }
  }
  </style>
</main>

<script>
const transactions = <?php echo json_encode($transactions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const activeCurrency = <?php echo json_encode($activeCurrency); ?>;
const defaultCurrencySymbol = <?php echo json_encode($currencyService->getSymbol($activeCurrency)); ?>;
const churchName = <?php echo json_encode($churchName); ?>;
const portalSubtitle = <?php echo json_encode($portalSubtitle); ?>;

let filtered = [...transactions];
let currentFilter = 'all';
let currentSearch = '';
let currentDate = '';

const categoryInfo = {
  Tithe: { label: 'Tithe', color: 'bg-deep text-white' },
  Offering: { label: 'Offering', color: 'bg-gold text-deep' },
  Missions: { label: 'Missions', color: 'bg-sage text-white' },
  Building: { label: 'Building Fund', color: 'bg-goldsoft text-deep' },
  Scholarship: { label: 'Scholarship', color: 'bg-ink text-gold' },
  General: { label: 'General', color: 'bg-ink/10 text-ink' }
};

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function getTransactionCurrency(tx) {
  return (tx.paystack_currency || tx.currency || activeCurrency || 'USD').toUpperCase();
}

function getTransactionSymbol(tx) {
  const symbols = { USD: '$', NGN: '₦', GBP: '£', EUR: '€' };
  return symbols[getTransactionCurrency(tx)] || defaultCurrencySymbol || (getTransactionCurrency(tx) + ' ');
}

function formatAmount(tx) {
  return `${getTransactionSymbol(tx)}${parseFloat(tx.amount || 0).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })}`;
}

function getMethodLabel(tx) {
  if (tx.card_type) {
    return `${tx.card_type} •••• ${tx.last_four_digits || '****'}`;
  }
  if (tx.channel) {
    return tx.channel.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
  }
  if (tx.payment_method_id) {
    return 'Bank Transfer';
  }
  return 'Online Payment';
}

function render(data) {
  const container = document.getElementById('transactions');
  const empty = document.getElementById('emptyState');
  container.innerHTML = '';

  if (data.length === 0) {
    empty.classList.remove('hidden');
    return;
  }

  empty.classList.add('hidden');

  data.forEach(tx => {
    const date = new Date(tx.transaction_date);
    const category = categoryInfo[tx.category] || categoryInfo.General;
    const projectTitle = tx.project_title || tx.campaign_title || '';

    const row = document.createElement('div');
    row.className = 'transaction-row transaction-card cursor-pointer';
    row.innerHTML = `
      <div class="transaction-main transaction-main-info">
        <div>
          <span class="inline-block px-3 py-1 rounded-full text-xs font-medium ${category.color}">
            ${escapeHtml(category.label)}
          </span>
          ${projectTitle ? `<div class="text-xs text-ink/40 mt-2 truncate">${escapeHtml(projectTitle)}</div>` : ''}
        </div>
        <div class="transaction-main-amount">
          <div class="transaction-detail-label md:hidden">Amount</div>
          <div class="transaction-amount">${escapeHtml(formatAmount(tx))}</div>
        </div>
      </div>
      <div class="transaction-mobile-details">
        <div class="transaction-date">
          <div class="transaction-detail-label">Date</div>
          <div class="transaction-detail-value">${date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</div>
        </div>
        <div class="transaction-method">
          <div class="transaction-detail-label">Method</div>
          <div class="transaction-detail-value">${escapeHtml(getMethodLabel(tx))}</div>
        </div>
      </div>
    `;
    row.addEventListener('click', () => showReceipt(tx));
    container.appendChild(row);
  });
}

function showReceipt(tx) {
  const date = new Date(tx.transaction_date);
  const projectTitle = tx.project_title || tx.campaign_title || '';
  const receiptContent = `
    <div class="space-y-6">
      <div class="text-center pb-6 border-b border-ink/10">
        <div class="flex items-center justify-center gap-2 mb-2">
          <div class="w-10 h-10 rounded-full bg-deep flex items-center justify-center text-gold font-bold text-lg">
            ${escapeHtml((churchName || 'C').trim().charAt(0) || 'C')}
          </div>
          <h2 class="font-serif text-2xl">${escapeHtml(churchName)}</h2>
        </div>
        <p class="text-ink/50 text-sm">${escapeHtml(portalSubtitle)}</p>
      </div>
      <div class="grid grid-cols-2 gap-4 text-sm">
        <div>
          <p class="text-ink/50 text-xs uppercase tracking-wide">Receipt Number</p>
          <p class="font-mono text-ink">${escapeHtml(tx.transaction_reference || 'N/A')}</p>
        </div>
        <div>
          <p class="text-ink/50 text-xs uppercase tracking-wide">Date</p>
          <p class="text-ink">${date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</p>
        </div>
      </div>
      <div class="bg-gradient-to-br from-deep to-deep/90 rounded-2xl p-6 text-center">
        <p class="text-ink/50 text-xs uppercase tracking-wide mb-1">Total Contribution</p>
        <p class="font-serif text-4xl text-gold">${escapeHtml(formatAmount(tx))}</p>
      </div>
      <div class="space-y-3">
        <div class="flex justify-between py-2 border-b border-ink/5">
          <span class="text-ink/60">Category</span>
          <span class="font-medium">${escapeHtml(tx.category || 'General')}</span>
        </div>
        <div class="flex justify-between py-2 border-b border-ink/5">
          <span class="text-ink/60">Payment Method</span>
          <span class="font-medium">${escapeHtml(getMethodLabel(tx))}</span>
        </div>
        ${projectTitle ? `
          <div class="flex justify-between py-2 border-b border-ink/5">
            <span class="text-ink/60">Project</span>
            <span class="font-medium">${escapeHtml(projectTitle)}</span>
          </div>
        ` : ''}
        <div class="flex justify-between py-2 border-b border-ink/5">
          <span class="text-ink/60">Frequency</span>
          <span class="font-medium">${escapeHtml(tx.frequency || 'One-time')}</span>
        </div>
        <div class="flex justify-between py-2 border-b border-ink/5">
          <span class="text-ink/60">Currency</span>
          <span class="font-medium">${escapeHtml(getTransactionCurrency(tx))}</span>
        </div>
        ${tx.notes ? `
          <div class="py-2">
            <span class="text-ink/60 block mb-1">Notes</span>
            <p class="text-ink/70 text-sm">${escapeHtml(tx.notes)}</p>
          </div>
        ` : ''}
      </div>
      <div class="bg-cream/50 rounded-xl p-4 text-center">
        <p class="text-ink/60 text-sm">Thank you for your generous giving.</p>
        <p class="text-ink/40 text-xs mt-1">This receipt is generated from your authenticated transaction history.</p>
      </div>
    </div>
  `;

  document.getElementById('receiptContent').innerHTML = receiptContent;
  document.getElementById('receiptModal').classList.remove('hidden');
  document.getElementById('receiptModal').classList.add('flex');
  document.body.style.overflow = 'hidden';
}

function applyFilters() {
  filtered = transactions.filter(tx => {
    if (currentFilter !== 'all' && tx.category !== currentFilter) {
      return false;
    }

    if (currentSearch) {
      const haystack = `${tx.category || ''} ${tx.project_title || tx.campaign_title || ''} ${tx.transaction_reference || ''}`.toLowerCase();
      if (!haystack.includes(currentSearch)) {
        return false;
      }
    }

    if (currentDate) {
      const txDate = new Date(tx.transaction_date).toISOString().split('T')[0];
      if (txDate !== currentDate) {
        return false;
      }
    }

    return true;
  });

  render(filtered);
}

document.getElementById('search').addEventListener('input', event => {
  currentSearch = event.target.value.toLowerCase().trim();
  applyFilters();
});

document.querySelectorAll('.filter').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.filter').forEach(item => item.classList.remove('bg-gold', 'text-white'));
    btn.classList.add('bg-gold', 'text-white');
    currentFilter = btn.dataset.type;
    applyFilters();
  });
});

document.getElementById('date').addEventListener('change', event => {
  currentDate = event.target.value;
  applyFilters();
});

document.getElementById('yearFilter').addEventListener('change', event => {
  window.location.href = 'history.php?year=' + encodeURIComponent(event.target.value);
});

document.getElementById('closeModal').addEventListener('click', () => {
  document.getElementById('receiptModal').classList.add('hidden');
  document.getElementById('receiptModal').classList.remove('flex');
  document.body.style.overflow = '';
});

document.getElementById('receiptModal').addEventListener('click', event => {
  if (event.target.id === 'receiptModal') {
    document.getElementById('receiptModal').classList.add('hidden');
    document.getElementById('receiptModal').classList.remove('flex');
    document.body.style.overflow = '';
  }
});

document.getElementById('printReceipt').addEventListener('click', () => window.print());
document.getElementById('downloadReceipt').addEventListener('click', () => {
  alert('PDF download is not wired yet. The receipt shown is sourced from your backend transaction record.');
});

document.addEventListener('keydown', event => {
  if (event.key === 'Escape') {
    document.getElementById('receiptModal').classList.add('hidden');
    document.getElementById('receiptModal').classList.remove('flex');
    document.body.style.overflow = '';
  }
});

render(filtered);
</script>
</body>
</html>
