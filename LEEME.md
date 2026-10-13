# ARTESOFT · Prototipo front end

Tienda web de demostración para Arte Didáctica S.A.S. Construida con HTML5, CSS3 y JavaScript sin dependencias de framework.

## Abrir

Abre `index.html` en un navegador moderno. La interfaz carga una imagen editorial desde Unsplash y las fuentes DM Sans y Manrope desde Google Fonts; el resto de la interfaz y sus ilustraciones son locales.

## Flujos incluidos

- Catálogo con búsqueda, categorías y ordenamiento.
- Bolsa con cantidades, control de existencias, envío estimado y totales.
- Confirmación de pedidos con pago contra entrega, validación de datos y seguimiento por correo.
- Panel de demostración con resumen, pedidos, clientes, inventario y cuentas del equipo.
- Alta, edición y eliminación confirmada de productos según el rol.
- Roles de demostración: administración, ventas/operación y consulta.
- Inicio de sesión y registro de clientes con cuentas guardadas en el almacenamiento local.
- Cuentas de prueba para administración, ventas/operación, consulta y clientes.
- Creación de cuentas internas y asignación de perfiles de trabajo desde el panel de administración.
- Checkout que reutiliza los datos de entrega de los clientes con sesión iniciada.
- Persistencia local para catálogo, bolsa, pedidos, cuentas y sesión.

## Productos e imágenes

El catálogo inicial incluye una selección de 12 referencias de los catálogos enviados: juegos didácticos, piñatería y decoración para fiestas. Las fotos vienen de los PDF y los precios se conservaron como **precios mayoristas en COP**. Las cantidades disponibles y los pedidos precargados son datos de demostración y se deben reemplazar por el inventario real.

Las fotos recortadas están en `assets/products/`. Para agregar o cambiar una imagen desde el panel:

1. Copia la foto a `assets/products/`.
2. Inicia sesión con una cuenta del equipo, abre **Inventario**, crea o edita el producto y escribe una ruta como `assets/products/mi-producto.jpg` en **Foto del producto**.
3. Guarda los cambios. La ruta se conserva en el navegador de demostración.

El logotipo ARTESOFT todavía es un rótulo de texto hecho con HTML y CSS. Los catálogos traen marcas del proveedor, no un archivo independiente del logo ARTESOFT. Cuando tengas el logo en PNG o SVG, colócalo en `assets/` y reemplaza el bloque `.brand` del encabezado y del pie en `index.html`.

## Arquitectura

Los módulos se organizan primero por funcionalidad y luego por MVC, siguiendo la guía ADSO:

```text
js/
├── auth/             repositorio local de usuarios y autenticación de demostración
├── carrito/          Model, View y Controller de la bolsa
├── core/             persistencia, permisos y utilidades
├── inventario/       View y Controller del CRUD de productos
├── pedidos/          Model y View de pedidos, clientes y estados
├── productos/        Model, View y Controller del catálogo
└── services/         datos de ejemplo
```

`StorageService` centraliza el acceso a `localStorage`. Los datos usan claves versionadas (`artesoft:v1:` y `artesoft:v3:`). Las cuentas y la sesión se conservan en el navegador.

## Acceso al panel

Inicia sesión desde el encabezado con una de estas cuentas de prueba (contraseña para todas: `Demo1234!`):

| Perfil | Correo |
|---|---|
| Administración | `admin@artesoft.local` |
| Ventas / operación | `ventas@artesoft.local` |
| Consulta | `consulta@artesoft.local` |
| Cliente | `laura@example.com` |

- **Administración:** crea, edita y elimina productos; actualiza pedidos.
- **Ventas / operación:** edita existencias y actualiza pedidos.
- **Consulta:** revisa productos, pedidos y clientes.
- **Cuentas:** solo administración puede crear cuentas internas y asignar los perfiles de administración, ventas/operación o consulta.

El registro público siempre crea una cuenta de cliente e incluye sus datos de entrega. Al confirmar una compra con sesión iniciada, esos datos se reutilizan y no se solicitan de nuevo. El historial de pedidos se consulta desde **Mis pedidos** con el correo del pedido; el correo de ejemplo para revisar pedidos precargados es `laura@example.com`.

## Límites de esta entrega

Los precios de los productos del catálogo corresponden a los valores mayoristas que aparecen en los PDF. Las existencias y los pedidos de ejemplo son ilustrativos. `localStorage` guarda los cambios solo en el navegador actual: no sincroniza dispositivos ni reemplaza una base de datos. La autenticación y el control de roles son únicamente demostrativos: las contraseñas se guardan localmente en texto y no deben ser reales; no hay un backend que proteja los datos. Este prototipo no implementa una pasarela de pagos ni facturación electrónica. No ingreses datos reales de clientes.
