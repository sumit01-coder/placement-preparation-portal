<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Submission.php';

Auth::requireLogin();
$userId = Auth::getUserId();

$submissionId = $_GET['id'] ?? null;
if (!$submissionId) {
    header('Location: submissions.php');
    exit;
}

$submission = new Submission();
$sub = $submission->getSubmissionById($submissionId, $userId);

if (!$sub) {
    header('Location: submissions.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Submission - <?php echo htmlspecialchars($sub['title']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 1000px; margin: 0 auto; }
        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        h1 { color: #667eea; margin-bottom: 15px; }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .info-item strong { color: #667eea; }
        .status-accepted { color: #2ea043; }
        .status-wrong { color: #da3633; }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .CodeMirror { height: auto; border: 1px solid #ddd; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1><?php echo htmlspecialchars($sub['title']); ?></h1>
            
            <div class="info-grid">
                <div class="info-item">
                    <strong>Status:</strong> 
                    <span class="status-<?php echo $sub['status'] == 'accepted' ? 'accepted' : 'wrong'; ?>">
                        <?php echo ucwords(str_replace('_', ' ', $sub['status'])); ?>
                    </span>
                </div>
                <div class="info-item">
                    <strong>Language:</strong> <?php echo htmlspecialchars($sub['language_name']); ?>
                </div>
                <div class="info-item">
                    <strong>Runtime:</strong> <?php echo number_format($sub['runtime'] ?? 0, 3); ?>s
                </div>
                <div class="info-item">
                    <strong>Memory:</strong> <?php echo number_format($sub['memory_used'] ?? 0); ?> KB
                </div>
                <div class="info-item">
                    <strong>Test Cases:</strong> <?php echo $sub['passed_tests'] . '/' . $sub['total_tests']; ?>
                </div>
                <div class="info-item">
                    <strong>Submitted:</strong> <?php echo date('M d, Y H:i', strtotime($sub['submitted_at'])); ?>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h2 style="color: #667eea; margin-bottom: 15px;">Source Code</h2>
            <textarea id="codeViewer"><?php echo htmlspecialchars($sub['source_code']); ?></textarea>
        </div>
        
        <div style="text-align: center;">
            <a href="submissions.php" class="btn">← Back to Submissions</a>
            <a href="editor.php?id=<?php echo $sub['problem_id']; ?>" class="btn">Try Again</a>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/python/python.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/clike/clike.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
    <script>
        const editor = CodeMirror.fromTextArea(document.getElementById('codeViewer'), {
            mode: '<?php echo $sub['language_id'] == 71 ? "python" : "text/x-c++src"; ?>',
            theme: 'monokai',
            lineNumbers: true,
            readOnly: true
        });
    </script>
</body>
</html>
