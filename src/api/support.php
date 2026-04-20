<?php
/**
 * Support Ticket API
 * Bright Light Ministry Int'l Partners Portal
 * 
 * Handles AJAX requests for support ticket operations
 */

require_once __DIR__ . '/../bootstrap.php';
requireVerifiedUser();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/Security.php';
require_once __DIR__ . '/../models/SupportTicket.php';

header('Content-Type: application/json');

$userId = (int) $_SESSION['user_id'];
$ticketModel = new SupportTicket();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_tickets':
            // Get all tickets for the user
            $tickets = $ticketModel->getUserTickets($userId);
            echo json_encode(['success' => true, 'tickets' => $tickets]);
            break;

        case 'get_ticket':
            $ticketId = (int) ($_GET['id'] ?? 0);
            if ($ticketId <= 0) {
                throw new Exception('Invalid ticket ID');
            }
            
            $ticket = $ticketModel->getById($ticketId);
            if (!$ticket || $ticket['user_id'] != $userId) {
                throw new Exception('Ticket not found');
            }
            
            // Get messages for this ticket
            $messages = $ticketModel->getMessages($ticketId);
            
            echo json_encode([
                'success' => true, 
                'ticket' => $ticket,
                'messages' => $messages
            ]);
            break;

        case 'send_message':
            $ticketId = (int) ($_POST['ticket_id'] ?? 0);
            $message = Security::sanitizeMessage($_POST['message'] ?? '');
            
            if ($ticketId <= 0) {
                throw new Exception('Invalid ticket ID');
            }
            
            if (empty($message)) {
                throw new Exception('Message cannot be empty');
            }
            
            // Verify ticket belongs to user
            $ticket = $ticketModel->getById($ticketId);
            if (!$ticket || $ticket['user_id'] != $userId) {
                throw new Exception('Ticket not found');
            }
            
            $messageId = $ticketModel->sendMessage($ticketId, $userId, $message);
            
            if ($messageId) {
                echo json_encode([
                    'success' => true, 
                    'message_id' => $messageId,
                    'message' => $message,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            } else {
                throw new Exception('Failed to send message');
            }
            break;

        case 'create_ticket':
            // Verify CSRF token
            $submittedToken = $_POST['csrf_token'] ?? '';
            $expectedToken = $_SESSION['csrf_token'] ?? '';
            
            if ($submittedToken === '' || $expectedToken === '' || !hash_equals($expectedToken, $submittedToken)) {
                throw new Exception('Invalid security token');
            }
            
            $subject = Security::sanitizeSubject($_POST['subject'] ?? '');
            $message = Security::sanitizeMessage($_POST['message'] ?? '');
            $category = Security::sanitizeCategory($_POST['category'] ?? '');
            
            if (empty($subject) || empty($message) || $category === false) {
                throw new Exception('Please fill in all required fields');
            }
            
            $ticketId = $ticketModel->create([
                'user_id' => $userId,
                'subject' => $subject,
                'message' => $message,
                'category' => $category,
                'status' => 'open'
            ]);
            
            if ($ticketId) {
                echo json_encode([
                    'success' => true, 
                    'ticket_id' => $ticketId,
                    'message' => 'Ticket created successfully'
                ]);
            } else {
                throw new Exception('Failed to create ticket');
            }
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}