<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

class CompanyPortal {
    private $db;
    private $tableCache = [];
    private $columnCache = [];
    private $submissionTable;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->submissionTable = $this->resolveSubmissionTable();
        $this->ensureSchema();
    }

    private function tableExists($table) {
        if (array_key_exists($table, $this->tableCache)) {
            return $this->tableCache[$table];
        }

        try {
            $row = $this->db->fetchOne(
                "SELECT COUNT(*) AS cnt
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                 AND table_name = :table_name",
                ['table_name' => $table]
            );
            $this->tableCache[$table] = ((int)($row['cnt'] ?? 0)) > 0;
        } catch (Exception $e) {
            $this->tableCache[$table] = false;
        }

        return $this->tableCache[$table];
    }

    private function columnExists($table, $column) {
        $key = $table . '.' . $column;
        if (array_key_exists($key, $this->columnCache)) {
            return $this->columnCache[$key];
        }

        if (!$this->tableExists($table)) {
            $this->columnCache[$key] = false;
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
            $this->columnCache[$key] = ((int)($row['cnt'] ?? 0)) > 0;
        } catch (Exception $e) {
            $this->columnCache[$key] = false;
        }

        return $this->columnCache[$key];
    }

    private function aptitudeCategorySelect($alias = 't') {
        if ($this->columnExists('aptitude_tests', 'category')) {
            return "{$alias}.category";
        }

        if (
            $this->columnExists('aptitude_tests', 'category_id')
            && $this->tableExists('aptitude_categories')
            && $this->columnExists('aptitude_categories', 'category_name')
        ) {
            return "COALESCE(ac.category_name, 'General')";
        }

        return "'General'";
    }

    private function aptitudeCategoryJoin($alias = 't') {
        if (
            $this->columnExists('aptitude_tests', 'category_id')
            && $this->tableExists('aptitude_categories')
            && $this->columnExists('aptitude_categories', 'category_id')
        ) {
            return " LEFT JOIN aptitude_categories ac ON ac.category_id = {$alias}.category_id";
        }

        return '';
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

    private function normalizeSubmissionStatus($status) {
        $status = strtolower(trim((string)$status));
        if ($status === 'accepted') {
            return 'accepted';
        }
        return $status;
    }

    private function ensureSchema() {
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS company_drives (
                drive_id INT AUTO_INCREMENT PRIMARY KEY,
                company_id INT NOT NULL,
                drive_title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                min_coding_solved INT NOT NULL DEFAULT 0,
                min_aptitude_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                start_date DATE NULL,
                end_date DATE NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_company_drives_company (company_id),
                INDEX idx_company_drives_active (is_active),
                CONSTRAINT fk_company_drives_company
                    FOREIGN KEY (company_id) REFERENCES companies(company_id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS company_drive_coding_problems (
                drive_id INT NOT NULL,
                problem_id INT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (drive_id, problem_id),
                INDEX idx_drive_problem_problem (problem_id),
                CONSTRAINT fk_drive_problem_drive
                    FOREIGN KEY (drive_id) REFERENCES company_drives(drive_id)
                    ON DELETE CASCADE,
                CONSTRAINT fk_drive_problem_problem
                    FOREIGN KEY (problem_id) REFERENCES coding_problems(problem_id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS company_drive_aptitude_tests (
                drive_id INT NOT NULL,
                test_id INT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (drive_id, test_id),
                INDEX idx_drive_test_test (test_id),
                CONSTRAINT fk_drive_test_drive
                    FOREIGN KEY (drive_id) REFERENCES company_drives(drive_id)
                    ON DELETE CASCADE,
                CONSTRAINT fk_drive_test_test
                    FOREIGN KEY (test_id) REFERENCES aptitude_tests(test_id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS company_interview_calls (
                call_id INT AUTO_INCREMENT PRIMARY KEY,
                drive_id INT NOT NULL,
                user_id INT NOT NULL,
                coding_solved INT NOT NULL DEFAULT 0,
                aptitude_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                total_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                status ENUM('pending', 'invited', 'rejected', 'waitlisted', 'selected')
                    NOT NULL DEFAULT 'pending',
                remarks VARCHAR(255) NULL,
                evaluated_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_drive_user (drive_id, user_id),
                INDEX idx_company_calls_user (user_id),
                INDEX idx_company_calls_status (status),
                CONSTRAINT fk_company_calls_drive
                    FOREIGN KEY (drive_id) REFERENCES company_drives(drive_id)
                    ON DELETE CASCADE,
                CONSTRAINT fk_company_calls_user
                    FOREIGN KEY (user_id) REFERENCES users(user_id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    private function normalizeIdList($values) {
        if (!is_array($values)) {
            return [];
        }

        $ids = [];
        foreach ($values as $value) {
            $id = (int)$value;
            if ($id > 0) {
                $ids[$id] = true;
            }
        }

        return array_keys($ids);
    }

    private function buildInClause($values, $prefix, &$params) {
        $placeholders = [];
        foreach (array_values($values) as $index => $value) {
            $key = $prefix . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int)$value;
        }

        return $placeholders ? implode(', ', $placeholders) : '';
    }

    private function userProfileCollegeSelect($alias = 'up') {
        $fields = [];
        if ($this->columnExists('user_profiles', 'college_name')) {
            $fields[] = "{$alias}.college_name";
        }
        if ($this->columnExists('user_profiles', 'college')) {
            $fields[] = "{$alias}.college";
        }

        if (empty($fields)) {
            return "'-'";
        }

        return 'COALESCE(' . implode(', ', $fields) . ", '-')";
    }

    private function getDriveCodingProblemIds($driveId) {
        $rows = $this->db->fetchAll(
            "SELECT problem_id
             FROM company_drive_coding_problems
             WHERE drive_id = :drive_id",
            ['drive_id' => $driveId]
        );

        return array_map('intval', array_column($rows, 'problem_id'));
    }

    private function getDriveAptitudeTestIds($driveId) {
        $rows = $this->db->fetchAll(
            "SELECT test_id
             FROM company_drive_aptitude_tests
             WHERE drive_id = :drive_id",
            ['drive_id' => $driveId]
        );

        return array_map('intval', array_column($rows, 'test_id'));
    }

    private function getUserAcceptedCodingCount($userId, array $problemIds = []) {
        if (!$this->submissionTable) {
            $row = $this->db->fetchOne(
                "SELECT COALESCE(problems_solved, 0) AS solved
                 FROM users
                 WHERE user_id = :user_id",
                ['user_id' => $userId]
            );
            return (int)($row['solved'] ?? 0);
        }

        $params = ['user_id' => (int)$userId];
        $sql = "SELECT COUNT(DISTINCT problem_id) AS solved
                FROM {$this->submissionTable}
                WHERE user_id = :user_id
                AND status = 'accepted'";

        if (!empty($problemIds)) {
            $in = $this->buildInClause($problemIds, 'cp_', $params);
            if ($in !== '') {
                $sql .= " AND problem_id IN ({$in})";
            }
        }

        $row = $this->db->fetchOne($sql, $params);
        return (int)($row['solved'] ?? 0);
    }

    private function getUserAcceptedCodingProblemIds($userId, array $problemIds = []) {
        if (!$this->submissionTable) {
            return [];
        }

        $params = ['user_id' => (int)$userId];
        $sql = "SELECT DISTINCT problem_id
                FROM {$this->submissionTable}
                WHERE user_id = :user_id
                AND status = 'accepted'";

        if (!empty($problemIds)) {
            $in = $this->buildInClause($problemIds, 'ps_', $params);
            if ($in !== '') {
                $sql .= " AND problem_id IN ({$in})";
            }
        }

        $rows = $this->db->fetchAll($sql, $params);
        return array_map('intval', array_column($rows, 'problem_id'));
    }

    private function getUserBestAptitudePercentage($userId, array $testIds = []) {
        $best = 0.0;

        if ($this->tableExists('aptitude_attempts')) {
            $params = ['user_id' => (int)$userId];
            $sql = "SELECT MAX(percentage) AS best_score
                    FROM aptitude_attempts
                    WHERE user_id = :user_id
                    AND percentage IS NOT NULL";

            if ($this->columnExists('aptitude_attempts', 'status')) {
                $sql .= " AND status = 'completed'";
            }

            if (!empty($testIds)) {
                $in = $this->buildInClause($testIds, 'at_', $params);
                if ($in !== '') {
                    $sql .= " AND test_id IN ({$in})";
                }
            }

            $row = $this->db->fetchOne($sql, $params);
            $best = max($best, (float)($row['best_score'] ?? 0));
        }

        if ($this->tableExists('test_attempts')) {
            $params = ['user_id' => (int)$userId];
            $sql = "SELECT MAX(percentage) AS best_score
                    FROM test_attempts
                    WHERE user_id = :user_id
                    AND percentage IS NOT NULL";

            if (!empty($testIds)) {
                $in = $this->buildInClause($testIds, 'tt_', $params);
                if ($in !== '') {
                    $sql .= " AND test_id IN ({$in})";
                }
            }

            $row = $this->db->fetchOne($sql, $params);
            $best = max($best, (float)($row['best_score'] ?? 0));
        }

        return round($best, 2);
    }

    private function getUserBestAptitudeAttemptMap($userId, array $testIds = []) {
        $map = [];
        $userId = (int)$userId;
        if ($userId <= 0 || empty($testIds)) {
            return $map;
        }

        $params = ['user_id' => $userId];
        $in = $this->buildInClause($testIds, 'ua_', $params);
        if ($in === '') {
            return $map;
        }

        if ($this->tableExists('aptitude_attempts')) {
            $sql = "SELECT aa.attempt_id, aa.test_id, aa.score, aa.total_marks, aa.percentage, COALESCE(aa.end_time, aa.start_time) AS attempted_at
                    FROM aptitude_attempts aa
                    WHERE aa.user_id = :user_id
                    AND aa.test_id IN ({$in})";
            if ($this->columnExists('aptitude_attempts', 'status')) {
                $sql .= " AND aa.status = 'completed'";
            }
            $sql .= " ORDER BY aa.percentage DESC, attempted_at DESC";
            $rows = $this->db->fetchAll($sql, $params);
        } elseif ($this->tableExists('test_attempts')) {
            $sql = "SELECT ta.attempt_id, ta.test_id, ta.score, ta.total_questions AS total_marks, ta.percentage, ta.attempted_at
                    FROM test_attempts ta
                    WHERE ta.user_id = :user_id
                    AND ta.test_id IN ({$in})
                    ORDER BY ta.percentage DESC, ta.attempted_at DESC";
            $rows = $this->db->fetchAll($sql, $params);
        } else {
            $rows = [];
        }

        foreach ($rows as $row) {
            $testId = (int)($row['test_id'] ?? 0);
            if ($testId <= 0 || isset($map[$testId])) {
                continue;
            }
            $map[$testId] = [
                'attempt_id' => (int)($row['attempt_id'] ?? 0),
                'score' => (int)($row['score'] ?? 0),
                'total_marks' => (int)($row['total_marks'] ?? 0),
                'percentage' => round((float)($row['percentage'] ?? 0), 2),
                'attempted_at' => (string)($row['attempted_at'] ?? '')
            ];
        }

        return $map;
    }

    public function getCompanies($activeOnly = true) {
        $sql = "SELECT
                    c.*,
                    (
                        SELECT COUNT(*)
                        FROM company_drives d
                        WHERE d.company_id = c.company_id
                        AND d.is_active = 1
                    ) AS active_drives
                FROM companies c
                WHERE 1=1";

        if ($activeOnly) {
            $sql .= " AND c.is_active = 1";
        }

        $sql .= " ORDER BY c.company_name ASC";
        return $this->db->fetchAll($sql);
    }

    public function getCompaniesForUser($userId) {
        $sql = "SELECT
                    c.*,
                    (
                        SELECT COUNT(*)
                        FROM company_drives d
                        WHERE d.company_id = c.company_id
                        AND d.is_active = 1
                    ) AS active_drives,
                    (
                        SELECT COUNT(*)
                        FROM company_interview_calls ic
                        JOIN company_drives d2 ON d2.drive_id = ic.drive_id
                        WHERE d2.company_id = c.company_id
                        AND ic.user_id = :user_id
                        AND ic.status IN ('invited', 'waitlisted', 'selected')
                    ) AS my_shortlists,
                    (
                        SELECT COUNT(*)
                        FROM company_questions cq
                        WHERE cq.company_id = c.company_id
                    ) AS question_count
                FROM companies c
                WHERE c.is_active = 1
                ORDER BY c.company_name ASC";

        return $this->db->fetchAll($sql, ['user_id' => (int)$userId]);
    }

    public function getCompanyById($companyId) {
        return $this->db->fetchOne(
            "SELECT *
             FROM companies
             WHERE company_id = :company_id",
            ['company_id' => (int)$companyId]
        );
    }

    public function getDrives($companyId = null, $activeOnly = false) {
        $sql = "SELECT
                    d.*,
                    c.company_name,
                    (
                        SELECT COUNT(*)
                        FROM company_drive_coding_problems cp
                        WHERE cp.drive_id = d.drive_id
                    ) AS coding_problem_count,
                    (
                        SELECT COUNT(*)
                        FROM company_drive_aptitude_tests ct
                        WHERE ct.drive_id = d.drive_id
                    ) AS aptitude_test_count,
                    (
                        SELECT COUNT(*)
                        FROM company_interview_calls ic
                        WHERE ic.drive_id = d.drive_id
                        AND ic.status IN ('invited', 'waitlisted', 'selected')
                    ) AS shortlisted_count
                FROM company_drives d
                JOIN companies c ON c.company_id = d.company_id
                WHERE 1=1";

        $params = [];

        if ($companyId !== null) {
            $sql .= " AND d.company_id = :company_id";
            $params['company_id'] = (int)$companyId;
        }

        if ($activeOnly) {
            $sql .= " AND d.is_active = 1";
        }

        $sql .= " ORDER BY d.created_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getDriveById($driveId) {
        $drive = $this->db->fetchOne(
            "SELECT d.*, c.company_name
             FROM company_drives d
             JOIN companies c ON c.company_id = d.company_id
             WHERE d.drive_id = :drive_id",
            ['drive_id' => (int)$driveId]
        );

        if (!$drive) {
            return null;
        }

        $drive['coding_problem_ids'] = $this->getDriveCodingProblemIds((int)$driveId);
        $drive['aptitude_test_ids'] = $this->getDriveAptitudeTestIds((int)$driveId);

        $drive['coding_problems'] = [];
        if (!empty($drive['coding_problem_ids'])) {
            $params = [];
            $in = $this->buildInClause($drive['coding_problem_ids'], 'gp_', $params);
            $drive['coding_problems'] = $this->db->fetchAll(
                "SELECT problem_id, title, difficulty
                 FROM coding_problems
                 WHERE problem_id IN ({$in})
                 ORDER BY title ASC",
                $params
            );
        }

        $drive['aptitude_tests'] = [];
        if (!empty($drive['aptitude_test_ids'])) {
            $params = [];
            $in = $this->buildInClause($drive['aptitude_test_ids'], 'gt_', $params);
            $categorySelect = $this->aptitudeCategorySelect('t');
            $categoryJoin = $this->aptitudeCategoryJoin('t');
            $drive['aptitude_tests'] = $this->db->fetchAll(
                "SELECT t.test_id, t.test_name, {$categorySelect} AS category, t.difficulty
                 FROM aptitude_tests t
                 {$categoryJoin}
                 WHERE t.test_id IN ({$in})
                 ORDER BY test_name ASC",
                $params
            );
        }

        return $drive;
    }

    public function getCodingProblemOptions() {
        return $this->db->fetchAll(
            "SELECT problem_id, title, difficulty
             FROM coding_problems
             ORDER BY title ASC"
        );
    }

    public function getAptitudeTestOptions() {
        $categorySelect = $this->aptitudeCategorySelect('t');
        $categoryJoin = $this->aptitudeCategoryJoin('t');
        $sql = "SELECT t.test_id, t.test_name, {$categorySelect} AS category, t.difficulty
                FROM aptitude_tests t
                {$categoryJoin}
                WHERE 1=1";

        if ($this->columnExists('aptitude_tests', 'is_active')) {
            $sql .= " AND t.is_active = 1";
        }

        $sql .= " ORDER BY t.test_name ASC";
        return $this->db->fetchAll($sql);
    }

    public function getAptitudeCategories() {
        if ($this->tableExists('aptitude_categories')) {
            return $this->db->fetchAll(
                "SELECT category_id, category_name
                 FROM aptitude_categories
                 WHERE is_active = 1
                 ORDER BY category_name ASC"
            );
        }

        return [
            ['category_id' => 1, 'category_name' => 'General']
        ];
    }

    public function addCompanyAptitudeTest($companyId, $driveId, $data, $createdBy = null) {
        try {
            $companyId = (int)$companyId;
            if ($companyId <= 0 || !$this->getCompanyById($companyId)) {
                return ['success' => false, 'message' => 'Invalid company selected.'];
            }

            $testName = trim((string)($data['test_name'] ?? ''));
            $questionText = trim((string)($data['question_text'] ?? ''));
            if ($testName === '' || $questionText === '') {
                return ['success' => false, 'message' => 'Test name and one question are required.'];
            }

            $categoryId = max(1, (int)($data['category_id'] ?? 1));
            $difficulty = strtolower(trim((string)($data['difficulty'] ?? 'medium')));
            if (!in_array($difficulty, ['easy', 'medium', 'hard', 'mixed'], true)) {
                $difficulty = 'medium';
            }

            $durationMinutes = max(5, (int)($data['duration_minutes'] ?? 20));
            $questionDifficulty = in_array($difficulty, ['easy', 'medium', 'hard'], true) ? $difficulty : 'medium';
            $marks = max(1, (int)($data['marks'] ?? 1));

            $testPayload = [
                'test_name' => $testName,
                'duration_minutes' => $durationMinutes,
                'total_marks' => $marks,
                'total_questions' => 1,
                'difficulty' => $difficulty,
                'is_active' => 1
            ];
            if ($this->columnExists('aptitude_tests', 'category_id')) {
                $testPayload['category_id'] = $categoryId;
            }
            if ($this->columnExists('aptitude_tests', 'test_description')) {
                $testPayload['test_description'] = trim((string)($data['description'] ?? ''));
            } elseif ($this->columnExists('aptitude_tests', 'description')) {
                $testPayload['description'] = trim((string)($data['description'] ?? ''));
            }
            if ($createdBy !== null && $this->columnExists('aptitude_tests', 'created_by')) {
                $testPayload['created_by'] = (int)$createdBy;
            }

            $this->db->beginTransaction();
            $this->db->insert('aptitude_tests', $testPayload);
            $testId = (int)$this->db->lastInsertId();

            $questionPayload = [
                'category_id' => $categoryId,
                'question_text' => $questionText,
                'option_a' => trim((string)($data['option_a'] ?? '')),
                'option_b' => trim((string)($data['option_b'] ?? '')),
                'option_c' => trim((string)($data['option_c'] ?? '')),
                'option_d' => trim((string)($data['option_d'] ?? '')),
                'correct_answer' => strtolower(trim((string)($data['correct_answer'] ?? 'a'))),
                'explanation' => trim((string)($data['explanation'] ?? '')),
                'difficulty' => $questionDifficulty,
                'marks' => $marks
            ];
            if ($createdBy !== null && $this->columnExists('aptitude_questions', 'created_by')) {
                $questionPayload['created_by'] = (int)$createdBy;
            }

            $this->db->insert('aptitude_questions', $questionPayload);
            $questionId = (int)$this->db->lastInsertId();

            if ($this->tableExists('test_questions')) {
                $this->db->insert('test_questions', [
                    'test_id' => $testId,
                    'question_id' => $questionId,
                    'question_order' => 1
                ]);
            } elseif ($this->columnExists('aptitude_questions', 'test_id')) {
                $this->db->update(
                    'aptitude_questions',
                    ['test_id' => $testId],
                    'question_id = :question_id',
                    ['question_id' => $questionId]
                );
            }

            $driveId = (int)$driveId;
            if ($driveId > 0) {
                $drive = $this->getDriveById($driveId);
                if ($drive && (int)$drive['company_id'] === $companyId) {
                    $this->db->query(
                        "INSERT INTO company_drive_aptitude_tests (drive_id, test_id)
                         VALUES (:drive_id, :test_id)
                         ON DUPLICATE KEY UPDATE test_id = VALUES(test_id)",
                        ['drive_id' => $driveId, 'test_id' => $testId]
                    );
                }
            }

            if ($this->tableExists('company_questions')) {
                $this->db->insert('company_questions', [
                    'company_id' => $companyId,
                    'question_text' => $questionText,
                    'question_type' => 'aptitude',
                    'difficulty' => $questionDifficulty,
                    'year' => (int)date('Y'),
                    'answer' => trim((string)($data['explanation'] ?? ''))
                ]);
            }

            $this->db->commit();
            return [
                'success' => true,
                'message' => 'Company aptitude test created successfully.',
                'test_id' => $testId
            ];
        } catch (Exception $e) {
            if ($this->db->getConnection()->inTransaction()) {
                $this->db->rollback();
            }
            error_log('Add Company Aptitude Test Error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create company aptitude test.'];
        }
    }

    private function generateUniqueProblemSlug($title) {
        $base = strtolower(trim((string)$title));
        $base = preg_replace('/[^a-z0-9]+/', '-', $base);
        $base = trim((string)$base, '-');
        if ($base === '') {
            $base = 'problem';
        }

        $slug = $base;
        $suffix = 1;
        while (true) {
            $existing = $this->db->fetchOne(
                "SELECT problem_id
                 FROM coding_problems
                 WHERE slug = :slug
                 LIMIT 1",
                ['slug' => $slug]
            );
            if (!$existing) {
                return $slug;
            }
            $suffix++;
            $slug = $base . '-' . $suffix;
        }
    }

    public function addCompanyCodingProblem($companyId, $driveId, $data, $createdBy = null) {
        try {
            $companyId = (int)$companyId;
            if ($companyId <= 0 || !$this->getCompanyById($companyId)) {
                return ['success' => false, 'message' => 'Invalid company selected.'];
            }

            $title = trim((string)($data['title'] ?? ''));
            $description = trim((string)($data['description'] ?? ''));
            if ($title === '' || $description === '') {
                return ['success' => false, 'message' => 'Problem title and description are required.'];
            }

            $difficulty = strtolower(trim((string)($data['difficulty'] ?? 'medium')));
            if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
                $difficulty = 'medium';
            }

            $slug = $this->generateUniqueProblemSlug($title);

            $tagsRaw = trim((string)($data['tags'] ?? 'company'));
            $tags = [];
            foreach (explode(',', $tagsRaw) as $tag) {
                $clean = trim($tag);
                if ($clean !== '') {
                    $tags[] = $clean;
                }
            }
            if (empty($tags)) {
                $tags = ['company'];
            }
            $tagsText = implode(',', array_slice(array_unique($tags), 0, 8));

            $timeLimit = max(1, (int)($data['time_limit'] ?? 2));
            $memoryLimit = max(64, (int)($data['memory_limit'] ?? 256));

            $payload = [
                'title' => $title,
                'slug' => $slug,
                'description' => $description,
                'input_format' => trim((string)($data['input_format'] ?? '')),
                'output_format' => trim((string)($data['output_format'] ?? '')),
                'constraints' => trim((string)($data['constraints'] ?? '')),
                'difficulty' => $difficulty,
                'tags' => $tagsText
            ];

            if ($this->columnExists('coding_problems', 'sample_input')) {
                $payload['sample_input'] = trim((string)($data['sample_input'] ?? ''));
            }
            if ($this->columnExists('coding_problems', 'sample_output')) {
                $payload['sample_output'] = trim((string)($data['sample_output'] ?? ''));
            }
            if ($this->columnExists('coding_problems', 'time_limit')) {
                $payload['time_limit'] = $timeLimit;
            }
            if ($this->columnExists('coding_problems', 'memory_limit')) {
                $payload['memory_limit'] = $memoryLimit;
            }
            if ($this->columnExists('coding_problems', 'time_limit_ms')) {
                $payload['time_limit_ms'] = $timeLimit * 1000;
            }
            if ($this->columnExists('coding_problems', 'memory_limit_mb')) {
                $payload['memory_limit_mb'] = $memoryLimit;
            }
            if ($createdBy !== null && $this->columnExists('coding_problems', 'created_by')) {
                $payload['created_by'] = (int)$createdBy;
            }

            $this->db->insert('coding_problems', $payload);
            $problemId = (int)$this->db->lastInsertId();

            if ($this->tableExists('test_cases')) {
                $sampleInput = trim((string)($data['sample_input'] ?? ''));
                $sampleOutput = trim((string)($data['sample_output'] ?? ''));
                if ($sampleInput !== '' && $sampleOutput !== '') {
                    $sampleCasePayload = [
                        'problem_id' => $problemId,
                        'input_data' => $sampleInput,
                        'expected_output' => $sampleOutput,
                        'is_sample' => 1,
                        'testcase_order' => 1,
                    ];
                    if ($this->columnExists('test_cases', 'is_hidden')) {
                        $sampleCasePayload['is_hidden'] = 0;
                    }
                    if ($this->columnExists('test_cases', 'weight')) {
                        $sampleCasePayload['weight'] = 1;
                    }
                    if ($this->columnExists('test_cases', 'time_limit_ms')) {
                        $sampleCasePayload['time_limit_ms'] = $timeLimit * 1000;
                    }
                    if ($this->columnExists('test_cases', 'memory_limit_mb')) {
                        $sampleCasePayload['memory_limit_mb'] = $memoryLimit;
                    }
                    $this->db->insert('test_cases', $sampleCasePayload);

                    $hiddenCasePayload = [
                        'problem_id' => $problemId,
                        'input_data' => $sampleInput,
                        'expected_output' => $sampleOutput,
                        'is_sample' => 0,
                        'testcase_order' => 2,
                    ];
                    if ($this->columnExists('test_cases', 'is_hidden')) {
                        $hiddenCasePayload['is_hidden'] = 1;
                    }
                    if ($this->columnExists('test_cases', 'weight')) {
                        $hiddenCasePayload['weight'] = 1;
                    }
                    if ($this->columnExists('test_cases', 'time_limit_ms')) {
                        $hiddenCasePayload['time_limit_ms'] = $timeLimit * 1000;
                    }
                    if ($this->columnExists('test_cases', 'memory_limit_mb')) {
                        $hiddenCasePayload['memory_limit_mb'] = $memoryLimit;
                    }
                    $this->db->insert('test_cases', $hiddenCasePayload);
                }
            }

            $driveId = (int)$driveId;
            if ($driveId > 0) {
                $drive = $this->getDriveById($driveId);
                if ($drive && (int)$drive['company_id'] === $companyId) {
                    $this->db->query(
                        "INSERT INTO company_drive_coding_problems (drive_id, problem_id)
                         VALUES (:drive_id, :problem_id)
                         ON DUPLICATE KEY UPDATE problem_id = VALUES(problem_id)",
                        ['drive_id' => $driveId, 'problem_id' => $problemId]
                    );
                }
            }

            if ($this->tableExists('company_questions')) {
                $questionText = 'Coding Problem: ' . $title . ' - ' . (function_exists('mb_strimwidth') ? mb_strimwidth($description, 0, 180, '...') : substr($description, 0, 180));
                $this->db->insert('company_questions', [
                    'company_id' => $companyId,
                    'question_text' => $questionText,
                    'question_type' => 'coding',
                    'difficulty' => $difficulty,
                    'year' => (int)date('Y'),
                    'answer' => ''
                ]);
            }

            return [
                'success' => true,
                'message' => 'Company coding problem created successfully.',
                'problem_id' => $problemId
            ];
        } catch (Exception $e) {
            error_log('Add Company Coding Problem Error: ' . $e->getMessage());
            if (strpos((string)$e->getMessage(), 'Duplicate entry') !== false) {
                return ['success' => false, 'message' => 'Problem title already exists. Try a different title.'];
            }
            return ['success' => false, 'message' => 'Failed to create company coding problem.'];
        }
    }

    public function saveDrive($data, $adminUserId, $driveId = 0) {
        try {
            $companyId = (int)($data['company_id'] ?? 0);
            $title = trim((string)($data['drive_title'] ?? ''));

            if ($companyId <= 0) {
                return ['success' => false, 'message' => 'Please select a company.'];
            }
            if ($title === '') {
                return ['success' => false, 'message' => 'Drive title is required.'];
            }

            $company = $this->getCompanyById($companyId);
            if (!$company) {
                return ['success' => false, 'message' => 'Selected company does not exist.'];
            }

            $payload = [
                'company_id' => $companyId,
                'drive_title' => $title,
                'description' => trim((string)($data['description'] ?? '')),
                'min_coding_solved' => max(0, (int)($data['min_coding_solved'] ?? 0)),
                'min_aptitude_percentage' => max(0, min(100, (float)($data['min_aptitude_percentage'] ?? 0))),
                'start_date' => ($data['start_date'] ?? '') !== '' ? $data['start_date'] : null,
                'end_date' => ($data['end_date'] ?? '') !== '' ? $data['end_date'] : null,
                'is_active' => isset($data['is_active']) ? 1 : 0
            ];

            if ($payload['start_date'] && $payload['end_date'] && $payload['start_date'] > $payload['end_date']) {
                return ['success' => false, 'message' => 'End date cannot be before start date.'];
            }

            if ($driveId > 0) {
                $existing = $this->getDriveById($driveId);
                if (!$existing) {
                    return ['success' => false, 'message' => 'Drive not found.'];
                }
                $this->db->update('company_drives', $payload, 'drive_id = :drive_id', ['drive_id' => $driveId]);
                return ['success' => true, 'message' => 'Drive updated successfully.', 'drive_id' => $driveId];
            }

            $payload['created_by'] = (int)$adminUserId;
            $this->db->insert('company_drives', $payload);
            $newId = (int)$this->db->lastInsertId();

            return ['success' => true, 'message' => 'Drive created successfully.', 'drive_id' => $newId];
        } catch (Exception $e) {
            error_log('Save Drive Error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to save drive right now.'];
        }
    }

    public function saveDriveMappings($driveId, $problemIds, $testIds) {
        try {
            $drive = $this->getDriveById($driveId);
            if (!$drive) {
                return ['success' => false, 'message' => 'Drive not found.'];
            }

            $problemIds = $this->normalizeIdList($problemIds);
            $testIds = $this->normalizeIdList($testIds);

            $this->db->beginTransaction();

            $this->db->query(
                "DELETE FROM company_drive_coding_problems WHERE drive_id = :drive_id",
                ['drive_id' => (int)$driveId]
            );
            $this->db->query(
                "DELETE FROM company_drive_aptitude_tests WHERE drive_id = :drive_id",
                ['drive_id' => (int)$driveId]
            );

            $validProblemIds = [];
            if (!empty($problemIds)) {
                $params = [];
                $in = $this->buildInClause($problemIds, 'mp_', $params);
                $rows = $this->db->fetchAll(
                    "SELECT problem_id
                     FROM coding_problems
                     WHERE problem_id IN ({$in})",
                    $params
                );
                $validProblemIds = array_map('intval', array_column($rows, 'problem_id'));
            }

            foreach ($validProblemIds as $problemId) {
                $this->db->insert('company_drive_coding_problems', [
                    'drive_id' => (int)$driveId,
                    'problem_id' => (int)$problemId
                ]);
            }

            $validTestIds = [];
            if (!empty($testIds)) {
                $params = [];
                $in = $this->buildInClause($testIds, 'mt_', $params);
                $rows = $this->db->fetchAll(
                    "SELECT test_id
                     FROM aptitude_tests
                     WHERE test_id IN ({$in})",
                    $params
                );
                $validTestIds = array_map('intval', array_column($rows, 'test_id'));
            }

            foreach ($validTestIds as $testId) {
                $this->db->insert('company_drive_aptitude_tests', [
                    'drive_id' => (int)$driveId,
                    'test_id' => (int)$testId
                ]);
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Drive mappings saved successfully.',
                'coding_count' => count($validProblemIds),
                'aptitude_count' => count($validTestIds)
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            error_log('Save Drive Mappings Error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to save drive mappings.'];
        }
    }

    public function addCompanyQuestion($companyId, $data) {
        try {
            $companyId = (int)$companyId;
            if ($companyId <= 0 || !$this->getCompanyById($companyId)) {
                return ['success' => false, 'message' => 'Invalid company selected.'];
            }

            $questionText = trim((string)($data['question_text'] ?? ''));
            if ($questionText === '') {
                return ['success' => false, 'message' => 'Question text is required.'];
            }

            $questionType = strtolower(trim((string)($data['question_type'] ?? 'aptitude')));
            $allowedTypes = ['aptitude', 'coding', 'technical', 'hr'];
            if (!in_array($questionType, $allowedTypes, true)) {
                $questionType = 'aptitude';
            }

            $difficulty = strtolower(trim((string)($data['difficulty'] ?? 'medium')));
            $allowedDifficulty = ['easy', 'medium', 'hard'];
            if (!in_array($difficulty, $allowedDifficulty, true)) {
                $difficulty = 'medium';
            }

            $year = (int)($data['year'] ?? 0);
            if ($year < 2000 || $year > 2100) {
                $year = (int)date('Y');
            }

            $roundId = (int)($data['round_id'] ?? 0);
            $payload = [
                'company_id' => $companyId,
                'question_text' => $questionText,
                'question_type' => $questionType,
                'difficulty' => $difficulty,
                'year' => $year,
                'answer' => trim((string)($data['answer'] ?? ''))
            ];

            if ($roundId > 0) {
                $payload['round_id'] = $roundId;
            }

            $this->db->insert('company_questions', $payload);
            return ['success' => true, 'message' => 'Company question added successfully.'];
        } catch (Exception $e) {
            error_log('Add Company Question Error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to add company question.'];
        }
    }

    public function getCompanyQuestions($companyId, $questionType = null, $limit = 50) {
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return [];
        }

        $sql = "SELECT q.*, r.round_name
                FROM company_questions q
                LEFT JOIN company_rounds r ON r.round_id = q.round_id
                WHERE q.company_id = :company_id";
        $params = ['company_id' => $companyId];

        if ($questionType !== null && $questionType !== '') {
            $type = strtolower((string)$questionType);
            $allowedTypes = ['aptitude', 'coding', 'technical', 'hr'];
            if (in_array($type, $allowedTypes, true)) {
                $sql .= " AND q.question_type = :question_type";
                $params['question_type'] = $type;
            }
        }

        $limit = max(1, (int)$limit);
        $sql .= " ORDER BY q.year DESC, q.cq_id DESC LIMIT {$limit}";

        return $this->db->fetchAll($sql, $params);
    }

    public function evaluateDriveForUser($driveId, $userId) {
        $drive = $this->getDriveById($driveId);
        if (!$drive) {
            return [
                'eligible' => false,
                'coding_solved' => 0,
                'aptitude_percentage' => 0.0,
                'required_coding' => 0,
                'required_aptitude' => 0.0,
                'total_score' => 0.0,
                'problem_statuses' => [],
                'test_statuses' => []
            ];
        }

        $driveId = (int)$drive['drive_id'];
        $problemIds = $drive['coding_problem_ids'] ?? [];
        $testIds = $drive['aptitude_test_ids'] ?? [];

        $codingSolved = $this->getUserAcceptedCodingCount($userId, $problemIds);
        $acceptedProblemIds = $this->getUserAcceptedCodingProblemIds($userId, $problemIds);
        $aptitudePercentage = $this->getUserBestAptitudePercentage($userId, $testIds);
        $bestAttempts = $this->getUserBestAptitudeAttemptMap($userId, $testIds);

        $requiredCoding = max(0, (int)($drive['min_coding_solved'] ?? 0));
        $requiredAptitude = max(0, (float)($drive['min_aptitude_percentage'] ?? 0));

        $codingTarget = $requiredCoding > 0 ? $requiredCoding : max(1, count($problemIds));
        if ($codingTarget <= 0) {
            $codingTarget = 1;
        }
        $codingScore = min(100, round(($codingSolved / $codingTarget) * 100, 2));
        $totalScore = round(($codingScore * 0.5) + ($aptitudePercentage * 0.5), 2);

        $eligible = ($codingSolved >= $requiredCoding) && ($aptitudePercentage >= $requiredAptitude);

        $problemStatuses = [];
        foreach ($problemIds as $problemId) {
            $problemStatuses[(int)$problemId] = [
                'problem_id' => (int)$problemId,
                'is_solved' => in_array((int)$problemId, $acceptedProblemIds, true)
            ];
        }

        $testStatuses = [];
        foreach ($testIds as $testId) {
            $attempt = $bestAttempts[(int)$testId] ?? null;
            $testStatuses[(int)$testId] = [
                'test_id' => (int)$testId,
                'attempt_id' => (int)($attempt['attempt_id'] ?? 0),
                'percentage' => (float)($attempt['percentage'] ?? 0),
                'score' => (int)($attempt['score'] ?? 0),
                'total_marks' => (int)($attempt['total_marks'] ?? 0),
                'attempted_at' => (string)($attempt['attempted_at'] ?? ''),
                'passed_cutoff' => isset($attempt['percentage']) ? ((float)$attempt['percentage'] >= $requiredAptitude) : false
            ];
        }

        return [
            'eligible' => $eligible,
            'coding_solved' => $codingSolved,
            'aptitude_percentage' => $aptitudePercentage,
            'required_coding' => $requiredCoding,
            'required_aptitude' => $requiredAptitude,
            'total_score' => $totalScore,
            'problem_statuses' => $problemStatuses,
            'test_statuses' => $testStatuses
        ];
    }

    public function generateInterviewCalls($driveId) {
        try {
            $drive = $this->getDriveById($driveId);
            if (!$drive) {
                return ['success' => false, 'message' => 'Drive not found.'];
            }

            $students = $this->db->fetchAll(
                "SELECT user_id
                 FROM users
                 WHERE role_id = 1
                 AND is_active = 1
                 ORDER BY user_id ASC"
            );

            if (empty($students)) {
                return ['success' => false, 'message' => 'No students found to evaluate.'];
            }

            $invited = 0;
            $rejected = 0;

            foreach ($students as $student) {
                $userId = (int)$student['user_id'];
                $evaluation = $this->evaluateDriveForUser((int)$driveId, $userId);
                $status = $evaluation['eligible'] ? 'invited' : 'rejected';

                if ($status === 'invited') {
                    $invited++;
                } else {
                    $rejected++;
                }

                $this->db->query(
                    "INSERT INTO company_interview_calls
                        (drive_id, user_id, coding_solved, aptitude_percentage, total_score, status, remarks, evaluated_at)
                     VALUES
                        (:drive_id, :user_id, :coding_solved, :aptitude_percentage, :total_score, :status, :remarks, NOW())
                     ON DUPLICATE KEY UPDATE
                        coding_solved = VALUES(coding_solved),
                        aptitude_percentage = VALUES(aptitude_percentage),
                        total_score = VALUES(total_score),
                        status = VALUES(status),
                        remarks = VALUES(remarks),
                        evaluated_at = NOW()",
                    [
                        'drive_id' => (int)$driveId,
                        'user_id' => $userId,
                        'coding_solved' => (int)$evaluation['coding_solved'],
                        'aptitude_percentage' => (float)$evaluation['aptitude_percentage'],
                        'total_score' => (float)$evaluation['total_score'],
                        'status' => $status,
                        'remarks' => $status === 'invited'
                            ? 'Auto-generated: eligible for interview call.'
                            : 'Auto-generated: below required cutoff.'
                    ]
                );
            }

            return [
                'success' => true,
                'message' => "Interview calls generated. Invited: {$invited}, Rejected: {$rejected}.",
                'invited' => $invited,
                'rejected' => $rejected
            ];
        } catch (Exception $e) {
            error_log('Generate Interview Calls Error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to generate interview calls.'];
        }
    }

    public function getDriveCalls($driveId) {
        $driveId = (int)$driveId;
        if ($driveId <= 0) {
            return [];
        }

        $collegeSelect = $this->userProfileCollegeSelect('up');

        return $this->db->fetchAll(
            "SELECT
                ic.*,
                u.email,
                COALESCE(up.full_name, u.email) AS full_name,
                {$collegeSelect} AS college_name,
                COALESCE(up.branch, '-') AS branch
             FROM company_interview_calls ic
             JOIN users u ON u.user_id = ic.user_id
             LEFT JOIN user_profiles up ON up.user_id = u.user_id
             WHERE ic.drive_id = :drive_id
             ORDER BY
                FIELD(ic.status, 'selected', 'invited', 'waitlisted', 'pending', 'rejected'),
                ic.total_score DESC,
                ic.updated_at DESC",
            ['drive_id' => $driveId]
        );
    }

    public function getDriveEvaluations($driveId) {
        $driveId = (int)$driveId;
        if ($driveId <= 0) {
            return [];
        }

        $drive = $this->getDriveById($driveId);
        if (!$drive) {
            return [];
        }

        $collegeSelect = $this->userProfileCollegeSelect('up');
        $students = $this->db->fetchAll(
            "SELECT
                u.user_id,
                u.email,
                COALESCE(up.full_name, u.email) AS full_name,
                {$collegeSelect} AS college_name,
                COALESCE(up.branch, '-') AS branch
             FROM users u
             LEFT JOIN user_profiles up ON up.user_id = u.user_id
             WHERE u.role_id = 1
             AND u.is_active = 1
             ORDER BY COALESCE(up.full_name, u.email) ASC"
        );

        $callRows = $this->db->fetchAll(
            "SELECT *
             FROM company_interview_calls
             WHERE drive_id = :drive_id",
            ['drive_id' => $driveId]
        );
        $callMap = [];
        foreach ($callRows as $row) {
            $callMap[(int)$row['user_id']] = $row;
        }

        $rows = [];
        foreach ($students as $student) {
            $userId = (int)$student['user_id'];
            $evaluation = $this->evaluateDriveForUser($driveId, $userId);
            $call = $callMap[$userId] ?? null;
            $rows[] = array_merge($student, [
                'coding_solved' => (int)($evaluation['coding_solved'] ?? 0),
                'required_coding' => (int)($evaluation['required_coding'] ?? 0),
                'aptitude_percentage' => (float)($evaluation['aptitude_percentage'] ?? 0),
                'required_aptitude' => (float)($evaluation['required_aptitude'] ?? 0),
                'total_score' => (float)($evaluation['total_score'] ?? 0),
                'eligible' => !empty($evaluation['eligible']),
                'status' => (string)($call['status'] ?? ($evaluation['eligible'] ? 'eligible' : 'not_eligible')),
                'remarks' => (string)($call['remarks'] ?? ''),
                'evaluated_at' => (string)($call['evaluated_at'] ?? ''),
                'problem_statuses' => $evaluation['problem_statuses'] ?? [],
                'test_statuses' => $evaluation['test_statuses'] ?? []
            ]);
        }

        usort($rows, static function ($a, $b) {
            $eligibilityCompare = ((int)!empty($b['eligible'])) <=> ((int)!empty($a['eligible']));
            if ($eligibilityCompare !== 0) {
                return $eligibilityCompare;
            }

            $scoreCompare = ((float)($b['total_score'] ?? 0)) <=> ((float)($a['total_score'] ?? 0));
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            return strcmp((string)($a['full_name'] ?? ''), (string)($b['full_name'] ?? ''));
        });

        return $rows;
    }

    public function updateInterviewCallStatus($callId, $status, $remarks = '') {
        try {
            $callId = (int)$callId;
            if ($callId <= 0) {
                return ['success' => false, 'message' => 'Invalid interview call selected.'];
            }

            $status = strtolower(trim((string)$status));
            $allowedStatus = ['pending', 'invited', 'rejected', 'waitlisted', 'selected'];
            if (!in_array($status, $allowedStatus, true)) {
                return ['success' => false, 'message' => 'Invalid interview status.'];
            }

            $existing = $this->db->fetchOne(
                "SELECT call_id
                 FROM company_interview_calls
                 WHERE call_id = :call_id",
                ['call_id' => $callId]
            );
            if (!$existing) {
                return ['success' => false, 'message' => 'Interview call record not found.'];
            }

            $this->db->update(
                'company_interview_calls',
                [
                    'status' => $status,
                    'remarks' => trim((string)$remarks)
                ],
                'call_id = :call_id',
                ['call_id' => $callId]
            );

            return ['success' => true, 'message' => 'Interview call status updated.'];
        } catch (Exception $e) {
            error_log('Update Interview Call Status Error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update interview call status.'];
        }
    }

    public function getUserDriveStatusByCompany($companyId, $userId) {
        $companyId = (int)$companyId;
        $userId = (int)$userId;
        if ($companyId <= 0 || $userId <= 0) {
            return [];
        }

        $drives = $this->db->fetchAll(
            "SELECT *
             FROM company_drives
             WHERE company_id = :company_id
             AND is_active = 1
             ORDER BY created_at DESC",
            ['company_id' => $companyId]
        );

        if (empty($drives)) {
            return [];
        }

        $driveIds = array_map(static function ($d) {
            return (int)$d['drive_id'];
        }, $drives);

        $driveParams = [];
        $in = $this->buildInClause($driveIds, 'drive_', $driveParams);
        if ($in === '') {
            return [];
        }

        $callMap = [];
        $callParams = array_merge(['user_id' => $userId], $driveParams);
        $rows = $this->db->fetchAll(
            "SELECT *
             FROM company_interview_calls
             WHERE user_id = :user_id
             AND drive_id IN ({$in})",
            $callParams
        );
        foreach ($rows as $row) {
            $callMap[(int)$row['drive_id']] = $row;
        }

        $codingMap = [];
        $codingRows = $this->db->fetchAll(
            "SELECT cp.drive_id, p.problem_id, p.title, p.difficulty
             FROM company_drive_coding_problems cp
             JOIN coding_problems p ON p.problem_id = cp.problem_id
             WHERE cp.drive_id IN ({$in})
             ORDER BY p.title ASC",
            $driveParams
        );
        foreach ($codingRows as $row) {
            $driveId = (int)$row['drive_id'];
            if (!isset($codingMap[$driveId])) {
                $codingMap[$driveId] = [];
            }
            $codingMap[$driveId][] = $row;
        }

        $testMap = [];
        $categorySelect = $this->aptitudeCategorySelect('t');
        $categoryJoin = $this->aptitudeCategoryJoin('t');
        $testRows = $this->db->fetchAll(
            "SELECT ct.drive_id, t.test_id, t.test_name, {$categorySelect} AS category, t.difficulty
             FROM company_drive_aptitude_tests ct
             JOIN aptitude_tests t ON t.test_id = ct.test_id
             {$categoryJoin}
             WHERE ct.drive_id IN ({$in})
             ORDER BY t.test_name ASC",
            $driveParams
        );
        foreach ($testRows as $row) {
            $driveId = (int)$row['drive_id'];
            if (!isset($testMap[$driveId])) {
                $testMap[$driveId] = [];
            }
            $testMap[$driveId][] = $row;
        }

        foreach ($drives as &$drive) {
            $driveId = (int)$drive['drive_id'];
            $evaluation = $this->evaluateDriveForUser($driveId, $userId);
            $call = $callMap[$driveId] ?? null;
            $problemStatuses = $evaluation['problem_statuses'] ?? [];
            $testStatuses = $evaluation['test_statuses'] ?? [];

            $codingProblems = $codingMap[$driveId] ?? [];
            foreach ($codingProblems as &$problem) {
                $status = $problemStatuses[(int)$problem['problem_id']] ?? null;
                $problem['is_solved'] = !empty($status['is_solved']);
            }
            unset($problem);

            $aptitudeTests = $testMap[$driveId] ?? [];
            foreach ($aptitudeTests as &$test) {
                $status = $testStatuses[(int)$test['test_id']] ?? null;
                $test['attempt_id'] = (int)($status['attempt_id'] ?? 0);
                $test['best_percentage'] = (float)($status['percentage'] ?? 0);
                $test['score'] = (int)($status['score'] ?? 0);
                $test['total_marks'] = (int)($status['total_marks'] ?? 0);
                $test['attempted_at'] = (string)($status['attempted_at'] ?? '');
                $test['passed_cutoff'] = !empty($status['passed_cutoff']);
            }
            unset($test);

            $drive['evaluation'] = $evaluation;
            $drive['coding_problems'] = $codingProblems;
            $drive['aptitude_tests'] = $aptitudeTests;
            $drive['call_status'] = $call['status'] ?? ($evaluation['eligible'] ? 'eligible' : 'not_eligible');
            $drive['call_record'] = $call;
        }

        return $drives;
    }
}
