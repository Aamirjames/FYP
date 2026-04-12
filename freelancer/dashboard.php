<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('freelancer');

$userId = (int) $_SESSION['user']['id'];
$name = $_SESSION['user']['name'] ?? 'Freelancer';

// Stats
$openJobs = (int) $pdo->query("SELECT COUNT(*) FROM job WHERE status='approved'")->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM user_skill WHERE user_id=?");
$stmt->execute([$userId]);
$skillCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT hourly_rate FROM users WHERE user_id=?");
$stmt->execute([$userId]);
$hourlyRate = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM proposals WHERE freelancer_id=?");
$stmt->execute([$userId]);
$proposalCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM job WHERE hired_freelancer_id=? AND status='in_progress'");
$stmt->execute([$userId]);
$activeJobCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM job WHERE hired_freelancer_id=? AND status='completed'");
$stmt->execute([$userId]);
$completedJobCount = (int) $stmt->fetchColumn();

// Profile completeness score
$stmt = $pdo->prepare("SELECT profile_image, portfolio_url, hourly_rate, phone FROM users WHERE user_id=?");
$stmt->execute([$userId]);
$profile = $stmt->fetch();

$profileItems = [
    'Profile Picture' => !empty($profile['profile_image']),
    'Hourly Rate' => !empty($profile['hourly_rate']),
    'Portfolio URL' => !empty($profile['portfolio_url']),
    'Phone Number' => !empty($profile['phone']),
    'Skills Added' => $skillCount > 0,
    'Job Completed' => $completedJobCount > 0,
];
$profileScore = count(array_filter($profileItems));
$profileTotal = count($profileItems);
$profilePercent = (int) (($profileScore / $profileTotal) * 100);
$profileComplete = $profilePercent === 100;

$title = "Freelancer Dashboard";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">

    <!-- Welcome Banner -->
    <div class="card card-soft p-4 mb-4">
        <div
            class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3">
            <div>
                <h3 class="mb-1">Welcome back,
                    <?= htmlspecialchars($name) ?> 👋
                </h3>
                <p class="text-muted2 mb-0">Find work that matches your skills and grow your portfolio.</p>
            </div>
        </div>
    </div>

    <!-- Profile Completeness -->
    <?php if (!$profileComplete): ?>
        <div class="card card-soft p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="fw-bold" style="font-size:.95rem;">Profile Completeness</div>
                <div
                    style="font-size:.9rem; color:<?= $profilePercent >= 80 ? '#4ade80' : ($profilePercent >= 50 ? '#fbbf24' : '#f87171') ?>; font-weight:700;">
                    <?= $profilePercent ?>%
                </div>
            </div>
            <div
                style="background:rgba(255,255,255,.08);border-radius:999px;height:10px;overflow:hidden;margin-bottom:12px;">
                <div style="width:<?= $profilePercent ?>%;
                        background:<?= $profilePercent >= 80 ? '#4ade80' : ($profilePercent >= 50 ? '#fbbf24' : '#f87171') ?>;
                        height:100%;border-radius:999px;transition:width .6s;"></div>
            </div>
            <div class="row g-2">
                <?php foreach ($profileItems as $item => $done): ?>
                    <div class="col-6 col-md-4">
                        <div style="font-size:.82rem; color:<?= $done ? '#4ade80' : '#94a3b8' ?>;">
                            <?= $done ? '✅' : '⬜' ?>
                            <?= htmlspecialchars($item) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <a href="<?= BASE_URL ?>/freelancer/profile.php" class="btn btn-outline-primary btn-sm rounded-pill mt-3">
                Complete My Profile →
            </a>
        </div>
    <?php else: ?>
        <div class="alert"
            style="background:rgba(74,222,128,.1);border:1px solid rgba(74,222,128,.3);color:#4ade80;border-radius:12px;">
            ✅ Your profile is 100% complete! Great work.
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
            <div class="card card-soft p-3 text-center">
                <div style="font-size:1.8rem;font-weight:800;color:var(--brand);">
                    <?= $openJobs ?>
                </div>
                <div class="text-muted2 mt-1" style="font-size:.8rem;">Open Jobs</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card card-soft p-3 text-center">
                <div style="font-size:1.8rem;font-weight:800;color:#a78bfa;">
                    <?= $proposalCount ?>
                </div>
                <div class="text-muted2 mt-1" style="font-size:.8rem;">Proposals Sent</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card card-soft p-3 text-center">
                <div style="font-size:1.8rem;font-weight:800;color:#fbbf24;">
                    <?= $activeJobCount ?>
                </div>
                <div class="text-muted2 mt-1" style="font-size:.8rem;">Active Jobs</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card card-soft p-3 text-center">
                <div style="font-size:1.8rem;font-weight:800;color:#4ade80;">
                    <?= $completedJobCount ?>
                </div>
                <div class="text-muted2 mt-1" style="font-size:.8rem;">Completed</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card card-soft p-3 text-center">
                <div style="font-size:1.8rem;font-weight:800;color:#22d3ee;">
                    <?= $skillCount ?>
                </div>
                <div class="text-muted2 mt-1" style="font-size:.8rem;">Skills</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card card-soft p-3 text-center">
                <div style="font-size:1.4rem;font-weight:800;color:#f472b6;">
                    <?= $hourlyRate ? 'PKR ' . number_format((float) $hourlyRate, 0) : '—' ?>
                </div>
                <div class="text-muted2 mt-1" style="font-size:.8rem;">Hourly Rate</div>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="row g-3">
        <div class="col-md-4">
            <a href="<?= BASE_URL ?>/freelancer/browse_jobs.php"
                class="card card-soft p-4 text-decoration-none d-block">
                <div style="font-size:2rem;">🔍</div>
                <div class="fw-bold mt-2">Browse Jobs</div>
                <div class="text-muted2" style="font-size:.85rem;">
                    <?= $openJobs ?> open jobs available
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="<?= BASE_URL ?>/freelancer/my_jobs.php" class="card card-soft p-4 text-decoration-none d-block">
                <div style="font-size:2rem;">💼</div>
                <div class="fw-bold mt-2">My Jobs</div>
                <div class="text-muted2" style="font-size:.85rem;">
                    <?= $activeJobCount ?> active ·
                    <?= $completedJobCount ?> completed
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="<?= BASE_URL ?>/freelancer/profile.php" class="card card-soft p-4 text-decoration-none d-block">
                <div style="font-size:2rem;">👤</div>
                <div class="fw-bold mt-2">My Profile</div>
                <div class="text-muted2" style="font-size:.85rem;">
                    <?= $profileComplete ? 'Profile complete ✅' : 'Incomplete — update now' ?>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="<?= BASE_URL ?>/freelancer/my_reviews.php" class="card card-soft p-4 text-decoration-none d-block">
                <div style="font-size:2rem;">⭐</div>
                <div class="fw-bold mt-2">My Reviews</div>
                <div class="text-muted2" style="font-size:.85rem;">See ratings from clients</div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="<?= BASE_URL ?>/freelancer/my_proposals.php"
                class="card card-soft p-4 text-decoration-none d-block">
                <div style="font-size:2rem;">📋</div>
                <div class="fw-bold mt-2">My Proposals</div>
                <div class="text-muted2" style="font-size:.85rem;">
                    <?= $proposalCount ?> submitted
                </div>
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>