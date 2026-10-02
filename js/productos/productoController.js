(function () {
  const state = { category: "Todos", search: "", sort: "featured" };
  function visibleProducts() {
    let products = window.Artesoft.ProductoModel.list().filter((product) => Number(product.stock) >= 0);
    if (state.category !== "Todos") products = products.filter((product) => product.category === state.category);
    if (state.search.trim()) {
      const term = state.search.trim().toLocaleLowerCase("es-CO");
      products = products.filter((product) => `${product.name} ${product.category} ${product.description || ""}`.toLocaleLowerCase("es-CO").includes(term));
    }
    if (state.sort === "price-asc") products.sort((a, b) => a.price - b.price);
    if (state.sort === "price-desc") products.sort((a, b) => b.price - a.price);
    if (state.sort === "name") products.sort((a, b) => a.name.localeCompare(b.name, "es"));
    return products;
  }
  function repaint() {
    window.Artesoft.ProductoView.renderCards(visibleProducts());
    document.querySelectorAll("[data-action='select-category']").forEach((button) => {
      const active = button.dataset.category === state.category;
      if (button.classList.contains("filter-chip")) {
        button.classList.toggle("is-selected", active);
        button.setAttribute("aria-pressed", String(active));
      }
    });
    const sort = document.getElementById("catalogSort");
    if (sort && sort.value !== state.sort) sort.value = state.sort;
  }
  const controller = {
    bind() {
      document.addEventListener("input", (event) => {
        if (event.target.id === "headerSearch") {
          state.search = event.target.value;
          if (window.Artesoft.App?.state.page !== "store") window.Artesoft.App.navigate("store");
          repaint();
          const catalogSearch = document.getElementById("catalogSearch");
          if (catalogSearch) catalogSearch.value = state.search;
        }
      });
      document.addEventListener("change", (event) => {
        if (event.target.id === "catalogSort") { state.sort = event.target.value; repaint(); }
      });
      document.addEventListener("click", (event) => {
        const categoryButton = event.target.closest("[data-action='select-category']");
        if (categoryButton) {
          state.category = categoryButton.dataset.category || "Todos";
          if (window.Artesoft.App?.state.page !== "store") window.Artesoft.App.navigate("store");
          repaint();
          if (categoryButton.closest(".category-card")) document.getElementById("catalogo")?.scrollIntoView({ behavior: "smooth" });
          return;
        }
        const detailButton = event.target.closest("[data-action='product-details']");
        if (detailButton) {
          const product = window.Artesoft.ProductoModel.get(detailButton.dataset.id);
          if (product) window.Artesoft.ProductoView.renderProductDetail(product);
          return;
        }
        const addButton = event.target.closest("[data-action='add-to-cart']");
        if (addButton) {
          const result = window.Artesoft.CarritoModel.add(addButton.dataset.id);
          if (!result.ok) return window.Artesoft.Helpers.toast(result.message, "error");
          window.Artesoft.CarritoController.refreshCount();
          window.Artesoft.Helpers.toast("Agregamos el producto a tu bolsa.");
          document.getElementById("productDialog")?.close();
        }
      });
    },
    visibleProducts,
    getState() { return { ...state }; },
    repaint
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.ProductoController = controller;
})();
