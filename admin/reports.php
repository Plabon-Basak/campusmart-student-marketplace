<?php
/**
 * Admin: moderate reports - update status, add resolution notes,
 * and quickly reach the affected user or listing.
 */
require_once __DIR__ . '/../includes/admin-auth.php';

$pdo = db();

// ---------- POST: update report ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $reportId = (int)($_POST['report_id'] ?? 0);
    $status = (string)($_POST['status'] ?? '');
    $note = trim((string)($_POST['admin_note'] ?? ''));

    $st = $pdo->prepare('SELECT * FROM reports WHERE id = ?');
    $st->execute([$reportId]);
    $report = $st->fetch();

    if ($report === false) {
        set_flash('error', 'Report not found.');
        redirect('reports.php');
    }
    if (!in_array($status, ['pending', 'under_review', 'resolved', 'rejected'], true)) {
        set_flash('error', 'Invalid status.');
        redirect('reports.php');
    }

    $resolvedAt = in_array($status, ['resolved', 'rejected'], true)
        ? 'COALESCE(resolved_at, NOW())'
        : 'NULL';

    $st = $pdo->prepare("UPDATE reports SET status = ?, admin_note = ?, resolved_at = $resolvedAt WHERE id = ?");
    $st->execute([$status, $note, $reportId]);

    audit_log('report_status_changed', 'report', $reportId, 'Report #' . $reportId . ' set to ' . $status . '. Note: ' . $note);
    create_notification((int)$report['reporter_id'], 'report_status', 'Report update', 'Your report (#' . $reportId . ') status is now: ' . ucwords(str_replace('_', ' ', $status)) . '.', $reportId);
    set_flash('success', 'Report #' . $reportId . ' updated to ' . ucwords(str_replace('_', ' ', $status)) . '.');
    redirect('reports.php');
}

// ---------- Filters ----------
$statusFilter = (string)($_GET['status'] ?? '');
$targetFilter = (string)($_GET['target'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$sql = "SELECT r.*, rep.full_name AS reporter_name,
               repu.full_name AS reported_name,
               p.title AS product_title
        FROM reports r
        JOIN users rep ON rep.id = r.reporter_id
        LEFT JOIN users repu ON repu.id = r.reported_user_id
        LEFT JOIN products p ON p.id = r.product_id
        WHERE 1=1";
$params = [];

if (in_array($statusFilter, ['pending', 'under_review', 'resolved', 'rejected'], true)) {
    $sql .= ' AND r.status = ?';
    $params[] = $statusFilter;
}
if ($targetFilter === 'product') {
    $sql .= ' AND r.product_id IS NOT NULL';
} elseif ($targetFilter === 'user') {
    $sql .= ' AND r.reported_user_id IS NOT NULL';
}

$wherePart = substr($sql, strpos($sql, 'WHERE 1=1'));
$countSt = $pdo->prepare('SELECT COUNT(*) FROM reports r
        JOIN users rep ON rep.id = r.reporter_id
        LEFT JOIN users repu ON repu.id = r.reported_user_id
        LEFT JOIN products p ON p.id = r.product_id ' . $wherePart);
$countSt->execute($params);
$total = (int)$countSt->fetchColumn();

$sql .= " ORDER BY CASE WHEN r.status IN ('pending','under_review') THEN 0 ELSE 1 END, r.created_at DESC LIMIT $perPage OFFSET $offset";

$st = $pdo->prepare($sql);
$st->execute($params);
$reports = $st->fetchAll();

$qs = http_build_query(array_filter(['status' => $statusFilter, 'target' => $targetFilter]));
$baseUrl = APP_BASE_URL . '/admin/reports.php' . ($qs !== '' ? '?' . $qs : '');

function report_status_class(string $status): string
{
    $map = ['pending' => 'pending', 'under_review' => 'warning', 'resolved' => 'success', 'rejected' => 'muted'];
    return $map[$status] ?? 'muted';
}

$pageTitle = 'Moderate Reports';
$dashboardPage = true;
$activeNav = 'reports';
require __DIR__ . '/../includes/header.php';
?>

<div class="dash-wrap">
  <?php require __DIR__ . '/_sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <div>
        <h1>Reports</h1>
        <div class="sub">Review and resolve reports from the community</div>
      </div>
      <form method="get" action="reports.php" class="filter-form">
        <select class="form-control" name="target" onchange="this.form.submit()">
          <option value="">All targets</option>
          <option value="product" <?= $targetFilter === 'product' ? 'selected' : '' ?>>Listings</option>
          <option value="user" <?= $targetFilter === 'user' ? 'selected' : '' ?>>Users</option>
        </select>
        <select class="form-control" name="status" onchange="this.form.submit()">
          <option value="">All statuses</option>
          <?php foreach (['pending', 'under_review', 'resolved', 'rejected'] as $s): ?>
            <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $s))) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <?php if (empty($reports)): ?>
      <div class="card empty-state">
        <div class="empty-icon">🚩</div>
        <h3>No reports found</h3>
        <p>Try adjusting the filters above.</p>
      </div>
    <?php else: ?>
      <div class="card table-card">
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Report</th>
                <th>Target</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Reported</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($reports as $r): ?>
                <tr>
                  <td>
                    <strong>#<?= (int)$r['id'] ?></strong>
                    <span class="block text-small text-muted">by <?= e($r['reporter_name']) ?></span>
                  </td>
                  <td class="text-small">
                    <?php if ($r['product_title']): ?>
                      <a href="<?= e(APP_BASE_URL . '/product-details.php?id=' . (int)$r['product_id']) ?>"><?= e(mb_strimwidth($r['product_title'], 0, 28, '…')) ?></a>
                    <?php elseif ($r['reported_name']): ?>
                      <a href="<?= e(APP_BASE_URL . '/admin/users.php?q=' . urlencode($r['reported_name'])) ?>"><?= e($r['reported_name']) ?></a>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-small"><?= e($r['reason']) ?></td>
                  <td><span class="badge badge-<?= e(report_status_class($r['status'])) ?>"><?= e(ucwords(str_replace('_', ' ', $r['status']))) ?></span></td>
                  <td class="text-small"><?= e(time_ago($r['created_at'])) ?></td>
                  <td><button type="button" class="btn btn-ghost btn-sm" data-open-modal="reportModal<?= (int)$r['id'] ?>">Review</button></td>
                </tr>

                <!-- Review modal -->
                <div class="modal-backdrop" id="reportModal<?= (int)$r['id'] ?>" role="dialog" aria-modal="true">
                  <div class="modal">
                    <h3>Report #<?= (int)$r['id'] ?></h3>
                    <div class="detail-grid">
                      <div><div class="label">Reporter</div><div class="value"><?= e($r['reporter_name']) ?></div></div>
                      <div><div class="label">Reason</div><div class="value"><?= e($r['reason']) ?></div></div>
                      <?php if ($r['product_title']): ?>
                        <div style="grid-column:1/-1;"><div class="label">Listing</div><div class="value"><a href="<?= e(APP_BASE_URL . '/product-details.php?id=' . (int)$r['product_id']) ?>"><?= e($r['product_title']) ?></a></div></div>
                      <?php endif; ?>
                      <?php if ($r['reported_name']): ?>
                        <div style="grid-column:1/-1;"><div class="label">Reported user</div><div class="value"><a href="<?= e(APP_BASE_URL . '/admin/users.php?q=' . urlencode($r['reported_name'])) ?>"><?= e($r['reported_name']) ?></a></div></div>
                      <?php endif; ?>
                      <div style="grid-column:1/-1;"><div class="label">Description</div><div class="value"><?= e($r['description'] ?? '-') ?></div></div>
                    </div>

                    <form method="post" action="reports.php" style="margin-top:14px;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                      <div class="form-group mb-2">
                        <label class="form-label" for="status_<?= (int)$r['id'] ?>">Status</label>
                        <select class="form-control" id="status_<?= (int)$r['id'] ?>" name="status">
                          <option value="pending" <?= $r['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                          <option value="under_review" <?= $r['status'] === 'under_review' ? 'selected' : '' ?>>Under Review</option>
                          <option value="resolved" <?= $r['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                          <option value="rejected" <?= $r['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                      </div>
                      <div class="form-group mb-2">
                        <label class="form-label" for="note_<?= (int)$r['id'] ?>">Resolution note</label>
                        <textarea class="form-control" id="note_<?= (int)$r['id'] ?>" name="admin_note" rows="3" placeholder="What did you find? What action was taken?"><?= e($r['admin_note'] ?? '') ?></textarea>
                      </div>
                      <div class="modal-actions">
                        <button type="button" class="btn btn-ghost" data-close-modal>Close</button>
                        <button type="submit" class="btn btn-primary">Save Resolution</button>
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
