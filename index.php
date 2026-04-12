<?php
$title = "Skill-Share Hub — Hire Talent or Find Work";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';

// Live platform stats
$totalFreelancers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='freelancer' AND status='active'")->fetchColumn();
$totalClients = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='client' AND status='active'")->fetchColumn();
$totalCompleted = (int) $pdo->query("SELECT COUNT(*) FROM job WHERE status='completed'")->fetchColumn();
$totalJobs = (int) $pdo->query("SELECT COUNT(*) FROM job WHERE status='approved'")->fetchColumn();
?>

<!-- ── HERO ─────────────────────────────────────────────────── -->
<section class="landing-hero">
    <div class="container">
        <div class="text-center mb-5">
            <div class="hero-tag mb-3">🇵🇰 Pakistan's Local Freelancing Platform</div>
            <h1 class="hero-title">
                The smarter way to<br>
                <span class="hero-highlight">hire</span> &amp;
                <span class="hero-highlight">get hired</span>
            </h1>
            <p class="hero-sub">
                Connect with verified local talent. Post jobs for free, receive proposals,
                and pay only when you're satisfied.
            </p>
        </div>

        <!-- Search Bar -->
        <div class="hero-search mb-5">
            <form action="<?= BASE_URL ?>/search.php" method="get" class="d-flex gap-2 align-items-center">
                <div class="hero-search-wrap flex-grow-1">
                    <span class="hero-search-icon">🔍</span>
                    <input type="text" name="q" class="hero-search-input"
                        placeholder="Search for jobs, skills or freelancers..."
                        value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                </div>
                <div class="hero-search-type">
                    <select name="type" class="form-select" style="border-radius:999px;min-width:140px;">
                        <option value="jobs">Find Jobs</option>
                        <option value="freelancers">Find Freelancers</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-brand rounded-pill px-4 py-2">Search</button>
            </form>
            <div class="hero-search-tags mt-2">
                <span class="text-muted2" style="font-size:.8rem;">Popular:</span>
                <?php
                $popular = ['PHP', 'Web Design', 'Data Entry', 'Content Writing', 'Python', 'WordPress', 'SEO'];
                foreach ($popular as $tag): ?>
                    <a href="<?= BASE_URL ?>/search.php?q=<?= urlencode($tag) ?>&type=jobs" class="search-tag">
                        <?= $tag ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Two CTA Paths -->
        <?php
        $__viewer = $_SESSION['user'] ?? null;
        $__loggedIn = is_array($__viewer) && !empty($__viewer['role']);
        ?>
        <div class="row g-4 justify-content-center mb-5">

            <!-- Hire Talent -->
            <div class="col-md-5">
                <div class="cta-card cta-client">
                    <div class="cta-icon">🏢</div>
                    <h3 class="cta-title">Hire Talent</h3>
                    <p class="cta-desc">
                        Post a job for free, review proposals from skilled freelancers,
                        and hire the perfect match for your project.
                    </p>
                    <ul class="cta-list">
                        <li>✅ Post jobs for free</li>
                        <li>✅ Review proposals & profiles</li>
                        <li>✅ Pay only when satisfied</li>
                        <li>✅ Rate & review freelancers</li>
                    </ul>
                    <?php if ($__loggedIn && ($__viewer['role'] ?? '') === 'client'): ?>
                        <a href="<?= BASE_URL ?>/client/dashboard.php"
                            class="btn btn-brand rounded-pill px-4 py-2 w-100 mt-3">
                            Go to Dashboard →
                        </a>
                    <?php elseif (!$__loggedIn): ?>
                        <a href="<?= BASE_URL ?>/register.php?role=client"
                            class="btn btn-brand rounded-pill px-4 py-2 w-100 mt-3">
                            Hire a Freelancer →
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Find Work -->
            <div class="col-md-5">
                <div class="cta-card cta-freelancer">
                    <div class="cta-icon">💼</div>
                    <h3 class="cta-title">Find Work</h3>
                    <p class="cta-desc">
                        Browse approved jobs, submit proposals with your best bid
                        and grow your freelancing career locally.
                    </p>
                    <ul class="cta-list">
                        <li>✅ Browse verified job listings</li>
                        <li>✅ Set your own hourly rate</li>
                        <li>✅ Build your portfolio</li>
                        <li>✅ Get rated & earn reviews</li>
                    </ul>
                    <?php if ($__loggedIn && ($__viewer['role'] ?? '') === 'freelancer'): ?>
                        <a href="<?= BASE_URL ?>/freelancer/dashboard.php"
                            class="btn btn-outline-primary rounded-pill px-4 py-2 w-100 mt-3">
                            Go to Dashboard →
                        </a>
                    <?php elseif (!$__loggedIn): ?>
                        <a href="<?= BASE_URL ?>/register.php?role=freelancer"
                            class="btn btn-outline-primary rounded-pill px-4 py-2 w-100 mt-3">
                            Start Freelancing →
                        </a>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Already have account -->
        <?php if (!$__loggedIn): ?>
            <div class="text-center mb-3">
                <span class="text-muted2" style="font-size:.9rem;">Already have an account? </span>
                <a href="<?= BASE_URL ?>/login.php" style="color:var(--brand); font-weight:600; font-size:.9rem;">Log
                    in →</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ── LIVE STATS ─────────────────────────────────────────────── -->
<section class="stats-section py-5">
    <div class="container">
        <div class="row g-4 text-center">
            <?php
            $stats = [
                [$totalFreelancers, 'Active Freelancers', '👨‍💻'],
                [$totalClients, 'Clients', '🏢'],
                [$totalJobs, 'Open Jobs', '📋'],
                [$totalCompleted, 'Jobs Completed', '✅'],
            ];
            foreach ($stats as [$val, $label, $icon]):
                ?>
                <div class="col-6 col-md-3 col-lg-2">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <?= $icon ?>
                        </div>
                        <div class="stat-number">
                            <?= number_format($val) ?>+
                        </div>
                        <div class="stat-label">
                            <?= $label ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── CATEGORIES ─────────────────────────────────────────────── -->
<section class="py-5">
    <div class="container">
        <h2 class="section-title text-center mb-2">Browse by Category</h2>
        <p class="text-muted2 text-center mb-5">Find the right skill for your next project</p>

        <div class="row g-3">
            <?php
            $cats = [
                ['💻', 'Web Development', 'PHP, JavaScript, React, Laravel'],
                ['📱', 'Mobile Development', 'Android, iOS, Flutter, React Native'],
                ['🎨', 'Graphic Design', 'Logo, Photoshop, Illustrator, Figma'],
                ['🖥️', 'UI/UX Design', 'Wireframes, Prototypes, Figma'],
                ['✍️', 'Writing', 'Content, Copywriting, SEO, Blogs'],
                ['📊', 'Data Entry', 'Excel, Spreadsheets, Research'],
                ['📣', 'Digital Marketing', 'SEO, Social Media, Email Marketing'],
                ['🎬', 'Video & Animation', 'Video Editing, Motion Graphics'],
                ['🐍', 'Python / AI', 'Python, Machine Learning, Data Science'],
                ['🔒', 'Cybersecurity', 'Ethical Hacking, Network Security'],
                ['📈', 'Business & Finance', 'Accounting, Business Writing'],
                ['🛒', 'E-Commerce', 'Shopify, WooCommerce, Amazon'],
            ];
            foreach ($cats as [$icon, $name, $desc]):
                ?>
                <div class="col-6 col-md-3">
                    <a href="<?= BASE_URL ?>/login.php" class="cat-card text-decoration-none d-block text-center">
                        <div class="cat-icon">
                            <?= $icon ?>
                        </div>
                        <div class="cat-name">
                            <?= $name ?>
                        </div>
                        <div class="cat-desc">
                            <?= $desc ?>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── HOW IT WORKS ───────────────────────────────────────────── -->
<section class="how-section py-5">
    <div class="container">
        <h2 class="section-title text-center mb-2">How It Works</h2>
        <p class="text-muted2 text-center mb-5">Simple. Fast. Trusted.</p>

        <div class="row g-4">

            <!-- For Clients -->
            <div class="col-md-6">
                <div class="how-card">
                    <div class="how-card-header">🏢 For Clients</div>
                    <div class="how-steps">
                        <div class="how-step">
                            <div class="how-step-num">1</div>
                            <div>
                                <div class="how-step-title">Post a Job</div>
                                <div class="how-step-desc">Describe your project, set a budget and deadline — it's free
                                </div>
                            </div>
                        </div>
                        <div class="how-step">
                            <div class="how-step-num">2</div>
                            <div>
                                <div class="how-step-title">Review Proposals</div>
                                <div class="how-step-desc">Freelancers submit bids — compare profiles, skills and rates
                                </div>
                            </div>
                        </div>
                        <div class="how-step">
                            <div class="how-step-num">3</div>
                            <div>
                                <div class="how-step-title">Hire & Collaborate</div>
                                <div class="how-step-desc">Hire your pick, chat via messaging, track job progress</div>
                            </div>
                        </div>
                        <div class="how-step">
                            <div class="how-step-num">4</div>
                            <div>
                                <div class="how-step-title">Pay & Review</div>
                                <div class="how-step-desc">Confirm completion, record payment and leave a rating</div>
                            </div>
                        </div>
                    </div>
                    <?php if ($__loggedIn && $__viewer['role'] === 'client'): ?>
                        <a href="<?= BASE_URL ?>/client/post_job.php" class="btn btn-brand rounded-pill px-4 mt-3">Post a
                            Job →</a>
                    <?php elseif (!$__loggedIn): ?>
                        <a href="<?= BASE_URL ?>/register.php?role=client"
                            class="btn btn-brand rounded-pill px-4 mt-3">Post a Job Free →</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- For Freelancers -->
            <div class="col-md-6">
                <div class="how-card">
                    <div class="how-card-header">💼 For Freelancers</div>
                    <div class="how-steps">
                        <div class="how-step">
                            <div class="how-step-num">1</div>
                            <div>
                                <div class="how-step-title">Create Your Profile</div>
                                <div class="how-step-desc">Add your skills, hourly rate, portfolio and profile picture
                                </div>
                            </div>
                        </div>
                        <div class="how-step">
                            <div class="how-step-num">2</div>
                            <div>
                                <div class="how-step-title">Browse Jobs</div>
                                <div class="how-step-desc">Filter jobs by category, budget and deadline</div>
                            </div>
                        </div>
                        <div class="how-step">
                            <div class="how-step-num">3</div>
                            <div>
                                <div class="how-step-title">Submit a Proposal</div>
                                <div class="how-step-desc">Send your best bid and cover letter to stand out</div>
                            </div>
                        </div>
                        <div class="how-step">
                            <div class="how-step-num">4</div>
                            <div>
                                <div class="how-step-title">Get Paid & Grow</div>
                                <div class="how-step-desc">Complete the job, receive payment and earn 5-star reviews
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if ($__loggedIn && $__viewer['role'] === 'freelancer'): ?>
                        <a href="<?= BASE_URL ?>/freelancer/browse_jobs.php"
                            class="btn btn-outline-primary rounded-pill px-4 mt-3">Browse Jobs →</a>
                    <?php elseif (!$__loggedIn): ?>
                        <a href="<?= BASE_URL ?>/register.php?role=freelancer"
                            class="btn btn-outline-primary rounded-pill px-4 mt-3">Start Earning →</a>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ── TRUST BANNER ───────────────────────────────────────────── -->
<section class="trust-section py-5 mb-4">
    <div class="container text-center">
        <h2 class="section-title mb-2">Why Skill-Share Hub?</h2>
        <p class="text-muted2 mb-5">Built with trust and transparency at its core</p>
        <div class="row g-4 justify-content-center">
            <?php
            $trusts = [
                ['🛡️', 'Admin Moderated', 'Every job and user is reviewed by our admin team for a safe experience'],
                ['💬', 'Built-in Messaging', 'Chat directly with clients or freelancers within the platform'],
                ['⭐', 'Verified Reviews', 'Honest ratings from real clients after every completed job'],
                ['🔒', 'Secure Platform', 'CSRF protection, hashed passwords and role-based access control'],
            ];
            foreach ($trusts as [$icon, $title, $desc]):
                ?>
                <div class="col-6 col-md-3">
                    <div class="trust-card">
                        <div style="font-size:2rem; margin-bottom:10px;">
                            <?= $icon ?>
                        </div>
                        <div class="trust-title">
                            <?= $title ?>
                        </div>
                        <div class="trust-desc">
                            <?= $desc ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>