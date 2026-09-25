<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/security.php';

$admin = require_admin_auth();
$db = get_db();

// Handle Status Updates via POST
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    require_csrf_token();
    $orderId = (int)($_POST['order_id'] ?? 0);
    $newStatus = trim((string)($_POST['order_status'] ?? ''));

    $validStatuses = ['new', 'preparing', 'ready', 'completed', 'cancelled'];
    if ($orderId > 0 && in_array($newStatus, $validStatuses, true)) {
        $stmt = $db->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $orderId]);
        $flash = "Order status successfully updated to " . ucfirst($newStatus) . ".";
    }
}

// Filters
$filterFulfillment = trim((string)($_GET['fulfillment'] ?? 'all'));
$filterPayment     = trim((string)($_GET['payment'] ?? 'all'));
$filterStatus      = trim((string)($_GET['status'] ?? 'all'));
$searchQuery       = trim((string)($_GET['q'] ?? ''));

// Construct dynamic query
$whereClauses = [];
$params = [];

if ($filterFulfillment !== 'all' && in_array($filterFulfillment, ['pickup', 'delivery'], true)) {
    $whereClauses[] = "o.fulfillment_type = ?";
    $params[] = $filterFulfillment;
}

if ($filterPayment !== 'all' && in_array($filterPayment, ['paid', 'pending', 'failed'], true)) {
    $whereClauses[] = "o.payment_status = ?";
    $params[] = $filterPayment;
}

if ($filterStatus !== 'all' && in_array($filterStatus, ['new', 'preparing', 'ready', 'completed', 'cancelled'], true)) {
    $whereClauses[] = "o.order_status = ?";
    $params[] = $filterStatus;
}

if (!empty($searchQuery)) {
    $whereClauses[] = "(o.order_number LIKE ? OR c.full_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
    $like = '%' . $searchQuery . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$sql = "
    SELECT o.*, c.full_name, c.email, c.phone, c.street_address, c.city, c.zip_code
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    {$whereSql}
    ORDER BY o.id DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Count totals for notification headers
$totalCount = count($orders);
$newCount = (int)$db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'new'")->fetchColumn();

$pageTitle = 'Order Management';
require_once __DIR__ . '/header.php';
?>

<div class="admin-page-header">
  <div>
    <h1 class="admin-page-title">
      📦 Order Management
      <?php if ($newCount > 0): ?>
        <span class="badge badge-new" style="font-size:0.9rem; vertical-align:middle; margin-left:0.5rem;">
          <?= $newCount ?> New Pending
        </span>
      <?php endif; ?>
    </h1>
    <p class="admin-page-desc">Track, filter, and advance local farmstand pickup and valley delivery orders.</p>
  </div>

  <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
    <a href="<?= htmlspecialchars(app_url('/admin/packing-slip.php?digest=weekly'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" class="btn-secondary-action" title="Print all orders for the upcoming 7 days">
      🖨️ Weekly Packing Digest
    </a>
    <a href="<?= htmlspecialchars(app_url('/admin/packing-slip.php?digest=monthly'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" class="btn-secondary-action" title="Print all orders for the current month">
      🖨️ Monthly Digest
    </a>
  </div>
</div>

<?php if (!empty($flash)): ?>
  <div class="admin-alert admin-alert-success" role="status">
    <span>✅</span>
    <div><?= htmlspecialchars($flash) ?></div>
  </div>
<?php endif; ?>

<!-- Filter & Search Bar -->
<div class="admin-card" style="padding:1.25rem; margin-bottom:1.5rem;">
  <form method="GET" action="<?= htmlspecialchars(app_url('/admin/orders.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-filter-bar">
    <input 
      type="text" 
      name="q" 
      class="admin-filter-input" 
      placeholder="Search Order # or Customer..." 
      value="<?= htmlspecialchars($searchQuery) ?>"
      style="min-width: 240px;"
    >

    <select name="fulfillment" class="admin-filter-select">
      <option value="all" <?= $filterFulfillment === 'all' ? 'selected' : '' ?>>All Fulfillment (Pickup & Delivery)</option>
      <option value="pickup" <?= $filterFulfillment === 'pickup' ? 'selected' : '' ?>>📍 Farmstand Pickup</option>
      <option value="delivery" <?= $filterFulfillment === 'delivery' ? 'selected' : '' ?>>🚚 Local Delivery</option>
    </select>

    <select name="status" class="admin-filter-select">
      <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Order Statuses</option>
      <option value="new" <?= $filterStatus === 'new' ? 'selected' : '' ?>>New</option>
      <option value="preparing" <?= $filterStatus === 'preparing' ? 'selected' : '' ?>>Preparing</option>
      <option value="ready" <?= $filterStatus === 'ready' ? 'selected' : '' ?>>Ready for Pickup / Out for Delivery</option>
      <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
      <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
    </select>

    <select name="payment" class="admin-filter-select">
      <option value="all" <?= $filterPayment === 'all' ? 'selected' : '' ?>>All Payments</option>
      <option value="paid" <?= $filterPayment === 'paid' ? 'selected' : '' ?>>Paid (Square)</option>
      <option value="pending" <?= $filterPayment === 'pending' ? 'selected' : '' ?>>Pending</option>
      <option value="failed" <?= $filterPayment === 'failed' ? 'selected' : '' ?>>Failed</option>
    </select>

    <button type="submit" class="btn-primary-action" style="padding:0.5rem 1rem;">Filter</button>
    <?php if ($filterFulfillment !== 'all' || $filterPayment !== 'all' || $filterStatus !== 'all' || !empty($searchQuery)): ?>
      <a href="<?= htmlspecialchars(app_url('/admin/orders.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="btn-secondary-action" style="padding:0.5rem 0.85rem;">Clear</a>
    <?php endif; ?>
  </form>
</div>

<!-- Orders Table -->
<div class="admin-card">
  <div class="admin-card-header">
    <h2 class="admin-card-title">Order Records (<?= count($orders) ?>)</h2>
    <span style="font-size:0.85rem; color:#64748b;">Live kitchen updates</span>
  </div>

  <?php if (empty($orders)): ?>
    <div style="text-align:center; padding:3rem 1rem; color:#64748b;">
      <div style="font-size:2.5rem; margin-bottom:0.5rem;">🔍</div>
      <p>No orders match the selected filters.</p>
    </div>
  <?php else: ?>
    <div class="admin-table-container">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Customer</th>
            <th>Type</th>
            <th>Fulfillment Date</th>
            <th>Time Window</th>
            <th>Total</th>
            <th>Payment</th>
            <th>Kitchen Status Toggle</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td>
                <a href="<?= htmlspecialchars(app_url('/admin/order-detail.php?id=' . $o['id']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" style="font-weight:700; color:var(--admin-primary); text-decoration:none;">
                  <?= htmlspecialchars($o['order_number']) ?>
                </a>
                <div style="font-size:0.75rem; color:#64748b; margin-top:2px;">
                  <?= date('M j, Y g:ia', strtotime($o['created_at'])) ?>
                </div>
              </td>
              <td>
                <div style="font-weight:600;"><?= htmlspecialchars($o['full_name']) ?></div>
                <div style="font-size:0.78rem; color:#64748b;"><?= htmlspecialchars($o['phone']) ?></div>
                <div style="font-size:0.78rem; color:#64748b;"><?= htmlspecialchars($o['city']) ?>, <?= htmlspecialchars($o['zip_code']) ?></div>
              </td>
              <td>
                <span class="badge badge-<?= htmlspecialchars($o['fulfillment_type']) ?>">
                  <?= $o['fulfillment_type'] === 'pickup' ? '📍 Pickup' : '🚚 Delivery' ?>
                </span>
              </td>
              <td>
                <strong><?= htmlspecialchars($o['fulfillment_date']) ?></strong>
              </td>
              <td>
                <div style="font-size:0.82rem; color:#334155;"><?= htmlspecialchars($o['fulfillment_time_slot']) ?></div>
              </td>
              <td>
                <strong>$<?= number_format((float)$o['total_amount'], 2) ?></strong>
              </td>
              <td>
                <span class="badge badge-<?= htmlspecialchars($o['payment_status']) ?>">
                  <?= ucfirst($o['payment_status']) ?>
                </span>
              </td>
              <td>
                <!-- Quick Status Toggle Form with CSRF -->
                <form method="POST" action="<?= htmlspecialchars(app_url('/admin/orders.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" style="display:inline-flex; align-items:center; gap:0.35rem;">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="order_id" value="<?= $o['id'] ?>">

                  <select name="order_status" onchange="this.form.submit()" class="admin-filter-select" style="padding:0.35rem 0.6rem; font-size:0.82rem; font-weight:600;">
                    <option value="new" <?= $o['order_status'] === 'new' ? 'selected' : '' ?>>🔵 New</option>
                    <option value="preparing" <?= $o['order_status'] === 'preparing' ? 'selected' : '' ?>>🟡 Preparing</option>
                    <option value="ready" <?= $o['order_status'] === 'ready' ? 'selected' : '' ?>>
                      <?= $o['fulfillment_type'] === 'pickup' ? '🟣 Ready for Pickup' : '🟣 Out for Delivery' ?>
                    </option>
                    <option value="completed" <?= $o['order_status'] === 'completed' ? 'selected' : '' ?>>🟢 Completed</option>
                    <option value="cancelled" <?= $o['order_status'] === 'cancelled' ? 'selected' : '' ?>>🔴 Cancelled</option>
                  </select>
                </form>
              </td>
              <td>
                <div style="display:flex; gap:0.4rem;">
                  <a href="<?= htmlspecialchars(app_url('/admin/order-detail.php?id=' . $o['id']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="btn-admin" style="background:#f1f5f9; color:#1e293b;" title="View Order Breakdown">
                    Details
                  </a>
                  <a href="<?= htmlspecialchars(app_url('/admin/packing-slip.php?order_id=' . $o['id']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" class="btn-admin" style="background:#e0e7ff; color:#3730a3;" title="Print Packing Slip">
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

<?php require_once __DIR__ . '/footer.php'; ?>
