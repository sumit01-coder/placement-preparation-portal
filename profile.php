<?php
include 'db.php';
$user_id = $_SESSION['user_id'];

// 1. Fetch User Details
$user_sql = "SELECT username FROM users WHERE id = $user_id";
$user_res = $conn->query($user_sql);
$user_data = $user_res->fetch_assoc();

// 2. Fetch Stats
$stats_sql = "SELECT 
                COUNT(*) as total_submissions,
                SUM(CASE WHEN status = 'Solved' THEN 1 ELSE 0 END) as solved_count,
                SUM(CASE WHEN difficulty = 'Easy' AND status = 'Solved' THEN 1 ELSE 0 END) as easy_solved,
                SUM(CASE WHEN difficulty = 'Medium' AND status = 'Solved' THEN 1 ELSE 0 END) as medium_solved,
                SUM(CASE WHEN difficulty = 'Hard' AND status = 'Solved' THEN 1 ELSE 0 END) as hard_solved
              FROM submissions s 
              JOIN problems p ON s.problem_id = p.id 
              WHERE s.user_id = $user_id";
$stats_res = $conn->query($stats_sql);
$stats = $stats_res->fetch_assoc();

// 3. Fetch Recent Submissions
$sql = "SELECT s.*, p.title, p.difficulty 
        FROM submissions s 
        JOIN problems p ON s.problem_id = p.id 
        WHERE s.user_id = $user_id 
        ORDER BY s.created_at DESC LIMIT 10";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo $user_data['username']; ?></title>
    <style>
        :root {
            --primary: #2563eb; --bg: #0f172a; --surface: #1e293b; --surface-light: #334155;
            --text: #f8fafc; --text-muted: #94a3b8;
            --easy: #10b981; --medium: #f59e0b; --hard: #ef4444;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg); color: var(--text);
            margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh;
        }

        /* --- Navbar (Consistent) --- */
        .navbar {
            background-color: var(--surface); padding: 10px 30px;
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid #334155;
        }
        .logo-area { display: flex; align-items: center; gap: 12px; text-decoration: none; color: white; }
        .logo-img { height: 45px; width: 45px; border-radius: 50%; border: 2px solid var(--primary); }
        .logo-text h2 { margin: 0; font-size: 1.4rem; }
        .logo-text span { font-size: 0.8rem; color: var(--text-muted); display: block; }
        .nav-links { display: flex; gap: 25px; align-items: center; }
        .nav-links a { color: var(--text-muted); text-decoration: none; font-weight: 500; transition: 0.2s; position: relative; }
        .nav-links a:hover { color: white; }
        .nav-links a.active { color: var(--primary); font-weight: 700; }
        .nav-links a.active::after { content: ''; position: absolute; bottom: -24px; left: 0; width: 100%; height: 3px; background: var(--primary); }
        .btn-admin { padding: 6px 15px; background: rgba(37,99,235,0.1); color: var(--primary); border-radius: 6px; border: 1px solid var(--primary); }

        /* --- Layout --- */
        .container { max-width: 1100px; margin: 40px auto; padding: 0 20px; flex: 1; display: flex; gap: 30px; }
        .profile-sidebar { flex: 1; display: flex; flex-direction: column; gap: 20px; }
        .profile-main { flex: 3; display: flex; flex-direction: column; gap: 25px; }

        /* --- User Card (Sidebar) --- */
        .user-card {
            background: var(--surface); padding: 30px; border-radius: 12px;
            border: 1px solid #334155; text-align: center;
        }
        .avatar-large {
            width: 100px; height: 100px; background: var(--surface-light); border-radius: 50%;
            margin: 0 auto 15px; display: flex; align-items: center; justify-content: center;
            font-size: 3rem; color: var(--text-muted); border: 2px solid var(--primary);
        }
        .user-card h2 { margin: 0 0 5px 0; }
        .rank-badge { 
            background: rgba(251, 191, 36, 0.1); color: #fbbf24; padding: 4px 12px; 
            border-radius: 20px; font-size: 0.8rem; border: 1px solid rgba(251, 191, 36, 0.3); display: inline-block; margin-bottom: 20px;
        }
        .stat-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 0.9rem; color: var(--text-muted); }
        .stat-row span { color: white; font-weight: 600; }

        /* --- Stats Grid --- */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .stat-box {
            background: var(--surface); padding: 20px; border-radius: 12px;
            border: 1px solid #334155; text-align: center;
        }
        .stat-box h3 { margin: 0; font-size: 2rem; }
        .stat-box p { margin: 5px 0 0 0; color: var(--text-muted); font-size: 0.9rem; }
        .box-easy h3 { color: var(--easy); }
        .box-medium h3 { color: var(--medium); }
        .box-hard h3 { color: var(--hard); }

        /* --- Activity Graph (Simulated) --- */
        .graph-card {
            background: var(--surface); padding: 25px; border-radius: 12px; border: 1px solid #334155;
        }
        .heatmap { display: flex; gap: 4px; margin-top: 15px; }
        .heat-box { width: 12px; height: 12px; border-radius: 2px; background: #334155; }
        /* Just for visual effect */
        .lvl-1 { background: #0e4429; } .lvl-2 { background: #006d32; } .lvl-3 { background: #26a641; } .lvl-4 { background: #39d353; }

        /* --- Submission Table --- */
        .table-card { background: var(--surface); border-radius: 12px; border: 1px solid #334155; overflow: hidden; }
        .card-header { padding: 20px; border-bottom: 1px solid #334155; }
        .card-header h3 { margin: 0; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px 20px; text-align: left; border-bottom: 1px solid #334155; font-size: 0.9rem; }
        th { background: rgba(0,0,0,0.2); color: var(--text-muted); font-weight: 600; }
        tr:last-child td { border-bottom: none; }
        
        .badge { padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; }
        .badge-solved { background: rgba(16, 185, 129, 0.15); color: var(--easy); }
        .badge-attempted { background: rgba(245, 158, 11, 0.15); color: var(--medium); }

        /* Footer */
        .footer { background: var(--surface); padding: 20px; text-align: center; border-top: 1px solid #334155; font-size: 0.9rem; color: var(--text-muted); margin-top: auto; }
    </style>
</head>
<body>

    <div class="navbar">
        <a href="index.php" class="logo-area">
            <img src="logo.png" alt="Logo" class="logo-img"> 
            <div class="logo-text"><h2>Placement Prep</h2><span>Your Career Launchpad</span></div>
        </a>
        <div class="nav-links">
            <a href="index.php">Explore Problems</a>
            <a href="contest.php">Contest</a>
            <a href="discuss.php">Discuss</a>
            <a href="profile.php" class="active">My Profile</a>
            <a href="admin_create.php" class="btn-admin">Admin Panel</a>
        </div>
    </div>

    <div class="container">
        
        <div class="profile-sidebar">
            <div class="user-card">
                <div class="avatar-large">
                    <?php echo strtoupper($user_data['username'][0]); ?>
                </div>
                <h2><?php echo $user_data['username']; ?></h2>
                <div class="rank-badge">Rank: 145,203</div>
                
                <div style="margin-top: 20px; text-align: left;">
                    <div class="stat-row">Global Rank <span>Top 15%</span></div>
                    <div class="stat-row">Country <span>India 🇮🇳</span></div>
                    <div class="stat-row">Institution <span>IIT Bombay</span></div>
                </div>
                
                <button style="width:100%; margin-top:20px; padding:10px; background:var(--surface-light); border:1px solid #475569; color:white; border-radius:6px; cursor:pointer;">Edit Profile</button>
            </div>

            <div class="user-card" style="text-align:left;">
                <h4 style="margin-top:0;">Skills</h4>
                <div style="margin-bottom:10px;">
                    <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:5px;"><span>Algorithms</span> <span>Advanced</span></div>
                    <div style="height:6px; background:#334155; border-radius:3px;"><div style="width:80%; height:100%; background:var(--primary); border-radius:3px;"></div></div>
                </div>
                <div style="margin-bottom:10px;">
                    <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:5px;"><span>Data Structures</span> <span>Intermediate</span></div>
                    <div style="height:6px; background:#334155; border-radius:3px;"><div style="width:60%; height:100%; background:var(--medium); border-radius:3px;"></div></div>
                </div>
            </div>
        </div>

        <div class="profile-main">
            
            <div class="stats-grid">
                <div class="stat-box box-easy">
                    <h3><?php echo $stats['easy_solved']; ?></h3>
                    <p>Easy Solved</p>
                </div>
                <div class="stat-box box-medium">
                    <h3><?php echo $stats['medium_solved']; ?></h3>
                    <p>Medium Solved</p>
                </div>
                <div class="stat-box box-hard">
                    <h3><?php echo $stats['hard_solved']; ?></h3>
                    <p>Hard Solved</p>
                </div>
            </div>

            <div class="graph-card">
                <h3 style="margin-top:0;">Submission Activity (Last 30 Days)</h3>
                <div class="heatmap">
                    <?php for($i=0; $i<30; $i++): 
                        $lvl = rand(0, 4); 
                        $cls = ($lvl > 0) ? "lvl-$lvl" : "";
                    ?>
                        <div class="heat-box <?php echo $cls; ?>" style="flex:1;"></div>
                    <?php endfor; ?>
                </div>
                <p style="font-size:0.8rem; color:var(--text-muted); margin-top:10px;">Total <?php echo $stats['total_submissions']; ?> submissions in the last year.</p>
            </div>

            <div class="table-card">
                <div class="card-header">
                    <h3>Recent Submissions</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Problem</th>
                            <th>Difficulty</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><a href="exam.php?id=<?php echo $row['problem_id']; ?>" style="color:var(--text); text-decoration:none; font-weight:500;"><?php echo $row['title']; ?></a></td>
                                <td>
                                    <?php 
                                        $diff = $row['difficulty'];
                                        $color = ($diff == 'Easy') ? 'var(--easy)' : (($diff == 'Medium') ? 'var(--medium)' : 'var(--hard)');
                                        echo "<span style='color:$color'>$diff</span>";
                                    ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo ($row['status'] == 'Solved') ? 'badge-solved' : 'badge-attempted'; ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d', strtotime($row['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align:center; padding:30px;">No submissions yet. Go solve some problems!</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <footer class="footer">
        &copy; <?php echo date("Y"); ?> Placement Preparation Portal. Made for coders.
    </footer>

</body>
</html>