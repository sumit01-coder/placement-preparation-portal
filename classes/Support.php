<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

class Support {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create a new support ticket
     */
    public function createTicket($userId, $subject, $description, $category = 'general') {
        try {
            $query = "INSERT INTO support_tickets (user_id, subject, description, category, status, created_at)
                      VALUES (:user_id, :subject, :description, :category, 'open', NOW())";
            
            $this->db->query($query, [
                'user_id' => $userId,
                'subject' => $subject,
                'description' => $description,
                'category' => $category
            ]);
            
            return ['success' => true, 'message' => 'Ticket created successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to create ticket'];
        }
    }
    
    /**
     * Get user's tickets
     */
    public function getUserTickets($userId) {
        $query = "SELECT * FROM support_tickets 
                  WHERE user_id = :user_id 
                  ORDER BY created_at DESC";
        
        return $this->db->fetchAll($query, ['user_id' => $userId]);
    }
    
    /**
     * Get all tickets (admin)
     */
    public function getAllTickets($limit = 50, $offset = 0, $status = 'all') {
        $whereClause = '';
        $params = [];
        
        if ($status !== 'all') {
            $whereClause = "WHERE st.status = :status";
            $params['status'] = $status;
        }
        
        $query = "SELECT st.*, up.full_name, u.email
                  FROM support_tickets st
                  JOIN users u ON st.user_id = u.user_id
                  LEFT JOIN user_profiles up ON st.user_id = up.user_id
                  $whereClause
                  ORDER BY st.created_at DESC
                  LIMIT $limit OFFSET $offset";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * Get ticket count
     */
    public function getTicketCount($status = 'all') {
        $whereClause = $status !== 'all' ? "WHERE status = :status" : '';
        $params = $status !== 'all' ? ['status' => $status] : [];
        
        $query = "SELECT COUNT(*) as count FROM support_tickets $whereClause";
        return $this->db->fetchOne($query, $params)['count'];
    }
    
    /**
     * Update ticket status
     */
    public function updateTicketStatus($ticketId, $status, $adminResponse = null) {
        try {
            $query = "UPDATE support_tickets 
                      SET status = :status, 
                          admin_response = :admin_response,
                          updated_at = NOW()
                      WHERE ticket_id = :ticket_id";
            
            $this->db->query($query, [
                'ticket_id' => $ticketId,
                'status' => $status,
                'admin_response' => $adminResponse
            ]);
            
            return ['success' => true, 'message' => 'Ticket updated successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to update ticket'];
        }
    }
    
    /**
     * Get ticket by ID
     */
    public function getTicket($ticketId) {
        $query = "SELECT st.*, up.full_name, u.email
                  FROM support_tickets st
                  JOIN users u ON st.user_id = u.user_id
                  LEFT JOIN user_profiles up ON st.user_id = up.user_id
                  WHERE st.ticket_id = :ticket_id";
        
        return $this->db->fetchOne($query, ['ticket_id' => $ticketId]);
    }
    
    /**
     * Delete ticket
     */
    public function deleteTicket($ticketId) {
        try {
            $this->db->delete('support_tickets', 'ticket_id = :ticket_id', ['ticket_id' => $ticketId]);
            return ['success' => true, 'message' => 'Ticket deleted successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to delete ticket'];
        }
    }
}
