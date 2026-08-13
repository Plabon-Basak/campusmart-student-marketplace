<?php
/**
 * AJAX: search suggestions for the header / hero search box.
 * GET ?q=...
 */
require_once __DIR__ . '/../includes/init.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$terms = preg_split('/\s+/', $q);
$where = [];
$params = [];
foreach ($terms as $term) {
    $pattern = '\b' . escape_regex($term) . '\b';
    $where[] = '(p.title REGEXP ? OR p.description REGEXP ? OR c.name REGEXP ?)';
    $params[] = $pattern;
    $params[] = $pattern;
    $params[] = $pattern;
}

$sql = 'SELECT p.id, p.title, p.price,
               c.name AS category_name,
               (SELECT pi.image_path FROM product_images pi
                WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.id ASC LIMIT 1) AS image_path
        FROM products p
        JOIN categories c ON c.id = p.category_id
        JOIN users u ON u.id = p.seller_id
        WHERE p.status = "active" AND u.status = "active" AND ' . implode(' AND ', $where) . '
        ORDER BY p.views DESC
        LIMIT 6';

$st = db()->prepare($sql);
$st->execute($params);

$currency = settings('currency') ?: '';
$out = [];
foreach ($st->fetchAll() as $row) {
    $out[] = [
        'id'       => (int)$row['id'],
        'title'    => htmlspecialchars($row['title'], ENT_QUOTES),
        'category' => htmlspecialchars($row['category_name'], ENT_QUOTES),
        'price'    => $currency . ' ' . number_format((float)$row['price'], 0),
        'image'    => image_url($row['image_path']),
    ];
}
echo json_encode($out);
