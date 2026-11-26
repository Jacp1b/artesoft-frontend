# Flujo principal de datos

1. El navegador solicita `GET /`. La ruta de Laravel en `routes/web.php` dirige la petición a `StoreController`.
2. `StoreController` consulta los productos en MySQL y entrega la vista Blade con el estado inicial y el token CSRF.
3. La aplicación JavaScript del navegador inicializa sus controladores y modelos. Para consultar o modificar datos, estos usan `StorageService` para enviar solicitudes a `api/index.php?resource=...`.
4. Laravel enruta la solicitud a `ApiController`, que valida los datos, la sesión, los permisos y el token CSRF cuando corresponde.
5. `ApiController` lee o modifica los productos, pedidos y usuarios en MySQL mediante el query builder de Laravel.
6. La API devuelve JSON; el frontend lo procesa y actualiza la vista. El carrito, en cambio, se conserva en `localStorage` del navegador.

```text
Carga inicial
Navegador -- GET / --> Router --> StoreController --> MySQL
Navegador <-- HTML Blade (estado inicial + CSRF) <-- StoreController

Solicitudes de datos
Vista/acción JS --> Controlador/Modelo --> StorageService
    --> api/index.php?resource=... --> Router --> ApiController
    --> Query Builder de Laravel --> MySQL
Vista/DOM <-- Controlador/Modelo <-- StorageService <-- JSON <-- ApiController
```
