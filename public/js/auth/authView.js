(function () {
  const view = {
    openCustomer(mode) {
      this.setCustomerMode(mode);
      const dialog = document.getElementById("customerAuthDialog");
      if (!dialog.open) dialog.showModal();
    },
    setCustomerMode(mode) {
      const isRegister = mode === "register";
      const nameField = document.querySelector(".customer-name-field");
      const registrationFields = document.querySelector(".customer-registration-fields");
      const nameInput = document.getElementById("customerAuthName");
      const passwordInput = document.getElementById("customerAuthPassword");
      const form = document.getElementById("customerAuthForm");
      document.getElementById("customerAuthTitle").textContent = isRegister ? "Crea tu cuenta" : "Inicia sesión";
      document.getElementById("customerAuthCopy").textContent = isRegister
        ? "Registra tus datos de entrega una sola vez y agiliza tus próximas compras."
        : "Accede a tu cuenta para tener tu experiencia de compra a la mano.";
      document.getElementById("customerAuthNotice").textContent = isRegister
        ? "Tus datos se guardan de forma segura para que puedas consultar tus pedidos."
        : "Administración inicial: admin@artesoft.local · contraseña temporal: Demo1234! Cámbiala después del primer acceso.";
      document.getElementById("customerAuthSubmit").innerHTML = isRegister
        ? 'Crear cuenta <span aria-hidden="true">→</span>'
        : 'Iniciar sesión <span aria-hidden="true">→</span>';
      nameField.hidden = !isRegister;
      nameInput.required = isRegister;
      nameInput.autocomplete = isRegister ? "name" : "off";
      registrationFields.hidden = !isRegister;
      registrationFields.querySelectorAll("input").forEach((input) => { input.required = isRegister; });
      passwordInput.autocomplete = isRegister ? "new-password" : "current-password";
      form.dataset.authMode = isRegister ? "register" : "login";
      document.querySelectorAll("[data-action='customer-auth-mode']").forEach((button) => {
        const active = button.dataset.authMode === mode;
        button.classList.toggle("is-active", active);
        button.setAttribute("aria-pressed", String(active));
      });
    },
    refreshHeader() {
      const user = window.Artesoft.AuthModel.getCurrentUser();
      const canAccessStaffPanel = ["administrador", "operador"].includes(user?.role);
      const loginButtons = [document.getElementById("customerLoginButton"), document.getElementById("mobileCustomerLoginButton")];
      const registerButtons = [document.getElementById("customerRegisterButton"), document.getElementById("mobileCustomerRegisterButton")];
      const staffPanelLinks = [document.getElementById("staffAdminButton"), document.getElementById("mobileStaffAdminLink")];
      loginButtons.forEach((button) => {
        if (!button) return;
        button.dataset.action = user ? "logout" : "open-customer-auth";
        if (!user) button.dataset.authMode = "login";
        button.textContent = user ? `Salir · ${user.name}` : "Iniciar sesión";
        button.setAttribute("aria-label", user ? `Cerrar sesión de ${user.name}` : "Iniciar sesión");
      });
      registerButtons.forEach((button) => { if (button) button.hidden = Boolean(user); });
      staffPanelLinks.forEach((link) => { if (link) link.hidden = !canAccessStaffPanel; });
    }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.AuthView = view;
})();
