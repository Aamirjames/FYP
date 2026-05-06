<?php
// Client browses freelancer profiles its is test case SSH17
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('client');

// Load all skills for filter dropdown
$allSkills = $pdo->query("SELECT skill_id, skill_name FROM skills ORDER BY skill_name")->fetchAll();

// Filters
$skillId = (int) ($_GET['skill_id'] ?? 0);
$maxRate = trim($_GET['max_rate'] ?? '');
$searchName = trim($_GET['name'] ?? '');
$sort = $_GET['sort'] ?? 'name_asc';

// Sort options mapping
$orderBy = match ($sort) {
    'rating_desc' => "r.avg_rating DESC NULLS LAST",
    'rate_asc' => "u.hourly_rate ASC",
    'rate_desc' => "u.hourly_rate DESC",
    'name_desc' => "u.name DESC",
    default => "u.name ASC"
};

$sql = "
    SELECT u.user_id, u.name, u.email, u.hourly_rate, u.portfolio_url, u.profile_image,
           u.last_active, u.created_at,
           GROUP_CONCAT(DISTINCT s.skill_name ORDER BY s.skill_name SEPARATOR ', ') AS skills,
           COALESCE(r.avg_rating, 0) AS avg_rating,
           COALESCE(r.review_count, 0) AS review_count
    FROM users u
    LEFT JOIN user_skill us ON us.user_id = u.user_id
    LEFT JOIN skills s ON s.skill_id = us.skill_id
    LEFT JOIN (
        SELECT freelancer_id, 
               ROUND(AVG(rating), 1) AS avg_rating,
               COUNT(review_id) AS review_count
        FROM reviews
        GROUP BY freelancer_id
    ) r ON r.freelancer_id = u.user_id
    WHERE u.role = 'freelancer' AND u.status = 'active'
";
$params = [];

if ($searchName !== '') {
    $sql .= " AND u.name LIKE ? ";
    $params[] = '%' . $searchName . '%';
}

if ($maxRate !== '' && is_numeric($maxRate)) {
    $sql .= " AND u.hourly_rate <= ? ";
    $params[] = (float) $maxRate;
}

if ($skillId > 0) {
    $sql .= " AND u.user_id IN (SELECT user_id FROM user_skill WHERE skill_id = ?) ";
    $params[] = $skillId;
}

$sql .= " GROUP BY u.user_id, u.name, u.email, u.hourly_rate, u.portfolio_url, u.profile_image, u.last_active, u.created_at, r.avg_rating, r.review_count ORDER BY $orderBy ";

// Pagination
$perPage = 9;
$page = max(1, (int) ($_GET['page'] ?? 1));

$countSql = "SELECT COUNT(DISTINCT u.user_id) FROM users u 
             LEFT JOIN user_skill us ON us.user_id = u.user_id 
             LEFT JOIN skills s ON s.skill_id = us.skill_id 
             WHERE u.role='freelancer' AND u.status='active'";
$countParams = [];
if ($searchName !== '') {
    $countSql .= " AND u.name LIKE ?";
    $countParams[] = '%' . $searchName . '%';
}
if ($maxRate !== '' && is_numeric($maxRate)) {
    $countSql .= " AND u.hourly_rate<=?";
    $countParams[] = (float) $maxRate;
}
if ($skillId > 0) {
    $countSql .= " AND u.user_id IN (SELECT user_id FROM user_skill WHERE skill_id=?)";
    $countParams[] = $skillId;
}
$cStmt = $pdo->prepare($countSql);
$cStmt->execute($countParams);
$totalFreelancers = (int) $cStmt->fetchColumn();
$totalPages = (int) ceil($totalFreelancers / $perPage);
$page = min($page, max(1, $totalPages));
$offset = ($page - 1) * $perPage;

$sql .= " LIMIT $perPage OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$freelancers = $stmt->fetchAll();

// Helper function for online status
// Compares both sides in UTC to avoid PHP/MySQL timezone mismatch.
// MySQL stores last_active as UTC (SET time_zone='+00:00' in DB).
// strtotime() interprets the datetime in PHP's local timezone, which can
// make the gap appear negative (freelancer always "online"). We parse the
// timestamp explicitly as UTC so both sides of the subtraction are UTC.
function isOnline($lastActive)
{
    if (!$lastActive)
        return false;
    // Parse the stored datetime as UTC regardless of PHP's local timezone
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $lastActive, new DateTimeZone('UTC'));
    if (!$dt)
        return false;
    $nowUtc = new DateTime('now', new DateTimeZone('UTC'));
    $diffSeconds = $nowUtc->getTimestamp() - $dt->getTimestamp();
    return $diffSeconds >= 0 && $diffSeconds < 180; // online if active within last 3 mins
}

function initials($name)
{
    $parts = preg_split('/\s+/', trim($name));
    $first = strtoupper(substr($parts[0] ?? 'U', 0, 1));
    $second = strtoupper(substr($parts[1] ?? '', 0, 1));
    return $first . ($second ?: '');
}

$title = "Browse Freelancers";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0 fw-bold">Browse Freelancers</h3>
    </div>

    <!-- Filters -->
    <div class="card card-soft p-4 mb-4">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search by Name</label>
                <input class="form-control" name="name" type="text" placeholder="e.g. Aamir"
                    value="<?= htmlspecialchars($searchName) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Filter by Skill</label>
                <select class="form-select" name="skill_id">
                    <option value="0">All Skills</option>
                    <?php foreach ($allSkills as $s): ?>
                        <option value="<?= (int) $s['skill_id'] ?>" <?= $skillId === (int) $s['skill_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['skill_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Max Rate (PKR)</label>
                <input class="form-control" name="max_rate" type="number" min="0" step="1" placeholder="e.g. 5000"
                    value="<?= htmlspecialchars($maxRate) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Sort by</label>
                <select class="form-select" name="sort">
                    <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                    <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                    <option value="rating_desc" <?= $sort === 'rating_desc' ? 'selected' : '' ?>>Rating (Highest)</option>
                    <option value="rate_asc" <?= $sort === 'rate_asc' ? 'selected' : '' ?>>Price (Low to High)</option>
                    <option value="rate_desc" <?= $sort === 'rate_desc' ? 'selected' : '' ?>>Price (High to Low)</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-brand w-100">Filter</button>
            </div>
        </form>
    </div>

    <p class="text-muted2 mb-3">
        <?= $totalFreelancers ?> freelancer
        <?= $totalFreelancers !== 1 ? 's' : '' ?> found.
    </p>

    <!-- Freelancer Cards -->
    <?php if ($freelancers): ?>
        <div class="row g-4">
            <?php foreach ($freelancers as $f):
                $imgUrl = $f['profile_image'] ? BASE_URL . '/' . $f['profile_image'] : null;
                $skills = $f['skills'] ? explode(', ', $f['skills']) : [];
                $online = isOnline($f['last_active']);
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card card-soft p-4 h-100 d-flex flex-column">

                        <!-- Avatar and Name -->
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <?php if ($imgUrl): ?>
                                <img src="<?= htmlspecialchars($imgUrl) ?>" class="profile-avatar" alt="Profile">
                            <?php else: ?>
                                <div class="profile-avatar-placeholder">
                                    <?= htmlspecialchars(initials($f['name'])) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <div class="fw-bold" style="font-size:1.05rem;">
                                        <?= htmlspecialchars($f['name']) ?>
                                    </div>
                                    <?php if ($online): ?>
                                        <span
                                            style="background:#e8f0fe; color:#1a73e8; font-size:0.65rem; padding:2px 6px; border-radius:20px;">
                                            🟢 Online
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted2" style="font-size:.84rem;">
                                    <?= htmlspecialchars($f['email']) ?>
                                </div>
                            </div>
                        </div>

                        <!-- Hourly Rate -->
                        <div class="mb-3">
                            <?php if ($f['hourly_rate']): ?>
                                <span style="color:var(--brand);font-weight:700;font-size:1rem;">
                                    PKR <?= number_format((float) $f['hourly_rate'], 0) ?>
                                </span>
                                <span class="text-muted2" style="font-size:.84rem;"> / hr</span>
                            <?php else: ?>
                                <span class="text-muted2" style="font-size:.88rem;">Hourly rate not set</span>
                            <?php endif; ?>
                        </div>

                        <!-- Ratings -->
                        <div class="mb-3">
                            <?php if ($f['avg_rating'] > 0): ?>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="text-warning" style="letter-spacing: 2px; font-size: 1rem;">
                                        <?php
                                        $fullStars = floor($f['avg_rating']);
                                        $halfStar = ($f['avg_rating'] - $fullStars) >= 0.5;
                                        for ($i = 1; $i <= 5; $i++):
                                            if ($i <= $fullStars):
                                                echo '★';
                                            elseif ($halfStar && $i == $fullStars + 1):
                                                echo '½';
                                            else:
                                                echo '☆';
                                            endif;
                                        endfor;
                                        ?>
                                    </div>
                                    <span class="fw-semibold"
                                        style="font-size: 0.95rem;"><?= number_format($f['avg_rating'], 1) ?></span>
                                    <span class="text-muted2" style="font-size: 0.75rem;">(<?= $f['review_count'] ?>
                                        review<?= $f['review_count'] != 1 ? 's' : '' ?>)</span>
                                </div>
                            <?php else: ?>
                                <div class="text-muted2" style="font-size: 0.85rem;">⭐ No reviews yet</div>
                            <?php endif; ?>
                        </div>

                        <!-- Last Active (only if exists) -->
                        <?php if ($f['last_active']): ?>
                            <div class="mb-2" style="font-size:0.75rem;">
                                <span class="text-muted2"
                                    title="Last seen: <?= date('M j, g:i A', strtotime($f['last_active'])) ?>">
                                    📅 Last active: <?= date('M j, g:i A', strtotime($f['last_active'])) ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <!-- Skills -->
                        <?php if ($skills): ?>
                            <div class="mb-3 d-flex flex-wrap gap-1">
                                <?php foreach ($skills as $skill): ?>
                                    <span
                                        style="background:rgba(34,211,238,.10); border:1px solid rgba(34,211,238,.25); color:var(--brand); border-radius:999px; padding:.25em .7em; font-size:.78rem; font-weight:600;">
                                        <?= htmlspecialchars(trim($skill)) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-muted2 mb-3" style="font-size:.85rem;">No skills listed</div>
                        <?php endif; ?>

                        <!-- Portfolio -->
                        <div class="mt-auto">
                            <?php if ($f['portfolio_url']): ?>
                                <a href="<?= htmlspecialchars($f['portfolio_url']) ?>" target="_blank"
                                    class="btn btn-outline-primary btn-sm rounded-pill w-100">
                                    🔗 View Portfolio
                                </a>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary btn-sm rounded-pill w-100" disabled>No Portfolio</button>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php else: ?>
        <div class="card card-soft p-4 text-center text-muted2 py-5">
            <div style="font-size:2.5rem;">👤</div>
            <div class="mt-2">No freelancers found for the selected filters.</div>
        </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-center mt-4">
            <nav>
                <ul class="pagination pagination-custom">
                    <?php
                    $queryParams = $_GET;
                    if ($page > 1):
                        $queryParams['page'] = $page - 1; ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($queryParams) ?>">← Prev</a></li>
                    <?php endif;
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    for ($i = $start; $i <= $end; $i++):
                        $queryParams['page'] = $i; ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query($queryParams) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor;
                    if ($page < $totalPages):
                        $queryParams['page'] = $page + 1; ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($queryParams) ?>">Next →</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <div class="text-center text-muted2 mt-2" style="font-size:.82rem;">
            Showing <?= (($page - 1) * $perPage) + 1 ?>–<?= min($page * $perPage, $totalFreelancers) ?> of
            <?= $totalFreelancers ?> freelancers
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>