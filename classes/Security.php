<?php
/**
 * Security Utility Class
 * Handles CSRF tokens, XSS prevention, and security headers
 */
class Security {
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Sanitize input to prevent XSS
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Hash password securely
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Set security headers
     */
    public static function setSecurityHeaders() {
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // Enable XSS protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
        
        // Force HTTPS (uncomment in production)
        // header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    
    /**
     * Rate limiting
     */
    public static function checkRateLimit($key, $maxAttempts = 5, $timeWindow = 300) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $rateLimitKey = 'rate_limit_' . $key;
        
        if (!isset($_SESSION[$rateLimitKey])) {
            $_SESSION[$rateLimitKey] = [
                'count' => 1,
                'start_time' => time()
            ];
            return true;
        }
        
        $data = $_SESSION[$rateLimitKey];
        $elapsed = time() - $data['start_time'];
        
        // Reset if time window has passed
        if ($elapsed > $timeWindow) {
            $_SESSION[$rateLimitKey] = [
                'count' => 1,
                'start_time' => time()
            ];
            return true;
        }
        
        // Check if limit exceeded
        if ($data['count'] >= $maxAttempts) {
            return false;
        }
        
        // Increment counter
        $_SESSION[$rateLimitKey]['count']++;
        return true;
    }
    
    /**
     * Validate file upload
     */
    public static function validateFileUpload($file, $allowedTypes = [], $maxSize = 5242880) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Upload failed'];
        }
        
        // Check file size (default 5MB)
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File too large'];
        }
        
        // Check file type
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedTypes)) {
            return ['valid' => false, 'error' => 'Invalid file type'];
        }
        
        // Verify MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimeTypes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png'
        ];
        
        if (isset($allowedMimeTypes[$fileExtension]) && $mimeType !== $allowedMimeTypes[$fileExtension]) {
            return ['valid' => false, 'error' => 'Invalid file content'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Generate secure random token
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
}
