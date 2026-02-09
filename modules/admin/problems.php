<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Admin.php';

Auth::requireLogin();

// Check if user is admin
$userId = Auth::getUserId();
$db = Database::getInstance();
$userRole = $db->fetchOne("SELECT role_id FROM users WHERE user_id = :uid", ['uid' => $userId])['role_id'];

if ($userRole != 2) {
    header('Location: ../../modules/dashboard/index.php');
    exit();
}

// Handle delete request
if (isset($_POST['delete_problem'])) {
    $admin = new Admin();
    $result = $admin->deleteProblem($_POST['problem_id']);
    $message = $result['message'];
}

$admin = new Admin();
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$problems = $admin->getProblems($limit, $offset);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Problem Management - Admin Panel</title>
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
            transition: background 0.2s;
        }
        .btn-primary:hover { background: #dc2626; }
        
        .problems-card {
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
        tbody tr:hover { background: #1f1f1f; }
        
        td { padding: 16px 20px; font-size: 0.95rem; }
        
        .difficulty-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .diff-easy { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
        .diff-medium { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
        .diff-hard { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        
        .action-btns { display: flex; gap: 10px; }
        .btn-edit, .btn-delete {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-edit { background: #2a2a2a; color: #e4e4e7; }
        .btn-edit:hover { background: #3a3a3a; }
        .btn-delete { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .btn-delete:hover { background: rgba(239, 68, 68, 0.25); }
        
        .alert {
            background: rgba(34, 197, 94, 0.15);
            color: #22c55e;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 3px solid #22c55e;
        }
    </style>
</head>
<body>

    <nav class="top-nav">
        <div class="logo">⚙️ Admin Panel</div>
        <div class="nav-menu">
            <a href="dashboard.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="problems.php" class="active">Problems</a>
            <a href="tests.php">Tests</a>
            <a href="analytics.php">Analytics</a>
        </div>
        <div style="color: #71717a; font-size: 0.9rem;">
            <a href="../dashboard/index.php" style="color: #ef4444; text-decoration: none;">Exit Admin →</a>
        </div>
    </nav>

    <div class="container">
        
        <?php if (isset($message)): ?>
            <div class="alert"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div class="header-section">
            <div>
                <h1>💻 Problem Management</h1>
                <p style="color: #a1a1aa;">Manage coding problems and test cases</p>
            </div>
            <a href="add-problem.php" class="btn-primary">+ Add New Problem</a>
        </div>

        <div class="problems-card">
            <?php if (empty($problems)): ?>
                <div style="text-align: center; padding: 80px 20px; color: #71717a;">
                    <h3>No problems yet</h3>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Difficulty</th>
                            <th>Solvers</th>
                            <th>Submissions</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($problems as $problem): ?>
                        <tr>
                            <td style="color: #71717a;">#<?php echo $problem['problem_id']; ?></td>
                            <td>
                                <a href="../coding/editor.php?id=<?php echo $problem['problem_id']; ?>" 
                                   style="color: #e4e4e7; text-decoration: none; font-weight: 500;">
                                    <?php echo htmlspecialchars($problem['title']); ?>
                                </a>
                            </td>
                            <td>
                                <span class="difficulty-badge diff-<?php echo strtolower($problem['difficulty']); ?>">
                                    <?php echo $problem['difficulty']; ?>
                                </span>
                            </td>
                            <td style="color: #a1a1aa;"><?php echo $problem['solvers_count'] ?? 0; ?></td>
                            <td style="color: #a1a1aa;"><?php echo $problem['total_submissions'] ?? 0; ?></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-edit" onclick="editProblem(<?php echo $problem['problem_id']; ?>)">Edit</button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this problem?');">
                                        <input type="hidden" name="problem_id" value="<?php echo $problem['problem_id']; ?>">
                                        <button type="submit" name="delete_problem" class="btn-delete">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function editProblem(problemId) {
            window.location.href= `edit-problem.php?id=${problemId}`;
        }
    </script>

</body>
</html>
