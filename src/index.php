<?php
/**
 * Church Financial Partnership System
 * Bright Light Ministry Int'l Partnership Portal
 * Main Dashboard Entry Point
 * Redesigned to match reference design
 */

require_once __DIR__ . '/bootstrap.php';

// Configuration
define('APP_VERSION', '1.0.0');

// Active currency info

// Get active currency
$activeCurrency = getActiveCurrency();
$currencyService = new CurrencyService();
$portalSettings = getPortalSettings();
$churchName = $portalSettings['church_name'];
$portalSubtitle = $portalSettings['portal_subtitle'];
$churchTagline = $portalSettings['church_tagline'];
$taxNote = $portalSettings['tax_note'];
define('APP_NAME', $churchName . ' ' . $portalSubtitle);

// Handle API requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    handleApiRequest();
    exit;
}

// Check if user is logged in
$userId = $_SESSION['user_id'] ?? null;
$user = null;
if ($userId) {
    $userModel = new User();
    $user = $userModel->getById($userId);
}

// Get dashboard data
$dashboardData = getDashboardData($userId, $portalSettings);

// Get user initials for avatar
$userInitials = 'DA';
if ($user) {
    $userInitials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,200..800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --cream: #FAF6EF;
            --ink: #0A111F;
            --gold: #C9A24B;
            --goldsoft: #E7D9B4;
            --deep: #16213E;
            --sage: #5B7B6A;
            --text-muted: rgba(15, 27, 45, 0.6);
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.12);
            --shadow-xl: 0 12px 40px rgba(0,0,0,0.15);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --radius-2xl: 24px;
            --radius-full: 9999px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--cream);
            color: var(--ink);
            line-height: 1.6;
            min-height: 100vh;
        }
        
        .font-serif {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-weight: 500;
        }
        
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        @media (min-width: 1024px) {
            .container {
                padding: 0 40px;
            }
        }
        
        /* Grain texture */
        .grain {
            background-image:
                radial-gradient(rgba(201,162,75,0.08) 1px, transparent 1px),
                radial-gradient(rgba(15,27,45,0.05) 1px, transparent 1px);
            background-size: 24px 24px, 48px 48px;
            background-position: 0 0, 12px 12px;
        }
        
        /* Hero gradient for Quick Give card */
        .hero-gradient {
            background:
                radial-gradient(ellipse at 80% 0%, rgba(201,162,75,0.25), transparent 55%),
                radial-gradient(ellipse at 0% 100%, rgba(91,123,106,0.28), transparent 55%),
                linear-gradient(135deg, #122137 0%, #0F1B2D 60%, #081220 100%);
        }
        
        /* Shine animation */
        .shine {
            background: linear-gradient(120deg, transparent 30%, rgba(255,255,255,0.15) 50%, transparent 70%);
            background-size: 200% 100%;
            animation: shine 6s linear infinite;
        }
        
        @keyframes shine {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        /* Progress bar */
        .progress-bar-fill {
            background: linear-gradient(90deg, var(--gold), var(--goldsoft));
        }
        
        /* Pulse dot animation */
        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--sage);
            box-shadow: 0 0 0 0 rgba(91,123,106,0.6);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(91,123,106,0.5); }
            70% { box-shadow: 0 0 0 10px rgba(91,123,106,0); }
            100% { box-shadow: 0 0 0 0 rgba(91,123,106,0); }
        }
        
        /* Divider ornate */
        .divider-ornate {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--gold);
        }
        
        .divider-ornate::before,
        .divider-ornate::after {
            content: "";
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
        }
        
        /* Scroll hide */
        .scroll-hide::-webkit-scrollbar { display: none; }
        .scroll-hide { -ms-overflow-style: none; scrollbar-width: none; }
        
        /* Chip hover effect */
        .chip:hover {
            transform: translateY(-2px);
            transition: transform 0.2s ease;
        }
        
        /* MAIN */
        .main {
            max-width: 1280px;
            margin: 0 auto;
            padding: 24px 20px 40px;
        }
        
        @media (min-width: 1024px) {
            .main {
                padding: 32px 40px 48px;
            }
        }
        
        /* HERO GREETING */
        .hero-greeting {
            margin-bottom: 24px;
        }
        
        .live-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }
        
        .live-text {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: var(--text-muted);
        }
        
        .welcome-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-weight: 500;
            font-size: 32px;
            line-height: 1.1;
            margin-bottom: 8px;
            color: var(--ink);
        }
        
        @media (min-width: 768px) {
            .welcome-title {
                font-size: 44px;
            }
        }
        
        @media (min-width: 1024px) {
            .welcome-title {
                font-size: 52px;
            }
        }
        
        .welcome-title .italic {
            font-style: italic;
            color: rgba(15, 27, 45, 0.7);
        }
        
        .welcome-title .gold {
            color: var(--gold);
        }
        
        .welcome-subtitle {
            margin-top: 12px;
            max-width: 720px;
            font-size: 17px;
            color: rgba(15, 27, 45, 0.7);
        }
        
        /* GRID LAYOUT */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
            align-items: start;
        }
        
        @media (min-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: minmax(0, 7fr) minmax(300px, 3fr);
                gap: 28px;
            }
        }
        
        .left-column {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        /* QUICK GIVE CARD */
        .quick-give-card {
            border-radius: var(--radius-2xl);
            padding: 24px;
            position: relative;
            overflow: hidden;
            color: var(--cream);
        }
        
        @media (min-width: 768px) {
            .quick-give-card {
                padding: 28px;
            }
        }
        
        .quick-give-card .shine {
            position: absolute;
            inset: 0;
            pointer-events: none;
        }
        
        .quick-give-card .glow {
            position: absolute;
            top: -80px;
            right: -80px;
            width: 288px;
            height: 288px;
            border-radius: 50%;
            background: rgba(201, 162, 75, 0.1);
            filter: blur(64px);
        }
        
        .quick-give-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            position: relative;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .quick-give-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.25em;
            color: rgba(201, 162, 75, 0.9);
        }
        
        .quick-give-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-weight: 500;
            font-size: 22px;
        }
        
        @media (min-width: 768px) {
            .quick-give-title {
                font-size: 26px;
            }
        }
        
        /* Amount Display */
        .amount-display {
            display: flex;
            align-items: baseline;
            gap: 4px;
            margin-bottom: 16px;
            position: relative;
            flex-wrap: wrap;
        }
        
        .amount-symbol {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-weight: 500;
            font-size: 28px;
            color: var(--gold);
        }
        
        @media (min-width: 768px) {
            .amount-symbol {
                font-size: 36px;
            }
        }
        
        .amount-input {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-weight: 500;
            font-size: 40px;
            background: transparent;
            border: none;
            outline: none;
            color: var(--cream);
            width: min(100%, 220px);
            min-width: 0;
        }
        
        @media (min-width: 768px) {
            .amount-input {
                font-size: 52px;
            }
        }
        
        .amount-input::placeholder {
            color: rgba(250, 246, 239, 0.3);
        }
        
        .amount-currency {
            font-size: 14px;
            color: rgba(250, 246, 239, 0.6);
            margin-left: 8px;
        }

        @media (max-width: 639px) {
            .amount-symbol {
                font-size: 22px;
            }

            .amount-input {
                font-size: 36px;
                width: 100%;
            }

            .amount-currency {
                width: 100%;
                margin-left: 0;
            }
        }
        
        /* Preset Amounts */
        .preset-amounts {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 20px;
        }

        @media (min-width: 768px) {
            .preset-amounts {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        
        .preset-btn {
            padding: 10px 14px;
            border-radius: var(--radius-lg);
            border: 1px solid rgba(255, 255, 255, 0.2);
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            background: transparent;
            color: var(--cream);
            min-width: 0;
            text-align: center;
        }
        
        .preset-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .preset-btn.active {
            background: var(--ink);
            color: var(--cream);
            border-color: var(--ink);
        }
        
        /* Give Button */
        .give-btn {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--gold);
            color: var(--deep);
            font-size: 16px;
            font-weight: 600;
            padding: 16px;
            border: none;
            border-radius: var(--radius-lg);
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .give-btn:hover {
            background: var(--goldsoft);
        }
        
        .give-btn svg {
            width: 16px;
            height: 16px;
            transition: transform 0.2s;
        }
        
        .give-btn:hover svg {
            transform: translateX(4px);
        }
        
        /* Frequency Options */
        .frequency-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-bottom: 20px;
        }
        
        @media (min-width: 640px) {
            .frequency-options {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        .freq-label {
            cursor: pointer;
        }
        
        .freq-label .peer:checked + .freq-btn {
            background: var(--gold);
            color: var(--deep);
            border-color: var(--gold);
        }
        
        .freq-btn {
            padding: 12px 16px;
            border-radius: var(--radius-lg);
            border: 1px solid rgba(255, 255, 255, 0.15);
            text-align: center;
            font-size: 14px;
            transition: all 0.2s;
            background: rgba(255, 255, 255, 0.05);
            color: rgba(250, 246, 239, 0.8);
        }
        
        .freq-label:hover .freq-btn {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .freq-label .peer:checked + .freq-btn {
            background: var(--gold);
            color: var(--deep);
            border-color: var(--gold);
        }
        
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border-width: 0;
        }
        
        /* Quick Give Note */
        .quick-give-note {
            margin-top: 14px;
            font-size: 12px;
            color: rgba(250, 246, 239, 0.5);
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .supplementary-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
            margin-top: 24px;
        }

        @media (min-width: 1024px) {
            .supplementary-grid {
                grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr) minmax(0, 0.8fr);
                align-items: start;
            }
        }

        .supporting-stack {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        
        .quick-give-note svg {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
        }
        
        /* PROJECTS SECTION */
        .projects-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .projects-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: var(--text-muted);
            margin-bottom: 4px;
        }
        
        .projects-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-weight: 500;
            font-size: 24px;
        }
        
        .view-all {
            font-size: 14px;
            color: rgba(15, 27, 45, 0.7);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .view-all:hover {
            color: var(--ink);
        }
        
        .view-all svg {
            width: 16px;
            height: 16px;
        }
        
        .projects-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            background: #FAF6EF;
            border-radius: 20px;
            padding: 20px;
            min-height: 100%;
        }
        
        @media (min-width: 768px) {
            .projects-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (min-width: 1200px) {
            .projects-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        .project-card-link {
            text-decoration: none;
            color: inherit;
            display: block;
            height: 100%;
        }
        
        .project-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 220px;
            border: none;
            box-shadow: 0 2px 8px rgba(15, 27, 45, 0.04);
        }

        @media (max-width: 639px) {
            .main {
                padding: 20px 16px 32px;
            }

            .hero-greeting {
                margin-bottom: 20px;
            }

            .welcome-title {
                font-size: 28px;
            }

            .welcome-subtitle {
                font-size: 15px;
            }

            .quick-give-card {
                padding: 18px;
            }

            .quick-give-title {
                font-size: 22px;
            }

            .projects-grid {
                padding: 16px;
                gap: 16px;
            }

            .project-card {
                padding: 18px;
                min-height: auto;
            }
        }
        
        .project-card-link {
            text-decoration: none;
            color: inherit;
            display: block;
            height: 100%;
        }
        
        .project-card-link:hover .project-card {
            box-shadow: 0 8px 24px rgba(15, 27, 45, 0.08);
            transform: translateY(-2px);
        }

        .content-empty-state {
            background: rgba(255, 255, 255, 0.82);
            border: 1px dashed rgba(15, 27, 45, 0.14);
            border-radius: 20px;
            padding: 28px 24px;
            color: rgba(15, 27, 45, 0.68);
        }

        .content-empty-state-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 20px;
            margin-bottom: 6px;
            color: var(--ink);
        }

        .content-empty-state-copy {
            font-size: 14px;
        }
        
        .project-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        
        .project-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--deep);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .project-icon svg {
            width: 24px;
            height: 24px;
            color: var(--gold);
        }
        
        .project-icon.sage {
            background: #4A6354;
        }
        
        .project-icon.sage svg {
            color: #FAF6EF;
        }
        
        .project-icon.gold {
            background: #C9A24B;
        }
        
        .project-icon.gold svg {
            color: var(--deep);
        }
        
        .project-tag {
            font-size: 11px;
            padding: 6px 12px;
            border-radius: var(--radius-full);
            font-weight: 500;
            background: rgba(15, 27, 45, 0.08);
            color: rgba(15, 27, 45, 0.6);
        }
        
        .tag-urgent {
            background: rgba(91, 123, 106, 0.12);
            color: var(--sage);
        }
        
        .tag-missions {
            background: rgba(201, 162, 75, 0.12);
            color: #8B7355;
        }
        
        .tag-seasonal {
            background: rgba(15, 27, 45, 0.06);
            color: rgba(15, 27, 45, 0.5);
        }
        
        .tag-scholarship {
            background: rgba(15, 27, 45, 0.06);
            color: rgba(15, 27, 45, 0.5);
        }
        
        .project-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 20px;
            margin-bottom: 10px;
            color: var(--ink);
            line-height: 1.35;
            font-weight: 600;
        }
        
        .project-description {
            font-size: 14px;
            color: #6B7280;
            margin-bottom: 20px;
            line-height: 1.5;
            flex-grow: 1;
        }
        
        .project-amounts {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .project-raised {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 18px;
        }
        
        .project-raised .gold {
            color: #C9A24B;
        }
        
        .project-raised .muted {
            color: #9CA3AF;
            font-size: 14px;
        }
        
        .project-percentage {
            color: #6B7280;
            font-size: 14px;
        }
        
        .project-progress {
            height: 6px;
            background: #E5E7EB;
            border-radius: var(--radius-full);
            overflow: hidden;
            margin-bottom: 16px;
        }
        
        .project-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #C9A24B, #E7D9B4);
            border-radius: var(--radius-full);
            transition: width 0.5s ease;
        }
        
        .project-footer {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #6B7280;
            padding-top: 12px;
        }
        
        /* PARTNERSHIP CARD */
        .partnership-card {
            background: var(--deep);
            color: var(--cream);
            border-radius: var(--radius-2xl);
            padding: 24px;
            position: relative;
            overflow: hidden;
        }
        
        .partnership-card .glow {
            position: absolute;
            top: -64px;
            right: -64px;
            width: 192px;
            height: 192px;
            border-radius: 50%;
            background: rgba(201, 162, 75, 0.2);
            filter: blur(48px);
        }
        
        .partnership-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
            position: relative;
        }
        
        .partnership-badge svg {
            width: 16px;
            height: 16px;
            color: var(--gold);
        }
        
        .partnership-badge-text {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: var(--gold);
        }
        
        .partnership-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 24px;
            margin-bottom: 16px;
            position: relative;
        }
        
        .partnership-stats {
            position: relative;
        }
        
        .partnership-stat {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding: 8px 0;
        }
        
        .partnership-stat-label {
            font-size: 14px;
            color: rgba(250, 246, 239, 0.7);
        }
        
        .partnership-stat-value {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 20px;
            color: var(--gold);
        }
        
        .partnership-stat-value.white {
            color: var(--cream);
        }
        
        .partnership-progress {
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-full);
            overflow: hidden;
            margin-top: 12px;
        }
        
        .partnership-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--gold), var(--goldsoft));
            border-radius: var(--radius-full);
        }
        
        .partnership-note {
            font-size: 12px;
            color: rgba(250, 246, 239, 0.6);
            margin-top: 8px;
        }
        
        .view-statement-btn {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: var(--radius-md);
            color: var(--cream);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 24px;
        }
        
        .view-statement-btn:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        
        /* PLEDGE CARD */
        .pledge-card {
            background: white;
            border-radius: var(--radius-2xl);
            border: 1px solid rgba(15, 27, 45, 0.1);
            padding: 24px;
        }
        
        .pledge-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 4px;
        }
        
        .pledge-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: var(--text-muted);
        }
        
        .pledge-status {
            font-size: 12px;
            background: rgba(91, 123, 106, 0.15);
            color: var(--sage);
            padding: 2px 8px;
            border-radius: var(--radius-full);
        }
        
        .pledge-amount {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 20px;
            margin-top: 8px;
        }
        
        .pledge-progress-header {
            display: flex;
            align-items: baseline;
            gap: 8px;
            margin-top: 16px;
        }
        
        .pledge-percentage {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 30px;
            color: var(--gold);
        }
        
        .pledge-percentage-label {
            font-size: 14px;
            color: var(--text-muted);
        }
        
        .pledge-progress {
            height: 8px;
            background: rgba(15, 27, 45, 0.1);
            border-radius: var(--radius-full);
            overflow: hidden;
            margin-top: 12px;
        }
        
        .pledge-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--gold), var(--goldsoft));
            border-radius: var(--radius-full);
        }
        
        .pledge-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 20px;
        }
        
        .pledge-detail {
            background: var(--cream);
            border-radius: var(--radius-md);
            padding: 12px;
        }
        
        .pledge-detail-label {
            font-size: 12px;
            color: var(--text-muted);
        }
        
        .pledge-detail-value {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 18px;
        }
        
        /* RECENT GIFTS */
        .recent-gifts-card {
            background: white;
            border-radius: var(--radius-2xl);
            border: 1px solid rgba(15, 27, 45, 0.1);
            padding: 24px;
        }
        
        .recent-gifts-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        
        .recent-gifts-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 20px;
        }
        
        .recent-gifts-all {
            font-size: 12px;
            color: var(--text-muted);
            text-decoration: none;
        }
        
        .recent-gifts-all:hover {
            color: var(--ink);
        }
        
        .gift-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .gift-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .gift-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .gift-icon.gold {
            background: rgba(201, 162, 75, 0.15);
        }
        
        .gift-icon.gold svg {
            color: var(--gold);
        }
        
        .gift-icon.sage {
            background: rgba(91, 123, 106, 0.15);
        }
        
        .gift-icon.sage svg {
            color: var(--sage);
        }
        
        .gift-icon.deep {
            background: rgba(18, 33, 55, 0.1);
        }
        
        .gift-icon.deep svg {
            color: var(--deep);
        }
        
        .gift-icon svg {
            width: 16px;
            height: 16px;
        }
        
        .gift-info {
            flex: 1;
        }
        
        .gift-name {
            font-size: 14px;
            font-weight: 500;
        }
        
        .gift-date {
            font-size: 12px;
            color: var(--text-muted);
        }
        
        .gift-amount {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 14px;
        }
        
        /* VERSE BOX */
        .verse-box {
            border-radius: var(--radius-2xl);
            padding: 24px;
            border: 1px solid rgba(201, 162, 75, 0.3);
            background: rgba(231, 217, 180, 0.2);
        }
        
        .verse-icon {
            width: 24px;
            height: 24px;
            color: var(--gold);
            margin-bottom: 12px;
        }
        
        .verse-text {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-style: italic;
            font-size: 18px;
            line-height: 1.4;
            color: var(--ink);
        }
        
        .verse-reference {
            font-size: 12px;
            color: var(--gold);
            margin-top: 12px;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        /* IMPACT SECTION */
        .impact-section {
            margin-top: 48px;
        }
        
        .impact-divider {
            margin-bottom: 32px;
        }
        
        .impact-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        @media (min-width: 768px) {
            .impact-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        .impact-card {
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid rgba(15, 27, 45, 0.1);
            padding: 24px;
            text-align: center;
        }
        
        .impact-number {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 36px;
            color: var(--gold);
        }
        
        .impact-label {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 4px;
        }
        
        /* FOOTER */
        .footer {
            margin-top: 56px;
            padding-top: 32px;
            border-top: 1px solid rgba(15, 27, 45, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            font-size: 14px;
            color: var(--text-muted);
        }
        
        @media (min-width: 768px) {
            .footer {
                flex-direction: row;
            }
        }
        
        .footer-links {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .footer-links a {
            color: var(--text-muted);
            text-decoration: none;
        }
        
        .footer-links a:hover {
            color: var(--ink);
        }
        
        .footer-secure {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .footer-secure svg {
            width: 14px;
            height: 14px;
        }
        
        /* Toast Notification */
        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 16px 24px;
            background: var(--deep);
            color: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast.success {
            border-left: 4px solid var(--sage);
        }
        
        .toast.error {
            border-left: 4px solid #dc3545;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <main class="main">
        <!-- HERO GREETING -->
        <section class="hero-greeting">
            <h1 class="welcome-title">
                Good morning, <?php echo $user ? htmlspecialchars($user['first_name']) : 'Friend'; ?>.<br/>
                <span class="italic">Thank you for </span><span class="gold">partnering</span><span class="italic"> with us.</span>
            </h1>
            <p class="welcome-subtitle">
                Welcome back to the <span class="font-serif italic"><?php echo htmlspecialchars($churchName); ?></span>
                <?php echo htmlspecialchars($portalSubtitle); ?>. <?php echo htmlspecialchars($churchTagline); ?>
            </p>
        </section>
        
        <!-- DASHBOARD GRID -->
        <div class="dashboard-grid">
            <!-- LEFT COLUMN - PROJECTS -->
            <div class="left-column">
                <!-- PROJECTS -->
                <div>
                    <div class="projects-header">
                        <div>
                            <p class="projects-label">Active Projects</p>
                            <h3 class="projects-title">Where your seed is working now</h3>
                        </div>
                        <a href="projects.php" class="view-all">
                            View all
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>
                    
                    <div class="projects-grid">
                        <?php if (!empty($dashboardData['projects'])): ?>
                            <?php foreach ($dashboardData['projects'] as $project): ?>
                                <?php
                                $goalAmount = (float) ($project['goal_amount'] ?? 0);
                                $currentAmount = (float) ($project['current_amount'] ?? 0);
                                $percentage = $goalAmount > 0 ? round(($currentAmount / $goalAmount) * 100) : 0;
                                $category = $project['category'] ?? 'Active';

                                $iconClass = 'sage';
                                $tagClass = 'sage';
                                if ($category === 'Missions') { $iconClass = 'deep'; $tagClass = 'gold'; }
                                if ($category === 'Urgent') { $iconClass = 'gold'; $tagClass = 'ink'; }
                                ?>
                                <a href="<?php echo htmlspecialchars(Project::buildDetailUrl($project)); ?>" class="project-card-link">
                                    <div class="project-card">
                                        <div class="project-header">
                                            <div class="project-icon <?php echo htmlspecialchars($iconClass); ?>">
                                                <?php echo getProjectIcon($project['icon'] ?? ''); ?>
                                            </div>
                                            <span class="project-tag <?php echo htmlspecialchars($tagClass); ?>"><?php echo htmlspecialchars($category); ?></span>
                                        </div>
                                        <h4 class="project-title"><?php echo htmlspecialchars($project['title'] ?? ''); ?></h4>
                                        <p class="project-description"><?php echo htmlspecialchars($project['description'] ?? ''); ?></p>

                                        <div class="project-amounts">
                                            <span class="project-raised">
                                                <span class="gold"><?php echo formatCurrency($currentAmount, false, $activeCurrency); ?></span>
                                                <span class="muted">of <?php echo formatCurrency($goalAmount, false, $activeCurrency); ?></span>
                                            </span>
                                            <span class="project-percentage"><?php echo (int) $percentage; ?>%</span>
                                        </div>

                                        <div class="project-progress">
                                            <div class="project-progress-fill progress-bar-fill" style="width: <?php echo min($percentage, 100); ?>%"></div>
                                        </div>

                                        <div class="project-footer">
                                            <span><?php echo (int) ($project['partner_count'] ?? 0); ?> partners</span>
                                            <span>Ends <?php echo !empty($project['end_date']) ? date('M j', strtotime($project['end_date'])) : 'TBD'; ?></span>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="content-empty-state">
                                <div class="content-empty-state-title">No active projects yet</div>
                                <div class="content-empty-state-copy">Projects added in the admin dashboard will appear here automatically.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- RIGHT COLUMN - QUICK GIVE -->
            <aside class="quick-give-card hero-gradient">
                <div class="shine"></div>
                <div class="glow"></div>
                
                <div class="quick-give-header">
                    <div>
                        <p class="quick-give-label">Quick Give</p>
                        <h2 class="quick-give-title">Give in a moment</h2>
                    </div>
                </div>

                <div class="amount-display">
                    <span class="amount-symbol" id="quickGiveCurrencySymbol"><?php echo $currencyService->getSymbol($activeCurrency); ?></span>
                    <input type="text" id="amount" value="<?php echo $activeCurrency === 'NGN' ? '50000' : '33'; ?>" class="amount-input" />
                    <span class="amount-currency" id="quickGiveCurrencyCode"><?php echo htmlspecialchars(getCurrencyUiLabel($activeCurrency)); ?></span>
                    <select id="quickGiveCurrencySelect" style="margin-left: auto; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: var(--cream); border-radius: 8px; padding: 6px 10px; font-size: 13px; cursor: pointer; outline: none; font-weight: 600;">
                        <option value="USD" <?= $activeCurrency === 'USD' ? 'selected' : '' ?>>$</option>
                        <option value="NGN" <?= $activeCurrency === 'NGN' ? 'selected' : '' ?>>N</option>
                    </select>
                </div>

                <!-- USD Preset Amounts -->
                <div class="preset-amounts usd-presets" style="<?= $activeCurrency === 'NGN' ? 'display: none;' : '' ?>">
                    <button class="preset-btn" data-amt="10">$10</button>
                    <button class="preset-btn" data-amt="25">$25</button>
                    <button class="preset-btn active" data-amt="50">$50</button>
                    <button class="preset-btn" data-amt="100">$100</button>
                    <button class="preset-btn" data-amt="250">$250</button>
                    <button class="preset-btn" data-amt="500">$500</button>
                </div>

                <!-- NGN Preset Amounts -->
                <div class="preset-amounts ngn-presets" style="<?= $activeCurrency === 'USD' ? 'display: none;' : '' ?>">
                    <button class="preset-btn" data-amt="5000">&#8358;5,000</button>
                    <button class="preset-btn" data-amt="20000">&#8358;20,000</button>
                    <button class="preset-btn active" data-amt="50000">&#8358;50,000</button>
                    <button class="preset-btn" data-amt="100000">&#8358;100,000</button>
                    <button class="preset-btn" data-amt="500000">&#8358;500,000</button>
                    <button class="preset-btn" data-amt="1000000">&#8358;1,000,000</button>
                </div>

                <div class="frequency-options" id="freqOptions">
                    <label class="freq-label">
                        <input type="radio" name="freq" value="one-time" class="peer sr-only" />
                        <div class="freq-btn">One-time</div>
                    </label>
                    <label class="freq-label">
                        <input type="radio" name="freq" value="weekly" class="peer sr-only" checked />
                        <div class="freq-btn active">Weekly</div>
                    </label>
                    <label class="freq-label">
                        <input type="radio" name="freq" value="monthly" class="peer sr-only" />
                        <div class="freq-btn">Monthly</div>
                    </label>
                    <label class="freq-label">
                        <input type="radio" name="freq" value="annually" class="peer sr-only" />
                        <div class="freq-btn">Annually</div>
                    </label>
                </div>

                <button class="give-btn" id="giveBtn" onclick="initiatePaystackPayment()">
                    <?php if ($userId): ?>
                    Give <?php echo $currencyService->getSymbol($activeCurrency) . number_format(convertCurrency(50000, 'NGN', $activeCurrency), 0); ?> Now
                    <?php else: ?>
                    Login to Give <?php echo $currencyService->getSymbol($activeCurrency) . number_format(convertCurrency(50000, 'NGN', $activeCurrency), 0); ?> Now
                    <?php endif; ?>
                    <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M5 12h14M13 5l7 7-7 7"/>
                    </svg>
                </button>

                <p class="quick-give-note">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 11V7a4 4 0 118 0m-8 4a2 2 0 00-2 2v5a2 2 0 002 2h8a2 2 0 002-2v-5a2 2 0 00-2-2h-8z"/></svg>
                    <?php if (!$userId): ?>
                    Please log in before starting a donation. Receipts are sent to your account email after checkout.
                    <?php else: ?>
                    <?php echo htmlspecialchars($taxNote); ?> Receipt emailed instantly.
                    <?php endif; ?>
                </p>
            </aside>
        </div>

        <section class="supplementary-grid">
            <div class="supporting-stack">
                <!-- Partnership Status -->
                <div class="partnership-card">
                    <div class="glow"></div>
                    <div class="partnership-badge">
                        <svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3 7h7l-5.5 4.5L18 21l-6-4.5L6 21l1.5-7.5L2 9h7z"/></svg>
                        <span class="partnership-badge-text">Gold Partner</span>
                    </div>
                    <h3 class="partnership-title">Your Partnership</h3>
                    
                    <div class="partnership-stats">
                        <div class="partnership-stat">
                            <span class="partnership-stat-label">Given this year</span>
                            <span class="partnership-stat-value"><?php echo formatCurrency($dashboardData['user']['yearlyGiving'] ?? 0, false, $activeCurrency); ?></span>
                        </div>
                        <div class="partnership-stat">
                            <span class="partnership-stat-label">Consecutive months</span>
                            <span class="partnership-stat-value white"><?php echo (int) ($dashboardData['user']['consecutiveMonths'] ?? 0); ?></span>
                        </div>
                        <div class="partnership-stat">
                            <span class="partnership-stat-label">Next milestone</span>
                            <span class="partnership-stat-value white" style="font-size: 14px;">
                                Platinum &middot; <?php echo formatCurrency($dashboardData['user']['nextTierTarget'] ?? 0, false, $activeCurrency); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="partnership-progress">
                        <div class="partnership-progress-fill" style="width: <?php echo min((int) ($dashboardData['user']['nextTierProgress'] ?? 0), 100); ?>%"></div>
                    </div>
                    <p class="partnership-note">
                        <?php if ($userId): ?>
                            Just <?php echo formatCurrency($dashboardData['user']['amountToNextTier'] ?? 0, false, $activeCurrency); ?> more to reach Platinum this year.
                        <?php else: ?>
                            Sign in to see your giving milestones and partnership history.
                        <?php endif; ?>
                    </p>
                    
                    <button class="view-statement-btn">View Giving Statement</button>
                </div>
                
                <div class="pledge-card">
                    <div class="pledge-header">
                        <span class="pledge-label">2024 Faith Pledge</span>
                        <span class="pledge-status">On track</span>
                    </div>
                    <div class="pledge-amount"><?php echo formatCurrency($dashboardData['user']['pledgeTotal'] ?? 0, false, $activeCurrency); ?> committed</div>
                    
                    <div class="pledge-progress-header">
                        <span class="pledge-percentage"><?php echo $dashboardData['user']['pledgePercentage'] ?? 80; ?>%</span>
                        <span class="pledge-percentage-label">of pledge fulfilled</span>
                    </div>
                    
                    <div class="pledge-progress">
                        <div class="pledge-progress-fill" style="width: <?php echo $dashboardData['user']['pledgePercentage'] ?? 80; ?>%"></div>
                    </div>
                    
                    <div class="pledge-details">
                        <div class="pledge-detail">
                            <span class="pledge-detail-label">Paid</span>
                            <span class="pledge-detail-value"><?php echo formatCurrency($dashboardData['user']['pledgePaid'] ?? 0, false, $activeCurrency); ?></span>
                        </div>
                        <div class="pledge-detail">
                            <span class="pledge-detail-label">Remaining</span>
                            <span class="pledge-detail-value"><?php echo formatCurrency($dashboardData['user']['pledgeRemaining'] ?? 0, false, $activeCurrency); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="recent-gifts-card">
                <div class="recent-gifts-header">
                    <h3 class="recent-gifts-title">Recent gifts</h3>
                    <a href="history.php" class="recent-gifts-all">All</a>
                </div>
                <div class="gift-list">
                    <?php if (!empty($dashboardData['recentGifts'])): ?>
                        <?php foreach ($dashboardData['recentGifts'] as $gift): ?>
                            <?php
                            $giftCategory = $gift['category'] ?? 'Donation';
                            $giftIcon = $gift['project_icon'] ?? ($giftCategory === 'Missions' ? 'globe' : 'church');
                            $giftTone = $giftCategory === 'Tithe' ? 'gold' : ($giftCategory === 'Missions' ? 'sage' : 'deep');
                            ?>
                            <div class="gift-item">
                                <div class="gift-icon <?php echo htmlspecialchars($giftTone); ?>">
                                    <?php echo getProjectIcon($giftIcon); ?>
                                </div>
                                <div class="gift-info">
                                    <div class="gift-name"><?php echo htmlspecialchars($gift['project_title'] ?? $giftCategory); ?></div>
                                    <div class="gift-date">
                                        <?php echo date('M j', strtotime($gift['transaction_date'])); ?>
                                        <?php echo !empty($gift['card_type']) ? ' &middot; ' . htmlspecialchars($gift['card_type']) . ' &bull;' . htmlspecialchars($gift['last_four_digits']) : ''; ?>
                                    </div>
                                </div>
                                <div class="gift-amount"><?php echo formatCurrency((float) ($gift['amount'] ?? 0), false, $activeCurrency); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="content-empty-state">
                            <div class="content-empty-state-title">No giving history yet</div>
                            <div class="content-empty-state-copy">Completed donations will appear here once they are recorded.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="verse-box">
                <svg class="verse-icon" fill="currentColor" viewBox="0 0 24 24"><path d="M9 7H5a2 2 0 00-2 2v4a2 2 0 002 2h2v2a2 2 0 01-2 2H4v2h1a4 4 0 004-4V7zm10 0h-4a2 2 0 00-2 2v4a2 2 0 002 2h2v2a2 2 0 01-2 2h-1v2h1a4 4 0 004-4V7z"/></svg>
                <p class="verse-text"><?php echo htmlspecialchars($portalSettings['bible_verse_text'] ?? ''); ?></p>
                <p class="verse-reference"><?php echo htmlspecialchars($portalSettings['bible_verse_ref'] ?? ''); ?></p>
            </div>
        </section>
        
        <!-- IMPACT STRIP -->
        <section class="impact-section">
            <div class="divider-ornate impact-divider">
                <span class="text-xs uppercase tracking-[0.3em]">Your collective impact</span>
            </div>
            <div class="impact-grid">
                <div class="impact-card">
                    <p class="impact-number"><?php echo number_format($dashboardData['impact']['familiesFed'] ?? 0); ?></p>
                    <p class="impact-label">Families fed</p>
                </div>
                <div class="impact-card">
                    <p class="impact-number"><?php echo number_format($dashboardData['impact']['missionariesSent'] ?? 0); ?></p>
                    <p class="impact-label">Missionaries sent</p>
                </div>
                <div class="impact-card">
                    <p class="impact-number"><?php echo number_format($dashboardData['impact']['campusesPlanted'] ?? 0); ?></p>
                    <p class="impact-label">Campuses planted</p>
                </div>
                <div class="impact-card">
                    <p class="impact-number"><?php echo htmlspecialchars((string) ($dashboardData['impact']['livesTouched'] ?? '0')); ?></p>
                    <p class="impact-label">Lives touched</p>
                </div>
            </div>
        </section>
        
        <!-- FOOTER -->
<footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($churchName); ?></p>
            <div class="footer-links">
                <a href="<?php echo htmlspecialchars($portalSettings['footer_privacy_url'] ?? '#'); ?>">Privacy</a>
                <a href="<?php echo htmlspecialchars($portalSettings['footer_terms_url'] ?? '#'); ?>">Terms</a>
                <a href="<?php echo htmlspecialchars($portalSettings['footer_contact_url'] ?? '#'); ?>">Contact</a>
                <span class="footer-secure">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M12 11V7a4 4 0 118 0m-8 4a2 2 0 00-2 2v5a2 2 0 002 2h8a2 2 0 002-2v-5a2 2 0 00-2-2h-8z"/>
                    </svg>
                    PCI-DSS secured
                </span>
            </div>
        </footer>
    </main>
    
    <!-- Toast Notification -->
    <div class="toast" id="toast"></div>
    
    <script>
        /* ============================================================
         * QUICK GIVE — Payment integration
         * Supports one-time (initialize.php) and recurring (subscription.php)
         * ============================================================ */

        const amountBtns  = document.querySelectorAll('.preset-btn');
        const amountInput = document.getElementById('amount');
        const giveBtn     = document.getElementById('giveBtn');
        const currencySelect = document.getElementById('quickGiveCurrencySelect');
        const currencySymbolEl = document.getElementById('quickGiveCurrencySymbol');
        const currencyCodeEl = document.getElementById('quickGiveCurrencyCode');
        let currSymbol  = '<?php echo $currencyService->getSymbol($activeCurrency); ?>';
        let currCode    = '<?php echo $activeCurrency; ?>';
        const userId      = <?php echo $userId ? (int) $userId : 'null'; ?>;
        const isAuthenticated = userId !== null;
        const donorEmail  = '<?php echo htmlspecialchars($user["email"] ?? "", ENT_QUOTES); ?>';
        const quickGiveCategory = 'General';

        // Currency data for quick give
        const quickGiveCurrencyData = {
            USD: { symbol: '$', defaultAmount: '50', code: 'USD', displayCode: '$' },
            NGN: { symbol: '₦', defaultAmount: '50000', code: 'NGN', displayCode: 'N' }
        };

        // Handle currency switch
        if (currencySelect) {
            currencySelect.addEventListener('change', function() {
                const selectedCurrency = this.value;
                const data = quickGiveCurrencyData[selectedCurrency];
                const usdPresets = document.querySelectorAll('.usd-presets');
                const ngnPresets = document.querySelectorAll('.ngn-presets');

                // Toggle visibility
                if (selectedCurrency === 'NGN') {
                    usdPresets.forEach(el => el.style.display = 'none');
                    ngnPresets.forEach(el => el.style.display = '');
                } else {
                    usdPresets.forEach(el => el.style.display = '');
                    ngnPresets.forEach(el => el.style.display = 'none');
                }

                // Update currency symbol and input
                currencySymbolEl.textContent = data.symbol;
                amountInput.value = data.defaultAmount;
                currencyCodeEl.textContent = data.displayCode;

                // Update active button
                amountBtns.forEach(btn => {
                    btn.classList.remove('active');
                    if (btn.dataset.amt === data.defaultAmount) {
                        btn.classList.add('active');
                    }
                });

                // Update global currency code
                currCode = data.code;
                currSymbol = data.symbol;

                refreshGiveButton();
            });
        }

        /* ---- Preset amount buttons ---- */
        amountBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                amountBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const val = btn.dataset.amt;
                amountInput.value = val;
                refreshGiveButton();
            });
        });

        amountInput.addEventListener('input', refreshGiveButton);
        refreshGiveButton();

        /* ---- Frequency buttons ---- */
        document.querySelectorAll('#freqOptions .freq-label').forEach(label => {
            label.addEventListener('click', () => {
                // Select the hidden radio inside this label
                const radio = label.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;

                // Update visual active state on ALL freq-btns
                document.querySelectorAll('#freqOptions .freq-btn').forEach(b => b.classList.remove('active'));
                label.querySelector('.freq-btn')?.classList.add('active');
            });
        });
        /* ---- Utility: update give button label ---- */
        function refreshGiveButton() {
            const amt = parseInt(amountInput.value) || 0;
            const ctaPrefix = isAuthenticated ? 'Give' : 'Login to Give';
            giveBtn.innerHTML =
                `${ctaPrefix} ${currSymbol}${amt.toLocaleString()} Now ` +
                `<svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" ` +
                `style="width:16px;height:16px;vertical-align:middle">` +
                `<path d="M5 12h14M13 5l7 7-7 7"/></svg>`;
        }

        function redirectToLogin(message = 'Please log in to continue your donation.') {
            showToast(message, 'error');
            window.setTimeout(() => {
                window.location.href = 'login.php';
            }, 300);
        }

        /* ---- Main payment handler ---- */
        async function initiatePaystackPayment() {
            if (!isAuthenticated) {
                redirectToLogin();
                return;
            }
            
            const amount    = parseInt(amountInput.value) || 0;
            const freqRadio = document.querySelector('input[name="freq"]:checked');
            const frequency = freqRadio ? freqRadio.value : 'one-time';
            const email     = donorEmail.trim();

            /* Validation */
            if (amount <= 0) {
                showToast('Please enter a valid donation amount.', 'error');
                console.error('[QuickGive] Validation failed: Invalid amount', amount);
                return;
            }
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showToast('Your account email is required before you can give. Please update your profile or contact support.', 'error');
                console.error('[QuickGive] Validation failed: Invalid email', email);
                return;
            }

            /* Loading state */
            const originalHtml = giveBtn.innerHTML;
            giveBtn.disabled = true;
            giveBtn.innerHTML =
                '<span style="display:inline-block;width:18px;height:18px;border:2px solid rgba(18,33,55,0.25);' +
                'border-top-color:#122137;border-radius:50%;animation:spin 0.8s linear infinite;vertical-align:middle;margin-right:8px"></span>' +
                'Processing...';

            try {
                const isRecurring = frequency !== 'one-time';
                const endpoint    = isRecurring
                    ? 'api/paystack/subscription.php'
                    : 'api/paystack/initialize.php';

                const payload = {
                    email,
                    amount,
                    currency:    currCode,
                    frequency,
                    category:    quickGiveCategory,
                    description: `Quick Give - ${frequency} donation`,
                    first_name:  '<?php echo htmlspecialchars($user["first_name"] ?? ""); ?>',
                    last_name:   '<?php echo htmlspecialchars($user["last_name"] ?? ""); ?>'
                };

                console.log('========== [QuickGive] DEBUG START ==========');
                console.log('[QuickGive] Step 1: Collected data');
                console.log('  - Amount:', amount);
                console.log('  - Category:', quickGiveCategory);
                console.log('  - Frequency:', frequency);
                console.log('  - Email:', email);
                console.log('  - User ID:', userId);
                console.log('  - Endpoint:', endpoint);
                console.log('  - Full payload:', JSON.stringify(payload, null, 2));

                console.log('[QuickGive] Step 2: Sending fetch request...');
                const res  = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                console.log('[QuickGive] Step 3: Received response');
                console.log('  - Status:', res.status);
                console.log('  - Status Text:', res.statusText);
                console.log('  - Content-Type:', res.headers.get('content-type'));

                if (res.status === 401) {
                    const authResult = await res.json().catch(() => null);
                    console.warn('[QuickGive] Session expired or unauthorized', authResult);
                    redirectToLogin(authResult?.error || 'Your session has expired. Please log in again.');
                    return;
                }

                // Guard: make sure we got JSON back
                const contentType = res.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    const raw = await res.text();
                    console.error('[QuickGive] Step 4: Non-JSON response received');
                    console.error('[QuickGive] Raw response:', raw);
                    throw new Error('Server returned an unexpected response. Check PHP error logs.');
                }

                const result = await res.json();
                console.log('[QuickGive] Step 4: Parsed JSON response');
                console.log('  - Full result:', JSON.stringify(result, null, 2));

                if (result.code === 'AUTH_REQUIRED') {
                    redirectToLogin(result.error || 'Your session has expired. Please log in again.');
                    return;
                }

                if (result.success && result.data && result.data.authorization_url) {
                    console.log('[QuickGive] Step 5: SUCCESS - Redirecting to Paystack');
                    console.log('  - Authorization URL:', result.data.authorization_url);
                    window.location.href = result.data.authorization_url;
                } else {
                    console.error('[QuickGive] Step 5: FAILED - API returned error');
                    console.error('  - result.success:', result.success);
                    console.error('  - result.data:', result.data);
                    console.error('  - result.error:', result.error);
                    console.error('  - result.message:', result.message);
                    console.error('  - result.debug:', result.debug);
                    // Surface the actual API error rather than a generic message
                    const msg = result.error || result.message || 'Payment initialization failed';
                    throw new Error(msg);
                }

            } catch (err) {
                console.error('========== [QuickGive] ERROR ==========');
                console.error('[QuickGive] Error type:', err.constructor.name);
                console.error('[QuickGive] Error message:', err.message);
                console.error('[QuickGive] Error stack:', err.stack);
                console.error('========================================');
                showToast(err.message || 'Payment failed. Please try again.', 'error');
                giveBtn.innerHTML = originalHtml;
                giveBtn.disabled  = false;
            }
        }

        /* ---- Toast ---- */
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            if (!toast) return;
            toast.textContent = message;
            toast.className = 'toast ' + type + ' show';
            setTimeout(() => toast.classList.remove('show'), 4000);
        }

        /* ---- Verify payment on return from Paystack ---- */
        (function checkPaymentReturn() {
            const params    = new URLSearchParams(window.location.search);
            const reference = params.get('reference') || params.get('trxref');
            if (!reference) return;

            fetch('api/paystack/verify.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ reference })
            })
            .then(r => r.json())
            .then(result => {
                if (result.success && result.data && result.data.status === 'success') {
                    showToast('Payment successful! Thank you for your generous gift. 🙏', 'success');
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

        /* Spin animation */
        if (!document.getElementById('spin-style')) {
            const s = document.createElement('style');
            s.id = 'spin-style';
            s.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
            document.head.appendChild(s);
        }
    </script>
</body>
</html>
<?php

function getDashboardData($userId, array $portalSettings) {
    $data = [
        'user' => [
            'yearlyGiving' => 0,
            'consecutiveMonths' => 0,
            'pledgeTotal' => 0,
            'pledgePaid' => 0,
            'pledgeRemaining' => 0,
            'pledgePercentage' => 0,
            'nextTierTarget' => 0,
            'nextTierProgress' => 0,
            'amountToNextTier' => 0
        ],
        'projects' => [],
        'recentGifts' => [],
        'impact' => [
            'familiesFed' => (int) ($portalSettings['impact_families_fed'] ?? 0),
            'missionariesSent' => (int) ($portalSettings['impact_missionaries_sent'] ?? 0),
            'campusesPlanted' => (int) ($portalSettings['impact_campuses_planted'] ?? 0),
            'livesTouched' => $portalSettings['impact_lives_touched'] ?? '0'
        ]
    ];

    try {
        $userModel = new User();
        $projectModel = new Project();
        // Use getActiveProjectsWithMilestones to include milestones in project data
        $data['projects'] = $projectModel->getActiveProjectsWithMilestones();

        if ($userId) {
            $yearlyGiving = $userModel->getYearlyGiving((int) date('Y'), $userId);
            $pledges = $userModel->getPledges($userId);
            $recentGifts = $userModel->getRecentTransactions(4, $userId);
            $donorTier = $userModel->getDonorTier($userId);
            $tierProgress = getNextTierProgress($userModel, $userId, $donorTier);

            $activePledge = $pledges[0] ?? null;
            $pledgeTotal = (float) ($activePledge['total_amount'] ?? 0);
            $pledgeRemaining = (float) ($activePledge['remaining_amount'] ?? 0);
            $pledgePaid = max($pledgeTotal - $pledgeRemaining, 0);
            $pledgePercentage = $pledgeTotal > 0 ? (int) round(($pledgePaid / $pledgeTotal) * 100) : 0;

            $data['user'] = [
                'yearlyGiving' => (float) ($yearlyGiving['total'] ?? 0),
                'consecutiveMonths' => (int) $userModel->getConsecutiveMonths($userId),
                'pledgeTotal' => $pledgeTotal,
                'pledgePaid' => $pledgePaid,
                'pledgeRemaining' => $pledgeRemaining,
                'pledgePercentage' => $pledgePercentage,
                'nextTierTarget' => $tierProgress['nextTierTarget'],
                'nextTierProgress' => $tierProgress['nextTierProgress'],
                'amountToNextTier' => $tierProgress['amountToNextTier']
            ];
            $data['recentGifts'] = $recentGifts;
        }
    } catch (Exception $e) {
        error_log("Dashboard data error: " . $e->getMessage());
    }

    return $data;
}

function getNextTierProgress(User $userModel, int $userId, ?array $currentTier): array {
    $db = Database::getInstance();
    $yearlyGiving = $userModel->getYearlyGiving((int) date('Y'), $userId);
    $currentAmount = (float) ($yearlyGiving['total'] ?? 0);
    $currentTierId = $currentTier['tier_id'] ?? 0;

    $nextTier = $db->fetchOne(
        'SELECT tier_name, min_amount FROM donor_tiers WHERE tier_id > :tier_id ORDER BY tier_id ASC LIMIT 1',
        ['tier_id' => (int) $currentTierId]
    );

    if (!$nextTier) {
        return [
            'nextTierTarget' => $currentAmount,
            'nextTierProgress' => 100,
            'amountToNextTier' => 0
        ];
    }

    $target = (float) $nextTier['min_amount'];
    $progress = $target > 0 ? (int) round(min(($currentAmount / $target) * 100, 100)) : 0;

    return [
        'nextTierTarget' => $target,
        'nextTierProgress' => $progress,
        'amountToNextTier' => max($target - $currentAmount, 0)
    ];
}

function getProjectIcon($iconName) {
    $icons = [
        'church' => '<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 21h18M4 21V10l8-6 8 6v11M9 21v-6h6v6"/></svg>',
        'globe' => '<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/></svg>',
        'star' => '<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 2l3 7h7l-5.5 4.5L18 21l-6-4.5L6 21l1.5-7.5L2 9h7z"/></svg>',
        'graduation-cap' => '<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 14l9-5-9-5-9 5 9 5zM12 14l9-5v6L12 20 3 15V9l9 5z"/></svg>',
        'default' => '<svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 2v20M6 8h12"/></svg>'
    ];
    return $icons[$iconName] ?? $icons['default'];
}

function handleApiRequest() {
    $action = $_GET['action'];
    
    switch ($action) {
        case 'process-donation':
            processDonation();
            break;
        case 'get-projects':
            getProjects();
            break;
        case 'get-user-stats':
            getUserStats();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function processDonation() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $userId = $_SESSION['user_id'];
    $amount = $data['amount'] ?? 0;
    $category = $data['category'] ?? 'Tithe';
    $frequency = $data['frequency'] ?? 'One-time';
    $projectId = $data['projectId'] ?? null;
    
    $validation = Transaction::validateAmount($amount);
    if (!$validation['valid']) {
        http_response_code(400);
        echo json_encode(['error' => $validation['error']]);
        return;
    }
    
    $transaction = new Transaction();
    $result = $transaction->processDonation($userId, $validation['amount'], $category, $frequency, $projectId);
    
    echo json_encode($result);
}

function getProjects() {
    $project = new Project();
    // Include milestones in the API response
    $projects = $project->getActiveProjectsWithMilestones();
    echo json_encode(['projects' => $projects]);
}

function getUserStats() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $user = new User();
    $stats = [
        'yearlyGiving' => $user->getYearlyGiving(date('Y')),
        'consecutiveMonths' => $user->getConsecutiveMonths(),
        'donorTier' => $user->getDonorTier()
    ];
    
    echo json_encode($stats);
}

function calculateServiceTime() {
    $now = new DateTime();
    $nextSunday = new DateTime();
    $nextSunday->setDate((int)$now->format('Y'), (int)$now->format('m'), (int)$now->format('d'));
    
    $dayOfWeek = (int)$now->format('N');
    $daysUntilSunday = 7 - $dayOfWeek;
    
    if ($daysUntilSunday === 0) {
        $daysUntilSunday = 7;
    }
    
    $nextSunday->modify("+{$daysUntilSunday} days");
    $nextSunday->setTime(9, 0, 0);
    
    $now->setTime(0, 0, 0);
    
    if ($nextSunday < $now) {
        $nextSunday->modify("+7 days");
    }
    
    $interval = $now->diff($nextSunday);
    $hours = floor($interval->days / 24);
    $minutes = floor(($interval->days % 24) * 24);
    
    if ($hours > 0) {
        return $hours . 'h ' . $minutes . 'm';
    }
    return $minutes . 'm';
}
