<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

requireRole('client');

$clientId = (int) $_SESSION['user']['id'];
$categories = $pdo->query("SELECT category_id, category_name FROM category ORDER BY category_name")->fetchAll();

$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $titleTxt = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $catId = (int) ($_POST['category_id'] ?? 0);
    $budget = (float) ($_POST['budget'] ?? 0);
    $deadline = $_POST['deadline'] ?? '';

    if ($titleTxt === '' || $desc === '' || $catId <= 0 || $deadline === '')
        $errors[] = "All fields are required.";
    if ($budget <= 0)
        $errors[] = "Budget must be greater than 0.";

    $d = DateTime::createFromFormat('Y-m-d', $deadline);
    if (!$d || $d->format('Y-m-d') !== $deadline) {
        $errors[] = "Invalid deadline date.";
    } elseif ($d < new DateTime('today')) {
        $errors[] = "Deadline cannot be in the past.";
    }

    if (!$errors) {
        $stmt = $pdo->prepare("
            INSERT INTO job(title, description, budget, deadline, status, client_id, category_id)
            VALUES (?, ?, ?, ?, 'pending', ?, ?)
        ");
        $stmt->execute([$titleTxt, $desc, $budget, $deadline, $clientId, $catId]);
        $success = "Job posted successfully! Waiting for admin approval.";
        $_POST = [];
    }
}

$title = "Post Job";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5 page-narrow">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0 fw-bold">Post a Job</h3>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e)
                    echo "<li>" . htmlspecialchars($e) . "</li>"; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="card card-soft p-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

        <div class="mb-3">
            <label class="form-label">Job Title</label>
            <input class="form-control" name="title" placeholder="e.g. Need a logo designer" required
                value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Job Description</label>
            <textarea class="form-control" name="description" rows="5" placeholder="Describe the job in detail..."
                required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Category</label>
            <select class="form-select" name="category_id" required>
                <option value="">Select category</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['category_id'] ?>" <?= ((int) ($_POST['category_id'] ?? 0) === (int) $c['category_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Budget (PKR / USD)</label>
            <input class="form-control" name="budget" type="number" min="1" step="0.01" placeholder="e.g. 5000" required
                value="<?= htmlspecialchars($_POST['budget'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Deadline</label>
            <input class="form-control" name="deadline" type="date" min="<?= date('Y-m-d') ?>" required
                value="<?= htmlspecialchars($_POST['deadline'] ?? '') ?>">
            <div class="text-muted2 mt-1" style="font-size:.85rem;">Deadline must be today or a future date.</div>
        </div>

        <button class="btn btn-brand w-100 py-2">Submit Job</button>
        <div class="text-muted2 mt-3" style="font-size:.85rem;">
            Your job will be visible to freelancers after admin approval.
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>