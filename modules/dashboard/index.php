<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Submission.php';
require_once __DIR__ . '/../../classes/Leaderboard.php';

Auth::requireLogin();

$userId = Auth::getUserId();
$user = new User();
$submission = new Submission();
$leaderboard = new Leaderboard();

$profile = $user->getProfile($userId);
$stats = $user->getDashboardStats($userId);
$submissionStats = $submission->getSubmissionStats($userId);
$recentSubmissions = $submission->getRecentSubmissions($userId, 5);
$myRank = $leaderboard->getUserRank($userId);

// Page config
$pageTitle = 'Dashboard - PlacementCode';
$additionalCSS = '
.welcome-card {
    background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
    border: 1px solid #3a3a3a;
    border-radius: 12px;
    padding: 40px;
    margin-bottom: 30px;
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.stat-card {
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    border-radius: 12px;
    padding: 25px;
    transition: transform 0.2s, border-color 0.2s;
}
.stat-card:hover { transform: translateY(-4px); border-color: #ffa116; }
.stat-icon { font-size: 2.5rem; margin-bottom: 15px; }
.stat-value { font-size: 2.5rem; font-weight: 700; color: #ffa116; margin-bottom: 8px; }
.stat-label { color: #a1a1aa; font-size: 0.95rem; }
.content-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
.card {
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    border-radius: 12px;
    padding: 25px;
}
.card h2 { margin-bottom: 20px; font-size: 1.3rem; }
.submission-item {
    padding: 15px;
    background: #0f0f0f;
    border-radius: 8px;
    margin-bottom: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.status-accepted { color: #22c55e; }
.status-wrong { color: #ef4444; }
.rank-badge {
    background: linear-gradient(135deg, #ffa116, #ff6b6b);
    color: #fff;
    padding: 15px 25px;
    border-radius: 12px;
    text-align: center;
    font-size: 1.8rem;
    font-weight: 700;
}
@media (max-width: 1024px) {
    .content-grid { grid-template-columns: 1fr; }
}
';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="container" style="max-width: 1400px; margin: 30px auto; padding: 0 20px;">
    
    <!-- Welcome Card -->
    <div class="welcome-card">
        <h1 style="font-size: 2.5rem; margin-bottom: 10px;">
            Welcome back, <?php echo htmlspecialchars($profile['full_name'] ?? 'Student'); ?>! 👋
        </h1>
        <p style="color: #a1a1aa; font-size: 1.1rem;">
            Ready to solve some problems today?
        </p>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">💻</div>
            <div class="stat-value"><?php echo $stats['problems_solved'] ?? 0; ?></div>
            <div class="stat-label">Problems Solved</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📝</div>
            <div class="stat-value"><?php echo $submissionStats['total_submissions'] ?? 0; ?></div>
            <div class="stat-label">Total Submissions</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?php echo $submissionStats['accepted'] ?? 0; ?></div>
            <div class="stat-label">Accepted Solutions</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🏆</div>
            <div class="stat-value">#<?php echo $myRank['rank'] ?? 'N/A'; ?></div>
            <div class="stat-label">Global Rank</div>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="content-grid">
        
        <!-- Recent Activity -->
        <div class="card">
            <h2>📊 Recent Submissions</h2>
            <?php if (empty($recentSubmissions)): ?>
                <p style="text-align: center; color: #71717a; padding: 40px 20px;">
                    No submissions yet. Start coding!
                </p>
            <?php else: ?>
                <?php foreach ($recentSubmissions as $sub): ?>
                    <div class="submission-item">
                        <div>
                            <div style="font-weight: 600; margin-bottom: 4px;">
                                <?php echo htmlspecialchars($sub['title']); ?>
                            </div>
                            <div style="color: #71717a; font-size: 0.85rem;">
                                <?php echo date('M j, Y • g:i A', strtotime($sub['submitted_at'])); ?>
                            </div>
                        </div>
                        <div class="<?php echo $sub['status'] === 'accepted' ? 'status-accepted' : 'status-wrong'; ?>" style="font-weight: 600;">
                            <?php echo ucfirst(str_replace('_', ' ', $sub['status'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <a href="../coding/submissions.php" style="display: block; text-align: center; margin-top: 20px; color: #ffa116; text-decoration: none; font-weight: 600;">
                View All Submissions →
            </a>
        </div>

        <!-- Quick Links -->
        <div>
            <div class="card" style="margin-bottom: 20px;">
                <h2>🚀 Quick Actions</h2>
                <a href="../coding/problems.php" class="submission-item" style="text-decoration: none; color: inherit;">
                    <span>Start Coding</span>
                    <span style="color: #ffa116;">→</span>
                </a>
                <a href="../aptitude/tests.php" class="submission-item" style="text-decoration: none; color: inherit;">
                    <span>Take Aptitude Test</span>
                    <span style="color: #ffa116;">→</span>
                </a>
                <a href="../community/index.php" class="submission-item" style="text-decoration: none; color: inherit;">
                    <span>Community Q&A</span>
                    <span style="color: #ffa116;">→</span>
                </a>
                <a href="../toolkit/resume-builder.php" class="submission-item" style="text-decoration: none; color: inherit;">
                    <span>Build Resume</span>
                    <span style="color: #ffa116;">→</span>
                </a>
            </div>
            
            <div class="card">
                <h2>🎯 Your Rank</h2>
                <div class="rank-badge">
                    #<?php echo $myRank['rank'] ?? 'N/A'; ?>
                </div>
                <p style="text-align: center; color: #a1a1aa; margin-top: 15px;">
                    Out of <?php echo $myRank['total_users'] ?? 0; ?> users
                </p>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
