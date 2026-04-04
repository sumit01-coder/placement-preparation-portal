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

$correctCount = 0;
$incorrectCount = 0;
foreach (($result['answers'] ?? []) as $a) {
    if (!empty($a['is_correct'])) {
        $correctCount++;
    } else {
        $incorrectCount++;
    }
}
$answeredCount = count($result['answers'] ?? []);
$accuracy = $answeredCount > 0 ? round(($correctCount / $answeredCount) * 100, 2) : 0;

function aptitudeOptionText(array $ans, $letter): string {
    $letter = strtoupper(trim((string)$letter));
    $map = [
        'A' => 'option_a',
        'B' => 'option_b',
        'C' => 'option_c',
        'D' => 'option_d',
    ];
    $key = $map[$letter] ?? null;
    if (!$key) {
        return '';
    }
    return trim((string)($ans[$key] ?? ''));
}

?>

<style>
    :root {
        --bg: #0a0a0a;
        --surface: #121212;
        --surface-2: #1a1a1a;
        --border: #2a2a2a;
        --text: #e4e4e7;
        --muted: #a1a1aa;
        --muted-2: #71717a;
        --accent: #ffa116;
        --danger: #ef4444;
        --success: #22c55e;
        --warning: #eab308;
        --shadow: 0 12px 30px rgba(0, 0, 0, 0.45);
    }

    body { background: var(--bg); color: var(--text); }

    .container {
        width: 100%;
        max-width: 1100px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .result-hero {
        margin: 26px 0 18px;
        background: radial-gradient(circle at 20% 10%, rgba(255, 161, 22, 0.12), rgba(10, 10, 10, 0) 50%),
                    linear-gradient(135deg, rgba(255, 161, 22, 0.10), rgba(255, 107, 107, 0.06));
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 18px;
        padding: 22px;
        box-shadow: var(--shadow);
        display: grid;
        grid-template-columns: 1fr 220px;
        gap: 18px;
        align-items: center;
    }

    .hero-kicker { color: var(--muted); font-size: 0.85rem; font-weight: 700; letter-spacing: 0.02em; }
    .hero-title { margin: 6px 0 10px; color: #fff; font-size: 1.7rem; line-height: 1.15; }
    .hero-meta { display: flex; gap: 10px; flex-wrap: wrap; }
    .pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px;
        border-radius: 999px;
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.10);
        color: var(--text);
        font-size: 0.85rem;
        font-weight: 700;
    }

    .score-circle {
        width: 190px;
        height: 190px;
        border-radius: 50%;
        border: 10px solid rgba(255,255,255,0.08);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.6rem;
        font-weight: 900;
        margin: 0 auto;
        background: rgba(0,0,0,0.12);
    }
    .score-circle.success { border-color: rgba(34, 197, 94, 0.55); color: var(--success); }
    .score-circle.warning { border-color: rgba(234, 179, 8, 0.55); color: var(--warning); }
    .score-circle.danger { border-color: rgba(239, 68, 68, 0.55); color: var(--danger); }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin: 14px 0 22px;
    }

    .stat-box {
        background: var(--surface-2);
        border: 1px solid var(--border);
        padding: 14px 14px;
        border-radius: 14px;
        box-shadow: 0 2px 0 rgba(0,0,0,0.25);
    }
    .stat-value { font-size: 1.25rem; font-weight: 900; color: #fff; margin-bottom: 4px; }
    .stat-label { color: var(--muted); font-size: 0.85rem; font-weight: 700; }

    .actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 12px;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        text-decoration: none;
        padding: 10px 14px;
        border-radius: 14px;
        font-weight: 800;
        border: 1px solid rgba(255,255,255,0.14);
        background: rgba(255,255,255,0.06);
        color: #fff;
    }
    .btn:hover { background: rgba(255,255,255,0.10); }
    .btn.primary { background: linear-gradient(135deg, var(--accent), #ff6b6b); color: #0b0b0b; border: none; }

    .review-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin: 12px 0 14px;
    }

    .segmented {
        display: inline-flex;
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.10);
        border-radius: 14px;
        padding: 4px;
        gap: 4px;
    }
    .segmented button {
        border: none;
        background: transparent;
        color: var(--muted);
        padding: 8px 10px;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 800;
    }
    .segmented button.active { background: rgba(255, 161, 22, 0.18); color: #fff; }

    .review-card {
        background: var(--surface-2);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 18px;
        margin-bottom: 14px;
        box-shadow: 0 2px 0 rgba(0,0,0,0.25);
    }
    .review-card.correct { border-left: 4px solid rgba(34, 197, 94, 0.9); }
    .review-card.incorrect { border-left: 4px solid rgba(239, 68, 68, 0.9); }

    .badge-correct, .badge-incorrect {
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.8rem;
        font-weight: 900;
        border: 1px solid rgba(255,255,255,0.12);
    }
    .badge-correct { background: rgba(34, 197, 94, 0.12); color: var(--success); border-color: rgba(34, 197, 94, 0.28); }
    .badge-incorrect { background: rgba(239, 68, 68, 0.12); color: var(--danger); border-color: rgba(239, 68, 68, 0.28); }

    .review-title { margin: 10px 0 12px; color: #fff; font-size: 1.05rem; line-height: 1.4; }
    .muted { color: var(--muted); }
    .answer-strong { font-weight: 900; }

    details.explain {
        margin-top: 12px;
        border: 1px solid rgba(255,255,255,0.10);
        border-radius: 14px;
        background: rgba(0,0,0,0.18);
        padding: 12px 14px;
    }
    details.explain summary { cursor: pointer; font-weight: 900; color: #fff; }
    details.explain p { margin: 10px 0 0; color: var(--text); font-size: 0.92rem; line-height: 1.55; }

    @media (max-width: 880px) {
        .result-hero { grid-template-columns: 1fr; }
        .score-circle { width: 160px; height: 160px; font-size: 2.3rem; }
        .summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
</style>

<div class="container" style="padding: 0 20px 60px;">
    <div class="result-hero">
        <div>
            <div class="hero-kicker">APTITUDE RESULT</div>
            <h1 class="hero-title"><?php echo htmlspecialchars((string)$result['test_name']); ?></h1>
            <div class="hero-meta">
                <span class="pill">⏱ <?php echo htmlspecialchars($timeString); ?></span>
                <span class="pill">✅ <?php echo $correctCount; ?> correct</span>
                <span class="pill">📌 <?php echo $answeredCount; ?> answered</span>
                <span class="pill">🎯 <?php echo $accuracy; ?>% accuracy</span>
            </div>
            <div class="actions">
                <a href="tests.php" class="btn primary">Take another test</a>
                <a href="../dashboard/index.php" class="btn">Back to dashboard</a>
            </div>
        </div>

        <div class="score-circle <?php echo ($result['percentage'] >= 75) ? 'success' : (($result['percentage'] >= 50) ? 'warning' : 'danger'); ?>" aria-label="Score percentage">
            <?php echo htmlspecialchars((string)$result['percentage']); ?>%
        </div>
    </div>

    <div class="summary-grid" aria-label="Summary stats">
        <div class="stat-box">
            <div class="stat-value"><?php echo htmlspecialchars((string)$result['score']); ?> / <?php echo htmlspecialchars((string)$result['total_marks']); ?></div>
            <div class="stat-label">Score</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?php echo $correctCount; ?></div>
            <div class="stat-label">Correct</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?php echo $incorrectCount; ?></div>
            <div class="stat-label">Incorrect</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?php echo htmlspecialchars((string)($result['category_name'] ?? 'General')); ?></div>
            <div class="stat-label">Category</div>
        </div>
    </div>

    <div class="review-toolbar">
        <div style="font-weight: 900; color: #fff; font-size: 1.15rem;">Detailed review</div>
        <div class="segmented" role="tablist" aria-label="Filter questions">
            <button type="button" class="active" data-filter="all" role="tab">All</button>
            <button type="button" data-filter="correct" role="tab">Correct</button>
            <button type="button" data-filter="incorrect" role="tab">Incorrect</button>
        </div>
    </div>

    <?php foreach (($result['answers'] ?? []) as $index => $ans):
        $statusClass = !empty($ans['is_correct']) ? 'correct' : 'incorrect';
        $statusBadge = !empty($ans['is_correct']) ? '<span class="badge-correct">Correct</span>' : '<span class="badge-incorrect">Incorrect</span>';
        $selected = strtoupper((string)($ans['selected_answer'] ?? ''));
        $correct = strtoupper((string)($ans['correct_answer'] ?? ''));
        $selectedText = aptitudeOptionText($ans, $selected);
        $correctText = aptitudeOptionText($ans, $correct);
    ?>
        <div class="review-card <?php echo $statusClass; ?>" data-status="<?php echo $statusClass; ?>">
            <div style="display: flex; justify-content: space-between; gap: 10px; align-items: center; flex-wrap: wrap;">
                <span class="muted" style="font-weight: 800;">Question <?php echo $index + 1; ?></span>
                <?php echo $statusBadge; ?>
            </div>

            <div class="review-title"><?php echo htmlspecialchars((string)($ans['question_text'] ?? '')); ?></div>

            <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px;">
                <div>
                    <div class="muted" style="font-size: 0.85rem; font-weight: 800; margin-bottom: 6px;">Your answer</div>
                    <div class="answer-strong" style="color: <?php echo !empty($ans['is_correct']) ? 'var(--success)' : 'var(--danger)'; ?>;">
                        Option <?php echo htmlspecialchars($selected); ?>
                        <?php if ($selectedText !== ''): ?>
                            <span class="muted" style="font-weight: 700;">(<?php echo htmlspecialchars($selectedText); ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <div class="muted" style="font-size: 0.85rem; font-weight: 800; margin-bottom: 6px;">Correct answer</div>
                    <div class="answer-strong" style="color: var(--success);">
                        Option <?php echo htmlspecialchars($correct); ?>
                        <?php if ($correctText !== ''): ?>
                            <span class="muted" style="font-weight: 700;">(<?php echo htmlspecialchars($correctText); ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($ans['explanation'])): ?>
                <details class="explain">
                    <summary>Explanation</summary>
                    <p><?php echo htmlspecialchars((string)$ans['explanation']); ?></p>
                </details>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<script>
    (function () {
        const buttons = Array.from(document.querySelectorAll('.segmented button[data-filter]'));
        const cards = Array.from(document.querySelectorAll('.review-card[data-status]'));

        function setActive(btn) {
            buttons.forEach((b) => b.classList.toggle('active', b === btn));
        }

        function applyFilter(filter) {
            cards.forEach((card) => {
                const status = card.getAttribute('data-status');
                card.style.display = (filter === 'all' || status === filter) ? '' : 'none';
            });
        }

        buttons.forEach((btn) => {
            btn.addEventListener('click', () => {
                setActive(btn);
                applyFilter(btn.getAttribute('data-filter'));
            });
        });
    })();
</script>

<?php require_once '../../includes/footer.php'; ?>
