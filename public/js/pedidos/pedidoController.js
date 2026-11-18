(function () {
  const controller = {
    bind() {
      document.addEventListener("change", (event) => {
        const select = event.target.closest("[data-order-status]");
        if (!select) return;
        const role = window.Artesoft.AuthModel.getRole();
        if (!window.Artesoft.Permissions.can(role, "pedidos.editar")) {
          window.Artesoft.Helpers.toast("Este perfil no tiene permiso para cambiar estados.", "error");
          return window.Artesoft.App.renderAdmin();
        }
        if (window.Artesoft.PedidoModel.updateStatus(select.dataset.orderStatus, select.value)) {
          window.Artesoft.Helpers.toast("Actualizamos el estado del pedido.");
          window.Artesoft.App.renderAdmin();
        }
      });
    }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.PedidoController = controller;
})();
