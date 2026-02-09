<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Community.php';

Auth::requireLogin();
$userId = Auth::getUserId();

$community = new Community();

$filter = $_GET['filter'] ?? 'recent';
$tag = $_GET['tag'] ?? null;
$search = $_GET['search'] ?? null;

$questions = $community->getQuestions($filter, $tag, $search);
$popularTags = $community->getPopularTags(10);

// Page config
$pageTitle = 'Community Q&A - PlacementCode';
$additionalCSS = '
.container { max-width: 1400px; margin: 0 auto; padding: 30px 20px; }

.header-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 20px;
}
.header-section h1 { font-size: 2rem; margin-bottom: 8px; color: #e4e4e7; }
.header-section p { color: #a1a1aa; font-size: 1rem; }

.btn-ask {
    background: linear-gradient(135deg, #ffa116, #ff6b6b);
    color: #fff;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: opacity 0.2s;
}
.btn-ask:hover { opacity: 0.9; }

.search-bar {
    margin-bottom: 20px;
}
.search-input {
    width: 100%;
    max-width: 500px;
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    padding: 12px 20px;
    border-radius: 8px;
    color: #e4e4e7;
    font-size: 0.95rem;
    font-family: inherit;
}
.search-input:focus { outline: none; border-color: #ffa116; }

.filters {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.filter-btn {
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    padding: 10px 20px;
    border-radius: 8px;
    color: #a1a1aa;
    text-decoration: none;
    transition: all 0.2s;
    font-weight: 500;
}
.filter-btn:hover { background: #2a2a2a; color: #fff; }
.filter-btn.active { background: #ffa116; color: #000; border-color: #ffa116; }

.content-grid { display: grid; grid-template-columns: 1fr 300px; gap: 30px; }

.question-card {
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    transition: transform 0.2s, border-color 0.2s;
}
.question-card:hover { transform: translateX(4px); border-color: #3a3a3a; }

.question-stats {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-right: 20px;
    min-width: 60px;
}
.stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
}
.stat-number { font-size: 1.1rem; font-weight: 700; color: #e4e4e7; }
.stat-label { font-size: 0.75rem; color: #71717a; }
.stat-votes { color: #ffa116; }

.question-body { flex: 1; }

.question-title {
    font-size: 1.2rem;
    font-weight: 600;
    margin-bottom: 8px;
    line-height: 1.4;
}
.question-link { color: #e4e4e7; text-decoration: none; transition: color 0.2s; }
.question-link:hover { color: #ffa116; }

.solved-badge {
    background: rgba(34, 197, 94, 0.15);
    color: #22c55e;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 8px;
    vertical-align: middle;
}

.question-excerpt {
    color: #a1a1aa;
    font-size: 0.9rem;
    margin-bottom: 12px;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.question-tags {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
.tag {
    background: rgba(255, 161, 22, 0.15);
    color: #ffa116;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.2s;
}
.tag:hover { background: rgba(255, 161, 22, 0.25); }

.question-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.85rem;
    color: #71717a;
}
.meta-user { color: #e4e4e7; font-weight: 500; }

.sidebar-card {
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}
.sidebar-card h3 { font-size: 1.1rem; margin-bottom: 15px; color: #e4e4e7; }

.tag-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.tag-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: #0f0f0f;
    border-radius: 6px;
    transition: background 0.2s;
}
.tag-item:hover { background: #1f1f1f; }
.tag-link { color: #ffa116; text-decoration: none; font-size: 0.9rem; font-weight: 500; }
.tag-count { color: #71717a; font-size: 0.8rem; }

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #71717a;
    background: #1a1a1a;
    border-radius: 12px;
    border: 1px solid #2a2a2a;
}

@media (max-width: 1024px) {
    .content-grid { grid-template-columns: 1fr; }
    .question-stats { flex-direction: row; margin-right: 0; margin-bottom: 15px; }
    .question-card > div { flex-direction: column; }
    .sidebar-card { display: none; } /* Optional: hide sidebar on mobile or move to bottom */
}
';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="container">
    
    <div class="header-section">
        <div>
            <h1>💬 Community Q&A</h1>
            <p>Ask questions, share knowledge, help others</p>
        </div>
        <a href="ask-question.php" class="btn-ask">Ask Question</a>
    </div>

    <!-- Search -->
    <div class="search-bar">
        <form method="GET">
            <input type="text" name="search" class="search-input" placeholder="Search questions..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
        </form>
    </div>

    <!-- Filters -->
    <div class="filters">
        <a href="?filter=recent" class="filter-btn <?php echo $filter === 'recent' ? 'active' : ''; ?>">Recent</a>
        <a href="?filter=popular" class="filter-btn <?php echo $filter === 'popular' ? 'active' : ''; ?>">Popular</a>
        <a href="?filter=unanswered" class="filter-btn <?php echo $filter === 'unanswered' ? 'active' : ''; ?>">Unanswered</a>
        <a href="?filter=solved" class="filter-btn <?php echo $filter === 'solved' ? 'active' : ''; ?>">Solved</a>
    </div>

    <div class="content-grid">
        
        <!-- Questions List -->
        <div>
            <?php if (empty($questions)): ?>
                <div class="empty-state">
                    <h3>No questions found</h3>
                    <p>Be the first to ask a question!</p>
                </div>
            <?php else: ?>
                <?php foreach ($questions as $q): ?>
                    <div class="question-card">
                        <div style="display: flex;">
                            <div class="question-stats">
                                <div class="stat-item">
                                    <div class="stat-number stat-votes"><?php echo $q['votes']; ?></div>
                                    <div class="stat-label">votes</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $q['answer_count']; ?></div>
                                    <div class="stat-label">answers</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $q['views']; ?></div>
                                    <div class="stat-label">views</div>
                                </div>
                            </div>
                            
                            <div class="question-body">
                                <div class="question-title">
                                    <a href="question.php?id=<?php echo $q['question_id']; ?>" class="question-link">
                                        <?php echo htmlspecialchars($q['title']); ?>
                                    </a>
                                    <?php if ($q['is_solved']): ?>
                                        <span class="solved-badge">✓ Solved</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="question-excerpt">
                                    <?php echo htmlspecialchars(substr($q['content'], 0, 200)) . '...'; ?>
                                </div>
                                
                                <div class="question-tags">
                                    <?php foreach (($q['tags'] ?? []) as $tag): ?>
                                        <a href="?tag=<?php echo urlencode($tag); ?>" class="tag"><?php echo htmlspecialchars($tag); ?></a>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="question-meta">
                                    <span>Asked by <span class="meta-user"><?php echo htmlspecialchars($q['full_name'] ?? 'Anonymous'); ?></span></span>
                                    <span><?php echo date('M j, Y', strtotime($q['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div>
            <div class="sidebar-card">
                <h3>🏆 Top Contributors</h3>
                <p style="color: #71717a; font-size: 0.85rem;">Coming soon...</p>
            </div>
            
            <div class="sidebar-card">
                <h3>🏷️ Popular Tags</h3>
                <div class="tag-list">
                    <?php foreach ($popularTags as $tag): ?>
                        <div class="tag-item">
                            <a href="?tag=<?php echo urlencode($tag['tag']); ?>" class="tag-link">
                                <?php echo htmlspecialchars($tag['tag']); ?>
                            </a>
                            <span class="tag-count"><?php echo $tag['count']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
