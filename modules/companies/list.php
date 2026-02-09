<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
$db = Database::getInstance();

Auth::requireLogin();

// Fetch companies
$companies = $db->fetchAll("SELECT * FROM companies WHERE is_active = 1 ORDER BY company_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Preparation - Placement Portal</title>
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
        }
        .header h2 { color: #667eea; margin-bottom: 10px; }
        
        .companies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        
        .company-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
            cursor: pointer;
            border-top: 5px solid #667eea;
        }
        .company-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .company-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin-bottom: 20px;
        }
        
        .company-card h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1.4rem;
        }
        
        .company-card p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        
        .company-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        .info-label {
            font-size: 0.75rem;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .info-value {
            font-weight: 600;
            color: #667eea;
        }
        
        .view-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 15px;
            transition: all 0.3s;
        }
        .view-btn:hover {
            transform: scale(1.02);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1>🏢 Company Specific Preparation</h1>
        <div class="nav-links">
            <a href="../dashboard/index.php">Dashboard</a>
            <a href="../aptitude/tests.php">Aptitude</a>
            <a href="../coding/problems.php">Coding</a>
            <a href="../community/questions.php">Community</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="header">
            <h2>Company-Specific Preparation</h2>
            <p>Prepare for your dream company with targeted resources, previous questions, and round-wise details</p>
        </div>
        
        <div class="companies-grid">
            <?php foreach ($companies as $company): ?>
                <div class="company-card" onclick="location.href='profile.php?id=<?php echo $company['company_id']; ?>'">
                    <div class="company-logo">
                        <?php echo strtoupper(substr($company['company_name'], 0, 1)); ?>
                    </div>
                    <h3><?php echo htmlspecialchars($company['company_name']); ?></h3>
                    <p><?php echo htmlspecialchars(substr($company['description'], 0, 120)); ?>...</p>
                    
                    <div class="company-info">
                        <div class="info-item">
                            <span class="info-label">Package Range</span>
                            <span class="info-value"><?php echo htmlspecialchars($company['package_range']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Resources</span>
                            <span class="info-value">Available</span>
                        </div>
                    </div>
                    
                    <button class="view-btn">View Details →</button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
