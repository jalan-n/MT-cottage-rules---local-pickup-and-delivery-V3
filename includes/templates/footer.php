<?php
declare(strict_types=1);
/**
 * Shared SSG Footer Template
 * Semantic footer with links, brand details, slide-over cart drawer, and scripts.
 */
$brandName     = $settings['brand_name'] ?? "Shelly's Jellys LLC";
$pickupAddress = trim((string)($settings['pickup_address'] ?? '458 Orchard Vista Way, Kalispell, MT 59901'));
$pickupPhone   = trim((string)($settings['pickup_phone'] ?? '(406) 555-0192'));
$pickupEmail   = trim((string)($settings['pickup_email'] ?? 'mtshellysjellys@gmail.com'));
$brandLogoPath = trim((string)($settings['header_logo_path'] ?? ''));
if ($brandLogoPath === '') {
    $brandLogoPath = rtrim(getenv('APP_ASSET_BASE') ?: '/SJ-cottage-food/public', '/') . '/assets/images/shellys-jellys-logo.svg';
} elseif (preg_match('/^(https?:)?\/\//i', $brandLogoPath) !== 1 && !str_starts_with($brandLogoPath, '/')) {
    $brandLogoPath = './' . ltrim($brandLogoPath, './');
} elseif (str_starts_with($brandLogoPath, '/')) {
    $basePrefix = function_exists('app_base_path') ? app_base_path() : (trim((string)(getenv('APP_BASE_PATH') ?: getenv('APP_ASSET_BASE') ?: '/SJ-cottage-food')));
    $basePrefix = rtrim($basePrefix, '/');
    if ($basePrefix !== '' && ($brandLogoPath === $basePrefix || str_starts_with($brandLogoPath, $basePrefix . '/'))) {
        $brandLogoPath = $brandLogoPath;
    } else {
        $brandLogoPath = function_exists('app_url') ? app_url($brandLogoPath) : (rtrim(getenv('APP_ASSET_BASE') ?: '/SJ-cottage-food/public', '/') . $brandLogoPath);
    }
}
$currentYear   = date('Y');
$assetBase     = rtrim(getenv('APP_ASSET_BASE') ?: '/SJ-cottage-food/public', '/');
?>
  </main>

  <!-- Slide-over Cart Drawer -->
  <div id="cart-drawer-overlay" class="drawer-overlay" aria-hidden="true"></div>
  <aside id="cart-drawer" class="cart-drawer" aria-label="Shopping Cart Drawer" aria-hidden="true">
    <div class="drawer-header">
      <h2 class="drawer-title">&#128722; Your Basket</h2>
      <button type="button" id="cart-drawer-close" class="drawer-close-btn" aria-label="Close basket">&times;</button>
    </div>

    <!-- Drawer Line Items List -->
    <div id="drawer-items-list" class="drawer-body">
      <!-- Injected dynamically by cart.js -->
      <p class="empty-state">Your basket is currently empty.</p>
    </div>

    <!-- Drawer Footer & Actions -->
    <div class="drawer-footer">
      <div class="drawer-subtotal-row">
        <span>Items Subtotal:</span>
        <strong id="drawer-subtotal-val">$0.00</strong>
      </div>
      <p class="drawer-delivery-note">Taxes and local delivery fee calculated at checkout.</p>
      <div class="drawer-buttons-row" style="display:flex; justify-content:space-between; align-items:center; gap:0.75rem; margin-top:0.5rem;">
        <button type="button" id="drawer-clear-cart-btn" class="btn-secondary" style="display:none;" onclick="window.cartEngine && window.cartEngine.clearCart();">Clear Cart</button>
        <a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/cart.html" class="btn-secondary drawer-cart-page-btn">View Full Basket</a>
      </div>
      <div class="drawer-buttons-row" style="margin-top:0.75rem;">
        <a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/checkout.html" class="btn-emerald drawer-checkout-btn" style="width:100%;">Checkout &rarr;</a>
      </div>
    </div>
  </aside>

  <!-- Site Footer -->
  <footer class="site-footer">
    <div class="footer-inner">
      <div class="footer-col brand-col">
        <img src="<?= htmlspecialchars($brandLogoPath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($brandName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="footer-logo" width="160" height="52" loading="lazy">
        <p class="footer-tagline">Homemade jam. More fruit. Less sugar. No preservatives.</p>
        <p class="footer-subtext">Handcrafted in small batches in Kalispell, Montana. Local pickup and delivery across Flathead County.</p>
      </div>

      <div class="footer-col links-col">
        <h3 class="footer-heading">Quick Links</h3>
        <ul class="footer-links">
          <li><a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/shop.html">Shop all flavors</a></li>
          <li><a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/packs.html">Build a Pack</a></li>
          <li><a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/gift-box.html">Build a Gift Box</a></li>
          <li><a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/custom-orders.html">Special Orders</a></li>
          <li><a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/contact.html">Contact Us</a></li>
          <li><a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/cart.html">View Basket</a></li>
        </ul>
      </div>

      <div class="footer-col contact-col">
        <h3 class="footer-heading">Kitchen & Farmstand</h3>
        <address class="footer-address">
          <?= htmlspecialchars($pickupAddress) ?><br>
          Kalispell, MT 59901<br>
          Phone: <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $pickupPhone) ?: '4065550192') ?>"><?= htmlspecialchars($pickupPhone) ?></a><br>
          Email: <a href="mailto:<?= htmlspecialchars($pickupEmail) ?>"><?= htmlspecialchars($pickupEmail) ?></a>
        </address>
        <div class="footer-vcard-wrap">
          <a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/ShellysJellyscontact.vcf" download="ShellysJellyscontact.vcf" class="vcard-link">
            &#128100; Download vCard Contact File
          </a>
        </div>
      </div>
    </div>

    <div class="footer-bottom-bar text-center">
      <p>&copy; <?= $currentYear ?> <?= htmlspecialchars($brandName) ?>. All rights reserved. Artisan Gourmet Jams &amp; Preserves.</p>
    </div>
  </footer>

  <!-- Vanilla Scripts -->
  <script src="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/js/search.js"></script>
  <script src="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/js/cart.js"></script>
  <script src="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/js/fulfillment.js"></script>
  <?php if (str_contains($canonical ?? '', 'packs')): ?>
    <script src="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/js/pack-builder.js"></script>
  <?php elseif (str_contains($canonical ?? '', 'gift-box')): ?>
    <script src="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/js/gift-box-builder.js"></script>
  <?php elseif (str_contains($canonical ?? '', 'checkout')): ?>
    <script src="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/js/checkout.js"></script>
  <?php endif; ?>
</body>
</html>
