/**
 * ============================================================================
 * CART ENGINE & SLIDE-OVER DRAWER (Vanilla ES6+)
 * Synchronizes cart items with localStorage and validates against /api/cart-validate.php
 * Handles cart page, drawer slide-over, and shareable cart links.
 * ============================================================================
 */

class CartEngine {
  constructor() {
    this.storageKey = "shellys_jellys_cart_v2"
    this.fulfillmentKey = "shellys_jellys_fulfillment_v2"
    this.items = this.loadCart()
    this.fulfillment = this.loadFulfillment()
    this.init()
  }

  loadCart() {
    try {
      const data = localStorage.getItem(this.storageKey)
      return data ? JSON.parse(data) : []
    } catch (e) {
      console.error("Failed to read cart from localStorage", e)
      return []
    }
  }

  saveCart() {
    try {
      localStorage.setItem(this.storageKey, JSON.stringify(this.items))
      this.updateHeaderBadge()
      this.renderDrawer()
      this.renderCartPage()
    } catch (e) {
      console.error("Failed to save cart to localStorage", e)
    }
  }

  loadFulfillment() {
    try {
      const data = localStorage.getItem(this.fulfillmentKey)
      return data ? JSON.parse(data) : { type: "pickup", zip: "59901" }
    } catch (e) {
      return { type: "pickup", zip: "59901" }
    }
  }

  saveFulfillment(f) {
    this.fulfillment = { ...this.fulfillment, ...f }
    localStorage.setItem(this.fulfillmentKey, JSON.stringify(this.fulfillment))
    this.renderCartPage()
  }

  init() {
    this.updateHeaderBadge()
    this.bindDrawerEvents()
    this.bindPageEvents()
    this.checkShareableCartUrl()
    this.renderDrawer()
    this.renderCartPage()
  }

  addItem(item, openDrawer = true) {
    const key = this.generateItemKey(item)
    const existingIndex = this.items.findIndex((i) => i.key === key)

    if (existingIndex > -1) {
      this.items[existingIndex].quantity += item.quantity || 1
    } else {
      item.key = key
      item.quantity = item.quantity || 1
      this.items.push(item)
    }

    this.saveCart()

    const isShopOrProduct =
      window.location.pathname.includes("shop") ||
      window.location.pathname.includes("product") ||
      window.location.pathname === "/" ||
      window.location.pathname.includes("index")

    if (openDrawer && isShopOrProduct) {
      this.openDrawer()
    }
  }

  updateQuantity(key, delta) {
    const item = this.items.find((i) => i.key === key)
    if (!item) return

    item.quantity += delta
    if (item.quantity <= 0) {
      this.removeItem(key)
    } else {
      this.saveCart()
    }
  }

  removeItem(key) {
    this.items = this.items.filter((i) => i.key !== key)
    this.saveCart()
  }

  clearCart() {
    this.items = []
    this.saveCart()
  }

  generateItemKey(item) {
    if (item.type === "single") {
      return `single_${item.product_id}_${item.variant_id}`
    }
    if (item.type === "pack") {
      const sortedFlavors = [...(item.flavors || [])].sort().join(",")
      return `pack_${item.bundle_type}_${item.jar_size}_${sortedFlavors}`
    }
    if (item.type === "gift_box") {
      const sortedFlavors = [...(item.flavors || [])].sort().join(",")
      return `giftbox_${sortedFlavors}_${item.price}`
    }
    return `item_${Date.now()}`
  }

  getTotalCount() {
    return this.items.reduce((sum, item) => sum + (item.quantity || 1), 0)
  }

  getSubtotal() {
    return this.items.reduce(
      (sum, item) => sum + (parseFloat(item.price) || 0) * (item.quantity || 1),
      0,
    )
  }

  updateHeaderBadge() {
    const badge = document.getElementById("header-cart-count")
    if (badge) {
      badge.textContent = String(this.getTotalCount())
    }
  }

  bindDrawerEvents() {
    const drawer = document.getElementById("cart-drawer")
    const overlay = document.getElementById("cart-drawer-overlay")
    const closeBtn = document.getElementById("cart-drawer-close")
    const toggleBtn = document.getElementById("cart-toggle-btn")

    if (toggleBtn) {
      toggleBtn.addEventListener("click", () => this.openDrawer())
    }
    if (closeBtn) {
      closeBtn.addEventListener("click", () => this.closeDrawer())
    }
    if (overlay) {
      overlay.addEventListener("click", () => this.closeDrawer())
    }
  }

  openDrawer() {
    const drawer = document.getElementById("cart-drawer")
    const overlay = document.getElementById("cart-drawer-overlay")
    if (drawer && overlay) {
      drawer.classList.add("active")
      overlay.classList.add("active")
      drawer.setAttribute("aria-hidden", "false")
      overlay.setAttribute("aria-hidden", "false")
    }
  }

  closeDrawer() {
    const drawer = document.getElementById("cart-drawer")
    const overlay = document.getElementById("cart-drawer-overlay")
    if (drawer && overlay) {
      drawer.classList.remove("active")
      overlay.classList.remove("active")
      drawer.setAttribute("aria-hidden", "true")
      overlay.setAttribute("aria-hidden", "true")
    }
  }

  renderDrawer() {
    const container = document.getElementById("drawer-items-list")
    const subtotalEl = document.getElementById("drawer-subtotal-val")
    if (!container) return

    if (this.items.length === 0) {
      container.innerHTML =
        '<p class="empty-state">Your basket is currently empty.</p>'
      if (subtotalEl) subtotalEl.textContent = "$0.00"
      return
    }

    container.innerHTML = this.items
      .map(
        (item) => `
          <div class="drawer-line-item">
            <img src="${this.resolveImageUrl(item.image)}" alt="${this.escapeHtml(item.title)}" class="drawer-thumb" width="50" height="50" loading="lazy">
            <div class="drawer-item-info">
              <div class="drawer-item-title">${this.escapeHtml(item.title)}</div>
              <div class="drawer-item-details">${this.escapeHtml(this.formatItemDetails(item))}</div>
              <div class="drawer-qty-row">
                <div class="qty-stepper-control">
                  <button type="button" class="qty-btn" onclick="window.cartEngine.updateQuantity('${item.key}', -1)">&minus;</button>
                  <span class="qty-number-input">${item.quantity}</span>
                  <button type="button" class="qty-btn" onclick="window.cartEngine.updateQuantity('${item.key}', 1)">&plus;</button>
                </div>
                <strong class="drawer-line-price">$${(parseFloat(item.price) * item.quantity).toFixed(2)}</strong>
              </div>
            </div>
          </div>
        `,
      )
      .join("")

    if (subtotalEl) {
      subtotalEl.textContent = `$${this.getSubtotal().toFixed(2)}`
    }
  }

  bindPageEvents() {
    const radios = document.querySelectorAll(
      'input[name="cart_page_fulfillment"]',
    )
    const zipWrapper = document.getElementById("cart-zip-wrapper")
    radios.forEach((r) => {
      if (r.value === this.fulfillment.type) r.checked = true
      r.addEventListener("change", () => {
        this.saveFulfillment({ type: r.value })
        if (zipWrapper) {
          zipWrapper.style.display = r.value === "delivery" ? "block" : "none"
        }
      })
    })

    if (zipWrapper && this.fulfillment.type === "delivery") {
      zipWrapper.style.display = "block"
    }

    const zipBtn = document.getElementById("cart-calc-zip-btn")
    const zipInput = document.getElementById("cart-est-zip")
    const zipStatus = document.getElementById("cart-zip-status")
    if (zipBtn && zipInput) {
      zipBtn.addEventListener("click", () => {
        const val = zipInput.value.trim()
        const supported = [
          "59901",
          "59902",
          "59903",
          "59904",
          "59911",
          "59912",
          "59937",
        ]

        if (supported.includes(val)) {
          this.saveFulfillment({ zip: val })
          if (zipStatus) {
            zipStatus.textContent = `Zone verified: ${val} is eligible for Flathead Valley local delivery.`
            zipStatus.style.color = "#137333"
          }
        } else if (zipStatus) {
          zipStatus.textContent = `ZIP ${val} is outside standard radius. Farmstand pickup is always free!`
          zipStatus.style.color = "#C5221F"
        }
      })
    }

    const shareBtn = document.getElementById("share-cart-btn")
    const shareFeedback = document.getElementById("share-link-feedback")
    if (shareBtn) {
      shareBtn.addEventListener("click", async () => {
        const url = this.generateShareableUrl()

        try {
          let copied = false

          if (
            navigator.clipboard &&
            (window.isSecureContext ||
              window.location.hostname === "localhost" ||
              window.location.hostname === "127.0.0.1")
          ) {
            await navigator.clipboard.writeText(url)
            copied = true
          } else {
            const textarea = document.createElement("textarea")
            textarea.value = url
            textarea.setAttribute("readonly", "")
            textarea.style.position = "fixed"
            textarea.style.top = "-9999px"
            textarea.style.left = "-9999px"
            document.body.appendChild(textarea)
            textarea.select()
            textarea.setSelectionRange(0, url.length)
            copied = document.execCommand("copy")
            document.body.removeChild(textarea)
          }

          if (shareFeedback) {
            shareFeedback.textContent = copied
              ? "Shareable cart link copied to clipboard!"
              : "Copy failed. Your shareable cart link is shown in the prompt below."
            shareFeedback.style.display = "block"
            setTimeout(() => {
              shareFeedback.style.display = "none"
            }, 4000)
          }

          if (!copied) {
            prompt("Copy this shareable cart link:", url)
          }
        } catch (error) {
          console.warn("Clipboard copy failed.", error)
          if (shareFeedback) {
            shareFeedback.textContent =
              "Copy failed. Your shareable cart link is shown in the prompt below."
            shareFeedback.style.display = "block"
            setTimeout(() => {
              shareFeedback.style.display = "none"
            }, 4000)
          }
          prompt("Copy this shareable cart link:", url)
        }
      })
    }

    const purchaseForm = document.getElementById("product-purchase-form")
    if (purchaseForm) {
      purchaseForm.addEventListener("submit", (e) => {
        e.preventDefault()
        const checkedVariant = purchaseForm.querySelector(
          'input[name="variant_id"]:checked',
        )
        const qtyInput = document.getElementById("purchase-qty")
        const qty = parseInt(qtyInput ? qtyInput.value : "1", 10) || 1
        const prodName = purchaseForm.dataset.productName || "Artisanal Jam"
        const prodId = purchaseForm.dataset.productId || 1

        if (checkedVariant) {
          const productImage = purchaseForm.dataset.productImage || ""
          this.addItem(
            {
              type: "single",
              product_id: parseInt(prodId, 10),
              variant_id: parseInt(checkedVariant.value, 10),
              title: prodName,
              size: checkedVariant.dataset.size,
              sku: checkedVariant.dataset.sku,
              price: parseFloat(checkedVariant.dataset.price),
              quantity: qty,
              image: this.resolveImageUrl(productImage),
            },
            true,
          )
        }
      })
    }
  }

  renderCartPage() {
    const tableContainer = document.getElementById("cart-page-items")
    const subtotalEl = document.getElementById("cart-page-subtotal")
    const feeEl = document.getElementById("cart-page-fulfillment-fee")
    const taxEl = document.getElementById("cart-page-tax")
    const totalEl = document.getElementById("cart-page-total")
    if (!tableContainer) return

    const shopUrl = new URL("./shop.html", window.location.href).toString()

    if (this.items.length === 0) {
      tableContainer.innerHTML = `
        <div class="empty-state text-center" style="padding: 2.5rem 1rem;">
          <p style="font-size: 1.1rem; margin-bottom: 1rem;">Your shopping basket is currently empty.</p>
          <a href="${shopUrl}" class="btn-emerald">Explore Handcrafted Jams</a>
        </div>
      `
      if (subtotalEl) subtotalEl.textContent = "$0.00"
      if (feeEl) feeEl.textContent = "$0.00"
      if (totalEl) totalEl.textContent = "$0.00"
      return
    }

    const subtotal = this.getSubtotal()
    const deliveryFee =
      this.fulfillment.type === "delivery"
        ? subtotal >= 45.0
          ? 0.0
          : 6.5
        : 0.0
    const tax = 0.0
    const grandTotal = subtotal + deliveryFee + tax

    tableContainer.innerHTML = `
      <table class="cart-table">
        <thead>
          <tr>
            <th>Item</th>
            <th>Details</th>
            <th>Price</th>
            <th class="text-center">Quantity</th>
            <th class="text-right">Total</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          ${this.items
            .map((item) => {
              const packUrl = new URL(
                "./packs.html",
                window.location.href,
              ).toString()
              const giftUrl = new URL(
                "./gift-box.html",
                window.location.href,
              ).toString()
              return `
                <tr>
                  <td>
                    <strong>${this.escapeHtml(item.title)}</strong>
                  </td>
                  <td>
                    <small style="color:#555;">${this.escapeHtml(this.formatItemDetails(item))}</small>
                    ${item.type === "pack" ? `<br><a href="${packUrl}" style="font-size:0.75rem; color:#8B7500; text-decoration:underline;">Edit Pack</a>` : ""}
                    ${item.type === "gift_box" ? `<br><a href="${giftUrl}" style="font-size:0.75rem; color:#8B7500; text-decoration:underline;">Edit Box</a>` : ""}
                  </td>
                  <td>$${parseFloat(item.price).toFixed(2)}</td>
                  <td class="text-center">
                    <div class="qty-stepper-control" style="display:inline-flex;">
                      <button type="button" class="qty-btn" onclick="window.cartEngine.updateQuantity('${item.key}', -1)">&minus;</button>
                      <span class="qty-number-input">${item.quantity}</span>
                      <button type="button" class="qty-btn" onclick="window.cartEngine.updateQuantity('${item.key}', 1)">&plus;</button>
                    </div>
                  </td>
                  <td class="text-right"><strong>$${(parseFloat(item.price) * item.quantity).toFixed(2)}</strong></td>
                  <td class="text-right">
                    <button type="button" onclick="window.cartEngine.removeItem('${item.key}')" style="color:#C5221F; font-size:1.1rem;" title="Remove item">&times;</button>
                  </td>
                </tr>
              `
            })
            .join("")}
        </tbody>
      </table>
    `

    if (subtotalEl) subtotalEl.textContent = `$${subtotal.toFixed(2)}`
    if (feeEl) {
      if (this.fulfillment.type === "delivery") {
        feeEl.textContent =
          deliveryFee === 0 ? "FREE ($45+ promo)" : `$${deliveryFee.toFixed(2)}`
      } else {
        feeEl.textContent = "FREE (Farmstand Pickup)"
      }
    }
    if (taxEl) taxEl.textContent = "$0.00"
    if (totalEl) totalEl.textContent = `$${grandTotal.toFixed(2)}`
  }

  formatItemDetails(item) {
    if (item.type === "single") {
      return `Size: ${item.size || "8 oz"} ${item.sku ? `(SKU: ${item.sku})` : ""}`
    }
    if (item.type === "pack") {
      const list = (item.flavors || []).join(", ")
      return `Jar Size: ${item.jar_size} | Flavors: ${list}`
    }
    if (item.type === "gift_box") {
      const list = (item.flavors || []).join(", ")
      return `Gift Box (4 oz jars): ${list || "Artisan Assortment"}`
    }
    return ""
  }

  generateShareableUrl() {
    const minimal = this.items.map((i) => ({
      t: i.type,
      pid: i.product_id,
      vid: i.variant_id,
      b: i.bundle_type,
      sz: i.jar_size,
      f: i.flavors,
      q: i.quantity,
      ttl: i.title,
      p: i.price,
    }))
    const encoded = encodeURIComponent(btoa(JSON.stringify(minimal)))
    const shareUrl = new URL("./cart.html", window.location.href)
    shareUrl.searchParams.set("shared_cart", encoded)
    return shareUrl.toString()
  }

  checkShareableCartUrl() {
    const params = new URLSearchParams(window.location.search)
    const shared = params.get("shared_cart")
    if (shared) {
      try {
        const decoded = JSON.parse(atob(decodeURIComponent(shared)))
        if (Array.isArray(decoded) && decoded.length > 0) {
          const restored = decoded.map((d) => ({
            type: d.t,
            product_id: d.pid,
            variant_id: d.vid,
            bundle_type: d.b,
            jar_size: d.sz,
            flavors: d.f,
            quantity: d.q || 1,
            title: d.ttl,
            price: d.p,
          }))
          this.items = restored
          this.saveCart()
          window.history.replaceState(
            {},
            document.title,
            window.location.pathname,
          )
        }
      } catch (err) {
        console.error("Failed to parse shareable cart URL", err)
      }
    }
  }

  resolveImageUrl(imagePath) {
    const defaultImage = new URL(
      "./assets/images/jam-jar-hero.svg",
      window.location.href,
    ).toString()

    if (!imagePath) {
      return defaultImage
    }

    if (/^(https?:)?\/\//i.test(imagePath) || imagePath.startsWith("data:")) {
      return imagePath
    }

    const lower = String(imagePath).toLowerCase()
    let mapped = null

    if (lower.includes("rhubarb") || lower.includes("rhubarhuck")) {
      mapped = "rhubarb-huckleberry.svg"
    } else if (lower.includes("flathead") || lower.includes("cherry")) {
      mapped = "flathead-cherry.svg"
    } else if (
      lower.includes("pineapple") ||
      lower.includes("toasted") ||
      lower.includes("coconut")
    ) {
      mapped = "pineapple-toasted-coconut.svg"
    } else if (lower.includes("raspberry")) {
      mapped = "raspberry.svg"
    } else if (
      lower.includes("strawberry") ||
      lower.includes("vanilla") ||
      lower.includes("bean")
    ) {
      mapped = "strawberry-vanilla-bean.svg"
    } else if (lower.includes("huckleberry")) {
      mapped = "huckleberry.svg"
    }

    if (mapped) {
      const basePath = window.location.pathname.includes("/SJ-cottage-food/")
        ? "/SJ-cottage-food"
        : ""
      return new URL(
        `${basePath}/public/assets/images/${mapped}`,
        window.location.origin,
      ).toString()
    }

    if (imagePath.startsWith("/")) {
      if (imagePath.includes("/SJ-cottage-food/")) {
        return imagePath
      }
      return new URL(`.${imagePath}`, window.location.href).toString()
    }

    return new URL(imagePath, window.location.href).toString()
  }

  escapeHtml(str) {
    return String(str ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;")
  }
}

window.cartEngine = new CartEngine()
