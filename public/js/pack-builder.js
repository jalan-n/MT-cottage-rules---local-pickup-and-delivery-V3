/**
 * ============================================================================
 * BUILD-A-PACK ENGINE (Vanilla ES6+)
 * Step 1: Pack Size (3, 6, 12 with automatic discounts)
 * Step 2: Uniform Jar Size (4 oz, 8 oz, 12 oz)
 * Step 3: Quick Pack autofill + Individual Slot Dropdowns
 * ============================================================================
 */

document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('pack-builder-app');
  if (!container) return;

  // Read product list
  const dataScript = document.getElementById('pack-products-data');
  const products = dataScript ? JSON.parse(dataScript.textContent) : [];

  // Elements
  const packRadios = document.querySelectorAll('input[name="pack_count"]');
  const jarRadios  = document.querySelectorAll('input[name="pack_jar_size"]');
  const quickSelect = document.getElementById('quick-pack-select');
  const slotsGrid   = document.getElementById('slots-dropdown-grid');
  const statusEl    = document.getElementById('slots-filled-status');
  const clearBtn    = document.getElementById('clear-slots-btn');
  const totalEl     = document.getElementById('live-pack-total');
  const submitBtn   = document.getElementById('submit-pack-btn');

  // State
  let packCount = 3;
  let bundleType = 'pack_3';
  let discountRate = 0.05;
  let jarSize = '4 oz';
  let basePrice = 9.00;
  let selectedFlavors = [];

  function init() {
    bindOptions();
    renderSlots();
    checkUrlShortcut();
    calculateTotal();
  }

  function bindOptions() {
    // Pack size pill selection
    packRadios.forEach(radio => {
      radio.closest('.pack-pill-choice').addEventListener('click', () => {
        document.querySelectorAll('.pack-pill-choice').forEach(el => el.classList.remove('active'));
        radio.closest('.pack-pill-choice').classList.add('active');
        radio.checked = true;

        packCount = parseInt(radio.value, 10);
        bundleType = radio.dataset.bundleType;
        discountRate = parseFloat(radio.dataset.discount);
        renderSlots();
        calculateTotal();
      });
    });

    // Jar size pill selection
    jarRadios.forEach(radio => {
      radio.closest('.size-pill-choice').addEventListener('click', () => {
        document.querySelectorAll('.size-pill-choice').forEach(el => el.classList.remove('active'));
        radio.closest('.size-pill-choice').classList.add('active');
        radio.checked = true;

        jarSize = radio.value;
        basePrice = parseFloat(radio.dataset.basePrice);
        calculateTotal();
      });
    });

    // Quick Pack Autofill
    if (quickSelect) {
      quickSelect.addEventListener('change', () => {
        const val = quickSelect.value;
        if (!val) return;

        selectedFlavors = Array(packCount).fill(val);
        const dropdowns = slotsGrid.querySelectorAll('.slot-select');
        dropdowns.forEach(dd => { dd.value = val; });
        updateStatus();
        calculateTotal();
      });
    }

    // Clear all slots
    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        selectedFlavors = [];
        if (quickSelect) quickSelect.value = '';
        const dropdowns = slotsGrid.querySelectorAll('.slot-select');
        dropdowns.forEach(dd => { dd.value = ''; });
        updateStatus();
        calculateTotal();
      });
    }

    // Add Pack to Cart
    if (submitBtn) {
      submitBtn.addEventListener('click', () => {
        if (selectedFlavors.filter(Boolean).length !== packCount) return;

        const total = calculateTotal();
        const packTitle = `${packCount}-Pack (${jarSize} Jars)`;

        if (window.cartEngine) {
          window.cartEngine.addItem({
            type: 'pack',
            bundle_type: bundleType,
            jar_size: jarSize,
            title: packTitle,
            flavors: [...selectedFlavors],
            price: total,
            quantity: 1,
            image: '/assets/images/jam-jar-hero.svg'
          }, true);
        }
      });
    }
  }

  function renderSlots() {
    slotsGrid.innerHTML = '';
    // Resize selectedFlavors array to match new pack count
    if (selectedFlavors.length > packCount) {
      selectedFlavors = selectedFlavors.slice(0, packCount);
    }

    for (let i = 0; i < packCount; i++) {
      const slotNum = i + 1;
      const slotDiv = document.createElement('div');
      slotDiv.className = 'slot-item';

      const label = document.createElement('label');
      label.setAttribute('for', `slot-select-${slotNum}`);
      label.textContent = `Slot ${slotNum}`;

      const select = document.createElement('select');
      select.id = `slot-select-${slotNum}`;
      select.className = 'custom-select-field slot-select';
      select.innerHTML = '<option value="">-- Choose a flavor --</option>' + 
        products.map(p => `<option value="${escapeHtml(p.name)}">${escapeHtml(p.name)}</option>`).join('');

      if (selectedFlavors[i]) {
        select.value = selectedFlavors[i];
      }

      select.addEventListener('change', () => {
        selectedFlavors[i] = select.value;
        if (quickSelect) quickSelect.value = '';
        updateStatus();
        calculateTotal();
      });

      slotDiv.appendChild(label);
      slotDiv.appendChild(select);
      slotsGrid.appendChild(slotDiv);
    }

    updateStatus();
  }

  function updateStatus() {
    const filledCount = selectedFlavors.filter(Boolean).length;
    if (statusEl) {
      statusEl.textContent = `(${filledCount} of ${packCount} filled)`;
    }

    if (filledCount === packCount) {
      submitBtn.disabled = false;
      submitBtn.classList.remove('disabled-btn');
      submitBtn.textContent = `Add ${packCount}-Pack to Cart`;
    } else {
      submitBtn.disabled = true;
      submitBtn.classList.add('disabled-btn');
      submitBtn.textContent = `Please Fill All ${packCount} Slots to Complete Your Pack`;
    }
  }

  function calculateTotal() {
    const rawTotal = packCount * basePrice;
    const discountedTotal = rawTotal * (1 - discountRate);
    const finalFormatted = discountedTotal.toFixed(2);

    if (totalEl) {
      totalEl.textContent = `$${finalFormatted}`;
    }
    return parseFloat(finalFormatted);
  }

  function checkUrlShortcut() {
    const params = new URLSearchParams(window.location.search);
    const flavor = params.get('flavor');
    if (flavor && quickSelect) {
      // Find matching option
      for (let opt of quickSelect.options) {
        if (opt.value.toLowerCase().includes(flavor.toLowerCase())) {
          quickSelect.value = opt.value;
          quickSelect.dispatchEvent(new Event('change'));
          break;
        }
      }
    }
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
  }

  init();
});
