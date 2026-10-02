(function () {
  const KEY = "artesoft:demo-session";
  const model = {
    getRole() {
      try { return JSON.parse(sessionStorage.getItem(KEY) || "null")?.role || ""; } catch (_) { return ""; }
    },
    setRole(role) {
      if (!["administrador", "operador", "consulta"].includes(role)) return false;
      sessionStorage.setItem(KEY, JSON.stringify({ role, createdAt: Date.now() }));
      return true;
    },
    clear() { sessionStorage.removeItem(KEY); }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.AuthModel = model;
})();
