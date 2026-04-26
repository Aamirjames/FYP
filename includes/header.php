<?php
// includes/header.php
require_once __DIR__ . '/../config/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = $_SESSION['user'] ?? null;
if (!is_array($user) || empty($user['id']) || empty($user['role'])) {
    $user = null;
}

/* Notifications — load unread count + recent 8 for dropdown */
$_notifUnread = 0;
$_notifList = [];
if ($user && $user['role'] !== 'admin') {
    require __DIR__ . '/../config/db.php';
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        goto skip_notifications;
    }
    $s = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $s->execute([$user['id']]);
    $_notifUnread = (int) $s->fetchColumn();

    $s = $pdo->prepare("
        SELECT notif_id, message, link, is_read, created_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 8
    ");
    $s->execute([$user['id']]);
    $_notifList = $s->fetchAll();
}
skip_notifications:

/* Brand/logo goes to dashboard when logged in, otherwise home */
$brandLink = BASE_URL . '/index.php';
if ($user) {
    if ($user['role'] === 'admin') {
        $brandLink = BASE_URL . '/admin/dashboard.php';
    } elseif ($user['role'] === 'client') {
        $brandLink = BASE_URL . '/client/dashboard.php';
    } elseif ($user['role'] === 'freelancer') {
        $brandLink = BASE_URL . '/freelancer/dashboard.php';
    }
}

/* Active link handling */
$currentPage = basename($_SERVER['PHP_SELF']);   // e.g., index.php, login.php
$isHome = ($currentPage === 'index.php');
$isLogin = ($currentPage === 'login.php');
$isRegister = ($currentPage === 'register.php');
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        <?= htmlspecialchars($title ?? 'Skill-Share Hub') ?>
    </title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="<?= BASE_URL ?>/css/style.css" rel="stylesheet">
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-skillhub border-bottom py-3">

        <div class="container">
            <a class="navbar-brand brand" href="<?= $brandLink ?>">Skill-Share Hub</a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"
                aria-controls="nav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="nav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                    <li class="nav-item">
                        <a class="nav-link <?= $isHome ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/about.php">About</a>
                    </li>

                    <?php if ($user && $user['role'] === 'client'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/client/browse_freelancers.php">Freelancers</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/client/post_job.php">Post a Job</a>
                        </li>
                    <?php elseif ($user && $user['role'] === 'freelancer'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/freelancer/browse_jobs.php">Browse Jobs</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/search.php">Search</a>
                        </li>
                    <?php elseif ($user && $user['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/admin/users.php">Users</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/admin/jobs.php">Jobs</a>
                        </li>
                    <?php endif; ?>

                    <?php if (!$user): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $isLogin ? 'active' : '' ?>" href="<?= BASE_URL ?>/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-brand rounded-pill px-4" href="<?= BASE_URL ?>/register.php">
                                Get started
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="btn btn-outline-primary rounded-pill px-4" href="<?= $brandLink ?>">
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/logout.php">Logout</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- ── Bell Notification (floating, outside navbar) ── -->
    <?php if ($user && $user['role'] !== 'admin'): ?>
        <div class="notif-float-wrap dropdown">
            <button class="notif-float-btn" id="notifFloatBtn" data-bs-toggle="dropdown" aria-expanded="false">
                🔔
                <?php if ($_notifUnread > 0): ?>
                    <span class="notif-badge">
                        <?= $_notifUnread > 9 ? '9+' : $_notifUnread ?>
                    </span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end notif-dropdown" aria-labelledby="notifFloatBtn">
                <li class="notif-header">
                    <span>Notifications</span>
                    <?php if ($_notifUnread > 0): ?>
                        <a href="<?= BASE_URL ?>/notifications.php?mark_all=1" style="font-size:.78rem;color:var(--brand);">Mark
                            all read</a>
                    <?php endif; ?>
                </li>
                <?php if (!$_notifList): ?>
                    <li class="notif-empty">No notifications yet.</li>
                <?php else: ?>
                    <?php foreach ($_notifList as $n): ?>
                        <li>
                            <a class="notif-item <?= (int) $n['is_read'] === 0 ? 'unread' : '' ?>"
                                href="<?= BASE_URL ?>/notifications.php?read=<?= (int) $n['notif_id'] ?><?= $n['link'] ? '&goto=' . urlencode($n['link']) : '' ?>">
                                <div class="notif-msg">
                                    <?= htmlspecialchars($n['message']) ?>
                                </div>
                                <div class="notif-time">
                                    <?= date('d M, h:i A', strtotime($n['created_at'])) ?>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <li class="notif-footer">
                        <a href="<?= BASE_URL ?>/notifications.php">View all →</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>