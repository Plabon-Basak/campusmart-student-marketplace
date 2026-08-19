<?php
/**
 * Core helper functions for CampusMart.
 * All pages must load config + this file (see includes/init.php).
 */

declare(strict_types=1);

/* ------------------------------------------------------------------
 * Database
 * ------------------------------------------------------------------ */

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            exit('Database connection failed.');
        }
    }
    return $pdo;
}

/* ------------------------------------------------------------------
 * Output escaping & redirects
 * ------------------------------------------------------------------ */

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function redirect_back(string $fallback = 'index.php'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($ref !== '' && parse_url($ref, PHP_URL_HOST) === $host) {
        redirect($ref);
    }
    redirect($fallback);
}

/* ------------------------------------------------------------------
 * Flash messages
 * ------------------------------------------------------------------ */

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/* ------------------------------------------------------------------
 * CSRF protection
 * ------------------------------------------------------------------ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Validates the CSRF token from a POST request. Exits on failure.
 */
function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$sent)) {
        http_response_code(419);
        exit('Invalid security token. Please go back, refresh the page and try again.');
    }
}

/* ------------------------------------------------------------------
 * Settings (cached)
 * ------------------------------------------------------------------ */

function settings(?string $key = null): mixed
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $rows = db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
            foreach ($rows as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable) {
            $cache = [];
        }
    }
    if ($key === null) {
        return $cache;
    }
    return $cache[$key] ?? null;
}

function save_setting(string $key, string $value): void
{
    $st = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $st->execute([$key, $value]);
}

/* ------------------------------------------------------------------
 * Authentication helpers
 * ------------------------------------------------------------------ */

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $st = db()->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([(int)$_SESSION['user_id']]);
        $user = $st->fetch() ?: null;
    }
    return $user;
}

function current_user_id(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === 'admin';
}

/**
 * Requires a logged-in user with an active (non-suspended) account.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? '';
        set_flash('error', 'Please log in to continue.');
        redirect('login.php');
    }
    $user = current_user();
    if ($user === null) {
        logout_user();
        redirect('login.php');
    }
    if ($user['status'] === 'suspended') {
        logout_user();
        set_flash('error', 'Your account has been suspended. Contact support for help.');
        redirect('login.php');
    }
}

function require_admin(): void
{
    if (!is_admin()) {
        if (!is_logged_in()) {
            set_flash('error', 'Please log in to continue.');
            redirect('login.php');
        }
        http_response_code(403);
        set_flash('error', 'You do not have permission to access that page.');
        redirect('../index.php');
    }
}

/* ------------------------------------------------------------------
 * Notifications
 * ------------------------------------------------------------------ */

function create_notification(int $userId, string $type, string $title, string $message, ?int $relatedId = null): void
{
    try {
        $st = db()->prepare(
            'INSERT INTO notifications (user_id, type, title, message, related_id) VALUES (?, ?, ?, ?, ?)'
        );
        $st->execute([$userId, $type, $title, $message, $relatedId]);
    } catch (Throwable) {
        // Notifications must never break a page.
    }
}

function unread_notifications_count(int $userId): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $st->execute([$userId]);
    return (int)$st->fetchColumn();
}

function notify_admins(string $type, string $title, string $message, ?int $relatedId = null): void
{
    $st = db()->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'");
    foreach ($st->fetchAll() as $admin) {
        create_notification((int)$admin['id'], $type, $title, $message, $relatedId);
    }
}

/* ------------------------------------------------------------------
 * Audit log
 * ------------------------------------------------------------------ */

function audit_log(string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null, ?int $actorId = null): void
{
    try {
        $st = db()->prepare(
            'INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $actorId ?? current_user_id() ?: null,
            $action,
            $entityType,
            $entityId,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable) {
        // Audit logging must never break a page.
    }
}

/* ------------------------------------------------------------------
 * Formatting helpers
 * ------------------------------------------------------------------ */

function format_price(mixed $amount): string
{
    $currency = settings('currency') ?: '৳';
    return $currency . ' ' . number_format((float)$amount, 0);
}

function format_decimal_price(mixed $amount): string
{
    $currency = settings('currency') ?: '৳';
    return $currency . ' ' . number_format((float)$amount, 2);
}

function time_ago(?string $datetime): string
{
    if ($datetime === null) {
        return 'N/A';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    $diff = time() - $ts;
    if ($diff < 0) {
        $diff = 0;
    }
    $units = [
        31536000 => 'year', 2592000 => 'month', 604800 => 'week',
        86400 => 'day', 3600 => 'hour', 60 => 'minute',
    ];
    foreach ($units as $secs => $label) {
        if ($diff >= $secs) {
            $n = (int)floor($diff / $secs);
            return $n . ' ' . $label . ($n > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}

function slugify(string $text): string
{
    $text = preg_replace('/[^a-zA-Z0-9 -]/', '', strtolower($text));
    $text = preg_replace('/[-\s]+/', '-', trim($text));
    return rtrim($text, '-');
}

/* ------------------------------------------------------------------
 * Status labels & badges
 * ------------------------------------------------------------------ */

function product_status_label(string $status): string
{
    $labels = [
        'draft'    => 'Draft',
        'pending'  => 'Pending Review',
        'approved' => 'Approved',
        'active'   => 'Active',
        'reserved' => 'Reserved',
        'sold'     => 'Sold',
        'expired'  => 'Expired',
        'rejected' => 'Rejected',
        'removed'  => 'Removed',
    ];
    return $labels[$status] ?? ucfirst($status);
}

function product_status_class(string $status): string
{
    $map = [
        'active' => 'success', 'approved' => 'info', 'sold' => 'dark',
        'reserved' => 'warning', 'pending' => 'pending', 'draft' => 'muted',
        'expired' => 'muted', 'rejected' => 'danger', 'removed' => 'danger',
    ];
    return $map[$status] ?? 'muted';
}

function order_status_label(string $status): string
{
    $labels = [
        'pending'   => 'Pending',
        'accepted'  => 'Accepted',
        'ready'     => 'Ready for Pickup',
        'completed' => 'Completed',
        'rejected'  => 'Rejected',
        'cancelled' => 'Cancelled',
    ];
    return $labels[$status] ?? ucfirst($status);
}

function order_status_class(string $status): string
{
    $map = [
        'pending' => 'pending', 'accepted' => 'info', 'ready' => 'warning',
        'completed' => 'success', 'rejected' => 'danger', 'cancelled' => 'muted',
    ];
    return $map[$status] ?? 'muted';
}

function condition_options(): array
{
    return ['New', 'Like New', 'Good', 'Fair', 'Used'];
}

/**
 * Official campus halls grouped by gender.
 *
 * @return array<string, string[]> Map of group label => hall names.
 */
function hall_groups(): array
{
    return [
        'Male Halls' => [
            'Shaheed Nur Hossain Hall',
            'Shaheed President Ziaur Rahman Hall',
            'Shaheed Abrar Fahad Hall',
            'International Hall',
            'Bijoy 24 Hall',
            'Mohabolipur',
            'BCS Goli',
        ],
        'Female Halls' => [
            'Begum Rokeya Hall',
            'Nawab Faizunnesa Hall',
            'Kobi Sufia Kamal Hall',
            'Khurshid Zahan Haque Hall',
        ],
    ];
}

/**
 * Renders a grouped <select> of official campus halls.
 */
function hall_select_html(string $name, string $selected = '', string $placeholder = 'Select your campus hall'): string
{
    $html = '<select class="form-control" id="' . e($name) . '" name="' . e($name) . '" required>';
    $html .= '<option value="">' . e($placeholder) . '</option>';
    foreach (hall_groups() as $group => $halls) {
        $html .= '<optgroup label="' . e($group) . '">';
        foreach ($halls as $hall) {
            $sel = $hall === $selected ? ' selected' : '';
            $html .= '<option value="' . e($hall) . '"' . $sel . '>' . e($hall) . '</option>';
        }
        $html .= '</optgroup>';
    }
    $html .= '</select>';
    return $html;
}

function report_reasons(): array
{
    return [
        'Scam / suspicious listing',
        'Fake product',
        'Duplicate listing',
        'Wrong category',
        'Inappropriate content',
        'Prohibited item',
        'Suspicious user',
    ];
}

/* ------------------------------------------------------------------
 * Categories
 * ------------------------------------------------------------------ */

function get_categories(bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM categories';
    if ($activeOnly) {
        $sql .= " WHERE status = 'active'";
    }
    $sql .= ' ORDER BY name ASC';
    return db()->query($sql)->fetchAll();
}

function get_category(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM categories WHERE id = ?');
    $st->execute([$id]);
    $cat = $st->fetch();
    return $cat ?: null;
}

/* ------------------------------------------------------------------
 * Users
 * ------------------------------------------------------------------ */

function get_user(int $id): ?array
{
    static $cache = [];
    if (isset($cache[$id])) {
        return $cache[$id];
    }
    $st = db()->prepare('SELECT id, full_name, student_id, email, phone, department, batch, hall, profile_image, role, status, is_verified, created_at FROM users WHERE id = ?');
    $st->execute([$id]);
    $user = $st->fetch() ?: null;
    if ($user) {
        $cache[$id] = $user;
    }
    return $user;
}

function seller_stats(int $userId): array
{
    $st = db()->prepare(
        "SELECT COALESCE(AVG(r.rating), 0) AS avg_rating, COUNT(r.id) AS review_count
         FROM reviews r
         WHERE r.reviewed_user_id = ? AND r.status = 'approved'"
    );
    $st->execute([$userId]);
    $stats = $st->fetch();
    $sold = db()->prepare("SELECT COUNT(*) FROM orders WHERE seller_id = ? AND status = 'completed'");
    $sold->execute([$userId]);
    return [
        'avg_rating'  => round((float)$stats['avg_rating'], 1),
        'review_count'=> (int)$stats['review_count'],
        'sales_count' => (int)$sold->fetchColumn(),
    ];
}

function is_trusted_seller(int $userId): bool
{
    $stats = seller_stats($userId);
    $minReviewsRaw = settings('trusted_seller_min_reviews');
    $minRatingRaw  = settings('trusted_seller_min_rating');
    $minReviews = ($minReviewsRaw !== null && $minReviewsRaw !== '') ? (int)$minReviewsRaw : 5;
    $minRating  = ($minRatingRaw !== null && $minRatingRaw !== '') ? (float)$minRatingRaw : 4.5;
    return $stats['review_count'] >= $minReviews && $stats['avg_rating'] >= $minRating;
}

function avatar_url(?array $user, int $size = 128): string
{
    if (!empty($user['profile_image']) && file_exists(BASE_PATH . '/' . $user['profile_image'])) {
        return APP_BASE_URL . '/' . ltrim($user['profile_image'], '/');
    }
    // Initials avatar generated on the client using the data attributes.
    return APP_BASE_URL . '/assets/images/avatar-placeholder.svg';
}

function user_display_name(array $user): string
{
    return $user['full_name'] ?: ($user['email'] ?? 'Student');
}

/* ------------------------------------------------------------------
 * Products
 * ------------------------------------------------------------------ */

function get_product(int $id): ?array
{
    $st = db()->prepare(
        'SELECT p.*, c.name AS category_name,
                u.full_name AS seller_name, u.student_id AS seller_student_id,
                u.profile_image AS seller_image, u.is_verified AS seller_verified,
                u.status AS seller_status
         FROM products p
         JOIN categories c ON c.id = p.category_id
         JOIN users u ON u.id = p.seller_id
         WHERE p.id = ?'
    );
    $st->execute([$id]);
    $p = $st->fetch();
    return $p ?: null;
}

function product_images(int $productId): array
{
    $st = db()->prepare(
        'SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC'
    );
    $st->execute([$productId]);
    return $st->fetchAll();
}

function product_primary_image(int $productId): ?string
{
    $st = db()->prepare(
        'SELECT image_path FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC LIMIT 1'
    );
    $st->execute([$productId]);
    $path = $st->fetchColumn();
    return $path ?: null;
}

function image_url(?string $path): string
{
    if ($path && file_exists(BASE_PATH . '/' . ltrim($path, '/'))) {
        return APP_BASE_URL . '/' . ltrim($path, '/');
    }
    return APP_BASE_URL . '/assets/images/no-image.svg';
}

function product_purchaseable_status(array $product): bool
{
    return in_array($product['status'], ['active', 'reserved'], true) && $product['seller_status'] === 'active';
}

function favorite_exists(int $userId, int $productId): bool
{
    $st = db()->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND product_id = ?');
    $st->execute([$userId, $productId]);
    return (bool)$st->fetchColumn();
}

/* ------------------------------------------------------------------
 * Orders
 * ------------------------------------------------------------------ */

function generate_order_code(PDO $pdo): string
{
    // CM-000001 style sequential codes derived from a persisted counter row.
    $st = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = '_last_order_seq' FOR UPDATE");
    $seq = (int)$st->fetchColumn();
    $seq++;
    save_setting('_last_order_seq', (string)$seq);
    return 'CM-' . str_pad((string)$seq, 6, '0', STR_PAD_LEFT);
}

function order_transition_allowed(string $from, string $to): bool
{
    $allowed = [
        'pending'   => ['accepted', 'rejected', 'cancelled'],
        'accepted'  => ['ready', 'rejected'],
        'ready'     => ['completed'],
    ];
    return in_array($to, $allowed[$from] ?? [], true);
}

function append_order_history(PDO $pdo, int $orderId, string $status): void
{
    $st = $pdo->prepare('SELECT status_history FROM orders WHERE id = ?');
    $st->execute([$orderId]);
    $history = $st->fetchColumn();
    $history = $history ? json_decode($history, true) : [];
    if (!is_array($history)) {
        $history = [];
    }
    $history[] = ['status' => $status, 'at' => date('Y-m-d H:i:s')];
    $st = $pdo->prepare('UPDATE orders SET status_history = ? WHERE id = ?');
    $st->execute([json_encode($history), $orderId]);
}

function get_order(int $id): ?array
{
    $st = db()->prepare(
        'SELECT o.*, p.title AS product_title, p.seller_id AS product_seller_id,
                b.full_name AS buyer_name, s.full_name AS seller_name,
                p.contact_preference
         FROM orders o
         JOIN products p ON p.id = o.product_id
         JOIN users b ON b.id = o.buyer_id
         JOIN users s ON s.id = o.seller_id
         WHERE o.id = ?'
    );
    $st->execute([$id]);
    $o = $st->fetch();
    return $o ?: null;
}

/**
 * One user may only have one open (non-terminal) request for the same product.
 */
function user_has_open_request(int $buyerId, int $productId): bool
{
    $st = db()->prepare(
        "SELECT 1 FROM orders
         WHERE buyer_id = ? AND product_id = ? AND status IN ('pending','accepted','ready')
         LIMIT 1"
    );
    $st->execute([$buyerId, $productId]);
    return (bool)$st->fetchColumn();
}

/* ------------------------------------------------------------------
 * Order transitions (central business logic for sales/purchases)
 * ------------------------------------------------------------------ */

/**
 * Applies a status transition to an order with full business-rule checks
 * and side effects. Both buyer and seller pages reuse this.
 *
 * Rules:
 *  - pending  -> accepted (seller), rejected (seller), cancelled (buyer)
 *  - accepted -> ready (seller), rejected (seller)
 *  - ready    -> completed (seller)
 *
 * @param array $order  The full order row (see get_order()).
 * @param int   $actorId The acting user id.
 * @return array{ok: bool, message: string}
 */
function apply_order_transition(PDO $pdo, array $order, string $newStatus, int $actorId, array $extra = []): array
{
    $current = $order['status'];
    if (!order_transition_allowed($current, $newStatus)) {
        return ['ok' => false, 'message' => 'This action is not allowed for the current order status.'];
    }

    $isSeller = $actorId === (int)$order['seller_id'];
    $isBuyer  = $actorId === (int)$order['buyer_id'];
    if (!$isSeller && !$isBuyer) {
        return ['ok' => false, 'message' => 'You are not part of this order.'];
    }

    // Who may perform which step.
    $sellerSteps = ['accepted', 'rejected', 'ready', 'completed'];
    $buyerSteps  = ['cancelled'];
    if (in_array($newStatus, $sellerSteps, true) && !$isSeller) {
        return ['ok' => false, 'message' => 'Only the seller can perform this action.'];
    }
    if (in_array($newStatus, $buyerSteps, true) && !$isBuyer) {
        return ['ok' => false, 'message' => 'Only the buyer can perform this action.'];
    }
    if (($newStatus === 'cancelled') && $current !== 'pending') {
        return ['ok' => false, 'message' => 'This order can no longer be cancelled.'];
    }

    $pdo->beginTransaction();
    try {
        // Re-read the order inside the transaction for safety.
        $st = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
        $st->execute([(int)$order['id']]);
        $row = $st->fetch();
        if ($row === false) {
            throw new RuntimeException('Order not found.');
        }
        if ($row['status'] !== $current) {
            throw new RuntimeException('The order status changed since the page loaded. Please try again.');
        }

        $now = date('Y-m-d H:i:s');
        $updates = ['status = ?'];
        $params  = [$newStatus];

        if ($newStatus === 'completed') {
            $updates[] = 'completed_at = ?';
            $params[]  = $now;
            $updates[] = "payment_status = 'paid'";
        }
        if ($newStatus === 'cancelled') {
            $updates[] = 'cancelled_at = ?';
            $params[]  = $now;
        }
        if ($newStatus === 'ready') {
            $updates[] = 'pickup_location = ?';
            $params[]  = trim((string)($extra['pickup_location'] ?? '')) ?: null;
            $updates[] = 'pickup_time = ?';
            $params[]  = trim((string)($extra['pickup_time'] ?? '')) ?: null;
        }
        if ($newStatus === 'rejected' && !empty($extra['reason'])) {
            $updates[] = 'status_history = CONCAT(status_history, ?)';
            $params[]  = '| ' . mb_substr((string)$extra['reason'], 0, 300);
        }

        $params[] = (int)$order['id'];
        $pdo->prepare('UPDATE orders SET ' . implode(', ', $updates) . ', updated_at = NOW() WHERE id = ?')->execute($params);

        append_order_history($pdo, (int)$order['id'], $newStatus);

        // Side effects on the product.
        $productSt = $pdo->prepare('SELECT status, quantity FROM products WHERE id = ? FOR UPDATE');
        $productSt->execute([(int)$order['product_id']]);
        $productRow = $productSt->fetch();

        if ($newStatus === 'accepted' && $productRow !== false && $productRow['status'] === 'active') {
            $pdo->prepare("UPDATE products SET status = 'reserved' WHERE id = ?")->execute([(int)$order['product_id']]);
        }
        if ($newStatus === 'completed' && $productRow !== false && in_array($productRow['status'], ['active', 'reserved'], true)) {
            $pdo->prepare("UPDATE products SET status = 'sold' WHERE id = ?")->execute([(int)$order['product_id']]);
        }
        if (in_array($newStatus, ['rejected', 'cancelled'], true) && $productRow !== false) {
            // Give the quantity back so the listing can be bought again.
            $newQty = (int)$productRow['quantity'] + (int)$order['quantity'];
            $newStatusFlag = ($productRow['status'] === 'reserved' && $newQty > 0) ? "status = 'active', " : '';
            $pdo->prepare("UPDATE products SET $newStatusFlag quantity = ? WHERE id = ?")->execute([$newQty, (int)$order['product_id']]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'The order could not be updated.'];
    }

    audit_log('order_status_changed', 'order', (int)$order['id'], 'Order ' . $order['order_code'] . ' changed from ' . $current . ' to ' . $newStatus . '.', $actorId);

    // Notify the other party.
    $otherId = $isSeller ? (int)$order['buyer_id'] : (int)$order['seller_id'];
    $actorName = get_user($actorId)['full_name'] ?? 'User';
    $titleMap = [
        'accepted'  => 'Purchase request accepted',
        'rejected'  => 'Purchase request rejected',
        'cancelled' => 'Order cancelled',
        'ready'     => 'Order ready for pickup',
        'completed' => 'Order completed',
    ];
    $messageMap = [
        'accepted'  => $actorName . ' accepted your purchase request for "' . $order['product_title'] . '".',
        'rejected'  => $actorName . ' rejected your purchase request for "' . $order['product_title'] . '".',
        'cancelled' => $actorName . ' cancelled the order ' . $order['order_code'] . '.',
        'ready'     => 'Your order ' . $order['order_code'] . ' is ready for pickup. ' . $actorName . ' is waiting for you on campus.',
        'completed' => 'Your order ' . $order['order_code'] . ' was completed. Thank you for using CampusMart!',
    ];
    create_notification($otherId, 'order_' . $newStatus, $titleMap[$newStatus] ?? 'Order updated', $messageMap[$newStatus] ?? 'Your order was updated.', (int)$order['id']);

    return ['ok' => true, 'message' => 'Order updated successfully.'];
}

/* ------------------------------------------------------------------
 * Reviews
 * ------------------------------------------------------------------ */

function is_eligible_for_review(int $orderId, int $userId): bool
{
    $st = db()->prepare(
        "SELECT id FROM orders WHERE id = ? AND status = 'completed'
         AND (buyer_id = ? OR seller_id = ?)"
    );
    $st->execute([$orderId, $userId, $userId]);
    if (!$st->fetchColumn()) {
        return false;
    }
    $st = db()->prepare('SELECT 1 FROM reviews WHERE order_id = ? AND reviewer_id = ? LIMIT 1');
    $st->execute([$orderId, $userId]);
    return !(bool)$st->fetchColumn();
}

function rating_stars(float $rating): string
{
    $full = (int)floor($rating);
    $half = ($rating - $full) >= 0.5;
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $full) {
            $out .= '<span class="star filled">★</span>';
        } elseif ($i === $full + 1 && $half) {
            $out .= '<span class="star half">★</span>';
        } else {
            $out .= '<span class="star">★</span>';
        }
    }
    return $out;
}

/* ------------------------------------------------------------------
 * Reports
 * ------------------------------------------------------------------ */

function user_has_open_report(int $reporterId, ?int $reportedUserId, ?int $productId, string $reason): bool
{
    $sql = "SELECT 1 FROM reports WHERE reporter_id = ? AND reason = ? AND status IN ('pending','under_review')";
    $params = [$reporterId, $reason];
    if ($productId !== null) {
        $sql .= ' AND product_id = ?';
        $params[] = $productId;
    } elseif ($reportedUserId !== null) {
        $sql .= ' AND reported_user_id = ?';
        $params[] = $reportedUserId;
    }
    $st = db()->prepare($sql);
    $st->execute($params);
    return (bool)$st->fetchColumn();
}

/* ------------------------------------------------------------------
 * Conversations & messages
 * ------------------------------------------------------------------ */

function find_conversation(int $userA, int $userB, ?int $productId): ?int
{
    $sql = 'SELECT id FROM conversations WHERE user_a = ? AND user_b = ?';
    $params = [$userA, $userB];
    if ($productId !== null) {
        $sql .= ' AND product_id = ?';
        $params[] = $productId;
    } else {
        $sql .= ' AND product_id IS NULL';
    }
    $st = db()->prepare($sql);
    $st->execute($params);
    $id = $st->fetchColumn();
    return $id ? (int)$id : null;
}

function get_or_create_conversation(int $userA, int $userB, ?int $productId): int
{
    $a = min($userA, $userB);
    $b = max($userA, $userB);
    $existing = find_conversation($a, $b, $productId);
    if ($existing !== null) {
        return $existing;
    }
    $st = db()->prepare('INSERT INTO conversations (user_a, user_b, product_id) VALUES (?, ?, ?)');
    $st->execute([$a, $b, $productId]);
    return (int)db()->lastInsertId();
}

/* ------------------------------------------------------------------
 * Recommendations (lightweight scoring, NOT machine learning)
 * ------------------------------------------------------------------ */

function similar_products(array $product, int $limit = 4): array
{
    $priceMin = (float)$product['price'] * 0.7;
    $priceMax = (float)$product['price'] * 1.3;
    $st = db()->prepare(
        "SELECT p.*, c.name AS category_name, u.full_name AS seller_name,
                (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY is_primary DESC, id ASC LIMIT 1) AS image_path,
                (CASE
                    WHEN p.category_id = ? THEN 3 ELSE 0
                 END) +
                (CASE WHEN p.price BETWEEN ? AND ? THEN 2 ELSE 0 END) +
                (CASE WHEN p.condition_label = ? THEN 1 ELSE 0 END) +
                (CASE WHEN p.views >= 50 THEN 1 ELSE 0 END) AS score
         FROM products p
         JOIN categories c ON c.id = p.category_id
         JOIN users u ON u.id = p.seller_id
         WHERE p.status = 'active' AND u.status = 'active' AND p.id <> ?
           AND (p.category_id = ? OR p.price BETWEEN ? AND ? OR p.condition_label = ?)
         ORDER BY score DESC, p.views DESC
         LIMIT " . (int)$limit
    );
    $st->execute([
        $product['category_id'], $priceMin, $priceMax, $product['condition_label'],
        $product['id'], $product['category_id'], $priceMin, $priceMax, $product['condition_label'],
    ]);
    return $st->fetchAll();
}

function recommendations_for_user(int $userId, int $limit = 4): array
{
    // Build a simple profile from the user's favorites and previous orders.
    $fav = db()->prepare(
        'SELECT p.category_id, p.price FROM favorites f
         JOIN products p ON p.id = f.product_id
         WHERE f.user_id = ?
         ORDER BY f.created_at DESC LIMIT 6'
    );
    $fav->execute([$userId]);
    $profile = $fav->fetchAll();

    $ord = db()->prepare(
        "SELECT p.category_id, p.price FROM orders o
         JOIN products p ON p.id = o.product_id
         WHERE o.buyer_id = ? AND o.status = 'completed'
         ORDER BY o.completed_at DESC LIMIT 6"
    );
    $ord->execute([$userId]);
    $profile = array_merge($profile, $ord->fetchAll());

    if (empty($profile)) {
        // Fall back to popular active listings.
        return db()->query(
            "SELECT p.*, c.name AS category_name, u.full_name AS seller_name,
                    (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY is_primary DESC, id ASC LIMIT 1) AS image_path
             FROM products p
             JOIN categories c ON c.id = p.category_id
             JOIN users u ON u.id = p.seller_id
             WHERE p.status = 'active' AND u.status = 'active'
             ORDER BY p.views DESC LIMIT " . (int)$limit
        )->fetchAll();
    }

    $catWeights = [];
    $avgPrice = 0.0;
    foreach ($profile as $row) {
        $catWeights[(int)$row['category_id']] = ($catWeights[(int)$row['category_id']] ?? 0) + 1;
        $avgPrice += (float)$row['price'];
    }
    $avgPrice /= count($profile);
    arsort($catWeights);
    $topCat = array_key_first($catWeights);

    $st = db()->prepare(
        "SELECT p.*, c.name AS category_name, u.full_name AS seller_name,
                (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY is_primary DESC, id ASC LIMIT 1) AS image_path,
                (CASE WHEN p.category_id = ? THEN 3 ELSE 0 END) +
                (CASE WHEN p.price BETWEEN ? AND ? THEN 2 ELSE 0 END) +
                (CASE WHEN p.views >= 50 THEN 1 ELSE 0 END) AS score
         FROM products p
         JOIN categories c ON c.id = p.category_id
         JOIN users u ON u.id = p.seller_id
         WHERE p.status = 'active' AND u.status = 'active'
         ORDER BY score DESC, p.views DESC
         LIMIT " . (int)$limit
    );
    $st->execute([$topCat, $avgPrice * 0.7, $avgPrice * 1.3]);
    return $st->fetchAll();
}

/* ------------------------------------------------------------------
 * Pagination
 * ------------------------------------------------------------------ */

function pagination(int $totalItems, int $currentPage, int $perPage, string $baseUrl): string
{
    $totalPages = (int)ceil($totalItems / $perPage);
    if ($totalPages <= 1) {
        return '';
    }
    $sep = strpos($baseUrl, '?') !== false ? '&' : '?';
    $html = '<nav class="pagination" aria-label="Pagination">';
    $html .= '<span class="page-info">Page ' . $currentPage . ' of ' . $totalPages . '</span>';
    $html .= '<a class="page-btn" href="' . e($baseUrl . $sep . 'page=1') . '">« First</a>';
    if ($currentPage > 1) {
        $html .= '<a class="page-btn" href="' . e($baseUrl . $sep . 'page=' . ($currentPage - 1)) . '">‹ Prev</a>';
    }
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $currentPage ? ' active' : '';
        $html .= '<a class="page-btn' . $active . '" href="' . e($baseUrl . $sep . 'page=' . $i) . '">' . $i . '</a>';
    }
    if ($currentPage < $totalPages) {
        $html .= '<a class="page-btn" href="' . e($baseUrl . $sep . 'page=' . ($currentPage + 1)) . '">Next ›</a>';
    }
    $html .= '<a class="page-btn" href="' . e($baseUrl . $sep . 'page=' . $totalPages) . '">Last »</a>';
    $html .= '</nav>';
    return $html;
}

function escape_like(string $value): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
}

function escape_regex(string $value): string
{
    return preg_quote($value, '/');
}

/* ------------------------------------------------------------------
 * Listing expiration
 * ------------------------------------------------------------------ */

/**
 * Marks overdue listings as expired and warns sellers whose listings expire soon.
 * Throttled so it does not run on every single request.
 */
function run_expiry_check(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $last = (int)(settings('_last_expiry_check') ?: 0);
        if (time() - $last < 300) {
            return;
        }
        save_setting('_last_expiry_check', (string)time());

        $pdo = db();
        $now = date('Y-m-d H:i:s');

        // 1) Auto-expire listings whose expiry date has passed.
        $st = $pdo->prepare("UPDATE products SET status = 'expired' WHERE status IN ('active','approved') AND expires_at < ?");
        $st->execute([$now]);

        // 2) Notify sellers 3 days before expiry, once per listing.
        $warnFrom = date('Y-m-d H:i:s', time() + 3 * 86400);
        $warnTo   = date('Y-m-d H:i:s', time() + 3 * 86400 + 300);
        $st = $pdo->prepare(
            "SELECT p.id, p.title, p.seller_id FROM products p
             WHERE p.status = 'active' AND p.expires_at BETWEEN ? AND ?
               AND NOT EXISTS (
                   SELECT 1 FROM notifications n
                   WHERE n.type = 'expiry_warning' AND n.related_id = p.id AND n.user_id = p.seller_id
               )"
        );
        $st->execute([$warnFrom, $warnTo]);
        foreach ($st->fetchAll() as $row) {
            create_notification(
                (int)$row['seller_id'],
                'expiry_warning',
                'Listing about to expire',
                'Your listing "' . $row['title'] . '" will expire soon. Renew it to keep it active.',
                (int)$row['id']
            );
        }
    } catch (Throwable) {
        // Never let maintenance logic break a page.
    }
}

/* ------------------------------------------------------------------
 * Product views (throttled)
 * ------------------------------------------------------------------ */

function track_product_view(int $productId, ?int $userId): void
{
    // Skip repeated refreshes from the same session for the same product.
    $viewed = $_SESSION['viewed_products'] ?? [];
    if (in_array($productId, $viewed, true)) {
        return;
    }
    $viewed[] = $productId;
    $_SESSION['viewed_products'] = array_slice($viewed, -30);
    try {
        $ipHash = md5($_SERVER['REMOTE_ADDR'] ?? '');
        $pdo = db();
        $st = $pdo->prepare('INSERT INTO product_views (product_id, user_id, ip_hash) VALUES (?, ?, ?)');
        $st->execute([$productId, $userId, $ipHash]);
        $st = $pdo->prepare('UPDATE products SET views = views + 1 WHERE id = ?');
        $st->execute([$productId]);
    } catch (Throwable) {
        // View tracking is best-effort.
    }
}

/* ------------------------------------------------------------------
 * Image uploads
 * ------------------------------------------------------------------ */

/**
 * Validates and stores uploaded images for a listing.
 *
 * @param array $files The $_FILES['images'] array (multi-upload input).
 * @param int   $maxCount Maximum number of files to accept.
 * @return array{paths: array, errors: array} Saved relative paths + per-file errors.
 */
function upload_product_images(array $files, int $maxCount): array
{
    $paths  = [];
    $errors = [];

    if (empty($files['name']) || !is_array($files['name'])) {
        return ['paths' => $paths, 'errors' => $errors];
    }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    $names = $files['name'];
    $count = min(count($names), $maxCount);
    for ($i = 0; $i < $count; $i++) {
        $tmpName = $files['tmp_name'][$i] ?? '';
        $error   = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            $errors[] = 'File "' . e($names[$i]) . '" could not be uploaded (error code ' . $error . ').';
            continue;
        }

        // 1) File size limit.
        if ($files['size'][$i] > MAX_UPLOAD_SIZE) {
            $errors[] = 'File "' . e($names[$i]) . '" is larger than ' . (int)(MAX_UPLOAD_SIZE / 1024 / 1024) . ' MB.';
            continue;
        }

        // 2) Validate the real MIME type (never trust the browser or extension).
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmpName);
        if (!in_array($mime, $allowedMime, true)) {
            $errors[] = 'File "' . e($names[$i]) . '" is not a valid image (' . e($mime) . ').';
            continue;
        }

        // 3) Validate dimensions with GD-independent checks.
        $size = @getimagesize($tmpName);
        if ($size === false || $size[0] < 50 || $size[1] < 50) {
            $errors[] = 'File "' . e($names[$i]) . '" is too small or is not a readable image.';
            continue;
        }
        if ($size[0] > 6000 || $size[1] > 6000) {
            $errors[] = 'File "' . e($names[$i]) . '" is too large in dimensions.';
            continue;
        }

        // 4) Extension sanity check.
        $ext = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            $errors[] = 'File "' . e($names[$i]) . '" has a disallowed extension.';
            continue;
        }

        // 5) Store with a random server-generated name.
        $destName = bin2hex(random_bytes(16)) . '.jpg';
        $destPath = UPLOAD_DIR . '/' . $destName;
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0775, true);
        }

        // 6) Re-encode / resize with GD when available, otherwise move the validated file.
        $saved = resize_and_save_image($tmpName, $destPath, $mime);
        if ($saved) {
            $paths[] = 'assets/images/uploads/' . $destName;
        } else {
            $errors[] = 'File "' . e($names[$i]) . '" could not be processed.';
        }
    }

    return ['paths' => $paths, 'errors' => $errors];
}

/**
 * Re-encodes an image as a bounded JPEG. Falls back to a plain move if GD is missing.
 */
function resize_and_save_image(string $source, string $destPath, string $mime): bool
{
    if (function_exists('imagecreatetruecolor')) {
        $img = null;
        switch ($mime) {
            case 'image/jpeg': $img = @imagecreatefromjpeg($source); break;
            case 'image/png':  $img = @imagecreatefrompng($source); break;
            case 'image/webp': $img = @imagecreatefromwebp($source); break;
            case 'image/gif':  $img = @imagecreatefromgif($source); break;
        }
        if ($img !== null) {
            $w = imagesx($img);
            $h = imagesy($img);
            $max = MAX_IMAGE_DIMENSION;
            if ($w > $max || $h > $max) {
                $ratio = min($max / $w, $max / $h);
                $nw = (int)round($w * $ratio);
                $nh = (int)round($h * $ratio);
                $resized = imagecreatetruecolor($nw, $nh);
                imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagedestroy($img);
                $img = $resized;
            }
            $ok = imagejpeg($img, $destPath, 82);
            imagedestroy($img);
            return $ok;
        }
    }
    // Fallback: safe move of the already-validated image file.
    return @move_uploaded_file($source, $destPath);
}

/**
 * Safely deletes a stored image file (only inside the uploads directory).
 */
function delete_image_file(?string $path): void
{
    if ($path === null || $path === '') {
        return;
    }
    $full = BASE_PATH . '/' . ltrim($path, '/');
    $real = realpath($full);
    $uploadReal = realpath(UPLOAD_DIR);
    if ($real !== false && $uploadReal !== false && str_starts_with($real, $uploadReal . DIRECTORY_SEPARATOR)) {
        @unlink($real);
    }
}

/**
 * Deletes image files that belong to a product (used when a listing is deleted).
 */
function delete_product_image_files(int $productId): void
{
    $st = db()->prepare('SELECT image_path FROM product_images WHERE product_id = ?');
    $st->execute([$productId]);
    foreach ($st->fetchAll() as $row) {
        delete_image_file($row['image_path']);
    }
}

/**
 * Validates and stores a single avatar/profile image. Returns relative path or null.
 */
function upload_avatar(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return null;
    }
    if ((int)$file['size'] > MAX_UPLOAD_SIZE) {
        return null;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
        return null;
    }
    $size = @getimagesize($file['tmp_name']);
    if ($size === false || $size[0] < 50 || $size[1] < 50) {
        return null;
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }
    $destPath = UPLOAD_DIR . '/avatar_' . bin2hex(random_bytes(12)) . '.jpg';
    if (resize_and_save_image($file['tmp_name'], $destPath, $mime)) {
        return 'assets/images/uploads/' . basename($destPath);
    }
    return null;
}

/**
 * Category card visuals (background photo + emoji).
 * Falls back to the generic "other" image for unknown categories.
 *
 * @return array{0: string, 1: string} [image filename, emoji]
 */
function category_visual(string $name): array
{
    static $map = [
        'books'              => ['books.jpg', '📚'],
        'electronics'        => ['electronics.jpg', '🎧'],
        'computers'          => ['computers.jpg', '💻'],
        'mobile accessories' => ['mobile-accessories.jpg', '📱'],
        'calculators'        => ['calculators.jpg', '🧮'],
        'lab equipment'      => ['lab-equipment.jpg', '🔬'],
        'bicycles'           => ['bicycles.jpg', '🚲'],
        'furniture'          => ['furniture.jpg', '🛋️'],
        'clothing'           => ['clothing.jpg', '👕'],
        'sports'             => ['sports.jpg', '🏃'],
        'other'              => ['other.jpg', '🛍️'],
    ];
    return $map[strtolower(trim($name))] ?? $map['other'];
}
