<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Aptitude.php';

Auth::requireAdmin();

$userId = Auth::getUserId();
$db = Database::getInstance();

$aptitude = new Aptitude();
$message = $_GET['msg'] ?? '';
$error = $_GET['err'] ?? '';

$manageTestId = isset($_GET['manage']) ? (int)$_GET['manage'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $testId = (int)($_POST['test_id'] ?? 0);
        $result = ['success' => false, 'message' => 'Invalid action'];

        if ($action === 'update_test' && $testId > 0) {
            $result = $aptitude->updateTest($testId, $_POST);
        } elseif ($action === 'add_question' && $testId > 0) {
            $result = $aptitude->addQuestion($testId, $_POST);
        } elseif ($action === 'update_question' && $testId > 0) {
            $questionId = (int)($_POST['question_id'] ?? 0);
            $result = $aptitude->updateQuestion($testId, $questionId, $_POST);
        } elseif ($action === 'delete_question' && $testId > 0) {
            $questionId = (int)($_POST['question_id'] ?? 0);
            $result = $aptitude->deleteQuestion($testId, $questionId);
        }

        $key = $result['success'] ? 'msg' : 'err';
        $target = 'tests.php?manage=' . max(0, $testId) . '&' . $key . '=' . urlencode($result['message']);
        header('Location: ' . $target);
        exit();
    }
}

$tests = $aptitude->getTests();
$selectedTest = $manageTestId > 0 ? $aptitude->getTestDetails($manageTestId) : null;
if ($manageTestId > 0 && !$selectedTest && !$error) {
    $error = 'Selected test not found.';
}

$shortText = static function ($text, $length = 90) {
    $text = (string)$text;
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $length, '...');
    }
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, max(0, $length - 3)) . '...';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Management - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: #0a0a0a; color: #e4e4e7; min-height: 100vh; }
        .top-nav { background: #1a1a1a; border-bottom: 1px solid #2a2a2a; padding: 12px 5%; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.3rem; font-weight: 700; color: #ef4444; }
        .nav-menu { display: flex; gap: 25px; }
        .nav-menu a { color: #a1a1aa; text-decoration: none; font-size: 0.95rem; font-weight: 500; transition: color 0.2s; }
        .nav-menu a:hover, .nav-menu a.active { color: #fff; }
        .container { max-width: 1600px; margin: 0 auto; padding: 30px 20px; }
        .header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 12px; flex-wrap: wrap; }
        .btn-primary { background: #ef4444; color: #fff; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; border: none; cursor: pointer; }
        .alert { padding: 12px 15px; border-radius: 8px; margin-bottom: 16px; }
        .alert-success { background: rgba(34,197,94,0.14); color: #22c55e; border: 1px solid rgba(34,197,94,0.3); }
        .alert-error { background: rgba(239,68,68,0.14); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }
        .card { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 12px; overflow: hidden; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #0f0f0f; }
        th { text-align: left; padding: 14px 18px; font-size: 0.82rem; color: #71717a; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; }
        td { padding: 14px 18px; border-top: 1px solid #2a2a2a; vertical-align: top; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-outline { background: #2a2a2a; color: #e4e4e7; border: 1px solid #3a3a3a; border-radius: 6px; padding: 6px 10px; text-decoration: none; font-size: 0.85rem; }
        .btn-danger { background: rgba(239,68,68,0.15); color: #ef4444; border: 1px solid rgba(239,68,68,0.35); border-radius: 6px; padding: 6px 10px; cursor: pointer; }
        .panel { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .panel h2 { font-size: 1.2rem; margin-bottom: 14px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .grid-1 { grid-column: 1 / -1; }
        label { display: block; font-size: 0.86rem; color: #a1a1aa; margin-bottom: 6px; }
        input, select, textarea { width: 100%; background: #0f0f0f; border: 1px solid #2a2a2a; color: #e4e4e7; border-radius: 8px; padding: 10px 12px; font-family: inherit; }
        textarea { min-height: 96px; resize: vertical; }
        .form-actions { margin-top: 14px; display: flex; justify-content: flex-end; gap: 10px; }
        details { border: 1px solid #2a2a2a; border-radius: 8px; padding: 10px 12px; margin-bottom: 10px; background: #121212; }
        summary { cursor: pointer; font-weight: 600; color: #f3f4f6; }
        .muted { color: #a1a1aa; font-size: 0.9rem; }
        @media (max-width: 960px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <nav class="top-nav">
        <div class="logo">Admin Panel</div>
        <div class="nav-menu">
            <a href="dashboard.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="problems.php">Problems</a>
            <a href="tests.php" class="active">Tests</a>
            <a href="company-drives.php">Company Drives</a>
            <a href="analytics.php">Analytics</a>
        </div>
        <div style="color:#71717a;font-size:.9rem;">
            <a href="../dashboard/index.php" style="color:#ef4444;text-decoration:none;">Exit Admin</a>
        </div>
    </nav>

    <div class="container">
        <div class="header-row">
            <div>
                <h1 style="font-size:2rem;">Aptitude Tests</h1>
                <p class="muted">Edit tests and manage question sets from one place.</p>
            </div>
            <a href="add-test.php" class="btn-primary">Create New Test</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <?php if (empty($tests)): ?>
                <div style="padding:26px;" class="muted">No tests found.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Test</th>
                            <th>Category</th>
                            <th>Difficulty</th>
                            <th>Questions</th>
                            <th>Duration</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tests as $test): ?>
                            <tr>
                                <td>#<?php echo (int)$test['test_id']; ?></td>
                                <td><?php echo htmlspecialchars($test['test_name'] ?? 'Untitled'); ?></td>
                                <td><?php echo htmlspecialchars($test['category'] ?? 'General'); ?></td>
                                <td><?php echo htmlspecialchars($test['difficulty'] ?? 'Medium'); ?></td>
                                <td><?php echo (int)($test['question_count'] ?? $test['total_questions'] ?? 0); ?></td>
                                <td><?php echo (int)($test['duration_minutes'] ?? 0); ?> min</td>
                                <td>
                                    <div class="actions">
                                        <a class="btn-outline" href="tests.php?manage=<?php echo (int)$test['test_id']; ?>">Manage Questions</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php if ($selectedTest): ?>
            <div class="panel">
                <h2>Manage Test: <?php echo htmlspecialchars($selectedTest['test_name']); ?></h2>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(Auth::getCsrfToken()); ?>">
                    <input type="hidden" name="action" value="update_test">
                    <input type="hidden" name="test_id" value="<?php echo (int)$selectedTest['test_id']; ?>">
                    <div class="grid">
                        <div class="grid-1">
                            <label>Test Name</label>
                            <input type="text" name="test_name" value="<?php echo htmlspecialchars($selectedTest['test_name'] ?? ''); ?>" required>
                        </div>
                        <div>
                            <label>Category</label>
                            <select name="category">
                                <?php $currCat = $selectedTest['category'] ?? 'Quantitative'; ?>
                                <?php foreach (['Quantitative', 'Logical', 'Verbal'] as $cat): ?>
                                    <option value="<?php echo $cat; ?>" <?php echo $currCat === $cat ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Difficulty</label>
                            <?php $currDiff = $selectedTest['difficulty'] ?? 'Medium'; ?>
                            <select name="difficulty">
                                <?php foreach (['Easy', 'Medium', 'Hard'] as $diff): ?>
                                    <option value="<?php echo $diff; ?>" <?php echo strcasecmp($currDiff, $diff) === 0 ? 'selected' : ''; ?>><?php echo $diff; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Duration (minutes)</label>
                            <input type="number" min="1" name="duration_minutes" value="<?php echo (int)($selectedTest['duration_minutes'] ?? 30); ?>">
                        </div>
                        <div>
                            <label>Total Questions</label>
                            <input type="number" min="1" name="total_questions" value="<?php echo (int)($selectedTest['total_questions'] ?? count($selectedTest['questions'] ?? [])); ?>">
                        </div>
                        <div class="grid-1">
                            <label>Description</label>
                            <textarea name="description"><?php echo htmlspecialchars($selectedTest['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Save Test Changes</button>
                    </div>
                </form>
            </div>

            <div class="panel">
                <h2>Add Question</h2>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(Auth::getCsrfToken()); ?>">
                    <input type="hidden" name="action" value="add_question">
                    <input type="hidden" name="test_id" value="<?php echo (int)$selectedTest['test_id']; ?>">
                    <div class="grid">
                        <div class="grid-1">
                            <label>Question Text</label>
                            <textarea name="question_text" required></textarea>
                        </div>
                        <div><label>Option A</label><input type="text" name="option_a" required></div>
                        <div><label>Option B</label><input type="text" name="option_b" required></div>
                        <div><label>Option C</label><input type="text" name="option_c" required></div>
                        <div><label>Option D</label><input type="text" name="option_d" required></div>
                        <div><label>Correct Answer</label>
                            <select name="correct_answer" required>
                                <option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option>
                            </select>
                        </div>
                        <div><label>Difficulty</label>
                            <select name="difficulty">
                                <option value="Easy">Easy</option><option value="Medium" selected>Medium</option><option value="Hard">Hard</option>
                            </select>
                        </div>
                        <div><label>Marks</label><input type="number" min="1" name="marks" value="1"></div>
                        <div class="grid-1"><label>Explanation (optional)</label><textarea name="explanation"></textarea></div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Add Question</button>
                    </div>
                </form>
            </div>

            <div class="panel">
                <h2>Existing Questions (<?php echo count($selectedTest['questions'] ?? []); ?>)</h2>
                <?php if (empty($selectedTest['questions'])): ?>
                    <p class="muted">No questions yet for this test.</p>
                <?php else: ?>
                    <?php foreach ($selectedTest['questions'] as $index => $q): ?>
                        <details>
                            <summary>Q<?php echo $index + 1; ?>: <?php echo htmlspecialchars($shortText($q['question_text'] ?? '', 90)); ?></summary>
                            <form method="POST" style="margin-top:10px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(Auth::getCsrfToken()); ?>">
                                <input type="hidden" name="action" value="update_question">
                                <input type="hidden" name="test_id" value="<?php echo (int)$selectedTest['test_id']; ?>">
                                <input type="hidden" name="question_id" value="<?php echo (int)$q['question_id']; ?>">
                                <div class="grid">
                                    <div class="grid-1"><label>Question Text</label><textarea name="question_text" required><?php echo htmlspecialchars($q['question_text'] ?? ''); ?></textarea></div>
                                    <div><label>Option A</label><input type="text" name="option_a" value="<?php echo htmlspecialchars($q['option_a'] ?? ''); ?>" required></div>
                                    <div><label>Option B</label><input type="text" name="option_b" value="<?php echo htmlspecialchars($q['option_b'] ?? ''); ?>" required></div>
                                    <div><label>Option C</label><input type="text" name="option_c" value="<?php echo htmlspecialchars($q['option_c'] ?? ''); ?>" required></div>
                                    <div><label>Option D</label><input type="text" name="option_d" value="<?php echo htmlspecialchars($q['option_d'] ?? ''); ?>" required></div>
                                    <div><label>Correct Answer</label>
                                        <?php $currCorrect = strtoupper((string)($q['correct_answer'] ?? 'A')); ?>
                                        <select name="correct_answer"><?php foreach (['A','B','C','D'] as $opt): ?><option value="<?php echo $opt; ?>" <?php echo $currCorrect === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option><?php endforeach; ?></select>
                                    </div>
                                    <div><label>Difficulty</label>
                                        <?php $qDiff = $q['difficulty'] ?? 'Medium'; ?>
                                        <select name="difficulty"><?php foreach (['Easy','Medium','Hard'] as $d): ?><option value="<?php echo $d; ?>" <?php echo strcasecmp($qDiff, $d) === 0 ? 'selected' : ''; ?>><?php echo $d; ?></option><?php endforeach; ?></select>
                                    </div>
                                    <div><label>Marks</label><input type="number" min="1" name="marks" value="<?php echo (int)($q['marks'] ?? 1); ?>"></div>
                                    <div class="grid-1"><label>Explanation</label><textarea name="explanation"><?php echo htmlspecialchars($q['explanation'] ?? ''); ?></textarea></div>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn-primary">Update Question</button>
                                </div>
                            </form>
                            <form method="POST" onsubmit="return confirm('Delete this question?');" style="margin-top:8px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(Auth::getCsrfToken()); ?>">
                                <input type="hidden" name="action" value="delete_question">
                                <input type="hidden" name="test_id" value="<?php echo (int)$selectedTest['test_id']; ?>">
                                <input type="hidden" name="question_id" value="<?php echo (int)$q['question_id']; ?>">
                                <button class="btn-danger" type="submit">Delete Question</button>
                            </form>
                        </details>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
