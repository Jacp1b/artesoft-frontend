<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#fffdf8">
  <meta name="description" content="Juegos didácticos, piñatería y detalles para celebrar. Explora el catálogo de ARTESOFT y arma tu pedido en minutos.">
  <title>ARTESOFT — Ideas para jugar, crear y celebrar</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/styles.css') }}">
</head>
<body>
  <a class="skip-link" href="#main">Saltar al contenido</a>
  <div class="announcement" role="region" aria-label="Promociones">
    <div class="announcement-track">
      <span class="announcement-copy"><span class="announcement-spark" aria-hidden="true">✳</span> Ideas para jugar, crear y celebrar <span class="announcement-divider" aria-hidden="true">·</span> Envíos en Bogotá y alrededores</span>
      <span class="announcement-copy" aria-hidden="true"><span class="announcement-spark">✳</span> Ideas para jugar, crear y celebrar <span class="announcement-divider">·</span> Envíos en Bogotá y alrededores</span>
    </div>
  </div>

  <header class="site-header">
    <div class="header-inner">
      <a class="brand" href="#inicio" data-route="store" aria-label="ARTESOFT, inicio">
        <span class="brand-mark" aria-hidden="true"><span></span><span></span><span></span><span></span></span>
        <span class="brand-word">arte<span>soft</span><small>ARTE DIDÁCTICA</small></span>
      </a>
      <nav class="main-nav" aria-label="Navegación principal">
        <a href="#inicio" data-route="store" class="nav-link is-active">Inicio</a>
        <a href="#catalogo" data-route="store" data-scroll="catalogo" class="nav-link">Catálogo</a>
        <a href="#pedidos" data-route="orders" class="nav-link">Mis pedidos</a>
      </nav>
      <div class="header-actions">
        <label class="header-search" aria-label="Buscar productos">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 4.3 4.3"></path></svg>
          <input id="headerSearch" type="search" placeholder="Buscar" autocomplete="off">
          <kbd>/</kbd>
        </label>
        <button class="button button-secondary button-small customer-auth-button" id="customerLoginButton" type="button" data-action="open-customer-auth" data-auth-mode="login">Iniciar sesión</button>
        <button class="button button-secondary button-small customer-auth-button" id="staffAdminButton" type="button" data-route="admin" hidden>Panel de gestión</button>
        <button class="button button-primary button-small customer-auth-button" id="customerRegisterButton" type="button" data-action="open-customer-auth" data-auth-mode="register">Registrarse</button>
        <button class="cart-button" type="button" data-action="open-cart" aria-label="Abrir carrito">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 10.2a2 2 0 0 0 2 1.6h8.3a2 2 0 0 0 1.9-1.4L21 8H6"></path><circle cx="9.5" cy="20" r="1.2"></circle><circle cx="18" cy="20" r="1.2"></circle></svg>
          <span>Bolsa</span><span class="cart-count" id="cartCount" aria-live="polite">0</span>
        </button>
        <button class="mobile-menu-button" type="button" data-action="toggle-menu" aria-label="Abrir menú" aria-expanded="false"><span></span><span></span></button>
      </div>
    </div>
    <div class="mobile-menu" id="mobileMenu" hidden>
      <a href="#inicio" data-route="store">Inicio</a><a href="#catalogo" data-route="store" data-scroll="catalogo">Catálogo</a><a href="#pedidos" data-route="orders">Mis pedidos</a><a href="#gestion" id="mobileStaffAdminLink" data-route="admin" hidden>Panel de gestión</a><button id="mobileCustomerLoginButton" type="button" data-action="open-customer-auth" data-auth-mode="login">Iniciar sesión</button><button id="mobileCustomerRegisterButton" type="button" data-action="open-customer-auth" data-auth-mode="register">Registrarse</button>
    </div>
  </header>

  <main id="main" tabindex="-1">
    <div id="appRoot" aria-live="polite"></div>
  </main>

  <footer class="site-footer" id="siteFooter">
    <div class="footer-main">
      <div class="footer-brand">
        <a class="brand brand-light" href="#inicio" data-route="store" aria-label="ARTESOFT, inicio">
          <span class="brand-mark" aria-hidden="true"><span></span><span></span><span></span><span></span></span>
          <span class="brand-word">arte<span>soft</span><small>ARTE DIDÁCTICA</small></span>
        </a>
        <p>Pequeñas ideas, grandes momentos.<br>Juegos, arte y detalles para compartir.</p>
      </div>
      <div class="footer-column"><strong>Explora</strong><a href="#catalogo" data-route="store" data-scroll="catalogo">Catálogo</a><a href="#pedidos" data-route="orders">Mis pedidos</a><a href="#storyTitle" data-route="store" data-scroll="storyTitle">Conoce ARTESOFT</a></div>
      <div class="footer-column"><strong>Tu compra</strong><span>Pago contra entrega</span><span>Disponibilidad visible</span><span>Atención cercana</span></div>
      <div class="footer-note"><span class="footer-flower" aria-hidden="true">✳</span><strong>Un mundo para imaginar</strong><span>Productos para crear, aprender y celebrar.</span></div>
    </div>
    <div class="footer-bottom"><span>© 2026 Arte Didáctica S.A.S. · ARTESOFT</span><span>Prototipo académico · Bogotá, Colombia</span></div>
  </footer>

  <dialog class="drawer" id="cartDialog" aria-labelledby="cartTitle">
    <div class="drawer-scrim" data-action="close-cart"></div>
    <section class="drawer-panel">
      <header class="drawer-header"><div><span class="eyebrow">TU SELECCIÓN</span><h2 id="cartTitle">Tu bolsa <span id="cartTitleCount"></span></h2></div><button class="icon-button" type="button" data-action="close-cart" aria-label="Cerrar bolsa"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"></path></svg></button></header>
      <div id="cartContents" class="drawer-content"></div>
      <div id="checkoutContents" class="drawer-content" hidden></div>
    </section>
  </dialog>

  <dialog class="modal-dialog customer-auth-dialog" id="customerAuthDialog" aria-labelledby="customerAuthTitle">
    <form method="dialog" class="modal-close-row"><button class="icon-button" aria-label="Cerrar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"></path></svg></button></form>
    <div class="modal-icon">♡</div>
    <span class="eyebrow">CUENTA ARTESOFT</span>
    <h2 id="customerAuthTitle">Inicia sesión</h2>
    <p class="modal-copy" id="customerAuthCopy">Accede a tu cuenta para tener tu experiencia de compra a la mano.</p>
    <div class="auth-mode-switch" role="group" aria-label="Tipo de acceso">
      <button class="auth-mode-button is-active" type="button" data-action="customer-auth-mode" data-auth-mode="login" aria-pressed="true">Iniciar sesión</button>
      <button class="auth-mode-button" type="button" data-action="customer-auth-mode" data-auth-mode="register" aria-pressed="false">Registrarse</button>
    </div>
    <form id="customerAuthForm" class="form-stack">
      <div class="customer-name-field" hidden>
        <label for="customerAuthName">Nombre completo</label>
        <input id="customerAuthName" name="name" type="text" autocomplete="name" minlength="3" maxlength="90">
      </div>
      <label for="customerAuthEmail">Correo electrónico</label>
      <input id="customerAuthEmail" name="email" type="email" autocomplete="email" maxlength="120" required>
      <div class="customer-registration-fields" hidden>
        <label for="customerAuthPhone">Celular</label>
        <input id="customerAuthPhone" name="phone" type="tel" autocomplete="tel" minlength="7" maxlength="20" pattern="[0-9+\(\) .\-]{7,20}">
        <label for="customerAuthCity">Ciudad o municipio</label>
        <input id="customerAuthCity" name="city" autocomplete="address-level2" minlength="2" maxlength="60">
        <label for="customerAuthAddress">Dirección de entrega</label>
        <input id="customerAuthAddress" name="address" autocomplete="street-address" minlength="5" maxlength="120">
      </div>
      <label for="customerAuthPassword">Contraseña</label>
      <input id="customerAuthPassword" name="password" type="password" autocomplete="current-password" minlength="8" maxlength="128" required>
      <p class="form-hint" id="customerAuthNotice">Administración inicial: admin@artesoft.local · contraseña temporal: Demo1234! Cámbiala después del primer acceso.</p>
      <button class="button button-primary button-wide" id="customerAuthSubmit" type="submit">Iniciar sesión <span aria-hidden="true">→</span></button>
    </form>
  </dialog>

  <dialog class="modal-dialog product-dialog" id="productDialog" aria-labelledby="productDialogTitle"></dialog>
  <dialog class="modal-dialog confirm-dialog" id="confirmDialog" aria-labelledby="confirmTitle"></dialog>
  <div class="toast-region" id="toastRegion" role="status" aria-live="polite" aria-atomic="true"></div>

  <script>
    window.Artesoft = window.Artesoft || {};
    window.Artesoft.initialState = {{ Illuminate\Support\Js::from($initialState) }};
  window.Artesoft.csrfToken = {{ Illuminate\Support\Js::from($csrfToken) }};
  </script>
  <script defer src="{{ asset('js/core/storageService.js') }}"></script>
  <script defer src="{{ asset('js/core/helpers.js') }}"></script>
  <script defer src="{{ asset('js/core/permissions.js') }}"></script>
  <script defer src="{{ asset('js/auth/userRepository.js') }}"></script>
  <script defer src="{{ asset('js/auth/authModel.js') }}"></script>
  <script defer src="{{ asset('js/auth/authView.js') }}"></script>
  <script defer src="{{ asset('js/auth/userAdminView.js') }}"></script>
  <script defer src="{{ asset('js/auth/authController.js') }}"></script>
  <script defer src="{{ asset('js/productos/productoModel.js') }}"></script>
  <script defer src="{{ asset('js/productos/productoView.js') }}"></script>
  <script defer src="{{ asset('js/productos/productoController.js') }}"></script>
  <script defer src="{{ asset('js/carrito/carritoModel.js') }}"></script>
  <script defer src="{{ asset('js/carrito/carritoView.js') }}"></script>
  <script defer src="{{ asset('js/carrito/carritoController.js') }}"></script>
  <script defer src="{{ asset('js/pedidos/pedidoModel.js') }}"></script>
  <script defer src="{{ asset('js/pedidos/pedidoView.js') }}"></script>
  <script defer src="{{ asset('js/pedidos/pedidoController.js') }}"></script>
  <script defer src="{{ asset('js/inventario/inventarioView.js') }}"></script>
  <script defer src="{{ asset('js/inventario/inventarioController.js') }}"></script>
  <script defer src="{{ asset('js/app.js') }}"></script>
</body>
</html>
