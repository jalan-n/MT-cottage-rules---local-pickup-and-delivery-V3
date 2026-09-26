/**
 * ============================================================================
 * HEADER LIVE SEARCH & MOBILE NAVIGATION (Vanilla ES6+)
 * ============================================================================
 */

document.addEventListener("DOMContentLoaded", () => {
  // 1. Promotional Announcement Bar Dismissal
  const banner = document.getElementById("promo-announcement-bar")
  const dismissBtn = document.getElementById("dismiss-promo-btn")
  if (banner && dismissBtn) {
    const bannerVersion = banner.dataset.promoVersion || "default"
    const dismissedVersion = sessionStorage.getItem("promo_banner_dismissed")
    if (dismissedVersion === bannerVersion) {
      banner.style.display = "none"
    }
    dismissBtn.addEventListener("click", () => {
      banner.style.display = "none"
      sessionStorage.setItem("promo_banner_dismissed", bannerVersion)
    })
  }

  // 2. Mobile Nav Toggle
  const mobileToggle = document.getElementById("mobile-menu-toggle")
  const navBar = document.getElementById("site-nav-bar")
  if (mobileToggle && navBar) {
    mobileToggle.addEventListener("click", () => {
      const isExpanded = mobileToggle.getAttribute("aria-expanded") === "true"
      mobileToggle.setAttribute("aria-expanded", String(!isExpanded))
      navBar.classList.toggle("nav-open")
    })
  }

  // 3. Live Keyword Search
  const searchInput = document.getElementById("header-search-input")
  const searchForm = document.getElementById("header-search-form")
  const searchResults = document.getElementById("live-search-results")

  const normalizeSlug = (value) =>
    String(value || "")
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-+|-+$/g, "") || "product"

  const catalog =
    Array.isArray(window.SJ_PRODUCT_CATALOG) && window.SJ_PRODUCT_CATALOG.length
      ? window.SJ_PRODUCT_CATALOG.map((product) => ({
          name: String(product.name || ""),
          slug: normalizeSlug(product.slug || product.name),
          desc: String(product.description || product.name || ""),
          img: String(product.image || ""),
          price: String(product.price || "$9.00 - $17.00"),
        }))
      : Array.from(document.querySelectorAll("[data-product-slug]"))
          .map((card) => {
            const name = (
              card.dataset.productName ||
              card.querySelector("h2, h3")?.textContent ||
              ""
            ).trim()
            return {
              name,
              slug: normalizeSlug(
                card.dataset.productSlug || card.dataset.slug || name,
              ),
              desc: (
                card.querySelector(".shop-flavor-desc, .flavor-card-name")
                  ?.textContent || name
              ).trim(),
              img: card.querySelector("img")?.getAttribute("src") || "",
              price: "$9.00 - $17.00",
            }
          })
          .filter((item) => item.name)

  if (searchInput && searchResults) {
    searchInput.addEventListener("input", () => {
      const q = searchInput.value.trim().toLowerCase()
      if (!q) {
        searchResults.style.display = "none"
        searchResults.innerHTML = ""
        return
      }

      const matches = catalog.filter(
        (p) =>
          p.name.toLowerCase().includes(q) || p.desc.toLowerCase().includes(q),
      )

      if (matches.length === 0) {
        searchResults.innerHTML = `<div class="search-no-results">No jams matching "<strong>${escapeHtml(q)}</strong>".</div>`
        searchResults.style.display = "block"
      } else {
        searchResults.innerHTML = matches
          .map(
            (p) => `
          <a href="./product-${p.slug}.html" class="search-result-item">
            <img src="${p.img}" alt="${escapeHtml(p.name)}" class="search-thumb" width="36" height="36" loading="lazy">
            <div class="search-info">
              <span class="search-title">${escapeHtml(p.name)}</span>
              <span class="search-price">${p.price}</span>
            </div>
          </a>
        `,
          )
          .join("")
        searchResults.style.display = "block"
      }
    })

    // Close on outside click
    document.addEventListener("click", (e) => {
      if (
        !searchInput.contains(e.target) &&
        !searchResults.contains(e.target)
      ) {
        searchResults.style.display = "none"
      }
    })
  }

  if (searchForm) {
    searchForm.addEventListener("submit", (e) => {
      e.preventDefault()
      const q = searchInput ? searchInput.value.trim() : ""
      if (q) {
        window.location.href = `./shop.html?search=${encodeURIComponent(q)}`
      }
    })
  }

  function escapeHtml(str) {
    const div = document.createElement("div")
    div.textContent = str || ""
    return div.innerHTML
  }
})
