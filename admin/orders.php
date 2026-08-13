<?php
/**
 * Admin: monitor all marketplace orders. Read-only oversight with
 * full order details; transactional changes are left to buyers/sellers.
 */
require_once __DIR__ . '/../includes/admin-auth.php';

$pdo = db();

$search = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$sql = "SELECT o.*, p.title AS product_title, b.full_name AS buyer_name, s.full_name AS seller_name
        FROM orders o
        JOIN products p ON p.id = o.product_id
        JOIN users b ON b.id = o.buyer_id
        JOIN users s ON s.id = o.seller_id
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= ' AND (o.order_code LIKE ? OR p.title LIKE ? OR b.full_name LIKE ? OR s.full_name LIKE ?)';
    $like = '%' . escape_like($search) . '%';
    array_push($params, $like, $like, $like, $like);
}
if (in_array($status, ['pending', 'accepted', 'ready', 'completed', 'rejected', 'cancelled'], true)) {
    $sql .= ' AND o.status = ?';
    $params[] = $status;
}

$wherePart = substr($sql, strpos($sql, 'WHERE 1=1'));
$countSt = $pdo->prepare('SELECT COUNT(*) FROM orders o JOIN products p ON p.id = o.product_id JOIN users b ON b.id = o.buyer_id JOIN users s ON s.id = o.seller_id ' . $wherePart);
$countSt->execute($params);
$total = (int)$countSt->fetchColumn();

$sql .= ' ORDER BY o.created_at DESC LIMIT ? OFFSET ?';
$params[] = $perPage;
$params[] = $offset;

$st = $pdo->prepare($sql);
$st->execute($params);
$orders = $st->fetchAll();

$qs = http_build_query(array_filter(['q' => $search, 'status' => $status]));
$baseUrl = APP_BASE_URL . '/admin/orders.php' . ($qs !== '' ? '?' . $qs : '');

$pageTitle = 'Monitor Orders';
$dashboardPage = true;
$activeNav = 'orders';
require __DIR__ . '/../includes/header.php';
?>

<div class="dash-wrap">
  <?php require __DIR__ . '/_sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <div>
        <h1>Orders</h1>
        <div class="sub">Oversight of every purchase request across the campus</div>
      </div>
      <form method="get" action="orders.php" class="filter-form">
        <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search order, product, user…">
        <select class="form-control" name="status" onchange="this.form.submit()">
          <option value="">All statuses</option>
          <?php foreach (['pending', 'accepted', 'ready', 'completed', 'rejected', 'cancelled'] as $s): ?>
            <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(order_status_label($s)) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary btn-sm" type="submit">Filter</button>
      </form>
    </div>

    <?php if (empty($orders)): ?>
      <div class="card empty-state">
        <div class="empty-icon">🧾</div>
        <h3>No orders found</h3>
        <p>Try adjusting the filters above.</p>
      </div>
    <?php else: ?>
      <div class="card table-card">
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Order</th>
                <th>Product</th>
                <th>Buyer</th>
                <th>Seller</th>
                <th>Amount</th>
                <th>Payment</th>
                <th>Status</th>
                <th>Placed</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orders as $o): ?>
                <tr>
                  <td><strong><?= e($o['order_code']) ?></strong></td>
                  <td class="text-small"><?= e(mb_strimwidth($o['product_title'], 0, 34, '…')) ?></td>
                  <td class="text-small"><?= e($o['buyer_name']) ?></td>
                  <td class="text-small"><?= e($o['seller_name']) ?></td>
                  <td class="text-small"><?= e(format_price($o['total_amount'])) ?></td>
                  <td><span class="badge badge-<?= $o['payment_status'] === 'paid' ? 'success' : 'pending' ?>"><?= e(ucfirst($o['payment_status'])) ?></span></td>
                  <td><span class="badge badge-<?= e(order_status_class($o['status'])) ?>"><?= e(order_status_label($o['status'])) ?></span></td>
                  <td class="text-small"><?= e(time_ago($o['created_at'])) ?></td>
                  <td><button type="button" class="btn btn-ghost btn-sm" data-open-modal="orderModal<?= (int)$o['id'] ?>">Details</button></td>
                </tr>

                <!-- Order detail modal -->
                <div class="modal-backdrop" id="orderModal<?= (int)$o['id'] ?>" role="dialog" aria-modal="true">
                  <div class="modal">
                    <h3>Order <?= e($o['order_code']) ?></h3>
                    <div class="detail-grid">
                      <div><div class="label">Product</div><div class="value"><?= e($o['product_title']) ?></div></div>
                      <div><div class="label">Quantity</div><div class="value"><?= (int)$o['quantity'] ?></div></div>
                      <div><div class="label">Unit price</div><div class="value"><?= e(format_price($o['unit_price'])) ?></div></div>
                      <div><div class="label">Total</div><div class="value"><?= e(format_price($o['total_amount'])) ?></div></div>
                      <div><div class="label">Buyer</div><div class="value"><?= e($o['buyer_name']) ?></div></div>
                      <div><div class="label">Seller</div><div class="value"><?= e($o['seller_name']) ?></div></div>
                      <div><div class="label">Payment method</div><div class="value"><?= e(ucfirst($o['payment_method'])) ?></div></div>
                      <div><div class="label">Payment status</div><div class="value"><?= e(ucfirst($o['payment_status'])) ?></div></div>
                      <div><div class="label">Status</div><div class="value"><span class="badge badge-<?= e(order_status_class($o['status'])) ?>"><?= e(order_status_label($o['status'])) ?></span></div></div>
                      <div><div class="label">Placed</div><div class="value"><?= e(date('M j, Y g:i A', strtotime($o['created_at']))) ?></div></div>
                      <?php if ($o['pickup_location']): ?>
                        <div><div class="label">Pickup</div><div class="value"><?= e($o['pickup_location']) ?><?= $o['pickup_time'] ? ' · ' . e($o['pickup_time']) : '' ?></div></div>
                      <?php endif; ?>
                      <?php if ($o['completed_at']): ?>
                        <div><div class="label">Completed</div><div class="value"><?= e(date('M j, Y g:i A', strtotime($o['completed_at']))) ?></div></div>
                      <?php endif; ?>
                    </div>
                    <?php if ($o['status_history']): ?>
                      <?php $history = json_decode((string)$o['status_history'], true); ?>
                      <?php if (is_array($history) && $history): ?>
                        <div class="detail-grid" style="margin-top:12px;">
                          <div style="grid-column:1/-1;"><div class="label">Status history</div>
                            <div class="value">
                              <?php foreach ($history as $h): ?>
                                <span class="block">• <?= e(order_status_label($h['status'])) ?> — <?= e(date('M j, g:i A', strtotime($h['at']))) ?></span>
                              <?php endforeach; ?>
                            </div>
                          </div>
                        </div>
                      <?php endif; ?>
                    <?php endif; ?>
                    <div class="modal-actions">
                      <button type="button" class="btn btn-ghost" data-close-modal>Close</button>
                    </div>
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
