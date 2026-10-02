(function () {
  const rules = {
    administrador: ["productos.ver", "productos.crear", "productos.editar", "productos.eliminar", "pedidos.ver", "pedidos.editar", "clientes.ver"],
    operador: ["productos.ver", "productos.editar", "pedidos.ver", "pedidos.editar", "clientes.ver"],
    consulta: ["productos.ver", "pedidos.ver", "clientes.ver"]
  };
  const labels = { administrador: "Administración", operador: "Operación", consulta: "Consulta" };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.Permissions = {
    can(role, action) { return (rules[role] || []).includes(action); },
    label(role) { return labels[role] || "Visitante"; }
  };
})();
