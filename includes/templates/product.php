<?php
declare(strict_types=1);
/**
 * SSG Single Product Detail Page Template
 * Size selector buttons (4 oz, 8 oz, 12 oz) updating price dynamically,
 * stock status indicator, quantity adjuster, and shortcuts to include in Pack/Gift Box.
 */
$brandName = $settings['brand_name'] ?? "Shelly's Jellys LLC";
$deliveryFee = number_format((float)($settings['local_delivery_fee'] ?? 6.50), 2);
$freeThreshold = number_format((float)($settings['free_delivery_threshold'] ?? 45.00), 2);
$assetBase = rtrim(getenv('APP_ASSET_BASE') ?: '/SJ-cottage-food/public', '/');
$productImage = normalize_product_image_url((string)($product['image_url'] ?? ''), (string)($product['slug'] ?? $product['name']));

// Schema.org Product structured data
$schemaOffers = [];
$productSlug = canonical_product_slug((string)($product['slug'] ?? $product['name']));
foreach ($product['variants'] as $v) {
    $schemaOffers[] = [
        '@type' => 'Offer',
        'name' => "{$product['name']} ({$v['size']})",
        'sku' => $v['sku'],
        'price' => number_format((float)$v['price'], 2),
        'priceCurrency' => 'USD',
        'availability' => $v['stock_status'] === 'in_stock' 
            ? 'https://schema.org/InStock' 
            : 'https://schema.org/OutOfStock',
        'url' => "/product-{$productSlug}.html"
    ];
}

$schemaJson = json_encode([
    '@context' => 'https://schema.org/',
    '@type' => 'Product',
    'name' => $product['name'],
    'description' => $product['description'],
    'image' => $productImage,
    'brand' => [
        '@type' => 'Brand',
        'name' => $brandName
    ],
    'offers' => $schemaOffers
] , JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!-- Schema.org Product Structured Data -->
<script type="application/ld+json">
<?= $schemaJson ?>
</script>

<nav class="breadcrumb-nav" aria-label="Breadcrumb navigation">
  <ol class="breadcrumb-list">
    <li><a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/index.html">Home</a></li>
    <li><a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/shop.html">Shop all flavors</a></li>
    <li aria-current="page"><?= htmlspecialchars($product['name']) ?></li>
  </ol>
</nav>

<article class="single-product-layout" data-product-id="<?= (int)$product['id'] ?>" data-product-name="<?= htmlspecialchars($product['name']) ?>">
  <!-- Product Media Column -->
  <div class="product-media-col">
    <div class="product-media-card">
      <img src="<?= htmlspecialchars($productImage) ?>" alt="<?= htmlspecialchars($product['name']) ?> Gourmet Jam Illustration" class="product-detail-img" width="280" height="210" loading="eager">
    </div>
  </div>

  <!-- Product Purchasing Details Column -->
  <div class="product-info-col">
    <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
    <p class="product-lead-desc"><?= htmlspecialchars($product['description']) ?></p>

    <!-- Dynamic Variant & Size Selection Form -->
    <form id="product-purchase-form" class="product-purchase-form" data-product-id="<?= (int)$product['id'] ?>" data-product-name="<?= htmlspecialchars($product['name']) ?>" data-product-image="<?= htmlspecialchars($productImage) ?>">
      
      <fieldset class="size-selector-fieldset">
        <legend class="selector-legend">Select Jar Size:</legend>
        <div class="size-buttons-group" role="radiogroup" aria-label="Jar size options">
          <?php foreach ($product['variants'] as $idx => $v): ?>
            <label class="size-pill-button <?= $idx === 0 ? 'selected' : '' ?> <?= $v['stock_status'] === 'out_of_stock' ? 'disabled' : '' ?>">
              <input type="radio" 
                     name="variant_id" 
                     value="<?= (int)$v['id'] ?>" 
                     data-price="<?= number_format((float)$v['price'], 2) ?>"
                     data-size="<?= htmlspecialchars($v['size']) ?>"
                     data-sku="<?= htmlspecialchars($v['sku']) ?>"
                     data-stock="<?= htmlspecialchars($v['stock_status']) ?>"
                     <?= $idx === 0 ? 'checked' : '' ?>
                     <?= $v['stock_status'] === 'out_of_stock' ? 'disabled' : '' ?>>
              <span class="pill-size"><?= htmlspecialchars($v['size']) ?></span>
              <span class="pill-price">$<?= number_format((float)$v['price'], 2) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <!-- Dynamic Price & Stock Status Strip -->
      <div class="live-variant-bar">
        <div class="price-display">
          <span class="label">Price:</span>
          <strong id="live-product-price" class="current-price">$<?= number_format((float)($product['variants'][0]['price'] ?? 9.00), 2) ?></strong>
        </div>
        <div class="stock-display">
          <span class="label">Status:</span>
          <span id="live-stock-indicator" class="stock-pill in-stock">In Stock</span>
        </div>
        <div class="sku-display">
          <span class="label">SKU:</span>
          <span id="live-sku-val"><?= htmlspecialchars($product['variants'][0]['sku'] ?? '') ?></span>
        </div>
      </div>

      <!-- Quantity Adjuster & Add to Cart -->
      <div class="purchase-action-row">
        <div class="qty-stepper-control" aria-label="Select quantity">
          <button type="button" id="qty-decrement" class="qty-btn" aria-label="Decrease quantity">&minus;</button>
          <input type="number" id="purchase-qty" name="quantity" value="1" min="1" max="99" class="qty-number-input" aria-label="Quantity">
          <button type="button" id="qty-increment" class="qty-btn" aria-label="Increase quantity">&plus;</button>
        </div>
        <button type="submit" id="add-to-cart-action-btn" class="btn-emerald add-cart-btn">
          Add to Cart
        </button>
      </div>
    </form>

    <!-- Shortcut Links to Bundle Builders -->
    <div class="bundle-shortcuts-card">
      <h2>Include in a Bundle:</h2>
      <p>Love this flavor? Add it directly to your custom assortment:</p>
      <div class="shortcuts-actions">
        <a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/packs.html?flavor=<?= urlencode($product['name']) ?>" class="btn-olive shortcut-btn">
          &plus; Include in a Build a Pack
        </a>
        <a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/gift-box.html?flavor=<?= urlencode($product['name']) ?>" class="btn-olive shortcut-btn">
          &plus; Include in a Gift Box
        </a>
      </div>
    </div>

    <!-- Craftsmanship & Ingredients Tab Block -->
    <div class="product-details-accordion">
      <div class="accordion-item">
        <h2>Handcrafted Ingredients</h2>
        <p>Whole fruit, pure unrefined cane sugar, non-GMO fruit pectin, and fresh lemon juice. Never any artificial colors, high-fructose corn syrup, or synthetic preservatives.</p>
      </div>
      <div class="accordion-item">
        <h2>Local Pickup & Valley Delivery</h2>
        <p>Free farmstand pickup in Kalispell or flat $<?= htmlspecialchars($deliveryFee, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> doorstep delivery across the Flathead Valley (FREE on orders over $<?= htmlspecialchars($freeThreshold, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>).</p>
      </div>
    </div>
  </div>
</article>

<script>
// Dynamic Size Button Switcher & Quantity Stepper
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('product-purchase-form');
  if (!form) return;

  const priceEl = document.getElementById('live-product-price');
  const stockEl = document.getElementById('live-stock-indicator');
  const skuEl   = document.getElementById('live-sku-val');
  const radios  = form.querySelectorAll('input[name="variant_id"]');
  const labels  = form.querySelectorAll('.size-pill-button');
  const qtyInput = document.getElementById('purchase-qty');
  const decBtn  = document.getElementById('qty-decrement');
  const incBtn  = document.getElementById('qty-increment');

  labels.forEach(label => {
    label.addEventListener('click', () => {
      labels.forEach(l => l.classList.remove('selected'));
      label.classList.add('selected');
      const radio = label.querySelector('input[type="radio"]');
      if (radio) {
        radio.checked = true;
        if (priceEl) priceEl.textContent = `$${parseFloat(radio.dataset.price).toFixed(2)}`;
        if (skuEl) skuEl.textContent = radio.dataset.sku;
        const inStock = radio.dataset.stock === 'in_stock';
        if (stockEl) {
          stockEl.textContent = inStock ? 'In Stock' : 'Out of Stock';
          stockEl.className = 'stock-pill ' + (inStock ? 'in-stock' : 'out-of-stock');
        }
      }
    });
  });

  if (decBtn && qtyInput) {
    decBtn.addEventListener('click', () => {
      let val = parseInt(qtyInput.value, 10) || 1;
      if (val > 1) qtyInput.value = val - 1;
    });
  }
  if (incBtn && qtyInput) {
    incBtn.addEventListener('click', () => {
      let val = parseInt(qtyInput.value, 10) || 1;
      qtyInput.value = val + 1;
    });
  }
});
</script>
