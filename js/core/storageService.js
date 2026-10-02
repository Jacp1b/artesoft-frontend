(function () {
  const store = {
    read(key, fallback) {
      try {
        const value = localStorage.getItem(key);
        return value === null ? fallback : JSON.parse(value);
      } catch (error) {
        console.warn("No se pudo leer el almacenamiento local:", error);
        return fallback;
      }
    },
    write(key, value) {
      try {
        localStorage.setItem(key, JSON.stringify(value));
        return true;
      } catch (error) {
        console.warn("No se pudo guardar el almacenamiento local:", error);
        return false;
      }
    },
    remove(key) {
      try { localStorage.removeItem(key); } catch (error) { console.warn(error); }
    }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.StorageService = store;
})();
