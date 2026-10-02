(function () {
  const state = { page: "store", adminTab: "overview", orderEmail: "", lookupOrders: null };
  const tabs = [
    ["overview", "◫", "Resumen"], ["orders", "▣", "Pedidos"], ["inventory", "✳", "Inventario"], ["clients", "♡", "Clientes"]
  ];
  function navButton([key, icon, label]) {
    return `<button class="admin-nav-button${state.adminTab === key ? " is-current" : ""}" type="button" data-action="admin-tab" data-tab="${key}"${state.adminTab === key ? ` aria-current="page"` : ""}><span class="admin-nav-icon" aria-hidden="true">${icon}</span>${label}</button>`;
  }
  function renderAdminContent() {
    const role = window.Artesoft.AuthModel.getRole();
    const products = window.Artesoft.ProductoModel.list();
    const orders = window.Artesoft.PedidoModel.list();
    if (state.adminTab === "orders") return window.Artesoft.PedidoView.renderAdminOrders(orders, role);
    if (state.adminTab === "inventory") return window.Artesoft.InventarioView.renderInventory(products, role, window.Artesoft.InventarioController.getSearch());
    if (state.adminTab === "clients") return window.Artesoft.PedidoView.renderClients(window.Artesoft.PedidoModel.customers());
    return window.Artesoft.PedidoView.renderDashboard(orders, products, role);
  }
  function setNavState() {
    document.querySelectorAll(".main-nav [data-route]").forEach((link) => {
      link.classList.toggle("is-active", (state.page === "orders" && link.dataset.route === "orders") || (state.page === "store" && link.dataset.route === "store" && !link.dataset.scroll));
    });
  }
  const app = {
    state,
    init() {
      window.Artesoft.ProductoModel.initialize();
      window.Artesoft.PedidoModel.initialize();
      window.Artesoft.ProductoController.bind();
      window.Artesoft.AuthController.bind();
      window.Artesoft.CarritoController.bind();
      window.Artesoft.InventarioController.bind();
      window.Artesoft.PedidoController.bind();
      this.bind();
      this.navigate("store", { scroll: false });
      window.Artesoft.CarritoController.refreshCount();
    },
    bind() {
      document.addEventListener("click", (event) => {
        const routeLink = event.target.closest("[data-route]");
        if (routeLink) {
          event.preventDefault();
          this.navigate(routeLink.dataset.route, { scroll: false });
          const scrollId = routeLink.dataset.scroll;
          if (scrollId) window.setTimeout(() => document.getElementById(scrollId)?.scrollIntoView({ behavior: "smooth" }), 30);
          else if (routeLink.dataset.route === "store") window.scrollTo({ top: 0, behavior: "smooth" });
          document.getElementById("mobileMenu")?.setAttribute("hidden", "");
          document.querySelector("[data-action='toggle-menu']")?.setAttribute("aria-expanded", "false");
          return;
        }
        const action = event.target.closest("[data-action]");
        if (!action) return;
        if (action.dataset.action === "admin-tab") {
          state.adminTab = action.dataset.tab || "overview";
          this.renderAdmin();
        }
        if (action.dataset.action === "toggle-menu") {
          const menu = document.getElementById("mobileMenu");
          const open = menu.hasAttribute("hidden");
          menu.toggleAttribute("hidden", !open);
          action.setAttribute("aria-expanded", String(open));
        }
      });
      document.addEventListener("submit", (event) => {
        if (event.target.id !== "orderLookup") return;
        event.preventDefault();
        const form = event.target;
        if (!form.checkValidity()) return form.reportValidity();
        this.setOrderEmail(form.elements.email.value.trim());
        this.navigate("orders", { scroll: false });
      });
      document.addEventListener("keydown", (event) => {
        if (event.key === "/" && !["INPUT", "TEXTAREA", "SELECT"].includes(document.activeElement?.tagName)) {
          event.preventDefault();
          const input = document.getElementById("headerSearch");
          input?.focus();
        }
        if (event.key === "Escape") document.querySelector(".header-search")?.classList.remove("is-open");
      });
      document.querySelector(".header-search")?.addEventListener("click", () => {
        if (window.matchMedia("(max-width: 760px)").matches) {
          const label = document.querySelector(".header-search");
          label.classList.add("is-open");
          document.getElementById("headerSearch")?.focus();
        }
      });
    },
    navigate(page, options = {}) {
      if (page === "admin" && !window.Artesoft.AuthModel.getRole()) {
        window.Artesoft.AuthView.open();
        page = "store";
      }
      state.page = page === "orders" ? "orders" : page === "admin" ? "admin" : "store";
      const root = document.getElementById("appRoot");
      const footer = document.getElementById("siteFooter");
      const publicHeader = document.querySelector(".site-header");
      const announcement = document.querySelector(".announcement");
      publicHeader.hidden = state.page === "admin";
      announcement.hidden = state.page === "admin";
      if (state.page === "admin") {
        document.getElementById("mobileMenu")?.setAttribute("hidden", "");
        document.querySelector("[data-action='toggle-menu']")?.setAttribute("aria-expanded", "false");
      }
      if (state.page === "admin") {
        root.innerHTML = this.adminShell();
        footer.hidden = true;
      } else if (state.page === "orders") {
        root.innerHTML = window.Artesoft.PedidoView.renderCustomerPage(state.orderEmail, state.lookupOrders);
        footer.hidden = false;
      } else {
        const searchState = window.Artesoft.ProductoController.getState();
        root.innerHTML = window.Artesoft.ProductoView.renderStore(window.Artesoft.ProductoController.visibleProducts(), searchState.category, searchState.search, searchState.sort);
        footer.hidden = false;
        const search = document.getElementById("headerSearch");
        if (search) search.value = searchState.search;
      }
      setNavState();
      document.body.classList.toggle("is-admin", state.page === "admin");
      if (options.scroll !== false) window.scrollTo({ top: 0, behavior: "smooth" });
    },
    adminShell() {
      const role = window.Artesoft.AuthModel.getRole();
      const roleLabel = window.Artesoft.Permissions.label(role);
      return `<section class="admin-shell" aria-label="Panel de gestión ARTESOFT"><header class="admin-topbar"><div class="admin-topbar-inner"><div class="admin-brand"><span class="brand-mark" aria-hidden="true"><span></span><span></span><span></span><span></span></span><span>ARTESOFT <span style="color:#d9ed73">/</span> Gestión</span></div><div class="admin-top-actions"><span class="admin-demo-tag">Modo demostración</span><button class="admin-user" type="button" data-action="open-access" title="Cambiar perfil"><span class="avatar">${roleLabel.charAt(0)}</span>${roleLabel} · Cambiar perfil</button><button class="icon-button" type="button" data-action="exit-admin" aria-label="Salir del panel" title="Salir del panel"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3m9-8h6a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-6"></path></svg></button></div></div></header>
        <nav class="admin-mobile-nav" aria-label="Secciones del panel">${tabs.map(navButton).join("")}</nav>
        <div class="admin-body"><aside class="admin-sidebar"><span class="sidebar-label">Espacio de trabajo</span><nav class="admin-nav" aria-label="Secciones del panel">${tabs.map(navButton).join("")}</nav><div class="sidebar-help"><strong>Prototipo local</strong>Los pedidos y cambios viven en este navegador. En producción se conectan al backend y a la base de datos.</div></aside><div class="admin-main"><div class="admin-content">${renderAdminContent()}</div></div></div></section>`;
    },
    renderAdmin() {
      if (state.page !== "admin") return;
      document.getElementById("appRoot").innerHTML = this.adminShell();
      window.Artesoft.InventarioController.refresh?.();
    },
    setOrderEmail(email) {
      state.orderEmail = email;
      state.lookupOrders = email ? window.Artesoft.PedidoModel.forEmail(email) : null;
    }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.App = app;
  document.addEventListener("DOMContentLoaded", () => app.init());
})();
