<?php
// freelancer/view_job.php — Full job detail page
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

requireRole('freelancer');

$freelancerId = (int) $_SESSION['user']['id'];
$jobId = (int) ($_GET['job_id'] ?? 0);

if ($jobId <= 0) {
    header("Location: " . BASE_URL . "/freelancer/browse_jobs.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT j.job_id, j.title, j.description, j.budget, j.deadline, j.created_at,
           j.is_reported, c.category_name, u.name AS client_name
    FROM job j
    JOIN category c ON c.category_id = j.category_id
    JOIN users u    ON u.user_id     = j.client_id
    WHERE j.job_id = ? AND j.status = 'approved'
    LIMIT 1
");
$stmt->execute([$jobId]);
$job = $stmt->fetch();

if (!$job) {
    header("Location: " . BASE_URL . "/freelancer/browse_jobs.php");
    exit;
}

// Check already applied
$stmt = $pdo->prepare("SELECT proposal_id, bid_amount, status FROM proposals WHERE job_id=? AND freelancer_id=? LIMIT 1");
$stmt->execute([$jobId, $freelancerId]);
$myProposal = $stmt->fetch();

// Total proposals on this job
$totalProposals = (int) $pdo->prepare("SELECT COUNT(*) FROM proposals WHERE job_id=?")->execute([$jobId])
    ?: 0;
$stmt = $pdo->prepare("SELECT COUNT(*) FROM proposals WHERE job_id=?");
$stmt->execute([$jobId]);
$totalProposals = (int) $stmt->fetchColumn();

// Days left
$deadline = new DateTime($job['deadline']);
$today = new DateTime();
$daysLeft = (int) $today->diff($deadline)->days;
$isPast = $today > $deadline;

$title = htmlspecialchars($job['title']);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5" style="max-width:800px;">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <?php if (!$myProposal && !$isPast): ?>
            <a class="btn btn-brand rounded-pill px-4" href="<?= BASE_URL ?>/freelancer/apply_job.php?job_id=<?= $jobId ?>">
                Apply Now →
            </a>
        <?php endif; ?>
    </div>

    <!-- Job Header -->
    <div class="card card-soft p-4 mb-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h2 class="fw-bold mb-1" style="font-size:1.6rem;">
                    <?= htmlspecialchars($job['title']) ?>
                </h2>
                <div class="text-muted2" style="font-size:.88rem;">
                    📂 <?= htmlspecialchars($job['category_name']) ?>
                    &bull; 👤 Posted by <?= htmlspecialchars($job['client_name']) ?>
                    &bull; 🕐 <?= date('d M Y', strtotime($job['created_at'])) ?>
                </div>
            </div>
            <div class="text-end">
                <div style="font-size:1.8rem; font-weight:900; color:var(--brand);">
                    PKR <?= number_format((float) $job['budget'], 0) ?>
                </div>
                <div class="text-muted2" style="font-size:.82rem;">Project Budget</div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- Job Description -->
        <div class="col-md-8">
            <div class="card card-soft p-4 mb-4">
                <h5 class="fw-bold mb-3">Job Description</h5>
                <div style="line-height:1.8; font-size:.95rem; white-space:pre-wrap;">
                    <?= htmlspecialchars($job['description']) ?>
                </div>
            </div>

            <!-- Apply / Already Applied -->
            <?php if ($myProposal): ?>
                <div class="card card-soft p-4" style="border-color:rgba(74,222,128,.3);">
                    <h5 class="fw-bold mb-2" style="color:#4ade80;">✅ You Already Applied</h5>
                    <div class="text-muted2" style="font-size:.88rem;">
                        Your bid: <strong style="color:var(--brand);">PKR
                            <?= number_format((float) $myProposal['bid_amount'], 0) ?></strong>
                        &bull; Status:
                        <span class="status-badge status-<?= htmlspecialchars($myProposal['status']) ?>">
                            <?= htmlspecialchars($myProposal['status']) ?>
                        </span>
                    </div>
                </div>
            <?php elseif ($isPast): ?>
                <div class="alert alert-danger">⏰ The deadline for this job has passed.</div>
            <?php else: ?>
                <div class="card card-soft p-4" style="border-color:rgba(34,211,238,.25);">
                    <h5 class="fw-bold mb-2">Interested in this job?</h5>
                    <p class="text-muted2 mb-3" style="font-size:.88rem;">
                        Submit your proposal with a competitive bid and a strong cover letter.
                    </p>
                    <a class="btn btn-brand rounded-pill px-4"
                        href="<?= BASE_URL ?>/freelancer/apply_job.php?job_id=<?= $jobId ?>">
                        Apply Now →
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="col-md-4">

            <!-- Job Details -->
            <div class="card card-soft p-4 mb-3">
                <h6 class="fw-bold mb-3">Job Details</h6>

                <div class="mb-3 pb-3" style="border-bottom:1px solid var(--border);">
                    <div class="text-muted2" style="font-size:.78rem; text-transform:uppercase; letter-spacing:.8px;">
                        Budget</div>
                    <div class="fw-bold mt-1" style="color:var(--brand);">
                        PKR <?= number_format((float) $job['budget'], 0) ?>
                    </div>
                </div>

                <div class="mb-3 pb-3" style="border-bottom:1px solid var(--border);">
                    <div class="text-muted2" style="font-size:.78rem; text-transform:uppercase; letter-spacing:.8px;">
                        Deadline</div>
                    <div class="fw-bold mt-1">
                        <?= date('d M Y', strtotime($job['deadline'])) ?>
                    </div>
                    <div
                        style="font-size:.8rem; color: <?= $isPast ? '#f87171' : ($daysLeft <= 3 ? '#fbbf24' : '#4ade80') ?>;">
                        <?= $isPast ? '⏰ Expired' : ($daysLeft === 0 ? '⚠ Due today' : "⏳ {$daysLeft} days left") ?>
                    </div>
                </div>

                <div class="mb-3 pb-3" style="border-bottom:1px solid var(--border);">
                    <div class="text-muted2" style="font-size:.78rem; text-transform:uppercase; letter-spacing:.8px;">
                        Category</div>
                    <div class="fw-bold mt-1"><?= htmlspecialchars($job['category_name']) ?></div>
                </div>

                <div>
                    <div class="text-muted2" style="font-size:.78rem; text-transform:uppercase; letter-spacing:.8px;">
                        Proposals</div>
                    <div class="fw-bold mt-1"><?= $totalProposals ?> submitted</div>
                </div>
            </div>

            <!-- Report -->
            <?php if (!(int) $job['is_reported']): ?>
                <div class="card card-soft p-3 text-center">
                    <div class="text-muted2" style="font-size:.82rem;">Something wrong with this job?</div>
                    <a href="<?= BASE_URL ?>/freelancer/report_job.php?job_id=<?= $jobId ?>"
                        class="btn btn-outline-danger btn-sm rounded-pill mt-2">Report Job</a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>