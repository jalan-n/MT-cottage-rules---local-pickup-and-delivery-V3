<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/security.php';

$admin = require_admin_auth();
$db = get_db();

// 1. Fetch Key Metrics
$totalOrders = 0;
$newOrdersCount = 0;
$totalRevenue = 0.00;
$activeProductsCount = 0;

try {
    $totalOrders = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $newOrdersCount = (int)$db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'new'")->fetchColumn();
    $totalRevenue = (float)$db->query("SELECT SUM(total_amount) FROM orders WHERE payment_status = 'paid'")->fetchColumn();
    $activeProductsCount = (int)$db->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();
} catch (Exception $e) {
    // Graceful fallback
}

// 2. Fetch Low Stock Variants (< 20 units or out_of_stock)
$lowStockVariants = [];
try {
    $stmt = $db->query("
        SELECT pv.*, p.name AS product_name 
        FROM product_variants pv
        JOIN products p ON pv.product_id = p.id
        WHERE pv.stock_qty <= 20 OR pv.stock_status = 'out_of_stock'
        ORDER BY pv.stock_qty ASC
        LIMIT 10
    ");
    $lowStockVariants = $stmt->fetchAll();
} catch (Exception $e) {
    // Graceful fallback
}

// 3. Fetch Recent 8 Orders
$recentOrders = [];
try {
    $stmt = $db->query("
        SELECT o.*, c.full_name, c.email, c.phone 
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        ORDER BY o.id DESC
        LIMIT 8
    ");
    $recentOrders = $stmt->fetchAll();
} catch (Exception $e) {
    // Graceful fallback
}

$pageTitle = 'Dashboard Overview';
require_once __DIR__ . '/header.php';
?>

<div class="admin-page-header">
  <div>
    <h1 class="admin-page-title">Store & Kitchen Dashboard</h1>
    <p class="admin-page-desc">Real-time overview of incoming gourmet jam orders, kitchen batch status, and static site synchronization.</p>
  </div>
  <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
    <a href="<?= htmlspecialchars(app_url('/admin/orders.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="btn-primary-action">
      📦 Manage Orders (<?= $newOrdersCount ?> New)
    </a>
    <a href="<?= htmlspecialchars(app_url('/admin/products.php?action=new'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="btn-secondary-action">
      ➕ Add Product
    </a>
  </div>
</div>

<!-- Stat Cards -->
<div class="stat-cards-grid">
  <div class="stat-card">
    <div class="stat-card-title">Total Orders</div>
    <div class="stat-card-value"><?= number_format($totalOrders) ?></div>
    <div class="stat-card-note">
      <strong style="color:var(--admin-warning);"><?= $newOrdersCount ?></strong> awaiting kitchen preparation
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-card-title">Lifetime Revenue</div>
    <div class="stat-card-value">$<?= number_format($totalRevenue, 2) ?></div>
    <div class="stat-card-note">Processed via Square Payments</div>
  </div>

  <div class="stat-card">
    <div class="stat-card-title">Active Jam Flavors</div>
    <div class="stat-card-value"><?= number_format($activeProductsCount) ?></div>
    <div class="stat-card-note">Handcrafted small batch preserves</div>
  </div>

  <div class="stat-card">
    <div class="stat-card-title">Inventory Alerts</div>
    <div class="stat-card-value" style="<?= count($lowStockVariants) > 0 ? 'color:var(--admin-danger);' : '' ?>">
      <?= count($lowStockVariants) ?>
    </div>
    <div class="stat-card-note">Variants below 20 jars</div>
  </div>
</div>

<!-- Recent Orders Table -->
<div class="admin-card">
  <div class="admin-card-header">
    <div>
      <h2 class="admin-card-title">Recent Inbound Orders</h2>
      <p style="font-size:0.85rem; color:#64748b;">Sorted by newest order submission timestamp</p>
    </div>
    <a href="<?= htmlspecialchars(app_url('/admin/orders.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="btn-secondary-action">View All Orders &rarr;</a>
  </div>

  <?php if (empty($recentOrders)): ?>
    <p style="color:#64748b; padding:1rem 0;">No orders recorded in database yet.</p>
  <?php else: ?>
    <div class="admin-table-container">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Customer</th>
            <th>Fulfillment</th>
            <th>Schedule</th>
            <th>Total</th>
            <th>Payment</th>
            <th>Order Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentOrders as $order): ?>
            <tr>
              <td>
                <a href="<?= htmlspecialchars(app_url('/admin/order-detail.php?id=' . $order['id']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" style="font-weight:700; color:var(--admin-primary); text-decoration:none;">
                  <?= htmlspecialchars($order['order_number']) ?>
                </a>
              </td>
              <td>
                <div style="font-weight:600;"><?= htmlspecialchars($order['full_name']) ?></div>
                <div style="font-size:0.78rem; color:#64748b;"><?= htmlspecialchars($order['email']) ?></div>
              </td>
              <td>
                <span class="badge badge-<?= htmlspecialchars($order['fulfillment_type']) ?>">
                  <?= $order['fulfillment_type'] === 'pickup' ? '📍 Farmstand Pickup' : '🚚 Local Delivery' ?>
                </span>
              </td>
              <td>
                <div style="font-size:0.82rem; font-weight:600;"><?= htmlspecialchars($order['fulfillment_date']) ?></div>
                <div style="font-size:0.75rem; color:#64748b;"><?= htmlspecialchars($order['fulfillment_time_slot']) ?></div>
              </td>
              <td>
                <strong>$<?= number_format((float)$order['total_amount'], 2) ?></strong>
              </td>
              <td>
                <span class="badge badge-<?= htmlspecialchars($order['payment_status']) ?>">
                  <?= ucfirst($order['payment_status']) ?>
                </span>
              </td>
              <td>
                <span class="badge badge-<?= htmlspecialchars($order['order_status']) ?>">
                  <?= ucfirst(str_replace('_', ' ', $order['order_status'])) ?>
                </span>
              </td>
              <td>
                <div style="display:flex; gap:0.4rem;">
                  <a href="<?= htmlspecialchars(app_url('/admin/order-detail.php?id=' . $order['id']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="btn-admin" style="background:#f1f5f9; color:#334155;">
                    View
                  </a>
                  <a href="<?= htmlspecialchars(app_url('/admin/packing-slip.php?order_id=' . $order['id']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" class="btn-admin" style="background:#e0e7ff; color:#3730a3;" title="Print Packing Slip">
                    🖨️ Slip
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Low Stock Alerts -->
<?php if (!empty($lowStockVariants)): ?>
  <div class="admin-card" style="border-left: 4px solid var(--admin-danger);">
    <div class="admin-card-header">
      <div>
        <h2 class="admin-card-title" style="color:var(--admin-danger);">⚠️ Low Stock Alerts</h2>
        <p style="font-size:0.85rem; color:#64748b;">The following jar variants require cooking batches to meet order demand.</p>
      </div>
      <a href="<?= htmlspecialchars(app_url('/admin/products.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="btn-secondary-action">Inventory Manager &rarr;</a>
    </div>

    <div class="admin-table-container">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Flavor</th>
            <th>Size</th>
            <th>SKU</th>
            <th>Price</th>
            <th>Current Stock Qty</th>
            <th>Status</th>
            <th>Quick Update</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lowStockVariants as $var): ?>
            <tr>
              <td><strong><?= htmlspecialchars($var['product_name']) ?></strong></td>
              <td><?= htmlspecialchars($var['size']) ?></td>
              <td><code><?= htmlspecialchars($var['sku']) ?></code></td>
              <td>$<?= number_format((float)$var['price'], 2) ?></td>
              <td>
                <span style="font-weight:700; color:<?= $var['stock_qty'] <= 5 ? 'var(--admin-danger)' : 'var(--admin-warning)' ?>;">
                  <?= $var['stock_qty'] ?> jars
                </span>
              </td>
              <td>
                <span class="badge badge-<?= $var['stock_status'] === 'in_stock' ? 'completed' : 'cancelled' ?>">
                  <?= $var['stock_status'] === 'in_stock' ? 'In Stock' : 'Out of Stock' ?>
                </span>
              </td>
              <td>
                <a href="<?= htmlspecialchars(app_url('/admin/product-edit.php?id=' . $var['product_id']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="btn-admin" style="background:#f1f5f9; color:#1e293b;">
                  Edit Stock &rarr;
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
