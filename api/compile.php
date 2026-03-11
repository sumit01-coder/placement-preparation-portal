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
} catch (Throwable $e) {
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

$sourceCode = (string)($data['source_code'] ?? '');
$languageId = isset($data['language_id']) ? (int)$data['language_id'] : 0;
$problemId = isset($data['problem_id']) ? (int)$data['problem_id'] : 0;
$customInput = (string)($data['custom_input'] ?? '');
$action = strtolower((string)($data['action'] ?? 'run'));  // 'run', 'submit', 'ai_verify'
$redirectTo = (string)($data['redirect_to'] ?? '');

// Validate inputs
if (trim($sourceCode) === '' || $languageId <= 0) {
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

        if (empty($result['success'])) {
            echo json_encode([
                'success' => false,
                'status' => $result['status'] ?? 'error',
                'message' => $result['message'] ?? 'Execution failed',
                'stderr' => $result['stderr'] ?? '',
                'compile_output' => $result['compile_output'] ?? ''
            ]);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'status' => $result['status'] ?? 'Completed',
            'stdout' => $result['stdout'] ?? '',
            'stderr' => $result['stderr'] ?? '',
            'compile_output' => $result['compile_output'] ?? '',
            'time' => $result['time'] ?? 0,
            'memory' => $result['memory'] ?? 0
        ]);
        
    } elseif ($action === 'submit') {
        // Submit solution - run against all test cases and track submission
        if ($problemId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing problem ID for submission']);
            exit;
        }
        $result = $compiler->submitSolution($userId, $problemId, $languageId, $sourceCode);

        if (empty($result['success'])) {
            echo json_encode([
                'success' => false,
                'message' => $result['message'] ?? 'Submission failed'
            ]);
            exit;
        }
        
        // If solution is accepted, clear focus violations (Reward)
        if (isset($result['status']) && strtolower($result['status']) === 'accepted') {
            require_once __DIR__ . '/../classes/FocusMode.php';
            $focusMode = new FocusMode();
            $clearResult = $focusMode->clearCurrentSessionViolations($userId);
            $result['violations_cleared'] = $clearResult['success'];
        }
        
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

        if (!$submissionId) {
            echo json_encode([
                'success' => false,
                'message' => 'Submission evaluated but failed to save to database'
            ]);
            exit;
        }

        // Add submission ID to response
        $result['submission_id'] = $submissionId;
        $result['redirect_to'] = trim($redirectTo) !== '' ? $redirectTo : 'problems.php';
        
        echo json_encode($result);
        
    } elseif ($action === 'ai_verify') {
        // AI Verification (Generate random input & verify)
        if ($problemId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing problem ID for verification']);
            exit;
        }
        
        $result = $compiler->verifySolutionWithAI($sourceCode, $languageId, $problemId);
        
        if (!is_array($result)) {
            echo json_encode(['success' => false, 'message' => 'AI verification service unavailable']);
            exit;
        }

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
