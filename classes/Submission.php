<?php
/**
 * Submission Management Class
 * Handles code submission tracking, history, and analytics
 */

class Submission {
    private $db;
    private $submissionTable;
    private $languageTable;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->submissionTable = $this->resolveSubmissionTable();
        $this->languageTable = $this->resolveLanguageTable();
    }

    private function resolveSubmissionTable() {
        if ($this->tableExists('coding_submissions')) {
            return 'coding_submissions';
        }
        if ($this->tableExists('submissions')) {
            return 'submissions';
        }
        return null;
    }

    private function resolveLanguageTable() {
        if ($this->tableExists('supported_languages')) {
            return 'supported_languages';
        }
        if ($this->tableExists('languages')) {
            return 'languages';
        }
        return null;
    }

    private function tableExists($table) {
        try {
            $row = $this->db->fetchOne(
                "SELECT COUNT(*) AS cnt
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                 AND table_name = :table_name",
                ['table_name' => $table]
            );
            return ((int)($row['cnt'] ?? 0)) > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    private function columnExists($table, $column) {
        if (!$table || !$this->tableExists($table)) {
            return false;
        }

        try {
            $row = $this->db->fetchOne(
                "SELECT COUNT(*) AS cnt
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                 AND table_name = :table_name
                 AND column_name = :column_name",
                ['table_name' => $table, 'column_name' => $column]
            );
            return ((int)($row['cnt'] ?? 0)) > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    private function getSourceCodeColumn() {
        if ($this->columnExists($this->submissionTable, 'source_code')) {
            return 'source_code';
        }
        if ($this->columnExists($this->submissionTable, 'code')) {
            return 'code';
        }
        return null;
    }

    private function getRuntimeSelect($alias = 's') {
        if ($this->columnExists($this->submissionTable, 'execution_time_ms')) {
            return "ROUND({$alias}.execution_time_ms / 1000, 3)";
        }
        if ($this->columnExists($this->submissionTable, 'runtime')) {
            return "{$alias}.runtime";
        }
        return 'NULL';
    }

    private function getMemorySelect($alias = 's') {
        if ($this->columnExists($this->submissionTable, 'memory_used_kb')) {
            return "{$alias}.memory_used_kb";
        }
        if ($this->columnExists($this->submissionTable, 'memory_used')) {
            return "{$alias}.memory_used";
        }
        if ($this->columnExists($this->submissionTable, 'memory')) {
            return "{$alias}.memory";
        }
        return 'NULL';
    }

    private function getPassedTestsSelect($alias = 's') {
        if ($this->columnExists($this->submissionTable, 'passed_testcases')) {
            return "COALESCE({$alias}.passed_testcases, 0)";
        }
        if ($this->columnExists($this->submissionTable, 'passed_tests')) {
            return "COALESCE({$alias}.passed_tests, 0)";
        }
        if ($this->columnExists($this->submissionTable, 'test_cases_passed')) {
            return "COALESCE({$alias}.test_cases_passed, 0)";
        }
        return '0';
    }

    private function getTotalTestsSelect($alias = 's') {
        if ($this->columnExists($this->submissionTable, 'total_testcases')) {
            return "COALESCE({$alias}.total_testcases, 0)";
        }
        if ($this->columnExists($this->submissionTable, 'total_tests')) {
            return "COALESCE({$alias}.total_tests, 0)";
        }
        if ($this->columnExists($this->submissionTable, 'total_test_cases')) {
            return "COALESCE({$alias}.total_test_cases, 0)";
        }
        return '0';
    }

    private function getLanguageJoinSql($alias = 's') {
        if ($this->languageTable && $this->columnExists($this->submissionTable, 'language_id')) {
            return "JOIN {$this->languageTable} l ON {$alias}.language_id = l.language_id";
        }
        return '';
    }

    private function getLanguageSelect($alias = 's') {
        if ($this->languageTable && $this->columnExists($this->submissionTable, 'language_id')) {
            return 'l.language_name';
        }
        if ($this->columnExists($this->submissionTable, 'language')) {
            return "{$alias}.language";
        }
        return "'Unknown'";
    }

    private function normalizeStatus($status) {
        $normalized = strtolower(trim((string)$status));

        if ($this->submissionTable === 'coding_submissions') {
            $map = [
                'compile_error' => 'compile_error',
                'compilation_error' => 'compile_error',
                'time_limit' => 'time_limit',
                'time_limit_exceeded' => 'time_limit',
                'accepted' => 'accepted',
                'wrong_answer' => 'wrong_answer',
                'runtime_error' => 'runtime_error',
                'pending' => 'pending'
            ];
            return $map[$normalized] ?? 'runtime_error';
        }

        $map = [
            'compile_error' => 'compilation_error',
            'compilation_error' => 'compilation_error',
            'time_limit' => 'time_limit_exceeded',
            'time_limit_exceeded' => 'time_limit_exceeded',
            'accepted' => 'accepted',
            'wrong_answer' => 'wrong_answer',
            'runtime_error' => 'runtime_error',
            'pending' => 'pending'
        ];

        return $map[$normalized] ?? 'runtime_error';
    }

    private function mapLegacyLanguage($languageId) {
        $map = [
            50 => 'c',
            54 => 'cpp',
            62 => 'java',
            71 => 'python',
            63 => 'javascript'
        ];

        return $map[(int)$languageId] ?? 'python';
    }

    private function extractMilliseconds($value) {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int)round((float)$value);
        }
        if (is_string($value) && preg_match('/-?\d+(\.\d+)?/', $value, $matches)) {
            $number = (float)$matches[0];
            if (stripos($value, 'ms') !== false) {
                return (int)round($number);
            }
            return (int)round($number * 1000);
        }
        return null;
    }

    private function extractSeconds($value) {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float)$value;
        }
        if (is_string($value) && preg_match('/-?\d+(\.\d+)?/', $value, $matches)) {
            $number = (float)$matches[0];
            if (stripos($value, 'ms') !== false) {
                return round($number / 1000, 3);
            }
            return $number;
        }
        return null;
    }

    private function extractNumeric($value) {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float)$value;
        }
        if (is_string($value) && preg_match('/-?\d+(\.\d+)?/', $value, $matches)) {
            return (float)$matches[0];
        }
        return null;
    }

    private function normalizeLanguageIdForStorage($languageId) {
        $normalizedId = (int)$languageId;
        if (
            !$this->languageTable
            || !$this->columnExists($this->submissionTable, 'language_id')
            || !$this->columnExists($this->languageTable, 'language_id')
        ) {
            return $normalizedId;
        }

        $existing = $this->db->fetchOne(
            "SELECT language_id
             FROM {$this->languageTable}
             WHERE language_id = :language_id
             LIMIT 1",
            ['language_id' => $normalizedId]
        );
        if ($existing && isset($existing['language_id'])) {
            return (int)$existing['language_id'];
        }

        if ($this->columnExists($this->languageTable, 'judge0_id')) {
            $mapped = $this->db->fetchOne(
                "SELECT language_id
                 FROM {$this->languageTable}
                 WHERE judge0_id = :judge0_id
                 LIMIT 1",
                ['judge0_id' => $normalizedId]
            );
            if ($mapped && isset($mapped['language_id'])) {
                return (int)$mapped['language_id'];
            }
        }

        if ($this->columnExists($this->languageTable, 'language_code')) {
            $mapped = $this->db->fetchOne(
                "SELECT language_id
                 FROM {$this->languageTable}
                 WHERE LOWER(language_code) = :language_code
                 LIMIT 1",
                ['language_code' => strtolower($this->mapLegacyLanguage($normalizedId))]
            );
            if ($mapped && isset($mapped['language_id'])) {
                return (int)$mapped['language_id'];
            }
        }

        return $normalizedId;
    }

    public function recordSubmission($userId, $problemId, $languageId, $code, $status, $result) {
        if (!$this->submissionTable) {
            return false;
        }

        try {
            $payload = [
                'user_id' => (int)$userId,
                'problem_id' => (int)$problemId,
                'status' => $this->normalizeStatus($status)
            ];

            if ($this->columnExists($this->submissionTable, 'language_id')) {
                $payload['language_id'] = $this->normalizeLanguageIdForStorage($languageId);
            } elseif ($this->columnExists($this->submissionTable, 'language')) {
                $payload['language'] = $this->mapLegacyLanguage($languageId);
            }

            $sourceCodeColumn = $this->getSourceCodeColumn();
            if ($sourceCodeColumn) {
                $payload[$sourceCodeColumn] = $code;
            }

            if ($this->columnExists($this->submissionTable, 'execution_time_ms')) {
                $payload['execution_time_ms'] = $this->extractMilliseconds($result['time'] ?? null);
            } elseif ($this->columnExists($this->submissionTable, 'runtime')) {
                $payload['runtime'] = $this->extractSeconds($result['time'] ?? null);
            }

            if ($this->columnExists($this->submissionTable, 'memory_used_kb')) {
                $payload['memory_used_kb'] = $this->extractNumeric($result['memory'] ?? null);
            } elseif ($this->columnExists($this->submissionTable, 'memory_used')) {
                $payload['memory_used'] = $this->extractNumeric($result['memory'] ?? null);
            } elseif ($this->columnExists($this->submissionTable, 'memory')) {
                $payload['memory'] = $this->extractNumeric($result['memory'] ?? null);
            }

            if ($this->columnExists($this->submissionTable, 'passed_testcases')) {
                $payload['passed_testcases'] = (int)($result['passed'] ?? 0);
            } elseif ($this->columnExists($this->submissionTable, 'passed_tests')) {
                $payload['passed_tests'] = (int)($result['passed'] ?? 0);
            } elseif ($this->columnExists($this->submissionTable, 'test_cases_passed')) {
                $payload['test_cases_passed'] = (int)($result['passed'] ?? 0);
            }

            if ($this->columnExists($this->submissionTable, 'total_testcases')) {
                $payload['total_testcases'] = (int)($result['total'] ?? 0);
            } elseif ($this->columnExists($this->submissionTable, 'total_tests')) {
                $payload['total_tests'] = (int)($result['total'] ?? 0);
            } elseif ($this->columnExists($this->submissionTable, 'total_test_cases')) {
                $payload['total_test_cases'] = (int)($result['total'] ?? 0);
            }

            if ($this->columnExists($this->submissionTable, 'time_complexity')) {
                $payload['time_complexity'] = $result['time_complexity'] ?? null;
            }
            if ($this->columnExists($this->submissionTable, 'space_complexity')) {
                $payload['space_complexity'] = $result['space_complexity'] ?? null;
            }
            if ($this->columnExists($this->submissionTable, 'error_message')) {
                $payload['error_message'] = $result['error_message'] ?? null;
            }

            $this->db->insert($this->submissionTable, $payload);
            $submissionId = (int)$this->db->lastInsertId();

            if ($payload['status'] === 'accepted') {
                $this->updateUserStats($userId, $problemId);
            }

            return $submissionId;
        } catch (Exception $e) {
            error_log('Submission recording failed: ' . $e->getMessage());
            return false;
        }
    }

    public function getUserSubmissions($userId, $limit = 20, $offset = 0) {
        if (!$this->submissionTable) {
            return [];
        }

        $limit = max(1, (int)$limit);
        $offset = max(0, (int)$offset);
        $runtimeExpr = $this->getRuntimeSelect('s');
        $memoryExpr = $this->getMemorySelect('s');
        $passedExpr = $this->getPassedTestsSelect('s');
        $totalExpr = $this->getTotalTestsSelect('s');
        $languageExpr = $this->getLanguageSelect('s');
        $languageJoin = $this->getLanguageJoinSql('s');

        $query = "SELECT s.*, p.title, p.difficulty,
                         {$languageExpr} AS language_name,
                         {$runtimeExpr} AS runtime,
                         {$memoryExpr} AS memory_used,
                         {$passedExpr} AS passed_tests,
                         {$totalExpr} AS total_tests
                  FROM {$this->submissionTable} s
                  JOIN coding_problems p ON s.problem_id = p.problem_id
                  {$languageJoin}
                  WHERE s.user_id = :user_id
                  ORDER BY s.submitted_at DESC
                  LIMIT {$limit} OFFSET {$offset}";

        return $this->db->fetchAll($query, ['user_id' => (int)$userId]);
    }

    public function getProblemSubmissions($userId, $problemId) {
        if (!$this->submissionTable) {
            return [];
        }

        $runtimeExpr = $this->getRuntimeSelect('s');
        $memoryExpr = $this->getMemorySelect('s');
        $passedExpr = $this->getPassedTestsSelect('s');
        $totalExpr = $this->getTotalTestsSelect('s');
        $languageExpr = $this->getLanguageSelect('s');
        $languageJoin = $this->getLanguageJoinSql('s');

        $query = "SELECT s.*,
                         {$languageExpr} AS language_name,
                         {$runtimeExpr} AS runtime,
                         {$memoryExpr} AS memory_used,
                         {$passedExpr} AS passed_tests,
                         {$totalExpr} AS total_tests
                  FROM {$this->submissionTable} s
                  {$languageJoin}
                  WHERE s.user_id = :user_id
                  AND s.problem_id = :problem_id
                  ORDER BY s.submitted_at DESC";

        return $this->db->fetchAll($query, [
            'user_id' => (int)$userId,
            'problem_id' => (int)$problemId
        ]);
    }

    public function getSubmissionById($submissionId, $userId = null) {
        if (!$this->submissionTable) {
            return null;
        }

        $runtimeExpr = $this->getRuntimeSelect('s');
        $memoryExpr = $this->getMemorySelect('s');
        $passedExpr = $this->getPassedTestsSelect('s');
        $totalExpr = $this->getTotalTestsSelect('s');
        $languageExpr = $this->getLanguageSelect('s');
        $languageJoin = $this->getLanguageJoinSql('s');

        $query = "SELECT s.*, p.title, p.difficulty,
                         {$languageExpr} AS language_name,
                         {$runtimeExpr} AS runtime,
                         {$memoryExpr} AS memory_used,
                         {$passedExpr} AS passed_tests,
                         {$totalExpr} AS total_tests,
                         up.full_name AS username
                  FROM {$this->submissionTable} s
                  JOIN coding_problems p ON s.problem_id = p.problem_id
                  {$languageJoin}
                  JOIN users u ON s.user_id = u.user_id
                  JOIN user_profiles up ON u.user_id = up.user_id
                  WHERE s.submission_id = :submission_id";

        $params = ['submission_id' => (int)$submissionId];
        if ($userId !== null) {
            $query .= ' AND s.user_id = :user_id';
            $params['user_id'] = (int)$userId;
        }

        return $this->db->fetchOne($query, $params);
    }

    public function getAcceptedSubmissions($problemId, $limit = 100) {
        if (!$this->submissionTable) {
            return [];
        }

        $limit = max(1, (int)$limit);
        $runtimeExpr = $this->getRuntimeSelect('s');
        $memoryExpr = $this->getMemorySelect('s');

        $query = "SELECT s.*,
                         {$runtimeExpr} AS runtime,
                         {$memoryExpr} AS memory_used,
                         up.full_name AS username,
                         up.full_name AS fullname
                  FROM {$this->submissionTable} s
                  JOIN users u ON s.user_id = u.user_id
                  JOIN user_profiles up ON u.user_id = up.user_id
                  WHERE s.problem_id = :problem_id
                  AND s.status = 'accepted'
                  ORDER BY {$runtimeExpr} ASC, s.submitted_at ASC
                  LIMIT {$limit}";

        return $this->db->fetchAll($query, ['problem_id' => (int)$problemId]);
    }

    public function getBestSubmission($userId, $problemId) {
        if (!$this->submissionTable) {
            return null;
        }

        $runtimeExpr = $this->getRuntimeSelect('s');
        $memoryExpr = $this->getMemorySelect('s');
        $passedExpr = $this->getPassedTestsSelect('s');
        $totalExpr = $this->getTotalTestsSelect('s');

        $query = "SELECT s.*,
                         {$runtimeExpr} AS runtime,
                         {$memoryExpr} AS memory_used,
                         {$passedExpr} AS passed_tests,
                         {$totalExpr} AS total_tests
                  FROM {$this->submissionTable} s
                  WHERE s.user_id = :user_id
                  AND s.problem_id = :problem_id
                  AND s.status = 'accepted'
                  ORDER BY {$runtimeExpr} ASC
                  LIMIT 1";

        return $this->db->fetchOne($query, [
            'user_id' => (int)$userId,
            'problem_id' => (int)$problemId
        ]);
    }

    public function getSubmissionStats($userId) {
        if (!$this->submissionTable) {
            return [
                'total_submissions' => 0,
                'accepted' => 0,
                'wrong_answer' => 0,
                'tle' => 0,
                'runtime_error' => 0,
                'compile_error' => 0,
                'avg_runtime' => 0,
                'avg_memory' => 0
            ];
        }

        $runtimeExpr = $this->getRuntimeSelect('s');
        $memoryExpr = $this->getMemorySelect('s');

        $query = "SELECT
                    COUNT(*) AS total_submissions,
                    COALESCE(SUM(CASE WHEN s.status = 'accepted' THEN 1 ELSE 0 END), 0) AS accepted,
                    COALESCE(SUM(CASE WHEN s.status = 'wrong_answer' THEN 1 ELSE 0 END), 0) AS wrong_answer,
                    COALESCE(SUM(CASE WHEN s.status IN ('time_limit_exceeded', 'time_limit') THEN 1 ELSE 0 END), 0) AS tle,
                    COALESCE(SUM(CASE WHEN s.status = 'runtime_error' THEN 1 ELSE 0 END), 0) AS runtime_error,
                    COALESCE(SUM(CASE WHEN s.status IN ('compilation_error', 'compile_error') THEN 1 ELSE 0 END), 0) AS compile_error,
                    AVG({$runtimeExpr}) AS avg_runtime,
                    AVG({$memoryExpr}) AS avg_memory
                  FROM {$this->submissionTable} s
                  WHERE s.user_id = :user_id";

        $stats = $this->db->fetchOne($query, ['user_id' => (int)$userId]);
        return $stats ?: [];
    }

    public function getRecentSubmissions($userId, $limit = 10) {
        if (!$this->submissionTable) {
            return [];
        }

        $limit = max(1, (int)$limit);
        $runtimeExpr = $this->getRuntimeSelect('s');

        $query = "SELECT s.submission_id,
                         s.problem_id,
                         s.status,
                         {$runtimeExpr} AS runtime,
                         s.submitted_at,
                         p.title,
                         p.difficulty
                  FROM {$this->submissionTable} s
                  JOIN coding_problems p ON s.problem_id = p.problem_id
                  WHERE s.user_id = :user_id
                  ORDER BY s.submitted_at DESC
                  LIMIT {$limit}";

        return $this->db->fetchAll($query, ['user_id' => (int)$userId]);
    }

    private function updateUserStats($userId, $problemId) {
        if (!$this->submissionTable || !$this->columnExists('users', 'problems_solved')) {
            return;
        }

        $query = "SELECT COUNT(*) AS count
                  FROM {$this->submissionTable}
                  WHERE user_id = :user_id
                  AND problem_id = :problem_id
                  AND status = 'accepted'";

        $result = $this->db->fetchOne($query, [
            'user_id' => (int)$userId,
            'problem_id' => (int)$problemId
        ]);

        if ((int)($result['count'] ?? 0) === 1) {
            $this->db->query(
                'UPDATE users SET problems_solved = problems_solved + 1 WHERE user_id = :user_id',
                ['user_id' => (int)$userId]
            );
        }
    }

    public function getDailyActivity($userId) {
        if (!$this->submissionTable) {
            return [];
        }

        $query = "SELECT DATE(submitted_at) AS date, COUNT(*) AS count
                  FROM {$this->submissionTable}
                  WHERE user_id = :user_id
                  AND submitted_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
                  GROUP BY DATE(submitted_at)";

        return $this->db->fetchAll($query, ['user_id' => (int)$userId]);
    }

    public function hasSolved($userId, $problemId) {
        if (!$this->submissionTable) {
            return false;
        }

        $query = "SELECT COUNT(*) AS count
                  FROM {$this->submissionTable}
                  WHERE user_id = :user_id
                  AND problem_id = :problem_id
                  AND status = 'accepted'";

        $result = $this->db->fetchOne($query, [
            'user_id' => (int)$userId,
            'problem_id' => (int)$problemId
        ]);

        return ((int)($result['count'] ?? 0)) > 0;
    }
}

