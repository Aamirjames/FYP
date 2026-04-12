<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

requireRole('admin');

$msg = "";
$err = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $userId = (int) ($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $map = [
        'approve' => 'active',
        'reject' => 'rejected',
        'block' => 'blocked',
        'unblock' => 'active',
    ];

    if ($userId <= 0 || !isset($map[$action])) {
        $err = "Invalid request.";
    } else {
        $stmt = $pdo->prepare("SELECT role, status FROM users WHERE user_id=? LIMIT 1");
        $stmt->execute([$userId]);
        $u = $stmt->fetch();

        if (!$u) {
            $err = "User not found.";
        } elseif ($u['role'] === 'admin') {
            $err = "You cannot modify admin accounts.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET status=? WHERE user_id=?");
            $stmt->execute([$map[$action], $userId]);
            $msg = "User status updated to " . $map[$action] . ".";
        }
    }
}

$filter = $_GET['status'] ?? 'all';
$where = "";
$params = [];

if (in_array($filter, ['pending', 'active', 'rejected', 'blocked'], true)) {
    $where = " AND status = ? ";
    $params[] = $filter;
}

$stmt = $pdo->prepare("
    SELECT user_id, name, email, role, status, created_at
    FROM users
    WHERE role IN ('client','freelancer') $where
    ORDER BY created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll();

$title = "Manage Users";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0 page-title">Manage Users</h3>
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

    <!-- Filter -->
    <div class="card card-soft p-4 mb-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Filter by status</label>
                <select name="status" class="form-select">
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="blocked" <?= $filter === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                    <option value="rejected" <?= $filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
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
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <?= (int) $u['user_id'] ?>
                            </td>
                            <td>
                                <div class="fw-bold">
                                    <?= htmlspecialchars($u['name']) ?>
                                </div>
                                <div class="text-muted2" style="font-size:.84rem;">
                                    <?= htmlspecialchars($u['email']) ?>
                                </div>
                            </td>
                            <td>
                                <?= htmlspecialchars($u['role']) ?>
                            </td>
                            <td>
                                <?php $st = preg_replace('/[^a-z_]/', '', strtolower(trim($u['status']))); ?>
                                <span class="status-badge status-<?= $st ?>">
                                    <?= htmlspecialchars($u['status']) ?>
                                </span>
                            </td>
                            <td class="text-muted2" style="font-size:.84rem;">
                                <?= date('d M Y', strtotime($u['created_at'])) ?>
                            </td>
                            <td>
                                <form method="post" class="d-flex gap-2 flex-wrap">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>">

                                    <?php if ($u['status'] === 'pending'): ?>
                                        <!-- pending → can approve or reject -->
                                        <button class="btn btn-success btn-sm" name="action" value="approve">Approve</button>
                                        <button class="btn btn-danger btn-sm" name="action" value="reject">Reject</button>

                                    <?php elseif ($u['status'] === 'active'): ?>
                                        <!-- active → can only block -->
                                        <button class="btn btn-warning btn-sm" name="action" value="block">Block</button>

                                    <?php elseif ($u['status'] === 'blocked'): ?>
                                        <!-- blocked → can unblock or reject -->
                                        <button class="btn btn-secondary btn-sm" name="action" value="unblock">Unblock</button>
                                        <button class="btn btn-danger btn-sm" name="action" value="reject">Reject</button>

                                    <?php elseif ($u['status'] === 'rejected'): ?>
                                        <!-- rejected → can approve (give second chance) or block -->
                                        <button class="btn btn-success btn-sm" name="action" value="approve">Approve</button>
                                        <button class="btn btn-warning btn-sm" name="action" value="block">Block</button>

                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$users): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted2 py-4">No users found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>