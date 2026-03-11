<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

class Community {
    private $db;
    private $questionTable;
    private $answerTable;
    private $commentTable;
    private $voteTable;
    private $tagTable;
    private $tagMapTable;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->questionTable = $this->db->firstExistingTable(['questions', 'community_questions']);
        $this->answerTable = $this->db->firstExistingTable(['answers', 'community_answers']);
        $this->commentTable = $this->db->firstExistingTable(['comments', 'community_comments']);
        $this->voteTable = $this->db->firstExistingTable(['votes', 'community_votes']);
        $this->tagTable = $this->db->firstExistingTable(['question_tags']);
        $this->tagMapTable = $this->db->firstExistingTable(['question_tag_mapping']);
    }

    private function isCurrentSchema() {
        return $this->questionTable === 'questions';
    }

    private function normalizeTags($tags) {
        if (!is_array($tags)) {
            return [];
        }

        $normalized = [];
        foreach ($tags as $tag) {
            $clean = trim((string)$tag);
            if ($clean === '') {
                continue;
            }

            $key = strtolower($clean);
            if (!isset($normalized[$key])) {
                $normalized[$key] = $clean;
            }

            if (count($normalized) >= 5) {
                break;
            }
        }

        return array_values($normalized);
    }

    private function slugifyTag($tag) {
        $slug = strtolower(trim((string)$tag));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim((string)$slug, '-');
        return $slug !== '' ? $slug : 'general';
    }

    private function buildInClause($values, $prefix, &$params) {
        $placeholders = [];
        foreach (array_values($values) as $index => $value) {
            $key = $prefix . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int)$value;
        }
        return implode(', ', $placeholders);
    }

    private function loadQuestionTags($questionIds) {
        $tagsByQuestion = [];
        $questionIds = array_values(array_unique(array_map('intval', $questionIds)));
        if (empty($questionIds) || !$this->tagTable || !$this->tagMapTable) {
            return $tagsByQuestion;
        }

        $params = [];
        $in = $this->buildInClause($questionIds, 'qt_', $params);
        if ($in === '') {
            return $tagsByQuestion;
        }

        $rows = $this->db->fetchAll(
            "SELECT qtm.question_id, qt.tag_name
             FROM {$this->tagMapTable} qtm
             JOIN {$this->tagTable} qt ON qt.tag_id = qtm.tag_id
             WHERE qtm.question_id IN ({$in})
             ORDER BY qt.tag_name ASC",
            $params
        );

        foreach ($rows as $row) {
            $questionId = (int)($row['question_id'] ?? 0);
            if ($questionId <= 0) {
                continue;
            }
            if (!isset($tagsByQuestion[$questionId])) {
                $tagsByQuestion[$questionId] = [];
            }
            $tagsByQuestion[$questionId][] = (string)($row['tag_name'] ?? '');
        }

        return $tagsByQuestion;
    }

    private function syncQuestionTags($questionId, $tags) {
        $tags = $this->normalizeTags($tags);
        if ($questionId <= 0 || empty($tags) || !$this->tagTable || !$this->tagMapTable) {
            return;
        }

        foreach ($tags as $tag) {
            $slug = $this->slugifyTag($tag);
            $this->db->query(
                "INSERT INTO {$this->tagTable} (tag_name, tag_slug, usage_count)
                 VALUES (:tag_name, :tag_slug, 1)
                 ON DUPLICATE KEY UPDATE usage_count = usage_count + 1",
                ['tag_name' => $tag, 'tag_slug' => $slug]
            );

            $tagRow = $this->db->fetchOne(
                "SELECT tag_id
                 FROM {$this->tagTable}
                 WHERE tag_slug = :tag_slug
                 LIMIT 1",
                ['tag_slug' => $slug]
            );

            $tagId = (int)($tagRow['tag_id'] ?? 0);
            if ($tagId <= 0) {
                continue;
            }

            $this->db->query(
                "INSERT IGNORE INTO {$this->tagMapTable} (question_id, tag_id)
                 VALUES (:question_id, :tag_id)",
                ['question_id' => (int)$questionId, 'tag_id' => $tagId]
            );
        }
    }

    public function postQuestion($userId, $title, $content, $tags = []) {
        try {
            if ($this->isCurrentSchema()) {
                $this->db->query(
                    "INSERT INTO {$this->questionTable} (user_id, title, question_body)
                     VALUES (:user_id, :title, :question_body)",
                    [
                        'user_id' => (int)$userId,
                        'title' => trim((string)$title),
                        'question_body' => trim((string)$content)
                    ]
                );

                $questionId = (int)$this->db->lastInsertId();
                $this->syncQuestionTags($questionId, $tags);

                return ['success' => true, 'message' => 'Question posted successfully'];
            }

            $this->db->query(
                "INSERT INTO {$this->questionTable} (user_id, title, content, tags, created_at)
                 VALUES (:user_id, :title, :content, :tags, NOW())",
                [
                    'user_id' => (int)$userId,
                    'title' => trim((string)$title),
                    'content' => trim((string)$content),
                    'tags' => json_encode($this->normalizeTags($tags))
                ]
            );

            $this->updateReputation($userId, 'question_asked');
            return ['success' => true, 'message' => 'Question posted successfully'];
        } catch (Exception $e) {
            error_log('Community postQuestion error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to post question'];
        }
    }

    public function getQuestions($filter = 'recent', $tag = null, $search = null, $limit = 20, $offset = 0) {
        $limit = max(1, (int)$limit);
        $offset = max(0, (int)$offset);

        if ($this->isCurrentSchema()) {
            $where = ['1=1'];
            $params = [];

            if ($tag) {
                $where[] = "q.question_id IN (
                    SELECT qtm.question_id
                    FROM {$this->tagMapTable} qtm
                    JOIN {$this->tagTable} qt ON qt.tag_id = qtm.tag_id
                    WHERE qt.tag_name = :tag_name OR qt.tag_slug = :tag_slug
                )";
                $params['tag_name'] = (string)$tag;
                $params['tag_slug'] = $this->slugifyTag($tag);
            }

            if ($search) {
                $where[] = "(q.title LIKE :search OR q.question_body LIKE :search)";
                $params['search'] = '%' . $search . '%';
            }

            $orderBy = match ($filter) {
                'popular' => '(q.upvotes - q.downvotes) DESC, q.views DESC, q.created_at DESC',
                'unanswered' => 'answer_count ASC, q.created_at DESC',
                'solved' => 'q.is_solved DESC, q.created_at DESC',
                default => 'q.created_at DESC'
            };

            $query = "SELECT
                        q.question_id,
                        q.user_id,
                        q.title,
                        q.question_body AS content,
                        q.views,
                        (q.upvotes - q.downvotes) AS votes,
                        (q.upvotes - q.downvotes) AS net_votes,
                        q.is_solved,
                        q.created_at,
                        q.updated_at,
                        COALESCE(up.full_name, u.email) AS full_name,
                        u.email,
                        (
                            SELECT COUNT(*)
                            FROM {$this->answerTable} a
                            WHERE a.question_id = q.question_id
                        ) AS answer_count
                      FROM {$this->questionTable} q
                      JOIN users u ON q.user_id = u.user_id
                      LEFT JOIN user_profiles up ON q.user_id = up.user_id
                      WHERE " . implode(' AND ', $where) . "
                      ORDER BY {$orderBy}
                      LIMIT {$limit} OFFSET {$offset}";

            $questions = $this->db->fetchAll($query, $params);
            $tagsByQuestion = $this->loadQuestionTags(array_column($questions, 'question_id'));

            foreach ($questions as &$question) {
                $questionId = (int)($question['question_id'] ?? 0);
                $question['tags'] = $tagsByQuestion[$questionId] ?? [];
            }

            return $questions;
        }

        $whereClause = "WHERE 1=1";
        $params = [];

        if ($tag) {
            $whereClause .= " AND JSON_CONTAINS(q.tags, :tag)";
            $params['tag'] = json_encode($tag);
        }

        if ($search) {
            $whereClause .= " AND (q.title LIKE :search OR q.content LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        $orderBy = match ($filter) {
            'popular' => 'q.votes DESC, q.views DESC',
            'unanswered' => 'answer_count ASC, q.created_at DESC',
            'solved' => 'q.is_solved DESC, q.created_at DESC',
            default => 'q.created_at DESC'
        };

        $query = "SELECT q.*, q.content AS content, q.votes AS votes,
                         COALESCE(up.full_name, u.email) AS full_name, u.email,
                         (SELECT COUNT(*) FROM {$this->answerTable} WHERE question_id = q.question_id) AS answer_count
                  FROM {$this->questionTable} q
                  JOIN users u ON q.user_id = u.user_id
                  LEFT JOIN user_profiles up ON q.user_id = up.user_id
                  {$whereClause}
                  ORDER BY {$orderBy}
                  LIMIT {$limit} OFFSET {$offset}";

        $questions = $this->db->fetchAll($query, $params);
        foreach ($questions as &$question) {
            $question['tags'] = json_decode((string)($question['tags'] ?? '[]'), true) ?? [];
        }

        return $questions;
    }

    public function getQuestion($questionId) {
        $questionId = (int)$questionId;
        if ($questionId <= 0) {
            return null;
        }

        if ($this->isCurrentSchema()) {
            $question = $this->db->fetchOne(
                "SELECT
                    q.question_id,
                    q.user_id,
                    q.title,
                    q.question_body AS content,
                    q.views,
                    (q.upvotes - q.downvotes) AS votes,
                    (q.upvotes - q.downvotes) AS net_votes,
                    q.is_solved,
                    q.created_at,
                    q.updated_at,
                    COALESCE(up.full_name, u.email) AS full_name,
                    u.email
                 FROM {$this->questionTable} q
                 JOIN users u ON q.user_id = u.user_id
                 LEFT JOIN user_profiles up ON q.user_id = up.user_id
                 WHERE q.question_id = :qid",
                ['qid' => $questionId]
            );

            if ($question) {
                $question['tags'] = $this->loadQuestionTags([$questionId])[$questionId] ?? [];
                $this->db->query(
                    "UPDATE {$this->questionTable} SET views = views + 1 WHERE question_id = :qid",
                    ['qid' => $questionId]
                );
            }

            return $question;
        }

        $question = $this->db->fetchOne(
            "SELECT q.*, q.content AS content,
                    COALESCE(up.full_name, u.email) AS full_name, u.email
             FROM {$this->questionTable} q
             JOIN users u ON q.user_id = u.user_id
             LEFT JOIN user_profiles up ON q.user_id = up.user_id
             WHERE q.question_id = :qid",
            ['qid' => $questionId]
        );

        if ($question) {
            $question['tags'] = json_decode((string)($question['tags'] ?? '[]'), true) ?? [];
            $this->db->query(
                "UPDATE {$this->questionTable} SET views = views + 1 WHERE question_id = :qid",
                ['qid' => $questionId]
            );
        }

        return $question;
    }

    public function postAnswer($questionId, $userId, $content) {
        try {
            if ($this->isCurrentSchema()) {
                $this->db->query(
                    "INSERT INTO {$this->answerTable} (question_id, user_id, answer_body)
                     VALUES (:qid, :uid, :content)",
                    ['qid' => (int)$questionId, 'uid' => (int)$userId, 'content' => trim((string)$content)]
                );

                return ['success' => true, 'message' => 'Answer posted successfully'];
            }

            $this->db->query(
                "INSERT INTO {$this->answerTable} (question_id, user_id, content, created_at)
                 VALUES (:qid, :uid, :content, NOW())",
                ['qid' => (int)$questionId, 'uid' => (int)$userId, 'content' => trim((string)$content)]
            );

            $this->db->query(
                "UPDATE {$this->questionTable} SET answer_count = answer_count + 1 WHERE question_id = :qid",
                ['qid' => (int)$questionId]
            );

            $this->updateReputation($userId, 'answer_given');
            return ['success' => true, 'message' => 'Answer posted successfully'];
        } catch (Exception $e) {
            error_log('Community postAnswer error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to post answer'];
        }
    }

    public function getAnswers($questionId) {
        $questionId = (int)$questionId;
        if ($questionId <= 0) {
            return [];
        }

        if ($this->isCurrentSchema()) {
            return $this->db->fetchAll(
                "SELECT
                    a.answer_id,
                    a.question_id,
                    a.user_id,
                    a.answer_body AS content,
                    (a.upvotes - a.downvotes) AS vote_count,
                    (a.upvotes - a.downvotes) AS votes,
                    a.is_accepted,
                    a.is_verified,
                    a.created_at,
                    COALESCE(up.full_name, u.email) AS full_name,
                    u.email
                 FROM {$this->answerTable} a
                 JOIN users u ON a.user_id = u.user_id
                 LEFT JOIN user_profiles up ON a.user_id = up.user_id
                 WHERE a.question_id = :qid
                 ORDER BY a.is_accepted DESC, (a.upvotes - a.downvotes) DESC, a.created_at ASC",
                ['qid' => $questionId]
            );
        }

        return $this->db->fetchAll(
            "SELECT a.*, a.content AS content,
                    COALESCE(up.full_name, u.email) AS full_name, u.email,
                    (SELECT COUNT(*) FROM {$this->voteTable} WHERE target_type = 'answer' AND target_id = a.answer_id) AS vote_count
             FROM {$this->answerTable} a
             JOIN users u ON a.user_id = u.user_id
             LEFT JOIN user_profiles up ON a.user_id = up.user_id
             WHERE a.question_id = :qid
             ORDER BY a.is_accepted DESC, a.votes DESC, a.created_at ASC",
            ['qid' => $questionId]
        );
    }

    public function vote($userId, $targetType, $targetId, $voteValue) {
        $targetType = strtolower(trim((string)$targetType));
        $targetId = (int)$targetId;
        $voteValue = (int)$voteValue;

        if (!in_array($targetType, ['question', 'answer'], true) || !in_array($voteValue, [-1, 1], true)) {
            return ['success' => false, 'message' => 'Invalid vote'];
        }

        try {
            if ($this->isCurrentSchema()) {
                $existing = $this->db->fetchOne(
                    "SELECT vote_id, vote_value
                     FROM {$this->voteTable}
                     WHERE user_id = :uid AND vote_type = :type AND target_id = :tid",
                    ['uid' => (int)$userId, 'type' => $targetType, 'tid' => $targetId]
                );

                $oldValue = (int)($existing['vote_value'] ?? 0);
                if ($existing) {
                    $this->db->query(
                        "UPDATE {$this->voteTable}
                         SET vote_value = :value
                         WHERE vote_id = :vote_id",
                        ['value' => $voteValue, 'vote_id' => (int)$existing['vote_id']]
                    );
                } else {
                    $this->db->query(
                        "INSERT INTO {$this->voteTable} (user_id, vote_type, target_id, vote_value)
                         VALUES (:uid, :type, :tid, :value)",
                        ['uid' => (int)$userId, 'type' => $targetType, 'tid' => $targetId, 'value' => $voteValue]
                    );
                }

                $table = $targetType === 'question' ? $this->questionTable : $this->answerTable;
                $idField = $targetType === 'question' ? 'question_id' : 'answer_id';

                if ($oldValue === 1) {
                    $this->db->query(
                        "UPDATE {$table} SET upvotes = GREATEST(0, upvotes - 1) WHERE {$idField} = :tid",
                        ['tid' => $targetId]
                    );
                } elseif ($oldValue === -1) {
                    $this->db->query(
                        "UPDATE {$table} SET downvotes = GREATEST(0, downvotes - 1) WHERE {$idField} = :tid",
                        ['tid' => $targetId]
                    );
                }

                if ($voteValue === 1) {
                    $this->db->query(
                        "UPDATE {$table} SET upvotes = upvotes + 1 WHERE {$idField} = :tid",
                        ['tid' => $targetId]
                    );
                } else {
                    $this->db->query(
                        "UPDATE {$table} SET downvotes = downvotes + 1 WHERE {$idField} = :tid",
                        ['tid' => $targetId]
                    );
                }

                return ['success' => true];
            }

            $existing = $this->db->fetchOne(
                "SELECT vote_value
                 FROM {$this->voteTable}
                 WHERE user_id = :uid AND target_type = :type AND target_id = :tid",
                ['uid' => (int)$userId, 'type' => $targetType, 'tid' => $targetId]
            );

            if ($existing) {
                $this->db->query(
                    "UPDATE {$this->voteTable}
                     SET vote_value = :value
                     WHERE user_id = :uid AND target_type = :type AND target_id = :tid",
                    ['value' => $voteValue, 'uid' => (int)$userId, 'type' => $targetType, 'tid' => $targetId]
                );

                $diff = $voteValue - (int)$existing['vote_value'];
            } else {
                $this->db->query(
                    "INSERT INTO {$this->voteTable} (user_id, target_type, target_id, vote_value)
                     VALUES (:uid, :type, :tid, :value)",
                    ['uid' => (int)$userId, 'type' => $targetType, 'tid' => $targetId, 'value' => $voteValue]
                );

                $diff = $voteValue;
            }

            $table = $targetType === 'question' ? $this->questionTable : $this->answerTable;
            $idField = $targetType === 'question' ? 'question_id' : 'answer_id';

            $this->db->query(
                "UPDATE {$table} SET votes = votes + :diff WHERE {$idField} = :tid",
                ['diff' => $diff, 'tid' => $targetId]
            );

            return ['success' => true];
        } catch (Exception $e) {
            error_log('Community vote error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to vote'];
        }
    }

    public function postComment($parentType, $parentId, $userId, $content) {
        try {
            if ($this->isCurrentSchema()) {
                $this->db->query(
                    "INSERT INTO {$this->commentTable} (parent_type, parent_id, user_id, comment_text)
                     VALUES (:type, :pid, :uid, :content)",
                    ['type' => $parentType, 'pid' => (int)$parentId, 'uid' => (int)$userId, 'content' => trim((string)$content)]
                );
            } else {
                $this->db->query(
                    "INSERT INTO {$this->commentTable} (parent_type, parent_id, user_id, content)
                     VALUES (:type, :pid, :uid, :content)",
                    ['type' => $parentType, 'pid' => (int)$parentId, 'uid' => (int)$userId, 'content' => trim((string)$content)]
                );
            }

            return ['success' => true, 'message' => 'Comment posted'];
        } catch (Exception $e) {
            error_log('Community postComment error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to post comment'];
        }
    }

    public function getComments($parentType, $parentId) {
        if ($this->isCurrentSchema()) {
            return $this->db->fetchAll(
                "SELECT c.*, c.comment_text AS content, COALESCE(up.full_name, u.email) AS full_name
                 FROM {$this->commentTable} c
                 JOIN users u ON c.user_id = u.user_id
                 LEFT JOIN user_profiles up ON c.user_id = up.user_id
                 WHERE c.parent_type = :type AND c.parent_id = :pid
                 ORDER BY c.created_at ASC",
                ['type' => $parentType, 'pid' => (int)$parentId]
            );
        }

        return $this->db->fetchAll(
            "SELECT c.*, c.content AS content, COALESCE(up.full_name, u.email) AS full_name
             FROM {$this->commentTable} c
             JOIN users u ON c.user_id = u.user_id
             LEFT JOIN user_profiles up ON c.user_id = up.user_id
             WHERE c.parent_type = :type AND c.parent_id = :pid
             ORDER BY c.created_at ASC",
            ['type' => $parentType, 'pid' => (int)$parentId]
        );
    }

    public function acceptAnswer($answerId, $questionId, $userId) {
        $questionId = (int)$questionId;
        $answerId = (int)$answerId;
        $userId = (int)$userId;

        $question = $this->db->fetchOne(
            "SELECT user_id FROM {$this->questionTable} WHERE question_id = :qid",
            ['qid' => $questionId]
        );

        if ((int)($question['user_id'] ?? 0) !== $userId) {
            return ['success' => false, 'message' => 'Only question author can accept answers'];
        }

        $this->db->query(
            "UPDATE {$this->answerTable} SET is_accepted = FALSE WHERE question_id = :qid",
            ['qid' => $questionId]
        );

        $this->db->query(
            "UPDATE {$this->answerTable} SET is_accepted = TRUE WHERE answer_id = :aid",
            ['aid' => $answerId]
        );

        $this->db->query(
            "UPDATE {$this->questionTable} SET is_solved = TRUE WHERE question_id = :qid",
            ['qid' => $questionId]
        );

        if (!$this->isCurrentSchema()) {
            $this->updateReputation($userId, 'answer_accepted');
        }

        return ['success' => true, 'message' => 'Answer accepted'];
    }

    public function getLeaderboard($limit = 20) {
        $limit = max(1, (int)$limit);

        if ($this->db->tableExists('leaderboard')) {
            return $this->db->fetchAll(
                "SELECT lb.*, lb.reputation_score AS points, COALESCE(up.full_name, u.email) AS full_name, u.email
                 FROM leaderboard lb
                 JOIN users u ON lb.user_id = u.user_id
                 LEFT JOIN user_profiles up ON lb.user_id = up.user_id
                 ORDER BY lb.reputation_score DESC, lb.answers_posted DESC
                 LIMIT {$limit}"
            );
        }

        if ($this->db->tableExists('user_reputation')) {
            return $this->db->fetchAll(
                "SELECT ur.*, ur.points AS points, COALESCE(up.full_name, u.email) AS full_name, u.email
                 FROM user_reputation ur
                 JOIN users u ON ur.user_id = u.user_id
                 LEFT JOIN user_profiles up ON ur.user_id = up.user_id
                 ORDER BY ur.points DESC
                 LIMIT {$limit}"
            );
        }

        return [];
    }

    private function updateReputation($userId, $action) {
        if (!$this->db->tableExists('user_reputation')) {
            return;
        }

        $points = match ($action) {
            'question_asked' => 5,
            'answer_given' => 10,
            'answer_accepted' => 15,
            'upvote_received' => 10,
            default => 0
        };

        $this->db->query(
            "INSERT INTO user_reputation (user_id, points)
             VALUES (:uid, 0)
             ON DUPLICATE KEY UPDATE user_id = user_id",
            ['uid' => (int)$userId]
        );

        $field = match ($action) {
            'question_asked' => 'questions_asked',
            'answer_given' => 'answers_given',
            'answer_accepted' => 'solutions_accepted',
            default => null
        };

        if ($field) {
            $this->db->query(
                "UPDATE user_reputation
                 SET points = points + :points, {$field} = {$field} + 1
                 WHERE user_id = :uid",
                ['points' => $points, 'uid' => (int)$userId]
            );
        }
    }

    public function getPopularTags($limit = 20) {
        $limit = max(1, (int)$limit);

        if ($this->tagTable) {
            return $this->db->fetchAll(
                "SELECT tag_name AS tag, usage_count AS count
                 FROM {$this->tagTable}
                 ORDER BY usage_count DESC, tag_name ASC
                 LIMIT {$limit}"
            );
        }

        return [
            ['tag' => 'javascript', 'count' => 150],
            ['tag' => 'python', 'count' => 120],
            ['tag' => 'java', 'count' => 100],
            ['tag' => 'algorithms', 'count' => 90],
            ['tag' => 'data-structures', 'count' => 85]
        ];
    }
}
