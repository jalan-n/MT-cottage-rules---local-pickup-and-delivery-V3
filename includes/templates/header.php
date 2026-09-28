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
$promoButtonText = trim((string)($settings['promo_banner_button_text'] ?? 'View deal'));
$promoButtonText = $promoButtonText !== '' ? $promoButtonText : 'View deal';
$promoButtonUrl  = trim((string)($settings['promo_banner_button_url'] ?? './shop.html'));
if ($promoButtonUrl === '') {
    $promoButtonUrl = './shop.html';
}
if (!preg_match('/^(https?:)?\/\//i', $promoButtonUrl) && !str_starts_with($promoButtonUrl, '/')) {
    $promoButtonUrl = './' . ltrim($promoButtonUrl, './');
}
$defaultNavigationItems = [
    ['label' => 'Home', 'url' => './index.html', 'visible' => true],
    ['label' => 'Shop All Jams', 'url' => './shop.html', 'visible' => true],
    ['label' => 'Build a Pack', 'url' => './packs.html', 'visible' => true],
    ['label' => 'Build a Gift Box', 'url' => './gift-box.html', 'visible' => true],
    ['label' => 'Custom Orders', 'url' => './custom-orders.html', 'visible' => true],
    ['label' => 'Cart', 'url' => './cart.html', 'visible' => true],
    ['label' => 'Checkout', 'url' => './checkout.html', 'visible' => true],
    ['label' => 'Contact Us', 'url' => './contact.html', 'visible' => true],
];
$rawNavigationLinks = json_decode((string)($settings['navigation_links'] ?? '[]'), true);
$headerNavigationItems = is_array($rawNavigationLinks) && $rawNavigationLinks !== [] ? $rawNavigationLinks : $defaultNavigationItems;
$headerNavigationItems = array_values(array_filter(array_map(function ($item) {
    if (!is_array($item)) {
        return null;
    }
    $label = trim((string)($item['label'] ?? ''));
    $url = trim((string)($item['url'] ?? ''));
    if ($label === '') {
        return null;
    }
    $visible = array_key_exists('visible', $item) ? (bool)$item['visible'] : true;
    if (is_string($item['visible'] ?? null)) {
        $visible = strtolower((string)$item['visible']) !== 'false' && strtolower((string)$item['visible']) !== '0';
    }
    if ($url === '') {
        $url = './shop.html';
    }
    return ['label' => $label, 'url' => $url, 'visible' => $visible];
}, $headerNavigationItems)));
if ($headerNavigationItems === []) {
    $headerNavigationItems = $defaultNavigationItems;
}
$currentUrl     = $canonical ?? '/';
$assetBase      = getenv('APP_ASSET_BASE') ?: '/SJ-cottage-food/public';
$assetBase      = rtrim($assetBase, '/');
$brandLogoPath  = trim((string)($settings['header_logo_path'] ?? ''));
if ($brandLogoPath === '') {
    $brandLogoPath = $assetBase . '/assets/images/shellys-jellys-logo.svg';
} elseif (preg_match('/^(https?:)?\/\//i', $brandLogoPath) !== 1) {
    $basePrefix = rtrim((string)(function_exists('app_base_path') ? app_base_path() : (getenv('APP_BASE_PATH') ?: getenv('APP_ASSET_BASE') ?: '/SJ-cottage-food')), '/');
    $normalizedLogoPath = $brandLogoPath;
    if ($basePrefix !== '' && str_starts_with($normalizedLogoPath, $basePrefix)) {
        $normalizedLogoPath = substr($normalizedLogoPath, strlen($basePrefix));
    }
    if (str_starts_with($normalizedLogoPath, '/')) {
        $brandLogoPath = function_exists('app_url') ? app_url($normalizedLogoPath) : (rtrim(getenv('APP_ASSET_BASE') ?: '/SJ-cottage-food/public', '/') . $normalizedLogoPath);
    } elseif (str_starts_with($normalizedLogoPath, 'public/')) {
        $brandLogoPath = function_exists('app_url') ? app_url('/' . $normalizedLogoPath) : (rtrim(getenv('APP_ASSET_BASE') ?: '/SJ-cottage-food/public', '/') . '/' . $normalizedLogoPath);
    } else {
        $brandLogoPath = './' . ltrim($normalizedLogoPath, './');
    }
}
$pickupAddress  = trim((string)($settings['pickup_address'] ?? '458 Orchard Vista Way, Kalispell, MT 59901'));
$pickupPhone    = trim((string)($settings['pickup_phone'] ?? '(406) 555-0192'));
$pickupEmail    = trim((string)($settings['pickup_email'] ?? 'mtshellysjellys@gmail.com'));
$promoVersion   = md5($promoText . '|' . $promoButtonText . '|' . $promoButtonUrl . '|' . ($promoActive ? '1' : '0'));
$seoTitle       = !empty($title) ? $this->capString($title, 60) ?? $title : "Shelly's Jellys LLC | Gourmet Homemade Jams";
$seoDescription = !empty($description) ? $this->capString($description, 160) ?? $description : 'Small-batch artisanal gourmet homemade jams. More fruit, less sugar, no preservatives. Local delivery & farmstand pickup.';
$brandJson = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'LocalBusiness',
    'name' => $brandName,
    'image' => '/assets/images/jam-jar-hero.svg',
    'telephone' => $pickupPhone,
    'email' => $pickupEmail,
    'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => $pickupAddress,
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
    window.APP_BASE_PATH = <?= json_encode(function_exists('app_base_path') ? app_base_path() : (trim((string)(getenv('APP_BASE_PATH') ?: getenv('APP_ASSET_BASE') ?: '/SJ-cottage-food')))) ?>;
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
    <aside id="promo-announcement-bar" class="site-banner promo-burgundy-bar" aria-label="Announcement" data-promo-version="<?= htmlspecialchars($promoVersion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <div class="banner-inner">
        <span class="banner-text"><?= htmlspecialchars($promoText) ?></span>
        <a href="<?= htmlspecialchars($promoButtonUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="pill-btn-deal"><?= htmlspecialchars($promoButtonText) ?></a>
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
        <a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/index.html" class="brand-logo-link" title="<?= htmlspecialchars($brandName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> Home">
          <img src="<?= htmlspecialchars($brandLogoPath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($brandName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="brand-logo-img" width="180" height="58">
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
          <?php foreach ($headerNavigationItems as $navItem): ?>
            <?php if (!($navItem['visible'] ?? true)) continue; ?>
            <?php $navHref = rtrim((string)($navItem['url'] ?? './shop.html'), '/'); ?>
            <?php if ($navHref === '') { $navHref = './shop.html'; } ?>
            <?php if (!preg_match('/^(https?:)?\/\//i', $navHref) && !str_starts_with($navHref, '/')) { $navHref = './' . ltrim($navHref, './'); } ?>
            <?php $navCurrent = rtrim((string)$currentUrl, '/'); ?>
            <?php $isActive = $navCurrent === '/index.html' ? $navHref === './index.html' : (str_ends_with($navCurrent, str_replace('./', '/', $navHref)) || $navCurrent === str_replace('./', '/', $navHref)); ?>
            <li><a href="<?= htmlspecialchars($navHref, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="<?= $isActive ? 'active' : '' ?>"><?= htmlspecialchars((string)($navItem['label'] ?? 'Navigation'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </nav>
  </header>

  <main id="main-content" class="page-container">
