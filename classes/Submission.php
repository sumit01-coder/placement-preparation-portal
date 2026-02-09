<?php
/**
 * Submission Management Class
 * Handles code submission tracking, history, and analytics
 */

class Submission {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Record a new code submission
     */
    public function recordSubmission($userId, $problemId, $languageId, $code, $status, $result) {
        try {
            $query = "INSERT INTO submissions 
                      (user_id, problem_id, language_id, source_code, status, 
                       runtime, memory_used, passed_tests, total_tests, 
                       time_complexity, space_complexity, submitted_at)
                      VALUES 
                      (:user_id, :problem_id, :language_id, :source_code, :status,
                       :runtime, :memory_used, :passed_tests, :total_tests,
                       :time_complexity, :space_complexity, NOW())";
            
            $params = [
                'user_id' => $userId,
                'problem_id' => $problemId,
                'language_id' => $languageId,
                'source_code' => $code,
                'status' => $status,
                'runtime' => $result['time'] ?? null,
                'memory_used' => $result['memory'] ?? null,
                'passed_tests' => $result['passed'] ?? 0,
                'total_tests' => $result['total'] ?? 0,
                'time_complexity' => $result['time_complexity'] ?? null,
                'space_complexity' => $result['space_complexity'] ?? null
            ];
            
            $submissionId = $this->db->insert($query, $params);
            
            // Update user stats if accepted
            if ($status === 'accepted') {
                $this->updateUserStats($userId, $problemId);
            }
            
            return $submissionId;
            
        } catch (Exception $e) {
            error_log("Submission recording failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get submission history for a user
     */
    public function getUserSubmissions($userId, $limit = 20, $offset = 0) {
        $query = "SELECT s.*, p.title, p.difficulty, 
                         l.language_name
                  FROM submissions s
                  JOIN coding_problems p ON s.problem_id = p.problem_id
                  JOIN languages l ON s.language_id = l.language_id
                  WHERE s.user_id = :user_id
                  ORDER BY s.submitted_at DESC
                  LIMIT :limit OFFSET :offset";
        
        return $this->db->fetchAll($query, [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * Get submission history for a specific problem
     */
    public function getProblemSubmissions($userId, $problemId) {
        $query = "SELECT s.*, l.language_name
                  FROM submissions s
                  JOIN languages l ON s.language_id = l.language_id
                  WHERE s.user_id = :user_id 
                  AND s.problem_id = :problem_id
                  ORDER BY s.submitted_at DESC";
        
        return $this->db->fetchAll($query, [
            'user_id' => $userId,
            'problem_id' => $problemId
        ]);
    }
    
    /**
     * Get submission by ID
     */
    public function getSubmissionById($submissionId, $userId = null) {
        $query = "SELECT s.*, p.title, p.difficulty,
                         l.language_name, up.full_name as username
                  FROM submissions s
                  JOIN coding_problems p ON s.problem_id = p.problem_id
                  JOIN languages l ON s.language_id = l.language_id
                  JOIN users u ON s.user_id = u.user_id
                  JOIN user_profiles up ON u.user_id = up.user_id
                  WHERE s.submission_id = :submission_id";
        
        $params = ['submission_id' => $submissionId];
        
        if ($userId !== null) {
            $query .= " AND s.user_id = :user_id";
            $params['user_id'] = $userId;
        }
        
        return $this->db->fetchOne($query, $params);
    }
    
    /**
     * Get accepted submissions for a problem (for leaderboard)
     */
    public function getAcceptedSubmissions($problemId, $limit = 100) {
        $query = "SELECT s.*, up.full_name as username, up.full_name as fullname
                  FROM submissions s
                  JOIN users u ON s.user_id = u.user_id
                  JOIN user_profiles up ON u.user_id = up.user_id
                  WHERE s.problem_id = :problem_id
                  AND s.status = 'accepted'
                  ORDER BY s.runtime ASC, s.submitted_at ASC
                  LIMIT :limit";
        
        return $this->db->fetchAll($query, [
            'problem_id' => $problemId,
            'limit' => $limit
        ]);
    }
    
    /**
     * Get user's best submission for a problem
     */
    public function getBestSubmission($userId, $problemId) {
        $query = "SELECT *
                  FROM submissions
                  WHERE user_id = :user_id
                  AND problem_id = :problem_id
                  AND status = 'accepted'
                  ORDER BY runtime ASC
                  LIMIT 1";
        
        return $this->db->fetchOne($query, [
            'user_id' => $userId,
            'problem_id' => $problemId
        ]);
    }
    
    /**
     * Get submission statistics for analytics
     */
    public function getSubmissionStats($userId) {
        $query = "SELECT 
                    COUNT(*) as total_submissions,
                    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                    SUM(CASE WHEN status = 'wrong_answer' THEN 1 ELSE 0 END) as wrong_answer,
                    SUM(CASE WHEN status = 'time_limit_exceeded' THEN 1 ELSE 0 END) as tle,
                    SUM(CASE WHEN status = 'runtime_error' THEN 1 ELSE 0 END) as runtime_error,
                    SUM(CASE WHEN status = 'compilation_error' THEN 1 ELSE 0 END) as compile_error,
                    AVG(runtime) as avg_runtime,
                    AVG(memory_used) as avg_memory
                  FROM submissions
                  WHERE user_id = :user_id";
        
        return $this->db->fetchOne($query, ['user_id' => $userId]);
    }
    
    /**
     * Get recent submissions for dashboard
     */
    public function getRecentSubmissions($userId, $limit = 10) {
        $query = "SELECT s.submission_id, s.problem_id, s.status, 
                         s.runtime, s.submitted_at,
                         p.title, p.difficulty
                  FROM submissions s
                  JOIN coding_problems p ON s.problem_id = p.problem_id
                  WHERE s.user_id = :user_id
                  ORDER BY s.submitted_at DESC
                  LIMIT :limit";
        
        return $this->db->fetchAll($query, [
            'user_id' => $userId,
            'limit' => $limit
        ]);
    }
    
    /**
     * Update user statistics after successful submission
     */
    private function updateUserStats($userId, $problemId) {
        // Check if this is a new problem solved
        $query = "SELECT COUNT(*) as count
                  FROM submissions
                  WHERE user_id = :user_id
                  AND problem_id = :problem_id
                  AND status = 'accepted'";
        
        $result = $this->db->fetchOne($query, [
            'user_id' => $userId,
            'problem_id' => $problemId
        ]);
        
        // If first AC for this problem, update user's solved count
        if ($result['count'] == 1) {
            $updateQuery = "UPDATE users 
                           SET problems_solved = problems_solved + 1
                           WHERE user_id = :user_id";
            
            $this->db->query($updateQuery, ['user_id' => $userId]);
        }
    }
    
    /**
     * Get daily submission activity for heatmap (last 365 days)
     */
    public function getDailyActivity($userId) {
        $query = "SELECT DATE(submitted_at) as date, COUNT(*) as count 
                  FROM submissions 
                  WHERE user_id = :user_id 
                  AND submitted_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
                  GROUP BY DATE(submitted_at)";
        
        return $this->db->fetchAll($query, ['user_id' => $userId]);
    }

    /**
     * Check if user has solved a problem
     */
    public function hasSolved($userId, $problemId) {
        $query = "SELECT COUNT(*) as count
                  FROM submissions
                  WHERE user_id = :user_id
                  AND problem_id = :problem_id
                  AND status = 'accepted'";
        
        $result = $this->db->fetchOne($query, [
            'user_id' => $userId,
            'problem_id' => $problemId
        ]);
        
        return $result['count'] > 0;
    }
}
