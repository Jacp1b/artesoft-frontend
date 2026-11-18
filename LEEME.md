# ARTESOFT · Tienda web con Laravel

Aplicación para Arte Didáctica S.A.S. Usa Laravel para el backend y la API REST, MySQL para usuarios, productos y pedidos, y conserva la interfaz JavaScript/CSS existente.

## Instalación

Requisitos: PHP 8.3 o superior con PDO MySQL, `mbstring`, `json` y `zip`; MySQL 8.0 o superior; Composer.

1. Instala las dependencias con `composer install`.
2. Copia `.env.example` a `.env` y configura `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD`. Crea la base de datos indicada en `DB_DATABASE`.
3. Genera la clave de la aplicación con `php artisan key:generate`.
4. Crea el esquema y los datos iniciales con `php artisan migrate --seed`.
5. Inicia el servidor de desarrollo con `php artisan serve --host=127.0.0.1 --port=8000` y abre `http://127.0.0.1:8000`.

El acceso inicial es `admin@artesoft.local` con contraseña temporal `Demo1234!`. En una instalación nueva puedes definirla con `ARTESOFT_ADMIN_PASSWORD` antes de ejecutar el seeder. Cámbiala inmediatamente desde **Cuentas**. Publica el sitio con el directorio `public` como raíz web, habilita HTTPS, configura `SESSION_SECURE_COOKIE=true` y credenciales propias antes de desplegar.

Las cuentas de prueba son `admin.prueba1@artesoft.local`, `admin.prueba2@artesoft.local`, `operador.prueba1@artesoft.local`, `operador.prueba2@artesoft.local`, `consulta.prueba1@artesoft.local`, `consulta.prueba2@artesoft.local`, `cliente.prueba1@artesoft.local` y `cliente.prueba2@artesoft.local`; todas usan temporalmente `Prueba2026!`. Elimina estas cuentas o cambia sus contraseñas antes de desplegar.

El seeder instala el catálogo y los pedidos de demostración, que no se consideran ventas. Para crear ocho pedidos de prueba persistidos, etiquetados y atribuidos a empleados, ejecuta `php artisan artesoft:seed-test-orders`. El comando es idempotente y no modifica las existencias. Los pedidos de prueba aparecen identificados en los informes y no representan ventas reales. Las migraciones reconocen las tablas existentes y agregan las columnas de atribución y clasificación de prueba que falten.

## API REST

La API recibe y devuelve JSON, excepto los informes, que descargan archivos PDF. Se conserva el formato de URL y las respuestas que usa la interfaz existente: `api/index.php?resource=...`. Las operaciones que modifican datos requieren la sesión y el encabezado `X-CSRF-Token` entregado por la página.

| Recurso | Métodos | Acceso |
|---|---|---|
| `auth/me` | GET | Público: devuelve el usuario de sesión y token CSRF |
| `auth/login`, `auth/register`, `auth/logout` | POST | Login/registro públicos; logout requiere sesión |
| `products`, `products&id={id}` | GET, POST, PUT, DELETE | Lectura pública; escritura para administración/operación; borrar solo administración |
| `orders`, `orders&id={id}` | GET, POST, PUT | Pedidos propios para cliente/invitado; historial completo y cambios de estado para el equipo |
| `users`, `users&id={id}` | GET, POST, PUT, DELETE | Solo administración |
| `reports/summary` | GET | Equipo autenticado: análisis JSON |
| `reports/pdf` | GET | Equipo autenticado: informe general en PDF |
| `reports/orders`, `reports/products` | GET | Equipo autenticado: informes PDF de pedidos o inventario |

El método HTTP determina la operación; para PUT y DELETE, el identificador va en el parámetro `id`. Los permisos se validan en el servidor.

## Funcionalidades

- Registro y acceso con contraseñas hasheadas, sesiones Laravel y protección CSRF.
- CRUD de productos y usuarios con validación y permisos por rol; no se puede eliminar ni degradar la última cuenta administradora.
- Pedidos con precios y envío calculados por el servidor, transacciones de inventario y consulta privada para cada cliente. Administración y operación pueden abrir la tienda desde su panel para registrar pedidos atribuidos a su cuenta.
- Atribución de pedidos creados desde perfiles de administración u operación e informes de rendimiento del equipo.
- Panel con inventario, pedidos, clientes y reportes con tendencias, estados y productos más vendidos.
- Informes PDF de ventas, pedidos e inventario.
- Carrito local en el navegador; productos, pedidos, usuarios y existencias se guardan en MySQL.

El sistema no integra pasarelas de pago ni facturación electrónica. No publiques `.env`; cambia las credenciales temporales de administración y de prueba antes del despliegue.

## Estructura

- `artisan`, `bootstrap/` y `routes/`: entrada y configuración de Laravel, rutas web/API y comandos.
- `app/Http/Controllers/`: storefront y API.
- `database/migrations/` y `database/seeders/`: esquema, compatibilidad con bases previas y datos de demostración.
- `resources/views/store.blade.php`: interfaz conservada, servida por Laravel.
- `public/js/`, `public/css/` y `public/assets/`: recursos estáticos del storefront.
- `api/report_template.php`: plantilla del informe PDF.
- `config/demo-data.php`: catálogo y pedidos de demostración iniciales.
- `composer.json` / `composer.lock`: Laravel y Dompdf.
