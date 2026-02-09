<?php
require_once '../../config/config.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Admin.php';
require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

Auth::requireAdmin();

$admin = new Admin();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $admin->addTest($_POST);
    if ($result['success']) {
        $message = $result['message'];
    } else {
        $error = $result['message'];
    }
}
?>

<style>
    .admin-form-container {
        max-width: 800px;
        margin: 40px auto;
        padding: 30px;
        background: #1a1a1a;
        border-radius: 12px;
        border: 1px solid #333;
        box-shadow: 0 4px 20px rgba(0,0,0,0.4);
    }
    
    .form-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid #333;
    }
    
    .form-header h2 {
        color: #ef4444;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-label {
        display: block;
        margin-bottom: 8px;
        color: #a1a1aa;
        font-weight: 500;
        font-size: 0.95rem;
    }

    .form-control {
        width: 100%;
        padding: 12px;
        background: #0f0f0f;
        border: 1px solid #333;
        border-radius: 8px;
        color: #fff;
        font-family: 'Inter', sans-serif;
        font-size: 0.95rem;
        transition: border-color 0.2s;
    }

    .form-control:focus {
        outline: none;
        border-color: #ef4444;
    }

    textarea.form-control {
        min-height: 100px;
        resize: vertical;
    }

    .btn-submit {
        background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
        color: white;
        border: none;
        padding: 14px 28px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        font-size: 1rem;
        transition: transform 0.2s;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
    }

    .btn-cancel {
        background: #27272a;
        color: #a1a1aa;
        padding: 14px 28px;
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
        margin-right: 15px;
        transition: background 0.2s;
    }

    .btn-cancel:hover {
        background: #3f3f46;
        color: #fff;
    }

    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 25px;
    }
    
    .alert-success { background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid #15803d; }
    .alert-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #b91c1c; }
</style>

<div class="container" style="padding-top: 40px; padding-bottom: 60px;">
    
    <?php if ($message): ?>
        <div class="alert alert-success">✅ <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="admin-form-container">
        <div class="form-header">
            <h2><span style="font-size: 1.5em;">📝</span> Create Aptitude Test</h2>
            <a href="tests.php" class="btn-cancel" style="padding: 8px 16px; font-size: 0.9rem;">Back to List</a>
        </div>

        <form method="POST" action="">
            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="form-label">Test Name</label>
                    <input type="text" name="test_name" class="form-control" placeholder="e.g., Logical Reasoning Set A" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-control" required>
                        <option value="Quantitative">Quantitative</option>
                        <option value="Logical">Logical</option>
                        <option value="Verbal">Verbal</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Difficulty</label>
                    <select name="difficulty" class="form-control" required>
                        <option value="Easy">Easy</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="Hard">Hard</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Duration (minutes)</label>
                    <input type="number" name="duration_minutes" class="form-control" value="30" min="5" step="5" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Total Questions</label>
                    <input type="number" name="total_questions" class="form-control" value="20" min="1" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Passing Score (Min. Correct)</label>
                    <input type="number" name="passing_score" class="form-control" value="8" min="1">
                </div>

                <div class="form-group full-width">
                    <label class="form-label">Description (Optional)</label>
                    <textarea name="description" class="form-control" placeholder="Brief description of topics covered..."></textarea>
                </div>
            </div>

            <div style="margin-top: 30px; display: flex; justify-content: flex-end;">
                <a href="tests.php" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn-submit">Create Test</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
