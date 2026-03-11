<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';

if (Auth::isAdmin()) {
    header('Location: ../admin/dashboard.php');
    exit;
}

if (Auth::isLoggedIn()) {
    header('Location: ../dashboard/index.php');
    exit;
}

$defaultAdminEmail = 'admin@placementportal.com';
$defaultAdminPassword = 'password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Placement Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --bg: #09090b;
            --panel: #18181b;
            --panel-2: #111114;
            --border: #2f2f35;
            --text: #f4f4f5;
            --muted: #a1a1aa;
            --accent: #ef4444;
            --accent-2: #f59e0b;
            --success: #22c55e;
        }

        body {
            min-height: 100vh;
            font-family: 'Inter', system-ui, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(239, 68, 68, 0.14), transparent 28%),
                radial-gradient(circle at bottom right, rgba(245, 158, 11, 0.12), transparent 28%),
                linear-gradient(180deg, #0b0b0d, #09090b);
            color: var(--text);
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .shell {
            width: min(980px, 100%);
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            border: 1px solid var(--border);
            border-radius: 24px;
            overflow: hidden;
            background: rgba(17, 17, 20, 0.88);
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(16px);
        }

        .intro {
            padding: 48px;
            background:
                linear-gradient(135deg, rgba(239, 68, 68, 0.08), transparent 45%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.02), transparent),
                #101014;
            border-right: 1px solid var(--border);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(239, 68, 68, 0.12);
            color: #fca5a5;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .intro h1 {
            margin-top: 20px;
            font-size: 3rem;
            line-height: 1;
        }

        .intro p {
            margin-top: 18px;
            color: var(--muted);
            font-size: 1rem;
            line-height: 1.7;
            max-width: 34rem;
        }

        .notes {
            margin-top: 28px;
            display: grid;
            gap: 14px;
        }

        .note {
            padding: 16px 18px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.02);
        }

        .note strong {
            display: block;
            margin-bottom: 6px;
            color: var(--text);
        }

        .note span {
            color: var(--muted);
            font-size: 0.92rem;
            line-height: 1.5;
        }

        .panel {
            padding: 48px 40px;
            background: var(--panel);
        }

        .panel h2 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .panel p {
            color: var(--muted);
            margin-bottom: 26px;
            line-height: 1.6;
        }

        .alert {
            display: none;
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 0.92rem;
            border: 1px solid transparent;
        }

        .alert.error {
            display: block;
            color: #fda4af;
            background: rgba(190, 24, 93, 0.12);
            border-color: rgba(244, 63, 94, 0.24);
        }

        .alert.success {
            display: block;
            color: #86efac;
            background: rgba(34, 197, 94, 0.12);
            border-color: rgba(34, 197, 94, 0.24);
        }

        .field {
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.92rem;
            color: #d4d4d8;
            font-weight: 600;
        }

        .field input {
            width: 100%;
            padding: 13px 14px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: var(--panel-2);
            color: var(--text);
            font: inherit;
        }

        .field input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.14);
        }

        .submit {
            width: 100%;
            margin-top: 8px;
            border: 0;
            border-radius: 14px;
            padding: 14px 16px;
            font: inherit;
            font-weight: 700;
            color: white;
            cursor: pointer;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
        }

        .submit:disabled {
            opacity: 0.7;
            cursor: wait;
        }

        .default-box {
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: 14px;
            background: rgba(245, 158, 11, 0.08);
            border: 1px solid rgba(245, 158, 11, 0.18);
        }

        .default-box strong {
            display: block;
            margin-bottom: 8px;
            color: #fcd34d;
        }

        .default-box code {
            display: block;
            color: #fde68a;
            font-size: 0.9rem;
            margin-top: 4px;
        }

        .back-link {
            display: inline-block;
            margin-top: 18px;
            color: var(--muted);
            text-decoration: none;
            font-size: 0.92rem;
        }

        .back-link:hover {
            color: var(--text);
        }

        @media (max-width: 900px) {
            .shell {
                grid-template-columns: 1fr;
            }

            .intro {
                border-right: 0;
                border-bottom: 1px solid var(--border);
            }

            .intro,
            .panel {
                padding: 32px 24px;
            }

            .intro h1 {
                font-size: 2.3rem;
            }
        }
    </style>
</head>
<body>
    <div class="shell">
        <section class="intro">
            <div class="eyebrow">Admin Access</div>
            <h1>Manage the portal without mixing into student login.</h1>
            <p>This entry point only accepts administrator accounts and sends them straight to the admin dashboard after authentication.</p>

            <div class="notes">
                <div class="note">
                    <strong>Role-gated</strong>
                    <span>Non-admin accounts are blocked even with valid credentials.</span>
                </div>
                <div class="note">
                    <strong>Uses existing auth</strong>
                    <span>The page posts into the same backend login flow and preserves the current session model.</span>
                </div>
            </div>
        </section>

        <section class="panel">
            <h2>Admin Sign In</h2>
            <p>Use your administrator email and password.</p>

            <div id="loginAlert" class="alert"></div>

            <form id="adminLoginForm">
                <input type="hidden" name="admin_only" value="1">

                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" placeholder="admin@placementportal.com" required>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="submit">Enter Admin Panel</button>
            </form>

            <div class="default-box">
                <strong>Imported SQL default admin</strong>
                <code>Email: <?php echo htmlspecialchars($defaultAdminEmail); ?></code>
                <code>Password: <?php echo htmlspecialchars($defaultAdminPassword); ?></code>
            </div>

            <a class="back-link" href="<?php echo BASE_URL; ?>/index.php">Back to main login</a>
        </section>
    </div>

    <script>
        document.getElementById('adminLoginForm').addEventListener('submit', async (event) => {
            event.preventDefault();

            const form = event.currentTarget;
            const button = form.querySelector('button[type="submit"]');
            const alertBox = document.getElementById('loginAlert');
            const originalText = button.textContent;
            const formData = new FormData(form);

            button.disabled = true;
            button.textContent = 'Signing in...';
            alertBox.className = 'alert';
            alertBox.textContent = '';

            try {
                const response = await fetch('login.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                alertBox.textContent = result.message || 'Login failed';
                alertBox.className = result.success ? 'alert success' : 'alert error';

                if (result.success) {
                    setTimeout(() => {
                        window.location.href = '<?php echo BASE_URL; ?>/modules/admin/dashboard.php';
                    }, 700);
                } else {
                    button.disabled = false;
                    button.textContent = originalText;
                }
            } catch (error) {
                alertBox.textContent = 'Unable to sign in right now';
                alertBox.className = 'alert error';
                button.disabled = false;
                button.textContent = originalText;
            }
        });
    </script>
</body>
</html>
