<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

class Admin {
    private $db;
    private $submissionTable;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->submissionTable = $this->db->firstExistingTable(['coding_submissions', 'submissions']);
    }

    public function getDashboardStats() {
        $stats = [];

        $stats['total_users'] = (int)($this->db->fetchOne(
            "SELECT COUNT(*) AS count FROM users WHERE role_id = 1"
        )['count'] ?? 0);

        $stats['total_problems'] = (int)($this->db->fetchOne(
            "SELECT COUNT(*) AS count FROM coding_problems"
        )['count'] ?? 0);

        $stats['recent_submissions'] = 0;
        $stats['today_submissions'] = 0;
        if ($this->submissionTable) {
            $stats['recent_submissions'] = (int)($this->db->fetchOne(
                "SELECT COUNT(*) AS count
                 FROM {$this->submissionTable}
                 WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            )['count'] ?? 0);

            $stats['today_submissions'] = (int)($this->db->fetchOne(
                "SELECT COUNT(*) AS count
                 FROM {$this->submissionTable}
                 WHERE DATE(submitted_at) = CURDATE()"
            )['count'] ?? 0);
        }

        $stats['total_tests'] = (int)($this->db->fetchOne(
            "SELECT COUNT(*) AS count FROM aptitude_tests"
        )['count'] ?? 0);

        if ($this->db->tableExists('user_activity')) {
            $stats['active_users'] = (int)($this->db->fetchOne(
                "SELECT COUNT(DISTINCT user_id) AS count
                 FROM user_activity
                 WHERE activity_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            )['count'] ?? 0);
        } else {
            $stats['active_users'] = (int)($this->db->fetchOne(
                "SELECT COUNT(*) AS count
                 FROM users
                 WHERE role_id = 1
                 AND last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            )['count'] ?? 0);
        }

        return $stats;
    }

    public function getUsers($limit = 50, $offset = 0, $search = '') {
        $whereClause = '';
        $params = [];

        if ($search !== '') {
            $whereClause = "WHERE u.email LIKE :search OR up.full_name LIKE :search";
            $params['search'] = '%' . $search . '%';
        }

        $limit = max(1, (int)$limit);
        $offset = max(0, (int)$offset);

        $collegeExpr = $this->db->columnExists('user_profiles', 'college_name')
            ? 'up.college_name'
            : ($this->db->columnExists('user_profiles', 'college') ? 'up.college' : "''");

        $query = "SELECT u.user_id, u.email, u.role_id, u.created_at, u.last_login,
                         up.full_name, {$collegeExpr} AS college, up.branch
                  FROM users u
                  LEFT JOIN user_profiles up ON u.user_id = up.user_id
                  {$whereClause}
                  ORDER BY u.created_at DESC
                  LIMIT {$limit} OFFSET {$offset}";

        return $this->db->fetchAll($query, $params);
    }

    public function getUserCount($search = '') {
        $whereClause = '';
        $params = [];

        if ($search !== '') {
            $whereClause = "WHERE u.email LIKE :search OR up.full_name LIKE :search";
            $params['search'] = '%' . $search . '%';
        }

        $query = "SELECT COUNT(*) AS count
                  FROM users u
                  LEFT JOIN user_profiles up ON u.user_id = up.user_id
                  {$whereClause}";

        return (int)($this->db->fetchOne($query, $params)['count'] ?? 0);
    }

    public function deleteUser($userId) {
        try {
            $this->db->delete('users', 'user_id = :user_id', ['user_id' => (int)$userId]);
            return ['success' => true, 'message' => 'User deleted successfully'];
        } catch (Exception $e) {
            error_log('Delete User Error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete user'];
        }
    }

    public function getProblems($limit = 50, $offset = 0) {
        $limit = max(1, (int)$limit);
        $offset = max(0, (int)$offset);

        $query = "SELECT p.*,
                         0 AS solvers_count,
                         0 AS total_submissions
                  FROM coding_problems p
                  ORDER BY p.problem_id DESC
                  LIMIT {$limit} OFFSET {$offset}";

        if ($this->submissionTable) {
            $query = "SELECT p.*,
                             COUNT(DISTINCT CASE WHEN s.status = 'accepted' THEN s.user_id END) AS solvers_count,
                             COUNT(s.submission_id) AS total_submissions
                      FROM coding_problems p
                      LEFT JOIN {$this->submissionTable} s ON p.problem_id = s.problem_id
                      GROUP BY p.problem_id
                      ORDER BY p.problem_id DESC
                      LIMIT {$limit} OFFSET {$offset}";
        }

        return $this->db->fetchAll($query);
    }

    public function deleteProblem($problemId) {
        try {
            $this->db->delete('coding_problems', 'problem_id = :problem_id', ['problem_id' => (int)$problemId]);
            return ['success' => true, 'message' => 'Problem deleted successfully'];
        } catch (Exception $e) {
            error_log('Delete Problem Error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete problem'];
        }
    }

    public function getRecentActivity($limit = 20) {
        $limit = max(1, (int)$limit);

        if ($this->db->tableExists('user_activity')) {
            return $this->db->fetchAll(
                "SELECT ua.*, up.full_name
                 FROM user_activity ua
                 LEFT JOIN user_profiles up ON ua.user_id = up.user_id
                 ORDER BY ua.activity_time DESC
                 LIMIT {$limit}"
            );
        }

        return $this->db->fetchAll(
            "SELECT
                u.user_id,
                COALESCE(up.full_name, u.email) AS full_name,
                'logged in' AS activity_type,
                u.last_login AS activity_time
             FROM users u
             LEFT JOIN user_profiles up ON u.user_id = up.user_id
             WHERE u.last_login IS NOT NULL
             ORDER BY u.last_login DESC
             LIMIT {$limit}"
        );
    }

    public function getSubmissionAnalytics($days = 30) {
        $days = max(1, (int)$days);
        if (!$this->submissionTable) {
            return [];
        }

        return $this->db->fetchAll(
            "SELECT DATE(submitted_at) AS date,
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) AS accepted
             FROM {$this->submissionTable}
             WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
             GROUP BY DATE(submitted_at)
             ORDER BY date ASC"
        );
    }

    public function getUserGrowth($days = 30) {
        $days = max(1, (int)$days);
        return $this->db->fetchAll(
            "SELECT DATE(created_at) AS date, COUNT(*) AS new_users
             FROM users
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
             GROUP BY DATE(created_at)
             ORDER BY date ASC"
        );
    }

    public function addProblem($data) {
        try {
            $title = trim((string)($data['title'] ?? ''));
            $description = trim((string)($data['description'] ?? ''));
            if ($title === '' || $description === '') {
                return ['success' => false, 'message' => 'Title and description are required'];
            }

            $slug = strtolower(trim((string)preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
            $slug = trim($slug, '-');
            if ($slug === '') {
                $slug = 'problem';
            }

            $difficulty = strtolower(trim((string)($data['difficulty'] ?? 'medium')));
            if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
                $difficulty = 'medium';
            }

            $payload = [
                'title' => $title,
                'slug' => $slug,
                'difficulty' => $difficulty,
                'description' => $description,
                'input_format' => trim((string)($data['input_format'] ?? '')),
                'output_format' => trim((string)($data['output_format'] ?? '')),
                'constraints' => trim((string)($data['constraints'] ?? ''))
            ];

            if ($this->db->columnExists('coding_problems', 'tags')) {
                $payload['tags'] = isset($data['tags']) ? implode(',', array_filter(array_map('trim', explode(',', (string)$data['tags'])))) : '';
            }

            if ($this->db->columnExists('coding_problems', 'time_limit')) {
                $payload['time_limit'] = max(1, (int)($data['time_limit'] ?? 2));
            } elseif ($this->db->columnExists('coding_problems', 'time_limit_ms')) {
                $payload['time_limit_ms'] = max(1, (int)($data['time_limit'] ?? 2)) * 1000;
            }

            if ($this->db->columnExists('coding_problems', 'memory_limit')) {
                $payload['memory_limit'] = max(64, (int)($data['memory_limit'] ?? 256));
            } elseif ($this->db->columnExists('coding_problems', 'memory_limit_mb')) {
                $payload['memory_limit_mb'] = max(64, (int)($data['memory_limit'] ?? 256));
            }

            if ($this->db->columnExists('coding_problems', 'sample_input')) {
                $payload['sample_input'] = trim((string)($data['sample_input'] ?? ''));
            }
            if ($this->db->columnExists('coding_problems', 'sample_output')) {
                $payload['sample_output'] = trim((string)($data['sample_output'] ?? ''));
            }

            $this->db->insert('coding_problems', $payload);
            $problemId = (int)$this->db->lastInsertId();

            $sampleInput = trim((string)($data['sample_input'] ?? ''));
            $sampleOutput = trim((string)($data['sample_output'] ?? ''));
            if ($problemId > 0 && $sampleInput !== '' && $sampleOutput !== '' && $this->db->tableExists('test_cases')) {
                $testCasePayload = [
                    'problem_id' => $problemId,
                    'input_data' => $sampleInput,
                    'expected_output' => $sampleOutput
                ];

                if ($this->db->columnExists('test_cases', 'is_sample')) {
                    $testCasePayload['is_sample'] = 1;
                }
                if ($this->db->columnExists('test_cases', 'testcase_order')) {
                    $testCasePayload['testcase_order'] = 1;
                }
                if ($this->db->columnExists('test_cases', 'is_hidden')) {
                    $testCasePayload['is_hidden'] = 0;
                }

                $this->db->insert('test_cases', $testCasePayload);
            }

            return ['success' => true, 'message' => 'Problem added successfully'];
        } catch (Exception $e) {
            error_log('Add Problem Error: ' . $e->getMessage());
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return ['success' => false, 'message' => 'Problem with this title already exists'];
            }
            return ['success' => false, 'message' => 'Failed to add problem'];
        }
    }

    public function getTests($limit = 50, $offset = 0) {
        $limit = max(1, (int)$limit);
        $offset = max(0, (int)$offset);

        if ($this->db->columnExists('aptitude_questions', 'test_id')) {
            $countExpr = "(SELECT COUNT(*) FROM aptitude_questions WHERE test_id = t.test_id)";
        } elseif ($this->db->tableExists('test_questions')) {
            $countExpr = "(SELECT COUNT(*) FROM test_questions WHERE test_id = t.test_id)";
        } else {
            $countExpr = '0';
        }

        return $this->db->fetchAll(
            "SELECT t.*, {$countExpr} AS question_count
             FROM aptitude_tests t
             ORDER BY t.created_at DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
    }

    public function addTest($data) {
        try {
            $payload = [
                'test_name' => trim((string)($data['test_name'] ?? ''))
            ];

            if ($payload['test_name'] === '') {
                return ['success' => false, 'message' => 'Test name is required'];
            }

            if ($this->db->columnExists('aptitude_tests', 'category')) {
                $payload['category'] = $data['category'] ?? 'Quantitative';
            }
            if ($this->db->columnExists('aptitude_tests', 'category_id')) {
                $payload['category_id'] = (int)($data['category_id'] ?? 1);
            }
            if ($this->db->columnExists('aptitude_tests', 'difficulty')) {
                $payload['difficulty'] = strtolower((string)($data['difficulty'] ?? 'mixed'));
            }
            if ($this->db->columnExists('aptitude_tests', 'duration_minutes')) {
                $payload['duration_minutes'] = max(1, (int)($data['duration_minutes'] ?? 30));
            }
            if ($this->db->columnExists('aptitude_tests', 'total_questions')) {
                $payload['total_questions'] = max(1, (int)($data['total_questions'] ?? 10));
            }
            if ($this->db->columnExists('aptitude_tests', 'total_marks')) {
                $payload['total_marks'] = max(1, (int)($data['total_questions'] ?? 10));
            }
            if ($this->db->columnExists('aptitude_tests', 'description')) {
                $payload['description'] = trim((string)($data['description'] ?? ''));
            } elseif ($this->db->columnExists('aptitude_tests', 'test_description')) {
                $payload['test_description'] = trim((string)($data['description'] ?? ''));
            }
            if ($this->db->columnExists('aptitude_tests', 'passing_score')) {
                $payload['passing_score'] = max(0, (int)($data['passing_score'] ?? 0));
            }

            $this->db->insert('aptitude_tests', $payload);
            return ['success' => true, 'message' => 'Test created successfully'];
        } catch (Exception $e) {
            error_log('Add Test Error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create test'];
        }
    }
}
