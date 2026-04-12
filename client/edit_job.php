<?php
// client/edit_job.php — Client edits or deletes a pending job
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';

requireRole('client');

$clientId = (int) $_SESSION['user']['id'];
$jobId = (int) ($_GET['job_id'] ?? 0);

if ($jobId <= 0) {
    header("Location: " . BASE_URL . "/client/my_jobs.php");
    exit;
}

// Load job — must belong to client AND be pending
$stmt = $pdo->prepare("
    SELECT j.job_id, j.title, j.description, j.budget, j.deadline, j.status, c.category_id
    FROM job j
    JOIN category c ON c.category_id = j.category_id
    WHERE j.job_id = ? AND j.client_id = ? AND j.status = 'pending'
    LIMIT 1
");
$stmt->execute([$jobId, $clientId]);
$job = $stmt->fetch();

if (!$job) {
    header("Location: " . BASE_URL . "/client/my_jobs.php");
    exit;
}

$categories = $pdo->query("SELECT category_id, category_name FROM category ORDER BY category_name")->fetchAll();

$errors = [];
$success = '';

// ── DELETE ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_verify();
    $pdo->prepare("DELETE FROM job WHERE job_id = ? AND client_id = ? AND status = 'pending'")
        ->execute([$jobId, $clientId]);
    header("Location: " . BASE_URL . "/client/my_jobs.php?msg=deleted");
    exit;
}

// ── EDIT ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    csrf_verify();

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $budget = trim($_POST['budget'] ?? '');
    $deadline = trim($_POST['deadline'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0);

    if ($title === '')
        $errors[] = "Title is required.";
    if ($description === '')
        $errors[] = "Description is required.";
    if (!is_numeric($budget) || (float) $budget <= 0)
        $errors[] = "Enter a valid budget.";
    if ($deadline === '' || strtotime($deadline) < time())
        $errors[] = "Deadline must be a future date.";
    if ($categoryId <= 0)
        $errors[] = "Select a category.";

    if (!$errors) {
        $pdo->prepare("
            UPDATE job SET title=?, description=?, budget=?, deadline=?, category_id=?
            WHERE job_id=? AND client_id=? AND status='pending'
        ")->execute([$title, $description, (float) $budget, $deadline, $categoryId, $jobId, $clientId]);
        $success = "Job updated successfully!";
        // Reload job
        $stmt = $pdo->prepare("SELECT j.job_id, j.title, j.description, j.budget, j.deadline, j.status, c.category_id FROM job j JOIN category c ON c.category_id = j.category_id WHERE j.job_id=? LIMIT 1");
        $stmt->execute([$jobId]);
        $job = $stmt->fetch();
    }
}

$title_page = "Edit Job";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5 page-narrow">

    <h3 class="fw-bold mb-1">Edit Job</h3>
    <p class="text-muted2 mb-4" style="font-size:.87rem;">
        ⚠️ You can only edit or delete jobs that are still <strong>pending admin approval</strong>.
    </p>

    <?php if ($errors): ?>
        <div class="alert alert-danger mb-3">
            <ul class="mb-0">
                <?php foreach ($errors as $e)
                    echo "<li>" . htmlspecialchars($e) . "</li>"; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success mb-3">✅
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- Edit Form -->
    <form method="post" class="card card-soft p-4 mb-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="edit">

        <div class="mb-3">
            <label class="form-label fw-bold">Job Title <span style="color:#f87171;">*</span></label>
            <input class="form-control" type="text" name="title" required
                value="<?= htmlspecialchars($_POST['title'] ?? $job['title']) ?>">
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Category <span style="color:#f87171;">*</span></label>
            <select class="form-select" name="category_id" required>
                <option value="">Select category</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['category_id'] ?>" <?= ((int) ($_POST['category_id'] ?? $job['category_id']) === (int) $c['category_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Description <span style="color:#f87171;">*</span></label>
            <textarea class="form-control" name="description" rows="5"
                required><?= htmlspecialchars($_POST['description'] ?? $job['description']) ?></textarea>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label fw-bold">Budget (PKR) <span style="color:#f87171;">*</span></label>
                <input class="form-control" type="number" name="budget" min="1" step="0.01" required
                    value="<?= htmlspecialchars($_POST['budget'] ?? $job['budget']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Deadline <span style="color:#f87171;">*</span></label>
                <input class="form-control" type="date" name="deadline" required
                    min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                    value="<?= htmlspecialchars($_POST['deadline'] ?? $job['deadline']) ?>">
            </div>
        </div>

        <button class="btn btn-brand w-100 py-2">Save Changes</button>
    </form>

    <!-- Delete -->
    <div class="card card-soft p-4" style="border-color:rgba(248,113,113,.3);">
        <h6 class="fw-bold mb-1" style="color:#f87171;">🗑️ Delete This Job</h6>
        <p class="text-muted2 mb-3" style="font-size:.85rem;">
            This will permanently delete the job. This action cannot be undone.
        </p>
        <form method="post"
            onsubmit="return confirm('Are you sure you want to delete this job? This cannot be undone.')">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <button class="btn btn-danger rounded-pill px-4">Delete Job</button>
        </form>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>