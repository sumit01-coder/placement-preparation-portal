<?php
require_once '../../config/config.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Aptitude.php';
require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

Auth::requireLogin();

$attemptId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($attemptId <= 0) {
    header('Location: tests.php');
    exit;
}

$aptitude = new Aptitude();
$result = $aptitude->getAttemptResult($attemptId);

if (!$result) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Result not found.</div></div>";
    require_once '../../includes/footer.php';
    exit;
}

// Format duration
$minutes = floor($result['duration_seconds'] / 60);
$seconds = $result['duration_seconds'] % 60;
$timeString = sprintf("%02dm %02ds", $minutes, $seconds);

// Determine badge color based on percentage
$scoreClass = 'text-danger';
if ($result['percentage'] >= 75) $scoreClass = 'text-success';
elseif ($result['percentage'] >= 50) $scoreClass = 'text-warning';

?>

<style>
    .result-card {
        background: #1a1a1a;
        border: 1px solid #333;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.4);
    }
    .score-circle {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        border: 8px solid #333;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        font-weight: 800;
        margin: 0 auto 20px;
        position: relative;
    }
    .score-circle.success { border-color: #22c55e; color: #22c55e; }
    .score-circle.warning { border-color: #eab308; color: #eab308; }
    .score-circle.danger { border-color: #ef4444; color: #ef4444; }
    
    .stat-box {
        background: #262626;
        padding: 15px;
        border-radius: 8px;
        text-align: center;
    }
    .stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #fff;
    }
    .stat-label {
        color: #a1a1aa;
        font-size: 0.9rem;
    }
    
    .review-card {
        background: #1a1a1a;
        border: 1px solid #333;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .review-card.correct { border-left: 4px solid #22c55e; }
    .review-card.incorrect { border-left: 4px solid #ef4444; }
    
    .badge-correct { background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid #15803d; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; }
    .badge-incorrect { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #b91c1c; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; }
</style>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Summary Card -->
            <div class="result-card mb-5 text-center">
                <h2 class="mb-4 text-white"><?php echo htmlspecialchars($result['test_name']); ?></h2>
                
                <div class="score-circle <?php echo ($result['percentage'] >= 75) ? 'success' : (($result['percentage'] >= 50) ? 'warning' : 'danger'); ?>">
                    <?php echo $result['percentage']; ?>%
                </div>
                
                <h4 class="text-white mb-4">You scored <?php echo $result['score']; ?> / <?php echo $result['total_marks']; ?></h4>
                
                <div class="row g-3">
                    <div class="col-4">
                        <div class="stat-box">
                            <div class="stat-value"><?php echo $timeString; ?></div>
                            <div class="stat-label">Time Taken</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-box">
                            <div class="stat-value"><?php echo count($result['answers']); ?></div>
                            <div class="stat-label">Questions</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-box">
                            <div class="stat-value"><?php echo ($result['percentage'] >= 75) ? 'Passed' : 'Average'; ?></div>
                            <div class="stat-label">Status</div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <a href="tests.php" class="btn btn-primary px-4">Take Another Test</a>
                </div>
            </div>
            
            <h4 class="text-white mb-3">Detailed Review</h4>
            
            <?php foreach ($result['answers'] as $index => $ans): 
                $statusClass = $ans['is_correct'] ? 'correct' : 'incorrect';
                $statusBadge = $ans['is_correct'] ? '<span class="badge-correct">Correct</span>' : '<span class="badge-incorrect">Incorrect</span>';
            ?>
                <div class="review-card <?php echo $statusClass; ?>">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Question <?php echo $index + 1; ?></span>
                        <?php echo $statusBadge; ?>
                    </div>
                    
                    <h5 class="text-white mb-3"><?php echo htmlspecialchars($ans['question_text']); ?></h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1 text-muted small">Your Answer:</p>
                            <p class="<?php echo $ans['is_correct'] ? 'text-success' : 'text-danger'; ?> fw-bold">
                                Option <?php echo $ans['selected_answer']; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1 text-muted small">Correct Answer:</p>
                            <p class="text-success fw-bold">
                                Option <?php echo $ans['correct_answer']; ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if (!empty($ans['explanation'])): ?>
                        <div class="mt-3 p-3 bg-dark rounded border border-secondary">
                            <small class="text-muted d-block mb-1">Explanation:</small>
                            <p class="mb-0 text-white small"><?php echo htmlspecialchars($ans['explanation']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
