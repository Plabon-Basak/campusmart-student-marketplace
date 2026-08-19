<?php
/**
 * Admin: moderate reviews - hide abusive or fraudulent reviews.
 */
require_once __DIR__ . '/../includes/admin-auth.php';

$pdo = db();

// ---------- POST: moderate a review ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $reviewId = (int)($_POST['review_id'] ?? 0);

    $st = $pdo->prepare('SELECT * FROM reviews WHERE id = ?');
    $st->execute([$reviewId]);
    $review = $st->fetch();

    if ($review === false) {
        set_flash('error', 'Review not found.');
        redirect('reviews.php');
    }

    if (($_POST['action'] ?? '') === 'remove') {
        $pdo->prepare("UPDATE reviews SET status = 'removed' WHERE id = ?")->execute([$reviewId]);
        audit_log('review_removed', 'review', $reviewId, 'Removed review #' . $reviewId . ' (rating ' . (int)$review['rating'] . ').');
        create_notification((int)$review['reviewed_user_id'], 'review_removed', 'Review removed', 'One of your reviews was removed by a moderator.');
        set_flash('success', 'Review removed from public view.');
    } elseif (($_POST['action'] ?? '') === 'restore') {
        $pdo->prepare("UPDATE reviews SET status = 'approved' WHERE id = ?")->execute([$reviewId]);
        audit_log('review_restored', 'review', $reviewId, 'Restored review #' . $reviewId . '.');
        set_flash('success', 'Review restored.');
    } else {
        set_flash('error', 'Invalid action.');
    }
    redirect('reviews.php');
}

// ---------- List reviews ----------
$statusFilter = (string)($_GET['status'] ?? '');
$search = trim((string)($_GET['q'] ?? ''));

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$sql = "SELECT r.*, rev.full_name AS reviewer_name, revu.full_name AS reviewed_name,
               o.order_code
        FROM reviews r
        JOIN users rev ON rev.id = r.reviewer_id
        JOIN users revu ON revu.id = r.reviewed_user_id
        JOIN orders o ON o.id = r.order_id
        WHERE 1=1";
$params = [];

if ($statusFilter === 'approved' || $statusFilter === 'removed') {
    $sql .= ' AND r.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $sql .= ' AND (rev.full_name LIKE ? OR revu.full_name LIKE ? OR r.comment LIKE ? OR o.order_code LIKE ?)';
    $like = '%' . escape_like($search) . '%';
    array_push($params, $like, $like, $like, $like);
}

$wherePart = substr($sql, strpos($sql, 'WHERE 1=1'));
$countSt = $pdo->prepare('SELECT COUNT(*) FROM reviews r
        JOIN users rev ON rev.id = r.reviewer_id
        JOIN users revu ON revu.id = r.reviewed_user_id
        JOIN orders o ON o.id = r.order_id ' . $wherePart);
$countSt->execute($params);
$total = (int)$countSt->fetchColumn();

$sql .= ' ORDER BY r.created_at DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;

$st = $pdo->prepare($sql);
$st->execute($params);
$reviews = $st->fetchAll();

$qs = http_build_query(array_filter(['status' => $statusFilter, 'q' => $search]));
$baseUrl = APP_BASE_URL . '/admin/reviews.php' . ($qs !== '' ? '?' . $qs : '');

$pageTitle = 'Moderate Reviews';
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
        <div class="sub">Keep ratings honest and helpful for the whole campus</div>
      </div>
      <form method="get" action="reviews.php" class="filter-form">
        <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search user or text…">
        <select class="form-control" name="status" onchange="this.form.submit()">
          <option value="">All statuses</option>
          <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
          <option value="removed" <?= $statusFilter === 'removed' ? 'selected' : '' ?>>Removed</option>
        </select>
        <button class="btn btn-primary btn-sm" type="submit">Filter</button>
      </form>
    </div>

    <?php if (empty($reviews)): ?>
      <div class="card empty-state">
        <div class="empty-icon">⭐</div>
        <h3>No reviews found</h3>
        <p>Try adjusting the filters above.</p>
      </div>
    <?php else: ?>
      <div class="card table-card">
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Rating</th>
                <th>Reviewer</th>
                <th>Reviewed</th>
                <th>Comment</th>
                <th>Order</th>
                <th>Status</th>
                <th>Date</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($reviews as $r): ?>
                <tr>
                  <td><?= rating_stars((float)$r['rating']) ?></td>
                  <td class="text-small"><?= e($r['reviewer_name']) ?></td>
                  <td class="text-small"><?= e($r['reviewed_name']) ?></td>
                  <td class="text-small text-muted"><?= e(mb_strimwidth($r['comment'] ?? '', 0, 60, '…')) ?></td>
                  <td class="text-small"><?= e($r['order_code']) ?></td>
                  <td><span class="badge badge-<?= $r['status'] === 'approved' ? 'success' : 'muted' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                  <td class="text-small"><?= e(time_ago($r['created_at'])) ?></td>
                  <td>
                    <?php if ($r['status'] === 'approved'): ?>
                      <form method="post" action="reviews.php" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-danger-soft btn-sm" type="submit" data-confirm-dangerous data-confirm="Remove this review from public view?">Remove</button>
                      </form>
                    <?php else: ?>
                      <form method="post" action="reviews.php" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="restore">
                        <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-success btn-sm" type="submit">Restore</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
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
