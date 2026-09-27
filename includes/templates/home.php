<?php
declare(strict_types=1);
/**
 * SSG Home Page Template
 * Matches uploaded designs: desktop & mobile mockups.
 * Hero with ratings & jar visual, full-bleed burgundy 3-card highlights band,
 * touch-swipe 6-flavor carousel with green buttons, and customer review cards.
 */
$reviews = get_customer_reviews();
$assetBase = rtrim(getenv('APP_ASSET_BASE') ?: '/SJ-cottage-food/public', '/');
$heroTitle = trim((string)($settings['hero_title'] ?? 'Homemade jam.'));
$heroTitle = $heroTitle !== '' ? $heroTitle : 'Homemade jam.';
$heroTagline = trim((string)($settings['hero_tagline'] ?? 'More fruit. Less sugar. No preservatives.'));
$heroTagline = $heroTagline !== '' ? $heroTagline : 'More fruit. Less sugar. No preservatives.';
$heroRatingVisible = ($settings['hero_rating_visible'] ?? '1') === '1';
$heroRatingText = trim((string)($settings['hero_rating_text'] ?? 'Consistent five star rating from our customers!'));
$heroRatingText = $heroRatingText !== '' ? $heroRatingText : 'Consistent five star rating from our customers!';
$heroImagePath = trim((string)($settings['hero_image_path'] ?? ''));
$heroImageUrl = '';
if ($heroImagePath !== '') {
    $heroAssetCandidate = $heroImagePath;
    $basePrefix = rtrim((string)app_base_path(), '/');
    if ($basePrefix !== '' && str_starts_with($heroAssetCandidate, $basePrefix)) {
        $heroAssetCandidate = substr($heroAssetCandidate, strlen($basePrefix));
    }

    if (preg_match('/^(https?:)?\/\//i', $heroAssetCandidate) === 1) {
        $heroImageUrl = $heroAssetCandidate;
    } elseif (str_starts_with($heroAssetCandidate, '/')) {
        $heroImageUrl = app_url($heroAssetCandidate);
    } elseif (str_starts_with($heroAssetCandidate, 'public/')) {
        $heroImageUrl = app_url('/' . $heroAssetCandidate);
    } else {
        $heroImageUrl = app_url('/public/images/' . ltrim($heroAssetCandidate, '/'));
    }
}
if ($heroImageUrl === '') {
    $heroImageUrl = app_url('/public/assets/images/jam-jar-hero.svg');
}
$heroCtasRaw = json_decode((string)($settings['hero_ctas'] ?? '[]'), true);
$heroCtas = is_array($heroCtasRaw) && $heroCtasRaw !== [] ? $heroCtasRaw : [
    ['label' => 'Shop all jams', 'url' => './shop.html', 'visible' => true],
    ['label' => 'Build a Gift Box', 'url' => './gift-box.html', 'visible' => true],
];
$heroCtas = array_values(array_filter(array_map(function ($cta) {
    if (!is_array($cta)) {
        return null;
    }
    $label = trim((string)($cta['label'] ?? ''));
    $url = trim((string)($cta['url'] ?? './shop.html'));
    $visible = array_key_exists('visible', $cta) ? (bool)$cta['visible'] : true;
    if (is_string($cta['visible'] ?? null)) {
        $visible = strtolower((string)$cta['visible']) !== 'false' && strtolower((string)$cta['visible']) !== '0';
    }
    if ($label === '') {
        return null;
    }
    if ($url === '') {
        $url = './shop.html';
    }
    return ['label' => $label, 'url' => $url, 'visible' => $visible];
}, $heroCtas)));
if ($heroCtas === []) {
    $heroCtas = [
        ['label' => 'Shop all jams', 'url' => './shop.html', 'visible' => true],
        ['label' => 'Build a Gift Box', 'url' => './gift-box.html', 'visible' => true],
    ];
}
?>
<!-- Hero Section -->
<section class="home-hero-section" aria-label="Welcome to Shelly's Jellys">
  <div class="hero-layout-grid">
    <div class="hero-text-block">
      <h1 class="hero-main-title"><?= htmlspecialchars($heroTitle) ?></h1>
      <p class="hero-tagline"><?= htmlspecialchars($heroTagline) ?></p>

      <?php if ($heroRatingVisible): ?>
        <div class="hero-rating-badge" aria-label="5 out of 5 stars customer rating">
          <div class="star-icons-row" aria-hidden="true">
            <span class="star-gold">&#9733;</span>
            <span class="star-gold">&#9733;</span>
            <span class="star-gold">&#9733;</span>
            <span class="star-gold">&#9733;</span>
            <span class="star-gold">&#9733;</span>
          </div>
          <p class="rating-subtext"><?= htmlspecialchars($heroRatingText) ?></p>
        </div>
      <?php endif; ?>

      <div class="hero-cta-buttons">
        <?php foreach ($heroCtas as $cta): ?>
          <?php if (!($cta['visible'] ?? true)) continue; ?>
          <?php $ctaHref = trim((string)($cta['url'] ?? './shop.html')); ?>
          <?php if ($ctaHref === '') { $ctaHref = './shop.html'; } ?>
          <?php if (!preg_match('/^(https?:)?\/\//i', $ctaHref) && !str_starts_with($ctaHref, '/')) { $ctaHref = './' . ltrim($ctaHref, './'); } ?>
          <a href="<?= htmlspecialchars($ctaHref, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="btn-emerald"><?= htmlspecialchars((string)($cta['label'] ?? 'Learn more')) ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Right Side Hero Media -->
    <div class="hero-media-block">
      <img src="<?= htmlspecialchars($heroImageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="Hero image for Shelly's Jellys" class="hero-jar-graphic" width="320" height="400" loading="eager">
    </div>
  </div>
</section>

<!-- Full-bleed Burgundy Band with 3 Interactive Highlights -->
<section class="burgundy-highlights-band" aria-label="Featured Offerings">
  <div class="highlights-grid">
    <!-- Card 1: Build a Pack -->
    <article class="highlight-white-card">
      <h2>Build a Pack</h2>
      <p>build a 3 , 6 or ,12 pack<br>build a Custom pack</p>
      <a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/packs.html" class="btn-olive">Build a Pack</a>
    </article>

    <!-- Card 2: Build a Gift Box -->
    <article class="highlight-white-card">
      <h2>Build a Gift Box</h2>
      <p>Fill a gift box with up to four 4 oz jars.</p>
      <a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/gift-box.html" class="btn-olive">Build a Gift Box</a>
    </article>

    <!-- Card 3: Special Orders -->
    <article class="highlight-white-card">
      <h2>Special Orders</h2>
      <p>Make an order for your special events.</p>
      <a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/custom-orders.html" class="btn-olive">Contact us now!</a>
    </article>
  </div>
</section>

<!-- Touch-Swipe Horizontal Product Slider of All 6 Jams -->
<section class="flavors-slider-section" aria-label="Our 6 Handcrafted Jam Flavors">
  <div class="section-title-wrap">
    <h2>Our Handcrafted Jam Flavors</h2>
    <p>Swipe or drag to explore our small-batch valley favorites ($9.00 – $17.00).</p>
  </div>

  <div class="touch-slider-wrapper">
    <div class="slider-track" id="flavors-slider-track" role="region" aria-label="Flavors carousel" tabindex="0">
      <?php foreach ($products as $prod): ?>
        <?php $canonicalSlug = canonical_product_slug((string)($prod['slug'] ?? $prod['name'])); ?>
        <?php $productImage = normalize_product_image_url((string)($prod['image_url'] ?? ''), (string)($prod['slug'] ?? $prod['name'])); ?>
        <article class="slider-flavor-card" data-product-slug="<?= htmlspecialchars($canonicalSlug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-product-name="<?= htmlspecialchars($prod['name']) ?>">
          <div class="flavor-card-img-wrap">
            <img src="<?= htmlspecialchars($productImage) ?>" alt="<?= htmlspecialchars($prod['name']) ?> Fresh Fruit Preservation" width="140" height="100" loading="lazy">
          </div>
          <h3 class="flavor-card-name"><?= htmlspecialchars($prod['name']) ?></h3>
          <div class="flavor-card-price">$9.00 &ndash; $17.00</div>
          <span class="stock-pill in-stock">In Stock</span>
          <a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/product-<?= htmlspecialchars($canonicalSlug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>.html" class="btn-emerald card-btn">View flavor</a>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Customer Review Carousel -->
<section class="reviews-section" aria-label="Customer Reviews">
  <div class="section-title-wrap text-center">
    <h2>Customer Reviews</h2>
    <p>Real quotes from our Flathead Valley community and gourmet jam lovers.</p>
  </div>

  <div class="reviews-slider-wrapper">
    <div class="reviews-track" id="reviews-slider-track">
      <?php foreach ($reviews as $rev): ?>
        <article class="review-card">
          <div class="review-stars">
            <?php for ($s = 1; $s <= 5; $s++): ?>
              <span class="<?= $s <= $rev['stars'] ? 'star-gold' : 'star-empty' ?>">&#9733;</span>
            <?php endfor; ?>
          </div>
          <div class="review-rating-label"><?= htmlspecialchars($rev['rating_text']) ?></div>
          <blockquote class="review-quote"><?= htmlspecialchars($rev['quote']) ?></blockquote>
          <div class="review-author">
            <strong><?= htmlspecialchars($rev['name']) ?></strong>
            <span class="verified-tag">&#10003; <?= htmlspecialchars($rev['source']) ?></span>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
