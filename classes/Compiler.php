<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

class Compiler {
    private $db;
    private $supportedLanguagesCache = null;
    private const STATUS_ACCEPTED = 3;
    private const STATUS_COMPILE_ERROR = 6;
    private const STATUS_RUNTIME_ERROR = 11;

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
            $problem['test_cases'] = $this->fetchProblemTestCases((int)$problemId, false, false);
            $problem['sample_cases'] = array_values(array_filter(
                $problem['test_cases'],
                static fn($tc) => (int)($tc['is_sample'] ?? 0) === 1
            ));
        }

        return $problem;
    }

    public function getSupportedLanguages(): array {
        if (is_array($this->supportedLanguagesCache)) {
            return $this->supportedLanguagesCache;
        }

        $languages = [];
        if ($this->tableExists('supported_languages')) {
            $languages = $this->fetchSupportedLanguagesFromTable('supported_languages');
        } elseif ($this->tableExists('languages')) {
            $languages = $this->fetchSupportedLanguagesFromTable('languages');
        }

        if (empty($languages)) {
            $languages = [
                ['language_id' => 50, 'language_name' => 'C', 'language_code' => 'c', 'judge0_id' => 50],
                ['language_id' => 54, 'language_name' => 'C++', 'language_code' => 'cpp', 'judge0_id' => 54],
                ['language_id' => 62, 'language_name' => 'Java', 'language_code' => 'java', 'judge0_id' => 62],
                ['language_id' => 71, 'language_name' => 'Python', 'language_code' => 'python', 'judge0_id' => 71],
                ['language_id' => 63, 'language_name' => 'JavaScript', 'language_code' => 'javascript', 'judge0_id' => 63]
            ];
        }

        $this->supportedLanguagesCache = $languages;
        return $languages;
    }

    // Execute code using OpenRouter API (AI-based execution)
    public function executeCode($sourceCode, $languageId, $input = '', $expectedOutput = null, $problemContext = null) {
        try {
            if (!$this->hasOpenRouterKey()) {
                return [
                    'success' => false,
                    'message' => 'OpenRouter API key not configured',
                    'status' => 'error'
                ];
            }

            $language = $this->getLanguageName($languageId);
            $problemBlock = '';
            if (is_array($problemContext)) {
                $problemBlock = $this->buildProblemContextBlock($problemContext);
            }
            $expectedBlock = $expectedOutput !== null
                ? "\nExpected Output (for judge comparison only):\n" . (string)$expectedOutput
                : '';

            $prompt = "You are a deterministic coding judge runtime.

Execute the user's code mentally for the input below and return strict JSON only.
Do not explain reasoning.

Language: {$language}
{$problemBlock}
User Code:
```{$language}
{$sourceCode}
```

Input:
{$input}
{$expectedBlock}

Return JSON with exactly these keys:
{
  \"stdout\": \"string\",
  \"stderr\": \"string\",
  \"status_id\": 3,
  \"time\": 0.01,
  \"memory\": 1024
}

Status IDs:
- 3 = accepted/run success
- 6 = compile error
- 11 = runtime error or timeout";

            $systemPrompt = 'You are a strict competitive-programming execution engine. Respond with JSON only.';
            $executionResult = $this->callOpenRouter($prompt, $systemPrompt);

            if (!is_array($executionResult)) {
                return [
                    'success' => false,
                    'message' => 'Invalid OpenRouter response',
                    'status' => 'error'
                ];
            }

            $stdout = (string)($executionResult['stdout'] ?? '');
            $stderr = (string)($executionResult['stderr'] ?? '');
            $statusId = $this->normalizeStatusId($executionResult['status_id'] ?? $executionResult['status'] ?? null, $stderr);
            $time = $this->normalizeFloat($executionResult['time'] ?? 0.05, 0.05);
            $memory = $this->normalizeInt($executionResult['memory'] ?? 1024, 1024);

            return [
                'success' => true,
                'status_id' => $statusId,
                'status' => $this->statusTextFromId($statusId),
                'stdout' => $stdout,
                'stderr' => $stderr,
                'compile_output' => $statusId === self::STATUS_COMPILE_ERROR ? $stderr : '',
                'time' => $time,
                'memory' => $memory
            ];
        } catch (Throwable $e) {
            error_log("Code Execution Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Execution failed',
                'error' => $e->getMessage()
            ];
        }
    }

    // Submit solution and evaluate against all test cases
    public function submitSolution($userId, $problemId, $languageId, $sourceCode) {
        try {
            $problem = $this->getProblem((int)$problemId);
            if (!$problem) {
                return ['success' => false, 'message' => 'Problem not found'];
            }

            $testCases = $this->fetchProblemTestCases((int)$problemId, false, true);
            if (empty($testCases)) {
                return ['success' => false, 'message' => 'No test cases found'];
            }

            $passedCount = 0;
            $totalCount = count($testCases);
            $maxTime = 0.0;
            $maxMemory = 0;
            $finalStatus = 'accepted';
            $failureReason = '';

            foreach ($testCases as $index => $testCase) {
                $input = (string)($testCase['input_data'] ?? '');
                $expected = (string)($testCase['expected_output'] ?? '');

                $result = $this->executeCode($sourceCode, $languageId, $input, $expected, $problem);
                if (!$result['success']) {
                    $finalStatus = 'runtime_error';
                    $failureReason = (string)($result['message'] ?? 'Execution failed');
                    break;
                }

                $maxTime = max($maxTime, (float)($result['time'] ?? 0.0));
                $maxMemory = max($maxMemory, (int)($result['memory'] ?? 0));

                $actualOut = $this->normalizeOutput((string)($result['stdout'] ?? ''));
                $expectedOut = $this->normalizeOutput($expected);
                $statusId = (int)($result['status_id'] ?? self::STATUS_RUNTIME_ERROR);

                if ($statusId === self::STATUS_ACCEPTED && $actualOut === $expectedOut) {
                    $passedCount++;
                    continue;
                }

                if ($statusId === self::STATUS_COMPILE_ERROR) {
                    $finalStatus = 'compilation_error';
                    $failureReason = (string)($result['stderr'] ?? 'Compilation error');
                } elseif ($statusId === 5) {
                    $finalStatus = 'time_limit_exceeded';
                    $failureReason = 'Time limit exceeded';
                } elseif ($statusId === self::STATUS_RUNTIME_ERROR) {
                    $finalStatus = 'runtime_error';
                    $failureReason = (string)($result['stderr'] ?? 'Runtime error');
                } else {
                    $finalStatus = 'wrong_answer';
                    $failureReason = "Failed at test case #" . ($index + 1);
                }
                break;
            }

            if ($passedCount === $totalCount) {
                $finalStatus = 'accepted';
            }

            $timeComplexity = $this->estimateTimeComplexity($sourceCode);
            $spaceComplexity = $this->estimateSpaceComplexity($sourceCode);

            return [
                'success' => true,
                'status' => $finalStatus,
                'passed' => $passedCount,
                'total' => $totalCount,
                'time' => round($maxTime * 1000, 2) . ' ms',
                'memory' => $maxMemory . ' KB',
                'time_complexity' => $timeComplexity,
                'space_complexity' => $spaceComplexity,
                'error_message' => $failureReason
            ];
        } catch (Throwable $e) {
            error_log("Submit Solution Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Submission failed'];
        }
    }

    // AI Verification Method
    public function verifySolutionWithAI($sourceCode, $languageId, $problemId) {
        try {
            if (!$this->hasOpenRouterKey()) {
                return ['success' => false, 'message' => 'OpenRouter API key not configured'];
            }

            $problem = $this->getProblem((int)$problemId);
            if (!$problem) {
                return ['success' => false, 'message' => 'Problem not found'];
            }

            $langName = $this->getLanguageName($languageId);
            $problemBlock = $this->buildProblemContextBlock($problem);

            $prompt = "You are a competitive-programming verifier.

{$problemBlock}
Language: {$langName}

User Solution:
```{$langName}
{$sourceCode}
```

Task:
1. Generate one valid non-trivial random input (not copied from sample cases).
2. Compute expected output for that input.
3. Simulate running user code on the same input.
4. Return strict JSON only.

Return:
{
  \"success\": true,
  \"status\": \"Accepted\" or \"Wrong Answer\" or \"Runtime Error\",
  \"input\": \"...\",
  \"expected\": \"...\",
  \"user_output\": \"...\",
  \"explanation\": \"short reason\"
}";

            $result = $this->callOpenRouter($prompt, 'You are a strict coding judge. Return JSON only.');
            if (!is_array($result)) {
                return ['success' => false, 'message' => 'AI verification parse failed'];
            }

            $status = strtolower((string)($result['status'] ?? 'runtime error'));
            if ($status === 'accepted') {
                $normalized = 'accepted';
            } elseif ($status === 'wrong answer') {
                $normalized = 'wrong_answer';
            } else {
                $normalized = 'runtime_error';
            }

            return [
                'success' => true,
                'status' => $normalized,
                'input' => (string)($result['input'] ?? ''),
                'expected' => (string)($result['expected'] ?? ''),
                'user_output' => (string)($result['user_output'] ?? ''),
                'explanation' => (string)($result['explanation'] ?? '')
            ];
        } catch (Throwable $e) {
            error_log("AI verify error: " . $e->getMessage());
            return ['success' => false, 'message' => 'AI verification failed'];
        }
    }

    // Get user submissions
    public function getUserSubmissions($userId, $problemId = null, $limit = 20) {
        $sql = "SELECT s.*, cp.title as problem_title, l.language_name
                FROM submissions s
                JOIN coding_problems cp ON s.problem_id = cp.problem_id
                JOIN languages l ON s.language_id = l.language_id
                WHERE s.user_id = :user_id";

        $params = ['user_id' => (int)$userId];

        if ($problemId) {
            $sql .= " AND s.problem_id = :problem_id";
            $params['problem_id'] = (int)$problemId;
        }

        $sql .= " ORDER BY s.submitted_at DESC LIMIT :limit";
        $params['limit'] = (int)$limit;

        return $this->db->fetchAll($sql, $params);
    }

    private function fetchProblemTestCases(int $problemId, bool $samplesOnly, bool $includeHidden = true): array {
        $orderBy = [];
        if ($this->columnExists('test_cases', 'testcase_order')) {
            $orderBy[] = 'testcase_order';
        }
        if ($this->columnExists('test_cases', 'testcase_id')) {
            $orderBy[] = 'testcase_id';
        } elseif ($this->columnExists('test_cases', 'test_case_id')) {
            $orderBy[] = 'test_case_id';
        }

        $sql = "SELECT * FROM test_cases WHERE problem_id = :problem_id";
        if (!empty($orderBy)) {
            $sql .= " ORDER BY " . implode(', ', $orderBy);
        }

        $rows = $this->db->fetchAll($sql, ['problem_id' => $problemId]);

        $normalized = [];
        foreach ($rows as $row) {
            $isSample = (int)($row['is_sample'] ?? 0) === 1;
            if ($samplesOnly && !$isSample) {
                continue;
            }
            if (!$includeHidden && (int)($row['is_hidden'] ?? 0) === 1) {
                continue;
            }

            $normalized[] = [
                'input_data' => (string)($row['input_data'] ?? $row['input'] ?? ''),
                'expected_output' => (string)($row['expected_output'] ?? ''),
                'is_sample' => $isSample ? 1 : 0,
                'testcase_order' => (int)($row['testcase_order'] ?? 0),
                'is_hidden' => (int)($row['is_hidden'] ?? 0)
            ];
        }

        return $normalized;
    }

    private function fetchSupportedLanguagesFromTable(string $table): array {
        $nameColumn = $this->columnExists($table, 'language_name') ? 'language_name' : ($this->columnExists($table, 'name') ? 'name' : '');
        $codeColumn = $this->columnExists($table, 'language_code') ? 'language_code' : ($this->columnExists($table, 'language') ? 'language' : '');

        if ($nameColumn === '') {
            return [];
        }

        $judge0Select = $this->columnExists($table, 'judge0_id') ? 'judge0_id' : 'NULL AS judge0_id';
        $codeSelect = $codeColumn !== '' ? "{$codeColumn} AS language_code" : "'' AS language_code";

        $sql = "SELECT language_id, {$nameColumn} AS language_name, {$codeSelect}, {$judge0Select}
                FROM {$table}";

        if ($this->columnExists($table, 'is_active')) {
            $sql .= " WHERE is_active = 1";
        }

        $sql .= " ORDER BY language_id ASC";

        $rows = $this->db->fetchAll($sql);
        foreach ($rows as &$row) {
            $row['language_id'] = (int)($row['language_id'] ?? 0);
            $row['judge0_id'] = isset($row['judge0_id']) ? (int)$row['judge0_id'] : null;
            $row['language_name'] = (string)($row['language_name'] ?? 'Unknown');
            $row['language_code'] = strtolower(trim((string)($row['language_code'] ?? '')));
        }

        return $rows;
    }

    private function tableExists(string $table): bool {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
             AND table_name = :table_name",
            ['table_name' => $table]
        );

        return ((int)($row['cnt'] ?? 0)) > 0;
    }

    private function columnExists(string $table, string $column): bool {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
             AND table_name = :table_name
             AND column_name = :column_name",
            ['table_name' => $table, 'column_name' => $column]
        );
        return ((int)($row['cnt'] ?? 0)) > 0;
    }

    private function buildProblemContextBlock(array $problem): string {
        $lines = [];
        $lines[] = "Problem Title: " . (string)($problem['title'] ?? 'Unknown Problem');
        $lines[] = "Difficulty: " . (string)($problem['difficulty'] ?? 'Unknown');
        $lines[] = "Description:\n" . (string)($problem['description'] ?? '');
        $lines[] = "Input Format:\n" . (string)($problem['input_format'] ?? '');
        $lines[] = "Output Format:\n" . (string)($problem['output_format'] ?? '');
        $lines[] = "Constraints:\n" . (string)($problem['constraints'] ?? '');

        $sampleCases = $problem['sample_cases'] ?? [];
        if (is_array($sampleCases) && !empty($sampleCases)) {
            $sampleLines = [];
            foreach ($sampleCases as $index => $sample) {
                $sampleLines[] = "Sample " . ($index + 1) . " Input:\n" . (string)($sample['input_data'] ?? '');
                $sampleLines[] = "Sample " . ($index + 1) . " Output:\n" . (string)($sample['expected_output'] ?? '');
            }
            $lines[] = implode("\n", $sampleLines);
        }

        return implode("\n\n", $lines);
    }

    // Helper to normalize output for comparison
    private function normalizeOutput($str) {
        if (!is_string($str)) {
            return (string)$str;
        }

        $str = trim($str);
        $str = str_replace(["\r\n", "\r"], "\n", $str);

        $lines = array_map(static fn($line) => trim($line), explode("\n", $str));
        return implode("\n", $lines);
    }

    // Simple complexity estimation
    private function estimateTimeComplexity($code) {
        $normalizedCode = strtolower((string)$code);
        $loopCount = substr_count($normalizedCode, 'for') + substr_count($normalizedCode, 'while');

        if ($loopCount >= 3) {
            return 'O(n^3)';
        }
        if ($loopCount === 2) {
            return 'O(n^2)';
        }
        if ($loopCount === 1) {
            return 'O(n)';
        }
        if (strpos($normalizedCode, 'sort') !== false) {
            return 'O(n log n)';
        }
        return 'O(1)';
    }

    private function estimateSpaceComplexity($code) {
        $normalizedCode = strtolower((string)$code);
        if (
            strpos($normalizedCode, 'list') !== false
            || strpos($normalizedCode, 'array') !== false
            || strpos($normalizedCode, 'vector') !== false
            || strpos($normalizedCode, 'map') !== false
        ) {
            return 'O(n)';
        }
        return 'O(1)';
    }

    private function statusTextFromId(int $statusId): string {
        if ($statusId === self::STATUS_ACCEPTED) {
            return 'Accepted';
        }
        if ($statusId === self::STATUS_COMPILE_ERROR) {
            return 'Compilation Error';
        }
        return 'Runtime Error';
    }

    private function normalizeStatusId($rawStatus, string $stderr): int {
        $statusId = (int)$rawStatus;
        if (in_array($statusId, [self::STATUS_ACCEPTED, self::STATUS_COMPILE_ERROR, self::STATUS_RUNTIME_ERROR, 5], true)) {
            return $statusId;
        }

        $stderrLower = strtolower($stderr);
        if ($stderrLower !== '') {
            if (
                strpos($stderrLower, 'syntax') !== false
                || strpos($stderrLower, 'compile') !== false
                || strpos($stderrLower, 'compil') !== false
            ) {
                return self::STATUS_COMPILE_ERROR;
            }
            return self::STATUS_RUNTIME_ERROR;
        }

        return self::STATUS_ACCEPTED;
    }

    private function normalizeFloat($value, float $default): float {
        if (is_numeric($value)) {
            return (float)$value;
        }
        if (is_string($value) && preg_match('/-?\d+(\.\d+)?/', $value, $matches)) {
            return (float)$matches[0];
        }
        return $default;
    }

    private function normalizeInt($value, int $default): int {
        if (is_numeric($value)) {
            return (int)$value;
        }
        if (is_string($value) && preg_match('/-?\d+/', $value, $matches)) {
            return (int)$matches[0];
        }
        return $default;
    }

    private function resolveLanguageRecord($languageId): ?array {
        $id = (int)$languageId;
        foreach ($this->getSupportedLanguages() as $language) {
            if ((int)($language['language_id'] ?? 0) === $id) {
                return $language;
            }
            if (isset($language['judge0_id']) && (int)$language['judge0_id'] === $id) {
                return $language;
            }
        }

        return null;
    }

    private function getLanguageName($languageId): string {
        $record = $this->resolveLanguageRecord($languageId);
        if ($record && isset($record['language_name'])) {
            return (string)$record['language_name'];
        }

        $id = (int)$languageId;
        $fallback = [
            50 => 'C',
            54 => 'C++',
            62 => 'Java',
            71 => 'Python',
            63 => 'JavaScript'
        ];

        return $fallback[$id] ?? 'Unknown';
    }

    private function hasOpenRouterKey(): bool {
        return $this->getOpenRouterApiKey() !== '';
    }

    private function getOpenRouterApiKey(): string {
        $envKey = getenv('OPENROUTER_API_KEY');
        if (is_string($envKey) && trim($envKey) !== '') {
            return trim($envKey);
        }

        if (defined('OPENROUTER_API_KEY') && trim((string)OPENROUTER_API_KEY) !== '') {
            return trim((string)OPENROUTER_API_KEY);
        }

        return '';
    }

    // Call OpenRouter API and parse JSON response
    private function callOpenRouter($prompt, $systemPrompt = null) {
        if ($systemPrompt === null) {
            $systemPrompt = 'You are a helpful coding assistant.';
        }

        $apiKey = $this->getOpenRouterApiKey();
        if ($apiKey === '') {
            error_log("OpenRouter API key is missing.");
            return null;
        }

        $data = [
            'model' => OPENROUTER_MODEL,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.1
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'HTTP-Referer: ' . SITE_URL,
            'X-Title: ' . SITE_NAME
        ];

        $ch = curl_init(OPENROUTER_API_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            error_log("OpenRouter API Error: HTTP $httpCode - $response - Curl Error: $error");
            return null;
        }

        $result = json_decode($response, true);
        $aiContent = (string)($result['choices'][0]['message']['content'] ?? '');
        return $this->extractJsonObject($aiContent);
    }

    private function extractJsonObject(string $content): ?array {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/```json\s*(\{[\s\S]*\})\s*```/i', $trimmed, $matches)) {
            $parsed = json_decode($matches[1], true);
            if (is_array($parsed)) {
                return $parsed;
            }
        }

        if (preg_match('/(\{[\s\S]*\})/', $trimmed, $matches)) {
            $parsed = json_decode($matches[1], true);
            if (is_array($parsed)) {
                return $parsed;
            }
        }

        $parsed = json_decode($trimmed, true);
        return is_array($parsed) ? $parsed : null;
    }
}
