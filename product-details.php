<?php
/**
 * Product details page: gallery, info, seller, buy request, messaging,
 * reporting, seller reviews and similar products.
 */
require_once __DIR__ . '/includes/init.php';

$pdo = db();
$pid = (int)($_GET['id'] ?? 0);
$product = $pid > 0 ? get_product($pid) : null;

if ($product === null) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$isOwner = is_logged_in() && current_user_id() === (int)$product['seller_id'];
$isAdmin = is_admin();
$publicStatuses = ['active', 'reserved', 'sold'];
if (!in_array($product['status'], $publicStatuses, true) && !$isOwner && !$isAdmin) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// ---------- POST handlers ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'request_purchase') {
        verify_csrf();
        require_login();
        $user = current_user();
        $qty = max(1, (int)($_POST['quantity'] ?? 1));

        if ((int)$product['seller_id'] === current_user_id()) {
            set_flash('error', 'You cannot purchase your own listing.');
            redirect_back('product-details.php?id=' . $pid);
        }
        if ($user['status'] !== 'active') {
            set_flash('error', 'Your account is not active.');
            redirect_back('product-details.php?id=' . $pid);
        }
        if ($product['status'] !== 'active') {
            set_flash('error', 'This listing is not available for purchase right now.');
            redirect_back('product-details.php?id=' . $pid);
        }
        if ($product['seller_status'] !== 'active') {
            set_flash('error', 'The seller account is unavailable right now.');
            redirect_back('product-details.php?id=' . $pid);
        }
        if ($qty > (int)$product['quantity']) {
            set_flash('error', 'Only ' . (int)$product['quantity'] . ' unit(s) are available.');
            redirect_back('product-details.php?id=' . $pid);
        }
        if (user_has_open_request(current_user_id(), $pid)) {
            set_flash('warning', 'You already have a pending request for this listing.');
            redirect_back('product-details.php?id=' . $pid);
        }

        $pdo->beginTransaction();
        try {
            // Re-check quantity inside the transaction.
            $chk = $pdo->prepare('SELECT quantity, status FROM products WHERE id = ? FOR UPDATE');
            $chk->execute([$pid]);
            $row = $chk->fetch();
            if ($row === false || $row['status'] !== 'active' || (int)$row['quantity'] < $qty) {
                throw new RuntimeException('Listing no longer available.');
            }

            $orderCode = generate_order_code($pdo);
            $unitPrice = (float)$product['price'];
            $total = $unitPrice * $qty;

            $st = $pdo->prepare(
                'INSERT INTO orders (order_code, buyer_id, seller_id, product_id, quantity, unit_price, total_amount, payment_method, payment_status, status, status_history)
                 VALUES (?, ?, ?, ?, ?, ?, ?, \'cash\', \'pending\', \'pending\', ?)'
            );
            $history = json_encode([['status' => 'pending', 'at' => date('Y-m-d H:i:s')]]);
            $st->execute([$orderCode, current_user_id(), (int)$product['seller_id'], $pid, $qty, $unitPrice, $total, $history]);
            $orderId = (int)$pdo->lastInsertId();

            // Reduce available quantity; reserve the item when it hits zero.
            $newQty = (int)$row['quantity'] - $qty;
            if ($newQty === 0) {
                $pdo->prepare("UPDATE products SET quantity = 0, status = 'reserved' WHERE id = ?")->execute([$pid]);
            } else {
                $pdo->prepare('UPDATE products SET quantity = ? WHERE id = ?')->execute([$newQty, $pid]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            set_flash('error', 'The request could not be created. ' . ($e instanceof RuntimeException ? $e->getMessage() : 'Please try again.'));
            redirect_back('product-details.php?id=' . $pid);
        }

        audit_log('order_created', 'order', $orderId, 'Purchase request ' . $orderCode . ' created for product "' . $product['title'] . '".');
        create_notification(
            (int)$product['seller_id'],
            'order_received',
            'Purchase request received',
            $user['full_name'] . ' requested to purchase "' . $product['title'] . '" (' . $qty . ' x ' . format_price($product['price']) . ').',
            $orderId
        );
        set_flash('success', 'Purchase request sent. The seller will review it shortly.');
        redirect('user/purchases.php');
    }

    if ($action === 'report') {
        verify_csrf();
        require_login();
        $targetType = $_POST['target_type'] ?? 'listing';
        $reason = trim((string)($_POST['reason'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $reportedUserId = $targetType === 'user' ? (int)$product['seller_id'] : null;
        $reportProductId = $targetType === 'listing' ? $pid : null;

        if (!in_array($reason, report_reasons(), true)) {
            set_flash('error', 'Please choose a valid reason.');
            redirect_back('product-details.php?id=' . $pid);
        }
        if (mb_strlen($description) < 10) {
            set_flash('error', 'Please describe the issue in at least 10 characters.');
            redirect_back('product-details.php?id=' . $pid);
        }
        if (user_has_open_report(current_user_id(), $reportedUserId, $reportProductId, $reason)) {
            set_flash('warning', 'You already reported this issue. Our moderators are reviewing it.');
            redirect_back('product-details.php?id=' . $pid);
        }

        $st = $pdo->prepare(
            'INSERT INTO reports (reporter_id, reported_user_id, product_id, reason, description, status)
             VALUES (?, ?, ?, ?, ?, \'pending\')'
        );
        $st->execute([current_user_id(), $reportedUserId, $reportProductId, $reason, $description]);
        $reportId = (int)$pdo->lastInsertId();

        audit_log('report_created', 'report', $reportId, 'Report #' . $reportId . ' created (' . $reason . ').');
        notify_admins('report_received', 'New report', 'A new report (' . $reason . ') was submitted and needs review.', $reportId);
        set_flash('success', 'Thank you. Your report has been submitted and will be reviewed by moderators.');
        redirect_back('product-details.php?id=' . $pid);
    }
}

// ---------- GET: start a conversation with the seller ----------
if (isset($_GET['action']) && $_GET['action'] === 'message') {
    require_login();
    if ((int)$product['seller_id'] !== current_user_id()) {
        $convId = get_or_create_conversation(current_user_id(), (int)$product['seller_id'], $pid);
        redirect('user/messages.php?conv=' . $convId);
    }
    redirect('user/messages.php');
}

// ---------- Page data ----------
$images = product_images($pid);
$primary = $images[0]['image_path'] ?? null;
$seller = get_user((int)$product['seller_id']);
$sellerStats = seller_stats((int)$product['seller_id']);
$trusted = is_trusted_seller((int)$product['seller_id']);
$isFav = is_logged_in() && favorite_exists(current_user_id(), $pid);
$myOrder = null;
$hasCompletedOrder = false;
if (is_logged_in() && !$isOwner) {
    $st = $pdo->prepare(
        "SELECT * FROM orders WHERE buyer_id = ? AND product_id = ? AND status IN ('pending','accepted','ready') ORDER BY id DESC LIMIT 1"
    );
    $st->execute([current_user_id(), $pid]);
    $myOrder = $st->fetch() ?: null;
    $st = $pdo->prepare(
        "SELECT id FROM orders WHERE buyer_id = ? AND product_id = ? AND status = 'completed' LIMIT 1"
    );
    $st->execute([current_user_id(), $pid]);
    $hasCompletedOrder = (bool)$st->fetchColumn();
}

$sellerReviews = $pdo->prepare(
    "SELECT r.*, u.full_name AS reviewer_name FROM reviews r
     JOIN users u ON u.id = r.reviewer_id
     WHERE r.reviewed_user_id = ? AND r.status = 'approved'
     ORDER BY r.created_at DESC LIMIT 5"
);
$sellerReviews->execute([(int)$product['seller_id']]);
$sellerReviews = $sellerReviews->fetchAll();

$similar = similar_products($product, 4);

$canRequest = $product['status'] === 'active'
    && $product['seller_status'] === 'active'
    && !$isOwner
    && is_logged_in()
    && $myOrder === null
    && current_user()['status'] === 'active';

$pageTitle = $product['title'];
require __DIR__ . '/includes/header.php';
?>

<div class="container">
  <nav class="text-small mb-2" style="padding-top:16px;" aria-label="Breadcrumb">
    <a href="<?= e(APP_BASE_URL . '/index.php') ?>">Home</a> »
    <a href="<?= e(APP_BASE_URL . '/products.php') ?>">Browse</a> »
    <a href="<?= e(APP_BASE_URL . '/products.php?category=' . (int)$product['category_id']) ?>"><?= e($product['category_name']) ?></a> »
    <span><?= e($product['title']) ?></span>
  </nav>

  <div class="detail-layout">
    <div class="detail-main">
      <div class="gallery" data-track-view="<?= (int)$pid ?>">
        <div class="gallery-main">
          <?php if ($primary): ?>
            <img src="<?= e(image_url($primary)) ?>" alt="<?= e($product['title']) ?>" id="galleryMain">
          <?php else: ?>
            <img src="<?= e(image_url(null)) ?>" alt="No image">
          <?php endif; ?>
        </div>
        <?php if (count($images) > 1): ?>
          <div class="gallery-thumbs">
            <?php foreach ($images as $i => $img): ?>
              <button type="button" class="<?= $i === 0 ? 'active' : '' ?>" data-src="<?= e(image_url($img['image_path'])) ?>" data-alt="<?= e($product['title']) ?>">
                <img src="<?= e(image_url($img['image_path'])) ?>" alt="Thumbnail <?= $i + 1 ?>" loading="lazy">
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="detail-description">
        <h3>Description</h3>
        <p><?= e($product['description']) ?></p>
      </div>

      <?php if ($similar): ?>
        <div style="margin-top:34px;">
          <h3 class="mb-2">Similar Products</h3>
          <div class="product-grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr));">
            <?php
            $detailProduct = $product;
            foreach ($similar as $product2) {
                $product = $product2;
                require __DIR__ . '/includes/product-card.php';
            }
            $product = $detailProduct;
            ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <aside class="buy-panel">
      <div class="card panel-box">
        <div class="flex justify-between">
          <span class="badge badge-<?= e(product_status_class($product['status'])) ?>"><?= e(product_status_label($product['status'])) ?></span>
          <button type="button" class="fav-btn <?= $isFav ? 'active' : '' ?>" data-fav="<?= (int)$pid ?>" style="position:static; width:38px; height:38px;" title="<?= $isFav ? 'Remove from favorites' : 'Add to favorites' ?>">&#9825;</button>
        </div>
        <h1 class="mt-2"><?= e($product['title']) ?></h1>
        <div class="detail-price"><?= e(format_price($product['price'])) ?></div>

        <div class="detail-meta">
          <div class="meta-item"><div class="label">Condition</div><div class="value"><?= e($product['condition_label']) ?></div></div>
          <div class="meta-item"><div class="label">Category</div><div class="value"><?= e($product['category_name']) ?></div></div>
          <div class="meta-item"><div class="label">Quantity available</div><div class="value"><?= (int)$product['quantity'] ?></div></div>
          <div class="meta-item"><div class="label">Listed</div><div class="value"><?= e(time_ago($product['created_at'])) ?></div></div>
          <div class="meta-item"><div class="label">Pickup location</div><div class="value"><?= e($product['location']) ?></div></div>
          <div class="meta-item"><div class="label">Views</div><div class="value"><?= (int)$product['views'] ?></div></div>
        </div>

        <?php if ($myOrder): ?>
          <div class="alert alert-info alert-inline">
            You already sent a purchase request for this item.
            <a href="<?= e(APP_BASE_URL . '/user/purchases.php') ?>">Track it here →</a>
          </div>
        <?php endif; ?>

        <div class="detail-actions">
          <?php if ($isOwner): ?>
            <a class="btn btn-primary btn-block" href="<?= e(APP_BASE_URL . '/user/edit-listing.php?id=' . (int)$pid) ?>">Edit Listing</a>
            <a class="btn btn-ghost btn-block" href="<?= e(APP_BASE_URL . '/user/listings.php') ?>">Manage My Listings</a>
          <?php elseif ($canRequest): ?>
            <form method="post" action="product-details.php?id=<?= (int)$pid ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="request_purchase">
              <div class="form-group mb-2">
                <label class="form-label" for="qty">Quantity</label>
                <input class="form-control" type="number" id="qty" name="quantity" value="1" min="1" max="<?= (int)$product['quantity'] ?>">
              </div>
              <button class="btn btn-primary btn-block btn-lg" type="submit">Request Purchase</button>
            </form>
            <p class="text-small text-muted text-center">📌 Meet in a public campus spot and inspect the item before paying.</p>
          <?php elseif (!is_logged_in()): ?>
            <a class="btn btn-primary btn-block btn-lg" href="<?= e(APP_BASE_URL . '/login.php?redirect=' . urlencode('product-details.php?id=' . $pid)) ?>">Log in to purchase</a>
            <p class="text-small text-muted text-center">📌 Meet in a public campus spot and inspect the item before paying.</p>
          <?php elseif ($product['status'] === 'reserved'): ?>
            <div class="alert alert-warning alert-inline">This item is currently reserved by another buyer.</div>
          <?php else: ?>
            <div class="alert alert-muted alert-inline">This listing is no longer available for purchase.</div>
          <?php endif; ?>

          <?php if (!$isOwner): ?>
            <a class="btn btn-ghost btn-block" href="<?= e(APP_BASE_URL . '/product-details.php?id=' . (int)$pid . '&action=message') ?>">💬 Contact Seller</a>
          <?php endif; ?>

          <div class="flex" style="gap:8px;">
            <button type="button" class="btn btn-ghost btn-sm grow" data-open-modal="reportModal">🚩 Report</button>
          </div>
        </div>

        <div class="divider"></div>

        <div class="seller-card">
          <span class="avatar avatar-md" data-initials="<?= e(strtoupper(substr($seller['full_name'] ?? 'U', 0, 2))) ?>">
            <?php if ($seller['profile_image']): ?><img src="<?= e(image_url($seller['profile_image'])) ?>" alt="<?= e($seller['full_name']) ?>"><?php endif; ?>
          </span>
          <div class="seller-info">
            <div class="name">
              <?= e($seller['full_name']) ?>
              <?php if ($trusted): ?><span class="trusted-badge" title="Meets the trusted seller criteria">Trusted Seller</span><?php endif; ?>
            </div>
            <div class="rating-line">
              <?= rating_stars((float)$sellerStats['avg_rating']) ?>
              <span><?= e(number_format($sellerStats['avg_rating'], 1)) ?> (<?= (int)$sellerStats['review_count'] ?> reviews)</span>
            </div>
            <div class="text-small text-muted"><?= (int)$sellerStats['sales_count'] ?> completed sales · <?= e($seller['department'] ?? '') ?></div>
          </div>
        </div>

        <?php if ($hasCompletedOrder): ?>
          <div class="divider"></div>
          <div class="text-small text-muted">You completed a purchase from this seller.
            <a href="<?= e(APP_BASE_URL . '/user/reviews.php') ?>">Rate the seller</a>.
          </div>
        <?php endif; ?>
      </div>

      <?php if ($sellerReviews): ?>
        <div class="card panel-box mt-2">
          <h3>Recent Reviews for <?= e(explode(' ', $seller['full_name'])[0]) ?></h3>
          <?php foreach ($sellerReviews as $rv): ?>
            <div style="padding:10px 0; border-bottom:1px solid var(--color-border);">
              <div class="flex justify-between">
                <strong class="text-small"><?= e($rv['reviewer_name']) ?></strong>
                <span class="text-small text-muted"><?= e(time_ago($rv['created_at'])) ?></span>
              </div>
              <div><?= rating_stars((float)$rv['rating']) ?></div>
              <p class="text-small" style="color:#334155;"><?= e($rv['comment']) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </aside>
  </div>
</div>

<!-- Report modal -->
<div class="modal-backdrop" id="reportModal" role="dialog" aria-modal="true">
  <div class="modal">
    <h3>Report this listing or seller</h3>
    <p>Help keep CampusMart safe. Our moderators review every report.</p>
    <form method="post" action="product-details.php?id=<?= (int)$pid ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="report">
      <div class="form-group mb-2">
        <label class="form-label" for="target_type">What are you reporting?</label>
        <select class="form-control" id="target_type" name="target_type">
          <option value="listing">This listing</option>
          <option value="user">This seller</option>
        </select>
      </div>
      <div class="form-group mb-2">
        <label class="form-label" for="reason">Reason</label>
        <select class="form-control" id="reason" name="reason" required>
          <option value="">Select a reason…</option>
          <?php foreach (report_reasons() as $reason): ?>
            <option value="<?= e($reason) ?>"><?= e($reason) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group mb-2">
        <label class="form-label" for="description">Description</label>
        <textarea class="form-control" id="description" name="description" minlength="10" required placeholder="Tell us what is wrong…"></textarea>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn-danger">Submit Report</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
