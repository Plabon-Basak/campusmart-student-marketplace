<?php
/**
 * My Sales - the seller's incoming purchase requests and sales history.
 * Seller actions: accept / reject (pending), mark ready (accepted),
 * complete (ready). Uses the shared apply_order_transition() rules.
 */
require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = db();
$userId = current_user_id();

// ---------- Seller actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $orderId = (int)($_POST['order_id'] ?? 0);

    $order = $orderId > 0 ? get_order($orderId) : null;
    if ($order === null || (int)$order['seller_id'] !== $userId) {
        set_flash('error', 'Order not found.');
        redirect('sales.php');
    }

    $result = apply_order_transition($pdo, $order, $action, $userId, [
        'pickup_location' => (string)($_POST['pickup_location'] ?? ''),
        'pickup_time'     => (string)($_POST['pickup_time'] ?? ''),
        'reason'          => (string)($_POST['reason'] ?? ''),
    ]);
    set_flash($result['ok'] ? 'success' : 'error', $result['message']);
    redirect('sales.php');
}

// ---------- Orders as seller ----------
$st = $pdo->prepare(
    "SELECT o.*, p.title AS product_title, p.status AS product_status,
            u.full_name AS buyer_name, u.status AS buyer_status,
            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = o.product_id ORDER BY pi.is_primary DESC, pi.id ASC LIMIT 1) AS image_path
     FROM orders o
     JOIN products p ON p.id = o.product_id
     JOIN users u ON u.id = o.buyer_id
     WHERE o.seller_id = ?
     ORDER BY o.created_at DESC"
);
$st->execute([$userId]);
$orders = $st->fetchAll();

$pageTitle = 'My Sales';
$dashboardPage = true;
$activeNav = 'sales';
require __DIR__ . '/../includes/header.php';
?>

<div class="dash-wrap">
  <?php require __DIR__ . '/_sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <div>
        <h1>My Sales</h1>
        <div class="sub">Review purchase requests and manage deliveries</div>
      </div>
    </div>

    <?php if (empty($orders)): ?>
      <div class="card empty-state">
        <div class="empty-icon">💰</div>
        <h3>No sales yet</h3>
        <p>When a buyer requests one of your listings, you'll see it here.</p>
        <a class="btn btn-primary" href="<?= e(APP_BASE_URL . '/user/add-listing.php') ?>">Sell an Item</a>
      </div>
    <?php else: ?>
      <?php foreach ($orders as $o): ?>
        <div class="card order-box">
          <div class="order-left">
            <img class="order-thumb" src="<?= e(image_url($o['image_path'])) ?>" alt="">
            <div>
              <div class="order-code"><?= e($o['order_code']) ?>
                <span class="badge badge-<?= e(order_status_class($o['status'])) ?>" style="margin-left:6px;"><?= e(order_status_label($o['status'])) ?></span>
                <span class="badge badge-outline" style="margin-left:4px;"><?= e($o['payment_status']) === 'paid' ? 'Paid' : 'Payment pending' ?></span>
              </div>
              <a href="<?= e(APP_BASE_URL . '/product-details.php?id=' . (int)$o['product_id']) ?>"><?= e($o['product_title']) ?></a>
              <div class="order-meta">
                Buyer: <?= e($o['buyer_name']) ?> · <?= (int)$o['quantity'] ?> × <?= e(format_price($o['unit_price'])) ?> = <?= e(format_price($o['total_amount'])) ?>
                · Requested <?= e(time_ago($o['created_at'])) ?>
              </div>
              <?php if ($o['pickup_location']): ?>
                <div class="order-meta">📍 Pickup: <?= e($o['pickup_location']) ?><?= $o['pickup_time'] ? ' · ' . e($o['pickup_time']) : '' ?></div>
              <?php endif; ?>
            </div>
          </div>
          <div class="order-right">
            <?php if ($o['status'] === 'pending'): ?>
              <form method="post" action="sales.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="accepted">
                <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                <div class="form-group mb-1">
                  <label class="form-label" for="pickup_location_<?= (int)$o['id'] ?>">Pickup location (optional)</label>
                  <input class="form-control" type="text" id="pickup_location_<?= (int)$o['id'] ?>" name="pickup_location" placeholder="e.g. Central cafeteria">
                </div>
                <button class="btn btn-success btn-sm" type="submit">Accept Request</button>
              </form>
              <form method="post" action="sales.php" style="margin-top:8px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="rejected">
                <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                <button class="btn btn-danger-soft btn-sm" type="submit" data-confirm="Reject this purchase request?">Reject</button>
              </form>
            <?php elseif ($o['status'] === 'accepted'): ?>
              <form method="post" action="sales.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="ready">
                <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                <div class="form-group mb-1">
                  <label class="form-label" for="pickup_location_<?= (int)$o['id'] ?>">Pickup location <span class="req">*</span></label>
                  <input class="form-control" type="text" id="pickup_location_<?= (int)$o['id'] ?>" name="pickup_location" value="<?= e($o['pickup_location'] ?? '') ?>" placeholder="e.g. Central cafeteria" required>
                </div>
                <div class="form-group mb-1">
                  <label class="form-label" for="pickup_time_<?= (int)$o['id'] ?>">Pickup time</label>
                  <input class="form-control" type="text" id="pickup_time_<?= (int)$o['id'] ?>" name="pickup_time" placeholder="e.g. After 5pm" value="<?= e($o['pickup_time'] ?? '') ?>">
                </div>
                <button class="btn btn-primary btn-sm" type="submit">Mark Ready for Pickup</button>
              </form>
            <?php elseif ($o['status'] === 'ready'): ?>
              <div class="alert alert-warning alert-inline text-small" style="margin-bottom:10px;">Waiting for the buyer to pick up the item.</div>
              <form method="post" action="sales.php" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="completed">
                <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                <button class="btn btn-success btn-sm" type="submit" data-confirm="Confirm the item was handed over and mark this order completed?">Mark Completed</button>
              </form>
            <?php elseif ($o['status'] === 'completed' && is_eligible_for_review((int)$o['id'], $userId)): ?>
              <a class="btn btn-primary btn-sm" href="<?= e(APP_BASE_URL . '/user/reviews.php') ?>">Leave a Review</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
