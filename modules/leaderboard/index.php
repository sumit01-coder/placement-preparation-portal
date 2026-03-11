<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Leaderboard.php';

Auth::requireLogin();
$userId = Auth::getUserId();
$isAdminViewer = Auth::isAdmin();

$leaderboard = new Leaderboard();
$rankings = $leaderboard->getGlobalLeaderboard(100);
$myRank = $leaderboard->getUserRank($userId);

// Page config
$pageTitle = 'Leaderboard - PlacementCode';
$additionalCSS = '
.container { max-width: 1400px; margin: 0 auto; padding: 30px 20px; }

.header-section {
    text-align: center;
    margin-bottom: 40px;
}
.header-section h1 { font-size: 2.5rem; margin-bottom: 10px; color: #e4e4e7; }
.header-section p { color: #a1a1aa; font-size: 1.1rem; }

.my-rank-badge {
    display: inline-block;
    background: linear-gradient(135deg, #ffa116, #ff6b6b);
    color: #000;
    padding: 12px 30px;
    border-radius: 25px;
    font-weight: 700;
    font-size: 1.1rem;
    margin-top: 15px;
    box-shadow: 0 4px 15px rgba(255, 161, 22, 0.3);
}
.info-banner {
    max-width: 760px;
    margin: 18px auto 0;
    padding: 12px 16px;
    border-radius: 10px;
    background: rgba(56, 189, 248, 0.1);
    border: 1px solid rgba(56, 189, 248, 0.25);
    color: #bae6fd;
    font-size: 0.95rem;
}

.leaderboard-card {
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    border-radius: 12px;
    overflow: hidden;
}

table { width: 100%; border-collapse: collapse; }
thead { background: #0f0f0f; }

th {
    text-align: left;
    padding: 16px 20px;
    font-size: 0.85rem;
    color: #71717a;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

tbody tr {
    border-bottom: 1px solid #2a2a2a;
    transition: background 0.15s;
}
tbody tr:hover { background: #1f1f1f; }
tbody tr.highlight { 
    background: rgba(255, 161, 22, 0.1); 
    border-left: 3px solid #ffa116; 
}

td { padding: 18px 20px; font-size: 0.95rem; }

.rank-cell {
    font-size: 1.5rem;
    font-weight: 700;
    width: 80px;
    text-align: center;
}
.rank-1 { font-size: 1.8rem; }
.rank-2 { font-size: 1.8rem; }
.rank-3 { font-size: 1.8rem; }
.rank-other { color: #71717a; font-size: 1.1rem; }

.user-info { display: flex; flex-direction: column; }
.user-name { font-weight: 600; color: #e4e4e7; margin-bottom: 4px; font-size: 1rem; }
.user-handle { font-size: 0.85rem; color: #71717a; }

.difficulty-badges { display: flex; gap: 8px; flex-wrap: wrap; }
.diff-badge {
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    min-width: 40px;
    text-align: center;
}
.diff-easy { background: rgba(34, 197, 94, 0.15); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.2); }
.diff-medium { background: rgba(251, 191, 36, 0.15); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.2); }
.diff-hard { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }

.solved-count { font-size: 1.2rem; font-weight: 700; color: #e4e4e7; }
.total-subs { color: #a1a1aa; font-family: monospace; }

.acceptance-rate { font-weight: 600; padding: 4px 8px; border-radius: 6px; font-size: 0.85rem; }
.rate-high { color: #22c55e; background: rgba(34, 197, 94, 0.1); }
.rate-medium { color: #fbbf24; background: rgba(251, 191, 36, 0.1); }
.rate-low { color: #ef4444; background: rgba(239, 68, 68, 0.1); }

.empty-state {
    text-align: center; 
    padding: 80px 20px; 
    color: #71717a;
}
.empty-state h2 { color: #a1a1aa; margin-bottom: 10px; }

@media (max-width: 768px) {
    .difficulty-badges { display: none; }
    .header-section h1 { font-size: 2rem; }
    td, th { padding: 12px 10px; }
    .rank-cell { width: 50px; font-size: 1.2rem; }
    .user-info { max-width: 120px; }
    .user-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
}
';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="container">
    
    <div class="header-section">
        <h1>🏆 Global Leaderboard</h1>
        <p>Compete with the best coders worldwide</p>
        <?php if (!$isAdminViewer && $myRank && !empty($myRank['rank'])): ?>
            <div class="my-rank-badge">Your Rank: #<?php echo $myRank['rank'] ?? 'N/A'; ?></div>
        <?php endif; ?>
        <?php if ($isAdminViewer): ?>
            <div class="info-banner">Leaderboard rankings only include student accounts. Admin submissions are excluded from the global ranking.</div>
        <?php endif; ?>
    </div>

    <?php if (empty($rankings)): ?>
        <div class="empty-state">
            <h2><?php echo $isAdminViewer ? 'No Student Rankings Yet' : 'No Rankings Yet'; ?></h2>
            <p><?php echo $isAdminViewer ? 'Students will appear here after they start solving coding problems.' : 'Be the first to solve problems and top the leaderboard!'; ?></p>
        </div>
    <?php else: ?>
        <div class="leaderboard-card">
            <table>
                <thead>
                    <tr>
                        <th style="text-align: center;">Rank</th>
                        <th>User</th>
                        <th>Solved</th>
                        <th>Difficulty Split</th>
                        <th>Submissions</th>
                        <th>Acceptance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rankings as $index => $user): ?>
                    <tr class="<?php echo $user['user_id'] == $userId ? 'highlight' : ''; ?>">
                        <td class="rank-cell">
                            <?php 
                            $rank = $index + 1;
                            if ($rank == 1) {
                                echo '<span class="rank-1">🥇</span>';
                            } elseif ($rank == 2) {
                                echo '<span class="rank-2">🥈</span>';
                            } elseif ($rank == 3) {
                                echo '<span class="rank-3">🥉</span>';
                            } else {
                                echo '<span class="rank-other">#' . $rank . '</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <div class="user-info">
                                <span class="user-name"><?php echo htmlspecialchars($user['fullname'] ?? $user['username']); ?></span>
                                <span class="user-handle">@<?php echo htmlspecialchars($user['username']); ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="solved-count"><?php echo $user['problems_solved']; ?></span>
                        </td>
                        <td>
                            <div class="difficulty-badges">
                                <span class="diff-badge diff-easy" title="Easy">E: <?php echo $user['easy_solved']; ?></span>
                                <span class="diff-badge diff-medium" title="Medium">M: <?php echo $user['medium_solved']; ?></span>
                                <span class="diff-badge diff-hard" title="Hard">H: <?php echo $user['hard_solved']; ?></span>
                            </div>
                        </td>
                        <td class="total-subs"><?php echo $user['total_submissions']; ?></td>
                        <td>
                            <?php 
                            $rate = $user['acceptance_rate'];
                            $class = $rate >= 60 ? 'rate-high' : ($rate >= 40 ? 'rate-medium' : 'rate-low');
                            ?>
                            <span class="acceptance-rate <?php echo $class; ?>">
                                <?php echo number_format($rate, 1); ?>%
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
