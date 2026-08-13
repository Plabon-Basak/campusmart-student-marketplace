<?php
/**
 * My Purchases - the buyer's order history with cancellation and review links.
 */
require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = db();
$userId = current_user_id();

// ---------- Cancel an eligible order ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $orderId = (int)($_POST['order_id'] ?? 0);

    $order = $orderId > 0 ? get_order($orderId) : null;
    if ($order === null || (int)$order['buyer_id'] !== $userId) {
        set_flash('error', 'Order not found.');
        redirect('purchases.php');
    }

    if ($action === 'cancel') {
        $result = apply_order_transition($pdo, $order, 'cancelled', $userId);
        set_flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('purchases.php');
    }

    set_flash('error', 'Invalid action.');
    redirect('purchases.php');
}

// ---------- Orders as buyer ----------
$st = $pdo->prepare(
    "SELECT o.*, p.title AS product_title, p.status AS product_status, p.contact_preference,
            u.full_name AS seller_name, u.status AS seller_status,
            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = o.product_id ORDER BY pi.is_primary DESC, pi.id ASC LIMIT 1) AS image_path
     FROM orders o
     JOIN products p ON p.id = o.product_id
     JOIN users u ON u.id = o.seller_id
     WHERE o.buyer_id = ?
     ORDER BY o.created_at DESC"
);
$st->execute([$userId]);
$orders = $st->fetchAll();

$pageTitle = 'My Purchases';
$dashboardPage = true;
$activeNav = 'purchases';
require __DIR__ . '/../includes/header.php';
?>

<div class="dash-wrap">
  <?php require __DIR__ . '/_sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <div>
        <h1>My Purchases</h1>
        <div class="sub">Track your purchase requests and pickups</div>
      </div>
    </div>

    <?php if (empty($orders)): ?>
      <div class="card empty-state">
        <div class="empty-icon">🛍️</div>
        <h3>No purchases yet</h3>
        <p>When you request to buy an item, it will show up here.</p>
        <a class="btn btn-primary" href="<?= e(APP_BASE_URL . '/products.php') ?>">Browse Marketplace</a>
      </div>
    <?php else: ?>
      <?php foreach ($orders as $o): ?>
        <div class="card order-box">
          <div class="order-left">
            <img class="order-thumb" src="<?= e(image_url($o['image_path'])) ?>" alt="">
            <div>
              <div class="order-code"><?= e($o['order_code']) ?>
                <span class="badge badge-<?= e(order_status_class($o['status'])) ?>" style="margin-left:6px;"><?= e(order_status_label($o['status'])) ?></span>
              </div>
              <a href="<?= e(APP_BASE_URL . '/product-details.php?id=' . (int)$o['product_id']) ?>"><?= e($o['product_title']) ?></a>
              <div class="order-meta">
                Seller: <?= e($o['seller_name']) ?> · <?= (int)$o['quantity'] ?> × <?= e(format_price($o['unit_price'])) ?> = <?= e(format_price($o['total_amount'])) ?>
                · Ordered <?= e(time_ago($o['created_at'])) ?>
              </div>
              <?php if (in_array($o['status'], ['accepted', 'ready'], true) && $o['pickup_location']): ?>
                <div class="order-meta">📍 Pickup: <?= e($o['pickup_location']) ?><?= $o['pickup_time'] ? ' · ' . e($o['pickup_time']) : '' ?></div>
              <?php endif; ?>
              <?php if ($o['status'] === 'rejected'): ?>
                <div class="order-meta">The seller could not accept this request at this time.</div>
              <?php endif; ?>
            </div>
          </div>
          <div class="order-right">
            <?php if (in_array($o['status'], ['pending', 'accepted', 'ready'], true)): ?>
              <div class="status-track">
                <div class="status-step <?= $o['status'] === 'pending' ? 'active' : (in_array($o['status'], ['accepted','ready','completed']) ? 'done' : '') ?>"><span class="dot"></span>Pending</div>
                <span class="line"></span>
                <div class="status-step <?= $o['status'] === 'accepted' ? 'active' : (in_array($o['status'], ['ready','completed']) ? 'done' : '') ?>"><span class="dot"></span>Accepted</div>
                <span class="line"></span>
                <div class="status-step <?= $o['status'] === 'ready' ? 'active' : ($o['status'] === 'completed' ? 'done' : '') ?>"><span class="dot"></span>Ready</div>
                <span class="line"></span>
                <div class="status-step <?= $o['status'] === 'completed' ? 'done' : '' ?>"><span class="dot"></span>Done</div>
              </div>
            <?php endif; ?>

            <div class="actions" style="justify-content:flex-end;">
              <?php if ($o['status'] === 'pending'): ?>
                <form method="post" action="purchases.php" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="cancel">
                  <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                  <button class="btn btn-danger-soft btn-sm" type="submit" data-confirm="Cancel this purchase request?">Cancel Request</button>
                </form>
              <?php endif; ?>
              <?php if ($o['status'] === 'completed' && is_eligible_for_review((int)$o['id'], $userId)): ?>
                <a class="btn btn-primary btn-sm" href="<?= e(APP_BASE_URL . '/user/reviews.php') ?>">Leave a Review</a>
              <?php endif; ?>
              <?php if ($o['status'] === 'completed' && $o['contact_preference'] !== 'Phone'): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE_URL . '/user/messages.php') ?>">Message Seller</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
