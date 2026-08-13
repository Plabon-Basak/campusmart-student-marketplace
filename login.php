<?php
/**
 * Student & admin login.
 */
require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    redirect(current_user()['role'] === 'admin' ? 'admin/dashboard.php' : 'user/dashboard.php');
}

$errors = [];
$email = $_POST['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $errors[] = 'Please enter both email and password.';
    } else {
        $user = attempt_login($email, $password);
        if ($user === null) {
            $errors[] = 'Invalid email or password.';
        } elseif ($user['status'] === 'suspended') {
            $errors[] = 'Your account has been suspended. Contact support for help.';
        } else {
            login_user($user);
            audit_log('login', 'user', (int)$user['id'], 'User logged in.');
            if ($user['role'] === 'admin') {
                redirect('admin/dashboard.php');
            }
            $target = safe_redirect_target('user/dashboard.php');
            redirect($target);
        }
    }
}

$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div class="card auth-card">
    <h1>Welcome back</h1>
    <p class="sub">Log in to browse, buy and sell within your campus.</p>

    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error alert-inline"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" action="login.php" novalidate>
      <?= csrf_field() ?>
      <div class="form-group mb-2">
        <label class="form-label" for="email">University email</label>
        <input class="form-control" type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="email" autofocus>
      </div>
      <div class="form-group mb-3">
        <label class="form-label" for="password">Password</label>
        <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <button class="btn btn-primary btn-block btn-lg" type="submit">Log In</button>
    </form>

    <p class="text-center mt-3 text-small">
      New to CampusMart? <a href="register.php">Create an account</a>
    </p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
