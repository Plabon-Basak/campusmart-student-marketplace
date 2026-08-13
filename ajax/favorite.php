<?php
/**
 * AJAX: add / remove a favorite.
 * POST  product_id + csrf_token
 */
require_once __DIR__ . '/../includes/init.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'redirect' => 'login.php']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Invalid request method.']);
    exit;
}

verify_csrf();

$productId = (int)($_POST['product_id'] ?? 0);
if ($productId < 1) {
    echo json_encode(['ok' => false, 'message' => 'Invalid product.']);
    exit;
}

$product = get_product($productId);
if ($product === null) {
    echo json_encode(['ok' => false, 'message' => 'Product not found.']);
    exit;
}

$userId = current_user_id();
$pdo = db();

$favSt = $pdo->prepare('SELECT id FROM favorites WHERE user_id = ? AND product_id = ?');
$favSt->execute([$userId, $productId]);
$existing = $favSt->fetchColumn();

if ($existing) {
    $del = $pdo->prepare('DELETE FROM favorites WHERE id = ?');
    $del->execute([(int)$existing]);
    echo json_encode(['ok' => true, 'favorited' => false, 'message' => 'Removed from favorites.']);
} else {
    $ins = $pdo->prepare('INSERT INTO favorites (user_id, product_id) VALUES (?, ?)');
    $ins->execute([$userId, $productId]);
    // Notify the seller (once per user/product thanks to the unique key).
    if ((int)$product['seller_id'] !== $userId) {
        create_notification(
            (int)$product['seller_id'],
            'favorite',
            'Product favorited',
            'Someone added your listing "' . $product['title'] . '" to their favorites.',
            $productId
        );
    }
    echo json_encode(['ok' => true, 'favorited' => true, 'message' => 'Added to favorites.']);
}
