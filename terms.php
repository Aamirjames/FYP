<?php
$title = "Terms & Conditions";
require_once __DIR__ . '/config/app.php';
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5" style="max-width:780px;">
    <h2 class="fw-bold mb-1">Terms &amp; Conditions</h2>
    <p class="text-muted2 mb-5" style="font-size:.87rem;">Last updated:
        <?= date('d F Y') ?>
    </p>

    <?php
    $sections = [
        [
            "1. Acceptance of Terms",
            "By registering and using Skill-Share Hub, you agree to be bound by these Terms and Conditions. If you do not agree, please do not use the platform."
        ],

        [
            "2. Platform Overview",
            "Skill-Share Hub is a local freelancing marketplace that connects clients with freelancers. The platform is admin-moderated to ensure a safe and trusted experience for all users."
        ],

        [
            "3. User Accounts",
            "You must register with accurate information. All accounts are subject to admin approval before activation. You are responsible for maintaining the confidentiality of your password and all activities under your account."
        ],

        [
            "4. Client Responsibilities",
            "Clients agree to post genuine job listings with accurate descriptions, budgets and deadlines. Clients must make payment promptly upon job completion and confirmation. Abuse of the reporting system is not permitted."
        ],

        [
            "5. Freelancer Responsibilities",
            "Freelancers agree to submit honest proposals and deliver work as described. Misrepresentation of skills or experience is grounds for account suspension. Freelancers must communicate professionally at all times."
        ],

        [
            "6. Payments",
            "All payments are recorded on the platform. Skill-Share Hub facilitates payment tracking but does not process actual financial transactions. Payment disputes are to be resolved between the client and freelancer directly."
        ],

        [
            "7. Prohibited Conduct",
            "Users must not post fraudulent jobs or proposals, harass other users, attempt to circumvent the platform, upload malicious content, or violate any applicable laws. Violations may result in immediate account suspension."
        ],

        [
            "8. Admin Moderation",
            "The platform admin reserves the right to approve, reject, or remove any job listing or user account at any time without prior notice if it violates these terms or platform guidelines."
        ],

        [
            "9. Intellectual Property",
            "All content uploaded to Skill-Share Hub remains the property of the respective user. By uploading content, you grant Skill-Share Hub a non-exclusive licence to display it on the platform."
        ],

        [
            "10. Limitation of Liability",
            "Skill-Share Hub is provided as a CS619 Final Year Project for educational purposes. The platform administrators are not liable for any financial losses, disputes, or damages arising from use of the platform."
        ],

        [
            "11. Changes to Terms",
            "These terms may be updated at any time. Continued use of the platform after changes constitutes acceptance of the new terms."
        ],

        [
            "12. Contact",
            "For any questions regarding these terms, please contact the platform administrator at admin@skillsharehub.com."
        ],
    ];
    foreach ($sections as [$heading, $text]): ?>
        <div class="mb-4">
            <h5 class="fw-bold mb-2" style="color:var(--brand);">
                <?= $heading ?>
            </h5>
            <p class="text-muted2 mb-0" style="line-height:1.75;">
                <?= $text ?>
            </p>
        </div>
    <?php endforeach; ?>

    <div class="mt-5 text-center">
        <?php
        $__tUser = $_SESSION['user'] ?? null;
        $__tLoggedIn = is_array($__tUser) && !empty($__tUser['role']);
        if (!$__tLoggedIn): ?>
            <a href="<?= BASE_URL ?>/register.php" class="btn btn-brand rounded-pill px-4">
                I Agree — Create Account
            </a>
        <?php else: ?>
            <?php
            $__tDash = match ($__tUser['role']) {
                'admin' => BASE_URL . '/admin/dashboard.php',
                'client' => BASE_URL . '/client/dashboard.php',
                default => BASE_URL . '/freelancer/dashboard.php'
            }; ?>
            <a href="<?= $__tDash ?>" class="btn btn-brand rounded-pill px-4">
                Go to Dashboard →
            </a>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>