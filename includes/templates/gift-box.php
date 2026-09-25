<?php
declare(strict_types=1);
/**
 * SSG Build a Gift Box Template
 * Matches uploaded 'website design example-build a Gift Box- desktop.jpg' design mockup.
 * Flavor chips grid, 4 visual spaces (filled burgundy cards / dashed empty spaces),
 * dynamic total ($5.00 base empty box + $9.00 per chosen 4 oz jar), and multi-box support.
 */
?>
<section class="gift-box-header text-center">
  <h1 class="gift-box-main-title">Build a Gift Box</h1>
  <p class="gift-box-subtitle">Choose up to four 4 oz jars to send as a gift!<br>Mix flavors or choose one flavor to fill your Gift Box.</p>
</section>

<div class="gift-box-builder-layout" id="gift-box-app">
  <!-- Flavor Selection Chips Grid -->
  <div class="flavor-chips-grid" id="gift-flavor-chips" role="group" aria-label="Available gift box flavors">
    <?php foreach ($products as $prod): ?>
      <?php $canonicalSlug = canonical_product_slug((string)($prod['slug'] ?? $prod['name'])); ?>
      <?php $productImage = normalize_product_image_url((string)($prod['image_url'] ?? ''), (string)($prod['slug'] ?? $prod['name'])); ?>
      <button type="button" 
              class="flavor-chip-btn" 
              data-flavor-name="<?= htmlspecialchars($prod['name']) ?>"
              data-flavor-slug="<?= htmlspecialchars($canonicalSlug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
              data-flavor-img="<?= htmlspecialchars($productImage) ?>">
        <img src="<?= htmlspecialchars($productImage) ?>" alt="<?= htmlspecialchars($prod['name']) ?>" width="40" height="30" loading="lazy">
        <span class="chip-label"><?= htmlspecialchars($prod['name']) ?></span>
      </button>
    <?php endforeach; ?>
  </div>

  <!-- Visual 4-Jar Box Container -->
  <div class="gift-box-visual-container">
    <div class="box-slots-grid" id="gift-box-slots-grid">
      <!-- 4 spaces rendered dynamically by gift-box-builder.js -->
      <div class="gift-slot space-empty" data-slot="1">
        <span>-- Space 1 empty --</span>
      </div>
      <div class="gift-slot space-empty" data-slot="2">
        <span>-- Space 2 empty --</span>
      </div>
      <div class="gift-slot space-empty" data-slot="3">
        <span>-- Space 3 empty --</span>
      </div>
      <div class="gift-slot space-empty" data-slot="4">
        <span>-- Space 4 empty --</span>
      </div>
    </div>

    <!-- Slots Footer Status -->
    <div class="box-footer-row">
      <span id="gift-flavors-counter" class="flavors-chosen-status">( 0 flavors chosen )</span>
      <button type="button" id="clear-gift-spaces-btn" class="btn-clear-spaces">Clear All Spaces</button>
    </div>
  </div>

  <!-- Dynamic Gift Box Total and Action -->
  <div class="gift-box-footer-wrap text-center">
    <div class="gift-total-line">
      <span class="total-label">Gift Box Total:</span>
      <strong id="live-gift-total" class="total-amount">$5.00</strong>
    </div>
    <div class="total-subtext">( empty gift box starts at $5.00 )</div>

    <button type="button" id="submit-gift-box-btn" class="gift-action-btn disabled-btn" disabled>
      Please choose at least one flavor.
    </button>
  </div>
</div>

<!-- Products JSON for Gift Box JS -->
<script id="gift-products-data" type="application/json">
<?= json_encode(array_map(function($p) {
    return [
        'id' => $p['id'],
        'name' => $p['name'],
        'slug' => canonical_product_slug((string)($p['slug'] ?? $p['name'])),
        'image_url' => normalize_product_image_url((string)($p['image_url'] ?? ''), (string)($p['slug'] ?? $p['name']))
    ];
}, $products), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>
</script>
