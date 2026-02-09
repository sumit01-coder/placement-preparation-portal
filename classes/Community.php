<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

class Community {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Post a new question
     */
    public function postQuestion($userId, $title, $content, $tags = []) {
        try {
            $query = "INSERT INTO community_questions (user_id, title, content, tags, created_at)
                      VALUES (:user_id, :title, :content, :tags, NOW())";
            
            $this->db->query($query, [
                'user_id' => $userId,
                'title' => $title,
                'content' => $content,
                'tags' => json_encode($tags)
            ]);
            
            // Update user reputation
            $this->updateReputation($userId, 'question_asked');
            
            return ['success' => true, 'message' => 'Question posted successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to post question'];
        }
    }
    
    /**
     * Get questions with filters
     */
    public function getQuestions($filter = 'recent', $tag = null, $search = null, $limit = 20, $offset = 0) {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if ($tag) {
            $whereClause .= " AND JSON_CONTAINS(q.tags, :tag)";
            $params['tag'] = json_encode($tag);
        }
        
        if ($search) {
            $whereClause .= " AND (q.title LIKE :search OR q.content LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }
        
        $orderBy = match($filter) {
            'popular' => 'q.votes DESC, q.views DESC',
            'unanswered' => 'q.answer_count ASC, q.created_at DESC',
            'solved' => 'q.is_solved DESC, q.created_at DESC',
            default => 'q.created_at DESC'
        };
        
        $query = "SELECT q.*, up.full_name, u.email,
                         (SELECT COUNT(*) FROM community_answers WHERE question_id = q.question_id) as answer_count
                  FROM community_questions q
                  JOIN users u ON q.user_id = u.user_id
                  LEFT JOIN user_profiles up ON q.user_id = up.user_id
                  $whereClause
                  ORDER BY $orderBy
                  LIMIT $limit OFFSET $offset";
        
        $questions = $this->db->fetchAll($query, $params);
        
        // Decode tags
        foreach ($questions as &$q) {
            $q['tags'] = json_decode($q['tags'], true) ?? [];
        }
        
        return $questions;
    }
    
    /**
     * Get question by ID
     */
    public function getQuestion($questionId) {
        $question = $this->db->fetchOne(
            "SELECT q.*, up.full_name, u.email
             FROM community_questions q
             JOIN users u ON q.user_id = u.user_id
             LEFT JOIN user_profiles up ON q.user_id = up.user_id
             WHERE q.question_id = :qid",
            ['qid' => $questionId]
        );
        
        if ($question) {
            $question['tags'] = json_decode($question['tags'], true) ?? [];
            
            // Increment views
            $this->db->query(
                "UPDATE community_questions SET views = views + 1 WHERE question_id = :qid",
                ['qid' => $questionId]
            );
        }
        
        return $question;
    }
    
    /**
     * Post an answer
     */
    public function postAnswer($questionId, $userId, $content) {
        try {
            $query = "INSERT INTO community_answers (question_id, user_id, content, created_at)
                      VALUES (:qid, :uid, :content, NOW())";
            
            $this->db->query($query, [
                'qid' => $questionId,
                'uid' => $userId,
                'content' => $content
            ]);
            
            // Update question answer count
            $this->db->query(
                "UPDATE community_questions SET answer_count = answer_count + 1 WHERE question_id = :qid",
                ['qid' => $questionId]
            );
            
            // Update user reputation
            $this->updateReputation($userId, 'answer_given');
            
            return ['success' => true, 'message' => 'Answer posted successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to post answer'];
        }
    }
    
    /**
     * Get answers for a question
     */
    public function getAnswers($questionId) {
        $query = "SELECT a.*, up.full_name, u.email,
                         (SELECT COUNT(*) FROM community_votes WHERE target_type = 'answer' AND target_id = a.answer_id) as vote_count
                  FROM community_answers a
                  JOIN users u ON a.user_id = u.user_id
                  LEFT JOIN user_profiles up ON a.user_id = up.user_id
                  WHERE a.question_id = :qid
                  ORDER BY a.is_accepted DESC, a.votes DESC, a.created_at ASC";
        
        return $this->db->fetchAll($query, ['qid' => $questionId]);
    }
    
    /**
     * Vote on question or answer
     */
    public function vote($userId, $targetType, $targetId, $voteValue) {
        try {
            // Check if user already voted
            $existing = $this->db->fetchOne(
                "SELECT vote_value FROM community_votes 
                 WHERE user_id = :uid AND target_type = :type AND target_id = :tid",
                ['uid' => $userId, 'type' => $targetType, 'tid' => $targetId]
            );
            
            if ($existing) {
                // Update existing vote
                $this->db->query(
                    "UPDATE community_votes SET vote_value = :value 
                     WHERE user_id = :uid AND target_type = :type AND target_id = :tid",
                    ['value' => $voteValue, 'uid' => $userId, 'type' => $targetType, 'tid' => $targetId]
                );
                
                $diff = $voteValue - $existing['vote_value'];
            } else {
                // Insert new vote
                $this->db->query(
                    "INSERT INTO community_votes (user_id, target_type, target_id, vote_value)
                     VALUES (:uid, :type, :tid, :value)",
                    ['uid' => $userId, 'type' => $targetType, 'tid' => $targetId, 'value' => $voteValue]
                );
                
                $diff = $voteValue;
            }
            
            // Update vote count
            $table = $targetType === 'question' ? 'community_questions' : 'community_answers';
            $idField = $targetType === 'question' ? 'question_id' : 'answer_id';
            
            $this->db->query(
                "UPDATE $table SET votes = votes + :diff WHERE $idField = :tid",
                ['diff' => $diff, 'tid' => $targetId]
            );
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to vote'];
        }
    }
    
    /**
     * Post a comment
     */
    public function postComment($parentType, $parentId, $userId, $content) {
        try {
            $this->db->query(
                "INSERT INTO community_comments (parent_type, parent_id, user_id, content)
                 VALUES (:type, :pid, :uid, :content)",
                ['type' => $parentType, 'pid' => $parentId, 'uid' => $userId, 'content' => $content]
            );
            
            return ['success' => true, 'message' => 'Comment posted'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to post comment'];
        }
    }
    
    /**
     * Get comments
     */
    public function getComments($parentType, $parentId) {
        return $this->db->fetchAll(
            "SELECT c.*, up.full_name
             FROM community_comments c
             JOIN user_profiles up ON c.user_id = up.user_id
             WHERE c.parent_type = :type AND c.parent_id = :pid
             ORDER BY c.created_at ASC",
            ['type' => $parentType, 'pid' => $parentId]
        );
    }
    
    /**
     * Accept an answer
     */
    public function acceptAnswer($answerId, $questionId, $userId) {
        // Verify question ownership
        $question = $this->db->fetchOne(
            "SELECT user_id FROM community_questions WHERE question_id = :qid",
            ['qid' => $questionId]
        );
        
        if ($question['user_id'] != $userId) {
            return ['success' => false, 'message' => 'Only question author can accept answers'];
        }
        
        // Unaccept other answers
        $this->db->query(
            "UPDATE community_answers SET is_accepted = FALSE WHERE question_id = :qid",
            ['qid' => $questionId]
        );
        
        // Accept this answer
        $this->db->query(
            "UPDATE community_answers SET is_accepted = TRUE WHERE answer_id = :aid",
            ['aid' => $answerId]
        );
        
        // Mark question as solved
        $this->db->query(
            "UPDATE community_questions SET is_solved = TRUE WHERE question_id = :qid",
            ['qid' => $questionId]
        );
        
        return ['success' => true, 'message' => 'Answer accepted'];
    }
    
    /**
     * Get community leaderboard
     */
    public function getLeaderboard($limit = 20) {
        return $this->db->fetchAll(
            "SELECT ur.*, up.full_name, u.email
             FROM user_reputation ur
             JOIN users u ON ur.user_id = u.user_id
             LEFT JOIN user_profiles up ON ur.user_id = up.user_id
             ORDER BY ur.points DESC
             LIMIT :limit",
            ['limit' => $limit]
        );
    }
    
    /**
     * Update user reputation
     */
    private function updateReputation($userId, $action) {
        $points = match($action) {
            'question_asked' => 5,
            'answer_given' => 10,
            'answer_accepted' => 15,
            'upvote_received' => 10,
            default => 0
        };
        
        // Ensure reputation record exists
        $this->db->query(
            "INSERT INTO user_reputation (user_id, points) 
             VALUES (:uid, 0) 
             ON DUPLICATE KEY UPDATE user_id = user_id",
            ['uid' => $userId]
        );
        
        // Update based on action
        $field = match($action) {
            'question_asked' => 'questions_asked',
            'answer_given' => 'answers_given',
            'answer_accepted' => 'solutions_accepted',
            default => null
        };
        
        if ($field) {
            $this->db->query(
                "UPDATE user_reputation SET points = points + :points, $field = $field + 1 WHERE user_id = :uid",
                ['points' => $points, 'uid' => $userId]
            );
        }
    }
    
    /**
     * Get popular tags
     */
    public function getPopularTags($limit = 20) {
        // This is a simplified version - tags are stored in JSON
        // In production, you'd want a proper tags table
        return [
            ['tag' => 'javascript', 'count' => 150],
            ['tag' => 'python', 'count' => 120],
            ['tag' => 'java', 'count' => 100],
            ['tag' => 'algorithms', 'count' => 90],
            ['tag' => 'data-structures', 'count' => 85]
        ];
    }
}
