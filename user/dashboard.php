<?php
/**
 * Student dashboard: stats, recent activity, recommended products and quick actions.
 */
require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = db();
$userId = current_user_id();
$user = current_user();

// ---------- Stats ----------
$stats = [];
$st = $pdo->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ? AND status IN ('active','reserved')");
$st->execute([$userId]); $stats['active_listings'] = (int)$st->fetchColumn();
$st = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id = ? AND status IN ('pending','accepted','ready')");
$st->execute([$userId]); $stats['active_purchases'] = (int)$st->fetchColumn();
$st = $pdo->prepare('SELECT COUNT(*) FROM favorites WHERE user_id = ?');
$st->execute([$userId]); $stats['favorites'] = (int)$st->fetchColumn();
$stats['unread_notifications'] = unread_notifications_count($userId);
$st = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE seller_id = ? AND status = 'pending'");
$st->execute([$userId]);
$stats['pending_requests'] = (int)$st->fetchColumn();

// ---------- Recent activity (orders + notifications merged) ----------
$activity = [];

$st = $pdo->prepare(
    "SELECT o.*, p.title AS product_title,
            CASE WHEN o.buyer_id = ? THEN 'bought' ELSE 'sold' END AS direction,
            CASE WHEN o.buyer_id = ? THEN u2.full_name ELSE u1.full_name END AS other_name
     FROM orders o
     JOIN products p ON p.id = o.product_id
     JOIN users u1 ON u1.id = o.seller_id
     JOIN users u2 ON u2.id = o.buyer_id
     WHERE o.buyer_id = ? OR o.seller_id = ?
     ORDER BY o.created_at DESC LIMIT 5"
);
$st->execute([$userId, $userId, $userId, $userId]);
foreach ($st->fetchAll() as $row) {
    $activity[] = [
        'at'  => $row['created_at'],
        'txt' => ($row['direction'] === 'bought'
            ? 'You requested to buy "' . $row['product_title'] . '" from ' . $row['other_name'] . ' — ' . order_status_label($row['status'])
            : '"' . $row['product_title'] . '" — ' . order_status_label($row['status']) . ' (' . $row['other_name'] . ')'),
    ];
}

$st = $pdo->prepare('SELECT created_at, title, message FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$st->execute([$userId]);
foreach ($st->fetchAll() as $row) {
    $activity[] = ['at' => $row['created_at'], 'txt' => $row['title'] . ($row['message'] ? ' — ' . $row['message'] : '')];
}

usort($activity, fn($a, $b) => strcmp($b['at'], $a['at']));
$activity = array_slice($activity, 0, 7);

// ---------- Recommendations ----------
$recommendations = recommendations_for_user($userId, 4);

// Greeting based on the time of day.
$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

$pageTitle = 'My Dashboard';
$dashboardPage = true;
$activeNav = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="dash-wrap">
  <?php require __DIR__ . '/_sidebar.php'; ?>

  <main class="dash-main">
    <div class="welcome-card">
      <h1><?= e($greeting) ?>, <?= e(explode(' ', $user['full_name'])[0]) ?> 👋</h1>
      <p>Here is what is happening with your marketplace activity today.</p>
    </div>

    <div class="stat-grid mb-3">
      <div class="card stat-card">
        <div class="stat-icon" style="background:var(--color-primary-light);">📦</div>
        <div class="stat-num"><?= (int)$stats['active_listings'] ?></div>
        <div class="stat-name">Active Listings</div>
      </div>
      <div class="card stat-card">
        <div class="stat-icon" style="background:var(--color-info-light);">🛍️</div>
        <div class="stat-num"><?= (int)$stats['active_purchases'] ?></div>
        <div class="stat-name">Active Purchases</div>
      </div>
      <div class="card stat-card">
        <div class="stat-icon" style="background:#fce7f3;">❤️</div>
        <div class="stat-num"><?= (int)$stats['favorites'] ?></div>
        <div class="stat-name">Favorites</div>
      </div>
      <div class="card stat-card">
        <div class="stat-icon" style="background:var(--color-warning-light);">💬</div>
        <div class="stat-num"><?= (int)$stats['unread_notifications'] ?></div>
        <div class="stat-name">Unread Notifications</div>
      </div>
      <div class="card stat-card">
        <div class="stat-icon" style="background:var(--color-success-light);">⏳</div>
        <div class="stat-num"><?= (int)$stats['pending_requests'] ?></div>
        <div class="stat-name">Requests Awaiting You</div>
      </div>
    </div>

    <div class="quick-actions mb-4">
      <a class="btn btn-primary" href="<?= e(APP_BASE_URL . '/user/add-listing.php') ?>">+ Sell an Item</a>
      <a class="btn btn-ghost" href="<?= e(APP_BASE_URL . '/products.php') ?>">Browse Marketplace</a>
      <a class="btn btn-ghost" href="<?= e(APP_BASE_URL . '/user/purchases.php') ?>">View Purchases</a>
      <a class="btn btn-ghost" href="<?= e(APP_BASE_URL . '/user/messages.php') ?>">View Messages</a>
      <a class="btn btn-ghost" href="<?= e(APP_BASE_URL . '/user/favorites.php') ?>">View Favorites</a>
    </div>

    <?php if ($activity): ?>
      <div class="dash-section">
        <h3>Recent Activity</h3>
        <div class="timeline">
          <?php foreach ($activity as $item): ?>
            <div class="timeline-item">
              <div class="time"><?= e(time_ago($item['at'])) ?></div>
              <div class="txt"><?= e($item['txt']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($recommendations): ?>
      <div class="dash-section">
        <h3>Recommended for You</h3>
        <p class="text-muted text-small mb-3">Based on your favorites and past purchases.</p>
        <div class="product-grid">
          <?php foreach ($recommendations as $product): require __DIR__ . '/../includes/product-card.php'; endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </main>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
