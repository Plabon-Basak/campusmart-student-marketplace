<?php
/**
 * My Listings dashboard with per-status actions and seller statistics.
 */
require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = db();
$userId = current_user_id();

// ---------- Status actions (owner only) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $pid = (int)($_POST['product_id'] ?? 0);

    $st = $pdo->prepare('SELECT id, title, status, expires_at FROM products WHERE id = ? AND seller_id = ?');
    $st->execute([$pid, $userId]);
    $item = $st->fetch();

    if ($item === false) {
        set_flash('error', 'Listing not found.');
        redirect('listings.php');
    }

    $expiryDays = max(1, (int)(settings('listing_expiry_days') ?: 30));

    if ($action === 'reserve' && $item['status'] === 'active') {
        $pdo->prepare("UPDATE products SET status = 'reserved' WHERE id = ?")->execute([$pid]);
        audit_log('listing_reserved', 'product', $pid, 'Listing "' . $item['title'] . '" reserved.');
        set_flash('success', 'Listing marked as reserved. No new purchase requests will be accepted.');
    } elseif ($action === 'sold' && in_array($item['status'], ['active', 'reserved'], true)) {
        $pdo->prepare("UPDATE products SET status = 'sold' WHERE id = ?")->execute([$pid]);
        audit_log('listing_sold', 'product', $pid, 'Listing "' . $item['title'] . '" marked as sold.');
        set_flash('success', 'Listing marked as sold.');
    } elseif ($action === 'renew' && in_array($item['status'], ['expired', 'rejected'], true)) {
        $pdo->prepare("UPDATE products SET status = 'active', expires_at = ?, reject_reason = NULL WHERE id = ?")
            ->execute([date('Y-m-d H:i:s', time() + $expiryDays * 86400), $pid]);
        audit_log('listing_renewed', 'product', $pid, 'Listing "' . $item['title'] . '" renewed.');
        set_flash('success', 'Listing renewed and is now active again.');
    } elseif ($action === 'delete' && in_array($item['status'], ['draft', 'pending', 'rejected', 'expired', 'active', 'reserved'], true)) {
        delete_product_image_files($pid);
        $pdo->prepare("UPDATE products SET status = 'removed' WHERE id = ?")->execute([$pid]);
        audit_log('listing_deleted', 'product', $pid, 'Listing "' . $item['title'] . '" removed by seller.');
        set_flash('success', 'Listing removed.');
    } else {
        set_flash('error', 'That action is not allowed for this listing status.');
    }
    redirect('listings.php');
}

// ---------- Statistics ----------
$stats = [];
$st = $pdo->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ? AND status = 'active'");
$st->execute([$userId]); $stats['active'] = (int)$st->fetchColumn();
$st = $pdo->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ? AND status = 'sold'");
$st->execute([$userId]); $stats['sold'] = (int)$st->fetchColumn();
$st = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE seller_id = ? AND status = 'pending'");
$st->execute([$userId]); $stats['pending_orders'] = (int)$st->fetchColumn();
$st = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE seller_id = ? AND status = 'completed'");
$st->execute([$userId]); $stats['total_sales'] = (float)$st->fetchColumn();
$st = $pdo->prepare(
    "SELECT COUNT(*) FROM favorites f JOIN products p ON p.id = f.product_id WHERE p.seller_id = ?"
);
$st->execute([$userId]); $stats['favorites'] = (int)$st->fetchColumn();
$stats['unread_messages'] = unread_notifications_count($userId);

// ---------- Listings with view/favorite counts ----------
$st = $pdo->prepare(
    "SELECT p.*, c.name AS category_name,
            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.id ASC LIMIT 1) AS image_path,
            (SELECT COUNT(*) FROM favorites f WHERE f.product_id = p.id) AS favorite_count
     FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE p.seller_id = ?
     ORDER BY p.created_at DESC"
);
$st->execute([$userId]);
$listings = $st->fetchAll();

$pageTitle = 'My Listings';
$dashboardPage = true;
$activeNav = 'listings';
require __DIR__ . '/../includes/header.php';
?>

<div class="dash-wrap">
  <?php require __DIR__ . '/_sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <div>
        <h1>My Listings</h1>
        <div class="sub">Manage, renew or update your marketplace listings</div>
      </div>
      <a class="btn btn-primary" href="add-listing.php">+ New Listing</a>
    </div>

    <div class="stat-grid mb-3">
      <div class="card stat-card">
        <div class="stat-icon" style="background:var(--color-primary-light);">📦</div>
        <div class="stat-num"><?= (int)$stats['active'] ?></div>
        <div class="stat-name">Active Listings</div>
      </div>
      <div class="card stat-card">
        <div class="stat-icon" style="background:var(--color-success-light);">✅</div>
        <div class="stat-num"><?= (int)$stats['sold'] ?></div>
        <div class="stat-name">Sold Items</div>
      </div>
      <div class="card stat-card">
        <div class="stat-icon" style="background:var(--color-warning-light);">⏳</div>
        <div class="stat-num"><?= (int)$stats['pending_orders'] ?></div>
        <div class="stat-name">Pending Orders</div>
      </div>
      <div class="card stat-card">
        <div class="stat-icon" style="background:var(--color-info-light);">💵</div>
        <div class="stat-num"><?= e(format_price($stats['total_sales'])) ?></div>
        <div class="stat-name">Total Sales</div>
      </div>
      <div class="card stat-card">
        <div class="stat-icon" style="background:#fce7f3;">❤️</div>
        <div class="stat-num"><?= (int)$stats['favorites'] ?></div>
        <div class="stat-name">Favorites Received</div>
      </div>
    </div>

    <?php if (empty($listings)): ?>
      <div class="card empty-state">
        <div class="empty-icon">📦</div>
        <h3>You have no listings yet</h3>
        <p>Sell your first item and reach students across campus.</p>
        <a class="btn btn-primary" href="add-listing.php">Sell an Item</a>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Product</th><th>Price</th><th>Views</th><th>Favorites</th><th>Status</th><th>Date</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($listings as $item): ?>
              <tr>
                <td>
                  <div class="cell-product">
                    <img class="cell-thumb" src="<?= e(image_url($item['image_path'])) ?>" alt="">
                    <div>
                      <strong><?= e($item['title']) ?></strong>
                      <div class="text-small text-muted"><?= e($item['category_name']) ?> · <?= e($item['location']) ?></div>
                    </div>
                  </div>
                </td>
                <td><?= e(format_price($item['price'])) ?></td>
                <td><?= (int)$item['views'] ?></td>
                <td><?= (int)$item['favorite_count'] ?></td>
                <td><span class="badge badge-<?= e(product_status_class($item['status'])) ?>"><?= e(product_status_label($item['status'])) ?></span></td>
                <td class="text-small"><?= e(time_ago($item['created_at'])) ?></td>
                <td>
                  <div class="actions">
                    <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE_URL . '/product-details.php?id=' . (int)$item['id']) ?>">View</a>
                    <?php if (in_array($item['status'], ['draft', 'pending', 'approved', 'active', 'reserved', 'rejected'], true)): ?>
                      <a class="btn btn-ghost btn-sm" href="edit-listing.php?id=<?= (int)$item['id'] ?>">Edit</a>
                    <?php endif; ?>
                    <?php if ($item['status'] === 'active'): ?>
                      <form method="post" action="listings.php" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reserve">
                        <input type="hidden" name="product_id" value="<?= (int)$item['id'] ?>">
                        <button class="btn btn-sm" style="background:var(--color-warning-light); color:#b45309;" type="submit">Reserve</button>
                      </form>
                      <form method="post" action="listings.php" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="sold">
                        <input type="hidden" name="product_id" value="<?= (int)$item['id'] ?>">
                        <button class="btn btn-sm" style="background:var(--color-success-light); color:#15803d;" type="submit" data-confirm="Mark this listing as sold?">Sold</button>
                      </form>
                    <?php endif; ?>
                    <?php if ($item['status'] === 'reserved'): ?>
                      <form method="post" action="listings.php" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="sold">
                        <input type="hidden" name="product_id" value="<?= (int)$item['id'] ?>">
                        <button class="btn btn-sm" style="background:var(--color-success-light); color:#15803d;" type="submit" data-confirm="Mark this listing as sold?">Sold</button>
                      </form>
                    <?php endif; ?>
                    <?php if (in_array($item['status'], ['expired', 'rejected'], true)): ?>
                      <form method="post" action="listings.php" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="renew">
                        <input type="hidden" name="product_id" value="<?= (int)$item['id'] ?>">
                        <button class="btn btn-sm" style="background:var(--color-primary-light); color:var(--color-primary);" type="submit">Renew</button>
                      </form>
                    <?php endif; ?>
                    <?php if (in_array($item['status'], ['draft', 'pending', 'rejected', 'expired', 'active', 'reserved'], true)): ?>
                      <form method="post" action="listings.php" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="product_id" value="<?= (int)$item['id'] ?>">
                        <button class="btn btn-danger-soft btn-sm" type="submit" data-confirm="Remove this listing?">Delete</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </main>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
