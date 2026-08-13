<?php
/**
 * Create a new listing with image upload and full server-side validation.
 */
require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = db();
$categories = get_categories(true);

$errors = [];
$maxImages = (int)(settings('max_listing_images') ?: 5);
$old = [
    'title' => '', 'category_id' => '', 'description' => '', 'price' => '',
    'condition_label' => 'Good', 'quantity' => '1', 'location' => '',
    'contact_preference' => 'In-app message',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($old as $k => $v) {
        $old[$k] = trim((string)($_POST[$k] ?? $v));
    }

    // Business rules
    $user = current_user();
    if ($user === null || $user['status'] !== 'active') {
        $errors['general'] = 'Your account is not active, so you cannot create listings.';
    }

    if (mb_strlen($old['title']) < 4 || mb_strlen($old['title']) > 200) {
        $errors['title'] = 'Title must be between 4 and 200 characters.';
    }
    if (mb_strlen($old['description']) < 20) {
        $errors['description'] = 'Description must be at least 20 characters.';
    }
    $categoryId = (int)$old['category_id'];
    $category = get_category($categoryId);
    if ($category === null || $category['status'] !== 'active') {
        $errors['category_id'] = 'Please choose a valid category.';
    }
    if (!is_numeric($old['price']) || (float)$old['price'] < 0 || (float)$old['price'] > 99999999) {
        $errors['price'] = 'Please enter a valid price (0 or more).';
    }
    if (!in_array($old['condition_label'], condition_options(), true)) {
        $errors['condition_label'] = 'Please choose a valid condition.';
    }
    if (!ctype_digit($old['quantity']) || (int)$old['quantity'] < 1 || (int)$old['quantity'] > 50) {
        $errors['quantity'] = 'Quantity must be a whole number between 1 and 50.';
    }
    if (mb_strlen($old['location']) < 2 || mb_strlen($old['location']) > 150) {
        $errors['location'] = 'Please enter a pickup location (e.g. your hall).';
    }
    if (!in_array($old['contact_preference'], ['In-app message', 'Phone', 'Both'], true)) {
        $errors['contact_preference'] = 'Please choose a valid contact preference.';
    }

    // Duplicate / excessive listing controls.
    if (!isset($errors['title'])) {
        $st = $pdo->prepare("SELECT 1 FROM products WHERE seller_id = ? AND title = ? AND status IN ('pending','approved','active','reserved')");
        $st->execute([current_user_id(), $old['title']]);
        if ($st->fetchColumn()) {
            $errors['title'] = 'You already have an active listing with this exact title.';
        }
    }
    if (empty($errors)) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ? AND status IN ('pending','approved','active','reserved')");
        $st->execute([current_user_id()]);
        if ((int)$st->fetchColumn() >= 20) {
            $errors['general'] = 'You already have 20 active or pending listings. Please sell or delete some first.';
        }
    }

    $uploaded = ['paths' => [], 'errors' => []];
    if (empty($errors) && !empty($_FILES['images']['name'][0])) {
        $uploaded = upload_product_images($_FILES['images'], $maxImages);
    }

    if (empty($errors)) {
        $requireApproval = (int)(settings('listings_require_approval') ?: 1) === 1;
        $status = $requireApproval ? 'pending' : 'active';
        $expiryDays = max(1, (int)(settings('listing_expiry_days') ?: 30));
        $expiresAt = date('Y-m-d H:i:s', time() + $expiryDays * 86400);

        $st = $pdo->prepare(
            'INSERT INTO products (seller_id, category_id, title, description, price, condition_label, quantity, location, contact_preference, status, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            current_user_id(), $categoryId, $old['title'], $old['description'],
            (float)$old['price'], $old['condition_label'], (int)$old['quantity'],
            $old['location'], $old['contact_preference'], $status, $expiresAt,
        ]);
        $productId = (int)$pdo->lastInsertId();

        // Save images (first uploaded file becomes primary).
        foreach ($uploaded['paths'] as $i => $path) {
            $st = $pdo->prepare('INSERT INTO product_images (product_id, image_path, is_primary, sort_order) VALUES (?, ?, ?, ?)');
            $st->execute([$productId, $path, $i === 0 ? 1 : 0, $i]);
        }

        audit_log('listing_created', 'product', $productId, 'Listing "' . $old['title'] . '" created (status: ' . $status . ').');
        if ($status === 'pending') {
            notify_admins('listing_pending', 'New listing pending review', 'A new listing "' . $old['title'] . '" needs admin approval.', $productId);
            set_flash('success', 'Your listing was submitted and is now pending review by our moderators.');
        } else {
            set_flash('success', 'Your listing is now live on the marketplace.');
        }
        if ($uploaded['errors']) {
            set_flash('warning', 'Your listing was created, but some images could not be uploaded: ' . implode(' ', $uploaded['errors']));
        }
        redirect('listings.php');
    } elseif (!empty($uploaded['errors'])) {
        foreach ($uploaded['errors'] as $upErr) {
            $errors[] = $upErr;
        }
    }
}

$pageTitle = 'Sell an Item';
$dashboardPage = true;
require __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding-top:26px;">
  <div class="dash-header">
    <div>
      <h1>Sell an Item</h1>
      <div class="sub">Create a new listing for your campus marketplace</div>
    </div>
  </div>

  <?php if (isset($errors['general'])): ?>
    <div class="alert alert-error alert-inline"><?= e($errors['general']) ?></div>
  <?php endif; ?>

  <div class="card" style="padding:26px; max-width:860px;">
    <form method="post" action="add-listing.php" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?>

      <div class="form-group mb-2">
        <label class="form-label" for="title">Product title <span class="req">*</span></label>
        <input class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>" type="text" id="title" name="title" value="<?= e($old['title']) ?>" required>
        <?php if (isset($errors['title'])): ?><span class="form-error"><?= e($errors['title']) ?></span><?php endif; ?>
      </div>

      <div class="form-grid mb-2">
        <div class="form-group">
          <label class="form-label" for="category_id">Category <span class="req">*</span></label>
          <select class="form-control" id="category_id" name="category_id" required>
            <option value="">Select a category…</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= (int)$cat['id'] ?>" <?= (int)$old['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['category_id'])): ?><span class="form-error"><?= e($errors['category_id']) ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="price">Price <span class="req">*</span></label>
          <input class="form-control" type="number" id="price" name="price" step="0.01" min="0" value="<?= e($old['price']) ?>" required>
          <?php if (isset($errors['price'])): ?><span class="form-error"><?= e($errors['price']) ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="condition_label">Condition <span class="req">*</span></label>
          <select class="form-control" id="condition_label" name="condition_label" required>
            <?php foreach (condition_options() as $cond): ?>
              <option value="<?= e($cond) ?>" <?= $old['condition_label'] === $cond ? 'selected' : '' ?>><?= e($cond) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['condition_label'])): ?><span class="form-error"><?= e($errors['condition_label']) ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="quantity">Quantity <span class="req">*</span></label>
          <input class="form-control" type="number" id="quantity" name="quantity" min="1" max="50" value="<?= e($old['quantity']) ?>" required>
          <?php if (isset($errors['quantity'])): ?><span class="form-error"><?= e($errors['quantity']) ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="location">Pickup hall <span class="req">*</span></label>
          <?= hall_select_html('location', $old['location'], 'Select a pickup hall') ?>
          <?php if (isset($errors['location'])): ?><span class="form-error"><?= e($errors['location']) ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="contact_preference">Contact preference <span class="req">*</span></label>
          <select class="form-control" id="contact_preference" name="contact_preference" required>
            <?php foreach (['In-app message', 'Phone', 'Both'] as $cp): ?>
              <option value="<?= e($cp) ?>" <?= $old['contact_preference'] === $cp ? 'selected' : '' ?>><?= e($cp) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group mb-2">
        <label class="form-label" for="description">Description <span class="req">*</span></label>
        <textarea class="form-control" id="description" name="description" minlength="20" required><?= e($old['description']) ?></textarea>
        <span class="form-hint">Include condition details, reason for selling, and any defects. At least 20 characters.</span>
        <?php if (isset($errors['description'])): ?><span class="form-error"><?= e($errors['description']) ?></span><?php endif; ?>
      </div>

      <div class="form-group mb-3">
        <label class="form-label" for="imageInput">Product images <span class="req">*</span></label>
        <div class="upload-zone" id="uploadZone" role="button" tabindex="0">
          <input type="file" id="imageInput" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple style="display:none;" data-max="<?= (int)$maxImages ?>">
          <p>Click to choose images or drag &amp; drop here</p>
          <p class="upload-hint text-small"><?= (int)$maxImages ?> images can be added (JPG, PNG, WebP or GIF, up to 5 MB).</p>
        </div>
        <div class="image-previews" id="imagePreviews"></div>
        <?php if (isset($errors['images'])): ?><span class="form-error"><?= e($errors['images']) ?></span><?php endif; ?>
      </div>

      <div class="alert alert-warning alert-inline text-small">
        🛡️ <strong>Safety reminder:</strong> your listing will be reviewed by a moderator before going live. Never share passwords or sensitive information. Agree to meet in public campus locations only.
      </div>

      <button class="btn btn-primary btn-lg" type="submit">Submit for Review</button>
      <a class="btn btn-ghost" href="listings.php">Cancel</a>
    </form>
  </div>
</div>

<?php
$extraScripts = ['/assets/js/listing.js'];
require __DIR__ . '/../includes/footer.php';
?>
