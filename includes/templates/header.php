<?php
declare(strict_types=1);
/**
 * Shared SSG Header Template
 * Matches design: Top burgundy promo bar, golden mustard header with cursive Shelly's Jellys logo,
 * centered live keyword search, dynamic Basket counter, and horizontal navigation bar.
 */
$brandName      = $settings['brand_name'] ?? "Shelly's Jellys LLC";
$promoText      = $settings['promo_banner_text'] ?? 'Promotional deal! 15% off orders over $100!';
$promoActive    = ($settings['promo_banner_active'] ?? '1') === '1';
$currentUrl     = $canonical ?? '/';
$assetBase      = getenv('APP_ASSET_BASE') ?: '/SJ-cottage-food/public';
$assetBase      = rtrim($assetBase, '/');
$seoTitle       = !empty($title) ? $this->capString($title, 60) ?? $title : "Shelly's Jellys LLC | Gourmet Homemade Jams";
$seoDescription = !empty($description) ? $this->capString($description, 160) ?? $description : 'Small-batch artisanal gourmet homemade jams. More fruit, less sugar, no preservatives. Local delivery & farmstand pickup.';
$brandJson = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'LocalBusiness',
    'name' => $brandName,
    'image' => '/assets/images/jam-jar-hero.svg',
    'telephone' => '(406) 555-0192',
    'email' => 'mtshellysjellys@gmail.com',
    'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => '458 Orchard Vista Way',
        'addressLocality' => 'Kalispell',
        'addressRegion' => 'MT',
        'postalCode' => '59901',
        'addressCountry' => 'US'
    ],
    'openingHours' => 'Tu-Sa 10:00-18:00',
    'priceRange' => '$$'
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$csrfToken = function_exists('get_csrf_token') ? get_csrf_token() : '';
$seoTitleEscaped = function_exists('e') ? e($seoTitle) : htmlspecialchars((string)$seoTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$seoDescriptionEscaped = function_exists('e') ? e($seoDescription) : htmlspecialchars((string)$seoDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$currentUrlEscaped = function_exists('e') ? e($currentUrl) : htmlspecialchars((string)$currentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$brandNameEscaped = function_exists('e') ? e($brandName) : htmlspecialchars((string)$brandName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$productCatalog = [];
foreach ($products as $productRow) {
    $productRowSlug = (string)($productRow['slug'] ?? $productRow['name'] ?? '');
    $productCatalog[] = [
        'name' => (string)($productRow['name'] ?? ''),
        'slug' => canonical_product_slug($productRowSlug),
        'description' => (string)($productRow['description'] ?? ''),
        'image' => normalize_product_image_url((string)($productRow['image_url'] ?? ''), $productRowSlug),
        'price' => '$9.00 - $17.00'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= function_exists('e') ? e($csrfToken) : htmlspecialchars((string)$csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
  <title><?= $seoTitleEscaped ?></title>
  <meta name="description" content="<?= $seoDescriptionEscaped ?>">
  <link rel="canonical" href="<?= $currentUrlEscaped ?>">

  <!-- OpenGraph / Social Meta Tags -->
  <meta property="og:site_name" content="<?= $brandNameEscaped ?>">
  <meta property="og:title" content="<?= $seoTitleEscaped ?>">
  <meta property="og:description" content="<?= $seoDescriptionEscaped ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= $currentUrlEscaped ?>">
  <meta property="og:image" content="/assets/images/jam-jar-hero.svg">

  <!-- Twitter Cards -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= $seoTitle ?>">
  <meta name="twitter:description" content="<?= $seoDescription ?>">
  <meta name="twitter:image" content="/assets/images/jam-jar-hero.svg">

  <!-- LocalBusiness Schema JSON-LD -->
  <script type="application/ld+json">
  <?= $brandJson ?>
  </script>

  <script>
    window.SJ_PRODUCT_CATALOG = <?= json_encode($productCatalog, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;
  </script>

  <!-- Google Fonts: Caveat for cursive brand mark, Outfit for friendly geometric headings -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@700&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">

  <!-- Core Layout & Responsive Stylesheet -->
  <link rel="stylesheet" href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/css/styles.css">

  <!-- Square Web Payments SDK (Sandbox / Production) -->
  <script src="https://sandbox.web.squarecdn.com/v1/square.js"></script>
</head>
<body>
  <!-- Top Dismissible Promotional Announcement Bar -->
  <?php if ($promoActive): ?>
    <aside id="promo-announcement-bar" class="site-banner promo-burgundy-bar" aria-label="Announcement">
      <div class="banner-inner">
        <span class="banner-text"><?= htmlspecialchars($promoText) ?></span>
        <a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/shop.html" class="pill-btn-deal">View deal</a>
        <button type="button" id="dismiss-promo-btn" class="banner-dismiss" aria-label="Dismiss Announcement">&times;</button>
      </div>
    </aside>
  <?php endif; ?>

  <!-- Golden Brand Header -->
  <header class="site-header golden-header">
    <div class="header-container">
      <!-- Mobile Hamburger Toggle -->
      <button type="button" id="mobile-menu-toggle" class="mobile-menu-btn" aria-label="Toggle navigation menu" aria-expanded="false">
        <span class="bar"></span>
        <span class="bar"></span>
        <span class="bar"></span>
      </button>

      <!-- Logo -->
      <div class="brand-logo-area">
        <a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/index.html" class="brand-logo-link" title="Shelly's Jellys Home">
          <img src="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/assets/images/shellys-jellys-logo.svg" alt="Shelly's Jellys LLC" class="brand-logo-img" width="180" height="58">
        </a>
      </div>

      <!-- Live Keyword Search Bar -->
      <div class="header-search-area">
        <form id="header-search-form" class="search-form-control" role="search">
          <label for="header-search-input" class="sr-only">Search handcrafted jam flavors</label>
          <input type="search" id="header-search-input" placeholder="Search handcrafted jam flavors..." autocomplete="off">
          <button type="submit" class="search-submit-btn" aria-label="Search">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
          </button>
        </form>
        <!-- Dropdown container for live search results -->
        <div id="live-search-results" class="live-search-dropdown" style="display:none;" role="region" aria-live="polite"></div>
      </div>

      <!-- Dynamic Cart Basket Icon -->
      <div class="header-cart-area">
        <a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/cart.html" id="cart-header-link" class="basket-pill-link" aria-label="View Shopping Basket">
          <span class="basket-label">Basket</span>
          <span id="header-cart-count" class="basket-count-badge">0</span>
        </a>
        <button type="button" id="cart-toggle-btn" class="drawer-trigger-btn" aria-label="Open Cart Drawer" title="Open Slide-over Drawer">
          &#128722;
        </button>
      </div>
    </div>

    <!-- Smooth Horizontal Touch-Slider Navigation Bar -->
    <nav id="site-nav-bar" class="site-navigation-bar" aria-label="Primary Navigation">
      <div class="nav-scroll-container">
        <ul class="nav-links-list">
          <li><a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/shop.html" class="<?= $currentUrl === '/shop.html' ? 'active' : '' ?>">Shop all flavors</a></li>
          <li><a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/packs.html" class="<?= $currentUrl === '/packs.html' ? 'active' : '' ?>">Build a Pack</a></li>
          <li><a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/gift-box.html" class="<?= $currentUrl === '/gift-box.html' ? 'active' : '' ?>">Build a Gift Box</a></li>
          <li><a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/custom-orders.html" class="<?= $currentUrl === '/custom-orders.html' ? 'active' : '' ?>">Special Orders</a></li>
          <li><a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/contact.html" class="<?= $currentUrl === '/contact.html' ? 'active' : '' ?>">Contact Us</a></li>
        </ul>
      </div>
    </nav>
  </header>

  <main id="main-content" class="page-container">
