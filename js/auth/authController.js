(function () {
  const controller = {
    bind() {
      document.addEventListener("click", (event) => {
        const action = event.target.closest("[data-action]");
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
          window.Artesoft.AuthView.refreshHeader();
          window.Artesoft.App.clearOrderLookup();
          window.Artesoft.Helpers.toast("Cerraste la sesión.");
          window.Artesoft.App.navigate("store");
        }
        if (action?.dataset.action === "logout") {
          window.Artesoft.AuthModel.clear();
          window.Artesoft.AuthView.refreshHeader();
          window.Artesoft.App.clearOrderLookup();
          window.Artesoft.Helpers.toast("Cerraste la sesión.");
          window.Artesoft.App.navigate("store");
        }
      });
      document.getElementById("customerAuthForm")?.addEventListener("submit", (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        if (!form.checkValidity()) return form.reportValidity();
        const data = Object.fromEntries(new FormData(form).entries());
        const mode = form.dataset.authMode;
        let result;
        if (mode === "register") {
          result = window.Artesoft.AuthModel.registerCustomer({
            name: data.name.trim(),
            email: data.email.trim(),
            password: data.password,
            phone: data.phone.trim(),
            city: data.city.trim(),
            address: data.address.trim()
          });
          if (result.ok) result = window.Artesoft.AuthModel.login(data.email, data.password);
        } else {
          result = window.Artesoft.AuthModel.login(data.email, data.password);
        }
        if (!result.ok) return window.Artesoft.Helpers.toast(result.message, "error");
        document.getElementById("customerAuthDialog")?.close();
        form.reset();
        window.Artesoft.AuthView.refreshHeader();
        if (result.user.role !== "cliente") {
          window.Artesoft.App.navigate("admin");
          window.Artesoft.Helpers.toast(`Sesión iniciada: ${window.Artesoft.Permissions.label(result.user.role)}.`);
        } else {
          window.Artesoft.App.navigate("store", { scroll: false });
          window.Artesoft.Helpers.toast(mode === "register" ? "Tu cuenta quedó creada y ya iniciaste sesión." : "Bienvenido de nuevo.");
        }
      });
      document.addEventListener("submit", (event) => {
        if (event.target.id !== "staffUserForm") return;
        event.preventDefault();
        if (window.Artesoft.AuthModel.getRole() !== "administrador") {
          return window.Artesoft.Helpers.toast("Solo administración puede crear cuentas del equipo.", "error");
        }
        const form = event.target;
        if (!form.checkValidity()) return form.reportValidity();
        const data = Object.fromEntries(new FormData(form).entries());
        const result = window.Artesoft.AuthModel.createStaff({
          name: data.name.trim(),
          email: data.email.trim(),
          password: data.password,
          role: data.role
        });
        if (!result.ok) return window.Artesoft.Helpers.toast(result.message, "error");
        window.Artesoft.Helpers.toast("Creamos la cuenta del equipo.");
        window.Artesoft.App.renderAdmin();
      });
    }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.AuthController = controller;
})();
