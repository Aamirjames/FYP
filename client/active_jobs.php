<?php
// SSH15 (Part 2) + SSH16: Client confirms job completion and logs payment
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/notify.php';

requireRole('client');

$clientId = (int) $_SESSION['user']['id'];
$msg = '';
$err = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $jobId = (int) ($_POST['job_id'] ?? 0);

    if ($jobId > 0) {
        // Verify this job belongs to this client
        $stmt = $pdo->prepare("SELECT job_id, status, hired_freelancer_id, completed_by_freelancer, confirmed_by_client FROM job WHERE job_id = ? AND client_id = ?");
        $stmt->execute([$jobId, $clientId]);
        $job = $stmt->fetch();

        if (!$job) {
            $err = "Job not found.";

        } elseif ($action === 'confirm_completion') {
            // SSH15: Client confirms completion
            if ((int) $job['completed_by_freelancer'] !== 1) {
                $err = "Freelancer has not marked this job as complete yet.";
            } elseif ((int) $job['confirmed_by_client'] === 1) {
                $err = "You already confirmed this job.";
            } else {
                $pdo->prepare("UPDATE job SET confirmed_by_client=1, confirmed_at=NOW() WHERE job_id=?")
                    ->execute([$jobId]);
                $msg = "✅ Job completion confirmed! You can now confirm payment.";

                // Notify freelancer (SSH05)
                notify(
                    $pdo,
                    (int) $job['hired_freelancer_id'],
                    "✅ Client confirmed your work completion. Awaiting payment.",
                    "freelancer/my_jobs.php"
                );
            }

        } elseif ($action === 'confirm_payment') {
            // SSH16: Client confirms payment
            if ((int) $job['confirmed_by_client'] !== 1) {
                $err = "Please confirm job completion before confirming payment.";
            } else {
                // Check if payment already recorded
                $stmt = $pdo->prepare("SELECT payment_id FROM payments WHERE job_id=? LIMIT 1");
                $stmt->execute([$jobId]);
                if ($stmt->fetch()) {
                    $err = "Payment already recorded for this job.";
                } else {
                    $amount = trim($_POST['amount'] ?? '');
                    $method = trim($_POST['method'] ?? 'Manual');
                    $note = trim($_POST['note'] ?? '');

                    if (!is_numeric($amount) || (float) $amount <= 0) {
                        $err = "Please enter a valid payment amount.";
                    } else {
                        $pdo->beginTransaction();
                        try {
                            // Insert payment record
                            $pdo->prepare("
                                INSERT INTO payments (job_id, client_id, freelancer_id, amount, method, note, status, paid_at)
                                VALUES (?, ?, ?, ?, ?, ?, 'confirmed', NOW())
                            ")->execute([$jobId, $clientId, $job['hired_freelancer_id'], (float) $amount, $method, $note]);

                            // Mark job as completed
                            $pdo->prepare("UPDATE job SET status='completed' WHERE job_id=?")
                                ->execute([$jobId]);

                            $pdo->commit();
                            $msg = "🎉 Payment confirmed! Job is now fully complete.";

                            // Notify freelancer (SSH05)
                            notify(
                                $pdo,
                                (int) $job['hired_freelancer_id'],
                                "💰 Payment confirmed for job completion. Thank you!",
                                "freelancer/my_jobs.php"
                            );
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            $err = "Something went wrong. Please try again.";
                        }
                    }
                }
            }
        }
    }
}

// Load all in-progress & recently completed jobs for this client
$stmt = $pdo->prepare("
    SELECT j.job_id, j.title, j.budget, j.deadline, j.status,
           j.completed_by_freelancer, j.completed_at,
           j.confirmed_by_client, j.confirmed_at,
           c.category_name,
           u.name AS freelancer_name,
           p.bid_amount,
           pay.payment_id, pay.amount AS paid_amount, pay.method, pay.note, pay.paid_at
    FROM job j
    JOIN category c ON c.category_id = j.category_id
    JOIN users u ON u.user_id = j.hired_freelancer_id
    LEFT JOIN proposals p ON p.job_id = j.job_id AND p.freelancer_id = j.hired_freelancer_id
    LEFT JOIN payments pay ON pay.job_id = j.job_id
    WHERE j.client_id = ? AND j.status IN ('in_progress','completed') AND j.hired_freelancer_id IS NOT NULL
    ORDER BY j.created_at DESC
");
$stmt->execute([$clientId]);
$activeJobs = $stmt->fetchAll();

$title = "Active Jobs";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Active & Completed Jobs</h3>
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

    <?php if (!$activeJobs): ?>
        <div class="card card-soft p-4 text-center text-muted2 py-5">
            <div style="font-size:2.5rem;">💼</div>
            <div class="mt-2">No active jobs yet. Hire a freelancer from your proposals.</div>
            <a href="<?= BASE_URL ?>/client/dashboard.php" class="btn btn-brand rounded-pill px-4 mt-3">Go to Dashboard</a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($activeJobs as $j):
                $doneByFreelancer = (int) $j['completed_by_freelancer'] === 1;
                $confirmedByClient = (int) $j['confirmed_by_client'] === 1;
                $paid = !empty($j['payment_id']);
                $status = $j['status'];
                ?>
                <div class="col-12">
                    <div class="card card-soft p-4">

                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                            <div>
                                <div class="fw-bold" style="font-size:1.1rem;">
                                    <?= htmlspecialchars($j['title']) ?>
                                </div>
                                <div class="text-muted2" style="font-size:.87rem;">
                                    📂
                                    <?= htmlspecialchars($j['category_name']) ?>
                                    &bull; 👤 Freelancer: <strong>
                                        <?= htmlspecialchars($j['freelancer_name']) ?>
                                    </strong>
                                </div>
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

                        <div class="d-flex gap-4 flex-wrap mb-3">
                            <span class="text-muted2">💰 Budget: <strong>PKR
                                    <?= number_format((float) $j['budget'], 2) ?>
                                </strong></span>
                            <?php if ($j['bid_amount']): ?>
                                <span class="text-muted2">🤝 Agreed Bid: <strong style="color:var(--brand);">PKR
                                        <?= number_format((float) $j['bid_amount'], 2) ?>
                                    </strong></span>
                            <?php endif; ?>
                            <span class="text-muted2">📅 Deadline:
                                <?= htmlspecialchars($j['deadline']) ?>
                            </span>
                        </div>

                        <!-- Progress Timeline -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <div class="p-3 rounded text-center"
                                    style="background:rgba(<?= $doneByFreelancer ? '74,222,128' : '100,116,139' ?>,.1);border:1px solid rgba(<?= $doneByFreelancer ? '74,222,128' : '100,116,139' ?>,.2);">
                                    <div style="font-size:1.4rem;">
                                        <?= $doneByFreelancer ? '✅' : '⏳' ?>
                                    </div>
                                    <div style="font-size:.85rem;font-weight:600;margin-top:4px;">Work Submitted</div>
                                    <?php if ($j['completed_at']): ?>
                                        <div class="text-muted2" style="font-size:.78rem;">
                                            <?= date('d M Y', strtotime($j['completed_at'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 rounded text-center"
                                    style="background:rgba(<?= $confirmedByClient ? '74,222,128' : '100,116,139' ?>,.1);border:1px solid rgba(<?= $confirmedByClient ? '74,222,128' : '100,116,139' ?>,.2);">
                                    <div style="font-size:1.4rem;">
                                        <?= $confirmedByClient ? '✅' : '⏳' ?>
                                    </div>
                                    <div style="font-size:.85rem;font-weight:600;margin-top:4px;">Client Confirmed</div>
                                    <?php if ($j['confirmed_at']): ?>
                                        <div class="text-muted2" style="font-size:.78rem;">
                                            <?= date('d M Y', strtotime($j['confirmed_at'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 rounded text-center"
                                    style="background:rgba(<?= $paid ? '74,222,128' : '100,116,139' ?>,.1);border:1px solid rgba(<?= $paid ? '74,222,128' : '100,116,139' ?>,.2);">
                                    <div style="font-size:1.4rem;">
                                        <?= $paid ? '✅' : '⏳' ?>
                                    </div>
                                    <div style="font-size:.85rem;font-weight:600;margin-top:4px;">Payment Confirmed</div>
                                    <?php if ($j['paid_at']): ?>
                                        <div class="text-muted2" style="font-size:.78rem;">
                                            <?= date('d M Y', strtotime($j['paid_at'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Summary (if paid) -->
                        <?php if ($paid): ?>
                            <div class="p-3 rounded mb-3"
                                style="background:rgba(34,211,238,.07);border:1px solid rgba(34,211,238,.18);">
                                <div class="fw-bold mb-1">💳 Payment Record</div>
                                <div class="text-muted2" style="font-size:.88rem;">
                                    Amount: <strong style="color:var(--brand);">PKR
                                        <?= number_format((float) $j['paid_amount'], 2) ?>
                                    </strong>
                                    &bull; Method:
                                    <?= htmlspecialchars($j['method']) ?>
                                    <?php if ($j['note']): ?>
                                        &bull; Note:
                                        <?= htmlspecialchars($j['note']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Action Area -->
                        <?php if ($status === 'in_progress'): ?>

                            <?php if (!$doneByFreelancer): ?>
                                <div class="alert alert-info mb-0 py-2" style="font-size:.88rem;">
                                    ⏳ Waiting for freelancer to mark work as complete…
                                </div>

                            <?php elseif ($doneByFreelancer && !$confirmedByClient): ?>
                                <!-- SSH15: Confirm Completion -->
                                <div class="p-3 rounded" style="background:rgba(251,191,36,.07);border:1px solid rgba(251,191,36,.3);">
                                    <div class="fw-bold mb-2">🏁 Freelancer has submitted the work</div>
                                    <div class="text-muted2 mb-3" style="font-size:.88rem;">
                                        Review the deliverables and confirm completion to proceed to payment.
                                    </div>
                                    <form method="post"
                                        onsubmit="return confirm('Confirm that the work is complete and satisfactory?')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                        <input type="hidden" name="job_id" value="<?= (int) $j['job_id'] ?>">
                                        <input type="hidden" name="action" value="confirm_completion">
                                        <button class="btn btn-brand rounded-pill px-4">✅ Confirm Completion</button>
                                    </form>
                                </div>

                            <?php elseif ($confirmedByClient && !$paid): ?>
                                <!-- SSH16: Confirm Payment -->
                                <div class="p-3 rounded" style="background:rgba(34,211,238,.07);border:1px solid rgba(34,211,238,.25);">
                                    <div class="fw-bold mb-3">💳 Confirm Payment to Freelancer</div>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                        <input type="hidden" name="job_id" value="<?= (int) $j['job_id'] ?>">
                                        <input type="hidden" name="action" value="confirm_payment">
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-4">
                                                <label class="form-label">Amount Paid (PKR) <span
                                                        style="color:#f87171;">*</span></label>
                                                <input class="form-control" type="number" name="amount" step="0.01" min="1"
                                                    value="<?= htmlspecialchars($j['bid_amount'] ?? $j['budget']) ?>" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Payment Method</label>
                                                <select class="form-select" name="method">
                                                    <option>Manual</option>
                                                    <option>Bank Transfer</option>
                                                    <option>JazzCash</option>
                                                    <option>Easypaisa</option>
                                                    <option>Cash</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Note (optional)</label>
                                                <input class="form-control" type="text" name="note" placeholder="Transaction ID etc.">
                                            </div>
                                            <div class="col-md-2">
                                                <button class="btn btn-brand w-100 rounded-pill">Confirm</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>

                        <?php elseif ($status === 'completed'): ?>
                            <div class="alert alert-success mb-0 py-2 text-center">
                                🎉 This job is fully completed and payment recorded.
                            </div>
                            <?php
                            // Check if already reviewed
                            $revStmt = $pdo->prepare("SELECT review_id FROM reviews WHERE job_id=? LIMIT 1");
                            $revStmt->execute([$j['job_id']]);
                            $alreadyReviewed = $revStmt->fetch();
                            ?>
                            <?php if (!$alreadyReviewed): ?>
                                <a class="btn btn-brand rounded-pill w-100 mt-2"
                                    href="<?= BASE_URL ?>/client/review_freelancer.php?job_id=<?= (int) $j['job_id'] ?>">
                                    ⭐ Rate Freelancer
                                </a>
                            <?php else: ?>
                                <a class="btn btn-outline-secondary rounded-pill w-100 mt-2"
                                    href="<?= BASE_URL ?>/client/review_freelancer.php?job_id=<?= (int) $j['job_id'] ?>">
                                    ✅ View Your Review
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>