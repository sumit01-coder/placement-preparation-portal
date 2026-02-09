<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Admin.php';

Auth::requireLogin();

// Check if user is admin (role_id = 2)
$userId = Auth::getUserId();
$db = Database::getInstance();
$userRole = $db->fetchOne("SELECT role_id FROM users WHERE user_id = :uid", ['uid' => $userId])['role_id'];

if ($userRole != 2) {
    header('Location: ../../modules/dashboard/index.php');
    exit();
}

$admin = new Admin();
$stats = $admin->getDashboardStats();
$recentActivity = $admin->getRecentActivity(10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - PlacementCode</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: #0a0a0a;
            color: #e4e4e7;
            min-height: 100vh;
        }
        
        /* Top Navbar */
        .top-nav {
            background: #1a1a1a;
            border-bottom: 1px solid #2a2a2a;
            padding: 12px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo { font-size: 1.3rem; font-weight: 700; color: #ef4444; }
        .nav-menu { display: flex; gap: 25px; }
        .nav-menu a { color: #a1a1aa; text-decoration: none; font-size: 0.95rem; font-weight: 500; transition: color 0.2s; }
        .nav-menu a:hover, .nav-menu a.active { color: #fff; }
        
        .container { max-width: 1600px; margin: 0 auto; padding: 30px 20px; }
        
        .header-section {
            margin-bottom: 30px;
        }
        .header-section h1 { font-size: 2rem; margin-bottom: 8px; }
        .header-section p { color: #a1a1aa; }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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
        .stat-card:hover { transform: translateY(-4px); border-color: #ef4444; }
        .stat-icon { font-size: 2rem; margin-bottom: 12px; }
        .stat-number { font-size: 2.5rem; font-weight: 700; color: #ef4444; margin-bottom: 8px; }
        .stat-label { color: #a1a1aa; font-size: 0.9rem; }
        .stat-change { font-size: 0.85rem; color: #22c55e; margin-top: 8px; }
        
        /* Two Column Layout */
        .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        
        /* Activity Card */
        .activity-card {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            padding: 25px;
        }
        .activity-card h3 { font-size: 1.2rem; margin-bottom: 20px; }
        
        .activity-item {
            padding: 15px;
            background: #0f0f0f;
            border-radius: 8px;
            margin-bottom: 12px;
            font-size: 0.9rem;
            color: #a1a1aa;
        }
        .activity-item:last-child { margin-bottom: 0; }
        .activity-time { color: #71717a; font-size: 0.75rem; }
        
        /* Quick Actions */
        .actions-card {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            padding: 25px;
        }
        .actions-card h3 { font-size: 1.1rem; margin-bottom: 15px; }
        
        .action-btn {
            display: block;
            width: 100%;
            background: #2a2a2a;
            border: 1px solid #3a3a3a;
            color: #e4e4e7;
            padding: 15px;
            border-radius: 8px;
            text-decoration: none;
            margin-bottom: 10px;
            transition: all 0.2s;
            font-weight: 500;
        }
        .action-btn:hover { background: #ef4444; color: #fff; border-color: #ef4444; }
        
        @media (max-width: 1024px) {
            .dashboard-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <nav class="top-nav">
        <div class="logo">⚙️ Admin Panel</div>
        <div class="nav-menu">
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="problems.php">Problems</a>
            <a href="tests.php">Tests</a>
            <a href="analytics.php">Analytics</a>
        </div>
        <div style="color: #71717a; font-size: 0.9rem;">
            <a href="../dashboard/index.php" style="color: #ef4444; text-decoration: none;">Exit Admin →</a>
        </div>
    </nav>

    <div class="container">
        
        <div class="header-section">
            <h1>🎛️ Admin Dashboard</h1>
            <p>Monitor platform metrics and manage content</p>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-number"><?php echo number_format($stats['total_users']); ?></div>
                <div class="stat-label">Total Students</div>
                <div class="stat-change">↑ <?php echo $stats['active_users']; ?> active (7 days)</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💻</div>
                <div class="stat-number"><?php echo number_format($stats['total_problems']); ?></div>
                <div class="stat-label">Coding Problems</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📝</div>
                <div class="stat-number"><?php echo number_format($stats['recent_submissions']); ?></div>
                <div class="stat-label">Submissions (30d)</div>
                <div class="stat-change">↑ <?php echo $stats['today_submissions']; ?> today</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-number"><?php echo number_format($stats['total_tests']); ?></div>
                <div class="stat-label">Aptitude Tests</div>
            </div>
        </div>

        <!-- Two Column Layout -->
        <div class="dashboard-grid">
            
            <!-- Recent Activity -->
            <div class="activity-card">
                <h3>📊 Recent Activity</h3>
                <?php if (empty($recentActivity)): ?>
                    <div style="text-align: center; padding: 40px; color: #71717a;">
                        No recent activity
                    </div>
                <?php else: ?>
                    <?php foreach ($recentActivity as $activity): ?>
                        <div class="activity-item">
                            <strong><?php echo htmlspecialchars($activity['full_name'] ?? 'User'); ?></strong> 
                            <?php echo htmlspecialchars($activity['activity_type'] ?? 'performed an action'); ?>
                            <div class="activity-time"><?php echo date('M j, g:i A', strtotime($activity['activity_time'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div>
                <div class="actions-card">
                    <h3>⚡ Quick Actions</h3>
                    <a href="users.php" class="action-btn">👥 Manage Users</a>
                    <a href="problems.php" class="action-btn">💻 Manage Problems</a>
                    <a href="tests.php" class="action-btn">📚 Manage Tests</a>
                    <a href="analytics.php" class="action-btn">📊 View Analytics</a>
                </div>
            </div>

        </div>
    </div>

</body>
</html>
