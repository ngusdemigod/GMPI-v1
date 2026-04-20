<?php
/**
 * Shared Admin Layout Component
 * Bright Light Ministry Int'l Admin Dashboard
 * 
 * This file provides a consistent layout wrapper for all admin pages.
 * It includes the sidebar, topbar, and main content area with proper padding.
 * 
 * Usage:
 *   $pageTitle = "Page Title";
 *   $pageSubtitle = "Page description";
 *   $activePage = "index"; // Current page for sidebar highlighting
 *   require_once __DIR__ . '/includes/layout.php';
 *   layoutHeader();
 *   // ... page content ...
 *   layoutFooter();
 */

// Prevent direct access
if (!defined('ADMIN_LAYOUT')) {
    define('ADMIN_LAYOUT', true);
}

// Get current page for active highlighting
$currentPage = $activePage ?? basename($_SERVER['PHP_SELF'], '.php');

/**
 * Output the layout header (DOCTYPE, head, sidebar, topbar)
 */
function layoutHeader() {
    global $admin, $pageTitle, $pageSubtitle, $currentPage;
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? e($pageTitle) . ' - ' : ''; ?>Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,200..800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="includes/design-system.css" rel="stylesheet">
    <style>
        :root {
            --gold: #C9A24B;
            --gold-light: #E7D9B4;
            --gold-dark: #A8863A;
            --danger: #dc3545;
            --success: #28a745;
            --warning: #ffc107;
            --info: #17a2b8;
            --sidebar-width: 220px;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            height: 100%;
            overflow: hidden;
        }
        body {
            background:
                radial-gradient(circle at top left, rgba(201, 162, 75, 0.12), transparent 30%),
                radial-gradient(circle at bottom right, rgba(91, 123, 106, 0.1), transparent 28%),
                var(--bg-primary);
            color: var(--text-primary);
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 220px;
            background:
                radial-gradient(circle at top right, rgba(201, 162, 75, 0.12), transparent 28%),
                linear-gradient(180deg, #15243a 0%, #0d1828 100%);
            color: white;
            padding: 0;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
            box-shadow: 28px 0 64px rgba(6, 15, 27, 0.22);
            scrollbar-width: none;
        }

        .sidebar::-webkit-scrollbar {
            width: 0;
            height: 0;
        }
        
        .sidebar-header {
            padding: 26px 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.09);
            flex-shrink: 0;
        }
        
        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }
        .sidebar-logo-icon {
            width: 34px;
            height: 34px;
            background: rgba(255, 255, 255, 0.94);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: var(--dark);
            flex-shrink: 0;
            box-shadow: 0 18px 36px rgba(0, 0, 0, 0.18);
        }
        
        .sidebar-logo-text {
            font-family: var(--font-heading);
            font-size: 20px;
            font-weight: 400;
            line-height: 1.2;
            letter-spacing: 0em;
            
        }
        
        .sidebar-logo-text span {
            display: block;
            font-size: 13px;
            font-family: var(--font-body);
            color: rgba(255,255,255,0.66);
            font-weight: 400;
            margin-top: 4px;
        }
        
        .admin-info {
            display: flex;
            align-items: center;
            gap: var(--space-4);
            padding: 10px 8px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 22px;
            margin: 20px 18px 14px;
            text-decoration: none;
            color: inherit;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
            transition: background-color 0.2s ease, border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }

        .admin-info:hover,
        .admin-info:focus-visible {
            background: rgba(255,255,255,0.11);
            border-color: rgba(201, 162, 75, 0.4);
            transform: translateY(-1px);
            box-shadow: 0 20px 34px rgba(0, 0, 0, 0.14);
            outline: none;
        }

        .admin-info.active {
            background: linear-gradient(135deg, rgba(201, 162, 75, 0.18), rgba(231, 217, 180, 0.08));
            border-color: rgba(201, 162, 75, 0.44);
        }
        
        .admin-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(145deg, var(--gold), var(--gold-light));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--dark);
            font-size: 20px;
            flex-shrink: 0;
            box-shadow: inset 0 -4px 10px rgba(0, 0, 0, 0.1);
        }
        
        .admin-details {
            flex: 1;
            min-width: 0;
        }
        
        .admin-name {
            font-size: 14px;
            font-weight: 400;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .admin-email {
            font-size: 10px;
            color: rgba(255,255,255,0.64);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 2px;
        }
        
        .nav-menu {
            list-style: none;
            padding: 10px 18px 0;
            flex: 1;
        }
        
        .nav-item {
            margin: 4px 0;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            min-height: 52px;
            padding: 10px 10px;
            color: rgba(255,255,255,0.72);
            text-decoration: none;
            border-radius: 18px;
            transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
            font-size: 14px;
            font-weight: 400;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.08);
            color: white;
            transform: translateX(2px);
        }
        
        .nav-link.active {
            background: linear-gradient(90deg, rgba(201, 162, 75, 0.22), rgba(231, 217, 180, 0.12));
            color: #fff6d1;
            box-shadow: inset 0 0 0 1px rgba(231, 217, 180, 0.12), 0 12px 28px rgba(0, 0, 0, 0.12);
        }
        
        .nav-link i {
            width: 22px;
            text-align: center;
            flex-shrink: 0;
            font-size: 15px;
        }
        
        .nav-section {
            padding: 22px 10px 8px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            color: rgba(255,255,255,0.42);
            font-weight: 700;
        }
        
        .sidebar-footer {
            padding: 18px 18px 24px;
            border-top: 1px solid rgba(255,255,255,0.09);
            flex-shrink: 0;
            margin-top: auto;
            background: linear-gradient(180deg, rgba(15, 27, 45, 0) 0%, rgba(15, 27, 45, 0.36) 100%);
        }
        
        .sidebar-footer .nav-link {
            color: rgba(255,255,255,0.5);
        }
        
        .sidebar-footer .nav-link:hover {
            color: var(--danger);
            background: rgba(220, 53, 69, 0.1);
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            height: 100vh;
            padding:30px;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s ease;
            max-width: calc(100vw - var(--sidebar-width));
        }
        
        .page-header {
            display: none;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--space-12);
            flex-wrap: wrap;
            gap: var(--space-5);
            padding: var(--space-4) 0 var(--space-2);
            flex-shrink: 0;
        }

        .page-scaffold {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: var(--space-3);
            padding-bottom: calc(var(--space-10) + env(safe-area-inset-bottom, 0px));
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .page-scaffold::-webkit-scrollbar {
            width: 0;
            height: 0;
            display: none;
        }

        .page-scaffold::-webkit-scrollbar-track {
            background: transparent;
        }

        .page-scaffold::-webkit-scrollbar-thumb {
            background: rgba(10, 17, 31, 0.15);
            border-radius: 3px;
        }

        .page-scaffold::-webkit-scrollbar-thumb:hover {
            background: rgba(10, 17, 31, 0.25);
        }
        
        .page-header-left {
            display: flex;
            align-items: center;
            gap: var(--space-4);
            min-width: 0;
            flex: 1;
        }
        
        .hamburger {
            display: none;
            background: none;
            border: none;
            font-size: 21px;
            color: var(--text-primary);
            cursor: pointer;
            padding: var(--space-2);
            border-radius: var(--radius-sm);
            flex-shrink: 0;
            line-height: 1;
        }
        
        .hamburger:hover {
            background: rgba(10, 17, 31, 0.05);
        }
        
        .page-title h1 {
            font-size: var(--type-h1-size);
            color: var(--dark);
            margin-bottom: var(--space-3);
            line-height: 1.06;
            letter-spacing: -0.03em;
            padding-right: var(--space-4);
        }

        .page-title {
            min-width: 0;
            flex: 1;
        }
        
        .page-title p {
            color: var(--text-secondary);
            font-size: var(--type-body-lg);
            max-width: 760px;
            line-height: 1.55;
        }
        
        .page-actions {
            display: flex;
            gap: var(--space-3);
            flex-wrap: wrap;
            flex-shrink: 0;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            min-height: 48px;
            padding: 13px 20px;
            border-radius: 16px;
            font-size: var(--type-body-sm);
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            white-space: nowrap;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #d2ab4f 0%, #ead39b 100%);
            color: var(--dark);
            box-shadow: 0 14px 26px rgba(201, 162, 75, 0.2);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--gold-dark), var(--gold));
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: white;
            color: var(--text-primary);
            border: 1px solid rgba(10, 17, 31, 0.08);
        }
        
        .btn-secondary:hover {
            background: var(--cream-light);
            border-color: rgba(201, 162, 75, 0.24);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-sm {
            padding: var(--space-2) var(--space-3);
            font-size: var(--type-body-xs);
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            border: 1px solid rgba(10,17,31,0.07);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--space-6) var(--space-8) var(--space-5);
            border-bottom: 1px solid rgba(10,17,31,0.06);
        }
        
        .card-title {
            font-size: var(--type-h4-size);
            color: var(--dark);
            font-weight: 600;
        }
        
        .card-body {
            padding: var(--space-8);
        }

        .soft-panel {
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(10, 17, 31, 0.08);
            border-radius: 28px;
            box-shadow: var(--shadow-sm);
        }

        .soft-panel-body {
            padding: 24px;
        }

        .soft-panel-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: var(--space-4);
            padding: 22px 24px 0;
        }

        .soft-panel-title {
            font-family: var(--font-heading);
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .soft-panel-subtitle {
            margin-top: 6px;
            color: var(--text-secondary);
            font-size: var(--type-body-sm);
            line-height: 1.55;
        }

        .soft-meta-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: var(--radius-full);
            background: rgba(10, 17, 31, 0.04);
            color: var(--text-secondary);
            font-size: var(--type-body-xs);
            font-weight: 600;
            white-space: nowrap;
        }

        .soft-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 14px;
            padding: 16px;
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(10, 17, 31, 0.08);
            border-radius: 28px;
            box-shadow: var(--shadow-sm);
            margin-bottom: var(--space-8);
        }

        .filter-chip-strip {
            display: grid;
            gap: 10px;
            margin-bottom: 16px;
        }

        .soft-toolbar-grow {
            flex: 1 1 220px;
        }

        .soft-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 0;
        }

        .soft-field-label {
            font-size: var(--type-body-xs-small);
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--text-tertiary);
            font-weight: 600;
        }

        .soft-input,
        .soft-select {
            width: 100%;
            min-height: 48px;
            padding: 12px 16px;
            border: 1px solid rgba(10, 17, 31, 0.12);
            border-radius: 18px;
            background: #fff;
            color: var(--text-primary);
            font-size: var(--type-body);
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        }

        .soft-input:focus,
        .soft-select:focus {
            outline: none;
            border-color: rgba(201, 162, 75, 0.7);
            box-shadow: 0 0 0 4px rgba(201, 162, 75, 0.12);
        }

        .chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 10px 18px;
            border-radius: var(--radius-full);
            border: 1px solid rgba(10, 17, 31, 0.12);
            background: #fff;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: var(--type-body-sm);
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .chip:hover {
            border-color: rgba(201, 162, 75, 0.5);
            color: var(--text-primary);
            transform: translateY(-1px);
        }

        .chip.is-active {
            background: linear-gradient(135deg, #d2ab4f 0%, #c29a3b 100%);
            border-color: transparent;
            color: #fff;
            box-shadow: 0 12px 22px rgba(201, 162, 75, 0.2);
        }

        .chip.is-dark {
            background: var(--deep);
            border-color: var(--deep);
            color: #fff;
            box-shadow: 0 12px 22px rgba(18, 33, 55, 0.18);
        }

        .toolbar-reset {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 12px 18px;
            border-radius: 16px;
            color: var(--text-secondary);
            text-decoration: none;
            border: 1px solid rgba(10, 17, 31, 0.1);
            background: rgba(10, 17, 31, 0.02);
            font-size: var(--type-body-sm);
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .toolbar-reset:hover {
            color: var(--text-primary);
            border-color: rgba(201, 162, 75, 0.4);
            background: rgba(250, 246, 239, 0.9);
        }

        .split-dashboard {
            display: grid;
            grid-template-columns: minmax(0, 1.6fr) minmax(280px, 0.9fr);
            gap: var(--space-6);
            align-items: start;
        }

        .info-stack {
            display: grid;
            gap: var(--space-6);
        }

        .subtle-kicker {
            font-size: var(--type-body-xs-small);
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--text-tertiary);
            font-weight: 600;
        }

        .empty-soft {
            padding: 40px 24px;
            text-align: center;
            color: var(--text-secondary);
        }

        .empty-soft-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 18px;
            border-radius: 20px;
            border: 1px solid rgba(10, 17, 31, 0.08);
            background: rgba(10, 17, 31, 0.03);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 24px;
        }

        .table-shell {
            overflow: hidden;
            background: rgba(255, 255, 255, 0.97);
            border: 1px solid rgba(10, 17, 31, 0.08);
            border-radius: 28px;
            box-shadow: var(--shadow-sm);
        }

        .table-shell-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--space-4);
            padding: 20px 24px;
            border-bottom: 1px solid rgba(10, 17, 31, 0.07);
        }

        .table-shell-body {
            padding: 8px 18px 18px;
        }

        .soft-table {
            width: 100%;
            border-collapse: collapse;
        }

        .soft-table th,
        .soft-table td {
            padding: 16px 10px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid rgba(10, 17, 31, 0.06);
        }

        .soft-table th {
            font-size: var(--type-body-xs-small);
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--text-tertiary);
            font-weight: 600;
        }

        .soft-table tbody tr {
            transition: background-color 0.2s ease;
        }

        .soft-table tbody tr:hover {
            background: rgba(250, 246, 239, 0.82);
        }
        
        /* Metrics Grid */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--space-6);
            margin-bottom: var(--space-8);
        }
        
        .metric-card {
            
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.95), rgba(250, 246, 239, 0.96));
            border: 1px solid rgba(10, 17, 31, 0.06);
            box-shadow: 0 12px 30px rgba(10, 17, 31, 0.08);
            border-radius: 24px;
            padding:18px;
        
        }
        
        .metric-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-3px);
        }
        
        .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-4);
        }
        
        .metric-icon {
            width: 54px;
            height: 54px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .metric-title {
            font-size: var(--type-body-xs-small);
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.14em;
        }
        .metric-subtitle{
            font-size:12px;
            color: rgba(15, 27, 45, 0.55);
            margin-top: 4px;

        }
        
        .metric-value {
            
            position: relative;
            z-index: 1;
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: clamp(26px, 2.5vw, 30px);
            font-weight: 600;
            line-height: 1;
            letter-spacing: -0.04em;
            color: #0a111f;
            margin-bottom: 8px;
            margin-top: 8px;
        }

        .metric-value .metric-currency-symbol {
            color: var(--gold);
            margin-right: 4px;
        }

        .metric-value .metric-number {
            color: #0a111f;
        }
        
        .metric-change {
            font-size: var(--type-body-xs);
            margin-top: var(--space-2);
        }
        
        .metric-change.positive { color: var(--success); }
        .metric-change.negative { color: var(--danger); }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: var(--space-6);
        }

        .stack-gap > * + * {
            margin-top: var(--space-6);
        }
        
        /* Alert Styles */
        .alert {
            padding: var(--space-5) var(--space-6);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-6);
            box-shadow: var(--shadow-xs);
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        
        .alert-warning {
            background: rgba(255, 193, 7, 0.1);
            color: #b7950b;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }
        
        /* Sidebar Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        
        .sidebar-overlay.active {
            display: block;
        }
        
        /* ============================================
           RESPONSIVE STYLES
           ============================================ */
        
        /* Tablet (max-width: 992px) */
        @media (max-width: 992px) {
            .metrics-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }

            .split-dashboard {
                grid-template-columns: 1fr;
            }
        }
        
        /* Mobile (max-width: 768px) */
        @media (max-width: 768px) {
            html, body {
                height: 100%;
                min-height: 100%;
                overflow: hidden;
            }

            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 10px;
                max-width: 100%;
                min-height: 100dvh;
                height: 100dvh;
                overflow-y: auto;
                overflow-x: hidden;
                -webkit-overflow-scrolling: touch;
            }
            
            .hamburger {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .page-header {
                display: flex;
                position: sticky;
                top: 0;
                z-index: 120;
                flex-wrap: nowrap;
                align-items: center;
                margin-bottom: var(--space-5);
                padding: 14px 16px;
                border-radius: 26px;
                background:
                    radial-gradient(circle at top left, rgba(201, 162, 75, 0.16), transparent 38%),
                    linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(249, 245, 236, 0.96));
                border: 1px solid rgba(10, 17, 31, 0.08);
                box-shadow: 0 18px 34px rgba(10, 17, 31, 0.08);
            }

            .page-header-left {
                align-items: center;
                gap: var(--space-3);
                flex-wrap: nowrap;
                min-width: 0;
            }
            
            .page-title h1 {
                font-size: 22px;
                margin-bottom: 0;
                padding-right: 0;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            
            .page-title p {
                display: none;
            }
            
            .page-actions {
                flex-shrink: 0;
                width: auto;
                justify-content: flex-end;
                flex-wrap: nowrap;
                min-width: 0;
            }

            .page-scaffold {
                padding: 0;
                overflow: visible;
                padding-bottom: calc(88px + env(safe-area-inset-bottom, 0px));
            }
            
            .btn-text {
                display: none;
            }
            
            .btn {
                padding: var(--space-3) var(--space-4);
            }
            
            .metrics-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--space-3);
                margin-bottom: var(--space-5);
            }
            
            .metric-card {
                padding: 14px;
            }
            
            .metric-value {
                font-size: 20px;
            }
            
            .card-header {
                padding: var(--space-5);
            }
            
            .card-body {
                padding: var(--space-5);
            }

            .soft-toolbar {
                padding: 14px;
                border-radius: 24px;
            }

            .soft-panel-body,
            .soft-panel-header,
            .table-shell-header {
                padding-left: 18px;
                padding-right: 18px;
            }

            .table-shell-body {
                padding: 6px 12px 12px;
            }
        }
        
        /* Small Mobile (max-width: 576px) */
        @media (max-width: 576px) {
            .main-content {
                padding: 10px;
            }
            
            .metrics-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            
            .page-title h1 {
                font-size: 20px;
            }

            .page-header {
                padding: 12px 14px;
                border-radius: 22px;
            }
            
            .sidebar-header {
                padding: var(--space-4);
            }
            
            .sidebar-logo-text {
                font-size: 15px;
            }
            
            .sidebar-logo-text span {
                font-size: 12px;
            }

            .page-scaffold {
                padding: 0;
            }
        }
        
        /* Extra Small (max-width: 480px) */
        @media (max-width: 480px) {
            .main-content {
                padding: 10px;
            }
            
            .page-title h1 {
                font-size: 18px;
            }
            
            .metric-value {
                font-size: 18px;
            }
            
            .btn {
                padding: var(--space-2) var(--space-3);
                font-size: var(--type-body-xs);
            }
        }
    </style>
    <?php
    // Allow pages to add custom styles
    if (function_exists('layoutCustomStyles')) {
        layoutCustomStyles();
    }
    ?>
</head>
<body>
    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <div class="sidebar-logo-icon" style="background: white; padding: 4px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                    <img src="BRIGHT-LIGHT-logo.png" alt="Church Logo" style="width: 32px; height: 32px; object-fit: contain; border-radius: 4px;">
                </div>
                <div class="sidebar-logo-text">
                    Bright Light Ministry Int'l
                    <span>Admin Dashboard</span>
                </div>
            </div>
        </div>
        
        <?php if (isset($admin)): ?>
        <a href="account.php" class="admin-info <?php echo $currentPage === 'account' ? 'active' : ''; ?>">
            <div class="admin-avatar">
                <?php echo strtoupper(substr($admin['first_name'], 0, 1) . substr($admin['last_name'], 0, 1)); ?>
            </div>
            <div class="admin-details">
                <div class="admin-name"><?php echo e($admin['first_name'] . ' ' . $admin['last_name']); ?></div>
                <div class="admin-email"><?php echo e($admin['email']); ?></div>
            </div>
        </a>
        <?php endif; ?>
        
        <nav>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="index.php" class="nav-link <?php echo $currentPage === 'index' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span>Overview</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="projects.php" class="nav-link <?php echo $currentPage === 'projects' ? 'active' : ''; ?>">
                        <i class="fas fa-project-diagram"></i>
                        <span>Projects</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="users.php" class="nav-link <?php echo $currentPage === 'users' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span>Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="transactions.php" class="nav-link <?php echo $currentPage === 'transactions' ? 'active' : ''; ?>">
                        <i class="fas fa-exchange-alt"></i>
                        <span>Transactions</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="plans.php" class="nav-link <?php echo $currentPage === 'plans' ? 'active' : ''; ?>">
                        <i class="fas fa-tags"></i>
                        <span>Payment Plans</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="partners.php" class="nav-link <?php echo $currentPage === 'partners' ? 'active' : ''; ?>">
                        <i class="fas fa-handshake"></i>
                        <span>Partners</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="support.php" class="nav-link <?php echo $currentPage === 'support' ? 'active' : ''; ?>">
                        <i class="fas fa-headset"></i>
                        <span>Support Tickets</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin-members.php" class="nav-link <?php echo $currentPage === 'admin-members' ? 'active' : ''; ?>">
                        <i class="fas fa-user-shield"></i>
                        <span>Admin Members</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="audit-logs.php" class="nav-link <?php echo $currentPage === 'audit-logs' ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i>
                        <span>Audit Logs</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="settings.php" class="nav-link <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <div class="sidebar-footer">
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <?php if (isset($pageTitle)): ?>
        <div class="page-header">
            <div class="page-header-left">
                <button class="hamburger" onclick="toggleSidebar()" aria-label="Toggle menu">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="page-title">
                    <h1><?php echo e($pageTitle); ?></h1>
                    <?php if (isset($pageSubtitle)): ?>
                    <p><?php echo e($pageSubtitle); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (function_exists('layoutPageActions')): ?>
            <div class="page-actions">
                <?php layoutPageActions(); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php
        // Show alerts if defined
        global $successMessage, $errorMessage;
        if (isset($successMessage) && $successMessage):
        ?>
            <div class="alert alert-success"><?php echo e($successMessage); ?></div>
        <?php endif; ?>
        
        <?php if (isset($errorMessage) && $errorMessage): ?>
            <div class="alert alert-error"><?php echo e($errorMessage); ?></div>
        <?php endif; ?>
        <div class="page-scaffold">
    <?php
}

/**
 * Output the layout footer (closing tags, scripts)
 */
function layoutFooter() {
    ?>
        </div>
    </main>
    
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        }
        
        // Close sidebar on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
            }
        });
    </script>
    
    <?php
    // Allow pages to add custom scripts
    if (function_exists('layoutCustomScripts')) {
        layoutCustomScripts();
    }
    ?>
</body>
</html>
    <?php
}
?>
