<?php
/**
 * Shared footer + script includes.
 */
$siteName = settings('site_name') ?: APP_NAME;
$supportEmail = settings('support_email') ?: '';
?>
<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-col">
      <a class="navbar-brand" href="<?= e(APP_BASE_URL . '/index.php') ?>">
        <span class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22">
            <path d="M6 7h12l-1 13H7L6 7z"/>
            <path d="M9 7a3 3 0 0 1 6 0"/>
          </svg>
        </span>
        <span class="brand-name">Campus<em>Mart</em></span>
      </a>
      <p><?= e(settings('site_tagline') ?: APP_TAGLINE) ?></p>
      <p class="footer-muted">A safe student-to-student marketplace for your campus.</p>
    </div>

    <div class="footer-col">
      <h4>Marketplace</h4>
      <ul>
        <li><a href="<?= e(APP_BASE_URL . '/products.php') ?>">Browse Products</a></li>
        <li><a href="<?= e(APP_BASE_URL . '/categories.php') ?>">Categories</a></li>
        <li><a href="<?= e(APP_BASE_URL . '/user/add-listing.php') ?>">Sell an Item</a></li>
        <li><a href="<?= e(APP_BASE_URL . '/search.php') ?>">Search</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h4>Community</h4>
      <ul>
        <li><a href="<?= e(APP_BASE_URL . '/safety.php') ?>">Campus Safety</a></li>
        <li><a href="<?= e(APP_BASE_URL . '/about.php') ?>">About Us</a></li>
        <li><a href="<?= e(APP_BASE_URL . '/contact.php') ?>">Contact</a></li>
        <li><a href="<?= e(APP_BASE_URL . '/register.php') ?>">Create Account</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h4>Support</h4>
      <ul>
        <?php if ($supportEmail !== ''): ?>
          <li><a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a></li>
        <?php endif; ?>
        <li>Meet in public campus spots.</li>
        <li>Inspect items before paying.</li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="container">
      <p>&copy; <?= date('Y') ?> <?= e($siteName) ?>. Buy. Sell. Connect. Within Your Campus.</p>
    </div>
  </div>
</footer>

<script src="<?= e(APP_BASE_URL . '/assets/js/main.js') ?>" defer></script>
<?php if (isset($extraScripts) && is_array($extraScripts)): ?>
  <?php foreach ($extraScripts as $script): ?>
    <script src="<?= e(APP_BASE_URL . $script) ?>" defer></script>
  <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
