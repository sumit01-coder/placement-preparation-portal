<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Aptitude.php';

Auth::requireLogin();

$testId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($testId <= 0) {
    header('Location: tests.php');
    exit;
}

$aptitude = new Aptitude();
$test = $aptitude->getTestDetails($testId);

if (!$test) {
    require_once __DIR__ . '/../../includes/header.php';
    require_once __DIR__ . '/../../includes/navbar.php';
    echo "<div class='container mt-5'><div class='alert alert-danger'>Test not found.</div></div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$questions = $test['questions'] ?? [];
if (empty($questions)) {
    require_once __DIR__ . '/../../includes/header.php';
    require_once __DIR__ . '/../../includes/navbar.php';
    ?>
    <div class="container mt-5">
        <div class="alert alert-warning">
            This test does not have questions yet. Please contact admin.
        </div>
        <a href="tests.php" class="btn btn-primary">Back to Tests</a>
    </div>
    <?php
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$attemptResult = $aptitude->startAttempt(Auth::getUserId(), $testId);
if (!$attemptResult['success']) {
    require_once __DIR__ . '/../../includes/header.php';
    require_once __DIR__ . '/../../includes/navbar.php';
    echo "<div class='container mt-5'><div class='alert alert-danger'>Error starting test: " . htmlspecialchars((string)$attemptResult['message']) . "</div></div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$attemptId = (int)$attemptResult['attempt_id'];
$durationMinutes = max(1, (int)($test['duration_minutes'] ?? 30));
$durationSeconds = $durationMinutes * 60;

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<style>
    body {
        background-color: #0d0d0d;
        color: #e0e0e0;
    }
    .test-header {
        background: #1a1a1a;
        padding: 15px 0;
        border-bottom: 2px solid #333;
        position: sticky;
        top: 0;
        z-index: 1000;
        box-shadow: 0 4px 10px rgba(0,0,0,0.5);
    }
    .timer-box {
        background: #2a2a2a;
        color: #ef4444;
        padding: 8px 16px;
        border-radius: 6px;
        font-weight: 700;
        font-family: 'Courier New', monospace;
        font-size: 1.2rem;
        border: 1px solid #444;
    }
    .question-card {
        background: #1a1a1a;
        border: 1px solid #333;
        border-radius: 8px;
        padding: 25px;
        margin-bottom: 25px;
    }
    .question-card:hover {
        border-color: #444;
    }
    .question-text {
        font-size: 1.1rem;
        font-weight: 500;
        margin-bottom: 20px;
        line-height: 1.6;
        color: #fff;
    }
    .option-label {
        display: block;
        padding: 12px 15px;
        margin-bottom: 10px;
        background: #262626;
        border: 1px solid #333;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .option-label:hover {
        background: #333;
    }
    .option-input {
        margin-right: 12px;
        accent-color: #ef4444;
    }
    .option-input:checked + span {
        color: #ef4444;
        font-weight: 600;
    }
    .question-badge {
        background: #333;
        color: #aaa;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
        margin-bottom: 15px;
        display: inline-block;
    }
    .btn-submit-exam {
        background: #ef4444;
        color: white;
        border: none;
        padding: 12px 30px;
        font-size: 1.1rem;
        border-radius: 8px;
        font-weight: 600;
        width: 100%;
    }
    .btn-submit-exam:hover {
        background: #dc2626;
    }
</style>

<div class="test-header">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <h4 class="m-0 text-white"><?php echo htmlspecialchars((string)($test['test_name'] ?? 'Aptitude Test')); ?></h4>
            <small class="text-muted">Total Questions: <?php echo count($questions); ?></small>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="timer-box" id="timer">00:00:00</div>
            <button type="button" onclick="confirmSubmit()" class="btn btn-danger btn-sm">Finish Test</button>
        </div>
    </div>
</div>

<div class="container py-5">
    <form id="testForm" action="submit-test.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(Auth::getCsrfToken()); ?>">
        <input type="hidden" name="attempt_id" value="<?php echo $attemptId; ?>">

        <?php foreach ($questions as $index => $q): ?>
            <div class="question-card" id="q<?php echo (int)$q['question_id']; ?>">
                <div class="d-flex justify-content-between">
                    <span class="question-badge">Question <?php echo $index + 1; ?></span>
                    <span class="text-muted small">Marks: <?php echo (int)($q['marks'] ?? 1); ?></span>
                </div>

                <div class="question-text">
                    <?php echo nl2br(htmlspecialchars((string)($q['question_text'] ?? ''))); ?>
                </div>

                <div class="options-list">
                    <?php
                    $options = ['A', 'B', 'C', 'D'];
                    foreach ($options as $opt):
                        $optText = trim((string)($q['option_' . strtolower($opt)] ?? ''));
                        if ($optText === '') {
                            continue;
                        }
                    ?>
                        <label class="option-label">
                            <input
                                type="radio"
                                name="answers[<?php echo (int)$q['question_id']; ?>]"
                                value="<?php echo $opt; ?>"
                                class="option-input"
                            >
                            <span><?php echo htmlspecialchars($optText); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="mt-4 mb-5">
            <button type="button" onclick="confirmSubmit()" class="btn-submit-exam">Submit Test</button>
        </div>
    </form>
</div>

<div id="submitModal" class="modal" tabindex="-1" style="display: none; background: rgba(0,0,0,0.8); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white">Submit Test?</h5>
            </div>
            <div class="modal-body text-gray-300">
                <p>Are you sure you want to finish the test? You cannot change your answers after submission.</p>
                <p id="unanswered-warning" class="text-warning small"></p>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="submitTest()">Yes, Submit</button>
            </div>
        </div>
    </div>
</div>

<script>
    let timeLeft = <?php echo $durationSeconds; ?>;
    const timerElement = document.getElementById('timer');
    let isSubmitting = false;

    function updateTimer() {
        const hours = Math.floor(timeLeft / 3600);
        const minutes = Math.floor((timeLeft % 3600) / 60);
        const seconds = timeLeft % 60;

        timerElement.textContent =
            String(hours).padStart(2, '0') + ':' +
            String(minutes).padStart(2, '0') + ':' +
            String(seconds).padStart(2, '0');

        if (timeLeft <= 0) {
            submitTest(true);
            return;
        }

        timeLeft -= 1;
        if (timeLeft < 300) {
            timerElement.style.color = '#ff0000';
            timerElement.style.borderColor = '#ff0000';
        }
    }

    const timerInterval = setInterval(updateTimer, 1000);
    updateTimer();

    function confirmSubmit() {
        const totalQuestions = <?php echo count($questions); ?>;
        const answered = document.querySelectorAll('input[type="radio"]:checked').length;
        const remaining = totalQuestions - answered;

        const warning = document.getElementById('unanswered-warning');
        if (remaining > 0) {
            warning.textContent = 'You have ' + remaining + ' unanswered question(s).';
        } else {
            warning.textContent = '';
        }

        document.getElementById('submitModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('submitModal').style.display = 'none';
    }

    function submitTest(force) {
        if (isSubmitting) {
            return;
        }
        isSubmitting = true;
        clearInterval(timerInterval);

        if (force === true) {
            alert('Time is up. Submitting your test automatically.');
        }
        document.getElementById('testForm').submit();
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
