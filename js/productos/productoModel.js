(function () {
  const api = () => window.Artesoft.Api;
  const model = {
    initialize() {
      const response = api().request("products");
      if (!response.ok) throw new Error(response.message);
    },
    list() {
      const response = api().request("products");
      return response.ok ? response.data : [];
    },
    get(id) {
      const response = api().request("products", "GET", null, id);
      return response.ok ? response.data : null;
    },
    create(data) {
      const response = api().request("products", "POST", data);
      return response.ok ? response.data : null;
    },
    update(id, data) {
      const response = api().request("products", "PUT", data, id);
      return response.ok ? response.data : null;
    },
    remove(id) {
      return api().request("products", "DELETE", null, id).ok;
    },
    existsName(name, exceptId = "") {
      const normalized = name.trim().toLocaleLowerCase("es-CO");
      return this.list().some((item) => item.id !== exceptId && item.name.trim().toLocaleLowerCase("es-CO") === normalized);
    }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.ProductoModel = model;
})();
