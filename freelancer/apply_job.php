<?php
// SSH09 + SSH10: Freelancer applies for a job and submits proposal
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/notify.php';

requireRole('freelancer');

$freelancerId = (int) $_SESSION['user']['id'];
$jobId = (int) ($_GET['job_id'] ?? 0);

if ($jobId <= 0) {
    header("Location: " . BASE_URL . "/freelancer/browse_jobs.php");
    exit;
}

// Load job — must be approved and not already in progress/completed
$stmt = $pdo->prepare("
    SELECT j.job_id, j.title, j.description, j.budget, j.deadline, j.status,
           c.category_name, u.name AS client_name
    FROM job j
    JOIN category c ON c.category_id = j.category_id
    JOIN users u ON u.user_id = j.client_id
    WHERE j.job_id = ? AND j.status = 'approved'
");
$stmt->execute([$jobId]);
$job = $stmt->fetch();

if (!$job) {
    header("Location: " . BASE_URL . "/freelancer/browse_jobs.php");
    exit;
}

// Check if already applied (SSH09 alternative path)
$stmt = $pdo->prepare("SELECT proposal_id, bid_amount, cover_letter, status FROM proposals WHERE job_id = ? AND freelancer_id = ? LIMIT 1");
$stmt->execute([$jobId, $freelancerId]);
$existingProposal = $stmt->fetch();

$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existingProposal) {
    csrf_verify();

    $bidAmount = trim($_POST['bid_amount'] ?? '');
    $coverLetter = trim($_POST['cover_letter'] ?? '');

    // Validation
    if ($bidAmount === '' || !is_numeric($bidAmount) || (float) $bidAmount <= 0)
        $errors[] = "Please enter a valid bid amount greater than 0.";
    if (strlen($coverLetter) < 20)
        $errors[] = "Cover letter must be at least 20 characters long.";

    if (!$errors) {
        $stmt = $pdo->prepare("
            INSERT INTO proposals (job_id, freelancer_id, bid_amount, cover_letter, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$jobId, $freelancerId, (float) $bidAmount, $coverLetter]);
        $success = "Your proposal has been submitted successfully!";

        // Notify client (SSH05)
        $freelancerName = $_SESSION['user']['name'];
        notify(
            $pdo,
            (int) $job['client_id'],
            "{$freelancerName} submitted a proposal on your job: {$job['title']}",
            "client/view_proposals.php?job_id={$jobId}"
        );

        // Reload existing proposal for display
        $stmt = $pdo->prepare("SELECT proposal_id, bid_amount, cover_letter, status FROM proposals WHERE job_id = ? AND freelancer_id = ? LIMIT 1");
        $stmt->execute([$jobId, $freelancerId]);
        $existingProposal = $stmt->fetch();
    }
}

$title = "Apply for Job";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5 page-narrow">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Apply for Job</h3>
    </div>

    <!-- Job Summary (SSH09: freelancer views job before applying) -->
    <div class="card card-soft p-4 mb-4">
        <div class="fw-bold mb-1" style="font-size:1.1rem;">
            <?= htmlspecialchars($job['title']) ?>
        </div>
        <div class="text-muted2 mb-3" style="font-size:.88rem;">
            📂
            <?= htmlspecialchars($job['category_name']) ?> &bull;
            👤 Client:
            <?= htmlspecialchars($job['client_name']) ?>
        </div>
        <div class="d-flex gap-4 flex-wrap mb-3">
            <span class="text-muted2">💰 Budget: <strong style="color:var(--brand);">PKR
                    <?= number_format((float) $job['budget'], 2) ?>
                </strong></span>
            <span class="text-muted2">📅 Deadline: <strong>
                    <?= htmlspecialchars($job['deadline']) ?>
                </strong></span>
        </div>
        <div class="text-muted2" style="font-size:.92rem; line-height:1.6;">
            <?= nl2br(htmlspecialchars($job['description'])) ?>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            🎉
            <?= htmlspecialchars($success) ?>
            <a href="<?= BASE_URL ?>/freelancer/browse_jobs.php">Browse more jobs →</a>
        </div>
    <?php endif; ?>

    <?php if ($existingProposal && !$success): ?>
        <!-- Already applied — show submitted proposal -->
        <div class="card card-soft p-4">
            <h5 class="fw-bold mb-3">✅ Your Submitted Proposal</h5>
            <div class="mb-3 p-3 rounded" style="background:rgba(34,211,238,.07);border:1px solid rgba(34,211,238,.18);">
                💰 Your Bid: <strong style="color:var(--brand); font-size:1.1rem;">PKR
                    <?= number_format((float) $existingProposal['bid_amount'], 2) ?>
                </strong>
            </div>
            <div class="mb-3">
                <div class="text-muted2 mb-1" style="font-size:.85rem;">Cover Letter:</div>
                <div style="line-height:1.6;">
                    <?= nl2br(htmlspecialchars($existingProposal['cover_letter'])) ?>
                </div>
            </div>
            <div>
                <?php $ps = $existingProposal['status']; ?>
                Status: <span
                    class="status-badge status-<?= $ps === 'accepted' ? 'approved' : ($ps === 'rejected' ? 'rejected' : 'pending') ?>">
                    <?= htmlspecialchars($ps) ?>
                </span>
            </div>
        </div>

    <?php elseif (!$success): ?>
        <!-- Proposal form (SSH10: submit proposal) -->
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $e)
                        echo "<li>" . htmlspecialchars($e) . "</li>"; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="card card-soft p-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

            <h5 class="fw-bold mb-3">Submit Your Proposal</h5>

            <div class="mb-3">
                <label class="form-label fw-bold">Your Bid Amount (PKR) <span style="color:#f87171;">*</span></label>
                <input class="form-control" type="number" name="bid_amount" min="1" step="0.01"
                    placeholder="Enter your proposed amount" value="<?= htmlspecialchars($_POST['bid_amount'] ?? '') ?>"
                    required>
                <div class="text-muted2 mt-1" style="font-size:.83rem;">
                    Client's budget is PKR
                    <?= number_format((float) $job['budget'], 2) ?>. You can bid lower or higher.
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">Cover Letter <span style="color:#f87171;">*</span></label>
                <textarea class="form-control" name="cover_letter" rows="7"
                    placeholder="Introduce yourself and explain why you are the best fit for this job. Mention your relevant skills, past experience and how you plan to approach the work..."
                    required><?= htmlspecialchars($_POST['cover_letter'] ?? '') ?></textarea>
                <div class="text-muted2 mt-1" style="font-size:.83rem;">Minimum 20 characters.</div>
            </div>

            <button class="btn btn-brand w-100 py-2">📨 Submit Proposal</button>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>