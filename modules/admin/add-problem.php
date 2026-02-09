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
    $result = $admin->addProblem($_POST);
    if ($result['success']) {
        $message = $result['message'];
    } else {
        $error = $result['message'];
    }
}
?>

<style>
    /* Scope styles to this page */
    .admin-form-container {
        max-width: 900px;
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
        color: #ef4444; /* Admin Red */
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
        margin-bottom: 25px;
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
        min-height: 120px;
        resize: vertical;
        line-height: 1.6;
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
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }

    .btn-cancel {
        background: #27272a;
        color: #a1a1aa;
        text-decoration: none;
        padding: 14px 28px;
        border-radius: 8px;
        font-weight: 600;
        transition: background 0.2s;
        margin-right: 15px;
    }

    .btn-cancel:hover {
        background: #3f3f46;
        color: #fff;
    }

    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 25px;
        font-weight: 500;
    }
    
    .alert-success { background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid #15803d; }
    .alert-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #b91c1c; }
</style>

<div class="container" style="padding-top: 40px; padding-bottom: 60px;">
    
    <?php if ($message): ?>
        <div class="alert alert-success">
            ✅ <?php echo htmlspecialchars($message); ?>
            <a href="problems.php" style="color: inherit; text-decoration: underline; margin-left: 10px;">View Problems</a>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="admin-form-container">
        <div class="form-header">
            <h2><span style="font-size: 1.5em;">⚡</span> Add Coding Problem</h2>
            <a href="problems.php" class="btn-cancel" style="padding: 8px 16px; font-size: 0.9rem;">Back to List</a>
        </div>

        <form method="POST" action="">
            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="form-label">Problem Title</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g., Two Sum" required>
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
                    <label class="form-label">Tags (comma separated)</label>
                    <input type="text" name="tags" class="form-control" placeholder="Arrays, Hash Table, Dynamic Programming">
                </div>

                <div class="form-group full-width">
                    <label class="form-label">Problem Description</label>
                    <textarea name="description" class="form-control" required placeholder="Detailed problem statement..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Input Format</label>
                    <textarea name="input_format" class="form-control" placeholder="First line contains an integer N..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Output Format</label>
                    <textarea name="output_format" class="form-control" placeholder="Print the sum of elements..."></textarea>
                </div>

                <div class="form-group full-width">
                    <label class="form-label">Constraints</label>
                    <textarea name="constraints" class="form-control" rows="3" placeholder="1 <= N <= 10^5..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Sample Input</label>
                    <textarea name="sample_input" class="form-control" style="font-family: monospace;" required></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Sample Output</label>
                    <textarea name="sample_output" class="form-control" style="font-family: monospace;" required></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Time Limit (seconds)</label>
                    <input type="number" name="time_limit" class="form-control" value="2" min="1" step="0.5">
                </div>

                <div class="form-group">
                    <label class="form-label">Memory Limit (MB)</label>
                    <input type="number" name="memory_limit" class="form-control" value="256" min="64" step="64">
                </div>
            </div>

            <div style="margin-top: 30px; display: flex; justify-content: flex-end;">
                <a href="problems.php" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn-submit">Save Problem</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
