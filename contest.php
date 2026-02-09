<?php
include 'db.php';

// Fetch Contests by Status
$active_sql = "SELECT * FROM contests WHERE status = 'Active'";
$active_result = $conn->query($active_sql);

$upcoming_sql = "SELECT * FROM contests WHERE status = 'Upcoming' ORDER BY start_time ASC";
$upcoming_result = $conn->query($upcoming_sql);

$past_sql = "SELECT * FROM contests WHERE status = 'Past' ORDER BY end_time DESC LIMIT 5";
$past_result = $conn->query($past_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contests - Placement Prep</title>
    <style>
        :root {
            --primary: #2563eb; --primary-hover: #1d4ed8;
            --bg: #0f172a; --surface: #1e293b; --surface-light: #334155;
            --text: #f8fafc; --text-muted: #94a3b8;
            --success: #10b981; --warning: #f59e0b; --danger: #ef4444;
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
        .logo-img { height: 45px; width: 45px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary); }
        .logo-text h2 { margin: 0; font-size: 1.4rem; }
        .logo-text span { font-size: 0.8rem; color: var(--text-muted); display: block; }
        .nav-links { display: flex; gap: 25px; align-items: center; }
        .nav-links a { color: var(--text-muted); text-decoration: none; font-weight: 500; transition: 0.2s; position: relative; }
        .nav-links a:hover { color: white; }
        .nav-links a.active { color: var(--primary); font-weight: 700; }
        .nav-links a.active::after { content: ''; position: absolute; bottom: -24px; left: 0; width: 100%; height: 3px; background: var(--primary); }
        .btn-admin { padding: 6px 15px; background: rgba(37,99,235,0.1); color: var(--primary); border-radius: 6px; border: 1px solid var(--primary); }

        /* --- Layout --- */
        .container { max-width: 1100px; margin: 40px auto; padding: 0 20px; flex: 1; }
        
        /* Hero Section */
        .hero-banner {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border: 1px solid #334155; border-radius: 16px;
            padding: 40px; text-align: center; margin-bottom: 40px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3);
        }
        .hero-banner h1 { margin-top: 0; font-size: 2.5rem; background: linear-gradient(to right, #60a5fa, #a78bfa); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hero-banner p { color: var(--text-muted); font-size: 1.1rem; max-width: 600px; margin: 10px auto 30px; }

        /* Section Titles */
        .section-title { font-size: 1.5rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .live-dot { height: 12px; width: 12px; background-color: var(--danger); border-radius: 50%; box-shadow: 0 0 10px var(--danger); animation: pulse 1.5s infinite; }
        
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }

        /* Contest Cards */
        .contest-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 25px; margin-bottom: 50px; }
        
        .contest-card {
            background: var(--surface); border: 1px solid #334155; border-radius: 12px;
            padding: 25px; position: relative; overflow: hidden; transition: transform 0.2s;
        }
        .contest-card:hover { transform: translateY(-5px); border-color: var(--primary); }
        
        .active-border { border-top: 4px solid var(--success); }
        .upcoming-border { border-top: 4px solid var(--primary); }

        .card-header { display: flex; justify-content: space-between; margin-bottom: 15px; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; }
        .badge-live { background: rgba(239,68,68,0.2); color: var(--danger); }
        .badge-upcoming { background: rgba(37,99,235,0.2); color: var(--primary); }

        .contest-title { font-size: 1.25rem; font-weight: 600; margin-bottom: 10px; color: #e2e8f0; }
        .contest-meta { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px; display: flex; gap: 20px; }
        .meta-item { display: flex; align-items: center; gap: 6px; }

        .btn-action {
            display: block; width: 100%; padding: 12px; text-align: center; border-radius: 8px;
            font-weight: 600; text-decoration: none; transition: 0.2s;
        }
        .btn-enter { background: var(--success); color: white; }
        .btn-enter:hover { background: #059669; }
        .btn-register { background: var(--surface-light); color: white; border: 1px solid #475569; }
        .btn-register:hover { background: #475569; }

        /* Table for Past Contests */
        .table-container { background: var(--surface); border-radius: 12px; border: 1px solid #334155; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px 25px; text-align: left; border-bottom: 1px solid #334155; }
        th { background: rgba(0,0,0,0.2); color: var(--text-muted); font-weight: 600; font-size: 0.9rem; }
        td { color: #e2e8f0; }
        tr:last-child td { border-bottom: none; }
        .rank-link { color: var(--primary); text-decoration: none; }
        .rank-link:hover { text-decoration: underline; }

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
            <a href="contest.php" class="active">Contest</a>
            <a href="discuss.php">Discuss</a>
            <a href="profile.php">My Profile</a>
            <a href="admin_create.php" class="btn-admin">Admin Panel</a>
        </div>
    </div>

    <div class="container">
        <div class="hero-banner">
            <h1>Compete, Code, Conquer.</h1>
            <p>Join our weekly coding contests to simulate real-world interview pressure. Solve algorithmic challenges and climb the global leaderboard.</p>
        </div>

        <?php if ($active_result->num_rows > 0): ?>
        <h2 class="section-title"><div class="live-dot"></div> Live Contests</h2>
        <div class="contest-grid">
            <?php while($row = $active_result->fetch_assoc()): ?>
            <div class="contest-card active-border">
                <div class="card-header">
                    <span class="status-badge badge-live">Live Now</span>
                    <span style="color:var(--text-muted); font-size:0.9rem;">Ends in: <strong>01:24:10</strong></span>
                </div>
                <div class="contest-title"><?php echo $row['title']; ?></div>
                <div class="contest-meta">
                    <div class="meta-item">👥 <?php echo $row['participants']; ?> Participating</div>
                    <div class="meta-item">⏱ 90 Mins</div>
                </div>
                <a href="exam.php?id=1" class="btn-action btn-enter">Enter Contest</a>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>

        <h2 class="section-title">📅 Upcoming Contests</h2>
        <div class="contest-grid">
            <?php if ($upcoming_result->num_rows > 0): ?>
                <?php while($row = $upcoming_result->fetch_assoc()): ?>
                <div class="contest-card upcoming-border">
                    <div class="card-header">
                        <span class="status-badge badge-upcoming">Upcoming</span>
                        <span style="color:var(--text-muted); font-size:0.9rem;"><?php echo date('M d, H:i', strtotime($row['start_time'])); ?></span>
                    </div>
                    <div class="contest-title"><?php echo $row['title']; ?></div>
                    <div class="contest-meta">
                        <div class="meta-item">📅 <?php echo date('l', strtotime($row['start_time'])); ?></div>
                        <div class="meta-item">👥 <?php echo $row['participants']; ?> Registered</div>
                    </div>
                    <button class="btn-action btn-register" onclick="alert('Registered Successfully!')">Register Now</button>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color:var(--text-muted);">No upcoming contests scheduled.</p>
            <?php endif; ?>
        </div>

        <h2 class="section-title">↺ Past Contests</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Contest Name</th>
                        <th>Date</th>
                        <th>Duration</th>
                        <th>Participants</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($past_result->num_rows > 0): ?>
                        <?php while($row = $past_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['title']; ?></td>
                            <td><?php echo date('M d, Y', strtotime($row['end_time'])); ?></td>
                            <td>90 Mins</td>
                            <td><?php echo $row['participants']; ?></td>
                            <td><a href="#" class="rank-link">Virtual Participate</a></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center;">No past contests found.</td></tr>
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