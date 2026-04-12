<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/csrf.php';

if (session_status() === PHP_SESSION_NONE)
    session_start();

$token = trim($_GET['token'] ?? '');
$error = '';
$success = '';
$validToken = null;

if ($token === '') {
    $error = "Invalid or missing reset link.";
} else {
    // Load token
    $stmt = $pdo->prepare("
        SELECT * FROM password_resets
        WHERE token = ? AND used = 0 AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $validToken = $stmt->fetch();

    if (!$validToken) {
        $error = "This reset link is invalid or has expired. Please request a new one.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    csrf_verify();
    $newPwd = $_POST['new_password'] ?? '';
    $confirmPwd = $_POST['confirm_password'] ?? '';

    if (strlen($newPwd) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($newPwd !== $confirmPwd) {
        $error = "Passwords do not match.";
    } else {
        // Update password
        $pdo->prepare("UPDATE users SET password=? WHERE email=?")
            ->execute([password_hash($newPwd, PASSWORD_DEFAULT), $validToken['email']]);

        // Mark token as used
        $pdo->prepare("UPDATE password_resets SET used=1 WHERE id=?")
            ->execute([$validToken['id']]);

        $success = "Your password has been reset successfully. You can now log in.";
        $validToken = null; // hide form
    }
}

$title = "Reset Password";
require_once __DIR__ . '/includes/header.php';
?>

<section class="py-5 min-vh-100 d-flex align-items-center">
    <div class="container" style="max-width:460px;">
        <div class="card card-soft p-4">

            <h4 class="fw-bold mb-1">Reset Password</h4>
            <p class="text-muted2 mb-4" style="font-size:.88rem;">
                Enter your new password below.
            </p>

            <?php if ($error): ?>
                <div class="alert alert-danger mb-3">
                    <?= htmlspecialchars($error) ?>
                </div>
                <div class="text-center">
                    <a href="<?= BASE_URL ?>/forgot_password.php" class="btn btn-brand rounded-pill px-4">Request New
                        Link</a>
                </div>

            <?php elseif ($success): ?>
                <div class="alert alert-success mb-3">✅
                    <?= htmlspecialchars($success) ?>
                </div>
                <div class="text-center">
                    <a href="<?= BASE_URL ?>/login.php" class="btn btn-brand rounded-pill px-4">Login Now</a>
                </div>

            <?php elseif ($validToken): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                    <div class="mb-3">
                        <label class="form-label fw-bold">New Password <span style="color:#f87171;">*</span></label>
                        <input class="form-control" type="password" name="new_password" placeholder="Minimum 6 characters"
                            minlength="6" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Confirm Password <span style="color:#f87171;">*</span></label>
                        <input class="form-control" type="password" name="confirm_password"
                            placeholder="Re-enter new password" required>
                    </div>
                    <button class="btn btn-brand w-100 py-2">Reset Password</button>
                </form>
            <?php endif; ?>

        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>