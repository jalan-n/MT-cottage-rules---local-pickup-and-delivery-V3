<?php
declare(strict_types=1);
/**
 * SSG Contact Us Page Template
 * Farmstand pickup address, kitchen operating hours, delivery radius,
 * and contact inquiry form with CSRF and honeypot spam protection.
 */
$pickupAddress = trim((string)($settings['pickup_address'] ?? '458 Orchard Vista Way, Kalispell, MT 59901'));
$pickupPhone   = trim((string)($settings['pickup_phone'] ?? '(406) 555-0192'));
$pickupEmail   = trim((string)($settings['pickup_email'] ?? 'mtshellysjellys@gmail.com'));
$mapsUrl       = 'https://maps.google.com/?q=' . urlencode($pickupAddress);
$brandName     = $settings['brand_name'] ?? "Shelly's Jellys LLC";
$deliveryFee  = (float)($settings['local_delivery_fee'] ?? 6.50);
$freeThreshold = (float)($settings['free_delivery_threshold'] ?? 45.00);
$deliveryRadiusMiles = trim((string)($settings['delivery_radius_miles'] ?? '20')) ?: '20';
$deliveryCities = get_supported_city_names();
if ($deliveryCities === []) {
  $deliveryCities = ['Kalispell', 'Whitefish', 'Columbia Falls', 'Bigfork', 'Lakeside', 'Somers', 'Creston', 'Kila'];
}
$deliveryCityList = implode(', ', $deliveryCities);
?>
<section class="page-header text-center">
  <h1>Contact Shelly's Jellys</h1>
  <p>Have questions about flavors, special batches, farmstand pickup, or local Flathead delivery? We'd love to hear from you.</p>
</section>

<div class="contact-page-layout">
  <!-- Left Column: Location & Hours -->
  <div class="contact-info-col">
    <div class="card-box info-card">
      <h2>Farmstand & Kitchen Location</h2>
      <address class="contact-address-block">
        <strong><?= htmlspecialchars($brandName) ?> Kitchen</strong><br>
        <?= htmlspecialchars($pickupAddress) ?><br>
        Flathead County, Montana<br>
        <strong>Phone:</strong> <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $pickupPhone) ?: '4065550192') ?>"><?= htmlspecialchars($pickupPhone) ?></a><br>
        <strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($pickupEmail) ?>"><?= htmlspecialchars($pickupEmail) ?></a>
      </address>
      
      <div class="map-action-wrap">
        <a href="<?= htmlspecialchars($mapsUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn-map">
          &#128205; Show Location on Google Map
        </a>
      </div>

      <div class="hours-block">
        <h3>Pickup Hours</h3>
        <p><strong>Tuesday &ndash; Saturday:</strong> 10:00 AM &ndash; 6:00 PM</p>
        <p><strong>Sunday &ndash; Monday:</strong> Closed for small-batch kettle cooking</p>
      </div>

      <div class="delivery-area-block">
        <h3>Local Delivery Zones</h3>
        <p>Doorstep delivery across <?= htmlspecialchars($deliveryCityList, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> ($<?= htmlspecialchars(number_format($deliveryFee, 2), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> flat fee, or FREE on orders over $<?= htmlspecialchars(number_format($freeThreshold, 2), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>).</p>
      </div>
    </div>
  </div>

  <!-- Right Column: Contact Inquiry Form -->
  <div class="contact-form-col">
    <div class="card-box form-card">
      <h2>Send Us a Message</h2>
      <p class="form-subtext">Direct inquiries are monitored by our kitchen and customer support teams.</p>

      <div id="contact-alert" class="form-status-alert" style="display:none;" role="status"></div>

      <form id="contact-form" class="standard-contact-form" novalidate>
        <input type="hidden" name="inquiry_type" value="general">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? bin2hex(random_bytes(16))) ?>">
        
        <!-- Honeypot spam trap -->
        <div style="display:none;" aria-hidden="true">
          <label for="contact_hp">Leave empty</label>
          <input type="text" id="contact_hp" name="website_url_hp" tabindex="-1" autocomplete="off">
        </div>

        <div class="form-group">
          <label for="contact-name">Your Name *</label>
          <input type="text" id="contact-name" name="name" required autocomplete="name">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="contact-email">Email Address *</label>
            <input type="email" id="contact-email" name="email" required autocomplete="email">
          </div>
          <div class="form-group">
            <label for="contact-phone">Phone Number</label>
            <input type="tel" id="contact-phone" name="phone" autocomplete="tel">
          </div>
        </div>

        <div class="form-group">
          <label for="contact-subject">Subject</label>
          <select id="contact-subject" name="subject">
            <option value="General Question">General Question</option>
            <option value="Pickup / Delivery Inquiry">Pickup / Delivery Inquiry</option>
            <option value="Custom Flavor Request">Custom Flavor Request</option>
            <option value="Wholesale & Retail">Wholesale & Retail</option>
          </select>
        </div>

        <div class="form-group">
          <label for="contact-message">Message *</label>
          <textarea id="contact-message" name="message" rows="5" placeholder="How can we help you?" required></textarea>
        </div>

        <button type="submit" id="contact-submit-btn" class="btn-emerald">
          Send Message
        </button>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('contact-form');
  const alertEl = document.getElementById('contact-alert');
  const btn = document.getElementById('contact-submit-btn');

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      btn.disabled = true;
      btn.textContent = 'Sending...';
      alertEl.style.display = 'none';

      const payload = {
        name: document.getElementById('contact-name').value.trim(),
        email: document.getElementById('contact-email').value.trim(),
        phone: document.getElementById('contact-phone').value.trim(),
        subject: document.getElementById('contact-subject').value,
        message: document.getElementById('contact-message').value.trim(),
        inquiry_type: 'general',
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
          alertEl.className = 'form-status-alert success-box';
          alertEl.textContent = 'Thank you for reaching out! Your message has been received by our kitchen team. We will get back to you shortly.';
          alertEl.style.display = 'block';
          form.reset();
        } else {
          alertEl.className = 'form-status-alert error-box';
          alertEl.textContent = data.error || 'Failed to send message. Please try again.';
          alertEl.style.display = 'block';
        }
      } catch (err) {
        alertEl.className = 'form-status-alert error-box';
        alertEl.textContent = 'Network error. Please try again later.';
        alertEl.style.display = 'block';
      } finally {
        btn.disabled = false;
        btn.textContent = 'Send Message';
      }
    });
  }
});
</script>
