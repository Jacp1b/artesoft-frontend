(function () {
  const H = () => window.Artesoft.Helpers;
  const statusSteps = ["Pedido recibido", "En preparación", "Enviado", "Entregado"];
  function progress(status) {
    const current = Math.max(0, statusSteps.indexOf(status));
    return `<div class="order-timeline" aria-label="Avance del pedido">${statusSteps.map((_, index) => `<span class="timeline-step${index <= current ? " done" : ""}"></span>`).join("")}</div>`;
  }
  function statusPill(status) { return `<span class="status-pill ${H().statusClass(status)}">${H().escapeHTML(status)}</span>`; }
  function statusSelect(order, disabled = false) {
    return `<select class="order-status-select" data-order-status="${H().escapeHTML(order.id)}" aria-label="Estado del pedido ${H().escapeHTML(order.id)}" ${disabled ? "disabled" : ""}>${statusSteps.map((status) => `<option ${status === order.status ? "selected" : ""}>${status}</option>`).join("")}</select>`;
  }
  function orderCard(order) {
    const itemText = order.items.map((item) => `${item.quantity} × ${H().escapeHTML(item.name)}`).join(" · ");
    return `<article class="order-card"><div class="order-card-head"><div><strong>${H().escapeHTML(order.id)}</strong><small>${H().date(order.createdAt)} · ${order.items.reduce((sum, item) => sum + Number(item.quantity), 0)} productos</small></div>${statusPill(order.status)}</div><div class="order-card-body"><div class="order-items-text">${itemText}<br><span class="form-hint">${H().escapeHTML(order.payment || "Pago contra entrega")}</span></div><div class="order-total"><small>Total del pedido</small><strong>${H().money(order.total)}</strong></div></div>${progress(order.status)}</article>`;
  }
  const view = {
    renderCustomerPage(email = "", orders = null) {
      let results = "";
      if (orders) results = orders.length ? `<div class="orders-list">${orders.map(orderCard).join("")}</div>` : `<div class="empty-state" style="max-width:740px;margin-top:18px"><strong>Aún no encontramos pedidos con ese correo</strong>Revisa que sea el mismo que escribiste al confirmar tu compra.</div>`;
      return `<div class="page-wrap orders-page"><div class="page-heading"><div><span class="eyebrow">Tu compra, siempre a la mano</span><h1 class="page-title">Mis pedidos</h1><p class="page-description">Consulta el estado de tu compra y revisa qué viene en camino. Usa el mismo correo que registraste al hacer el pedido.</p></div><span class="status-pill status-enviado">Seguimiento sencillo</span></div>
        <form class="order-search-card" id="orderLookup"><div class="form-field"><label for="orderEmail">Correo electrónico de tu compra</label><input id="orderEmail" name="email" type="email" required maxlength="120" autocomplete="email" value="${H().escapeHTML(email)}" placeholder="tu@correo.com"></div><button class="button button-primary" type="submit">Buscar pedidos <span aria-hidden="true">→</span></button></form>
        <p class="privacy-note">El historial de este prototipo se consulta con el correo del pedido y solo está guardado en este navegador. Dato de ejemplo para explorar: <strong>laura@example.com</strong>.</p>${results || ""}</div>`;
    },
    renderDashboard(orders, products, role) {
      const open = orders.filter((order) => order.status !== "Entregado").length;
      const sales = orders.reduce((sum, order) => sum + Number(order.total), 0);
      const customers = window.Artesoft.PedidoModel.customers().length;
      const lowStock = products.filter((product) => product.stock <= 9).sort((a, b) => a.stock - b.stock).slice(0, 4);
      const recent = orders.slice(0, 5);
      const canUpdate = window.Artesoft.Permissions.can(role, "pedidos.editar");
      return `<div class="admin-heading"><div><span class="eyebrow">RESUMEN DEL NEGOCIO</span><h1>Hola, equipo Arte Didáctica</h1><p>Así se mueve la tienda en este prototipo.</p></div><span class="admin-demo-tag">Datos de ejemplo y de este navegador</span></div>
        <div class="metric-grid"><article class="metric-card"><div class="metric-label"><span>Pedidos por atender</span><span class="metric-icon">▣</span></div><strong class="metric-number">${open}</strong><span class="metric-foot">En recepción, preparación o envío</span></article><article class="metric-card"><div class="metric-label"><span>Ventas registradas</span><span class="metric-icon">$</span></div><strong class="metric-number">${H().money(sales)}</strong><span class="metric-foot">Suma de pedidos del navegador</span></article><article class="metric-card"><div class="metric-label"><span>Productos activos</span><span class="metric-icon">✳</span></div><strong class="metric-number">${products.length}</strong><span class="metric-foot">Referencias en el catálogo</span></article><article class="metric-card"><div class="metric-label"><span>Clientes registrados</span><span class="metric-icon">♡</span></div><strong class="metric-number">${customers}</strong><span class="metric-foot">Con pedidos en este prototipo</span></article></div>
        <div class="admin-grid-two"><section class="admin-panel"><div class="panel-head"><div><h2>Pedidos recientes</h2><p>Actualiza el estado según avance la entrega.</p></div><button type="button" data-action="admin-tab" data-tab="orders">Ver pedidos →</button></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Pedido</th><th>Cliente</th><th>Total</th><th>Estado</th></tr></thead><tbody>${recent.length ? recent.map((order) => `<tr><td><strong>${H().escapeHTML(order.id)}</strong><span class="table-sub">${H().date(order.createdAt)}</span></td><td>${H().escapeHTML(order.customer.name)}</td><td>${H().money(order.total)}</td><td>${statusSelect(order, !canUpdate)}</td></tr>`).join("") : `<tr><td colspan="4" class="admin-empty">Aún no hay pedidos.</td></tr>`}</tbody></table></div></section>
        <section class="admin-panel"><div class="panel-head"><div><h2>Inventario por revisar</h2><p>Referencias con pocas unidades.</p></div><button type="button" data-action="admin-tab" data-tab="inventory">Ver inventario →</button></div><div class="stock-list">${lowStock.length ? lowStock.map((product) => `<div class="stock-item"><span class="stock-item-icon ${H().escapeHTML(product.color || "lilac")}">${H().escapeHTML(product.icon || "🎨")}</span><div><strong>${H().escapeHTML(product.name)}</strong><small>${H().escapeHTML(product.category)}</small></div><span class="stock-level">${product.stock} und.</span></div>`).join("") : `<div class="admin-empty">Todo el inventario está en buen nivel.</div>`}</div></section></div>`;
    },
    renderAdminOrders(orders, role) {
      const canUpdate = window.Artesoft.Permissions.can(role, "pedidos.editar");
      return `<div class="admin-heading"><div><span class="eyebrow">VENTAS Y SEGUIMIENTO</span><h1>Pedidos</h1><p>Revisa cada compra y acompáñala hasta la entrega.</p></div></div><section class="admin-panel"><div class="panel-head"><div><h2>Todos los pedidos</h2><p>${orders.length} registrados en este navegador.</p></div><span class="admin-demo-tag">Datos locales de demostración</span></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Pedido</th><th>Cliente</th><th>Contacto</th><th>Fecha</th><th>Total</th><th>Estado</th></tr></thead><tbody>${orders.length ? orders.map((order) => `<tr><td><strong>${H().escapeHTML(order.id)}</strong></td><td>${H().escapeHTML(order.customer.name)}<span class="table-sub">${order.items.reduce((sum, item) => sum + item.quantity, 0)} productos</span></td><td>${H().escapeHTML(order.customer.email)}<span class="table-sub">${H().escapeHTML(order.customer.phone || "")}</span></td><td>${H().date(order.createdAt)}</td><td>${H().money(order.total)}</td><td>${statusSelect(order, !canUpdate)}</td></tr>`).join("") : `<tr><td colspan="6" class="admin-empty">Los pedidos nuevos aparecerán aquí.</td></tr>`}</tbody></table></div>${canUpdate ? "" : `<p class="readonly-note">Tu perfil puede consultar los pedidos, pero no cambiar su estado.</p>`}</section>`;
    },
    renderClients(customers) {
      return `<div class="admin-heading"><div><span class="eyebrow">COMUNIDAD ARTESOFT</span><h1>Clientes</h1><p>Personas que han registrado un pedido en este prototipo.</p></div></div><section class="admin-panel"><div class="panel-head"><div><h2>Directorio de clientes</h2><p>Los datos provienen de los pedidos guardados en este navegador.</p></div><span class="admin-demo-tag">${customers.length} clientes</span></div><div class="clients-grid">${customers.length ? customers.map((customer) => {
        const initials = customer.name.split(/\s+/).slice(0, 2).map((part) => part[0]).join("").toUpperCase();
        return `<article class="client-card"><span class="client-initials">${H().escapeHTML(initials)}</span><div><strong>${H().escapeHTML(customer.name)}</strong><small>${H().escapeHTML(customer.email)} · ${H().escapeHTML(customer.city || "")}</small><small>${customer.orders} ${customer.orders === 1 ? "pedido" : "pedidos"} · ${H().money(customer.total)} · último ${H().date(customer.lastOrder)}</small></div></article>`;
      }).join("") : `<div class="admin-empty">Los clientes aparecerán cuando confirmen su primer pedido.</div>`}</div></section>`;
    }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.PedidoView = view;
})();
