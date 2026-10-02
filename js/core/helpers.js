(function () {
  const money = new Intl.NumberFormat("es-CO", { style: "currency", currency: "COP", maximumFractionDigits: 0 });
  const escapeHTML = (value) => String(value ?? "").replace(/[&<>"']/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[char]));
  const date = (value) => new Intl.DateTimeFormat("es-CO", { dateStyle: "medium", timeZone: "America/Bogota" }).format(new Date(value));
  const toast = (message, type = "success") => {
    const region = document.getElementById("toastRegion");
    if (!region) return;
    const item = document.createElement("div");
    item.className = `toast${type === "error" ? " toast-error" : ""}`;
    item.textContent = message;
    region.append(item);
    window.setTimeout(() => item.remove(), 3200);
  };
  const statusClass = (status) => {
    const normal = String(status || "").toLowerCase();
    if (normal.includes("prepar")) return "status-preparacion";
    if (normal.includes("enviado")) return "status-enviado";
    if (normal.includes("entregado")) return "status-entregado";
    return "";
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.Helpers = { money: (value) => money.format(Number(value) || 0), escapeHTML, date, toast, statusClass };
})();
