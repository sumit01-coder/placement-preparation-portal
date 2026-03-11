<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Aptitude.php';

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: tests.php');
    exit;
}

if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    header('Location: tests.php?err=' . urlencode('Invalid request. Please retry.'));
    exit;
}

$attemptId = isset($_POST['attempt_id']) ? (int)$_POST['attempt_id'] : 0;
$answers = $_POST['answers'] ?? [];

if ($attemptId <= 0) {
    header('Location: tests.php?err=' . urlencode('Invalid test attempt.'));
    exit;
}

$db = Database::getInstance();
$userId = (int)Auth::getUserId();

$tableExists = static function ($tableName) use ($db) {
    $row = $db->fetchOne(
        "SELECT COUNT(*) AS cnt
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
         AND table_name = :table_name",
        ['table_name' => $tableName]
    );
    return ((int)($row['cnt'] ?? 0)) > 0;
};

$ownedAttempt = null;
if ($tableExists('aptitude_attempts')) {
    $ownedAttempt = $db->fetchOne(
        "SELECT attempt_id
         FROM aptitude_attempts
         WHERE attempt_id = :attempt_id
         AND user_id = :user_id",
        ['attempt_id' => $attemptId, 'user_id' => $userId]
    );
}
if (!$ownedAttempt && $tableExists('test_attempts')) {
    $ownedAttempt = $db->fetchOne(
        "SELECT attempt_id
         FROM test_attempts
         WHERE attempt_id = :attempt_id
         AND user_id = :user_id",
        ['attempt_id' => $attemptId, 'user_id' => $userId]
    );
}

if (!$ownedAttempt) {
    header('Location: tests.php?err=' . urlencode('Unauthorized attempt access.'));
    exit;
}

$aptitude = new Aptitude();
$validOptions = ['A', 'B', 'C', 'D'];

if (is_array($answers)) {
    foreach ($answers as $questionId => $selectedOption) {
        $qid = (int)$questionId;
        $answer = strtoupper(trim((string)$selectedOption));
        if ($qid <= 0 || !in_array($answer, $validOptions, true)) {
            continue;
        }
        $aptitude->submitAnswer($attemptId, $qid, $answer);
    }
}

$result = $aptitude->completeAttempt($attemptId);
if (!$result['success']) {
    header('Location: tests.php?err=' . urlencode((string)($result['message'] ?? 'Failed to submit test.')));
    exit;
}

header('Location: result.php?id=' . $attemptId);
exit;
?>
