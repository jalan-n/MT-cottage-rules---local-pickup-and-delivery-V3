<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/security.php';

$admin = require_admin_auth();
$db = get_db();

$flash = '';
$flashType = 'success';

// Handle product deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_product') {
    require_csrf_token();
    $productId = (int)($_POST['product_id'] ?? 0);
    if ($productId > 0) {
        try {
            // Delete product (cascades to variants)
            $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $flash = "Product and variants successfully removed. Run 'One-Click Rebuild' to update static catalog.";
        } catch (Exception $e) {
            $flash = "Could not delete product: " . $e->getMessage();
            $flashType = 'error';
        }
    }
}

// Handle active status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_active') {
    require_csrf_token();
    $productId = (int)($_POST['product_id'] ?? 0);
    $currentStatus = (int)($_POST['current_status'] ?? 0);
    $newStatus = $currentStatus === 1 ? 0 : 1;

    $stmt = $db->prepare("UPDATE products SET is_active = ? WHERE id = ?");
    $stmt->execute([$newStatus, $productId]);
    $flash = "Product visibility updated. Remember to rebuild the site.";
}

// Fetch all products with variants
$products = get_all_products(false); // including inactive

$pageTitle = 'Products & Inventory';
require_once __DIR__ . '/header.php';
?>

<div class="admin-page-header">
  <div>
    <h1 class="admin-page-title">🍯 Products & Inventory</h1>
    <p class="admin-page-desc">Create and edit gourmet jam offerings, configure jar size pricing (4 oz, 8 oz, 12 oz), and manage real-time inventory counts.</p>
  </div>
  <div>
    <a href="<?= htmlspecialchars(app_url('/admin/product-edit.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="btn-primary-action">
      ➕ Add New Product
    </a>
  </div>
</div>

<?php if (!empty($flash)): ?>
  <div class="admin-alert admin-alert-<?= $flashType ?>" role="status">
    <span><?= $flashType === 'success' ? '✅' : '⚠️' ?></span>
    <div><?= htmlspecialchars($flash) ?></div>
  </div>
<?php endif; ?>

<div class="admin-card">
  <div class="admin-card-header">
    <h2 class="admin-card-title">Catalog Inventory (<?= count($products) ?> Items)</h2>
    <span style="font-size:0.85rem; color:#64748b;">Pricing & stock synchronization</span>
  </div>

  <div class="admin-table-container">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Image</th>
          <th>Product Name & Slug</th>
          <th>Category</th>
          <th>Size Variants & Stock</th>
          <th>Status</th>
          <th>Sort Order</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p): ?>
          <tr>
            <td style="width:60px;">
              <img 
                src="<?= htmlspecialchars($p['image_url'] ?: app_url('/public/assets/images/jam-huckleberry.jpg')) ?>" 
                alt="<?= htmlspecialchars($p['name']) ?>" 
                style="width:50px; height:50px; object-fit:cover; border-radius:6px; border:1px solid #e2e8f0;"
              >
            </td>
            <td>
              <a href="<?= htmlspecialchars(app_url('/admin/product-edit.php?id=' . $p['id']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" style="font-weight:700; color:var(--admin-primary); text-decoration:none; font-size:0.95rem;">
                <?= htmlspecialchars($p['name']) ?>
              </a>
              <div style="font-size:0.78rem; color:#64748b;">slug: <code><?= htmlspecialchars($p['slug']) ?></code></div>
            </td>
            <td>
              <span class="badge" style="background:#f1f5f9; color:#475569;">
                <?= htmlspecialchars($p['category']) ?>
              </span>
            </td>
            <td>
              <div style="display:flex; flex-direction:column; gap:0.25rem;">
                <?php foreach ($p['variants'] as $v): ?>
                  <div style="font-size:0.8rem; display:flex; gap:0.5rem; align-items:center;">
                    <span style="font-weight:600; min-width:35px;"><?= htmlspecialchars($v['size']) ?>:</span>
                    <span>$<?= number_format((float)$v['price'], 2) ?></span>
                    <span style="color:#64748b;">(<?= $v['stock_qty'] ?> in stock)</span>
                    <span class="badge badge-<?= $v['stock_status'] === 'in_stock' ? 'completed' : 'cancelled' ?>" style="font-size:0.68rem; padding:0.1rem 0.35rem;">
                      <?= $v['stock_status'] === 'in_stock' ? 'In Stock' : 'Out' ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div>
            </td>
            <td>
              <form method="POST" action="<?= htmlspecialchars(app_url('/admin/products.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                <input type="hidden" name="current_status" value="<?= (int)$p['is_active'] ?>">
                <button type="submit" class="badge badge-<?= $p['is_active'] ? 'completed' : 'cancelled' ?>" style="border:none; cursor:pointer;">
                  <?= $p['is_active'] ? '● Active' : '○ Inactive' ?>
                </button>
              </form>
            </td>
            <td style="text-align:center;">
              <?= (int)$p['display_order'] ?>
            </td>
            <td>
              <div style="display:flex; gap:0.4rem;">
                <a href="<?= htmlspecialchars(app_url('/admin/product-edit.php?id=' . $p['id']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="btn-admin" style="background:#f1f5f9; color:#1e293b;">
                  Edit
                </a>
                <form method="POST" action="<?= htmlspecialchars(app_url('/admin/products.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" style="display:inline;" onsubmit="return confirm('Delete this product and all its variants?');">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete_product">
                  <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                  <button type="submit" class="btn-admin" style="background:#fee2e2; color:#991b1b; border:none;">
                    Delete
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
