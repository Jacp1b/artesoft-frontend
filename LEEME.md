# ARTESOFT · Prototipo front end

Tienda web de demostración para Arte Didáctica S.A.S. Construida con HTML5, CSS3 y JavaScript sin dependencias de framework.

## Estructura del repositorio

Este monorepo contiene el prototipo web en la raíz y la aplicación Laravel, su API y el proyecto Android en `laravel_app/`. Consulta `laravel_app/LEEME.md` para configurar Laravel y `laravel_app/mobile/README.md` para ejecutar la aplicación móvil.

## Abrir

Abre `index.html` en un navegador moderno. La interfaz carga una imagen editorial desde Unsplash y las fuentes DM Sans y Manrope desde Google Fonts; el resto de la interfaz y sus ilustraciones son locales.

## Flujos incluidos

- Catálogo con búsqueda, categorías y ordenamiento.
- Bolsa con cantidades, control de existencias, envío estimado y totales.
- Confirmación de pedidos con pago contra entrega, validación de datos y seguimiento por correo.
- Panel de demostración con resumen, pedidos, clientes e inventario.
- Alta, edición y eliminación confirmada de productos según el rol.
- Roles de demostración: administración, operación y consulta.
- Persistencia local para catálogo, bolsa y pedidos.

## Productos e imágenes

El catálogo inicial incluye una selección de 12 referencias de los catálogos enviados: juegos didácticos, piñatería y decoración para fiestas. Las fotos vienen de los PDF y los precios se conservaron como **precios mayoristas en COP**. Las cantidades disponibles y los pedidos precargados son datos de demostración y se deben reemplazar por el inventario real.

Las fotos recortadas están en `assets/products/`. Para agregar o cambiar una imagen desde el panel:

1. Copia la foto a `assets/products/`.
2. Entra al **Espacio del equipo → Inventario**, crea o edita el producto y escribe una ruta como `assets/products/mi-producto.jpg` en **Foto del producto**.
3. Guarda los cambios. La ruta se conserva en el navegador de demostración.

El logotipo ARTESOFT todavía es un rótulo de texto hecho con HTML y CSS. Los catálogos traen marcas del proveedor, no un archivo independiente del logo ARTESOFT. Cuando tengas el logo en PNG o SVG, colócalo en `assets/` y reemplaza el bloque `.brand` del encabezado y del pie en `index.html`.

## Arquitectura

Los módulos se organizan primero por funcionalidad y luego por MVC, siguiendo la guía ADSO:

```text
js/
├── auth/             sesión de demostración y selector de rol
├── carrito/          Model, View y Controller de la bolsa
├── core/             persistencia, permisos y utilidades
├── inventario/       View y Controller del CRUD de productos
├── pedidos/          Model y View de pedidos, clientes y estados
├── productos/        Model, View y Controller del catálogo
└── services/         datos de ejemplo
```

`StorageService` centraliza el acceso a `localStorage`. Las claves actuales usan el prefijo `artesoft:v3:`. El rol de demostración se conserva solo durante la pestaña mediante `sessionStorage`.

## Acceso al panel

Usa el icono de personas en el encabezado (o “Espacio del equipo” en el menú móvil), elige un rol y entra. No hay contraseña de prueba.

- **Administración:** crea, edita y elimina productos; actualiza pedidos.
- **Operación:** edita existencias y actualiza pedidos.
- **Consulta:** revisa productos, pedidos y clientes.

El historial público se consulta desde **Mis pedidos** con el correo del checkout. Correo de ejemplo para revisar pedidos precargados: `laura@example.com`.

## Límites de esta entrega

Los precios de los productos del catálogo corresponden a los valores mayoristas que aparecen en los PDF. Las existencias y los pedidos de ejemplo son ilustrativos. `localStorage` guarda los cambios solo en el navegador actual: no sincroniza dispositivos ni reemplaza una base de datos. El selector de rol es una sesión simulada; este prototipo no implementa autenticación segura, pasarela de pagos ni facturación electrónica. No ingreses datos reales de clientes.
