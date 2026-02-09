<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

// ============================================
// USER CLASS
// ============================================

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // Get user profile
    public function getProfile($userId) {
        return $this->db->fetchOne(
            "SELECT u.*, up.*, r.role_name
             FROM users u
             LEFT JOIN user_profiles up ON u.user_id = up.user_id
             LEFT JOIN roles r ON u.role_id = r.role_id
             WHERE u.user_id = :user_id",
            ['user_id' => $userId]
        );
    }
    
    // Update profile
    public function updateProfile($userId, $data) {
        try {
            $allowedFields = ['full_name', 'phone', 'college_name', 'branch', 'graduation_year', 'bio', 'linkedin_url', 'github_url'];
            $updateData = array_intersect_key($data, array_flip($allowedFields));
            
            $this->db->update('user_profiles',
                $updateData,
                'user_id = :user_id',
                ['user_id' => $userId]
            );
            
            return ['success' => true, 'message' => 'Profile updated successfully'];
        } catch (Exception $e) {
            error_log("Profile Update Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update profile'];
        }
    }
    
    // Update profile picture
    public function updateProfilePicture($userId, $file) {
        try {
            $allowedTypes = ALLOWED_IMAGE_TYPES;
            $maxSize = MAX_FILE_SIZE;
            
            if (!in_array($file['type'], $allowedTypes)) {
                return ['success' => false, 'message' => 'Invalid file type'];
            }
            
            if ($file['size'] > $maxSize) {
                return ['success' => false, 'message' => 'File size exceeds limit'];
            }
            
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $userId . '_' . time() . '.' . $extension;
            $uploadPath = PROFILE_PATH . '/' . $filename;
            
            if (!is_dir(PROFILE_PATH)) {
                mkdir(PROFILE_PATH, 0777, true);
            }
            
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $this->db->update('user_profiles',
                    ['profile_picture' => $filename],
                    'user_id = :user_id',
                    ['user_id' => $userId]
                );
                
                return ['success' => true, 'message' => 'Profile picture updated', 'filename' => $filename];
            }
            
            return ['success' => false, 'message' => 'Failed to upload file'];
            
        } catch (Exception $e) {
            error_log("Profile Picture Upload Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Upload failed'];
        }
    }
    
    // Get dashboard statistics
    public function getDashboardStats($userId) {
        try {
            $stats = [];
            
            // Tests taken
            $stats['tests_taken'] = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM aptitude_attempts WHERE user_id = :user_id AND status = 'completed'",
                ['user_id' => $userId]
            )['count'];
            
            // Average score
            $avgScore = $this->db->fetchOne(
                "SELECT AVG(percentage) as avg_score FROM aptitude_attempts WHERE user_id = :user_id AND status = 'completed'",
                ['user_id' => $userId]
            );
            $stats['avg_score'] = round($avgScore['avg_score'] ?? 0, 2);
            
            // Problems solved
            $stats['problems_solved'] = $this->db->fetchOne(
                "SELECT COUNT(DISTINCT problem_id) as count FROM coding_submissions WHERE user_id = :user_id AND status = 'accepted'",
                ['user_id' => $userId]
            )['count'];
            
            // Total submissions
            $stats['total_submissions'] = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM coding_submissions WHERE user_id = :user_id",
                ['user_id' => $userId]
            )['count'];
            
            // Community rank
            $leaderboard = $this->db->fetchOne(
                "SELECT rank_position, reputation_score FROM leaderboard WHERE user_id = :user_id",
                ['user_id' => $userId]
            );
            $stats['community_rank'] = $leaderboard['rank_position'] ?? '-';
            $stats['reputation'] = $leaderboard['reputation_score'] ?? 0;
            
            // Recent activities
            $stats['recent_tests'] = $this->db->fetchAll(
                "SELECT aa.*, at.test_name, at.total_marks
                 FROM aptitude_attempts aa
                 JOIN aptitude_tests at ON aa.test_id = at.test_id
                 WHERE aa.user_id = :user_id AND aa.status = 'completed'
                 ORDER BY aa.end_time DESC
                 LIMIT 5",
                ['user_id' => $userId]
            );
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Dashboard Stats Error: " . $e->getMessage());
            return [];
        }
    }
    
    // Change password
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            $user = $this->db->fetchOne(
                "SELECT password_hash FROM users WHERE user_id = :user_id",
                ['user_id' => $userId]
            );
            
            if (!password_verify($currentPassword, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Current password is incorrect'];
            }
            
            $newHash = password_hash($newPassword, PASSWORD_HASH_ALGO, ['cost' => PASSWORD_HASH_COST]);
            
            $this->db->update('users',
                ['password_hash' => $newHash],
                'user_id = :user_id',
                ['user_id' => $userId]
            );
            
            return ['success' => true, 'message' => 'Password changed successfully'];
            
        } catch (Exception $e) {
            error_log("Change Password Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to change password'];
        }
    }
}
