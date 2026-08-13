<?php
/**
 * 404 error page.
 */
require_once __DIR__ . '/includes/init.php';
http_response_code(404);

$pageTitle = 'Page Not Found';
require __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div class="error-page">
    <div class="error-code">404</div>
    <h1>Page not found</h1>
    <p>The page you are looking for may have been removed, sold, or never existed.</p>
    <a class="btn btn-primary" href="<?= e(APP_BASE_URL . '/index.php') ?>">Back to Homepage</a>
    <a class="btn btn-ghost" href="<?= e(APP_BASE_URL . '/products.php') ?>">Browse Products</a>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
