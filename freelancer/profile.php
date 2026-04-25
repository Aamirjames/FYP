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
$activeTab = $_GET['tab'] ?? 'profile';

/* Load all skills */
$allSkills = $pdo->query("SELECT skill_id, skill_name FROM skills ORDER BY skill_name")->fetchAll();

/* Load profile */
$stmt = $pdo->prepare("SELECT hourly_rate, portfolio_url, profile_image, phone, email FROM users WHERE user_id=? LIMIT 1");
$stmt->execute([$freelancerId]);
$profile = $stmt->fetch();

/* Load selected skills */
$stmt = $pdo->prepare("SELECT skill_id FROM user_skill WHERE user_id=?");
$stmt->execute([$freelancerId]);
$selectedSkills = array_map(fn($r) => (int) $r['skill_id'], $stmt->fetchAll());

/* ================= PROFILE UPDATE ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'profile') {
  csrf_verify();
  $activeTab = 'profile';

  $hourly = trim($_POST['hourly_rate'] ?? '');
  $portfolio = trim($_POST['portfolio_url'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $skills = $_POST['skills'] ?? [];

  if ($hourly !== '' && (!is_numeric($hourly) || (float) $hourly < 0)) {
    $errors[] = "Hourly rate must be valid.";
  }

  if (empty($skills)) {
    $errors[] = "Select at least one skill.";
  }

  /* Image Upload */
  $newImagePath = null;
  if (!empty($_FILES['profile_image']) && ($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {

    if ($_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
      if ($_FILES['profile_image']['size'] <= 2 * 1024 * 1024) {

        $info = @getimagesize($_FILES['profile_image']['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

        if ($info && isset($allowed[$info['mime']])) {

          $dir = __DIR__ . '/../uploads/profiles/';
          if (!is_dir($dir))
            mkdir($dir, 0777, true);

          $filename = 'user_' . $freelancerId . '_' . time() . '.' . $allowed[$info['mime']];
          move_uploaded_file($_FILES['profile_image']['tmp_name'], $dir . $filename);

          $newImagePath = 'uploads/profiles/' . $filename;
        } else {
          $errors[] = "Invalid image type.";
        }

      } else
        $errors[] = "Image too large.";
    } else
      $errors[] = "Upload failed.";
  }

  if (!$errors) {
    $pdo->beginTransaction();
    try {

      if ($newImagePath) {
        $pdo->prepare("UPDATE users SET hourly_rate=?, portfolio_url=?, phone=?, profile_image=? WHERE user_id=?")
          ->execute([$hourly ?: null, $portfolio ?: null, $phone ?: null, $newImagePath, $freelancerId]);
      } else {
        $pdo->prepare("UPDATE users SET hourly_rate=?, portfolio_url=?, phone=? WHERE user_id=?")
          ->execute([$hourly ?: null, $portfolio ?: null, $phone ?: null, $freelancerId]);
      }

      /* Skills */
      $pdo->prepare("DELETE FROM user_skill WHERE user_id=?")->execute([$freelancerId]);

      $ins = $pdo->prepare("INSERT INTO user_skill(user_id, skill_id) VALUES (?, ?)");
      foreach ($skills as $sid) {
        $ins->execute([$freelancerId, (int) $sid]);
      }

      $pdo->commit();
      $success = "Profile updated successfully.";

    } catch (Exception $e) {
      $pdo->rollBack();
      $errors[] = "Update failed.";
    }
  }
}

/* ================= CHANGE EMAIL ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'email') {
  csrf_verify();
  $activeTab = 'email';

  $newEmail = trim($_POST['new_email'] ?? '');
  $password = $_POST['confirm_password'] ?? '';

  if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email.";
  }

  if (!$errors) {
    $row = $pdo->prepare("SELECT password FROM users WHERE user_id=?");
    $row->execute([$freelancerId]);

    if (!password_verify($password, $row->fetchColumn())) {
      $errors[] = "Wrong password.";
    }
  }

  if (!$errors) {
    $pdo->prepare("UPDATE users SET email=? WHERE user_id=?")
      ->execute([$newEmail, $freelancerId]);

    $_SESSION['user']['email'] = $newEmail;
    $success = "Email updated.";
  }
}

/* ================= CHANGE PASSWORD ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'password') {
  csrf_verify();
  $activeTab = 'password';

  $current = $_POST['current_password'] ?? '';
  $new = $_POST['new_password'] ?? '';
  $confirm = $_POST['confirm_password'] ?? '';

  $row = $pdo->prepare("SELECT password FROM users WHERE user_id=?");
  $row->execute([$freelancerId]);

  if (!password_verify($current, $row->fetchColumn())) {
    $errors[] = "Wrong current password.";
  }

  if (strlen($new) < 6) {
    $errors[] = "Password too short.";
  }

  if ($new !== $confirm) {
    $errors[] = "Passwords do not match.";
  }

  if (!$errors) {
    $pdo->prepare("UPDATE users SET password=? WHERE user_id=?")
      ->execute([password_hash($new, PASSWORD_DEFAULT), $freelancerId]);

    $success = "Password updated.";
  }
}

$title = "Freelancer Profile";
require_once __DIR__ . '/../includes/header.php';

$imgUrl = !empty($profile['profile_image']) ? BASE_URL . '/' . $profile['profile_image'] : null;
?>

<div class="container py-5 page-narrow">

  <h3 class="mb-4">My Profile</h3>

  <ul class="nav nav-tabs mb-4">
    <li class="nav-item">
      <a class="nav-link <?= $activeTab === 'profile' ? 'active' : '' ?>" href="?tab=profile">Profile</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $activeTab === 'email' ? 'active' : '' ?>" href="?tab=email">Email</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $activeTab === 'password' ? 'active' : '' ?>" href="?tab=password">Password</a>
    </li>
  </ul>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $e)
        echo "<div>" . htmlspecialchars($e) . "</div>"; ?>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success">
      <?= $success ?>
    </div>
  <?php endif; ?>

  <?php if ($activeTab === 'profile'): ?>
    <form method="post" enctype="multipart/form-data" class="card p-4">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="form" value="profile">

      <div class="mb-3">
        <label>Profile Image</label><br>
        <?php if ($imgUrl): ?>
          <img src="<?= $imgUrl ?>" class="profile-avatar">
        <?php else: ?>
          <div class="profile-avatar-placeholder">
            <?= initials($freelancerName) ?>
          </div>
        <?php endif; ?>
        <input type="file" name="profile_image" class="form-control mt-2">
      </div>

      <input class="form-control mb-3" type="number" name="hourly_rate" placeholder="Hourly Rate"
        value="<?= $profile['hourly_rate'] ?>">
      <input class="form-control mb-3" type="text" name="phone" placeholder="Phone" value="<?= $profile['phone'] ?>">
      <input class="form-control mb-3" type="url" name="portfolio_url" placeholder="Portfolio"
        value="<?= $profile['portfolio_url'] ?>">

      <label class="form-label fw-bold">Skills</label>

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

      <button class="btn btn-brand w-100 mt-3">Save</button>
    </form>
  <?php endif; ?>

  <?php if ($activeTab === 'email'): ?>
    <form method="post" class="card card-soft p-4">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="form" value="email">

      <div class="mb-3">
        <label class="form-label fw-bold">Current Email</label>
        <input class="form-control" type="text" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" disabled>
      </div>

      <div class="mb-3">
        <label class="form-label fw-bold">New Email Address <span style="color:#f87171;">*</span></label>
        <input class="form-control" type="email" name="new_email" placeholder="Enter new email address"
          value="<?= htmlspecialchars($_POST['new_email'] ?? '') ?>" required>
      </div>

      <div class="mb-4">
        <label class="form-label fw-bold">Current Password <span style="color:#f87171;">*</span></label>
        <input class="form-control" type="password" name="confirm_password" placeholder="Enter your password to confirm"
          required>
        <div class="text-muted2 mt-1" style="font-size:.82rem;">Required to verify this change.</div>
      </div>

      <button class="btn btn-brand w-100 py-2">Update Email</button>
    </form>
  <?php endif; ?>

  <!-- ========== CHANGE PASSWORD – CLIENT STYLE ========== -->
  <?php if ($activeTab === 'password'): ?>
    <form method="post" class="card card-soft p-4">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="form" value="password">

      <div class="mb-3">
        <label class="form-label fw-bold">Current Password <span style="color:#f87171;">*</span></label>
        <input class="form-control" type="password" name="current_password" placeholder="Enter your current password"
          required>
      </div>

      <div class="mb-3">
        <label class="form-label fw-bold">New Password <span style="color:#f87171;">*</span></label>
        <input class="form-control" type="password" name="new_password" placeholder="Minimum 6 characters" minlength="6"
          required>
      </div>

      <div class="mb-4">
        <label class="form-label fw-bold">Confirm New Password <span style="color:#f87171;">*</span></label>
        <input class="form-control" type="password" name="confirm_password" placeholder="Re-enter new password" required>
      </div>

      <button class="btn btn-brand w-100 py-2">Change Password</button>
    </form>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>