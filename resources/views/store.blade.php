<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
    'path' => '/',
]);
session_start();
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
header('Cache-Control: no-store');

try {
    $database = artesoft_database();
    $driver = $database->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $database->exec("INSERT OR IGNORE INTO app_state (state_key, payload) VALUES ('artesoft:v4:relational-initialized', '[\"pending\"]')");
    } else {
        $database->exec("INSERT IGNORE INTO app_state (state_key, payload) VALUES ('artesoft:v4:relational-initialized', JSON_ARRAY('pending'))");
    }
    $database->beginTransaction();
    try {
        $lockSuffix = $driver === 'sqlite' ? '' : ' FOR UPDATE';
        $marker = $database->query("SELECT payload FROM app_state WHERE state_key = 'artesoft:v4:relational-initialized'{$lockSuffix}")->fetchColumn();
        $initialized = $marker !== false && json_decode((string) $marker, true) === ['done'];
        if (!$initialized) {
            $legacy = $database->prepare("SELECT state_key, payload FROM app_state WHERE state_key IN ('artesoft:v3:productos', 'artesoft:v3:pedidos')");
            $legacy->execute();
            $legacyData = [];
            foreach ($legacy->fetchAll() as $row) {
                $legacyData[$row['state_key']] = json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR);
            }
            $demoData = require __DIR__ . '/config/demo-data.php';
            $productCount = (int) $database->query('SELECT COUNT(*) FROM products')->fetchColumn();
            $sourceProducts = $productCount > 0
                ? []
                : (($legacyData['artesoft:v3:productos'] ?? []) ?: $demoData['products']);
            $insertProduct = $database->prepare('INSERT INTO products (product_id, name, category, price, stock, description, badge, image, color, icon) VALUES (:id, :name, :category, :price, :stock, :description, :badge, :image, :color, :icon)');
            foreach ($sourceProducts as $product) {
                $insertProduct->execute([
                    'id' => (string) $product['id'], 'name' => (string) $product['name'], 'category' => (string) $product['category'],
                    'price' => (int) $product['price'], 'stock' => (int) $product['stock'], 'description' => (string) ($product['description'] ?? ''),
                    'badge' => (string) ($product['badge'] ?? ''), 'image' => (string) ($product['image'] ?? ''),
                    'color' => (string) ($product['color'] ?? 'lilac'), 'icon' => (string) ($product['icon'] ?? '🎨'),
                ]);
            }

            $orderCount = (int) $database->query('SELECT COUNT(*) FROM orders')->fetchColumn();
            $sourceOrders = $orderCount > 0
                ? []
                : (($legacyData['artesoft:v3:pedidos'] ?? []) ?: $demoData['orders']);
            $knownProducts = array_fill_keys(array_map('strval', $database->query('SELECT product_id FROM products')->fetchAll(PDO::FETCH_COLUMN)), true);
            $knownUsers = array_fill_keys(array_map('strval', $database->query('SELECT id FROM users')->fetchAll(PDO::FETCH_COLUMN)), true);
            $insertOrder = $database->prepare('INSERT INTO orders (order_id, customer_user_id, guest_token_hash, customer_name, customer_email, customer_phone, customer_city, customer_address, customer_notes, subtotal, shipping, total, status, payment, is_demo, created_at) VALUES (:id, :user_id, :guest_hash, :name, :email, :phone, :city, :address, :notes, :subtotal, :shipping, :total, :status, :payment, :is_demo, :created_at)');
            $insertItem = $database->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity) VALUES (:order_id, :product_id, :name, :price, :quantity)');
            foreach ($sourceOrders as $order) {
                $customer = $order['customer'] ?? [];
                $date = new DateTimeImmutable((string) ($order['createdAt'] ?? 'now'));
                $legacyUserId = isset($order['userId']) ? (string) $order['userId'] : '';
                $insertOrder->execute([
                    'id' => (string) $order['id'], 'user_id' => isset($knownUsers[$legacyUserId]) ? (int) $legacyUserId : null,
                    'guest_hash' => isset($order['guestToken']) ? hash('sha256', (string) $order['guestToken']) : null,
                    'name' => (string) ($customer['name'] ?? ''), 'email' => (string) ($customer['email'] ?? ''),
                    'phone' => (string) ($customer['phone'] ?? ''), 'city' => (string) ($customer['city'] ?? ''),
                    'address' => (string) ($customer['address'] ?? ''), 'notes' => (string) ($customer['notes'] ?? ''),
                    'subtotal' => (int) ($order['subtotal'] ?? 0), 'shipping' => (int) ($order['shipping'] ?? 0),
                    'total' => (int) ($order['total'] ?? 0), 'status' => (string) ($order['status'] ?? 'Pedido recibido'),
                    'payment' => (string) ($order['payment'] ?? 'Pago contra entrega'), 'is_demo' => !empty($order['demo']) ? 1 : 0,
                    'created_at' => $date->format('Y-m-d H:i:s'),
                ]);
                foreach ($order['items'] ?? [] as $item) {
                    $insertItem->execute([
                        'order_id' => (string) $order['id'],
                        'product_id' => isset($knownProducts[(string) ($item['productId'] ?? '')]) ? (string) $item['productId'] : null,
                        'name' => (string) ($item['name'] ?? ''), 'price' => (int) ($item['price'] ?? 0),
                        'quantity' => (int) ($item['quantity'] ?? 0),
                    ]);
                }
            }
            $done = json_encode(['done']);
            $updateMarker = $database->prepare("UPDATE app_state SET payload = :payload WHERE state_key = 'artesoft:v4:relational-initialized'");
            $updateMarker->execute(['payload' => $done]);
        }
        $database->commit();
    } catch (Throwable $error) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        throw $error;
    }

    $initialState = [
        'artesoft:v3:productos' => array_map(static fn(array $row): array => [
            'id' => $row['product_id'], 'name' => $row['name'], 'category' => $row['category'],
            'price' => (int) $row['price'], 'stock' => (int) $row['stock'], 'description' => $row['description'],
            'badge' => $row['badge'], 'image' => $row['image'], 'color' => $row['color'], 'icon' => $row['icon'],
        ], $database->query('SELECT product_id, name, category, price, stock, description, badge, image, color, icon FROM products ORDER BY created_at, product_id')->fetchAll()),
        'artesoft:v3:pedidos' => [],
    ];
} catch (Throwable $error) {
    error_log('ARTESOFT database bootstrap failed: ' . $error->getMessage());
    http_response_code(500);
    ?>
    <!doctype html>
    <html lang="es">
    <head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>ARTESOFT · Configuración requerida</title></head>
    <body>
      <main>
        <h1>No se pudo conectar con la base de datos</h1>
        <p>Importa <code>database.sql</code> en MySQL y revisa los datos de conexión en <code>config/database.php</code>.</p>
      </main>
    </body>
    </html>
    <?php
    exit;
}
?>
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
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
  <a class="skip-link" href="#main">Saltar al contenido</a>
  <div class="announcement"><span class="announcement-spark">✳</span> Ideas para jugar, crear y celebrar <span class="announcement-divider">·</span> Envíos en Bogotá y alrededores</div>

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
        <button class="button button-primary button-small customer-auth-button" id="customerRegisterButton" type="button" data-action="open-customer-auth" data-auth-mode="register">Registrarse</button>
        <button class="cart-button" type="button" data-action="open-cart" aria-label="Abrir carrito">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 10.2a2 2 0 0 0 2 1.6h8.3a2 2 0 0 0 1.9-1.4L21 8H6"></path><circle cx="9.5" cy="20" r="1.2"></circle><circle cx="18" cy="20" r="1.2"></circle></svg>
          <span>Bolsa</span><span class="cart-count" id="cartCount" aria-live="polite">0</span>
        </button>
        <button class="mobile-menu-button" type="button" data-action="toggle-menu" aria-label="Abrir menú" aria-expanded="false"><span></span><span></span></button>
      </div>
    </div>
    <div class="mobile-menu" id="mobileMenu" hidden>
      <a href="#inicio" data-route="store">Inicio</a><a href="#catalogo" data-route="store" data-scroll="catalogo">Catálogo</a><a href="#pedidos" data-route="orders">Mis pedidos</a><button id="mobileCustomerLoginButton" type="button" data-action="open-customer-auth" data-auth-mode="login">Iniciar sesión</button><button id="mobileCustomerRegisterButton" type="button" data-action="open-customer-auth" data-auth-mode="register">Registrarse</button>
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
      <section class="test-login-options" id="testLoginOptions" aria-label="Cuentas de prueba">
        <strong>Acceso rápido a perfiles de prueba</strong>
        <p>Contraseña compartida: <code>Prueba2026!</code>. Selecciona un perfil para completar el formulario.</p>
        <div class="test-login-grid">
          <button type="button" class="test-login-option" data-action="fill-test-login" data-test-email="admin.prueba1@artesoft.local"><strong>Administración</strong><span>admin.prueba1@artesoft.local</span></button>
          <button type="button" class="test-login-option" data-action="fill-test-login" data-test-email="operador.prueba1@artesoft.local"><strong>Operación</strong><span>operador.prueba1@artesoft.local</span></button>
          <button type="button" class="test-login-option" data-action="fill-test-login" data-test-email="consulta.prueba1@artesoft.local"><strong>Consulta</strong><span>consulta.prueba1@artesoft.local</span></button>
          <button type="button" class="test-login-option" data-action="fill-test-login" data-test-email="cliente.prueba1@artesoft.local"><strong>Cliente</strong><span>cliente.prueba1@artesoft.local</span></button>
        </div>
      </section>
      <button class="button button-primary button-wide" id="customerAuthSubmit" type="submit">Iniciar sesión <span aria-hidden="true">→</span></button>
    </form>
  </dialog>

  <dialog class="modal-dialog product-dialog" id="productDialog" aria-labelledby="productDialogTitle"></dialog>
  <dialog class="modal-dialog confirm-dialog" id="confirmDialog" aria-labelledby="confirmTitle"></dialog>
  <div class="toast-region" id="toastRegion" role="status" aria-live="polite" aria-atomic="true"></div>

  <script>
    window.Artesoft = window.Artesoft || {};
    window.Artesoft.initialState = <?= json_encode($initialState, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
  window.Artesoft.csrfToken = <?= json_encode($_SESSION['csrf_token'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
  </script>
  <script defer src="js/core/storageService.js"></script>
  <script defer src="js/core/helpers.js"></script>
  <script defer src="js/core/permissions.js"></script>
  <script defer src="js/auth/userRepository.js"></script>
  <script defer src="js/auth/authModel.js"></script>
  <script defer src="js/auth/authView.js"></script>
  <script defer src="js/auth/userAdminView.js"></script>
  <script defer src="js/auth/authController.js"></script>
  <script defer src="js/productos/productoModel.js"></script>
  <script defer src="js/productos/productoView.js"></script>
  <script defer src="js/productos/productoController.js"></script>
  <script defer src="js/carrito/carritoModel.js"></script>
  <script defer src="js/carrito/carritoView.js"></script>
  <script defer src="js/carrito/carritoController.js"></script>
  <script defer src="js/pedidos/pedidoModel.js"></script>
  <script defer src="js/pedidos/pedidoView.js"></script>
  <script defer src="js/pedidos/pedidoController.js"></script>
  <script defer src="js/inventario/inventarioView.js"></script>
  <script defer src="js/inventario/inventarioController.js"></script>
  <script defer src="js/app.js"></script>
</body>
</html>
