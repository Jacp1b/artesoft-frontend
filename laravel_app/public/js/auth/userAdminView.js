(function () {
  const H = () => window.Artesoft.Helpers;
  const roleLabels = {
    administrador: "Administración",
    operador: "Ventas / operación",
    consulta: "Consulta",
    cliente: "Cliente"
  };
  const view = {
    render(users) {
      return `<div class="admin-heading"><div><span class="eyebrow">ACCESO Y PERFILES</span><h1>Cuentas de usuario</h1><p>Administra perfiles y restablece contraseñas.</p></div><button class="button button-primary button-small" type="button" data-action="add-user">＋ Nueva cuenta</button></div>
        <section class="admin-panel"><div class="panel-head"><div><h2>Usuarios registrados</h2><p>${users.length} cuentas en el sistema.</p></div></div>
          <div class="table-wrap"><table class="data-table"><thead><tr><th>Persona</th><th>Correo</th><th>Perfil</th><th>Acciones</th></tr></thead><tbody>${users.length ? users.map((user) => `<tr><td><strong>${H().escapeHTML(user.name)}</strong></td><td>${H().escapeHTML(user.email)}</td><td><span class="status-pill status-entregado">${H().escapeHTML(roleLabels[user.role] || user.role)}</span></td><td><div class="row-actions"><button class="mini-action" type="button" data-action="edit-user" data-id="${H().escapeHTML(user.id)}" aria-label="Editar cuenta de ${H().escapeHTML(user.name)}" title="Editar">✎</button><button class="mini-action danger" type="button" data-action="delete-user" data-id="${H().escapeHTML(user.id)}" aria-label="Eliminar cuenta de ${H().escapeHTML(user.name)}" title="Eliminar">⌫</button></div></td></tr>`).join("") : `<tr><td colspan="4" class="admin-empty">Aún no hay cuentas registradas.</td></tr>`}</tbody></table></div>
        </section>`;
    },
    renderForm(user = null) {
      const editing = Boolean(user);
      const initial = user || { name: "", email: "", role: "operador", phone: "", city: "", address: "" };
      const customerRole = initial.role === "cliente";
      const dialog = document.getElementById("confirmDialog");
      dialog.className = "modal-dialog";
      dialog.innerHTML = `<form method="dialog" class="modal-close-row"><button class="icon-button" aria-label="Cerrar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"></path></svg></button></form><span class="eyebrow">GESTIÓN DE CUENTAS</span><h2>${editing ? "Editar cuenta" : "Crear cuenta"}</h2><p class="modal-copy">${editing ? "Deja la contraseña vacía para conservar la actual." : "La contraseña debe tener al menos ocho caracteres."}</p>
        <form id="staffUserForm" class="form-stack" data-id="${H().escapeHTML(user?.id || "")}">
          <div class="form-field"><label for="staffUserName">Nombre completo</label><input id="staffUserName" name="name" required minlength="2" maxlength="90" value="${H().escapeHTML(initial.name)}" autocomplete="name"></div>
          <div class="form-field"><label for="staffUserEmail">Correo electrónico</label><input id="staffUserEmail" name="email" type="email" required maxlength="120" value="${H().escapeHTML(initial.email)}" autocomplete="email"></div>
          <div class="form-field"><label for="staffUserPassword">Contraseña ${editing ? "(opcional)" : ""}</label><input id="staffUserPassword" name="password" type="password" ${editing ? "" : "required"} minlength="8" maxlength="128" autocomplete="new-password"></div>
          <div class="form-field"><label for="staffUserRole">Perfil</label><select id="staffUserRole" name="role" required>${Object.entries(roleLabels).map(([role, label]) => `<option value="${role}" ${initial.role === role ? "selected" : ""}>${label}</option>`).join("")}</select></div>
          <div class="form-field"><label for="staffUserPhone">Celular</label><input id="staffUserPhone" name="phone" ${customerRole ? "required pattern=\"[0-9+(). \u005c-]{7,20}\"" : ""} maxlength="20" value="${H().escapeHTML(initial.phone || "")}"></div>
          <div class="form-field"><label for="staffUserCity">Ciudad o municipio</label><input id="staffUserCity" name="city" ${customerRole ? "required minlength=\"2\"" : ""} maxlength="60" value="${H().escapeHTML(initial.city || "")}"></div>
          <div class="form-field"><label for="staffUserAddress">Dirección</label><input id="staffUserAddress" name="address" ${customerRole ? "required minlength=\"5\"" : ""} maxlength="120" value="${H().escapeHTML(initial.address || "")}"></div>
          <button class="button button-primary button-wide" type="submit">${editing ? "Guardar cambios" : "Crear cuenta"}</button>
        </form>`;
      dialog.showModal();
      document.getElementById("staffUserName")?.focus();
    }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.UserAdminView = view;
})();
