<?php
/**
 * Reusable product card.
 * Expects $product with: id, title, price, condition_label, category_name,
 * location, seller_name, status, image_path (primary image path or null).
 * Optional: $favorited (bool), $showSeller (bool)
 */
$p = $product;
$img = image_url($p['image_path'] ?? null);
$isFav = $favorited ?? favorite_exists(current_user_id(), (int)$p['id']);
$showSeller = $showSeller ?? false;
?>
<article class="card product-card">
  <?php if (($p['status'] ?? 'active') !== 'active'): ?>
    <span class="status-ribbon badge badge-<?= e(product_status_class($p['status'])) ?>"><?= e(product_status_label($p['status'])) ?></span>
  <?php endif; ?>
  <button type="button" class="fav-btn <?= $isFav ? 'active' : '' ?>" data-fav="<?= (int)$p['id'] ?>"
          title="<?= $isFav ? 'Remove from favorites' : 'Add to favorites' ?>"
          aria-label="<?= $isFav ? 'Remove from favorites' : 'Add to favorites' ?>">&#9825;</button>
  <a class="card-media" href="<?= e(APP_BASE_URL . '/product-details.php?id=' . (int)$p['id']) ?>">
    <img src="<?= e($img) ?>" alt="<?= e($p['title']) ?>" loading="lazy">
  </a>
  <div class="card-body">
    <a class="card-title" href="<?= e(APP_BASE_URL . '/product-details.php?id=' . (int)$p['id']) ?>"><?= e($p['title']) ?></a>
    <div class="card-price"><?= e(format_price($p['price'])) ?></div>
    <div class="card-meta">
      <span><?= e($p['condition_label'] ?? '') ?></span>
      <span>·</span>
      <span><?= e($p['category_name'] ?? '') ?></span>
      <?php if (!empty($p['location'])): ?>
        <span>·</span>
        <span title="Pickup location"><?= e($p['location']) ?></span>
      <?php endif; ?>
    </div>
    <div class="card-foot">
      <span><?= $showSeller && !empty($p['seller_name']) ? e($p['seller_name']) : e(format_price($p['price'])) ?></span>
      <?php if ($showSeller && !empty($p['seller_name'])): ?>
        <span class="text-muted"><?= e($p['views'] ?? 0) ?> views</span>
      <?php endif; ?>
      <a class="btn btn-outline btn-sm" href="<?= e(APP_BASE_URL . '/product-details.php?id=' . (int)$p['id']) ?>">View</a>
    </div>
  </div>
</article>
