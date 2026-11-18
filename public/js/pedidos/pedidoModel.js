(function () {
  const api = () => window.Artesoft.Api;
  const model = {
    initialize() {
      const response = api().request("orders");
      if (response.status === 401) return [];
      if (!response.ok) throw new Error(response.message);
      return response.data;
    },
    list() {
      const response = api().request("orders");
      return response.ok ? response.data.slice().sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt)) : [];
    },
    create(data) {
      return api().request("orders", "POST", data);
    },
    updateStatus(id, status) {
      return api().request("orders", "PUT", { status }, id).ok;
    },
    forEmail(email) {
      const user = window.Artesoft.AuthModel.getCurrentUser();
      if (user?.role === "cliente" && user.email.toLocaleLowerCase("es-CO") !== email.trim().toLocaleLowerCase("es-CO")) return [];
      return this.list();
    },
    customers() {
      const map = new Map();
      this.list().forEach((order) => {
        const email = order.customer.email.toLocaleLowerCase("es-CO");
        const current = map.get(email) || { ...order.customer, orders: 0, total: 0, lastOrder: order.createdAt };
        current.orders += 1;
        current.total += Number(order.total);
        if (new Date(order.createdAt) > new Date(current.lastOrder)) current.lastOrder = order.createdAt;
        map.set(email, current);
      });
      return [...map.values()].sort((a, b) => new Date(b.lastOrder) - new Date(a.lastOrder));
    }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.PedidoModel = model;
})();
