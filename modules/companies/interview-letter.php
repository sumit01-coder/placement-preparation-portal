<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/CompanyPortal.php';
require_once __DIR__ . '/../../classes/User.php';

Auth::requireLogin();

$userId = (int)Auth::getUserId();
$driveId = isset($_GET['drive_id']) ? (int)$_GET['drive_id'] : 0;
$download = isset($_GET['download']) && (string)$_GET['download'] === '1';

if ($driveId <= 0) {
    header('Location: list.php');
    exit;
}

$portal = new CompanyPortal();
$drive = $portal->getDriveById($driveId);
if (!$drive) {
    header('Location: list.php');
    exit;
}

$db = Database::getInstance();
$call = $db->fetchOne(
    "SELECT *
     FROM company_interview_calls
     WHERE drive_id = :drive_id AND user_id = :user_id
     LIMIT 1",
    ['drive_id' => $driveId, 'user_id' => $userId]
);

$status = strtolower((string)($call['status'] ?? ''));
if (!in_array($status, ['invited', 'selected'], true)) {
    // No letter unless officially invited/selected.
    header('Location: profile.php?id=' . (int)$drive['company_id']);
    exit;
}

$user = new User();
$profile = $user->getProfile($userId) ?: [];

$studentName = trim((string)($profile['full_name'] ?? 'Student'));
$studentEmail = trim((string)($profile['email'] ?? ''));
$studentPhone = trim((string)($profile['phone'] ?? ''));
$college = trim((string)($profile['college_name'] ?? $profile['college'] ?? ''));
$branch = trim((string)($profile['branch'] ?? ''));
$gradYear = trim((string)($profile['graduation_year'] ?? ''));

$companyName = trim((string)($drive['company_name'] ?? 'Company'));
$driveTitle = trim((string)($drive['drive_title'] ?? 'Campus Drive'));
$driveStart = trim((string)($drive['start_date'] ?? ''));
$driveEnd = trim((string)($drive['end_date'] ?? ''));
$referenceNo = 'PP-' . (int)($call['call_id'] ?? 0) . '-' . date('Ymd');
$issuedOn = date('F j, Y');

$headline = $status === 'selected' ? 'Selection Letter' : 'Interview Invitation Letter';
$subjectLine = $status === 'selected'
    ? "Congratulations! You have been selected for {$companyName}"
    : "You are invited for an interview with {$companyName}";

$filenameSafeCompany = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $companyName);
$filenameSafeCompany = trim((string)$filenameSafeCompany, '-');
if ($filenameSafeCompany === '') {
    $filenameSafeCompany = 'company';
}
$fileName = "interview-letter-{$filenameSafeCompany}-drive-{$driveId}.html";

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($headline); ?> - <?php echo htmlspecialchars($companyName); ?></title>
    <style>
        :root {
            --bg: #0a0a0a;
            --paper: #ffffff;
            --ink: #111827;
            --muted: #6b7280;
            --accent: #f59e0b;
            --line: rgba(17, 24, 39, 0.12);
        }

        body {
            margin: 0;
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            background: var(--bg);
            color: #e5e7eb;
        }

        .wrap {
            max-width: 980px;
            margin: 24px auto 60px;
            padding: 0 18px;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .toolbar a {
            color: #93c5fd;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.92rem;
        }

        .btn {
            appearance: none;
            border: 1px solid rgba(255,255,255,0.14);
            background: rgba(255,255,255,0.06);
            color: #fff;
            padding: 10px 12px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 800;
        }
        .btn.primary {
            border: none;
            background: linear-gradient(135deg, #ffa116, #ff6b6b);
            color: #0b0b0b;
        }

        .paper {
            background: var(--paper);
            color: var(--ink);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 18px 40px rgba(0,0,0,0.45);
        }

        .letter-head {
            padding: 22px 26px;
            border-bottom: 1px solid var(--line);
            background: linear-gradient(135deg, rgba(245,158,11,0.10), rgba(255,255,255,0));
        }

        .brand {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
        }

        .brand h1 {
            margin: 0;
            font-size: 1.35rem;
            letter-spacing: -0.02em;
        }

        .brand .meta {
            text-align: right;
            font-size: 0.9rem;
            color: var(--muted);
            line-height: 1.35;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            padding: 8px 10px;
            border-radius: 999px;
            border: 1px solid rgba(245,158,11,0.35);
            background: rgba(245,158,11,0.10);
            color: #92400e;
            font-weight: 900;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .letter-body { padding: 24px 26px 28px; }

        .subject {
            margin: 4px 0 14px;
            font-size: 1.15rem;
            font-weight: 900;
            letter-spacing: -0.01em;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin: 16px 0 18px;
        }

        .card {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px 12px;
            background: #fafafa;
        }

        .label { color: var(--muted); font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; }
        .value { margin-top: 6px; font-weight: 800; color: var(--ink); line-height: 1.4; }

        p { margin: 0 0 12px; line-height: 1.7; color: #111827; }

        .fine {
            margin-top: 16px;
            color: var(--muted);
            font-size: 0.92rem;
        }

        .sign {
            margin-top: 20px;
            padding-top: 18px;
            border-top: 1px solid var(--line);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            align-items: end;
        }

        .sign .sig {
            color: #111827;
            font-weight: 900;
        }

        @media (max-width: 800px) {
            .grid { grid-template-columns: 1fr; }
            .brand .meta { text-align: left; }
            .sign { grid-template-columns: 1fr; }
        }

        @media print {
            body { background: #fff; color: #000; }
            .toolbar { display: none !important; }
            .wrap { margin: 0; max-width: none; padding: 0; }
            .paper { border-radius: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="toolbar">
        <a href="profile.php?id=<?php echo (int)$drive['company_id']; ?>">← Back to Company</a>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a class="btn" href="?drive_id=<?php echo $driveId; ?>&download=1">Download</a>
            <button class="btn primary" type="button" onclick="window.print()">Print</button>
        </div>
    </div>

    <div class="paper">
        <div class="letter-head">
            <div class="brand">
                <div>
                    <h1><?php echo htmlspecialchars($companyName); ?></h1>
                    <div class="badge"><?php echo htmlspecialchars($headline); ?></div>
                </div>
                <div class="meta">
                    <div><strong>Reference:</strong> <?php echo htmlspecialchars($referenceNo); ?></div>
                    <div><strong>Issued on:</strong> <?php echo htmlspecialchars($issuedOn); ?></div>
                </div>
            </div>
        </div>

        <div class="letter-body">
            <div class="subject"><?php echo htmlspecialchars($subjectLine); ?></div>

            <div class="grid" aria-label="Candidate and drive details">
                <div class="card">
                    <div class="label">Candidate</div>
                    <div class="value"><?php echo htmlspecialchars($studentName); ?></div>
                    <?php if ($studentEmail !== ''): ?><div class="fine"><?php echo htmlspecialchars($studentEmail); ?></div><?php endif; ?>
                    <?php if ($studentPhone !== ''): ?><div class="fine"><?php echo htmlspecialchars($studentPhone); ?></div><?php endif; ?>
                    <?php if ($college !== '' || $branch !== '' || $gradYear !== ''): ?>
                        <div class="fine">
                            <?php echo htmlspecialchars(trim($college . ($branch !== '' ? " • {$branch}" : '') . ($gradYear !== '' ? " • {$gradYear}" : ''))); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card">
                    <div class="label">Drive</div>
                    <div class="value"><?php echo htmlspecialchars($driveTitle); ?></div>
                    <div class="fine">
                        Drive window:
                        <?php echo $driveStart !== '' ? htmlspecialchars($driveStart) : 'Open'; ?>
                        to
                        <?php echo $driveEnd !== '' ? htmlspecialchars($driveEnd) : 'Open'; ?>
                    </div>
                    <?php if (!empty($call['evaluated_at'])): ?>
                        <div class="fine">Reviewed at: <?php echo htmlspecialchars((string)$call['evaluated_at']); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <p>Dear <?php echo htmlspecialchars($studentName); ?>,</p>

            <?php if ($status === 'selected'): ?>
                <p>
                    We are pleased to inform you that you have been <strong>selected</strong> in the
                    <strong><?php echo htmlspecialchars($driveTitle); ?></strong> hiring process for
                    <strong><?php echo htmlspecialchars($companyName); ?></strong>.
                </p>
            <?php else: ?>
                <p>
                    Congratulations! Based on your performance in the
                    <strong><?php echo htmlspecialchars($driveTitle); ?></strong> screening for
                    <strong><?php echo htmlspecialchars($companyName); ?></strong>, you have been <strong>invited for the interview round</strong>.
                </p>
            <?php endif; ?>

            <?php if (!empty($call['remarks'])): ?>
                <p><strong>Remarks:</strong> <?php echo htmlspecialchars((string)$call['remarks']); ?></p>
            <?php endif; ?>

            <p>
                Interview schedule details (date/time/venue or meeting link) will be shared via the portal or email.
                Please keep your documents ready and monitor the Companies section for updates.
            </p>

            <p class="fine">
                This letter is auto-generated by PlacementCode for confirmation purposes.
                If you believe this is an error, contact the placement coordinator or raise a ticket.
            </p>

            <div class="sign">
                <div>
                    <div class="label">Generated by</div>
                    <div class="sig">PlacementCode System</div>
                    <div class="fine"><?php echo htmlspecialchars(SITE_NAME); ?></div>
                </div>
                <div style="text-align:right;">
                    <div class="label">For</div>
                    <div class="sig"><?php echo htmlspecialchars($companyName); ?></div>
                    <div class="fine">Campus Hiring Team</div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php
$html = (string)ob_get_clean();

if ($download) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    echo $html;
    exit;
}

echo $html;
