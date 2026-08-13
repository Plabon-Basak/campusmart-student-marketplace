<?php
/**
 * Category listing page.
 */
require_once __DIR__ . '/includes/init.php';

$pdo = db();
$categories = $pdo->query(
    "SELECT c.*, COUNT(p.id) AS product_count,
            (SELECT COUNT(*) FROM products p2 WHERE p2.category_id = c.id AND p2.status = 'active') AS active_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id AND p.status IN ('active','reserved','sold')
     WHERE c.status = 'active'
     GROUP BY c.id
     ORDER BY active_count DESC, c.name ASC"
)->fetchAll();

$pageTitle = 'Categories';
require __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top:30px; padding-bottom:10px;">
  <div class="section-head">
    <div>
      <h2>Browse by Category</h2>
      <p>Everything students sell and buy on campus, organised.</p>
    </div>
  </div>

  <?php if (empty($categories)): ?>
    <div class="card empty-state">
      <div class="empty-icon">📦</div>
      <h3>No categories yet</h3>
      <p>Categories will appear here once they are published.</p>
    </div>
  <?php else: ?>
    <div class="feature-grid">
      <?php foreach ($categories as $cat): [$catImg, $catEmoji] = category_visual($cat['name']); ?>
        <a class="card feature cat-card" href="<?= e(APP_BASE_URL . '/products.php?category=' . (int)$cat['id']) ?>">
          <img class="cat-img" src="<?= e(APP_BASE_URL . '/assets/images/categories/' . $catImg) ?>" alt="<?= e($cat['name']) ?>" loading="lazy">
          <div class="cat-body">
            <div class="cat-icon" aria-hidden="true"><?= $catEmoji ?></div>
            <h3><?= e($cat['name']) ?></h3>
            <p>
              <?= (int)$cat['active_count'] ?> available
              <?php if ((int)$cat['product_count'] !== (int)$cat['active_count']): ?>
                · <?= (int)$cat['product_count'] ?> total
              <?php endif; ?>
            </p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
