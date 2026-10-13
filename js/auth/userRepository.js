(function () {
  const KEY = "artesoft:v1:users";
  const storage = () => window.Artesoft.StorageService;
  const testUsers = [
    { id: "demo-admin", name: "Administración Demo", email: "admin@artesoft.local", password: "Demo1234!", role: "administrador" },
    { id: "demo-sales", name: "Ventas Demo", email: "ventas@artesoft.local", password: "Demo1234!", role: "operador" },
    { id: "demo-viewer", name: "Consulta Demo", email: "consulta@artesoft.local", password: "Demo1234!", role: "consulta" },
    { id: "demo-customer", name: "Laura Martínez", email: "laura@example.com", password: "Demo1234!", role: "cliente", phone: "300 555 0101", city: "Bogotá", address: "Calle 83 # 12-40" }
  ];
  const repository = {
    initialize() {
      if (storage().read(KEY, null) === null && !storage().write(KEY, testUsers)) {
        throw new Error("No se pudieron inicializar las cuentas de demostración en el almacenamiento local.");
      }
    },
    list() { return storage().read(KEY, []); },
    findByEmail(email) {
      const normalized = email.trim().toLocaleLowerCase("es-CO");
      return this.list().find((user) => user.email.toLocaleLowerCase("es-CO") === normalized) || null;
    },
    findById(id) { return this.list().find((user) => user.id === id) || null; },
    create(user) {
      const users = this.list();
      if (users.some((entry) => entry.email.toLocaleLowerCase("es-CO") === user.email.toLocaleLowerCase("es-CO"))) {
        return { ok: false, message: "Ya existe una cuenta con ese correo electrónico." };
      }
      const created = { ...user, id: `user-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`, createdAt: new Date().toISOString() };
      if (!storage().write(KEY, [...users, created])) {
        return { ok: false, message: "No se pudo guardar la cuenta. Revisa el almacenamiento de este navegador." };
      }
      return { ok: true, user: created };
    }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.UserRepository = repository;
})();
