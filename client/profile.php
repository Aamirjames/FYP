<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/db.php';

requireRole('client');

$userId = (int) $_SESSION['user']['id'];
$userName = $_SESSION['user']['name'] ?? 'User';

function initials($name)
{
    $parts = preg_split('/\s+/', trim($name));
    $first = strtoupper(substr($parts[0] ?? 'U', 0, 1));
    $second = strtoupper(substr($parts[1] ?? '', 0, 1));
    return $first . ($second ?: '');
}

function handleImageUpload($userId, &$errors)
{
    if (
        empty($_FILES['profile_image']) ||
        ($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
    )
        return null;
    if ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Image upload failed.";
        return null;
    }
    if (($_FILES['profile_image']['size'] ?? 0) > 2 * 1024 * 1024) {
        $errors[] = "Image must be under 2MB.";
        return null;
    }
    $info = @getimagesize($_FILES['profile_image']['tmp_name']);
    if (!$info) {
        $errors[] = "File is not a valid image.";
        return null;
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$info['mime']])) {
        $errors[] = "Only JPG, PNG, WEBP allowed.";
        return null;
    }
    $dir = __DIR__ . '/../uploads/profiles/';
    if (!is_dir($dir))
        mkdir($dir, 0777, true);
    $fn = 'user_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$info['mime']];
    if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $dir . $fn)) {
        $errors[] = "Could not save image.";
        return null;
    }
    return 'uploads/profiles/' . $fn;
}

/* Load profile */
$stmt = $pdo->prepare("SELECT name, email, phone, profile_image FROM users WHERE user_id=?");
$stmt->execute([$userId]);
$profile = $stmt->fetch();

$errors = [];
$success = '';
$activeTab = $_GET['tab'] ?? 'profile';

/* ── FORM: Profile Info ───────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'profile') {
    csrf_verify();
    $activeTab = 'profile';

    $phone = trim($_POST['phone'] ?? '');
    $newImg = handleImageUpload($userId, $errors);

    if (!$errors) {
        if ($newImg !== null) {
            $pdo->prepare("UPDATE users SET phone=?, profile_image=? WHERE user_id=?")
                ->execute([$phone ?: null, $newImg, $userId]);
        } else {
            $pdo->prepare("UPDATE users SET phone=? WHERE user_id=?")
                ->execute([$phone ?: null, $userId]);
        }
        $success = "Profile updated successfully.";
        $stmt = $pdo->prepare("SELECT name, email, phone, profile_image FROM users WHERE user_id=?");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();
    }
}

/* ── FORM: Change Email ───────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'email') {
    csrf_verify();
    $activeTab = 'email';

    $newEmail = trim($_POST['new_email'] ?? '');
    $password = $_POST['confirm_password'] ?? '';

    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL))
        $errors[] = "Please enter a valid email address.";

    if (!$errors) {
        $row = $pdo->prepare("SELECT password FROM users WHERE user_id=?");
        $row->execute([$userId]);
        if (!password_verify($password, $row->fetchColumn()))
            $errors[] = "Current password is incorrect.";
    }
    if (!$errors) {
        $chk = $pdo->prepare("SELECT user_id FROM users WHERE email=? AND user_id != ?");
        $chk->execute([$newEmail, $userId]);
        if ($chk->fetch())
            $errors[] = "That email address is already taken.";
    }
    if (!$errors) {
        $pdo->prepare("UPDATE users SET email=? WHERE user_id=?")->execute([$newEmail, $userId]);
        $_SESSION['user']['email'] = $newEmail;
        $profile['email'] = $newEmail;
        $success = "Email updated successfully.";
    }
}

/* ── FORM: Change Password ────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'password') {
    csrf_verify();
    $activeTab = 'password';

    $currentPwd = $_POST['current_password'] ?? '';
    $newPwd = $_POST['new_password'] ?? '';
    $confirmPwd = $_POST['confirm_password'] ?? '';

    $row = $pdo->prepare("SELECT password FROM users WHERE user_id=?");
    $row->execute([$userId]);
    if (!password_verify($currentPwd, $row->fetchColumn()))
        $errors[] = "Current password is incorrect.";
    if (strlen($newPwd) < 6)
        $errors[] = "New password must be at least 6 characters.";
    if ($newPwd !== $confirmPwd)
        $errors[] = "New passwords do not match.";

    if (!$errors) {
        $pdo->prepare("UPDATE users SET password=? WHERE user_id=?")
            ->execute([password_hash($newPwd, PASSWORD_DEFAULT), $userId]);
        $success = "Password changed successfully.";
    }
}

$title = "My Profile";
$imgUrl = ($profile['profile_image'] ?? null) ? BASE_URL . '/' . $profile['profile_image'] : null;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5 page-narrow">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0 fw-bold">My Profile</h3>
    </div>

    <!-- Avatar + name -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <?php if ($imgUrl): ?>
            <img src="<?= htmlspecialchars($imgUrl) ?>" class="profile-avatar" alt="Profile">
        <?php else: ?>
            <div class="profile-avatar-placeholder">
                <?= htmlspecialchars(initials($userName)) ?>
            </div>
        <?php endif; ?>
        <div>
            <div class="fw-bold" style="font-size:1.1rem;">
                <?= htmlspecialchars($profile['name']) ?>
            </div>
            <div class="text-muted2" style="font-size:.85rem;">
                <?= htmlspecialchars($profile['email']) ?>
            </div>
            <?php if (!empty($profile['phone'])): ?>
                <div class="text-muted2" style="font-size:.82rem;">📞
                    <?= htmlspecialchars($profile['phone']) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" style="border-color:var(--border);">
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'profile' ? 'active' : '' ?>" href="?tab=profile">Profile Info</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'email' ? 'active' : '' ?>" href="?tab=email">Change Email</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'password' ? 'active' : '' ?>" href="?tab=password">Change
                Password</a>
        </li>
    </ul>

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

    <!-- ── TAB: Profile Info ── -->
    <?php if ($activeTab === 'profile'): ?>
        <form method="post" enctype="multipart/form-data" class="card card-soft p-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="form" value="profile">

            <div class="mb-3">
                <label class="form-label fw-bold">Profile Picture</label>
                <div class="d-flex align-items-center gap-3 mb-2">
                    <?php if ($imgUrl): ?>
                        <img src="<?= htmlspecialchars($imgUrl) ?>" class="profile-avatar" alt="Profile">
                    <?php else: ?>
                        <div class="profile-avatar-placeholder">
                            <?= htmlspecialchars(initials($userName)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="text-muted2" style="font-size:.84rem;">JPG, PNG, WEBP · max 2MB</div>
                </div>
                <input class="form-control" type="file" name="profile_image" accept=".jpg,.jpeg,.png,.webp,image/*">
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">Phone Number</label>
                <input class="form-control" type="text" name="phone" placeholder="e.g. 03001234567"
                    value="<?= htmlspecialchars($profile['phone'] ?? '') ?>">
            </div>

            <button class="btn btn-brand w-100 py-2">Save Profile</button>
        </form>

        <!-- ── TAB: Change Email ── -->
    <?php elseif ($activeTab === 'email'): ?>
        <form method="post" class="card card-soft p-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="form" value="email">

            <div class="mb-3">
                <label class="form-label fw-bold">Current Email</label>
                <input class="form-control" type="text" value="<?= htmlspecialchars($profile['email']) ?>" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">New Email Address <span style="color:#f87171;">*</span></label>
                <input class="form-control" type="email" name="new_email" placeholder="Enter new email address"
                    value="<?= htmlspecialchars($_POST['new_email'] ?? '') ?>" required>
            </div>
            <div class="mb-4">
                <label class="form-label fw-bold">Current Password <span style="color:#f87171;">*</span></label>
                <input class="form-control" type="password" name="confirm_password"
                    placeholder="Enter your password to confirm" required>
                <div class="text-muted2 mt-1" style="font-size:.82rem;">Required to verify this change.</div>
            </div>
            <button class="btn btn-brand w-100 py-2">Update Email</button>
        </form>

        <!-- ── TAB: Change Password ── -->
    <?php elseif ($activeTab === 'password'): ?>
        <form method="post" class="card card-soft p-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="form" value="password">

            <div class="mb-3">
                <label class="form-label fw-bold">Current Password <span style="color:#f87171;">*</span></label>
                <input class="form-control" type="password" name="current_password"
                    placeholder="Enter your current password" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">New Password <span style="color:#f87171;">*</span></label>
                <input class="form-control" type="password" name="new_password" placeholder="Minimum 6 characters"
                    minlength="6" required>
            </div>
            <div class="mb-4">
                <label class="form-label fw-bold">Confirm New Password <span style="color:#f87171;">*</span></label>
                <input class="form-control" type="password" name="confirm_password" placeholder="Re-enter new password"
                    required>
            </div>
            <button class="btn btn-brand w-100 py-2">Change Password</button>
        </form>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>