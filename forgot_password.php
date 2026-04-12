<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/csrf.php';

if (session_status() === PHP_SESSION_NONE)
    session_start();

// Already logged in → redirect
if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check user exists
        $stmt = $pdo->prepare("SELECT user_id, name FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Always show success message (security don't reveal if email exists)
        $success = "If that email is registered, you will receive a password reset link shortly.";

        if ($user) {
            // Delete any old unused tokens for this email
            $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

            // Generate token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")
                ->execute([$email, $token, $expiresAt]);

            // Build reset link
            $resetLink = BASE_URL . "/reset_password.php?token=" . urlencode($token);

            // Send email
            try {
                require_once __DIR__ . '/includes/mailer.php';
                $html = "
                    <div style='font-family:sans-serif;max-width:500px;margin:auto;'>
                        <h2 style='color:#22d3ee;'>Password Reset</h2>
                        <p>Hi " . htmlspecialchars($user['name']) . ",</p>
                        <p>We received a request to reset your Skill-Share Hub password.</p>
                        <p>Click the button below to set a new password. This link expires in <strong>1 hour</strong>.</p>
                        <p style='text-align:center;margin:30px 0;'>
                            <a href='" . $resetLink . "'
                               style='background:#22d3ee;color:#000;padding:12px 28px;border-radius:999px;
                                      text-decoration:none;font-weight:700;'>
                                Reset My Password
                            </a>
                        </p>
                        <p style='color:#888;font-size:.85rem;'>
                            If you didn't request this, you can safely ignore this email.<br>
                            Link: " . $resetLink . "
                        </p>
                    </div>
                ";
                sendMail($email, $user['name'], "Reset Your Password — Skill-Share Hub", $html);
            } catch (Exception $e) {
                error_log("Password reset mail failed: " . $e->getMessage());
            }
        }
    }
}

$title = "Forgot Password";
require_once __DIR__ . '/includes/header.php';
?>

<section class="py-5 min-vh-100 d-flex align-items-center">
    <div class="container" style="max-width:460px;">
        <div class="card card-soft p-4">

            <h4 class="fw-bold mb-1">Forgot Password?</h4>
            <p class="text-muted2 mb-4" style="font-size:.88rem;">
                Enter your registered email and we'll send you a reset link.
            </p>

            <?php if ($error): ?>
                <div class="alert alert-danger mb-3">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success mb-3">
                    <?= htmlspecialchars($success) ?>
                </div>
                <div class="text-center mt-2">
                    <a href="<?= BASE_URL ?>/login.php" class="btn btn-outline-primary rounded-pill px-4">
                        ← Back to Login
                    </a>
                </div>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email Address</label>
                        <input class="form-control" type="email" name="email" placeholder="Enter your registered email"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                    </div>
                    <button class="btn btn-brand w-100 py-2">Send Reset Link</button>
                </form>
                <div class="text-center mt-3">
                    <a href="<?= BASE_URL ?>/login.php" style="font-size:.87rem; color:var(--brand);">← Back to
                        Login</a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>