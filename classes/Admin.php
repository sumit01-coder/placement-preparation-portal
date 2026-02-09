<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

class Admin {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get dashboard overview statistics
     */
    public function getDashboardStats() {
        $stats = [];
        
        // Total users
        $stats['total_users'] = $this->db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role_id = 1")['count'];
        
        // Total problems
        $stats['total_problems'] = $this->db->fetchOne("SELECT COUNT(*) as count FROM coding_problems")['count'];
        
        // Total submissions (last 30 days)
        $stats['recent_submissions'] = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM submissions WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        )['count'];
        
        // Total aptitude tests
        $stats['total_tests'] = $this->db->fetchOne("SELECT COUNT(*) as count FROM aptitude_tests")['count'];
        
        // Active users (logged in last 7 days)
        $stats['active_users'] = $this->db->fetchOne(
            "SELECT COUNT(DISTINCT user_id) as count FROM user_activity 
             WHERE activity_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )['count'] ?? 0;
        
        // Today's submissions
        $stats['today_submissions'] = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM submissions WHERE DATE(submitted_at) = CURDATE()"
        )['count'];
        
        return $stats;
    }
    
    /**
     * Get all users with pagination
     */
    public function getUsers($limit = 50, $offset = 0, $search = '') {
        $whereClause = '';
        $params = [];
        
        if (!empty($search)) {
            $whereClause = "WHERE u.email LIKE :search OR up.full_name LIKE :search";
            $params['search'] = '%' . $search . '%';
        }
        
        // Ensure limit and offset are integers
        $limit = (int)$limit;
        $offset = (int)$offset;

        $query = "SELECT u.user_id, u.email, u.role_id, u.created_at, u.last_login,
                         up.full_name, up.college, up.branch
                  FROM users u
                  LEFT JOIN user_profiles up ON u.user_id = up.user_id
                  $whereClause
                  ORDER BY u.created_at DESC
                  LIMIT $limit OFFSET $offset";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * Get user count
     */
    public function getUserCount($search = '') {
        $whereClause = '';
        $params = [];
        
        if (!empty($search)) {
            $whereClause = "WHERE u.email LIKE :search OR up.full_name LIKE :search";
            $params['search'] = '%' . $search . '%';
        }
        
        $query = "SELECT COUNT(*) as count FROM users u
                  LEFT JOIN user_profiles up ON u.user_id = up.user_id
                  $whereClause";
        
        return $this->db->fetchOne($query, $params)['count'];
    }
    
    /**
     * Delete user
     */
    public function deleteUser($userId) {
        try {
            $this->db->delete('users', 'user_id = :user_id', ['user_id' => $userId]);
            return ['success' => true, 'message' => 'User deleted successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to delete user'];
        }
    }
    
    /**
     * Get all coding problems
     */
    public function getProblems($limit = 50, $offset = 0) {
        $query = "SELECT p.*,
                         COUNT(DISTINCT s.user_id) as solvers_count,
                         COUNT(s.submission_id) as total_submissions
                  FROM coding_problems p
                  LEFT JOIN submissions s ON p.problem_id = s.problem_id AND s.status = 'accepted'
                  GROUP BY p.problem_id
                  ORDER BY p.problem_id DESC
                  LIMIT $limit OFFSET $offset";
        
        return $this->db->fetchAll($query);
    }
    
    /**
     * Delete problem
     */
    public function deleteProblem($problemId) {
        try {
            $this->db->delete('coding_problems', 'problem_id = :problem_id', ['problem_id' => $problemId]);
            return ['success' => true, 'message' => 'Problem deleted successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to delete problem'];
        }
    }
    
    /**
     * Get recent activity logs
     */
    public function getRecentActivity($limit = 20) {
        $query = "SELECT ua.*, up.full_name
                  FROM user_activity ua
                  LEFT JOIN user_profiles up ON ua.user_id = up.user_id
                  ORDER BY ua.activity_time DESC
                  LIMIT :limit";
        
        return $this->db->fetchAll($query, ['limit' => $limit]);
    }
    
    /**
     * Get submission statistics for analytics
     */
    public function getSubmissionAnalytics($days = 30) {
        $query = "SELECT DATE(submitted_at) as date,
                         COUNT(*) as total,
                         SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted
                  FROM submissions
                  WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                  GROUP BY DATE(submitted_at)
                  ORDER BY date ASC";
        
        return $this->db->fetchAll($query, ['days' => $days]);
    }
    
    /**
     * Get user growth statistics
     */
    public function getUserGrowth($days = 30) {
        $query = "SELECT DATE(created_at) as date, COUNT(*) as new_users
                  FROM users
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                  GROUP BY DATE(created_at)
                  ORDER BY date ASC";
        
        return $this->db->fetchAll($query, ['days' => $days]);
    }

    /**
     * Add new coding problem
     */
    public function addProblem($data) {
        try {
            // Generate slug if not provided
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['title'])));
            
            $this->db->insert('coding_problems', [
                'title' => $data['title'],
                'slug' => $slug,
                'difficulty' => $data['difficulty'],
                'description' => $data['description'],
                'input_format' => $data['input_format'],
                'output_format' => $data['output_format'],
                'constraints' => $data['constraints'],
                'sample_input' => $data['sample_input'],
                'sample_output' => $data['sample_output'],
                'tags' => json_encode(array_map('trim', explode(',', $data['tags']))),
                'time_limit' => $data['time_limit'] ?? 2,
                'memory_limit' => $data['memory_limit'] ?? 256
            ]);
            
            return ['success' => true, 'message' => 'Problem added successfully'];
        } catch (Exception $e) {
            error_log("Add Problem Error: " . $e->getMessage());
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return ['success' => false, 'message' => 'Problem with this title already exists'];
            }
            return ['success' => false, 'message' => 'Failed to add problem ' . $e->getMessage()];
        }
    }

    /**
     * Get all aptitude tests
     */
    public function getTests($limit = 50, $offset = 0) {
        $query = "SELECT t.*, 
                         (SELECT COUNT(*) FROM aptitude_questions WHERE test_id = t.test_id) as question_count
                  FROM aptitude_tests t
                  ORDER BY t.created_at DESC
                  LIMIT $limit OFFSET $offset";
        
        return $this->db->fetchAll($query);
    }

    /**
     * Add new aptitude test
     */
    public function addTest($data) {
        try {
            $this->db->insert('aptitude_tests', [
                'test_name' => $data['test_name'],
                'category' => $data['category'],
                'difficulty' => $data['difficulty'],
                'duration_minutes' => $data['duration_minutes'],
                'total_questions' => $data['total_questions'],
                'description' => $data['description'],
                'passing_score' => $data['passing_score'] ?? 0
            ]);
            
            return ['success' => true, 'message' => 'Test created successfully'];
        } catch (Exception $e) {
            error_log("Add Test Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create test'];
        }
    }
}
