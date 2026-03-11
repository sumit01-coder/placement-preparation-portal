<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Admin.php';

Auth::requireAdmin();

// Handle delete request
if (isset($_POST['delete_user'])) {
    $admin = new Admin();
    $result = $admin->deleteUser($_POST['user_id']);
    $message = $result['message'];
}

$admin = new Admin();
$search = $_GET['search'] ?? '';
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$users = $admin->getUsers($limit, $offset, $search);
$totalUsers = $admin->getUserCount($search);
$totalPages = ceil($totalUsers / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
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
        .header-section h1 { font-size: 2rem; }
        
        .search-bar {
            position: relative;
            max-width: 400px;
        }
        .search-bar input {
            width: 100%;
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            padding: 12px 20px 12px 45px;
            border-radius: 8px;
            color: #e4e4e7;
            font-size: 0.95rem;
        }
        .search-bar input:focus { outline: none; border-color: #ef4444; }
        .search-bar::before {
            content: '🔍';
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .users-card {
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
        
        .user-info { display: flex; flex-direction: column; }
        .user-name { font-weight: 600; color: #e4e4e7; margin-bottom: 4px; }
        .user-email { font-size: 0.85rem; color: #71717a; }
        
        .role-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .role-student { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
        .role-admin { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        
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
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 30px;
        }
        .page-btn {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            color: #a1a1aa;
            padding: 8px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .page-btn:hover { background: #2a2a2a; color: #fff; }
        .page-btn.active { background: #ef4444; color: #fff; border-color: #ef4444; }
        
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
            <a href="users.php" class="active">Users</a>
            <a href="problems.php">Problems</a>
            <a href="tests.php">Tests</a>
            <a href="company-drives.php">Company Drives</a>
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
                <h1>👥 User Management</h1>
                <p style="color: #a1a1aa;">Manage all registered users</p>
            </div>
            
            <form method="GET" class="search-bar">
                <input type="text" name="search" placeholder="Search users..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </form>
        </div>

        <div class="users-card">
            <?php if (empty($users)): ?>
                <div style="text-align: center; padding: 80px 20px; color: #71717a;">
                    <h3>No users found</h3>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>College/Branch</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td style="color: #71717a;">#<?php echo $user['user_id']; ?></td>
                            <td>
                                <div class="user-info">
                                    <span class="user-name"><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></span>
                                    <span class="user-email"><?php echo htmlspecialchars($user['email']); ?></span>
                                </div>
                            </td>
                            <td style="color: #a1a1aa;">
                                <?php echo htmlspecialchars($user['college'] ?? 'N/A'); ?><br>
                                <small style="color: #71717a;"><?php echo htmlspecialchars($user['branch'] ?? ''); ?></small>
                            </td>
                            <td>
                                <span class="role-badge role-<?php echo $user['role_id'] == 2 ? 'admin' : 'student'; ?>">
                                    <?php echo $user['role_id'] == 2 ? 'Admin' : 'Student'; ?>
                                </span>
                            </td>
                            <td style="color: #a1a1aa; font-size: 0.85rem;">
                                <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                            </td>
                            <td style="color: #a1a1aa; font-size: 0.85rem;">
                                <?php echo $user['last_login'] ? date('M j, Y', strtotime($user['last_login'])) : 'Never'; ?>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-edit" onclick="editUser(<?php echo $user['user_id']; ?>)">Edit</button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this user?');">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <button type="submit" name="delete_user" class="btn-delete">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= min($totalPages, 10); $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                   class="page-btn <?php echo $page == $i ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        function editUser(userId) {
            alert('Edit functionality coming soon for user #' + userId);
            // TODO: Implement edit modal or redirect to edit page
        }
    </script>

</body>
</html>
