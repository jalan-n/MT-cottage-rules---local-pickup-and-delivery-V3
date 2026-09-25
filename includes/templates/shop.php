<?php
declare(strict_types=1);
/**
 * SSG Shop Catalog Template
 * Matches uploaded 'website design example-Shop- desktop.jpg' design mockup.
 */
$assetBase = rtrim(getenv('APP_ASSET_BASE') ?: '/SJ-cottage-food/public', '/');
?>
<section class="shop-catalog-header text-center">
  <h1 class="shop-main-title">Shop all 6 flavors</h1>
  <p class="shop-subtitle">Choose from 4 oz, 8 oz, and 12 oz. Get as many flavors as you like!</p>
</section>

<section class="shop-cards-grid-section" aria-label="Jam Flavor Catalog">
  <div class="shop-products-grid">
    <?php foreach ($products as $prod): ?>
      <?php $canonicalSlug = canonical_product_slug((string)($prod['slug'] ?? $prod['name'])); ?>
      <?php $productImage = normalize_product_image_url((string)($prod['image_url'] ?? ''), (string)($prod['slug'] ?? $prod['name'])); ?>
      <article class="shop-product-card" data-product-slug="<?= htmlspecialchars($canonicalSlug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-product-name="<?= htmlspecialchars($prod['name']) ?>">
        <div class="shop-card-media">
          <img src="<?= htmlspecialchars($productImage) ?>" alt="<?= htmlspecialchars($prod['name']) ?> Fruit Preserve" width="160" height="120" loading="lazy">
        </div>
        <h2 class="shop-flavor-title"><?= htmlspecialchars($prod['name']) ?></h2>
        <p class="shop-flavor-desc"><?= htmlspecialchars($prod['description']) ?></p>
        <div class="shop-card-action">
          <a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/product-<?= htmlspecialchars($canonicalSlug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>.html" class="btn-emerald card-btn">View Flavor</a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<!-- Additional Offerings Banner -->
<section class="shop-bundles-callout text-center">
  <div class="card-box callout-highlight-box">
    <h2>Mix & Match Volume Bundles</h2>
    <p>Looking to bundle multiple jars? Build custom combinations and enjoy automatic bundle discounts.</p>
    <div class="callout-actions">
      <a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/packs.html" class="btn-emerald">Build a Pack (3, 6, 12)</a>
      <a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/gift-box.html" class="btn-emerald">Build a Gift Box (4 Jars)</a>
    </div>
  </div>
</section>
