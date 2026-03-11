<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Aptitude.php';

Auth::requireLogin();

$userId = Auth::getUserId();
$aptitude = new Aptitude();
$tests = $aptitude->getTests();
$userAttempts = $aptitude->getUserHistory($userId, 5);

$requestedCategory = strtolower(trim((string)($_GET['category'] ?? 'all')));

$toSlug = static function ($value) {
    $slug = strtolower((string)$value);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim((string)$slug, '-');
    return $slug !== '' ? $slug : 'general';
};

$categories = [];
foreach ($tests as $test) {
    $categoryName = trim((string)($test['category'] ?? 'General'));
    $categorySlug = $toSlug($categoryName);
    $categories[$categorySlug] = $categoryName;
}
ksort($categories);

if ($requestedCategory !== 'all' && !isset($categories[$requestedCategory])) {
    $requestedCategory = 'all';
}

$pageTitle = 'Aptitude Tests - PlacementCode';
$additionalCSS = '
.container { max-width: 1400px; margin: 0 auto; padding: 30px 20px; }
.header-section { margin-bottom: 30px; }
.header-section h1 { font-size: 2rem; margin-bottom: 8px; color: #e4e4e7; }
.header-section p { color: #a1a1aa; font-size: 1rem; }

.category-tabs { display: flex; gap: 10px; margin-bottom: 30px; flex-wrap: wrap; }
.category-tab {
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    padding: 12px 20px;
    border-radius: 8px;
    color: #a1a1aa;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 500;
    font-family: inherit;
}
.category-tab:hover { background: #2a2a2a; color: #fff; }
.category-tab.active { background: #ffa116; color: #000; border-color: #ffa116; }

.tests-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; margin-bottom: 40px; }
.test-card {
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    border-radius: 12px;
    padding: 25px;
    transition: transform 0.2s, border-color 0.2s;
}
.test-card:hover { transform: translateY(-4px); border-color: #ffa116; }
.test-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; gap: 10px; }
.test-title { font-size: 1.3rem; font-weight: 600; color: #e4e4e7; margin-bottom: 8px; }
.test-desc { color: #a1a1aa; margin: 0 0 12px; font-size: 0.92rem; line-height: 1.5; }

.category-badge {
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    background: rgba(255, 161, 22, 0.15);
    color: #ffa116;
}

.test-stats {
    display: flex;
    gap: 18px;
    margin: 15px 0;
    padding: 15px 0;
    border-top: 1px solid #2a2a2a;
    border-bottom: 1px solid #2a2a2a;
}
.stat-item { display: flex; flex-direction: column; }
.stat-value { font-size: 1.1rem; font-weight: 700; color: #ffa116; }
.stat-label { font-size: 0.75rem; color: #71717a; margin-top: 4px; text-transform: uppercase; }

.btn-start {
    width: 100%;
    background: linear-gradient(135deg, #ffa116, #ff6b6b);
    color: #fff;
    padding: 12px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.2s;
    font-family: inherit;
    font-size: 0.95rem;
}
.btn-start:hover { opacity: 0.9; }

.empty-state { grid-column: 1/-1; text-align: center; padding: 80px 20px; color: #71717a; }
.empty-state h3 { margin-bottom: 8px; color: #a1a1aa; }

.history-section {
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    border-radius: 12px;
    padding: 25px;
}
.history-section h2 { font-size: 1.4rem; margin-bottom: 20px; color: #e4e4e7; }
.history-table { width: 100%; border-collapse: collapse; }
.history-table thead { background: #0f0f0f; }
.history-table th {
    text-align: left;
    padding: 12px 16px;
    font-size: 0.85rem;
    color: #71717a;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.history-table tbody tr { border-top: 1px solid #2a2a2a; transition: background 0.2s; }
.history-table tbody tr:hover { background: #0f0f0f; }
.history-table td { padding: 14px 16px; font-size: 0.95rem; }
.td-muted { color: #a1a1aa; }
.score-badge {
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}
.score-high { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
.score-medium { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
.score-low { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

@media (max-width: 768px) {
    .tests-grid { grid-template-columns: 1fr; }
    .category-tabs { flex-direction: column; }
    .history-table th, .history-table td { padding: 10px 8px; }
}
';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="container">
    <div class="header-section">
        <h1>Aptitude Tests</h1>
        <p>Practice tests built by the admin panel and track your recent scores.</p>
    </div>

    <div class="category-tabs">
        <button type="button" class="category-tab<?php echo $requestedCategory === 'all' ? ' active' : ''; ?>" onclick="filterTests('all', this)">All Tests</button>
        <?php foreach ($categories as $slug => $name): ?>
            <button type="button" class="category-tab<?php echo $requestedCategory === $slug ? ' active' : ''; ?>" onclick="filterTests('<?php echo htmlspecialchars($slug); ?>', this)">
                <?php echo htmlspecialchars($name); ?>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="tests-grid" id="testsGrid">
        <?php if (empty($tests)): ?>
            <div class="empty-state">
                <h3>No tests available</h3>
                <p>Ask admin to create a test and add questions.</p>
            </div>
        <?php else: ?>
            <?php foreach ($tests as $test): ?>
                <?php
                $category = trim((string)($test['category'] ?? 'General'));
                $categorySlug = $toSlug($category);
                $questionCount = (int)($test['question_count'] ?? $test['total_questions'] ?? 0);
                $duration = (int)($test['duration_minutes'] ?? 0);
                $difficulty = trim((string)($test['difficulty'] ?? 'Medium'));
                $description = trim((string)($test['description'] ?? ''));
                ?>
                <div class="test-card" data-category="<?php echo htmlspecialchars($categorySlug); ?>">
                    <div class="test-header">
                        <div>
                            <div class="test-title"><?php echo htmlspecialchars($test['test_name'] ?? 'Untitled Test'); ?></div>
                            <span class="category-badge"><?php echo htmlspecialchars($category); ?></span>
                        </div>
                    </div>

                    <?php if ($description !== ''): ?>
                        <p class="test-desc"><?php echo htmlspecialchars($description); ?></p>
                    <?php endif; ?>

                    <div class="test-stats">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $questionCount; ?></span>
                            <span class="stat-label">Questions</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $duration; ?></span>
                            <span class="stat-label">Minutes</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo htmlspecialchars($difficulty); ?></span>
                            <span class="stat-label">Level</span>
                        </div>
                    </div>

                    <button class="btn-start" onclick="startTest(<?php echo (int)$test['test_id']; ?>)">Start Test</button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if (!empty($userAttempts)): ?>
        <div class="history-section">
            <h2>Recent Attempts</h2>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Test Name</th>
                        <th>Category</th>
                        <th>Score</th>
                        <th>Time Taken</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($userAttempts as $attempt): ?>
                        <?php
                        $attemptCategory = trim((string)($attempt['category'] ?? 'General'));
                        $score = (int)($attempt['score'] ?? 0);
                        $total = (int)($attempt['total_questions'] ?? $attempt['total_marks'] ?? 0);
                        if ($total <= 0) {
                            $total = 1;
                        }
                        $percentage = isset($attempt['percentage']) ? (float)$attempt['percentage'] : round(($score / $total) * 100, 2);
                        $scoreClass = $percentage >= 70 ? 'score-high' : ($percentage >= 50 ? 'score-medium' : 'score-low');
                        $timeTaken = isset($attempt['time_taken']) ? (int)$attempt['time_taken'] : (int)round(((int)($attempt['duration_seconds'] ?? 0)) / 60);
                        $attemptedAt = $attempt['attempted_at'] ?? null;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($attempt['test_name'] ?? 'Unknown Test'); ?></td>
                            <td><span class="category-badge"><?php echo htmlspecialchars($attemptCategory); ?></span></td>
                            <td>
                                <span class="score-badge <?php echo $scoreClass; ?>">
                                    <?php echo $score; ?>/<?php echo $total; ?> (<?php echo round($percentage); ?>%)
                                </span>
                            </td>
                            <td class="td-muted"><?php echo $timeTaken; ?> min</td>
                            <td class="td-muted">
                                <?php echo $attemptedAt ? htmlspecialchars(date('M j, Y', strtotime($attemptedAt))) : '-'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function filterTests(category, buttonEl) {
    document.querySelectorAll('.category-tab').forEach(function(tab) {
        tab.classList.remove('active');
    });
    if (buttonEl) {
        buttonEl.classList.add('active');
    }

    document.querySelectorAll('.test-card').forEach(function(card) {
        card.style.display = (category === 'all' || card.dataset.category === category) ? 'block' : 'none';
    });
}

function startTest(testId) {
    window.location.href = 'take-test.php?id=' + encodeURIComponent(testId);
}

(function initCategoryFilter() {
    const initialCategory = <?php echo json_encode($requestedCategory); ?>;
    if (initialCategory === 'all') {
        return;
    }

    const activeBtn = document.querySelector('.category-tab.active');
    filterTests(initialCategory, activeBtn);
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
