<?php
/**
 * AJAX: record a product view (throttled in track_product_view).
 * POST product_id + csrf_token
 */
require_once __DIR__ . '/../includes/init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

verify_csrf();

$productId = (int)($_POST['product_id'] ?? 0);
$userId = is_logged_in() ? current_user_id() : null;

if ($productId < 1) {
    echo json_encode(['ok' => false]);
    exit;
}

track_product_view($productId, $userId);
echo json_encode(['ok' => true]);
