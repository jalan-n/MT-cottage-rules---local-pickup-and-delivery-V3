<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/security.php';

$admin = require_admin_auth();
$db = get_db();

$flash = '';
$flashType = 'success';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    $navLabelList = $_POST['nav_label'] ?? [];
    $navUrlList = $_POST['nav_url'] ?? [];
    $navVisibleList = $_POST['nav_visible'] ?? [];
    $navigationItems = [];

    $navCount = max(count($navLabelList), count($navUrlList), 1);
    for ($i = 0; $i < $navCount; $i++) {
        $label = trim((string)($navLabelList[$i] ?? ''));
        $url = trim((string)($navUrlList[$i] ?? ''));
        $visible = in_array((string)$i, array_map('strval', $navVisibleList), true);

        if ($label === '') {
            continue;
        }

        if ($url === '') {
            $url = './shop.html';
        }

        $navigationItems[] = [
            'label' => $label,
            'url' => $url,
            'visible' => $visible,
        ];
    }

    $heroCtaLabels = $_POST['hero_cta_label'] ?? [];
    $heroCtaUrls = $_POST['hero_cta_url'] ?? [];
    $heroCtaVisible = $_POST['hero_cta_visible'] ?? [];
    $heroCtas = [];
    $heroCtaCount = max(count($heroCtaLabels), count($heroCtaUrls), 1);

    for ($i = 0; $i < $heroCtaCount; $i++) {
        $label = trim((string)($heroCtaLabels[$i] ?? ''));
        $url = trim((string)($heroCtaUrls[$i] ?? ''));
        $visible = in_array((string)$i, array_map('strval', $heroCtaVisible), true);

        if ($label === '') {
            continue;
        }

        if ($url === '') {
            $url = './shop.html';
        }

        $heroCtas[] = [
            'label' => $label,
            'url' => $url,
            'visible' => $visible,
        ];
    }

    if ($heroCtas === []) {
        $heroCtas = [
            ['label' => 'Shop all jams', 'url' => './shop.html', 'visible' => true],
            ['label' => 'Build a Gift Box', 'url' => './gift-box.html', 'visible' => true],
        ];
    }

    $supportedCitiesValue = trim((string)($_POST['supported_cities'] ?? $_POST['supported_zip_codes'] ?? ''));
    $supportedCities = [];
    foreach (preg_split('/\s*,\s*/', $supportedCitiesValue) ?: [$supportedCitiesValue] as $city) {
        $city = trim((string)$city);
        if ($city !== '') {
            $supportedCities[] = $city;
        }
    }
    $supportedCities = array_values(array_unique($supportedCities));

    $settingsToUpdate = [
        'brand_name'              => trim((string)($_POST['brand_name'] ?? '')),
        'header_logo_path'        => trim((string)($_POST['header_logo_path'] ?? '')),
        'hero_image_path'         => trim((string)($_POST['hero_image_path'] ?? '')),
        'promo_banner_active'     => isset($_POST['promo_banner_active']) ? '1' : '0',
        'promo_banner_text'       => trim((string)($_POST['promo_banner_text'] ?? '')),
        'promo_banner_button_text' => trim((string)($_POST['promo_banner_button_text'] ?? 'View deal')),
        'promo_banner_button_url' => trim((string)($_POST['promo_banner_button_url'] ?? './shop.html')),
        'hero_title'              => trim((string)($_POST['hero_title'] ?? 'Homemade jam.')),
        'hero_tagline'            => trim((string)($_POST['hero_tagline'] ?? 'More fruit. Less sugar. No preservatives.')),
        'hero_rating_visible'     => isset($_POST['hero_rating_visible']) ? '1' : '0',
        'hero_rating_text'        => trim((string)($_POST['hero_rating_text'] ?? 'Consistent five star rating from our customers!')),
        'hero_ctas'               => json_encode($heroCtas, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        'admin_email_primary'     => trim((string)($_POST['admin_email_primary'] ?? '')),
        'admin_email_secondary'   => trim((string)($_POST['admin_email_secondary'] ?? '')),
        'pickup_address'          => trim((string)($_POST['pickup_address'] ?? '')),
        'pickup_phone'            => trim((string)($_POST['pickup_phone'] ?? '')),
        'pickup_email'            => trim((string)($_POST['pickup_email'] ?? '')),
        'pickup_hours'            => trim((string)($_POST['pickup_hours'] ?? '')),
        'delivery_radius_miles'   => trim((string)($_POST['delivery_radius_miles'] ?? '20')),
        'local_delivery_fee'      => trim((string)($_POST['local_delivery_fee'] ?? '6.50')),
        'free_delivery_threshold' => trim((string)($_POST['free_delivery_threshold'] ?? '45.00')),
        'supported_cities'        => json_encode($supportedCities, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        'supported_zip_codes'     => json_encode($supportedCities, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        'navigation_links'        => json_encode($navigationItems, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    ];

    // Handle Logo File Upload if provided
    if (isset($_FILES['logo_file']) && !empty($_FILES['logo_file']['name'])) {
        $uploadRes = sanitize_and_store_upload(
            $_FILES['logo_file'],
            dirname(__DIR__) . '/public/images',
            ['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'],
            2097152 // 2MB
        );
        if ($uploadRes['success']) {
            $settingsToUpdate['header_logo_path'] = app_url('/public/images/' . $uploadRes['filename']);
        }
    }

    $removeHeroImage = isset($_POST['remove_hero_image']) && $_POST['remove_hero_image'] === '1';
    if ($removeHeroImage) {
        $currentHeroImage = trim((string)($settings['hero_image_path'] ?? ''));
        if ($currentHeroImage !== '') {
            $currentHeroPath = parse_url($currentHeroImage, PHP_URL_PATH);
            if ($currentHeroPath !== '') {
                $diskPath = dirname(__DIR__) . '/' . ltrim($currentHeroPath, '/');
                if (is_file($diskPath)) {
                    @unlink($diskPath);
                }
            }
        }
        $settingsToUpdate['hero_image_path'] = '';
    } elseif (isset($_FILES['hero_image_file']) && !empty($_FILES['hero_image_file']['name'])) {
        $uploadRes = sanitize_and_store_upload(
            $_FILES['hero_image_file'],
            dirname(__DIR__) . '/public/images',
            ['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'],
            2097152 // 2MB
        );
        if ($uploadRes['success']) {
            $settingsToUpdate['hero_image_path'] = app_url('/public/images/' . $uploadRes['filename']);
        } else {
            $flash = 'Hero image upload failed: ' . $uploadRes['error'];
            $flashType = 'error';
        }
    }

    try {
        $driver = strtolower((string)$db->getAttribute(PDO::ATTR_DRIVER_NAME));
        $isMysql = $driver === 'mysql' || $driver === 'mariadb';

        foreach ($settingsToUpdate as $k => $v) {
            try {
                if ($isMysql) {
                    $stmt = $db->prepare("
                        INSERT INTO site_settings (setting_key, setting_value, updated_at)
                        VALUES (?, ?, NOW())
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
                    ");
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO site_settings (setting_key, setting_value, updated_at)
                        VALUES (?, ?, CURRENT_TIMESTAMP)
                        ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = CURRENT_TIMESTAMP
                    ");
                }
                $stmt->execute([$k, $v]);
            } catch (Exception $sqlErr) {
                $fallbackStmt = $db->prepare("UPDATE site_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?");
                $fallbackStmt->execute([$v, $k]);
            }
        }

        // Auto-rebuild if requested
        if (isset($_POST['auto_rebuild'])) {
            $buildScript = dirname(__DIR__) . '/build.php';
            if (file_exists($buildScript)) {
                @exec('php ' . escapeshellarg($buildScript) . ' 2>&1');
                if (file_exists(dirname(__DIR__) . '/public/index.html')) {
                    @copy(dirname(__DIR__) . '/public/index.html', dirname(__DIR__) . '/index.html');
                }
            }
            $flash = "Settings saved and static site rebuilt successfully!";
        } else {
            $flash = "Settings saved successfully. Click 'One-Click Rebuild' to publish changes to live storefront.";
        }
    } catch (Exception $e) {
        $flash = "Error saving settings: " . $e->getMessage();
        $flashType = 'error';
    }
}

// Fetch current settings
$settings = get_site_settings();

$defaultNavLinks = [
    ['label' => 'Home', 'url' => './index.html', 'visible' => true],
    ['label' => 'Shop All Jams', 'url' => './shop.html', 'visible' => true],
    ['label' => 'Build a Pack', 'url' => './packs.html', 'visible' => true],
    ['label' => 'Build a Gift Box', 'url' => './gift-box.html', 'visible' => true],
    ['label' => 'Custom Orders', 'url' => './custom-orders.html', 'visible' => true],
    ['label' => 'Cart', 'url' => './cart.html', 'visible' => true],
    ['label' => 'Checkout', 'url' => './checkout.html', 'visible' => true],
    ['label' => 'Contact Us', 'url' => './contact.html', 'visible' => true],
];

$navigationConfig = json_decode((string)($settings['navigation_links'] ?? '[]'), true);
if (!is_array($navigationConfig)) {
    $navigationConfig = $defaultNavLinks;
} else {
    $normalizedNavigation = [];
    $seenKeys = [];
    foreach ($navigationConfig as $navItem) {
        if (!is_array($navItem)) {
            continue;
        }
        $label = trim((string)($navItem['label'] ?? ''));
        $url = trim((string)($navItem['url'] ?? ''));
        if ($label === '') {
            continue;
        }
        $key = strtolower($label) . '|' . strtolower($url);
        if (isset($seenKeys[$key])) {
            continue;
        }
        $seenKeys[$key] = true;
        $normalizedNavigation[] = [
            'label' => $label,
            'url' => $url !== '' ? $url : './shop.html',
            'visible' => array_key_exists('visible', $navItem) ? (bool)$navItem['visible'] : true,
        ];
    }
    foreach ($defaultNavLinks as $defaultItem) {
        $label = trim((string)($defaultItem['label'] ?? ''));
        $url = trim((string)($defaultItem['url'] ?? './shop.html'));
        if ($label === '') {
            continue;
        }
        $key = strtolower($label) . '|' . strtolower($url);
        if (!isset($seenKeys[$key])) {
            $seenKeys[$key] = true;
            $normalizedNavigation[] = [
                'label' => $label,
                'url' => $url,
                'visible' => (bool)($defaultItem['visible'] ?? true),
            ];
        }
    }
    $navigationConfig = $normalizedNavigation;
}

$defaultHeroCtas = [
    ['label' => 'Shop all jams', 'url' => './shop.html', 'visible' => true],
    ['label' => 'Build a Gift Box', 'url' => './gift-box.html', 'visible' => true],
];

$heroCtas = json_decode((string)($settings['hero_ctas'] ?? '[]'), true);
if (!is_array($heroCtas) || $heroCtas === []) {
    $heroCtas = $defaultHeroCtas;
}

$deliveryRadiusCityMap = [
    '5' => ['Kalispell', 'Evergreen', 'Columbia Heights', 'Batavia'],
    '10' => ['Kalispell', 'Evergreen', 'Columbia Heights', 'Somers', 'Creston', 'Batavia', 'Whitefish'],
    '15' => ['Kalispell', 'Whitefish', 'Columbia Falls', 'Bigfork', 'Somers', 'Creston', 'Kila', 'Evergreen', 'Columbia Heights'],
    '20' => ['Kalispell', 'Whitefish', 'Columbia Falls', 'Bigfork', 'Lakeside', 'Somers', 'Creston', 'Kila', 'Marion', 'Rollins', 'Hungry Horse', 'Coram', 'Ferndale', 'Swan River', 'Woods Bay', 'Evergreen'],
    '30' => ['Kalispell', 'Whitefish', 'Columbia Falls', 'Bigfork', 'Marion', 'Ferndale', 'Swan River', 'Woods Bay', 'Rollins', 'Creston', 'Kila', 'Lakeside', 'Somers', 'Hungry Horse', 'Coram', 'Martin City', 'West Glacier', 'Big Arm', 'Dayton', 'Olney', 'Bear Dance', 'Apgar', 'Swan Lake', 'Elmo', 'Nyack', 'Niarada'],
    '40' => ['Kalispell', 'Whitefish', 'Columbia Falls', 'Bigfork', 'Marion', 'Ferndale', 'Swan River', 'Woods Bay', 'Rollins', 'Bear Dance', 'Olney', 'Dayton', 'Proctor', 'Elmo', 'West Glacier', 'Lake Mary Ronan', 'Polson', 'Stryker', 'Polebridge', 'Happys Inn', 'Trego', 'Lakeside', 'Somers', 'Hungry Horse', 'Coram', 'Martin City', 'Kila', 'Creston', 'Evergreen', 'Columbia Heights', 'Big Arm', 'Ronan'],
    '50' => ['Kalispell', 'Whitefish', 'Columbia Falls', 'Bigfork', 'Lakeside', 'Somers', 'Marion', 'Kila', 'Creston', 'Hungry Horse', 'Coram', 'Martin City', 'West Glacier', 'Eureka', 'Big Arm', 'Rollins', 'Dayton', 'Elmo', 'Proctor', 'Swan Lake', 'Ronan', 'Polson', 'Condon', 'Olney', 'Stryker', 'Niarada', 'Finley Point', 'Woods Bay', 'Bear Dance', 'Happys Inn', 'Nyack', 'Lake Mary Ronan', 'Libby'],
];
$selectedRadius = trim((string)($settings['delivery_radius_miles'] ?? '20'));
if (!isset($deliveryRadiusCityMap[$selectedRadius])) {
    $closestRadius = '20';
    $closestDiff = PHP_INT_MAX;
    foreach (array_keys($deliveryRadiusCityMap) as $radiusKey) {
        $diff = abs((int)$radiusKey - (int)$selectedRadius);
        if ($diff < $closestDiff) {
            $closestDiff = $diff;
            $closestRadius = (string)$radiusKey;
        }
    }
    $selectedRadius = $closestRadius;
}
$supportedCitiesFromSettings = get_supported_city_names();
$supportedCitiesFieldValue = $supportedCitiesFromSettings !== [] ? implode(', ', $supportedCitiesFromSettings) : implode(', ', $deliveryRadiusCityMap[$selectedRadius]);

$promoLinkOptions = [
    './index.html' => 'Home',
    './shop.html' => 'Shop All Jams',
    './packs.html' => 'Build a Pack',
    './gift-box.html' => 'Build a Gift Box',
    './custom-orders.html' => 'Custom Orders',
    './cart.html' => 'Cart',
    './checkout.html' => 'Checkout',
    './contact.html' => 'Contact Us',
];

$pageTitle = 'UI & Site Settings';
require_once __DIR__ . '/header.php';
?>

<div class="admin-page-header">
  <div>
    <h1 class="admin-page-title">⚙️ UI Settings Panel</h1>
    <p class="admin-page-desc">Configure brand identity, logo, announcement banner, contact notification emails, and valley delivery parameters.</p>
  </div>
</div>

<?php if (!empty($flash)): ?>
  <div class="admin-alert admin-alert-<?= $flashType ?>" role="status">
    <span><?= $flashType === 'success' ? '✅' : '⚠️' ?></span>
    <div><?= htmlspecialchars($flash) ?></div>
  </div>
<?php endif; ?>

<form method="POST" action="<?= htmlspecialchars(app_url('/admin/settings.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">

  <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap:1.75rem;">
    <!-- Left Column: Brand, Logo, Announcement Banner, Contact Emails -->
    <div>
      <div class="admin-card">
        <h2 class="admin-card-title" style="margin-bottom:1.25rem;">Brand Identity & Logo</h2>

        <div class="form-group">
          <label for="brand_name">Brand Title *</label>
          <input type="text" id="brand_name" name="brand_name" required value="<?= htmlspecialchars($settings['brand_name'] ?? "Shelly's Jellys LLC") ?>">
        </div>

        <div class="form-group">
          <label for="header_logo_path">Logo Asset URL / Path</label>
          <input type="text" id="header_logo_path" name="header_logo_path" value="<?= htmlspecialchars($settings['header_logo_path'] ?? app_url('/public/assets/images/logo.svg')) ?>">
        </div>

        <div class="form-group" style="padding-top:0.75rem; border-top:1px dashed #cbd5e1;">
          <label for="logo_file">Upload New Logo (SVG, PNG, WebP)</label>
          <input type="file" id="logo_file" name="logo_file" accept="image/svg+xml,image/png,image/jpeg,image/webp">
          <span class="form-help">Max size: 2MB &bull; Replaces header_logo_path</span>
        </div>
      </div>

      <div class="admin-card">
        <h2 class="admin-card-title" style="margin-bottom:1.25rem;">Home Page Hero Section</h2>

        <div class="form-group">
          <label for="hero_title">Hero Main Title</label>
          <input type="text" id="hero_title" name="hero_title" value="<?= htmlspecialchars($settings['hero_title'] ?? 'Homemade jam.') ?>">
        </div>

        <div class="form-group">
          <label for="hero_tagline">Hero Tagline</label>
          <input type="text" id="hero_tagline" name="hero_tagline" value="<?= htmlspecialchars($settings['hero_tagline'] ?? 'More fruit. Less sugar. No preservatives.') ?>">
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
          <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:600;">
            <input type="checkbox" name="hero_rating_visible" value="1" <?= ($settings['hero_rating_visible'] ?? '1') === '1' ? 'checked' : '' ?>>
            <span>Show Hero Rating Badge</span>
          </label>
        </div>

        <div class="form-group">
          <label for="hero_rating_text">Hero Rating Badge Text</label>
          <input type="text" id="hero_rating_text" name="hero_rating_text" value="<?= htmlspecialchars($settings['hero_rating_text'] ?? 'Consistent five star rating from our customers!') ?>">
        </div>

        <div class="form-group">
          <label for="hero_image_path">Hero Media Image URL / Path</label>
          <input type="text" id="hero_image_path" name="hero_image_path" value="<?= htmlspecialchars((string)($settings['hero_image_path'] ?? '')) ?>" placeholder="/public/images/hero-image.jpg">
        </div>

        <div class="form-group" style="padding-top:0.75rem; border-top:1px dashed #cbd5e1;">
          <label for="hero_image_file">Upload Hero Image (PNG, JPG, WebP, SVG)</label>
          <input type="file" id="hero_image_file" name="hero_image_file" accept="image/svg+xml,image/png,image/jpeg,image/webp">
          <span class="form-help">Max size: 2MB. Leave blank to keep the default jam jar graphic.</span>
          <div id="hero-image-preview-wrap" style="margin-top:0.75rem; display:none; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; padding:0.5rem; max-width:220px;">
            <img id="hero-image-preview" src="" alt="Hero image preview" style="display:block; width:100%; max-height:180px; object-fit:contain; border-radius:6px; background:white;">
          </div>
        </div>

        <div class="form-group" style="margin-top:0.5rem;">
          <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:600;">
            <input type="checkbox" name="remove_hero_image" value="1">
            <span>Remove current hero image and use the default graphic</span>
          </label>
        </div>

        <div class="form-group">
          <label>Hero CTA Buttons</label>
          <div id="hero-cta-list" style="display:flex; flex-direction:column; gap:0.75rem;">
            <?php foreach ($heroCtas as $ctaIndex => $cta): ?>
              <?php $ctaLabel = trim((string)($cta['label'] ?? '')) ?: 'New CTA'; ?>
              <?php $ctaUrl = trim((string)($cta['url'] ?? './shop.html')); ?>
              <?php $ctaVisible = !array_key_exists('visible', $cta) || (bool)$cta['visible']; ?>
              <div class="hero-cta-row" style="display:grid; grid-template-columns:1.4fr 1.3fr auto auto; gap:0.65rem; align-items:center; padding:0.75rem; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc;">
                <input type="text" name="hero_cta_label[]" value="<?= htmlspecialchars($ctaLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" placeholder="Button name">
                <select name="hero_cta_url[]" aria-label="Hero CTA destination">
                  <?php foreach ($promoLinkOptions as $pageValue => $pageLabel): ?>
                    <option value="<?= htmlspecialchars($pageValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" <?= $ctaUrl === $pageValue ? 'selected' : '' ?>><?= htmlspecialchars($pageLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                </select>
                <label style="display:flex; align-items:center; gap:0.35rem; margin:0; font-size:0.8rem; white-space:nowrap;">
                  <input type="checkbox" name="hero_cta_visible[]" value="<?= htmlspecialchars((string)$ctaIndex, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" <?= $ctaVisible ? 'checked' : '' ?>>
                  Show
                </label>
                <button type="button" class="btn-secondary" data-remove-cta="true" style="padding:0.45rem 0.75rem;">Remove</button>
              </div>
            <?php endforeach; ?>
          </div>
          <button type="button" id="add-hero-cta-btn" class="btn-secondary" style="margin-top:0.75rem;">+ Add CTA Button</button>
        </div>
      </div>

      <div class="admin-card">
        <h2 class="admin-card-title" style="margin-bottom:1.25rem;">Top Promotional Banner</h2>

        <div style="margin-bottom:1rem;">
          <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:600;">
            <input type="checkbox" name="promo_banner_active" value="1" <?= ($settings['promo_banner_active'] ?? '1') === '1' ? 'checked' : '' ?>>
            <span>Display Announcement Bar on Storefront</span>
          </label>
        </div>

        <div class="form-group">
          <label for="promo_banner_text">Banner Announcement Copy</label>
          <textarea id="promo_banner_text" name="promo_banner_text" rows="3"><?= htmlspecialchars($settings['promo_banner_text'] ?? 'Fresh small-batch seasonal harvest ready! Free local delivery on orders $45+ in the Flathead Valley.') ?></textarea>
          <span class="form-help">Dismissible by customers on mobile and desktop browsers.</span>
        </div>

        <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
          <div class="form-group">
            <label for="promo_banner_button_text">Button Label</label>
            <input type="text" id="promo_banner_button_text" name="promo_banner_button_text" value="<?= htmlspecialchars($settings['promo_banner_button_text'] ?? 'View deal') ?>">
          </div>

          <div class="form-group">
            <label for="promo_banner_button_url">Button Link</label>
            <select id="promo_banner_button_url" name="promo_banner_button_url">
              <?php foreach ($promoLinkOptions as $value => $label): ?>
                <option value="<?= htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" <?= (trim((string)($settings['promo_banner_button_url'] ?? './shop.html')) === $value) ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="admin-card">
        <h2 class="admin-card-title" style="margin-bottom:1.25rem;">Contact Form Recipient Inboxes</h2>

        <div class="form-group">
          <label for="admin_email_primary">Primary Administrator Email *</label>
          <input type="email" id="admin_email_primary" name="admin_email_primary" required value="<?= htmlspecialchars($settings['admin_email_primary'] ?? 'orders@wildandorchardjam.com') ?>">
          <span class="form-help">Receives order receipts, customer questions, and custom order leads.</span>
        </div>

        <div class="form-group">
          <label for="admin_email_secondary">Secondary Kitchen Notification Email</label>
          <input type="email" id="admin_email_secondary" name="admin_email_secondary" value="<?= htmlspecialchars($settings['admin_email_secondary'] ?? 'kitchen@wildandorchardjam.com') ?>">
          <span class="form-help">Kitchen batch dispatch email.</span>
        </div>
      </div>
    </div>

    <!-- Right Column: Pickup, Delivery, Navigation & Actions -->
    <div>
      <div class="admin-card">
        <h2 class="admin-card-title" style="margin-bottom:1.25rem;">Farmstand Pickup Settings</h2>

        <div class="form-group">
          <label for="pickup_address">Kitchen & Farmstand Physical Address</label>
          <input type="text" id="pickup_address" name="pickup_address" value="<?= htmlspecialchars($settings['pickup_address'] ?? 'The Jam Kitchen & Farmstand, 458 Orchard Vista Way, Suite B, Kalispell, MT 59901') ?>">
        </div>

        <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
          <div class="form-group">
            <label for="pickup_phone">Farmstand & Kitchen Phone</label>
            <input type="tel" id="pickup_phone" name="pickup_phone" value="<?= htmlspecialchars($settings['pickup_phone'] ?? '(406) 555-0192') ?>">
          </div>

          <div class="form-group">
            <label for="pickup_email">Farmstand & Kitchen Email</label>
            <input type="email" id="pickup_email" name="pickup_email" value="<?= htmlspecialchars($settings['pickup_email'] ?? 'mtshellysjellys@gmail.com') ?>">
          </div>
        </div>

        <div class="form-group">
          <label for="pickup_hours">Operating Pickup Hours</label>
          <input type="text" id="pickup_hours" name="pickup_hours" value="<?= htmlspecialchars($settings['pickup_hours'] ?? 'Tuesday – Saturday: 10:00 AM – 6:00 PM (Closed Sunday & Monday)') ?>">
        </div>
      </div>

      <div class="admin-card">
        <h2 class="admin-card-title" style="margin-bottom:1.25rem;">Local Valley Delivery Rules</h2>

        <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
          <div class="form-group">
            <label for="local_delivery_fee">Standard Delivery Fee ($)</label>
            <input type="number" step="0.25" id="local_delivery_fee" name="local_delivery_fee" value="<?= htmlspecialchars($settings['local_delivery_fee'] ?? '6.50') ?>">
          </div>

          <div class="form-group">
            <label for="free_delivery_threshold">Free Delivery Minimum ($)</label>
            <input type="number" step="1.00" id="free_delivery_threshold" name="free_delivery_threshold" value="<?= htmlspecialchars($settings['free_delivery_threshold'] ?? '45.00') ?>">
          </div>
        </div>

        <div class="form-group">
          <label for="delivery_radius_miles">Delivery Radius (Miles)</label>
          <select id="delivery_radius_miles" name="delivery_radius_miles">
            <?php foreach ([5, 10, 15, 20, 25, 30, 35, 40, 45, 50] as $radius): ?>
              <option value="<?= htmlspecialchars((string)$radius, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" <?= (string)$radius === (string)($settings['delivery_radius_miles'] ?? '20') ? 'selected' : '' ?>><?= htmlspecialchars((string)$radius, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> miles</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <input type="hidden" id="supported_cities" name="supported_cities" value="<?= htmlspecialchars($supportedCitiesFieldValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
          <div style="margin-bottom:0.5rem; font-size:0.9rem; font-weight:600; color:#0f172a;">
            List of Cities within <span id="delivery-radius-label"><?= htmlspecialchars((string)($settings['delivery_radius_miles'] ?? '20'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span> miles
          </div>
          <div id="delivery-city-preview" style="display:flex; flex-wrap:wrap; gap:0.5rem; margin-top:0.75rem;"></div>
        </div>
      </div>

      <div class="admin-card">
        <h2 class="admin-card-title" style="margin-bottom:1.25rem;">Navigation Links Configuration</h2>
        <div class="form-group" id="nav-links-config-group">
          <div style="display:flex; justify-content:space-between; align-items:center; gap:0.5rem; margin-bottom:0.75rem;">
            <label style="margin:0; font-weight:600;">Main Navigation Links</label>
            <button type="button" id="add-nav-item-btn" class="btn-secondary" style="padding:0.45rem 0.8rem; font-size:0.8rem;">Add menu item</button>
          </div>

          <?php foreach ($navigationConfig as $index => $navItem): ?>
            <?php $navLabel = trim((string)($navItem['label'] ?? '')) ?: 'Navigation Link'; ?>
            <?php $navUrl = trim((string)($navItem['url'] ?? './shop.html')); ?>
            <?php $navVisible = !array_key_exists('visible', $navItem) || (bool)$navItem['visible']; ?>
            <div class="nav-item-row" style="display:grid; grid-template-columns: 1.5fr 1fr auto auto auto; gap:0.75rem; align-items:center; margin-bottom:0.75rem; padding:0.75rem; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc;">
              <input type="text" name="nav_label[]" value="<?= htmlspecialchars($navLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-label="Navigation label">
              <select name="nav_url[]" aria-label="Navigation link destination">
                <?php foreach ($promoLinkOptions as $pageValue => $pageLabel): ?>
                  <option value="<?= htmlspecialchars($pageValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" <?= $navUrl === $pageValue ? 'selected' : '' ?>><?= htmlspecialchars($pageLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
              <label style="display:flex; align-items:center; gap:0.4rem; font-size:0.85rem; margin:0; white-space:nowrap;">
                <input type="checkbox" name="nav_visible[]" value="<?= htmlspecialchars((string)$index, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" <?= $navVisible ? 'checked' : '' ?>>
                Show
              </label>
              <div style="display:flex; gap:0.4rem;">
                <button type="button" class="btn-secondary" data-nav-move="up" aria-label="Move menu item up" style="padding:0.4rem 0.55rem; font-size:0.8rem;">↑</button>
                <button type="button" class="btn-secondary" data-nav-move="down" aria-label="Move menu item down" style="padding:0.4rem 0.55rem; font-size:0.8rem;">↓</button>
              </div>
              <button type="button" class="btn-secondary" data-remove-nav-item="true" style="padding:0.45rem 0.7rem; font-size:0.8rem;">Remove</button>
            </div>
          <?php endforeach; ?>
          <span class="form-help">Use the checkboxes to hide or show each main navigation item on the storefront.</span>
        </div>

        <div style="margin-top:1.5rem; background:#f0fdf4; padding:0.75rem; border-radius:6px; border:1px solid #bbf7d0;">
          <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.85rem; color:#166534;">
            <input type="checkbox" name="auto_rebuild" value="1" checked>
            <span><strong>One-Click Rebuild</strong>: Immediately compile updates to static storefront</span>
          </label>
        </div>

        <div style="margin-top:1.25rem;">
          <button type="submit" class="btn-primary-action" style="width:100%; justify-content:center; padding:0.8rem;">
            💾 Save UI Settings
          </button>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const deliveryRadiusCityMap = {
      5: ["Kalispell", "Evergreen", "Columbia Heights", "Batavia"],
      10: ["Kalispell", "Evergreen", "Columbia Heights", "Somers", "Creston", "Batavia", "Whitefish"],
      15: ["Kalispell", "Whitefish", "Columbia Falls", "Bigfork", "Somers", "Creston", "Kila", "Evergreen", "Columbia Heights"],
      20: ["Kalispell", "Whitefish", "Columbia Falls", "Bigfork", "Lakeside", "Somers", "Creston", "Kila", "Marion", "Rollins", "Hungry Horse", "Coram", "Ferndale", "Swan River", "Woods Bay", "Evergreen"],
      25: ["Kalispell", "Whitefish", "Columbia Falls", "Bigfork", "Lakeside", "Somers", "Creston", "Kila", "Marion", "Rollins", "Hungry Horse", "Coram", "Ferndale", "Swan River", "Woods Bay", "Evergreen"],
      30: ["Kalispell", "Whitefish", "Columbia Falls", "Bigfork", "Marion", "Ferndale", "Swan River", "Woods Bay", "Rollins", "Creston", "Kila", "Lakeside", "Somers", "Hungry Horse", "Coram", "Martin City", "West Glacier", "Big Arm", "Dayton", "Olney", "Bear Dance", "Apgar", "Swan Lake", "Elmo", "Nyack", "Niarada"],
      35: ["Kalispell", "Whitefish", "Columbia Falls", "Bigfork", "Marion", "Ferndale", "Swan River", "Woods Bay", "Rollins", "Creston", "Kila", "Lakeside", "Somers", "Hungry Horse", "Coram", "Martin City", "West Glacier", "Big Arm", "Dayton", "Olney", "Bear Dance", "Apgar", "Swan Lake", "Elmo", "Nyack", "Niarada"],
      40: ["Kalispell", "Whitefish", "Columbia Falls", "Bigfork", "Marion", "Ferndale", "Swan River", "Woods Bay", "Rollins", "Bear Dance", "Olney", "Dayton", "Proctor", "Elmo", "West Glacier", "Lake Mary Ronan", "Polson", "Stryker", "Polebridge", "Happys Inn", "Trego", "Lakeside", "Somers", "Hungry Horse", "Coram", "Martin City", "Kila", "Creston", "Evergreen", "Columbia Heights", "Big Arm", "Ronan"],
      45: ["Kalispell", "Whitefish", "Columbia Falls", "Bigfork", "Marion", "Ferndale", "Swan River", "Woods Bay", "Rollins", "Bear Dance", "Olney", "Dayton", "Proctor", "Elmo", "West Glacier", "Lake Mary Ronan", "Polson", "Stryker", "Polebridge", "Happys Inn", "Trego", "Lakeside", "Somers", "Hungry Horse", "Coram", "Martin City", "Kila", "Creston", "Evergreen", "Columbia Heights", "Big Arm", "Ronan"],
      50: ["Kalispell", "Whitefish", "Columbia Falls", "Bigfork", "Lakeside", "Somers", "Marion", "Kila", "Creston", "Hungry Horse", "Coram", "Martin City", "West Glacier", "Eureka", "Big Arm", "Rollins", "Dayton", "Elmo", "Proctor", "Swan Lake", "Ronan", "Polson", "Condon", "Olney", "Stryker", "Niarada", "Finley Point", "Woods Bay", "Bear Dance", "Happys Inn", "Nyack", "Lake Mary Ronan", "Libby"]
    }

    const deliveryRadiusSelect = document.getElementById('delivery_radius_miles')
    const deliveryCitiesField = document.getElementById('supported_cities')
    const deliveryCityPreview = document.getElementById('delivery-city-preview')
    const deliveryRadiusLabel = document.getElementById('delivery-radius-label')

    function normalizeCity(value) {
      return String(value || '').trim().replace(/\s+/g, ' ')
    }

    function updateRadiusLabel() {
      if (!deliveryRadiusLabel || !deliveryRadiusSelect) return
      deliveryRadiusLabel.textContent = deliveryRadiusSelect.value
    }

    function updateCityPreview() {
      if (!deliveryCityPreview || !deliveryCitiesField) return

      const names = deliveryCitiesField.value
        .split(',')
        .map((city) => normalizeCity(city))
        .filter(Boolean)

      if (names.length === 0) {
        deliveryCityPreview.innerHTML = '<span style="color:#64748b; font-size:0.8rem;">No cities selected.</span>'
        return
      }

      deliveryCityPreview.innerHTML = names.map(function (city) {
        return `
          <span style="display:inline-flex; align-items:center; gap:0.35rem; padding:0.35rem 0.55rem; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:999px; font-size:0.8rem; color:#0f172a;">
            ${city}
            <button type="button" data-remove-city="${city}" style="border:none; background:transparent; color:#334155; cursor:pointer; font-size:1rem; line-height:1; padding:0;">×</button>
          </span>
        `
      }).join('')
    }

    if (deliveryRadiusSelect && deliveryCitiesField && deliveryCityPreview) {
      const radiusValues = Object.keys(deliveryRadiusCityMap).map(Number).sort(function (a, b) { return a - b })
      const getNearestRadius = function (radius) {
        const target = Number(radius) || 20
        return radiusValues.reduce(function (best, current) {
          return Math.abs(current - target) < Math.abs(best - target) ? current : best
        }, radiusValues[0])
      }

      const syncCitiesForRadius = function (radius) {
        const nearestRadius = getNearestRadius(radius)
        const chosen = deliveryRadiusCityMap[nearestRadius] || []
        deliveryCitiesField.value = chosen.join(', ')
        updateRadiusLabel()
        updateCityPreview()
      }

      deliveryRadiusSelect.addEventListener('change', function () {
        syncCitiesForRadius(deliveryRadiusSelect.value)
      })

      deliveryCitiesField.addEventListener('input', updateCityPreview)
      updateRadiusLabel()
      deliveryCityPreview.addEventListener('click', function (event) {
        const removeBtn = event.target.closest('[data-remove-city]')
        if (!removeBtn) return

        const cityToRemove = removeBtn.getAttribute('data-remove-city')
        const remaining = deliveryCitiesField.value
          .split(',')
          .map(function (city) {
            return normalizeCity(city)
          })
          .filter(function (city) {
            return city && city !== cityToRemove
          })

        deliveryCitiesField.value = remaining.join(', ')
        updateCityPreview()
      })

      updateCityPreview()
    }

    const heroImageInput = document.getElementById('hero_image_file')
    const heroImagePreviewWrap = document.getElementById('hero-image-preview-wrap')
    const heroImagePreview = document.getElementById('hero-image-preview')
    const heroImagePathInput = document.getElementById('hero_image_path')

    if (heroImageInput && heroImagePreview && heroImagePreviewWrap) {
      const showHeroImagePreview = function (src) {
        if (!src) {
          heroImagePreviewWrap.style.display = 'none'
          heroImagePreview.src = ''
          return
        }
        heroImagePreview.src = src
        heroImagePreviewWrap.style.display = 'block'
      }

      const savedHeroImagePath = heroImagePathInput && heroImagePathInput.value ? heroImagePathInput.value.trim() : ''
      if (savedHeroImagePath) {
        showHeroImagePreview(savedHeroImagePath)
      }

      heroImageInput.addEventListener('change', function () {
        const file = this.files && this.files[0]
        if (!file) {
          if (savedHeroImagePath) {
            showHeroImagePreview(savedHeroImagePath)
          }
          return
        }
        const reader = new FileReader()
        reader.onload = function (event) {
          showHeroImagePreview(String(event.target && event.target.result ? event.target.result : ''))
        }
        reader.readAsDataURL(file)
      })
    }

    const addNavBtn = document.getElementById('add-nav-item-btn')
    const navConfigGroup = document.getElementById('nav-links-config-group')

    function reindexNavRows() {
      if (!navConfigGroup) return
      const rows = navConfigGroup.querySelectorAll('.nav-item-row')
      rows.forEach(function (row, index) {
        const visibleInput = row.querySelector('input[name="nav_visible[]"]')
        if (visibleInput) {
          visibleInput.value = String(index)
        }
      })
    }

    if (navConfigGroup) {
      navConfigGroup.addEventListener('click', function (event) {
        const removeBtn = event.target.closest('[data-remove-nav-item="true"]')
        if (removeBtn) {
          const row = removeBtn.closest('.nav-item-row')
          if (row) {
            row.remove()
            reindexNavRows()
          }
          return
        }

        const moveBtn = event.target.closest('[data-nav-move]')
        if (moveBtn) {
          const row = moveBtn.closest('.nav-item-row')
          if (!row) return
          const rows = Array.from(navConfigGroup.querySelectorAll('.nav-item-row'))
          const currentIndex = rows.indexOf(row)
          const direction = moveBtn.getAttribute('data-nav-move') === 'up' ? -1 : 1
          const nextIndex = currentIndex + direction
          if (nextIndex < 0 || nextIndex >= rows.length) return
          const targetRow = rows[nextIndex]
          if (direction < 0) {
            navConfigGroup.insertBefore(row, targetRow)
          } else {
            navConfigGroup.insertBefore(targetRow, row)
          }
          reindexNavRows()
        }
      })
    }

    if (addNavBtn && navConfigGroup) {
      addNavBtn.addEventListener('click', function () {
        const rows = navConfigGroup.querySelectorAll('.nav-item-row')
        const row = document.createElement('div')
        row.className = 'nav-item-row'
        row.style.display = 'grid'
        row.style.gridTemplateColumns = '1.5fr 1fr auto auto auto'
        row.style.gap = '0.75rem'
        row.style.alignItems = 'center'
        row.style.marginBottom = '0.75rem'
        row.style.padding = '0.75rem'
        row.style.border = '1px solid #e2e8f0'
        row.style.borderRadius = '8px'
        row.style.background = '#f8fafc'

        const options = `<?= htmlspecialchars(json_encode(array_keys($promoLinkOptions), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>`
        const optionList = JSON.parse(options)
        const selectMarkup = optionList.map(function (value) {
          const label = <?= json_encode(array_values($promoLinkOptions), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>[optionList.indexOf(value)]
          return `<option value="${value}" ${value === './shop.html' ? 'selected' : ''}>${label}</option>`
        }).join('')

        row.innerHTML = `
          <input type="text" name="nav_label[]" value="" placeholder="Menu label" aria-label="Navigation label">
          <select name="nav_url[]" aria-label="Navigation link destination">${selectMarkup}</select>
          <label style="display:flex; align-items:center; gap:0.4rem; font-size:0.85rem; margin:0; white-space:nowrap;">
            <input type="checkbox" name="nav_visible[]" value="${rows.length}" checked>
            Show
          </label>
          <div style="display:flex; gap:0.4rem;">
            <button type="button" class="btn-secondary" data-nav-move="up" aria-label="Move menu item up" style="padding:0.4rem 0.55rem; font-size:0.8rem;">↑</button>
            <button type="button" class="btn-secondary" data-nav-move="down" aria-label="Move menu item down" style="padding:0.4rem 0.55rem; font-size:0.8rem;">↓</button>
          </div>
          <button type="button" class="btn-secondary" data-remove-nav-item="true" style="padding:0.45rem 0.7rem; font-size:0.8rem;">Remove</button>
        `

        navConfigGroup.appendChild(row)
        reindexNavRows()
      })
    }

    const addBtn = document.getElementById('add-hero-cta-btn')
    const list = document.getElementById('hero-cta-list')

    if (addBtn && list) {
      addBtn.addEventListener('click', function () {
        const row = document.createElement('div')
        row.className = 'hero-cta-row'
        row.style.display = 'grid'
        row.style.gridTemplateColumns = '1.4fr 1.3fr auto auto'
        row.style.gap = '0.65rem'
        row.style.alignItems = 'center'
        row.style.padding = '0.75rem'
        row.style.border = '1px solid #e2e8f0'
        row.style.borderRadius = '8px'
        row.style.background = '#f8fafc'
        const options = `<?= htmlspecialchars(json_encode(array_keys($promoLinkOptions), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>`
        const optionList = JSON.parse(options)
        const selectMarkup = optionList.map(function (value) {
          const label = <?= json_encode(array_values($promoLinkOptions), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>[optionList.indexOf(value)]
          return `<option value="${value}" ${value === './shop.html' ? 'selected' : ''}>${label}</option>`
        }).join('')

        row.innerHTML = `
          <input type="text" name="hero_cta_label[]" value="" placeholder="Button name">
          <select name="hero_cta_url[]">${selectMarkup}</select>
          <label style="display:flex; align-items:center; gap:0.35rem; margin:0; font-size:0.8rem; white-space:nowrap;">
            <input type="checkbox" name="hero_cta_visible[]" value="${list.children.length}" checked>
            Show
          </label>
          <button type="button" class="btn-secondary" data-remove-cta="true" style="padding:0.45rem 0.75rem;">Remove</button>
        `

        const removeBtn = row.querySelector('[data-remove-cta="true"]')
        removeBtn.addEventListener('click', function () {
          row.remove()
        })

        list.appendChild(row)
      })
    }

    document.querySelectorAll('[data-remove-cta="true"]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const row = btn.closest('.hero-cta-row')
        if (row) row.remove()
      })
    })
  })
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
