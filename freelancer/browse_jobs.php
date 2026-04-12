<?php
// Freelancer browses available jobs
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('freelancer');

$freelancerId = (int) $_SESSION['user']['id'];
$msg = $_GET['msg'] ?? '';

$categories = $pdo->query("SELECT category_id, category_name FROM category ORDER BY category_name")->fetchAll();

// Track which jobs this freelancer applied
$stmt = $pdo->prepare("SELECT job_id FROM proposals WHERE freelancer_id = ?");
$stmt->execute([$freelancerId]);
$appliedJobIds = array_column($stmt->fetchAll(), 'job_id');

// Filters
$categoryId = (int) ($_GET['category_id'] ?? 0);
$minBudget = trim($_GET['min_budget'] ?? '');
$maxBudget = trim($_GET['max_budget'] ?? '');
$deadlineOn = trim($_GET['deadline_on'] ?? '');

$sql = "
  SELECT j.job_id, j.title, j.description, j.budget, j.deadline, j.created_at,
         j.is_reported, c.category_name, u.name AS client_name
  FROM job j
  JOIN category c ON c.category_id = j.category_id
  JOIN users u ON u.user_id = j.client_id
  WHERE j.status = 'approved'
";
$params = [];

if ($categoryId > 0) {
    $sql .= " AND j.category_id = ? ";
    $params[] = $categoryId;
}
if ($minBudget !== '' && is_numeric($minBudget)) {
    $sql .= " AND j.budget >= ? ";
    $params[] = (float) $minBudget;
}
if ($maxBudget !== '' && is_numeric($maxBudget)) {
    $sql .= " AND j.budget <= ? ";
    $params[] = (float) $maxBudget;
}
if ($deadlineOn !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $deadlineOn);
    if ($d && $d->format('Y-m-d') === $deadlineOn) {
        $sql .= " AND j.deadline <= ? ";
        $params[] = $deadlineOn;
    }
}

$sql .= " ORDER BY j.created_at DESC";

// ── Pagination ───────────────────────────────────────────────
$perPage = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));

// Count total
$countSql = "SELECT COUNT(*) FROM job j JOIN category c ON c.category_id=j.category_id JOIN users u ON u.user_id=j.client_id WHERE j.status='approved'";
$countParams = [];
if ($categoryId > 0) {
    $countSql .= " AND j.category_id=?";
    $countParams[] = $categoryId;
}
if ($minBudget !== '' && is_numeric($minBudget)) {
    $countSql .= " AND j.budget>=?";
    $countParams[] = (float) $minBudget;
}
if ($maxBudget !== '' && is_numeric($maxBudget)) {
    $countSql .= " AND j.budget<=?";
    $countParams[] = (float) $maxBudget;
}
if ($deadlineOn !== '') {
    $d2 = DateTime::createFromFormat('Y-m-d', $deadlineOn);
    if ($d2 && $d2->format('Y-m-d') === $deadlineOn) {
        $countSql .= " AND j.deadline<=?";
        $countParams[] = $deadlineOn;
    }
}
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$totalJobs = (int) $countStmt->fetchColumn();
$totalPages = (int) ceil($totalJobs / $perPage);
$page = min($page, max(1, $totalPages));
$offset = ($page - 1) * $perPage;

$sql .= " LIMIT $perPage OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

$title = "Browse Jobs";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Browse Jobs</h3>
    </div>

    <?php if ($msg === 'reported'): ?>
        <div class="alert alert-success">Job reported successfully. Admin will review it.</div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card card-soft p-4 mb-4">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-3">
                <label class="form-label">Category</label>
                <select class="form-select" name="category_id">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['category_id'] ?>" <?= $categoryId === (int) $c['category_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Min Budget</label>
                <input class="form-control" name="min_budget" type="number" step="0.01"
                    value="<?= htmlspecialchars($minBudget) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Max Budget</label>
                <input class="form-control" name="max_budget" type="number" step="0.01"
                    value="<?= htmlspecialchars($maxBudget) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Deadline on/before</label>
                <input class="form-control" name="deadline_on" type="date" value="<?= htmlspecialchars($deadlineOn) ?>">
            </div>
            <div class="col-md-2">
                <button class="btn btn-brand w-100">Filter</button>
            </div>
        </form>
    </div>

    <p class="text-muted2 mb-3">
        <?= $totalJobs ?> job(s) found.
    </p>

    <!-- Job Cards -->
    <div class="row g-3">
        <?php foreach ($jobs as $j):
            $reported = (int) $j['is_reported'] === 1;
            $alreadyApplied = in_array((int) $j['job_id'], $appliedJobIds, true);
            ?>
            <div class="col-md-6">
                <div class="card card-soft p-4 h-100 d-flex flex-column">

                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div class="fw-bold" style="font-size:1.05rem;">
                            <?= htmlspecialchars($j['title']) ?>
                        </div>
                        <div style="color:var(--brand);font-weight:700;white-space:nowrap;margin-left:8px;">
                            PKR
                            <?= number_format((float) $j['budget'], 2) ?>
                        </div>
                    </div>

                    <div class="text-muted2 mb-2" style="font-size:.87rem;">
                        📂
                        <?= htmlspecialchars($j['category_name']) ?>
                        &bull; 👤
                        <?= htmlspecialchars($j['client_name']) ?>
                    </div>

                    <?php if ($reported): ?>
                        <span class="badge text-bg-warning mb-2">⚠ Reported</span>
                    <?php endif; ?>

                    <div class="text-muted2 mb-3" style="font-size:.91rem;line-height:1.6;flex:1;">
                        <?= nl2br(htmlspecialchars(
                            mb_strlen($j['description']) > 140
                            ? mb_substr($j['description'], 0, 140) . '…'
                            : $j['description']
                        )) ?>
                    </div>

                    <div class="text-muted2 mb-3" style="font-size:.84rem;">
                        📅 Deadline:
                        <?= htmlspecialchars($j['deadline']) ?>
                    </div>

                    <!-- Action buttons -->
                    <div class="d-flex gap-2 flex-wrap mt-auto">
                        <?php if ($alreadyApplied): ?>
                            <span class="btn btn-outline-secondary btn-sm rounded-pill"
                                style="cursor:default;pointer-events:none;">
                                ✅ Applied
                            </span>
                        <?php else: ?>
                            <a class="btn btn-brand btn-sm rounded-pill"
                                href="<?= BASE_URL ?>/freelancer/apply_job.php?job_id=<?= (int) $j['job_id'] ?>">
                                Apply →
                            </a>
                        <?php endif; ?>

                        <?php if (!$reported): ?>
                            <a class="btn btn-outline-danger btn-sm rounded-pill"
                                href="<?= BASE_URL ?>/freelancer/report_job.php?job_id=<?= (int) $j['job_id'] ?>">
                                Report
                            </a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary btn-sm rounded-pill" disabled>Already Reported</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (!$jobs): ?>
            <div class="col-12">
                <div class="card card-soft p-4 text-center text-muted2 py-5">
                    <div style="font-size:2rem;">🔍</div>
                    <div class="mt-2">No approved jobs found for selected filters.</div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-center mt-4">
            <nav>
                <ul class="pagination pagination-custom">
                    <?php
                    $queryParams = $_GET;
                    // Prev
                    if ($page > 1):
                        $queryParams['page'] = $page - 1;
                        ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query($queryParams) ?>">← Prev</a>
                        </li>
                    <?php endif;
                    // Pages
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    for ($i = $start; $i <= $end; $i++):
                        $queryParams['page'] = $i;
                        ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query($queryParams) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor;
                    // Next
                    if ($page < $totalPages):
                        $queryParams['page'] = $page + 1;
                        ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query($queryParams) ?>">Next →</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <div class="text-center text-muted2 mt-2" style="font-size:.82rem;">
            Showing
            <?= (($page - 1) * $perPage) + 1 ?>–
            <?= min($page * $perPage, $totalJobs) ?> of
            <?= $totalJobs ?> jobs
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>