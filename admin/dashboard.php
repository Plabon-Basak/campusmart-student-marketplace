<?php
/**
 * Admin dashboard: key marketplace metrics and simple analytics.
 * Charts are lightweight CSS bars - no external chart library.
 */
require_once __DIR__ . '/../includes/admin-auth.php';

$pdo = db();

// ---------- Core metrics ----------
$counts = [];
$queries = [
    'total_users'       => 'SELECT COUNT(*) FROM users',
    'active_users'      => "SELECT COUNT(*) FROM users WHERE status = 'active'",
    'suspended_users'   => "SELECT COUNT(*) FROM users WHERE status = 'suspended'",
    'total_listings'    => 'SELECT COUNT(*) FROM products',
    'active_listings'   => "SELECT COUNT(*) FROM products WHERE status = 'active'",
    'pending_listings'  => "SELECT COUNT(*) FROM products WHERE status = 'pending'",
    'sold_listings'     => "SELECT COUNT(*) FROM products WHERE status = 'sold'",
    'total_orders'      => 'SELECT COUNT(*) FROM orders',
    'completed_orders'  => "SELECT COUNT(*) FROM orders WHERE status = 'completed'",
    'pending_orders'    => "SELECT COUNT(*) FROM orders WHERE status = 'pending'",
    'pending_reports'   => "SELECT COUNT(*) FROM reports WHERE status = 'pending'",
];
foreach ($queries as $key => $sql) {
    $counts[$key] = (int)$pdo->query($sql)->fetchColumn();
}
$counts['total_value'] = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = 'completed'")->fetchColumn();

// ---------- Category popularity ----------
$categories = $pdo->query(
    "SELECT c.name, COUNT(p.id) AS cnt
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id, c.name
     ORDER BY cnt DESC LIMIT 8"
)->fetchAll();
$maxCat = $categories ? max(array_column($categories, 'cnt')) : 1;

// ---------- Most viewed products ----------
$topProducts = $pdo->query(
    "SELECT p.id, p.title, p.views, c.name AS category_name
     FROM products p
     JOIN categories c ON c.id = p.category_id
     ORDER BY p.views DESC LIMIT 6"
)->fetchAll();
$maxViews = $topProducts ? max(array_column($topProducts, 'views')) : 1;

// ---------- Orders by status ----------
$orderStatus = $pdo->query(
    "SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status"
)->fetchAll();
$orderMap = [];
foreach (['pending', 'accepted', 'ready', 'completed', 'rejected', 'cancelled'] as $s) {
    $orderMap[$s] = 0;
}
foreach ($orderStatus as $row) {
    if (isset($orderMap[$row['status']])) {
        $orderMap[$row['status']] = (int)$row['cnt'];
    }
}

// ---------- Listings by status ----------
$listingStatus = $pdo->query(
    "SELECT status, COUNT(*) AS cnt FROM products GROUP BY status"
)->fetchAll();
$listingMap = [];
foreach (['draft', 'pending', 'approved', 'active', 'reserved', 'sold', 'expired', 'rejected', 'removed'] as $s) {
    $listingMap[$s] = 0;
}
foreach ($listingStatus as $row) {
    if (isset($listingMap[$row['status']])) {
        $listingMap[$row['status']] = (int)$row['cnt'];
    }
}

// ---------- Recent activity ----------
$recent = $pdo->query(
    "SELECT a.*, u.full_name AS actor_name
     FROM audit_logs a
     LEFT JOIN users u ON u.id = a.actor_id
     ORDER BY a.created_at DESC LIMIT 10"
)->fetchAll();

// ---------- Sales by month (last 6 months) ----------
$salesByMonth = $pdo->query(
    "SELECT DATE_FORMAT(completed_at, '%Y-%m') AS ym, SUM(total_amount) AS total, COUNT(*) AS cnt
     FROM orders
     WHERE status = 'completed' AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY ym ORDER BY ym"
)->fetchAll();
$monthMax = $salesByMonth ? max(array_column($salesByMonth, 'total')) : 1;

$pageTitle = 'Admin Dashboard';
$dashboardPage = true;
$activeNav = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="dash-wrap">
  <?php require __DIR__ . '/_sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <div>
        <h1>Marketplace Overview</h1>
        <div class="sub">Real-time health of your campus marketplace</div>
      </div>
      <a class="btn btn-primary" href="<?= e(APP_BASE_URL . '/admin/products.php?status=pending') ?>">Review Pending Listings (<?= (int)$counts['pending_listings'] ?>)</a>
    </div>

    <div class="stat-grid mb-3">
      <div class="card stat-card"><div class="stat-icon" style="background:var(--color-primary-light);">👥</div><div class="stat-num"><?= (int)$counts['total_users'] ?></div><div class="stat-name">Total Users</div></div>
      <div class="card stat-card"><div class="stat-icon" style="background:var(--color-success-light);">📦</div><div class="stat-num"><?= (int)$counts['active_listings'] ?></div><div class="stat-name">Active Listings</div></div>
      <div class="card stat-card"><div class="stat-icon" style="background:#fce7f3;">✅</div><div class="stat-num"><?= (int)$counts['sold_listings'] ?></div><div class="stat-name">Sold Listings</div></div>
      <div class="card stat-card"><div class="stat-icon" style="background:var(--color-info-light);">🧾</div><div class="stat-num"><?= (int)$counts['total_orders'] ?></div><div class="stat-name">Total Orders</div></div>
      <div class="card stat-card"><div class="stat-icon" style="background:var(--color-warning-light);">🚩</div><div class="stat-num"><?= (int)$counts['pending_reports'] ?></div><div class="stat-name">Pending Reports</div></div>
      <div class="card stat-card"><div class="stat-icon" style="background:var(--color-success-light);">💰</div><div class="stat-num"><?= e(format_price($counts['total_value'])) ?></div><div class="stat-name">Completed Sales Value</div></div>
    </div>

    <div class="chart-grid">
      <div class="chart-box">
        <h4>Listings per Category</h4>
        <?php if (empty($categories)): ?>
          <p class="text-muted text-small">No categories yet.</p>
        <?php else: ?>
          <div class="hbar-list">
            <?php foreach ($categories as $c): ?>
              <div class="hbar-row">
                <span class="hbar-label"><?= e($c['name']) ?></span>
                <div class="hbar-track"><div class="hbar-fill" style="width:<?= (int)round($c['cnt'] / $maxCat * 100) ?>%;"></div></div>
                <span class="hbar-value"><?= (int)$c['cnt'] ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="chart-box">
        <h4>Most Viewed Products</h4>
        <?php if (empty($topProducts)): ?>
          <p class="text-muted text-small">No products yet.</p>
        <?php else: ?>
          <div class="hbar-list">
            <?php foreach ($topProducts as $p): ?>
              <div class="hbar-row">
                <a class="hbar-label" href="<?= e(APP_BASE_URL . '/product-details.php?id=' . (int)$p['id']) ?>"><?= e(mb_strimwidth($p['title'], 0, 30, '…')) ?></a>
                <div class="hbar-track"><div class="hbar-fill" style="width:<?= (int)round($p['views'] / $maxViews * 100) ?>%;"></div></div>
                <span class="hbar-value"><?= (int)$p['views'] ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="chart-box">
        <h4>Orders by Status</h4>
        <div class="donut-wrap">
          <?php foreach ($orderMap as $status => $cnt): ?>
            <div class="donut-seg">
              <span class="swatch swatch-<?= e(order_status_class($status)) ?>"></span>
              <span><?= e(order_status_label($status)) ?></span>
              <strong><?= (int)$cnt ?></strong>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="chart-box">
        <h4>Completed Sales (last 6 months)</h4>
        <?php if (empty($salesByMonth)): ?>
          <p class="text-muted text-small">No completed sales in this period.</p>
        <?php else: ?>
          <div class="hbar-list">
            <?php foreach ($salesByMonth as $m): ?>
              <div class="hbar-row">
                <span class="hbar-label"><?= date('M Y', strtotime($m['ym'] . '-01')) ?></span>
                <div class="hbar-track"><div class="hbar-fill" style="width:<?= (int)round($m['total'] / $monthMax * 100) ?>%;"></div></div>
                <span class="hbar-value"><?= e(format_price($m['total'])) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="dash-section" style="margin-top:18px;">
      <div class="dash-header" style="margin-bottom:10px;">
        <h3 style="margin:0;">Recent Admin Activity</h3>
        <a class="btn btn-ghost btn-sm" href="<?= e(APP_BASE_URL . '/admin/dashboard.php') ?>">Refresh</a>
      </div>
      <?php if (empty($recent)): ?>
        <p class="text-muted text-small">No recorded activity yet.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr><th>Time</th><th>Admin</th><th>Action</th><th>Entity</th><th>Details</th></tr>
            </thead>
            <tbody>
              <?php foreach ($recent as $a): ?>
                <tr>
                  <td class="text-small"><?= e(time_ago($a['created_at'])) ?></td>
                  <td class="text-small"><?= e($a['actor_name'] ?? 'System') ?></td>
                  <td><span class="badge badge-outline"><?= e(str_replace('_', ' ', $a['action'])) ?></span></td>
                  <td class="text-small"><?= e($a['entity_type'] ?? '-') ?><?= $a['entity_id'] ? ' #' . (int)$a['entity_id'] : '' ?></td>
                  <td class="text-small text-muted"><?= e(mb_strimwidth($a['details'] ?? '', 0, 80, '…')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
