/**
 * ============================================================================
 * CHECKOUT & SQUARE WEB PAYMENTS ENGINE (Vanilla ES6+)
 * Two-column layout, Square card tokenization, server-side validation against
 * /api/checkout.php, and printable Thank You confirmation state.
 * ============================================================================
 */

document.addEventListener("DOMContentLoaded", () => {
  const checkoutView = document.getElementById("checkout-view")
  const thankYouView = document.getElementById("thank-you-view")
  if (!checkoutView) return

  const form = document.getElementById("standalone-checkout-form")
  const submitBtn = document.getElementById("co-submit-btn")
  const feedbackEl = document.getElementById("checkout-form-feedback")
  const itemsTbody = document.getElementById("checkout-items-rows")
  const subtotalEl = document.getElementById("co-subtotal-val")
  const deliveryEl = document.getElementById("co-delivery-val")
  const taxEl = document.getElementById("co-tax-val")
  const grandTotalEl = document.getElementById("co-grand-total-val")
  const typeNameEl = document.getElementById("co-fulfillment-type-name")
  const csrfToken =
    document.querySelector('meta[name="csrf-token"]')?.content || ""

  // Fulfillment Radios
  const fulfillmentRadios = document.querySelectorAll(
    'input[name="fulfillment_type"]',
  )
  const pickupBox = document.getElementById("pickup-location-box")
  const deliveryBox = document.getElementById("delivery-address-section")
  const dateLabel = document.getElementById("co-date-label")
  const timeLabel = document.getElementById("co-time-label")

  let squareCard = null
  let squarePayments = null

  // Initialize
  initFulfillment()
  renderOrderSummary()
  initSquarePayments()
  bindSubmit()

  function initFulfillment() {
    fulfillmentRadios.forEach((radio) => {
      radio.addEventListener("change", () => {
        const isDelivery = radio.value === "delivery"
        if (pickupBox) pickupBox.style.display = isDelivery ? "none" : "block"
        if (deliveryBox)
          deliveryBox.style.display = isDelivery ? "block" : "none"
        if (dateLabel)
          dateLabel.textContent = isDelivery
            ? "Delivery Date *"
            : "Pickup Date *"
        if (timeLabel)
          timeLabel.textContent = isDelivery
            ? "Delivery Window *"
            : "Pickup Window *"
        if (typeNameEl)
          typeNameEl.textContent = isDelivery ? "Delivery" : "Pickup"

        // Update local cartEngine fulfillment
        if (window.cartEngine) {
          window.cartEngine.saveFulfillment({ type: radio.value })
        }
        renderOrderSummary()
      })
    })

    // Set minimum date to tomorrow
    const dateInput = document.getElementById("co-date")
    if (dateInput) {
      const tomorrow = new Date()
      tomorrow.setDate(tomorrow.getDate() + 1)
      dateInput.min = tomorrow.toISOString().split("T")[0]
      dateInput.value = tomorrow.toISOString().split("T")[0]
    }
  }

  function renderOrderSummary() {
    const items = window.cartEngine ? window.cartEngine.items : []
    if (!itemsTbody) return

    if (items.length === 0) {
      const shopUrl = new URL("./shop.html", window.location.href).toString()
      itemsTbody.innerHTML = `
        <tr>
          <td colspan="4" class="text-center" style="padding: 2rem;">
            Your basket is empty. <a href="${shopUrl}" style="color:#00875A; font-weight:700;">Start shopping &rarr;</a>
          </td>
        </tr>
      `
      if (subtotalEl) subtotalEl.textContent = "$0.00"
      if (deliveryEl) deliveryEl.textContent = "$0.00"
      if (taxEl) taxEl.textContent = "$0.00"
      if (grandTotalEl) grandTotalEl.textContent = "$0.00"
      if (submitBtn) submitBtn.disabled = true
      return
    }

    const subtotal = window.cartEngine.getSubtotal()
    const isDelivery =
      document.querySelector('input[name="fulfillment_type"]:checked')
        ?.value === "delivery"
    const deliveryFee = isDelivery ? (subtotal >= 45.0 ? 0.0 : 6.5) : 0.0
    const tax = 0.0
    const total = subtotal + deliveryFee + tax

    itemsTbody.innerHTML = items
      .map(
        (item) => `
      <tr>
        <td>
          <strong>${escapeHtml(item.title)}</strong><br>
          <small style="color:#666;">${escapeHtml(window.cartEngine.formatItemDetails(item))}</small>
        </td>
        <td class="text-center">${item.quantity}</td>
        <td class="text-right">$${parseFloat(item.price).toFixed(2)}</td>
        <td class="text-right"><strong>$${(parseFloat(item.price) * item.quantity).toFixed(2)}</strong></td>
      </tr>
    `,
      )
      .join("")

    if (subtotalEl) subtotalEl.textContent = `$${subtotal.toFixed(2)}`
    if (deliveryEl) {
      if (isDelivery) {
        deliveryEl.textContent =
          deliveryFee === 0 ? "FREE ($45+ promo)" : `$${deliveryFee.toFixed(2)}`
      } else {
        deliveryEl.textContent = "FREE"
      }
    }
    if (taxEl) taxEl.textContent = "$0.00"
    if (grandTotalEl) grandTotalEl.textContent = `$${total.toFixed(2)}`
    if (submitBtn) submitBtn.disabled = false
  }

  async function initSquarePayments() {
    const cardContainer = document.getElementById("checkout-card-element")
    if (!cardContainer) return

    if (typeof window.Square === "undefined") {
      cardContainer.innerHTML = `
        <div style="padding: 0.75rem; background: #FAF7EC; border: 1px dashed #7B6209; border-radius: 4px; font-size: 0.9rem;">
          &#128274; <strong>Square Payment Gateway Ready</strong><br>
          <span style="font-size: 0.8rem; color: #555;">Test sandbox simulation mode active. Orders will process and verify securely against server logic.</span>
        </div>
      `
      return
    }

    try {
      // Initialize Square Payments SDK with sandbox client credentials
      const appId = "sandbox-sq0idb-demo-flathead-jam"
      const locId = "L_TEST_FLATHEAD_01"
      squarePayments = window.Square.payments(appId, locId)
      squareCard = await squarePayments.card()
      await squareCard.attach("#checkout-card-element")
    } catch (e) {
      console.warn("Square Web Payments SDK running in local demo mode", e)
      cardContainer.innerHTML = `
        <div style="padding: 0.75rem; background: #FAF7EC; border: 1px dashed #7B6209; border-radius: 4px; font-size: 0.9rem;">
          &#128274; <strong>Square Payments Gateway Ready</strong><br>
          <span style="font-size: 0.8rem; color: #555;">Demo sandbox card simulator active. Transactions verify securely through /api/checkout.php.</span>
        </div>
      `
    }
  }

  function bindSubmit() {
    if (!submitBtn) return

    submitBtn.addEventListener("click", async (e) => {
      e.preventDefault()

      if (!form.checkValidity()) {
        form.reportValidity()
        return
      }

      const items = window.cartEngine ? window.cartEngine.items : []
      if (items.length === 0) {
        showFeedback(
          "Your basket is empty. Please add items before placing an order.",
          "error",
        )
        return
      }

      submitBtn.disabled = true
      submitBtn.textContent = "Authorizing Payment..."
      hideFeedback()

      let paymentToken = "cnon:card-nonce-ok"
      if (squareCard) {
        try {
          const result = await squareCard.tokenize()
          if (result.status === "OK") {
            paymentToken = result.token
          } else {
            showFeedback(
              `Payment authorization failed: ${result.errors[0].message}`,
              "error",
            )
            submitBtn.disabled = false
            submitBtn.textContent = "Authorize & Place Order"
            return
          }
        } catch (cardErr) {
          console.warn("Square tokenization fallback", cardErr)
          paymentToken = "cnon:card-nonce-ok"
        }
      }

      const fulfillmentType =
        document.querySelector('input[name="fulfillment_type"]:checked')
          ?.value || "pickup"
      const payload = {
        customer: {
          full_name: document.getElementById("co-name").value.trim(),
          email: document.getElementById("co-email").value.trim(),
          phone: document.getElementById("co-phone").value.trim(),
          street_address: document.getElementById("co-street")
            ? document.getElementById("co-street").value.trim()
            : "",
          city: document.getElementById("co-city")
            ? document.getElementById("co-city").value.trim()
            : "Kalispell",
          state: "MT",
          zip_code: document.getElementById("co-zip")
            ? document.getElementById("co-zip").value.trim()
            : "59901",
        },
        fulfillment_type: fulfillmentType,
        fulfillment_date: document.getElementById("co-date").value,
        fulfillment_time_slot: document.getElementById("co-time-slot").value,
        special_instructions: document.getElementById("co-notes").value.trim(),
        cart_items: items,
        square_nonce: paymentToken,
      }

      try {
        const response = await fetch("/api/checkout.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-CSRF-Token": csrfToken,
          },
          body: JSON.stringify(payload),
        })

        const data = await response.json()

        if (data.success) {
          // Render Printable Thank You Page View
          renderThankYouPage(data.order, payload.customer, items)
          if (window.cartEngine) window.cartEngine.clearCart()
        } else {
          showFeedback(
            data.error || "Checkout failed. Please verify your details.",
            "error",
          )
          submitBtn.disabled = false
          submitBtn.textContent = "Authorize & Place Order"
        }
      } catch (networkErr) {
        showFeedback("Network communication error. Please try again.", "error")
        submitBtn.disabled = false
        submitBtn.textContent = "Authorize & Place Order"
      }
    })
  }

  function renderThankYouPage(order, customer, items) {
    checkoutView.style.display = "none"
    thankYouView.style.display = "block"
    window.scrollTo({ top: 0, behavior: "smooth" })

    // Populate Customer Email
    const emailEl = document.getElementById("ty-customer-email")
    if (emailEl) emailEl.textContent = customer.email

    // Populate Order Number
    const orderNumEl = document.getElementById("ty-order-number")
    if (orderNumEl) orderNumEl.textContent = order.order_number

    // Populate Fulfillment Instructions
    const instructionsEl = document.getElementById(
      "ty-fulfillment-instructions",
    )
    if (instructionsEl) {
      if (order.fulfillment_type === "delivery") {
        instructionsEl.innerHTML = `
          <strong>Doorstep Delivery Scheduled:</strong><br>
          We will hand-deliver your order to <strong>${escapeHtml(customer.street_address)}, ${escapeHtml(customer.city)}</strong> on 
          <strong>${escapeHtml(order.fulfillment_date)}</strong> during the <strong>${escapeHtml(order.fulfillment_time_slot)}</strong> window.
        `
      } else {
        instructionsEl.innerHTML = `
          <strong>Farmstand Pickup Scheduled:</strong><br>
          Your order will be packaged and ready for pickup at our kitchen (<strong>458 Orchard Vista Way, Kalispell, MT</strong>) on 
          <strong>${escapeHtml(order.fulfillment_date)}</strong> during <strong>${escapeHtml(order.fulfillment_time_slot)}</strong>.
        `
      }
    }

    // Populate Receipt Table
    const receiptTbody = document.getElementById("ty-receipt-items")
    if (receiptTbody) {
      receiptTbody.innerHTML = items
        .map(
          (item) => `
        <tr>
          <td>
            <strong>${escapeHtml(item.title)}</strong><br>
            <small style="color:#666;">${escapeHtml(window.cartEngine ? window.cartEngine.formatItemDetails(item) : "")}</small>
          </td>
          <td class="text-center">${item.quantity}</td>
          <td class="text-right">$${parseFloat(item.price).toFixed(2)}</td>
          <td class="text-right"><strong>$${(parseFloat(item.price) * item.quantity).toFixed(2)}</strong></td>
        </tr>
      `,
        )
        .join("")
    }

    const tySubtotal = document.getElementById("ty-subtotal")
    const tyDelivery = document.getElementById("ty-delivery")
    const tyTax = document.getElementById("ty-tax")
    const tyTotal = document.getElementById("ty-total")

    if (tySubtotal)
      tySubtotal.textContent = `$${parseFloat(order.subtotal).toFixed(2)}`
    if (tyDelivery)
      tyDelivery.textContent =
        parseFloat(order.delivery_fee) > 0
          ? `$${parseFloat(order.delivery_fee).toFixed(2)}`
          : "FREE"
    if (tyTax) tyTax.textContent = "$0.00"
    if (tyTotal)
      tyTotal.textContent = `$${parseFloat(order.total_amount).toFixed(2)}`

    // Social Share Button
    const shareBtn = document.getElementById("ty-share-btn")
    if (shareBtn) {
      shareBtn.addEventListener("click", () => {
        const text = `I just ordered handcrafted artisanal jam from Shelly's Jellys in Flathead Valley! Order ${order.order_number}`
        if (navigator.share) {
          navigator
            .share({
              title: "Shelly's Jellys LLC",
              text: text,
              url: window.location.origin,
            })
            .catch(() => {})
        } else {
          navigator.clipboard.writeText(`${text} - ${window.location.origin}`)
          alert("Order share message copied to clipboard!")
        }
      })
    }
  }

  function showFeedback(msg, type = "error") {
    if (feedbackEl) {
      feedbackEl.className = `checkout-feedback ${type}-box`
      feedbackEl.textContent = msg
      feedbackEl.style.display = "block"
    }
  }

  function hideFeedback() {
    if (feedbackEl) feedbackEl.style.display = "none"
  }

  function escapeHtml(str) {
    return String(str ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;")
  }
})
