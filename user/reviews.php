<?php
/**
 * Reviews: leave a review for a completed transaction and browse the
 * reviews you have given or received. Only participants of a completed
 * order may review, and only once per order.
 */
require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = db();
$userId = current_user_id();

// ---------- Submit a review ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $orderId = (int)($_POST['order_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim((string)($_POST['comment'] ?? ''));

    $order = $orderId > 0 ? get_order($orderId) : null;
    if ($order === null || !is_eligible_for_review($orderId, $userId)) {
        set_flash('error', 'This order is not eligible for a review.');
        redirect('reviews.php');
    }
    if ($rating < 1 || $rating > 5) {
        set_flash('error', 'Please select a rating between 1 and 5 stars.');
        redirect('reviews.php?order=' . $orderId);
    }
    if ($comment === '' || mb_strlen($comment) < 5 || mb_strlen($comment) > 1000) {
        set_flash('error', 'Your review must be between 5 and 1000 characters.');
        redirect('reviews.php?order=' . $orderId);
    }

    $reviewedId = (int)$userId === (int)$order['buyer_id'] ? (int)$order['seller_id'] : (int)$order['buyer_id'];

    try {
        $st = $pdo->prepare(
            'INSERT INTO reviews (reviewer_id, reviewed_user_id, order_id, rating, comment, status)
             VALUES (?, ?, ?, ?, ?, "approved")'
        );
        $st->execute([$userId, $reviewedId, $orderId, $rating, $comment]);
    } catch (Throwable $e) {
        set_flash('error', 'You have already reviewed this order.');
        redirect('reviews.php');
    }

    audit_log('review_created', 'review', (int)$pdo->lastInsertId(), 'Review for order ' . $order['order_code'] . ' with rating ' . $rating . '.');
    create_notification(
        $reviewedId,
        'review_received',
        'New review received',
        'You received a ' . $rating . '-star review for order ' . $order['order_code'] . '.',
        $orderId
    );
    set_flash('success', 'Thank you! Your review has been published.');
    redirect('reviews.php');
}

// ---------- Review form mode ----------
$reviewTarget = null;
if (isset($_GET['order'])) {
    $orderId = (int)$_GET['order'];
    $order = $orderId > 0 ? get_order($orderId) : null;
    if ($order !== null && is_eligible_for_review($orderId, $userId)) {
        $reviewTarget = $order;
        $reviewTargetUser = (int)$userId === (int)$order['buyer_id'] ? get_user((int)$order['seller_id']) : get_user((int)$order['buyer_id']);
    }
}

// ---------- My reviews given ----------
$given = $pdo->prepare(
    'SELECT r.*, o.order_code, o.product_id, p.title AS product_title, u.full_name AS reviewed_name
     FROM reviews r
     JOIN orders o ON o.id = r.order_id
     JOIN products p ON p.id = o.product_id
     JOIN users u ON u.id = r.reviewed_user_id
     WHERE r.reviewer_id = ?
     ORDER BY r.created_at DESC LIMIT 50'
);
$given->execute([$userId]);
$given = $given->fetchAll();

// ---------- Reviews received ----------
$received = $pdo->prepare(
    'SELECT r.*, o.order_code, o.product_id, p.title AS product_title, u.full_name AS reviewer_name
     FROM reviews r
     JOIN orders o ON o.id = r.order_id
     JOIN products p ON p.id = o.product_id
     JOIN users u ON u.id = r.reviewer_id
     WHERE r.reviewed_user_id = ?
     ORDER BY r.created_at DESC LIMIT 50'
);
$received->execute([$userId]);
$received = $received->fetchAll();

// ---------- Eligible orders awaiting my review ----------
$eligible = $pdo->prepare(
    "SELECT o.*, p.title AS product_title, u.full_name AS other_name
     FROM orders o
     JOIN products p ON p.id = o.product_id
     JOIN users u ON u.id = CASE WHEN o.buyer_id = ? THEN o.seller_id ELSE o.buyer_id END
     WHERE o.status = 'completed' AND (o.buyer_id = ? OR o.seller_id = ?)
       AND NOT EXISTS (SELECT 1 FROM reviews r WHERE r.order_id = o.id AND r.reviewer_id = ?)
     ORDER BY o.completed_at DESC LIMIT 20"
);
$eligible->execute([$userId, $userId, $userId, $userId]);
$eligible = $eligible->fetchAll();

$pageTitle = 'Reviews';
$dashboardPage = true;
$activeNav = 'reviews';
require __DIR__ . '/../includes/header.php';
?>

<div class="dash-wrap">
  <?php require __DIR__ . '/_sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <div>
        <h1>Reviews</h1>
        <div class="sub">Rate completed transactions and see how others rate you</div>
      </div>
    </div>

    <?php if ($reviewTarget !== null): ?>
      <div class="card dash-section" style="max-width:640px;">
        <h3>Leave a review</h3>
        <p class="text-small text-muted">
          Order <?= e($reviewTarget['order_code']) ?> · <?= e($reviewTarget['product_title']) ?> ·
          with <?= e($reviewTargetUser['full_name'] ?? 'User') ?>
        </p>
        <form method="post" action="reviews.php">
          <?= csrf_field() ?>
          <input type="hidden" name="order_id" value="<?= (int)$reviewTarget['id'] ?>">

          <div class="form-group mb-2">
            <label class="form-label">Your rating</label>
            <div class="star-input">
              <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" required>
                <label for="star<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">★</label>
              <?php endfor; ?>
            </div>
          </div>

          <div class="form-group mb-2">
            <label class="form-label" for="comment">Your review</label>
            <textarea class="form-control" id="comment" name="comment" rows="4" minlength="5" maxlength="1000"
                      placeholder="How was the transaction? Be honest and helpful." required></textarea>
          </div>

          <div class="modal-actions" style="justify-content:flex-start;">
            <button class="btn btn-primary" type="submit">Publish Review</button>
            <a class="btn btn-ghost" href="<?= e(APP_BASE_URL . '/user/reviews.php') ?>">Cancel</a>
          </div>
        </form>
      </div>
    <?php elseif (!empty($eligible)): ?>
      <div class="dash-section">
        <h3>Transactions awaiting your review</h3>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Order</th>
                <th>Product</th>
                <th>With</th>
                <th>Completed</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($eligible as $o): ?>
                <tr>
                  <td><strong><?= e($o['order_code']) ?></strong></td>
                  <td><?= e($o['product_title']) ?></td>
                  <td><?= e($o['other_name']) ?></td>
                  <td><?= e(time_ago($o['completed_at'])) ?></td>
                  <td><a class="btn btn-primary btn-sm" href="<?= e(APP_BASE_URL . '/user/reviews.php?order=' . (int)$o['id']) ?>">Leave a Review</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <div class="dash-section">
      <h3>Reviews I've Given</h3>
      <?php if (empty($given)): ?>
        <p class="text-muted text-small">You have not written any reviews yet.</p>
      <?php else: ?>
        <?php foreach ($given as $r): ?>
          <div class="review-row">
            <div>
              <?= rating_stars((float)$r['rating']) ?>
              <span class="text-small text-muted">for <?= e($r['product_title']) ?> · <?= e($r['order_code']) ?> · <?= e(time_ago($r['created_at'])) ?></span>
            </div>
            <div class="review-by"><?= e($r['comment']) ?></div>
            <div class="text-small text-muted">Reviewed: <?= e($r['reviewed_name']) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="dash-section">
      <h3>Reviews I've Received</h3>
      <?php if (empty($received)): ?>
        <p class="text-muted text-small">No one has reviewed you yet. Complete transactions to start earning ratings.</p>
      <?php else: ?>
        <?php foreach ($received as $r): ?>
          <div class="review-row">
            <div>
              <?= rating_stars((float)$r['rating']) ?>
              <span class="text-small text-muted">for <?= e($r['product_title']) ?> · <?= e($r['order_code']) ?> · <?= e(time_ago($r['created_at'])) ?></span>
            </div>
            <div class="review-by"><?= e($r['comment']) ?></div>
            <div class="text-small text-muted">From: <?= e($r['reviewer_name']) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
