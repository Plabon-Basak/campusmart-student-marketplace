<?php
/**
 * Edit an existing listing (owner only). Handles updates, image
 * replacement/removal, and listing deletion.
 */
require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$product = $id > 0 ? get_product($id) : null;

if ($product === null || (int)$product['seller_id'] !== current_user_id()) {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

$images = product_images($id);
$maxImages = (int)(settings('max_listing_images') ?: 5);

// ---------- POST actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_listing') {
        delete_product_image_files($id);
        $pdo->prepare("UPDATE products SET status = 'removed' WHERE id = ?")->execute([$id]);
        audit_log('listing_deleted', 'product', $id, 'Listing "' . $product['title'] . '" removed by seller.');
        set_flash('success', 'Your listing has been removed.');
        redirect('listings.php');
    }

    if ($action === 'update') {
        $errors = [];
        $fields = [
            'title' => trim((string)($_POST['title'] ?? '')),
            'description' => trim((string)($_POST['description'] ?? '')),
            'price' => trim((string)($_POST['price'] ?? '')),
            'condition_label' => (string)($_POST['condition_label'] ?? ''),
            'quantity' => (string)($_POST['quantity'] ?? ''),
            'location' => trim((string)($_POST['location'] ?? '')),
            'contact_preference' => (string)($_POST['contact_preference'] ?? ''),
        ];
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $category = get_category($categoryId);

        if (mb_strlen($fields['title']) < 4 || mb_strlen($fields['title']) > 200) {
            $errors['title'] = 'Title must be between 4 and 200 characters.';
        }
        if (mb_strlen($fields['description']) < 20) {
            $errors['description'] = 'Description must be at least 20 characters.';
        }
        if ($category === null || $category['status'] !== 'active') {
            $errors['category_id'] = 'Please choose a valid category.';
        }
        if (!is_numeric($fields['price']) || (float)$fields['price'] < 0) {
            $errors['price'] = 'Please enter a valid price.';
        }
        if (!in_array($fields['condition_label'], condition_options(), true)) {
            $errors['condition_label'] = 'Invalid condition.';
        }
        if (!ctype_digit($fields['quantity']) || (int)$fields['quantity'] < 1 || (int)$fields['quantity'] > 50) {
            $errors['quantity'] = 'Quantity must be between 1 and 50.';
        }
        if (mb_strlen($fields['location']) < 2) {
            $errors['location'] = 'Please enter a pickup location.';
        }
        if (!in_array($fields['contact_preference'], ['In-app message', 'Phone', 'Both'], true)) {
            $errors['contact_preference'] = 'Invalid contact preference.';
        }

        if (empty($errors)) {
            $st = $pdo->prepare(
                'UPDATE products SET title = ?, description = ?, price = ?, condition_label = ?, quantity = ?,
                        location = ?, contact_preference = ?, category_id = ? WHERE id = ? AND seller_id = ?'
            );
            $st->execute([
                $fields['title'], $fields['description'], (float)$fields['price'],
                $fields['condition_label'], (int)$fields['quantity'],
                $fields['location'], $fields['contact_preference'], $categoryId,
                $id, current_user_id(),
            ]);

            // Remove flagged images.
            $removeIds = trim((string)($_POST['remove_images'] ?? ''));
            $removedCount = 0;
            if ($removeIds !== '') {
                foreach (explode(',', $removeIds) as $ridRaw) {
                    $rid = (int)$ridRaw;
                    if ($rid < 1) continue;
                    $st = $pdo->prepare('SELECT image_path FROM product_images WHERE id = ? AND product_id = ?');
                    $st->execute([$rid, $id]);
                    $path = $st->fetchColumn();
                    if ($path) {
                        delete_image_file($path);
                        $pdo->prepare('DELETE FROM product_images WHERE id = ?')->execute([$rid]);
                        $removedCount++;
                    }
                }
            }

            // Add new images.
            if (!empty($_FILES['images']['name'][0])) {
                $remainingSlots = $maxImages - count(product_images($id));
                if ($remainingSlots > 0) {
                    $uploaded = upload_product_images($_FILES['images'], $remainingSlots);
                    foreach ($uploaded['paths'] as $i => $path) {
                        $st = $pdo->prepare('INSERT INTO product_images (product_id, image_path, is_primary, sort_order) VALUES (?, ?, 0, ?)');
                        $st->execute([$id, $path, count(product_images($id))]);
                    }
                    if ($uploaded['errors']) {
                        set_flash('warning', 'Listing updated, but some images failed: ' . implode(' ', $uploaded['errors']));
                    }
                } else {
                    set_flash('warning', 'Listing updated, but you have reached the image limit.');
                }
            }

            // Ensure a primary image exists.
            $pdo->prepare('UPDATE product_images SET is_primary = 0 WHERE product_id = ?')->execute([$id]);
            $pdo->prepare('UPDATE product_images SET is_primary = 1 WHERE product_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1')->execute([$id]);

            audit_log('listing_updated', 'product', $id, 'Listing "' . $fields['title'] . '" updated.');
            set_flash('success', 'Your listing has been updated.');
            redirect('listings.php');
        }
    }
}

$pageTitle = 'Edit Listing';
$dashboardPage = true;
require __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding-top:26px;">
  <div class="dash-header">
    <div>
      <h1>Edit Listing</h1>
      <div class="sub">Status: <strong><?= e(product_status_label($product['status'])) ?></strong></div>
    </div>
    <a class="btn btn-ghost" href="listings.php">← Back to My Listings</a>
  </div>

  <div class="card" style="padding:26px; max-width:860px;">
    <form method="post" action="edit-listing.php?id=<?= (int)$id ?>" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">

      <div class="form-group mb-2">
        <label class="form-label" for="title">Product title</label>
        <input class="form-control" type="text" id="title" name="title" value="<?= e($fields['title'] ?? $product['title']) ?>" required>
      </div>

      <div class="form-grid mb-2">
        <div class="form-group">
          <label class="form-label" for="category_id">Category</label>
          <select class="form-control" id="category_id" name="category_id" required>
            <?php foreach (get_categories(true) as $cat): ?>
              <option value="<?= (int)$cat['id'] ?>" <?= (int)($categoryId ?? $product['category_id']) === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="price">Price</label>
          <input class="form-control" type="number" id="price" name="price" step="0.01" min="0" value="<?= e($fields['price'] ?? $product['price']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="condition_label">Condition</label>
          <select class="form-control" id="condition_label" name="condition_label">
            <?php foreach (condition_options() as $cond): ?>
              <option value="<?= e($cond) ?>" <?= ($fields['condition_label'] ?? $product['condition_label']) === $cond ? 'selected' : '' ?>><?= e($cond) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="quantity">Quantity</label>
          <input class="form-control" type="number" id="quantity" name="quantity" min="1" max="50" value="<?= e($fields['quantity'] ?? $product['quantity']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="location">Pickup hall</label>
          <?= hall_select_html('location', $fields['location'] ?? $product['location'], 'Select a pickup hall') ?>
        </div>
        <div class="form-group">
          <label class="form-label" for="contact_preference">Contact preference</label>
          <select class="form-control" id="contact_preference" name="contact_preference">
            <?php foreach (['In-app message', 'Phone', 'Both'] as $cp): ?>
              <option value="<?= e($cp) ?>" <?= ($fields['contact_preference'] ?? $product['contact_preference']) === $cp ? 'selected' : '' ?>><?= e($cp) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group mb-2">
        <label class="form-label" for="description">Description</label>
        <textarea class="form-control" id="description" name="description" required><?= e($fields['description'] ?? $product['description']) ?></textarea>
      </div>

      <div class="form-group mb-2">
        <label class="form-label">Current images (<?= count($images) ?> / <?= (int)$maxImages ?>)</label>
        <div class="image-previews">
          <?php foreach ($images as $img): ?>
            <div class="preview-item" data-existing="<?= e($img['image_path']) ?>" data-image-id="<?= (int)$img['id'] ?>">
              <img src="<?= e(image_url($img['image_path'])) ?>" alt="Product image">
              <?php if ((int)$img['is_primary']): ?><span class="preview-primary">Primary</span><?php endif; ?>
              <button type="button" class="preview-remove" aria-label="Remove image">×</button>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="form-group mb-3">
        <label class="form-label" for="imageInput">Add more images</label>
        <div class="upload-zone" id="uploadZone" role="button" tabindex="0">
          <input type="file" id="imageInput" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple style="display:none;" data-max="<?= (int)$maxImages ?>">
          <p>Click to choose images or drag &amp; drop here</p>
          <p class="upload-hint text-small">JPG, PNG, WebP or GIF, up to 5 MB.</p>
        </div>
        <div class="image-previews" id="imagePreviews"></div>
      </div>

      <button class="btn btn-primary btn-lg" type="submit">Save Changes</button>
      <a class="btn btn-ghost" href="listings.php">Cancel</a>
      <button type="submit" name="action" value="delete_listing" class="btn btn-danger-soft" data-confirm="Delete this listing permanently? This cannot be undone.">Delete Listing</button>
    </form>
  </div>
</div>

<?php
$extraScripts = ['/assets/js/listing.js'];
require __DIR__ . '/../includes/footer.php';
?>
