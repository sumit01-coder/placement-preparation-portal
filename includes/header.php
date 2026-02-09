<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'PlacementCode - Placement Preparation Platform'; ?></title>
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
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .logo { 
            font-size: 1.3rem; 
            font-weight: 700; 
            color: #ffa116;
            text-decoration: none;
        }
        
        .nav-menu { 
            display: flex; 
            gap: 25px;
            align-items: center;
        }
        
        .nav-menu a { 
            color: #a1a1aa; 
            text-decoration: none; 
            font-size: 0.95rem; 
            font-weight: 500; 
            transition: color 0.2s;
            padding: 8px 16px;
            border-radius: 6px;
        }
        
        .nav-menu a:hover { 
            color: #fff; 
            background: rgba(255, 161, 22, 0.1);
        }
        
        .nav-menu a.active { 
            color: #fff;
            background: rgba(255, 161, 22, 0.15);
        }
        
        .user-menu {
            position: relative;
        }
        
        .user-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #2a2a2a;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .user-btn:hover {
            background: #3a3a3a;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ffa116, #ff6b6b);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .user-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 8px;
            min-width: 220px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s ease;
            z-index: 1000;
        }
        
        .user-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #e4e4e7;
            text-decoration: none;
            transition: background 0.2s;
            font-size: 0.9rem;
        }
        
        .dropdown-item:first-child {
            border-radius: 8px 8px 0 0;
        }
        
        .dropdown-item:last-child {
            border-radius: 0 0 8px 8px;
        }
        
        .dropdown-item:hover {
            background: #2a2a2a;
        }
        
        .dropdown-item.logout {
            color: #ef4444;
        }
        
        .dropdown-item.logout:hover {
            background: rgba(239, 68, 68, 0.1);
        }
        
        .dropdown-divider {
            height: 1px;
            background: #2a2a2a;
            margin: 4px 0;
        }
        
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: #e4e4e7;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .nav-menu {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: #1a1a1a;
                flex-direction: column;
                padding: 20px;
                border-bottom: 1px solid #2a2a2a;
            }
            
            .nav-menu.active {
                display: flex;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .user-dropdown {
                right: auto;
                left: 0;
            }
        }
    </style>
    
    <?php if (isset($additionalCSS)): ?>
        <style><?php echo $additionalCSS; ?></style>
    <?php endif; ?>
</head>
<body>
