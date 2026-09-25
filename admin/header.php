<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/security.php';

$admin = require_admin_auth();
$db = get_db();

// Count incoming new orders for badge
$newOrdersCount = 0;
try {
    $newOrdersCount = (int)$db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'new'")->fetchColumn();
} catch (Exception $e) {
    // If orders table not yet populated
}

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$siteSettings = get_site_settings();
$brandName = $siteSettings['brand_name'] ?? "Shelly's Jellys LLC";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Admin Dashboard') ?> | <?= htmlspecialchars($brandName) ?></title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="<?= htmlspecialchars(app_url('/public/css/admin.css'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
  <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars(app_url('/public/assets/images/logo.svg'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
  <meta name="csrf-token" content="<?= htmlspecialchars(get_csrf_token()) ?>">
</head>
<body class="admin-body">

<header class="admin-header">
  <div class="admin-topbar">
    <a href="<?= htmlspecialchars(app_url('/admin/index.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-brand">
      <div class="admin-brand-logo">🍓</div>
      <div>
        <div class="admin-brand-name"><?= htmlspecialchars($brandName) ?></div>
        <span class="admin-badge-role">Store Manager</span>
      </div>
    </a>

    <div class="admin-header-actions">
      <!-- One-Click SSG Rebuild Button -->
      <form action="<?= htmlspecialchars(app_url('/admin/rebuild.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" method="POST" style="display:inline;" onsubmit="return confirm('Recompile entire static site from database now?');">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
        <button type="submit" class="btn-admin btn-admin-rebuild" title="Regenerate all public static HTML files with latest database content">
          ⚡ One-Click Rebuild
        </button>
      </form>

      <a href="<?= htmlspecialchars(app_url('/public/index.html'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn-admin btn-admin-storefront" title="Open public storefront in new tab">
        🌐 View Storefront ↗
      </a>

      <div style="font-size:0.82rem; color:rgba(255,255,255,0.7); margin-left:0.5rem;">
        👤 <strong><?= htmlspecialchars($admin['username']) ?></strong>
      </div>

      <a href="<?= htmlspecialchars(app_url('/admin/logout.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="btn-admin btn-admin-logout" title="Sign out of admin session">
        Sign Out
      </a>
    </div>
  </div>

  <nav class="admin-navbar">
    <div class="admin-nav-inner">
      <a href="<?= htmlspecialchars(app_url('/admin/index.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-nav-link <?= $currentPage === 'index' ? 'active' : '' ?>">
        📊 Overview
      </a>
      <a href="<?= htmlspecialchars(app_url('/admin/orders.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-nav-link <?= in_array($currentPage, ['orders', 'order-detail', 'packing-slip']) ? 'active' : '' ?>">
        📦 Orders
        <?php if ($newOrdersCount > 0): ?>
          <span class="admin-nav-counter"><?= $newOrdersCount ?></span>
        <?php endif; ?>
      </a>
      <a href="<?= htmlspecialchars(app_url('/admin/products.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-nav-link <?= in_array($currentPage, ['products', 'product-edit']) ? 'active' : '' ?>">
        🍯 Products & Inventory
      </a>
      <a href="<?= htmlspecialchars(app_url('/admin/customers.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-nav-link <?= in_array($currentPage, ['customers', 'customer-detail']) ? 'active' : '' ?>">
        👥 Customers
      </a>
      <a href="<?= htmlspecialchars(app_url('/admin/settings.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-nav-link <?= $currentPage === 'settings' ? 'active' : '' ?>">
        ⚙️ UI Settings
      </a>
      <a href="<?= htmlspecialchars(app_url('/admin/assets.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-nav-link <?= $currentPage === 'assets' ? 'active' : '' ?>">
        📁 Asset Manager
      </a>
    </div>
  </nav>
</header>

<main class="admin-main">
