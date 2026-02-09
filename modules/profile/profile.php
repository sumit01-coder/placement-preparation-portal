<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/User.php';

Auth::requireLogin();
$userId = Auth::getUserId();

$userClass = new User();
$db = Database::getInstance();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $updateData = [
        'full_name' => $_POST['full_name'],
        'phone' => $_POST['phone'],
        'college' => $_POST['college'],
        'branch' => $_POST['branch'],
        'graduation_year' => $_POST['graduation_year'],
        'bio' => $_POST['bio']
    ];
    
    // Check if profile exists
    $existing = $db->fetchOne("SELECT profile_id FROM user_profiles WHERE user_id = :uid", ['uid' => $userId]);
    
    if ($existing) {
        // Update
        $db->query(
            "UPDATE user_profiles SET full_name = :name, phone = :phone, college = :college, 
             branch = :branch, graduation_year = :year, bio = :bio WHERE user_id = :uid",
            [
                'name' => $updateData['full_name'],
                'phone' => $updateData['phone'],
                'college' => $updateData['college'],
                'branch' => $updateData['branch'],
                'year' => $updateData['graduation_year'],
                'bio' => $updateData['bio'],
                'uid' => $userId
            ]
        );
    } else {
        // Insert
        $db->query(
            "INSERT INTO user_profiles (user_id, full_name, phone, college, branch, graduation_year, bio) 
             VALUES (:uid, :name, :phone, :college, :branch, :year, :bio)",
            [
                'uid' => $userId,
                'name' => $updateData['full_name'],
                'phone' => $updateData['phone'],
                'college' => $updateData['college'],
                'branch' => $updateData['branch'],
                'year' => $updateData['graduation_year'],
                'bio' => $updateData['bio']
            ]
        );
    }
    
    $success = "Profile updated successfully!";
}

// Get user profile
$user = $db->fetchOne("SELECT u.*, up.* FROM users u LEFT JOIN user_profiles up ON u.user_id = up.user_id WHERE u.user_id = :uid", ['uid' => $userId]);
$stats = $userClass->getDashboardStats($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - PlacementCode</title>
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
        .logo { font-size: 1.3rem; font-weight: 700; color: #ffa116; text-decoration: none; }
        .nav-menu { display: flex; gap: 25px; }
        .nav-menu a { color: #a1a1aa; text-decoration: none; font-size: 0.95rem; font-weight: 500; transition: color 0.2s; }
        .nav-menu a:hover, .nav-menu a.active { color: #fff; }
        
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        
        .profile-header {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            padding: 40px;
            margin-bottom: 30px;
            display: flex;
            gap: 30px;
            align-items: center;
        }
        
        .avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ffa116, #ff6b6b);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 700;
            color: #fff;
        }
        
        .profile-info h1 { font-size: 2rem; margin-bottom: 8px; }
        .profile-info p { color: #a1a1aa; margin-bottom: 15px; }
        
        .stats-row {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }
        .stat-item { text-align: center; }
        .stat-number { font-size: 1.8rem; font-weight: 700; color: #ffa116; }
        .stat-label { color: #71717a; font-size: 0.85rem; margin-top: 4px; }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .card {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            padding: 25px;
        }
        .card h2 { margin-bottom: 20px; font-size: 1.3rem; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #a1a1aa; font-weight: 500; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            background: #0f0f0f;
            border: 1px solid #2a2a2a;
            padding: 12px;
            border-radius: 8px;
            color: #e4e4e7;
            font-family: inherit;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none;
            border-color: #ffa116;
        }
        .form-group textarea { min-height: 100px; resize: vertical; }
        
        .btn-save {
            background: linear-gradient(135deg, #ffa116, #ff6b6b);
            color: #fff;
            padding: 12px 32px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }
        
        .activity-item {
            padding: 15px;
            background: #0f0f0f;
            border-radius: 8px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            color: #22c55e;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 1024px) {
            .content-grid { grid-template-columns: 1fr; }
            .profile-header { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

    <nav class="top-nav">
        <a href="../dashboard/index.php" class="logo">⚡ PlacementCode</a>
        <div class="nav-menu">
            <a href="../dashboard/index.php">Dashboard</a>
            <a href="../coding/problems.php">Problems</a>
            <a href="profile.php" class="active">Profile</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </nav>

    <div class="container">
        
        <?php if (isset($success)): ?>
            <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="avatar">
                <?php echo strtoupper(substr($user['full_name'] ?? $user['email'], 0, 1)); ?>
            </div>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></h1>
                <p><?php echo htmlspecialchars($user['email']); ?></p>
                <p style="color: #a1a1aa;">
                    <?php echo htmlspecialchars($user['college'] ?? 'No college specified'); ?> • 
                    <?php echo htmlspecialchars($user['branch'] ?? 'No branch'); ?>
                </p>
                
                <div class="stats-row">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['problems_solved'] ?? 0; ?></div>
                        <div class="stat-label">Problems Solved</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['total_submissions'] ?? 0; ?></div>
                        <div class="stat-label">Submissions</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['global_rank'] ?? 'N/A'; ?></div>
                        <div class="stat-label">Global Rank</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-grid">
            
            <!-- Edit Profile Form -->
            <div class="card">
                <h2>Edit Profile</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>College</label>
                        <input type="text" name="college" value="<?php echo htmlspecialchars($user['college'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Branch</label>
                        <input type="text" name="branch" value="<?php echo htmlspecialchars($user['branch'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Graduation Year</label>
                        <select name="graduation_year">
                            <option value="">Select Year</option>
                            <?php for ($year = date('Y'); $year <= date('Y') + 5; $year++): ?>
                                <option value="<?php echo $year; ?>" <?php echo ($user['graduation_year'] ?? '') == $year ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Bio</label>
                        <textarea name="bio" placeholder="Tell us about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn-save">Save Changes</button>
                </form>
            </div>

            <!-- Quick Links -->
            <div>
                <div class="card" style="margin-bottom: 20px;">
                    <h2>Quick Actions</h2>
                    <div class="activity-item">
                        <span>📄 Resume Builder</span>
                        <a href="../toolkit/resume-builder.php" style="color: #ffa116; text-decoration: none;">→</a>
                    </div>
                    <div class="activity-item">
                        <span>🔒 My Documents</span>
                        <a href="../toolkit/documents.php" style="color: #ffa116; text-decoration: none;">→</a>
                    </div>
                    <div class="activity-item">
                        <span>📊 Analytics</span>
                        <a href="../focus/analytics.php" style="color: #ffa116; text-decoration: none;">→</a>
                    </div>
                    <div class="activity-item">
                        <span>🎫 Support</span>
                        <a href="../support/support.php" style="color: #ffa116; text-decoration: none;">→</a>
                    </div>
                </div>
                
                <div class="card">
                    <h2>Account Info</h2>
                    <div class="activity-item">
                        <span style="color: #a1a1aa;">Member Since</span>
                        <span><?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                    </div>
                    <div class="activity-item">
                        <span style="color: #a1a1aa;">Email</span>
                        <span style="font-size: 0.85rem;"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="activity-item">
                        <span style="color: #a1a1aa;">Role</span>
                        <span><?php echo $user['role_id'] == 2 ? 'Admin' : 'Student'; ?></span>
                    </div>
                </div>
            </div>

        </div>
    </div>

</body>
</html>
