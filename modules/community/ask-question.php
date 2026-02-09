<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Community.php';

Auth::requireLogin();
$userId = Auth::getUserId();

// Handle question posting
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $community = new Community();
    $tags = isset($_POST['tags']) ? explode(',', $_POST['tags']) : [];
    $tags = array_map('trim', $tags);
    
    $result = $community->postQuestion(
        $userId,
        $_POST['title'],
        $_POST['content'],
        $tags
    );
    
    if ($result['success']) {
        header('Location: index.php');
        exit;
    }
    
    $error = $result['message'];
}

// Page config
$pageTitle = 'Ask Question - PlacementCode';
$additionalCSS = '
.container { max-width: 900px; margin: 30px auto; padding: 0 20px; }

.card {
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    border-radius: 12px;
    padding: 30px;
}

h1 { margin-bottom: 10px; color: #e4e4e7; }
.subtitle { color: #a1a1aa; margin-bottom: 30px; }

.form-group { margin-bottom: 25px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #e4e4e7; }
.form-control {
    width: 100%;
    background: #0f0f0f;
    border: 1px solid #2a2a2a;
    padding: 12px;
    border-radius: 8px;
    color: #e4e4e7;
    font-family: inherit;
    font-size: 1rem;
    transition: border-color 0.2s;
}
.form-control:focus {
    outline: none;
    border-color: #ffa116;
}
textarea.form-control { min-height: 200px; resize: vertical; }

.hint { font-size: 0.85rem; color: #71717a; margin-top: 6px; }

.btn-submit {
    background: linear-gradient(135deg, #ffa116, #ff6b6b);
    color: #fff;
    padding: 14px 32px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: opacity 0.2s;
}
.btn-submit:hover { opacity: 0.9; }

.btn-cancel {
    background: #2a2a2a;
    color: #e4e4e7;
    padding: 14px 32px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    margin-left: 10px;
    text-decoration: none;
    display: inline-block;
    transition: background 0.2s;
}
.btn-cancel:hover { background: #3a3a3a; }

.alert-error {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid rgba(239, 68, 68, 0.2);
}
';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="container">
    <div class="card">
        <h1>Ask a Question</h1>
        <p class="subtitle">Get help from the community</p>
        
        <?php if (isset($error)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" class="form-control" placeholder="e.g., How to reverse a linked list?" required>
                <div class="hint">Be specific and imagine you're asking a question to another person</div>
            </div>
            
            <div class="form-group">
                <label>Content *</label>
                <textarea name="content" class="form-control" placeholder="Describe your problem in detail..." required></textarea>
                <div class="hint">Include what you've tried and what you're trying to achieve</div>
            </div>
            
            <div class="form-group">
                <label>Tags</label>
                <input type="text" name="tags" class="form-control" placeholder="javascript, algorithms, data-structures (comma-separated)">
                <div class="hint">Add up to 5 tags to describe what your question is about</div>
            </div>
            
            <div>
                <button type="submit" class="btn-submit">Post Question</button>
                <a href="index.php" class="btn-cancel">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
