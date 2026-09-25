<?php
declare(strict_types=1);
/**
 * SSG Checkout Page Template (/checkout.html)
 * Two-column checkout layout, Square Payment element, and printable Thank You state.
 */
$pickupAddress = $settings['pickup_address'] ?? '458 Orchard Vista Way, Kalispell, MT 59901';
$mapsUrl       = 'https://maps.google.com/?q=' . urlencode($pickupAddress);
$deliveryFee   = number_format((float)($settings['local_delivery_fee'] ?? 6.50), 2);
$freeThreshold = number_format((float)($settings['free_delivery_threshold'] ?? 45.00), 2);
$assetBase     = rtrim(getenv('APP_ASSET_BASE') ?: '/SJ-cottage-food/public', '/');
?>
<section class="page-header text-center">
  <h1>Complete Your Gourmet Jam Order</h1>
  <p>Secure checkout with Square. Flathead Valley local delivery or farmstand pickup.</p>
</section>

<!-- Active Checkout Form Section -->
<div id="checkout-view" class="checkout-layout">
  <!-- Column One: Customer Contact & Fulfillment Details -->
  <div class="checkout-col checkout-form-col">
    <div id="checkout-form-feedback" class="checkout-feedback" style="display:none;" role="alert"></div>

    <form id="standalone-checkout-form" novalidate>
      <!-- Customer Information -->
      <fieldset class="form-section">
        <legend>1. Customer Contact</legend>
        <div class="form-group">
          <label for="co-name">Full Name *</label>
          <input type="text" id="co-name" name="full_name" required autocomplete="name">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="co-email">Email Address *</label>
            <input type="email" id="co-email" name="email" required autocomplete="email">
          </div>
          <div class="form-group">
            <label for="co-phone">Phone Number *</label>
            <input type="tel" id="co-phone" name="phone" required autocomplete="tel">
          </div>
        </div>
      </fieldset>

      <!-- Fulfillment Switcher -->
      <fieldset class="form-section">
        <legend>2. Fulfillment Method</legend>
        <div class="fulfillment-radio-group">
          <label class="fulfillment-choice">
            <input type="radio" name="fulfillment_type" value="pickup" checked>
            <div>
              <strong>Farmstand Pickup (FREE)</strong>
              <small><?= htmlspecialchars($pickupAddress) ?></small>
            </div>
          </label>

          <label class="fulfillment-choice">
            <input type="radio" name="fulfillment_type" value="delivery">
            <div>
              <strong>Local Valley Delivery ($<?= $deliveryFee ?>)</strong>
              <small>FREE for orders over $<?= $freeThreshold ?></small>
            </div>
          </label>
        </div>

        <!-- Pickup Details Section -->
        <div id="pickup-location-box" class="pickup-location-card">
          <h4>Farmstand Location</h4>
          <address>
            <strong>Shelly's Jellys Kitchen</strong><br>
            <?= htmlspecialchars($pickupAddress) ?>
          </address>
          <a href="<?= htmlspecialchars($mapsUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn-map">
            &#128205; Show location on Google Map
          </a>
        </div>

        <!-- Delivery Address Section -->
        <div id="delivery-address-section" class="delivery-address-group" style="display:none;">
          <div class="form-group">
            <label for="co-street">Street Address *</label>
            <input type="text" id="co-street" name="street_address" autocomplete="street-address">
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="co-city">City *</label>
              <input type="text" id="co-city" name="city" value="Kalispell">
            </div>
            <div class="form-group">
              <label for="co-zip">Postal Code (ZIP) *</label>
              <input type="text" id="co-zip" name="zip_code" placeholder="59901" maxlength="10">
              <span id="co-zip-feedback" class="field-hint"></span>
            </div>
          </div>
        </div>

        <!-- Schedule & Instructions -->
        <div class="form-row">
          <div class="form-group">
            <label for="co-date" id="co-date-label">Pickup Date *</label>
            <input type="date" id="co-date" name="fulfillment_date" required>
          </div>
          <div class="form-group">
            <label for="co-time-slot" id="co-time-label">Pickup Window *</label>
            <select id="co-time-slot" name="fulfillment_time_slot" required>
              <option value="">Select a time window...</option>
              <option value="10:00 AM - 12:00 PM">10:00 AM - 12:00 PM</option>
              <option value="12:00 PM - 2:00 PM">12:00 PM - 2:00 PM</option>
              <option value="2:00 PM - 4:00 PM">2:00 PM - 4:00 PM</option>
              <option value="4:00 PM - 6:00 PM">4:00 PM - 6:00 PM</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label for="co-notes">Special Instructions or Gift Notes</label>
          <textarea id="co-notes" name="special_instructions" rows="2" placeholder="Gate codes, porch drop instructions, or note to recipient..."></textarea>
        </div>
      </fieldset>

      <!-- Mobile Button Placeholder: Desktop submit is in Column 2 -->
    </form>
  </div>

  <!-- Column Two: Order Summary & Square Payment -->
  <div class="checkout-col checkout-summary-col">
    <!-- Order Summary Card -->
    <div class="card-box summary-card">
      <div class="summary-card-header">
        <h2>Order Summary</h2>
        <a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/cart.html" class="btn-edit-cart">&#9998; Edit Cart</a>
      </div>

      <div class="table-responsive">
        <table class="order-table">
          <thead>
            <tr>
              <th>Item</th>
              <th class="text-center">Qty</th>
              <th class="text-right">Price</th>
              <th class="text-right">Total</th>
            </tr>
          </thead>
          <tbody id="checkout-items-rows">
            <!-- Injected dynamically by checkout.js -->
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3">Items Subtotal:</td>
              <td id="co-subtotal-val" class="text-right">$0.00</td>
            </tr>
            <tr>
              <td colspan="3">Fulfillment (<span id="co-fulfillment-type-name">Pickup</span>):</td>
              <td id="co-delivery-val" class="text-right">$0.00</td>
            </tr>
            <tr>
              <td colspan="3">Sales Tax:</td>
              <td id="co-tax-val" class="text-right">$0.00</td>
            </tr>
            <tr class="grand-total-row">
              <td colspan="3"><strong>Total Amount:</strong></td>
              <td id="co-grand-total-val" class="text-right"><strong>$0.00</strong></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Square Payment Element -->
      <fieldset class="payment-fieldset">
        <legend>Secure Card Payment</legend>
        <p class="payment-subtext">Protected by 256-bit encryption through Square Payments.</p>
        <div id="checkout-card-element" class="square-card-container"></div>
        <div id="checkout-card-errors" class="error-text" role="alert"></div>
      </fieldset>

      <button type="button" id="co-submit-btn" class="primary-checkout-btn">
        Authorize & Place Order
      </button>
    </div>
  </div>
</div>

<!-- Order Confirmation / Printable Thank You View -->
<div id="thank-you-view" class="thank-you-section" style="display:none;">
  <div class="thank-you-card printable-card">
    <div class="success-banner">
      <div class="check-icon">&#10003;</div>
      <h2>Thank You for Stopping By Today!</h2>
      <p class="confirmation-lead">An email has been sent to <strong id="ty-customer-email">customer@example.com</strong> with your complete order details.</p>
    </div>

    <div class="order-ref-block">
      <span>Order Reference Number:</span>
      <h3 id="ty-order-number" class="order-number-display">SJ-000000</h3>
    </div>

    <!-- Fulfillment Instructions -->
    <div class="instructions-block">
      <h4>Fulfillment Instructions:</h4>
      <p id="ty-fulfillment-instructions">Your farmstand pickup is scheduled. Please bring your order number when arriving.</p>
    </div>

    <!-- Printable Receipt -->
    <div class="receipt-table-block">
      <h4>Customer Receipt:</h4>
      <table class="receipt-table">
        <thead>
          <tr>
            <th>Item</th>
            <th class="text-center">Qty</th>
            <th class="text-right">Price</th>
            <th class="text-right">Total</th>
          </tr>
        </thead>
        <tbody id="ty-receipt-items"></tbody>
        <tfoot>
          <tr>
            <td colspan="3">Items Subtotal:</td>
            <td id="ty-subtotal" class="text-right">$0.00</td>
          </tr>
          <tr>
            <td colspan="3">Fulfillment Fee:</td>
            <td id="ty-delivery" class="text-right">$0.00</td>
          </tr>
          <tr>
            <td colspan="3">Sales Tax:</td>
            <td id="ty-tax" class="text-right">$0.00</td>
          </tr>
          <tr class="receipt-grand-total">
            <td colspan="3"><strong>Total Paid:</strong></td>
            <td id="ty-total" class="text-right"><strong>$0.00</strong></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Action Buttons: Print, Social Share, Add as Contact -->
    <div class="thank-you-actions no-print">
      <button type="button" onclick="window.print()" class="btn-print">
        &#128424; Print Receipt
      </button>

      <button type="button" id="ty-share-btn" class="btn-share-social">
        &#128172; Share on Social Media
      </button>

      <a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/ShellysJellyscontact.vcf" download="ShellysJellyscontact.vcf" class="btn-vcard">
        &#128100; Add Us as a Contact (vCard)
      </a>

      <a href="<?= htmlspecialchars($assetBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/shop.html" class="btn-primary">
        Back to Shop
      </a>
    </div>
  </div>
</div>
