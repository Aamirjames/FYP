<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('client');

$clientId = (int) $_SESSION['user']['id'];

$filter = $_GET['status'] ?? 'all';
$msg = $_GET['msg'] ?? '';
$where = "";
$params = [$clientId];

if (in_array($filter, ['pending', 'approved', 'rejected'], true)) {
    $where = " AND j.status = ? ";
    $params[] = $filter;
}

$stmt = $pdo->prepare("
    SELECT j.job_id, j.title, j.budget, j.deadline, j.status, j.created_at,
           j.is_reported, c.category_name
    FROM job j
    JOIN category c ON c.category_id = j.category_id
    WHERE j.client_id = ? $where
    ORDER BY j.created_at DESC
");
$stmt->execute($params);
$jobs = $stmt->fetchAll();

$title = "My Jobs";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0 fw-bold">My Jobs</h3>
        <div class="d-flex gap-2">
            <a class="btn btn-brand rounded-pill px-4" href="<?= BASE_URL ?>/client/post_job.php">+ Post a Job</a>
        </div>
    </div>

    <!-- Filter -->
    <div class="card card-soft p-3 mb-4">
        <form method="get" class="d-flex gap-2 align-items-end flex-wrap">
            <div>
                <label class="form-label mb-1">Filter by status</label>
                <select name="status" class="form-select">
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            <button class="btn btn-brand">Apply</button>
        </form>
    </div>

    <?php if ($jobs): ?>
        <p class="text-muted2 mb-3">
            <?= count($jobs) ?> job
            <?= count($jobs) !== 1 ? 's' : '' ?> found
        </p>
    <?php endif; ?>

    <div class="card card-soft p-4">
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
                        <th>Posted</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobs as $j): ?>
                        <tr>
                            <td>
                                <?= (int) $j['job_id'] ?>
                            </td>
                            <td>
                                <div class="fw-bold">
                                    <?= htmlspecialchars($j['title']) ?>
                                </div>
                                <?php if ((int) $j['is_reported']): ?>
                                    <span class="status-badge status-reported" style="font-size:.75rem;">reported</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted2">
                                <?= htmlspecialchars($j['category_name']) ?>
                            </td>
                            <td style="color:var(--brand); font-weight:700;">
                                PKR
                                <?= number_format((float) $j['budget'], 0) ?>
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
                            <td class="text-muted2">
                                <?= htmlspecialchars(date('d M Y', strtotime($j['created_at']))) ?>
                            </td>
                            <td>
                                <?php if ($j['status'] === 'pending'): ?>
                                    <a class="btn btn-outline-primary btn-sm rounded-pill"
                                        href="<?= BASE_URL ?>/client/edit_job.php?job_id=<?= (int) $j['job_id'] ?>">
                                        ✏️ Edit
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted2" style="font-size:.82rem;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$jobs): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted2 py-4">
                                No jobs found.
                                <a href="<?= BASE_URL ?>/client/post_job.php">Post your first job</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>