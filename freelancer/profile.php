<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

requireRole('freelancer');

$freelancerId = (int) $_SESSION['user']['id'];
$freelancerName = $_SESSION['user']['name'] ?? 'User';

function initials($name)
{
  $parts = preg_split('/\s+/', trim($name));
  $first = strtoupper(substr($parts[0] ?? 'U', 0, 1));
  $second = strtoupper(substr($parts[1] ?? '', 0, 1));
  return $first . ($second ?: '');
}

$errors = [];
$success = "";

/* Load all skills */
$allSkills = $pdo->query("SELECT skill_id, skill_name FROM skills ORDER BY skill_name")->fetchAll();

/* Load current profile from users table */
$stmt = $pdo->prepare("SELECT hourly_rate, portfolio_url, profile_image FROM users WHERE user_id=? LIMIT 1");
$stmt->execute([$freelancerId]);
$profile = $stmt->fetch() ?: ['hourly_rate' => null, 'portfolio_url' => null, 'profile_image' => null];

/* Load selected skills */
$stmt = $pdo->prepare("SELECT skill_id FROM user_skill WHERE user_id=?");
$stmt->execute([$freelancerId]);
$selectedSkills = array_map(fn($r) => (int) $r['skill_id'], $stmt->fetchAll());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  $hourly = trim($_POST['hourly_rate'] ?? '');
  $portfolio = trim($_POST['portfolio_url'] ?? '');
  $skills = $_POST['skills'] ?? [];

  if ($hourly !== '' && (!is_numeric($hourly) || (float) $hourly < 0)) {
    $errors[] = "Hourly rate must be a valid number (0 or greater).";
  }
  if (!is_array($skills))
    $skills = [];

  // uplodad profile pic 

  $newImagePath = null;

  if (!empty($_FILES['profile_image']) && ($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {

    if ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
      $errors[] = "Image upload failed.";
    } else {
      $maxBytes = 2 * 1024 * 1024; // 2MB
      if (($_FILES['profile_image']['size'] ?? 0) > $maxBytes) {
        $errors[] = "Image must be less than 2MB.";
      } else {
        $tmp = $_FILES['profile_image']['tmp_name'];
        $info = @getimagesize($tmp);

        if ($info === false) {
          $errors[] = "Uploaded file is not a valid image.";
        } else {
          $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
          ];

          $mime = $info['mime'] ?? '';
          if (!isset($allowed[$mime])) {
            $errors[] = "Only JPG, PNG, WEBP images are allowed.";
          } else {
            $ext = $allowed[$mime];

            $uploadDir = __DIR__ . '/../uploads/profiles/';
            if (!is_dir($uploadDir)) {
              mkdir($uploadDir, 0777, true);
            }

            $filename = 'user_' . $freelancerId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destFs = $uploadDir . $filename;

            if (!move_uploaded_file($tmp, $destFs)) {
              $errors[] = "Could not save uploaded image.";
            } else {
              // Path stor in database
              $newImagePath = 'uploads/profiles/' . $filename;
            }
          }
        }
      }
    }
  }
  // -----------------------------------------------

  if (!$errors) {
    $pdo->beginTransaction();
    try {

      // Update portfolio
      if ($newImagePath !== null) {
        $stmt = $pdo->prepare("UPDATE users SET hourly_rate=?, portfolio_url=?, profile_image=? WHERE user_id=?");
        $stmt->execute([
          $hourly === '' ? null : (float) $hourly,
          $portfolio === '' ? null : $portfolio,
          $newImagePath,
          $freelancerId
        ]);
      } else {
        $stmt = $pdo->prepare("UPDATE users SET hourly_rate=?, portfolio_url=? WHERE user_id=?");
        $stmt->execute([
          $hourly === '' ? null : (float) $hourly,
          $portfolio === '' ? null : $portfolio,
          $freelancerId
        ]);
      }

      // Replace skills
      $stmt = $pdo->prepare("DELETE FROM user_skill WHERE user_id=?");
      $stmt->execute([$freelancerId]);

      if (count($skills) > 0) {
        $ins = $pdo->prepare("INSERT INTO user_skill(user_id, skill_id) VALUES (?, ?)");
        foreach ($skills as $sid) {
          $sid = (int) $sid;
          if ($sid > 0)
            $ins->execute([$freelancerId, $sid]);
        }
      }

      $pdo->commit();
      $success = "Profile updated successfully.";

      // Reload display data
      $stmt = $pdo->prepare("SELECT hourly_rate, portfolio_url, profile_image FROM users WHERE user_id=? LIMIT 1");
      $stmt->execute([$freelancerId]);
      $profile = $stmt->fetch();

      $stmt = $pdo->prepare("SELECT skill_id FROM user_skill WHERE user_id=?");
      $stmt->execute([$freelancerId]);
      $selectedSkills = array_map(fn($r) => (int) $r['skill_id'], $stmt->fetchAll());

    } catch (Exception $e) {
      $pdo->rollBack();
      $errors[] = "Failed to update profile. Try again.";
    }
  }
}

$title = "Freelancer Profile";
require_once __DIR__ . '/../includes/header.php';

$imgPath = $profile['profile_image'] ?? null;
$imgUrl = $imgPath ? (BASE_URL . '/' . $imgPath) : null;
?>

<div class="container py-5 page-narrow">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">My Profile</h3>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $e)
          echo "<li>" . htmlspecialchars($e) . "</li>"; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success">
      <?= htmlspecialchars($success) ?>
    </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="card card-soft p-4">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

    <div class="mb-3">
      <label class="form-label">Profile Picture</label>

      <div class="d-flex align-items-center gap-3 mb-2">
        <?php if ($imgUrl): ?>
          <img src="<?= htmlspecialchars($imgUrl) ?>" class="profile-avatar" alt="Profile">
        <?php else: ?>
          <div class="profile-avatar-placeholder">
            <?= htmlspecialchars(initials($freelancerName)) ?>
          </div>
        <?php endif; ?>

        <div class="text-muted2">Allowed: JPG, PNG, WEBP (max 2MB)</div>
      </div>

      <input class="form-control" type="file" name="profile_image" accept=".jpg,.jpeg,.png,.webp,image/*">
    </div>

    <div class="mb-3">
      <label class="form-label">Hourly Rate</label>
      <input class="form-control" type="number" min="0" step="0.01" name="hourly_rate"
        value="<?= htmlspecialchars($profile['hourly_rate'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">Portfolio URL</label>
      <input class="form-control" type="url" name="portfolio_url"
        value="<?= htmlspecialchars($profile['portfolio_url'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">Skills</label>
      <div class="row">
        <?php foreach ($allSkills as $s): ?>
          <?php $sid = (int) $s['skill_id']; ?>
          <div class="col-6">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="skills[]" id="skill<?= $sid ?>" value="<?= $sid ?>"
                <?= in_array($sid, $selectedSkills, true) ? 'checked' : '' ?>>
              <label class="form-check-label" for="skill<?= $sid ?>">
                <?= htmlspecialchars($s['skill_name']) ?>
              </label>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <button class="btn btn-brand w-100 py-2">Save Profile</button>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>