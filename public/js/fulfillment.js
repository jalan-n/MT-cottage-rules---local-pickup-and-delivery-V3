/**
 * ============================================================================
 * LOCAL FULFILLMENT ENGINE (Vanilla ES6+)
 * ============================================================================
 * Manages Local Pickup vs Local Delivery, address requirements,
 * delivery zone validation, and date/time window scheduling.
 */

class FulfillmentEngine {
  constructor() {
    this.currentType = 'pickup'; // 'pickup' | 'delivery'
    this.zipCode = '';
    this.isZipValid = true;
    this.initDOM();
    this.bindEvents();
    this.setMinDates();
  }

  initDOM() {
    this.radios = document.querySelectorAll('input[name="fulfillment_type"]');
    this.deliveryAddressGroup = document.getElementById('delivery-address-group');
    this.dateLabel = document.getElementById('date-label');
    this.timeLabel = document.getElementById('time-label');
    this.dateInput = document.getElementById('fulfillment-date');
    this.zipInput = document.getElementById('cust-zip');
    this.zipHint = document.getElementById('zip-validation-msg');
  }

  bindEvents() {
    this.radios.forEach(radio => {
      radio.addEventListener('change', () => {
        this.currentType = radio.value;
        this.updateFulfillmentView();
        this.triggerValidation();
      });
    });

    if (this.zipInput) {
      let debounceTimeout = null;
      this.zipInput.addEventListener('input', () => {
        clearTimeout(debounceTimeout);
        debounceTimeout = setTimeout(() => {
          this.zipCode = this.zipInput.value.trim();
          this.triggerValidation();
        }, 400);
      });
    }
  }

  setMinDates() {
    if (!this.dateInput) return;
    // Set minimum date to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const yyyy = tomorrow.getFullYear();
    const mm = String(tomorrow.getMonth() + 1).padStart(2, '0');
    const dd = String(tomorrow.getDate()).padStart(2, '0');
    this.dateInput.min = `${yyyy}-${mm}-${dd}`;
    this.dateInput.value = `${yyyy}-${mm}-${dd}`;
  }

  updateFulfillmentView() {
    if (this.currentType === 'delivery') {
      if (this.deliveryAddressGroup) this.deliveryAddressGroup.style.display = 'block';
      if (this.dateLabel) this.dateLabel.textContent = 'Delivery Date *';
      if (this.timeLabel) this.timeLabel.textContent = 'Delivery Window *';
    } else {
      if (this.deliveryAddressGroup) this.deliveryAddressGroup.style.display = 'none';
      if (this.dateLabel) this.dateLabel.textContent = 'Pickup Date *';
      if (this.timeLabel) this.timeLabel.textContent = 'Pickup Window *';
      if (this.zipHint) this.zipHint.textContent = '';
    }
  }

  async triggerValidation() {
    if (window.checkoutEngine) {
      await window.checkoutEngine.revalidatePrices();
    }
  }

  getType() {
    return this.currentType;
  }

  getZip() {
    return this.zipCode;
  }
}

document.addEventListener('DOMContentLoaded', () => {
  window.fulfillmentEngine = new FulfillmentEngine();
});
