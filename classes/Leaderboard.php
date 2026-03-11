<?php
/**
 * Leaderboard Management Class
 * Handles global rankings and problem-specific leaderboards
 */

class Leaderboard {
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

    private function runtimeExpr($alias = 's') {
        if ($this->columnExists($this->submissionTable, 'execution_time_ms')) {
            return "ROUND({$alias}.execution_time_ms / 1000, 3)";
        }
        if ($this->columnExists($this->submissionTable, 'runtime')) {
            return "{$alias}.runtime";
        }
        return 'NULL';
    }

    private function memoryExpr($alias = 's') {
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

    private function languageJoinSql($alias = 's') {
        if ($this->languageTable && $this->columnExists($this->submissionTable, 'language_id')) {
            return "JOIN {$this->languageTable} l ON {$alias}.language_id = l.language_id";
        }
        return '';
    }

    private function languageSelect($alias = 's') {
        if ($this->languageTable && $this->columnExists($this->submissionTable, 'language_id')) {
            return 'l.language_name';
        }
        if ($this->columnExists($this->submissionTable, 'language')) {
            return "{$alias}.language";
        }
        return "'Unknown'";
    }

    public function getGlobalLeaderboard($limit = 100) {
        if (!$this->submissionTable) {
            return [];
        }

        $limit = max(1, (int)$limit);
        $query = "SELECT
                    u.user_id,
                    up.full_name AS fullname,
                    up.full_name AS username,
                    COUNT(DISTINCT CASE WHEN s.status = 'accepted' THEN s.problem_id END) AS problems_solved,
                    COUNT(s.submission_id) AS total_submissions,
                    SUM(CASE WHEN s.status = 'accepted' AND LOWER(p.difficulty) = 'easy' THEN 1 ELSE 0 END) AS easy_solved,
                    SUM(CASE WHEN s.status = 'accepted' AND LOWER(p.difficulty) = 'medium' THEN 1 ELSE 0 END) AS medium_solved,
                    SUM(CASE WHEN s.status = 'accepted' AND LOWER(p.difficulty) = 'hard' THEN 1 ELSE 0 END) AS hard_solved,
                    ROUND(COUNT(DISTINCT CASE WHEN s.status = 'accepted' THEN s.problem_id END) * 100.0 / NULLIF(COUNT(s.submission_id), 0), 2) AS acceptance_rate
                  FROM users u
                  JOIN user_profiles up ON u.user_id = up.user_id
                  LEFT JOIN {$this->submissionTable} s ON u.user_id = s.user_id
                  LEFT JOIN coding_problems p ON s.problem_id = p.problem_id
                  WHERE u.role_id = 1
                  GROUP BY u.user_id, up.full_name
                  HAVING problems_solved > 0
                  ORDER BY problems_solved DESC, acceptance_rate DESC
                  LIMIT {$limit}";

        return $this->db->fetchAll($query);
    }

    public function getProblemLeaderboard($problemId, $limit = 50) {
        if (!$this->submissionTable) {
            return [];
        }

        $limit = max(1, (int)$limit);
        $runtimeExpr = $this->runtimeExpr('s1');
        $memoryExpr = $this->memoryExpr('s1');
        $languageExpr = $this->languageSelect('s1');
        $languageJoin = $this->languageJoinSql('s1');

        $query = "SELECT
                    up.full_name AS username,
                    up.full_name AS fullname,
                    s.runtime,
                    s.memory_used,
                    s.language_name,
                    s.submitted_at
                  FROM (
                      SELECT
                          s1.user_id,
                          {$runtimeExpr} AS runtime,
                          {$memoryExpr} AS memory_used,
                          {$languageExpr} AS language_name,
                          s1.submitted_at,
                          ROW_NUMBER() OVER (PARTITION BY s1.user_id ORDER BY {$runtimeExpr} ASC) AS rn
                      FROM {$this->submissionTable} s1
                      {$languageJoin}
                      WHERE s1.problem_id = :problem_id
                      AND s1.status = 'accepted'
                  ) s
                  JOIN users u ON s.user_id = u.user_id
                  JOIN user_profiles up ON u.user_id = up.user_id
                  WHERE s.rn = 1
                  ORDER BY s.runtime ASC
                  LIMIT {$limit}";

        return $this->db->fetchAll($query, ['problem_id' => (int)$problemId]);
    }

    public function getUserRank($userId) {
        $totalUsers = (int)($this->db->fetchOne(
            "SELECT COUNT(*) AS count FROM users WHERE role_id = 1",
            []
        )['count'] ?? 0);

        if (!$this->submissionTable) {
            return ['rank' => null, 'total_users' => $totalUsers];
        }

        $query = "SELECT rank_position FROM (
                    SELECT
                        user_id,
                        ROW_NUMBER() OVER (ORDER BY problems_solved DESC, acceptance_rate DESC) AS rank_position
                    FROM (
                        SELECT
                            u.user_id,
                            COUNT(DISTINCT CASE WHEN s.status = 'accepted' THEN s.problem_id END) AS problems_solved,
                            ROUND(COUNT(DISTINCT CASE WHEN s.status = 'accepted' THEN s.problem_id END) * 100.0 / NULLIF(COUNT(s.submission_id), 0), 2) AS acceptance_rate
                        FROM users u
                        LEFT JOIN {$this->submissionTable} s ON u.user_id = s.user_id
                        WHERE u.role_id = 1
                        GROUP BY u.user_id
                    ) stats
                  ) ranked
                  WHERE user_id = :user_id";

        $result = $this->db->fetchOne($query, ['user_id' => (int)$userId]);

        return [
            'rank' => $result['rank_position'] ?? null,
            'total_users' => $totalUsers
        ];
    }
}
