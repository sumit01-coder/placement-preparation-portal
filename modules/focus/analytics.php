<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/FocusMode.php';
require_once __DIR__ . '/../../classes/Submission.php';
require_once __DIR__ . '/../../classes/User.php';

Auth::requireLogin();

$userId = Auth::getUserId();
$focusMode = new FocusMode();
$submission = new Submission();
$userClass = new User();

// Fetch analytics data
$focusStats = $focusMode->getUserAnalytics($userId, 30);
$codeStats = $submission->getSubmissionStats($userId);
$dailyCode = $submission->getDailyActivity($userId);
$dashboardStats = $userClass->getDashboardStats($userId);

// Calculate acceptance rate
$totalSubs = $codeStats['total_submissions'] ?? 0;
$accepted = $codeStats['accepted'] ?? 0;
$acceptanceRate = $totalSubs > 0 ? round(($accepted / $totalSubs) * 100, 1) : 0;

// Calculate trends and insights
$dailyData = $focusStats['daily'] ?? [];

// Split into last 7 days vs previous 7 days for comparison
$last7Days = array_slice($dailyData, 0, 7);
$prev7Days = array_slice($dailyData, 7, 7);

// Calculate averages
$last7AvgHours = count($last7Days) > 0 ? array_sum(array_column($last7Days, 'hours')) / count($last7Days) : 0;
$prev7AvgHours = count($prev7Days) > 0 ? array_sum(array_column($prev7Days, 'hours')) / count($prev7Days) : 0;

$last7AvgFocus = count($last7Days) > 0 ? array_sum(array_column($last7Days, 'focus_score')) / count($last7Days) : 0;
$prev7AvgFocus = count($prev7Days) > 0 ? array_sum(array_column($prev7Days, 'focus_score')) / count($prev7Days) : 0;

// Calculate percentage change
$hoursChange = $prev7AvgHours > 0 ? (($last7AvgHours - $prev7AvgHours) / $prev7AvgHours) * 100 : 0;
$focusChange = $prev7AvgFocus > 0 ? (($last7AvgFocus - $prev7AvgFocus) / $prev7AvgFocus) * 100 : 0;

// Calculate current streak (placeholder - should check consecutive days)
$currentStreak = 0;
$today = date('Y-m-d');
foreach ($dailyData as $day) {
    if ($day['hours'] > 0) {
        $currentStreak++;
    } else {
        break;
    }
}

// Calculate weekly progress
$weeklyHours = [];
$weeklyFocus = [];
for ($i = 0; $i < 4; $i++) {
    $weekData = array_slice($dailyData, $i * 7, 7);
    $weeklyHours[$i] = count($weekData) > 0 ? array_sum(array_column($weekData, 'hours')) : 0;
    $weeklyFocus[$i] = count($weekData) > 0 ? array_sum(array_column($weekData, 'focus_score')) / count($weekData) : 0;
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
        <p>Track your coding progress and focus patterns</p>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">🔥</div>
            <div class="stat-value"><?php echo $currentStreak; ?></div>
            <div class="stat-label">Day Streak</div>
            <div class="stat-sub">
                <?php echo $currentStreak > 0 ? 'Keep it going!' : 'Start your streak today!'; ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?php echo $dashboardStats['problems_solved'] ?? 0; ?></div>
            <div class="stat-label">Problems Solved</div>
            <div class="stat-sub">
                <?php echo $acceptanceRate; ?>% acceptance rate
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🎯</div>
            <div class="stat-value"><?php echo round($last7AvgFocus); ?>%</div>
            <div class="stat-label">Weekly Avg Focus</div>
            <div class="stat-sub <?php echo $focusChange >= 0 ? 'text-success' : 'text-danger'; ?>">
                <?php echo $focusChange >= 0 ? '↑' : '↓'; ?> 
                <?php echo abs(round($focusChange, 1)); ?>% vs last week
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏱️</div>
            <div class="stat-value"><?php echo round($last7AvgHours, 1); ?>h</div>
            <div class="stat-label">Daily Avg (7 days)</div>
            <div class="stat-sub <?php echo $hoursChange >= 0 ? 'text-success' : 'text-danger'; ?>">
                <?php echo $hoursChange >= 0 ? '↑' : '↓'; ?> 
                <?php echo abs(round($hoursChange, 1)); ?>% vs last week
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
        <div class="chart-title">💡 Weekly Insights</div>
        <div class="insights-grid">
            <?php for ($i = 0; $i < 4; $i++): ?>
                <div class="insight-card">
                    <div class="insight-label">Week <?php echo 4 - $i; ?></div>
                    <div class="insight-main"><?php echo round($weeklyHours[$i], 1); ?>h</div>
                    <div class="insight-sub"><?php echo round($weeklyFocus[$i]); ?>% focus</div>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Charts -->
    <div class="chart-grid">
        <div class="chart-card">
            <div class="chart-title">📈 Study & Focus Trends (Last 30 Days)</div>
            <canvas id="focusChart" style="max-height: 300px;"></canvas>
        </div>
        <div class="chart-card">
            <div class="chart-title">🧩 Submission Breakdown</div>
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
            <div class="chart-title">📝 Submission Stats</div>
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
                <span class="detail-label">Acceptance Rate</span>
                <span class="detail-value" style="color: #ffa116;"><?php echo $acceptanceRate; ?>%</span>
            </div>
        </div>
    </div>

</div>

<script>
    // Focus & Study Chart
    const dailyData = <?php echo json_encode($focusStats['daily'] ?? []); ?>;
    const labels = dailyData.map(d => d.date).reverse();
    const hours = dailyData.map(d => parseFloat(d.hours || 0)).reverse();
    const focusScores = dailyData.map(d => parseFloat(d.focus_score || 0)).reverse();
    
    new Chart(document.getElementById('focusChart'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Study Hours',
                    data: hours,
                    backgroundColor: 'rgba(255, 161, 22, 0.7)',
                    yAxisID: 'y',
                    borderRadius: 4
                },
                {
                    label: 'Focus Score (%)',
                    data: focusScores,
                    type: 'line',
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
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
                y: { beginAtZero: true, ticks: { color: '#a1a1aa' }, grid: { color: '#2a2a2a' } },
                y1: { beginAtZero: true, position: 'right', max: 100, ticks: { color: '#a1a1aa' }, grid: { drawOnChartArea: false, color: '#2a2a2a' } },
                x: { ticks: { color: '#a1a1aa' }, grid: { color: '#2a2a2a' } }
            }
        }
    });

    // Coding Pie Chart
    const totalSubs = <?php echo $totalSubs; ?>;
    const accepted = <?php echo $accepted; ?>;
    const others = totalSubs - accepted;
    
    new Chart(document.getElementById('codingPieChart'), {
        type: 'doughnut',
        data: {
            labels: ['Accepted', 'Not Accepted'],
            datasets: [{
                data: [accepted, others],
                backgroundColor: ['#22c55e', '#ef4444'],
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

    // Activity Heatmap
    const container = document.getElementById('activityHeatmap');
    const activityData = <?php echo json_encode($dailyCode ?? []); ?>;
    const activityMap = {};
    activityData.forEach(item => { activityMap[item.date] = parseInt(item.count); });
    
    // Generate 52 weeks
    for (let i = 0; i < 52; i++) {
        const weekCol = document.createElement('div');
        weekCol.className = 'heatmap-week';
        for (let j = 0; j < 7; j++) {
            const dayDiv = document.createElement('div');
            dayDiv.className = 'heatmap-day';
            // Use random for demo (replace with actual data mapping)
            dayDiv.dataset.level = Math.floor(Math.random() * 5);
            weekCol.appendChild(dayDiv);
        }
        container.appendChild(weekCol);
    }
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
