<?php
/**
 * Admin: moderate listings - approve, reject (with reason) and remove.
 */
require_once __DIR__ . '/../includes/admin-auth.php';

$pdo = db();

// ---------- POST: moderate a listing ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $productId = (int)($_POST['product_id'] ?? 0);

    $product = $productId > 0 ? get_product($productId) : null;
    if ($product === null) {
        set_flash('error', 'Listing not found.');
        redirect('products.php');
    }

    if ($action === 'approve') {
        $expiryDays = max(1, (int)(settings('listing_expiry_days') ?: 30));
        $expiresAt = date('Y-m-d H:i:s', time() + $expiryDays * 86400);
        $st = $pdo->prepare("UPDATE products SET status = 'active', reject_reason = NULL, expires_at = ? WHERE id = ?");
        $st->execute([$expiresAt, $productId]);
        audit_log('listing_approved', 'product', $productId, 'Approved listing "' . $product['title'] . '".');
        create_notification((int)$product['seller_id'], 'listing_approved', 'Listing approved', 'Your listing "' . $product['title'] . '" has been approved and is now active.', $productId);
        set_flash('success', 'Listing approved and is now active.');
        redirect('products.php');
    }

    if ($action === 'reject') {
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($reason === '' || mb_strlen($reason) < 5) {
            set_flash('error', 'Please provide a rejection reason (at least 5 characters).');
            redirect('products.php');
        }
        $st = $pdo->prepare("UPDATE products SET status = 'rejected', reject_reason = ? WHERE id = ?");
        $st->execute([$reason, $productId]);
        audit_log('listing_rejected', 'product', $productId, 'Rejected listing "' . $product['title'] . '" (reason: ' . $reason . ').');
        create_notification((int)$product['seller_id'], 'listing_rejected', 'Listing rejected', 'Your listing "' . $product['title'] . '" was rejected. Reason: ' . $reason, $productId);
        set_flash('success', 'Listing rejected and the seller has been notified.');
        redirect('products.php');
    }

    if ($action === 'remove') {
        $reason = trim((string)($_POST['reason'] ?? 'Removed by moderator.'));
        $st = $pdo->prepare("UPDATE products SET status = 'removed', reject_reason = ? WHERE id = ?");
        $st->execute([$reason, $productId]);
        audit_log('listing_removed', 'product', $productId, 'Removed listing "' . $product['title'] . '" (reason: ' . $reason . ').');
        create_notification((int)$product['seller_id'], 'listing_rejected', 'Listing removed', 'Your listing "' . $product['title'] . '" was removed by a moderator. Reason: ' . $reason, $productId);
        set_flash('success', 'Listing removed from the marketplace.');
        redirect('products.php');
    }

    set_flash('error', 'Invalid action.');
    redirect('products.php');
}

// ---------- Filters ----------
$search = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? '');
$categoryId = (int)($_GET['category'] ?? 0);

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$sql = "SELECT p.*, c.name AS category_name, u.full_name AS seller_name
        FROM products p
        JOIN categories c ON c.id = p.category_id
        JOIN users u ON u.id = p.seller_id
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= ' AND (p.title LIKE ? OR p.description LIKE ? OR u.full_name LIKE ?)';
    $like = '%' . escape_like($search) . '%';
    array_push($params, $like, $like, $like);
}
if (in_array($status, ['draft', 'pending', 'approved', 'active', 'reserved', 'sold', 'expired', 'rejected', 'removed'], true)) {
    $sql .= ' AND p.status = ?';
    $params[] = $status;
}
if ($categoryId > 0) {
    $sql .= ' AND p.category_id = ?';
    $params[] = $categoryId;
}

$wherePart = substr($sql, strpos($sql, 'WHERE 1=1'));
$countSt = $pdo->prepare('SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id JOIN users u ON u.id = p.seller_id ' . $wherePart);
$countSt->execute($params);
$total = (int)$countSt->fetchColumn();

$sql .= " ORDER BY CASE WHEN p.status = 'pending' THEN 0 ELSE 1 END, p.created_at DESC LIMIT $perPage OFFSET $offset";

$st = $pdo->prepare($sql);
$st->execute($params);
$products = $st->fetchAll();

$categories = get_categories();

$qs = http_build_query(array_filter(['q' => $search, 'status' => $status, 'category' => $categoryId ?: null]));
$baseUrl = APP_BASE_URL . '/admin/products.php' . ($qs !== '' ? '?' . $qs : '');

$pageTitle = 'Moderate Listings';
$dashboardPage = true;
$activeNav = 'products';
require __DIR__ . '/../includes/header.php';
?>

<div class="dash-wrap">
  <?php require __DIR__ . '/_sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <div>
        <h1>Listings</h1>
        <div class="sub">Approve, reject or remove listings to keep the marketplace safe</div>
      </div>
      <form method="get" action="products.php" class="filter-form">
        <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search title, seller…">
        <select class="form-control" name="status" onchange="this.form.submit()">
          <option value="">All statuses</option>
          <?php foreach (['pending', 'approved', 'active', 'reserved', 'sold', 'expired', 'rejected', 'removed', 'draft'] as $s): ?>
            <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(product_status_label($s)) ?></option>
          <?php endforeach; ?>
        </select>
        <select class="form-control" name="category" onchange="this.form.submit()">
          <option value="">All categories</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $categoryId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary btn-sm" type="submit">Filter</button>
      </form>
    </div>

    <?php if (empty($products)): ?>
      <div class="card empty-state">
        <div class="empty-icon">📦</div>
        <h3>No listings found</h3>
        <p>Try adjusting the filters above.</p>
      </div>
    <?php else: ?>
      <div class="card table-card">
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Listing</th>
                <th>Seller</th>
                <th>Price</th>
                <th>Status</th>
                <th>Views</th>
                <th>Listed</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($products as $p): ?>
                <tr>
                  <td>
                    <div class="flex" style="gap:10px; align-items:center; max-width:300px;">
                      <span class="avatar avatar-sm" style="border-radius:8px;">
                        <?php $thumb = product_primary_image((int)$p['id']); ?>
                        <img src="<?= e(image_url($thumb)) ?>" alt="">
                      </span>
                      <span>
                        <a href="<?= e(APP_BASE_URL . '/product-details.php?id=' . (int)$p['id']) ?>"><strong><?= e(mb_strimwidth($p['title'], 0, 40, '…')) ?></strong></a>
                        <span class="block text-small text-muted"><?= e($p['category_name']) ?> · <?= e($p['condition_label']) ?></span>
                      </span>
                    </div>
                  </td>
                  <td class="text-small"><?= e($p['seller_name']) ?></td>
                  <td class="text-small"><?= e(format_price($p['price'])) ?></td>
                  <td>
                    <span class="badge badge-<?= e(product_status_class($p['status'])) ?>"><?= e(product_status_label($p['status'])) ?></span>
                    <?php if ($p['reject_reason']): ?>
                      <span class="block text-small text-danger" title="<?= e($p['reject_reason']) ?>">Reason: <?= e(mb_strimwidth($p['reject_reason'], 0, 28, '…')) ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="text-small"><?= (int)$p['views'] ?></td>
                  <td class="text-small"><?= e(time_ago($p['created_at'])) ?></td>
                  <td>
                    <div class="flex" style="gap:6px; justify-content:flex-end; flex-wrap:wrap;">
                      <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE_URL . '/product-details.php?id=' . (int)$p['id']) ?>">View</a>
                      <?php if ($p['status'] === 'pending'): ?>
                        <form method="post" action="products.php" style="display:inline;">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="approve">
                          <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                          <button class="btn btn-success btn-sm" type="submit">Approve</button>
                        </form>
                        <button type="button" class="btn btn-danger-soft btn-sm" data-open-modal="rejectModal<?= (int)$p['id'] ?>">Reject</button>
                      <?php endif; ?>
                      <?php if (in_array($p['status'], ['active', 'approved', 'reserved'], true)): ?>
                        <button type="button" class="btn btn-danger-soft btn-sm" data-open-modal="removeModal<?= (int)$p['id'] ?>">Remove</button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>

                <!-- Reject modal -->
                <div class="modal-backdrop" id="rejectModal<?= (int)$p['id'] ?>" role="dialog" aria-modal="true">
                  <div class="modal">
                    <h3>Reject "<?= e(mb_strimwidth($p['title'], 0, 40, '…')) ?>"</h3>
                    <p>The seller will be notified with your reason.</p>
                    <form method="post" action="products.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="reject">
                      <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                      <div class="form-group mb-2">
                        <label class="form-label" for="reject_reason_<?= (int)$p['id'] ?>">Reason for rejection</label>
                        <textarea class="form-control" id="reject_reason_<?= (int)$p['id'] ?>" name="reason" rows="3" minlength="5" required placeholder="Explain why this listing cannot be approved…"></textarea>
                      </div>
                      <div class="modal-actions">
                        <button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject Listing</button>
                      </div>
                    </form>
                  </div>
                </div>

                <!-- Remove modal -->
                <div class="modal-backdrop" id="removeModal<?= (int)$p['id'] ?>" role="dialog" aria-modal="true">
                  <div class="modal">
                    <h3>Remove "<?= e(mb_strimwidth($p['title'], 0, 40, '…')) ?>"?</h3>
                    <p>This hides the listing from the marketplace immediately. Historical orders are preserved.</p>
                    <form method="post" action="products.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="remove">
                      <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                      <div class="form-group mb-2">
                        <label class="form-label" for="remove_reason_<?= (int)$p['id'] ?>">Reason (visible to seller)</label>
                        <textarea class="form-control" id="remove_reason_<?= (int)$p['id'] ?>" name="reason" rows="2" placeholder="e.g. Violates marketplace rules…"></textarea>
                      </div>
                      <div class="modal-actions">
                        <button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
                        <button type="submit" class="btn btn-danger" data-confirm-dangerous data-confirm="Remove this listing from the marketplace?">Remove Listing</button>
                      </div>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?= pagination($total, $page, $perPage, $baseUrl) ?>
    <?php endif; ?>
  </main>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
