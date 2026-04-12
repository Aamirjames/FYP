<?php
// freelancer/my_proposals.php — Freelancer views all submitted proposals
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';

requireRole('freelancer');

$freelancerId = (int) $_SESSION['user']['id'];

// ── Withdraw Proposal ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'withdraw') {
    require_once __DIR__ . '/../includes/csrf.php';
    csrf_verify();
    $proposalId = (int) ($_POST['proposal_id'] ?? 0);
    // Only allow withdraw if status is pending
    $pdo->prepare("
        DELETE FROM proposals
        WHERE proposal_id=? AND freelancer_id=? AND status='pending'
    ")->execute([$proposalId, $freelancerId]);
    header("Location: " . BASE_URL . "/freelancer/my_proposals.php?msg=withdrawn");
    exit;
}

$filter = $_GET['status'] ?? 'all';
$allowed = ['all', 'pending', 'accepted', 'rejected'];
if (!in_array($filter, $allowed))
    $filter = 'all';

$sql = "
    SELECT p.proposal_id, p.bid_amount, p.cover_letter, p.status, p.created_at,
           j.job_id, j.title AS job_title, j.budget, j.deadline, j.status AS job_status,
           u.name AS client_name
    FROM proposals p
    JOIN job   j ON j.job_id   = p.job_id
    JOIN users u ON u.user_id  = j.client_id
    WHERE p.freelancer_id = ?
";
$params = [$freelancerId];

if ($filter !== 'all') {
    $sql .= " AND p.status = ?";
    $params[] = $filter;
}
$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$proposals = $stmt->fetchAll();

// Summary counts
$counts = ['all' => 0, 'pending' => 0, 'accepted' => 0, 'rejected' => 0];
$countStmt = $pdo->prepare("
    SELECT status, COUNT(*) AS total FROM proposals
    WHERE freelancer_id = ? GROUP BY status
");
$countStmt->execute([$freelancerId]);
foreach ($countStmt->fetchAll() as $row) {
    $counts[$row['status']] = (int) $row['total'];
    $counts['all'] += (int) $row['total'];
}

$title = "My Proposals";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0 fw-bold">My Proposals</h3>
    </div>

    <?php if (($_GET['msg'] ?? '') === 'withdrawn'): ?>
        <div class="alert alert-success mb-3">✅ Proposal withdrawn successfully.</div>
    <?php endif; ?>

    <!-- Summary Stats -->
    <div class="row g-3 mb-4">
        <?php
        $statItems = [
            ['all', 'Total', '#22d3ee'],
            ['pending', 'Pending', '#fbbf24'],
            ['accepted', 'Accepted', '#4ade80'],
            ['rejected', 'Rejected', '#f87171'],
        ];
        foreach ($statItems as [$key, $label, $color]): ?>
            <div class="col-6 col-md-3">
                <a href="?status=<?= $key ?>" class="card card-soft p-3 text-center text-decoration-none d-block
               <?= $filter === $key ? 'border-brand' : '' ?>">
                    <div style="font-size:1.8rem;font-weight:800;color:<?= $color ?>;">
                        <?= $counts[$key] ?>
                    </div>
                    <div class="text-muted2 mt-1" style="font-size:.85rem;">
                        <?= $label ?>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Filter Tabs -->
    <div class="d-flex gap-2 mb-4 flex-wrap">
        <?php foreach ($statItems as [$key, $label, $color]): ?>
            <a href="?status=<?= $key ?>"
                class="btn btn-sm rounded-pill <?= $filter === $key ? 'btn-brand' : 'btn-outline-primary' ?>">
                <?= $label ?> (
                <?= $counts[$key] ?>)
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Proposals List -->
    <?php if (!$proposals): ?>
        <div class="card card-soft p-4 text-center text-muted2 py-5">
            <div style="font-size:2.5rem;">📋</div>
            <div class="mt-2 fw-bold">No proposals found</div>
            <div class="text-muted2 mt-1" style="font-size:.87rem;">
                <?= $filter !== 'all' ? "No {$filter} proposals." : "You haven't submitted any proposals yet." ?>
            </div>
            <a href="<?= BASE_URL ?>/freelancer/browse_jobs.php" class="btn btn-brand rounded-pill px-4 mt-3">Browse Jobs
                →</a>
        </div>
    <?php else: ?>
        <div class="d-flex flex-column gap-3">
            <?php foreach ($proposals as $p):
                $statusColors = [
                    'pending' => ['#fbbf24', 'rgba(251,191,36,.1)', 'rgba(251,191,36,.25)'],
                    'accepted' => ['#4ade80', 'rgba(74,222,128,.1)', 'rgba(74,222,128,.25)'],
                    'rejected' => ['#f87171', 'rgba(248,113,113,.1)', 'rgba(248,113,113,.25)'],
                ];
                [$clr, $bg, $border] = $statusColors[$p['status']] ?? ['#94a3b8', 'rgba(255,255,255,.05)', 'var(--border)'];
                ?>
                <div class="card card-soft p-4" style="border-left:4px solid <?= $clr ?>;">
                    <div class="row align-items-start g-3">

                        <!-- Job Info -->
                        <div class="col-md-7">
                            <div class="fw-bold mb-1" style="font-size:1rem;">
                                <?= htmlspecialchars($p['job_title']) ?>
                            </div>
                            <div class="text-muted2 mb-2" style="font-size:.83rem;">
                                👤
                                <?= htmlspecialchars($p['client_name']) ?>
                                &bull; 📅 Deadline:
                                <?= htmlspecialchars($p['deadline']) ?>
                                &bull; 💰 Job Budget: PKR
                                <?= number_format((float) $p['budget'], 0) ?>
                            </div>
                            <?php if ($p['cover_letter']): ?>
                                <div class="text-muted2" style="font-size:.85rem;line-height:1.55;">
                                    "
                                    <?= nl2br(htmlspecialchars(
                                        mb_strlen($p['cover_letter']) > 150
                                        ? mb_substr($p['cover_letter'], 0, 150) . '…'
                                        : $p['cover_letter']
                                    )) ?>"
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Proposal Details -->
                        <div class="col-md-3 text-md-center">
                            <div style="font-size:1.3rem;font-weight:800;color:var(--brand);">
                                PKR
                                <?= number_format((float) $p['bid_amount'], 0) ?>
                            </div>
                            <div class="text-muted2" style="font-size:.78rem;">Your Bid</div>
                            <div class="mt-2">
                                <span class="status-badge status-<?= htmlspecialchars($p['status']) ?>">
                                    <?= htmlspecialchars($p['status']) ?>
                                </span>
                            </div>
                            <div class="text-muted2 mt-1" style="font-size:.75rem;">
                                <?= date('d M Y', strtotime($p['created_at'])) ?>
                            </div>
                        </div>

                        <!-- Action -->
                        <div class="col-md-2 text-md-end d-flex flex-column gap-2">
                            <?php if ($p['status'] === 'accepted' && $p['job_status'] === 'in_progress'): ?>
                                <a href="<?= BASE_URL ?>/freelancer/my_jobs.php" class="btn btn-brand btn-sm rounded-pill">
                                    View Job →
                                </a>
                            <?php else: ?>
                                <a href="<?= BASE_URL ?>/freelancer/view_job.php?job_id=<?= (int) $p['job_id'] ?>"
                                    class="btn btn-outline-primary btn-sm rounded-pill">
                                    View Job
                                </a>
                            <?php endif; ?>
                            <?php if ($p['status'] === 'pending'): ?>
                                <form method="post" onsubmit="return confirm('Withdraw this proposal? This cannot be undone.')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="withdraw">
                                    <input type="hidden" name="proposal_id" value="<?= (int) $p['proposal_id'] ?>">
                                    <button class="btn btn-outline-danger btn-sm rounded-pill w-100">
                                        Withdraw
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>