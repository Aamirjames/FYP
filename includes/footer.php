<?php
$__fUser = $_SESSION['user'] ?? null;
$__fLoggedIn = is_array($__fUser) && !empty($__fUser['role']);
$__fRole = $__fUser['role'] ?? '';
$__fDash = $__fLoggedIn ? (
    $__fRole === 'admin' ? BASE_URL . '/admin/dashboard.php' :
    ($__fRole === 'client' ? BASE_URL . '/client/dashboard.php' :
        BASE_URL . '/freelancer/dashboard.php')
) : '';
?>
<footer class="site-footer mt-5">

    <div class="footer-main py-5">
        <div class="container">
            <div class="row g-4">

                <!-- ── Brand Column ── -->
                <div class="<?= (!$__fLoggedIn) ? 'col-lg-4' : 'col-lg-5' ?> col-md-12">
                    <div class="footer-brand mb-2">Skill-Share Hub</div>
                    <p class="text-muted2 mb-3" style="font-size:.88rem;line-height:1.7;">
                        A local freelancing marketplace connecting skilled professionals
                        with clients. Post jobs, hire talent and grow your career.
                        All in one trusted platform.
                    </p>
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="footer-badge">🔒 Secure Payments</span>
                        <span class="footer-badge">✅ Verified Freelancers</span>
                        <span class="footer-badge">🛡️ Admin Moderated</span>
                    </div>
                </div>

                <!-- ── Role-Based Navigation ── -->
                <?php if (!$__fLoggedIn): ?>
                    <!-- if as  GUEST 3 columns -->
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="footer-heading">For Clients</div>
                        <ul class="footer-links">
                            <li><a href="<?= BASE_URL ?>/register.php?role=client">Post a Job</a></li>
                            <li><a href="<?= BASE_URL ?>/register.php?role=client">Create Account</a></li>
                            <li><a href="<?= BASE_URL ?>/login.php">Login</a></li>
                        </ul>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="footer-heading">For Freelancers</div>
                        <ul class="footer-links">
                            <li><a href="<?= BASE_URL ?>/register.php?role=freelancer">Join as Freelancer</a></li>
                            <li><a href="<?= BASE_URL ?>/login.php">Login</a></li>
                        </ul>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="footer-heading">Popular Skills</div>
                        <ul class="footer-links">
                            <li><a href="<?= BASE_URL ?>/register.php">PHP / Laravel</a></li>
                            <li><a href="<?= BASE_URL ?>/register.php">JavaScript / React</a></li>
                            <li><a href="<?= BASE_URL ?>/register.php">UI/UX Design</a></li>
                            <li><a href="<?= BASE_URL ?>/register.php">WordPress</a></li>
                            <li><a href="<?= BASE_URL ?>/register.php">Content Writing</a></li>
                            <li><a href="<?= BASE_URL ?>/register.php">Data Entry</a></li>
                        </ul>
                    </div>

                <?php elseif ($__fRole === 'client'): ?>
                    <!-- if as a CLIENT 2 columns -->
                    <div class="col-lg-3 col-md-6 col-6">
                        <div class="footer-heading">My Account</div>
                        <ul class="footer-links">
                            <li><a href="<?= BASE_URL ?>/client/dashboard.php">Dashboard</a></li>
                            <li><a href="<?= BASE_URL ?>/client/post_job.php">Post a Job</a></li>
                            <li><a href="<?= BASE_URL ?>/client/my_jobs.php">My Jobs</a></li>
                            <li><a href="<?= BASE_URL ?>/client/active_jobs.php">Active Jobs</a></li>
                        </ul>
                    </div>
                    <div class="col-lg-2 col-md-6 col-6">
                        <div class="footer-heading">Quick Links</div>
                        <ul class="footer-links">
                            <li><a href="<?= BASE_URL ?>/client/browse_freelancers.php">Browse Freelancers</a></li>
                            <li><a href="<?= BASE_URL ?>/client/profile.php">My Profile</a></li>
                            <li><a href="<?= BASE_URL ?>/notifications.php">Notifications</a></li>
                            <li><a href="<?= BASE_URL ?>/logout.php">Logout</a></li>
                        </ul>
                    </div>

                <?php elseif ($__fRole === 'freelancer'): ?>
                    <!--if as a FREELANCER 2 columns -->
                    <div class="col-lg-3 col-md-6 col-6">
                        <div class="footer-heading">My Account</div>
                        <ul class="footer-links">
                            <li><a href="<?= BASE_URL ?>/freelancer/dashboard.php">Dashboard</a></li>
                            <li><a href="<?= BASE_URL ?>/freelancer/browse_jobs.php">Browse Jobs</a></li>
                            <li><a href="<?= BASE_URL ?>/freelancer/my_jobs.php">My Jobs</a></li>
                            <li><a href="<?= BASE_URL ?>/freelancer/my_proposals.php">My Proposals</a></li>
                        </ul>
                    </div>
                    <div class="col-lg-2 col-md-6 col-6">
                        <div class="footer-heading">Quick Links</div>
                        <ul class="footer-links">
                            <li><a href="<?= BASE_URL ?>/freelancer/my_reviews.php">My Reviews</a></li>
                            <li><a href="<?= BASE_URL ?>/freelancer/profile.php">My Profile</a></li>
                            <li><a href="<?= BASE_URL ?>/notifications.php">Notifications</a></li>
                            <li><a href="<?= BASE_URL ?>/logout.php">Logout</a></li>
                        </ul>
                    </div>

                <?php elseif ($__fRole === 'admin'): ?>
                    <!--if as  ADMIN 2 columns -->
                    <div class="col-lg-3 col-md-6 col-6">
                        <div class="footer-heading">Admin Panel</div>
                        <ul class="footer-links">
                            <li><a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
                            <li><a href="<?= BASE_URL ?>/admin/users.php">Manage Users</a></li>
                            <li><a href="<?= BASE_URL ?>/admin/jobs.php">Manage Jobs</a></li>
                            <li><a href="<?= BASE_URL ?>/admin/reports.php">Reports</a></li>
                        </ul>
                    </div>
                    <div class="col-lg-2 col-md-6 col-6">
                        <div class="footer-heading">Quick Links</div>
                        <ul class="footer-links">
                            <li><a href="<?= BASE_URL ?>/about.php">About Platform</a></li>
                            <li><a href="<?= BASE_URL ?>/notifications.php">Notifications</a></li>
                            <li><a href="<?= BASE_URL ?>/logout.php">Logout</a></li>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- ── Company ── -->
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="footer-heading">Company</div>
                    <ul class="footer-links">
                        <li><a href="<?= BASE_URL ?>/about.php">About Us</a></li>
                        <li><a href="<?= BASE_URL ?>/terms.php">Terms &amp; Conditions</a></li>
                        <?php if ($__fLoggedIn): ?>
                            <li><a href="<?= $__fDash ?>">My Dashboard</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Bar -->
    <div class="footer-bottom py-3">
        <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <div style="font-size:.83rem;" class="text-muted2">
                &copy;
                <?= date('Y') ?> <strong style="color:var(--brand);">Skill-Share Hub</strong>
                &mdash; All rights reserved.
            </div>
            <div class="d-flex gap-3" style="font-size:.83rem;">
                <?php if (!$__fLoggedIn): ?>
                    <a href="<?= BASE_URL ?>/login.php" class="footer-bottom-link">Login</a>
                    <a href="<?= BASE_URL ?>/register.php" class="footer-bottom-link">Register</a>
                <?php else: ?>
                    <a href="<?= $__fDash ?>" class="footer-bottom-link">Dashboard</a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/index.php" class="footer-bottom-link">Home</a>
                <a href="<?= BASE_URL ?>/about.php" class="footer-bottom-link">About</a>
            </div>
        </div>
    </div>

</footer>


<script src="<?= BASE_URL ?>/js/app.js"></script>
</body>

</html>