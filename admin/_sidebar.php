<?php
/**
 * Shared admin sidebar.
 * Set $activeNav to one of: dashboard, users, products, categories,
 * orders, reports, reviews, settings.
 */
$admin = current_user();
$pendingListings = (int)db()->query("SELECT COUNT(*) FROM products WHERE status = 'pending'")->fetchColumn();
$pendingReports = (int)db()->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();

$navItems = [
    'dashboard'   => ['📊', 'Dashboard', 'dashboard.php'],
    'users'       => ['👥', 'Users', 'users.php'],
    'products'    => ['📦', 'Listings', 'products.php'],
    'categories'  => ['🗂️', 'Categories', 'categories.php'],
    'orders'      => ['🧾', 'Orders', 'orders.php'],
    'reports'     => ['🚩', 'Reports', 'reports.php'],
    'reviews'     => ['⭐', 'Reviews', 'reviews.php'],
    'settings'    => ['⚙️', 'Settings', 'settings.php'],
];
?>
<aside class="dash-side">
  <div class="card dash-profile">
    <span class="avatar avatar-lg" data-initials="<?= e(strtoupper(substr($admin['full_name'], 0, 2))) ?>">
      <?php if ($admin['profile_image']): ?><img src="<?= e(image_url($admin['profile_image'])) ?>" alt=""><?php endif; ?>
    </span>
    <div class="name"><?= e($admin['full_name']) ?></div>
    <div class="role">Marketplace Admin</div>
  </div>
  <nav class="dash-nav">
    <?php foreach ($navItems as $key => $item): ?>
      <a href="<?= e(APP_BASE_URL . '/admin/' . $item[2]) ?>" class="<?= $activeNav === $key ? 'active' : '' ?>"><?= $item[0] ?> <?= e($item[1]) ?>
        <?php if ($key === 'products' && $pendingListings > 0): ?><span class="nav-badge"><?= (int)$pendingListings ?></span><?php endif; ?>
        <?php if ($key === 'reports' && $pendingReports > 0): ?><span class="nav-badge"><?= (int)$pendingReports ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
</aside>
