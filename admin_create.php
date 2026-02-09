<?php
include 'db.php'; // Ensures session_start() is called

$showLoginModal = false;
$loginError = "";
$successMsg = "";
$errorMsg = "";

// 1. HANDLE LOGIN SUBMISSION
if (isset($_POST['login_submit'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // In a real app, use password_verify() with hashed passwords.
    // For this demo, we check exact string match against the database.
    $stmt = $conn->prepare("SELECT id, role, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // CHECK THE PASSWORD HERE (Assuming plain text for demo, otherwise password_verify)
        if ($user['password'] === $password) { 
            
            if ($user['role'] === 'admin') {
                $_SESSION['user_id'] = $user['id'];
                // Refresh to clear post data and remove modal
                header("Location: admin_create.php");
                exit();
            } else {
                $loginError = "Access Denied: You are not an Admin.";
                $showLoginModal = true;
            }

        } else {
            $loginError = "Incorrect Password.";
            $showLoginModal = true;
        }
    } else {
        $loginError = "User not found.";
        $showLoginModal = true;
    }
}

// 2. CHECK AUTHORIZATION
if (!isset($_SESSION['user_id'])) {
    $showLoginModal = true;
} else {
    // Double check if current user is admin
    $uid = $_SESSION['user_id'];
    $check = $conn->query("SELECT role FROM users WHERE id = $uid");
    
    if ($check && $check->num_rows > 0) {
        $u = $check->fetch_assoc();
        if ($u['role'] !== 'admin') {
            $showLoginModal = true;
            $loginError = "You are currently logged in as a Student.";
        }
    } else {
        // Session exists but user not in DB (edge case)
        session_destroy();
        $showLoginModal = true;
    }
}

// 3. HANDLE PROBLEM CREATION (Only if authorized)
if (!$showLoginModal && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_problem'])) {
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $diff = $_POST['difficulty'];
    $in_fmt = trim($_POST['input_format']);
    $out_fmt = trim($_POST['output_format']);
    $samp_in = trim($_POST['sample_input']);
    $samp_out = trim($_POST['sample_output']);

    if(empty($title) || empty($desc)) {
        $errorMsg = "Title and Description are required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO problems (title, description, difficulty, input_format, output_format, sample_input, sample_output) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $title, $desc, $diff, $in_fmt, $out_fmt, $samp_in, $samp_out);
        if ($stmt->execute()) {
            $successMsg = "Problem created successfully!";
        } else {
            $errorMsg = "Database Error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Create Challenge</title>
    <style>
        :root {
            --primary: #2563eb; --primary-hover: #1d4ed8;
            --bg: #0f172a; --surface: #1e293b; --surface-light: #334155;
            --text: #f8fafc; --text-muted: #94a3b8;
            --success: #059669; --danger: #dc2626;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg); color: var(--text);
            margin: 0; padding: 0;
        }

        /* Utility for blurring content when logged out */
        .blur-content {
            filter: blur(5px);
            pointer-events: none;
            user-select: none;
        }

        /* --- Navbar & Layout --- */
        .navbar {
            background-color: var(--surface); padding: 15px 30px;
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid #334155;
        }
        .navbar h2 { margin: 0; font-size: 1.5rem; }
        .nav-link { color: var(--text-muted); text-decoration: none; font-weight: 500; transition: 0.2s; }
        .nav-link:hover { color: white; }
        .container { max-width: 800px; margin: 40px auto; padding: 0 20px; }

        /* --- Forms --- */
        .form-card {
            background: var(--surface); padding: 30px; border-radius: 12px;
            border: 1px solid #334155; box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        .form-header { margin-bottom: 25px; border-bottom: 1px solid #334155; padding-bottom: 15px; }
        .form-header h1 { margin: 0; font-size: 1.8rem; }
        
        label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-muted); font-size: 0.9rem; }
        input, textarea, select {
            width: 100%; padding: 12px; margin-bottom: 20px;
            background: var(--bg); border: 1px solid #334155; border-radius: 6px;
            color: white; font-family: inherit; transition: border-color 0.2s; box-sizing: border-box;
        }
        input:focus, textarea:focus, select:focus { outline: none; border-color: var(--primary); }

        .btn-create {
            background: var(--primary); color: white; padding: 12px 24px;
            border: none; border-radius: 6px; font-size: 1rem; font-weight: 600;
            cursor: pointer; width: 100%; transition: background 0.2s;
        }
        .btn-create:hover { background: var(--primary-hover); }

        .form-row { display: flex; gap: 20px; }
        .form-col { flex: 1; }
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: center; }
        .alert-success { background: rgba(5, 150, 105, 0.2); color: #34d399; border: 1px solid #059669; }
        .alert-error { background: rgba(220, 38, 38, 0.2); color: #f87171; border: 1px solid #dc2626; }

        /* --- LOGIN MODAL (Popup) --- */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.85); z-index: 1000;
            display: flex; justify-content: center; align-items: center;
            backdrop-filter: blur(5px);
        }
        .login-box {
            background: var(--surface); padding: 40px; border-radius: 16px;
            border: 1px solid #334155; width: 100%; max-width: 400px;
            text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            animation: slideDown 0.4s ease;
        }
        @keyframes slideDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        
        .login-box h2 { margin-top: 0; color: white; }
        .login-box p { color: var(--text-muted); margin-bottom: 30px; font-size: 0.9rem; }
        .login-btn {
            background: var(--primary); color: white; width: 100%; padding: 12px;
            border: none; border-radius: 6px; font-size: 1rem; cursor: pointer; margin-top: 10px;
        }
        .login-btn:hover { background: var(--primary-hover); }
        .error-msg { color: var(--danger); font-size: 0.9rem; margin-bottom: 15px; display: block; }

    </style>
</head>
<body>

    <?php if ($showLoginModal): ?>
    <div class="modal-overlay">
        <div class="login-box">
            <h2>Admin Access</h2>
            <p>Please enter your credentials to manage problems.</p>
            
            <?php if($loginError): ?>
                <span class="error-msg"><?php echo $loginError; ?></span>
            <?php endif; ?>

            <form method="POST">
                <div style="text-align: left;">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="e.g. admin1" required autofocus>
                    
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter password" required>
                </div>
                <button type="submit" name="login_submit" class="login-btn">Secure Login</button>
            </form>
            <div style="margin-top:20px;">
                <a href="index.php" style="color:var(--text-muted); font-size:0.9rem;">Back to Student Dashboard</a>
            </div>
        </div>
    </div>
    <?php endif; ?>


    <div class="<?php echo $showLoginModal ? 'blur-content' : ''; ?>">
        
        <div class="navbar">
            <h2>Secure<span style="color:var(--primary)">Code</span> Admin</h2>
            <a href="index.php" class="nav-link">&larr; Back to Dashboard</a>
        </div>

        <div class="container">
            <?php if(!empty($successMsg)): ?>
                <div class="alert alert-success"><?php echo $successMsg; ?></div>
            <?php endif; ?>
            <?php if(!empty($errorMsg)): ?>
                <div class="alert alert-error"><?php echo $errorMsg; ?></div>
            <?php endif; ?>

            <div class="form-card">
                <div class="form-header">
                    <h1>Create New Problem</h1>
                    <p>Fill in the details below to add a coding challenge to the database.</p>
                </div>

                <form method="POST">
                    <input type="hidden" name="create_problem" value="1">

                    <div class="form-row">
                        <div class="form-col">
                            <label>Problem Title</label>
                            <input type="text" name="title" placeholder="e.g. Reverse Linked List" required>
                        </div>
                        <div class="form-col">
                            <label>Difficulty</label>
                            <select name="difficulty">
                                <option value="Easy">Easy</option>
                                <option value="Medium">Medium</option>
                                <option value="Hard">Hard</option>
                            </select>
                        </div>
                    </div>

                    <label>Description</label>
                    <textarea name="description" rows="5" placeholder="Explain the problem statement clearly..." required></textarea>

                    <div class="form-row">
                        <div class="form-col">
                            <label>Input Format</label>
                            <textarea name="input_format" rows="3" placeholder="e.g. An integer array arr[]"></textarea>
                        </div>
                        <div class="form-col">
                            <label>Output Format</label>
                            <textarea name="output_format" rows="3" placeholder="e.g. The reversed array"></textarea>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <label>Sample Input</label>
                            <textarea name="sample_input" rows="3" placeholder="[1, 2, 3, 4, 5]" style="font-family: monospace;"></textarea>
                        </div>
                        <div class="form-col">
                            <label>Sample Output</label>
                            <textarea name="sample_output" rows="3" placeholder="[5, 4, 3, 2, 1]" style="font-family: monospace;"></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn-create">Publish Problem</button>
                </form>
            </div>
        </div>
    </div>

</body>
</html>