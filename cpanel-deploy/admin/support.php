<?php
/**
 * Admin Support Ticket Management
 * Church Financial Partnership System
 */

require_once __DIR__ . '/includes/config.php';

// Require authentication
$admin = requireAdmin();

// Set page variables for layout
$pageTitle = 'Support Tickets';
$pageSubtitle = 'Manage and respond to user support requests';
$activePage = 'support';

$db = Database::getInstance();

// Load SupportTicket model
require_once __DIR__ . '/../src/models/SupportTicket.php';

$ticketModel = new SupportTicket();

// Get action
$action = $_GET['action'] ?? 'list';
$ticketId = $_GET['id'] ?? null;

// Handle form submissions
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $ticketId = (int)$_POST['ticket_id'];
        $status = $_POST['status'];
        if ($ticketModel->updateStatus($ticketId, $status)) {
            $successMessage = 'Ticket status updated successfully.';
        } else {
            $errorMessage = 'Failed to update ticket status.';
        }
    } elseif (isset($_POST['add_response'])) {
        $ticketId = (int)$_POST['ticket_id'];
        $response = trim($_POST['response'] ?? '');
        $isInternal = isset($_POST['is_internal']);
        $adminId = $admin['admin_id'];
        
        if (!empty($response)) {
            $ticketModel->addResponse($ticketId, $response, $adminId);
            $successMessage = 'Response added successfully.';
        } else {
            $errorMessage = 'Response cannot be empty.';
        }
    }
}

// Get filter
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Include layout
require_once __DIR__ . '/includes/layout.php';

// Define custom styles for this page
function layoutCustomStyles() {
    ?>
    <style>
        .filter-bar {
            background: white;
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            margin-bottom: var(--space-6);
            box-shadow: var(--shadow-sm);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: var(--space-4);
            align-items: end;
        }

        .filter-group-search {
            min-width: 0;
        }

        .filter-group-submit {
            justify-content: flex-end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: var(--space-2);
        }
        
        .filter-label {
            font-size: var(--type-body-xs);
            font-weight: 500;
            color: var(--text-secondary);
        }
        
        .filter-input, .filter-select {
            padding: 10px 14px;
            border: 1.5px solid rgba(0,0,0,0.1);
            border-radius: var(--radius-sm);
            font-size: var(--type-body);
        }
        
        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: var(--gold);
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: var(--space-3) var(--space-4);
            text-align: left;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        th {
            font-size: var(--type-body-xs-small);
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        tr:hover {
            background: var(--cream-light);
            cursor: pointer;
        }

        .support-ticket-row {
            cursor: pointer;
            transition: background-color 0.2s ease, box-shadow 0.2s ease;
        }

        .support-ticket-row:focus-visible {
            outline: none;
            box-shadow: inset 0 0 0 2px rgba(201, 162, 75, 0.42);
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: var(--type-body-xs);
            font-weight: 500;
        }
        
        .status-open { background: rgba(220, 53, 69, 0.1); color: var(--danger); }
        .status-in_progress { background: rgba(255, 193, 7, 0.1); color: var(--warning); }
        .status-resolved { background: rgba(40, 167, 69, 0.1); color: var(--success); }
        .status-closed { background: rgba(108, 117, 125, 0.1); color: var(--text-muted); }
        
        .response-item {
            border-left: 4px solid var(--gold);
            padding-left: var(--space-4);
            margin-bottom: var(--space-4);
        }
        
        .response-item.internal {
            border-left-color: var(--text-muted);
        }
        
        .response-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: var(--space-2);
        }
        
        .response-author {
            font-size: var(--type-body-sm);
            font-weight: 500;
            color: var(--dark);
        }
        
        .response-time {
            font-size: var(--type-body-xs);
            color: var(--text-muted);
        }
        
        .response-content {
            background: var(--cream-light);
            border-radius: var(--radius-sm);
            padding: var(--space-3);
            font-size: var(--type-body);
        }
        
        .ticket-detail-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: var(--space-6);
        }
        
        .detail-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            margin-bottom: var(--space-6);
            box-shadow: var(--shadow-sm);
        }
        
        .detail-section-title {
            font-size: var(--type-h5-size);
            font-weight: 600;
            color: var(--dark);
            margin-bottom: var(--space-4);
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: var(--space-3) 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-size: var(--type-body-sm);
            color: var(--text-secondary);
        }
        
        .detail-value {
            font-size: var(--type-body-sm);
            font-weight: 500;
            color: var(--dark);
        }
        
        .message-box {
            background: var(--cream-light);
            border-radius: var(--radius-md);
            padding: var(--space-5);
            font-size: var(--type-body);
            line-height: 1.6;
        }
        
        .attachment-item {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-3);
            background: var(--cream-light);
            border-radius: var(--radius-sm);
            margin-bottom: var(--space-2);
        }
        
        .attachment-icon {
            color: var(--gold-dark);
            font-size: 18px;
        }
        
        .attachment-name {
            font-size: var(--type-body-sm);
            font-weight: 500;
            color: var(--dark);
        }
        
        .attachment-date {
            font-size: var(--type-body-xs);
            color: var(--text-muted);
            margin-left: auto;
        }
        
        .response-form {
            background: var(--cream-light);
            border-radius: var(--radius-md);
            padding: var(--space-5);
        }
        
        .response-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid rgba(0,0,0,0.1);
            border-radius: var(--radius-sm);
            font-size: var(--type-body);
            min-height: 120px;
            resize: vertical;
        }
        
        .response-textarea:focus {
            outline: none;
            border-color: var(--gold);
        }
        
        .quick-action-btn {
            width: 100%;
            padding: 10px 16px;
            border-radius: var(--radius-sm);
            font-size: var(--type-body-sm);
            font-weight: 500;
            text-align: left;
            cursor: pointer;
            border: 1.5px solid rgba(0,0,0,0.1);
            background: white;
            margin-bottom: var(--space-2);
            transition: all 0.2s;
        }
        
        .quick-action-btn:hover {
            background: var(--cream-light);
            border-color: var(--gold);
        }
        
        .quick-action-btn.danger {
            border-color: rgba(220, 53, 69, 0.3);
            color: var(--danger);
        }
        
        .quick-action-btn.danger:hover {
            background: rgba(220, 53, 69, 0.05);
        }

        .ticket-chat-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 2400;
            padding: 24px;
            background: rgba(8, 14, 24, 0.58);
            align-items: center;
            justify-content: center;
        }

        .ticket-chat-modal.active {
            display: flex;
        }

        .ticket-chat-dialog {
            width: min(100%, 980px);
            max-height: min(88vh, 920px);
            display: grid;
            grid-template-rows: auto auto minmax(0, 1fr) auto;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.99), rgba(249, 245, 236, 0.97));
            border: 1px solid rgba(10, 17, 31, 0.08);
            border-radius: 30px;
            box-shadow: 0 28px 64px rgba(10, 17, 31, 0.2);
            overflow: hidden;
        }

        .ticket-chat-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: var(--space-4);
            padding: 24px 26px 18px;
            border-bottom: 1px solid rgba(10, 17, 31, 0.07);
        }

        .ticket-chat-heading {
            min-width: 0;
        }

        .ticket-chat-kicker {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(201, 162, 75, 0.14);
            color: rgba(10, 17, 31, 0.7);
            font-size: 11px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .ticket-chat-title {
            margin: 0;
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 28px;
            line-height: 1.05;
            letter-spacing: -0.04em;
            color: rgba(10, 17, 31, 0.94);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ticket-chat-meta {
            margin-top: 8px;
            color: var(--text-secondary);
            font-size: var(--type-body-sm);
            line-height: 1.5;
        }

        .ticket-chat-close {
            width: 44px;
            height: 44px;
            border: none;
            border-radius: 50%;
            background: rgba(10, 17, 31, 0.06);
            color: rgba(10, 17, 31, 0.82);
            font-size: 24px;
            cursor: pointer;
            flex-shrink: 0;
        }

        .ticket-chat-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--space-4);
            padding: 16px 26px;
            border-bottom: 1px solid rgba(10, 17, 31, 0.07);
            background: rgba(255, 255, 255, 0.76);
        }

        .ticket-chat-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .ticket-chat-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(10, 17, 31, 0.05);
            color: rgba(10, 17, 31, 0.76);
            font-size: var(--type-body-xs);
        }

        .ticket-chat-status-form {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .ticket-chat-status-form .filter-select {
            min-width: 160px;
        }

        .ticket-chat-body {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            min-height: 0;
            padding: 22px 26px;
            overflow: hidden;
        }

        .ticket-chat-thread {
            display: flex;
            flex-direction: column;
            gap: 14px;
            min-height: 0;
            overflow-y: auto;
            padding-right: 4px;
        }

        .ticket-chat-empty {
            padding: 48px 16px;
            text-align: center;
            color: var(--text-secondary);
        }

        .ticket-chat-message {
            display: flex;
        }

        .ticket-chat-message.is-admin {
            justify-content: flex-end;
        }

        .ticket-chat-bubble {
            max-width: min(82%, 620px);
            padding: 14px 16px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(10, 17, 31, 0.08);
            box-shadow: 0 10px 20px rgba(10, 17, 31, 0.06);
        }

        .ticket-chat-message.is-admin .ticket-chat-bubble {
            background: #182345;
            border-color: transparent;
            color: #fff;
        }

        .ticket-chat-message.is-original .ticket-chat-bubble {
            background: rgba(201, 162, 75, 0.12);
            border-color: rgba(201, 162, 75, 0.24);
        }

        .ticket-chat-author {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 8px;
            font-size: 11px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(10, 17, 31, 0.52);
        }

        .ticket-chat-message.is-admin .ticket-chat-author {
            color: rgba(255, 255, 255, 0.72);
        }

        .ticket-chat-text {
            font-size: var(--type-body-sm);
            line-height: 1.7;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .ticket-chat-composer {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            padding: 18px 26px 24px;
            border-top: 1px solid rgba(10, 17, 31, 0.07);
            background: rgba(255, 255, 255, 0.84);
        }

        .ticket-chat-input {
            flex: 1 1 auto;
            min-height: 56px;
            max-height: 180px;
            padding: 14px 16px;
            border: 1px solid rgba(10, 17, 31, 0.1);
            border-radius: 20px;
            resize: vertical;
            font-size: var(--type-body);
            background: white;
        }

        .ticket-chat-input:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(201, 162, 75, 0.14);
        }

        body.support-ticket-modal-open {
            overflow: hidden;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .metrics-grid { grid-template-columns: repeat(2, 1fr); }
            .ticket-detail-grid { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 768px) {
            .metrics-grid { grid-template-columns: repeat(2, 1fr); gap: var(--space-3); margin-bottom: var(--space-5); }
            .filter-grid { grid-template-columns: minmax(0, 1fr) auto; }
            .filter-group-search { grid-column: 1; grid-row: 1; }
            .filter-group-submit { grid-column: 2; grid-row: 1; }
            .filter-group-status { grid-column: 1 / -1; grid-row: 2; }
            .filter-group-submit .btn { min-height: 46px; }
            .ticket-chat-modal { padding: 12px; }
            .ticket-chat-dialog {
                width: 100%;
                max-height: calc(100vh - 24px);
                border-radius: 24px;
            }
            .ticket-chat-header,
            .ticket-chat-toolbar,
            .ticket-chat-body,
            .ticket-chat-composer {
                padding-left: 18px;
                padding-right: 18px;
            }
            .ticket-chat-header {
                padding-top: 18px;
            }
            .ticket-chat-title {
                font-size: 22px;
            }
            .ticket-chat-toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            .ticket-chat-status-form {
                width: 100%;
            }
            .ticket-chat-status-form .filter-select,
            .ticket-chat-status-form .btn {
                flex: 1 1 0;
            }
            .ticket-chat-bubble {
                max-width: 100%;
            }
            .ticket-chat-composer {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
    <?php
}

// Define page actions for layout
function layoutPageActions() {
    global $action;
    if ($action === 'list'):
    ?>
    <a href="settings.php" class="btn btn-secondary">
        <i class="fas fa-cog"></i>
        <span class="btn-text">Settings</span>
    </a>
    <?php
    endif;
}

// Start the layout
layoutHeader();
?>

<?php if ($action === 'list'): ?>
    <!-- Stats Cards -->
    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-header">
                <span class="metric-title">Total Tickets</span>
                <div class="metric-icon total">
                    <i class="fas fa-ticket-alt"></i>
                </div>
            </div>
            <div class="metric-value">
                <?php
                $stmt = $db->getConnection()->query("SELECT COUNT(*) as count FROM support_tickets");
                echo $stmt->fetch()['count'];
                ?>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-header">
                <span class="metric-title">Open</span>
                <div class="metric-icon open">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
            </div>
            <div class="metric-value">
                <?php
                $stmt = $db->getConnection()->query("SELECT COUNT(*) as count FROM support_tickets WHERE status='open'");
                echo $stmt->fetch()['count'];
                ?>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-header">
                <span class="metric-title">In Progress</span>
                <div class="metric-icon progress">
                    <i class="fas fa-spinner"></i>
                </div>
            </div>
            <div class="metric-value">
                <?php
                $stmt = $db->getConnection()->query("SELECT COUNT(*) as count FROM support_tickets WHERE status='in_progress'");
                echo $stmt->fetch()['count'];
                ?>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-header">
                <span class="metric-title">Resolved</span>
                <div class="metric-icon resolved">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <div class="metric-value">
                <?php
                $stmt = $db->getConnection()->query("SELECT COUNT(*) as count FROM support_tickets WHERE status='resolved'");
                echo $stmt->fetch()['count'];
                ?>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="filter-bar">
        <form method="GET">
            <input type="hidden" name="action" value="list">
            <div class="filter-grid">
                <div class="filter-group filter-group-search">
                    <label class="filter-label">Search</label>
                    <input type="text" name="search" class="filter-input"
                        value="<?php echo htmlspecialchars($searchQuery); ?>"
                        placeholder="Search tickets...">
                </div>
                
                <div class="filter-group filter-group-status">
                    <label class="filter-label">Status</label>
                    <select name="status" class="filter-select">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="open" <?php echo $statusFilter === 'open' ? 'selected' : ''; ?>>Open</option>
                        <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="resolved" <?php echo $statusFilter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        <option value="closed" <?php echo $statusFilter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>
                
                <div class="filter-group filter-group-submit">
                    <label class="filter-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        <span class="btn-text">Filter</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Tickets Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Subject</th>
                            <th>User</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $where = [];
                        $params = [];
                        
                        if ($statusFilter !== 'all') {
                            $where[] = "status = :status";
                            $params[':status'] = $statusFilter;
                        }
                        
                        if (!empty($searchQuery)) {
                            $where[] = "(subject LIKE :search OR message LIKE :search2 OR email LIKE :search3)";
                            $params[':search'] = "%$searchQuery%";
                            $params[':search2'] = "%$searchQuery%";
                            $params[':search3'] = "%$searchQuery%";
                        }
                        
                        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
                        
                        $sql = "SELECT st.*, u.first_name, u.last_name, u.email 
                                FROM support_tickets st
                                LEFT JOIN users u ON st.user_id = u.user_id
                                $whereSql
                                ORDER BY st.created_at DESC
                                LIMIT 50";
                        
                        $stmt = $db->getConnection()->prepare($sql);
                        foreach ($params as $key => $value) {
                            $stmt->bindValue($key, $value);
                        }
                        $stmt->execute();
                        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (empty($tickets)):
                        ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: var(--space-6); color: var(--text-muted);">
                                    No tickets found matching your criteria.
                                </td>
                            </tr>
                        <?php
                        else:
                            foreach ($tickets as $ticket):
                                $statusClass = 'status-' . str_replace(' ', '_', $ticket['status']);
                        ?>
                            <tr class="support-ticket-row" tabindex="0" role="button" data-ticket-id="<?php echo (int) $ticket['ticket_id']; ?>" data-ticket-url="?action=view&id=<?php echo (int) $ticket['ticket_id']; ?>">
                                <td><code>#<?php echo $ticket['ticket_id']; ?></code></td>
                                <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                <td>
                                    <?php echo $ticket['first_name'] ? htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']) : 'Anonymous'; ?>
                                    <?php if ($ticket['email']): ?>
                                        <br><small style="color: var(--text-muted);"><?php echo htmlspecialchars($ticket['email']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $ticket['category']))); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $ticket['status']))); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($ticket['created_at'])); ?></td>
                                <td>
                                    <a href="?action=view&id=<?php echo $ticket['ticket_id']; ?>" class="btn btn-sm btn-secondary support-ticket-view-link">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php 
                            endforeach;
                        endif;
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="ticket-chat-modal" id="ticketChatModal" aria-hidden="true">
        <div class="ticket-chat-dialog" role="dialog" aria-modal="true" aria-labelledby="ticketChatTitle">
            <div class="ticket-chat-header">
                <div class="ticket-chat-heading">
                    <div class="ticket-chat-kicker" id="ticketChatKicker">Support Ticket</div>
                    <h2 class="ticket-chat-title" id="ticketChatTitle">Ticket conversation</h2>
                    <div class="ticket-chat-meta" id="ticketChatMeta">Loading conversation...</div>
                </div>
                <button class="ticket-chat-close" type="button" aria-label="Close ticket conversation" onclick="closeTicketChatModal()">&times;</button>
            </div>
            <div class="ticket-chat-toolbar">
                <div class="ticket-chat-summary">
                    <div class="ticket-chat-pill" id="ticketChatUserPill">User</div>
                    <div class="ticket-chat-pill" id="ticketChatCategoryPill">Category</div>
                    <div class="ticket-chat-pill" id="ticketChatStatusPill">Status</div>
                </div>
                <form class="ticket-chat-status-form" id="ticketChatStatusForm">
                    <input type="hidden" name="ticket_id" id="ticketChatStatusTicketId" value="">
                    <select name="status" class="filter-select" id="ticketChatStatusSelect">
                        <option value="open">Open</option>
                        <option value="in_progress">In Progress</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </form>
            </div>
            <div class="ticket-chat-body">
                <div class="ticket-chat-thread" id="ticketChatThread">
                    <div class="ticket-chat-empty">Loading conversation...</div>
                </div>
            </div>
            <form class="ticket-chat-composer" id="ticketChatComposer">
                <input type="hidden" name="ticket_id" id="ticketChatTicketId" value="">
                <textarea name="message" id="ticketChatInput" class="ticket-chat-input" placeholder="Reply to this ticket..."></textarea>
                <button type="submit" class="btn btn-primary">Send Message</button>
            </form>
        </div>
    </div>
    
<?php elseif ($action === 'view' && $ticketId): ?>
    <?php
    $ticket = $ticketModel->getById($ticketId);
    if (!$ticket):
        header('Location:?action=list');
        exit;
    endif;
    
    $responses = $ticketModel->getResponses($ticketId);
    $attachments = $ticketModel->getAttachments($ticketId);
    ?>
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            <a href="?action=list" style="color: var(--gold); text-decoration: none; display: inline-flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-2);">
                <i class="fas fa-arrow-left"></i> Back to Tickets
            </a>
            <h1><?php echo htmlspecialchars($ticket['subject']); ?></h1>
            <p>Ticket #<?php echo $ticket['ticket_id']; ?></p>
        </div>
        <form method="POST" style="display: flex; gap: var(--space-2); align-items: center;">
            <input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id']; ?>">
            <select name="status" class="filter-select" style="width: auto;">
                <option value="open" <?php echo $ticket['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                <option value="in_progress" <?php echo $ticket['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="resolved" <?php echo $ticket['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                <option value="closed" <?php echo $ticket['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
            </select>
            <button type="submit" name="update_status" class="btn btn-primary">
                <i class="fas fa-save"></i> Update Status
            </button>
        </form>
    </div>
    
    <?php if ($successMessage): ?>
        <div class="alert alert-success"><?php echo e($successMessage); ?></div>
    <?php endif; ?>
    
    <?php if ($errorMessage): ?>
        <div class="alert alert-error"><?php echo e($errorMessage); ?></div>
    <?php endif; ?>
    
    <div class="ticket-detail-grid">
        <!-- Left Column: Ticket Info -->
        <div>
            <!-- User Info -->
            <div class="detail-section">
                <h3 class="detail-section-title">User Information</h3>
                <div class="detail-row">
                    <span class="detail-label">Name</span>
                    <span class="detail-value">
                        <?php echo $ticket['first_name'] ? htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']) : 'Anonymous'; ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email</span>
                    <span class="detail-value">
                        <?php echo $ticket['email'] ? htmlspecialchars($ticket['email']) : 'N/A'; ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Category</span>
                    <span class="detail-value">
                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $ticket['category']))); ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span class="detail-value">
                        <span class="status-badge status-<?php echo str_replace(' ', '_', $ticket['status']); ?>">
                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $ticket['status']))); ?>
                        </span>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Created</span>
                    <span class="detail-value">
                        <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?>
                    </span>
                </div>
            </div>
            
            <!-- Original Message -->
            <div class="detail-section">
                <h3 class="detail-section-title">Original Message</h3>
                <div class="message-box">
                    <?php echo nl2br(htmlspecialchars($ticket['message'])); ?>
                </div>
            </div>
            
            <!-- Attachments -->
            <?php if (!empty($attachments)): ?>
            <div class="detail-section">
                <h3 class="detail-section-title">Attachments</h3>
                <?php foreach ($attachments as $attachment): ?>
                    <div class="attachment-item">
                        <i class="fas fa-paperclip attachment-icon"></i>
                        <span class="attachment-name"><?php echo htmlspecialchars($attachment['file_name']); ?></span>
                        <span class="attachment-date"><?php echo date('M j, Y', strtotime($attachment['created_at'])); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Responses -->
            <div class="detail-section">
                <h3 class="detail-section-title">Responses</h3>
                <?php if (empty($responses)): ?>
                    <p style="color: var(--text-muted);">No responses yet.</p>
                <?php else: ?>
                    <?php foreach ($responses as $response): ?>
                        <div class="response-item <?php echo $response['is_internal'] ? 'internal' : ''; ?>">
                            <div class="response-header">
                                <span class="response-author">
                                    <?php echo $response['admin_name'] ? htmlspecialchars($response['admin_name']) : 'System'; ?>
                                    <?php if ($response['is_internal']): ?>
                                        <span style="color: var(--text-muted); font-size: var(--type-body-xs);">(Internal Note)</span>
                                    <?php endif; ?>
                                </span>
                                <span class="response-time">
                                    <?php echo date('M j, Y g:i A', strtotime($response['created_at'])); ?>
                                </span>
                            </div>
                            <div class="response-content">
                                <?php echo nl2br(htmlspecialchars($response['response'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Add Response -->
            <div class="detail-section">
                <h3 class="detail-section-title">Add Response</h3>
                <form method="POST" class="response-form">
                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id']; ?>">
                    <textarea name="response" class="response-textarea" placeholder="Type your response..."></textarea>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: var(--space-4);">
                        <label style="display: flex; align-items: center; gap: var(--space-2);">
                            <input type="checkbox" name="is_internal"> Internal Note
                        </label>
                        <button type="submit" name="add_response" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Response
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Right Column: Quick Actions -->
        <div>
            <div class="detail-section">
                <h3 class="detail-section-title">Quick Actions</h3>
                <form method="POST">
                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id']; ?>">
                    <button type="submit" name="update_status" value="in_progress" class="quick-action-btn">
                        <i class="fas fa-spinner"></i> Mark as In Progress
                    </button>
                    <button type="submit" name="update_status" value="resolved" class="quick-action-btn">
                        <i class="fas fa-check-circle"></i> Mark as Resolved
                    </button>
                    <button type="submit" name="update_status" value="closed" class="quick-action-btn danger">
                        <i class="fas fa-times-circle"></i> Close Ticket
                    </button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
function layoutCustomScripts() {
    global $action;
    if ($action !== 'list') {
        return;
    }
    ?>
    <script>
        const supportTicketApiUrl = 'api/support-ticket.php';
        const supportTicketCsrfToken = '<?php echo e(generateCSRFToken()); ?>';
        const ticketChatModal = document.getElementById('ticketChatModal');
        const ticketChatThread = document.getElementById('ticketChatThread');
        const ticketChatTitle = document.getElementById('ticketChatTitle');
        const ticketChatMeta = document.getElementById('ticketChatMeta');
        const ticketChatUserPill = document.getElementById('ticketChatUserPill');
        const ticketChatCategoryPill = document.getElementById('ticketChatCategoryPill');
        const ticketChatStatusPill = document.getElementById('ticketChatStatusPill');
        const ticketChatStatusSelect = document.getElementById('ticketChatStatusSelect');
        const ticketChatStatusTicketId = document.getElementById('ticketChatStatusTicketId');
        const ticketChatTicketId = document.getElementById('ticketChatTicketId');
        const ticketChatInput = document.getElementById('ticketChatInput');

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function formatSupportLabel(value) {
            return String(value ?? '')
                .replace(/_/g, ' ')
                .replace(/\b\w/g, function (char) {
                    return char.toUpperCase();
                });
        }

        function formatSupportDate(value) {
            if (!value) {
                return '';
            }

            const date = new Date(value.replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) {
                return value;
            }

            return date.toLocaleString([], {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit'
            });
        }

        function renderTicketMessages(messages) {
            if (!ticketChatThread) {
                return;
            }

            if (!Array.isArray(messages) || messages.length === 0) {
                ticketChatThread.innerHTML = '<div class="ticket-chat-empty">No messages yet.</div>';
                return;
            }

            ticketChatThread.innerHTML = messages.map(function (message) {
                const isAdmin = Boolean(Number(message.is_admin));
                const isOriginal = message.type === 'original';
                const author = escapeHtml(message.author || (isAdmin ? 'Admin' : 'User'));
                const time = escapeHtml(formatSupportDate(message.created_at));
                const text = escapeHtml(message.message || '');
                const typeLabel = isOriginal ? 'Original request' : author;

                return `
                    <div class="ticket-chat-message ${isAdmin ? 'is-admin' : ''} ${isOriginal ? 'is-original' : ''}">
                        <div class="ticket-chat-bubble">
                            <div class="ticket-chat-author">
                                <span>${typeLabel}</span>
                                <span>${time}</span>
                            </div>
                            <div class="ticket-chat-text">${text}</div>
                        </div>
                    </div>
                `;
            }).join('');

            ticketChatThread.scrollTop = ticketChatThread.scrollHeight;
        }

        function applyTicketData(payload) {
            const ticket = payload.ticket || {};
            const userName = ticket.user_name || 'Anonymous';
            const userEmail = ticket.user_email || 'No email';

            ticketChatTitle.textContent = ticket.subject || 'Ticket conversation';
            ticketChatMeta.textContent = `Ticket #${ticket.ticket_id} opened ${formatSupportDate(ticket.created_at)}`;
            ticketChatUserPill.textContent = `${userName}${userEmail !== 'No email' ? ' - ' + userEmail : ''}`;
            ticketChatCategoryPill.textContent = formatSupportLabel(ticket.category || 'general');
            ticketChatStatusPill.textContent = formatSupportLabel(ticket.status || 'open');
            ticketChatStatusSelect.value = ticket.status || 'open';
            ticketChatStatusTicketId.value = ticket.ticket_id || '';
            ticketChatTicketId.value = ticket.ticket_id || '';
            renderTicketMessages(payload.messages || []);
        }

        async function loadTicketConversation(ticketId) {
            const response = await fetch(`${supportTicketApiUrl}?action=get_ticket&id=${encodeURIComponent(ticketId)}`, {
                headers: {
                    'Accept': 'application/json'
                }
            });

            const payload = await response.json();
            if (!response.ok || !payload.success) {
                throw new Error(payload.error || 'Unable to load ticket');
            }

            applyTicketData(payload);
        }

        async function openTicketChatModal(ticketId, fallbackUrl) {
            if (!ticketChatModal) {
                window.location.href = fallbackUrl;
                return;
            }

            ticketChatModal.classList.add('active');
            ticketChatModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('support-ticket-modal-open');
            ticketChatThread.innerHTML = '<div class="ticket-chat-empty">Loading conversation...</div>';
            ticketChatMeta.textContent = 'Loading conversation...';

            try {
                await loadTicketConversation(ticketId);
                ticketChatInput.focus();
            } catch (error) {
                ticketChatModal.classList.remove('active');
                ticketChatModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('support-ticket-modal-open');
                window.location.href = fallbackUrl;
            }
        }

        function closeTicketChatModal() {
            if (!ticketChatModal) {
                return;
            }

            ticketChatModal.classList.remove('active');
            ticketChatModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('support-ticket-modal-open');
            ticketChatTicketId.value = '';
            ticketChatStatusTicketId.value = '';
            ticketChatInput.value = '';
        }

        document.querySelectorAll('.support-ticket-row').forEach(function (row) {
            const ticketId = row.dataset.ticketId;
            const fallbackUrl = row.dataset.ticketUrl || '?action=view&id=' + ticketId;

            row.addEventListener('click', function (event) {
                if (event.target.closest('a, button, input, select, textarea')) {
                    return;
                }

                openTicketChatModal(ticketId, fallbackUrl);
            });

            row.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openTicketChatModal(ticketId, fallbackUrl);
                }
            });
        });

        document.querySelectorAll('.support-ticket-view-link').forEach(function (link) {
            link.addEventListener('click', function (event) {
                const row = link.closest('.support-ticket-row');
                if (!row) {
                    return;
                }

                event.preventDefault();
                openTicketChatModal(row.dataset.ticketId, link.href);
            });
        });

        ticketChatModal?.addEventListener('click', function (event) {
            if (event.target === ticketChatModal) {
                closeTicketChatModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && ticketChatModal?.classList.contains('active')) {
                closeTicketChatModal();
            }
        });

        document.getElementById('ticketChatComposer')?.addEventListener('submit', async function (event) {
            event.preventDefault();

            const ticketId = ticketChatTicketId.value;
            const message = ticketChatInput.value.trim();
            if (!ticketId || !message) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('ticket_id', ticketId);
            formData.append('message', message);
            formData.append('csrf_token', supportTicketCsrfToken);

            const response = await fetch(supportTicketApiUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            });

            const payload = await response.json();
            if (!response.ok || !payload.success) {
                alert(payload.error || 'Unable to send message.');
                return;
            }

            ticketChatInput.value = '';
            applyTicketData(payload);
        });

        document.getElementById('ticketChatStatusForm')?.addEventListener('submit', async function (event) {
            event.preventDefault();

            const ticketId = ticketChatStatusTicketId.value;
            const status = ticketChatStatusSelect.value;
            if (!ticketId || !status) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('ticket_id', ticketId);
            formData.append('status', status);
            formData.append('csrf_token', supportTicketCsrfToken);

            const response = await fetch(supportTicketApiUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            });

            const payload = await response.json();
            if (!response.ok || !payload.success) {
                alert(payload.error || 'Unable to update status.');
                return;
            }

            applyTicketData(payload);
        });
    </script>
    <?php
}
// End the layout
layoutFooter();
?>
