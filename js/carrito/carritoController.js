(function () {
  const model = () => window.Artesoft.CarritoModel;
  const view = () => window.Artesoft.CarritoView;
  const controller = {
    refreshCount() {
      const count = model().count();
      const badge = document.getElementById("cartCount");
      const title = document.getElementById("cartTitleCount");
      if (badge) badge.textContent = count;
      if (title) title.textContent = count ? `(${count})` : "";
      if (document.getElementById("cartDialog")?.open && !document.getElementById("checkoutContents")?.hidden) view().renderCart(model().list(), model().totals());
    },
    open() {
      const dialog = document.getElementById("cartDialog");
      if (!dialog.open) dialog.showModal();
      view().renderCart(model().list(), model().totals());
      this.refreshCount();
    },
    bind() {
      document.addEventListener("click", (event) => {
        const target = event.target.closest("[data-action]");
        if (!target) return;
        const action = target.dataset.action;
        if (action === "open-cart") { event.preventDefault(); this.open(); }
        if (action === "close-cart") document.getElementById("cartDialog")?.close();
        if (action === "cart-quantity") {
          const result = model().setQuantity(target.dataset.id, Number(target.dataset.delta) + (model().list().find((line) => line.id === target.dataset.id)?.quantity || 0));
          if (!result.ok) window.Artesoft.Helpers.toast(result.message || "No se pudo actualizar la cantidad.", "error");
          view().renderCart(model().list(), model().totals());
          this.refreshCount();
        }
        if (action === "remove-cart-line") {
          model().remove(target.dataset.id);
          view().renderCart(model().list(), model().totals());
          this.refreshCount();
        }
        if (action === "start-checkout") {
          const lines = model().list();
          if (!lines.length) return;
          const user = window.Artesoft.AuthModel.getCurrentUser();
          view().renderCheckout(lines, model().totals(), user);
          document.getElementById("checkoutName")?.focus();
        }
        if (action === "back-to-cart") view().renderCart(model().list(), model().totals());
        if (action === "check-my-order") {
          const email = window.Artesoft.CarritoController.lastCustomerEmail || "";
          document.getElementById("cartDialog")?.close();
          window.Artesoft.App.setOrderEmail(email);
          window.Artesoft.App.navigate("orders");
        }
      });
      document.addEventListener("submit", (event) => {
        if (event.target.id !== "checkoutForm") return;
        event.preventDefault();
        const form = event.target;
        const user = window.Artesoft.AuthModel.getCurrentUser();
        const savedCustomer = form.dataset.savedCustomer === "true" && user?.role === "cliente" ? user : null;
        let customer;
        if (savedCustomer) {
          customer = {
            name: savedCustomer.name,
            email: savedCustomer.email,
            phone: savedCustomer.phone,
            city: savedCustomer.city,
            address: savedCustomer.address
          };
        } else {
          const name = form.elements.name.value.trim();
          const phone = form.elements.phone.value.trim();
          const city = form.elements.city.value.trim();
          const address = form.elements.address.value.trim();
          if (name.length < 3 || phone.length < 7 || city.length < 2 || address.length < 5) {
            window.Artesoft.Helpers.toast("Revisa el nombre, celular y dirección de entrega.", "error");
            form.reportValidity();
            return;
          }
          customer = { name, email: form.elements.email.value.trim(), phone, city, address };
        }
        if (!form.checkValidity()) { form.reportValidity(); return; }
        const lines = model().list();
        const outOfStock = lines.find((line) => line.quantity > (window.Artesoft.ProductoModel.get(line.id)?.stock ?? 0));
        if (outOfStock) {
          window.Artesoft.Helpers.toast(`La disponibilidad de ${outOfStock.name} cambió. Ajusta la bolsa.`, "error");
          view().renderCart(model().list(), model().totals());
          return;
        }
        const totals = model().totals();
        const result = window.Artesoft.PedidoModel.create({
          customer: { ...customer, notes: form.elements.notes.value.trim() },
          items: lines.map((line) => ({ productId: line.id, name: line.name, price: line.price, quantity: line.quantity })),
          subtotal: totals.subtotal, shipping: totals.shipping, total: totals.total, payment: "Pago contra entrega"
        });
        if (!result.ok) {
          window.Artesoft.Helpers.toast(result.message, "error");
          return;
        }
        const order = result.data;
        model().clear();
        this.lastCustomerEmail = order.customer.email;
        this.refreshCount();
        view().renderSuccess(order);
        window.Artesoft.Helpers.toast("Tu pedido quedó registrado.");
      });
    }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.CarritoController = controller;
})();
