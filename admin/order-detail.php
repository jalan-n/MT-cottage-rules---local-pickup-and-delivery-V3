<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/security.php';

$admin = require_admin_auth();
$db = get_db();

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    header('Location: ' . app_url('/admin/orders.php'));
    exit;
}

// Handle Status Update
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order_status') {
    require_csrf_token();
    $newStatus = trim((string)($_POST['order_status'] ?? ''));
    $validStatuses = ['new', 'preparing', 'ready', 'completed', 'cancelled'];
    if (in_array($newStatus, $validStatuses, true)) {
        $stmt = $db->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $orderId]);
        $flash = "Order status updated to " . ucfirst($newStatus) . ".";
    }
}

// Fetch Order and Customer Data
$stmt = $db->prepare("
    SELECT o.*, c.full_name, c.email, c.phone, c.street_address, c.unit, c.city, c.state, c.zip_code, c.created_at AS customer_since
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    WHERE o.id = ?
    LIMIT 1
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: ' . app_url('/admin/orders.php'));
    exit;
}

// Fetch Items
$stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();

$pageTitle = 'Order ' . $order['order_number'];
require_once __DIR__ . '/header.php';
?>

<div class="admin-page-header">
  <div>
    <a href="<?= htmlspecialchars(app_url('/admin/orders.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" style="color:#64748b; text-decoration:none; font-size:0.85rem;">&larr; Back to All Orders</a>
    <h1 class="admin-page-title" style="margin-top:0.35rem;">
      Order #<?= htmlspecialchars($order['order_number']) ?>
      <span class="badge badge-<?= htmlspecialchars($order['order_status']) ?>" style="font-size:0.9rem; vertical-align:middle; margin-left:0.5rem;">
        <?= ucfirst(str_replace('_', ' ', $order['order_status'])) ?>
      </span>
    </h1>
    <p class="admin-page-desc">Placed on <?= date('F j, Y \a\t g:i A', strtotime($order['created_at'])) ?></p>
  </div>

  <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
    <a href="<?= htmlspecialchars(app_url('/admin/packing-slip.php?order_id=' . $order['id']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" class="btn-primary-action">
      🖨️ Print Packing Slip
    </a>
  </div>
</div>

<?php if (!empty($flash)): ?>
  <div class="admin-alert admin-alert-success" role="status">
    <span>✅</span>
    <div><?= htmlspecialchars($flash) ?></div>
  </div>
<?php endif; ?>

<div class="form-grid" style="grid-template-columns: 2fr 1fr; margin-bottom:2rem;">
  <!-- Left Column: Item breakdown & Instructions -->
  <div>
    <div class="admin-card">
      <div class="admin-card-header">
        <h2 class="admin-card-title">Order Items</h2>
        <span style="font-size:0.85rem; color:#64748b;"><?= count($items) ?> Line Items</span>
      </div>

      <div class="admin-table-container">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Item & Flavor Details</th>
              <th>Type</th>
              <th>Qty</th>
              <th>Unit Price</th>
              <th style="text-align:right;">Line Total</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($item['item_title']) ?></strong>
                  <?php if (!empty($item['variant_details'])): ?>
                    <?php 
                      $details = json_decode($item['variant_details'], true);
                    ?>
                    <?php if (is_array($details)): ?>
                      <div style="margin-top:0.35rem; font-size:0.82rem; color:#475569; background:#f8fafc; padding:0.4rem 0.6rem; border-radius:4px; border:1px solid #e2e8f0;">
                        <?php if (isset($details['size'])): ?>
                          <div>Size: <strong><?= htmlspecialchars($details['size']) ?></strong> | SKU: <code><?= htmlspecialchars($details['sku'] ?? '') ?></code></div>
                        <?php endif; ?>
                        <?php if (isset($details['flavors']) && is_array($details['flavors'])): ?>
                          <div style="margin-top:2px;">
                            <strong>Selected Flavors:</strong>
                            <ul style="margin:2px 0 0 1rem; padding:0;">
                              <?php foreach ($details['flavors'] as $fl): ?>
                                <li><?= htmlspecialchars($fl) ?></li>
                              <?php endforeach; ?>
                            </ul>
                          </div>
                        <?php endif; ?>
                      </div>
                    <?php else: ?>
                      <div style="font-size:0.8rem; color:#64748b;"><?= htmlspecialchars($item['variant_details']) ?></div>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
                <td>
                  <span style="text-transform:uppercase; font-size:0.75rem; font-weight:700; color:#64748b;">
                    <?= htmlspecialchars($item['item_type']) ?>
                  </span>
                </td>
                <td><strong><?= (int)$item['quantity'] ?></strong></td>
                <td>$<?= number_format((float)$item['unit_price'], 2) ?></td>
                <td style="text-align:right;"><strong>$<?= number_format((float)$item['line_total'], 2) ?></strong></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="4" style="text-align:right; font-weight:600;">Items Subtotal:</td>
              <td style="text-align:right; font-weight:600;">$<?= number_format((float)$order['subtotal'], 2) ?></td>
            </tr>
            <tr>
              <td colspan="4" style="text-align:right; font-weight:600;">
                Delivery Fee (<?= $order['fulfillment_type'] === 'pickup' ? 'Farmstand Pickup' : 'Local Valley Delivery' ?>):
              </td>
              <td style="text-align:right; font-weight:600;">$<?= number_format((float)$order['delivery_fee'], 2) ?></td>
            </tr>
            <tr style="background:#f8fafc; font-size:1.05rem;">
              <td colspan="4" style="text-align:right; font-weight:700; color:var(--admin-primary-dark);">Grand Total Paid:</td>
              <td style="text-align:right; font-weight:700; color:var(--admin-primary-dark);">$<?= number_format((float)$order['total_amount'], 2) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <?php if (!empty($order['special_instructions'])): ?>
      <div class="admin-card" style="background:#fffbeb; border-color:#fef3c7;">
        <h3 style="font-size:0.95rem; font-weight:700; color:#92400e; margin-bottom:0.5rem;">📝 Customer Notes & Special Instructions:</h3>
        <p style="font-size:0.9rem; color:#78350f;"><?= nl2br(htmlspecialchars($order['special_instructions'])) ?></p>
      </div>
    <?php endif; ?>
  </div>

  <!-- Right Column: Status control, Customer, Square Info -->
  <div>
    <!-- Status Control -->
    <div class="admin-card">
      <h3 style="font-size:1.1rem; font-weight:700; color:var(--admin-primary-dark); margin-bottom:1rem;">Update Order Status</h3>
      <form method="POST" action="<?= htmlspecialchars(app_url('/admin/order-detail.php?id=' . $order['id']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
        <input type="hidden" name="action" value="update_order_status">

        <div class="form-group">
          <label for="order_status">Current Status</label>
          <select name="order_status" id="order_status">
            <option value="new" <?= $order['order_status'] === 'new' ? 'selected' : '' ?>>🔵 New</option>
            <option value="preparing" <?= $order['order_status'] === 'preparing' ? 'selected' : '' ?>>🟡 Preparing in Kitchen</option>
            <option value="ready" <?= $order['order_status'] === 'ready' ? 'selected' : '' ?>>
              🟣 <?= $order['fulfillment_type'] === 'pickup' ? 'Ready for Farmstand Pickup' : 'Out for Local Delivery' ?>
            </option>
            <option value="completed" <?= $order['order_status'] === 'completed' ? 'selected' : '' ?>>🟢 Completed / Fulfilled</option>
            <option value="cancelled" <?= $order['order_status'] === 'cancelled' ? 'selected' : '' ?>>🔴 Cancelled / Refunded</option>
          </select>
        </div>

        <button type="submit" class="btn-primary-action" style="width:100%; justify-content:center;">
          Update Status
        </button>
      </form>
    </div>

    <!-- Fulfillment Schedule -->
    <div class="admin-card">
      <h3 style="font-size:1.1rem; font-weight:700; color:var(--admin-primary-dark); margin-bottom:1rem;">Fulfillment Schedule</h3>
      <div style="font-size:0.88rem; display:flex; flex-direction:column; gap:0.5rem;">
        <div>
          <span style="color:#64748b;">Method:</span>
          <strong><?= $order['fulfillment_type'] === 'pickup' ? '📍 Farmstand Pickup' : '🚚 Local Valley Delivery' ?></strong>
        </div>
        <div>
          <span style="color:#64748b;">Scheduled Date:</span>
          <strong><?= htmlspecialchars($order['fulfillment_date']) ?></strong>
        </div>
        <div>
          <span style="color:#64748b;">Time Window:</span>
          <strong><?= htmlspecialchars($order['fulfillment_time_slot']) ?></strong>
        </div>
      </div>
    </div>

    <!-- Customer Card -->
    <div class="admin-card">
      <div class="admin-card-header" style="margin-bottom:0.75rem; padding-bottom:0.5rem;">
        <h3 style="font-size:1.1rem; font-weight:700; color:var(--admin-primary-dark);">Customer Details</h3>
        <a href="<?= htmlspecialchars(app_url('/admin/customer-detail.php?id=' . $order['customer_id']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" style="font-size:0.8rem; color:var(--admin-primary);">View History &rarr;</a>
      </div>

      <div style="font-size:0.88rem; display:flex; flex-direction:column; gap:0.5rem;">
        <div><strong><?= htmlspecialchars($order['full_name']) ?></strong></div>
        <div><a href="mailto:<?= htmlspecialchars($order['email']) ?>" style="color:var(--admin-primary);"><?= htmlspecialchars($order['email']) ?></a></div>
        <div><a href="tel:<?= htmlspecialchars($order['phone']) ?>" style="color:var(--admin-primary);"><?= htmlspecialchars($order['phone']) ?></a></div>
        <div style="padding-top:0.5rem; border-top:1px solid #e2e8f0; color:#475569;">
          <strong>Address:</strong><br>
          <?= htmlspecialchars($order['street_address']) ?>
          <?php if (!empty($order['unit'])): ?> #<?= htmlspecialchars($order['unit']) ?><?php endif; ?><br>
          <?= htmlspecialchars($order['city']) ?>, <?= htmlspecialchars($order['state']) ?> <?= htmlspecialchars($order['zip_code']) ?>
        </div>
      </div>
    </div>

    <!-- Payment Details -->
    <div class="admin-card">
      <h3 style="font-size:1.1rem; font-weight:700; color:var(--admin-primary-dark); margin-bottom:0.75rem;">Payment Reference</h3>
      <div style="font-size:0.82rem; display:flex; flex-direction:column; gap:0.4rem; color:#475569;">
        <div>Status: <span class="badge badge-<?= htmlspecialchars($order['payment_status']) ?>"><?= ucfirst($order['payment_status']) ?></span></div>
        <div>Method: <?= htmlspecialchars($order['payment_method']) ?></div>
        <?php if (!empty($order['square_payment_id'])): ?>
          <div>Payment ID: <code style="font-size:0.75rem;"><?= htmlspecialchars($order['square_payment_id']) ?></code></div>
        <?php endif; ?>
        <?php if (!empty($order['square_order_id'])): ?>
          <div>Square Order ID: <code style="font-size:0.75rem;"><?= htmlspecialchars($order['square_order_id']) ?></code></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
