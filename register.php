<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/csrf.php';

// If already logged in, redirect to dashboard
if (!empty($_SESSION['user']) && is_array($_SESSION['user']) && !empty($_SESSION['user']['role'])) {
    redirectByRole($_SESSION['user']['role']);
}

$errors = [];
$success = "";

// Pre-select role from URL parameter (coming from landing page)
$preRole = in_array($_GET['role'] ?? '', ['client', 'freelancer']) ? $_GET['role'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';

    if ($name === '' || $email === '' || $password === '' || $role === '')
        $errors[] = "All fields are required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = "Invalid email format.";
    if (!in_array($role, ['client', 'freelancer'], true))
        $errors[] = "Please select Client or Freelancer.";
    if (strlen($password) < 6)
        $errors[] = "Password must be at least 6 characters.";
    if ($password !== $confirm_password)
        $errors[] = "Passwords do not match.";

    if (!$errors) {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Email already registered.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users(name,email,password,role,status) VALUES (?,?,?,?,?)")
                ->execute([$name, $email, $hash, $role, 'pending']);
            $success = "Registered successfully! Your account is pending admin approval.";
        }
    }

    // Keep pre-role on error
    if ($errors)
        $preRole = $role;
}

$selectedRole = $_POST['role'] ?? $preRole;

$title = "Create Account";
require_once __DIR__ . '/includes/header.php';
?>

<style>
    #password.form-control:valid,
    #confirm_password.form-control:valid,
    .was-validated #password.form-control:valid,
    .was-validated #confirm_password.form-control:valid {
        border-color: var(--bs-border-color) !important;
        background-image: none !important;
        padding-right: 0.75rem !important;
    }

    .role-card {
        border: 2px solid var(--border);
        border-radius: 14px;
        padding: 20px 16px;
        cursor: pointer;
        transition: border-color .2s, background .2s, transform .15s;
        text-align: center;
        background: rgba(255, 255, 255, .02);
        user-select: none;
    }

    .role-card:hover {
        border-color: rgba(34, 211, 238, .4);
        background: rgba(34, 211, 238, .04);
        transform: translateY(-2px);
    }

    .role-card.selected {
        border-color: var(--brand);
        background: rgba(34, 211, 238, .08);
    }

    .role-card .role-icon {
        font-size: 2rem;
        margin-bottom: 8px;
    }

    .role-card .role-name {
        font-weight: 700;
        font-size: 1rem;
        color: var(--text);
        margin-bottom: 4px;
    }

    .role-card .role-desc {
        font-size: .78rem;
        color: var(--muted);
        line-height: 1.4;
    }

    .role-card.selected .role-name {
        color: var(--brand);
    }
</style>

<div class="container py-5" style="max-width: 520px;">

    <div class="text-center mb-4">
        <h3 class="fw-bold mb-1">Create your account</h3>
        <p class="text-muted2" style="font-size:.88rem;">
            Join Skill-Share Hub — it's free and takes 2 minutes
        </p>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger mb-3">
            <ul class="mb-0">
                <?php foreach ($errors as $e)
                    echo "<li>" . htmlspecialchars($e) . "</li>"; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success mb-3 text-center">
            🎉
            <?= htmlspecialchars($success) ?>
            <div class="mt-2">
                <a href="<?= BASE_URL ?>/login.php" class="btn rounded-pill px-4 btn-sm"
                    style="background:var(--brand);color:#000;font-weight:600;">
                    Go to Login →
                </a>
            </div>
        </div>
    <?php else: ?>

        <form method="post" class="card card-soft p-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

            <!-- Role Selection Cards -->
            <div class="mb-4">
                <label class="form-label fw-bold mb-3">I want to...</label>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="role-card <?= $selectedRole === 'client' ? 'selected' : '' ?>"
                            onclick="selectRole('client')">
                            <div class="role-icon">🏢</div>
                            <div class="role-name">Hire Talent</div>
                            <div class="role-desc">Post jobs &amp; hire freelancers</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="role-card <?= $selectedRole === 'freelancer' ? 'selected' : '' ?>"
                            onclick="selectRole('freelancer')">
                            <div class="role-icon">💼</div>
                            <div class="role-name">Find Work</div>
                            <div class="role-desc">Browse jobs &amp; earn money</div>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($selectedRole) ?>">
                <div id="roleError" class="text-danger mt-2" style="font-size:.83rem; display:none;">
                    Please select a role to continue.
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Full Name</label>
                <input class="form-control" name="name" placeholder="Enter your full name" required
                    value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Email Address</label>
                <input class="form-control" name="email" placeholder="Enter your email" type="email" required
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Password</label>
                <div class="position-relative">
                    <input class="form-control pe-5" name="password" id="password" placeholder="Minimum 6 characters"
                        type="password" minlength="6" required>
                    <button type="button"
                        onclick="const i=document.getElementById('password');const icon=this.querySelector('i');i.type=i.type==='password'?'text':'password';icon.classList.toggle('bi-eye');icon.classList.toggle('bi-eye-slash');"
                        style="position:absolute;top:50%;right:10px;transform:translateY(-50%);background:none;border:none;padding:0;cursor:pointer;color:#6c757d;">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">Confirm Password</label>
                <div class="position-relative">
                    <input class="form-control pe-5" name="confirm_password" id="confirm_password"
                        placeholder="Re-enter your password" type="password" required>
                    <button type="button"
                        onclick="const i=document.getElementById('confirm_password');const icon=this.querySelector('i');i.type=i.type==='password'?'text':'password';icon.classList.toggle('bi-eye');icon.classList.toggle('bi-eye-slash');"
                        style="position:absolute;top:50%;right:10px;transform:translateY(-50%);background:none;border:none;padding:0;cursor:pointer;color:#6c757d;">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <div class="mb-3" style="font-size:.82rem;" class="text-muted2">
                <input type="checkbox" id="agreeTerms" required>
                <label for="agreeTerms" class="text-muted2 ms-1">
                    I agree to the
                    <a href="<?= BASE_URL ?>/terms.php" target="_blank" style="color:var(--brand);">Terms &amp;
                        Conditions</a>
                </label>
            </div>
            <button class="btn btn-brand w-100 py-2" onclick="return validateRole()">
                Create Account
            </button>

            <div class="text-center mt-3" style="font-size:.87rem;">
                Already have an account?
                <a href="<?= BASE_URL ?>/login.php" style="color:var(--brand);">Login →</a>
            </div>
        </form>

    <?php endif; ?>
</div>

<script>
    function selectRole(role) {
        document.getElementById('roleInput').value = role;
        document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
        event.currentTarget.classList.add('selected');
        document.getElementById('roleError').style.display = 'none';
    }

    function validateRole() {
        if (!document.getElementById('roleInput').value) {
            document.getElementById('roleError').style.display = 'block';
            return false;
        }
        return true;
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>