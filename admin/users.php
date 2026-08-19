<?php
/**
 * Admin: manage users - search, view details, suspend and reactivate.
 * Accounts with transaction history are suspended rather than deleted.
 */
require_once __DIR__ . '/../includes/admin-auth.php';

$pdo = db();
$adminId = current_user_id();

// ---------- POST: suspend / reactivate ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    $target = $userId > 0 ? get_user($userId) : null;
    if ($target === null || $target['role'] === 'admin') {
        set_flash('error', 'That user cannot be modified.');
        redirect('users.php');
    }

    if ($action === 'suspend') {
        $st = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
        $st->execute([$userId]);
        audit_log('user_suspended', 'user', $userId, 'Suspended user ' . $target['full_name'] . ' (' . $target['email'] . ').');
        create_notification($userId, 'account', 'Account suspended', 'Your CampusMart account has been suspended. Contact support for help.');
        set_flash('success', $target['full_name'] . ' has been suspended.');
        redirect('users.php');
    }

    if ($action === 'activate') {
        $st = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $st->execute([$userId]);
        audit_log('user_reactivated', 'user', $userId, 'Reactivated user ' . $target['full_name'] . ' (' . $target['email'] . ').');
        create_notification($userId, 'account', 'Account reactivated', 'Your CampusMart account has been reactivated. Welcome back!');
        set_flash('success', $target['full_name'] . ' has been reactivated.');
        redirect('users.php');
    }

    set_flash('error', 'Invalid action.');
    redirect('users.php');
}

// ---------- List users ----------
$search = trim((string)($_GET['q'] ?? ''));
$statusFilter = (string)($_GET['status'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$sql = "SELECT u.*,
        (SELECT COUNT(*) FROM products p WHERE p.seller_id = u.id) AS listings,
        (SELECT COUNT(*) FROM orders o WHERE o.seller_id = u.id) AS sales,
        (SELECT COUNT(*) FROM orders o WHERE o.buyer_id = u.id) AS purchases,
        (SELECT COUNT(*) FROM reports r WHERE r.reported_user_id = u.id AND r.status IN ('pending','under_review')) AS open_reports
        FROM users u WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR u.student_id LIKE ? OR u.phone LIKE ?)';
    $like = '%' . escape_like($search) . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($statusFilter === 'active' || $statusFilter === 'suspended') {
    $sql .= ' AND u.status = ?';
    $params[] = $statusFilter;
}

$wherePart = substr($sql, strpos($sql, 'WHERE 1=1'));
$countSt = $pdo->prepare('SELECT COUNT(*) FROM users u ' . $wherePart);
$countSt->execute($params);
$total = (int)$countSt->fetchColumn();

$sql .= ' ORDER BY u.created_at DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;

$st = $pdo->prepare($sql);
$st->execute($params);
$users = $st->fetchAll();

$qs = http_build_query(array_filter(['q' => $search, 'status' => $statusFilter]));
$baseUrl = APP_BASE_URL . '/admin/users.php' . ($qs !== '' ? '?' . $qs : '');

$pageTitle = 'Manage Users';
$dashboardPage = true;
$activeNav = 'users';
require __DIR__ . '/../includes/header.php';
?>

<div class="dash-wrap">
  <?php require __DIR__ . '/_sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <div>
        <h1>Users</h1>
        <div class="sub"><?= (int)$total ?> account<?= $total === 1 ? '' : 's' ?></div>
      </div>
      <form method="get" action="users.php" class="filter-form">
        <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, email, ID…">
        <select class="form-control" name="status" onchange="this.form.submit()">
          <option value="">All statuses</option>
          <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
        </select>
        <button class="btn btn-primary btn-sm" type="submit">Search</button>
      </form>
    </div>

    <?php if (empty($users)): ?>
      <div class="card empty-state">
        <div class="empty-icon">👥</div>
        <h3>No users found</h3>
        <p>Try a different search or filter.</p>
      </div>
    <?php else: ?>
      <div class="card table-card">
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>User</th>
                <th>Student ID</th>
                <th>Department</th>
                <th>Activity</th>
                <th>Status</th>
                <th>Joined</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td>
                    <div class="flex" style="gap:10px; align-items:center;">
                      <span class="avatar avatar-sm" data-initials="<?= e(strtoupper(substr($u['full_name'], 0, 2))) ?>">
                        <?php if ($u['profile_image']): ?><img src="<?= e(image_url($u['profile_image'])) ?>" alt=""><?php endif; ?>
                      </span>
                      <span>
                        <strong><?= e($u['full_name']) ?></strong>
                        <span class="block text-small text-muted"><?= e($u['email']) ?></span>
                      </span>
                    </div>
                  </td>
                  <td class="text-small"><?= e($u['student_id']) ?></td>
                  <td class="text-small"><?= e(mb_strimwidth($u['department'] ?? '-', 0, 24, '…')) ?></td>
                  <td class="text-small text-muted">
                    <?= (int)$u['listings'] ?> listings · <?= (int)$u['sales'] ?> sales · <?= (int)$u['purchases'] ?> purchases
                  </td>
                  <td>
                    <?php if ($u['role'] === 'admin'): ?>
                      <span class="badge badge-info">Admin</span>
                    <?php else: ?>
                      <span class="badge badge-<?= $u['status'] === 'active' ? 'success' : 'danger' ?>"><?= e(ucfirst($u['status'])) ?></span>
                      <?php if ((int)$u['open_reports'] > 0): ?><span class="badge badge-warning" title="Open reports">🚩 <?= (int)$u['open_reports'] ?></span><?php endif; ?>
                    <?php endif; ?>
                  </td>
                  <td class="text-small"><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
                  <td>
                    <div class="flex" style="gap:6px; justify-content:flex-end;">
                      <button type="button" class="btn btn-ghost btn-sm" data-open-modal="userModal<?= (int)$u['id'] ?>">View</button>
                      <?php if ($u['role'] !== 'admin'): ?>
                        <?php if ($u['status'] === 'active'): ?>
                          <form method="post" action="users.php" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="suspend">
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <button class="btn btn-danger-soft btn-sm" type="submit" data-confirm-dangerous data-confirm="Suspend <?= e($u['full_name']) ?>? They will not be able to log in or use the marketplace.">Suspend</button>
                          </form>
                        <?php else: ?>
                          <form method="post" action="users.php" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="activate">
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <button class="btn btn-success btn-sm" type="submit">Reactivate</button>
                          </form>
                        <?php endif; ?>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>

                <!-- User detail modal -->
                <div class="modal-backdrop" id="userModal<?= (int)$u['id'] ?>" role="dialog" aria-modal="true">
                  <div class="modal">
                    <h3><?= e($u['full_name']) ?></h3>
                    <div class="detail-grid">
                      <div><div class="label">Student ID</div><div class="value"><?= e($u['student_id']) ?></div></div>
                      <div><div class="label">Email</div><div class="value"><?= e($u['email']) ?></div></div>
                      <div><div class="label">Phone</div><div class="value"><?= e($u['phone'] ?? '-') ?></div></div>
                      <div><div class="label">Department</div><div class="value"><?= e($u['department'] ?? '-') ?></div></div>
                      <div><div class="label">Batch</div><div class="value"><?= e($u['batch'] ?? '-') ?></div></div>
                      <div><div class="label">Hall / Campus</div><div class="value"><?= e($u['hall'] ?? '-') ?></div></div>
                      <div><div class="label">Role</div><div class="value"><?= e(ucfirst($u['role'])) ?></div></div>
                      <div><div class="label">Joined</div><div class="value"><?= e(date('M j, Y g:i A', strtotime($u['created_at']))) ?></div></div>
                    </div>
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
