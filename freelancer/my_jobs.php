<?php
// SSH15 (Part 1): Freelancer views their active/completed jobs & marks job as done
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/notify.php';

requireRole('freelancer');

$freelancerId = (int) $_SESSION['user']['id'];
$msg = '';
$err = '';

// Handle "Mark as Complete" submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $jobId = (int) ($_POST['job_id'] ?? 0);

    if ($jobId > 0) {
        // Verify this freelancer is actually hired for this job
        $stmt = $pdo->prepare("
            SELECT job_id FROM job
            WHERE job_id = ? AND hired_freelancer_id = ? AND status = 'in_progress' AND completed_by_freelancer = 0
        ");
        $stmt->execute([$jobId, $freelancerId]);

        if ($stmt->fetch()) {
            $pdo->prepare("
                UPDATE job SET completed_by_freelancer = 1, completed_at = NOW()
                WHERE job_id = ?
            ")->execute([$jobId]);
            $msg = "✅ Job marked as completed! Waiting for client confirmation.";

            // Notify client (SSH05)
            $cStmt = $pdo->prepare("SELECT client_id, title FROM job WHERE job_id=? LIMIT 1");
            $cStmt->execute([$jobId]);
            $cJob = $cStmt->fetch();
            if ($cJob) {
                notify(
                    $pdo,
                    (int) $cJob['client_id'],
                    "🏁 Freelancer has marked the job complete: {$cJob['title']}. Please confirm.",
                    "client/active_jobs.php"
                );
            }
        } else {
            $err = "Unable to mark this job as complete.";
        }
    }
}

// Load all jobs this freelancer is hired for
$stmt = $pdo->prepare("
    SELECT j.job_id, j.title, j.budget, j.deadline, j.status,
           j.completed_by_freelancer, j.completed_at,
           j.confirmed_by_client, j.confirmed_at,
           c.category_name,
           u.name AS client_name,
           p.bid_amount,
           pay.payment_id, pay.status AS payment_status, pay.paid_at
    FROM job j
    JOIN category c ON c.category_id = j.category_id
    JOIN users u ON u.user_id = j.client_id
    LEFT JOIN proposals p ON p.job_id = j.job_id AND p.freelancer_id = ?
    LEFT JOIN payments pay ON pay.job_id = j.job_id
    WHERE j.hired_freelancer_id = ?
    ORDER BY j.created_at DESC
");
$stmt->execute([$freelancerId, $freelancerId]);
$myJobs = $stmt->fetchAll();

$title = "My Jobs";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">My Jobs</h3>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($err) ?>
        </div>
    <?php endif; ?>

    <?php if (!$myJobs): ?>
        <div class="card card-soft p-4 text-center text-muted2 py-5">
            <div style="font-size:2.5rem;">💼</div>
            <div class="mt-2">You haven't been hired for any jobs yet.</div>
            <a href="<?= BASE_URL ?>/freelancer/browse_jobs.php" class="btn btn-brand rounded-pill px-4 mt-3">Browse
                Jobs</a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($myJobs as $j):
                $status = $j['status'];
                $doneByFreelancer = (int) $j['completed_by_freelancer'] === 1;
                $confirmedByClient = (int) $j['confirmed_by_client'] === 1;
                $paid = !empty($j['payment_id']);
                ?>
                <div class="col-lg-6">
                    <div class="card card-soft p-4 h-100 d-flex flex-column">

                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="fw-bold" style="font-size:1.05rem;">
                                <?= htmlspecialchars($j['title']) ?>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <?php $st = preg_replace('/[^a-z_]/', '', strtolower(trim($status))); ?>
                                <span class="status-badge status-<?= $st ?>">
                                    <?= htmlspecialchars($status) ?>
                                </span>
                                <a class="btn btn-outline-primary btn-sm rounded-pill"
                                    href="<?= BASE_URL ?>/messages.php?job_id=<?= (int) $j['job_id'] ?>">
                                    💬 Message
                                </a>
                            </div>
                        </div>

                        <div class="text-muted2 mb-3" style="font-size:.87rem;">
                            📂
                            <?= htmlspecialchars($j['category_name']) ?>
                            &bull; 👤 Client:
                            <?= htmlspecialchars($j['client_name']) ?>
                        </div>

                        <div class="d-flex gap-4 flex-wrap mb-3">
                            <span class="text-muted2">💰 Job Budget: <strong>PKR
                                    <?= number_format((float) $j['budget'], 2) ?>
                                </strong></span>
                            <?php if ($j['bid_amount']): ?>
                                <span class="text-muted2">🤝 Your Bid: <strong style="color:var(--brand);">PKR
                                        <?= number_format((float) $j['bid_amount'], 2) ?>
                                    </strong></span>
                            <?php endif; ?>
                        </div>

                        <div class="text-muted2 mb-3" style="font-size:.84rem;">📅 Deadline:
                            <?= htmlspecialchars($j['deadline']) ?>
                        </div>

                        <!-- Progress Timeline -->
                        <div class="mb-3 p-3 rounded"
                            style="background:rgba(34,211,238,.05);border:1px solid rgba(34,211,238,.12);">
                            <div class="d-flex flex-column gap-2" style="font-size:.88rem;">
                                <div style="color: <?= $doneByFreelancer ? '#4ade80' : 'var(--text-muted)' ?>;">
                                    <?= $doneByFreelancer ? '✅' : '⬜' ?> Work submitted
                                    <?php if ($j['completed_at']): ?>
                                        <span class="text-muted2">(
                                            <?= date('d M Y', strtotime($j['completed_at'])) ?>)
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div style="color: <?= $confirmedByClient ? '#4ade80' : 'var(--text-muted)' ?>;">
                                    <?= $confirmedByClient ? '✅' : '⬜' ?> Client confirmed completion
                                    <?php if ($j['confirmed_at']): ?>
                                        <span class="text-muted2">(
                                            <?= date('d M Y', strtotime($j['confirmed_at'])) ?>)
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div style="color: <?= $paid ? '#4ade80' : 'var(--text-muted)' ?>;">
                                    <?= $paid ? '✅' : '⬜' ?> Payment confirmed
                                    <?php if ($j['paid_at']): ?>
                                        <span class="text-muted2">(
                                            <?= date('d M Y', strtotime($j['paid_at'])) ?>)
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Action -->
                        <div class="mt-auto">
                            <?php if ($status === 'in_progress' && !$doneByFreelancer): ?>
                                <form method="post"
                                    onsubmit="return confirm('Mark this job as complete?\n\nMake sure you have submitted all deliverables to the client.')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="job_id" value="<?= (int) $j['job_id'] ?>">
                                    <button class="btn btn-brand w-100 rounded-pill">🏁 Mark as Complete</button>
                                </form>
                            <?php elseif ($doneByFreelancer && !$confirmedByClient): ?>
                                <div class="alert alert-warning mb-0 py-2 text-center" style="font-size:.88rem;">
                                    ⏳ Waiting for client to confirm completion…
                                </div>
                            <?php elseif ($confirmedByClient && !$paid): ?>
                                <div class="alert alert-info mb-0 py-2 text-center" style="font-size:.88rem;">
                                    💳 Job confirmed! Awaiting payment from client.
                                </div>
                            <?php elseif ($paid): ?>
                                <div class="alert alert-success mb-0 py-2 text-center" style="font-size:.88rem;">
                                    🎉 Payment received! Job fully complete.
                                </div>
                            <?php elseif ($status === 'completed'): ?>
                                <div class="alert alert-success mb-0 py-2 text-center" style="font-size:.88rem;">
                                    ✅ Job completed.
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>