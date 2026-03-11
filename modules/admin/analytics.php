<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Admin.php';

Auth::requireAdmin();

$admin = new Admin();
$submissionData = $admin->getSubmissionAnalytics(30);
$userGrowth = $admin->getUserGrowth(30);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Admin Panel</title>
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
        
        .header-section { margin-bottom: 30px; }
        .header-section h1 { font-size: 2rem; margin-bottom: 8px; }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
        }
        
        .chart-card {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            padding: 25px;
        }
        .chart-card h3 { margin-bottom: 20px; font-size: 1.1rem; }
        .chart-container { position: relative; height: 300px; }
    </style>
</head>
<body>

    <nav class="top-nav">
        <div class="logo">⚙️ Admin Panel</div>
        <div class="nav-menu">
            <a href="dashboard.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="problems.php">Problems</a>
            <a href="tests.php">Tests</a>
            <a href="company-drives.php">Company Drives</a>
            <a href="analytics.php" class="active">Analytics</a>
        </div>
        <div style="color: #71717a; font-size: 0.9rem;">
            <a href="../dashboard/index.php" style="color: #ef4444; text-decoration: none;">Exit Admin →</a>
        </div>
    </nav>

    <div class="container">
        
        <div class="header-section">
            <h1>📊 Platform Analytics</h1>
            <p style="color: #a1a1aa;">Monitor platform performance and user engagement</p>
        </div>

        <div class="charts-grid">
            
            <!-- Submission Trends -->
            <div class="chart-card">
                <h3>📝 Submission Trends (Last 30 Days)</h3>
                <div class="chart-container">
                    <canvas id="submissionsChart"></canvas>
                </div>
            </div>

            <!-- User Growth -->
            <div class="chart-card">
                <h3>👥 User Growth (Last 30 Days)</h3>
                <div class="chart-container">
                    <canvas id="userGrowthChart"></canvas>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Submission Trends Chart
        const submissionCtx = document.getElementById('submissionsChart').getContext('2d');
        new Chart(submissionCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($submissionData, 'date')); ?>,
                datasets: [{
                    label: 'Total Submissions',
                    data: <?php echo json_encode(array_column($submissionData, 'total')); ?>,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Accepted',
                    data: <?php echo json_encode(array_column($submissionData, 'accepted')); ?>,
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: '#e4e4e7' } }
                },
                scales: {
                    y: { ticks: { color: '#a1a1aa' }, grid: { color: '#2a2a2a' } },
                    x: { ticks: { color: '#a1a1aa' }, grid: { color: '#2a2a2a' } }
                }
            }
        });

        // User Growth Chart
        const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
        new Chart(userGrowthCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($userGrowth, 'date')); ?>,
                datasets: [{
                    label: 'New Users',
                    data: <?php echo json_encode(array_column($userGrowth, 'new_users')); ?>,
                    backgroundColor: '#ef4444',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: '#e4e4e7' } }
                },
                scales: {
                    y: { ticks: { color: '#a1a1aa' }, grid: { color: '#2a2a2a' } },
                    x: { ticks: { color: '#a1a1aa' }, grid: { color: '#2a2a2a' } }
                }
            }
        });
    </script>

</body>
</html>
