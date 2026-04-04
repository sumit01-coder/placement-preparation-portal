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

$existingAttemptId = $aptitude->getLatestCompletedAttemptIdForTest((int)Auth::getUserId(), $testId);
if ($existingAttemptId) {
    header('Location: result.php?id=' . (int)$existingAttemptId);
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
    :root {
        --bg: #0a0a0a;
        --surface: #121212;
        --surface-2: #1a1a1a;
        --border: #2a2a2a;
        --border-2: #333333;
        --text: #e4e4e7;
        --muted: #a1a1aa;
        --muted-2: #71717a;
        --accent: #ffa116;
        --danger: #ef4444;
        --danger-2: #dc2626;
        --shadow: 0 10px 30px rgba(0, 0, 0, 0.45);
    }

    body { background: var(--bg); color: var(--text); }

    .container {
        width: 100%;
        max-width: 1280px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .test-header {
        background: rgba(18, 18, 18, 0.9);
        border-bottom: 1px solid var(--border);
        position: sticky;
        top: 0;
        z-index: 1000;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }

    .test-header-inner {
        padding: 14px 0 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
    }

    .test-title { margin: 0; color: #fff; font-size: 1.05rem; font-weight: 700; }
    .test-subtitle { color: var(--muted); font-size: 0.9rem; margin-top: 2px; }

    .header-right {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .meta-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 161, 22, 0.1);
        border: 1px solid rgba(255, 161, 22, 0.25);
        color: var(--text);
        padding: 8px 10px;
        border-radius: 999px;
        font-size: 0.85rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .timer-box {
        background: var(--surface-2);
        color: var(--accent);
        padding: 8px 12px;
        border-radius: 10px;
        font-weight: 800;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: 1.05rem;
        border: 1px solid var(--border);
        letter-spacing: 0.02em;
    }

    .timer-box.is-danger { color: #fff; border-color: rgba(239, 68, 68, 0.5); background: rgba(239, 68, 68, 0.18); }

    .btn-finish {
        border: 1px solid rgba(239, 68, 68, 0.5);
        background: rgba(239, 68, 68, 0.12);
        color: #fecaca;
        padding: 8px 12px;
        border-radius: 10px;
        font-weight: 700;
        cursor: pointer;
    }
    .btn-finish:hover { background: rgba(239, 68, 68, 0.18); }

    .time-progress {
        height: 6px;
        background: rgba(255, 255, 255, 0.06);
        border-top: 1px solid rgba(255, 255, 255, 0.04);
    }
    .time-progress > div {
        height: 100%;
        width: 100%;
        background: linear-gradient(90deg, var(--accent), #ff6b6b);
        transform-origin: left center;
    }

    .qnav-strip {
        display: none;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        padding: 10px 0;
        overflow-x: auto;
    }
    .qnav-strip::-webkit-scrollbar { height: 8px; }
    .qnav-strip::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.12); border-radius: 999px; }

    .qnav-grid {
        display: grid;
        grid-template-columns: repeat(8, 1fr);
        gap: 8px;
    }

    .qnav-btn {
        appearance: none;
        border: 1px solid var(--border);
        background: rgba(255, 255, 255, 0.04);
        color: var(--muted);
        border-radius: 10px;
        padding: 8px 0;
        font-weight: 700;
        cursor: pointer;
        min-width: 42px;
        transition: transform 0.12s ease, background 0.12s ease, border-color 0.12s ease, color 0.12s ease;
    }
    .qnav-btn:hover { transform: translateY(-1px); border-color: rgba(255, 161, 22, 0.35); color: var(--text); }
    .qnav-btn.is-answered { background: rgba(255, 161, 22, 0.14); border-color: rgba(255, 161, 22, 0.35); color: #fff; }
    .qnav-btn.is-active { outline: 2px solid rgba(255, 161, 22, 0.35); outline-offset: 2px; }

    .test-shell {
        padding: 26px 0 60px;
        display: grid;
        grid-template-columns: 1fr 320px;
        gap: 22px;
        align-items: start;
    }

    .aside-card {
        background: var(--surface-2);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 16px;
        position: sticky;
        top: 86px;
        box-shadow: var(--shadow);
    }

    .aside-title { font-weight: 800; color: #fff; margin-bottom: 10px; }
    .aside-hint { color: var(--muted-2); font-size: 0.85rem; margin-top: 10px; line-height: 1.4; }

    .question-card {
        background: var(--surface-2);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 22px;
        margin-bottom: 18px;
        box-shadow: 0 2px 0 rgba(0,0,0,0.25);
    }
    .question-card.answered { border-color: rgba(34, 197, 94, 0.25); }
    .question-card:target { outline: 2px solid rgba(255, 161, 22, 0.35); outline-offset: 3px; }

    .question-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 10px;
    }

    .question-badge {
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.08);
        color: var(--muted);
        padding: 5px 10px;
        border-radius: 999px;
        font-size: 0.82rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .marks-pill {
        color: var(--muted);
        font-size: 0.82rem;
        border: 1px solid rgba(255, 255, 255, 0.08);
        padding: 5px 10px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.04);
        white-space: nowrap;
    }

    .question-text {
        font-size: 1.05rem;
        font-weight: 600;
        margin: 10px 0 14px;
        line-height: 1.6;
        color: #fff;
    }

    .option-label {
        display: grid;
        grid-template-columns: 20px 26px 1fr;
        gap: 10px;
        align-items: start;
        padding: 12px 12px;
        margin-bottom: 10px;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 12px;
        cursor: pointer;
        transition: background 0.12s ease, border-color 0.12s ease, transform 0.12s ease;
    }

    .option-label:hover { background: rgba(255, 255, 255, 0.05); border-color: rgba(255, 161, 22, 0.20); transform: translateY(-1px); }

    .option-input { margin-top: 2px; accent-color: var(--accent); }

    .option-letter {
        width: 26px;
        height: 26px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.08);
        color: var(--muted);
        font-weight: 800;
        font-size: 0.85rem;
        line-height: 1;
        user-select: none;
    }

    .option-text { color: var(--text); line-height: 1.45; }

    .option-input:checked ~ .option-letter {
        background: rgba(255, 161, 22, 0.14);
        border-color: rgba(255, 161, 22, 0.30);
        color: #fff;
    }

    .option-input:checked ~ .option-text { color: #fff; font-weight: 600; }

    .btn-submit-exam {
        background: linear-gradient(135deg, var(--accent), #ff6b6b);
        color: #0b0b0b;
        border: none;
        padding: 14px 18px;
        font-size: 1.05rem;
        border-radius: 14px;
        font-weight: 800;
        width: 100%;
        cursor: pointer;
        box-shadow: var(--shadow);
    }
    .btn-submit-exam:hover { filter: brightness(1.02); }

    .modal-shell {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 18px;
    }
    .modal-card {
        width: 100%;
        max-width: 520px;
        background: var(--surface-2);
        border: 1px solid var(--border);
        border-radius: 16px;
        box-shadow: var(--shadow);
        overflow: hidden;
    }
    .modal-card header {
        padding: 16px 18px;
        border-bottom: 1px solid rgba(255,255,255,0.08);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
    }
    .modal-card header h5 { margin: 0; color: #fff; font-size: 1.05rem; }
    .modal-card .modal-body { padding: 16px 18px; color: var(--text); }
    .modal-card .modal-actions {
        padding: 14px 18px;
        border-top: 1px solid rgba(255,255,255,0.08);
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        flex-wrap: wrap;
    }
    .btn {
        border: 1px solid rgba(255,255,255,0.14);
        background: rgba(255,255,255,0.06);
        color: #fff;
        padding: 10px 12px;
        border-radius: 12px;
        cursor: pointer;
        font-weight: 700;
    }
    .btn:hover { background: rgba(255,255,255,0.10); }
    .btn.primary { border-color: rgba(255, 161, 22, 0.35); background: rgba(255, 161, 22, 0.14); }
    .btn.danger { border-color: rgba(239, 68, 68, 0.5); background: rgba(239, 68, 68, 0.16); }

    @media (max-width: 1024px) {
        .test-shell { grid-template-columns: 1fr; }
        .aside-card { position: static; }
    }

    @media (max-width: 720px) {
        .test-header-inner { align-items: flex-start; }
        .qnav-strip { display: block; }
        .qnav-grid { grid-template-columns: repeat(10, 1fr); }
        .aside-card { display: none; }
    }
</style>

<div class="test-header">
    <div class="container">
        <div class="test-header-inner">
            <div>
                <h4 class="test-title"><?php echo htmlspecialchars((string)($test['test_name'] ?? 'Aptitude Test')); ?></h4>
                <div class="test-subtitle">Answer all questions before time runs out.</div>
            </div>
            <div class="header-right">
                <div class="meta-pill" id="answeredPill">
                    Answered <span id="answeredCount">0</span> / <?php echo count($questions); ?>
                </div>
                <div class="timer-box" id="timer" aria-live="polite">00:00:00</div>
                <button type="button" onclick="confirmSubmit()" class="btn-finish">Finish</button>
            </div>
        </div>
    </div>
    <div class="time-progress" aria-hidden="true">
        <div id="timeProgressBar"></div>
    </div>
    <div class="qnav-strip" aria-label="Question navigation">
        <div class="container">
            <div class="qnav-grid" id="qnavStrip">
                <?php foreach ($questions as $index => $q): ?>
                    <button
                        type="button"
                        class="qnav-btn"
                        data-target="q<?php echo (int)$q['question_id']; ?>"
                        aria-label="Jump to question <?php echo $index + 1; ?>"
                    >
                        <?php echo $index + 1; ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="container test-shell">
    <div>
        <form id="testForm" action="submit-test.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(Auth::getCsrfToken()); ?>">
            <input type="hidden" name="attempt_id" value="<?php echo $attemptId; ?>">

            <?php foreach ($questions as $index => $q): ?>
                <div
                    class="question-card"
                    id="q<?php echo (int)$q['question_id']; ?>"
                    data-question-id="<?php echo (int)$q['question_id']; ?>"
                >
                    <div class="question-top">
                        <span class="question-badge">
                            Q<?php echo $index + 1; ?>
                            <span style="color: var(--muted-2); font-weight: 700;">/ <?php echo count($questions); ?></span>
                        </span>
                        <span class="marks-pill">Marks: <?php echo (int)($q['marks'] ?? 1); ?></span>
                    </div>

                    <div class="question-text">
                        <?php echo nl2br(htmlspecialchars((string)($q['question_text'] ?? ''))); ?>
                    </div>

                    <div class="options-list" role="group" aria-label="Options for question <?php echo $index + 1; ?>">
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
                                <span class="option-letter"><?php echo $opt; ?></span>
                                <span class="option-text"><?php echo htmlspecialchars($optText); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div style="margin-top: 18px;">
                <button type="button" onclick="confirmSubmit()" class="btn-submit-exam">Submit Test</button>
            </div>
        </form>
    </div>

    <aside class="aside-card" aria-label="Question navigator">
        <div class="aside-title">Questions</div>
        <div class="qnav-grid" id="qnavAside">
            <?php foreach ($questions as $index => $q): ?>
                <button
                    type="button"
                    class="qnav-btn"
                    data-target="q<?php echo (int)$q['question_id']; ?>"
                    aria-label="Jump to question <?php echo $index + 1; ?>"
                >
                    <?php echo $index + 1; ?>
                </button>
            <?php endforeach; ?>
        </div>
        <div class="aside-hint">
            Tip: answered questions turn highlighted. Use Finish when you’re ready.
        </div>
    </aside>
</div>

<div id="submitModal" class="modal" tabindex="-1" style="display: none; align-items: center; justify-content: center; background: rgba(0,0,0,0.8); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050;">
    <div class="modal-shell" role="dialog" aria-modal="true" aria-labelledby="submitTitle">
        <div class="modal-card">
            <header>
                <h5 id="submitTitle">Submit test?</h5>
            </header>
            <div class="modal-body">
                <p style="margin: 0 0 10px;">
                    You can’t change answers after submission.
                </p>
                <p id="unanswered-warning" style="margin: 0; color: #fbbf24; font-size: 0.9rem; font-weight: 700;"></p>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn danger" onclick="submitTest()">Yes, submit</button>
            </div>
        </div>
    </div>
</div>

<script>
    let timeLeft = <?php echo $durationSeconds; ?>;
    const totalTime = <?php echo $durationSeconds; ?>;
    const timerElement = document.getElementById('timer');
    const timeProgressBar = document.getElementById('timeProgressBar');
    let isSubmitting = false;

    function clamp(num, min, max) {
        return Math.min(max, Math.max(min, num));
    }

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

        const ratio = totalTime > 0 ? (timeLeft / totalTime) : 0;
        if (timeProgressBar) {
            timeProgressBar.style.transform = 'scaleX(' + clamp(ratio, 0, 1) + ')';
        }

        if (timeLeft <= 300) {
            timerElement.classList.add('is-danger');
        }
    }

    const timerInterval = setInterval(updateTimer, 1000);
    updateTimer();

    function updateAnsweredUI() {
        const answered = document.querySelectorAll('input[type="radio"]:checked').length;
        const answeredCount = document.getElementById('answeredCount');
        if (answeredCount) {
            answeredCount.textContent = String(answered);
        }

        const cards = document.querySelectorAll('.question-card[data-question-id]');
        for (const card of cards) {
            const qid = card.getAttribute('data-question-id');
            const isAnswered = card.querySelector('input[type="radio"]:checked') !== null;
            card.classList.toggle('answered', isAnswered);

            const selector = '.qnav-btn[data-target="q' + qid + '"]';
            document.querySelectorAll(selector).forEach((btn) => {
                btn.classList.toggle('is-answered', isAnswered);
            });
        }
    }

    function scrollToQuestion(targetId) {
        const el = document.getElementById(targetId);
        if (!el) return;

        document.querySelectorAll('.qnav-btn').forEach((b) => b.classList.remove('is-active'));
        document.querySelectorAll('.qnav-btn[data-target="' + targetId + '"]').forEach((b) => b.classList.add('is-active'));

        const y = el.getBoundingClientRect().top + window.scrollY - 92;
        window.scrollTo({ top: y, behavior: 'smooth' });
    }

    document.addEventListener('change', function (e) {
        const target = e.target;
        if (target && target.matches && target.matches('input[type="radio"]')) {
            updateAnsweredUI();
        }
    });

    document.querySelectorAll('.qnav-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            if (targetId) scrollToQuestion(targetId);
        });
    });

    updateAnsweredUI();

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
