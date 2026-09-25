<?php
declare(strict_types=1);
/**
 * SSG Custom Orders & Special Events Template
 * Matches uploaded 'website design example-Special Orders- desktop.jpg' design mockup.
 * Tiered volume pricing cards and rich mustard-gold quote request form.
 */
?>
<section class="special-orders-header text-center">
  <h1 class="special-orders-title">Custom Orders & Special Events</h1>
  <p class="special-orders-subtitle">Whether celebrating a wedding, expressing client appreciation,<br>or stocking private-label pantry jars, our homemade jams won't dissapoint.</p>
</section>

<!-- Tiered Volume Pricing Section -->
<section class="volume-pricing-section" aria-label="Tiered Volume Discounts">
  <h2 class="volume-pricing-heading text-center">Tiered Volume Pricing</h2>
  <div class="tiered-pricing-grid">
    <div class="tier-card">
      <div class="tier-count">48 - 84 jars</div>
      <div class="tier-discount">10% OFF</div>
    </div>
    <div class="tier-card">
      <div class="tier-count">85 - 168 jars</div>
      <div class="tier-discount">12% OFF</div>
    </div>
    <div class="tier-card">
      <div class="tier-count">169 - 252 jars</div>
      <div class="tier-discount">15% OFF</div>
    </div>
  </div>
</section>

<!-- Custom Order Quote Form Section -->
<section class="quote-request-section" aria-label="Request a custom order quote">
  <div class="quote-intro text-center">
    <h2 class="quote-heading">Request a custom order quote</h2>
    <p class="quote-desc">Tell us about your event and desired flavors. Our kitchen team will respond within 24 business hours with pricing and sample options.</p>
  </div>

  <div class="mustard-form-wrapper">
    <div id="custom-order-status" class="form-status-alert" style="display:none;" role="status"></div>

    <form id="custom-order-form" class="mustard-custom-form" novalidate>
      <input type="hidden" name="inquiry_type" value="custom_event">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? bin2hex(random_bytes(16))) ?>">
      <!-- Honeypot anti-spam -->
      <div style="display:none;" aria-hidden="true">
        <label for="co_hp">Leave this empty</label>
        <input type="text" id="co_hp" name="website_url_hp" tabindex="-1" autocomplete="off">
      </div>

      <!-- Row 1: Full Name, Email Address, Phone Number -->
      <div class="mustard-form-row three-col">
        <div class="form-field-group">
          <label for="co_full_name">*Full Name</label>
          <input type="text" id="co_full_name" name="full_name" required autocomplete="name">
        </div>
        <div class="form-field-group">
          <label for="co_email">*Email Address</label>
          <input type="email" id="co_email" name="email" required autocomplete="email">
        </div>
        <div class="form-field-group">
          <label for="co_phone">* Phone Number</label>
          <input type="tel" id="co_phone" name="phone" required autocomplete="tel">
        </div>
      </div>

      <!-- Row 2: Street Address, City -->
      <div class="mustard-form-row two-col">
        <div class="form-field-group">
          <label for="co_street">* Street Address</label>
          <input type="text" id="co_street" name="street_address" required autocomplete="street-address">
        </div>
        <div class="form-field-group">
          <label for="co_city">*City</label>
          <input type="text" id="co_city" name="city" required value="Kalispell">
        </div>
      </div>

      <!-- Row 3: Estimate Quantity, Target Event Date -->
      <div class="mustard-form-row two-col">
        <div class="form-field-group">
          <label for="co_quantity">*Estimate Quantity</label>
          <select id="co_quantity" name="estimated_jars" required>
            <option value="48 - 52 jars">48 - 52 jars</option>
            <option value="53 - 84 jars">53 - 84 jars (10% OFF)</option>
            <option value="85 - 168 jars">85 - 168 jars (12% OFF)</option>
            <option value="169 - 252 jars">169 - 252 jars (15% OFF)</option>
            <option value="253+ jars">253+ jars (Custom Corporate Tier)</option>
          </select>
        </div>
        <div class="form-field-group">
          <label for="co_date">*Target Event Date</label>
          <input type="date" id="co_date" name="event_date" required>
        </div>
      </div>

      <!-- Row 4: Event Details Textarea -->
      <div class="mustard-form-row one-col">
        <div class="form-field-group">
          <label for="co_message">* Tell us about your event.</label>
          <textarea id="co_message" name="message" rows="4" placeholder="--Details about your event--" required></textarea>
        </div>
      </div>

      <!-- Submit Button Row -->
      <div class="mustard-btn-row">
        <button type="submit" id="co-submit-request-btn" class="btn-mustard-submit">
          Send Custom Request
        </button>
      </div>
    </form>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('custom-order-form');
  const statusEl = document.getElementById('custom-order-status');
  const btn = document.getElementById('co-submit-request-btn');

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      btn.disabled = true;
      btn.textContent = 'Sending Request...';
      statusEl.style.display = 'none';

      const payload = {
        name: document.getElementById('co_full_name').value.trim(),
        email: document.getElementById('co_email').value.trim(),
        phone: document.getElementById('co_phone').value.trim(),
        address: `${document.getElementById('co_street').value.trim()}, ${document.getElementById('co_city').value.trim()}`,
        estimated_jars: document.getElementById('co_quantity').value,
        event_date: document.getElementById('co_date').value,
        message: document.getElementById('co_message').value.trim(),
        inquiry_type: 'custom_event',
        website_url_hp: form.querySelector('input[name="website_url_hp"]').value
      };

      try {
        const res = await fetch('/api/contact.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
          statusEl.className = 'form-status-alert success-box';
          statusEl.textContent = 'Thank you! Your custom order request has been dispatched to our kitchen team. We will respond within 24 business hours.';
          statusEl.style.display = 'block';
          form.reset();
        } else {
          statusEl.className = 'form-status-alert error-box';
          statusEl.textContent = data.error || 'Failed to submit quote request. Please try again.';
          statusEl.style.display = 'block';
        }
      } catch (err) {
        statusEl.className = 'form-status-alert error-box';
        statusEl.textContent = 'Network error. Please check your connection and try again.';
        statusEl.style.display = 'block';
      } finally {
        btn.disabled = false;
        btn.textContent = 'Send Custom Request';
      }
    });
  }
});
</script>
