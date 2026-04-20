<?php
/**
 * All Projects Listing Page
 * Public projects directory backed by database records only.
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

$portalSettings = getPortalSettings();
$churchName = $portalSettings['church_name'];
$portalSubtitle = $portalSettings['portal_subtitle'];
$churchTagline = $portalSettings['church_tagline'];

$userId = $_SESSION['user_id'] ?? null;
$user = null;
if ($userId) {
    try {
        $userModel = new User();
        $user = $userModel->getById($userId);
    } catch (Exception $e) {
        error_log('User fetch error: ' . $e->getMessage());
    }
}

$projects = [];
$dbError = false;

try {
    $projectModel = new Project();
    $projects = $projectModel->getActiveProjects();
} catch (Exception $e) {
    error_log('Projects fetch error: ' . $e->getMessage());
    $dbError = true;
}

$allowedFilters = ['All', 'Urgent', 'Missions', 'Seasonal', 'Scholarship', 'Building', 'General'];
$activeFilter = 'All';
if (isset($_GET['category']) && in_array($_GET['category'], $allowedFilters, true)) {
    $activeFilter = $_GET['category'];
}

$displayProjects = $activeFilter === 'All'
    ? $projects
    : array_values(array_filter($projects, function ($project) use ($activeFilter) {
        return ($project['category'] ?? '') === $activeFilter;
    }));

$totalPartners = array_sum(array_map(function ($project) {
    return (int) ($project['partner_count'] ?? 0);
}, $projects));

$totalRaised = array_sum(array_map(function ($project) {
    return (float) ($project['current_amount'] ?? 0);
}, $projects));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giving Projects - <?php echo htmlspecialchars($churchName . ' ' . $portalSubtitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars('View and support active giving projects at ' . $churchName . '. ' . $churchTagline); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,200..800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --cream: #FAF6EF;
            --ink: #0F1B2D;
            --gold: #C9A24B;
            --goldsoft: #E7D9B4;
            --deep: #122137;
            --sage: #5B7B6A;
            --text-muted: rgba(15,27,45,0.6);
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --radius-2xl: 24px;
            --radius-full: 9999px;
            --shadow-sm: 0 2px 4px rgba(0,0,0,.05);
            --shadow-xl: 0 12px 40px rgba(0,0,0,.15);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--cream);
            color: var(--ink);
            min-height: 100vh;
        }

        .hero {
            background:
                radial-gradient(ellipse at 80% 0%, rgba(201,162,75,.22), transparent 55%),
                radial-gradient(ellipse at 0% 100%, rgba(91,123,106,.25), transparent 55%),
                linear-gradient(135deg, #122137 0%, #0F1B2D 60%, #081220 100%);
            color: var(--cream);
            padding: 60px 24px 52px;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(rgba(201,162,75,.07) 1px, transparent 1px),
                radial-gradient(rgba(15,27,45,.05) 1px, transparent 1px);
            background-size: 24px 24px, 48px 48px;
            background-position: 0 0, 12px 12px;
            pointer-events: none;
        }

        .hero-inner, .main {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
        }

        .hero-eyebrow {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .25em;
            color: rgba(201,162,75,.9);
            margin-bottom: 12px;
        }

        .hero h1 {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-weight: 500;
            font-size: clamp(2rem, 5vw, 3.25rem);
            line-height: 1.05;
            margin-bottom: 14px;
        }

        .hero h1 em { font-style: italic; opacity: .75; }

        .hero-sub {
            font-size: 15px;
            color: rgba(250,246,239,.7);
            max-width: 560px;
            margin-bottom: 28px;
            line-height: 1.7;
        }

        .hero-stats {
            display: flex;
            gap: 32px;
            flex-wrap: wrap;
        }

        .hero-stat-val {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-weight: 500;
            font-size: 1.6rem;
            color: var(--gold);
        }

        .hero-stat-lbl {
            font-size: 11px;
            color: rgba(250,246,239,.55);
            text-transform: uppercase;
            letter-spacing: .15em;
            margin-top: 2px;
        }

        .main {
            padding: 40px 24px 80px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            color: var(--text-muted);
            font-size: 13px;
            margin-bottom: 28px;
            transition: color .2s;
        }

        .back-link:hover { color: var(--ink); }
        .back-link svg { width: 14px; height: 14px; }

        .filter-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 36px;
        }

        .filter-btn {
            padding: 7px 18px;
            border-radius: var(--radius-full);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid rgba(15,27,45,.15);
            background: white;
            color: var(--text-muted);
            text-decoration: none;
            transition: all .2s;
        }

        .filter-btn:hover { border-color: var(--ink); color: var(--ink); }
        .filter-btn.active { background: var(--ink); color: var(--cream); border-color: var(--ink); }
        .filter-count { margin-left: auto; font-size: 13px; color: var(--text-muted); }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }

        .card-link { text-decoration: none; color: inherit; display: block; }

        .card {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid rgba(15,27,45,.09);
            padding: 24px;
            transition: all .25s;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .card-link:hover .card {
            box-shadow: var(--shadow-xl);
            transform: translateY(-4px);
            border-color: rgba(201,162,75,.3);
        }

        .card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            background: var(--deep);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .card-icon svg { width: 22px; height: 22px; color: var(--gold); }
        .card-icon.sage-bg { background: var(--sage); }
        .card-icon.sage-bg svg { color: var(--cream); }
        .card-icon.gold-bg { background: var(--gold); }
        .card-icon.gold-bg svg { color: var(--deep); }

        .badge {
            font-size: 11px;
            padding: 4px 10px;
            border-radius: var(--radius-full);
            font-weight: 500;
        }

        .badge-urgent { background: rgba(91,123,106,.1); color: var(--sage); }
        .badge-missions { background: rgba(201,162,75,.15); color: var(--gold); }
        .badge-seasonal, .badge-scholarship, .badge-general { background: rgba(15,27,45,.08); color: var(--text-muted); }
        .badge-building { background: rgba(18,33,55,.1); color: var(--deep); }

        .card-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-weight: 500;
            font-size: 19px;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .card-desc {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 20px;
            line-height: 1.6;
            flex: 1;
        }

        .card-amounts {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .card-raised {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-weight: 500;
            font-size: 17px;
        }

        .card-raised .gold { color: var(--gold); }
        .card-raised .muted { color: var(--text-muted); font-size: 13px; font-family: 'Inter', sans-serif; }
        .card-pct { color: var(--text-muted); font-size: 13px; }

        .progress-track {
            height: 7px;
            background: rgba(15,27,45,.08);
            border-radius: var(--radius-full);
            overflow: hidden;
            margin-bottom: 14px;
        }

        .progress-fill {
            height: 100%;
            border-radius: var(--radius-full);
            background: linear-gradient(90deg, var(--gold), var(--goldsoft));
            transition: width .6s cubic-bezier(.4,0,.2,1);
        }

        .card-footer {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: var(--text-muted);
        }

        .empty {
            text-align: center;
            padding: 80px 24px;
            grid-column: 1 / -1;
        }

        .empty h3 {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 22px;
            margin-bottom: 8px;
        }

        .empty p { color: var(--text-muted); font-size: 14px; }

        .db-warning {
            background: rgba(201,162,75,.12);
            border: 1px solid rgba(201,162,75,.3);
            border-radius: var(--radius-md);
            padding: 12px 16px;
            margin-bottom: 24px;
            font-size: 13px;
            color: var(--ink);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cta-strip {
            margin-top: 56px;
            background:
                radial-gradient(ellipse at 100% 0%, rgba(201,162,75,.25), transparent 50%),
                linear-gradient(135deg, #122137, #0F1B2D);
            border-radius: var(--radius-2xl);
            padding: 40px;
            color: var(--cream);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
        }

        .cta-strip h2 {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-weight: 500;
            font-size: 1.6rem;
            margin-bottom: 6px;
        }

        .cta-strip p { font-size: 14px; opacity: .7; }

        .cta-btn {
            background: var(--gold);
            color: var(--deep);
            padding: 13px 28px;
            border-radius: var(--radius-md);
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            white-space: nowrap;
            transition: background .2s;
        }

        .cta-btn:hover { background: var(--goldsoft); }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<header class="hero" role="banner">
    <div class="hero-inner">
        <p class="hero-eyebrow"><?php echo htmlspecialchars($churchName); ?> · Giving Projects</p>
        <h1>Where your seed <em>takes root</em></h1>
        <p class="hero-sub"><?php echo htmlspecialchars($churchTagline); ?></p>
        <div class="hero-stats">
            <div>
                <p class="hero-stat-val"><?php echo count($projects); ?></p>
                <p class="hero-stat-lbl">Active projects</p>
            </div>
            <div>
                <p class="hero-stat-val"><?php echo number_format($totalPartners); ?></p>
                <p class="hero-stat-lbl">Faithful partners</p>
            </div>
            <div>
                <p class="hero-stat-val"><?php echo formatCurrency($totalRaised); ?></p>
                <p class="hero-stat-lbl">Raised to date</p>
            </div>
        </div>
    </div>
</header>

<main class="main" id="main-content">
    <a href="index.php" class="back-link">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
        Back to Dashboard
    </a>

    <?php if ($dbError): ?>
    <div class="db-warning" role="alert">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
        Live project data is temporarily unavailable. Please contact your administrator.
    </div>
    <?php endif; ?>

    <div class="filter-bar" role="navigation" aria-label="Category filters">
        <?php foreach ($allowedFilters as $filter): ?>
        <a
            href="projects.php<?php echo $filter !== 'All' ? '?category=' . urlencode($filter) : ''; ?>"
            class="filter-btn<?php echo $activeFilter === $filter ? ' active' : ''; ?>"
            aria-current="<?php echo $activeFilter === $filter ? 'page' : 'false'; ?>"
        ><?php echo htmlspecialchars($filter); ?></a>
        <?php endforeach; ?>
        <span class="filter-count"><?php echo count($displayProjects); ?> project<?php echo count($displayProjects) !== 1 ? 's' : ''; ?></span>
    </div>

    <div class="grid" id="projects-grid" aria-label="Project listing">
        <?php if (empty($displayProjects)): ?>
        <div class="empty">
            <h3>No projects available</h3>
            <p>There are currently no active projects in this category.</p>
        </div>
        <?php else: ?>
        <?php foreach ($displayProjects as $project): ?>
        <?php
            $id = (int) ($project['id'] ?? $project['project_id'] ?? 0);
            $goalAmount = (float) ($project['goal_amount'] ?? 0);
            $currentAmount = (float) ($project['current_amount'] ?? 0);
            $category = $project['category'] ?? 'General';
            $percentage = $goalAmount > 0 ? min((int) round(($currentAmount / $goalAmount) * 100, 0), 100) : 0;
            $endDate = !empty($project['end_date']) ? date('M j, Y', strtotime($project['end_date'])) : 'N/A';
            $iconClass = $category === 'Missions' ? 'sage-bg' : ($category === 'Seasonal' ? 'gold-bg' : '');
            $badgeClass = 'badge-' . strtolower($category ?: 'general');
        ?>
        <a href="<?php echo htmlspecialchars(Project::buildDetailUrl($project)); ?>" class="card-link" aria-label="View <?php echo htmlspecialchars($project['title'] ?? ''); ?> project">
            <article class="card">
                <div class="card-top">
                    <div class="card-icon <?php echo htmlspecialchars($iconClass); ?>">
                        <?php echo getProjectIcon($project['icon'] ?? 'default'); ?>
                    </div>
                    <span class="badge <?php echo htmlspecialchars($badgeClass); ?>"><?php echo htmlspecialchars($category); ?></span>
                </div>

                <h2 class="card-title"><?php echo htmlspecialchars($project['title'] ?? ''); ?></h2>
                <p class="card-desc"><?php echo htmlspecialchars($project['description'] ?? ''); ?></p>

                <div class="card-amounts">
                    <span class="card-raised">
                        <span class="gold"><?php echo formatCurrency($currentAmount); ?></span>
                        <span class="muted"> of <?php echo formatCurrency($goalAmount); ?></span>
                    </span>
                    <span class="card-pct"><?php echo $percentage; ?>%</span>
                </div>

                <div class="progress-track">
                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                </div>

                <div class="card-footer">
                    <span><?php echo (int) ($project['partner_count'] ?? 0); ?> partners</span>
                    <span>Ends <?php echo htmlspecialchars($endDate); ?></span>
                </div>
            </article>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="cta-strip">
        <div>
            <h2>Not sure where to give?</h2>
            <p>Your generosity matters wherever it lands. Begin with whatever is on your heart.</p>
        </div>
        <a href="index.php" class="cta-btn">Give a gift today</a>
    </div>
</main>

</body>
</html>
<?php

function getProjectIcon(string $name): string {
    $icons = [
        'church' => '<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 21h18M4 21V10l8-6 8 6v11M9 21v-6h6v6"/></svg>',
        'globe' => '<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/></svg>',
        'star' => '<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 2l3 7h7l-5.5 4.5L18 21l-6-4.5L6 21l1.5-7.5L2 9h7z"/></svg>',
        'graduation-cap' => '<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 14l9-5-9-5-9 5 9 5zM12 14l9-5v6L12 20 3 15V9l9 5z"/></svg>',
        'default' => '<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 2v20M6 8h12"/></svg>'
    ];

    return $icons[$name] ?? $icons['default'];
}
