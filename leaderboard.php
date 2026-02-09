<?php
include 'db.php';

// Fetch Leaderboard Data
// Counts 'Solved' submissions for each user and orders them by count
$sql = "SELECT u.username, COUNT(s.id) as solved_count 
        FROM users u 
        LEFT JOIN submissions s ON u.id = s.user_id AND s.status = 'Solved' 
        GROUP BY u.id 
        ORDER BY solved_count DESC, u.username ASC";
$result = $conn->query($sql);

// Store top 3 for the podium
$top3 = [];
$others = [];
$rank = 1;

while($row = $result->fetch_assoc()) {
    if ($rank <= 3) {
        $top3[] = $row;
    } else {
        $row['rank'] = $rank;
        $others[] = $row;
    }
    $rank++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - Placement Prep</title>
    <style>
        :root {
            --primary: #2563eb; --bg: #0f172a; --surface: #1e293b; 
            --text: #f8fafc; --text-muted: #94a3b8;
            --gold: #fbbf24; --silver: #94a3b8; --bronze: #78350f;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg); color: var(--text);
            margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh;
        }

        /* --- Navbar --- */
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

        /* --- Container --- */
        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; flex: 1; }

        .header-title { text-align: center; margin-bottom: 50px; }
        .header-title h1 { font-size: 2.5rem; margin-bottom: 10px; }
        .header-title p { color: var(--text-muted); font-size: 1.1rem; }

        /* --- Podium Section --- */
        .podium {
            display: flex; justify-content: center; align-items: flex-end; gap: 20px;
            margin-bottom: 60px; padding-bottom: 20px;
        }

        .podium-card {
            background: var(--surface); border-radius: 12px; padding: 20px;
            text-align: center; width: 220px; position: relative;
            border: 1px solid #334155; transition: transform 0.3s;
            display: flex; flex-direction: column; align-items: center;
        }
        
        .podium-card:hover { transform: translateY(-10px); }

        .rank-1 { order: 2; height: 280px; border-color: var(--gold); box-shadow: 0 0 20px rgba(251, 191, 36, 0.2); }
        .rank-2 { order: 1; height: 240px; border-color: var(--silver); }
        .rank-3 { order: 3; height: 220px; border-color: var(--bronze); }

        .crown { font-size: 2rem; position: absolute; top: -30px; }
        .avatar {
            width: 80px; height: 80px; border-radius: 50%; margin-bottom: 15px;
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; font-weight: bold; color: white;
            border: 4px solid var(--surface);
        }
        
        .rank-1 .avatar { background: var(--gold); }
        .rank-2 .avatar { background: var(--silver); }
        .rank-3 .avatar { background: var(--bronze); }

        .username { font-size: 1.2rem; font-weight: bold; margin-bottom: 5px; }
        .score { font-size: 0.9rem; color: var(--text-muted); }
        .score strong { color: white; font-size: 1.1rem; }

        .badge-rank {
            background: var(--bg); padding: 5px 15px; border-radius: 20px;
            font-weight: bold; margin-top: auto; display: inline-block;
        }

        /* --- List Table --- */
        .leaderboard-list {
            background: var(--surface); border-radius: 12px; border: 1px solid #334155;
            overflow: hidden;
        }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 18px 25px; text-align: left; border-bottom: 1px solid #334155; }
        th { background: rgba(0,0,0,0.2); color: var(--text-muted); font-weight: 600; text-transform: uppercase; font-size: 0.85rem; }
        tr:hover { background: rgba(255,255,255,0.02); }
        tr:last-child td { border-bottom: none; }

        .rank-circle {
            width: 30px; height: 30px; background: #334155; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.9rem;
        }
        
        .user-cell { display: flex; align-items: center; gap: 10px; font-weight: 500; }
        .user-avatar-sm { 
            width: 30px; height: 30px; border-radius: 50%; background: var(--primary);
            display: flex; align-items: center; justify-content: center; font-size: 0.8rem;
        }

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
            <a href="profile.php">My Profile</a>
            <a href="leaderboard.php" class="active">Leaderboard</a> <a href="admin_create.php" class="btn-admin">Admin Panel</a>
        </div>
    </div>

    <div class="container">
        <div class="header-title">
            <h1>Hall of Fame</h1>
            <p>Top coders who are consistently solving challenges.</p>
        </div>

        <?php if (!empty($top3)): ?>
        <div class="podium">
            <?php if (isset($top3[1])): ?>
            <div class="podium-card rank-2">
                <div class="avatar"><?php echo strtoupper($top3[1]['username'][0]); ?></div>
                <div class="username"><?php echo $top3[1]['username']; ?></div>
                <div class="score">Solved: <strong><?php echo $top3[1]['solved_count']; ?></strong></div>
                <div class="badge-rank" style="color: #cbd5e1;">#2 Silver</div>
            </div>
            <?php endif; ?>

            <?php if (isset($top3[0])): ?>
            <div class="podium-card rank-1">
                <div class="crown">👑</div>
                <div class="avatar"><?php echo strtoupper($top3[0]['username'][0]); ?></div>
                <div class="username"><?php echo $top3[0]['username']; ?></div>
                <div class="score">Solved: <strong><?php echo $top3[0]['solved_count']; ?></strong></div>
                <div class="badge-rank" style="color: #fbbf24;">#1 Gold</div>
            </div>
            <?php endif; ?>

            <?php if (isset($top3[2])): ?>
            <div class="podium-card rank-3">
                <div class="avatar"><?php echo strtoupper($top3[2]['username'][0]); ?></div>
                <div class="username"><?php echo $top3[2]['username']; ?></div>
                <div class="score">Solved: <strong><?php echo $top3[2]['solved_count']; ?></strong></div>
                <div class="badge-rank" style="color: #d97706;">#3 Bronze</div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="leaderboard-list">
            <table>
                <thead>
                    <tr>
                        <th width="100">Rank</th>
                        <th>User</th>
                        <th>Problems Solved</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($others) > 0): ?>
                        <?php foreach($others as $user): ?>
                        <tr>
                            <td><div class="rank-circle"><?php echo $user['rank']; ?></div></td>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar-sm"><?php echo strtoupper($user['username'][0]); ?></div>
                                    <?php echo $user['username']; ?>
                                </div>
                            </td>
                            <td><strong style="color:#4ade80"><?php echo $user['solved_count']; ?></strong> Problems</td>
                            <td style="color: var(--text-muted);">Active</td>
                        </tr>
                        <?php endforeach; ?>
                    <?php elseif (empty($top3)): ?>
                        <tr><td colspan="4" style="text-align:center;">No data available yet. Start solving!</td></tr>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center; padding: 20px; color: var(--text-muted);">That's everyone for now!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <footer class="footer">
        &copy; <?php echo date("Y"); ?> Placement Preparation Portal. Made for coders.
    </footer>

</body>
</html>