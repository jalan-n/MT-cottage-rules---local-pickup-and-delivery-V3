<?php
declare(strict_types=1);
/**
 * SSG Build-a-Pack Template
 * Matches uploaded 'website design example-build a pack- desktop.jpg' design mockup.
 */
?>
<section class="pack-header text-center">
  <h1 class="pack-main-title">Build a Pack</h1>
  <p class="pack-subtitle">Select 3, 6, or 12 jars. Mix and match any flavors in your chosen jar size and receive an automatic discount.</p>
</section>

<div class="pack-builder-layout" id="pack-builder-app">
  <!-- Step 1: Pack Size Selector -->
  <fieldset class="builder-section">
    <legend class="builder-legend">1. Select Pack Size (Discounts Applied):</legend>
    <div class="pill-options-group" role="radiogroup" aria-label="Pack Size Options">
      <label class="pack-pill-choice active">
        <input type="radio" name="pack_count" value="3" data-discount="0.05" data-bundle-type="pack_3" checked>
        <span class="pill-top">3 Pack</span>
        <span class="pill-sub">5% off</span>
      </label>

      <label class="pack-pill-choice">
        <input type="radio" name="pack_count" value="6" data-discount="0.08" data-bundle-type="pack_6">
        <span class="pill-top">6 Pack</span>
        <span class="pill-sub">8% off</span>
      </label>

      <label class="pack-pill-choice">
        <input type="radio" name="pack_count" value="12" data-discount="0.10" data-bundle-type="pack_12">
        <span class="pill-top">12 Pack</span>
        <span class="pill-sub">10% off</span>
      </label>
    </div>
  </fieldset>

  <!-- Step 2: Uniform Jar Size Selector -->
  <fieldset class="builder-section">
    <legend class="builder-legend">2. Select Uniform Jar Size:</legend>
    <div class="pill-options-group" role="radiogroup" aria-label="Jar Size Options">
      <label class="size-pill-choice active">
        <input type="radio" name="pack_jar_size" value="4 oz" data-base-price="9.00" checked>
        <span class="pill-top">4 oz</span>
        <span class="pill-sub">$9 base</span>
      </label>

      <label class="size-pill-choice">
        <input type="radio" name="pack_jar_size" value="8 oz" data-base-price="14.00">
        <span class="pill-top">8 oz</span>
        <span class="pill-sub">$14 base</span>
      </label>

      <label class="size-pill-choice">
        <input type="radio" name="pack_jar_size" value="12 oz" data-base-price="17.00">
        <span class="pill-top">12 oz</span>
        <span class="pill-sub">$17 base</span>
      </label>
    </div>
  </fieldset>

  <!-- Step 3: Slots and Flavor Assignment -->
  <fieldset class="builder-section">
    <legend class="builder-legend">3. Fill the slots with your chosen flavor:</legend>

    <!-- Quick Pack Autofill Box -->
    <div class="quick-pack-card">
      <div class="quick-pack-info">
        <h3>Quick Pack</h3>
        <p>Fill all the slots of your Pack with one flavor.</p>
      </div>
      <div class="quick-pack-select-wrap">
        <select id="quick-pack-select" class="custom-select-field" aria-label="Quick Pack flavor selector">
          <option value="">-- Choose a flavor --</option>
          <?php foreach ($products as $prod): ?>
            <option value="<?= htmlspecialchars($prod['name']) ?>"><?= htmlspecialchars($prod['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Individual Slots Container -->
    <div class="individual-slots-card">
      <div id="slots-dropdown-grid" class="slots-dropdown-grid">
        <!-- Rendered dynamically by pack-builder.js -->
      </div>

      <div class="slots-footer-row">
        <span id="slots-filled-status" class="filled-counter">(0 of 3 filled)</span>
        <button type="button" id="clear-slots-btn" class="btn-clear-link">Clear All Slots</button>
      </div>
    </div>
  </fieldset>

  <!-- Dynamic Pack Total and Submission -->
  <div class="pack-checkout-footer text-center">
    <div class="pack-total-line">
      <span>Pack total:</span>
      <strong id="live-pack-total">$0.00</strong>
    </div>

    <button type="button" id="submit-pack-btn" class="pack-action-btn disabled-btn" disabled>
      Please Fill All 3 Slots to Complete Your Pack
    </button>
  </div>
</div>

<!-- Products array payload for client pack script -->
<script id="pack-products-data" type="application/json">
<?= json_encode(array_map(function($p) {
    return [
        'id' => $p['id'],
        'name' => $p['name'],
        'slug' => $p['slug']
    ];
}, $products)) ?>
</script>
