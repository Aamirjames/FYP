<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

requireRole('freelancer');

$freelancerId = (int) $_SESSION['user']['id'];
$jobId = (int) ($_GET['job_id'] ?? 0);

$stmt = $pdo->prepare("
  SELECT job_id, title, is_reported
  FROM job
  WHERE job_id = ? AND status = 'approved'
  LIMIT 1
");
$stmt->execute([$jobId]);
$job = $stmt->fetch();

if (!$job) {
    http_response_code(404);
    exit("Job not found or not approved.");
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $reason = trim($_POST['reason'] ?? '');
    if ($reason === '')
        $errors[] = "Reason is required.";
    if (strlen($reason) > 255)
        $errors[] = "Reason must be 255 characters or less.";

    if (!$errors) {
        $stmt = $pdo->prepare("
      UPDATE job
      SET is_reported=1,
          reported_reason=?,
          reported_by=?,
          reported_at=NOW()
      WHERE job_id=? AND is_reported=0
    ");
        $stmt->execute([$reason, $freelancerId, $jobId]);

        if ($stmt->rowCount() === 0) {
            $errors[] = "This job is already reported.";
        } else {
            header("Location: " . BASE_URL . "/freelancer/browse_jobs.php?msg=reported");
            exit;
        }
    }
}

$title = "Report Job";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5 page-narrow">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Report Job</h3>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e)
                    echo "<li>" . htmlspecialchars($e) . "</li>"; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card card-soft p-4 mb-3">
        <div class="fw-bold">
            <?= htmlspecialchars($job['title']) ?>
        </div>
    </div>

    <form method="post" class="card card-soft p-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

        <div class="mb-3">
            <label class="form-label">Reason</label>
            <textarea class="form-control" name="reason" rows="4"
                required><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
            <div class="text-muted2 mt-2">Example: scam, spam, inappropriate content, fake job, etc.</div>
        </div>

        <button class="btn btn-danger w-100 py-2">Submit Report</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>