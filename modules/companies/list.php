<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/CompanyPortal.php';

Auth::requireLogin();

$userId = (int)Auth::getUserId();
$portal = new CompanyPortal();
$companies = $portal->getCompaniesForUser($userId);

$pageTitle = 'Companies - PlacementCode';
$additionalCSS = '
.container { max-width: 1400px; margin: 28px auto; padding: 0 20px; }
.header-card {
    background: linear-gradient(140deg, rgba(239,68,68,0.18), rgba(17,24,39,0.4));
    border: 1px solid #2a2a2a;
    border-radius: 14px;
    padding: 26px;
    margin-bottom: 24px;
}
.header-card h1 { font-size: 2rem; margin-bottom: 8px; }
.header-card p { color: #9ca3af; max-width: 780px; line-height: 1.55; }
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 18px;
}
.company-card {
    background: #151515;
    border: 1px solid #2a2a2a;
    border-radius: 12px;
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.company-card h3 { font-size: 1.18rem; }
.meta { color: #a1a1aa; font-size: 0.88rem; line-height: 1.45; }
.stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.stat {
    background: #0e0e0e;
    border: 1px solid #262626;
    border-radius: 8px;
    padding: 10px;
}
.stat-label { color: #9ca3af; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.35px; }
.stat-value { color: #f3f4f6; font-size: 1.08rem; font-weight: 600; margin-top: 4px; }
.btn {
    margin-top: auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    background: #ef4444;
    border: 1px solid #ef4444;
    color: #fff;
    padding: 10px 14px;
    border-radius: 8px;
    font-weight: 600;
}
.empty {
    padding: 36px;
    text-align: center;
    border: 1px dashed #2a2a2a;
    border-radius: 12px;
    color: #9ca3af;
}
@media (max-width: 768px) {
    .stats { grid-template-columns: 1fr; }
}
';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="container">
    <div class="header-card">
        <h1>Company Preparation</h1>
        <p>
            Browse active company drives, see your shortlist progress, and prepare using coding and aptitude requirements defined by each company.
        </p>
    </div>

    <?php if (empty($companies)): ?>
        <div class="empty">No active companies are configured yet.</div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($companies as $company): ?>
                <article class="company-card">
                    <h3><?php echo htmlspecialchars((string)$company['company_name']); ?></h3>
                    <div class="meta">
                        <?php
                        $desc = trim((string)($company['description'] ?? ''));
                        if ($desc === '') {
                            echo 'Company-specific drives and interview process are configured by admin.';
                        } else {
                            echo htmlspecialchars(function_exists('mb_strimwidth') ? mb_strimwidth($desc, 0, 140, '...') : substr($desc, 0, 140));
                        }
                        ?>
                    </div>
                    <div class="stats">
                        <div class="stat">
                            <div class="stat-label">Active Drives</div>
                            <div class="stat-value"><?php echo (int)($company['active_drives'] ?? 0); ?></div>
                        </div>
                        <div class="stat">
                            <div class="stat-label">My Shortlists</div>
                            <div class="stat-value"><?php echo (int)($company['my_shortlists'] ?? 0); ?></div>
                        </div>
                        <div class="stat">
                            <div class="stat-label">Prep Questions</div>
                            <div class="stat-value"><?php echo (int)($company['question_count'] ?? 0); ?></div>
                        </div>
                    </div>
                    <div class="meta">
                        Package: <?php echo htmlspecialchars((string)($company['package_range'] ?? 'Not specified')); ?>
                    </div>
                    <a class="btn" href="profile.php?id=<?php echo (int)$company['company_id']; ?>">View Company Drives</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
