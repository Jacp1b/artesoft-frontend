(function () {
  const H = () => window.Artesoft.Helpers;
  const categories = ["Todos", "Juegos didácticos", "Piñatería", "Fiesta y decoración"];
  const artClasses = ["lilac", "sun", "mint", "pink", "coral", "blue", "green", "peach"];
  function visualContent(product, detail = false) {
    if (product.image) return `<img class="${detail ? "product-detail-photo" : "product-photo"}" src="${H().escapeHTML(product.image)}" alt="${detail ? H().escapeHTML(product.name) : ""}" loading="lazy">`;
    return detail
      ? H().escapeHTML(product.icon || "🎨")
      : `<span class="product-emoji" aria-hidden="true">${H().escapeHTML(product.icon || "🎨")}</span>`;
  }
  function productCard(product) {
    const stockText = product.stock <= 0 ? "Agotado" : product.stock < 6 ? `Quedan ${product.stock}` : "Disponible";
    return `<article class="product-card">
      <button class="product-visual ${H().escapeHTML(product.color || "lilac")}${product.image ? " has-photo" : ""}" type="button" data-action="product-details" data-id="${H().escapeHTML(product.id)}" aria-label="Ver detalles de ${H().escapeHTML(product.name)}">
        <span class="product-badge">${H().escapeHTML(product.badge || "Para imaginar")}</span>${visualContent(product)}
      </button>
      <div class="product-info"><span class="product-category">${H().escapeHTML(product.category)}</span><h3 class="product-name">${H().escapeHTML(product.name)}</h3>
        <div class="product-bottom"><div><span class="product-price">${H().money(product.price)}</span><span class="product-stock">${stockText}</span></div>
        <button class="add-button" type="button" data-action="add-to-cart" data-id="${H().escapeHTML(product.id)}" aria-label="Agregar ${H().escapeHTML(product.name)} a la bolsa" ${product.stock <= 0 ? "disabled" : ""}>+</button></div>
      </div>
    </article>`;
  }
  const view = {
    categories,
    renderStore(products, activeCategory = "Todos", search = "", sort = "featured") {
      const hasSearch = search.trim().length > 0;
      const categoryTiles = [
        ["Juegos didácticos", "Aprender también es jugar", "🧩"], ["Piñatería", "Que empiece la celebración", "🎈"],
        ["Fiesta y decoración", "Detalles para vestir la fiesta", "🎊"], ["Todos", "Ideas para cualquier ocasión", "✨"]
      ];
      return `<div class="page-wrap" id="inicio">
        <section class="hero" aria-labelledby="heroTitle">
          <div class="hero-copy"><span class="eyebrow">Un mundo hecho para imaginar</span><h1 id="heroTitle">Todo lo que imaginas, <em>listo para crear.</em></h1>
            <p>Juegos didácticos, piñatería y detalles para celebrar. Encuentra eso que convierte cualquier día en un gran plan.</p>
            <div class="hero-actions"><a class="button button-primary" href="#catalogo" data-scroll="catalogo">Explorar catálogo <span aria-hidden="true">↗</span></a><a class="button button-secondary" href="#categorias" data-scroll="categorias">Ver categorías</a></div>
            <div class="hero-proof"><span><i class="proof-check">✓</i> Compra fácil</span><span><i class="proof-check">✓</i> Pago contra entrega</span><span><i class="proof-check">✓</i> Stock visible</span></div>
          </div>
          <figure class="hero-visual"><div class="hero-shape" aria-hidden="true"><span class="brand-mark"><span></span><span></span><span></span><span></span></span></div><div class="hero-photo-frame"><img class="hero-photo" src="https://images.unsplash.com/photo-1542666704-ff97566ec537?auto=format&fit=crop&w=1200&q=85" alt="Materiales coloridos de arte listos para una tarde creativa" fetchpriority="high"></div><span class="hero-doodle hero-doodle-one" aria-hidden="true">✳</span><span class="hero-doodle hero-doodle-two" aria-hidden="true">✂</span><div class="hero-sticker"><span>Ideas para</span> compartir</div><figcaption class="hero-caption">Un espacio para crear sin límites</figcaption></figure>
        </section>
      </div>
      <div class="service-strip"><div class="service-inner">
        <div class="service-item"><span class="service-icon" aria-hidden="true">✦</span><div><strong>Compra en pocos pasos</strong><span>Elige, confirma y listo</span></div></div>
        <div class="service-item"><span class="service-icon" aria-hidden="true">▣</span><div><strong>Pago contra entrega</strong><span>Pagas cuando recibes</span></div></div>
        <div class="service-item"><span class="service-icon" aria-hidden="true">◷</span><div><strong>Pedidos organizados</strong><span>Sigue el estado de tu compra</span></div></div>
        <div class="service-item"><span class="service-icon" aria-hidden="true">♡</span><div><strong>Atención cercana</strong><span>Estamos para ayudarte</span></div></div>
      </div></div>
      <div class="page-wrap">
        <section class="section" id="categorias" aria-labelledby="categoriesTitle">
          <div class="section-heading"><div><span class="eyebrow">Encuentra tu próximo plan</span><h2 id="categoriesTitle">Explora por categoría</h2><p>Un rincón para cada idea, celebración y momento de juego.</p></div><a class="text-link" href="#catalogo" data-scroll="catalogo">Ver todo el catálogo <span aria-hidden="true">→</span></a></div>
          <div class="category-grid">${categoryTiles.map(([name, sub, icon], index) => `<button class="category-card" type="button" data-action="select-category" data-category="${H().escapeHTML(name)}"><span class="category-arrow" aria-hidden="true">↗</span><span><strong>${H().escapeHTML(name === "Todos" ? "Para cada ocasión" : name)}</strong><small>${H().escapeHTML(sub)}</small></span><span class="category-art" aria-hidden="true">${icon}</span></button>`).join("")}</div>
        </section>
        <section class="section catalog-section" id="catalogo" aria-labelledby="catalogTitle">
          <div class="section-heading"><div><span class="eyebrow">Catálogo ARTESOFT</span><h2 id="catalogTitle">Ideas para jugar y celebrar</h2><p>Referencias de tus catálogos. Precios mayoristas en pesos colombianos.</p></div><span class="catalog-note">Valores del catálogo 2026 ✳</span></div>
          <div class="catalog-tools"><div class="filter-list" role="group" aria-label="Filtrar productos por categoría">${categories.map((category) => `<button class="filter-chip${activeCategory === category ? " is-selected" : ""}" type="button" data-action="select-category" data-category="${H().escapeHTML(category)}" aria-pressed="${activeCategory === category}">${H().escapeHTML(category)}</button>`).join("")}</div>
            <select class="sort-select" id="catalogSort" aria-label="Ordenar productos"><option value="featured" ${sort === "featured" ? "selected" : ""}>Ordenar: recomendados</option><option value="price-asc" ${sort === "price-asc" ? "selected" : ""}>Precio: menor a mayor</option><option value="price-desc" ${sort === "price-desc" ? "selected" : ""}>Precio: mayor a menor</option><option value="name" ${sort === "name" ? "selected" : ""}>Nombre: A a Z</option></select>
          </div>
          <p class="product-count" id="productCount" aria-live="polite">${products.length} productos para ti</p>
          <div class="product-grid" id="productGrid">${products.length ? products.map(productCard).join("") : `<div class="empty-state"><strong>No encontramos ese producto</strong>Prueba con otra palabra o categoría.</div>`}</div>
        </section>
        <section class="story-band" aria-labelledby="storyTitle"><div class="story-art"><span aria-hidden="true">🎨</span></div><div class="story-copy"><span class="eyebrow">Más que productos, momentos</span><h2 id="storyTitle">Una idea colorida puede cambiar el día.</h2><p>Un juego para aprender juntos, un detalle que pone la fiesta en marcha o una caja llena de color. Aquí empieza ese recuerdo.</p><a href="#catalogo" data-scroll="catalogo" class="button button-lime">Encuentra tu idea <span aria-hidden="true">→</span></a></div></section>
      </div>`;
    },
    renderCards(products) {
      const grid = document.getElementById("productGrid");
      const count = document.getElementById("productCount");
      if (!grid) return;
      grid.innerHTML = products.length ? products.map(productCard).join("") : `<div class="empty-state"><strong>No encontramos ese producto</strong>Prueba con otra palabra o categoría.</div>`;
      if (count) count.textContent = `${products.length} ${products.length === 1 ? "producto" : "productos"} para ti`;
    },
    renderProductDetail(product) {
      const dialog = document.getElementById("productDialog");
      if (!dialog) return;
      dialog.innerHTML = `<form method="dialog" class="modal-close-row"><button class="icon-button" aria-label="Cerrar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"></path></svg></button></form><div class="product-detail"><div class="product-detail-visual ${this.colorClass(product.color)}${product.image ? " has-photo" : ""}">${visualContent(product, true)}</div><div class="product-detail-copy"><span class="eyebrow">${H().escapeHTML(product.category)}</span><h2 id="productDialogTitle">${H().escapeHTML(product.name)}</h2><p>${H().escapeHTML(product.description || "Un producto para imaginar, aprender y compartir.")}</p><div class="product-price">${H().money(product.price)}</div><span class="product-stock">${Number(product.stock) > 0 ? `${product.stock} unidades disponibles` : "Por ahora está agotado"}</span><button class="button button-primary button-wide" type="button" data-action="add-to-cart" data-id="${H().escapeHTML(product.id)}" ${product.stock <= 0 ? "disabled" : ""}>Agregar a la bolsa <span aria-hidden="true">+</span></button></div></div>`;
      dialog.showModal();
    },
    colorClass(color) { return artClasses.includes(color) ? color : "lilac"; }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.ProductoView = view;
})();
