(function () {
  const H = () => window.Artesoft.Helpers;
  const view = {
    render(users) {
      const staff = users.slice().sort((a, b) => a.name.localeCompare(b.name, "es"));
      return `<div class="admin-heading"><div><span class="eyebrow">ACCESO Y PERFILES</span><h1>Cuentas del equipo</h1><p>Solo administración puede crear cuentas internas y asignarles permisos.</p></div></div>
        <div class="admin-grid-two">
          <section class="admin-panel"><div class="panel-head"><div><h2>Crear cuenta del equipo</h2><p>Los clientes se registran desde la tienda; aquí solo se asignan perfiles de trabajo.</p></div></div>
            <form id="staffUserForm" class="form-stack">
              <div class="form-field"><label for="staffUserName">Nombre completo</label><input id="staffUserName" name="name" required minlength="2" maxlength="90" autocomplete="name"></div>
              <div class="form-field"><label for="staffUserEmail">Correo electrónico</label><input id="staffUserEmail" name="email" type="email" required maxlength="120" autocomplete="email"></div>
              <div class="form-field"><label for="staffUserPassword">Contraseña temporal</label><input id="staffUserPassword" name="password" type="password" required minlength="8" maxlength="128" autocomplete="new-password"></div>
              <div class="form-field"><label for="staffUserRole">Perfil de trabajo</label><select id="staffUserRole" name="role" required><option value="operador">Ventas / operación</option><option value="consulta">Consulta · solo lectura</option><option value="administrador">Administración</option></select></div>
              <button class="button button-primary button-wide" type="submit">Crear cuenta del equipo</button>
              <p class="form-hint">Este prototipo guarda las cuentas en este navegador. No uses contraseñas reales.</p>
            </form>
          </section>
          <section class="admin-panel"><div class="panel-head"><div><h2>Perfiles registrados</h2><p>${staff.length} cuentas con acceso al panel.</p></div></div>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Persona</th><th>Correo</th><th>Perfil</th></tr></thead><tbody>${staff.length ? staff.map((user) => `<tr><td><strong>${H().escapeHTML(user.name)}</strong></td><td>${H().escapeHTML(user.email)}</td><td><span class="status-pill status-entregado">${H().escapeHTML(window.Artesoft.Permissions.label(user.role))}</span></td></tr>`).join("") : `<tr><td colspan="3" class="admin-empty">Aún no hay cuentas internas.</td></tr>`}</tbody></table></div>
          </section>
        </div>`;
    }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.UserAdminView = view;
})();
