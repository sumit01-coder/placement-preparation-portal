<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';

Auth::requireLogin();
$userId = Auth::getUserId();
$db = Database::getInstance();

// Get filters
$difficulty = $_GET['difficulty'] ?? '';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$whereClause = "WHERE 1=1";
$params = [];

if ($difficulty) {
    $whereClause .= " AND p.difficulty = :difficulty";
    $params['difficulty'] = $difficulty;
}

if ($search) {
    $whereClause .= " AND (p.title LIKE :search OR p.description LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM coding_problems p $whereClause";
$totalResult = $db->fetchOne($countQuery, $params);
$totalProblems = $totalResult['total'];
$totalPages = ceil($totalProblems / $limit);

// Get problems
$query = "SELECT p.*, 
          COUNT(DISTINCT s.submission_id) as total_submissions,
          SUM(CASE WHEN s.status = 'accepted' THEN 1 ELSE 0 END) as accepted_count,
          ROUND(SUM(CASE WHEN s.status = 'accepted' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(s.submission_id), 0), 1) as acceptance_rate,
          (SELECT COUNT(*) FROM submissions WHERE user_id = :user_id1 AND problem_id = p.problem_id AND status = 'accepted') as user_solved,
          (SELECT COUNT(*) FROM submissions WHERE user_id = :user_id2 AND problem_id = p.problem_id AND status != 'accepted') as user_attempted
          FROM coding_problems p
          LEFT JOIN submissions s ON p.problem_id = s.problem_id
          $whereClause
          GROUP BY p.problem_id
          ORDER BY p.problem_id ASC
          LIMIT $limit OFFSET $offset";

$queryParams = array_merge($params, ['user_id1' => $userId, 'user_id2' => $userId]);
$problems = $db->fetchAll($query, $queryParams);

// Page config
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

.filter-select {
    min-width: 160px;
    cursor: pointer;
}

.filter-select:focus, .filter-input:focus {
    outline: none;
    border-color: #ffa116;
}

.filter-input {
    flex: 1;
    min-width: 300px;
}

.btn-filter {
    background: #ffa116;
    color: #000;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.2s;
    font-family: inherit;
}

.btn-filter:hover { opacity: 0.9; }

.problems-table {
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    border-radius: 12px;
    overflow: hidden;
}

.problems-table table {
    width: 100%;
    border-collapse: collapse;
}

.problems-table thead {
    background: #0f0f0f;
}

.problems-table th {
    text-align: left;
    padding: 15px 20px;
    font-size: 0.85rem;
    color: #71717a;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.col-status { width: 60px; text-align: center; }
.col-acceptance { width: 120px; color: #71717a; }
.col-difficulty { width: 120px; }

.problems-table tbody tr {
    border-top: 1px solid #2a2a2a;
    transition: background 0.2s;
}

.problems-table tbody tr:hover {
    background: #0f0f0f;
}

.problems-table td {
    padding: 18px 20px;
}

.status-icon {
    font-size: 1.2rem;
}

.status-solved { color: #22c55e; }
.status-attempted { color: #fbbf24; }

.problem-title {
    color: #e4e4e7;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s;
}

.problem-title:hover {
    color: #ffa116;
}

.difficulty-easy { color: #22c55e; font-weight: 600; }
.difficulty-medium { color: #fbbf24; font-weight: 600; }
.difficulty-hard { color: #ef4444; font-weight: 600; }

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #71717a;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 30px;
}

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

.page-link:hover {
    background: #2a2a2a;
    color: #fff;
    border-color: #3a3a3a;
}

.page-link.active {
    background: #ffa116;
    color: #000;
    border-color: #ffa116;
}

@media (max-width: 768px) {
    .filter-bar {
        flex-direction: column;
    }
    
    .filter-select, .filter-input {
        width: 100%;
    }
    
    .problems-table th, .problems-table td {
        padding: 12px;
        font-size: 0.9rem;
    }
    
    .col-acceptance {
        display: none;
    }
}
';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="container">
    
    <div class="header-section">
        <h1>💻 Coding Problems</h1>
        <p>Practice coding problems and improve your skills</p>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <select name="difficulty" class="filter-select">
                <option value="">All Difficulties</option>
                <option value="Easy" <?php echo $difficulty === 'Easy' ? 'selected' : ''; ?>>Easy</option>
                <option value="Medium" <?php echo $difficulty === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                <option value="Hard" <?php echo $difficulty === 'Hard' ? 'selected' : ''; ?>>Hard</option>
            </select>
            <input type="text" name="search" class="filter-input" placeholder="🔍 Search problems..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn-filter">Apply Filters</button>
        </form>
    </div>

    <!-- Problems Table -->
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
                        <td colspan="4" class="empty-state">
                            No problems found. Try adjusting your filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($problems as $p): ?>
                        <tr>
                            <td class="col-status">
                                <?php if ($p['user_solved']): ?>
                                    <span class="status-icon status-solved">✓</span>
                                <?php elseif ($p['user_attempted']): ?>
                                    <span class="status-icon status-attempted">○</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="editor.php?id=<?php echo $p['problem_id']; ?>" class="problem-title">
                                    <?php echo $p['problem_id']; ?>. <?php echo htmlspecialchars($p['title']); ?>
                                </a>
                            </td>
                            <td class="col-acceptance">
                                <?php echo number_format($p['acceptance_rate'] ?? 0, 1); ?>%
                            </td>
                            <td class="col-difficulty difficulty-<?php echo strtolower($p['difficulty']); ?>">
                                <?php echo $p['difficulty']; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?><?php echo $difficulty ? '&difficulty=' . $difficulty : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="page-link">← Previous</a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?page=<?php echo $i; ?><?php echo $difficulty ? '&difficulty=' . $difficulty : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                   class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php echo $difficulty ? '&difficulty=' . $difficulty : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="page-link">Next →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
