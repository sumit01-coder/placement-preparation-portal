<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/FocusMode.php';
require_once __DIR__ . '/../../classes/Submission.php';

Auth::requireLogin();

$userId = Auth::getUserId();
$db = Database::getInstance();
$focusMode = new FocusMode();
$submission = new Submission();
$submissionTable = $db->firstExistingTable(['coding_submissions', 'submissions']);

// Fetch analytics data
$focusStats = $focusMode->getUserAnalytics($userId, 30);
$codeStats = $submission->getSubmissionStats($userId);
$dailyCode = $submission->getDailyActivity($userId);

// Schema-safe helpers for aptitude activity
$tableExists = static function ($db, $table) {
    $row = $db->fetchOne(
        "SELECT COUNT(*) AS cnt
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
         AND table_name = :table_name",
        ['table_name' => $table]
    );
    return ((int)($row['cnt'] ?? 0)) > 0;
};

$columnExists = static function ($db, $table, $column) use ($tableExists) {
    if (!$tableExists($db, $table)) {
        return false;
    }
    $row = $db->fetchOne(
        "SELECT COUNT(*) AS cnt
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
         AND table_name = :table_name
         AND column_name = :column_name",
        ['table_name' => $table, 'column_name' => $column]
    );
    return ((int)($row['cnt'] ?? 0)) > 0;
};

$dailyAptitude = [];
$totalAptitudeAttempts = 0;
$last30AptitudeAttempts = 0;
$last30AptitudeBestPercentage = 0.0;

if ($tableExists($db, 'aptitude_attempts')) {
    $hasUserId = $columnExists($db, 'aptitude_attempts', 'user_id');
    $hasStartTime = $columnExists($db, 'aptitude_attempts', 'start_time');
    $hasEndTime = $columnExists($db, 'aptitude_attempts', 'end_time');

    if ($hasUserId && ($hasStartTime || $hasEndTime)) {
        if ($hasEndTime && $hasStartTime) {
            $dateExpr = 'COALESCE(end_time, start_time)';
        } elseif ($hasEndTime) {
            $dateExpr = 'end_time';
        } else {
            $dateExpr = 'start_time';
        }

        $where = "user_id = :user_id";
        if ($columnExists($db, 'aptitude_attempts', 'status')) {
            $where .= " AND status = 'completed'";
        }

        $rows = $db->fetchAll(
            "SELECT DATE({$dateExpr}) AS date, COUNT(*) AS count
             FROM aptitude_attempts
             WHERE {$where}
             AND {$dateExpr} >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
             GROUP BY DATE({$dateExpr})",
            ['user_id' => $userId]
        );
        foreach ($rows as $row) {
            $date = (string)($row['date'] ?? '');
            if ($date !== '') {
                $dailyAptitude[$date] = (int)($row['count'] ?? 0);
            }
        }

        $totalAptitudeAttempts += (int)($db->fetchOne(
            "SELECT COUNT(*) AS cnt
             FROM aptitude_attempts
             WHERE {$where}",
            ['user_id' => $userId]
        )['cnt'] ?? 0);

        $last30AptitudeAttempts += (int)($db->fetchOne(
            "SELECT COUNT(*) AS cnt
             FROM aptitude_attempts
             WHERE {$where}
             AND {$dateExpr} >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            ['user_id' => $userId]
        )['cnt'] ?? 0);

        if ($columnExists($db, 'aptitude_attempts', 'percentage')) {
            $last30AptitudeBestPercentage = max(
                $last30AptitudeBestPercentage,
                (float)($db->fetchOne(
                    "SELECT MAX(percentage) AS best
                     FROM aptitude_attempts
                     WHERE {$where}
                     AND {$dateExpr} >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                    ['user_id' => $userId]
                )['best'] ?? 0)
            );
        }
    }
}

if (
    $tableExists($db, 'test_attempts')
    && $columnExists($db, 'test_attempts', 'user_id')
    && $columnExists($db, 'test_attempts', 'attempted_at')
) {
    $rows = $db->fetchAll(
        "SELECT DATE(attempted_at) AS date, COUNT(*) AS count
         FROM test_attempts
         WHERE user_id = :user_id
         AND attempted_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
         GROUP BY DATE(attempted_at)",
        ['user_id' => $userId]
    );
    foreach ($rows as $row) {
        $date = (string)($row['date'] ?? '');
        if ($date === '') {
            continue;
        }
        $dailyAptitude[$date] = (int)($dailyAptitude[$date] ?? 0) + (int)($row['count'] ?? 0);
    }

    $totalAptitudeAttempts += (int)($db->fetchOne(
        "SELECT COUNT(*) AS cnt
         FROM test_attempts
         WHERE user_id = :user_id",
        ['user_id' => $userId]
    )['cnt'] ?? 0);

    $last30AptitudeAttempts += (int)($db->fetchOne(
        "SELECT COUNT(*) AS cnt
         FROM test_attempts
         WHERE user_id = :user_id
         AND attempted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        ['user_id' => $userId]
    )['cnt'] ?? 0);

    if ($columnExists($db, 'test_attempts', 'percentage')) {
        $last30AptitudeBestPercentage = max(
            $last30AptitudeBestPercentage,
            (float)($db->fetchOne(
                "SELECT MAX(percentage) AS best
                 FROM test_attempts
                 WHERE user_id = :user_id
                 AND attempted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                ['user_id' => $userId]
            )['best'] ?? 0)
        );
    }
}

// Coding acceptance stats (all-time)
$totalSubs = (int)($codeStats['total_submissions'] ?? 0);
$accepted = (int)($codeStats['accepted'] ?? 0);
$acceptanceRate = $totalSubs > 0 ? round(($accepted / $totalSubs) * 100, 1) : 0;

// Build date-indexed maps for real analytics
$focusByDate = [];
foreach (($focusStats['daily'] ?? []) as $row) {
    $date = (string)($row['date'] ?? '');
    if ($date === '') {
        continue;
    }
    $focusByDate[$date] = [
        'hours' => (float)($row['hours'] ?? 0),
        'focus_score' => (float)($row['focus_score'] ?? 0),
        'sessions' => (int)($row['sessions'] ?? 0)
    ];
}

$codingByDate = [];
foreach (($dailyCode ?? []) as $row) {
    $date = (string)($row['date'] ?? '');
    if ($date === '') {
        continue;
    }
    $codingByDate[$date] = (int)($row['count'] ?? 0);
}

$activityByDate = $codingByDate;
foreach ($dailyAptitude as $date => $count) {
    $activityByDate[$date] = (int)($activityByDate[$date] ?? 0) + (int)$count;
}

// 30-day calendar series for charts (oldest -> newest)
$chartDates = [];
$chartLabels = [];
$chartCodingSubmissions = [];
$chartAptitudeSubmissions = [];
$chartActivityCounts = [];
$chartHours = [];
$chartFocus = [];

for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $chartDates[] = $date;
    $chartLabels[] = date('M j', strtotime($date));
    $chartCodingSubmissions[] = (int)($codingByDate[$date] ?? 0);
    $chartAptitudeSubmissions[] = (int)($dailyAptitude[$date] ?? 0);
    $chartActivityCounts[] = (int)($activityByDate[$date] ?? 0);
    $chartHours[] = (float)($focusByDate[$date]['hours'] ?? 0);
    $chartFocus[] = (float)($focusByDate[$date]['focus_score'] ?? 0);
}

$average = static function ($values) {
    if (empty($values)) {
        return 0;
    }
    return array_sum($values) / count($values);
};

// Last 7 days vs previous 7 days comparisons
$last7Submissions = array_slice($chartActivityCounts, -7);
$prev7Submissions = array_slice($chartActivityCounts, -14, 7);
$last7Hours = array_slice($chartHours, -7);
$prev7Hours = array_slice($chartHours, -14, 7);
$last7FocusValues = array_slice($chartFocus, -7);
$prev7FocusValues = array_slice($chartFocus, -14, 7);

$last7AvgSubmissions = $average($last7Submissions);
$prev7AvgSubmissions = $average($prev7Submissions);
$last7AvgHours = $average($last7Hours);
$prev7AvgHours = $average($prev7Hours);
$last7AvgFocus = $average($last7FocusValues);
$prev7AvgFocus = $average($prev7FocusValues);

$submissionChange = $prev7AvgSubmissions > 0 ? (($last7AvgSubmissions - $prev7AvgSubmissions) / $prev7AvgSubmissions) * 100 : 0;
$hoursChange = $prev7AvgHours > 0 ? (($last7AvgHours - $prev7AvgHours) / $prev7AvgHours) * 100 : 0;
$focusChange = $prev7AvgFocus > 0 ? (($last7AvgFocus - $prev7AvgFocus) / $prev7AvgFocus) * 100 : 0;

// Current streak based on real daily activity
$currentStreak = 0;
for ($i = 0; $i < 365; $i++) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    if ((int)($activityByDate[$date] ?? 0) > 0) {
        $currentStreak++;
    } else {
        break;
    }
}

// 4 weekly insight buckets (current week + previous 3)
$weeklyHours = [];
$weeklyFocus = [];
$weeklySubmissions = [];
$weeklyCoding = [];
$weeklyAptitude = [];
for ($week = 0; $week < 4; $week++) {
    $weekHours = 0;
    $weekFocusTotal = 0;
    $weekFocusDays = 0;
    $weekTotalActivity = 0;
    $weekCoding = 0;
    $weekAptitude = 0;

    for ($day = 0; $day < 7; $day++) {
        $offset = ($week * 7) + $day;
        $date = date('Y-m-d', strtotime("-{$offset} days"));
        $weekCoding += (int)($codingByDate[$date] ?? 0);
        $weekAptitude += (int)($dailyAptitude[$date] ?? 0);
        $weekTotalActivity += (int)($activityByDate[$date] ?? 0);
        $weekHours += (float)($focusByDate[$date]['hours'] ?? 0);

        $focusValue = (float)($focusByDate[$date]['focus_score'] ?? 0);
        if ($focusValue > 0) {
            $weekFocusTotal += $focusValue;
            $weekFocusDays++;
        }
    }

    $weeklySubmissions[$week] = $weekTotalActivity;
    $weeklyCoding[$week] = $weekCoding;
    $weeklyAptitude[$week] = $weekAptitude;
    $weeklyHours[$week] = $weekHours;
    $weeklyFocus[$week] = $weekFocusDays > 0 ? ($weekFocusTotal / $weekFocusDays) : 0;
}

$last30SubmissionTotal = array_sum($chartActivityCounts);
$last30CodingTotal = array_sum($chartCodingSubmissions);
$last30AptitudeTotal = array_sum($chartAptitudeSubmissions);
$last30Accepted = 0;
if ($submissionTable) {
    $last30Accepted = (int)($db->fetchOne(
        "SELECT COUNT(*) AS accepted
         FROM {$submissionTable}
         WHERE user_id = :user_id
         AND status = 'accepted'
         AND submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        ['user_id' => $userId]
    )['accepted'] ?? 0);
}
$last30AcceptanceRate = $last30CodingTotal > 0 ? round(($last30Accepted / $last30CodingTotal) * 100, 1) : 0;

$dailyActivityCombined = [];
foreach ($activityByDate as $date => $count) {
    $dailyActivityCombined[] = ['date' => $date, 'count' => (int)$count];
}

// Page Config
$pageTitle = 'Performance Analytics - PlacementCode';
$additionalCSS = '
.container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }

.header-section { margin-bottom: 30px; }
.header-section h1 { font-size: 2rem; margin-bottom: 8px; color: #e4e4e7; }
.header-section p { color: #a1a1aa; font-size: 1rem; }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    border-radius: 12px;
    padding: 25px;
    transition: transform 0.2s, border-color 0.2s;
}
.stat-card:hover { transform: translateY(-4px); border-color: #ffa116; }
.stat-icon { font-size: 2rem; margin-bottom: 12px; }
.stat-value { font-size: 2.5rem; font-weight: 700; color: #ffa116; margin-bottom: 8px; }
.stat-label { color: #a1a1aa; font-size: 0.9rem; }
.stat-sub { color: #71717a; font-size: 0.85rem; margin-top: 8px; }

.chart-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.chart-card {
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    border-radius: 12px;
    padding: 25px;
}
.chart-title { font-size: 1.2rem; font-weight: 600; margin-bottom: 20px; color: #e4e4e7; }

.heatmap-card {
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
}

.heatmap-container {
    display: flex;
    gap: 3px;
    overflow-x: auto;
    padding: 10px 0;
    scrollbar-width: thin;
    scrollbar-color: #2a2a2a #1a1a1a;
}
.heatmap-container::-webkit-scrollbar { height: 6px; }
.heatmap-container::-webkit-scrollbar-track { background: #1a1a1a; }
.heatmap-container::-webkit-scrollbar-thumb { background: #2a2a2a; border-radius: 3px; }

.heatmap-week { display: flex; flex-direction: column; gap: 3px; }
.heatmap-day {
    width: 11px;
    height: 11px;
    border-radius: 2px;
    background: #1f1f1f;
}
.heatmap-day[data-level="1"] { background: rgba(255, 161, 22, 0.3); }
.heatmap-day[data-level="2"] { background: rgba(255, 161, 22, 0.5); }
.heatmap-day[data-level="3"] { background: rgba(255, 161, 22, 0.7); }
.heatmap-day[data-level="4"] { background: #ffa116; }

.heatmap-legend {
    margin-top: 15px; 
    text-align: right; 
    color: #71717a; 
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
}
.legend-box { display: inline-block; width: 11px; height: 11px; border-radius: 2px; }

.details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.insights-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
}

.detail-card {
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    border-radius: 12px;
    padding: 25px;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid #2a2a2a;
}
.detail-item:last-child { border-bottom: none; }
.detail-label { color: #a1a1aa; }
.detail-value { font-weight: 600; color: #e4e4e7; }
.value-success { color: #22c55e; }
.value-error { color: #ef4444; }
.value-warning { color: #fbbf24; }

.insight-card {
    background: #0f0f0f;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #2a2a2a;
}
.insight-label { color: #71717a; font-size: 0.85rem; margin-bottom: 8px; }
.insight-main { font-size: 1.5rem; font-weight: 700; color: #ffa116; margin-bottom: 4px; }
.insight-sub { color: #a1a1aa; font-size: 0.85rem; }

.text-success { color: #22c55e; }
.text-danger { color: #ef4444; }

@media (max-width: 1024px) {
    .chart-grid, .details-grid, .insights-grid { grid-template-columns: 1fr; }
}
';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>
<!-- ChartJS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container">
    
    <div class="header-section">
        <h1>📊 Performance Analytics</h1>
        <p>Track coding, aptitude, and focus patterns</p>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">🔥</div>
            <div class="stat-value"><?php echo $currentStreak; ?></div>
            <div class="stat-label">Activity Streak</div>
            <div class="stat-sub">
                <?php echo $currentStreak > 0 ? 'Consecutive days with coding or aptitude activity.' : 'Start with a coding submission or aptitude test today.'; ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?php echo (int)$last30SubmissionTotal; ?></div>
            <div class="stat-label">Total Activity (30 Days)</div>
            <div class="stat-sub">
                <?php echo (int)$last30CodingTotal; ?> coding + <?php echo (int)$last30AptitudeTotal; ?> aptitude | <?php echo $last30AcceptanceRate; ?>% coding accepted
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🎯</div>
            <div class="stat-value"><?php echo round($last7AvgFocus); ?>%</div>
            <div class="stat-label">Weekly Avg Focus</div>
            <div class="stat-sub <?php echo $focusChange >= 0 ? 'text-success' : 'text-danger'; ?>">
                <?php echo $focusChange >= 0 ? '↑' : '↓'; ?> 
                <?php echo abs(round($focusChange, 1)); ?>% vs previous 7 days
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏱️</div>
            <div class="stat-value"><?php echo round($last7AvgSubmissions, 1); ?></div>
            <div class="stat-label">Daily Activity (7 days)</div>
            <div class="stat-sub <?php echo $submissionChange >= 0 ? 'text-success' : 'text-danger'; ?>">
                <?php echo $submissionChange >= 0 ? '↑' : '↓'; ?> 
                <?php echo abs(round($submissionChange, 1)); ?>% vs previous 7 days
            </div>
        </div>
    </div>

    <!-- Activity Heatmap -->
    <div class="heatmap-card">
        <div class="chart-title">📅 Activity Heatmap (Last Year)</div>
        <div class="heatmap-container" id="activityHeatmap"></div>
        <div class="heatmap-legend">
            Less 
            <span class="legend-box" style="background:#1f1f1f;"></span>
            <span class="legend-box" style="background:rgba(255, 161, 22, 0.3);"></span>
            <span class="legend-box" style="background:rgba(255, 161, 22, 0.5);"></span>
            <span class="legend-box" style="background:rgba(255, 161, 22, 0.7);"></span>
            <span class="legend-box" style="background:#ffa116;"></span>
            More
        </div>
    </div>

    <!-- Insights Section -->
    <div class="chart-card" style="margin-bottom: 20px;">
        <div class="chart-title">💡 Weekly Activity Insights</div>
        <div class="insights-grid">
            <?php for ($i = 3; $i >= 0; $i--): ?>
                <div class="insight-card">
                    <div class="insight-label">
                        <?php
                        if ($i === 0) {
                            echo 'Current Week';
                        } else {
                            echo $i . ' Week' . ($i > 1 ? 's' : '') . ' Ago';
                        }
                        ?>
                    </div>
                    <div class="insight-main"><?php echo (int)$weeklySubmissions[$i]; ?></div>
                    <div class="insight-sub">
                        <?php echo (int)$weeklyCoding[$i]; ?> coding | <?php echo (int)$weeklyAptitude[$i]; ?> aptitude | <?php echo round($weeklyHours[$i], 1); ?>h | <?php echo round($weeklyFocus[$i]); ?>% focus
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Charts -->
    <div class="chart-grid">
        <div class="chart-card">
            <div class="chart-title">📈 Activity & Focus Trends (Last 30 Days)</div>
            <canvas id="focusChart" style="max-height: 300px;"></canvas>
        </div>
        <div class="chart-card">
            <div class="chart-title">🧩 Activity Mix (All Time)</div>
            <canvas id="codingPieChart" style="max-height: 300px;"></canvas>
        </div>
    </div>

    <!-- Details -->
    <div class="details-grid">
        <div class="detail-card">
            <div class="chart-title">⚠️ Distraction Analysis</div>
            <?php if (empty($focusStats['violations_by_type'])): ?>
                <p style="text-align: center; color: #71717a; padding: 40px 20px;">
                    No distractions recorded! Great job! 🎉
                </p>
            <?php else: ?>
                <?php foreach ($focusStats['violations_by_type'] as $v): ?>
                    <div class="detail-item">
                        <span class="detail-label" style="text-transform: capitalize;">
                            <?php echo str_replace('_', ' ', $v['violation_type']); ?>
                        </span>
                        <span class="detail-value value-error"><?php echo $v['count']; ?> times</span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="detail-card">
            <div class="chart-title">📝 Coding + Aptitude Stats</div>
            <div class="detail-item">
                <span class="detail-label">Accepted</span>
                <span class="detail-value value-success"><?php echo $codeStats['accepted'] ?? 0; ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Wrong Answer</span>
                <span class="detail-value value-error"><?php echo $codeStats['wrong_answer'] ?? 0; ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Time Limit</span>
                <span class="detail-value value-warning"><?php echo $codeStats['tle'] ?? 0; ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Runtime Error</span>
                <span class="detail-value value-warning"><?php echo $codeStats['runtime_error'] ?? 0; ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Aptitude Attempts (All Time)</span>
                <span class="detail-value"><?php echo (int)$totalAptitudeAttempts; ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Aptitude Attempts (30 Days)</span>
                <span class="detail-value"><?php echo (int)$last30AptitudeAttempts; ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Best Aptitude Score (30 Days)</span>
                <span class="detail-value" style="color: #ffa116;"><?php echo round($last30AptitudeBestPercentage, 1); ?>%</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Coding Acceptance Rate</span>
                <span class="detail-value" style="color: #ffa116;"><?php echo $acceptanceRate; ?>%</span>
            </div>
        </div>
    </div>

</div>

<script>
    // Real coding + aptitude + focus trend chart (last 30 days)
    const labels = <?php echo json_encode($chartLabels); ?>;
    const codingCounts = <?php echo json_encode($chartCodingSubmissions); ?>;
    const aptitudeCounts = <?php echo json_encode($chartAptitudeSubmissions); ?>;
    const focusScores = <?php echo json_encode($chartFocus); ?>;

    new Chart(document.getElementById('focusChart'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Coding',
                    data: codingCounts,
                    backgroundColor: 'rgba(255, 161, 22, 0.75)',
                    borderRadius: 4,
                    yAxisID: 'y'
                },
                {
                    label: 'Aptitude',
                    data: aptitudeCounts,
                    backgroundColor: 'rgba(56, 189, 248, 0.75)',
                    borderRadius: 4,
                    yAxisID: 'y'
                },
                {
                    label: 'Focus Score (%)',
                    data: focusScores,
                    type: 'line',
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34, 197, 94, 0.12)',
                    borderWidth: 2,
                    tension: 0.35,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { labels: { color: '#e4e4e7' } }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { color: '#a1a1aa', precision: 0 },
                    grid: { color: '#2a2a2a' }
                },
                y1: {
                    beginAtZero: true,
                    max: 100,
                    position: 'right',
                    ticks: { color: '#a1a1aa' },
                    grid: { drawOnChartArea: false, color: '#2a2a2a' }
                },
                x: { ticks: { color: '#a1a1aa' }, grid: { color: '#2a2a2a' } }
            }
        }
    });

    // Activity mix chart (all-time)
    const totalCoding = <?php echo (int)$totalSubs; ?>;
    const totalAptitude = <?php echo (int)$totalAptitudeAttempts; ?>;

    new Chart(document.getElementById('codingPieChart'), {
        type: 'doughnut',
        data: {
            labels: ['Coding Submissions', 'Aptitude Attempts'],
            datasets: [{
                data: [totalCoding, totalAptitude],
                backgroundColor: ['#ffa116', '#38bdf8'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { position: 'bottom', labels: { color: '#e4e4e7' } }
            }
        }
    });

    // Real activity heatmap based on coding + aptitude counts (last 52 weeks)
    const container = document.getElementById('activityHeatmap');
    const activityData = <?php echo json_encode($dailyActivityCombined); ?>;
    const activityMap = {};
    let maxActivity = 0;

    activityData.forEach(item => {
        const count = parseInt(item.count, 10) || 0;
        activityMap[item.date] = count;
        if (count > maxActivity) {
            maxActivity = count;
        }
    });

    const formatDateKey = (dateObj) => {
        const year = dateObj.getFullYear();
        const month = String(dateObj.getMonth() + 1).padStart(2, '0');
        const day = String(dateObj.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const getLevel = (count, maxValue) => {
        if (count <= 0) {
            return 0;
        }
        if (maxValue <= 1) {
            return 4;
        }
        const ratio = count / maxValue;
        if (ratio <= 0.25) return 1;
        if (ratio <= 0.50) return 2;
        if (ratio <= 0.75) return 3;
        return 4;
    };

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const startDate = new Date(today);
    startDate.setDate(today.getDate() - ((52 * 7) - 1));

    for (let week = 0; week < 52; week++) {
        const weekCol = document.createElement('div');
        weekCol.className = 'heatmap-week';

        for (let day = 0; day < 7; day++) {
            const currentDate = new Date(startDate);
            currentDate.setDate(startDate.getDate() + (week * 7) + day);
            const dateKey = formatDateKey(currentDate);
            const count = activityMap[dateKey] || 0;

            const dayDiv = document.createElement('div');
            dayDiv.className = 'heatmap-day';
            dayDiv.dataset.level = String(getLevel(count, maxActivity));
            dayDiv.title = `${dateKey}: ${count} activit${count === 1 ? 'y' : 'ies'}`;
            weekCol.appendChild(dayDiv);
        }

        container.appendChild(weekCol);
    }
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

