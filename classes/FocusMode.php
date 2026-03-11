<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

// ============================================
// FOCUS MODE CLASS - USP Feature
// ============================================

class FocusMode {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // Start focus session
    public function startSession($userId, $sessionType, $referenceId = null) {
        try {
            $this->db->insert('focus_sessions', [
                'user_id' => $userId,
                'session_type' => $sessionType,
                'reference_id' => $referenceId
            ]);
            
            return [
                'success' => true,
                'session_id' => $this->db->lastInsertId()
            ];
        } catch (Exception $e) {
            error_log("Start Focus Session Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to start session'];
        }
    }
    
    // Log violation
    public function logViolation($sessionId, $violationType, $duration = 0) {
        try {
            $this->db->insert('focus_violations', [
                'session_id' => $sessionId,
                'violation_type' => $violationType,
                'duration_seconds' => $duration
            ]);
            
            return ['success' => true];
        } catch (Exception $e) {
            error_log("Log Violation Error: " . $e->getMessage());
            return ['success' => false];
        }
    }
    
    // End focus session
    public function endSession($sessionId) {
        try {
            $session = $this->db->fetchOne(
                "SELECT start_time, violation_count, focus_score FROM focus_sessions WHERE session_id = :session_id",
                ['session_id' => $sessionId]
            );
            
            $startTime = new DateTime($session['start_time']);
            $endTime = new DateTime();
            $duration = $endTime->getTimestamp() - $startTime->getTimestamp();
            
            $this->db->update('focus_sessions',
                [
                    'end_time' => date('Y-m-d H:i:s'),
                    'duration_seconds' => $duration
                ],
                'session_id = :session_id',
                ['session_id' => $sessionId]
            );
            
            // Update test attempt if applicable
            if ($session['focus_score'] !== null) {
                $this->updateAttemptFocusScore($sessionId, $session['focus_score']);
            }
            
            return [
                'success' => true,
                'duration' => $duration,
                'violations' => $session['violation_count'],
                'focus_score' => $session['focus_score']
            ];
            
        } catch (Exception $e) {
            error_log("End Focus Session Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to end session'];
        }
    }
    
    // Update test attempt with focus score
    private function updateAttemptFocusScore($sessionId, $focusScore) {
        try {
            $session = $this->db->fetchOne(
                "SELECT reference_id FROM focus_sessions WHERE session_id = :session_id AND session_type = 'test'",
                ['session_id' => $sessionId]
            );
            
            if ($session && $session['reference_id']) {
                $this->db->update('aptitude_attempts',
                    ['focus_score' => $focusScore],
                    'attempt_id = :attempt_id',
                    ['attempt_id' => $session['reference_id']]
                );
            }
        } catch (Exception $e) {
            error_log("Update Focus Score Error: " . $e->getMessage());
        }
    }
    
    // Get user focus analytics
    public function getUserAnalytics($userId, $days = 30) {
        try {
            $stats = [];
            
            // Overall stats
            $overall = $this->db->fetchOne(
                "SELECT 
                    COUNT(*) as total_sessions,
                    SUM(duration_seconds) as total_time,
                    AVG(focus_score) as avg_focus_score,
                    SUM(violation_count) as total_violations
                 FROM focus_sessions
                 WHERE user_id = :user_id AND end_time IS NOT NULL
                 AND start_time >= DATE_SUB(NOW(), INTERVAL :days DAY)",
                ['user_id' => $userId, 'days' => $days]
            );
            
            $stats['total_sessions'] = $overall['total_sessions'] ?? 0;
            $stats['total_hours'] = round(($overall['total_time'] ?? 0) / 3600, 2);
            $stats['avg_focus_score'] = round($overall['avg_focus_score'] ?? 100, 2);
            $stats['total_violations'] = $overall['total_violations'] ?? 0;
            
            // Daily breakdown
            $stats['daily'] = $this->db->fetchAll(
                "SELECT 
                    DATE(start_time) as date,
                    COUNT(*) as sessions,
                    SUM(duration_seconds)/3600 as hours,
                    AVG(focus_score) as focus_score
                 FROM focus_sessions
                 WHERE user_id = :user_id AND end_time IS NOT NULL
                 AND start_time >= DATE_SUB(NOW(), INTERVAL :days DAY)
                 GROUP BY DATE(start_time)
                 ORDER BY date DESC",
                ['user_id' => $userId, 'days' => $days]
            );
            
            // Violation breakdown
            $stats['violations_by_type'] = $this->db->fetchAll(
                "SELECT 
                    fv.violation_type,
                    COUNT(*) as count
                 FROM focus_violations fv
                 JOIN focus_sessions fs ON fv.session_id = fs.session_id
                 WHERE fs.user_id = :user_id
                 AND fv.violation_time >= DATE_SUB(NOW(), INTERVAL :days DAY)
                 GROUP BY fv.violation_type",
                ['user_id' => $userId, 'days' => $days]
            );
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Focus Analytics Error: " . $e->getMessage());
            return [];
        }
    }
    // Clear violations for current active session (Reward for solving problem)
    public function clearCurrentSessionViolations($userId) {
        try {
            // Find active session
            $session = $this->db->fetchOne(
                "SELECT session_id FROM focus_sessions WHERE user_id = :user_id AND end_time IS NULL ORDER BY start_time DESC LIMIT 1",
                ['user_id' => $userId]
            );
            
            if ($session) {
                // Delete violations for this session
                $this->db->query(
                    "DELETE FROM focus_violations WHERE session_id = :session_id",
                    ['session_id' => $session['session_id']]
                );
                
                // Reset violation count in session table
                $this->db->update('focus_sessions',
                    ['violation_count' => 0],
                    'session_id = :session_id',
                    ['session_id' => $session['session_id']]
                );
                
                return ['success' => true, 'message' => 'Violations cleared'];
            }
            
            return ['success' => false, 'message' => 'No active session found'];
            
        } catch (Exception $e) {
            error_log("Clear Violations Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to clear violations'];
        }
    }
}
