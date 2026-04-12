<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

// If already logged in, redirect to dashboard
if (!empty($_SESSION['user']) && is_array($_SESSION['user']) && !empty($_SESSION['user']['role'])) {
    redirectByRole($_SESSION['user']['role']);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT user_id,name,email,password,role,status FROM users WHERE email=? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        $errors[] = "Invalid email or password.";
    } else {
        if ($user['status'] !== 'active') {
            if ($user['status'] === 'pending')
                $errors[] = "Your account is pending admin approval.";
            elseif ($user['status'] === 'rejected')
                $errors[] = "Your account was rejected by admin.";
            elseif ($user['status'] === 'blocked')
                $errors[] = "Your account is blocked.";
            else
                $errors[] = "Account not active.";
        } else {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (int) $user['user_id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ];
            redirectByRole($user['role']);
        }
    }
}

$title = "Login";
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* im disbaled green tick bootstrap in login because it was showing green tick on wrong password also so no need there */
    #login-form .form-control:valid,
    .was-validated #login-form .form-control:valid {
        border-color: var(--bs-border-color) !important;
        background-image: none !important;
        padding-right: 0.75rem !important;
    }
</style>

<div class="container py-5 page-narrow">
    <h3 class="mb-3 text-center text-sm-start">Login</h3>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e)
                    echo "<li>" . htmlspecialchars($e) . "</li>"; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form id="login-form" method="post" class="card card-soft p-4 needs-validation" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

        <div class="mb-3">
            <label class="form-label">Email</label>
            <input class="form-control" name="email" placeholder="Please enter your email" type="email" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Password</label>
            <input class="form-control" name="password" placeholder="Please enter your password" type="password"
                required>
        </div>

        <button class="btn btn-brand w-100 py-2">Login</button>

        <div class="d-flex justify-content-between mt-3" style="font-size:.88rem;">
            <a href="<?= BASE_URL ?>/forgot_password.php" style="color:var(--brand);">Forgot Password?</a>
            <a href="<?= BASE_URL ?>/register.php">Create new account</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>