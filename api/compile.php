<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Compiler.php';
require_once __DIR__ . '/../classes/Submission.php';

header('Content-Type: application/json');

// Require authentication
try {
    Auth::requireLogin();
    $userId = Auth::getUserId();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get request data
$data = json_decode(file_get_contents('php://input'), true);

$sourceCode = $data['source_code'] ?? '';
$languageId = $data['language_id'] ?? null;
$problemId = $data['problem_id'] ?? null;
$customInput = $data['custom_input'] ?? '';
$action = $data['action'] ?? 'run';  // 'run' or 'submit'

// Validate inputs
if (empty($sourceCode) || empty($languageId)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$compiler = new Compiler();
$submissionClass = new Submission();

try {
    if ($action === 'run') {
        // Run code with custom input (no submission tracking)
        $input = !empty($customInput) ? $customInput : '';
        $result = $compiler->executeCode($sourceCode, $languageId, $input);
        
        echo json_encode([
            'success' => true,
            'status' => $result['status'] ?? 'Completed',
            'stdout' => $result['stdout'] ?? '',
            'stderr' => $result['stderr'] ?? '',
            'compile_output' => $result['compile_output'] ?? '',
            'time' => $result['time'] ?? 0,
            'memory' => $result['memory'] ?? 0
        ]);
        
    } elseif ($action === 'submit' && $problemId) {
        // Submit solution - run against all test cases and track submission
        $result = $compiler->submitSolution($userId, $problemId, $languageId, $sourceCode);
        
        // Record submission in database
        $submissionData = [
            'time' => $result['time'] ?? null,
            'memory' => $result['memory'] ?? null,
            'passed' => $result['passed'] ?? 0,
            'total' => $result['total'] ?? 0,
            'time_complexity' => $result['time_complexity'] ?? null,
            'space_complexity' => $result['space_complexity'] ?? null
        ];
        
        $submissionId = $submissionClass->recordSubmission(
            $userId,
            $problemId,
            $languageId,
            $sourceCode,
            $result['status'] ?? 'pending',
            $submissionData
        );
        
        // Add submission ID to response
        $result['submission_id'] = $submissionId;
        
        echo json_encode($result);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action or missing problem ID']);
    }
    
} catch (Exception $e) {
    error_log("Compilation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during code execution',
        'error' => $e->getMessage()
    ]);
}
