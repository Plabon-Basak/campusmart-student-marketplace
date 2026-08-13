<?php
/**
 * My Favorites - saved products with availability status and removal.
 */
require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = db();
$userId = current_user_id();

// Remove a favorite (form fallback; the heart button also works via AJAX).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pid = (int)($_POST['product_id'] ?? 0);
    $st = $pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND product_id = ?');
    $st->execute([$userId, $pid]);
    set_flash('success', 'Removed from favorites.');
    redirect('favorites.php');
}

$st = $pdo->prepare(
    "SELECT f.id AS fav_id, f.created_at AS favorited_at,
            p.*, c.name AS category_name, u.full_name AS seller_name, u.status AS seller_status,
            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.id ASC LIMIT 1) AS image_path
     FROM favorites f
     JOIN products p ON p.id = f.product_id
     JOIN categories c ON c.id = p.category_id
     JOIN users u ON u.id = p.seller_id
     WHERE f.user_id = ?
     ORDER BY f.created_at DESC"
);
$st->execute([$userId]);
$favorites = $st->fetchAll();

$pageTitle = 'My Favorites';
$dashboardPage = true;
$activeNav = 'favorites';
require __DIR__ . '/../includes/header.php';
?>

<div class="dash-wrap">
  <?php require __DIR__ . '/_sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <div>
        <h1>My Favorites</h1>
        <div class="sub">Items you saved for later</div>
      </div>
    </div>

    <?php if (empty($favorites)): ?>
      <div class="card empty-state">
        <div class="empty-icon">❤️</div>
        <h3>No favorites yet</h3>
        <p>Tap the heart on any listing to save it here and compare later.</p>
        <a class="btn btn-primary" href="<?= e(APP_BASE_URL . '/products.php') ?>">Browse Marketplace</a>
      </div>
    <?php else: ?>
      <div class="product-grid">
        <?php foreach ($favorites as $fav): ?>
          <article class="card product-card">
            <?php if ($fav['status'] !== 'active'): ?>
              <span class="status-ribbon badge badge-<?= e(product_status_class($fav['status'])) ?>"><?= e(product_status_label($fav['status'])) ?></span>
            <?php endif; ?>
            <button type="button" class="fav-btn active" data-fav="<?= (int)$fav['id'] ?>" title="Remove from favorites" aria-label="Remove from favorites">&#9825;</button>
            <a class="card-media" href="<?= e(APP_BASE_URL . '/product-details.php?id=' . (int)$fav['id']) ?>">
              <img src="<?= e(image_url($fav['image_path'])) ?>" alt="<?= e($fav['title']) ?>" loading="lazy">
            </a>
            <div class="card-body">
              <a class="card-title" href="<?= e(APP_BASE_URL . '/product-details.php?id=' . (int)$fav['id']) ?>"><?= e($fav['title']) ?></a>
              <div class="card-price"><?= e(format_price($fav['price'])) ?></div>
              <div class="card-meta">
                <span><?= e($fav['condition_label']) ?></span>
                <span>·</span>
                <span><?= e($fav['seller_name']) ?></span>
              </div>
              <?php if ($fav['status'] === 'sold' || $fav['status'] === 'removed'): ?>
                <div class="alert alert-muted alert-inline text-small" style="margin-bottom:0;">This item is no longer available.</div>
              <?php endif; ?>
              <div class="card-foot">
                <span class="text-muted">Saved <?= e(time_ago($fav['favorited_at'])) ?></span>
                <form method="post" action="favorites.php" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="product_id" value="<?= (int)$fav['id'] ?>">
                  <button class="btn btn-ghost btn-sm" type="submit">Remove</button>
                </form>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
