<?php
/**
 * Notifications center: list all notifications, mark single or all as read.
 */
require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = db();
$userId = current_user_id();

// ---------- POST: mark read / mark all read ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'read') {
        $id = (int)($_POST['id'] ?? 0);
        $st = $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?');
        $st->execute([$id, $userId]);
        set_flash('success', 'Notification marked as read.');
        redirect('notifications.php');
    }
    if ($action === 'read_all') {
        $st = $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0');
        $st->execute([$userId]);
        set_flash('success', 'All notifications marked as read.');
        redirect('notifications.php');
    }

    set_flash('error', 'Invalid action.');
    redirect('notifications.php');
}

// ---------- Load notifications ----------
$totalSt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?');
$totalSt->execute([$userId]);
$total = (int)$totalSt->fetchColumn();

$unreadSt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$unreadSt->execute([$userId]);
$unread = (int)$unreadSt->fetchColumn();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$st = $pdo->prepare(
    'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?'
);
$st->bindValue(1, $userId, PDO::PARAM_INT);
$st->bindValue(2, $perPage, PDO::PARAM_INT);
$st->bindValue(3, $offset, PDO::PARAM_INT);
$st->execute();
$notifications = $st->fetchAll();

function notification_icon(string $type): string
{
    $icons = [
        'listing_approved' => '✅', 'listing_rejected' => '❌', 'listing_expired' => '⏰',
        'order_received'   => '🛍️', 'order_accepted'   => '✔️', 'order_rejected' => '🚫',
        'order_ready'      => '📦', 'order_completed'  => '🎉', 'order_cancelled' => '↩️',
        'message'          => '💬', 'favorite'         => '❤️', 'review_received' => '⭐',
        'report_status'    => '🚩', 'expiry_warning'   => '⏳', 'review_removed'   => '⭐',
    ];
    return $icons[$type] ?? '🔔';
}

function notification_link(string $type, ?int $relatedId): string
{
    if ($relatedId === null) {
        return '';
    }
    if (str_starts_with($type, 'order_')) {
        return APP_BASE_URL . '/user/purchases.php';
    }
    if ($type === 'message') {
        return APP_BASE_URL . '/user/messages.php?conv=' . $relatedId;
    }
    if (in_array($type, ['listing_approved', 'listing_rejected', 'expiry_warning', 'listing_expired'], true)) {
        return APP_BASE_URL . '/user/listings.php';
    }
    if (in_array($type, ['favorite'], true)) {
        return APP_BASE_URL . '/product-details.php?id=' . $relatedId;
    }
    if (in_array($type, ['review_received', 'review_removed'], true)) {
        return APP_BASE_URL . '/user/reviews.php';
    }
    if ($type === 'report_status') {
        return APP_BASE_URL . '/safety.php';
    }
    return '';
}

$pageTitle = 'Notifications';
$dashboardPage = true;
$activeNav = 'notifications';
require __DIR__ . '/../includes/header.php';
?>

<div class="dash-wrap">
  <?php require __DIR__ . '/_sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <div>
        <h1>Notifications</h1>
        <div class="sub"><?= (int)$unread ?> unread · <?= (int)$total ?> total</div>
      </div>
      <?php if ($unread > 0): ?>
        <form method="post" action="notifications.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="read_all">
          <button class="btn btn-ghost btn-sm" type="submit">Mark all as read</button>
        </form>
      <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>
      <div class="card empty-state">
        <div class="empty-icon">🔔</div>
        <h3>No notifications</h3>
        <p>When something happens with your listings, orders or messages, it will appear here.</p>
        <a class="btn btn-primary" href="<?= e(APP_BASE_URL . '/products.php') ?>">Browse Marketplace</a>
      </div>
    <?php else: ?>
      <div class="notif-list">
        <?php foreach ($notifications as $n): ?>
          <?php $link = notification_link($n['type'], $n['related_id'] !== null ? (int)$n['related_id'] : null); ?>
          <div class="notif-item card <?= $n['is_read'] ? '' : 'unread' ?>">
            <span class="notif-icon"><?= notification_icon($n['type']) ?></span>
            <div class="notif-body">
              <div class="notif-title"><?= e($n['title']) ?></div>
              <div class="notif-msg"><?= e($n['message']) ?></div>
              <div class="notif-meta">
                <span><?= e(time_ago($n['created_at'])) ?></span>
                <?php if ($n['type']): ?><span>· <?= e(str_replace('_', ' ', $n['type'])) ?></span><?php endif; ?>
              </div>
            </div>
            <div class="notif-actions">
              <?php if ($link !== ''): ?>
                <a class="btn btn-outline btn-sm" href="<?= e($link) ?>">View</a>
              <?php endif; ?>
              <?php if (!$n['is_read']): ?>
                <form method="post" action="notifications.php" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="read">
                  <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                  <button class="btn btn-ghost btn-sm" type="submit">Mark read</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php $baseUrl = APP_BASE_URL . '/user/notifications.php'; ?>
      <?= pagination($total, $page, $perPage, $baseUrl) ?>
    <?php endif; ?>
  </main>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
