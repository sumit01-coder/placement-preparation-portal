<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

// ============================================
// APTITUDE ENGINE CLASS
// ============================================

class Aptitude {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // Get all active tests
    public function getTests($categoryId = null, $difficulty = null, $limit = null, $offset = 0) {
        $sql = "SELECT t.*, c.category_name, c.icon,
                       (SELECT COUNT(*) FROM aptitude_questions WHERE test_id = t.test_id) as question_count
                FROM aptitude_tests t
                LEFT JOIN aptitude_categories c ON t.category_id = c.category_id
                WHERE t.is_active = 1";
        
        $params = [];
        
        if ($categoryId) {
            $sql .= " AND t.category_id = :category_id";
            $params['category_id'] = $categoryId;
        }
        
        if ($difficulty) {
            $sql .= " AND t.difficulty = :difficulty";
            $params['difficulty'] = $difficulty;
        }
        
        $sql .= " GROUP BY t.test_id ORDER BY t.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT :limit OFFSET :offset";
            $params['limit'] = (int)$limit;
            $params['offset'] = (int)$offset;
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    // Get test details with questions
    public function getTestDetails($testId) {
        $test = $this->db->fetchOne(
            "SELECT t.*, c.category_name
             FROM aptitude_tests t
             LEFT JOIN aptitude_categories c ON t.category_id = c.category_id
             WHERE t.test_id = :test_id",
            ['test_id' => $testId]
        );
        
        if ($test) {
            $test['questions'] = $this->db->fetchAll(
                "SELECT * FROM aptitude_questions 
                 WHERE test_id = :test_id 
                 ORDER BY question_id ASC",
                ['test_id' => $testId]
            );
        }
        
        return $test;
    }
    
    // Start test attempt
    public function startAttempt($userId, $testId) {
        try {
            // Check if test exists
            $test = $this->db->fetchOne(
                "SELECT test_id, total_marks FROM aptitude_tests WHERE test_id = :test_id",
                ['test_id' => $testId]
            );
            
            if (!$test) {
                return ['success' => false, 'message' => 'Test not found'];
            }
            
            // Create attempt
            $this->db->insert('aptitude_attempts', [
                'user_id' => $userId,
                'test_id' => $testId,
                'total_marks' => $test['total_marks'],
                'status' => 'in_progress'
            ]);
            
            $attemptId = $this->db->lastInsertId();
            
            return [
                'success' => true,
                'attempt_id' => $attemptId,
                'message' => 'Test started successfully'
            ];
            
        } catch (Exception $e) {
            error_log("Start Attempt Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to start test'];
        }
    }
    
    // Submit answer
    public function submitAnswer($attemptId, $questionId, $selectedAnswer) {
        try {
            // Get correct answer
            $question = $this->db->fetchOne(
                "SELECT correct_answer, marks FROM aptitude_questions WHERE question_id = :question_id",
                ['question_id' => $questionId]
            );
            
            $isCorrect = ($selectedAnswer === $question['correct_answer']);
            
            // Check if already answered
            $existing = $this->db->fetchOne(
                "SELECT answer_id FROM aptitude_answers WHERE attempt_id = :attempt_id AND question_id = :question_id",
                ['attempt_id' => $attemptId, 'question_id' => $questionId]
            );
            
            if ($existing) {
                // Update existing answer
                $this->db->update('aptitude_answers',
                    [
                        'selected_answer' => $selectedAnswer,
                        'is_correct' => $isCorrect
                    ],
                    'answer_id = :answer_id',
                    ['answer_id' => $existing['answer_id']]
                );
            } else {
                // Insert new answer
                $this->db->insert('aptitude_answers', [
                    'attempt_id' => $attemptId,
                    'question_id' => $questionId,
                    'selected_answer' => $selectedAnswer,
                    'is_correct' => $isCorrect
                ]);
            }
            
            return ['success' => true, 'is_correct' => $isCorrect];
            
        } catch (Exception $e) {
            error_log("Submit Answer Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to submit answer'];
        }
    }
    
    // Complete test attempt
    public function completeAttempt($attemptId) {
        try {
            // Get attempt details
            $attempt = $this->db->fetchOne(
                "SELECT user_id, test_id, start_time, total_marks FROM aptitude_attempts WHERE attempt_id = :attempt_id",
                ['attempt_id' => $attemptId]
            );
            
            // Calculate score
            $result = $this->db->fetchOne(
                "SELECT 
                    COUNT(*) as total_answered,
                    SUM(CASE WHEN aa.is_correct = 1 THEN q.marks ELSE 0 END) as score
                 FROM aptitude_answers aa
                 JOIN aptitude_questions q ON aa.question_id = q.question_id
                 WHERE aa.attempt_id = :attempt_id",
                ['attempt_id' => $attemptId]
            );
            
            $score = $result['score'] ?? 0;
            $percentage = ($attempt['total_marks'] > 0) ? ($score / $attempt['total_marks']) * 100 : 0;
            
            // Calculate duration
            $startTime = new DateTime($attempt['start_time']);
            $endTime = new DateTime();
            $duration = $endTime->getTimestamp() - $startTime->getTimestamp();
            
            // Update attempt
            $this->db->update('aptitude_attempts',
                [
                    'end_time' => date('Y-m-d H:i:s'),
                    'duration_seconds' => $duration,
                    'score' => $score,
                    'percentage' => round($percentage, 2),
                    'status' => 'completed'
                ],
                'attempt_id = :attempt_id',
                ['attempt_id' => $attemptId]
            );
            
            return [
                'success' => true,
                'score' => $score,
                'total_marks' => $attempt['total_marks'],
                'percentage' => round($percentage, 2),
                'duration' => $duration
            ];
            
        } catch (Exception $e) {
            error_log("Complete Attempt Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to complete test'];
        }
    }
    
    // Get attempt result with detailed analysis
    public function getAttemptResult($attemptId) {
        $result = $this->db->fetchOne(
            "SELECT aa.*, at.test_name, at.total_marks, c.category_name
             FROM aptitude_attempts aa
             JOIN aptitude_tests at ON aa.test_id = at.test_id
             LEFT JOIN aptitude_categories c ON at.category_id = c.category_id
             WHERE aa.attempt_id = :attempt_id",
            ['attempt_id' => $attemptId]
        );
        
        if ($result) {
            // Get question-wise details
            $result['answers'] = $this->db->fetchAll(
                "SELECT ans.*, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, 
                        q.correct_answer, q.explanation, q.marks
                 FROM aptitude_answers ans
                 JOIN aptitude_questions q ON ans.question_id = q.question_id
                 WHERE ans.attempt_id = :attempt_id",
                ['attempt_id' => $attemptId]
            );
        }
        
        return $result;
    }
    
    // Get user's test history
    public function getUserHistory($userId, $limit = 10) {
        return $this->db->fetchAll(
            "SELECT aa.*, at.test_name, at.total_marks, c.category_name
             FROM aptitude_attempts aa
             JOIN aptitude_tests at ON aa.test_id = at.test_id
             LEFT JOIN aptitude_categories c ON at.category_id = c.category_id
             WHERE aa.user_id = :user_id AND aa.status = 'completed'
             ORDER BY aa.end_time DESC
             LIMIT :limit",
            ['user_id' => $userId, 'limit' => (int)$limit]
        );
    }
    
    // Get categories
    public function getCategories() {
        return $this->db->fetchAll(
            "SELECT c.*, COUNT(t.test_id) as test_count
             FROM aptitude_categories c
             LEFT JOIN aptitude_tests t ON c.category_id = t.category_id AND t.is_active = 1
             WHERE c.is_active = 1
             GROUP BY c.category_id"
        );
    }
}
