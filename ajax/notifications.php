<?php
/**
 * AJAX: notifications.
 * POST mark=read|all
 * GET  count=1 -> unread count
 */
require_once __DIR__ . '/../includes/init.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'redirect' => 'login.php']);
    exit;
}

$userId = current_user_id();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'read') {
        $id = (int)($_POST['id'] ?? 0);
        $st = $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?');
        $st->execute([$id, $userId]);
        echo json_encode(['ok' => true]);
    } elseif ($action === 'read_all') {
        $st = $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0');
        $st->execute([$userId]);
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Unknown action.']);
    }
    exit;
}

$count = unread_notifications_count($userId);
echo json_encode(['ok' => true, 'unread' => $count]);
