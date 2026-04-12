<?php
// SSH13 + SSH14: Client reviews proposals and hires a freelancer
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/notify.php';

requireRole('client');

$clientId = (int) $_SESSION['user']['id'];
$jobId = (int) ($_GET['job_id'] ?? 0);

if ($jobId <= 0) {
    header("Location: " . BASE_URL . "/client/dashboard.php");
    exit;
}

// Load job — must belong to this client
$stmt = $pdo->prepare("
    SELECT j.job_id, j.title, j.budget, j.deadline, j.status, j.hired_freelancer_id,
           c.category_name
    FROM job j
    JOIN category c ON c.category_id = j.category_id
    WHERE j.job_id = ? AND j.client_id = ?
");
$stmt->execute([$jobId, $clientId]);
$job = $stmt->fetch();

if (!$job) {
    header("Location: " . BASE_URL . "/client/dashboard.php");
    exit;
}

$msg = "";
$err = "";

// SSH14: Handle Hire / Reject action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $proposalId = (int) ($_POST['proposal_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($proposalId <= 0 || !in_array($action, ['hire', 'reject'], true)) {
        $err = "Invalid request.";
    } else {
        // Make sure proposal belongs to this job
        $stmt = $pdo->prepare("SELECT freelancer_id FROM proposals WHERE proposal_id = ? AND job_id = ? LIMIT 1");
        $stmt->execute([$proposalId, $jobId]);
        $proposal = $stmt->fetch();

        if (!$proposal) {
            $err = "Proposal not found.";
        } elseif ($job['status'] !== 'approved') {
            $err = "This job is no longer open for hiring.";
        } else {
            $pdo->beginTransaction();
            try {
                if ($action === 'hire') {
                    // Accept this proposal
                    $pdo->prepare("UPDATE proposals SET status='accepted' WHERE proposal_id=?")
                        ->execute([$proposalId]);

                    // Auto-reject all other proposals for this job (SSH14)
                    $pdo->prepare("UPDATE proposals SET status='rejected' WHERE job_id=? AND proposal_id!=?")
                        ->execute([$jobId, $proposalId]);

                    // Update job: in_progress + set hired freelancer
                    $pdo->prepare("UPDATE job SET status='in_progress', hired_freelancer_id=? WHERE job_id=?")
                        ->execute([$proposal['freelancer_id'], $jobId]);

                    $msg = "🎉 Freelancer hired successfully! Job is now in progress.";

                    // Notify hired freelancer (SSH05)
                    notify(
                        $pdo,
                        (int) $proposal['freelancer_id'],
                        "🎉 You have been hired for the job: {$job['title']}",
                        "freelancer/my_jobs.php"
                    );
                } else {
                    // Reject single proposal
                    $pdo->prepare("UPDATE proposals SET status='rejected' WHERE proposal_id=?")
                        ->execute([$proposalId]);
                    $msg = "Proposal rejected.";

                    // Notify rejected freelancer (SSH05)
                    $rejStmt = $pdo->prepare("SELECT freelancer_id FROM proposals WHERE proposal_id=? LIMIT 1");
                    $rejStmt->execute([$proposalId]);
                    $rejRow = $rejStmt->fetch();
                    if ($rejRow) {
                        notify(
                            $pdo,
                            (int) $rejRow['freelancer_id'],
                            "Your proposal for the job was not accepted.",
                            "freelancer/browse_jobs.php"
                        );
                    }
                }
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $err = "Something went wrong. Please try again.";
            }
        }

        // Reload job after update
        $stmt = $pdo->prepare("SELECT j.job_id, j.title, j.budget, j.deadline, j.status, j.hired_freelancer_id, c.category_name FROM job j JOIN category c ON c.category_id = j.category_id WHERE j.job_id = ? AND j.client_id = ?");
        $stmt->execute([$jobId, $clientId]);
        $job = $stmt->fetch();
    }
}

// SSH13: Load all proposals with freelancer details
$stmt = $pdo->prepare("
    SELECT p.proposal_id, p.bid_amount, p.cover_letter, p.status, p.created_at,
           u.user_id AS freelancer_id, u.name AS freelancer_name, u.email,
           u.hourly_rate, u.portfolio_url, u.profile_image,
           GROUP_CONCAT(s.skill_name ORDER BY s.skill_name SEPARATOR ', ') AS skills
    FROM proposals p
    JOIN users u ON u.user_id = p.freelancer_id
    LEFT JOIN user_skill us ON us.user_id = u.user_id
    LEFT JOIN skills s ON s.skill_id = us.skill_id
    WHERE p.job_id = ?
    GROUP BY p.proposal_id
    ORDER BY
        CASE p.status WHEN 'accepted' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END,
        p.created_at ASC
");
$stmt->execute([$jobId]);
$proposals = $stmt->fetchAll();

$title = "View Proposals";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Proposals for: <span style="color:var(--brand);">
                <?= htmlspecialchars($job['title']) ?>
            </span></h3>
    </div>

    <!-- Job Info Bar -->
    <div class="card card-soft p-3 mb-4">
        <div class="d-flex flex-wrap gap-4 align-items-center">
            <span class="text-muted2">📂
                <?= htmlspecialchars($job['category_name']) ?>
            </span>
            <span class="text-muted2">💰 Budget: <strong style="color:var(--brand);">PKR
                    <?= number_format((float) $job['budget'], 2) ?>
                </strong></span>
            <span class="text-muted2">📅 Deadline:
                <?= htmlspecialchars($job['deadline']) ?>
            </span>
            <span>
                <?php $st = preg_replace('/[^a-z_]/', '', strtolower(trim($job['status']))); ?>
                <span class="status-badge status-<?= $st ?>">
                    <?= htmlspecialchars($job['status']) ?>
                </span>
            </span>
            <span class="text-muted2">👥
                <?= count($proposals) ?> proposal(s)
            </span>
        </div>
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

    <?php if (!$proposals): ?>
        <div class="card card-soft p-4 text-center text-muted2 py-5">
            <div style="font-size:2.5rem;">📭</div>
            <div class="mt-2">No proposals submitted yet. Check back later.</div>
        </div>

    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($proposals as $p):
                $pStatus = $p['status'];
                $imgUrl = $p['profile_image'] ? BASE_URL . '/' . $p['profile_image'] : null;
                $initial = strtoupper(substr($p['freelancer_name'], 0, 1));
                $isAccepted = $pStatus === 'accepted';
                $isRejected = $pStatus === 'rejected';
                ?>
                <div class="col-lg-6">
                    <div class="card card-soft p-4 h-100 d-flex flex-column"
                        style="<?= $isAccepted ? 'border-color:rgba(34,197,94,.4);' : ($isRejected ? 'opacity:.65;' : '') ?>">

                        <!-- Freelancer Header -->
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <?php if ($imgUrl): ?>
                                <img src="<?= htmlspecialchars($imgUrl) ?>" class="profile-avatar" alt="Profile">
                            <?php else: ?>
                                <div class="profile-avatar-placeholder">
                                    <?= htmlspecialchars($initial) ?>
                                </div>
                            <?php endif; ?>
                            <div class="flex-grow-1">
                                <div class="fw-bold">
                                    <?= htmlspecialchars($p['freelancer_name']) ?>
                                </div>
                                <div class="text-muted2" style="font-size:.84rem;">
                                    <?= htmlspecialchars($p['email']) ?>
                                </div>
                                <?php if ($p['hourly_rate']): ?>
                                    <div class="text-muted2" style="font-size:.84rem;">⏱ PKR
                                        <?= number_format((float) $p['hourly_rate'], 0) ?>/hr
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <span
                                    class="status-badge status-<?= $isAccepted ? 'approved' : ($isRejected ? 'rejected' : 'pending') ?>">
                                    <?= htmlspecialchars($pStatus) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Skills -->
                        <?php if ($p['skills']): ?>
                            <div class="text-muted2 mb-2" style="font-size:.84rem;">🛠
                                <?= htmlspecialchars($p['skills']) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Portfolio -->
                        <?php if ($p['portfolio_url']): ?>
                            <div class="mb-2" style="font-size:.84rem;">
                                🔗 <a href="<?= htmlspecialchars($p['portfolio_url']) ?>" target="_blank"
                                    style="color:var(--brand);">View Portfolio</a>
                            </div>
                        <?php endif; ?>

                        <!-- Bid Amount -->
                        <div class="mb-3 p-2 rounded"
                            style="background:rgba(34,211,238,.07);border:1px solid rgba(34,211,238,.18);">
                            💰 Bid: <strong style="color:var(--brand);font-size:1.1rem;">PKR
                                <?= number_format((float) $p['bid_amount'], 2) ?>
                            </strong>
                            <?php
                            $diff = (float) $p['bid_amount'] - (float) $job['budget'];
                            if ($diff < 0): ?>
                                <span class="text-muted2" style="font-size:.82rem;"> (PKR
                                    <?= number_format(abs($diff), 2) ?> under budget ✅)
                                </span>
                            <?php elseif ($diff > 0): ?>
                                <span class="text-muted2" style="font-size:.82rem;"> (PKR
                                    <?= number_format($diff, 2) ?> over budget)
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Cover Letter -->
                        <div class="mb-3" style="font-size:.92rem;line-height:1.65;flex:1;">
                            <div class="text-muted2 mb-1" style="font-size:.82rem;">Cover Letter:</div>
                            <?= nl2br(htmlspecialchars($p['cover_letter'])) ?>
                        </div>

                        <div class="text-muted2 mb-3" style="font-size:.78rem;">
                            Submitted:
                            <?= date('d M Y, h:i A', strtotime($p['created_at'])) ?>
                        </div>

                        <!-- Action Buttons (SSH14) -->
                        <?php if ($job['status'] === 'approved' && $pStatus === 'pending'): ?>
                            <form method="post" class="d-flex gap-2 mt-auto">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="proposal_id" value="<?= (int) $p['proposal_id'] ?>">
                                <button class="btn btn-brand rounded-pill px-4" name="action" value="hire"
                                    onclick="return confirm('Hire <?= htmlspecialchars(addslashes($p['freelancer_name'])) ?> for PKR <?= number_format((float) $p['bid_amount'], 2) ?>?\n\nAll other proposals will be automatically rejected.')">
                                    ✅ Hire
                                </button>
                                <button class="btn btn-outline-danger rounded-pill px-3" name="action" value="reject"
                                    onclick="return confirm('Reject this proposal?')">
                                    Reject
                                </button>
                            </form>

                        <?php elseif ($isAccepted): ?>
                            <div class="alert alert-success mb-0 py-2 mt-auto">✅ You hired this freelancer!</div>

                        <?php elseif ($isRejected): ?>
                            <div class="text-muted2 mt-auto" style="font-size:.85rem;">❌ Proposal rejected.</div>

                        <?php elseif ($job['status'] === 'in_progress'): ?>
                            <div class="text-muted2 mt-auto" style="font-size:.85rem;">⏳ Job already in progress.</div>
                        <?php endif; ?>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>