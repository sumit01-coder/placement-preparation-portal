<?php
require_once 'config/config.php';

// Auto-redirect if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: modules/admin/dashboard.php');
        exit;
    }
    header('Location: modules/dashboard/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlacementCode - Master Your Career</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #0a0a0a;
            color: #e4e4e7;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Navbar */
        .navbar {
            padding: 20px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #2a2a2a;
            background: rgba(10, 10, 10, 0.8);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: #f8f9fa;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .logo span { color: #ffa116; }

        .nav-links {
            display: flex;
            gap: 30px;
        }

        .nav-link {
            color: #a1a1aa;
            text-decoration: none;
            font-size: 0.95rem;
            transition: color 0.2s;
        }

        .nav-link:hover { color: #fff; }

        .nav-link.admin-link { color: #fca5a5; }

        /* Hero Section */
        .hero {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
            background: radial-gradient(circle at 50% 10%, rgba(255, 161, 22, 0.05) 0%, rgba(10, 10, 10, 0) 50%);
        }

        .hero-container {
            max-width: 1200px;
            width: 100%;
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 60px;
            align-items: center;
        }

        .hero-text h1 {
            font-size: 3.5rem;
            line-height: 1.1;
            margin-bottom: 20px;
            background: linear-gradient(to right, #fff, #a1a1aa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .hero-text h1 span {
            background: linear-gradient(to right, #ffa116, #ff6b6b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-text p {
            font-size: 1.2rem;
            color: #a1a1aa;
            margin-bottom: 40px;
            line-height: 1.6;
            max-width: 500px;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 40px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #d4d4d8;
            font-size: 0.95rem;
        }

        .feature-icon {
            color: #ffa116;
            background: rgba(255, 161, 22, 0.1);
            padding: 8px;
            border-radius: 8px;
        }

        /* Auth Card */
        .auth-card {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 20px;
            padding: 40px;
            max-width: 480px;
            width: 100%;
            margin-left: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }

        .tabs {
            display: flex;
            margin-bottom: 30px;
            background: #121212;
            padding: 4px;
            border-radius: 10px;
            border: 1px solid #2a2a2a;
        }

        .tab {
            flex: 1;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            border-radius: 8px;
            color: #a1a1aa;
            font-weight: 500;
            transition: all 0.2s;
        }

        .tab.active {
            background: #2a2a2a;
            color: #fff;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #d4d4d8;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            background: #0f0f0f;
            border: 1px solid #2a2a2a;
            border-radius: 8px;
            color: #fff;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: #ffa116;
        }

        .btn-primary {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #ffa116, #ff6b6b);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .btn-primary:hover {
            opacity: 0.9;
        }

        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
            font-size: 0.9rem;
        }

        .alert.error { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        .alert.success { background: rgba(34, 197, 94, 0.15); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.2); }

        .hidden { display: none; }

        @media (max-width: 968px) {
            .hero-container {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .hero-text h1 { font-size: 2.5rem; }
            .hero-text p { margin: 0 auto 40px; }
            .feature-grid { justify-content: center; }
            .feature-item { justify-content: center; }
            .auth-card { margin: 0 auto; }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="index.php" class="logo">⚡ Placement<span>Code</span></a>
        <div class="nav-links">
            <a href="modules/auth/admin-login.php" class="nav-link admin-link">Admin Login</a>
        </div>
    </nav>

    <div class="hero">
        <div class="hero-container">
            <div class="hero-text">
                <h1>Master Your <br><span>Placement Journey</span></h1>
                <p>The comprehensive platform for coding practice, aptitude preparation, and distraction-free learning. Powered by industry-standard tools.</p>
                
                <div class="feature-grid">
                    <div class="feature-item">
                        <div class="feature-icon">💻</div>
                        <span>Smart Code Studio</span>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">🧠</div>
                        <span>Aptitude Engine</span>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">🎯</div>
                        <span>Focus Tracking</span>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">🚀</div>
                        <span>Career Toolkit</span>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">🏆</div>
                        <span>Global Leaderboard</span>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">👥</div>
                        <span>Community Q&A</span>
                    </div>
                </div>
            </div>

            <div class="auth-card">
                <div class="tabs">
                    <div class="tab active" onclick="switchTab('login')">Login</div>
                    <div class="tab" onclick="switchTab('register')">Register</div>
                </div>

                <!-- Login Form -->
                <form id="loginForm">
                    <div id="loginAlert" class="alert"></div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="john@example.com" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                    <button type="submit" class="btn-primary">Sign In</button>
                    <p style="text-align: center; margin-top: 15px; font-size: 0.9rem; color: #71717a;">
                        Admin? Use the dedicated <a href="modules/auth/admin-login.php" style="color:#fca5a5; text-decoration:none;">admin login</a>.
                    </p>
                </form>

                <!-- Register Form -->
                <form id="registerForm" class="hidden">
                    <div id="registerAlert" class="alert"></div>
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" placeholder="John Doe" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="john@example.com" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Min. 6 characters" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label class="form-label">College Name (Optional)</label>
                        <input type="text" name="college" class="form-control" placeholder="University Name">
                    </div>
                    <button type="submit" class="btn-primary">Create Account</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            if (tab === 'login') {
                document.querySelectorAll('.tab')[0].classList.add('active');
                document.getElementById('loginForm').classList.remove('hidden');
                document.getElementById('registerForm').classList.add('hidden');
            } else {
                document.querySelectorAll('.tab')[1].classList.add('active');
                document.getElementById('loginForm').classList.add('hidden');
                document.getElementById('registerForm').classList.remove('hidden');
            }
        }

        // Login Handler
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            const originalText = btn.textContent;
            btn.textContent = 'Signing in...';
            btn.disabled = true;

            const formData = new FormData(e.target);
            
            try {
                const response = await fetch('modules/auth/login.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                const alert = document.getElementById('loginAlert');
                alert.textContent = result.message;
                alert.style.display = 'block';
                
                if (result.success) {
                    alert.className = 'alert success';
                    setTimeout(() => window.location.href = result.redirect, 1000);
                } else {
                    alert.className = 'alert error';
                    btn.textContent = originalText;
                    btn.disabled = false;
                }
            } catch (error) {
                console.error('Error:', error);
                btn.textContent = originalText;
                btn.disabled = false;
            }
        });

        // Register Handler
        document.getElementById('registerForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            const originalText = btn.textContent;
            btn.textContent = 'Creating account...';
            btn.disabled = true;

            const formData = new FormData(e.target);
            
            try {
                const response = await fetch('modules/auth/register.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                const alert = document.getElementById('registerAlert');
                alert.textContent = result.message;
                alert.style.display = 'block';
                
                if (result.success) {
                    alert.className = 'alert success';
                    setTimeout(() => switchTab('login'), 2000);
                } else {
                    alert.className = 'alert error';
                }
            } catch (error) {
                console.error('Error:', error);
            } finally {
                btn.textContent = originalText;
                btn.disabled = false;
            }
        });
    </script>
</body>
</html>
