<?php
// SSH05: Notifications page — view all, mark read, redirect
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$myId = (int) $_SESSION['user']['id'];
$myRole = $_SESSION['user']['role'];

if ($myRole === 'admin') {
    header("Location: " . BASE_URL . "/admin/dashboard.php");
    exit;
}

// Mark single notification as read then redirect
if (isset($_GET['read'])) {
    $notifId = (int) $_GET['read'];
    $goto = $_GET['goto'] ?? '';

    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE notif_id=? AND user_id=?")
        ->execute([$notifId, $myId]);

    if ($goto !== '') {
        header("Location: " . BASE_URL . "/" . ltrim($goto, '/'));
    } else {
        header("Location: " . BASE_URL . "/notifications.php");
    }
    exit;
}

// Mark all as read
if (isset($_GET['mark_all'])) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")
        ->execute([$myId]);
    header("Location: " . BASE_URL . "/notifications.php");
    exit;
}

// Load all notifications
$stmt = $pdo->prepare("
    SELECT notif_id, message, link, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$myId]);
$notifications = $stmt->fetchAll();

$unreadCount = count(array_filter($notifications, fn($n) => !(int) $n['is_read']));

$title = "Notifications";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5" style="max-width:700px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0 fw-bold">🔔 Notifications</h3>
        <div class="d-flex gap-2">
            <?php if ($unreadCount > 0): ?>
                <a class="btn btn-outline-primary btn-sm rounded-pill" href="<?= BASE_URL ?>/notifications.php?mark_all=1">
                    Mark all read
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$notifications): ?>
        <div class="card card-soft p-4 text-center text-muted2 py-5">
            <div style="font-size:2.5rem;">🔔</div>
            <div class="mt-2">No notifications yet.</div>
        </div>

    <?php else: ?>
        <div class="d-flex flex-column gap-2">
            <?php foreach ($notifications as $n):
                $isUnread = (int) $n['is_read'] === 0;
                ?>
                <a href="<?= BASE_URL ?>/notifications.php?read=<?= (int) $n['notif_id'] ?><?= $n['link'] ? '&goto=' . urlencode($n['link']) : '' ?>"
                    class="card card-soft p-3 text-decoration-none d-flex flex-row align-items-start gap-3"
                    style="<?= $isUnread ? 'border-color:rgba(34,211,238,.35);background:rgba(34,211,238,.04);' : '' ?>">

                    <div style="font-size:1.4rem; margin-top:2px;">
                        <?= $isUnread ? '🔵' : '⚪' ?>
                    </div>
                    <div class="flex-grow-1">
                        <div style="font-size:.93rem; color:var(--text); font-weight: <?= $isUnread ? '600' : '400' ?>;">
                            <?= htmlspecialchars($n['message']) ?>
                        </div>
                        <div class="text-muted2 mt-1" style="font-size:.78rem;">
                            <?= date('d M Y, h:i A', strtotime($n['created_at'])) ?>
                        </div>
                    </div>
                    <?php if ($isUnread): ?>
                        <span class="status-badge status-pending" style="font-size:.72rem; align-self:center;">New</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>