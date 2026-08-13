<?php
/**
 * Profile management: update profile info + change password.
 * Student ID and email are identity fields and cannot be changed.
 */
require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = db();
$userId = current_user_id();
$user = current_user();

$errors = [];
$old = [
    'full_name' => $user['full_name'],
    'phone'     => $user['phone'] ?? '',
    'department'=> $user['department'] ?? '',
    'batch'     => $user['batch'] ?? '',
    'hall'      => $user['hall'] ?? '',
];

// ---------- Update profile ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        foreach ($old as $k => $v) {
            $old[$k] = trim((string)($_POST[$k] ?? $v));
        }

        if (mb_strlen($old['full_name']) < 3) {
            $errors['full_name'] = 'Full name must be at least 3 characters.';
        }
        if (!preg_match('/^\+?[0-9]{7,15}$/', str_replace([' ', '-'], '', $old['phone']))) {
            $errors['phone'] = 'Please enter a valid phone number.';
        }
        if (mb_strlen($old['department']) < 2) {
            $errors['department'] = 'Please enter your department.';
        }
        if ($old['batch'] === '') {
            $errors['batch'] = 'Please enter your batch or year.';
        }
        if (mb_strlen($old['hall']) < 2) {
            $errors['hall'] = 'Please enter your campus or hall.';
        }

        $avatarPath = $user['profile_image'];
        if (empty($errors) && !empty($_FILES['profile_image']['name'])) {
            $newAvatar = upload_avatar($_FILES['profile_image']);
            if ($newAvatar === null) {
                $errors['profile_image'] = 'Profile picture could not be uploaded. Use a JPG, PNG, WebP or GIF under 5 MB.';
            } else {
                if ($avatarPath) {
                    delete_image_file($avatarPath);
                }
                $avatarPath = $newAvatar;
            }
        }

        if (empty($errors)) {
            $st = $pdo->prepare(
                'UPDATE users SET full_name = ?, phone = ?, department = ?, batch = ?, hall = ?, profile_image = ? WHERE id = ?'
            );
            $st->execute([
                $old['full_name'], str_replace(' ', '', $old['phone']),
                $old['department'], $old['batch'], $old['hall'], $avatarPath, $userId,
            ]);
            audit_log('profile_updated', 'user', $userId, 'User updated their profile.');
            set_flash('success', 'Profile updated.');
            redirect('profile.php');
        }
    }

    if ($action === 'change_password') {
        $current = (string)($_POST['current_password'] ?? '');
        $newPass = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');

        if (!password_verify($current, $user['password_hash'])) {
            $errors['current_password'] = 'Your current password is incorrect.';
        }
        if (mb_strlen($newPass) < 8 || !preg_match('/[A-Za-z]/', $newPass) || !preg_match('/[0-9]/', $newPass)) {
            $errors['new_password'] = 'New password must be at least 8 characters and include letters and numbers.';
        }
        if ($newPass !== $confirm) {
            $errors['password_confirm'] = 'Passwords do not match.';
        }
        if ($newPass !== '' && $newPass === $current) {
            $errors['new_password'] = 'New password must be different from the current one.';
        }

        if (empty($errors)) {
            $st = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $st->execute([password_hash($newPass, PASSWORD_DEFAULT), $userId]);
            audit_log('password_changed', 'user', $userId, 'User changed their password.');
            set_flash('success', 'Password changed successfully.');
            redirect('profile.php');
        }
    }
}

$pageTitle = 'My Profile';
$dashboardPage = true;
$activeNav = 'profile';
require __DIR__ . '/../includes/header.php';
?>

<div class="dash-wrap">
  <?php require __DIR__ . '/_sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <div>
        <h1>My Profile</h1>
        <div class="sub">Update your details and keep your account secure</div>
      </div>
    </div>

    <div class="chart-grid" style="grid-template-columns: 1.4fr 1fr; margin-bottom:20px;">
      <div class="card" style="padding:24px;">
        <h3 class="mb-2">Profile information</h3>
        <?php if (isset($errors['general'])): ?>
          <div class="alert alert-error alert-inline"><?= e($errors['general']) ?></div>
        <?php endif; ?>
        <form method="post" action="profile.php" enctype="multipart/form-data" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_profile">

          <div class="flex mb-3">
            <span class="avatar avatar-lg" data-initials="<?= e(strtoupper(substr($old['full_name'], 0, 2))) ?>">
              <?php if ($user['profile_image']): ?><img src="<?= e(image_url($user['profile_image'])) ?>" alt=""><?php endif; ?>
            </span>
            <div>
              <div class="form-group">
                <label class="form-label" for="profile_image">Profile picture</label>
                <input class="form-control" type="file" id="profile_image" name="profile_image" accept="image/jpeg,image/png,image/webp,image/gif" style="max-width:320px;">
                <?php if (isset($errors['profile_image'])): ?><span class="form-error"><?= e($errors['profile_image']) ?></span><?php endif; ?>
              </div>
            </div>
          </div>

          <div class="form-grid mb-2">
            <div class="form-group <?= isset($errors['full_name']) ? 'invalid' : '' ?>">
              <label class="form-label" for="full_name">Full name</label>
              <input class="form-control" type="text" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>" required>
              <?php if (isset($errors['full_name'])): ?><span class="form-error"><?= e($errors['full_name']) ?></span><?php endif; ?>
            </div>
            <div class="form-group">
              <label class="form-label" for="email">University email</label>
              <input class="form-control" type="email" value="<?= e($user['email']) ?>" disabled>
              <span class="form-hint">Email is an identity field and cannot be changed.</span>
            </div>
            <div class="form-group">
              <label class="form-label" for="student_id">Student ID</label>
              <input class="form-control" type="text" value="<?= e($user['student_id']) ?>" disabled>
              <span class="form-hint">Student ID is an identity field and cannot be changed.</span>
            </div>
            <div class="form-group <?= isset($errors['phone']) ? 'invalid' : '' ?>">
              <label class="form-label" for="phone">Phone number</label>
              <input class="form-control" type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>" required>
              <?php if (isset($errors['phone'])): ?><span class="form-error"><?= e($errors['phone']) ?></span><?php endif; ?>
            </div>
            <div class="form-group <?= isset($errors['department']) ? 'invalid' : '' ?>">
              <label class="form-label" for="department">Department</label>
              <input class="form-control" type="text" id="department" name="department" value="<?= e($old['department']) ?>" required>
              <?php if (isset($errors['department'])): ?><span class="form-error"><?= e($errors['department']) ?></span><?php endif; ?>
            </div>
            <div class="form-group <?= isset($errors['batch']) ? 'invalid' : '' ?>">
              <label class="form-label" for="batch">Batch / Year</label>
              <input class="form-control" type="text" id="batch" name="batch" value="<?= e($old['batch']) ?>" required>
              <?php if (isset($errors['batch'])): ?><span class="form-error"><?= e($errors['batch']) ?></span><?php endif; ?>
            </div>
            <div class="form-group <?= isset($errors['hall']) ? 'invalid' : '' ?>">
              <label class="form-label" for="hall">Campus / Hall</label>
              <?= hall_select_html('hall', $old['hall'], 'Select your campus hall') ?>
              <?php if (isset($errors['hall'])): ?><span class="form-error"><?= e($errors['hall']) ?></span><?php endif; ?>
            </div>
          </div>

          <button class="btn btn-primary" type="submit">Save Changes</button>
        </form>
      </div>

      <div class="card" style="padding:24px; align-self:start;">
        <h3 class="mb-2">Change password</h3>
        <p class="text-muted text-small mb-3">Use a strong password you don't use anywhere else.</p>
        <form method="post" action="profile.php" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="change_password">
          <div class="form-group mb-2 <?= isset($errors['current_password']) ? 'invalid' : '' ?>">
            <label class="form-label" for="current_password">Current password</label>
            <input class="form-control" type="password" id="current_password" name="current_password" required autocomplete="current-password">
            <?php if (isset($errors['current_password'])): ?><span class="form-error"><?= e($errors['current_password']) ?></span><?php endif; ?>
          </div>
          <div class="form-group mb-2 <?= isset($errors['new_password']) ? 'invalid' : '' ?>">
            <label class="form-label" for="new_password">New password</label>
            <input class="form-control" type="password" id="new_password" name="new_password" required minlength="8">
            <span class="form-hint">At least 8 characters with letters and numbers.</span>
            <?php if (isset($errors['new_password'])): ?><span class="form-error"><?= e($errors['new_password']) ?></span><?php endif; ?>
          </div>
          <div class="form-group mb-3 <?= isset($errors['password_confirm']) ? 'invalid' : '' ?>">
            <label class="form-label" for="password_confirm">Confirm new password</label>
            <input class="form-control" type="password" id="password_confirm" name="password_confirm" required>
            <?php if (isset($errors['password_confirm'])): ?><span class="form-error"><?= e($errors['password_confirm']) ?></span><?php endif; ?>
          </div>
          <button class="btn btn-primary" type="submit">Update Password</button>
        </form>
      </div>
    </div>
  </main>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
