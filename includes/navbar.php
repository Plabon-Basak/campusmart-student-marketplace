<?php
/**
 * Shared responsive navigation bar.
 * Renders public, student or admin menus depending on the logged-in role.
 */
$navUser = current_user();
$navUnread = $navUser ? unread_notifications_count((int)$navUser['id']) : 0;

$navScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$navDir = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));

if ($navDir === 'admin') {
    $navActive = $navScript;
} else {
    $navMap = [
        'index.php'        => 'home',
        'products.php'     => 'browse',
        'search.php'       => 'browse',
        'categories.php'   => 'categories',
        'sell.php'         => 'sell',
        'safety.php'       => 'safety',
        'about.php'        => 'about',
        'dashboard.php'    => 'my-dashboard',
        'add-listing.php'  => 'sell',
        'edit-listing.php' => 'sell',
        'favorites.php'    => 'favorites',
        'messages.php'     => 'messages',
        'notifications.php'=> 'notifications',
    ];
    $navActive = $navMap[$navScript] ?? '';
}

function nav_active(string $key, string $current): string
{
    return $current === $key ? ' class="active"' : '';
}

function nav_initials(array $user): string
{
    $words = preg_split('/\s+/', trim($user['full_name']));
    $initials = '';
    foreach (array_slice($words ?: [], 0, 2) as $w) {
        $initials .= mb_substr($w, 0, 1);
    }
    return strtoupper($initials ?: 'U');
}
?>
<header class="navbar">
  <div class="nav-container">
    <a class="navbar-brand" href="<?= e(APP_BASE_URL . '/index.php') ?>">
      <span class="brand-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22">
          <path d="M6 7h12l-1 13H7L6 7z"/>
          <path d="M9 7a3 3 0 0 1 6 0"/>
        </svg>
      </span>
      <span class="brand-name">Campus<em>Mart</em></span>
    </a>

    <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>

    <nav class="nav-links" id="navLinks" aria-label="Main navigation">
      <ul class="nav-menu">
        <?php if ($navUser && $navUser['role'] === 'admin'): ?>
          <li><a href="<?= e(APP_BASE_URL . '/admin/dashboard.php') ?>"<?= nav_active('dashboard.php', $navActive) ?>>Dashboard</a></li>
          <li><a href="<?= e(APP_BASE_URL . '/admin/users.php') ?>"<?= nav_active('users.php', $navActive) ?>>Users</a></li>
          <li><a href="<?= e(APP_BASE_URL . '/admin/products.php') ?>"<?= nav_active('products.php', $navActive) ?>>Listings</a></li>
          <li><a href="<?= e(APP_BASE_URL . '/admin/categories.php') ?>"<?= nav_active('categories.php', $navActive) ?>>Categories</a></li>
          <li><a href="<?= e(APP_BASE_URL . '/admin/orders.php') ?>"<?= nav_active('orders.php', $navActive) ?>>Orders</a></li>
          <li><a href="<?= e(APP_BASE_URL . '/admin/reports.php') ?>"<?= nav_active('reports.php', $navActive) ?>>Reports</a></li>
          <li><a href="<?= e(APP_BASE_URL . '/admin/reviews.php') ?>"<?= nav_active('reviews.php', $navActive) ?>>Reviews</a></li>
          <li><a href="<?= e(APP_BASE_URL . '/admin/settings.php') ?>"<?= nav_active('settings.php', $navActive) ?>>Settings</a></li>
        <?php else: ?>
          <li><a href="<?= e(APP_BASE_URL . '/index.php') ?>"<?= nav_active('home', $navActive) ?>>Home</a></li>
          <li><a href="<?= e(APP_BASE_URL . '/products.php') ?>"<?= nav_active('browse', $navActive) ?>>Browse</a></li>
          <li><a href="<?= e(APP_BASE_URL . '/categories.php') ?>"<?= nav_active('categories', $navActive) ?>>Categories</a></li>
          <?php if ($navUser): ?>
            <li><a href="<?= e(APP_BASE_URL . '/user/add-listing.php') ?>"<?= nav_active('sell', $navActive) ?>>Sell</a></li>
            <li><a href="<?= e(APP_BASE_URL . '/user/favorites.php') ?>"<?= nav_active('favorites', $navActive) ?>>Favorites</a></li>
            <li><a href="<?= e(APP_BASE_URL . '/user/messages.php') ?>"<?= nav_active('messages', $navActive) ?>>Messages</a></li>
            <li><a href="<?= e(APP_BASE_URL . '/user/notifications.php') ?>"<?= nav_active('notifications', $navActive) ?>>
              Notifications
              <?php if ($navUnread > 0): ?><span class="nav-badge"><?= (int)$navUnread ?></span><?php endif; ?>
            </a></li>
            <li><a href="<?= e(APP_BASE_URL . '/user/dashboard.php') ?>"<?= nav_active('my-dashboard', $navActive) ?>>My Dashboard</a></li>
          <?php else: ?>
            <li><a href="<?= e(APP_BASE_URL . '/safety.php') ?>"<?= nav_active('safety', $navActive) ?>>Safety</a></li>
            <li><a href="<?= e(APP_BASE_URL . '/about.php') ?>"<?= nav_active('about', $navActive) ?>>About</a></li>
          <?php endif; ?>
        <?php endif; ?>
      </ul>

      <ul class="nav-actions">
        <?php if (!$navUser): ?>
          <li><a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE_URL . '/login.php') ?>">Login</a></li>
          <li><a class="btn btn-primary btn-sm" href="<?= e(APP_BASE_URL . '/register.php') ?>">Register</a></li>
        <?php else: ?>
          <li class="nav-user">
            <a class="user-chip" href="<?= e(APP_BASE_URL . ($navUser['role'] === 'admin' ? '/admin/dashboard.php' : '/user/profile.php')) ?>">
              <span class="avatar avatar-xs" data-initials="<?= e(nav_initials($navUser)) ?>"></span>
              <span class="user-name"><?= e($navUser['full_name']) ?></span>
            </a>
            <ul class="user-dropdown">
              <?php if ($navUser['role'] !== 'admin'): ?>
                <li><a href="<?= e(APP_BASE_URL . '/user/dashboard.php') ?>">Dashboard</a></li>
                <li><a href="<?= e(APP_BASE_URL . '/user/profile.php') ?>">Profile</a></li>
                <li><a href="<?= e(APP_BASE_URL . '/user/listings.php') ?>">My Listings</a></li>
                <li><a href="<?= e(APP_BASE_URL . '/user/purchases.php') ?>">My Purchases</a></li>
                <li><a href="<?= e(APP_BASE_URL . '/user/sales.php') ?>">My Sales</a></li>
              <?php else: ?>
                <li><a href="<?= e(APP_BASE_URL . '/admin/dashboard.php') ?>">Admin Panel</a></li>
              <?php endif; ?>
              <li><a class="text-danger" href="<?= e(APP_BASE_URL . '/logout.php') ?>">Logout</a></li>
            </ul>
          </li>
        <?php endif; ?>
      </ul>
    </nav>
  </div>
</header>
