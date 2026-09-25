<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/security.php';

$admin = require_admin_auth();
$db = get_db();

$customerId = (int)($_GET['id'] ?? 0);
if ($customerId <= 0) {
    header('Location: ' . app_url('/admin/customers.php'));
    exit;
}

// Fetch Customer
$stmt = $db->prepare("SELECT * FROM customers WHERE id = ? LIMIT 1");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    header('Location: ' . app_url('/admin/customers.php'));
    exit;
}

// Fetch all orders for this customer
$stmt = $db->prepare("
    SELECT o.*, 
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
    FROM orders o
    WHERE o.customer_id = ?
    ORDER BY o.id DESC
");
$stmt->execute([$customerId]);
$orders = $stmt->fetchAll();

// Lifetime metrics
$lifetimeSpend = 0.0;
$totalJarsCount = 0;
foreach ($orders as $ord) {
    if ($ord['payment_status'] === 'paid') {
        $lifetimeSpend += (float)$ord['total_amount'];
    }
}

$pageTitle = 'Customer: ' . $customer['full_name'];
require_once __DIR__ . '/header.php';
?>

<div class="admin-page-header">
  <div>
    <a href="<?= htmlspecialchars(app_url('/admin/customers.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" style="color:#64748b; text-decoration:none; font-size:0.85rem;">&larr; Back to Customers</a>
    <h1 class="admin-page-title" style="margin-top:0.35rem;">
      <?= htmlspecialchars($customer['full_name']) ?>
    </h1>
    <p class="admin-page-desc">Customer profile, addresses, and complete purchase history.</p>
  </div>
</div>

<div class="stat-cards-grid" style="margin-bottom:2rem;">
  <div class="stat-card">
    <div class="stat-card-title">Lifetime Spend</div>
    <div class="stat-card-value">$<?= number_format($lifetimeSpend, 2) ?></div>
    <div class="stat-card-note">Total completed purchases</div>
  </div>

  <div class="stat-card">
    <div class="stat-card-title">Orders Placed</div>
    <div class="stat-card-value"><?= count($orders) ?></div>
    <div class="stat-card-note">First ordered on <?= date('M j, Y', strtotime($customer['created_at'])) ?></div>
  </div>

  <div class="stat-card">
    <div class="stat-card-title">Average Order Value</div>
    <div class="stat-card-value">
      $<?= count($orders) > 0 ? number_format($lifetimeSpend / count($orders), 2) : '0.00' ?>
    </div>
    <div class="stat-card-note">Per checkout transaction</div>
  </div>
</div>

<div class="form-grid" style="grid-template-columns: 1fr 2fr; gap:1.75rem;">
  <!-- Left: Customer Profile & Addresses -->
  <div>
    <div class="admin-card">
      <h3 style="font-size:1.15rem; font-weight:700; color:var(--admin-primary-dark); margin-bottom:1rem;">Customer Profile</h3>
      
      <div style="display:flex; flex-direction:column; gap:0.75rem; font-size:0.9rem;">
        <div>
          <span style="color:#64748b; font-size:0.8rem; text-transform:uppercase; font-weight:600;">Full Name</span>
          <div style="font-weight:600; font-size:1.05rem;"><?= htmlspecialchars($customer['full_name']) ?></div>
        </div>

        <div>
          <span style="color:#64748b; font-size:0.8rem; text-transform:uppercase; font-weight:600;">Email Address</span>
          <div><a href="mailto:<?= htmlspecialchars($customer['email']) ?>" style="color:var(--admin-primary);"><?= htmlspecialchars($customer['email']) ?></a></div>
        </div>

        <div>
          <span style="color:#64748b; font-size:0.8rem; text-transform:uppercase; font-weight:600;">Phone Number</span>
          <div><a href="tel:<?= htmlspecialchars($customer['phone']) ?>" style="color:var(--admin-primary);"><?= htmlspecialchars($customer['phone']) ?></a></div>
        </div>

        <div style="padding-top:0.75rem; border-top:1px solid #e2e8f0;">
          <span style="color:#64748b; font-size:0.8rem; text-transform:uppercase; font-weight:600;">Saved Delivery Address</span>
          <div style="margin-top:0.25rem; line-height:1.4;">
            <?= htmlspecialchars($customer['street_address']) ?>
            <?php if (!empty($customer['unit'])): ?> #<?= htmlspecialchars($customer['unit']) ?><?php endif; ?><br>
            <?= htmlspecialchars($customer['city']) ?>, <?= htmlspecialchars($customer['state']) ?> <?= htmlspecialchars($customer['zip_code']) ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Right: Purchase History Table -->
  <div>
    <div class="admin-card">
      <div class="admin-card-header">
        <h3 class="admin-card-title">Order History (<?= count($orders) ?>)</h3>
        <span style="font-size:0.85rem; color:#64748b;">All historical transactions</span>
      </div>

      <?php if (empty($orders)): ?>
        <p style="color:#64748b; padding:1.5rem 0;">No orders found for this customer.</p>
      <?php else: ?>
        <div class="admin-table-container">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Order #</th>
                <th>Date</th>
                <th>Fulfillment</th>
                <th>Total</th>
                <th>Payment</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orders as $ord): ?>
                <tr>
                  <td>
                    <a href="<?= htmlspecialchars(app_url('/admin/order-detail.php?id=' . $ord['id']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" style="font-weight:700; color:var(--admin-primary); text-decoration:none;">
                      <?= htmlspecialchars($ord['order_number']) ?>
                    </a>
                  </td>
                  <td>
                    <?= date('M j, Y', strtotime($ord['created_at'])) ?>
                  </td>
                  <td>
                    <span class="badge badge-<?= htmlspecialchars($ord['fulfillment_type']) ?>">
                      <?= $ord['fulfillment_type'] === 'pickup' ? '📍 Pickup' : '🚚 Delivery' ?>
                    </span>
                  </td>
                  <td>
                    <strong>$<?= number_format((float)$ord['total_amount'], 2) ?></strong>
                  </td>
                  <td>
                    <span class="badge badge-<?= htmlspecialchars($ord['payment_status']) ?>">
                      <?= ucfirst($ord['payment_status']) ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge badge-<?= htmlspecialchars($ord['order_status']) ?>">
                      <?= ucfirst(str_replace('_', ' ', $ord['order_status'])) ?>
                    </span>
                  </td>
                  <td>
                    <div style="display:flex; gap:0.4rem;">
                      <a href="<?= htmlspecialchars(app_url('/admin/order-detail.php?id=' . $ord['id']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="btn-admin" style="background:#f1f5f9; color:#1e293b;">
                        View
                      </a>
                      <a href="<?= htmlspecialchars(app_url('/admin/packing-slip.php?order_id=' . $ord['id']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" class="btn-admin" style="background:#e0e7ff; color:#3730a3;" title="Print Slip">
                        🖨️
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
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
