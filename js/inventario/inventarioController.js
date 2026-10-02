(function () {
  let search = "";
  function allowed(action) {
    return window.Artesoft.Permissions.can(window.Artesoft.AuthModel.getRole(), action);
  }
  function updateRows() {
    const role = window.Artesoft.AuthModel.getRole();
    const canEdit = allowed("productos.editar");
    const canDelete = allowed("productos.eliminar");
    const term = search.trim().toLocaleLowerCase("es-CO");
    const products = window.Artesoft.ProductoModel.list().filter((product) => `${product.name} ${product.category}`.toLocaleLowerCase("es-CO").includes(term));
    const rows = document.getElementById("inventoryRows");
    const count = document.getElementById("inventoryCount");
    if (rows) rows.innerHTML = window.Artesoft.InventarioView.renderRows(products, canEdit, canDelete);
    if (count) count.textContent = `${products.length} ${products.length === 1 ? "producto" : "productos"}`;
  }
  const controller = {
    bind() {
      document.addEventListener("input", (event) => {
        if (event.target.matches("[data-inventory-search]")) { search = event.target.value; updateRows(); }
      });
      document.addEventListener("click", (event) => {
        const button = event.target.closest("[data-action]");
        if (!button) return;
        if (button.dataset.action === "add-product") {
          if (!allowed("productos.crear")) return window.Artesoft.Helpers.toast("Tu perfil no puede crear productos.", "error");
          window.Artesoft.InventarioView.renderProductForm();
        }
        if (button.dataset.action === "edit-product") {
          if (!allowed("productos.editar")) return window.Artesoft.Helpers.toast("Tu perfil no puede editar productos.", "error");
          const product = window.Artesoft.ProductoModel.get(button.dataset.id);
          if (product) window.Artesoft.InventarioView.renderProductForm(product);
        }
        if (button.dataset.action === "delete-product") {
          if (!allowed("productos.eliminar")) return window.Artesoft.Helpers.toast("Solo administración puede eliminar productos.", "error");
          const product = window.Artesoft.ProductoModel.get(button.dataset.id);
          if (!product) return;
          if (!window.confirm(`¿Eliminar “${product.name}” del catálogo? Esta acción no se puede deshacer.`)) return;
          window.Artesoft.ProductoModel.remove(product.id);
          window.Artesoft.Helpers.toast("Eliminamos el producto del catálogo.");
          window.Artesoft.App.renderAdmin();
        }
      });
      document.addEventListener("submit", (event) => {
        if (event.target.id !== "productForm") return;
        event.preventDefault();
        const form = event.target;
        const editing = Boolean(form.dataset.id);
        if (!allowed(editing ? "productos.editar" : "productos.crear")) return window.Artesoft.Helpers.toast("Tu perfil no tiene permiso para guardar este producto.", "error");
        const data = Object.fromEntries(new FormData(form).entries());
        data.name = data.name.trim();
        data.description = data.description.trim();
        data.badge = data.badge.trim() || "Para imaginar";
        data.image = data.image.trim();
        data.color = ({ "Juegos didácticos": "lilac", "Piñatería": "pink", "Fiesta y decoración": "sun" })[data.category] || "lilac";
        data.price = Number(data.price);
        data.stock = Number(data.stock);
        if (!form.checkValidity()) { form.reportValidity(); return; }
        if (data.name.length < 3 || data.name.length > 100) return window.Artesoft.Helpers.toast("El nombre debe tener entre 3 y 100 caracteres.", "error");
        if (window.Artesoft.ProductoModel.existsName(data.name, form.dataset.id)) return window.Artesoft.Helpers.toast("Ya existe un producto con ese nombre.", "error");
        if (!Number.isFinite(data.price) || data.price <= 0 || !Number.isInteger(data.stock) || data.stock < 0) return window.Artesoft.Helpers.toast("Revisa el precio y las unidades disponibles.", "error");
        if (editing) window.Artesoft.ProductoModel.update(form.dataset.id, data);
        else window.Artesoft.ProductoModel.create(data);
        document.getElementById("productDialog")?.close();
        window.Artesoft.Helpers.toast(editing ? "Guardamos los cambios del producto." : "Agregamos el producto al catálogo.");
        search = "";
        window.Artesoft.App.renderAdmin();
      });
    },
    getSearch() { return search; }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.InventarioController = controller;
})();
