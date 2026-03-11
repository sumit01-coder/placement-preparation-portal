<?php
// ============================================
// DATABASE CONFIGURATION
// ============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');           // Change to your DB username
define('DB_PASS', '');               // Change to your DB password
define('DB_NAME', 'placement_portal');
define('DB_CHARSET', 'utf8mb4');

// ============================================
// SITE CONFIGURATION
// ============================================

define('SITE_NAME', 'Placement Portal');
define('SITE_URL', 'http://localhost/placementportal');
define('BASE_URL', 'http://localhost/placementportal');
define('SITE_EMAIL', 'admin@placementportal.com');

// ============================================
// PATH CONFIGURATION
// ============================================

define('ROOT_PATH', __DIR__ . '/..');
define('UPLOAD_PATH', ROOT_PATH . '/assets/uploads');
define('DOCUMENT_PATH', UPLOAD_PATH . '/documents');
define('RESUME_PATH', UPLOAD_PATH . '/resumes');
define('PROFILE_PATH', UPLOAD_PATH . '/profiles');

// ============================================
// SECURITY CONFIGURATION
// ============================================

define('SESSION_LIFETIME', 86400); // 24 hours
define('PASSWORD_HASH_ALGO', PASSWORD_BCRYPT);
define('PASSWORD_HASH_COST', 10);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// ============================================
// FILE UPLOAD CONFIGURATION
// ============================================

define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/jpg']);
define('ALLOWED_DOCUMENT_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

// ============================================
// CODE COMPILER CONFIGURATION (Judge0)
// ============================================

define('JUDGE0_API_URL', 'https://judge0-ce.p.rapidapi.com');
define('JUDGE0_API_KEY', 'YOUR_RAPIDAPI_KEY_HERE');
define('CODE_EXECUTION_TIMEOUT', 10); // seconds

// ============================================
// OPENROUTER CONFIGURATION (AI Compiler)
// ============================================

// Set OPENROUTER_API_KEY as an environment variable in production.
$openRouterApiKey = getenv('OPENROUTER_API_KEY');
if (!is_string($openRouterApiKey) || trim($openRouterApiKey) === '') {
    $openRouterApiKey = 'YOUR_OPENROUTER_API_KEY_HERE'; // Replace before use
}
define('OPENROUTER_API_KEY', trim($openRouterApiKey));
define('OPENROUTER_API_URL', 'https://openrouter.ai/api/v1/chat/completions');
define('OPENROUTER_MODEL', 'google/gemini-2.0-flash-001');

// ============================================
// FOCUS MODE CONFIGURATION
// ============================================

define('FOCUS_PENALTY_PER_VIOLATION', 5); // percentage points
define('MIN_FOCUS_SCORE', 0);
define('MAX_FOCUS_VIOLATIONS', 20);

// ============================================
// PAGINATION CONFIGURATION
// ============================================

define('ITEMS_PER_PAGE', 20);
define('QUESTIONS_PER_PAGE', 15);
define('TESTS_PER_PAGE', 12);

// ============================================
// ERROR REPORTING (disable in production)
// ============================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ============================================
// TIMEZONE
// ============================================

date_default_timezone_set('Asia/Kolkata');

// ============================================
// SESSION CONFIGURATION
// ============================================

ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path' => '/',
    'domain' => '',
    'secure' => false, // Set to true if using HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// ============================================
// CSRF TOKEN GENERATION
// ============================================

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
