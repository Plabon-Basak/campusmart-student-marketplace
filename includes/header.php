<?php
/**
 * Shared HTML header + navbar + flash messages.
 * Pages set $pageTitle (optional) before including this file.
 */
$pageTitle = $pageTitle ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= e(APP_TAGLINE) ?>">
  <title><?= $pageTitle !== '' ? e($pageTitle) . ' · ' : '' ?><?= e(settings('site_name') ?: APP_NAME) ?></title>
  <link rel="icon" href="<?= e(APP_BASE_URL . '/assets/images/favicon.svg') ?>" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="<?= e(APP_BASE_URL . '/assets/css/style.css?v=' . filemtime(__DIR__ . '/../assets/css/style.css')) ?>">
  <?php if (isset($dashboardPage) && $dashboardPage): ?>
    <link rel="stylesheet" href="<?= e(APP_BASE_URL . '/assets/css/dashboard.css?v=' . filemtime(__DIR__ . '/../assets/css/dashboard.css')) ?>">
  <?php endif; ?>
  <script>
    window.CM_BASE = <?= json_encode(APP_BASE_URL) ?>;
    window.CM_CSRF = <?= json_encode(csrf_token()) ?>;
  </script>
</head>
<body>
<?php require __DIR__ . '/navbar.php'; ?>

<?php if ($flash = get_flash()): ?>
  <div class="flash-container" id="flashContainer">
    <div class="alert alert-<?= e($flash['type']) ?>" role="alert">
      <span><?= e($flash['message']) ?></span>
      <button class="alert-close" type="button" aria-label="Close">&times;</button>
    </div>
  </div>
<?php endif; ?>
