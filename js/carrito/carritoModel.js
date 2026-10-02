(function () {
  const KEY = "artesoft:v3:carrito";
  const storage = () => window.Artesoft.StorageService;
  function raw() { return storage().read(KEY, []); }
  function save(lines) { storage().write(KEY, lines.filter((line) => Number(line.quantity) > 0)); }
  const model = {
    list() {
      const products = window.Artesoft.ProductoModel.list();
      const lines = raw().map((line) => {
        const product = products.find((item) => item.id === line.productId);
        return product ? { ...product, quantity: Math.min(Number(line.quantity) || 1, Number(product.stock)) } : null;
      }).filter((line) => line && line.quantity > 0);
      save(lines.map(({ id, quantity }) => ({ productId: id, quantity })));
      return lines;
    },
    add(productId) {
      const product = window.Artesoft.ProductoModel.get(productId);
      if (!product || product.stock <= 0) return { ok: false, message: "Este producto no está disponible por ahora." };
      const lines = raw();
      const existing = lines.find((line) => line.productId === productId);
      if (existing) {
        if (existing.quantity >= product.stock) return { ok: false, message: "Ya tienes todas las unidades disponibles en la bolsa." };
        existing.quantity += 1;
      } else lines.push({ productId, quantity: 1 });
      save(lines);
      return { ok: true };
    },
    setQuantity(productId, quantity) {
      const product = window.Artesoft.ProductoModel.get(productId);
      const lines = raw();
      const line = lines.find((item) => item.productId === productId);
      if (!line || !product) return { ok: false };
      if (quantity < 1) return this.remove(productId), { ok: true };
      if (quantity > product.stock) return { ok: false, message: `Solo quedan ${product.stock} unidades disponibles.` };
      line.quantity = quantity;
      save(lines);
      return { ok: true };
    },
    remove(productId) { save(raw().filter((line) => line.productId !== productId)); },
    clear() { storage().remove(KEY); },
    count() { return this.list().reduce((total, line) => total + line.quantity, 0); },
    totals() {
      const subtotal = this.list().reduce((total, line) => total + Number(line.price) * line.quantity, 0);
      const shipping = subtotal === 0 || subtotal >= 120000 ? 0 : 8900;
      return { subtotal, shipping, total: subtotal + shipping, freeFrom: 120000 };
    }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.CarritoModel = model;
})();
