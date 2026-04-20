<?php
/**
 * Admin support ticket chat API.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../../src/models/SupportTicket.php';

header('Content-Type: application/json; charset=UTF-8');

$admin = requireAdmin();
$db = Database::getInstance();
$ticketModel = new SupportTicket();
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $requestMethod === 'POST' ? ($_POST['action'] ?? '') : ($_GET['action'] ?? '');

function supportTicketJsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function supportTicketPayload(Database $db, SupportTicket $ticketModel, int $ticketId): array
{
    $ticket = $ticketModel->getById($ticketId);
    if (!$ticket) {
        throw new RuntimeException('Ticket not found.');
    }

    $messageRows = $db->fetchAll(
        "SELECT stm.message_id,
                stm.message,
                stm.is_admin,
                stm.created_at,
                CASE
                    WHEN stm.is_admin = 1 THEN CONCAT(COALESCE(admin_user.first_name, ''), ' ', COALESCE(admin_user.last_name, ''))
                    ELSE CONCAT(COALESCE(user_user.first_name, ''), ' ', COALESCE(user_user.last_name, ''))
                END AS author_name,
                'message' AS type
         FROM support_ticket_messages stm
         LEFT JOIN admin_users au ON stm.is_admin = 1 AND stm.user_id = au.admin_id
         LEFT JOIN users admin_user ON au.user_id = admin_user.user_id
         LEFT JOIN users user_user ON stm.is_admin = 0 AND stm.user_id = user_user.user_id
         WHERE stm.ticket_id = :ticket_id",
        ['ticket_id' => $ticketId]
    );

    $responseRows = $db->fetchAll(
        "SELECT CONCAT('response-', str.response_id) AS message_id,
                str.response AS message,
                1 AS is_admin,
                str.created_at,
                CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS author_name,
                'response' AS type
         FROM support_ticket_responses str
         LEFT JOIN admin_users au ON str.admin_id = au.admin_id
         LEFT JOIN users u ON au.user_id = u.user_id
         WHERE str.ticket_id = :ticket_id",
        ['ticket_id' => $ticketId]
    );

    $messages = array_merge(
        [[
            'message_id' => 'original-' . $ticketId,
            'message' => (string) ($ticket['message'] ?? ''),
            'is_admin' => 0,
            'created_at' => $ticket['created_at'],
            'author_name' => trim((string) (($ticket['first_name'] ?? '') . ' ' . ($ticket['last_name'] ?? ''))) ?: 'User',
            'type' => 'original',
        ]],
        $messageRows,
        $responseRows
    );

    usort($messages, static function (array $left, array $right): int {
        return strcmp((string) ($left['created_at'] ?? ''), (string) ($right['created_at'] ?? ''));
    });

    return [
        'ticket' => [
            'ticket_id' => (int) $ticket['ticket_id'],
            'subject' => (string) ($ticket['subject'] ?? ''),
            'status' => (string) ($ticket['status'] ?? 'open'),
            'category' => (string) ($ticket['category'] ?? 'other'),
            'created_at' => (string) ($ticket['created_at'] ?? ''),
            'user_name' => trim((string) (($ticket['first_name'] ?? '') . ' ' . ($ticket['last_name'] ?? ''))) ?: 'Anonymous',
            'user_email' => (string) ($ticket['email'] ?? ''),
        ],
        'messages' => array_map(static function (array $message): array {
            return [
                'message_id' => (string) ($message['message_id'] ?? ''),
                'message' => (string) ($message['message'] ?? ''),
                'is_admin' => (int) ($message['is_admin'] ?? 0),
                'created_at' => (string) ($message['created_at'] ?? ''),
                'author' => trim((string) ($message['author_name'] ?? '')) ?: ((int) ($message['is_admin'] ?? 0) === 1 ? 'Admin' : 'User'),
                'type' => (string) ($message['type'] ?? 'message'),
            ];
        }, $messages),
    ];
}

try {
    if ($requestMethod === 'GET' && $action === 'get_ticket') {
        $ticketId = (int) ($_GET['id'] ?? 0);
        if ($ticketId <= 0) {
            throw new RuntimeException('Invalid ticket.');
        }

        supportTicketJsonResponse(200, [
            'success' => true,
        ] + supportTicketPayload($db, $ticketModel, $ticketId));
    }

    if ($requestMethod === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Invalid CSRF token.');
        }

        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        if ($ticketId <= 0) {
            throw new RuntimeException('Invalid ticket.');
        }

        if ($action === 'send_message') {
            $message = trim((string) ($_POST['message'] ?? ''));
            if ($message === '') {
                throw new RuntimeException('Message cannot be empty.');
            }

            $ticketModel->addAdminMessage($ticketId, (int) $admin['admin_id'], $message);

            supportTicketJsonResponse(200, [
                'success' => true,
            ] + supportTicketPayload($db, $ticketModel, $ticketId));
        }

        if ($action === 'update_status') {
            $status = trim((string) ($_POST['status'] ?? ''));
            $allowedStatuses = ['open', 'in_progress', 'resolved', 'closed'];
            if (!in_array($status, $allowedStatuses, true)) {
                throw new RuntimeException('Invalid status.');
            }

            if (!$ticketModel->updateStatus($ticketId, $status)) {
                throw new RuntimeException('Unable to update ticket status.');
            }

            supportTicketJsonResponse(200, [
                'success' => true,
            ] + supportTicketPayload($db, $ticketModel, $ticketId));
        }
    }

    supportTicketJsonResponse(400, [
        'success' => false,
        'error' => 'Unsupported request.',
    ]);
} catch (Throwable $error) {
    supportTicketJsonResponse(400, [
        'success' => false,
        'error' => $error->getMessage(),
    ]);
}
