(function () {
  const KEY = "artesoft:v3:productos";
  const storage = () => window.Artesoft.StorageService;
  const copy = (value) => JSON.parse(JSON.stringify(value));
  const model = {
    initialize() {
      if (storage().read(KEY, null) === null) storage().write(KEY, copy(window.Artesoft.DemoData.products));
    },
    list() { return storage().read(KEY, []); },
    get(id) { return this.list().find((item) => item.id === id) || null; },
    create(data) {
      const items = this.list();
      const product = { ...data, id: `p-${Date.now().toString(36)}`, price: Number(data.price), stock: Number(data.stock) };
      items.unshift(product);
      storage().write(KEY, items);
      return product;
    },
    update(id, data) {
      const items = this.list();
      const index = items.findIndex((item) => item.id === id);
      if (index < 0) return null;
      items[index] = { ...items[index], ...data, price: Number(data.price), stock: Number(data.stock) };
      storage().write(KEY, items);
      return items[index];
    },
    remove(id) {
      const items = this.list();
      const remaining = items.filter((item) => item.id !== id);
      if (remaining.length === items.length) return false;
      storage().write(KEY, remaining);
      return true;
    },
    existsName(name, exceptId = "") {
      const normalized = name.trim().toLocaleLowerCase("es-CO");
      return this.list().some((item) => item.id !== exceptId && item.name.trim().toLocaleLowerCase("es-CO") === normalized);
    },
    reduceStock(lines) {
      const items = this.list();
      lines.forEach((line) => {
        const product = items.find((item) => item.id === line.productId);
        if (product) product.stock = Math.max(0, Number(product.stock) - Number(line.quantity));
      });
      storage().write(KEY, items);
    }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.ProductoModel = model;
})();
