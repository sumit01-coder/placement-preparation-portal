<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Community.php';

Auth::requireLogin();

$community = new Community();
$questions = $community->getQuestions();
$popularTags = $community->getPopularTags(10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Q&A - Placement Portal</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f5f7fa; }
        
        .navbar {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 15px 30px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar h1 { font-size: 1.5rem; }
        .nav-links a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            padding: 8px 16px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .nav-links a:hover { background: rgba(255,255,255,0.2); }
        
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h2 { color: #667eea; }
        
        .ask-btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }
        .ask-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .layout {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 25px;
        }
        
        .questions-list { display: flex; flex-direction: column; gap: 15px; }
        
        .question-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
            cursor: pointer;
        }
        .question-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            border-left: 4px solid #667eea;
        }
        
        .question-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        .stat {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 8px 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .stat-value {
            font-weight: bold;
            color: #667eea;
            font-size: 1.2rem;
        }
        .stat-label {
            color: #666;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        
        .question-title {
            color: #333;
            font-size: 1.1rem;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .question-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #666;
            font-size: 0.85rem;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
        }
        
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .sidebar-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .sidebar-card h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        
        .tags-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .tag {
            padding: 6px 12px;
            background: #f0f4ff;
            color: #667eea;
            border-radius: 5px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .tag:hover {
            background: #667eea;
            color: white;
        }
        
        .leaderboard-item {
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .leaderboard-item:last-child { border-bottom: none; }
        .rank {
            font-weight: bold;
            color: #667eea;
            margin-right: 10px;
        }
        
        @media (max-width: 768px) {
            .layout { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1>💬 Community Q&A</h1>
        <div class="nav-links">
            <a href="../dashboard/index.php">Dashboard</a>
            <a href="../aptitude/tests.php">Aptitude</a>
            <a href="../coding/problems.php">Coding</a>
            <a href="questions.php" style="background: rgba(255,255,255,0.2);">Community</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="header">
            <div>
                <h2>Community Questions</h2>
                <p style="color: #666; margin-top: 5px;">Ask questions, help others, and learn together</p>
            </div>
            <a href="ask.php" class="ask-btn">Ask Question</a>
        </div>
        
        <div class="layout">
            <div class="questions-list">
                <?php foreach ($questions as $question): ?>
                    <div class="question-card" onclick="location.href='view.php?id=<?php echo $question['question_id']; ?>'">
                        <div class="question-stats">
                            <div class="stat">
                                <span class="stat-value"><?php echo $question['net_votes']; ?></span>
                                <span class="stat-label">Votes</span>
                            </div>
                            <div class="stat">
                                <span class="stat-value"><?php echo $question['answer_count']; ?></span>
                                <span class="stat-label">Answers</span>
                            </div>
                            <div class="stat">
                                <span class="stat-value"><?php echo $question['views']; ?></span>
                                <span class="stat-label">Views</span>
                            </div>
                        </div>
                        
                        <h3 class="question-title"><?php echo htmlspecialchars($question['title']); ?></h3>
                        
                        <div class="question-meta">
                            <span>Asked by <strong><?php echo htmlspecialchars($question['full_name']); ?></strong></span>
                            <span><?php echo date('M j, Y', strtotime($question['created_at'])); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <aside class="sidebar">
                <div class="sidebar-card">
                    <h3>Popular Tags</h3>
                    <div class="tags-list">
                        <?php foreach ($popularTags as $tag): ?>
                            <span class="tag"><?php echo htmlspecialchars($tag['tag_name']); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="sidebar-card">
                    <h3>Top Contributors</h3>
                    <div class="leaderboard-item">
                        <div><span class="rank">#1</span>View Leaderboard</div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</body>
</html>
