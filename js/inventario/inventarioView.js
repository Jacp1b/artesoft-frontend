(function () {
  const H = () => window.Artesoft.Helpers;
  const view = {
    renderInventory(products, role, search = "") {
      const canEdit = window.Artesoft.Permissions.can(role, "productos.editar");
      const canDelete = window.Artesoft.Permissions.can(role, "productos.eliminar");
      return `<div class="admin-heading"><div><span class="eyebrow">CATÁLOGO Y EXISTENCIAS</span><h1>Inventario</h1><p>Administra las referencias y mantén visible la disponibilidad.</p></div>${window.Artesoft.Permissions.can(role, "productos.crear") ? `<button class="button button-primary button-small" type="button" data-action="add-product">＋ Nuevo producto</button>` : ""}</div>
        <section class="admin-panel"><div class="panel-head"><div><h2>Productos de la tienda</h2><p>El stock disponible controla lo que se puede agregar a la bolsa.</p></div><span class="admin-demo-tag">${products.length} referencias</span></div><div class="table-toolbar"><input class="table-search" type="search" data-inventory-search value="${H().escapeHTML(search)}" placeholder="Buscar producto o categoría" aria-label="Buscar producto o categoría"><span class="table-count" id="inventoryCount">${products.length} productos</span></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Producto</th><th>Categoría</th><th>Precio</th><th>Disponibles</th><th>Estado</th><th>Acciones</th></tr></thead><tbody id="inventoryRows">${this.renderRows(products, canEdit, canDelete)}</tbody></table></div>${!canEdit ? `<p class="readonly-note">Este perfil puede consultar el inventario, pero no modificar productos.</p>` : `<p class="readonly-note">Los cambios se guardan en este navegador. La integración con la base de datos central corresponde al backend.</p>`}</section>`;
    },
    renderRows(products, canEdit, canDelete) {
      if (!products.length) return `<tr><td colspan="6" class="admin-empty">No encontramos productos con esa búsqueda.</td></tr>`;
      return products.map((product) => `<tr><td><div class="inventory-product"><span class="inventory-product-icon ${H().escapeHTML(product.color || "lilac")}">${product.image ? `<img src="${H().escapeHTML(product.image)}" alt="">` : H().escapeHTML(product.icon || "🎨")}</span><div><strong>${H().escapeHTML(product.name)}</strong><span class="table-sub">${H().escapeHTML(product.id)}</span></div></div></td><td>${H().escapeHTML(product.category)}</td><td>${H().money(product.price)}</td><td><strong>${product.stock}</strong></td><td><span class="status-pill ${product.stock === 0 ? "" : product.stock <= 5 ? "status-preparacion" : "status-entregado"}">${product.stock === 0 ? "Agotado" : product.stock <= 5 ? "Pocas unidades" : "Disponible"}</span></td><td><div class="row-actions">${canEdit ? `<button class="mini-action" type="button" data-action="edit-product" data-id="${H().escapeHTML(product.id)}" aria-label="Editar ${H().escapeHTML(product.name)}" title="Editar">✎</button>` : ""}${canDelete ? `<button class="mini-action danger" type="button" data-action="delete-product" data-id="${H().escapeHTML(product.id)}" aria-label="Eliminar ${H().escapeHTML(product.name)}" title="Eliminar">⌫</button>` : ""}${!canEdit && !canDelete ? `<span class="table-sub">Solo lectura</span>` : ""}</div></td></tr>`).join("");
    },
    renderProductForm(product = null) {
      const dialog = document.getElementById("productDialog");
      const editing = Boolean(product);
      const initial = product || { name: "", category: "Juegos didácticos", price: "", stock: "", icon: "🎨", image: "", color: "lilac", badge: "Nuevo", description: "" };
      dialog.innerHTML = `<form method="dialog" class="modal-close-row"><button class="icon-button" aria-label="Cerrar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"></path></svg></button></form><span class="eyebrow">GESTIÓN DE CATÁLOGO</span><h2 id="productDialogTitle">${editing ? "Editar producto" : "Nuevo producto"}</h2><p class="modal-copy">Completa los datos que verá el cliente en el catálogo.</p>
        <form id="productForm" class="form-stack" data-id="${H().escapeHTML(product?.id || "")}">
          <div class="form-field"><label for="productName">Nombre del producto</label><input id="productName" name="name" required minlength="3" maxlength="100" value="${H().escapeHTML(initial.name)}" placeholder="Ej. Caja de colores"></div>
          <div class="form-row"><div class="form-field"><label for="productCategory">Categoría</label><select id="productCategory" name="category" required><option ${initial.category === "Juegos didácticos" ? "selected" : ""}>Juegos didácticos</option><option ${initial.category === "Piñatería" ? "selected" : ""}>Piñatería</option><option ${initial.category === "Fiesta y decoración" ? "selected" : ""}>Fiesta y decoración</option></select></div><div class="form-field"><label for="productIcon">Ícono</label><select id="productIcon" name="icon">${["🎨", "🧩", "🎲", "🎈", "🎊", "🖍️", "🦜", "✂️", "🌈", "🖌️"].map((icon) => `<option ${initial.icon === icon ? "selected" : ""}>${icon}</option>`).join("")}</select></div></div>
          <div class="form-row"><div class="form-field"><label for="productPrice">Precio (COP)</label><input id="productPrice" name="price" type="number" min="100" max="10000000" step="100" required value="${H().escapeHTML(initial.price)}"></div><div class="form-field"><label for="productStock">Unidades disponibles</label><input id="productStock" name="stock" type="number" min="0" max="9999" step="1" required value="${H().escapeHTML(initial.stock)}"></div></div>
          <div class="form-field"><label for="productBadge">Etiqueta corta</label><input id="productBadge" name="badge" maxlength="28" value="${H().escapeHTML(initial.badge || "")}" placeholder="Nuevo"></div>
          <div class="form-field"><label for="productImage">Foto del producto (ruta)</label><input id="productImage" name="image" maxlength="180" value="${H().escapeHTML(initial.image || "")}" placeholder="assets/products/mi-producto.jpg"><small class="form-hint">Copia la imagen a la carpeta assets/products y escribe su ruta relativa.</small></div>
          <div class="form-field"><label for="productDescription">Descripción</label><textarea id="productDescription" name="description" maxlength="180" placeholder="Cuéntale al cliente qué lo hace especial">${H().escapeHTML(initial.description || "")}</textarea></div>
          <button class="button button-primary button-wide" type="submit">${editing ? "Guardar cambios" : "Crear producto"}</button>
        </form>`;
      dialog.showModal();
      document.getElementById("productName")?.focus();
    }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.InventarioView = view;
})();
