<?php
require_once '../../config/config.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Aptitude.php';

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: tests.php');
    exit;
}

$attemptId = isset($_POST['attempt_id']) ? (int)$_POST['attempt_id'] : 0;
$answers = isset($_POST['answers']) ? $_POST['answers'] : [];

if ($attemptId <= 0) {
    die("Invalid attempt ID");
}

$aptitude = new Aptitude();

// Process all answers
foreach ($answers as $questionId => $selectedOption) {
    $aptitude->submitAnswer($attemptId, $questionId, $selectedOption);
}

// Complete the attempt (calculates score)
$result = $aptitude->completeAttempt($attemptId);

if ($result['success']) {
    // Redirect to result page
    header("Location: result.php?id=" . $attemptId);
    exit;
} else {
    echo "Error submitting test: " . htmlspecialchars($result['message']);
}
?>
