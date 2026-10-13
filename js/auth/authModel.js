(function () {
  const SESSION_KEY = "artesoft:v1:session";
  const roles = ["administrador", "operador", "consulta"];
  const storage = () => window.Artesoft.StorageService;
  const repository = () => window.Artesoft.UserRepository;
  const model = {
    initialize() {
      repository().initialize();
      sessionStorage.removeItem("artesoft:demo-session");
    },
    getCurrentUser() {
      const session = storage().read(SESSION_KEY, null);
      return session?.userId ? repository().findById(session.userId) : null;
    },
    getRole() { return this.getCurrentUser()?.role || ""; },
    login(email, password) {
      const user = repository().findByEmail(email);
      if (!user || user.password !== password) {
        return { ok: false, message: "El correo o la contraseña no son correctos." };
      }
      if (!storage().write(SESSION_KEY, { userId: user.id, createdAt: Date.now() })) {
        return { ok: false, message: "No se pudo iniciar la sesión en este navegador." };
      }
      return { ok: true, user };
    },
    registerCustomer(data) {
      return repository().create({ ...data, role: "cliente", createdAt: new Date().toISOString() });
    },
    createStaff(data) {
      if (this.getRole() !== "administrador") {
        return { ok: false, message: "Solo administración puede crear cuentas del equipo." };
      }
      if (!roles.includes(data.role)) {
        return { ok: false, message: "Selecciona un perfil de trabajo válido." };
      }
      return repository().create({ ...data, createdAt: new Date().toISOString() });
    },
    listStaff() { return repository().list().filter((user) => user.role !== "cliente"); },
    clear() {
      storage().remove(SESSION_KEY);
      sessionStorage.removeItem("artesoft:demo-session");
    }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.AuthModel = model;
})();
