<?php
declare(strict_types=1);
/**
 * SSG Cart Page Template (/cart.html)
 * Semantic, accessible shopping cart page with line-item breakdown,
 * fulfillment selector (Delivery vs Pickup), delivery fee estimation,
 * and shareable cart link generation.
 */
$pickupAddress = $settings['pickup_address'] ?? '458 Orchard Vista Way, Kalispell, MT 59901';
$deliveryFee   = number_format((float)($settings['local_delivery_fee'] ?? 6.50), 2);
$freeThreshold = number_format((float)($settings['free_delivery_threshold'] ?? 45.00), 2);
$assetBase     = rtrim(getenv('APP_ASSET_BASE') ?: '/SJ-cottage-food/public', '/');
?>
<section class="page-header text-center">
  <h1>Your Shopping Basket</h1>
  <p>Review your handcrafted preserves, select local delivery or pickup, and proceed to checkout.</p>
</section>

<div class="cart-page-layout" id="cart-page-app">
  <!-- Left Column: Items List -->
  <div class="cart-items-section">
    <div class="card-box">
      <h2>Basket Items</h2>
      <div id="cart-page-items" class="cart-page-table-wrapper">
        <!-- Rendered dynamically by cart.js -->
        <p class="empty-state">Loading your basket items...</p>
      </div>

      <div class="cart-actions-row">
        <a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/shop.html" class="btn-secondary">&larr; Continue Shopping</a>
        <button type="button" id="share-cart-btn" class="btn-share" title="Copy shareable cart link">
          &#128279; Shareable Cart Link
        </button>
      </div>
      <div id="share-link-feedback" class="share-feedback" style="display:none;" role="status"></div>
    </div>
  </div>

  <!-- Right Column: Fulfillment & Totals -->
  <aside class="cart-summary-section">
    <div class="card-box summary-box">
      <h2>Order Summary & Fulfillment</h2>

      <fieldset class="cart-fulfillment-fieldset">
        <legend>Select Fulfillment Method</legend>
        <div class="fulfillment-radio-group">
          <label class="fulfillment-choice">
            <input type="radio" name="cart_page_fulfillment" value="pickup" checked>
            <div>
              <strong>Farmstand Pickup (FREE)</strong>
              <small><?= htmlspecialchars($pickupAddress) ?></small>
            </div>
          </label>
          <label class="fulfillment-choice">
            <input type="radio" name="cart_page_fulfillment" value="delivery">
            <div>
              <strong>Local Valley Delivery ($<?= $deliveryFee ?>)</strong>
              <small>FREE for orders over $<?= $freeThreshold ?></small>
            </div>
          </label>
        </div>

        <!-- Inline ZIP verification for delivery fee preview -->
        <div id="cart-zip-wrapper" class="cart-zip-preview" style="display:none;">
          <label for="cart-est-zip">Delivery ZIP Code:</label>
          <div class="zip-inline-input">
            <input type="text" id="cart-est-zip" placeholder="59901" maxlength="5">
            <button type="button" id="cart-calc-zip-btn" class="btn-secondary">Check</button>
          </div>
          <p id="cart-zip-status" class="field-hint"></p>
        </div>
      </fieldset>

      <table class="summary-table">
        <tbody>
          <tr>
            <td>Items Subtotal:</td>
            <td id="cart-page-subtotal" class="text-right">$0.00</td>
          </tr>
          <tr>
            <td>Fulfillment Fee:</td>
            <td id="cart-page-fulfillment-fee" class="text-right">$0.00</td>
          </tr>
          <tr>
            <td>Estimated Tax:</td>
            <td id="cart-page-tax" class="text-right">$0.00</td>
          </tr>
          <tr class="grand-total-row">
            <td><strong>Estimated Total:</strong></td>
            <td id="cart-page-total" class="text-right"><strong>$0.00</strong></td>
          </tr>
        </tbody>
      </table>

      <div class="cart-checkout-cta">
        <a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/checkout.html" id="proceed-to-checkout-btn" class="checkout-proceed-btn">
          Proceed to Checkout &rarr;
        </a>
      </div>
    </div>
  </aside>
</div>
