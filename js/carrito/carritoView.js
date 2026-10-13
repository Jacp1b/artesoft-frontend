(function () {
  const H = () => window.Artesoft.Helpers;
  const view = {
    renderCart(lines, totals) {
      const content = document.getElementById("cartContents");
      const checkout = document.getElementById("checkoutContents");
      if (!content || !checkout) return;
      checkout.hidden = true;
      content.hidden = false;
      if (!lines.length) {
        content.innerHTML = `<div class="drawer-empty"><span class="empty-icon" aria-hidden="true">✳</span><strong>Tu bolsa está esperando una idea</strong><p>Encuentra algo para crear, aprender o celebrar.</p><button class="button button-primary button-small" type="button" data-action="close-cart">Explorar productos</button></div>`;
        return;
      }
      const untilFree = Math.max(0, totals.freeFrom - totals.subtotal);
      content.innerHTML = `<div class="drawer-items">${lines.map((line) => `<article class="cart-line"><div class="cart-line-visual ${H().escapeHTML(line.color || "lilac")}">${H().escapeHTML(line.icon || "🎨")}</div><div class="cart-line-copy"><strong>${H().escapeHTML(line.name)}</strong><small>${H().money(line.price)} c/u</small><div class="quantity-control" aria-label="Cantidad de ${H().escapeHTML(line.name)}"><button type="button" data-action="cart-quantity" data-id="${H().escapeHTML(line.id)}" data-delta="-1" aria-label="Restar una unidad">−</button><span>${line.quantity}</span><button type="button" data-action="cart-quantity" data-id="${H().escapeHTML(line.id)}" data-delta="1" aria-label="Agregar una unidad">+</button></div></div><div class="cart-line-price">${H().money(line.price * line.quantity)}<button class="remove-line" type="button" data-action="remove-cart-line" data-id="${H().escapeHTML(line.id)}">Quitar</button></div></article>`).join("")}</div>
        <div class="cart-note">${totals.shipping === 0 ? "¡Tu envío va por cuenta de la casa!" : `Te faltan ${H().money(untilFree)} para obtener envío gratis.`}</div>
        <div class="cart-summary"><div class="summary-row"><span>Subtotal</span><strong>${H().money(totals.subtotal)}</strong></div><div class="summary-row"><span>Envío</span><strong>${totals.shipping === 0 ? "Gratis" : H().money(totals.shipping)}</strong></div><div class="summary-row summary-total"><span>Total estimado</span><strong>${H().money(totals.total)}</strong></div></div>
        <button class="button button-primary button-wide" type="button" data-action="start-checkout">Continuar con mis datos <span aria-hidden="true">→</span></button><p class="form-hint" style="text-align:center;margin-top:9px">Confirmas el pedido y pagas al recibirlo.</p>`;
    },
    renderCheckout(lines, totals, user = null) {
      const content = document.getElementById("cartContents");
      const checkout = document.getElementById("checkoutContents");
      if (!checkout) return;
      content.hidden = true;
      checkout.hidden = false;
      const savedDetails = user?.role === "cliente" ? user : null;
      const customerFields = savedDetails
        ? `<div class="saved-delivery-details"><strong>Usaremos los datos de tu cuenta</strong><p>${H().escapeHTML(savedDetails.name)} · ${H().escapeHTML(savedDetails.email)}</p><p>${H().escapeHTML(savedDetails.phone)}</p><p>${H().escapeHTML(savedDetails.address)}, ${H().escapeHTML(savedDetails.city)}</p></div>`
        : `<div class="form-field"><label for="checkoutName">Nombre completo</label><input id="checkoutName" name="name" autocomplete="name" required minlength="3" maxlength="90" placeholder="¿Cómo te llamas?"></div>
          <div class="form-row"><div class="form-field"><label for="checkoutEmail">Correo electrónico</label><input id="checkoutEmail" name="email" type="email" autocomplete="email" required maxlength="120" placeholder="tu@correo.com"></div><div class="form-field"><label for="checkoutPhone">Celular</label><input id="checkoutPhone" name="phone" type="tel" autocomplete="tel" inputmode="tel" required minlength="7" maxlength="20" pattern="[0-9+\\(\\) .\\-]{7,20}" placeholder="300 000 0000"></div></div>
          <div class="form-row"><div class="form-field"><label for="checkoutCity">Ciudad o municipio</label><input id="checkoutCity" name="city" autocomplete="address-level2" required minlength="2" maxlength="60" value="Bogotá"></div><div class="form-field"><label for="checkoutAddress">Dirección de entrega</label><input id="checkoutAddress" name="address" autocomplete="street-address" required minlength="5" maxlength="120" placeholder="Calle, carrera, número"></div></div>`;
      checkout.innerHTML = `<div class="checkout-header"><button class="checkout-back" type="button" data-action="back-to-cart" aria-label="Volver a la bolsa">←</button><div><strong>Tus datos de entrega</strong><p class="step-caption">Un último paso y queda listo</p></div></div>
        <form class="checkout-form" id="checkoutForm" novalidate data-saved-customer="${savedDetails ? "true" : "false"}">
          ${customerFields}
          <div class="form-field"><label for="checkoutNotes">Indicaciones para la entrega <span class="form-hint">Opcional</span></label><textarea id="checkoutNotes" name="notes" maxlength="220" placeholder="Apartamento, barrio o una referencia"></textarea></div>
          <div class="form-field"><label for="checkoutPayment">Forma de pago</label><select id="checkoutPayment" name="payment" required><option>Pago contra entrega</option></select></div>
          <div class="checkout-recap"><div class="summary-row"><span>${lines.reduce((n, line) => n + line.quantity, 0)} productos</span><strong>${H().money(totals.subtotal)}</strong></div><div class="summary-row"><span>Envío</span><strong>${totals.shipping ? H().money(totals.shipping) : "Gratis"}</strong></div><div class="summary-row summary-total"><span>Total a pagar</span><strong>${H().money(totals.total)}</strong></div></div>
          <button class="button button-primary button-wide" type="submit">Confirmar mi pedido <span aria-hidden="true">→</span></button>
          <p class="form-hint">${savedDetails ? "Se usarán los datos de entrega guardados en tu cuenta." : "Tus datos se guardan en este navegador como parte de este prototipo."}</p>
        </form>`;
    },
    renderSuccess(order) {
      const content = document.getElementById("cartContents");
      const checkout = document.getElementById("checkoutContents");
      content.hidden = true;
      checkout.hidden = false;
      checkout.innerHTML = `<div class="drawer-empty"><span class="empty-icon" aria-hidden="true">✓</span><span class="eyebrow">PEDIDO CONFIRMADO</span><strong style="margin-top:8px">¡Gracias, ${H().escapeHTML(order.customer.name.split(" ")[0])}!</strong><p>Guardamos tu pedido <strong>${H().escapeHTML(order.id)}</strong>. Podrás revisar su avance con el correo que registraste.</p><div style="width:100%;display:grid;gap:8px"><button class="button button-primary button-wide" type="button" data-action="check-my-order">Seguir mi pedido</button><button class="button button-secondary button-wide" type="button" data-action="close-cart">Seguir explorando</button></div></div>`;
    }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.CarritoView = view;
})();
