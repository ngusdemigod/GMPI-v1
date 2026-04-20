<?php
/**
 * Support Ticket Page
 * Bright Light Ministry Int'l Partners Portal
 * 
 * Features:
 * - List of all user's support tickets in a table
 * - "Report an Issue" CTA button to create new tickets
 * - Ticket details modal with chat system
 */

require_once __DIR__ . '/bootstrap.php';
requireVerifiedUser();

// Load security classes
require_once __DIR__ . '/config/Security.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/ResendService.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/SupportTicket.php';
require_once __DIR__ . '/helpers/system_settings.php';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = Security::generateCsrfToken();
}

// Get user info
$userId = (int) $_SESSION['user_id'];
$user = null;
$userModel = new User();
$user = $userModel->getById($userId);

$portalSettings = getPortalSettings();
$churchName = $portalSettings['church_name'];
$portalSubtitle = $portalSettings['portal_subtitle'];

$userInitials = 'DA';
if ($user) {
    $userInitials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
}

// Get user's tickets
$ticketModel = new SupportTicket();
$userTickets = $ticketModel->getByUser($userId);

/**
 * Notify all admins about a newly submitted support ticket.
 */
function notifyAdminsOfSupportTicket(SupportTicket $ticketModel, int $ticketId, array $user): void
{
    try {
        $ticket = $ticketModel->getById($ticketId);
        if (!$ticket) {
            return;
        }

        $recipients = $ticketModel->getAdminNotificationRecipients();
        if (empty($recipients)) {
            error_log('[SupportTicketNotification] No admin recipients available for support notifications.');
            return;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
        $rootPath = preg_replace('#/src$#', '', $basePath) ?: '';
        $adminUrl = $scheme . '://' . $host . $rootPath . '/admin/support.php?action=view&id=' . $ticketId;

        $mailer = new ResendService();
        $submittedBy = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $payload = [
            'ticket_id' => $ticketId,
            'subject_line' => $ticket['subject'] ?? 'Support request',
            'category' => $ticket['category'] ?? 'other',
            'submitted_by' => $submittedBy !== '' ? $submittedBy : 'Authenticated user',
            'submitter_email' => $user['email'] ?? '',
            'message' => $ticket['message'] ?? '',
            'admin_url' => $adminUrl,
        ];

        foreach ($recipients as $recipient) {
            $result = $mailer->sendSupportTicketNotification(array_merge($payload, [
                'email' => $recipient['email'],
            ]));

            if (empty($result['success'])) {
                error_log(sprintf(
                    '[SupportTicketNotification] Failed to notify admin %s for ticket #%d: %s',
                    $recipient['email'],
                    $ticketId,
                    $result['error'] ?? 'unknown error'
                ));
            }
        }
    } catch (Throwable $e) {
        error_log('[SupportTicketNotification] Unexpected failure: ' . $e->getMessage());
    }
}

// Handle form submission (for creating new tickets)
$successMessage = '';
$errorMessage = '';
$formErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_ticket') {
    // Verify CSRF token
    $submittedToken = $_POST['csrf_token'] ?? '';
    $expectedToken = $_SESSION['csrf_token'] ?? '';
    
    if ($submittedToken === '' || $expectedToken === '' || !hash_equals($expectedToken, $submittedToken)) {
        $errorMessage = 'Invalid security token. Please refresh the page and try again.';
    } else {
        // Check rate limit
        $identifier = $userId ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateLimitResult = Security::checkRateLimit(
            $identifier,
            Security::RATE_LIMIT_REQUESTS,
            Security::RATE_LIMIT_WINDOW,
            'session'
        );
        
        if (!$rateLimitResult['allowed']) {
            $errorMessage = 'Too many requests. Please try again in ' . 
                $rateLimitResult['retryAfter'] . ' seconds.';
        } else {
            // Sanitize inputs using Security class
            $subject = Security::sanitizeSubject($_POST['subject'] ?? '');
            $message = Security::sanitizeMessage($_POST['message'] ?? '');
            $category = Security::sanitizeCategory($_POST['category'] ?? '');
            
            // Validate required fields
            if (empty($subject)) {
                $formErrors['subject'] = 'Subject is required';
            }
            if (empty($message)) {
                $formErrors['message'] = 'Message is required';
            }
            if ($category === false) {
                $formErrors['category'] = 'Please select a valid category';
            }
            
            // If no validation errors, proceed with ticket creation
            if (empty($formErrors)) {
                try {
                    $ticketModel = new SupportTicket();
                    $ticketId = $ticketModel->create([
                        'user_id' => $userId,
                        'subject' => $subject,
                        'message' => $message,
                        'category' => $category,
                        'status' => 'open'
                    ]);
                    
                    if ($ticketId) {
                        notifyAdminsOfSupportTicket($ticketModel, (int) $ticketId, $user ?? []);
                        $successMessage = 'Your support ticket has been submitted successfully. We will respond within 24-48 hours.';
                        
                        // Regenerate CSRF token after successful submission
                        $_SESSION['csrf_token'] = Security::generateCsrfToken();
                        
                        // Refresh tickets
                        $userTickets = $ticketModel->getByUser($userId);
                    } else {
                        $errorMessage = 'Failed to create support ticket. Please try again.';
                    }
                } catch (Exception $e) {
                    error_log("Support ticket error: " . $e->getMessage());
                    $errorMessage = 'An error occurred while submitting your ticket. Please try again.';
                }
            } else {
                $errorMessage = 'Please correct the errors in the form.';
            }
        }
    }
}

function getRecaptchaSiteKey() {
    return defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : '';
}

// Status badge colors
function getStatusBadgeClass($status) {
    $badges = [
        'open' => 'bg-blue-100 text-blue-700',
        'in_progress' => 'bg-amber-100 text-amber-700',
        'resolved' => 'bg-green-100 text-green-700',
        'closed' => 'bg-gray-100 text-gray-600',
    ];
    return $badges[$status] ?? 'bg-gray-100 text-gray-600';
}

function getStatusLabel($status) {
    $labels = [
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];
    return $labels[$status] ?? ucfirst($status);
}

function getCategoryLabel($category) {
    $labels = [
        'technical' => 'Technical Issue',
        'payment' => 'Payment/Donation',
        'account' => 'Account Access',
        'project' => 'Project Inquiry',
        'other' => 'Other',
    ];
    return $labels[$category] ?? ucfirst($category);
}

function formatDate($dateStr) {
    $date = new DateTime($dateStr);
    return $date->format('M j, Y g:i A');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Support - <?php echo htmlspecialchars($churchName . ' ' . $portalSubtitle); ?></title>
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
  .font-serif { font-family: 'Bricolage Grotesque', sans-serif; }
  
  /* Modal styles */
  .modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
  }
  .modal-overlay.active {
    display: flex;
  }
  
  /* Chat styles */
  .chat-container {
    max-height: 400px;
    overflow-y: auto;
  }
  .chat-message {
    margin-bottom: 1rem;
  }
  .chat-message.user {
    text-align: right;
  }
  .chat-message.admin {
    text-align: left;
  }
  .chat-bubble {
    display: inline-block;
    max-width: 80%;
    padding: 0.75rem 1rem;
    border-radius: 1rem;
  }
  .chat-message.user .chat-bubble {
    background: #C9A24B;
    color: #122137;
    border-bottom-right-radius: 0.25rem;
  }
  .chat-message.admin .chat-bubble {
    background: #E7D9B4;
    color: #0F1B2D;
    border-bottom-left-radius: 0.25rem;
  }
  .chat-time {
    font-size: 0.75rem;
    color: rgba(15, 27, 45, 0.5);
    margin-top: 0.25rem;
  }
</style>
</head>
<body class="min-h-screen">

<!-- NAV -->
<?php include 'header.php'; ?>

<main class="max-w-5xl mx-auto px-5 lg:px-10 py-10">

  <!-- BREADCRUMB -->
  <nav class="flex items-center gap-2 text-sm text-ink/50 mb-6">
    <a href="index.php" class="hover:text-ink">Home</a>
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
    <span class="text-ink font-medium">Support</span>
  </nav>

  <!-- HEADER -->
  <section class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    <div>
      <h1 class="font-serif text-4xl md:text-5xl">
        Get Support
      </h1>
      <p class="text-ink/60 mt-2 text-lg">
        Have questions or need assistance? We're here to help.
      </p>
    </div>
    <!-- REPORT AN ISSUE CTA BUTTON -->
    <button onclick="openCreateModal()" 
      class="bg-gold hover:bg-goldsoft text-deep font-semibold px-6 py-3 rounded-xl transition flex items-center justify-center gap-2 whitespace-nowrap"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      Report an Issue
    </button>
  </section>

  <!-- SUCCESS MESSAGE -->
  <?php if ($successMessage): ?>
  <div class="bg-sage/10 border border-sage rounded-2xl p-6 mb-6">
    <div class="flex items-start gap-3">
      <svg class="w-6 h-6 text-sage flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <div>
        <h3 class="font-serif text-lg text-sage mb-1">Ticket Submitted Successfully!</h3>
        <p class="text-sage/80"><?php echo htmlspecialchars($successMessage); ?></p>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ERROR MESSAGE -->
  <?php if ($errorMessage): ?>
  <div class="bg-red-50 border border-red-200 rounded-2xl p-6 mb-6">
    <div class="flex items-start gap-3">
      <svg class="w-6 h-6 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <div>
        <h3 class="font-serif text-lg text-red-700 mb-1">Error</h3>
        <p class="text-red-600"><?php echo htmlspecialchars($errorMessage); ?></p>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- TICKETS TABLE -->
  <div class="bg-white rounded-3xl border border-ink/10 overflow-hidden">
    <div class="p-6 border-b border-ink/10">
      <h2 class="font-serif text-2xl">Your Support Tickets</h2>
      <p class="text-ink/60 text-sm mt-1">View and manage your support requests</p>
    </div>
    
    <?php if (empty($userTickets)): ?>
    <div class="p-12 text-center">
      <div class="w-16 h-16 rounded-full bg-gold/10 flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-gold" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
      </div>
      <h3 class="font-serif text-xl mb-2">No Tickets Yet</h3>
      <p class="text-ink/60 mb-6">You haven't submitted any support tickets.</p>
      <button onclick="openCreateModal()" 
        class="bg-gold hover:bg-goldsoft text-deep font-semibold px-6 py-3 rounded-xl transition inline-flex items-center gap-2"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Report an Issue
      </button>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-cream/50">
          <tr>
            <th class="text-left px-6 py-4 text-sm font-medium text-ink/60">Ticket ID</th>
            <th class="text-left px-6 py-4 text-sm font-medium text-ink/60">Subject</th>
            <th class="text-left px-6 py-4 text-sm font-medium text-ink/60">Category</th>
            <th class="text-left px-6 py-4 text-sm font-medium text-ink/60">Status</th>
            <th class="text-left px-6 py-4 text-sm font-medium text-ink/60">Created</th>
            <th class="text-left px-6 py-4 text-sm font-medium text-ink/60">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-ink/5">
          <?php foreach ($userTickets as $ticket): ?>
          <tr class="hover:bg-cream/30 transition">
            <td class="px-6 py-4 text-sm font-mono text-ink/50">#<?php echo str_pad($ticket['ticket_id'], 5, '0', STR_PAD_LEFT); ?></td>
            <td class="px-6 py-4">
              <div class="font-medium text-ink"><?php echo htmlspecialchars($ticket['subject']); ?></div>
            </td>
            <td class="px-6 py-4 text-sm text-ink/70"><?php echo getCategoryLabel($ticket['category']); ?></td>
            <td class="px-6 py-4">
              <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo getStatusBadgeClass($ticket['status']); ?>">
                <?php echo getStatusLabel($ticket['status']); ?>
              </span>
            </td>
            <td class="px-6 py-4 text-sm text-ink/60"><?php echo formatDate($ticket['created_at']); ?></td>
            <td class="px-6 py-4">
              <button onclick="openTicketModal(<?php echo $ticket['ticket_id']; ?>)" 
                class="text-gold hover:text-deep font-medium text-sm transition flex items-center gap-1"
              >
                View Details
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- CONTACT INFO -->
  <div class="mt-8 grid md:grid-cols-3 gap-4">
    <div class="bg-white rounded-2xl border border-ink/10 p-6 text-center">
      <div class="w-12 h-12 rounded-full bg-gold/10 flex items-center justify-center mx-auto mb-3">
        <svg class="w-6 h-6 text-gold" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
      </div>
      <h4 class="font-serif text-lg mb-1">Email</h4>
      <p class="text-sm text-ink/60"><?php echo htmlspecialchars($portalSettings['support_email'] ?? 'support@brightlightministry.org'); ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-ink/10 p-6 text-center">
      <div class="w-12 h-12 rounded-full bg-gold/10 flex items-center justify-center mx-auto mb-3">
        <svg class="w-6 h-6 text-gold" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
      </div>
      <h4 class="font-serif text-lg mb-1">Phone</h4>
      <p class="text-sm text-ink/60"><?php echo htmlspecialchars($portalSettings['support_phone'] ?? '+234 800 SUPPORT'); ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-ink/10 p-6 text-center">
      <div class="w-12 h-12 rounded-full bg-gold/10 flex items-center justify-center mx-auto mb-3">
        <svg class="w-6 h-6 text-gold" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      </div>
      <h4 class="font-serif text-lg mb-1">Response Time</h4>
      <p class="text-sm text-ink/60"><?php echo htmlspecialchars($portalSettings['auto_resolve_days'] ?? '24-48'); ?> hours</p>
    </div>
  </div>

</main>

<!-- CREATE TICKET MODAL -->
<div id="createModal" class="modal-overlay">
  <div class="bg-white rounded-3xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
    <div class="p-6 border-b border-ink/10 flex items-center justify-between">
      <h2 class="font-serif text-2xl">Report an Issue</h2>
      <button onclick="closeCreateModal()" class="text-ink/50 hover:text-ink transition">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    
    <form method="POST" action="support.php" class="p-6 space-y-6">
      <input type="hidden" name="action" value="create_ticket">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
      
      <!-- Subject -->
      <div>
        <label for="subject" class="block text-sm font-medium text-ink mb-2">
          Subject <span class="text-red-500">*</span>
        </label>
        <input type="text" id="subject" name="subject" required
          class="w-full px-4 py-3 rounded-xl border border-ink/15 focus:border-gold focus:ring-2 focus:ring-gold/20 outline-none transition"
          placeholder="Brief description of your issue"
        />
      </div>

      <!-- Category -->
      <div>
        <label for="category" class="block text-sm font-medium text-ink mb-2">
          Category <span class="text-red-500">*</span>
        </label>
        <select id="category" name="category" required
          class="w-full px-4 py-3 rounded-xl border border-ink/15 focus:border-gold focus:ring-2 focus:ring-gold/20 outline-none transition bg-white"
        >
          <option value="">Select a category</option>
          <option value="technical">Technical Issue</option>
          <option value="payment">Payment/Donation</option>
          <option value="account">Account Access</option>
          <option value="project">Project Inquiry</option>
          <option value="other">Other</option>
        </select>
      </div>

      <!-- Message -->
      <div>
        <label for="message" class="block text-sm font-medium text-ink mb-2">
          Message <span class="text-red-500">*</span>
        </label>
        <textarea id="message" name="message" rows="6" required
          class="w-full px-4 py-3 rounded-xl border border-ink/15 focus:border-gold focus:ring-2 focus:ring-gold/20 outline-none transition resize-none"
          placeholder="Please describe your issue in detail..."
        ></textarea>
      </div>

      <!-- Submit Button -->
      <button type="submit"
        class="w-full bg-gold hover:bg-goldsoft text-deep font-semibold px-6 py-4 rounded-xl transition flex items-center justify-center gap-2"
      >
        Submit Support Ticket
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
      </button>
    </form>
  </div>
</div>

<!-- TICKET DETAILS MODAL -->
<div id="ticketModal" class="modal-overlay">
  <div class="bg-white rounded-3xl w-full max-w-3xl mx-4 max-h-[90vh] overflow-hidden flex flex-col">
    <!-- Modal Header -->
    <div class="p-6 border-b border-ink/10 flex items-center justify-between">
      <div>
        <h2 class="font-serif text-2xl" id="ticketSubject">Ticket Details</h2>
        <p class="text-sm text-ink/60 mt-1" id="ticketMeta"></p>
      </div>
      <button onclick="closeTicketModal()" class="text-ink/50 hover:text-ink transition">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    
    <!-- Ticket Info -->
    <div class="p-6 bg-cream/30 border-b border-ink/10">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div>
          <span class="text-xs text-ink/50 uppercase">Ticket ID</span>
          <p class="font-mono text-sm" id="ticketId"></p>
        </div>
        <div>
          <span class="text-xs text-ink/50 uppercase">Category</span>
          <p class="text-sm" id="ticketCategory"></p>
        </div>
        <div>
          <span class="text-xs text-ink/50 uppercase">Status</span>
          <p id="ticketStatus"></p>
        </div>
        <div>
          <span class="text-xs text-ink/50 uppercase">Created</span>
          <p class="text-sm" id="ticketCreated"></p>
        </div>
      </div>
      <div class="mt-4">
        <span class="text-xs text-ink/50 uppercase">Original Message</span>
        <p class="text-sm text-ink/80 mt-1" id="ticketMessage"></p>
      </div>
    </div>
    
    <!-- Chat Container -->
    <div class="flex-1 overflow-hidden flex flex-col">
      <div class="p-4 border-b border-ink/10 bg-cream/20">
        <h3 class="font-medium text-sm text-ink/70">Conversation</h3>
      </div>
      <div id="chatContainer" class="chat-container flex-1 p-4 space-y-4">
        <!-- Messages will be loaded here -->
      </div>
      
      <!-- Message Input -->
      <div class="p-4 border-t border-ink/10">
        <form id="chatForm" class="flex gap-3">
          <input type="hidden" id="chatTicketId" value="">
          <textarea id="chatMessage" rows="2" 
            class="flex-1 px-4 py-3 rounded-xl border border-ink/15 focus:border-gold focus:ring-2 focus:ring-gold/20 outline-none transition resize-none"
            placeholder="Type your message..."
          ></textarea>
          <button type="submit" 
            class="bg-gold hover:bg-goldsoft text-deep font-semibold px-6 py-3 rounded-xl transition flex items-center gap-2 self-end"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
            Send
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
// Modal functions
function openCreateModal() {
  document.getElementById('createModal').classList.add('active');
  document.body.style.overflow = 'hidden';
}

function closeCreateModal() {
  document.getElementById('createModal').classList.remove('active');
  document.body.style.overflow = '';
}

function openTicketModal(ticketId) {
  document.getElementById('ticketModal').classList.add('active');
  document.body.style.overflow = 'hidden';
  loadTicketDetails(ticketId);
}

function closeTicketModal() {
  document.getElementById('ticketModal').classList.remove('active');
  document.body.style.overflow = '';
}

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(modal => {
  modal.addEventListener('click', function(e) {
    if (e.target === this) {
      this.classList.remove('active');
      document.body.style.overflow = '';
    }
  });
});

// Load ticket details
async function loadTicketDetails(ticketId) {
  const chatContainer = document.getElementById('chatContainer');
  chatContainer.innerHTML = '<div class="flex items-center justify-center h-full"><div class="animate-spin w-6 h-6 border-2 border-gold border-t-transparent rounded-full"></div></div>';
  
  try {
    const response = await fetch(`api/support.php?action=get_ticket&id=${ticketId}`);
    const data = await response.json();
    
    if (data.success) {
      const ticket = data.ticket;
      const messages = data.messages;
      
      // Update ticket info
      document.getElementById('ticketSubject').textContent = ticket.subject;
      document.getElementById('ticketMeta').textContent = `Submitted on ${formatDate(ticket.created_at)}`;
      document.getElementById('ticketId').textContent = '#' + String(ticket.ticket_id).padStart(5, '0');
      document.getElementById('ticketCategory').textContent = getCategoryLabel(ticket.category);
      document.getElementById('ticketStatus').innerHTML = `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${getStatusBadgeClass(ticket.status)}">${getStatusLabel(ticket.status)}</span>`;
      document.getElementById('ticketCreated').textContent = formatDate(ticket.created_at);
      document.getElementById('ticketMessage').textContent = ticket.message;
      document.getElementById('chatTicketId').value = ticketId;
      
      // Load messages
      renderMessages(messages);
    } else {
      chatContainer.innerHTML = `<div class="text-center text-red-500 py-8">${data.error || 'Failed to load ticket'}</div>`;
    }
  } catch (error) {
    chatContainer.innerHTML = '<div class="text-center text-red-500 py-8">Failed to load ticket details</div>';
  }
}

// Render chat messages
function renderMessages(messages) {
  const chatContainer = document.getElementById('chatContainer');
  
  if (messages.length === 0) {
    chatContainer.innerHTML = '<div class="text-center text-ink/40 py-8">No messages yet. Start the conversation by sending a message below.</div>';
    return;
  }
  
  chatContainer.innerHTML = messages.map(msg => {
    const isAdmin = msg.is_admin == 1;
    const senderName = isAdmin ? (msg.first_name ? msg.first_name + ' ' + msg.last_name : 'Support Team') : 'You';
    const time = formatDate(msg.created_at);
    
    return `
      <div class="chat-message ${isAdmin ? 'admin' : 'user'}">
        <div class="chat-bubble">
          <div class="text-xs font-medium mb-1 ${isAdmin ? 'text-ink/50' : 'text-deep/60'}">${senderName}</div>
          <div>${escapeHtml(msg.message)}</div>
        </div>
        <div class="chat-time">${time}</div>
      </div>
    `;
  }).join('');
  
  // Scroll to bottom
  chatContainer.scrollTop = chatContainer.scrollHeight;
}

// Send message
document.getElementById('chatForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  
  const ticketId = document.getElementById('chatTicketId').value;
  const messageInput = document.getElementById('chatMessage');
  const message = messageInput.value.trim();
  
  if (!message) return;
  
  const submitBtn = this.querySelector('button[type="submit"]');
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<div class="animate-spin w-5 h-5 border-2 border-deep border-t-transparent rounded-full"></div>';
  
  try {
    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('ticket_id', ticketId);
    formData.append('message', message);
    
    const response = await fetch('api/support.php', {
      method: 'POST',
      body: formData
    });
    
    const data = await response.json();
    
    if (data.success) {
      messageInput.value = '';
      // Reload messages
      loadTicketDetails(ticketId);
    } else {
      alert(data.error || 'Failed to send message');
    }
  } catch (error) {
    alert('Failed to send message');
  } finally {
    submitBtn.disabled = false;
    submitBtn.innerHTML = `<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg> Send`;
  }
});

// Helper functions
function formatDate(dateStr) {
  const date = new Date(dateStr);
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}

function getCategoryLabel(category) {
  const labels = {
    'technical': 'Technical Issue',
    'payment': 'Payment/Donation',
    'account': 'Account Access',
    'project': 'Project Inquiry',
    'other': 'Other'
  };
  return labels[category] || category;
}

function getStatusBadgeClass(status) {
  const badges = {
    'open': 'bg-blue-100 text-blue-700',
    'in_progress': 'bg-amber-100 text-amber-700',
    'resolved': 'bg-green-100 text-green-700',
    'closed': 'bg-gray-100 text-gray-600'
  };
  return badges[status] || 'bg-gray-100 text-gray-600';
}

function getStatusLabel(status) {
  const labels = {
    'open': 'Open',
    'in_progress': 'In Progress',
    'resolved': 'Resolved',
    'closed': 'Closed'
  };
  return labels[status] || status;
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}
</script>

</body>
</html>