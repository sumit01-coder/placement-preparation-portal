<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$fullName = $_POST['full_name'] ?? '';
$college = $_POST['college'] ?? null;
$phone = $_POST['phone'] ?? null;

if (empty($email) || empty($password) || empty($fullName)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

$auth = new Auth();
$result = $auth->register($email, $password, $fullName, $phone, $college);

echo json_encode($result);
