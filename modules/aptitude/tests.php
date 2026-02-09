<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';

Auth::requireLogin();
$userId = Auth::getUserId();
$db = Database::getInstance();

// Get all available tests
$tests = $db->fetchAll("SELECT * FROM aptitude_tests ORDER BY created_at DESC");

// Get user's test history
$userAttempts = $db->fetchAll(
    "SELECT ta.*, at.test_name, at.category, at.duration_minutes
     FROM test_attempts ta
     JOIN aptitude_tests at ON ta.test_id = at.test_id
     WHERE ta.user_id = :uid
     ORDER BY ta.attempted_at DESC
     LIMIT 5",
    ['uid' => $userId]
);

// Page config
$pageTitle = 'Aptitude Tests - PlacementCode';
$additionalCSS = '
.container { max-width: 1400px; margin: 0 auto; padding: 30px 20px; }

.header-section { margin-bottom: 30px; }
.header-section h1 { font-size: 2rem; margin-bottom: 8px; color: #e4e4e7; }
.header-section p { color: #a1a1aa; font-size: 1rem; }

.category-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.category-tab {
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    padding: 12px 24px;
    border-radius: 8px;
    color: #a1a1aa;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 500;
    font-family: inherit;
}

.category-tab:hover { background: #2a2a2a; color: #fff; }
.category-tab.active { background: #ffa116; color: #000; border-color: #ffa116; }

.tests-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.test-card {
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    border-radius: 12px;
    padding: 25px;
    transition: transform 0.2s, border-color 0.2s;
}

.test-card:hover {
    transform: translateY(-4px);
    border-color: #ffa116;
}

.test-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 15px;
}

.test-title {
    font-size: 1.3rem;
    font-weight: 600;
    color: #e4e4e7;
    margin-bottom: 8px;
}

.category-badge {
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.cat-quantitative { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.cat-logical { background: rgba(168, 85, 247, 0.15); color: #a855f7; }
.cat-verbal { background: rgba(34, 197, 94, 0.15); color: #22c55e; }

.test-stats {
    display: flex;
    gap: 20px;
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

.empty-state {
    grid-column: 1/-1;
    text-align: center;
    padding: 80px 20px;
    color: #71717a;
}

.empty-state h3 { margin-bottom: 8px; color: #a1a1aa; }

.history-section {
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    border-radius: 12px;
    padding: 25px;
}

.history-section h2 { font-size: 1.4rem; margin-bottom: 20px; color: #e4e4e7; }

.history-table {
    width: 100%;
    border-collapse: collapse;
}

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

.history-table tbody tr {
    border-top: 1px solid #2a2a2a;
    transition: background 0.2s;
}

.history-table tbody tr:hover { background: #0f0f0f; }

.history-table td {
    padding: 14px 16px;
    font-size: 0.95rem;
}

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
    .tests-grid {
        grid-template-columns: 1fr;
    }
    
    .category-tabs {
        flex-direction: column;
    }
    
    .history-table {
        font-size: 0.85rem;
    }
    
    .history-table th, .history-table td {
        padding: 10px 8px;
    }
}
';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="container">
    
    <div class="header-section">
        <h1>📚 Aptitude Tests</h1>
        <p>Practice aptitude tests to improve your problem-solving skills</p>
    </div>

    <!-- Category Filters -->
    <div class="category-tabs">
        <div class="category-tab active" onclick="filterTests('all')">All Tests</div>
        <div class="category-tab" onclick="filterTests('quantitative')">Quantitative</div>
        <div class="category-tab" onclick="filterTests('logical')">Logical Reasoning</div>
        <div class="category-tab" onclick="filterTests('verbal')">Verbal Ability</div>
    </div>

    <!-- Tests Grid -->
    <div class="tests-grid">
        <?php if (empty($tests)): ?>
            <div class="empty-state">
                <h3>No tests available</h3>
                <p>Check back later for new tests</p>
            </div>
        <?php else: ?>
            <?php foreach ($tests as $test): ?>
                <div class="test-card" data-category="<?php echo strtolower($test['category']); ?>">
                    <div class="test-header">
                        <div>
                            <div class="test-title"><?php echo htmlspecialchars($test['test_name']); ?></div>
                            <span class="category-badge cat-<?php echo strtolower($test['category']); ?>">
                                <?php echo $test['category']; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="test-stats">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $test['total_questions'] ?? 20; ?></span>
                            <span class="stat-label">Questions</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $test['duration_minutes']; ?></span>
                            <span class="stat-label">Minutes</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $test['difficulty'] ?? 'Medium'; ?></span>
                            <span class="stat-label">Level</span>
                        </div>
                    </div>
                    
                    <button class="btn-start" onclick="startTest(<?php echo $test['test_id']; ?>)">
                        Start Test →
                    </button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Test History -->
    <?php if (!empty($userAttempts)): ?>
        <div class="history-section">
            <h2>📊 Recent Attempts</h2>
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
                        <tr>
                            <td><?php echo htmlspecialchars($attempt['test_name']); ?></td>
                            <td>
                                <span class="category-badge cat-<?php echo strtolower($attempt['category']); ?>">
                                    <?php echo $attempt['category']; ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $percentage = ($attempt['score'] / $attempt['total_questions']) * 100;
                                $scoreClass = $percentage >= 70 ? 'score-high' : ($percentage >= 50 ? 'score-medium' : 'score-low');
                                ?>
                                <span class="score-badge <?php echo $scoreClass; ?>">
                                    <?php echo $attempt['score']; ?>/<?php echo $attempt['total_questions']; ?>
                                    (<?php echo round($percentage); ?>%)
                                </span>
                            </td>
                            <td class="td-muted"><?php echo $attempt['time_taken']; ?> min</td>
                            <td class="td-muted"><?php echo date('M j, Y', strtotime($attempt['attempted_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<script>
function filterTests(category) {
    // Update active tab
    document.querySelectorAll('.category-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Filter cards
    document.querySelectorAll('.test-card').forEach(card => {
        if (category === 'all' || card.dataset.category === category) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

function startTest(testId) {
    window.location.href = `take-test.php?id=${testId}`;
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
