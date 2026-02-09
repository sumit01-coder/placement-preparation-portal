<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

// ==================================================================
// COMPILER CLASS - Smart Code Studio Integration
// ============================================

class Compiler {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // Get all problems
    public function getProblems($difficulty = null, $tag = null, $limit = 20, $offset = 0) {
        $sql = "SELECT * FROM coding_problems WHERE 1=1";
        $params = [];
        
        if ($difficulty) {
            $sql .= " AND difficulty = :difficulty";
            $params['difficulty'] = $difficulty;
        }
        
        if ($tag) {
            $sql .= " AND FIND_IN_SET(:tag, tags)";
            $params['tag'] = $tag;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $params['limit'] = (int)$limit;
        $params['offset'] = (int)$offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    // Get problem details with test cases
    public function getProblem($problemId) {
        $problem = $this->db->fetchOne(
            "SELECT * FROM coding_problems WHERE problem_id = :problem_id",
            ['problem_id' => $problemId]
        );
        
        if ($problem) {
            $problem['test_cases'] = $this->db->fetchAll(
                "SELECT * FROM test_cases WHERE problem_id = :problem_id ORDER BY testcase_order",
                ['problem_id' => $problemId]
            );
            
            $problem['sample_cases'] = array_filter($problem['test_cases'], function($tc) {
                return $tc['is_sample'] == 1;
            });
        }
        
        return $problem;
    }
    
    // Execute code using Judge0 API
    public function executeCode($sourceCode, $languageId, $input = '', $expectedOutput = null) {
        try {
            // Judge0 submission
            $url = JUDGE0_API_URL . '/submissions?base64_encoded=true&wait=true';
            
            $data = [
                'source_code' => base64_encode($sourceCode),
                'language_id' => $languageId,
                'stdin' => base64_encode($input),
                'expected_output' => $expectedOutput ? base64_encode($expectedOutput) : null
            ];
            
            $headers = [
                'Content-Type: application/json',
                'X-RapidAPI-Host: judge0-ce.p.rapidapi.com',
                'X-RapidAPI-Key: ' . JUDGE0_API_KEY
            ];
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200 && $httpCode !== 201) {
                return [
                    'success' => false,
                    'message' => 'Code execution service unavailable',
                    'status' => 'error'
                ];
            }
            
            $result = json_decode($response, true);
            
            return [
                'success' => true,
                'status_id' => $result['status']['id'] ?? 0,
                'status' => $result['status']['description'] ?? 'Unknown',
                'stdout' => isset($result['stdout']) ? base64_decode($result['stdout']) : '',
                'stderr' => isset($result['stderr']) ? base64_decode($result['stderr']) : '',
                'compile_output' => isset($result['compile_output']) ? base64_decode($result['compile_output']) : '',
                'time' => $result['time'] ?? 0,
                'memory' => $result['memory'] ?? 0
            ];
            
        } catch (Exception $e) {
            error_log("Code Execution Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Execution failed',
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Submit solution
    public function submitSolution($userId, $problemId, $languageId, $sourceCode) {
        try {
            // Get test cases
            $testCases = $this->db->fetchAll(
                "SELECT * FROM test_cases WHERE problem_id = :problem_id",
                ['problem_id' => $problemId]
            );
            
            if (empty($testCases)) {
                return ['success' => false, 'message' => 'No test cases found'];
            }
            
            $passedCount = 0;
            $totalCount = count($testCases);
            $maxTime = 0;
            $maxMemory = 0;
            $finalStatus = 'accepted';
            
            // Run against all test cases
            foreach ($testCases as $testCase) {
                $result = $this->executeCode(
                    $sourceCode,
                    $languageId,
                    $testCase['input_data'],
                    $testCase['expected_output']
                );
                
                if (!$result['success']) {
                    $finalStatus = 'runtime_error';
                    break;
                }
                
                $maxTime = max($maxTime, $result['time'] ?? 0);
                $maxMemory = max($maxMemory, $result['memory'] ?? 0);
                
                $output = trim($result['stdout']);
                $expected = trim($testCase['expected_output']);
                
                if ($result['status_id'] == 3 && $output === $expected) {
                    $passedCount++;
                } else {
                    if ($result['status_id'] == 5) {
                        $finalStatus = 'time_limit';
                    } elseif ($result['status_id'] == 6) {
                        $finalStatus = 'compile_error';
                    } else {
                        $finalStatus = 'wrong_answer';
                    }
                    break;
                }
            }
            
            if ($passedCount === $totalCount) {
                $finalStatus = 'accepted';
            }
            
            // Estimate complexity (simple heuristic)
            $timeComplexity = $this->estimateTimeComplexity($sourceCode);
            $spaceComplexity = $this->estimateSpaceComplexity($sourceCode);
            
            // Save submission
            $this->db->insert('coding_submissions', [
                'user_id' => $userId,
                'problem_id' => $problemId,
                'language_id' => $languageId,
                'source_code' => $sourceCode,
                'status' => $finalStatus,
                'execution_time_ms' => round($maxTime * 1000),
                'memory_used_kb' => $maxMemory,
                'time_complexity' => $timeComplexity,
                'space_complexity' => $spaceComplexity,
                'passed_testcases' => $passedCount,
                'total_testcases' => $totalCount
            ]);
            
            return [
                'success' => true,
                'status' => $finalStatus,
                'passed' => $passedCount,
                'total' => $totalCount,
                'time' => round($maxTime * 1000, 2) . ' ms',
                'memory' => $maxMemory . ' KB',
                'time_complexity' => $timeComplexity,
                'space_complexity' => $spaceComplexity
            ];
            
        } catch (Exception $e) {
            error_log("Submit Solution Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Submission failed'];
        }
    }
    
    // Simple complexity estimation
    private function estimateTimeComplexity($code) {
        $loopCount = substr_count(strtolower($code), 'for') + substr_count(strtolower($code), 'while');
        
        if ($loopCount >= 3) return 'O(n³)';
        if ($loopCount == 2) return 'O(n²)';
        if ($loopCount == 1) return 'O(n)';
        if (stripos($code, 'sort') !== false) return 'O(n log n)';
        return 'O(1)';
    }
    
    private function estimateSpaceComplexity($code) {
        if (stripos($code, 'list') !== false || stripos($code, 'array') !== false) {
            return 'O(n)';
        }
        return 'O(1)';
    }
    
    // Get user submissions
    public function getUserSubmissions($userId, $problemId = null, $limit = 20) {
        $sql = "SELECT cs.*, cp.title as problem_title, sl.language_name
                FROM coding_submissions cs
                JOIN coding_problems cp ON cs.problem_id = cp.problem_id
                JOIN supported_languages sl ON cs.language_id = sl.language_id
                WHERE cs.user_id = :user_id";
        
        $params = ['user_id' => $userId];
        
        if ($problemId) {
            $sql .= " AND cs.problem_id = :problem_id";
            $params['problem_id'] = $problemId;
        }
        
        $sql .= " ORDER BY cs.submitted_at DESC LIMIT :limit";
        $params['limit'] = (int)$limit;
        
        return $this->db->fetchAll($sql, $params);
    }
}
