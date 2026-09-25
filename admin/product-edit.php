<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/security.php';

$admin = require_admin_auth();
$db = get_db();

$productId = (int)($_GET['id'] ?? 0);
$isNew = ($productId <= 0);

$error = '';
$flash = '';

// Load existing or defaults
$product = [
    'id' => 0,
    'name' => '',
    'slug' => '',
    'description' => '',
    'category' => 'regular',
    'image_url' => app_url('/public/assets/images/jam-huckleberry.jpg'),
    'is_active' => 1,
    'display_order' => 1,
    'variants' => []
];

if (!$isNew) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$productId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        header('Location: ' . app_url('/admin/products.php'));
        exit;
    }
    $product = $existing;

    $vStmt = $db->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY price ASC");
    $vStmt->execute([$productId]);
    $product['variants'] = $vStmt->fetchAll();
} else {
    // Default 3 standard variants for new jam
    $product['variants'] = [
        ['size' => '4 oz', 'price' => 9.00, 'sku' => '', 'stock_status' => 'in_stock', 'stock_qty' => 50],
        ['size' => '8 oz', 'price' => 14.00, 'sku' => '', 'stock_status' => 'in_stock', 'stock_qty' => 60],
        ['size' => '12 oz', 'price' => 17.00, 'sku' => '', 'stock_status' => 'in_stock', 'stock_qty' => 30]
    ];
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    $name = trim((string)($_POST['name'] ?? ''));
    $slug = trim((string)($_POST['slug'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $category = trim((string)($_POST['category'] ?? 'regular'));
    $imageUrl = trim((string)($_POST['image_url'] ?? ''));
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $displayOrder = (int)($_POST['display_order'] ?? 1);
    $autoRebuild = isset($_POST['auto_rebuild']);

    if (empty($name)) {
        $error = 'Product name is required.';
    }

    $slug = canonical_product_slug($slug ?: $name);

    // Handle Image Upload if present
    if (isset($_FILES['product_image']) && !empty($_FILES['product_image']['name'])) {
        $uploadRes = sanitize_and_store_upload(
            $_FILES['product_image'],
            dirname(__DIR__) . '/public/images',
            ['image/jpeg', 'image/png', 'image/webp'],
            5242880 // 5MB
        );

        if ($uploadRes['success']) {
            $imageUrl = app_url('/public/images/' . $uploadRes['filename']);
        } else {
            $error = 'Image upload error: ' . $uploadRes['error'];
        }
    }

    if (empty($error)) {
        try {
            $db->beginTransaction();

            if ($isNew) {
                $stmt = $db->prepare("
                    INSERT INTO products (name, slug, description, category, image_url, is_active, display_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $slug, $description, $category, $imageUrl, $isActive, $displayOrder]);
                $productId = (int)$db->lastInsertId();
            } else {
                $stmt = $db->prepare("
                    UPDATE products 
                    SET name = ?, slug = ?, description = ?, category = ?, image_url = ?, is_active = ?, display_order = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $slug, $description, $category, $imageUrl, $isActive, $displayOrder, $productId]);
            }

            // Process Variants
            $varSizes   = $_POST['var_size'] ?? [];
            $varPrices  = $_POST['var_price'] ?? [];
            $varSkus    = $_POST['var_sku'] ?? [];
            $varStatuses= $_POST['var_status'] ?? [];
            $varQtys    = $_POST['var_qty'] ?? [];

            // Delete removed variants or refresh
            $stmtDel = $db->prepare("DELETE FROM product_variants WHERE product_id = ?");
            $stmtDel->execute([$productId]);

            $stmtIns = $db->prepare("
                INSERT INTO product_variants (product_id, size, price, sku, stock_status, stock_qty)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            for ($i = 0; $i < count($varSizes); $i++) {
                $size = trim((string)$varSizes[$i]);
                if (empty($size)) continue;

                $price = (float)($varPrices[$i] ?? 9.00);
                $sku = trim((string)($varSkus[$i] ?? ''));
                if (empty($sku)) {
                    $sku = 'JAM-' . strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $name), 0, 4)) . '-' . substr($size, 0, 2);
                }
                $status = in_array($varStatuses[$i] ?? '', ['in_stock', 'out_of_stock'], true) ? $varStatuses[$i] : 'in_stock';
                $qty = (int)($varQtys[$i] ?? 50);

                $stmtIns->execute([$productId, $size, $price, $sku, $status, $qty]);
            }

            $db->commit();

            // Trigger Site Rebuild if requested
            if ($autoRebuild) {
                $buildScript = dirname(__DIR__) . '/build.php';
                if (file_exists($buildScript)) {
                    @exec('php ' . escapeshellarg($buildScript) . ' 2>&1');
                    if (file_exists(dirname(__DIR__) . '/public/index.html')) {
                        @copy(dirname(__DIR__) . '/public/index.html', dirname(__DIR__) . '/index.html');
                    }
                }
            }

            header('Location: ' . app_url('/admin/products.php?saved=1'));
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

$pageTitle = $isNew ? 'Add New Product' : 'Edit ' . $product['name'];
require_once __DIR__ . '/header.php';
?>

<div class="admin-page-header">
  <div>
    <a href="<?= htmlspecialchars(app_url('/admin/products.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" style="color:#64748b; text-decoration:none; font-size:0.85rem;">&larr; Back to Products</a>
    <h1 class="admin-page-title" style="margin-top:0.35rem;">
      <?= $isNew ? '➕ Add New Jam Product' : '✏️ Edit ' . htmlspecialchars($product['name']) ?>
    </h1>
    <p class="admin-page-desc">Configure product descriptions, jar variants, pricing, and stock levels.</p>
  </div>
</div>

<?php if (!empty($error)): ?>
  <div class="admin-alert admin-alert-error" role="alert">
    <span>⚠️</span>
    <div><?= htmlspecialchars($error) ?></div>
  </div>
<?php endif; ?>

<form method="POST" action="<?= htmlspecialchars(app_url('/admin/product-edit.php' . ($isNew ? '' : '?id=' . $productId)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">

  <div class="form-grid" style="grid-template-columns: 2fr 1fr; gap:1.75rem;">
    <!-- Left Column: Primary Details -->
    <div>
      <div class="admin-card">
        <h2 class="admin-card-title" style="margin-bottom:1.25rem;">General Information</h2>

        <div class="form-group">
          <label for="name">Product Name *</label>
          <input type="text" id="name" name="name" required value="<?= htmlspecialchars($product['name']) ?>" placeholder="e.g. Wild Mountain Huckleberry Jam">
        </div>

        <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
          <div class="form-group">
            <label for="slug">URL Slug (leave blank to auto-generate)</label>
            <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($product['slug']) ?>" placeholder="e.g. huckleberry">
          </div>

          <div class="form-group">
            <label for="category">Category</label>
            <select id="category" name="category">
              <option value="regular" <?= $product['category'] === 'regular' ? 'selected' : '' ?>>Regular Single Jars</option>
              <option value="pack" <?= $product['category'] === 'pack' ? 'selected' : '' ?>>Multi-Jar Pack</option>
              <option value="gift_box" <?= $product['category'] === 'gift_box' ? 'selected' : '' ?>>Curated Gift Box</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label for="description">Detailed Description *</label>
          <textarea id="description" name="description" rows="4" required placeholder="Describe ingredients, tasting notes, and pairing suggestions..."><?= htmlspecialchars($product['description']) ?></textarea>
        </div>
      </div>

      <!-- Variants & Sizes -->
      <div class="admin-card">
        <div class="admin-card-header">
          <h2 class="admin-card-title">Jar Sizes & Inventory Variants</h2>
          <span style="font-size:0.85rem; color:#64748b;">Standard prices: 4 oz ($9), 8 oz ($14), 12 oz ($17)</span>
        </div>

        <div class="admin-table-container">
          <table class="admin-table" id="variants-table">
            <thead>
              <tr>
                <th>Size</th>
                <th>Price ($)</th>
                <th>SKU</th>
                <th>Stock Status</th>
                <th>Stock Qty</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($product['variants'] as $v): ?>
                <tr>
                  <td>
                    <input type="text" name="var_size[]" value="<?= htmlspecialchars($v['size']) ?>" style="width:80px; padding:0.4rem; border:1px solid #cbd5e1; border-radius:4px;" required>
                  </td>
                  <td>
                    <input type="number" step="0.50" name="var_price[]" value="<?= number_format((float)$v['price'], 2, '.', '') ?>" style="width:90px; padding:0.4rem; border:1px solid #cbd5e1; border-radius:4px;" required>
                  </td>
                  <td>
                    <input type="text" name="var_sku[]" value="<?= htmlspecialchars($v['sku'] ?? '') ?>" style="width:140px; padding:0.4rem; border:1px solid #cbd5e1; border-radius:4px;" placeholder="Auto-generated">
                  </td>
                  <td>
                    <select name="var_status[]" style="padding:0.4rem; border:1px solid #cbd5e1; border-radius:4px;">
                      <option value="in_stock" <?= ($v['stock_status'] ?? '') === 'in_stock' ? 'selected' : '' ?>>In Stock</option>
                      <option value="out_of_stock" <?= ($v['stock_status'] ?? '') === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                    </select>
                  </td>
                  <td>
                    <input type="number" name="var_qty[]" value="<?= (int)($v['stock_qty'] ?? 50) ?>" style="width:80px; padding:0.4rem; border:1px solid #cbd5e1; border-radius:4px;" required>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Right Column: Image & Publishing -->
    <div>
      <div class="admin-card">
        <h3 style="font-size:1.1rem; font-weight:700; color:var(--admin-primary-dark); margin-bottom:1rem;">Product Image</h3>

        <?php if (!empty($product['image_url'])): ?>
          <div style="margin-bottom:1rem; text-align:center;">
            <img 
              src="<?= htmlspecialchars($product['image_url']) ?>" 
              alt="Current preview" 
              style="max-width:100%; height:180px; object-fit:cover; border-radius:8px; border:1px solid #e2e8f0;"
            >
          </div>
        <?php endif; ?>

        <div class="form-group">
          <label for="image_url">Image Asset Path / URL</label>
          <input type="text" id="image_url" name="image_url" value="<?= htmlspecialchars($product['image_url']) ?>" placeholder="<?= htmlspecialchars(app_url('/public/assets/images/jam.jpg'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        </div>

        <div class="form-group" style="padding-top:0.75rem; border-top:1px dashed #cbd5e1;">
          <label for="product_image">Upload New Image (JPEG, PNG, WebP)</label>
          <input type="file" id="product_image" name="product_image" accept="image/jpeg,image/png,image/webp">
          <span class="form-help">MIME verified &bull; Max size: 5MB</span>
        </div>
      </div>

      <div class="admin-card">
        <h3 style="font-size:1.1rem; font-weight:700; color:var(--admin-primary-dark); margin-bottom:1rem;">Publishing Settings</h3>

        <div class="form-group">
          <label for="display_order">Display Sort Order</label>
          <input type="number" id="display_order" name="display_order" value="<?= (int)$product['display_order'] ?>" style="width:100px;">
          <span class="form-help">Lower numbers appear first in storefront listings.</span>
        </div>

        <div style="margin-bottom:1.25rem;">
          <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:600;">
            <input type="checkbox" name="is_active" value="1" <?= $product['is_active'] ? 'checked' : '' ?>>
            <span>Active & Visible in Storefront</span>
          </label>
        </div>

        <div style="margin-bottom:1.5rem; background:#f0fdf4; padding:0.75rem; border-radius:6px; border:1px solid #bbf7d0;">
          <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.85rem; color:#166534;">
            <input type="checkbox" name="auto_rebuild" value="1" checked>
            <span><strong>One-Click Rebuild</strong>: Recompile static HTML pages automatically upon saving</span>
          </label>
        </div>

        <button type="submit" class="btn-primary-action" style="width:100%; justify-content:center; padding:0.75rem;">
          💾 Save Product & Variants
        </button>
      </div>
    </div>
  </div>
</form>

<?php require_once __DIR__ . '/footer.php'; ?>
