# ARTESOFT · Tienda web PHP y MySQL

Aplicación para Arte Didáctica S.A.S. Usa PHP para el backend y API REST, MySQL para usuarios, productos y pedidos, y JavaScript/CSS para la interfaz.

## Instalación

Requisitos: PHP 8.1 o superior con PDO MySQL y las extensiones `mbstring` y `json`; MySQL 8.0 o superior.

1. Importa `database.sql` en MySQL. Crea la base `artesoft`, sus tablas y una cuenta inicial de administración.
2. Configura `ARTESOFT_DB_HOST`, `ARTESOFT_DB_NAME`, `ARTESOFT_DB_USER` y `ARTESOFT_DB_PASSWORD`, o modifica los valores predeterminados en `config/database.php`.
3. En la carpeta del proyecto, ejecuta `php -S 127.0.0.1:8000`.
4. Abre `http://127.0.0.1:8000/index.php`.

El acceso inicial es `admin@artesoft.local` con contraseña temporal `Demo1234!`. Cambia la contraseña inmediatamente desde **Cuentas**. Publica el sitio detrás de HTTPS y configura credenciales de base de datos propias antes de usarlo fuera de desarrollo.

## API REST

La API recibe y devuelve JSON (excepto los reportes CSV). Las rutas usan `api/index.php?resource=...`; las operaciones que modifican datos requieren el encabezado `X-CSRF-Token` que entrega la página.

| Recurso | Métodos | Acceso |
|---|---|---|
| `auth/me` | GET | Público: devuelve el usuario de sesión y token CSRF |
| `auth/login`, `auth/register`, `auth/logout` | POST | Login/registro públicos; logout requiere sesión |
| `products`, `products&id={id}` | GET, POST, PUT, DELETE | Lectura pública; escritura requiere sesión; borrar solo administración |
| `orders`, `orders&id={id}` | GET, POST, PUT | Pedidos propios para cliente/invitado; historial completo y cambios de estado para equipo |
| `users`, `users&id={id}` | GET, POST, PUT, DELETE | Solo administración |
| `reports/orders`, `reports/products` | GET | Usuarios autenticados del equipo; descarga CSV |

Ejemplo de URL de consulta: `api/index.php?resource=products`. El método HTTP determina la operación; para PUT y DELETE el identificador va en el parámetro `id`. Los permisos se verifican en el servidor, no solo en la interfaz.

## Funcionalidades

- Registro de clientes y login con `password_hash` / `password_verify`, cookies de sesión `HttpOnly` y `SameSite`, regeneración del identificador y protección CSRF.
- CRUD de productos con validación de campos, permisos por rol y persistencia MySQL.
- CRUD de usuarios para administración, contraseñas almacenadas como hash y protección contra eliminar o degradar la última cuenta administradora.
- Pedidos con cálculo de precios y envío en el servidor, control transaccional de existencias y consulta privada para cada cliente.
- Panel con resumen, inventario, pedidos, clientes y administración de cuentas.
- Exportación de reportes de pedidos e inventario en CSV desde el panel.
- Carrito local del navegador; los productos, pedidos, usuarios y cambios de inventario se guardan en la base de datos.

El catálogo y los pedidos de ejemplo se inicializan de forma segura en el servidor durante la primera visita; la API no acepta escrituras públicas de inicialización. Los datos de ejemplo no deben confundirse con ventas reales.

## Estructura

- `index.php`: página inicial, inicialización de datos de ejemplo y token CSRF.
- `config/demo-data.php`: catálogo y pedidos de demostración iniciales.
- `api/index.php`: API REST, autenticación, permisos, operaciones de dominio y reportes.
- `config/database.php`: conexión PDO.
- `database.sql`: base, tablas y cuenta inicial.
- `js/`: componentes de interfaz y clientes de API.

El sistema no integra pasarelas de pago ni facturación electrónica. La cuenta de administración inicial tiene una contraseña temporal conocida; cámbiala antes de desplegar.
