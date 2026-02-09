<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';

Auth::requireLogin();

// Check if user is admin
$userId = Auth::getUserId();
$db = Database::getInstance();
$userRole = $db->fetchOne("SELECT role_id FROM users WHERE user_id = :uid", ['uid' => $userId])['role_id'];

if ($userRole != 2) {
    header('Location: ../../modules/dashboard/index.php');
    exit();
}

// Get all aptitude tests
$tests = $db->fetchAll("SELECT * FROM aptitude_tests ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Management - Admin Panel</title>
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
        .logo { font-size: 1.3rem; font-weight: 700; color: #ef4444; }
        .nav-menu { display: flex; gap: 25px; }
        .nav-menu a { color: #a1a1aa; text-decoration: none; font-size: 0.95rem; font-weight: 500; transition: color 0.2s; }
        .nav-menu a:hover, .nav-menu a.active { color: #fff; }
        
        .container { max-width: 1600px; margin: 0 auto; padding: 30px 20px; }
        
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .btn-primary {
            background: #ef4444;
            color: #fff;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }
        
        .tests-card {
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
        
        tbody tr { border-bottom: 1px solid #2a2a2a; transition: background 0.15s; }
        tbody tr:hover { background: #1f1f1f; }
        td { padding: 16px 20px; font-size: 0.95rem; }
        
        .category-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .cat-quantitative { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
        .cat-logical { background: rgba(168, 85, 247, 0.15); color: #a855f7; }
        .cat-verbal { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
        
        .action-btns { display: flex; gap: 10px; }
        .btn-edit, .btn-delete {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            border: none;
        }
        .btn-edit { background: #2a2a2a; color: #e4e4e7; }
        .btn-delete { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
    </style>
</head>
<body>

    <nav class="top-nav">
        <div class="logo">⚙️ Admin Panel</div>
        <div class="nav-menu">
            <a href="dashboard.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="problems.php">Problems</a>
            <a href="tests.php" class="active">Tests</a>
            <a href="analytics.php">Analytics</a>
        </div>
        <div style="color: #71717a; font-size: 0.9rem;">
            <a href="../dashboard/index.php" style="color: #ef4444; text-decoration: none;">Exit Admin →</a>
        </div>
    </nav>

    <div class="container">
        
        <div class="header-section">
            <div>
                <h1>📚 Test Management</h1>
                <p style="color: #a1a1aa;">Manage aptitude tests and question banks</p>
            </div>
            <a href="add-test.php" class="btn-primary">+ Create New Test</a>
        </div>

        <div class="tests-card">
            <?php if (empty($tests)): ?>
                <div style="text-align: center; padding: 80px 20px; color: #71717a;">
                    <h3>No tests created yet</h3>
                    <p>Create your first aptitude test to get started</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Test Name</th>
                            <th>Category</th>
                            <th>Questions</th>
                            <th>Duration</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tests as $test): ?>
                        <tr>
                            <td style="color: #71717a;">#<?php echo $test['test_id']; ?></td>
                            <td style="font-weight: 500;"><?php echo htmlspecialchars($test['test_name']); ?></td>
                            <td>
                                <span class="category-badge cat-<?php echo strtolower($test['category']); ?>">
                                    <?php echo $test['category']; ?>
                                </span>
                            </td>
                            <td style="color: #a1a1aa;"><?php echo $test['total_questions'] ?? 0; ?></td>
                            <td style="color: #a1a1aa;"><?php echo $test['duration_minutes']; ?> min</td>
                            <td style="color: #a1a1aa; font-size: 0.85rem;">
                                <?php echo date('M j, Y', strtotime($test['created_at'])); ?>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-edit">Edit</button>
                                    <button class="btn-delete">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
