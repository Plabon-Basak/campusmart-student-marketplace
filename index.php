<?php
/**
 * Homepage - hero, popular categories, recent & featured listings,
 * trending products, how it works, safety, call-to-action.
 */
require_once __DIR__ . '/includes/init.php';

$pdo = db();

$activeWhere = "p.status = 'active' AND u.status = 'active'";

$recent = $pdo->query(
    "SELECT p.*, c.name AS category_name, u.full_name AS seller_name,
            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.id ASC LIMIT 1) AS image_path
     FROM products p
     JOIN categories c ON c.id = p.category_id
     JOIN users u ON u.id = p.seller_id
     WHERE $activeWhere
     ORDER BY p.created_at DESC
     LIMIT 8"
)->fetchAll();

$featured = $pdo->query(
    "SELECT p.*, c.name AS category_name, u.full_name AS seller_name,
            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.id ASC LIMIT 1) AS image_path
     FROM products p
     JOIN categories c ON c.id = p.category_id
     JOIN users u ON u.id = p.seller_id
     WHERE $activeWhere AND p.views >= 100
     ORDER BY p.views DESC
     LIMIT 4"
)->fetchAll();

$trending = $pdo->query(
    "SELECT p.*, c.name AS category_name, u.full_name AS seller_name,
            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.id ASC LIMIT 1) AS image_path
     FROM products p
     JOIN categories c ON c.id = p.category_id
     JOIN users u ON u.id = p.seller_id
     WHERE $activeWhere
     ORDER BY p.views DESC
     LIMIT 4"
)->fetchAll();

$popularCats = $pdo->query(
    "SELECT c.*, COUNT(p.id) AS product_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id AND p.status = 'active'
     WHERE c.status = 'active'
     GROUP BY c.id
     ORDER BY product_count DESC, c.name ASC
     LIMIT 8"
)->fetchAll();

$totalListings = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$completedOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'")->fetchColumn();

$loggedIn = current_user();
$pageTitle = 'Buy. Sell. Connect. Within Your Campus.';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="container">
    <div class="hero-content">
      <h1>Buy &amp; Sell Within Your Campus</h1>
      <p class="lead">CampusMart is a safe marketplace for university students to buy and sell textbooks, electronics, furniture, bikes and everything else you need — all from fellow students on your campus.</p>

      <form class="hero-search" action="<?= e(APP_BASE_URL . '/products.php') ?>" method="get" role="search">
        <div class="search-input-wrap">
          <input type="search" name="q" placeholder="Search textbooks, calculators, laptops…" aria-label="Search products" data-search-suggest autocomplete="off">
        </div>
        <button class="btn btn-primary" type="submit">Search</button>
      </form>

      <div class="hero-ctas">
        <a class="btn btn-light btn-lg" href="<?= e(APP_BASE_URL . '/products.php') ?>">Browse Products</a>
        <a class="btn btn-outline btn-lg" href="<?= e(APP_BASE_URL . ($loggedIn ? '/user/add-listing.php' : '/register.php')) ?>" style="border-color:#c7d2fe; color:#e0e7ff;">Sell an Item</a>
      </div>

      <div class="hero-stats">
        <div><div class="stat-value"><?= number_format($totalListings) ?></div><div class="stat-label">Active Listings</div></div>
        <div><div class="stat-value"><?= number_format($totalUsers) ?>+</div><div class="stat-label">Student Sellers</div></div>
        <div><div class="stat-value"><?= number_format($completedOrders) ?></div><div class="stat-label">Completed Deals</div></div>
      </div>
    </div>
  </div>
</section>

<?php if ($popularCats): ?>
<section class="section">
  <div class="container">
    <div class="section-head">
      <div>
        <h2>Popular Categories</h2>
        <p>Find what you need across campus</p>
      </div>
      <a class="btn btn-ghost" href="<?= e(APP_BASE_URL . '/categories.php') ?>">View all categories →</a>
    </div>
    <div class="feature-grid">
      <?php foreach ($popularCats as $cat): [$catImg, $catEmoji] = category_visual($cat['name']); ?>
        <a class="card feature cat-card" href="<?= e(APP_BASE_URL . '/products.php?category=' . (int)$cat['id']) ?>">
          <img class="cat-img" src="<?= e(APP_BASE_URL . '/assets/images/categories/' . $catImg) ?>" alt="<?= e($cat['name']) ?>" loading="lazy">
          <div class="cat-body">
            <div class="cat-icon" aria-hidden="true"><?= $catEmoji ?></div>
            <h3><?= e($cat['name']) ?></h3>
            <p><?= (int)$cat['product_count'] ?> active listing<?= (int)$cat['product_count'] === 1 ? '' : 's' ?></p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($recent): ?>
<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="section-head">
      <div>
        <h2>Recently Added</h2>
        <p>The latest items posted by students</p>
      </div>
      <a class="btn btn-ghost" href="<?= e(APP_BASE_URL . '/products.php') ?>">Browse all →</a>
    </div>
    <div class="product-grid">
      <?php foreach ($recent as $product): require __DIR__ . '/includes/product-card.php'; endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($featured): ?>
<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="section-head">
      <div>
        <h2>Featured Listings</h2>
        <p>Popular items students are checking out</p>
      </div>
    </div>
    <div class="product-grid">
      <?php foreach ($featured as $product): require __DIR__ . '/includes/product-card.php'; endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($trending): ?>
<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="section-head">
      <div>
        <h2>Trending Products</h2>
        <p>Most viewed right now</p>
      </div>
    </div>
    <div class="product-grid">
      <?php foreach ($trending as $product): require __DIR__ . '/includes/product-card.php'; endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="section-head">
      <div>
        <h2>How CampusMart Works</h2>
        <p>Three simple steps to a great deal</p>
      </div>
    </div>
    <div class="steps">
      <div class="card step">
        <div class="step-num">1</div>
        <h3>Find an item</h3>
        <p>Browse or search listings from students across campus. Filter by category, price and condition.</p>
      </div>
      <div class="card step">
        <div class="step-num">2</div>
        <h3>Contact or request purchase</h3>
        <p>Message the seller privately or send a purchase request. No need to share your phone number right away.</p>
      </div>
      <div class="card step">
        <div class="step-num">3</div>
        <h3>Meet on campus &amp; complete</h3>
        <p>Agree on a pickup spot on campus, inspect the item, pay in person and complete the transaction safely.</p>
      </div>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="section-head">
      <div>
        <h2>Safety Guidelines</h2>
        <p>Keep every deal safe and simple</p>
      </div>
      <a class="btn btn-ghost" href="<?= e(APP_BASE_URL . '/safety.php') ?>">Full safety guide →</a>
    </div>
    <div class="safety-list">
      <div class="card safety-item">
        <span class="num">✓</span>
        <div><h3>Meet in public campus locations</h3><p>Arrange pickups in busy, well-lit public areas such as cafeterias, halls or the library.</p></div>
      </div>
      <div class="card safety-item">
        <span class="num">✓</span>
        <div><h3>Verify the item before paying</h3><p>Inspect the product carefully and only pay once you are satisfied it matches the listing.</p></div>
      </div>
      <div class="card safety-item">
        <span class="num">✓</span>
        <div><h3>Report suspicious listings</h3><p>If something looks too good to be true, report it so our moderators can review it.</p></div>
      </div>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="card" style="padding:40px; text-align:center; background:linear-gradient(135deg,var(--color-primary),#7c3aed); color:#fff; border:0;">
      <h2 style="font-size:1.7rem; margin-bottom:8px;">Ready to declutter or save money?</h2>
      <p style="color:#e0e7ff; margin-bottom:22px;">Join hundreds of students buying and selling within your campus today.</p>
      <div class="flex" style="justify-content:center; gap:12px; flex-wrap:wrap;">
        <a class="btn btn-light btn-lg" href="<?= e(APP_BASE_URL . ($loggedIn ? '/user/add-listing.php' : '/register.php')) ?>">Start Selling</a>
        <a class="btn btn-lg" href="<?= e(APP_BASE_URL . '/products.php') ?>" style="background:#fff; color:var(--color-primary);">Browse Marketplace</a>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
