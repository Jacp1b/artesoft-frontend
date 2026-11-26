(function () {
  const api = () => window.Artesoft.Api;
  const repository = {
    initialize() { return this.me(); },
    me() { return api().request("auth/me").user || null; },
    login(email, password) {
      return api().request("auth/login", "POST", { email, password });
    },
    register(user) {
      return api().request("auth/register", "POST", user);
    },
    logout() {
      return api().request("auth/logout", "POST");
    },
    list() {
      const response = api().request("users");
      return response.ok ? response.data : [];
    },
    create(user) {
      const response = api().request("users", "POST", user);
      return response.ok
        ? { ok: true, user: response.data }
        : { ok: false, message: response.message };
    },
    update(id, user) {
      return api().request("users", "PUT", user, id);
    },
    remove(id) {
      return api().request("users", "DELETE", null, id);
    }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.UserRepository = repository;
})();
