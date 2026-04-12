<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/notify.php';

requireRole('admin');

$msg = "";
$err = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $jobId = (int) ($_POST['job_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($jobId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
        $err = "Invalid request.";
    } else {
        if ($action === 'approve') {
            $stmt = $pdo->prepare("
        UPDATE job
        SET status='approved',
            is_reported=0,
            reported_reason=NULL,
            reported_by=NULL,
            reported_at=NULL
        WHERE job_id=?
      ");
            $stmt->execute([$jobId]);
            $msg = "Job approved (report cleared if it was reported).";

            // Notify client (SSH05)
            $cStmt = $pdo->prepare("SELECT client_id, title FROM job WHERE job_id=? LIMIT 1");
            $cStmt->execute([$jobId]);
            $cJob = $cStmt->fetch();
            if ($cJob) {
                notify(
                    $pdo,
                    (int) $cJob['client_id'],
                    "✅ Your job \"{$cJob['title']}\" has been approved by admin.",
                    "client/dashboard.php"
                );
            }
        } else {
            $stmt = $pdo->prepare("
        UPDATE job
        SET status='rejected',
            is_reported=0,
            reported_reason=NULL,
            reported_by=NULL,
            reported_at=NULL
        WHERE job_id=?
      ");
            $stmt->execute([$jobId]);
            $msg = "Job rejected.";

            // Notify client (SSH05)
            $cStmt2 = $pdo->prepare("SELECT client_id, title FROM job WHERE job_id=? LIMIT 1");
            $cStmt2->execute([$jobId]);
            $cJob2 = $cStmt2->fetch();
            if ($cJob2) {
                notify(
                    $pdo,
                    (int) $cJob2['client_id'],
                    "❌ Your job \"{$cJob2['title']}\" was rejected by admin.",
                    "client/dashboard.php"
                );
            }
        }
    }
}

$filter = $_GET['status'] ?? 'pending';

$where = "";
$params = [];

if ($filter === 'reported') {
    $where = " WHERE j.is_reported = 1 ";
} elseif ($filter !== 'all' && in_array($filter, ['pending', 'approved', 'rejected'], true)) {
    $where = " WHERE j.status = ? ";
    $params[] = $filter;
}

$stmt = $pdo->prepare("
  SELECT j.job_id, j.title, j.budget, j.deadline, j.status, j.created_at,
         j.is_reported, j.reported_reason, j.reported_at,
         u.name AS client_name, c.category_name,
         ru.name AS reported_by_name
  FROM job j
  JOIN users u ON u.user_id = j.client_id
  JOIN category c ON c.category_id = j.category_id
  LEFT JOIN users ru ON ru.user_id = j.reported_by
  $where
  ORDER BY j.created_at DESC
");
$stmt->execute($params);
$jobs = $stmt->fetchAll();

$title = "Manage Jobs";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Manage Job Postings</h3>
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

    <div class="card card-soft p-4 mb-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Filter</label>
                <select name="status" class="form-select">
                    <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="reported" <?= $filter === 'reported' ? 'selected' : '' ?>>Reported</option>
                    <option value="approved" <?= $filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-brand w-100">Apply</button>
            </div>
        </form>
    </div>

    <div class="card card-soft p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Job</th>
                        <th>Client</th>
                        <th>Category</th>
                        <th>Budget</th>
                        <th>Deadline</th>
                        <th>Status</th>
                        <th>Report Info</th>
                        <th>Actions</th>
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
                                <div class="text-muted2">
                                    <?= htmlspecialchars($j['created_at']) ?>
                                </div>
                            </td>
                            <td>
                                <?= htmlspecialchars($j['client_name']) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($j['category_name']) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($j['budget']) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($j['deadline']) ?>
                            </td>

                            <td>
                                <?php $st = preg_replace('/[^a-z_]/', '', strtolower(trim($j['status']))); ?>
                                <span class="status-badge status-<?= $st ?>">
                                    <?= htmlspecialchars($j['status']) ?>
                                </span>

                                <?php if ((int) $j['is_reported'] === 1): ?>
                                    <span class="status-badge status-reported">reported</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted2">
                                <?php if ((int) $j['is_reported'] === 1): ?>
                                    <div><b>By:</b>
                                        <?= htmlspecialchars($j['reported_by_name'] ?? 'Unknown') ?>
                                    </div>
                                    <div><b>Reason:</b>
                                        <?= htmlspecialchars($j['reported_reason'] ?? '-') ?>
                                    </div>
                                    <div><b>At:</b>
                                        <?= htmlspecialchars($j['reported_at'] ?? '-') ?>
                                    </div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $jStatus = $j['status']; ?>
                                <?php if ($jStatus === 'in_progress'): ?>
                                    <span class="status-badge status-in_progress">⏳ In Progress</span>
                                <?php elseif ($jStatus === 'completed'): ?>
                                    <span class="status-badge status-approved">✅ Completed</span>
                                <?php elseif ($jStatus === 'approved'): ?>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                        <input type="hidden" name="job_id" value="<?= (int) $j['job_id'] ?>">
                                        <button class="btn btn-danger btn-sm rounded-pill" name="action"
                                            value="reject">Reject</button>
                                    </form>
                                <?php elseif ($jStatus === 'rejected'): ?>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                        <input type="hidden" name="job_id" value="<?= (int) $j['job_id'] ?>">
                                        <button class="btn btn-success btn-sm rounded-pill" name="action"
                                            value="approve">Approve</button>
                                    </form>
                                <?php elseif (in_array($jStatus, ['pending', 'reported'])): ?>
                                    <form method="post" class="d-flex gap-2 flex-wrap">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                        <input type="hidden" name="job_id" value="<?= (int) $j['job_id'] ?>">
                                        <button class="btn btn-success btn-sm rounded-pill" name="action"
                                            value="approve">Approve</button>
                                        <button class="btn btn-danger btn-sm rounded-pill" name="action"
                                            value="reject">Reject</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$jobs): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted2 py-4">No jobs found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>