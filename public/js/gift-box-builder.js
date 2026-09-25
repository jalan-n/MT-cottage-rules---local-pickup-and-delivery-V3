/**
 * ============================================================================
 * BUILD-A-GIFT-BOX ENGINE (Vanilla ES6+)
 * Up to four 4 oz jars packaged in a custom branded gift box.
 * Matches uploaded 'website design example-build a Gift Box- desktop.jpg' design.
 * ============================================================================
 */

document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('gift-box-app');
  if (!container) return;

  const dataScript = document.getElementById('gift-products-data');
  const products = dataScript ? JSON.parse(dataScript.textContent) : [];

  const chips = document.querySelectorAll('.flavor-chip-btn');
  const slots = document.querySelectorAll('.gift-slot');
  const counterEl = document.getElementById('gift-flavors-counter');
  const clearBtn  = document.getElementById('clear-gift-spaces-btn');
  const totalEl   = document.getElementById('live-gift-total');
  const submitBtn = document.getElementById('submit-gift-box-btn');

  // State: Array of up to 4 chosen flavor names
  let chosenFlavors = [];
  const BASE_BOX_PRICE = 5.00;
  const JAR_PRICE = 9.00;

  function init() {
    bindChips();
    bindSlots();
    checkUrlShortcut();
    updateUI();
  }

  function bindChips() {
    chips.forEach(chip => {
      chip.addEventListener('click', () => {
        const flavor = chip.dataset.flavorName;
        // If already chosen in all 4 slots, ignore
        if (chosenFlavors.length >= 4) {
          alert('All 4 gift box spaces are filled! Clear a space to choose a different flavor.');
          return;
        }

        // Add flavor to next open space
        chosenFlavors.push(flavor);
        updateUI();
      });
    });

    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        chosenFlavors = [];
        updateUI();
      });
    }

    if (submitBtn) {
      submitBtn.addEventListener('click', () => {
        if (chosenFlavors.length === 0) return;

        const total = calculateTotal();
        const boxTitle = `Custom Gift Box (${chosenFlavors.length} Jars)`;

        if (window.cartEngine) {
          window.cartEngine.addItem({
            type: 'gift_box',
            bundle_type: 'gift_box_4',
            title: boxTitle,
            flavors: [...chosenFlavors],
            price: total,
            quantity: 1,
            image: '/assets/images/jam-jar-hero.svg'
          }, true);
        }
      });
    }
  }

  function bindSlots() {
    slots.forEach((slot, idx) => {
      slot.addEventListener('click', () => {
        if (chosenFlavors[idx]) {
          // Remove this specific slot
          chosenFlavors.splice(idx, 1);
          updateUI();
        }
      });
    });
  }

  function updateUI() {
    // 1. Update Slots
    slots.forEach((slot, idx) => {
      const slotNum = idx + 1;
      const flavor = chosenFlavors[idx];

      if (flavor) {
        slot.className = 'gift-slot space-filled';
        slot.innerHTML = `<span>${escapeHtml(flavor)} - 4 oz</span>`;
        slot.title = 'Click to remove this jar';
      } else {
        slot.className = 'gift-slot space-empty';
        slot.innerHTML = `<span>-- Space ${slotNum} empty --</span>`;
        slot.title = 'Click a flavor above to fill this space';
      }
    });

    // 2. Update Chips active state
    chips.forEach(chip => {
      const name = chip.dataset.flavorName;
      if (chosenFlavors.includes(name)) {
        chip.classList.add('active');
      } else {
        chip.classList.remove('active');
      }
    });

    // 3. Update status counter
    const count = chosenFlavors.length;
    if (counterEl) {
      counterEl.textContent = `( ${count} flavor${count === 1 ? '' : 's'} chosen )`;
    }

    // 4. Update Price & Submit Button
    const total = calculateTotal();
    if (totalEl) {
      totalEl.textContent = `$${total.toFixed(2)}`;
    }

    if (count > 0) {
      submitBtn.disabled = false;
      submitBtn.classList.remove('disabled-btn');
      submitBtn.textContent = 'Add Gift Box to Cart';
    } else {
      submitBtn.disabled = true;
      submitBtn.classList.add('disabled-btn');
      submitBtn.textContent = 'Please choose at least one flavor.';
    }
  }

  function calculateTotal() {
    return BASE_BOX_PRICE + (chosenFlavors.length * JAR_PRICE);
  }

  function checkUrlShortcut() {
    const params = new URLSearchParams(window.location.search);
    const flavor = params.get('flavor');
    if (flavor) {
      // Find matching product
      const found = products.find(p => p.name.toLowerCase().includes(flavor.toLowerCase()));
      if (found && chosenFlavors.length < 4) {
        chosenFlavors.push(found.name);
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
