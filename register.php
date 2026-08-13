<?php
/**
 * Registration with full server-side validation.
 */
require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$old = [
    'full_name' => '', 'student_id' => '', 'email' => '', 'phone' => '',
    'department' => '', 'batch' => '', 'hall' => '', 'password' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach ($old as $k => $v) {
        $old[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $password = $old['password'];
    $confirm = (string)($_POST['password_confirm'] ?? '');

    // --- Server-side validation ---
    if (mb_strlen($old['full_name']) < 3) {
        $errors['full_name'] = 'Full name must be at least 3 characters.';
    }
    if (!preg_match('/^[A-Za-z0-9\-\.\/]+$/', $old['student_id'])) {
        $errors['student_id'] = 'Student ID may only contain letters, numbers, dashes, dots or slashes.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } else {
        $domain = settings('email_domain');
        if (!empty($domain)) {
            $userDomain = strtolower(substr($old['email'], strpos($old['email'], '@') + 1));
            if ($userDomain !== strtolower($domain)) {
                $errors['email'] = 'Registration is restricted to the ' . e($domain) . ' university email domain.';
            }
        }
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
    if (mb_strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must be at least 8 characters and include letters and numbers.';
    }
    if ($password !== $confirm) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }

    // --- Duplicate checks ---
    if (!isset($errors['student_id'])) {
        $st = db()->prepare('SELECT 1 FROM users WHERE student_id = ?');
        $st->execute([$old['student_id']]);
        if ($st->fetchColumn()) {
            $errors['student_id'] = 'This Student ID is already registered.';
        }
    }
    if (!isset($errors['email'])) {
        $st = db()->prepare('SELECT 1 FROM users WHERE email = ?');
        $st->execute([$old['email']]);
        if ($st->fetchColumn()) {
            $errors['email'] = 'This email is already registered.';
        }
    }

    if (empty($errors)) {
        // Optional profile picture.
        $avatarPath = null;
        if (!empty($_FILES['profile_image']['name'])) {
            $avatarPath = upload_avatar($_FILES['profile_image']);
            if ($avatarPath === null) {
                $errors['profile_image'] = 'Profile picture could not be uploaded. Use a JPG, PNG, WebP or GIF image under 5 MB.';
            }
        }
        if (empty($errors)) {
            $pdo = db();
            $st = $pdo->prepare(
                'INSERT INTO users (full_name, student_id, email, phone, password_hash, department, batch, hall, profile_image, role, status, is_verified)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "student", "active", 1)'
            );
            $st->execute([
                $old['full_name'], $old['student_id'], $old['email'], str_replace(' ', '', $old['phone']),
                password_hash($password, PASSWORD_DEFAULT),
                $old['department'], $old['batch'], $old['hall'], $avatarPath,
            ]);
            $newId = (int)$pdo->lastInsertId();
            audit_log('user_registered', 'user', $newId, 'New student account created.', $newId);

            login_user(get_user($newId));
            set_flash('success', 'Welcome to CampusMart! Your account is ready.');
            redirect('user/dashboard.php');
        }
    }
}

$pageTitle = 'Create Account';
require __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div class="card auth-card wide">
    <h1>Create your account</h1>
    <p class="sub">Join the student marketplace and start buying and selling on campus.</p>

    <?php if ($errors): ?>
      <div class="alert alert-error alert-inline">Please fix the highlighted fields below.</div>
    <?php endif; ?>

    <form method="post" action="register.php" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?>

      <div class="form-grid">
        <div class="form-group <?= isset($errors['full_name']) ? 'invalid' : '' ?>">
          <label class="form-label" for="full_name">Full name <span class="req">*</span></label>
          <input class="form-control" type="text" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>" required>
          <?php if (isset($errors['full_name'])): ?><span class="form-error"><?= e($errors['full_name']) ?></span><?php endif; ?>
        </div>

        <div class="form-group <?= isset($errors['student_id']) ? 'invalid' : '' ?>">
          <label class="form-label" for="student_id">Student ID <span class="req">*</span></label>
          <input class="form-control" type="text" id="student_id" name="student_id" value="<?= e($old['student_id']) ?>" required>
          <?php if (isset($errors['student_id'])): ?><span class="form-error"><?= e($errors['student_id']) ?></span><?php endif; ?>
        </div>

        <div class="form-group <?= isset($errors['email']) ? 'invalid' : '' ?>">
          <label class="form-label" for="email">University email <span class="req">*</span></label>
          <input class="form-control" type="email" id="email" name="email" value="<?= e($old['email']) ?>" required placeholder="<?= e(settings('email_domain') ? 'you@' . settings('email_domain') : '') ?>">
          <?php if (isset($errors['email'])): ?><span class="form-error"><?= e($errors['email']) ?></span><?php endif; ?>
        </div>

        <div class="form-group <?= isset($errors['phone']) ? 'invalid' : '' ?>">
          <label class="form-label" for="phone">Phone number <span class="req">*</span></label>
          <input class="form-control" type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>" required>
          <?php if (isset($errors['phone'])): ?><span class="form-error"><?= e($errors['phone']) ?></span><?php endif; ?>
        </div>

        <div class="form-group <?= isset($errors['department']) ? 'invalid' : '' ?>">
          <label class="form-label" for="department">Department <span class="req">*</span></label>
          <input class="form-control" type="text" id="department" name="department" value="<?= e($old['department']) ?>" required>
          <?php if (isset($errors['department'])): ?><span class="form-error"><?= e($errors['department']) ?></span><?php endif; ?>
        </div>

        <div class="form-group <?= isset($errors['batch']) ? 'invalid' : '' ?>">
          <label class="form-label" for="batch">Batch / Year <span class="req">*</span></label>
          <input class="form-control" type="text" id="batch" name="batch" value="<?= e($old['batch']) ?>" placeholder="e.g. 2024" required>
          <?php if (isset($errors['batch'])): ?><span class="form-error"><?= e($errors['batch']) ?></span><?php endif; ?>
        </div>

        <div class="form-group <?= isset($errors['hall']) ? 'invalid' : '' ?>">
          <label class="form-label" for="hall">Campus / Hall <span class="req">*</span></label>
          <?= hall_select_html('hall', $old['hall'], 'Select your campus hall') ?>
          <?php if (isset($errors['hall'])): ?><span class="form-error"><?= e($errors['hall']) ?></span><?php endif; ?>
        </div>

        <div class="form-group <?= isset($errors['profile_image']) ? 'invalid' : '' ?>">
          <label class="form-label" for="profile_image">Profile picture</label>
          <input class="form-control" type="file" id="profile_image" name="profile_image" accept="image/jpeg,image/png,image/webp,image/gif">
          <span class="form-hint">Optional. JPG, PNG, WebP or GIF, up to 5 MB.</span>
          <?php if (isset($errors['profile_image'])): ?><span class="form-error"><?= e($errors['profile_image']) ?></span><?php endif; ?>
        </div>

        <div class="form-group <?= isset($errors['password']) ? 'invalid' : '' ?>">
          <label class="form-label" for="password">Password <span class="req">*</span></label>
          <input class="form-control" type="password" id="password" name="password" required minlength="8">
          <span class="form-hint">At least 8 characters with letters and numbers.</span>
          <?php if (isset($errors['password'])): ?><span class="form-error"><?= e($errors['password']) ?></span><?php endif; ?>
        </div>

        <div class="form-group <?= isset($errors['password_confirm']) ? 'invalid' : '' ?>">
          <label class="form-label" for="password_confirm">Confirm password <span class="req">*</span></label>
          <input class="form-control" type="password" id="password_confirm" name="password_confirm" required>
          <?php if (isset($errors['password_confirm'])): ?><span class="form-error"><?= e($errors['password_confirm']) ?></span><?php endif; ?>
        </div>
      </div>

      <button class="btn btn-primary btn-block btn-lg mt-3" type="submit">Create Account</button>
      <p class="text-center mt-2 text-small">Already have an account? <a href="login.php">Log in</a></p>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
