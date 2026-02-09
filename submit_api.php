<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $problem_id = $_POST['problem_id'];
    $code = $_POST['code'];
    $language = $_POST['language'];
    $status = $_POST['status'];
    $violations = $_POST['violations'];

    $stmt = $conn->prepare("INSERT INTO submissions (user_id, problem_id, code, language, status, violations) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssi", $user_id, $problem_id, $code, $language, $status, $violations);
    
    if ($stmt->execute()) {
        echo "Success";
    } else {
        echo "Error";
    }
}
?>