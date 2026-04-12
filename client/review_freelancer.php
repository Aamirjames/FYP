<?php
// SSH11: Client leaves a rating & review for a freelancer after job completion
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

requireRole('client');

$clientId = (int) $_SESSION['user']['id'];
$jobId = (int) ($_GET['job_id'] ?? 0);

if ($jobId <= 0) {
    header("Location: " . BASE_URL . "/client/active_jobs.php");
    exit;
}

// Load job — must be completed, belong to this client, and payment confirmed
$stmt = $pdo->prepare("
    SELECT j.job_id, j.title, j.hired_freelancer_id,
           j.status, j.confirmed_by_client,
           u.name AS freelancer_name,
           u.profile_image,
           pay.payment_id
    FROM job j
    JOIN users u   ON u.user_id   = j.hired_freelancer_id
    LEFT JOIN payments pay ON pay.job_id = j.job_id
    WHERE j.job_id = ? AND j.client_id = ? AND j.status = 'completed'
");
$stmt->execute([$jobId, $clientId]);
$job = $stmt->fetch();

if (!$job) {
    header("Location: " . BASE_URL . "/client/active_jobs.php");
    exit;
}

// Check if already reviewed
$stmt = $pdo->prepare("SELECT review_id, rating, review_text FROM reviews WHERE job_id = ? LIMIT 1");
$stmt->execute([$jobId]);
$existingReview = $stmt->fetch();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existingReview) {
    csrf_verify();

    $rating = (int) ($_POST['rating'] ?? 0);
    $reviewText = trim($_POST['review_text'] ?? '');

    if ($rating < 1 || $rating > 5)
        $errors[] = "Please select a rating between 1 and 5 stars.";

    if (!$errors) {
        $pdo->prepare("
            INSERT INTO reviews (job_id, client_id, freelancer_id, rating, review_text)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([
                    $jobId,
                    $clientId,
                    (int) $job['hired_freelancer_id'],
                    $rating,
                    $reviewText === '' ? null : $reviewText
                ]);

        $success = "Review submitted successfully!";

        // Reload
        $stmt = $pdo->prepare("SELECT review_id, rating, review_text FROM reviews WHERE job_id = ? LIMIT 1");
        $stmt->execute([$jobId]);
        $existingReview = $stmt->fetch();
    }
}

// initials helper
function initials($name)
{
    $parts = preg_split('/\s+/', trim($name));
    $first = strtoupper(substr($parts[0] ?? 'U', 0, 1));
    $second = strtoupper(substr($parts[1] ?? '', 0, 1));
    return $first . ($second ?: '');
}

$title = "Rate Freelancer";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5 page-narrow">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0 fw-bold">Rate Freelancer</h3>
    </div>

    <!-- Job & Freelancer Info -->
    <div class="card card-soft p-4 mb-4">
        <div class="d-flex align-items-center gap-3">
            <?php
            $imgUrl = $job['profile_image'] ? BASE_URL . '/' . $job['profile_image'] : null;
            ?>
            <?php if ($imgUrl): ?>
                <img src="<?= htmlspecialchars($imgUrl) ?>" class="profile-avatar" alt="Profile">
            <?php else: ?>
                <div class="profile-avatar-placeholder">
                    <?= htmlspecialchars(initials($job['freelancer_name'])) ?>
                </div>
            <?php endif; ?>
            <div>
                <div class="fw-bold" style="font-size:1.05rem;">
                    <?= htmlspecialchars($job['freelancer_name']) ?>
                </div>
                <div class="text-muted2" style="font-size:.85rem;">
                    📋 Job:
                    <?= htmlspecialchars($job['title']) ?>
                </div>
            </div>
        </div>
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
        <div class="alert alert-success mb-3">🎉
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($existingReview): ?>
        <!-- Already reviewed — show submitted review -->
        <div class="card card-soft p-4">
            <h5 class="fw-bold mb-3">✅ Your Review</h5>
            <div class="mb-3" style="font-size:1.6rem; letter-spacing:2px;">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span
                        style="color: <?= $i <= (int) $existingReview['rating'] ? '#fbbf24' : 'rgba(255,255,255,.2)' ?>;">★</span>
                <?php endfor; ?>
                <span class="text-muted2" style="font-size:.9rem; letter-spacing:0;">
                    (
                    <?= (int) $existingReview['rating'] ?>/5)
                </span>
            </div>
            <?php if ($existingReview['review_text']): ?>
                <div style="line-height:1.65;">
                    <?= nl2br(htmlspecialchars($existingReview['review_text'])) ?>
                </div>
            <?php else: ?>
                <div class="text-muted2">No written review.</div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- Review Form -->
        <form method="post" class="card card-soft p-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

            <!-- Star Rating -->
            <div class="mb-4">
                <label class="form-label fw-bold">
                    Your Rating <span style="color:#f87171;">*</span>
                </label>
                <div class="star-rating" id="starRating">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="star" data-value="<?= $i ?>">★</span>
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="rating" id="ratingInput" value="0">
                <div class="text-muted2 mt-1" style="font-size:.83rem;" id="ratingLabel">
                    Click a star to rate
                </div>
            </div>

            <!-- Written Review -->
            <div class="mb-4">
                <label class="form-label fw-bold">Written Review <span class="text-muted2">(optional)</span></label>
                <textarea class="form-control" name="review_text" rows="4"
                    placeholder="Share your experience working with this freelancer…"><?= htmlspecialchars($_POST['review_text'] ?? '') ?></textarea>
            </div>

            <button class="btn btn-brand w-100 py-2" id="submitBtn" disabled>Submit Review</button>
            <div class="text-muted2 mt-2 text-center" style="font-size:.82rem;">
                You can only submit one review per job.
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
    const stars = document.querySelectorAll('.star');
    const ratingInput = document.getElementById('ratingInput');
    const ratingLabel = document.getElementById('ratingLabel');
    const submitBtn = document.getElementById('submitBtn');

    const labels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

    function highlightStars(value) {
        stars.forEach(s => {
            s.classList.toggle('active', parseInt(s.dataset.value) <= value);
        });
    }

    stars.forEach(star => {
        // Hover preview
        star.addEventListener('mouseover', function () {
            highlightStars(parseInt(this.dataset.value));
        });

        // Reset to selected on mouse out
        star.addEventListener('mouseout', function () {
            highlightStars(parseInt(ratingInput.value));
        });

        // Select rating
        star.addEventListener('click', function () {
            const val = parseInt(this.dataset.value);
            ratingInput.value = val;
            highlightStars(val);
            ratingLabel.textContent = labels[val] + ' (' + val + '/5)';
            ratingLabel.style.color = '#fbbf24';
            submitBtn.disabled = false;
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>