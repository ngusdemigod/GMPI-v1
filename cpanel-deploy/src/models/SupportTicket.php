<?php
/**
 * Support Ticket Model
 * Bright Light Ministry Int'l Partners Portal
 * 
 * This model uses the Security class for input sanitization
 * to prevent injection attacks and ensure data integrity.
 */

// Load Security class if not already loaded
if (!class_exists('Security')) {
    require_once __DIR__ . '/../config/Security.php';
}

class SupportTicket {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create a new support ticket
     * 
     * Security Measures:
     * - All inputs are sanitized using the Security class
     * - Uses PDO prepared statements for SQL injection prevention
     * - Category is validated against whitelist
     * 
     * @param array $data Ticket data
     * @return int|false Ticket ID on success, false on failure
     */
    public function create($data) {
        // Sanitize all inputs before database insertion
        $userId = Security::sanitizeInteger($data['user_id'] ?? null);
        $subject = Security::sanitizeSubject($data['subject'] ?? '');
        $message = Security::sanitizeMessage($data['message'] ?? '');
        $category = Security::sanitizeCategory($data['category'] ?? '');
        $status = Security::sanitizeString($data['status'] ?? 'open', 20);
        
        // Validate required fields
        if (empty($subject) || empty($message) || $category === false) {
            return false;
        }
        
        // Validate status is one of the allowed values
        $allowedStatuses = ['open', 'in_progress', 'resolved', 'closed'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'open';
        }
        
        // Use PDO prepared statement for SQL injection prevention
        $sql = "INSERT INTO support_tickets 
                (user_id, subject, message, category, status, created_at) 
                VALUES (:user_id, :subject, :message, :category, :status, NOW())";
        
        try {
            $stmt = $this->db->getConnection()->prepare($sql);
            
            // Use proper PDO parameter types
            $stmt->bindValue(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':subject', $subject, PDO::PARAM_STR);
            $stmt->bindValue(':message', $message, PDO::PARAM_STR);
            $stmt->bindValue(':category', $category, PDO::PARAM_STR);
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
            
            $stmt->execute();
            
            return $this->db->getConnection()->lastInsertId();
            
        } catch (PDOException $e) {
            // Log the error but don't expose details
            error_log('Support ticket creation error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Attach a file to a ticket
     */
    public function attachFile($ticketId, $fileName) {
        $sql = "INSERT INTO support_ticket_attachments 
                (ticket_id, file_name, uploaded_at) 
                VALUES (:ticket_id, :file_name, NOW())";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([
            ':ticket_id' => $ticketId,
            ':file_name' => $fileName
        ]);
        
        return $this->db->getConnection()->lastInsertId();
    }
    
    /**
     * Get all tickets for admin
     */
    public function getAllTickets($limit = 50, $offset = 0) {
        $sql = "SELECT st.*, u.first_name, u.last_name, u.email 
                FROM support_tickets st
                LEFT JOIN users u ON st.user_id = u.user_id
                ORDER BY st.created_at DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get ticket by ID
     */
    public function getById($ticketId) {
        $sql = "SELECT st.*, u.first_name, u.last_name, u.email 
                FROM support_tickets st
                LEFT JOIN users u ON st.user_id = u.user_id
                WHERE st.ticket_id = :ticket_id";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get tickets by user
     */
    public function getByUser($userId) {
        $sql = "SELECT * FROM support_tickets 
                WHERE user_id = :user_id 
                ORDER BY created_at DESC";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get active admin email recipients for support notifications.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAdminNotificationRecipients() {
        $sql = "SELECT DISTINCT au.admin_id, u.user_id, u.email, u.first_name, u.last_name
                FROM admin_users au
                INNER JOIN users u ON au.user_id = u.user_id
                WHERE u.is_active = TRUE
                  AND u.email IS NOT NULL
                  AND u.email != ''
                ORDER BY u.first_name, u.last_name";

        $stmt = $this->db->getConnection()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update ticket status
     */
    public function updateStatus($ticketId, $status) {
        $sql = "UPDATE support_tickets 
                SET status = :status, updated_at = NOW() 
                WHERE ticket_id = :ticket_id";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([
            ':ticket_id' => $ticketId,
            ':status' => $status
        ]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Add admin response to ticket
     */
    public function addResponse($ticketId, $response, $adminId) {
        $sql = "INSERT INTO support_ticket_responses 
                (ticket_id, admin_id, response, created_at) 
                VALUES (:ticket_id, :admin_id, :response, NOW())";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([
            ':ticket_id' => $ticketId,
            ':admin_id' => $adminId,
            ':response' => $response
        ]);
        
        return $this->db->getConnection()->lastInsertId();
    }
    
    /**
     * Get ticket attachments
     */
    public function getAttachments($ticketId) {
        $sql = "SELECT * FROM support_ticket_attachments 
                WHERE ticket_id = :ticket_id";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get ticket responses
     */
    public function getResponses($ticketId) {
        $sql = "SELECT str.*, a.first_name, a.last_name 
                FROM support_ticket_responses str
                LEFT JOIN admin_users a ON str.admin_id = a.admin_id
                WHERE str.ticket_id = :ticket_id
                ORDER BY str.created_at ASC";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get ticket count by status
     */
    public function getCountByStatus() {
        $sql = "SELECT status, COUNT(*) as count 
                FROM support_tickets 
                GROUP BY status";
        
        $stmt = $this->db->getConnection()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add user message to ticket (chat)
     */
    public function sendMessage($ticketId, $userId, $message) {
        $sql = "INSERT INTO support_ticket_messages 
                (ticket_id, user_id, message, is_admin, created_at) 
                VALUES (:ticket_id, :user_id, :message, 0, NOW())";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([
            ':ticket_id' => $ticketId,
            ':user_id' => $userId,
            ':message' => $message
        ]);
        
        return $this->db->getConnection()->lastInsertId();
    }

    /**
     * Get ticket messages (chat)
     */
    public function getMessages($ticketId) {
        $sql = "SELECT stm.*, u.first_name, u.last_name, u.email
                FROM support_ticket_messages stm
                LEFT JOIN users u ON stm.user_id = u.user_id
                WHERE stm.ticket_id = :ticket_id
                ORDER BY stm.created_at ASC";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get tickets for a user with status info
     */
    public function getUserTickets($userId, $limit = 50, $offset = 0) {
        $sql = "SELECT st.*, 
                (SELECT COUNT(*) FROM support_ticket_messages stm WHERE stm.ticket_id = st.ticket_id) as message_count
                FROM support_tickets st
                WHERE st.user_id = :user_id 
                ORDER BY st.updated_at DESC, st.created_at DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add admin message to ticket (chat)
     */
    public function addAdminMessage($ticketId, $adminId, $message) {
        $sql = "INSERT INTO support_ticket_messages 
                (ticket_id, user_id, message, is_admin, created_at) 
                VALUES (:ticket_id, :user_id, :message, 1, NOW())";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([
            ':ticket_id' => $ticketId,
            ':user_id' => $adminId,
            ':message' => $message
        ]);
        
        return $this->db->getConnection()->lastInsertId();
    }

    /**
     * Get all ticket messages including admin responses (unified chat)
     */
    public function getUnifiedMessages($ticketId) {
        // Get messages from support_ticket_messages
        $sql = "SELECT stm.*, u.first_name, u.last_name, u.email, 'message' as type
                FROM support_ticket_messages stm
                LEFT JOIN users u ON stm.user_id = u.user_id
                WHERE stm.ticket_id = :ticket_id
                UNION ALL
                SELECT str.response_id as message_id, str.admin_id as user_id, str.response as message, 
                       1 as is_admin, str.created_at, a.first_name, a.last_name, NULL as email, 'response' as type
                FROM support_ticket_responses str
                LEFT JOIN admin_users a ON str.admin_id = a.admin_id
                LEFT JOIN users u2 ON a.user_id = u2.user_id
                WHERE str.ticket_id = :ticket_id
                ORDER BY created_at ASC";
        
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
