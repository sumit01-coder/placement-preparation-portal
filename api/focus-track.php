<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/FocusMode.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$sessionId = $data['session_id'] ?? null;
$violationType = $data['violation_type'] ?? null;
$duration = $data['duration'] ?? 0;

if (!$sessionId || !$violationType) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$focusMode = new FocusMode();
$result = $focusMode->logViolation($sessionId, $violationType, $duration);

echo json_encode($result);
