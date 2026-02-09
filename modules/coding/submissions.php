<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Submission.php';

Auth::requireLogin();
$userId = Auth::getUserId();

$submission = new Submission();
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$submissions = $submission->getUserSubmissions($userId, $limit, $offset);
$stats = $submission->getSubmissionStats($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Submissions - PlacementCode</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
        .logo { font-size: 1.3rem; font-weight: 700; color: #ffa116; }
        .nav-menu { display: flex; gap: 25px; }
        .nav-menu a { color: #a1a1aa; text-decoration: none; font-size: 0.95rem; font-weight: 500; transition: color 0.2s; }
        .nav-menu a:hover, .nav-menu a.active { color: #fff; }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 30px 20px; }
        
        .header-section {
            margin-bottom: 30px;
        }
        .header-section h1 { font-size: 2rem; margin-bottom: 10px; }
        .header-section p { color: #a1a1aa; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .stat-card {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        .stat-value { font-size: 2rem; font-weight: 700; color: #ffa116; margin-bottom: 8px; }
        .stat-label { color: #a1a1aa; font-size: 0.85rem; }
        
        .submissions-card {
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
        }
        
        tbody tr {
            border-bottom: 1px solid #2a2a2a;
            transition: background 0.15s;
        }
        tbody tr:hover {background: #1f1f1f; }
        
        td { padding: 16px 20px; font-size: 0.95rem; }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .status-accepted { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
        .status-wrong_answer { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .status-time_limit_exceeded { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
        .status-runtime_error { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .status-compilation_error { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        
        .problem-link { color: #e4e4e7; text-decoration: none; font-weight: 500; }
        .problem-link:hover { color: #ffa116; }
        
        .difficulty-badge {
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 4px;
            display: inline-block;
        }
        .diff-easy { color: #22c55e; }
        .diff-medium { color: #fbbf24; }
        .diff-hard { color: #ef4444; }
        
        .view-btn {
            color: #ffa116;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.85rem;
        }
        .view-btn:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <nav class="top-nav">
        <div class="logo">⚡ PlacementCode</div>
        <div class="nav-menu">
            <a href="../dashboard/index.php">Dashboard</a>
            <a href="problems.php">Problems</a>
            <a href="submissions.php" class="active">Submissions</a>
            <a href="../focus/analytics.php">Analytics</a>
        </div>
    </nav>

    <div class="container">
        
        <div class="header-section">
            <h1>📊 Submission History</h1>
            <p>Track your coding progress and performance</p>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_submissions'] ?? 0; ?></div>
                    <div class="stat-label">Total Submissions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['accepted'] ?? 0; ?></div>
                    <div class="stat-label">Accepted</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['wrong_answer'] ?? 0; ?></div>
                    <div class="stat-label">Wrong Answer</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['tle'] ?? 0; ?></div>
                    <div class="stat-label">Time Limit</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['avg_runtime'] ?? 0, 2); ?>s</div>
                    <div class="stat-label">Avg Runtime</div>
                </div>
            </div>
        </div>

        <?php if (empty($submissions)): ?>
            <div style="text-align: center; padding: 80px 20px; color: #71717a;">
                <h2 style="color: #a1a1aa;">No Submissions Yet</h2>
                <p>Start solving problems to build your submission history!</p>
                <a href="problems.php" style="color: #ffa116; text-decoration: none; margin-top: 15px; display: inline-block;">Browse Problems →</a>
            </div>
        <?php else: ?>
            <div class="submissions-card">
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Problem</th>
                            <th>Status</th>
                            <th>Language</th>
                            <th>Runtime</th>
                            <th>Memory</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $sub): ?>
                        <tr>
                            <td style="color: #71717a; font-size: 0.85rem;">
                                <?php echo date('M d, H:i', strtotime($sub['submitted_at'])); ?>
                            </td>
                            <td>
                                <a href="editor.php?id=<?php echo $sub['problem_id']; ?>" class="problem-link">
                                    <?php echo htmlspecialchars($sub['title']); ?>
                                </a>
                                <div class="difficulty-badge diff-<?php echo strtolower($sub['difficulty']); ?>">
                                    <?php echo $sub['difficulty']; ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $sub['status']; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $sub['status'])); ?>
                                </span>
                                <?php if ($sub['status'] != 'accepted'): ?>
                                    <div style="font-size: 0.75rem; color: #71717a; margin-top: 4px;">
                                        <?php echo $sub['passed_tests'] . '/' . $sub['total_tests']; ?> tests
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="color: #a1a1aa;"><?php echo htmlspecialchars($sub['language_name']); ?></td>
                            <td style="color: #a1a1aa;"><?php echo number_format($sub['runtime'] ?? 0, 3); ?>s</td>
                            <td style="color: #a1a1aa;"><?php echo number_format($sub['memory_used'] ?? 0); ?> KB</td>
                            <td>
                                <a href="view-submission.php?id=<?php echo $sub['submission_id']; ?>" class="view-btn">
                                    View →
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
