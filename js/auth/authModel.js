(function () {
  const roles = ["administrador", "operador", "consulta", "cliente"];
  let currentUser = null;
  const repository = () => window.Artesoft.UserRepository;
  const model = {
    initialize() { currentUser = repository().initialize(); },
    getCurrentUser() { return currentUser; },
    getRole() { return currentUser?.role || ""; },
    login(email, password) {
      const result = repository().login(email, password);
      if (result.ok) currentUser = result.user;
      return result;
    },
    registerCustomer(data) {
      const result = repository().register({ ...data, role: "cliente" });
      if (result.ok) currentUser = result.user;
      return result;
    },
    createStaff(data) {
      if (this.getRole() !== "administrador") {
        return { ok: false, message: "Solo administración puede crear cuentas del equipo." };
      }
      if (!roles.includes(data.role)) {
        return { ok: false, message: "Selecciona un perfil válido." };
      }
      return repository().create(data);
    },
    updateStaff(id, data) {
      if (this.getRole() !== "administrador") {
        return { ok: false, message: "Solo administración puede editar cuentas del equipo." };
      }
      if (data.role !== "cliente" && !roles.includes(data.role)) {
        return { ok: false, message: "Selecciona un perfil válido." };
      }
      const result = repository().update(id, data);
      if (!result.ok) return result;
      if (String(currentUser?.id) === String(id)) currentUser = result.data;
      return { ok: true, user: result.data };
    },
    deleteStaff(id) {
      if (this.getRole() !== "administrador") {
        return { ok: false, message: "Solo administración puede eliminar cuentas." };
      }
      return repository().remove(id);
    },
    listStaff() { return repository().list(); },
    clear() {
      const result = repository().logout();
      if (result.ok) currentUser = null;
      return result;
    }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.AuthModel = model;
})();
