<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');

// ── Stats ────────────────────────────────────────────────────
$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('client','freelancer')")->fetchColumn();
$pendingUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status='pending'")->fetchColumn();
$blockedUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status='blocked'")->fetchColumn();
$totalJobs = (int) $pdo->query("SELECT COUNT(*) FROM job")->fetchColumn();
$pendingJobs = (int) $pdo->query("SELECT COUNT(*) FROM job WHERE status='pending'")->fetchColumn();
$reportedJobs = (int) $pdo->query("SELECT COUNT(*) FROM job WHERE is_reported=1")->fetchColumn();
$approvedJobs = (int) $pdo->query("SELECT COUNT(*) FROM job WHERE status='approved'")->fetchColumn();
$inProgressJobs = (int) $pdo->query("SELECT COUNT(*) FROM job WHERE status='in_progress'")->fetchColumn();
$completedJobs = (int) $pdo->query("SELECT COUNT(*) FROM job WHERE status='completed'")->fetchColumn();
$totalProposals = (int) $pdo->query("SELECT COUNT(*) FROM proposals")->fetchColumn();
$totalMessages = (int) $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
$totalPayments = (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE status='confirmed'")->fetchColumn();
$totalPaidOut = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='confirmed'")->fetchColumn();

// ── Recent Users ─────────────────────────────────────────────
$recentUsers = $pdo->query("
    SELECT name, email, role, status, created_at
    FROM users WHERE role != 'admin'
    ORDER BY created_at DESC LIMIT 5
")->fetchAll();

// ── Recent Jobs ──────────────────────────────────────────────
$recentJobs = $pdo->query("
    SELECT j.job_id, j.title, j.status, j.created_at, u.name AS client_name
    FROM job j JOIN users u ON u.user_id = j.client_id
    ORDER BY j.created_at DESC LIMIT 5
")->fetchAll();

$title = "Admin Dashboard";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">

    <!-- Header -->
    <div class="card card-soft p-4 mb-4"
        style="background:linear-gradient(135deg,rgba(34,211,238,.08),rgba(167,139,250,.06));">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h3 class="mb-1 fw-bold">⚙️ Admin Dashboard</h3>
                <p class="text-muted2 mb-0">Platform overview — manage users, jobs and reports.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-brand rounded-pill px-4" href="<?= BASE_URL ?>/admin/users.php">Manage Users</a>
                <a class="btn btn-outline-primary rounded-pill px-4" href="<?= BASE_URL ?>/admin/jobs.php">Manage
                    Jobs</a>
                <a class="btn btn-outline-primary rounded-pill px-4" href="<?= BASE_URL ?>/admin/reports.php">📊
                    Reports</a>
            </div>
        </div>
    </div>

    <!-- Urgent Alerts -->
    <?php if ($pendingUsers > 0): ?>
        <div class="alert mb-3"
            style="background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.35);color:#fbbf24;border-radius:12px;">
            ⚠️ <strong>
                <?= $pendingUsers ?> user
                <?= $pendingUsers > 1 ? 's' : '' ?>
            </strong> waiting for approval.
            <a href="<?= BASE_URL ?>/admin/users.php?status=pending" style="color:#fbbf24;font-weight:700;">Review now →</a>
        </div>
    <?php endif; ?>
    <?php if ($reportedJobs > 0): ?>
        <div class="alert mb-4"
            style="background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.35);color:#f87171;border-radius:12px;">
            🚨 <strong>
                <?= $reportedJobs ?> job
                <?= $reportedJobs > 1 ? 's' : '' ?>
            </strong> reported and need review.
            <a href="<?= BASE_URL ?>/admin/jobs.php?status=reported" style="color:#f87171;font-weight:700;">Review now →</a>
        </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <h5 class="fw-bold mb-3">👥 Users</h5>
    <div class="row g-3 mb-4">
        <?php
        $userStats = [
            ['Total Users', $totalUsers, '#22d3ee', null],
            ['Pending Approval', $pendingUsers, '#fbbf24', BASE_URL . '/admin/users.php?status=pending'],
            ['Blocked', $blockedUsers, '#f87171', BASE_URL . '/admin/users.php?status=blocked'],
        ];
        foreach ($userStats as [$label, $val, $color, $link]): ?>
            <div class="col-6 col-md-4">
                <?php if ($link): ?>
                    <a href="<?= $link ?>" class="card card-soft p-3 text-center text-decoration-none d-block">
                    <?php else: ?>
                        <div class="card card-soft p-3 text-center">
                        <?php endif; ?>
                        <div style="font-size:2rem;font-weight:800;color:<?= $color ?>;">
                            <?= $val ?>
                        </div>
                        <div class="text-muted2 mt-1">
                            <?= $label ?>
                        </div>
                        <?php echo $link ? '</a>' : '</div>'; ?>
                    </div>
                <?php endforeach; ?>
        </div>

        <h5 class="fw-bold mb-3">💼 Jobs</h5>
        <div class="row g-3 mb-4">
            <?php
            $jobStats = [
                ['Total', $totalJobs, '#22d3ee', null],
                ['Pending', $pendingJobs, '#fbbf24', BASE_URL . '/admin/jobs.php?status=pending'],
                ['Approved', $approvedJobs, '#4ade80', BASE_URL . '/admin/jobs.php?status=approved'],
                ['In Progress', $inProgressJobs, '#22d3ee', null],
                ['Completed', $completedJobs, '#4ade80', null],
                ['Reported', $reportedJobs, '#f87171', BASE_URL . '/admin/jobs.php?status=reported'],
            ];
            foreach ($jobStats as [$label, $val, $color, $link]): ?>
                <div class="col-6 col-md-2">
                    <?php if ($link): ?>
                        <a href="<?= $link ?>" class="card card-soft p-3 text-center text-decoration-none d-block">
                        <?php else: ?>
                            <div class="card card-soft p-3 text-center">
                            <?php endif; ?>
                            <div style="font-size:1.7rem;font-weight:800;color:<?= $color ?>;">
                                <?= $val ?>
                            </div>
                            <div class="text-muted2 mt-1" style="font-size:.8rem;">
                                <?= $label ?>
                            </div>
                            <?php echo $link ? '</a>' : '</div>'; ?>
                        </div>
                    <?php endforeach; ?>
            </div>

            <h5 class="fw-bold mb-3">📈 Platform Activity</h5>
            <div class="row g-3 mb-5">
                <?php
                $actStats = [
                    ['Total Proposals', $totalProposals, '#a78bfa'],
                    ['Messages Sent', $totalMessages, '#22d3ee'],
                    ['Payments Made', $totalPayments, '#4ade80'],
                    ['Total Paid Out', 'PKR ' . number_format($totalPaidOut, 0), '#4ade80'],
                ];
                foreach ($actStats as [$label, $val, $color]): ?>
                    <div class="col-6 col-md-3">
                        <div class="card card-soft p-3 text-center">
                            <div style="font-size:1.5rem;font-weight:800;color:<?= $color ?>;">
                                <?= $val ?>
                            </div>
                            <div class="text-muted2 mt-1" style="font-size:.82rem;">
                                <?= $label ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Recent Activity -->
            <div class="row g-4">

                <!-- Recent Users -->
                <div class="col-md-6">
                    <div class="card card-soft p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">🆕 Recent Registrations</h5>
                            <a href="<?= BASE_URL ?>/admin/users.php" style="font-size:.83rem;color:var(--brand);">View
                                all →</a>
                        </div>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($recentUsers as $u): ?>
                                <div class="d-flex justify-content-between align-items-center p-2 rounded"
                                    style="background:rgba(255,255,255,.03);">
                                    <div>
                                        <div class="fw-bold" style="font-size:.88rem;">
                                            <?= htmlspecialchars($u['name']) ?>
                                        </div>
                                        <div class="text-muted2" style="font-size:.76rem;">
                                            <?= htmlspecialchars($u['email']) ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <?php $st = preg_replace('/[^a-z_]/', '', strtolower(trim($u['status']))); ?>
                                        <span class="status-badge status-<?= $st ?>" style="font-size:.7rem;">
                                            <?= $u['status'] ?>
                                        </span>
                                        <div class="text-muted2" style="font-size:.72rem;">
                                            <?= date('d M', strtotime($u['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$recentUsers): ?>
                                <div class="text-muted2 text-center py-3">No users yet.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Jobs -->
                <div class="col-md-6">
                    <div class="card card-soft p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">📋 Recent Jobs</h5>
                            <a href="<?= BASE_URL ?>/admin/jobs.php" style="font-size:.83rem;color:var(--brand);">View
                                all →</a>
                        </div>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($recentJobs as $j): ?>
                                <div class="d-flex justify-content-between align-items-center p-2 rounded"
                                    style="background:rgba(255,255,255,.03);">
                                    <div>
                                        <div class="fw-bold" style="font-size:.88rem;">
                                            <?= htmlspecialchars($j['title']) ?>
                                        </div>
                                        <div class="text-muted2" style="font-size:.76rem;">by
                                            <?= htmlspecialchars($j['client_name']) ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <?php $st = preg_replace('/[^a-z_]/', '', strtolower(trim($j['status']))); ?>
                                        <span class="status-badge status-<?= $st ?>" style="font-size:.7rem;">
                                            <?= $j['status'] ?>
                                        </span>
                                        <div class="text-muted2" style="font-size:.72rem;">
                                            <?= date('d M', strtotime($j['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$recentJobs): ?>
                                <div class="text-muted2 text-center py-3">No jobs yet.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>