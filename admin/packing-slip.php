<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/security.php';

$admin = require_admin_auth();
$db = get_db();

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$digest = isset($_GET['digest']) ? trim((string)$_GET['digest']) : '';

$ordersToPrint = [];

if ($orderId > 0) {
    // Single order
    $stmt = $db->prepare("
        SELECT o.*, c.full_name, c.email, c.phone, c.street_address, c.unit, c.city, c.state, c.zip_code
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        WHERE o.id = ?
        LIMIT 1
    ");
    $stmt->execute([$orderId]);
    $o = $stmt->fetch();
    if ($o) {
        $ordersToPrint[] = $o;
    }
} elseif ($digest === 'weekly') {
    // Next 7 days orders
    $stmt = $db->query("
        SELECT o.*, c.full_name, c.email, c.phone, c.street_address, c.unit, c.city, c.state, c.zip_code
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        WHERE o.order_status IN ('new', 'preparing', 'ready')
        ORDER BY o.fulfillment_date ASC, o.id ASC
    ");
    $ordersToPrint = $stmt->fetchAll();
} elseif ($digest === 'monthly') {
    // Current month orders
    $stmt = $db->query("
        SELECT o.*, c.full_name, c.email, c.phone, c.street_address, c.unit, c.city, c.state, c.zip_code
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        ORDER BY o.id DESC
        LIMIT 50
    ");
    $ordersToPrint = $stmt->fetchAll();
}

$siteSettings = get_site_settings();
$brandName = $siteSettings['brand_name'] ?? "Shelly's Jellys LLC";
$pickupAddress = $siteSettings['pickup_address'] ?? "The Jam Kitchen & Farmstand, 458 Orchard Vista Way, Kalispell, MT 59901";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Packing Slips | <?= htmlspecialchars($brandName) ?></title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
      color: #0f172a;
      background: #f8fafc;
      padding: 1.5rem;
      font-size: 13px;
      line-height: 1.4;
    }
    .toolbar {
      max-width: 800px;
      margin: 0 auto 1.5rem auto;
      background: #ffffff;
      padding: 1rem 1.5rem;
      border-radius: 8px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .btn-print {
      background: #134e3f;
      color: #ffffff;
      border: none;
      padding: 0.6rem 1.25rem;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
    }
    .slip-container {
      max-width: 800px;
      margin: 0 auto 2rem auto;
      background: #ffffff;
      border: 1px solid #cbd5e1;
      padding: 2.5rem;
      border-radius: 6px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
      page-break-after: always;
    }
    .slip-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      border-bottom: 2px solid #0f172a;
      padding-bottom: 1.25rem;
      margin-bottom: 1.5rem;
    }
    .brand-title {
      font-size: 20px;
      font-weight: 800;
      color: #134e3f;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .slip-meta {
      text-align: right;
      font-size: 12px;
      color: #475569;
    }
    .order-number {
      font-size: 18px;
      font-weight: 800;
      color: #0f172a;
    }
    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
      margin-bottom: 1.5rem;
      background: #f8fafc;
      padding: 1rem;
      border-radius: 6px;
      border: 1px solid #e2e8f0;
    }
    .info-block h4 {
      font-size: 11px;
      text-transform: uppercase;
      color: #64748b;
      margin-bottom: 0.35rem;
      letter-spacing: 0.05em;
    }
    .items-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 1.5rem;
    }
    .items-table th {
      background: #f1f5f9;
      border-bottom: 2px solid #cbd5e1;
      padding: 0.6rem 0.75rem;
      text-align: left;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .items-table td {
      border-bottom: 1px solid #e2e8f0;
      padding: 0.65rem 0.75rem;
      vertical-align: top;
    }
    .checklist-box {
      width: 16px;
      height: 16px;
      border: 1.5px solid #0f172a;
      display: inline-block;
      vertical-align: middle;
      margin-right: 0.4rem;
    }
    .flavor-tag {
      font-size: 11px;
      color: #475569;
      margin-top: 3px;
    }
    .slip-footer {
      border-top: 1px dashed #94a3b8;
      padding-top: 1rem;
      display: flex;
      justify-content: space-between;
      font-size: 11px;
      color: #64748b;
    }

    @media print {
      body {
        background: #ffffff;
        padding: 0;
      }
      .toolbar {
        display: none !important;
      }
      .slip-container {
        border: none;
        box-shadow: none;
        padding: 0;
        margin: 0;
        page-break-after: always;
      }
    }
  </style>
</head>
<body>

<div class="toolbar">
  <div>
    <strong>Kitchen Packing Slips</strong> &bull; 
    <?= count($ordersToPrint) ?> Order(s) Selected
    <?php if ($digest): ?> (<?= ucfirst($digest) ?> Digest)<?php endif; ?>
  </div>
  <div>
    <button onclick="window.print()" class="btn-print">🖨️ Print Packing Slip(s)</button>
    <a href="<?= htmlspecialchars(app_url('/admin/orders.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" style="margin-left:1rem; color:#64748b; text-decoration:none; font-size:13px;">&larr; Back</a>
  </div>
</div>

<?php if (empty($ordersToPrint)): ?>
  <div style="text-align:center; padding:3rem; background:#fff; max-width:800px; margin:0 auto; border-radius:8px;">
    <h3>No Orders Found</h3>
    <p style="color:#64748b; margin-top:0.5rem;">No active orders match the requested printing parameters.</p>
  </div>
<?php else: ?>
  <?php foreach ($ordersToPrint as $order): ?>
    <?php
      $stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
      $stmt->execute([$order['id']]);
      $items = $stmt->fetchAll();
    ?>
    <div class="slip-container">
      <div class="slip-header">
        <div>
          <div class="brand-title">🍓 <?= htmlspecialchars($brandName) ?></div>
          <div style="font-size:12px; color:#475569; margin-top:2px;">Handcrafted Small Batch Artisan Jams</div>
        </div>
        <div class="slip-meta">
          <div class="order-number"><?= htmlspecialchars($order['order_number']) ?></div>
          <div>Ordered: <?= date('M j, Y g:ia', strtotime($order['created_at'])) ?></div>
          <div>Payment: <strong><?= strtoupper($order['payment_status']) ?></strong> (<?= htmlspecialchars($order['payment_method']) ?>)</div>
        </div>
      </div>

      <div class="info-grid">
        <div class="info-block">
          <h4>Customer Information</h4>
          <strong><?= htmlspecialchars($order['full_name']) ?></strong><br>
          Phone: <?= htmlspecialchars($order['phone']) ?><br>
          Email: <?= htmlspecialchars($order['email']) ?><br>
          <?php if ($order['fulfillment_type'] === 'delivery'): ?>
            <div style="margin-top:0.4rem; padding-top:0.4rem; border-top:1px dashed #cbd5e1;">
              <strong>Delivery Address:</strong><br>
              <?= htmlspecialchars($order['street_address']) ?>
              <?php if (!empty($order['unit'])): ?> #<?= htmlspecialchars($order['unit']) ?><?php endif; ?><br>
              <?= htmlspecialchars($order['city']) ?>, <?= htmlspecialchars($order['state']) ?> <?= htmlspecialchars($order['zip_code']) ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="info-block">
          <h4>Fulfillment Schedule</h4>
          <div style="font-size:14px; font-weight:700; color:#134e3f;">
            <?= $order['fulfillment_type'] === 'pickup' ? '📍 FARMSTAND PICKUP' : '🚚 LOCAL VALLEY DELIVERY' ?>
          </div>
          <div style="margin-top:0.35rem;">
            <strong>Scheduled Date:</strong> <?= htmlspecialchars($order['fulfillment_date']) ?><br>
            <strong>Time Window:</strong> <?= htmlspecialchars($order['fulfillment_time_slot']) ?>
          </div>
          <?php if ($order['fulfillment_type'] === 'pickup'): ?>
            <div style="margin-top:0.4rem; font-size:11px; color:#64748b;">
              Pickup: <?= htmlspecialchars($pickupAddress) ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!empty($order['special_instructions'])): ?>
        <div style="background:#fffbeb; border:1px solid #fef3c7; padding:0.6rem 0.85rem; border-radius:4px; margin-bottom:1.25rem;">
          <strong style="color:#92400e;">Customer Instructions:</strong> 
          <span style="color:#78350f;"><?= htmlspecialchars($order['special_instructions']) ?></span>
        </div>
      <?php endif; ?>

      <table class="items-table">
        <thead>
          <tr>
            <th style="width:40px;">Pack</th>
            <th>Item / Flavor Selection</th>
            <th style="width:90px; text-align:center;">Size</th>
            <th style="width:60px; text-align:center;">Qty</th>
            <th style="width:90px; text-align:right;">Line Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $it): ?>
            <?php 
              $details = json_decode($it['variant_details'] ?? '', true);
            ?>
            <tr>
              <td style="text-align:center;">
                <span class="checklist-box"></span>
              </td>
              <td>
                <strong><?= htmlspecialchars($it['item_title']) ?></strong>
                <?php if (is_array($details) && isset($details['flavors']) && is_array($details['flavors'])): ?>
                  <div class="flavor-tag">
                    Assorted Flavors: <?= implode(' &bull; ', array_map('htmlspecialchars', $details['flavors'])) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td style="text-align:center;">
                <?= htmlspecialchars($details['size'] ?? 'Standard') ?>
              </td>
              <td style="text-align:center; font-weight:700;">
                <?= (int)$it['quantity'] ?>
              </td>
              <td style="text-align:right;">
                $<?= number_format((float)$it['line_total'], 2) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="slip-footer">
        <div>
          Packed By: ___________________ &bull; Quality Checked: [ ]
        </div>
        <div>
          Total Order: <strong>$<?= number_format((float)$order['total_amount'], 2) ?></strong>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
