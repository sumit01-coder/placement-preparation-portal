<?php
/**
 * Leaderboard Management Class
 * Handles global rankings and problem-specific leaderboards
 */

class Leaderboard {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get global leaderboard
     */
    public function getGlobalLeaderboard($limit = 100) {
        $query = "SELECT 
                    u.user_id,
                    up.full_name as fullname,
                    up.full_name as username, -- Fallback for UI that expects username
                    COUNT(DISTINCT CASE WHEN s.status = 'accepted' THEN s.problem_id END) as problems_solved,
                    COUNT(s.submission_id) as total_submissions,
                    SUM(CASE WHEN s.status = 'accepted' AND p.difficulty = 'Easy' THEN 1 ELSE 0 END) as easy_solved,
                    SUM(CASE WHEN s.status = 'accepted' AND p.difficulty = 'Medium' THEN 1 ELSE 0 END) as medium_solved,
                    SUM(CASE WHEN s.status = 'accepted' AND p.difficulty = 'Hard' THEN 1 ELSE 0 END) as hard_solved,
                    ROUND(COUNT(DISTINCT CASE WHEN s.status = 'accepted' THEN s.problem_id END) * 100.0 / NULLIF(COUNT(s.submission_id), 0), 2) as acceptance_rate
                  FROM users u
                  JOIN user_profiles up ON u.user_id = up.user_id
                  LEFT JOIN submissions s ON u.user_id = s.user_id
                  LEFT JOIN coding_problems p ON s.problem_id = p.problem_id
                  WHERE u.role_id = 1
                  GROUP BY u.user_id, up.full_name
                  HAVING problems_solved > 0
                  ORDER BY problems_solved DESC, acceptance_rate DESC
                  LIMIT :limit";
        
        return $this->db->fetchAll($query, ['limit' => $limit]);
    }
    
    /**
     * Get problem-specific leaderboard (fastest solutions)
     */
    public function getProblemLeaderboard($problemId, $limit = 50) {
        $query = "SELECT 
                    up.full_name as username,
                    up.full_name as fullname,
                    s.runtime,
                    s.memory_used,
                    s.language_name,
                    s.submitted_at
                  FROM (
                      SELECT 
                          s1.user_id,
                          s1.runtime,
                          s1.memory_used,
                          s1.language_id,
                          s1.submitted_at,
                          l.language_name,
                          ROW_NUMBER() OVER (PARTITION BY s1.user_id ORDER BY s1.runtime ASC) as rn
                      FROM submissions s1
                      JOIN languages l ON s1.language_id = l.language_id
                      WHERE s1.problem_id = :problem_id
                      AND s1.status = 'accepted'
                  ) s
                  JOIN users u ON s.user_id = u.user_id
                  JOIN user_profiles up ON u.user_id = up.user_id
                  WHERE s.rn = 1
                  ORDER BY s.runtime ASC
                  LIMIT :limit";
        
        return $this->db->fetchAll($query, [
            'problem_id' => $problemId,
            'limit' => $limit
        ]);
    }
    
    /**
     * Get user's rank
     */
    public function getUserRank($userId) {
        $query = "SELECT rank_position FROM (
                    SELECT 
                        user_id,
                        ROW_NUMBER() OVER (ORDER BY problems_solved DESC, acceptance_rate DESC) as rank_position
                    FROM (
                        SELECT 
                            u.user_id,
                            COUNT(DISTINCT CASE WHEN s.status = 'accepted' THEN s.problem_id END) as problems_solved,
                            ROUND(COUNT(DISTINCT CASE WHEN s.status = 'accepted' THEN s.problem_id END) * 100.0 / NULLIF(COUNT(s.submission_id), 0), 2) as acceptance_rate
                        FROM users u
                        LEFT JOIN submissions s ON u.user_id = s.user_id
                        WHERE u.role_id = 1
                        GROUP BY u.user_id
                    ) stats
                  ) ranked
                  WHERE user_id = :user_id";
        
        $result = $this->db->fetchOne($query, ['user_id' => $userId]);
        return $result['rank_position'] ?? null;
    }
}
