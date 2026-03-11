<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';

Auth::requireLogin();

$userId = (int)Auth::getUserId();
$db = Database::getInstance();
$submissionTable = $db->firstExistingTable(['coding_submissions', 'submissions']);

$allowedDifficulties = ['easy', 'medium', 'hard'];
$difficulty = strtolower(trim((string)($_GET['difficulty'] ?? '')));
if (!in_array($difficulty, $allowedDifficulties, true)) {
    $difficulty = '';
}

$search = trim((string)($_GET['search'] ?? ''));
if (strlen($search) > 100) {
    $search = substr($search, 0, 100);
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

$limit = 20;
$whereClause = " WHERE 1=1";
$params = [];

if ($difficulty !== '') {
    $whereClause .= " AND LOWER(p.difficulty) = :difficulty";
    $params['difficulty'] = $difficulty;
}

if ($search !== '') {
    $whereClause .= " AND (p.title LIKE :search OR p.description LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

$countQuery = "SELECT COUNT(*) AS total FROM coding_problems p {$whereClause}";
$totalProblems = (int)($db->fetchOne($countQuery, $params)['total'] ?? 0);
$totalPages = max(1, (int)ceil($totalProblems / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $limit;

$query = "
    SELECT
        p.problem_id,
        p.title,
        LOWER(p.difficulty) AS difficulty,
        0 AS total_submissions,
        0 AS accepted_count,
        0 AS acceptance_rate,
        0 AS user_solved,
        0 AS user_attempted
    FROM coding_problems p
    {$whereClause}
    ORDER BY p.problem_id ASC
    LIMIT {$limit} OFFSET {$offset}
";

$queryParams = $params;
if ($submissionTable) {
    $query = "
        SELECT
            p.problem_id,
            p.title,
            LOWER(p.difficulty) AS difficulty,
            COALESCE(st.total_submissions, 0) AS total_submissions,
            COALESCE(st.accepted_count, 0) AS accepted_count,
            CASE
                WHEN COALESCE(st.total_submissions, 0) = 0 THEN 0
                ELSE ROUND((st.accepted_count * 100.0) / st.total_submissions, 1)
            END AS acceptance_rate,
            COALESCE(us.user_solved, 0) AS user_solved,
            COALESCE(us.user_attempted, 0) AS user_attempted
        FROM coding_problems p
        LEFT JOIN (
            SELECT
                problem_id,
                COUNT(*) AS total_submissions,
                SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) AS accepted_count
            FROM {$submissionTable}
            GROUP BY problem_id
        ) st ON p.problem_id = st.problem_id
        LEFT JOIN (
            SELECT
                problem_id,
                MAX(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) AS user_solved,
                MAX(CASE WHEN status <> 'accepted' THEN 1 ELSE 0 END) AS user_attempted
            FROM {$submissionTable}
            WHERE user_id = :user_id
            GROUP BY problem_id
        ) us ON p.problem_id = us.problem_id
        {$whereClause}
        ORDER BY p.problem_id ASC
        LIMIT {$limit} OFFSET {$offset}
    ";

    $queryParams = array_merge(['user_id' => $userId], $params);
}

$problems = $db->fetchAll($query, $queryParams);

$baseQuery = [];
if ($difficulty !== '') {
    $baseQuery['difficulty'] = $difficulty;
}
if ($search !== '') {
    $baseQuery['search'] = $search;
}
$buildPageUrl = static function ($targetPage) use ($baseQuery) {
    $params = $baseQuery;
    $params['page'] = (int)$targetPage;
    return '?' . http_build_query($params);
};

$pageTitle = 'Coding Problems - PlacementCode';
$additionalCSS = '
.container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
.header-section { margin-bottom: 30px; }
.header-section h1 { font-size: 2rem; margin-bottom: 8px; color: #e4e4e7; }
.header-section p { color: #a1a1aa; font-size: 1rem; }

.filter-bar {
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}
.filter-form { display: contents; }
.filter-select, .filter-input {
    background: #0f0f0f;
    border: 1px solid #2a2a2a;
    padding: 12px 16px;
    border-radius: 8px;
    color: #e4e4e7;
    font-size: 0.95rem;
    font-family: inherit;
}
.filter-select { min-width: 170px; cursor: pointer; }
.filter-select:focus, .filter-input:focus { outline: none; border-color: #ffa116; }
.filter-input { flex: 1; min-width: 300px; }

.btn-filter, .btn-clear {
    padding: 12px 22px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-family: inherit;
}
.btn-filter { background: #ffa116; color: #000; }
.btn-clear { background: #303036; color: #d4d4d8; border: 1px solid #3a3a3f; }
.btn-filter:hover, .btn-clear:hover { opacity: 0.92; }

.problems-table {
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    border-radius: 12px;
    overflow: hidden;
}
.problems-table table { width: 100%; border-collapse: collapse; }
.problems-table thead { background: #0f0f0f; }
.problems-table th {
    text-align: left;
    padding: 15px 20px;
    font-size: 0.85rem;
    color: #71717a;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.col-status { width: 65px; text-align: center; }
.col-acceptance { width: 130px; color: #71717a; }
.col-difficulty { width: 120px; }

.problems-table tbody tr { border-top: 1px solid #2a2a2a; transition: background 0.2s; }
.problems-table tbody tr:hover { background: #0f0f0f; }
.problems-table td { padding: 18px 20px; }

.status-icon { font-size: 1.1rem; line-height: 1; }
.status-solved { color: #22c55e; }
.status-attempted { color: #fbbf24; }

.problem-title { color: #e4e4e7; text-decoration: none; font-weight: 500; transition: color 0.2s; }
.problem-title:hover { color: #ffa116; }

.difficulty-easy { color: #22c55e; font-weight: 600; }
.difficulty-medium { color: #fbbf24; font-weight: 600; }
.difficulty-hard { color: #ef4444; font-weight: 600; }

.empty-state { text-align: center; padding: 60px 20px; color: #71717a; }

.pagination { display: flex; justify-content: center; gap: 10px; margin-top: 30px; flex-wrap: wrap; }
.page-link {
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    padding: 10px 16px;
    border-radius: 8px;
    color: #a1a1aa;
    text-decoration: none;
    transition: all 0.2s;
    font-weight: 500;
}
.page-link:hover { background: #2a2a2a; color: #fff; border-color: #3a3a3a; }
.page-link.active { background: #ffa116; color: #000; border-color: #ffa116; }

@media (max-width: 768px) {
    .filter-bar { flex-direction: column; }
    .filter-select, .filter-input { width: 100%; min-width: 100%; }
    .problems-table th, .problems-table td { padding: 12px; font-size: 0.9rem; }
    .col-acceptance { display: none; }
}
';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="container">
    <div class="header-section">
        <h1>Coding Problems</h1>
        <p>Practice curated coding problems and track your progress.</p>
    </div>

    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <select name="difficulty" class="filter-select">
                <option value="">All Difficulties</option>
                <option value="easy" <?php echo $difficulty === 'easy' ? 'selected' : ''; ?>>Easy</option>
                <option value="medium" <?php echo $difficulty === 'medium' ? 'selected' : ''; ?>>Medium</option>
                <option value="hard" <?php echo $difficulty === 'hard' ? 'selected' : ''; ?>>Hard</option>
            </select>
            <input
                type="text"
                name="search"
                class="filter-input"
                placeholder="Search problems by title or description"
                value="<?php echo htmlspecialchars($search); ?>"
            >
            <button type="submit" class="btn-filter">Apply Filters</button>
            <?php if ($difficulty !== '' || $search !== ''): ?>
                <a href="problems.php" class="btn-clear">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="problems-table">
        <table>
            <thead>
                <tr>
                    <th class="col-status">Status</th>
                    <th>Title</th>
                    <th class="col-acceptance">Acceptance</th>
                    <th class="col-difficulty">Difficulty</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($problems)): ?>
                    <tr>
                        <td colspan="4" class="empty-state">No problems found. Try adjusting filters.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($problems as $p): ?>
                        <?php
                        $diff = strtolower((string)($p['difficulty'] ?? 'medium'));
                        if (!in_array($diff, $allowedDifficulties, true)) {
                            $diff = 'medium';
                        }
                        $displayDiff = ucfirst($diff);
                        ?>
                        <tr>
                            <td class="col-status">
                                <?php if ((int)($p['user_solved'] ?? 0) > 0): ?>
                                    <span class="status-icon status-solved">&#10003;</span>
                                <?php elseif ((int)($p['user_attempted'] ?? 0) > 0): ?>
                                    <span class="status-icon status-attempted">&#9675;</span>
                                <?php else: ?>
                                    <span class="status-icon" style="color:#4b5563;">&#183;</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="editor.php?id=<?php echo (int)$p['problem_id']; ?>" class="problem-title">
                                    <?php echo (int)$p['problem_id']; ?>. <?php echo htmlspecialchars((string)$p['title']); ?>
                                </a>
                            </td>
                            <td class="col-acceptance">
                                <?php echo number_format((float)($p['acceptance_rate'] ?? 0), 1); ?>%
                            </td>
                            <td class="col-difficulty difficulty-<?php echo $diff; ?>">
                                <?php echo $displayDiff; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="<?php echo htmlspecialchars($buildPageUrl($page - 1)); ?>" class="page-link">Previous</a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="<?php echo htmlspecialchars($buildPageUrl($i)); ?>" class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?php echo htmlspecialchars($buildPageUrl($page + 1)); ?>" class="page-link">Next</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

