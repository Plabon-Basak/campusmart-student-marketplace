<?php
/**
 * Shared student dashboard sidebar.
 * Set $activeNav to one of: dashboard, add-listing, listings, purchases,
 * sales, favorites, messages, notifications, reviews, profile.
 */
$u = current_user();
$unreadCount = unread_notifications_count((int)$u['id']);
$navItems = [
    'dashboard'     => ['📊', 'Dashboard', 'dashboard.php'],
    'add-listing'   => ['➕', 'Sell an Item', 'add-listing.php'],
    'listings'      => ['📦', 'My Listings', 'listings.php'],
    'purchases'     => ['🛍️', 'My Purchases', 'purchases.php'],
    'sales'         => ['💰', 'My Sales', 'sales.php'],
    'favorites'     => ['❤️', 'Favorites', 'favorites.php'],
    'messages'      => ['💬', 'Messages', 'messages.php'],
    'notifications' => ['🔔', 'Notifications', 'notifications.php'],
    'reviews'       => ['⭐', 'Reviews', 'reviews.php'],
    'profile'       => ['👤', 'Profile', 'profile.php'],
];
?>
<aside class="dash-side">
  <div class="card dash-profile">
    <span class="avatar avatar-lg" data-initials="<?= e(strtoupper(substr($u['full_name'], 0, 2))) ?>">
      <?php if ($u['profile_image']): ?><img src="<?= e(image_url($u['profile_image'])) ?>" alt=""><?php endif; ?>
    </span>
    <div class="name"><?= e($u['full_name']) ?></div>
    <div class="role"><?= e($u['department'] ?? 'Student') ?></div>
  </div>
  <nav class="dash-nav">
    <?php foreach ($navItems as $key => $item): ?>
      <?php if ($key === 'notifications'): ?>
        <a href="<?= e(APP_BASE_URL . '/user/' . $item[2]) ?>" class="<?= $activeNav === $key ? 'active' : '' ?>"><?= $item[0] ?> <?= e($item[1]) ?>
          <?php if ($unreadCount > 0): ?><span class="nav-badge"><?= (int)$unreadCount ?></span><?php endif; ?>
        </a>
      <?php else: ?>
        <a href="<?= e(APP_BASE_URL . '/user/' . $item[2]) ?>" class="<?= $activeNav === $key ? 'active' : '' ?>"><?= $item[0] ?> <?= e($item[1]) ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
</aside>
