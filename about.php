<?php
$title = "About Us";
require_once __DIR__ . '/config/app.php';
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5" style="max-width:820px;">

    <!-- Hero -->
    <div class="text-center mb-5">
        <h1 class="fw-bold mb-2">About <span style="color:var(--brand);">Skill-Share Hub</span></h1>
        <p class="text-muted2" style="font-size:1rem;max-width:560px;margin:0 auto;line-height:1.75;">
            A trusted local freelancing marketplace connecting skilled professionals with clients who need their
            expertise.
        </p>
    </div>

    <!-- Mission -->
    <div class="card card-soft p-4 mb-4">
        <h4 class="fw-bold mb-3">Our Mission</h4>
        <p class="text-muted2 mb-0" style="line-height:1.8;">
            Skill-Share Hub was built to bridge the gap between local talent and local opportunity. We believe skilled
            professionals deserve a platform that is simple to use, safe, and transparent. Clients deserve access to
            verified, quality freelancers without the complexity of large international platforms.
        </p>
    </div>

    <!-- Core Features (with emojis from Code 2) -->
    <div class="card card-soft p-4 mb-4">
        <h4 class="fw-bold mb-3"><span style="margin-right:8px;">⚡</span>What We Offer</h4>
        <div class="row g-3">
            <?php
            $features = [
                ['Verified Users', 'Every user and job is reviewed by our admin team before going live.'],
                ['Direct Communication', 'Clients and freelancers can communicate directly within the platform.'],
                ['Transparent Reviews', 'Honest review system so clients can make informed hiring decisions.'],
                ['Competitive Bidding', 'Freelancers submit competitive bids with cover letters for each job.'],
                ['Real-time Notifications', 'Stay informed at every step of your project.'],
                ['Admin Supervision', 'Full platform support and supervision by our admin team.'],
            ];
            $featureEmojis = ['🔒', '💬', '⭐', '📋', '🔔', '📊'];
            $i = 0;
            foreach ($features as [$title, $desc]):
                $emoji = $featureEmojis[$i++];
                ?>
                <div class="col-md-4">
                    <div class="p-3 rounded h-100" style="background:rgba(255,255,255,.03);border:1px solid var(--border);">
                        <div style="font-size:1.6rem;margin-bottom:8px;"><?= $emoji ?></div>
                        <div class="fw-bold mb-2" style="font-size:.95rem;"><?= $title ?></div>
                        <div class="text-muted2" style="font-size:.82rem;line-height:1.5;"><?= $desc ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Why Choose Us (unchanged, no emojis from Code 2) -->
    <div class="card card-soft p-4 mb-4">
        <h4 class="fw-bold mb-3">Why Choose Skill-Share Hub?</h4>
        <div class="row g-3">
            <?php
            $reasons = [
                ['Local Community', 'Support local talent and build meaningful professional relationships.'],
                ['Simple & Transparent', 'No hidden fees, straightforward pricing and clear terms.'],
                ['Secure Platform', 'Bank-level security with verified user profiles and safe payments.'],
                ['Professional Support', 'Our team is here to help resolve disputes and support your success.'],
            ];
            foreach ($reasons as [$title, $desc]): ?>
                <div class="col-md-6">
                    <div style="display: flex; gap: 12px;">
                        <div style="font-size:1.2rem;flex-shrink:0;color:var(--brand);">✓</div>
                        <div>
                            <div class="fw-bold" style="font-size:.95rem;margin-bottom:4px;"><?= $title ?></div>
                            <div class="text-muted2" style="font-size:.82rem;line-height:1.5;"><?= $desc ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Team (no emoji before heading) -->
    <div class="card card-soft p-4 mb-4">
        <h4 class="fw-bold mb-4">Founders</h4>
        <div class="row g-4">
            <?php
            $team = [
                ['AK', 'Aamir Khan', 'Founder & Lead Developer'],
                ['QA', 'Qurrat Ul Ain', 'Co-Founder & Developer'],
            ];
            foreach ($team as [$initials, $name, $role]): ?>
                <div class="col-md-6">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:56px;height:56px;border-radius:50%;background:rgba(34,211,238,.12);
                                    display:flex;align-items:center;justify-content:center;
                                    font-weight:800;color:var(--brand);font-size:1rem;
                                    border:2px solid rgba(34,211,238,.3);">
                            <?= $initials ?>
                        </div>
                        <div>
                            <div class="fw-bold"><?= $name ?></div>
                            <div style="font-size:.8rem;color:var(--brand);font-weight:600;"><?= $role ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-4 pt-3" style="border-top:1px solid var(--border);">
            <div class="row g-2 text-muted2" style="font-size:.85rem;">
                <div class="col-md-4">Special thanks to: Komal Saleem</div>
            </div>
        </div>
    </div>

    <!-- CTA (with arrow emojis from Code 2) -->
    <div class="text-center mt-4">
        <?php
        $__aUser = $_SESSION['user'] ?? null;
        $__aLoggedIn = is_array($__aUser) && !empty($__aUser['role']);
        if (!$__aLoggedIn): ?>
            <a href="<?= BASE_URL ?>/register.php" class="btn btn-brand rounded-pill px-5 py-2 me-2">
                Join Now →
            </a>
        <?php else: ?>
            <?php
            $__dash = match ($__aUser['role']) {
                'admin' => BASE_URL . '/admin/dashboard.php',
                'client' => BASE_URL . '/client/dashboard.php',
                default => BASE_URL . '/freelancer/dashboard.php'
            }; ?>
            <a href="<?= $__dash ?>" class="btn btn-brand rounded-pill px-5 py-2 me-2">
                Go to Dashboard →
            </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline-primary rounded-pill px-5 py-2">
            Home
        </a>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>