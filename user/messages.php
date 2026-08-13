<?php
/**
 * Internal messaging: conversation list + active thread.
 * New conversations are started from a product page via ?user=&product=.
 * Live updates use light polling (ajax/messages.php), no websockets.
 */
require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = db();
$userId = current_user_id();

// ---------- Start a new conversation from ?user= / ?product= ----------
$startUser = (int)($_GET['user'] ?? 0);
$startProduct = (int)($_GET['product'] ?? 0);

if ($startUser > 0 && $startUser !== $userId) {
    $target = get_user($startUser);
    if ($target === null || $target['status'] !== 'active') {
        set_flash('error', 'That user is not available to message right now.');
        redirect('messages.php');
    }
    $product = $startProduct > 0 ? get_product($startProduct) : null;
    if ($product !== null && (int)$product['seller_id'] !== $startUser) {
        $startUser = (int)$product['seller_id'];
    }
    $convId = get_or_create_conversation($userId, $startUser, $startProduct > 0 ? $startProduct : null);
    redirect('messages.php?conv=' . $convId);
} elseif ($startProduct > 0 && $startUser === 0) {
    $product = get_product($startProduct);
    if ($product !== null && (int)$product['seller_id'] !== $userId) {
        $convId = get_or_create_conversation($userId, (int)$product['seller_id'], $startProduct);
        redirect('messages.php?conv=' . $convId);
    }
}

// ---------- Conversation list ----------
$st = $pdo->prepare(
    "SELECT c.id, c.product_id, c.last_message_at,
            CASE WHEN c.user_a = ? THEN c.user_b ELSE c.user_a END AS other_id,
            (SELECT body FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_body,
            (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_id <> ? AND m.is_read = 0) AS unread
     FROM conversations c
     WHERE c.user_a = ? OR c.user_b = ?
     ORDER BY COALESCE(c.last_message_at, c.created_at) DESC"
);
$st->execute([$userId, $userId, $userId, $userId]);
$conversations = $st->fetchAll();

// ---------- Active conversation ----------
$active = null;
$activeConv = null;
$activeMessages = [];
if (isset($_GET['conv'])) {
    $activeId = (int)$_GET['conv'];
    $st = $pdo->prepare('SELECT * FROM conversations WHERE id = ? AND (user_a = ? OR user_b = ?)');
    $st->execute([$activeId, $userId, $userId]);
    $activeConv = $st->fetch() ?: null;
    if ($activeConv !== null) {
        $otherId = (int)$activeConv['user_a'] === $userId ? (int)$activeConv['user_b'] : (int)$activeConv['user_a'];
        $active = get_user($otherId);
        $product = $activeConv['product_id'] ? get_product((int)$activeConv['product_id']) : null;

        // Mark incoming messages as read.
        $pdo->prepare('UPDATE messages SET is_read = 1, read_at = NOW() WHERE conversation_id = ? AND sender_id <> ? AND is_read = 0')
            ->execute([$activeId, $userId]);

        $st = $pdo->prepare('SELECT id, sender_id, body, created_at FROM messages WHERE conversation_id = ? ORDER BY id ASC LIMIT 500');
        $st->execute([$activeId]);
        $activeMessages = $st->fetchAll();
        $lastMsgId = $activeMessages ? (int)$activeMessages[count($activeMessages) - 1]['id'] : 0;
    } else {
        set_flash('error', 'Conversation not found.');
        redirect('messages.php');
    }
}

$pageTitle = 'Messages';
$dashboardPage = true;
$activeNav = 'messages';
$extraScripts = ['/assets/js/messages.js'];
require __DIR__ . '/../includes/header.php';
?>

<div class="dash-wrap">
  <?php require __DIR__ . '/_sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <div>
        <h1>Messages</h1>
        <div class="sub">Talk to buyers and sellers without sharing your phone number</div>
      </div>
    </div>

    <?php if (empty($conversations) && $activeConv === null): ?>
      <div class="card empty-state">
        <div class="empty-icon">💬</div>
        <h3>No conversations yet</h3>
        <p>Open any listing and press "Contact Seller" to start a conversation.</p>
        <a class="btn btn-primary" href="<?= e(APP_BASE_URL . '/products.php') ?>">Browse Marketplace</a>
      </div>
    <?php else: ?>
      <div class="messages-layout">
        <div class="card conv-list">
          <div class="conv-list-head"><h3>Conversations</h3></div>
          <?php if (empty($conversations)): ?>
            <p class="text-small text-muted" style="padding:16px;">No conversations yet.</p>
          <?php else: ?>
            <?php foreach ($conversations as $c): ?>
              <?php $other = get_user((int)$c['other_id']); ?>
              <a class="conv-item <?= $activeConv && (int)$activeConv['id'] === (int)$c['id'] ? 'active' : '' ?> <?= (int)$c['unread'] > 0 ? 'unread' : '' ?>"
                 href="<?= e(APP_BASE_URL . '/user/messages.php?conv=' . (int)$c['id']) ?>">
                <span class="avatar avatar-sm" data-initials="<?= e(strtoupper(mb_substr($other['full_name'] ?? 'U', 0, 2))) ?>">
                  <?php if (!empty($other['profile_image'])): ?><img src="<?= e(image_url($other['profile_image'])) ?>" alt=""><?php endif; ?>
                </span>
                <span class="conv-body">
                  <span class="conv-name">
                    <span><?= e($other['full_name'] ?? 'User') ?></span>
                    <?php if ($c['last_message_at']): ?><span class="conv-time"><?= e(time_ago($c['last_message_at'])) ?></span><?php endif; ?>
                  </span>
                  <span class="conv-preview">
                    <?= e(mb_strimwidth($c['last_body'] ?? 'No messages yet', 0, 42, '…')) ?>
                    <?php if ((int)$c['unread'] > 0): ?><span class="nav-badge"><?= (int)$c['unread'] ?></span><?php endif; ?>
                  </span>
                </span>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <?php if ($activeConv !== null && $active !== null): ?>
          <div class="thread" data-thread="<?= (int)$activeConv['id'] ?>">
            <div class="thread-header">
              <a class="back-btn" href="<?= e(APP_BASE_URL . '/user/messages.php') ?>" aria-label="Back to conversations">&larr;</a>
              <span class="avatar avatar-sm" data-initials="<?= e(strtoupper(mb_substr($active['full_name'] ?? 'U', 0, 2))) ?>">
                <?php if (!empty($active['profile_image'])): ?><img src="<?= e(image_url($active['profile_image'])) ?>" alt=""><?php endif; ?>
              </span>
              <span class="thread-user">
                <strong><?= e($active['full_name'] ?? 'User') ?></strong>
                <?php if (!empty($product)): ?>
                  <span class="text-small text-muted">about <a href="<?= e(APP_BASE_URL . '/product-details.php?id=' . (int)$product['id']) ?>"><?= e($product['title']) ?></a></span>
                <?php else: ?>
                  <span class="text-small text-muted"><?= e($active['department'] ?? 'Student') ?></span>
                <?php endif; ?>
              </span>
            </div>

            <div class="thread-messages" id="threadMessages" data-last-id="<?= (int)($lastMsgId ?? 0) ?>">
              <?php if (empty($activeMessages)): ?>
                <div class="thread-empty">Say hello and arrange a campus meetup.</div>
              <?php else: ?>
                <?php foreach ($activeMessages as $m): ?>
                  <div class="msg-bubble <?= (int)$m['sender_id'] === $userId ? 'msg-mine' : 'msg-theirs' ?>">
                    <?= e($m['body']) ?>
                    <span class="msg-time"><?= date('M j, g:i A', strtotime($m['created_at'])) ?></span>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <form class="thread-compose" id="composeForm" autocomplete="off">
              <textarea class="form-control" name="body" rows="2" maxlength="2000" placeholder="Write a message…"></textarea>
              <button class="btn btn-primary" type="submit">Send</button>
            </form>
            <div class="thread-safety text-small text-muted">
              🛡️ Meet in public campus areas and inspect items before handing over money.
            </div>
          </div>
        <?php else: ?>
          <div class="card thread thread-placeholder">
            <div class="thread-empty">Select a conversation to read your messages.</div>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </main>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
