(function () {
  const initialState = window.Artesoft?.initialState || {};
  const cache = new Map();
  const browserOnlyKeys = new Set(["artesoft:v3:carrito"]);

  const api = {
    request(resource, method = "GET", data = null, id = null) {
      const params = new URLSearchParams({ resource });
      if (id !== null) params.set("id", String(id));
      const xhr = new XMLHttpRequest();
      xhr.open(method, `api/index.php?${params.toString()}`, false);
      xhr.setRequestHeader("Accept", "application/json");
      if (method !== "GET") {
        xhr.setRequestHeader("Content-Type", "application/json");
        xhr.setRequestHeader("X-CSRF-Token", window.Artesoft.csrfToken || "");
      }
      try {
        xhr.send(method === "GET" ? null : JSON.stringify(data || {}));
        const response = xhr.responseText ? JSON.parse(xhr.responseText) : {};
        if (response.csrfToken) window.Artesoft.csrfToken = response.csrfToken;
        if (xhr.status < 200 || xhr.status >= 300 || response.ok !== true) {
          return { ok: false, message: response.message || `Error HTTP ${xhr.status}`, status: xhr.status };
        }
        return response;
      } catch (error) {
        console.error("No se pudo comunicar con el servidor:", error);
        window.dispatchEvent(new CustomEvent("artesoft:storage-error", {
          detail: { message: "No se pudo comunicar con el servidor. Revisa la conexión e inténtalo de nuevo." }
        }));
        return { ok: false, message: "No se pudo comunicar con el servidor.", status: 0 };
      }
    }
  };

  const store = {
    read(key, fallback) {
      if (cache.has(key)) return JSON.parse(JSON.stringify(cache.get(key)));
      if (Object.prototype.hasOwnProperty.call(initialState, key)) {
        const value = JSON.parse(JSON.stringify(initialState[key]));
        cache.set(key, value);
        return JSON.parse(JSON.stringify(value));
      }
      if (!browserOnlyKeys.has(key)) return fallback;
      try {
        const value = localStorage.getItem(key);
        if (value === null) return fallback;
        const parsed = JSON.parse(value);
        cache.set(key, parsed);
        return JSON.parse(JSON.stringify(parsed));
      } catch (error) {
        console.error("No se pudo leer el almacenamiento local:", error);
        return fallback;
      }
    },
    write(key, value) {
      if (!browserOnlyKeys.has(key)) return false;
      try {
        localStorage.setItem(key, JSON.stringify(value));
        cache.set(key, JSON.parse(JSON.stringify(value)));
        return true;
      } catch (error) {
        console.error("No se pudo guardar el almacenamiento local:", error);
        return false;
      }
    },
    remove(key) {
      if (!browserOnlyKeys.has(key)) return;
      try {
        localStorage.removeItem(key);
        cache.delete(key);
      } catch (error) {
        console.error("No se pudo borrar el almacenamiento local:", error);
      }
    }
  };
  window.Artesoft = window.Artesoft || {};
  window.Artesoft.Api = api;
  window.Artesoft.StorageService = store;
})();
