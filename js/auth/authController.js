(function () {
  const controller = {
    bind() {
      document.addEventListener("click", (event) => {
        const action = event.target.closest("[data-action]");
        if (action?.dataset.action === "open-access") window.Artesoft.AuthView.open();
        if (action?.dataset.action === "open-customer-auth") {
          document.getElementById("mobileMenu")?.setAttribute("hidden", "");
          document.querySelector("[data-action='toggle-menu']")?.setAttribute("aria-expanded", "false");
          window.Artesoft.AuthView.openCustomer(action.dataset.authMode);
        }
        if (action?.dataset.action === "customer-auth-mode") {
          document.getElementById("customerAuthForm").reset();
          window.Artesoft.AuthView.setCustomerMode(action.dataset.authMode);
        }
        if (event.target.closest("[data-action='exit-admin']")) {
          window.Artesoft.AuthModel.clear();
          window.Artesoft.Helpers.toast("Cerraste el espacio del equipo.");
          window.Artesoft.App.navigate("store");
        }
      });
      document.getElementById("accessForm")?.addEventListener("submit", (event) => {
        event.preventDefault();
        const role = document.getElementById("demoRole").value;
        if (!window.Artesoft.AuthModel.setRole(role)) return;
        document.getElementById("accessDialog")?.close();
        window.Artesoft.App.navigate("admin");
        window.Artesoft.Helpers.toast(`Perfil de ${window.Artesoft.Permissions.label(role).toLowerCase()} activado para esta demostración.`);
      });
      document.getElementById("customerAuthForm")?.addEventListener("submit", (event) => {
        event.preventDefault();
        const mode = document.querySelector(".auth-mode-button.is-active")?.dataset.authMode;
        window.Artesoft.Helpers.toast(
          mode === "register"
            ? "El registro no está disponible: falta conectar el servicio de autenticación."
            : "El inicio de sesión no está disponible: falta conectar el servicio de autenticación.",
          "error"
        );
      });
    }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.AuthController = controller;
})();
