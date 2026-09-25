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

    $settingsToUpdate = [
        'brand_name'              => trim((string)($_POST['brand_name'] ?? '')),
        'header_logo_path'        => trim((string)($_POST['header_logo_path'] ?? '')),
        'promo_banner_active'     => isset($_POST['promo_banner_active']) ? '1' : '0',
        'promo_banner_text'       => trim((string)($_POST['promo_banner_text'] ?? '')),
        'admin_email_primary'     => trim((string)($_POST['admin_email_primary'] ?? '')),
        'admin_email_secondary'   => trim((string)($_POST['admin_email_secondary'] ?? '')),
        'pickup_address'          => trim((string)($_POST['pickup_address'] ?? '')),
        'pickup_hours'            => trim((string)($_POST['pickup_hours'] ?? '')),
        'delivery_radius_miles'   => trim((string)($_POST['delivery_radius_miles'] ?? '18')),
        'local_delivery_fee'      => trim((string)($_POST['local_delivery_fee'] ?? '6.50')),
        'free_delivery_threshold' => trim((string)($_POST['free_delivery_threshold'] ?? '45.00')),
        'supported_zip_codes'     => trim((string)($_POST['supported_zip_codes'] ?? '[]')),
        'navigation_links'        => trim((string)($_POST['navigation_links'] ?? '[]'))
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
          <input type="number" id="delivery_radius_miles" name="delivery_radius_miles" value="<?= htmlspecialchars($settings['delivery_radius_miles'] ?? '18') ?>">
        </div>

        <div class="form-group">
          <label for="supported_zip_codes">Supported Flathead Valley ZIP Codes (JSON format)</label>
          <textarea id="supported_zip_codes" name="supported_zip_codes" rows="3"><?= htmlspecialchars($settings['supported_zip_codes'] ?? '["59901", "59902", "59903", "59904", "59911", "59912", "59937"]') ?></textarea>
        </div>
      </div>

      <div class="admin-card">
        <h2 class="admin-card-title" style="margin-bottom:1.25rem;">Navigation Links Configuration</h2>
        <div class="form-group">
          <label for="navigation_links">Storefront Navigation Items (JSON)</label>
          <textarea id="navigation_links" name="navigation_links" rows="3"><?= htmlspecialchars($settings['navigation_links'] ?? '[{"label":"Shop All Jams","url":"./shop.html"},{"label":"Build a Pack","url":"./packs.html"},{"label":"Build a Gift Box","url":"./gift-box.html"},{"label":"Special Orders","url":"./custom-orders.html"},{"label":"Contact","url":"./contact.html"}]') ?></textarea>
          <span class="form-help">JSON array of navigation items rendered in static header.</span>
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

<?php require_once __DIR__ . '/footer.php'; ?>
