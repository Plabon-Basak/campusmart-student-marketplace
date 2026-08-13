<?php
/**
 * Admin: manage categories - create, edit, enable/disable and safe delete.
 * A category that still has products cannot be deleted.
 */
require_once __DIR__ . '/../includes/admin-auth.php';

$pdo = db();
$errors = [];

// ---------- POST handlers ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $categoryId = (int)($_POST['category_id'] ?? 0);

    if ($action === 'create' || $action === 'edit') {
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $status = ($_POST['status'] ?? 'active') === 'disabled' ? 'disabled' : 'active';

        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Category name must be between 2 and 100 characters.';
        }
        if (mb_strlen($description) > 255) {
            $errors[] = 'Description must be 255 characters or fewer.';
        }

        if (empty($errors)) {
            try {
                if ($action === 'create') {
                    $st = $pdo->prepare('INSERT INTO categories (name, description, status) VALUES (?, ?, ?)');
                    $st->execute([$name, $description, $status]);
                    audit_log('category_created', 'category', (int)$pdo->lastInsertId(), 'Created category "' . $name . '".');
                    set_flash('success', 'Category created.');
                } else {
                    $cat = get_category($categoryId);
                    if ($cat === null) {
                        $errors[] = 'Category not found.';
                    } else {
                        $st = $pdo->prepare('UPDATE categories SET name = ?, description = ?, status = ? WHERE id = ?');
                        $st->execute([$name, $description, $status, $categoryId]);
                        audit_log('category_updated', 'category', $categoryId, 'Updated category "' . $name . '".');
                        set_flash('success', 'Category updated.');
                    }
                }
            } catch (Throwable $e) {
                $errors[] = 'A category with this name already exists.';
            }
        }
    }

    if ($action === 'delete' && empty($errors)) {
        $cat = get_category($categoryId);
        if ($cat === null) {
            $errors[] = 'Category not found.';
        } else {
            $st = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
            $st->execute([$categoryId]);
            $count = (int)$st->fetchColumn();
            if ($count > 0) {
                $errors[] = 'Cannot delete "' . $cat['name'] . '" because ' . $count . ' listing(s) use it. Reassign or remove them first.';
            } else {
                $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$categoryId]);
                audit_log('category_deleted', 'category', $categoryId, 'Deleted category "' . $cat['name'] . '".');
                set_flash('success', 'Category deleted.');
            }
        }
    }

    if ($action === 'toggle') {
        $cat = get_category($categoryId);
        if ($cat !== null) {
            $new = $cat['status'] === 'active' ? 'disabled' : 'active';
            $pdo->prepare('UPDATE categories SET status = ? WHERE id = ?')->execute([$new, $categoryId]);
            audit_log('category_' . $new, 'category', $categoryId, ($new === 'disabled' ? 'Disabled' : 'Enabled') . ' category "' . $cat['name'] . '".');
            set_flash('success', 'Category ' . $new . '.');
        }
        redirect('categories.php');
    }

    if (!empty($errors)) {
        foreach ($errors as $err) {
            set_flash('error', $err);
        }
    }
    redirect('categories.php');
}

// ---------- List categories with product counts ----------
$categories = $pdo->query(
    "SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
     FROM categories c ORDER BY c.name ASC"
)->fetchAll();

$pageTitle = 'Categories';
$dashboardPage = true;
$activeNav = 'categories';
require __DIR__ . '/../includes/header.php';
?>

<div class="dash-wrap">
  <?php require __DIR__ . '/_sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-header">
      <div>
        <h1>Categories</h1>
        <div class="sub">Organise the marketplace into clear, safe categories</div>
      </div>
      <button type="button" class="btn btn-primary" data-open-modal="createModal">+ New Category</button>
    </div>

    <div class="card table-card">
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Description</th>
              <th>Listings</th>
              <th>Status</th>
              <th>Created</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($categories as $c): ?>
              <tr>
                <td><strong><?= e($c['name']) ?></strong></td>
                <td class="text-small text-muted"><?= e($c['description'] ?? '-') ?></td>
                <td class="text-small"><?= (int)$c['product_count'] ?></td>
                <td>
                  <span class="badge badge-<?= $c['status'] === 'active' ? 'success' : 'muted' ?>"><?= e(ucfirst($c['status'])) ?></span>
                </td>
                <td class="text-small"><?= e(date('M j, Y', strtotime($c['created_at']))) ?></td>
                <td>
                  <div class="flex" style="gap:6px; justify-content:flex-end;">
                    <button type="button" class="btn btn-ghost btn-sm" data-open-modal="editModal<?= (int)$c['id'] ?>">Edit</button>
                    <form method="post" action="categories.php" style="display:inline;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="category_id" value="<?= (int)$c['id'] ?>">
                      <button class="btn btn-ghost btn-sm" type="submit"><?= $c['status'] === 'active' ? 'Disable' : 'Enable' ?></button>
                    </form>
                    <?php if ((int)$c['product_count'] === 0): ?>
                      <form method="post" action="categories.php" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="category_id" value="<?= (int)$c['id'] ?>">
                        <button class="btn btn-danger-soft btn-sm" type="submit" data-confirm-dangerous data-confirm="Delete the category &quot;<?= e($c['name']) ?>&quot; permanently?">Delete</button>
                      </form>
                    <?php else: ?>
                      <button class="btn btn-danger-soft btn-sm" type="button" disabled title="Cannot delete: <?= (int)$c['product_count'] ?> listing(s) use this category.">Delete</button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>

              <!-- Edit modal -->
              <div class="modal-backdrop" id="editModal<?= (int)$c['id'] ?>" role="dialog" aria-modal="true">
                <div class="modal">
                  <h3>Edit "<?= e($c['name']) ?>"</h3>
                  <form method="post" action="categories.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="category_id" value="<?= (int)$c['id'] ?>">
                    <div class="form-group mb-2">
                      <label class="form-label" for="edit_name_<?= (int)$c['id'] ?>">Name</label>
                      <input class="form-control" type="text" id="edit_name_<?= (int)$c['id'] ?>" name="name" value="<?= e($c['name']) ?>" maxlength="100" required>
                    </div>
                    <div class="form-group mb-2">
                      <label class="form-label" for="edit_desc_<?= (int)$c['id'] ?>">Description</label>
                      <textarea class="form-control" id="edit_desc_<?= (int)$c['id'] ?>" name="description" rows="2" maxlength="255"><?= e($c['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group mb-2">
                      <label class="form-label" for="edit_status_<?= (int)$c['id'] ?>">Status</label>
                      <select class="form-control" id="edit_status_<?= (int)$c['id'] ?>" name="status">
                        <option value="active" <?= $c['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="disabled" <?= $c['status'] === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                      </select>
                    </div>
                    <div class="modal-actions">
                      <button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
                      <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create modal -->
    <div class="modal-backdrop" id="createModal" role="dialog" aria-modal="true">
      <div class="modal">
        <h3>New Category</h3>
        <form method="post" action="categories.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="create">
          <div class="form-group mb-2">
            <label class="form-label" for="create_name">Name</label>
            <input class="form-control" type="text" id="create_name" name="name" maxlength="100" required placeholder="e.g. Musical Instruments">
          </div>
          <div class="form-group mb-2">
            <label class="form-label" for="create_desc">Description</label>
            <textarea class="form-control" id="create_desc" name="description" rows="2" maxlength="255" placeholder="What belongs in this category?"></textarea>
          </div>
          <div class="form-group mb-2">
            <label class="form-label" for="create_status">Status</label>
            <select class="form-control" id="create_status" name="status">
              <option value="active">Active</option>
              <option value="disabled">Disabled</option>
            </select>
          </div>
          <div class="modal-actions">
            <button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
            <button type="submit" class="btn btn-primary">Create Category</button>
          </div>
        </form>
      </div>
    </div>
  </main>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
