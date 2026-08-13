<?php
/**
 * Admin: marketplace settings.
 */
require_once __DIR__ . '/../includes/admin-auth.php';

$pdo = db();

$allSettings = [
    'site_name'                => ['Site name', 'The name shown in the browser title and footer.', 'text'],
    'site_tagline'             => ['Site tagline', 'Short description of the marketplace.', 'text'],
    'support_email'            => ['Support email', 'Public contact address shown in the footer.', 'email'],
    'currency'                 => ['Currency symbol', 'Symbol used when formatting prices.', 'text'],
    'email_domain'             => ['University email domain', 'Only registrations using @ this domain are accepted. Leave empty to allow any email.', 'text'],
    'listing_expiry_days'      => ['Listing expiry (days)', 'Listings are automatically marked expired after this many days.', 'number'],
    'listings_require_approval'=> ['Require listing approval', 'If enabled, new listings must be approved by an admin before going live.', 'select'],
    'max_listing_images'       => ['Max images per listing', 'Maximum number of images a seller may upload for one listing.', 'number'],
    'trusted_seller_min_reviews'=> ['Trusted seller: min reviews', 'Reviews required before the Trusted Seller badge is shown.', 'number'],
    'trusted_seller_min_rating' => ['Trusted seller: min rating', 'Minimum average rating required for the Trusted Seller badge.', 'number'],
];

$existing = settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($allSettings as $key => $def) {
        if (!isset($_POST[$key])) {
            continue;
        }
        $value = trim((string)$_POST[$key]);
        if (in_array($key, ['listing_expiry_days', 'max_listing_images', 'trusted_seller_min_reviews'], true)) {
            $value = (string)max(1, (int)$value);
        }
        if ($key === 'trusted_seller_min_rating') {
            $value = (string)max(1, min(5, (float)$value));
        }
        if ($key === 'listings_require_approval') {
            $value = $value === '1' ? '1' : '0';
        }
        if ($key === 'currency' && $value === '') {
            $value = '৳';
        }
        save_setting($key, $value);
    }
    audit_log('settings_updated', null, null, 'Marketplace settings updated.');
    set_flash('success', 'Settings saved.');
    redirect('settings.php');
}

$pageTitle = 'Marketplace Settings';
$dashboardPage = true;
$activeNav = 'settings';
require __DIR__ . '/../includes/header.php';
?>

<div class="dash-wrap">
  <?php require __DIR__ . '/_sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <div>
        <h1>Settings</h1>
        <div class="sub">Configure how CampusMart behaves</div>
      </div>
    </div>

    <div class="card dash-section" style="max-width:720px;">
      <form method="post" action="settings.php">
        <?= csrf_field() ?>

        <?php foreach ($allSettings as $key => $def): ?>
          <div class="form-group mb-3">
            <label class="form-label" for="<?= e($key) ?>"><?= e($def[0]) ?></label>
            <?php if ($def[2] === 'select'): ?>
              <select class="form-control" id="<?= e($key) ?>" name="<?= e($key) ?>">
                <option value="1" <?= ($existing[$key] ?? '1') === '1' ? 'selected' : '' ?>>Yes, require approval</option>
                <option value="0" <?= ($existing[$key] ?? '1') === '0' ? 'selected' : '' ?>>No, auto-approve</option>
              </select>
            <?php else: ?>
              <input class="form-control" type="<?= e($def[2]) ?>" id="<?= e($key) ?>" name="<?= e($key) ?>"
                     value="<?= e($existing[$key] ?? '') ?>"
                     <?= $def[2] === 'number' ? 'min="1"' : '' ?>>
            <?php endif; ?>
            <div class="text-small text-muted"><?= e($def[1]) ?></div>
          </div>
        <?php endforeach; ?>

        <div class="form-group mb-3">
          <div class="alert alert-warning alert-inline text-small">
            The university email domain and listing-approval settings are applied to new actions only. Changing them does not rewrite existing accounts or listings.
          </div>
        </div>

        <button class="btn btn-primary" type="submit">Save Settings</button>
      </form>
    </div>
  </main>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
