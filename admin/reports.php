<?php
// SSH28: Admin Reports & Statistics
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');

// ── Platform Overview ────────────────────────────────────────
$totalClients = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn();
$totalFreelancers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='freelancer'")->fetchColumn();
$pendingUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status='pending'")->fetchColumn();
$blockedUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status='blocked'")->fetchColumn();

$totalJobs = (int) $pdo->query("SELECT COUNT(*) FROM job")->fetchColumn();
$pendingJobs = (int) $pdo->query("SELECT COUNT(*) FROM job WHERE status='pending'")->fetchColumn();
$approvedJobs = (int) $pdo->query("SELECT COUNT(*) FROM job WHERE status='approved'")->fetchColumn();
$inProgressJobs = (int) $pdo->query("SELECT COUNT(*) FROM job WHERE status='in_progress'")->fetchColumn();
$completedJobs = (int) $pdo->query("SELECT COUNT(*) FROM job WHERE status='completed'")->fetchColumn();
$rejectedJobs = (int) $pdo->query("SELECT COUNT(*) FROM job WHERE status='rejected'")->fetchColumn();
$reportedJobs = (int) $pdo->query("SELECT COUNT(*) FROM job WHERE is_reported=1")->fetchColumn();

$totalProposals = (int) $pdo->query("SELECT COUNT(*) FROM proposals")->fetchColumn();
$totalMessages = (int) $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
$totalReviews = (int) $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
$avgRating = (float) $pdo->query("SELECT COALESCE(AVG(rating),0) FROM reviews")->fetchColumn();

// ── Payment Stats ────────────────────────────────────────────
$totalPayments = (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE status='confirmed'")->fetchColumn();
$totalPaidOut = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='confirmed'")->fetchColumn();

// ── Jobs by Category ─────────────────────────────────────────
$jobsByCategory = $pdo->query("
    SELECT c.category_name, COUNT(j.job_id) AS total
    FROM category c
    LEFT JOIN job j ON j.category_id = c.category_id
    GROUP BY c.category_id
    ORDER BY total DESC
")->fetchAll();
// Filter for screen display - only categories with jobs
$jobsByCategoryActive = array_filter($jobsByCategory, fn($c) => (int)$c['total'] > 0);

// ── Top Freelancers (by completed jobs + avg rating) ─────────
$topFreelancers = $pdo->query("
    SELECT u.user_id, u.name, u.email, u.profile_image,
           COUNT(DISTINCT j.job_id)   AS completed_jobs,
           COALESCE(AVG(r.rating), 0) AS avg_rating,
           COUNT(DISTINCT r.review_id) AS review_count
    FROM users u
    LEFT JOIN job j     ON j.hired_freelancer_id = u.user_id AND j.status = 'completed'
    LEFT JOIN reviews r ON r.freelancer_id = u.user_id
    WHERE u.role = 'freelancer' AND u.status = 'active'
    GROUP BY u.user_id
    ORDER BY completed_jobs DESC, avg_rating DESC
    LIMIT 5
")->fetchAll();

// ── Recent Registrations ─────────────────────────────────────
$recentUsers = $pdo->query("
    SELECT name, email, role, status, created_at
    FROM users
    WHERE role != 'admin'
    ORDER BY created_at DESC
    LIMIT 6
")->fetchAll();

// ── Recent Payments ──────────────────────────────────────────
$recentPayments = $pdo->query("
    SELECT p.amount, p.method, p.paid_at,
           uc.name AS client_name,
           uf.name AS freelancer_name,
           j.title AS job_title
    FROM payments p
    JOIN users uc ON uc.user_id = p.client_id
    JOIN users uf ON uf.user_id = p.freelancer_id
    JOIN job   j  ON j.job_id   = p.job_id
    WHERE p.status = 'confirmed'
    ORDER BY p.paid_at DESC
    LIMIT 5
")->fetchAll();

// ── Chart Data ──────────────────────────────────────────────
// Jobs by status for doughnut chart
$jobStatusData = [
    'Pending'     => $pendingJobs,
    'Approved'    => $approvedJobs,
    'In Progress' => $inProgressJobs,
    'Completed'   => $completedJobs,
    'Rejected'    => $rejectedJobs,
];

// Registrations last 6 months
$regByMonth = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%b %Y') AS month,
           COUNT(*) AS total
    FROM users
    WHERE role != 'admin'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY MIN(created_at)
")->fetchAll();

// initials helper
function initials($name)
{
    $parts = preg_split('/\s+/', trim($name));
    $first = strtoupper(substr($parts[0] ?? 'U', 0, 1));
    $second = strtoupper(substr($parts[1] ?? '', 0, 1));
    return $first . ($second ?: '');
}

$title = "Admin Reports";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1 fw-bold">📊 Platform Reports</h3>
            <div class="text-muted2" style="font-size:.87rem;">
                Generated on <?= date('d M Y, h:i A') ?>
            </div>
        </div>
    </div>

    <!-- ── Overview Stats (screen only, hidden on print) ── -->
    <div class="no-print">
    <h5 class="fw-bold mb-3">👥 Users</h5>
    <div class="row g-3 mb-4">
        <?php
        $userStats = [
            ['Clients', $totalClients, '#22d3ee'],
            ['Freelancers', $totalFreelancers, '#a78bfa'],
            ['Pending', $pendingUsers, '#fbbf24'],
            ['Blocked', $blockedUsers, '#f87171'],
        ];
        foreach ($userStats as [$label, $val, $color]): ?>
            <div class="col-6 col-md-3">
                <div class="card card-soft p-3 text-center">
                    <div style="font-size:2rem;font-weight:800;color:<?= $color ?>;"><?= $val ?></div>
                    <div class="text-muted2 mt-1"><?= $label ?></div>
                </div>
            </div>

        <?php endforeach; ?>
    </div>

    <h5 class="fw-bold mb-3">💼 Jobs</h5>
    <div class="row g-3 mb-4">
        <?php
        $jobStats = [
            ['Total', $totalJobs, '#22d3ee'],
            ['Pending', $pendingJobs, '#fbbf24'],
            ['Approved', $approvedJobs, '#4ade80'],
            ['In Progress', $inProgressJobs, '#22d3ee'],
            ['Completed', $completedJobs, '#4ade80'],
            ['Rejected', $rejectedJobs, '#f87171'],
            ['Reported', $reportedJobs, '#fb923c'],
        ];
        foreach ($jobStats as [$label, $val, $color]): ?>
            <div class="col-6 col-md-3">
                <div class="card card-soft p-3 text-center">
                    <div style="font-size:2rem;font-weight:800;color:<?= $color ?>;"><?= $val ?></div>
                    <div class="text-muted2 mt-1"><?= $label ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <h5 class="fw-bold mb-3">📈 Activity & Payments</h5>
    <div class="row g-3 mb-5">
        <?php
        $actStats = [
            ['Proposals', $totalProposals, '#a78bfa'],
            ['Messages Sent', $totalMessages, '#22d3ee'],
            ['Reviews', $totalReviews, '#fbbf24'],
            ['Avg Rating', number_format($avgRating, 1) . ' ★', '#fbbf24'],
            ['Payments Made', $totalPayments, '#4ade80'],
            ['Total Paid Out', 'PKR ' . number_format($totalPaidOut, 0), '#4ade80'],
        ];
        foreach ($actStats as [$label, $val, $color]): ?>
            <div class="col-6 col-md-4">
                <div class="card card-soft p-3 text-center">
                    <div style="font-size:1.6rem;font-weight:800;color:<?= $color ?>;"><?= $val ?></div>
                    <div class="text-muted2 mt-1"><?= $label ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    </div><!-- end no-print stats -->

    <!-- ── Top Freelancers (screen only) ── -->
    <div class="card card-soft p-4 mb-4 no-print">
        <h5 class="fw-bold mb-3">🏆 Top Freelancers</h5>
        <?php if (!$topFreelancers): ?>
            <div class="text-muted2">No completed jobs yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Freelancer</th>
                            <th>Email</th>
                            <th>Completed Jobs</th>
                            <th>Avg Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topFreelancers as $i => $f): ?>
                            <tr>
                                <td class="fw-bold" style="color:var(--brand);"><?= $i + 1 ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($f['name']) ?></td>
                                <td class="text-muted2" style="font-size:.85rem;"><?= htmlspecialchars($f['email']) ?></td>
                                <td>
                                    <span style="color:#4ade80;font-weight:700;">
                                        <?= (int)$f['completed_jobs'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($f['review_count'] > 0): ?>
                                        <span style="color:#fbbf24;font-weight:700;">
                                            ★ <?= number_format((float)$f['avg_rating'], 1) ?>
                                        </span>
                                        <span class="text-muted2" style="font-size:.8rem;">
                                            (<?= (int)$f['review_count'] ?> reviews)
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted2" style="font-size:.82rem;">No reviews</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── Charts (screen only) ── -->
    <div class="row g-4 mb-5" id="chartSection">

        <!-- Jobs by Status Doughnut -->
        <div class="col-md-4">
            <div class="card card-soft p-4 h-100">
                <h5 class="fw-bold mb-4">📊 Jobs by Status</h5>
                <div style="position:relative; height:240px;">
                    <canvas id="jobStatusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Jobs by Category Bar -->
        <div class="col-md-4">
            <div class="card card-soft p-4 h-100">
                <h5 class="fw-bold mb-4">📂 Jobs by Category</h5>
                <div style="position:relative; height:240px;">
                    <canvas id="jobCategoryChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Registrations Line Chart -->
        <div class="col-md-4">
            <div class="card card-soft p-4 h-100">
                <h5 class="fw-bold mb-4">👥 New Users (6 Months)</h5>
                <div style="position:relative; height:240px;">
                    <canvas id="regChart"></canvas>
                </div>
            </div>
        </div>

    </div>

    <!-- ── Print-Only Data Tables (hidden on screen) ── -->
    <div class="print-only mb-5">
        <h5 class="fw-bold mb-3">🏆 Top Freelancers</h5>
        <table>
            <thead><tr><th>#</th><th>Freelancer</th><th>Email</th><th>Completed Jobs</th><th>Avg Rating</th></tr></thead>
            <tbody>
                <?php foreach ($topFreelancers as $i => $f): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($f['name']) ?></td>
                        <td><?= htmlspecialchars($f['email']) ?></td>
                        <td><?= (int)$f['completed_jobs'] ?></td>
                        <td><?= $f['review_count'] > 0 ? number_format((float)$f['avg_rating'],1).'/5 ('.((int)$f['review_count']).' reviews)' : 'No reviews' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$topFreelancers): ?><tr><td colspan="5">No completed jobs yet.</td></tr><?php endif; ?>
            </tbody>
        </table>

        <h5 class="fw-bold mb-3">📊 Jobs by Status</h5>
        <table>
            <thead><tr><th>Status</th><th>Count</th></tr></thead>
            <tbody>
                <?php foreach ($jobStatusData as $status => $count): ?>
                    <tr><td><?= htmlspecialchars($status) ?></td><td><?= $count ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h5 class="fw-bold mb-3 mt-4">📂 Jobs by Category</h5>
        <table>
            <thead><tr><th>Category</th><th>Total Jobs</th></tr></thead>
            <tbody>
                <?php foreach ($jobsByCategory as $cat): ?>
                    <?php if ((int)$cat['total'] > 0): ?>
                    <tr>
                        <td><?= htmlspecialchars($cat['category_name']) ?></td>
                        <td><?= (int)$cat['total'] ?></td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h5 class="fw-bold mb-3 mt-4">👥 New Registrations (Last 6 Months)</h5>
        <table>
            <thead><tr><th>Month</th><th>New Users</th></tr></thead>
            <tbody>
                <?php foreach ($regByMonth as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['month']) ?></td>
                        <td><?= (int)$r['total'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- ── Recent Registrations ── -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card card-soft p-4">
                <h5 class="fw-bold mb-3">🆕 Recent Registrations</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $u): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold" style="font-size:.88rem;"><?= htmlspecialchars($u['name']) ?>
                                        </div>
                                        <div class="text-muted2" style="font-size:.76rem;">
                                            <?= htmlspecialchars($u['email']) ?>
                                        </div>
                                    </td>
                                    <td class="text-muted2" style="font-size:.85rem;"><?= htmlspecialchars($u['role']) ?>
                                    </td>
                                    <td>
                                        <?php $st = preg_replace('/[^a-z_]/', '', strtolower(trim($u['status']))); ?>
                                        <span class="status-badge status-<?= $st ?>" style="font-size:.72rem;">
                                            <?= htmlspecialchars($u['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted2" style="font-size:.78rem;">
                                        <?= date('d M Y', strtotime($u['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$recentUsers): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted2">No users yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ── Recent Payments ── -->
        <div class="col-md-6">
            <div class="card card-soft p-4">
                <h5 class="fw-bold mb-3">💰 Recent Payments</h5>
                <?php if (!$recentPayments): ?>
                    <div class="text-muted2 py-3 text-center">No payments yet.</div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($recentPayments as $p): ?>
                            <div class="p-3 rounded"
                                style="background:rgba(74,222,128,.05);border:1px solid rgba(74,222,128,.15);">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold" style="font-size:.88rem;"><?= htmlspecialchars($p['job_title']) ?>
                                        </div>
                                        <div class="text-muted2" style="font-size:.78rem;">
                                            <?= htmlspecialchars($p['client_name']) ?> →
                                            <?= htmlspecialchars($p['freelancer_name']) ?>
                                            &bull; <?= htmlspecialchars($p['method']) ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div style="color:#4ade80;font-weight:800;font-size:.93rem;">
                                            PKR <?= number_format((float) $p['amount'], 0) ?>
                                        </div>
                                        <div class="text-muted2" style="font-size:.75rem; text-align:right;">
                                            <?= date('d M Y', strtotime($p['paid_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.0/jspdf.plugin.autotable.min.js"></script>
<script>
const chartDefaults = {
    plugins: { legend: { labels: { color: '#94a3b8', font: { size: 11 } } } },
    scales:  { x: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,.05)' } },
               y: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,.05)' } } }
};

// Jobs by Status — Doughnut
new Chart(document.getElementById('jobStatusChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_keys($jobStatusData)) ?>,
        datasets: [{
            data: <?= json_encode(array_values($jobStatusData)) ?>,
            backgroundColor: ['#fbbf24','#22d3ee','#a78bfa','#4ade80','#f87171'],
            borderWidth: 0,
        }]
    },
    options: {
        cutout: '65%',
        plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8', font: { size: 11 }, padding: 12 } } }
    }
});

// Jobs by Category — Horizontal Bar
new Chart(document.getElementById('jobCategoryChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($jobsByCategory, 'category_name')) ?>,
        datasets: [{
            label: 'Jobs',
            data: <?= json_encode(array_column($jobsByCategory, 'total')) ?>,
            backgroundColor: 'rgba(34,211,238,.7)',
            borderRadius: 6,
        }]
    },
    options: {
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,.05)' } },
            y: { ticks: { color: '#94a3b8', font: { size: 10 } }, grid: { display: false } }
        }
    }
});

// Registrations Line Chart
new Chart(document.getElementById('regChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($regByMonth, 'month')) ?>,
        datasets: [{
            label: 'New Users',
            data: <?= json_encode(array_column($regByMonth, 'total')) ?>,
            borderColor: '#a78bfa',
            backgroundColor: 'rgba(167,139,250,.15)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#a78bfa',
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color: '#64748b', font:{size:10} }, grid: { color: 'rgba(255,255,255,.05)' } },
            y: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,.05)' }, beginAtZero: true }
        }
    }
});
</script>

<style>
@media screen {
    .print-only { display: none !important; }
}
@media print {
    .no-print { display: none !important; }
}
@media print {
    /* Hide navigation, charts, buttons */
    .navbar-skillhub,
    .site-footer,
    .btn, button,
    #chartSection,
    .notif-badge,
    .notif-dropdown { display: none !important; }

    /* Show print data tables */
    .print-only { display: block !important; }

    /* Page */
    @page { margin: 1.5cm; size: A4; }
    body { background: #ffffff !important; color: #000000 !important; font-size: 12px !important; }
    .container { max-width: 100% !important; padding: 8px !important; margin: 0 !important; }

    /* Cards become plain boxes */
    .card, .card-soft {
        background: #ffffff !important;
        border: 1px solid #cccccc !important;
        box-shadow: none !important;
        break-inside: avoid !important;
        page-break-inside: avoid !important;
        margin-bottom: 12px !important;
        padding: 12px !important;
    }

    /* Text */
    h3, h4, h5, h6 { color: #000000 !important; }
    .text-muted2, .text-muted, [style*="color:var(--muted)"] { color: #555555 !important; }
    [style*="color:var(--brand)"], [style*="color:#22d3ee"] { color: #000000 !important; font-weight: bold !important; }
    [style*="color:#4ade80"] { color: #000000 !important; }
    [style*="color:#fbbf24"] { color: #000000 !important; }
    [style*="color:#f87171"] { color: #000000 !important; }
    [style*="color:#a78bfa"] { color: #000000 !important; }

    /* Stat numbers */
    [style*="font-size:2rem"][style*="font-weight:800"],
    [style*="font-size:1.8rem"][style*="font-weight:800"],
    [style*="font-size:1.6rem"][style*="font-weight:800"],
    [style*="font-size:1.5rem"][style*="font-weight:800"] {
        color: #000000 !important;
        font-size: 1.2rem !important;
    }

    /* Tables */
    table { border-collapse: collapse !important; width: 100% !important; }
    th, td {
        border: 1px solid #cccccc !important;
        padding: 5px 8px !important;
        color: #000000 !important;
        font-size: 11px !important;
        background: #ffffff !important;
    }
    th { background: #f5f5f5 !important; font-weight: bold !important; }

    /* Status badges */
    .status-badge {
        border: 1px solid #999999 !important;
        color: #333333 !important;
        background: #eeeeee !important;
    }

    /* Row gutters */
    .row { margin: 0 !important; }
    .col-md-3, .col-md-4, .col-md-6, .col-6, .col-md-2 {
        float: left !important;
        padding: 4px !important;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>