<?php
require_once __DIR__ . '/../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

header('Content-Type: application/json');
echo json_encode(['success' => true, 'timestamp' => time()]);
?>
