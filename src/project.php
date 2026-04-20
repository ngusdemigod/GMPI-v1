<?php
/**
 * Project Details Page
 * Bright Light Ministry Int'l Partners Portal
 */

session_start();

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/config/' . $class . '.php',
        __DIR__ . '/models/' . $class . '.php'
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

require_once __DIR__ . '/config/CurrencyService.php';
require_once __DIR__ . '/helpers/currency_helpers.php';
require_once __DIR__ . '/helpers/system_settings.php';

$projectId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$projectSlug = trim((string) ($_GET['slug'] ?? ''));
if ($projectId <= 0 && $projectSlug === '') {
    header('Location: projects.php');
    exit;
}

$dbError = false;
$project = null;
$projectModel = null;

try {
    $projectModel = new Project();
    if ($projectSlug !== '') {
        $project = $projectModel->getBySlug($projectSlug);
    }

    if (!$project && $projectId > 0) {
        $project = $projectModel->getById($projectId);
        if ($project && !empty($project['slug']) && $projectSlug === '') {
            header('Location: ' . Project::buildDetailUrl($project));
            exit;
        }
    }
} catch (Exception $e) {
    error_log('Project error: ' . $e->getMessage());
    $dbError = true;
}

if (!$project) {
    header('Location: projects.php');
    exit;
}

$projectId = (int) ($project['project_id'] ?? $project['id'] ?? $projectId);

$activeCurrency = getActiveCurrency();
$currencyService = new CurrencyService();

$userId = $_SESSION['user_id'] ?? null;
$user = null;
if ($userId) {
    try {
        $userModel = new User();
        $user = $userModel->getById($userId);
    } catch (Exception $e) {
        error_log('User lookup error: ' . $e->getMessage());
    }
}

$userInitials = getInitials($user['first_name'] ?? 'D', $user['last_name'] ?? 'A');

$percentage = $project['goal_amount'] > 0
    ? round(($project['current_amount'] / $project['goal_amount']) * 100, 1)
    : 0;
$remaining = max(0, (float) $project['goal_amount'] - (float) $project['current_amount']);
$participantCount = (int) ($project['partner_count'] ?? 0);

$daysLeft = 0;
if (!empty($project['end_date'])) {
    $end = new DateTime($project['end_date']);
    $now = new DateTime();
    $daysLeft = $end >= $now ? $now->diff($end)->days : 0;
}

$recentGifts = [];
$userContribution = 0;
$userContributionCount = 0;

if ($userId) {
    try {
        $userModel = $userModel ?? new User();
        $recentGifts = $userModel->getRecentTransactions(100);
        foreach ($recentGifts as $gift) {
            if ((int) ($gift['project_id'] ?? 0) === $projectId) {
                $userContribution += (float) $gift['amount'];
                $userContributionCount++;
            }
        }
    } catch (Exception $e) {
        error_log('User contribution error: ' . $e->getMessage());
    }
}

$otherProjects = [];
$projectTransactions = [];
$projectMilestones = [];
$topParticipants = [];

try {
    $allProjects = $projectModel->getActiveProjects();
    $otherProjects = array_values(array_filter($allProjects, function ($item) use ($projectId) {
        return (int) $item['project_id'] !== $projectId;
    }));
    $projectTransactions = $projectModel->getRecentContributions($projectId, 5);
    
    // Get milestones from the new project_milestones table
    $projectMilestones = $projectModel->getMilestones($projectId);
    $topParticipants = $projectModel->getTopParticipants($projectId, 4);
} catch (Exception $e) {
    error_log('Project detail queries error: ' . $e->getMessage());
}

$projectMilestones = buildProjectMilestones($project, $projectMilestones, $percentage);

if (empty($topParticipants)) {
    $topParticipants = array_slice($projectTransactions, 0, 4);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?php echo htmlspecialchars($project['title']); ?> - Project Details</title>
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
          serif: ['"Bricolage Grotesque"', 'ui-serif', 'Georgia', 'serif'],
          sans: ['"Inter"', 'ui-sans-serif', 'system-ui'],
        }
      }
    }
  }
</script>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,200..800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  body { font-family: 'Inter', sans-serif; background: #FAF6EF; color: #0F1B2D; }
  .font-serif { font-family: 'Bricolage Grotesque', sans-serif; font-weight: 500; }
  .shine {
    background: linear-gradient(120deg, transparent 30%, rgba(255,255,255,0.15) 50%, transparent 70%);
    background-size: 200% 100%;
    animation: shine 6s linear infinite;
  }
  @keyframes shine {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
  }
  .progress-bar-fill { background: linear-gradient(90deg, #C9A24B, #E7D9B4); }
  .amount-btn.active { background: #0F1B2D; color: #FAF6EF; border-color: #0F1B2D; }
  .divider-ornate { display: flex; align-items: center; gap: 12px; color: #C9A24B; }
  .divider-ornate::before, .divider-ornate::after {
    content: "";
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, transparent, #C9A24B, transparent);
  }
  .pulse-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: #5B7B6A;
    box-shadow: 0 0 0 0 rgba(91,123,106,0.6);
    animation: pulse 2s infinite;
  }
  @keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(91,123,106,0.5); }
    70% { box-shadow: 0 0 0 10px rgba(91,123,106,0); }
    100% { box-shadow: 0 0 0 0 rgba(91,123,106,0); }
  }
  .project-hero-bg {
    background:
      radial-gradient(ellipse at 100% 0%, rgba(201,162,75,0.3), transparent 50%),
      radial-gradient(ellipse at 0% 100%, rgba(91,123,106,0.3), transparent 50%),
      linear-gradient(150deg, #122137 0%, #0F1B2D 50%, #0a1a2e 100%);
  }
  .milestone-line::before {
    content: '';
    position: absolute;
    left: 11px;
    top: 24px;
    bottom: -8px;
    width: 1px;
    background: linear-gradient(180deg, #C9A24B44, transparent);
  }
  .partner-avatar {
    width: 32px;
    height: 32px;
    border-radius: 999px;
    border: 2px solid #FAF6EF;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 600;
    color: #FAF6EF;
  }
  .tab-active { background: #0F1B2D; color: #FAF6EF; }
  @media (min-width: 1024px) {
    .sticky-panel { position: sticky; top: 88px; }
  }
  .bar-animate {
    animation: fillBar 1.2s cubic-bezier(.4,0,.2,1) forwards;
    width: 0;
  }
  @keyframes fillBar { to { width: var(--fill); } }
  .milestone-reached { background: #C9A24B; }
  .milestone-pending { background: #0F1B2D22; }
  .toast {
    position: fixed;
    right: 20px;
    bottom: 20px;
    max-width: 340px;
    padding: 14px 16px;
    border-radius: 14px;
    color: #FAF6EF;
    background: #122137;
    box-shadow: 0 18px 48px rgba(15,27,45,0.25);
    opacity: 0;
    transform: translateY(10px);
    transition: opacity .2s ease, transform .2s ease;
    z-index: 60;
  }
  .toast.show { opacity: 1; transform: translateY(0); }
  .toast.success { background: #5B7B6A; }
  .toast.error { background: #8A3B3B; }
</style>
</head>
<body class="min-h-screen">

<?php include 'header.php'; ?>

<main class="max-w-7xl mx-auto px-5 lg:px-10 py-8 lg:py-10">
  <nav class="flex items-center gap-2 text-sm text-ink/50 mb-6">
    <a href="index.php" class="hover:text-ink">Home</a>
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
    <a href="projects.php" class="hover:text-ink">Projects</a>
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
    <span class="text-ink font-medium"><?php echo htmlspecialchars($project['title']); ?></span>
  </nav>

  <?php if ($dbError): ?>
  <div class="mb-6 bg-gold/10 border border-gold/30 rounded-xl px-4 py-3 flex items-center gap-3 text-sm" role="alert">
    <svg class="w-5 h-5 text-gold flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
    <span class="text-ink/80">Some live project details could not be refreshed from the database.</span>
  </div>
  <?php endif; ?>

  <section class="project-hero-bg rounded-3xl text-cream p-7 md:p-10 lg:p-12 mb-10 lg:mb-12 relative overflow-hidden">
    <div class="absolute inset-0 shine pointer-events-none opacity-50"></div>
    <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-gold/10 blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-0 left-0 w-64 h-64 rounded-full bg-sage/10 blur-3xl pointer-events-none"></div>

    <div class="relative grid gap-8 lg:gap-12 lg:grid-cols-[minmax(0,1fr)_380px] lg:items-start">
      <div class="space-y-8 lg:space-y-10">
        <div class="flex items-center justify-between mb-0 flex-wrap gap-4">
          <a href="javascript:history.back();" class="flex items-center gap-2 text-sm text-cream/70 hover:text-cream transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            Back to Projects
          </a>
          <div class="flex items-center gap-2">
            <span class="flex items-center gap-1.5 text-xs bg-sage/20 text-sage border border-sage/30 px-3 py-1.5 rounded-full font-medium">
              <span class="pulse-dot" style="background:#5B7B6A;"></span>
              <?php echo htmlspecialchars($project['category']); ?> - Ends <?php echo !empty($project['end_date']) ? date('M j, Y', strtotime($project['end_date'])) : 'N/A'; ?>
            </span>
          </div>
        </div>

        <div class="flex items-start gap-5 md:gap-6">
          <div class="w-16 h-16 md:w-18 md:h-18 rounded-2xl bg-white/10 border border-white/15 flex items-center justify-center flex-shrink-0">
            <?php echo getProjectIcon($project['icon'] ?? 'church'); ?>
          </div>
          <div class="pt-1">
            <p class="text-xs uppercase tracking-[0.3em] text-gold/90 mb-3"><?php echo htmlspecialchars($project['category']); ?> Fund - Project</p>
            <h1 class="font-serif text-3xl md:text-4xl lg:text-5xl leading-[1.05]"><?php echo htmlspecialchars($project['title']); ?></h1>
          </div>
        </div>

        <div class="max-w-4xl">
          <div class="flex flex-wrap items-baseline gap-x-3 gap-y-2 mb-4">
            <span class="font-serif text-4xl md:text-5xl text-gold"><?php echo formatCurrency($project['current_amount']); ?></span>
            <span class="text-cream/60 text-lg">of <?php echo formatCurrency($project['goal_amount']); ?></span>
          </div>
          <div class="h-3 bg-white/10 rounded-full overflow-hidden mb-4">
            <div class="progress-bar-fill h-full rounded-full bar-animate" style="--fill: <?php echo $percentage; ?>%;"></div>
          </div>
          <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <span class="text-cream/70"><span class="text-cream font-medium"><?php echo $percentage; ?>%</span> of goal reached</span>
            <span class="text-cream/70"><span class="text-gold font-medium"><?php echo formatCurrency($remaining); ?></span> remaining</span>
          </div>
        </div>

        <div class="pt-1">
          <p class="text-xs uppercase tracking-[0.24em] text-cream/60 mb-4">Overview</p>
          <div class="max-w-4xl space-y-4 text-cream/80 leading-8">
            <p><?php echo nl2br(htmlspecialchars($project['description'] ?? '')); ?></p>
          </div>

          <div class="divider-ornate my-7 md:my-8">
            <span class="text-xs uppercase tracking-[0.3em]">Key details</span>
          </div>

          <div class="grid sm:grid-cols-2 gap-4 md:gap-5">
            <div class="rounded-2xl bg-white/10 p-4 md:p-5 flex gap-4">
              <div class="w-11 h-11 rounded-xl bg-white/10 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
              </div>
              <div class="pt-0.5">
                <p class="text-xs text-cream/60">Start Date</p>
                <p class="font-medium text-base mt-1 text-cream"><?php echo !empty($project['start_date']) ? date('M j, Y', strtotime($project['start_date'])) : 'N/A'; ?></p>
              </div>
            </div>
            <div class="rounded-2xl bg-white/10 p-4 md:p-5 flex gap-4">
              <div class="w-11 h-11 rounded-xl bg-white/10 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 21h18M4 21V10l8-6 8 6v11M9 21v-6h6v6"/></svg>
              </div>
              <div class="pt-0.5">
                <p class="text-xs text-cream/60">End Date</p>
                <p class="font-medium text-base mt-1 text-cream"><?php echo !empty($project['end_date']) ? date('M j, Y', strtotime($project['end_date'])) : 'N/A'; ?></p>
              </div>
            </div>
            <div class="rounded-2xl bg-white/10 p-4 md:p-5 flex gap-4">
              <div class="w-11 h-11 rounded-xl bg-white/10 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
              </div>
              <div class="pt-0.5">
                <p class="text-xs text-cream/60">Goal Amount</p>
                <p class="font-medium text-base mt-1 text-cream"><?php echo formatCurrency($project['goal_amount']); ?></p>
              </div>
            </div>
            <div class="rounded-2xl bg-white/10 p-4 md:p-5 flex gap-4">
              <div class="w-11 h-11 rounded-xl bg-white/10 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 14l9-5-9-5-9 5 9 5zM12 14l9-5v6L12 20 3 15V9l9 5z"/></svg>
              </div>
              <div class="pt-0.5">
                <p class="text-xs text-cream/60">Raised</p>
                <p class="font-medium text-base mt-1 text-cream"><?php echo formatCurrency($project['current_amount']); ?></p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-white text-ink rounded-3xl border border-white/10 p-6 md:p-7 lg:p-8 relative overflow-hidden lg:mt-3">
        <p class="text-xs uppercase tracking-[0.25em] text-ink/50 mb-2">Support this project</p>
        <h2 class="font-serif text-2xl mb-3"><?php echo htmlspecialchars($project['title']); ?></h2>
        <p class="text-sm leading-7 text-ink/65 mb-6">Choose an amount and frequency. This uses the same Paystack flow as the homepage quick-give form.</p>

        <div class="flex items-center justify-between mb-4">
          <div class="flex items-baseline gap-1">
            <span class="font-serif text-3xl text-gold" id="currencySymbol"><?php echo $currencyService->getSymbol($activeCurrency); ?></span>
            <input id="campaignAmount" value="<?php echo $activeCurrency === 'NGN' ? '375000' : '250'; ?>" inputmode="numeric" class="font-serif text-4xl bg-transparent outline-none w-full placeholder-ink/30" />
          </div>
          <select id="projectCurrencySelect" class="text-sm border border-ink/20 rounded-lg px-3 py-2 bg-transparent font-medium">
            <option value="USD" <?= $activeCurrency === 'USD' ? 'selected' : '' ?>>$</option>
            <option value="NGN" <?= $activeCurrency === 'NGN' ? 'selected' : '' ?>>N</option>
          </select>
        </div>

        <!-- USD Amount Buttons -->
        <div class="flex flex-wrap gap-2.5 mb-6 usd-amt-btns" style="<?= $activeCurrency === 'NGN' ? 'display: none;' : '' ?>">
          <button type="button" class="camp-amt-btn px-3 py-1.5 rounded-full border border-ink/20 text-xs hover:bg-ink/5 transition" data-val="100">$100</button>
          <button type="button" class="camp-amt-btn active px-3 py-1.5 rounded-full border border-ink/20 text-xs" data-val="250">$250</button>
          <button type="button" class="camp-amt-btn px-3 py-1.5 rounded-full border border-ink/20 text-xs hover:bg-ink/5 transition" data-val="500">$500</button>
          <button type="button" class="camp-amt-btn px-3 py-1.5 rounded-full border border-ink/20 text-xs hover:bg-ink/5 transition" data-val="1000">$1,000</button>
        </div>
        
        <!-- NGN Amount Buttons -->
        <div class="flex flex-wrap gap-2.5 mb-6 ngn-amt-btns" style="<?= $activeCurrency === 'USD' ? 'display: none;' : '' ?>">
          <button type="button" class="camp-amt-btn px-3 py-1.5 rounded-full border border-ink/20 text-xs hover:bg-ink/5 transition" data-val="150000">₦150,000</button>
          <button type="button" class="camp-amt-btn active px-3 py-1.5 rounded-full border border-ink/20 text-xs" data-val="375000">₦375,000</button>
          <button type="button" class="camp-amt-btn px-3 py-1.5 rounded-full border border-ink/20 text-xs hover:bg-ink/5 transition" data-val="750000">₦750,000</button>
          <button type="button" class="camp-amt-btn px-3 py-1.5 rounded-full border border-ink/20 text-xs hover:bg-ink/5 transition" data-val="1500000">₦1,500,000</button>
        </div>


        <button type="button" id="projGiveBtn" onclick="initiateProjectPayment()" class="group w-full flex items-center justify-center gap-2 bg-gold hover:bg-goldsoft text-deep font-semibold px-5 py-3.5 rounded-xl transition">
          Give <span id="campCta"><?php echo formatCurrency(250); ?></span> Now
          <svg class="w-4 h-4 group-hover:translate-x-1 transition" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
        </button>

        <div class="mt-7 pt-6 border-t border-ink/10">
          <p class="text-xs uppercase tracking-[0.2em] text-gold/80 mb-5">Project impact so far</p>
          <div class="space-y-4">
            <div class="flex justify-between items-baseline">
              <span class="text-sm text-ink/70">Partners</span>
              <span class="font-serif text-xl"><?php echo $participantCount; ?></span>
            </div>
            <div class="flex justify-between items-baseline">
              <span class="text-sm text-ink/70">Raised</span>
              <span class="font-serif text-xl text-gold"><?php echo formatCurrency($project['current_amount']); ?></span>
            </div>
            <div class="flex justify-between items-baseline">
              <span class="text-sm text-ink/70">Goal</span>
              <span class="font-serif text-xl"><?php echo formatCurrency($project['goal_amount']); ?></span>
            </div>
            <div class="h-px bg-ink/10 my-2"></div>
            <div class="flex justify-between items-baseline">
              <span class="text-sm text-ink/70">Project ends</span>
              <span class="text-sm font-medium"><?php echo !empty($project['end_date']) ? date('M j, Y', strtotime($project['end_date'])) : 'N/A'; ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 lg:gap-10">
    <div class="lg:col-span-2 space-y-8 lg:space-y-10">
      <div class="flex gap-1 p-1 bg-ink/5 rounded-2xl w-fit text-sm" id="detailTabs">
        <button type="button" data-dtab="milestones" class="dtab px-5 py-2.5 rounded-xl font-medium transition tab-active">Milestones</button>
        <button type="button" data-dtab="partners" class="dtab px-5 py-2.5 rounded-xl font-medium transition text-ink/60 hover:text-ink">Partners</button>
      </div>

      <div id="tab-milestones" class="tab-content">
        <div class="bg-white rounded-3xl border border-ink/10 p-6 md:p-8">
          <p class="text-xs uppercase tracking-[0.2em] text-ink/50 mb-1">Project Timeline</p>
          <h3 class="font-serif text-xl mb-6">Milestones & progress</h3>
          <div class="space-y-8">
            <?php foreach ($projectMilestones as $index => $milestone): ?>
            <?php
              $status = $milestone['status'] ?? 'upcoming';
              $isReached = $status === 'reached';
              $isCurrent = $status === 'current';
              $wrapperClasses = $isReached ? 'relative milestone-line flex gap-4' : 'relative flex gap-4';
              if ($status === 'upcoming') {
                  $wrapperClasses .= ' opacity-50';
              }
            ?>
            <div class="<?php echo $wrapperClasses; ?>">
              <?php if ($isReached): ?>
              <div class="w-6 h-6 rounded-full milestone-reached flex-shrink-0 flex items-center justify-center mt-0.5">
                <svg class="w-3.5 h-3.5 text-deep" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
              </div>
              <?php elseif ($isCurrent): ?>
              <div class="w-6 h-6 rounded-full bg-gold flex-shrink-0 flex items-center justify-center mt-0.5">
                <div class="pulse-dot" style="width:6px;height:6px;background:#122137;"></div>
              </div>
              <?php else: ?>
              <div class="w-6 h-6 rounded-full milestone-pending flex-shrink-0 border-2 border-dashed border-ink/20 mt-0.5"></div>
              <?php endif; ?>
              <div class="flex-1 <?php echo $index < count($projectMilestones) - 1 ? 'pb-6 border-b border-ink/8' : ''; ?>">
                <div class="flex items-center justify-between mb-1 flex-wrap gap-2">
                  <h4 class="font-serif text-lg"><?php echo htmlspecialchars($milestone['title'] ?? 'Milestone'); ?></h4>
                  <span class="text-xs px-2.5 py-1 rounded-full <?php echo getMilestoneBadgeClass($status); ?>">
                    <?php echo htmlspecialchars($milestone['label'] ?? ucfirst($status)); ?>
                  </span>
                </div>
                <p class="text-sm text-ink/60"><?php echo htmlspecialchars($milestone['description'] ?? ''); ?></p>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div id="tab-partners" class="tab-content hidden">
        <div class="bg-white rounded-3xl border border-ink/10 p-6 md:p-8">
          <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div>
              <p class="text-xs uppercase tracking-[0.2em] text-ink/50 mb-1">Our Partners</p>
              <h3 class="font-serif text-xl"><?php echo $participantCount; ?> faithful givers</h3>
            </div>
            <div class="flex items-center">
              <div class="flex -space-x-2">
                <?php foreach (array_slice($topParticipants, 0, 3) as $index => $partner): ?>
                <div class="partner-avatar" style="<?php echo getPartnerAvatarStyle($index); ?>">
                  <?php echo htmlspecialchars(getInitials($partner['first_name'] ?? 'A', $partner['last_name'] ?? 'N')); ?>
                </div>
                <?php endforeach; ?>
                <?php if ($participantCount > 3): ?>
                <div class="partner-avatar" style="background: linear-gradient(135deg, #C9A24B88, #E7D9B488); color: #0F1B2D;">+<?php echo max(0, $participantCount - 3); ?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <p class="text-xs uppercase tracking-[0.2em] text-ink/40 mb-4">Recent contributions</p>
          <ul class="space-y-4">
            <?php if (!empty($projectTransactions)): ?>
              <?php foreach ($projectTransactions as $tx): ?>
              <li class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-gold to-goldsoft flex items-center justify-center text-deep text-xs font-bold">
                  <?php echo htmlspecialchars(getInitials($tx['first_name'] ?? 'A', $tx['last_name'] ?? 'N')); ?>
                </div>
                <div class="flex-1">
                  <p class="text-sm font-medium"><?php echo htmlspecialchars(trim(($tx['first_name'] ?? 'Anonymous') . ' ' . ($tx['last_name'] ?? ''))); ?></p>
                  <p class="text-xs text-ink/50"><?php echo date('M j, Y', strtotime($tx['transaction_date'])); ?> - <?php echo formatCurrency($tx['amount']); ?></p>
                </div>
                <span class="text-xs bg-gold/15 text-gold px-2.5 py-1 rounded-full"><?php echo htmlspecialchars(getPartnerTierLabel($tx['total_contributed'] ?? $tx['amount'])); ?></span>
              </li>
              <?php endforeach; ?>
            <?php else: ?>
              <li class="text-sm text-ink/60 bg-cream rounded-2xl px-4 py-5">No completed project contributions have been recorded yet.</li>
            <?php endif; ?>
          </ul>

          <button type="button" class="mt-6 w-full border border-ink/10 rounded-xl py-3 text-sm font-medium hover:bg-ink/5 transition" disabled>
            <?php echo !empty($projectTransactions) ? 'Most recent partners shown' : 'Waiting for first partner'; ?>
          </button>
        </div>
      </div>
    </div>

    <aside class="space-y-5">
      <div class="sticky-panel space-y-5">
        <div class="bg-white rounded-3xl border border-ink/10 p-5">
          <p class="text-xs uppercase tracking-[0.2em] text-ink/50 mb-3">Spread the word</p>
          <p class="text-sm text-ink/70 mb-4">Invite others to partner - every share brings us closer to <?php echo formatCurrency($project['goal_amount']); ?>.</p>
          <div class="grid grid-cols-2 gap-2">
            <button type="button" class="flex items-center justify-center gap-2 border border-ink/10 rounded-xl py-2.5 text-sm hover:bg-ink/5 transition">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.4 5.4 3.9 9 4-.9-4.2 4-6.6 7-3.8 1.1 0 3-1.2 3-1.2z"/></svg>
              Twitter
            </button>
            <button type="button" class="flex items-center justify-center gap-2 border border-ink/10 rounded-xl py-2.5 text-sm hover:bg-ink/5 transition">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>
              Facebook
            </button>
            <button type="button" class="flex items-center justify-center gap-2 border border-ink/10 rounded-xl py-2.5 text-sm hover:bg-ink/5 transition col-span-2" onclick="navigator.clipboard && navigator.clipboard.writeText(window.location.href);">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8M16 6l-4-4-4 4M12 2v13"/></svg>
              Copy link
            </button>
          </div>
        </div>
      </div>
    </aside>
  </div>

  <section class="mt-14">
    <div class="divider-ornate mb-8">
      <span class="text-xs uppercase tracking-[0.3em]">Other active projects</span>
    </div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
      <?php foreach (array_slice($otherProjects, 0, 3) as $otherProject): ?>
      <?php $otherPercentage = $otherProject['goal_amount'] > 0 ? round(($otherProject['current_amount'] / $otherProject['goal_amount']) * 100, 0) : 0; ?>
      <a href="<?php echo htmlspecialchars(Project::buildDetailUrl($otherProject)); ?>" class="bg-white rounded-2xl border border-ink/10 p-6 hover:shadow-xl hover:-translate-y-1 transition cursor-pointer">
        <div class="flex items-start justify-between mb-4">
          <div class="w-12 h-12 rounded-xl bg-deep flex items-center justify-center">
            <?php echo getProjectIcon($otherProject['icon'] ?? 'church'); ?>
          </div>
          <span class="text-xs bg-gold/15 text-gold px-2.5 py-1 rounded-full font-medium"><?php echo htmlspecialchars($otherProject['category']); ?></span>
        </div>
        <h4 class="font-serif text-xl mb-2"><?php echo htmlspecialchars($otherProject['title']); ?></h4>
        <p class="text-sm text-ink/60 mb-5"><?php echo htmlspecialchars($otherProject['description'] ?? ''); ?></p>
        <div class="flex items-baseline justify-between mb-2 text-sm">
          <span class="font-serif text-lg"><span class="text-gold"><?php echo formatCurrency($otherProject['current_amount']); ?></span> <span class="text-ink/50 text-sm">of <?php echo formatCurrency($otherProject['goal_amount']); ?></span></span>
          <span class="text-ink/60"><?php echo $otherPercentage; ?>%</span>
        </div>
        <div class="h-2 bg-ink/10 rounded-full overflow-hidden">
          <div class="progress-bar-fill h-full rounded-full" style="width: <?php echo $otherPercentage; ?>%"></div>
        </div>
        <div class="flex justify-between mt-4 text-xs text-ink/60">
          <span><?php echo (int) ($otherProject['partner_count'] ?? 0); ?> partners</span>
          <span>Ends <?php echo !empty($otherProject['end_date']) ? date('M j', strtotime($otherProject['end_date'])) : 'N/A'; ?></span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </section>

  <?php
  $projectPortalSettings = getPortalSettings();
  ?>
  <footer class="mt-14 pt-8 border-t border-ink/10 flex flex-col md:flex-row items-center justify-between gap-4 text-sm text-ink/60">
    <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($projectPortalSettings['church_name']); ?></p>
    <div class="flex items-center gap-5">
      <a href="<?php echo htmlspecialchars($projectPortalSettings['footer_privacy_url'] ?? '#'); ?>" class="hover:text-ink">Privacy</a>
      <a href="<?php echo htmlspecialchars($projectPortalSettings['footer_terms_url'] ?? '#'); ?>" class="hover:text-ink">Terms</a>
      <a href="<?php echo htmlspecialchars($projectPortalSettings['footer_contact_url'] ?? '#'); ?>" class="hover:text-ink">Contact</a>
      <span class="flex items-center gap-1.5">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 11V7a4 4 0 118 0m-8 4a2 2 0 00-2 2v5a2 2 0 002 2h8a2 2 0 002-2v-5a2 2 0 00-2-2h-8z"/></svg>
        PCI-DSS secured
      </span>
    </div>
  </footer>
</main>

<div class="toast" id="projToast"></div>

<script>
  const currSymbol = '<?php echo $currencyService->getSymbol($activeCurrency); ?>';
  const currCode = '<?php echo $activeCurrency; ?>';
  const projectId = <?php echo (int) $projectId; ?>;
  const projectName = <?php echo json_encode($project['title'] ?? ''); ?>;
  const userId = <?php echo $userId ? (int) $userId : 'null'; ?>;
  const isAuthenticated = userId !== null;
  const donorEmail = <?php echo json_encode($user['email'] ?? ''); ?>;
  const userFirstName = <?php echo json_encode($user['first_name'] ?? ''); ?>;
  const userLastName = <?php echo json_encode($user['last_name'] ?? ''); ?>;

  const projBtns = document.querySelectorAll('.camp-amt-btn');
  const projInput = document.getElementById('campaignAmount');
  const projGiveBtn = document.getElementById('projGiveBtn');
  const currencySelect = document.getElementById('projectCurrencySelect');
  const currencySymbolEl = document.getElementById('currencySymbol');

  // Currency data for project page
  const projectCurrencyData = {
    USD: { symbol: '$', defaultAmount: '250', code: 'USD' },
    NGN: { symbol: '₦', defaultAmount: '375000', code: 'NGN' }
  };

  // Handle currency switch
  if (currencySelect) {
    currencySelect.addEventListener('change', function() {
      const selectedCurrency = this.value;
      const data = projectCurrencyData[selectedCurrency];
      const usdBtns = document.querySelectorAll('.usd-amt-btns');
      const ngnBtns = document.querySelectorAll('.ngn-amt-btns');

      // Toggle visibility
      if (selectedCurrency === 'NGN') {
        usdBtns.forEach(el => el.style.display = 'none');
        ngnBtns.forEach(el => el.style.display = '');
      } else {
        usdBtns.forEach(el => el.style.display = '');
        ngnBtns.forEach(el => el.style.display = 'none');
      }

      // Update currency symbol and input
      currencySymbolEl.textContent = data.symbol;
      projInput.value = data.defaultAmount;

      // Update active button
      projBtns.forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.val === data.defaultAmount) {
          btn.classList.add('active');
        }
      });

      // Update global currency code
      window.currCode = data.code;
      window.currSymbol = data.symbol;

      refreshProjectGiveButton();
    });
  }

  function refreshProjectGiveButton() {
    const amount = parseInt(projInput.value, 10) || 0;
    const symbol = window.currSymbol || currSymbol;
    const ctaPrefix = isAuthenticated ? 'Give' : 'Login to Give';
    projGiveBtn.innerHTML =
      `${ctaPrefix} <span id="campCta">${symbol}${amount.toLocaleString()}</span> Now ` +
      `<svg class="w-4 h-4 group-hover:translate-x-1 transition" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14M13 5l7 7-7 7"/></svg>`;
  }

  function showToast(message, type = 'success') {
    const toast = document.getElementById('projToast');
    toast.textContent = message;
    toast.className = `toast ${type} show`;
    window.setTimeout(() => {
      toast.classList.remove('show');
    }, 4000);
  }

  function redirectToLogin(message = 'Please log in to continue your donation.') {
    showToast(message, 'error');
    window.setTimeout(() => {
      window.location.href = 'login.php';
    }, 300);
  }

  document.querySelectorAll('.dtab').forEach((tab) => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.dtab').forEach((item) => {
        item.classList.remove('tab-active');
        item.classList.add('text-ink/60');
      });
      document.querySelectorAll('.tab-content').forEach((panel) => panel.classList.add('hidden'));

      tab.classList.add('tab-active');
      tab.classList.remove('text-ink/60');

      const target = document.getElementById(`tab-${tab.dataset.dtab}`);
      if (target) {
        target.classList.remove('hidden');
      }
    });
  });

  projBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      projBtns.forEach((item) => {
        item.classList.remove('active');
      });
      btn.classList.add('active');
      projInput.value = btn.dataset.val;
      refreshProjectGiveButton();
    });
  });

  projInput.addEventListener('input', refreshProjectGiveButton);
  refreshProjectGiveButton();

  window.setTimeout(() => {
    document.querySelectorAll('.bar-animate').forEach((bar) => {
      bar.style.width = getComputedStyle(bar).getPropertyValue('--fill').trim() || bar.style.getPropertyValue('--fill');
    });
  }, 200);

  async function initiateProjectPayment() {
    if (!isAuthenticated) {
      redirectToLogin();
      return;
    }

    const amount = parseInt(projInput.value, 10) || 0;
    const frequency = 'one-time';
    const email = donorEmail.trim();

    if (amount <= 0) {
      showToast('Please enter a valid donation amount.', 'error');
      return;
    }

    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      showToast('Your account email is required before you can give. Please update your profile or contact support.', 'error');
      return;
    }

    const originalHtml = projGiveBtn.innerHTML;
    projGiveBtn.disabled = true;
    projGiveBtn.innerHTML =
      '<span style="display:inline-block;width:18px;height:18px;border:2px solid rgba(18,33,55,0.25);border-top-color:#122137;border-radius:50%;animation:spin 0.8s linear infinite;vertical-align:middle;margin-right:8px"></span>' +
      'Processing...';

    try {
      const isRecurring = frequency !== 'one-time';
      const endpoint = isRecurring ? 'api/paystack/subscription.php' : 'api/paystack/initialize.php';

      const payload = {
        email,
        amount,
        currency: currCode,
        frequency,
        project_id: projectId,
        category: 'Project Support',
        description: `Support for ${projectName} (${frequency})`,
        first_name: userFirstName,
        last_name: userLastName
      };

      const res = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      if (res.status === 401) {
        const authResult = await res.json().catch(() => null);
        redirectToLogin(authResult?.error || 'Your session has expired. Please log in again.');
        return;
      }

      const contentType = res.headers.get('content-type') || '';
      if (!contentType.includes('application/json')) {
        const raw = await res.text();
        console.error('[Project Donation] Non-JSON response:', raw);
        throw new Error('Server returned an unexpected response. Check PHP error logs.');
      }

      const result = await res.json();

      if (result.code === 'AUTH_REQUIRED') {
        redirectToLogin(result.error || 'Your session has expired. Please log in again.');
        return;
      }

      if (result.success && result.data && result.data.authorization_url) {
        window.location.href = result.data.authorization_url;
        return;
      }

      throw new Error(result.error || result.message || 'Payment initialization failed');
    } catch (err) {
      console.error('[Project Donation] Error:', err);
      showToast(err.message || 'Payment failed. Please try again.', 'error');
      projGiveBtn.innerHTML = originalHtml;
      projGiveBtn.disabled = false;
    }
  }

  (function checkPaymentReturn() {
    const params = new URLSearchParams(window.location.search);
    const reference = params.get('reference') || params.get('trxref');
    if (!reference) {
      return;
    }

    fetch('api/paystack/verify.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ reference })
    })
    .then((response) => response.json())
    .then((result) => {
      if (result.success && result.data && result.data.status === 'success') {
        showToast(`Payment successful! Thank you for supporting ${projectName}.`, 'success');
      } else {
        showToast('Payment could not be verified. Please contact support.', 'error');
      }
      window.history.replaceState({}, document.title, window.location.pathname);
    })
    .catch(() => {
      showToast('Could not verify payment. Please contact support.', 'error');
      window.history.replaceState({}, document.title, window.location.pathname);
    });
  })();

  if (!document.getElementById('proj-spin-style')) {
    const style = document.createElement('style');
    style.id = 'proj-spin-style';
    style.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
    document.head.appendChild(style);
  }
</script>
</body>
</html>
<?php

function getProjectIcon($icon) {
    $icons = [
        'church' => '<svg class="w-8 h-8 text-gold" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 21h18M4 21V10l8-6 8 6v11M9 21v-6h6v6"/></svg>',
        'globe' => '<svg class="w-6 h-6 text-cream" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/></svg>',
        'star' => '<svg class="w-6 h-6 text-deep" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 2l3 7h7l-5.5 4.5L18 21l-6-4.5L6 21l1.5-7.5L2 9h7z"/></svg>',
        'graduation-cap' => '<svg class="w-6 h-6 text-gold" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 14l9-5-9-5-9 5 9 5zM12 14l9-5v6L12 20 3 15V9l9 5z"/></svg>',
        'default' => '<svg class="w-8 h-8 text-gold" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 2v20M6 8h12"/></svg>'
    ];

    return $icons[$icon] ?? $icons['default'];
}

function buildProjectMilestones(array $project, array $savedMilestones, float $percentage): array {
    $timeline = [];

    $timeline[] = [
        'title' => 'Project launched',
        'description' => !empty($project['start_date'])
            ? 'Started on ' . date('M j, Y', strtotime($project['start_date'])) . '.'
            : 'This project has been launched and is accepting contributions.',
        'status' => 'reached',
        'label' => 'Reached'
    ];

    $hasGoalMilestone = false;

    foreach ($savedMilestones as $milestone) {
        $effectiveTarget = (float) ($milestone['effective_target_amount'] ?? $milestone['target_amount'] ?? 0);
        $isComplete = !empty($milestone['is_complete']);
        $progress = (float) ($milestone['progress_percentage'] ?? 0);
        $displayTitle = trim((string) ($milestone['display_title'] ?? $milestone['title'] ?? 'Milestone'));
        $displayLabel = (string) ($milestone['display_label'] ?? ($isComplete ? 'Reached' : ($progress > 0 ? $progress . '% Complete' : 'Upcoming')));
        $rawDescription = trim((string) ($milestone['description'] ?? ''));
        $normalizedDescription = strtolower(preg_replace('/\s+/', ' ', $rawDescription) ?? '');
        $normalizedTitle = strtolower(preg_replace('/\s+/', ' ', $displayTitle) ?? '');
        $fundingCopy = 'Raised ' . formatCurrency($project['current_amount']) . ' toward ' . formatCurrency($effectiveTarget) . ' (' . $progress . '%).';

        if ($normalizedDescription === '' || $normalizedDescription === $normalizedTitle) {
            $description = $fundingCopy;
        } else {
            $description = rtrim($rawDescription, '. ') . '. ' . $fundingCopy;
        }

        $status = $isComplete ? 'reached' : ($progress > 0 ? 'current' : 'upcoming');
        $isGoalMilestone = !empty($milestone['is_goal_reached_milestone']) || (
            (float) ($project['goal_amount'] ?? 0) > 0 && $effectiveTarget >= (float) $project['goal_amount']
        );

        if ($isGoalMilestone) {
            $hasGoalMilestone = true;
        }

        $timeline[] = [
            'title' => $displayTitle !== '' ? $displayTitle : 'Milestone',
            'description' => $description,
            'status' => $status,
            'label' => $displayLabel
        ];
    }

    $timeline[] = [
        'title' => 'Current progress',
        'description' => formatCurrency($project['current_amount']) . ' raised so far, representing ' . $percentage . '% of the goal.',
        'status' => $percentage >= 100 ? 'reached' : 'current',
        'label' => $percentage >= 100 ? 'Reached' : 'Current - ' . $percentage . '%'
    ];

    if (!empty($project['end_date'])) {
        $timeline[] = [
            'title' => 'Project closes',
            'description' => 'Target completion date is ' . date('M j, Y', strtotime($project['end_date'])) . '.',
            'status' => 'upcoming',
            'label' => 'Upcoming'
        ];
    }

    if (!$hasGoalMilestone) {
        $timeline[] = [
            'title' => $percentage >= 100 ? 'Goals Reached' : 'Funding goal',
            'description' => 'Goal set at ' . formatCurrency($project['goal_amount']) . '.',
            'status' => $percentage >= 100 ? 'reached' : 'upcoming',
            'label' => $percentage >= 100 ? 'Goals Reached' : 'Upcoming'
        ];
    }

    return $timeline;
}

function getMilestoneBadgeClass(string $status): string {
    switch ($status) {
        case 'reached':
            return 'bg-sage/10 text-sage';
        case 'current':
            return 'bg-gold/15 text-gold font-medium';
        default:
            return 'bg-ink/8 text-ink/50';
    }
}

function getInitials(string $firstName, string $lastName): string {
    return strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
}

function getPartnerAvatarStyle(int $index): string {
    $styles = [
        'background: linear-gradient(135deg, #C9A24B, #E7D9B4); color: #0F1B2D;',
        'background: linear-gradient(135deg, #5B7B6A, #8aab97);',
        'background: linear-gradient(135deg, #122137, #3a5a7a);'
    ];

    return $styles[$index] ?? $styles[0];
}

function getPartnerTierLabel($totalContribution): string {
    $amount = (float) $totalContribution;

    if ($amount >= 1000) {
        return 'Platinum';
    }
    if ($amount >= 500) {
        return 'Gold';
    }
    if ($amount >= 100) {
        return 'Silver';
    }

    return 'Partner';
}
