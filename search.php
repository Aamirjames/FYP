<?php
// search.php — Enhanced broad search 
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';

if (session_status() === PHP_SESSION_NONE)
    session_start();

$viewer = $_SESSION['user'] ?? null;
$isLoggedIn = is_array($viewer) && !empty($viewer['id']);
$role = $viewer['role'] ?? '';

$q = trim($_GET['q'] ?? '');
$type = ($_GET['type'] ?? 'jobs') === 'freelancers' ? 'freelancers' : 'jobs';

$results = [];
$totalFound = 0;

if ($q !== '') {

    // ── Split into individual keywords ──────────────────────
    // e.g. "php web developer" → ['php', 'web', 'developer']
    $rawWords = preg_split('/[\s,;]+/', $q, -1, PREG_SPLIT_NO_EMPTY);
    $keywords = array_unique(array_filter(array_map('trim', $rawWords), fn($w) => strlen($w) >= 2));

    if (empty($keywords)) {
        $keywords = [$q];
    }

    if ($type === 'jobs') {

        //  Build WHERE clause: each keyword must match at least one field
        // Fields searched: title, description, category_name, client name
        $whereParts = [];
        $params = [];

        foreach ($keywords as $kw) {
            $like = '%' . $kw . '%';
            $whereParts[] = "(j.title LIKE ? OR j.description LIKE ? OR c.category_name LIKE ? OR u.name LIKE ?)";
            $params = array_merge($params, [$like, $like, $like, $like]);
        }

        // Also do a full phrase match for relevance ordering
        $fullLike = '%' . $q . '%';
        $whereStr = implode(' OR ', $whereParts);

        // Relevance score: full phrase match in title scores highest
        $sql = "
            SELECT j.job_id, j.title, j.description, j.budget, j.deadline,
                   c.category_name, u.name AS client_name,
                   (
                       (j.title LIKE ?) * 10 +
                       (c.category_name LIKE ?) * 6 +
                       (j.description LIKE ?) * 3 +
                       (u.name LIKE ?) * 2
                   ) AS relevance
            FROM job j
            JOIN category c ON c.category_id = j.category_id
            JOIN users u    ON u.user_id     = j.client_id
            WHERE j.status = 'approved'
              AND ($whereStr)
            ORDER BY relevance DESC, j.created_at DESC
            LIMIT 50
        ";

        $relevanceParams = [$fullLike, $fullLike, $fullLike, $fullLike];
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($relevanceParams, $params));

    } else {

        //  Freelancer search: name, skills, portfolio URL
        $whereParts = [];
        $params = [];

        foreach ($keywords as $kw) {
            $like = '%' . $kw . '%';
            $whereParts[] = "(u.name LIKE ? OR s.skill_name LIKE ? OR u.portfolio_url LIKE ?)";
            $params = array_merge($params, [$like, $like, $like]);
        }

        $fullLike = '%' . $q . '%';
        $whereStr = implode(' OR ', $whereParts);

        $sql = "
            SELECT u.user_id, u.name, u.hourly_rate, u.profile_image,
                   GROUP_CONCAT(DISTINCT s.skill_name ORDER BY s.skill_name SEPARATOR ', ') AS skills,
                   (
                       (u.name LIKE ?) * 10 +
                       MAX(s.skill_name LIKE ?) * 6
                   ) AS relevance
            FROM users u
            LEFT JOIN user_skill us ON us.user_id = u.user_id
            LEFT JOIN skills s      ON s.skill_id = us.skill_id
            WHERE u.role = 'freelancer' AND u.status = 'active'
              AND ($whereStr)
            GROUP BY u.user_id
            ORDER BY relevance DESC, u.name ASC
            LIMIT 50
        ";

        $relevanceParams = [$fullLike, $fullLike];
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($relevanceParams, $params));
    }

    $results = $stmt->fetchAll();
    $totalFound = count($results);
}

function initials($name)
{
    $parts = preg_split('/\s+/', trim($name));
    return strtoupper(substr($parts[0] ?? 'U', 0, 1)) . strtoupper(substr($parts[1] ?? '', 0, 1));
}

// Highlight matching keywords in text
function highlight(string $text, array $keywords): string
{
    $text = htmlspecialchars($text);
    foreach ($keywords as $kw) {
        $safe = preg_quote(htmlspecialchars($kw), '/');
        $text = preg_replace(
            '/(' . $safe . ')/i',
            '<mark style="background:rgba(34,211,238,.25);color:inherit;border-radius:3px;padding:0 2px;">$1</mark>',
            $text
        );
    }
    return $text;
}

$keywords = isset($keywords) ? $keywords : [];
$title = $q ? "Search: " . htmlspecialchars($q) : "Search";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">

    <!-- Search Form -->
    <div class="card card-soft p-4 mb-4">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label fw-bold">Search</label>
                <input class="form-control" type="text" name="q"
                    placeholder="e.g. PHP developer, logo design, data entry..." value="<?= htmlspecialchars($q) ?>"
                    autofocus>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Search In</label>
                <select class="form-select" name="type">
                    <option value="jobs" <?= $type === 'jobs' ? 'selected' : '' ?>>Jobs</option>
                    <option value="freelancers" <?= $type === 'freelancers' ? 'selected' : '' ?>>Freelancers</option>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-brand w-100">🔍 Search</button>
            </div>
        </form>

        <!-- Popular tags -->
        <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
            <span class="text-muted2" style="font-size:.8rem;">Try:</span>
            <?php foreach (['PHP', 'Web Design', 'Data Entry', 'Content Writing', 'Python', 'WordPress', 'SEO', 'Logo Design', 'Excel', 'JavaScript'] as $tag): ?>
                <a href="?q=<?= urlencode($tag) ?>&type=<?= $type ?>" class="search-tag"><?= $tag ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($q !== ''): ?>
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="fw-bold mb-0">
                <?= $totalFound ?> result<?= $totalFound !== 1 ? 's' : '' ?>
                for "<span style="color:var(--brand);"><?= htmlspecialchars($q) ?></span>"
            </h5>
            <div class="d-flex gap-2">
                <a href="?q=<?= urlencode($q) ?>&type=jobs"
                    class="btn btn-sm rounded-pill <?= $type === 'jobs' ? 'btn-brand' : 'btn-outline-primary' ?>">
                    💼 Jobs
                </a>
                <a href="?q=<?= urlencode($q) ?>&type=freelancers"
                    class="btn btn-sm rounded-pill <?= $type === 'freelancers' ? 'btn-brand' : 'btn-outline-primary' ?>">
                    👤 Freelancers
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Login banner -->
    <?php if (!$isLoggedIn && $results): ?>
        <div class="alert mb-4"
            style="background:rgba(34,211,238,.08);border:1px solid rgba(34,211,238,.25);border-radius:12px;">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <strong style="color:var(--brand);">Want to apply or hire?</strong>
                    <span class="text-muted2" style="font-size:.88rem;"> Create a free account to get started.</span>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL ?>/login.php" class="btn btn-sm rounded-pill px-3 fw-bold"
                        style="background:transparent;color:#22d3ee;border:1px solid #22d3ee;">Login</a>
                    <a href="<?= BASE_URL ?>/register.php" class="btn btn-sm rounded-pill px-3 fw-bold"
                        style="background:#22d3ee;color:#000;border:none;">Sign Up Free</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- No results -->
    <?php if ($q !== '' && !$results): ?>
        <div class="text-center text-muted2 py-5 card card-soft p-4">
            <div style="font-size:2.5rem;">😕</div>
            <div class="mt-2 fw-bold">No results found for "<?= htmlspecialchars($q) ?>"</div>
            <div class="text-muted2 mt-2" style="font-size:.87rem;">
                Try different keywords, shorter terms or check spelling
            </div>
            <div class="mt-3 d-flex gap-2 justify-content-center flex-wrap">
                <?php foreach (['PHP', 'Web Design', 'Data Entry', 'Writing', 'Python'] as $tag): ?>
                    <a href="?q=<?= urlencode($tag) ?>&type=<?= $type ?>" class="search-tag"><?= $tag ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Empty state -->
    <?php elseif ($q === ''): ?>
        <div class="text-center text-muted2 py-5">
            <div style="font-size:3rem;">🔍</div>
            <div class="mt-2 fw-bold">What are you looking for?</div>
            <div class="text-muted2 mt-1" style="font-size:.87rem;">
                Search jobs by title, description, category or find freelancers by name or skill
            </div>
        </div>

        <!-- Job Results -->
    <?php elseif ($type === 'jobs'): ?>
        <div class="row g-3">
            <?php foreach ($results as $j): ?>
                <div class="col-md-6">
                    <div class="card card-soft p-4 h-100 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <div class="fw-bold" style="font-size:1.05rem;">
                                <?= highlight($j['title'], $keywords) ?>
                            </div>
                            <div style="color:var(--brand);font-weight:700;white-space:nowrap;margin-left:8px;">
                                PKR <?= number_format((float) $j['budget'], 0) ?>
                            </div>
                        </div>
                        <div class="text-muted2 mb-2" style="font-size:.85rem;">
                            📂 <?= highlight($j['category_name'], $keywords) ?>
                            &bull; 👤 <?= htmlspecialchars($j['client_name']) ?>
                            &bull; 📅 <?= htmlspecialchars($j['deadline']) ?>
                        </div>
                        <div class="text-muted2 mb-3" style="font-size:.88rem;line-height:1.6;flex:1;">
                            <?= highlight(
                                mb_strlen($j['description']) > 150
                                ? mb_substr($j['description'], 0, 150) . '…'
                                : $j['description'],
                                $keywords
                            ) ?>
                        </div>
                        <div class="d-flex gap-2 mt-auto">
                            <?php if ($isLoggedIn && $role === 'freelancer'): ?>
                                <a class="btn btn-brand btn-sm rounded-pill"
                                    href="<?= BASE_URL ?>/freelancer/view_job.php?job_id=<?= (int) $j['job_id'] ?>">
                                    View & Apply →
                                </a>
                            <?php elseif (!$isLoggedIn): ?>
                                <a class="btn btn-brand btn-sm rounded-pill" href="<?= BASE_URL ?>/register.php?role=freelancer">
                                    Sign Up to Apply
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Freelancer Results -->
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($results as $f):
                $imgUrl = ($f['profile_image'] ?? null) ? BASE_URL . '/' . $f['profile_image'] : null;
                $skills = $f['skills'] ? explode(', ', $f['skills']) : [];
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card card-soft p-4 h-100 d-flex flex-column">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <?php if ($imgUrl): ?>
                                <img src="<?= htmlspecialchars($imgUrl) ?>" class="profile-avatar" alt="">
                            <?php else: ?>
                                <div class="profile-avatar-placeholder"><?= htmlspecialchars(initials($f['name'])) ?></div>
                            <?php endif; ?>
                            <div>
                                <div class="fw-bold"><?= highlight($f['name'], $keywords) ?></div>
                                <?php if ($f['hourly_rate']): ?>
                                    <div style="color:var(--brand);font-size:.85rem;font-weight:700;">
                                        PKR <?= number_format((float) $f['hourly_rate'], 0) ?>/hr
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($skills): ?>
                            <div class="d-flex flex-wrap gap-1 mb-3 flex-grow-1">
                                <?php foreach (array_slice($skills, 0, 6) as $sk): ?>
                                    <span class="skill-tag"
                                        style="<?= preg_grep('/' . preg_quote(trim($sk), '/') . '/i', $keywords) ? 'border-color:var(--brand);color:var(--brand);background:rgba(34,211,238,.1);' : '' ?>">
                                        <?= htmlspecialchars(trim($sk)) ?>
                                    </span>
                                <?php endforeach; ?>
                                <?php if (count($skills) > 6): ?>
                                    <span class="skill-tag">+<?= count($skills) - 6 ?> more</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="mt-auto d-flex gap-2">
                            <?php if ($isLoggedIn && $role === 'client'): ?>
                                <a href="<?= BASE_URL ?>/client/invite_freelancer.php?freelancer_id=<?= (int) $f['user_id'] ?>"
                                    class="btn btn-brand btn-sm rounded-pill w-100">
                                    📨 Invite to Job
                                </a>
                            <?php elseif (!$isLoggedIn): ?>
                                <a href="<?= BASE_URL ?>/register.php?role=client" class="btn btn-brand btn-sm rounded-pill w-100">
                                    Sign Up to Hire
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>