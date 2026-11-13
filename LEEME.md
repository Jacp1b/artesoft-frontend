# ARTESOFT · Tienda web PHP y MySQL

Aplicación para Arte Didáctica S.A.S. Usa PHP para el backend y API REST, MySQL para usuarios, productos y pedidos, y JavaScript/CSS para la interfaz.

## Instalación

Requisitos: PHP 8.1 o superior con PDO MySQL y las extensiones `mbstring`, `json` y `zip` (o una herramienta ZIP compatible para Composer); MySQL 8.0 o superior; Composer.

1. Importa `database.sql` en MySQL. Crea la base `artesoft`, sus tablas, la cuenta inicial de administración y ocho cuentas de prueba.
2. Ejecuta `composer install` para instalar Dompdf, necesario para descargar los informes PDF.
3. Configura `ARTESOFT_DB_HOST`, `ARTESOFT_DB_NAME`, `ARTESOFT_DB_USER` y `ARTESOFT_DB_PASSWORD`, o modifica los valores predeterminados en `config/database.php`.
4. En la carpeta del proyecto, ejecuta `php -S 127.0.0.1:8000`.
5. Abre `http://127.0.0.1:8000/index.php`.

El acceso inicial es `admin@artesoft.local` con contraseña temporal `Demo1234!`. Cambia la contraseña inmediatamente desde **Cuentas**. Publica el sitio detrás de HTTPS y configura credenciales de base de datos propias antes de usarlo fuera de desarrollo.

Las cuentas de prueba son `admin.prueba1@artesoft.local`, `admin.prueba2@artesoft.local`, `operador.prueba1@artesoft.local`, `operador.prueba2@artesoft.local`, `consulta.prueba1@artesoft.local`, `consulta.prueba2@artesoft.local`, `cliente.prueba1@artesoft.local` y `cliente.prueba2@artesoft.local`; todas usan temporalmente `Prueba2026!`. Elimina estas cuentas o cambia sus contraseñas antes de desplegar.

La aplicación crea el catálogo y los pedidos de demostración en las tablas `products`, `orders` y `order_items` al iniciar por primera vez; estos no se consideran ventas. Para agregar ocho pedidos de prueba persistidos, etiquetados como prueba y atribuidos a los empleados de prueba, en InfinityFree u otro host sin SSH, inicia sesión como administrador, abre `https://tu-dominio/seed-test-orders.php` y pulsa **Ejecutar seeder**. Primero abre la aplicación al menos una vez para inicializar la base de datos. Al terminar, elimina `seed-test-orders.php` del hosting. También puedes ejecutarlo por CLI con `php config/seed-test-orders.php`. El script es idempotente y no modifica las existencias. Los pedidos de prueba se incluyen en las estadísticas para probar los reportes, pero el sistema los marca claramente y no representan ventas reales. Si actualizas una instalación previa, la API agrega automáticamente las columnas de atribución y clasificación de prueba.

## API REST

La API recibe y devuelve JSON, excepto las rutas de reportes, que descargan archivos PDF. Las rutas usan `api/index.php?resource=...`; las operaciones que modifican datos requieren el encabezado `X-CSRF-Token` que entrega la página.

| Recurso | Métodos | Acceso |
|---|---|---|
| `auth/me` | GET | Público: devuelve el usuario de sesión y token CSRF |
| `auth/login`, `auth/register`, `auth/logout` | POST | Login/registro públicos; logout requiere sesión |
| `products`, `products&id={id}` | GET, POST, PUT, DELETE | Lectura pública; escritura requiere sesión; borrar solo administración |
| `orders`, `orders&id={id}` | GET, POST, PUT | Pedidos propios para cliente/invitado; historial completo y cambios de estado para equipo |
| `users`, `users&id={id}` | GET, POST, PUT, DELETE | Solo administración |
| `reports/summary` | GET | Usuarios autenticados del equipo; análisis JSON |
| `reports/pdf` | GET | Usuarios autenticados del equipo; descarga de informe PDF |
| `reports/orders`, `reports/products` | GET | Usuarios autenticados del equipo; descarga PDF detallado de pedidos o inventario |

Ejemplo de URL de consulta: `api/index.php?resource=products`. El método HTTP determina la operación; para PUT y DELETE el identificador va en el parámetro `id`. Los permisos se verifican en el servidor, no solo en la interfaz.

## Funcionalidades

- Registro de clientes y login con `password_hash` / `password_verify`, cookies de sesión `HttpOnly` y `SameSite`, regeneración del identificador y protección CSRF.
- CRUD de productos con validación de campos, permisos por rol y persistencia MySQL.
- CRUD de usuarios para administración, contraseñas almacenadas como hash y protección contra eliminar o degradar la última cuenta administradora.
- Pedidos con cálculo de precios y envío en el servidor, control transaccional de existencias y consulta privada para cada cliente.
- Atribución de pedidos creados desde perfiles de administración u operación; los informes muestran ventas por empleado.
- Pedidos de prueba idempotentes, atribuidos al vendedor y cliente correspondientes; están diferenciados de demostraciones y ventas reales.
- Panel con resumen, inventario, pedidos, clientes y reportes con tendencias de seis meses, ventas por estado, clasificación del equipo y productos más vendidos.
- Informes PDF de ventas, pedidos detallados e inventario, con métricas, gráficos tabulares y datos consultados desde la base de datos.
- Carrito local del navegador; los productos, pedidos, usuarios y cambios de inventario se guardan en la base de datos.

El catálogo y los pedidos de ejemplo se inicializan de forma segura en el servidor durante la primera visita; la API no acepta escrituras públicas de inicialización. Los datos de ejemplo no deben confundirse con ventas reales.

## Estructura

- `index.php`: página inicial, inicialización de datos de ejemplo y token CSRF.
- `config/demo-data.php`: catálogo y pedidos de demostración iniciales.
- `api/index.php`: API REST, autenticación, permisos, operaciones de dominio, migraciones y reportes.
- `seed-test-orders.php`: herramienta temporal protegida para ejecutar el seeder desde un navegador.
- `api/report_template.php`: plantilla del informe PDF descargable.
- `config/database.php`: conexión PDO.
- `database.sql`: base, tablas y cuenta inicial.
- `composer.json` / `composer.lock`: dependencia de Dompdf.
- `js/`: componentes de interfaz y clientes de API.

El sistema no integra pasarelas de pago ni facturación electrónica. La cuenta de administración inicial tiene una contraseña temporal conocida; cámbiala antes de desplegar.
