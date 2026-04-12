<?php
// client/invite_freelancer.php — SSH18: Client invites freelancer to a job
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/notify.php';

requireRole('client');

$clientId = (int) $_SESSION['user']['id'];
$freelancerId = (int) ($_GET['freelancer_id'] ?? 0);

if ($freelancerId <= 0) {
    header("Location: " . BASE_URL . "/client/browse_freelancers.php");
    exit;
}

// Load freelancer
$stmt = $pdo->prepare("
    SELECT user_id, name, email, hourly_rate, profile_image
    FROM users WHERE user_id=? AND role='freelancer' AND status='active' LIMIT 1
");
$stmt->execute([$freelancerId]);
$freelancer = $stmt->fetch();

if (!$freelancer) {
    header("Location: " . BASE_URL . "/client/browse_freelancers.php");
    exit;
}

// Load client's approved jobs that are not yet in_progress/completed
$stmt = $pdo->prepare("
    SELECT j.job_id, j.title, j.budget, j.deadline
    FROM job j
    WHERE j.client_id = ?
      AND j.status = 'approved'
      AND j.hired_freelancer_id IS NULL
    ORDER BY j.created_at DESC
");
$stmt->execute([$clientId]);
$myJobs = $stmt->fetchAll();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $jobId = (int) ($_POST['job_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    if ($jobId <= 0)
        $errors[] = "Please select a job.";

    if (!$errors) {
        // Verify job belongs to client and is approved
        $stmt = $pdo->prepare("SELECT job_id, title FROM job WHERE job_id=? AND client_id=? AND status='approved' LIMIT 1");
        $stmt->execute([$jobId, $clientId]);
        $job = $stmt->fetch();

        if (!$job) {
            $errors[] = "Invalid job selected.";
        }
    }

    if (!$errors) {
        // Check not already invited
        $stmt = $pdo->prepare("
            SELECT notif_id FROM notifications
            WHERE user_id=? AND link LIKE ? AND is_read=0
            LIMIT 1
        ");
        $stmt->execute([$freelancerId, "%job_id=" . $jobId . "%"]);
        $alreadyInvited = $stmt->fetch();

        if ($alreadyInvited) {
            $errors[] = "You have already invited this freelancer to this job.";
        }
    }

    if (!$errors) {
        $clientName = $_SESSION['user']['name'];
        $notifMsg = "📨 {$clientName} invited you to apply for: \"{$job['title']}\"";
        if ($message !== '') {
            $notifMsg .= " — \"{$message}\"";
        }

        notify(
            $pdo,
            $freelancerId,
            $notifMsg,
            "freelancer/view_job.php?job_id={$jobId}"
        );

        $success = "Invitation sent to " . htmlspecialchars($freelancer['name']) . " successfully!";
    }
}

function initials($name)
{
    $parts = preg_split('/\s+/', trim($name));
    return strtoupper(substr($parts[0] ?? 'U', 0, 1)) . strtoupper(substr($parts[1] ?? '', 0, 1));
}

$imgUrl = ($freelancer['profile_image'] ?? null) ? BASE_URL . '/' . $freelancer['profile_image'] : null;
$title = "Invite Freelancer";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5 page-narrow">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0 fw-bold">📨 Invite to Job</h3>
    </div>

    <!-- Freelancer Card -->
    <div class="card card-soft p-4 mb-4">
        <div class="d-flex align-items-center gap-3">
            <?php if ($imgUrl): ?>
                <img src="<?= htmlspecialchars($imgUrl) ?>" class="profile-avatar" alt="">
            <?php else: ?>
                <div class="profile-avatar-placeholder"><?= htmlspecialchars(initials($freelancer['name'])) ?></div>
            <?php endif; ?>
            <div>
                <div class="fw-bold" style="font-size:1.05rem;"><?= htmlspecialchars($freelancer['name']) ?></div>
                <div class="text-muted2" style="font-size:.85rem;"><?= htmlspecialchars($freelancer['email']) ?></div>
                <?php if ($freelancer['hourly_rate']): ?>
                    <div style="color:var(--brand);font-size:.88rem;font-weight:700;">
                        PKR <?= number_format((float) $freelancer['hourly_rate'], 0) ?>/hr
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger mb-3">
            <ul class="mb-0"><?php foreach ($errors as $e)
                echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success mb-3">✅ <?= htmlspecialchars($success) ?></div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/client/browse_freelancers.php" class="btn btn-outline-primary rounded-pill">
                Browse More Freelancers
            </a>
            <a href="<?= BASE_URL ?>/client/dashboard.php" class="btn btn-brand rounded-pill">
                Go to Dashboard
            </a>
        </div>

    <?php elseif (!$myJobs): ?>
        <div class="card card-soft p-4 text-center text-muted2 py-5">
            <div style="font-size:2.5rem;">📋</div>
            <div class="mt-2 fw-bold">No approved jobs available</div>
            <div class="text-muted2 mt-1" style="font-size:.87rem;">
                You need at least one approved job to invite a freelancer.
            </div>
            <a href="<?= BASE_URL ?>/client/post_job.php" class="btn btn-brand rounded-pill px-4 mt-3">
                Post a Job →
            </a>
        </div>

    <?php else: ?>
        <form method="post" class="card card-soft p-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

            <div class="mb-4">
                <label class="form-label fw-bold">Select Job to Invite For <span style="color:#f87171;">*</span></label>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($myJobs as $j): ?>
                        <label
                            class="job-select-card <?= (int) ($_POST['job_id'] ?? 0) === (int) $j['job_id'] ? 'selected' : '' ?>">
                            <input type="radio" name="job_id" value="<?= (int) $j['job_id'] ?>"
                                <?= (int) ($_POST['job_id'] ?? 0) === (int) $j['job_id'] ? 'checked' : '' ?> class="d-none">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold" style="font-size:.95rem;"><?= htmlspecialchars($j['title']) ?></div>
                                    <div class="text-muted2" style="font-size:.8rem;">
                                        📅 Deadline: <?= htmlspecialchars($j['deadline']) ?>
                                    </div>
                                </div>
                                <div style="color:var(--brand);font-weight:700;">
                                    PKR <?= number_format((float) $j['budget'], 0) ?>
                                </div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">Personal Message <span class="text-muted2">(optional)</span></label>
                <textarea class="form-control" name="message" rows="3"
                    placeholder="e.g. Hi, I think your skills are a great match for my project..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                <div class="text-muted2 mt-1" style="font-size:.81rem;">
                    This message will appear in the freelancer's notification.
                </div>
            </div>

            <button class="btn btn-brand w-100 py-2">📨 Send Invitation</button>
        </form>
    <?php endif; ?>

</div>



<script>
    document.querySelectorAll('.job-select-card').forEach(card => {
        card.addEventListener('click', function () {
            document.querySelectorAll('.job-select-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            this.querySelector('input[type=radio]').checked = true;
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>