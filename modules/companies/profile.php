<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/CompanyPortal.php';

Auth::requireLogin();

$userId = (int)Auth::getUserId();
$companyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($companyId <= 0) {
    header('Location: list.php');
    exit;
}

$portal = new CompanyPortal();
$company = $portal->getCompanyById($companyId);
if (!$company) {
    header('Location: list.php');
    exit;
}

$drives = $portal->getUserDriveStatusByCompany($companyId, $userId);
$questions = $portal->getCompanyQuestions($companyId, null, 25);

$callLabel = static function ($status) {
    $status = strtolower((string)$status);
    if ($status === 'selected') {
        return 'Selected';
    }
    if ($status === 'invited') {
        return 'Interview Invited';
    }
    if ($status === 'waitlisted') {
        return 'Waitlisted';
    }
    if ($status === 'rejected') {
        return 'Not Shortlisted';
    }
    if ($status === 'pending') {
        return 'Pending Review';
    }
    if ($status === 'eligible') {
        return 'Eligible (Awaiting Admin Review)';
    }
    return 'Not Eligible Yet';
};

$statusClass = static function ($status) {
    $status = strtolower((string)$status);
    if (in_array($status, ['selected', 'invited', 'eligible'], true)) {
        return 'ok';
    }
    if ($status === 'waitlisted' || $status === 'pending') {
        return 'warn';
    }
    return 'bad';
};

$pageTitle = htmlspecialchars((string)$company['company_name']) . ' - Company Drives';
$additionalCSS = '
.container { max-width: 1450px; margin: 28px auto; padding: 0 20px; }
.back-link { display: inline-flex; color: #fda4af; text-decoration: none; margin-bottom: 16px; font-size: 0.9rem; }
.hero {
    background: linear-gradient(130deg, rgba(239,68,68,0.22), rgba(20,20,20,0.55));
    border: 1px solid #2a2a2a;
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 20px;
}
.hero h1 { font-size: 2rem; margin-bottom: 8px; }
.hero p { color: #a1a1aa; line-height: 1.55; }
.hero-meta { display: flex; gap: 18px; flex-wrap: wrap; margin-top: 14px; color: #d1d5db; font-size: 0.9rem; }
.section-card {
    background: #151515;
    border: 1px solid #2a2a2a;
    border-radius: 12px;
    padding: 18px;
    margin-bottom: 18px;
}
.section-card h2 { font-size: 1.16rem; margin-bottom: 14px; }
.drive { border: 1px solid #262626; border-radius: 10px; padding: 14px; margin-bottom: 12px; background: #101010; }
.drive:last-child { margin-bottom: 0; }
.drive-header { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 10px; }
.drive-title { font-size: 1.04rem; font-weight: 600; }
.badge { font-size: 0.78rem; border-radius: 99px; padding: 4px 10px; border: 1px solid transparent; }
.badge.ok { color: #22c55e; border-color: rgba(34,197,94,0.35); background: rgba(34,197,94,0.12); }
.badge.warn { color: #fbbf24; border-color: rgba(251,191,36,0.35); background: rgba(251,191,36,0.12); }
.badge.bad { color: #f87171; border-color: rgba(248,113,113,0.35); background: rgba(248,113,113,0.12); }
.meta { color: #9ca3af; font-size: 0.88rem; line-height: 1.45; }
.stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-top: 10px; }
.stat { background: #0d0d0d; border: 1px solid #262626; border-radius: 8px; padding: 10px; }
.stat-label { color: #9ca3af; font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.3px; }
.stat-value { font-size: 1rem; margin-top: 4px; font-weight: 600; color: #f4f4f5; }
.resource-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 10px; }
.list-box { border: 1px solid #262626; border-radius: 8px; padding: 10px; background: #0f0f0f; }
.list-box h4 { font-size: 0.9rem; margin-bottom: 8px; color: #d1d5db; }
.list-box ul { list-style: none; display: grid; gap: 6px; }
.list-box li { font-size: 0.88rem; color: #d4d4d8; display: flex; justify-content: space-between; gap: 10px; align-items: center; flex-wrap: wrap; }
.list-box a { color: #93c5fd; text-decoration: none; }
.mini-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 999px;
    font-size: 0.76rem;
    border: 1px solid transparent;
}
.mini-badge.ok { color: #22c55e; border-color: rgba(34,197,94,0.35); background: rgba(34,197,94,0.12); }
.mini-badge.warn { color: #fbbf24; border-color: rgba(251,191,36,0.35); background: rgba(251,191,36,0.12); }
.mini-badge.bad { color: #f87171; border-color: rgba(248,113,113,0.35); background: rgba(248,113,113,0.12); }
.result-note {
    margin-top: 10px;
    color: #cbd5e1;
    font-size: 0.85rem;
}
.empty { color: #9ca3af; font-size: 0.9rem; }
table { width: 100%; border-collapse: collapse; }
th, td { border-top: 1px solid #2a2a2a; padding: 10px; text-align: left; font-size: 0.9rem; vertical-align: top; }
thead th { color: #9ca3af; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.35px; background: #101010; }
@media (max-width: 900px) {
    .stats { grid-template-columns: 1fr; }
    .resource-grid { grid-template-columns: 1fr; }
}
';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="container">
    <a class="back-link" href="list.php">Back to Companies</a>

    <section class="hero">
        <h1><?php echo htmlspecialchars((string)$company['company_name']); ?></h1>
        <p><?php echo htmlspecialchars((string)($company['description'] ?? 'Company-specific preparation and drive eligibility.')); ?></p>
        <div class="hero-meta">
            <div>Package: <?php echo htmlspecialchars((string)($company['package_range'] ?? 'Not specified')); ?></div>
            <div>Website:
                <?php if (!empty($company['website_url'])): ?>
                    <a href="<?php echo htmlspecialchars((string)$company['website_url']); ?>" target="_blank" rel="noopener" style="color:#93c5fd;">Visit</a>
                <?php else: ?>
                    Not provided
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="section-card">
        <h2>Active Drives and Interview Eligibility</h2>
        <?php if (empty($drives)): ?>
            <div class="empty">No active drives are published for this company.</div>
        <?php else: ?>
            <?php foreach ($drives as $drive): ?>
                <?php
                $evaluation = $drive['evaluation'] ?? [];
                $status = (string)($drive['call_status'] ?? 'not_eligible');
                $codingProblems = $drive['coding_problems'] ?? [];
                $aptitudeTests = $drive['aptitude_tests'] ?? [];
                ?>
                <article class="drive">
                    <div class="drive-header">
                        <div>
                            <div class="drive-title"><?php echo htmlspecialchars((string)$drive['drive_title']); ?></div>
                            <div class="meta">
                                <?php echo htmlspecialchars((string)($drive['description'] ?? '')); ?>
                            </div>
                        </div>
                        <span class="badge <?php echo $statusClass($status); ?>"><?php echo htmlspecialchars($callLabel($status)); ?></span>
                    </div>
                    <div class="meta">
                        Drive window: <?php echo htmlspecialchars((string)($drive['start_date'] ?? 'Open')); ?> to <?php echo htmlspecialchars((string)($drive['end_date'] ?? 'Open')); ?>
                    </div>
                    <div class="stats">
                        <div class="stat">
                            <div class="stat-label">Coding Solved</div>
                            <div class="stat-value">
                                <?php echo (int)($evaluation['coding_solved'] ?? 0); ?> / <?php echo (int)($evaluation['required_coding'] ?? 0); ?>
                            </div>
                        </div>
                        <div class="stat">
                            <div class="stat-label">Aptitude %</div>
                            <div class="stat-value">
                                <?php echo number_format((float)($evaluation['aptitude_percentage'] ?? 0), 2); ?> / <?php echo number_format((float)($evaluation['required_aptitude'] ?? 0), 2); ?>
                            </div>
                        </div>
                        <div class="stat">
                            <div class="stat-label">Composite Score</div>
                            <div class="stat-value"><?php echo number_format((float)($evaluation['total_score'] ?? 0), 2); ?></div>
                        </div>
                    </div>
                    <?php if (!empty($drive['call_record'])): ?>
                        <div class="result-note">
                            Latest company review: <?php echo htmlspecialchars($callLabel((string)$drive['call_record']['status'])); ?>
                            <?php if (!empty($drive['call_record']['remarks'])): ?>
                                | <?php echo htmlspecialchars((string)$drive['call_record']['remarks']); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="resource-grid">
                        <div class="list-box">
                            <h4>Mapped Coding Problems</h4>
                            <?php if (empty($codingProblems)): ?>
                                <div class="empty">No specific mapping. General coding solve count is used.</div>
                            <?php else: ?>
                                <ul>
                                    <?php foreach ($codingProblems as $problem): ?>
                                        <li>
                                            <a href="<?php echo BASE_URL; ?>/modules/coding/editor.php?id=<?php echo (int)$problem['problem_id']; ?>">
                                                <?php echo htmlspecialchars((string)$problem['title']); ?>
                                            </a>
                                            <span class="mini-badge <?php echo !empty($problem['is_solved']) ? 'ok' : 'warn'; ?>">
                                                <?php echo !empty($problem['is_solved']) ? 'Accepted' : 'Pending'; ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        <div class="list-box">
                            <h4>Mapped Aptitude Tests</h4>
                            <?php if (empty($aptitudeTests)): ?>
                                <div class="empty">No specific mapping. Best aptitude score across tests is used.</div>
                            <?php else: ?>
                                <ul>
                                    <?php foreach ($aptitudeTests as $test): ?>
                                        <li>
                                            <a href="<?php echo BASE_URL; ?>/modules/aptitude/take-test.php?id=<?php echo (int)$test['test_id']; ?>">
                                                <?php echo htmlspecialchars((string)$test['test_name']); ?>
                                            </a>
                                            <?php if (!empty($test['attempt_id'])): ?>
                                                <span class="mini-badge <?php echo !empty($test['passed_cutoff']) ? 'ok' : 'warn'; ?>">
                                                    <?php echo number_format((float)$test['best_percentage'], 2); ?>%
                                                </span>
                                            <?php else: ?>
                                                <span class="mini-badge bad">Not Attempted</span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="result-note">
                        Complete the mapped coding problems and aptitude tests, then refresh this page to see your latest company result.
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="section-card">
        <h2>Company Questions</h2>
        <?php if (empty($questions)): ?>
            <div class="empty">No company-specific questions are available yet.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Difficulty</th>
                        <th>Year</th>
                        <th>Question</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions as $question): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(ucfirst((string)$question['question_type'])); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst((string)$question['difficulty'])); ?></td>
                            <td><?php echo (int)$question['year']; ?></td>
                            <td><?php echo htmlspecialchars((string)$question['question_text']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
