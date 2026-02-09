<?php
include 'db.php';

// --- 1. Helper Function: Time Ago ---
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year', 'm' => 'month', 'w' => 'week',
        'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

// --- 2. Handle New Discussion ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_discussion'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $category = $_POST['category'];
    $user_id = $_SESSION['user_id'];

    if (!empty($title) && !empty($content)) {
        $stmt = $conn->prepare("INSERT INTO discussions (user_id, title, content, category) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $title, $content, $category);
        if($stmt->execute()) {
            // Redirect with a success flag
            header("Location: discuss.php?msg=posted");
            exit();
        }
    }
}

// --- 3. Build Query with Search & Filter ---
$whereClause = "1"; // Default true
$params = [];
$types = "";

// Check for Category Filter
if (isset($_GET['cat']) && !empty($_GET['cat'])) {
    $whereClause .= " AND d.category = ?";
    $params[] = $_GET['cat'];
    $types .= "s";
}

// Check for Search Query
if (isset($_GET['q']) && !empty($_GET['q'])) {
    $search = "%" . $_GET['q'] . "%";
    $whereClause .= " AND (d.title LIKE ? OR d.content LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $types .= "ss";
}

$sql = "SELECT d.*, u.username, LEFT(u.username, 1) as initial 
        FROM discussions d 
        JOIN users u ON d.user_id = u.id 
        WHERE $whereClause
        ORDER BY d.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discussion Forum - Placement Prep</title>
    <style>
        :root {
            --primary: #3b82f6; --primary-dark: #2563eb;
            --bg: #0f172a; --surface: #1e293b; --surface-light: #334155;
            --text: #f8fafc; --text-muted: #94a3b8;
            --success: #10b981;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background-color: var(--bg); color: var(--text);
            margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh;
        }

        /* --- Navbar --- */
        .navbar {
            background-color: var(--surface); padding: 12px 30px;
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid #334155;
        }
        .logo-area { display: flex; align-items: center; gap: 12px; text-decoration: none; color: white; }
        .logo-img { height: 40px; width: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary); }
        .logo-text h2 { margin: 0; font-size: 1.3rem; }
        .logo-text span { font-size: 0.75rem; color: var(--text-muted); display: block; }
        .nav-links { display: flex; gap: 25px; align-items: center; }
        .nav-links a { color: var(--text-muted); text-decoration: none; font-weight: 500; font-size: 0.95rem; transition: 0.2s; position: relative; }
        .nav-links a:hover { color: white; }
        .nav-links a.active { color: var(--primary); font-weight: 700; }
        .btn-admin { padding: 6px 15px; background: rgba(59, 130, 246, 0.1); color: var(--primary); border-radius: 6px; border: 1px solid var(--primary); }

        /* --- Layout --- */
        .container { max-width: 1150px; margin: 40px auto; padding: 0 20px; flex: 1; display: flex; gap: 30px; }
        .main-content { flex: 3; }
        .sidebar { flex: 1; display: flex; flex-direction: column; gap: 25px; }

        /* --- Header --- */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-header h1 { margin: 0; font-size: 1.8rem; background: linear-gradient(to right, #fff, #94a3b8); -webkit-background-clip: text; -webkit-text-fill-color: transparent;}
        
        .btn-new-post {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white; border: none; padding: 12px 24px;
            border-radius: 8px; font-weight: 600; cursor: pointer; 
            display: flex; align-items: center; gap: 8px;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-new-post:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4); }

        /* --- Cards --- */
        .discuss-card {
            background-color: var(--surface); border: 1px solid #334155; border-radius: 12px;
            padding: 25px; margin-bottom: 20px; transition: all 0.2s ease;
            cursor: pointer; position: relative; overflow: hidden;
        }
        .discuss-card:hover { transform: translateY(-3px); border-color: var(--primary); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); }
        .discuss-card::before {
            content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--primary);
            opacity: 0; transition: opacity 0.2s;
        }
        .discuss-card:hover::before { opacity: 1; }

        .card-top { display: flex; justify-content: space-between; margin-bottom: 12px; }
        .user-info { display: flex; align-items: center; gap: 12px; font-size: 0.9rem; color: var(--text-muted); }
        .avatar { 
            width: 35px; height: 35px; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; 
            color: white; font-weight: bold; font-size: 0.9rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .card-title { font-size: 1.3rem; font-weight: 700; color: #f1f5f9; margin-bottom: 10px; line-height: 1.4; }
        .card-preview { font-size: 1rem; color: #cbd5e1; line-height: 1.6; margin-bottom: 18px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

        .card-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 15px; border-top: 1px solid #334155; font-size: 0.85rem; color: var(--text-muted); }
        .category-tag { background: rgba(59, 130, 246, 0.15); color: #60a5fa; padding: 4px 12px; border-radius: 20px; font-weight: 600; font-size: 0.75rem; border: 1px solid rgba(59, 130, 246, 0.3); }
        .stats { display: flex; gap: 20px; }
        .stats span { display: flex; align-items: center; gap: 6px; }

        /* --- Sidebar --- */
        .widget { background: var(--surface); padding: 25px; border-radius: 12px; border: 1px solid #334155; }
        .widget h3 { margin-top: 0; font-size: 1.1rem; border-bottom: 1px solid #334155; padding-bottom: 12px; margin-bottom: 15px; color: #e2e8f0; }
        
        .search-box { display: flex; gap: 10px; }
        .search-input { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #334155; background: var(--bg); color: white; outline: none; transition: border-color 0.2s; }
        .search-input:focus { border-color: var(--primary); }
        .btn-search { background: var(--surface-light); border: 1px solid #475569; color: white; border-radius: 8px; cursor: pointer; padding: 0 12px; }

        .category-list a { 
            display: flex; justify-content: space-between; padding: 10px 12px; 
            color: var(--text-muted); text-decoration: none; border-radius: 6px; 
            margin-bottom: 5px; transition: all 0.2s;
        }
        .category-list a:hover, .category-list a.active-cat { background: var(--surface-light); color: white; }
        .cat-count { background: var(--bg); padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; }

        /* --- Modal --- */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.85); z-index: 1000;
            display: none; justify-content: center; align-items: center;
            backdrop-filter: blur(8px);
        }
        .modal-content {
            background: var(--surface); padding: 30px; border-radius: 16px;
            width: 100%; max-width: 600px; border: 1px solid #334155;
            box-shadow: 0 25px 50px rgba(0,0,0,0.5); animation: zoomIn 0.25s ease;
        }
        @keyframes zoomIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        .modal-header { display: flex; justify-content: space-between; margin-bottom: 25px; }
        .form-control { width: 100%; padding: 14px; background: var(--bg); border: 1px solid #334155; border-radius: 8px; color: white; font-family: inherit; margin-bottom: 20px; outline: none; }
        .form-control:focus { border-color: var(--primary); }
        .btn-submit { width: 100%; background: var(--primary); padding: 14px; border: none; border-radius: 8px; color: white; font-weight: bold; cursor: pointer; font-size: 1rem; }
        
        /* Toast Notification */
        .toast {
            visibility: hidden; min-width: 250px; background-color: var(--success); color: #fff;
            text-align: center; border-radius: 8px; padding: 16px; position: fixed; z-index: 1;
            left: 50%; bottom: 30px; transform: translateX(-50%);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3); font-weight: 600;
        }
        .toast.show { visibility: visible; animation: fadein 0.5s, fadeout 0.5s 2.5s; }
        @keyframes fadein { from {bottom: 0; opacity: 0;} to {bottom: 30px; opacity: 1;} }
        @keyframes fadeout { from {bottom: 30px; opacity: 1;} to {bottom: 0; opacity: 0;} }

        /* Footer */
        .footer { background: var(--surface); padding: 25px; text-align: center; border-top: 1px solid #334155; font-size: 0.9rem; color: var(--text-muted); margin-top: auto; }
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
            <a href="discuss.php" class="active">Discuss</a>
            <a href="profile.php">My Profile</a>
            <a href="admin_create.php" class="btn-admin">Admin Panel</a>
        </div>
    </div>

    <div class="container">
        <div class="main-content">
            <div class="page-header">
                <h1>Community Discussions</h1>
                <button class="btn-new-post" onclick="openModal()">
                    <span style="font-size:1.2rem;">+</span> Start Discussion
                </button>
            </div>

            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <div class="discuss-card" onclick="window.location.href='view_post.php?id=<?php echo $row['id']; ?>'">
                        <div class="card-top">
                            <div class="user-info">
                                <div class="avatar" style="background-color: hsl(<?php echo rand(0, 360); ?>, 60%, 40%)">
                                    <?php echo strtoupper($row['initial']); ?>
                                </div>
                                <span style="font-weight:600; color:#e2e8f0;"><?php echo htmlspecialchars($row['username']); ?></span>
                                <span style="font-size:0.5rem; opacity:0.5;">●</span>
                                <span><?php echo time_elapsed_string($row['created_at']); ?></span>
                            </div>
                        </div>

                        <div class="card-title"><?php echo htmlspecialchars($row['title']); ?></div>
                        <div class="card-preview"><?php echo htmlspecialchars(substr($row['content'], 0, 150)) . '...'; ?></div>

                        <div class="card-footer">
                            <span class="category-tag"><?php echo htmlspecialchars($row['category']); ?></span>
                            <div class="stats">
                                <span>👁 <?php echo $row['views']; ?></span>
                                <span>💬 <?php echo $row['replies']; ?> Replies</span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align:center; padding:60px; color:var(--text-muted); border: 2px dashed #334155; border-radius:12px;">
                    <h3 style="margin-bottom:10px; color:white;">No discussions found</h3>
                    <p>Try clearing your filters or be the first to post!</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="sidebar">
            <div class="widget">
                <form method="GET" action="discuss.php" class="search-box">
                    <input type="text" name="q" class="search-input" placeholder="Search topics..." value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                    <button type="submit" class="btn-search">🔍</button>
                </form>
            </div>

            <div class="widget">
                <h3>Categories</h3>
                <div class="category-list">
                    <?php 
                        $cats = ['General Queries', 'Interview Experience', 'Algorithms', 'System Design', 'Bug Fix'];
                        $current_cat = isset($_GET['cat']) ? $_GET['cat'] : '';
                        
                        // "All" Link
                        $active = ($current_cat == '') ? 'active-cat' : '';
                        echo "<a href='discuss.php' class='$active'><span>All Topics</span></a>";

                        foreach($cats as $c) {
                            $active = ($current_cat == $c) ? 'active-cat' : '';
                            // In real app, count query here. Used static 0 for demo speed
                            echo "<a href='discuss.php?cat=$c' class='$active'><span>$c</span></a>";
                        }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <div id="postModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 style="margin:0;">Create New Discussion</h2>
                <button onclick="closeModal()" style="background:none; border:none; color:var(--text-muted); font-size:1.5rem; cursor:pointer;">&times;</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="create_discussion" value="1">
                
                <label style="color:var(--text-muted); font-size:0.9rem; display:block; margin-bottom:8px;">Title</label>
                <input type="text" name="title" class="form-control" placeholder="Briefly summarize your question..." required>

                <label style="color:var(--text-muted); font-size:0.9rem; display:block; margin-bottom:8px;">Category</label>
                <select name="category" class="form-control">
                    <option>General Queries</option>
                    <option>Interview Experience</option>
                    <option>Algorithms</option>
                    <option>System Design</option>
                    <option>Bug Fix</option>
                </select>

                <label style="color:var(--text-muted); font-size:0.9rem; display:block; margin-bottom:8px;">Details</label>
                <textarea name="content" class="form-control" rows="6" placeholder="Provide more details, code snippets, or context..." required></textarea>

                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" onclick="closeModal()" style="background:transparent; color:var(--text-muted); padding:10px 20px; border:none; cursor:pointer;">Cancel</button>
                    <button type="submit" class="btn-submit" style="width:auto; padding:10px 30px;">Post</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toast" class="toast">Discussion Posted Successfully!</div>

    <footer class="footer">
        &copy; <?php echo date("Y"); ?> Placement Preparation Portal. Made for coders.
    </footer>

    <script>
        // Modal Logic
        const modal = document.getElementById('postModal');
        function openModal() { modal.style.display = 'flex'; }
        function closeModal() { modal.style.display = 'none'; }
        window.onclick = function(e) { if (e.target == modal) closeModal(); }

        // Toast Logic (Show if URL has msg=posted)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('msg') === 'posted') {
            const x = document.getElementById("toast");
            x.className = "toast show";
            setTimeout(function(){ x.className = x.className.replace("show", ""); }, 3000);
            // Clean URL
            window.history.replaceState({}, document.title, "discuss.php");
        }
    </script>

</body>
</html>