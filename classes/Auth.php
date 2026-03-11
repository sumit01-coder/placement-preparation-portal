<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

// ============================================
// AUTHENTICATION CLASS
// ============================================

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // Register new user
    public function register($email, $password, $fullName, $phone = null, $college = null) {
        try {
            // Check if email already exists
            $existingUser = $this->db->fetchOne(
                "SELECT user_id FROM users WHERE email = :email",
                ['email' => $email]
            );
            
            if ($existingUser) {
                return ['success' => false, 'message' => 'Email already registered'];
            }
            
            // Hash password
            $passwordHash = password_hash($password, PASSWORD_HASH_ALGO, ['cost' => PASSWORD_HASH_COST]);
            
            // Generate verification token
            $verificationToken = bin2hex(random_bytes(32));
            
            $this->db->beginTransaction();
            
            // Insert into users table
            $this->db->insert('users', [
                'email' => $email,
                'password_hash' => $passwordHash,
                'verification_token' => $verificationToken,
                'role_id' => 1 // Student
            ]);
            
            $userId = $this->db->lastInsertId();
            
            // Insert into user_profiles table
            $this->db->insert('user_profiles', [
                'user_id' => $userId,
                'full_name' => $fullName,
                'phone' => $phone,
                'college_name' => $college
            ]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Registration successful! Please login.',
                'user_id' => $userId
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Registration Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }
    
    // Login user
    public function login($email, $password, $rememberMe = false, $adminOnly = false) {
        try {
            $user = $this->db->fetchOne(
                "SELECT u.*, up.full_name, r.role_name 
                 FROM users u
                 LEFT JOIN user_profiles up ON u.user_id = up.user_id
                 LEFT JOIN roles r ON u.role_id = r.role_id
                 WHERE u.email = :email",
                ['email' => $email]
            );
            
            if (!$user) {
                return ['success' => false, 'message' => 'Invalid email or password'];
            }
            
            if (!$user['is_active']) {
                return ['success' => false, 'message' => 'Account is inactive. Contact support.'];
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Invalid email or password'];
            }

            if ($adminOnly && strtolower((string)($user['role_name'] ?? '')) !== 'admin') {
                return ['success' => false, 'message' => 'Admin access required'];
            }
            
            // Update last login
            $this->db->update('users', 
                ['last_login' => date('Y-m-d H:i:s')],
                'user_id = :user_id',
                ['user_id' => $user['user_id']]
            );
            
            // Log activity
            try {
                $this->db->insert('user_activity', [
                    'user_id' => $user['user_id'],
                    'activity_type' => 'login',
                    'details' => 'User logged in'
                ]);
            } catch (Exception $e) {
                // Ignore activity log error to not block login
            }
            
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role_name'];
            $_SESSION['logged_in'] = true;
            
            return [
                'success' => true,
                'message' => 'Login successful!',
                'redirect' => $user['role_name'] === 'admin' ? 'modules/admin/dashboard.php' : 'modules/dashboard/index.php'
            ];
            
        } catch (Exception $e) {
            error_log("Login Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed. Please try again.'];
        }
    }
    
    // Logout user
    public function logout() {
        session_unset();
        session_destroy();
        return ['success' => true, 'message' => 'Logged out successfully'];
    }
    
    // Check if user is logged in
    public static function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    // Check if user is admin
    public static function isAdmin() {
        return self::isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
    
    // Get current user ID
    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    // Require login
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: ' . SITE_URL . '/index.php');
            exit;
        }
    }
    
    // Require admin
    public static function requireAdmin() {
        self::requireLogin();
        if (!self::isAdmin()) {
            header('Location: ' . SITE_URL . '/modules/dashboard/index.php');
            exit;
        }
    }
    
    // Verify CSRF token
    public static function verifyCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    // Get CSRF token
    public static function getCsrfToken() {
        return $_SESSION['csrf_token'] ?? '';
    }
    
    // Password reset request
    public function requestPasswordReset($email) {
        try {
            $user = $this->db->fetchOne(
                "SELECT user_id FROM users WHERE email = :email",
                ['email' => $email]
            );
            
            if (!$user) {
                return ['success' => false, 'message' => 'Email not found'];
            }
            
            $resetToken = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $this->db->update('users',
                [
                    'reset_token' => $resetToken,
                    'reset_token_expiry' => $expiry
                ],
                'user_id = :user_id',
                ['user_id' => $user['user_id']]
            );
            
            // TODO: Send email with reset link
            
            return [
                'success' => true,
                'message' => 'Password reset link sent to your email',
                'reset_token' => $resetToken // For testing only
            ];
            
        } catch (Exception $e) {
            error_log("Password Reset Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to process request'];
        }
    }
    
    // Reset password
    public function resetPassword($token, $newPassword) {
        try {
            $user = $this->db->fetchOne(
                "SELECT user_id FROM users 
                 WHERE reset_token = :token 
                 AND reset_token_expiry > NOW()",
                ['token' => $token]
            );
            
            if (!$user) {
                return ['success' => false, 'message' => 'Invalid or expired reset token'];
            }
            
            $passwordHash = password_hash($newPassword, PASSWORD_HASH_ALGO, ['cost' => PASSWORD_HASH_COST]);
            
            $this->db->update('users',
                [
                    'password_hash' => $passwordHash,
                    'reset_token' => null,
                    'reset_token_expiry' => null
                ],
                'user_id = :user_id',
                ['user_id' => $user['user_id']]
            );
            
            return ['success' => true, 'message' => 'Password reset successful'];
            
        } catch (Exception $e) {
            error_log("Password Reset Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to reset password'];
        }
    }
}
