(function () {
  const view = {
    open() {
      const dialog = document.getElementById("accessDialog");
      const role = window.Artesoft.AuthModel.getRole();
      if (role) document.getElementById("demoRole").value = role;
      if (!dialog.open) dialog.showModal();
    },
    openCustomer(mode) {
      this.setCustomerMode(mode);
      const dialog = document.getElementById("customerAuthDialog");
      if (!dialog.open) dialog.showModal();
    },
    setCustomerMode(mode) {
      const isRegister = mode === "register";
      const nameField = document.querySelector(".customer-name-field");
      const nameInput = document.getElementById("customerAuthName");
      const passwordInput = document.getElementById("customerAuthPassword");
      document.getElementById("customerAuthTitle").textContent = isRegister ? "Crea tu cuenta" : "Inicia sesión";
      document.getElementById("customerAuthCopy").textContent = isRegister
        ? "Regístrate para tener tu experiencia de compra a la mano."
        : "Accede a tu cuenta para tener tu experiencia de compra a la mano.";
      document.getElementById("customerAuthNotice").textContent = isRegister
        ? "Interfaz de demostración: el registro requiere conectar un servicio de autenticación."
        : "Interfaz de demostración: el inicio de sesión requiere conectar un servicio de autenticación.";
      document.getElementById("customerAuthSubmit").innerHTML = isRegister
        ? 'Crear cuenta <span aria-hidden="true">→</span>'
        : 'Iniciar sesión <span aria-hidden="true">→</span>';
      nameField.hidden = !isRegister;
      nameInput.required = isRegister;
      nameInput.autocomplete = isRegister ? "name" : "off";
      passwordInput.autocomplete = isRegister ? "new-password" : "current-password";
      document.querySelectorAll("[data-action='customer-auth-mode']").forEach((button) => {
        const active = button.dataset.authMode === mode;
        button.classList.toggle("is-active", active);
        button.setAttribute("aria-pressed", String(active));
      });
    }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.AuthView = view;
})();
