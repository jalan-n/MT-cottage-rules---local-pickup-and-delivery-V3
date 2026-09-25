<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/security.php';

$admin = require_admin_auth();
$db = get_db();

// Search filter
$q = trim((string)($_GET['q'] ?? ''));

$whereSql = '';
$params = [];
if (!empty($q)) {
    $whereSql = "WHERE (c.full_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.city LIKE ? OR c.zip_code LIKE ?)";
    $like = '%' . $q . '%';
    $params = [$like, $like, $like, $like, $like];
}

$sql = "
    SELECT 
        c.*,
        COUNT(o.id) AS total_orders,
        COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_amount ELSE 0 END), 0) AS lifetime_spend,
        MAX(o.created_at) AS last_order_date
    FROM customers c
    LEFT JOIN orders o ON c.id = o.customer_id
    {$whereSql}
    GROUP BY c.id
    ORDER BY lifetime_spend DESC, total_orders DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$pageTitle = 'Customer Directory';
require_once __DIR__ . '/header.php';
?>

<div class="admin-page-header">
  <div>
    <h1 class="admin-page-title">👥 Customer Directory</h1>
    <p class="admin-page-desc">Customer order history, delivery addresses, and lifetime spend across the Flathead Valley.</p>
  </div>
</div>

<div class="admin-card" style="padding:1.25rem; margin-bottom:1.5rem;">
  <form method="GET" action="<?= htmlspecialchars(app_url('/admin/customers.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-filter-bar">
    <input 
      type="text" 
      name="q" 
      class="admin-filter-input" 
      placeholder="Search name, email, phone, city, or ZIP..." 
      value="<?= htmlspecialchars($q) ?>"
      style="min-width: 320px;"
    >
    <button type="submit" class="btn-primary-action" style="padding:0.5rem 1rem;">Search</button>
    <?php if (!empty($q)): ?>
      <a href="<?= htmlspecialchars(app_url('/admin/customers.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="btn-secondary-action" style="padding:0.5rem 0.85rem;">Clear</a>
    <?php endif; ?>
  </form>
</div>

<div class="admin-card">
  <div class="admin-card-header">
    <h2 class="admin-card-title">Customers (<?= count($customers) ?>)</h2>
    <span style="font-size:0.85rem; color:#64748b;">Sorted by lifetime spend</span>
  </div>

  <?php if (empty($customers)): ?>
    <div style="text-align:center; padding:3rem; color:#64748b;">
      <p>No customers found matching your search query.</p>
    </div>
  <?php else: ?>
    <div class="admin-table-container">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Customer Name</th>
            <th>Contact Email & Phone</th>
            <th>Primary Address</th>
            <th style="text-align:center;">Orders</th>
            <th>Lifetime Spend</th>
            <th>Last Purchase</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($customers as $c): ?>
            <tr>
              <td>
                <a href="<?= htmlspecialchars(app_url('/admin/customer-detail.php?id=' . $c['id']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" style="font-weight:700; color:var(--admin-primary); text-decoration:none;">
                  <?= htmlspecialchars($c['full_name']) ?>
                </a>
              </td>
              <td>
                <div><a href="mailto:<?= htmlspecialchars($c['email']) ?>" style="color:inherit;"><?= htmlspecialchars($c['email']) ?></a></div>
                <div style="font-size:0.78rem; color:#64748b;"><a href="tel:<?= htmlspecialchars($c['phone']) ?>" style="color:inherit;"><?= htmlspecialchars($c['phone']) ?></a></div>
              </td>
              <td>
                <div><?= htmlspecialchars($c['street_address']) ?></div>
                <div style="font-size:0.78rem; color:#64748b;"><?= htmlspecialchars($c['city']) ?>, <?= htmlspecialchars($c['state']) ?> <?= htmlspecialchars($c['zip_code']) ?></div>
              </td>
              <td style="text-align:center;">
                <span class="badge" style="background:#f1f5f9; color:#334155; font-size:0.85rem; font-weight:700;">
                  <?= (int)$c['total_orders'] ?>
                </span>
              </td>
              <td>
                <strong style="color:var(--admin-primary-dark); font-size:0.95rem;">
                  $<?= number_format((float)$c['lifetime_spend'], 2) ?>
                </strong>
              </td>
              <td>
                <?= !empty($c['last_order_date']) ? date('M j, Y', strtotime($c['last_order_date'])) : 'Never' ?>
              </td>
              <td>
                <a href="<?= htmlspecialchars(app_url('/admin/customer-detail.php?id=' . $c['id']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="btn-admin" style="background:#f1f5f9; color:#1e293b;">
                  View History &rarr;
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
