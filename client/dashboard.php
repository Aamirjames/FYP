<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('client');

$clientId = (int) $_SESSION['user']['id'];
$clientName = $_SESSION['user']['name'] ?? 'Client';

// Stats
$totalJobs = $pdo->prepare("SELECT COUNT(*) FROM job WHERE client_id=?");
$totalJobs->execute([$clientId]);
$totalJobs = (int) $totalJobs->fetchColumn();

$pendingJobs = $pdo->prepare("SELECT COUNT(*) FROM job WHERE client_id=? AND status='pending'");
$pendingJobs->execute([$clientId]);
$pendingJobs = (int) $pendingJobs->fetchColumn();

$inProgressJobs = $pdo->prepare("SELECT COUNT(*) FROM job WHERE client_id=? AND status='in_progress'");
$inProgressJobs->execute([$clientId]);
$inProgressJobs = (int) $inProgressJobs->fetchColumn();

$completedJobs = $pdo->prepare("SELECT COUNT(*) FROM job WHERE client_id=? AND status='completed'");
$completedJobs->execute([$clientId]);
$completedJobs = (int) $completedJobs->fetchColumn();

// My jobs with proposal counts
$stmt = $pdo->prepare("
    SELECT j.job_id, j.title, j.budget, j.deadline, j.status, j.created_at,
           c.category_name,
           COUNT(p.proposal_id)                                 AS proposal_count,
           SUM(CASE WHEN p.status='pending' THEN 1 ELSE 0 END) AS pending_proposals
    FROM job j
    JOIN category c ON c.category_id = j.category_id
    LEFT JOIN proposals p ON p.job_id = j.job_id
    WHERE j.client_id = ?
    GROUP BY j.job_id
    ORDER BY j.created_at DESC
");
$stmt->execute([$clientId]);
$myJobs = $stmt->fetchAll();

$title = "Client Dashboard";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">

    <!-- Welcome Banner -->
    <div class="card card-soft p-4 mb-4">
        <div
            class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3">
            <div>
                <h3 class="mb-1">Welcome back,
                    <?= htmlspecialchars($clientName) ?> 👋
                </h3>
                <p class="text-muted2 mb-0">Post jobs and find the right freelancer for your projects.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-primary rounded-pill" href="<?= BASE_URL ?>/client/my_jobs.php">My Jobs</a>
                <a class="btn btn-outline-primary rounded-pill"
                    href="<?= BASE_URL ?>/client/browse_freelancers.php">Browse Freelancers</a>
                <a class="btn btn-outline-primary rounded-pill" href="<?= BASE_URL ?>/client/active_jobs.php">Active
                    Jobs</a>
                <a class="btn btn-outline-primary rounded-pill" href="<?= BASE_URL ?>/client/profile.php">My Profile</a>
                <a class="btn btn-brand rounded-pill px-4" href="<?= BASE_URL ?>/client/post_job.php">+ Post a Job</a>
            </div>
        </div>
    </div>

    <!-- -  Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card card-soft p-3 text-center">
                <div style="font-size:2rem;font-weight:800;color:var(--brand);">
                    <?= $totalJobs ?>
                </div>
                <div class="text-muted2 mt-1">Total Jobs</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-soft p-3 text-center">
                <div style="font-size:2rem;font-weight:800;color:#fbbf24;">
                    <?= $pendingJobs ?>
                </div>
                <div class="text-muted2 mt-1">Pending Approval</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-soft p-3 text-center">
                <div style="font-size:2rem;font-weight:800;color:#22d3ee;">
                    <?= $inProgressJobs ?>
                </div>
                <div class="text-muted2 mt-1">In Progress</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-soft p-3 text-center">
                <div style="font-size:2rem;font-weight:800;color:#4ade80;">
                    <?= $completedJobs ?>
                </div>
                <div class="text-muted2 mt-1">Completed</div>
            </div>
        </div>
    </div>

    <!--  My Jobs Table --->
    <div class="card card-soft p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">My Posted Jobs</h5>
            <a class="btn btn-outline-primary btn-sm rounded-pill" href="<?= BASE_URL ?>/client/my_jobs.php">View All
                →</a>
        </div>
        <?php if ($myJobs): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Budget</th>
                        <th>Deadline</th>
                        <th>Status</th>
                        <th>Proposals</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($myJobs as $j): ?>
                    <tr>
                        <td class="text-muted2">
                            <?= (int) $j['job_id'] ?>
                        </td>
                        <td class="fw-bold">
                            <?= htmlspecialchars($j['title']) ?>
                        </td>
                        <td class="text-muted2">
                            <?= htmlspecialchars($j['category_name']) ?>
                        </td>
                        <td>PKR
                            <?= number_format((float) $j['budget'], 2) ?>
                        </td>
                        <td class="text-muted2">
                            <?= htmlspecialchars($j['deadline']) ?>
                        </td>
                        <td>
                            <?php $st = preg_replace('/[^a-z_]/', '', strtolower(trim($j['status']))); ?>
                            <span class="status-badge status-<?= $st ?>">
                                <?= htmlspecialchars($j['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ((int) $j['proposal_count'] > 0): ?>
                            <span style="color:var(--brand);font-weight:700;">
                                <?= (int) $j['proposal_count'] ?>
                            </span>
                            <?php if ((int) $j['pending_proposals'] > 0): ?>
                            <span class="text-muted2" style="font-size:.82rem;"> (
                                <?= (int) $j['pending_proposals'] ?> new)
                            </span>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="text-muted2">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($j['status'] === 'approved'): ?>
                            <a class="btn btn-brand btn-sm rounded-pill"
                                href="<?= BASE_URL ?>/client/view_proposals.php?job_id=<?= (int) $j['job_id'] ?>">
                                View Proposals
                            </a>
                            <?php elseif ($j['status'] === 'in_progress'): ?>
                            <a class="btn btn-outline-primary btn-sm rounded-pill"
                                href="<?= BASE_URL ?>/client/active_jobs.php">
                                Manage →
                            </a>
                            <?php elseif ($j['status'] === 'completed'): ?>
                            <span class="text-muted2" style="font-size:.84rem;">✅ Done</span>
                            <?php else: ?>
                            <span class="text-muted2" style="font-size:.84rem;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center text-muted2 py-5">
            <div style="font-size:2.5rem;">📋</div>
            <div class="mt-2">You haven't posted any jobs yet.</div>
            <a href="<?= BASE_URL ?>/client/post_job.php" class="btn btn-brand rounded-pill px-4 mt-3">Post your first
                job</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>