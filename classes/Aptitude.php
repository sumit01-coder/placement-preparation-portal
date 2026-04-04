<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

class Aptitude {
    private $db;
    private $tableCache = [];
    private $columnCache = [];

    public function __construct() {
        $this->db = Database::getInstance();
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

    private function normalizeCategory($test) {
        return $test['category'] ?? $test['category_label'] ?? $test['category_name'] ?? 'General';
    }

    private function normalizeAnswer($selected, $correct) {
        $selected = trim((string)$selected);
        $correct = trim((string)$correct);
        return ($correct === strtolower($correct)) ? strtolower($selected) : strtoupper($selected);
    }

    private function resolveCategoryId($categoryLabel = null) {
        if (!$this->tableExists('aptitude_categories') || !$this->columnExists('aptitude_categories', 'category_id')) {
            return null;
        }

        $label = strtolower(trim((string)$categoryLabel));
        if ($label !== '') {
            $exact = $this->db->fetchOne(
                "SELECT category_id
                 FROM aptitude_categories
                 WHERE LOWER(category_name) = :name
                 LIMIT 1",
                ['name' => $label]
            );
            if ($exact && isset($exact['category_id'])) {
                return (int)$exact['category_id'];
            }

            $patterns = [];
            if (strpos($label, 'quant') !== false || strpos($label, 'math') !== false || strpos($label, 'numer') !== false) {
                $patterns[] = '%quant%';
            }
            if (strpos($label, 'logic') !== false || strpos($label, 'reason') !== false || strpos($label, 'puzzle') !== false) {
                $patterns[] = '%logic%';
            }
            if (strpos($label, 'verbal') !== false || strpos($label, 'english') !== false || strpos($label, 'grammar') !== false) {
                $patterns[] = '%verbal%';
            }

            foreach ($patterns as $pattern) {
                $row = $this->db->fetchOne(
                    "SELECT category_id
                     FROM aptitude_categories
                     WHERE LOWER(category_name) LIKE :pattern
                     ORDER BY category_id ASC
                     LIMIT 1",
                    ['pattern' => $pattern]
                );
                if ($row && isset($row['category_id'])) {
                    return (int)$row['category_id'];
                }
            }
        }

        $fallback = $this->db->fetchOne(
            "SELECT category_id
             FROM aptitude_categories
             ORDER BY category_id ASC
             LIMIT 1"
        );
        return $fallback && isset($fallback['category_id']) ? (int)$fallback['category_id'] : null;
    }

    private function countQuestionsForTest($testId, $categoryId = null) {
        if ($this->columnExists('aptitude_questions', 'test_id')) {
            $row = $this->db->fetchOne("SELECT COUNT(*) AS cnt FROM aptitude_questions WHERE test_id = :test_id", ['test_id' => $testId]);
            return (int)($row['cnt'] ?? 0);
        }

        if ($this->tableExists('test_questions')) {
            $row = $this->db->fetchOne("SELECT COUNT(*) AS cnt FROM test_questions WHERE test_id = :test_id", ['test_id' => $testId]);
            return (int)($row['cnt'] ?? 0);
        }

        if ($categoryId !== null && $this->columnExists('aptitude_questions', 'category_id')) {
            $row = $this->db->fetchOne("SELECT COUNT(*) AS cnt FROM aptitude_questions WHERE category_id = :category_id", ['category_id' => $categoryId]);
            return (int)($row['cnt'] ?? 0);
        }

        return 0;
    }

    private function syncTotalQuestions($testId) {
        if (!$this->columnExists('aptitude_tests', 'total_questions')) {
            return;
        }

        $test = $this->db->fetchOne("SELECT test_id, category_id FROM aptitude_tests WHERE test_id = :test_id", ['test_id' => $testId]);
        if (!$test) {
            return;
        }

        $count = $this->countQuestionsForTest($testId, $test['category_id'] ?? null);
        $this->db->update('aptitude_tests', ['total_questions' => $count], 'test_id = :test_id', ['test_id' => $testId]);
    }

    public function getTests($categoryId = null, $difficulty = null, $limit = null, $offset = 0) {
        $hasCategoryText = $this->columnExists('aptitude_tests', 'category');
        $hasCategoryId = $this->columnExists('aptitude_tests', 'category_id');
        $hasDifficulty = $this->columnExists('aptitude_tests', 'difficulty');
        $hasActive = $this->columnExists('aptitude_tests', 'is_active');
        $joinCategories = !$hasCategoryText && $hasCategoryId && $this->tableExists('aptitude_categories');

        $categoryExpr = $hasCategoryText ? "t.category" : ($joinCategories ? "c.category_name" : "'General'");
        $descriptionExpr = $this->columnExists('aptitude_tests', 'description')
            ? "t.description"
            : ($this->columnExists('aptitude_tests', 'test_description') ? "t.test_description" : "''");

        if ($this->columnExists('aptitude_questions', 'test_id')) {
            $countExpr = "(SELECT COUNT(*) FROM aptitude_questions aq WHERE aq.test_id = t.test_id)";
        } elseif ($this->tableExists('test_questions')) {
            $countExpr = "(SELECT COUNT(*) FROM test_questions tq WHERE tq.test_id = t.test_id)";
        } elseif ($hasCategoryId && $this->columnExists('aptitude_questions', 'category_id')) {
            $countExpr = "(SELECT COUNT(*) FROM aptitude_questions aq WHERE aq.category_id = t.category_id)";
        } else {
            $countExpr = "COALESCE(t.total_questions, 0)";
        }

        $sql = "SELECT t.*, {$categoryExpr} AS category_label, {$descriptionExpr} AS description, {$countExpr} AS question_count FROM aptitude_tests t";
        if ($joinCategories) {
            $sql .= " LEFT JOIN aptitude_categories c ON t.category_id = c.category_id";
        }
        $sql .= " WHERE 1=1";

        $params = [];
        if ($hasActive) {
            $sql .= " AND t.is_active = 1";
        }
        if ($categoryId !== null) {
            if ($hasCategoryId && is_numeric($categoryId)) {
                $sql .= " AND t.category_id = :category_id";
                $params['category_id'] = (int)$categoryId;
            } else {
                $sql .= " AND LOWER({$categoryExpr}) = :category_text";
                $params['category_text'] = strtolower((string)$categoryId);
            }
        }
        if ($difficulty && $hasDifficulty) {
            $sql .= " AND LOWER(t.difficulty) = :difficulty";
            $params['difficulty'] = strtolower((string)$difficulty);
        }
        $sql .= " ORDER BY t.created_at DESC";
        if ($limit) {
            $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        }

        $tests = $this->db->fetchAll($sql, $params);
        foreach ($tests as &$test) {
            $test['category'] = $this->normalizeCategory($test);
            $test['question_count'] = (int)($test['question_count'] ?? 0);
            if (!isset($test['difficulty']) || $test['difficulty'] === null || $test['difficulty'] === '') {
                $test['difficulty'] = 'Medium';
            }
        }

        return $tests;
    }

    public function getQuestionsForTest($testId) {
        $test = $this->db->fetchOne("SELECT test_id, category_id, total_questions FROM aptitude_tests WHERE test_id = :test_id", ['test_id' => $testId]);
        if (!$test) {
            return [];
        }

        $marksSelect = $this->columnExists('aptitude_questions', 'marks') ? "aq.marks" : "1 AS marks";
        $diffSelect = $this->columnExists('aptitude_questions', 'difficulty') ? "aq.difficulty" : "'Medium' AS difficulty";

        if ($this->columnExists('aptitude_questions', 'test_id')) {
            $sql = "SELECT aq.question_id, aq.question_text, aq.option_a, aq.option_b, aq.option_c, aq.option_d, aq.correct_answer, aq.explanation, {$diffSelect}, {$marksSelect}
                    FROM aptitude_questions aq
                    WHERE aq.test_id = :test_id
                    ORDER BY aq.question_id ASC";
            $questions = $this->db->fetchAll($sql, ['test_id' => $testId]);
        } elseif ($this->tableExists('test_questions')) {
            $orderExpr = $this->columnExists('test_questions', 'question_order') ? "tq.question_order ASC, " : "";
            $sql = "SELECT aq.question_id, aq.question_text, aq.option_a, aq.option_b, aq.option_c, aq.option_d, aq.correct_answer, aq.explanation, {$diffSelect}, {$marksSelect}
                    FROM test_questions tq
                    JOIN aptitude_questions aq ON tq.question_id = aq.question_id
                    WHERE tq.test_id = :test_id
                    ORDER BY {$orderExpr}aq.question_id ASC";
            $questions = $this->db->fetchAll($sql, ['test_id' => $testId]);
        } elseif ($this->columnExists('aptitude_questions', 'category_id') && isset($test['category_id'])) {
            $limit = max(1, (int)($test['total_questions'] ?? 20));
            $sql = "SELECT aq.question_id, aq.question_text, aq.option_a, aq.option_b, aq.option_c, aq.option_d, aq.correct_answer, aq.explanation, {$diffSelect}, {$marksSelect}
                    FROM aptitude_questions aq
                    WHERE aq.category_id = :category_id
                    ORDER BY aq.question_id ASC
                    LIMIT {$limit}";
            $questions = $this->db->fetchAll($sql, ['category_id' => $test['category_id']]);
        } else {
            $questions = [];
        }

        foreach ($questions as &$q) {
            $q['correct_answer'] = strtoupper((string)$q['correct_answer']);
            $q['marks'] = (int)($q['marks'] ?? 1);
            $q['difficulty'] = $q['difficulty'] ?? 'Medium';
        }

        return $questions;
    }

    public function getTestDetails($testId) {
        $hasCategoryText = $this->columnExists('aptitude_tests', 'category');
        $hasCategoryId = $this->columnExists('aptitude_tests', 'category_id');
        $joinCategories = !$hasCategoryText && $hasCategoryId && $this->tableExists('aptitude_categories');
        $categoryExpr = $hasCategoryText ? "t.category" : ($joinCategories ? "c.category_name" : "'General'");
        $descriptionExpr = $this->columnExists('aptitude_tests', 'description')
            ? "t.description"
            : ($this->columnExists('aptitude_tests', 'test_description') ? "t.test_description" : "''");

        $sql = "SELECT t.*, {$categoryExpr} AS category_label, {$descriptionExpr} AS description FROM aptitude_tests t";
        if ($joinCategories) {
            $sql .= " LEFT JOIN aptitude_categories c ON t.category_id = c.category_id";
        }
        $sql .= " WHERE t.test_id = :test_id";

        $test = $this->db->fetchOne($sql, ['test_id' => $testId]);
        if (!$test) {
            return null;
        }

        $test['category'] = $this->normalizeCategory($test);
        $test['questions'] = $this->getQuestionsForTest($testId);
        if ((!isset($test['total_questions']) || (int)$test['total_questions'] <= 0) && !empty($test['questions'])) {
            $test['total_questions'] = count($test['questions']);
        }

        return $test;
    }

    public function startAttempt($userId, $testId) {
        try {
            $test = $this->getTestDetails($testId);
            if (!$test) {
                return ['success' => false, 'message' => 'Test not found'];
            }

            $totalQuestions = count($test['questions'] ?? []);
            if ($totalQuestions <= 0) {
                $totalQuestions = max(1, (int)($test['total_questions'] ?? 1));
            }

            $totalMarks = 0;
            foreach ($test['questions'] ?? [] as $q) {
                $totalMarks += (int)($q['marks'] ?? 1);
            }
            if ($totalMarks <= 0) {
                $totalMarks = $totalQuestions;
            }

            if ($this->tableExists('aptitude_attempts')) {
                $payload = ['user_id' => $userId, 'test_id' => $testId];
                if ($this->columnExists('aptitude_attempts', 'total_marks')) {
                    $payload['total_marks'] = $totalMarks;
                }
                if ($this->columnExists('aptitude_attempts', 'status')) {
                    $payload['status'] = 'in_progress';
                }
                $this->db->insert('aptitude_attempts', $payload);
            } elseif ($this->tableExists('test_attempts')) {
                $payload = ['user_id' => $userId, 'test_id' => $testId];
                if ($this->columnExists('test_attempts', 'score')) {
                    $payload['score'] = 0;
                }
                if ($this->columnExists('test_attempts', 'total_questions')) {
                    $payload['total_questions'] = $totalQuestions;
                }
                if ($this->columnExists('test_attempts', 'time_taken')) {
                    $payload['time_taken'] = 0;
                }
                if ($this->columnExists('test_attempts', 'percentage')) {
                    $payload['percentage'] = 0;
                }
                $this->db->insert('test_attempts', $payload);
            } else {
                return ['success' => false, 'message' => 'No supported attempt table found'];
            }

            return ['success' => true, 'attempt_id' => $this->db->lastInsertId(), 'message' => 'Test started successfully'];
        } catch (Exception $e) {
            error_log("Start Attempt Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to start test'];
        }
    }

    public function submitAnswer($attemptId, $questionId, $selectedAnswer) {
        try {
            $question = $this->db->fetchOne("SELECT correct_answer FROM aptitude_questions WHERE question_id = :question_id", ['question_id' => $questionId]);
            if (!$question) {
                return ['success' => false, 'message' => 'Question not found'];
            }

            $answer = $this->normalizeAnswer($selectedAnswer, $question['correct_answer']);
            $isCorrect = strcasecmp($answer, (string)$question['correct_answer']) === 0;

            if ($this->tableExists('aptitude_answers')) {
                $table = 'aptitude_answers';
            } elseif ($this->tableExists('test_answers')) {
                $table = 'test_answers';
            } else {
                return ['success' => false, 'message' => 'No supported answers table found'];
            }

            $existing = $this->db->fetchOne(
                "SELECT answer_id FROM {$table} WHERE attempt_id = :attempt_id AND question_id = :question_id",
                ['attempt_id' => $attemptId, 'question_id' => $questionId]
            );

            if ($existing) {
                $this->db->update(
                    $table,
                    ['selected_answer' => $answer, 'is_correct' => $isCorrect],
                    'answer_id = :answer_id',
                    ['answer_id' => $existing['answer_id']]
                );
            } else {
                $this->db->insert($table, [
                    'attempt_id' => $attemptId,
                    'question_id' => $questionId,
                    'selected_answer' => $answer,
                    'is_correct' => $isCorrect
                ]);
            }

            return ['success' => true, 'is_correct' => $isCorrect];
        } catch (Exception $e) {
            error_log("Submit Answer Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to submit answer'];
        }
    }

    public function completeAttempt($attemptId) {
        try {
            if ($this->tableExists('aptitude_attempts')) {
                $attempt = $this->db->fetchOne(
                    "SELECT user_id, test_id, start_time, total_marks
                     FROM aptitude_attempts
                     WHERE attempt_id = :attempt_id",
                    ['attempt_id' => $attemptId]
                );
                if (!$attempt) {
                    throw new Exception('Attempt not found');
                }

                $marksExpr = $this->columnExists('aptitude_questions', 'marks') ? 'q.marks' : '1';
                $scoreRow = $this->db->fetchOne(
                    "SELECT
                        COUNT(*) AS total_answered,
                        COALESCE(SUM(CASE WHEN aa.is_correct = 1 THEN {$marksExpr} ELSE 0 END), 0) AS score
                     FROM aptitude_answers aa
                     JOIN aptitude_questions q ON aa.question_id = q.question_id
                     WHERE aa.attempt_id = :attempt_id",
                    ['attempt_id' => $attemptId]
                );

                $score = (int)($scoreRow['score'] ?? 0);
                $total = (int)($attempt['total_marks'] ?? 0);
                if ($total <= 0) {
                    $total = max(1, (int)($scoreRow['total_answered'] ?? 0));
                }
                $percentage = round(($score / $total) * 100, 2);
                $duration = max(0, time() - (new DateTime($attempt['start_time']))->getTimestamp());

                $update = ['score' => $score, 'percentage' => $percentage];
                if ($this->columnExists('aptitude_attempts', 'end_time')) {
                    $update['end_time'] = date('Y-m-d H:i:s');
                }
                if ($this->columnExists('aptitude_attempts', 'duration_seconds')) {
                    $update['duration_seconds'] = $duration;
                }
                if ($this->columnExists('aptitude_attempts', 'status')) {
                    $update['status'] = 'completed';
                }

                $this->db->update('aptitude_attempts', $update, 'attempt_id = :attempt_id', ['attempt_id' => $attemptId]);
                return ['success' => true, 'score' => $score, 'total_marks' => $total, 'percentage' => $percentage, 'duration' => $duration];
            }

            if ($this->tableExists('test_attempts')) {
                $attempt = $this->db->fetchOne(
                    "SELECT ta.*, at.total_questions AS configured_total
                     FROM test_attempts ta
                     LEFT JOIN aptitude_tests at ON ta.test_id = at.test_id
                     WHERE ta.attempt_id = :attempt_id",
                    ['attempt_id' => $attemptId]
                );
                if (!$attempt) {
                    throw new Exception('Attempt not found');
                }

                $scoreRow = $this->db->fetchOne(
                    "SELECT
                        COUNT(*) AS total_answered,
                        COALESCE(SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END), 0) AS score
                     FROM test_answers
                     WHERE attempt_id = :attempt_id",
                    ['attempt_id' => $attemptId]
                );

                $score = (int)($scoreRow['score'] ?? 0);
                $total = (int)($attempt['total_questions'] ?? 0);
                if ($total <= 0) {
                    $total = (int)($attempt['configured_total'] ?? 0);
                }
                if ($total <= 0) {
                    $total = max(1, (int)($scoreRow['total_answered'] ?? 0));
                }
                $percentage = round(($score / $total) * 100, 2);
                $duration = !empty($attempt['attempted_at']) ? max(0, time() - (new DateTime($attempt['attempted_at']))->getTimestamp()) : 0;

                $update = ['score' => $score, 'total_questions' => $total, 'percentage' => $percentage];
                if ($this->columnExists('test_attempts', 'time_taken')) {
                    $update['time_taken'] = (int)round($duration / 60);
                }
                $this->db->update('test_attempts', $update, 'attempt_id = :attempt_id', ['attempt_id' => $attemptId]);

                return ['success' => true, 'score' => $score, 'total_marks' => $total, 'percentage' => $percentage, 'duration' => $duration];
            }

            return ['success' => false, 'message' => 'No supported attempt table found'];
        } catch (Exception $e) {
            error_log("Complete Attempt Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to complete test'];
        }
    }

    public function getAttemptResult($attemptId) {
        $result = null;

        if ($this->tableExists('aptitude_attempts')) {
            $hasCategoryText = $this->columnExists('aptitude_tests', 'category');
            $joinCategories = !$hasCategoryText && $this->columnExists('aptitude_tests', 'category_id') && $this->tableExists('aptitude_categories');
            $categoryExpr = $hasCategoryText ? "at.category" : ($joinCategories ? "c.category_name" : "'General'");

            $sql = "SELECT aa.*, at.test_name, {$categoryExpr} AS category_name
                    FROM aptitude_attempts aa
                    JOIN aptitude_tests at ON aa.test_id = at.test_id";
            if ($joinCategories) {
                $sql .= " LEFT JOIN aptitude_categories c ON at.category_id = c.category_id";
            }
            $sql .= " WHERE aa.attempt_id = :attempt_id";

            $result = $this->db->fetchOne($sql, ['attempt_id' => $attemptId]);
            if ($result) {
                $marksSelect = $this->columnExists('aptitude_questions', 'marks') ? "q.marks" : "1 AS marks";
                $result['answers'] = $this->db->fetchAll(
                    "SELECT ans.*, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_answer, q.explanation, {$marksSelect}
                     FROM aptitude_answers ans
                     JOIN aptitude_questions q ON ans.question_id = q.question_id
                     WHERE ans.attempt_id = :attempt_id
                     ORDER BY ans.answer_id ASC",
                    ['attempt_id' => $attemptId]
                );
                $result['total_marks'] = (int)($result['total_marks'] ?? 0);
                if ($result['total_marks'] <= 0) {
                    $result['total_marks'] = max(1, (int)($result['score'] ?? 0));
                }
                $result['duration_seconds'] = (int)($result['duration_seconds'] ?? 0);
            }
        }

        if (!$result && $this->tableExists('test_attempts')) {
            $hasCategoryText = $this->columnExists('aptitude_tests', 'category');
            $joinCategories = !$hasCategoryText && $this->columnExists('aptitude_tests', 'category_id') && $this->tableExists('aptitude_categories');
            $categoryExpr = $hasCategoryText ? "at.category" : ($joinCategories ? "c.category_name" : "'General'");

            $sql = "SELECT ta.*, at.test_name, {$categoryExpr} AS category_name
                    FROM test_attempts ta
                    JOIN aptitude_tests at ON ta.test_id = at.test_id";
            if ($joinCategories) {
                $sql .= " LEFT JOIN aptitude_categories c ON at.category_id = c.category_id";
            }
            $sql .= " WHERE ta.attempt_id = :attempt_id";

            $result = $this->db->fetchOne($sql, ['attempt_id' => $attemptId]);
            if ($result) {
                $result['answers'] = $this->db->fetchAll(
                    "SELECT ans.*, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_answer, q.explanation, 1 AS marks
                     FROM test_answers ans
                     JOIN aptitude_questions q ON ans.question_id = q.question_id
                     WHERE ans.attempt_id = :attempt_id
                     ORDER BY ans.answer_id ASC",
                    ['attempt_id' => $attemptId]
                );
                $result['total_marks'] = (int)($result['total_questions'] ?? 0);
                $result['duration_seconds'] = (int)($result['time_taken'] ?? 0) * 60;
                if (!isset($result['percentage']) || $result['percentage'] === null) {
                    $total = max(1, (int)($result['total_questions'] ?? 1));
                    $result['percentage'] = round((((int)($result['score'] ?? 0)) / $total) * 100, 2);
                }
            }
        }

        if ($result && isset($result['answers'])) {
            foreach ($result['answers'] as &$ans) {
                $ans['correct_answer'] = strtoupper((string)($ans['correct_answer'] ?? ''));
                if (isset($ans['selected_answer']) && $ans['selected_answer'] !== null) {
                    $ans['selected_answer'] = strtoupper((string)$ans['selected_answer']);
                }
            }
        }

        return $result;
    }

    public function getUserHistory($userId, $limit = 10) {
        $limit = (int)$limit;
        $hasCategoryText = $this->columnExists('aptitude_tests', 'category');
        $joinCategories = !$hasCategoryText && $this->columnExists('aptitude_tests', 'category_id') && $this->tableExists('aptitude_categories');
        $categoryExpr = $hasCategoryText ? "at.category" : ($joinCategories ? "c.category_name" : "'General'");

        if ($this->tableExists('aptitude_attempts')) {
            $sql = "SELECT aa.*, at.test_name, {$categoryExpr} AS category_name
                    FROM aptitude_attempts aa
                    JOIN aptitude_tests at ON aa.test_id = at.test_id";
            if ($joinCategories) {
                $sql .= " LEFT JOIN aptitude_categories c ON at.category_id = c.category_id";
            }
            $sql .= " WHERE aa.user_id = :user_id";
            if ($this->columnExists('aptitude_attempts', 'status')) {
                $sql .= " AND aa.status = 'completed'";
            }
            $sql .= $this->columnExists('aptitude_attempts', 'end_time') ? " ORDER BY aa.end_time DESC" : " ORDER BY aa.start_time DESC";
            $sql .= " LIMIT {$limit}";
            $rows = $this->db->fetchAll($sql, ['user_id' => $userId]);
        } elseif ($this->tableExists('test_attempts')) {
            $sql = "SELECT ta.*, at.test_name, {$categoryExpr} AS category_name
                    FROM test_attempts ta
                    JOIN aptitude_tests at ON ta.test_id = at.test_id";
            if ($joinCategories) {
                $sql .= " LEFT JOIN aptitude_categories c ON at.category_id = c.category_id";
            }
            $sql .= " WHERE ta.user_id = :user_id ORDER BY ta.attempted_at DESC LIMIT {$limit}";
            $rows = $this->db->fetchAll($sql, ['user_id' => $userId]);
        } else {
            $rows = [];
        }

        foreach ($rows as &$row) {
            $row['category'] = $row['category_name'] ?? $this->normalizeCategory($row);
            $row['attempted_at'] = $row['attempted_at'] ?? $row['end_time'] ?? $row['start_time'] ?? null;
            if (!isset($row['time_taken']) && isset($row['duration_seconds'])) {
                $row['time_taken'] = (int)round(((int)$row['duration_seconds']) / 60);
            }
            if ((!isset($row['total_questions']) || (int)$row['total_questions'] <= 0) && isset($row['total_marks'])) {
                $row['total_questions'] = (int)$row['total_marks'];
            }
        }

        return $rows;
    }

    public function getLatestCompletedAttemptIdForTest($userId, $testId): ?int {
        $userId = (int)$userId;
        $testId = (int)$testId;
        if ($userId <= 0 || $testId <= 0) {
            return null;
        }

        if ($this->tableExists('aptitude_attempts')) {
            $sql = "SELECT attempt_id
                    FROM aptitude_attempts
                    WHERE user_id = :user_id AND test_id = :test_id";
            if ($this->columnExists('aptitude_attempts', 'status')) {
                $sql .= " AND status = 'completed'";
            }
            $sql .= $this->columnExists('aptitude_attempts', 'end_time')
                ? " ORDER BY end_time DESC, attempt_id DESC"
                : " ORDER BY start_time DESC, attempt_id DESC";
            $sql .= " LIMIT 1";

            $row = $this->db->fetchOne($sql, ['user_id' => $userId, 'test_id' => $testId]);
            return $row && isset($row['attempt_id']) ? (int)$row['attempt_id'] : null;
        }

        if ($this->tableExists('test_attempts')) {
            $row = $this->db->fetchOne(
                "SELECT attempt_id
                 FROM test_attempts
                 WHERE user_id = :user_id AND test_id = :test_id
                 ORDER BY attempted_at DESC, attempt_id DESC
                 LIMIT 1",
                ['user_id' => $userId, 'test_id' => $testId]
            );
            return $row && isset($row['attempt_id']) ? (int)$row['attempt_id'] : null;
        }

        return null;
    }

    public function getUserCompletedAttemptsByTest($userId): array {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return [];
        }

        $byTest = [];

        if ($this->tableExists('aptitude_attempts')) {
            $sql = "SELECT attempt_id, test_id, score, total_marks, percentage, duration_seconds, start_time, end_time
                    FROM aptitude_attempts
                    WHERE user_id = :user_id";
            if ($this->columnExists('aptitude_attempts', 'status')) {
                $sql .= " AND status = 'completed'";
            }
            $sql .= $this->columnExists('aptitude_attempts', 'end_time')
                ? " ORDER BY end_time DESC, attempt_id DESC"
                : " ORDER BY start_time DESC, attempt_id DESC";

            $rows = $this->db->fetchAll($sql, ['user_id' => $userId]);
            foreach ($rows as $row) {
                $testId = (int)($row['test_id'] ?? 0);
                if ($testId <= 0 || isset($byTest[$testId])) {
                    continue;
                }
                $byTest[$testId] = $row;
            }

            return $byTest;
        }

        if ($this->tableExists('test_attempts')) {
            $rows = $this->db->fetchAll(
                "SELECT attempt_id, test_id, score, total_questions, percentage, time_taken, attempted_at
                 FROM test_attempts
                 WHERE user_id = :user_id
                 ORDER BY attempted_at DESC, attempt_id DESC",
                ['user_id' => $userId]
            );
            foreach ($rows as $row) {
                $testId = (int)($row['test_id'] ?? 0);
                if ($testId <= 0 || isset($byTest[$testId])) {
                    continue;
                }
                $row['total_marks'] = $row['total_questions'] ?? null;
                $row['duration_seconds'] = isset($row['time_taken']) ? ((int)$row['time_taken'] * 60) : null;
                $byTest[$testId] = $row;
            }
        }

        return $byTest;
    }

    public function getCategories() {
        if ($this->tableExists('aptitude_categories')) {
            return $this->db->fetchAll(
                "SELECT c.*, COUNT(t.test_id) AS test_count
                 FROM aptitude_categories c
                 LEFT JOIN aptitude_tests t ON c.category_id = t.category_id
                 WHERE c.is_active = 1
                 GROUP BY c.category_id"
            );
        }

        return [
            ['category_id' => 1, 'category_name' => 'Quantitative', 'test_count' => 0],
            ['category_id' => 2, 'category_name' => 'Logical', 'test_count' => 0],
            ['category_id' => 3, 'category_name' => 'Verbal', 'test_count' => 0]
        ];
    }

    public function createTest($data) {
        try {
            $name = trim((string)($data['test_name'] ?? ''));
            if ($name === '') {
                return ['success' => false, 'message' => 'Test name is required'];
            }

            $insert = ['test_name' => $name];
            $categoryLabel = trim((string)($data['category'] ?? 'Quantitative'));
            if ($this->columnExists('aptitude_tests', 'category')) {
                $insert['category'] = $categoryLabel;
            }
            if ($this->columnExists('aptitude_tests', 'category_id')) {
                $resolvedCategoryId = null;
                if (isset($data['category_id']) && is_numeric($data['category_id']) && (int)$data['category_id'] > 0) {
                    $resolvedCategoryId = (int)$data['category_id'];
                } else {
                    $resolvedCategoryId = $this->resolveCategoryId($categoryLabel);
                }
                if ($resolvedCategoryId !== null && $resolvedCategoryId > 0) {
                    $insert['category_id'] = $resolvedCategoryId;
                }
            }
            if ($this->columnExists('aptitude_tests', 'difficulty')) {
                $insert['difficulty'] = trim((string)($data['difficulty'] ?? 'Medium'));
            }
            if ($this->columnExists('aptitude_tests', 'duration_minutes')) {
                $insert['duration_minutes'] = max(1, (int)($data['duration_minutes'] ?? 30));
            }
            if ($this->columnExists('aptitude_tests', 'total_questions')) {
                $insert['total_questions'] = max(1, (int)($data['total_questions'] ?? 10));
            }
            if ($this->columnExists('aptitude_tests', 'total_marks')) {
                $insert['total_marks'] = max(1, (int)($data['total_marks'] ?? ($insert['total_questions'] ?? 10)));
            }
            if ($this->columnExists('aptitude_tests', 'passing_score') && isset($data['passing_score'])) {
                $insert['passing_score'] = max(0, (int)$data['passing_score']);
            }
            if ($this->columnExists('aptitude_tests', 'description')) {
                $insert['description'] = trim((string)($data['description'] ?? ''));
            } elseif ($this->columnExists('aptitude_tests', 'test_description')) {
                $insert['test_description'] = trim((string)($data['description'] ?? ''));
            }

            $this->db->insert('aptitude_tests', $insert);
            return ['success' => true, 'message' => 'Test created successfully', 'test_id' => (int)$this->db->lastInsertId()];
        } catch (Exception $e) {
            error_log("Create Test Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create test'];
        }
    }

    public function updateTest($testId, $data) {
        try {
            $test = $this->db->fetchOne("SELECT test_id FROM aptitude_tests WHERE test_id = :test_id", ['test_id' => $testId]);
            if (!$test) {
                return ['success' => false, 'message' => 'Test not found'];
            }

            $update = [];
            if (isset($data['test_name'])) {
                $name = trim((string)$data['test_name']);
                if ($name === '') {
                    return ['success' => false, 'message' => 'Test name is required'];
                }
                $update['test_name'] = $name;
            }
            if ($this->columnExists('aptitude_tests', 'category') && isset($data['category'])) {
                $update['category'] = trim((string)$data['category']);
            }
            if ($this->columnExists('aptitude_tests', 'category_id')) {
                if (isset($data['category_id']) && is_numeric($data['category_id']) && (int)$data['category_id'] > 0) {
                    $update['category_id'] = (int)$data['category_id'];
                } elseif (isset($data['category'])) {
                    $resolvedCategoryId = $this->resolveCategoryId((string)$data['category']);
                    if ($resolvedCategoryId !== null && $resolvedCategoryId > 0) {
                        $update['category_id'] = $resolvedCategoryId;
                    }
                }
            }
            foreach (['difficulty', 'duration_minutes', 'total_questions', 'total_marks', 'passing_score'] as $field) {
                if ($this->columnExists('aptitude_tests', $field) && isset($data[$field])) {
                    if (in_array($field, ['duration_minutes', 'total_questions', 'total_marks', 'passing_score'], true)) {
                        $update[$field] = max(0, (int)$data[$field]);
                    } else {
                        $update[$field] = trim((string)$data[$field]);
                    }
                }
            }
            if ($this->columnExists('aptitude_tests', 'description') && isset($data['description'])) {
                $update['description'] = trim((string)$data['description']);
            } elseif ($this->columnExists('aptitude_tests', 'test_description') && isset($data['description'])) {
                $update['test_description'] = trim((string)$data['description']);
            }

            if (empty($update)) {
                return ['success' => false, 'message' => 'No valid fields to update'];
            }

            $this->db->update('aptitude_tests', $update, 'test_id = :test_id', ['test_id' => $testId]);
            $this->syncTotalQuestions($testId);
            return ['success' => true, 'message' => 'Test updated successfully'];
        } catch (Exception $e) {
            error_log("Update Test Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update test'];
        }
    }

    public function addQuestion($testId, $data) {
        try {
            $test = $this->db->fetchOne("SELECT * FROM aptitude_tests WHERE test_id = :test_id", ['test_id' => $testId]);
            if (!$test) {
                return ['success' => false, 'message' => 'Test not found'];
            }

            $text = trim((string)($data['question_text'] ?? ''));
            if ($text === '') {
                return ['success' => false, 'message' => 'Question text is required'];
            }

            $correctRaw = strtoupper(trim((string)($data['correct_answer'] ?? '')));
            if (!in_array($correctRaw, ['A', 'B', 'C', 'D'], true)) {
                return ['success' => false, 'message' => 'Correct answer must be A/B/C/D'];
            }

            $type = $this->db->fetchOne(
                "SELECT COLUMN_TYPE AS col_type
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                 AND table_name = 'aptitude_questions'
                 AND column_name = 'correct_answer'"
            );
            $correct = (strpos(strtolower((string)($type['col_type'] ?? '')), "'a'") !== false) ? strtolower($correctRaw) : $correctRaw;

            $insert = [
                'question_text' => $text,
                'option_a' => trim((string)($data['option_a'] ?? '')),
                'option_b' => trim((string)($data['option_b'] ?? '')),
                'option_c' => trim((string)($data['option_c'] ?? '')),
                'option_d' => trim((string)($data['option_d'] ?? '')),
                'correct_answer' => $correct
            ];
            if ($this->columnExists('aptitude_questions', 'test_id')) {
                $insert['test_id'] = $testId;
            }
            if ($this->columnExists('aptitude_questions', 'category_id')) {
                $resolvedCategoryId = (isset($test['category_id']) && (int)$test['category_id'] > 0) ? (int)$test['category_id'] : null;
                if ($resolvedCategoryId === null) {
                    $resolvedCategoryId = $this->resolveCategoryId($test['category'] ?? null);
                    if ($resolvedCategoryId !== null && $resolvedCategoryId > 0 && $this->columnExists('aptitude_tests', 'category_id')) {
                        try {
                            $this->db->update(
                                'aptitude_tests',
                                ['category_id' => $resolvedCategoryId],
                                'test_id = :test_id',
                                ['test_id' => $testId]
                            );
                        } catch (Exception $inner) {
                            error_log("Add Question Category Sync Warning: " . $inner->getMessage());
                        }
                    }
                }

                if ($resolvedCategoryId === null || $resolvedCategoryId <= 0) {
                    return ['success' => false, 'message' => 'Unable to map test category for question'];
                }
                $insert['category_id'] = $resolvedCategoryId;
            }
            if ($this->columnExists('aptitude_questions', 'explanation')) {
                $insert['explanation'] = trim((string)($data['explanation'] ?? ''));
            }
            if ($this->columnExists('aptitude_questions', 'difficulty') && isset($data['difficulty'])) {
                $insert['difficulty'] = trim((string)$data['difficulty']);
            }
            if ($this->columnExists('aptitude_questions', 'marks')) {
                $insert['marks'] = max(1, (int)($data['marks'] ?? 1));
            }

            $this->db->insert('aptitude_questions', $insert);
            $questionId = (int)$this->db->lastInsertId();

            if ($this->tableExists('test_questions') && !$this->columnExists('aptitude_questions', 'test_id')) {
                $map = ['test_id' => $testId, 'question_id' => $questionId];
                if ($this->columnExists('test_questions', 'question_order')) {
                    $next = $this->db->fetchOne(
                        "SELECT COALESCE(MAX(question_order), 0) + 1 AS next_order
                         FROM test_questions
                         WHERE test_id = :test_id",
                        ['test_id' => $testId]
                    );
                    $map['question_order'] = (int)($next['next_order'] ?? 1);
                }
                $this->db->insert('test_questions', $map);
            }

            $this->syncTotalQuestions($testId);
            return ['success' => true, 'message' => 'Question added successfully', 'question_id' => $questionId];
        } catch (Exception $e) {
            error_log("Add Question Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to add question'];
        }
    }

    public function updateQuestion($testId, $questionId, $data) {
        try {
            $question = $this->db->fetchOne("SELECT question_id, correct_answer FROM aptitude_questions WHERE question_id = :question_id", ['question_id' => $questionId]);
            if (!$question) {
                return ['success' => false, 'message' => 'Question not found'];
            }

            if ($this->columnExists('aptitude_questions', 'test_id')) {
                $owner = $this->db->fetchOne(
                    "SELECT question_id
                     FROM aptitude_questions
                     WHERE question_id = :question_id
                     AND test_id = :test_id",
                    ['question_id' => $questionId, 'test_id' => $testId]
                );
                if (!$owner) {
                    return ['success' => false, 'message' => 'Question does not belong to this test'];
                }
            } elseif ($this->tableExists('test_questions')) {
                $map = $this->db->fetchOne(
                    "SELECT question_id
                     FROM test_questions
                     WHERE test_id = :test_id
                     AND question_id = :question_id",
                    ['test_id' => $testId, 'question_id' => $questionId]
                );
                if (!$map) {
                    return ['success' => false, 'message' => 'Question does not belong to this test'];
                }
            }

            $update = [];
            foreach (['question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'explanation', 'difficulty'] as $field) {
                if (isset($data[$field]) && $this->columnExists('aptitude_questions', $field)) {
                    $update[$field] = trim((string)$data[$field]);
                }
            }
            if (isset($data['marks']) && $this->columnExists('aptitude_questions', 'marks')) {
                $update['marks'] = max(1, (int)$data['marks']);
            }
            if (isset($data['correct_answer'])) {
                $raw = strtoupper(trim((string)$data['correct_answer']));
                if (!in_array($raw, ['A', 'B', 'C', 'D'], true)) {
                    return ['success' => false, 'message' => 'Correct answer must be A/B/C/D'];
                }
                $update['correct_answer'] = $this->normalizeAnswer($raw, $question['correct_answer']);
            }
            if (empty($update)) {
                return ['success' => false, 'message' => 'No changes to update'];
            }

            $this->db->update('aptitude_questions', $update, 'question_id = :question_id', ['question_id' => $questionId]);
            $this->syncTotalQuestions($testId);
            return ['success' => true, 'message' => 'Question updated successfully'];
        } catch (Exception $e) {
            error_log("Update Question Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update question'];
        }
    }

    public function deleteQuestion($testId, $questionId) {
        try {
            if ($this->columnExists('aptitude_questions', 'test_id')) {
                $this->db->query(
                    "DELETE FROM aptitude_questions
                     WHERE question_id = :question_id
                     AND test_id = :test_id",
                    ['question_id' => $questionId, 'test_id' => $testId]
                );
            } elseif ($this->tableExists('test_questions')) {
                $this->db->query(
                    "DELETE FROM test_questions
                     WHERE test_id = :test_id
                     AND question_id = :question_id",
                    ['test_id' => $testId, 'question_id' => $questionId]
                );
                $left = $this->db->fetchOne("SELECT COUNT(*) AS cnt FROM test_questions WHERE question_id = :question_id", ['question_id' => $questionId]);
                if ((int)($left['cnt'] ?? 0) === 0) {
                    $this->db->query("DELETE FROM aptitude_questions WHERE question_id = :question_id", ['question_id' => $questionId]);
                }
            } else {
                $this->db->query("DELETE FROM aptitude_questions WHERE question_id = :question_id", ['question_id' => $questionId]);
            }

            $this->syncTotalQuestions($testId);
            return ['success' => true, 'message' => 'Question deleted successfully'];
        } catch (Exception $e) {
            error_log("Delete Question Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete question'];
        }
    }
}
