<?php
/**
 * AJAX: conversations / messages.
 * GET  conversation=<id>[&since=<id>]  -> list of messages in a thread
 * POST conversation=<id>&body=<text>   -> send a message
 */
require_once __DIR__ . '/../includes/init.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'redirect' => 'login.php']);
    exit;
}

$userId = current_user_id();
$pdo = db();

function conversation_belongs_to_user(PDO $pdo, int $conversationId, int $userId): bool
{
    $st = $pdo->prepare('SELECT 1 FROM conversations WHERE id = ? AND (user_a = ? OR user_b = ?)');
    $st->execute([$conversationId, $userId, $userId]);
    return (bool)$st->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $conversationId = (int)($_POST['conversation'] ?? 0);
    $body = trim($_POST['body'] ?? '');

    if ($conversationId < 1 || !conversation_belongs_to_user($pdo, $conversationId, $userId)) {
        echo json_encode(['ok' => false, 'message' => 'Conversation not found.']);
        exit;
    }
    if ($body === '' || mb_strlen($body) > 2000) {
        echo json_encode(['ok' => false, 'message' => 'Message must be between 1 and 2000 characters.']);
        exit;
    }

    // Suspended users cannot message.
    $user = current_user();
    if ($user === null || $user['status'] !== 'active') {
        echo json_encode(['ok' => false, 'message' => 'Your account cannot send messages right now.']);
        exit;
    }

    $ins = $pdo->prepare('INSERT INTO messages (conversation_id, sender_id, body) VALUES (?, ?, ?)');
    $ins->execute([$conversationId, $userId, $body]);
    $pdo->prepare('UPDATE conversations SET last_message_at = NOW() WHERE id = ?')->execute([$conversationId]);

    // Notify the other participant.
    $st = $pdo->prepare('SELECT user_a, user_b FROM conversations WHERE id = ?');
    $st->execute([$conversationId]);
    $conv = $st->fetch();
    $other = (int)$conv['user_a'] === $userId ? (int)$conv['user_b'] : (int)$conv['user_a'];
    if ($other !== $userId) {
        $otherUser = get_user($other);
        create_notification($other, 'message', 'New message', $user['full_name'] . ' sent you a message.', $conversationId);
    }

    echo json_encode(['ok' => true, 'message_id' => (int)$pdo->lastInsertId()]);
    exit;
}

// GET: fetch messages
$conversationId = (int)($_GET['conversation'] ?? 0);
$since = (int)($_GET['since'] ?? 0);

if ($conversationId < 1 || !conversation_belongs_to_user($pdo, $conversationId, $userId)) {
    echo json_encode(['ok' => false, 'message' => 'Conversation not found.']);
    exit;
}

// Mark all incoming messages in this conversation as read.
$pdo->prepare('UPDATE messages SET is_read = 1, read_at = NOW() WHERE conversation_id = ? AND sender_id <> ? AND is_read = 0')
    ->execute([$conversationId, $userId]);

$st = $pdo->prepare('SELECT id, sender_id, body, created_at FROM messages WHERE conversation_id = ? AND id > ? ORDER BY id ASC LIMIT 200');
$st->execute([$conversationId, $since]);

$out = [];
foreach ($st->fetchAll() as $m) {
    $out[] = [
        'id'     => (int)$m['id'],
        'mine'   => (int)$m['sender_id'] === $userId,
        'body'   => htmlspecialchars($m['body'], ENT_QUOTES),
        'time'   => date('M j, g:i A', strtotime($m['created_at'])),
    ];
}
echo json_encode(['ok' => true, 'messages' => $out]);
