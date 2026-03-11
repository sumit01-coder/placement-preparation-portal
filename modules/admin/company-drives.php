<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/CompanyPortal.php';

Auth::requireAdmin();

$userId = (int)Auth::getUserId();
$db = Database::getInstance();

$portal = new CompanyPortal();
$message = $_GET['msg'] ?? '';
$error = $_GET['err'] ?? '';

$companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
$manageDriveId = isset($_GET['drive_id']) ? (int)$_GET['drive_id'] : 0;

$buildRedirect = static function ($companyId, $driveId, $key, $value) {
    $params = [];
    if ($companyId > 0) {
        $params['company_id'] = $companyId;
    }
    if ($driveId > 0) {
        $params['drive_id'] = $driveId;
    }
    if ($key !== '') {
        $params[$key] = $value;
    }

    $query = http_build_query($params);
    return 'company-drives.php' . ($query ? '?' . $query : '');
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $result = ['success' => false, 'message' => 'Invalid action.'];
        $redirectCompanyId = (int)($_POST['company_id'] ?? $companyId);
        $redirectDriveId = (int)($_POST['drive_id'] ?? $manageDriveId);

        if ($action === 'save_drive') {
            $driveId = (int)($_POST['drive_id'] ?? 0);
            $result = $portal->saveDrive($_POST, $userId, $driveId);
            if ($result['success']) {
                $redirectDriveId = (int)($result['drive_id'] ?? $driveId);
                $redirectCompanyId = (int)($_POST['company_id'] ?? $redirectCompanyId);
            }
        } elseif ($action === 'save_mapping') {
            $driveId = (int)($_POST['drive_id'] ?? 0);
            $result = $portal->saveDriveMappings(
                $driveId,
                $_POST['problem_ids'] ?? [],
                $_POST['test_ids'] ?? []
            );
            $redirectDriveId = $driveId;
        } elseif ($action === 'generate_calls') {
            $driveId = (int)($_POST['drive_id'] ?? 0);
            $result = $portal->generateInterviewCalls($driveId);
            $redirectDriveId = $driveId;
        } elseif ($action === 'update_call_status') {
            $callId = (int)($_POST['call_id'] ?? 0);
            $result = $portal->updateInterviewCallStatus(
                $callId,
                $_POST['status'] ?? '',
                $_POST['remarks'] ?? ''
            );
        } elseif ($action === 'add_question') {
            $targetCompanyId = (int)($_POST['company_id'] ?? 0);
            $result = $portal->addCompanyQuestion($targetCompanyId, $_POST);
            $redirectCompanyId = $targetCompanyId;
        } elseif ($action === 'add_company_problem') {
            $targetCompanyId = (int)($_POST['company_id'] ?? 0);
            $targetDriveId = (int)($_POST['drive_id'] ?? 0);
            $result = $portal->addCompanyCodingProblem($targetCompanyId, $targetDriveId, $_POST, $userId);
            $redirectCompanyId = $targetCompanyId;
            if ($targetDriveId > 0) {
                $redirectDriveId = $targetDriveId;
            }
        } elseif ($action === 'add_company_test') {
            $targetCompanyId = (int)($_POST['company_id'] ?? 0);
            $targetDriveId = (int)($_POST['drive_id'] ?? 0);
            $result = $portal->addCompanyAptitudeTest($targetCompanyId, $targetDriveId, $_POST, $userId);
            $redirectCompanyId = $targetCompanyId;
            if ($targetDriveId > 0) {
                $redirectDriveId = $targetDriveId;
            }
        }

        $key = $result['success'] ? 'msg' : 'err';
        header('Location: ' . $buildRedirect($redirectCompanyId, $redirectDriveId, $key, (string)($result['message'] ?? '')));
        exit();
    }
}

$companies = $portal->getCompanies(false);
if ($companyId <= 0 && !empty($companies)) {
    $companyId = (int)$companies[0]['company_id'];
}

$selectedDrive = null;
if ($manageDriveId > 0) {
    $selectedDrive = $portal->getDriveById($manageDriveId);
    if ($selectedDrive) {
        $companyId = (int)$selectedDrive['company_id'];
    } elseif ($error === '') {
        $error = 'Selected drive was not found.';
    }
}

$drives = $portal->getDrives($companyId > 0 ? $companyId : null, false);
$codingProblems = $portal->getCodingProblemOptions();
$aptitudeTests = $portal->getAptitudeTestOptions();
$aptitudeCategories = $portal->getAptitudeCategories();
$companyQuestions = $companyId > 0 ? $portal->getCompanyQuestions($companyId, null, 25) : [];
$driveCalls = $selectedDrive ? $portal->getDriveCalls((int)$selectedDrive['drive_id']) : [];
$driveEvaluations = $selectedDrive ? $portal->getDriveEvaluations((int)$selectedDrive['drive_id']) : [];

$isEditingDrive = $selectedDrive !== null;
$defaultStartDate = date('Y-m-d');
$defaultEndDate = date('Y-m-d', strtotime('+30 days'));
$formatDateInputValue = static function ($value, $fallback = '') {
    $value = trim((string)$value);
    if ($value === '') {
        return $fallback;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
        return $value;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $fallback;
    }

    return date('Y-m-d', $timestamp);
};

$driveForm = [
    'company_id' => (int)($selectedDrive['company_id'] ?? $companyId),
    'drive_title' => (string)($selectedDrive['drive_title'] ?? 'Campus Drive'),
    'min_coding_solved' => (int)($selectedDrive['min_coding_solved'] ?? 1),
    'min_aptitude_percentage' => (string)($selectedDrive['min_aptitude_percentage'] ?? '50'),
    'start_date' => $formatDateInputValue($selectedDrive['start_date'] ?? '', $defaultStartDate),
    'end_date' => $formatDateInputValue($selectedDrive['end_date'] ?? '', $defaultEndDate),
    'description' => (string)($selectedDrive['description'] ?? ''),
    'is_active' => (int)($selectedDrive['is_active'] ?? 1)
];

$statusBadgeClass = static function ($status) {
    $status = strtolower((string)$status);
    if ($status === 'selected') {
        return 'status-selected';
    }
    if ($status === 'invited') {
        return 'status-invited';
    }
    if ($status === 'waitlisted') {
        return 'status-waitlisted';
    }
    if ($status === 'rejected') {
        return 'status-rejected';
    }
    return 'status-pending';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Drives - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: #0a0a0a; color: #e4e4e7; min-height: 100vh; }
        .top-nav { background: #1a1a1a; border-bottom: 1px solid #2a2a2a; padding: 12px 5%; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.2rem; font-weight: 700; color: #ef4444; }
        .nav-menu { display: flex; gap: 20px; }
        .nav-menu a { color: #a1a1aa; text-decoration: none; font-size: 0.94rem; }
        .nav-menu a.active, .nav-menu a:hover { color: #fff; }
        .container { max-width: 1700px; margin: 0 auto; padding: 28px 20px; }
        .grid { display: grid; grid-template-columns: 320px 1fr; gap: 24px; }
        .card { background: #161616; border: 1px solid #2a2a2a; border-radius: 12px; padding: 18px; margin-bottom: 18px; }
        .card h2 { font-size: 1.1rem; margin-bottom: 14px; }
        .muted { color: #9ca3af; font-size: 0.88rem; }
        label { display: block; margin-bottom: 6px; font-size: 0.86rem; color: #9ca3af; }
        input, textarea, select {
            width: 100%;
            background: #0f0f0f;
            border: 1px solid #2e2e2e;
            color: #e4e4e7;
            border-radius: 8px;
            padding: 10px 12px;
            font-family: inherit;
        }
        textarea { min-height: 90px; resize: vertical; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .btn {
            display: inline-block;
            border: 1px solid #3a3a3a;
            background: #242424;
            color: #f4f4f5;
            border-radius: 8px;
            padding: 9px 14px;
            text-decoration: none;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .btn-primary { background: #ef4444; border-color: #ef4444; color: #fff; }
        .btn-success { background: #16a34a; border-color: #16a34a; color: #fff; }
        .actions { margin-top: 14px; display: flex; gap: 10px; flex-wrap: wrap; }
        .list { display: grid; gap: 10px; }
        .drive-item {
            display: block;
            border: 1px solid #2a2a2a;
            border-radius: 8px;
            padding: 10px;
            color: #d4d4d8;
            text-decoration: none;
            background: #101010;
        }
        .drive-item.active { border-color: #ef4444; background: rgba(239, 68, 68, 0.12); }
        .alert { margin-bottom: 16px; padding: 11px 14px; border-radius: 8px; font-size: 0.9rem; }
        .alert-success { background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.35); color: #22c55e; }
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.35); color: #ef4444; }
        .multi-list {
            max-height: 220px;
            overflow: auto;
            border: 1px solid #2a2a2a;
            border-radius: 8px;
            padding: 8px;
            background: #0f0f0f;
        }
        .check-item { display: flex; align-items: center; gap: 8px; padding: 7px; border-radius: 6px; }
        .check-item:hover { background: #1a1a1a; }
        .check-item input { width: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-top: 1px solid #2a2a2a; padding: 10px; text-align: left; font-size: 0.9rem; vertical-align: top; }
        thead th { color: #a1a1aa; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.35px; background: #101010; }
        .status-pill { display: inline-block; font-size: 0.75rem; border-radius: 99px; padding: 4px 10px; border: 1px solid transparent; }
        .status-pending { color: #fbbf24; border-color: rgba(251,191,36,0.35); background: rgba(251,191,36,0.12); }
        .status-invited { color: #22c55e; border-color: rgba(34,197,94,0.35); background: rgba(34,197,94,0.12); }
        .status-selected { color: #10b981; border-color: rgba(16,185,129,0.35); background: rgba(16,185,129,0.12); }
        .status-waitlisted { color: #60a5fa; border-color: rgba(96,165,250,0.35); background: rgba(96,165,250,0.12); }
        .status-rejected { color: #f87171; border-color: rgba(248,113,113,0.35); background: rgba(248,113,113,0.12); }
        @media (max-width: 1200px) { .grid { grid-template-columns: 1fr; } }
        @media (max-width: 760px) { .row { grid-template-columns: 1fr; } .nav-menu { display: none; } }
    </style>
</head>
<body>
    <nav class="top-nav">
        <div class="logo">Admin Panel</div>
        <div class="nav-menu">
            <a href="dashboard.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="problems.php">Problems</a>
            <a href="tests.php">Tests</a>
            <a href="company-drives.php" class="active">Company Drives</a>
            <a href="analytics.php">Analytics</a>
        </div>
        <div style="font-size:0.88rem;">
            <a href="../dashboard/index.php" style="color:#ef4444;text-decoration:none;">Exit Admin</a>
        </div>
    </nav>

    <div class="container">
        <?php if ($message !== ''): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="grid">
            <aside>
                <div class="card">
                    <h2>Company Filter</h2>
                    <form method="GET">
                        <label for="company_id">Company</label>
                        <select id="company_id" name="company_id" onchange="this.form.submit()">
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo (int)$company['company_id']; ?>" <?php echo (int)$companyId === (int)$company['company_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($company['company_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <div class="card">
                    <h2>Drives</h2>
                    <div class="list">
                        <?php if (empty($drives)): ?>
                            <div class="muted">No drives yet for this company.</div>
                        <?php else: ?>
                            <?php foreach ($drives as $drive): ?>
                                <?php $isActive = ((int)$manageDriveId === (int)$drive['drive_id']); ?>
                                <a class="drive-item <?php echo $isActive ? 'active' : ''; ?>" href="company-drives.php?company_id=<?php echo (int)$companyId; ?>&drive_id=<?php echo (int)$drive['drive_id']; ?>">
                                    <div style="font-weight:600;"><?php echo htmlspecialchars($drive['drive_title']); ?></div>
                                    <div class="muted" style="margin-top:4px;">
                                        Cutoff: <?php echo (int)$drive['min_coding_solved']; ?> coding, <?php echo number_format((float)$drive['min_aptitude_percentage'], 1); ?>% aptitude
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>

            <main>
                <div class="card">
                    <h2><?php echo $selectedDrive ? 'Edit Drive' : 'Create New Drive'; ?></h2>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(Auth::getCsrfToken()); ?>">
                        <input type="hidden" name="action" value="save_drive">
                        <input type="hidden" name="drive_id" value="<?php echo (int)($selectedDrive['drive_id'] ?? 0); ?>">
                        <div class="row">
                            <div>
                                <label>Company</label>
                                <select name="company_id" required>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?php echo (int)$company['company_id']; ?>" <?php echo (int)$driveForm['company_id'] === (int)$company['company_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($company['company_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>Drive Title</label>
                                <input type="text" name="drive_title" required value="<?php echo htmlspecialchars($driveForm['drive_title']); ?>">
                            </div>
                            <div>
                                <label>Minimum Solved Coding Problems</label>
                                <input type="number" min="0" name="min_coding_solved" value="<?php echo (int)$driveForm['min_coding_solved']; ?>">
                            </div>
                            <div>
                                <label>Minimum Aptitude Percentage</label>
                                <input type="number" min="0" max="100" step="0.1" name="min_aptitude_percentage" value="<?php echo htmlspecialchars((string)$driveForm['min_aptitude_percentage']); ?>">
                            </div>
                            <div>
                                <label>Start Date</label>
                                <input id="startDate" type="date" name="start_date" value="<?php echo htmlspecialchars($driveForm['start_date']); ?>" min="<?php echo $isEditingDrive ? '' : htmlspecialchars($defaultStartDate); ?>">
                            </div>
                            <div>
                                <label>End Date</label>
                                <input id="endDate" type="date" name="end_date" value="<?php echo htmlspecialchars($driveForm['end_date']); ?>" min="<?php echo htmlspecialchars($driveForm['start_date']); ?>">
                                <div class="muted" style="margin-top:6px;">End date will stay on or after the selected start date.</div>
                            </div>
                        </div>
                        <div style="margin-top:12px;">
                            <label>Description</label>
                            <textarea name="description"><?php echo htmlspecialchars($driveForm['description']); ?></textarea>
                        </div>
                        <div style="margin-top:10px;">
                            <label style="display:flex;align-items:center;gap:7px;color:#e4e4e7;">
                                <input type="checkbox" name="is_active" style="width:auto;" <?php echo ((int)$driveForm['is_active'] === 1 ? 'checked' : ''); ?>>
                                Active drive
                            </label>
                        </div>
                        <div class="actions">
                            <button type="submit" class="btn btn-primary"><?php echo $selectedDrive ? 'Update Drive' : 'Create Drive'; ?></button>
                            <?php if ($selectedDrive): ?>
                                <a href="company-drives.php?company_id=<?php echo (int)$companyId; ?>" class="btn">Create Another</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <?php if ($selectedDrive): ?>
                    <div class="card">
                        <h2>Map Coding + Aptitude for This Drive</h2>
                        <p class="muted" style="margin-bottom:10px;">Students are evaluated using only the mapped items when mappings are present.</p>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(Auth::getCsrfToken()); ?>">
                            <input type="hidden" name="action" value="save_mapping">
                            <input type="hidden" name="company_id" value="<?php echo (int)$companyId; ?>">
                            <input type="hidden" name="drive_id" value="<?php echo (int)$selectedDrive['drive_id']; ?>">
                            <div class="row">
                                <div>
                                    <label>Coding Problems</label>
                                    <div class="multi-list">
                                        <?php foreach ($codingProblems as $problem): ?>
                                            <?php $checked = in_array((int)$problem['problem_id'], $selectedDrive['coding_problem_ids'] ?? [], true); ?>
                                            <label class="check-item">
                                                <input type="checkbox" name="problem_ids[]" value="<?php echo (int)$problem['problem_id']; ?>" <?php echo $checked ? 'checked' : ''; ?>>
                                                <span><?php echo htmlspecialchars($problem['title']); ?> (<?php echo htmlspecialchars((string)$problem['difficulty']); ?>)</span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div>
                                    <label>Aptitude Tests</label>
                                    <div class="multi-list">
                                        <?php foreach ($aptitudeTests as $test): ?>
                                            <?php $checked = in_array((int)$test['test_id'], $selectedDrive['aptitude_test_ids'] ?? [], true); ?>
                                            <label class="check-item">
                                                <input type="checkbox" name="test_ids[]" value="<?php echo (int)$test['test_id']; ?>" <?php echo $checked ? 'checked' : ''; ?>>
                                                <span><?php echo htmlspecialchars($test['test_name']); ?> (<?php echo htmlspecialchars((string)$test['category']); ?>)</span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="actions">
                                <button class="btn btn-primary" type="submit">Save Mappings</button>
                                <button class="btn btn-success" type="submit" name="action" value="generate_calls">Generate Interview Calls</button>
                                <a class="btn" href="#company-problem-form">Create Coding Problem</a>
                                <a class="btn" href="#company-test-form">Create Aptitude Test</a>
                            </div>
                        </form>
                    </div>

                    <div class="card">
                        <h2>Live Student Results</h2>
                        <p class="muted" style="margin-bottom:10px;">This view updates directly from accepted coding submissions and completed aptitude attempts mapped to this drive.</p>
                        <?php if (empty($driveEvaluations)): ?>
                            <div class="muted">No student evaluations are available yet.</div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Coding Progress</th>
                                        <th>Aptitude Progress</th>
                                        <th>Total Score</th>
                                        <th>Eligibility</th>
                                        <th>Current Call Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($driveEvaluations as $evaluationRow): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight:600;"><?php echo htmlspecialchars((string)$evaluationRow['full_name']); ?></div>
                                                <div class="muted"><?php echo htmlspecialchars((string)$evaluationRow['email']); ?></div>
                                            </td>
                                            <td>
                                                <?php echo (int)$evaluationRow['coding_solved']; ?> / <?php echo (int)$evaluationRow['required_coding']; ?>
                                            </td>
                                            <td>
                                                <?php echo number_format((float)$evaluationRow['aptitude_percentage'], 2); ?>% / <?php echo number_format((float)$evaluationRow['required_aptitude'], 2); ?>%
                                            </td>
                                            <td><?php echo number_format((float)$evaluationRow['total_score'], 2); ?></td>
                                            <td>
                                                <span class="status-pill <?php echo !empty($evaluationRow['eligible']) ? 'status-invited' : 'status-rejected'; ?>">
                                                    <?php echo !empty($evaluationRow['eligible']) ? 'Eligible' : 'Not Eligible'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-pill <?php echo $statusBadgeClass($evaluationRow['status']); ?>">
                                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', (string)$evaluationRow['status']))); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <div class="card">
                        <h2>Interview Calls</h2>
                        <?php if (empty($driveCalls)): ?>
                            <div class="muted">No call records yet. Click "Generate Interview Calls" after mapping and cutoffs are configured.</div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Coding Solved</th>
                                        <th>Aptitude %</th>
                                        <th>Total Score</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($driveCalls as $call): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight:600;"><?php echo htmlspecialchars($call['full_name']); ?></div>
                                                <div class="muted"><?php echo htmlspecialchars($call['email']); ?></div>
                                            </td>
                                            <td><?php echo (int)$call['coding_solved']; ?></td>
                                            <td><?php echo number_format((float)$call['aptitude_percentage'], 2); ?></td>
                                            <td><?php echo number_format((float)$call['total_score'], 2); ?></td>
                                            <td><span class="status-pill <?php echo $statusBadgeClass($call['status']); ?>"><?php echo htmlspecialchars(ucfirst((string)$call['status'])); ?></span></td>
                                            <td>
                                                <form method="POST" style="display:flex;gap:8px;flex-wrap:wrap;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(Auth::getCsrfToken()); ?>">
                                                    <input type="hidden" name="action" value="update_call_status">
                                                    <input type="hidden" name="company_id" value="<?php echo (int)$companyId; ?>">
                                                    <input type="hidden" name="drive_id" value="<?php echo (int)$selectedDrive['drive_id']; ?>">
                                                    <input type="hidden" name="call_id" value="<?php echo (int)$call['call_id']; ?>">
                                                    <select name="status" style="min-width:128px;">
                                                        <?php foreach (['pending', 'invited', 'waitlisted', 'selected', 'rejected'] as $status): ?>
                                                            <option value="<?php echo $status; ?>" <?php echo strtolower((string)$call['status']) === $status ? 'selected' : ''; ?>>
                                                                <?php echo ucfirst($status); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <input type="text" name="remarks" placeholder="Remarks" value="<?php echo htmlspecialchars((string)($call['remarks'] ?? '')); ?>">
                                                    <button class="btn" type="submit">Save</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="card" id="company-problem-form">
                    <h2>Add Company Coding Problem</h2>
                    <p class="muted" style="margin-bottom:10px;">
                        Create a new coding problem from here and automatically map it to the selected drive.
                    </p>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(Auth::getCsrfToken()); ?>">
                        <input type="hidden" name="action" value="add_company_problem">
                        <input type="hidden" name="company_id" value="<?php echo (int)$companyId; ?>">
                        <input type="hidden" name="drive_id" value="<?php echo (int)($selectedDrive['drive_id'] ?? 0); ?>">
                        <div class="row">
                            <div>
                                <label>Problem Title</label>
                                <input type="text" name="title" required placeholder="e.g. Company Pair Sum">
                            </div>
                            <div>
                                <label>Difficulty</label>
                                <select name="difficulty">
                                    <option value="easy">Easy</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="hard">Hard</option>
                                </select>
                            </div>
                            <div>
                                <label>Time Limit (sec)</label>
                                <input type="number" min="1" name="time_limit" value="2">
                            </div>
                            <div>
                                <label>Memory Limit (MB)</label>
                                <input type="number" min="64" name="memory_limit" value="256">
                            </div>
                        </div>
                        <div style="margin-top:12px;">
                            <label>Description</label>
                            <textarea name="description" required placeholder="Problem statement"></textarea>
                        </div>
                        <div class="row" style="margin-top:12px;">
                            <div>
                                <label>Input Format</label>
                                <textarea name="input_format" placeholder="Describe expected input format"></textarea>
                            </div>
                            <div>
                                <label>Output Format</label>
                                <textarea name="output_format" placeholder="Describe expected output format"></textarea>
                            </div>
                            <div>
                                <label>Constraints</label>
                                <textarea name="constraints" placeholder="1 <= N <= 1e5"></textarea>
                            </div>
                            <div>
                                <label>Tags (comma separated)</label>
                                <input type="text" name="tags" placeholder="arrays,hashmap,company">
                            </div>
                        </div>
                        <div class="row" style="margin-top:12px;">
                            <div>
                                <label>Sample Input</label>
                                <textarea name="sample_input" placeholder="2 7 11 15"></textarea>
                            </div>
                            <div>
                                <label>Sample Output</label>
                                <textarea name="sample_output" placeholder="0 1"></textarea>
                            </div>
                        </div>
                        <div class="actions">
                            <button type="submit" class="btn btn-primary">Create Company Problem</button>
                        </div>
                    </form>
                </div>

                <div class="card" id="company-test-form">
                    <h2>Add Company Aptitude Test</h2>
                    <p class="muted" style="margin-bottom:10px;">
                        Create a new aptitude test with one starter question and map it to the selected drive.
                    </p>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(Auth::getCsrfToken()); ?>">
                        <input type="hidden" name="action" value="add_company_test">
                        <input type="hidden" name="company_id" value="<?php echo (int)$companyId; ?>">
                        <input type="hidden" name="drive_id" value="<?php echo (int)($selectedDrive['drive_id'] ?? 0); ?>">
                        <div class="row">
                            <div>
                                <label>Test Name</label>
                                <input type="text" name="test_name" required placeholder="e.g. Accenture Quant Screening">
                            </div>
                            <div>
                                <label>Category</label>
                                <select name="category_id">
                                    <?php foreach ($aptitudeCategories as $category): ?>
                                        <option value="<?php echo (int)$category['category_id']; ?>">
                                            <?php echo htmlspecialchars((string)$category['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>Difficulty</label>
                                <select name="difficulty">
                                    <option value="easy">Easy</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="hard">Hard</option>
                                </select>
                            </div>
                            <div>
                                <label>Duration (minutes)</label>
                                <input type="number" min="5" name="duration_minutes" value="20">
                            </div>
                        </div>
                        <div style="margin-top:12px;">
                            <label>Description</label>
                            <textarea name="description" placeholder="Brief test description"></textarea>
                        </div>
                        <div style="margin-top:12px;">
                            <label>Question</label>
                            <textarea name="question_text" required placeholder="Enter the aptitude question"></textarea>
                        </div>
                        <div class="row" style="margin-top:12px;">
                            <div>
                                <label>Option A</label>
                                <input type="text" name="option_a" required>
                            </div>
                            <div>
                                <label>Option B</label>
                                <input type="text" name="option_b" required>
                            </div>
                            <div>
                                <label>Option C</label>
                                <input type="text" name="option_c" required>
                            </div>
                            <div>
                                <label>Option D</label>
                                <input type="text" name="option_d" required>
                            </div>
                            <div>
                                <label>Correct Answer</label>
                                <select name="correct_answer">
                                    <option value="a">A</option>
                                    <option value="b">B</option>
                                    <option value="c">C</option>
                                    <option value="d">D</option>
                                </select>
                            </div>
                            <div>
                                <label>Marks</label>
                                <input type="number" min="1" name="marks" value="1">
                            </div>
                        </div>
                        <div style="margin-top:12px;">
                            <label>Explanation</label>
                            <textarea name="explanation" placeholder="Why this answer is correct"></textarea>
                        </div>
                        <div class="actions">
                            <button type="submit" class="btn btn-primary">Create Company Aptitude Test</button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <h2>Add Company Question</h2>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(Auth::getCsrfToken()); ?>">
                        <input type="hidden" name="action" value="add_question">
                        <input type="hidden" name="company_id" value="<?php echo (int)$companyId; ?>">
                        <?php if ($selectedDrive): ?>
                            <input type="hidden" name="drive_id" value="<?php echo (int)$selectedDrive['drive_id']; ?>">
                        <?php endif; ?>
                        <div class="row">
                            <div>
                                <label>Question Type</label>
                                <select name="question_type">
                                    <option value="coding">Coding</option>
                                    <option value="aptitude">Aptitude</option>
                                    <option value="technical">Technical</option>
                                    <option value="hr">HR</option>
                                </select>
                            </div>
                            <div>
                                <label>Difficulty</label>
                                <select name="difficulty">
                                    <option value="easy">Easy</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="hard">Hard</option>
                                </select>
                            </div>
                            <div>
                                <label>Year</label>
                                <input type="number" name="year" min="2000" max="2100" value="<?php echo date('Y'); ?>">
                            </div>
                            <div>
                                <label>Round ID (optional)</label>
                                <input type="number" name="round_id" min="1" placeholder="Example: 1">
                            </div>
                        </div>
                        <div style="margin-top:12px;">
                            <label>Question</label>
                            <textarea name="question_text" required placeholder="Enter question text..."></textarea>
                        </div>
                        <div style="margin-top:12px;">
                            <label>Answer / Explanation</label>
                            <textarea name="answer" placeholder="Optional answer or explanation"></textarea>
                        </div>
                        <div class="actions">
                            <button type="submit" class="btn btn-primary">Add Question</button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <h2>Recent Company Questions</h2>
                    <?php if (empty($companyQuestions)): ?>
                        <div class="muted">No questions added for this company yet.</div>
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
                                <?php foreach ($companyQuestions as $question): ?>
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
                </div>
            </main>
        </div>
    </div>
    <script>
        (function () {
            const startDateInput = document.getElementById('startDate');
            const endDateInput = document.getElementById('endDate');

            if (!startDateInput || !endDateInput) {
                return;
            }

            const syncDateBounds = () => {
                if (startDateInput.value) {
                    endDateInput.min = startDateInput.value;
                } else {
                    endDateInput.removeAttribute('min');
                }

                if (startDateInput.value && endDateInput.value && endDateInput.value < startDateInput.value) {
                    endDateInput.value = startDateInput.value;
                }
            };

            startDateInput.addEventListener('change', syncDateBounds);
            syncDateBounds();
        }());
    </script>
</body>
</html>
