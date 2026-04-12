<?php
// Friendly error page
require_once __DIR__ . '/config/app.php';
if (session_status() === PHP_SESSION_NONE)
    session_start();

http_response_code(404);
$title = "Page Not Found";
require_once __DIR__ . '/includes/header.php';
?>

<section class="min-vh-100 d-flex align-items-center py-5">
    <div class="container text-center" style="max-width:520px;">
        <div style="font-size:6rem;line-height:1;margin-bottom:16px;">🔍</div>
        <h1 class="fw-bold mb-2" style="font-size:3rem;color:var(--brand);">404</h1>
        <h2 class="fw-bold mb-3" style="font-size:1.4rem;">Page Not Found</h2>
        <p class="text-muted2 mb-4" style="font-size:.95rem;line-height:1.7;">
            The page you're looking for doesn't exist or has been moved.
        </p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="<?= BASE_URL ?>/index.php" class="btn btn-brand rounded-pill px-4 py-2">
                🏠 Go Home
            </a>
            <?php
            $user = $_SESSION['user'] ?? null;
            if (is_array($user) && !empty($user['role'])):
                $dash = match ($user['role']) {
                    'admin' => BASE_URL . '/admin/dashboard.php',
                    'client' => BASE_URL . '/client/dashboard.php',
                    'freelancer' => BASE_URL . '/freelancer/dashboard.php',
                    default => BASE_URL . '/index.php'
                };
                ?>
                <a href="<?= $dash ?>" class="btn btn-outline-primary rounded-pill px-4 py-2">
                    Dashboard →
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>