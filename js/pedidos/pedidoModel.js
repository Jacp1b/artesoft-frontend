(function () {
  const KEY = "artesoft:v3:pedidos";
  const storage = () => window.Artesoft.StorageService;
  const model = {
    initialize() {
      if (storage().read(KEY, null) === null) storage().write(KEY, JSON.parse(JSON.stringify(window.Artesoft.DemoData.orders)));
    },
    list() { return storage().read(KEY, []).slice().sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt)); },
    create(data) {
      const orders = storage().read(KEY, []);
      const order = { ...data, id: `AT-${String(Date.now()).slice(-6)}`, status: "Pedido recibido", createdAt: new Date().toISOString(), demo: false };
      orders.unshift(order);
      storage().write(KEY, orders);
      return order;
    },
    updateStatus(id, status) {
      const orders = storage().read(KEY, []);
      const order = orders.find((item) => item.id === id);
      if (!order) return false;
      order.status = status;
      storage().write(KEY, orders);
      return true;
    },
    forEmail(email) {
      const normalized = email.trim().toLocaleLowerCase("es-CO");
      return this.list().filter((order) => order.customer.email.toLocaleLowerCase("es-CO") === normalized);
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
