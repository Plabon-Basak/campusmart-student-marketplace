<?php
/**
 * Marketplace browse page: search, filters, sorting and pagination.
 * All SQL values are bound parameters; the ORDER BY field is whitelisted.
 */
require_once __DIR__ . '/includes/init.php';

$pdo = db();

$q          = trim((string)($_GET['q'] ?? ''));
$categoryId = (int)($_GET['category'] ?? 0);
$minPrice   = trim((string)($_GET['min_price'] ?? ''));
$maxPrice   = trim((string)($_GET['max_price'] ?? ''));
$condition  = trim((string)($_GET['condition'] ?? ''));
$location   = trim((string)($_GET['location'] ?? ''));
$availability = trim((string)($_GET['availability'] ?? 'available'));
$dateRange  = trim((string)($_GET['date'] ?? ''));
$sort       = trim((string)($_GET['sort'] ?? 'newest'));
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 12;

// ---------- Whitelisted sort ----------
$sortMap = [
    'newest'    => 'p.created_at DESC',
    'oldest'    => 'p.created_at ASC',
    'price_low' => 'p.price ASC',
    'price_high'=> 'p.price DESC',
    'viewed'    => 'p.views DESC',
    'rated'     => 'seller_rating DESC',
];
$orderBy = $sortMap[$sort] ?? $sortMap['newest'];

// ---------- Build WHERE clauses (always bound) ----------
$where = ["u.status = 'active'"];
$params = [];

$statusMap = [
    'available' => "p.status = 'active'",
    'reserved'  => "p.status = 'reserved'",
    'sold'      => "p.status = 'sold'",
    'all'       => "p.status IN ('active','reserved','sold')",
];
$where[] = $statusMap[$availability] ?? $statusMap['available'];

if ($q !== '') {
    $terms = preg_split('/\s+/', $q);
    foreach ($terms as $term) {
        $pattern = '\b' . escape_regex($term) . '\b';
        $where[] = '(p.title REGEXP ? OR p.description REGEXP ? OR u.full_name REGEXP ? OR c.name REGEXP ?)';
        $params[] = $pattern;
        $params[] = $pattern;
        $params[] = $pattern;
        $params[] = $pattern;
    }
}
if ($categoryId > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $categoryId;
}
if ($minPrice !== '' && is_numeric($minPrice)) {
    $where[] = 'p.price >= ?';
    $params[] = (float)$minPrice;
}
if ($maxPrice !== '' && is_numeric($maxPrice)) {
    $where[] = 'p.price <= ?';
    $params[] = (float)$maxPrice;
}
if ($condition !== '' && in_array($condition, condition_options(), true)) {
    $where[] = 'p.condition_label = ?';
    $params[] = $condition;
}
if ($location !== '') {
    $where[] = 'p.location = ?';
    $params[] = $location;
}
if ($dateRange !== '') {
    $days = ['7' => 7, '30' => 30, '90' => 90];
    if (isset($days[$dateRange])) {
        $where[] = 'p.created_at >= ?';
        $params[] = date('Y-m-d H:i:s', time() - $days[$dateRange] * 86400);
    }
}

$whereSql = implode(' AND ', $where);

// ---------- Count ----------
$countSt = $pdo->prepare("SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id JOIN users u ON u.id = p.seller_id WHERE $whereSql");
$countSt->execute($params);
$total = (int)$countSt->fetchColumn();

// ---------- Data ----------
$sql = "SELECT p.*, c.name AS category_name, u.full_name AS seller_name,
               (SELECT pi.image_path FROM product_images pi
                WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.id ASC LIMIT 1) AS image_path,
               COALESCE((SELECT AVG(r.rating) FROM reviews r
                         WHERE r.reviewed_user_id = u.id AND r.status = 'approved'), 0) AS seller_rating
        FROM products p
        JOIN categories c ON c.id = p.category_id
        JOIN users u ON u.id = p.seller_id
        WHERE $whereSql
        ORDER BY $orderBy
        LIMIT $perPage OFFSET " . (($page - 1) * $perPage);

$st = $pdo->prepare($sql);
$st->execute($params);
$products = $st->fetchAll();

$categories = get_categories(true);
$locations = $pdo->query("SELECT DISTINCT location FROM products WHERE location <> '' ORDER BY location")->fetchAll(PDO::FETCH_COLUMN);

// ---------- Rebuild query string for pagination ----------
$baseQuery = $_GET;
unset($baseQuery['page']);
$baseUrl = 'products.php?' . http_build_query($baseQuery);

$pageTitle = $q !== '' ? 'Search: ' . $q : 'Browse Marketplace';
require __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top:26px;">
  <div class="sort-bar">
    <div class="result-count">
      <?= number_format($total) ?> listing<?= $total === 1 ? '' : 's' ?> found
      <?php if ($q !== ''): ?>for "<strong><?= e($q) ?></strong>"<?php endif; ?>
    </div>
    <form method="get" action="products.php" class="flex" style="flex-wrap:wrap;">
      <?php foreach (['q','category','min_price','max_price','condition','location','availability','date'] as $keep): ?>
        <?php if (isset($_GET[$keep]) && $keep !== 'sort'): ?>
          <input type="hidden" name="<?= e($keep) ?>" value="<?= e($_GET[$keep]) ?>">
        <?php endif; ?>
      <?php endforeach; ?>
      <label class="text-small text-muted" for="sort">Sort by</label>
      <select class="form-control" id="sort" name="sort" onchange="this.form.submit()">
        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
        <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: low to high</option>
        <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: high to low</option>
        <option value="viewed" <?= $sort === 'viewed' ? 'selected' : '' ?>>Most viewed</option>
        <option value="rated" <?= $sort === 'rated' ? 'selected' : '' ?>>Highest rated sellers</option>
      </select>
    </form>
  </div>

  <div class="browse-layout">
    <aside class="card filter-panel">
      <h3>Filters</h3>
      <form method="get" action="products.php">
        <?php foreach (['q','sort'] as $keep): ?>
          <?php if (isset($_GET[$keep]) && $keep !== 'category'): ?>
            <input type="hidden" name="<?= e($keep) ?>" value="<?= e($_GET[$keep]) ?>">
          <?php endif; ?>
        <?php endforeach; ?>
        <input type="hidden" name="category" value="<?= (int)$categoryId ?>">

        <div class="filter-group">
          <span class="filter-label">Search</span>
          <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Keywords…">
        </div>

        <div class="filter-group">
          <span class="filter-label">Category</span>
          <select class="form-control" name="category">
            <option value="0">All categories</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= (int)$cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-group">
          <span class="filter-label">Price range</span>
          <div class="range-row">
            <input class="form-control" type="number" min="0" name="min_price" placeholder="Min" value="<?= e($minPrice) ?>">
            <span>–</span>
            <input class="form-control" type="number" min="0" name="max_price" placeholder="Max" value="<?= e($maxPrice) ?>">
          </div>
        </div>

        <div class="filter-group">
          <span class="filter-label">Condition</span>
          <select class="form-control" name="condition">
            <option value="">Any condition</option>
            <?php foreach (condition_options() as $cond): ?>
              <option value="<?= e($cond) ?>" <?= $condition === $cond ? 'selected' : '' ?>><?= e($cond) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-group">
          <span class="filter-label">Campus location</span>
          <select class="form-control" name="location">
            <option value="">Any location</option>
            <?php foreach ($locations as $loc): ?>
              <option value="<?= e($loc) ?>" <?= $location === $loc ? 'selected' : '' ?>><?= e($loc) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-group">
          <span class="filter-label">Availability</span>
          <select class="form-control" name="availability">
            <option value="available" <?= $availability === 'available' ? 'selected' : '' ?>>Available</option>
            <option value="reserved" <?= $availability === 'reserved' ? 'selected' : '' ?>>Reserved</option>
            <option value="sold" <?= $availability === 'sold' ? 'selected' : '' ?>>Sold</option>
            <option value="all" <?= $availability === 'all' ? 'selected' : '' ?>>All statuses</option>
          </select>
        </div>

        <div class="filter-group">
          <span class="filter-label">Listing date</span>
          <select class="form-control" name="date">
            <option value="">Any time</option>
            <option value="7" <?= $dateRange === '7' ? 'selected' : '' ?>>Last 7 days</option>
            <option value="30" <?= $dateRange === '30' ? 'selected' : '' ?>>Last 30 days</option>
            <option value="90" <?= $dateRange === '90' ? 'selected' : '' ?>>Last 90 days</option>
          </select>
        </div>

        <button class="btn btn-primary btn-block" type="submit">Apply Filters</button>
        <a class="btn btn-ghost btn-block mt-1" href="products.php">Clear all</a>
      </form>
    </aside>

    <section>
      <?php if (empty($products)): ?>
        <div class="card empty-state">
          <div class="empty-icon">🔍</div>
          <h3>No listings found</h3>
          <p>Try adjusting your search or filters, or check back later.</p>
          <a class="btn btn-primary" href="products.php">Clear filters</a>
        </div>
      <?php else: ?>
        <div class="product-grid">
          <?php foreach ($products as $product): require __DIR__ . '/includes/product-card.php'; endforeach; ?>
        </div>
        <?= pagination($total, $page, $perPage, $baseUrl) ?>
      <?php endif; ?>
    </section>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
