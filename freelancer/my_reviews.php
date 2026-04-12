<?php
// SSH11: Freelancer views their received ratings & reviews
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('freelancer');

$freelancerId = (int) $_SESSION['user']['id'];

// Load all reviews for this freelancer
$stmt = $pdo->prepare("
    SELECT r.review_id, r.rating, r.review_text, r.created_at,
           j.title AS job_title,
           u.name  AS client_name
    FROM reviews r
    JOIN job   j ON j.job_id   = r.job_id
    JOIN users u ON u.user_id  = r.client_id
    WHERE r.freelancer_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$freelancerId]);
$reviews = $stmt->fetchAll();

// Overall stats
$totalReviews = count($reviews);
$avgRating = 0;
if ($totalReviews > 0) {
    $avgRating = array_sum(array_column($reviews, 'rating')) / $totalReviews;
}

// Star distribution
$dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($reviews as $r)
    $dist[(int) $r['rating']]++;

$title = "My Reviews";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0 fw-bold">My Reviews</h3>
    </div>

    <?php if (!$reviews): ?>
        <div class="card card-soft p-4 text-center text-muted2 py-5">
            <div style="font-size:2.5rem;">⭐</div>
            <div class="mt-2">No reviews yet. Complete jobs to receive ratings.</div>
        </div>

    <?php else: ?>

        <!-- Overall Rating Summary -->
        <div class="card card-soft p-4 mb-4">
            <div class="row align-items-center g-4">

                <!-- Average Score -->
                <div class="col-md-3 text-center">
                    <div style="font-size:3.5rem; font-weight:800; color:#fbbf24; line-height:1;">
                        <?= number_format($avgRating, 1) ?>
                    </div>
                    <div style="font-size:1.5rem; letter-spacing:3px; color:#fbbf24;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span style="color: <?= $i <= round($avgRating) ? '#fbbf24' : 'rgba(255,255,255,.2)' ?>;">★</span>
                        <?php endfor; ?>
                    </div>
                    <div class="text-muted2 mt-1" style="font-size:.85rem;">
                        <?= $totalReviews ?> review
                        <?= $totalReviews !== 1 ? 's' : '' ?>
                    </div>
                </div>

                <!-- Star Distribution -->
                <div class="col-md-9">
                    <?php for ($i = 5; $i >= 1; $i--):
                        $count = $dist[$i];
                        $percent = $totalReviews > 0 ? ($count / $totalReviews) * 100 : 0;
                        ?>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div style="width:20px; text-align:right; color:#fbbf24; font-size:.85rem;">
                                <?= $i ?>★
                            </div>
                            <div
                                style="flex:1; background:rgba(255,255,255,.08); border-radius:999px; height:8px; overflow:hidden;">
                                <div
                                    style="width:<?= number_format($percent, 1) ?>%; background:#fbbf24; height:100%; border-radius:999px; transition:width .4s;">
                                </div>
                            </div>
                            <div class="text-muted2" style="width:24px; font-size:.82rem;">
                                <?= $count ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>

            </div>
        </div>

        <!-- Individual Reviews -->
        <div class="row g-4">
            <?php foreach ($reviews as $r): ?>
                <div class="col-md-6">
                    <div class="card card-soft p-4 h-100">

                        <!-- Stars -->
                        <div style="font-size:1.3rem; letter-spacing:2px; margin-bottom:8px;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span style="color: <?= $i <= (int) $r['rating'] ? '#fbbf24' : 'rgba(255,255,255,.2)' ?>;">★</span>
                            <?php endfor; ?>
                        </div>

                        <!-- Review Text -->
                        <?php if ($r['review_text']): ?>
                            <div style="line-height:1.65; font-size:.93rem; flex:1;">
                                "
                                <?= nl2br(htmlspecialchars($r['review_text'])) ?>"
                            </div>
                        <?php else: ?>
                            <div class="text-muted2" style="font-size:.88rem; font-style:italic;">No written review.</div>
                        <?php endif; ?>

                        <!-- Meta -->
                        <div class="mt-3 pt-3" style="border-top:1px solid var(--border); font-size:.82rem;"
                            class="text-muted2">
                            <span class="text-muted2">👤
                                <?= htmlspecialchars($r['client_name']) ?>
                            </span>
                            &bull;
                            <span class="text-muted2">📋
                                <?= htmlspecialchars($r['job_title']) ?>
                            </span>
                            &bull;
                            <span class="text-muted2">
                                <?= date('d M Y', strtotime($r['created_at'])) ?>
                            </span>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>