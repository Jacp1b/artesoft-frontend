<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

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

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > 1_000_000) {
        respond(413, ['ok' => false, 'message' => 'La solicitud excede el tamaño permitido.']);
    }
    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        respond(400, ['ok' => false, 'message' => 'El cuerpo JSON no es válido.']);
    }
    if (!is_array($data)) {
        respond(400, ['ok' => false, 'message' => 'El cuerpo JSON debe ser un objeto.']);
    }
    return $data;
}

function database(): PDO
{
    return artesoft_database();
}

function ensure_report_schema(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $columns = $pdo->query('PRAGMA table_info(orders)')->fetchAll();
        $existing = [];
        foreach ($columns as $column) {
            $existing[$column['name']] = true;
        }
        if (!isset($existing['sold_by_user_id'])) {
            $pdo->exec('ALTER TABLE orders ADD COLUMN sold_by_user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL');
        }
        if (!isset($existing['is_test'])) {
            $pdo->exec('ALTER TABLE orders ADD COLUMN is_test INTEGER NOT NULL DEFAULT 0');
        }
        $pdo->exec('CREATE INDEX IF NOT EXISTS orders_seller_index ON orders (sold_by_user_id)');
        return;
    }

    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $statement->execute(['orders', 'sold_by_user_id']);
    if ((int) $statement->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN sold_by_user_id BIGINT UNSIGNED NULL, ADD KEY orders_seller_index (sold_by_user_id), ADD CONSTRAINT orders_seller_fk FOREIGN KEY (sold_by_user_id) REFERENCES users (id) ON DELETE SET NULL');
    }
    $statement->execute(['orders', 'is_test']);
    if ((int) $statement->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN is_test BOOLEAN NOT NULL DEFAULT FALSE');
    }
}

function current_user(): ?array
{
    static $loaded = false;
    static $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id) && !ctype_digit((string) $id)) {
        return null;
    }
    $statement = database()->prepare('SELECT id, name, email, role, phone, city, address FROM users WHERE id = :id');
    $statement->execute(['id' => (int) $id]);
    $user = $statement->fetch() ?: null;
    if ($user === null) {
        unset($_SESSION['user_id']);
    }
    return $user;
}

function require_user(array $roles = []): array
{
    $user = current_user();
    if ($user === null) {
        respond(401, ['ok' => false, 'message' => 'Inicia sesión para continuar.']);
    }
    if ($roles !== [] && !in_array($user['role'], $roles, true)) {
        respond(403, ['ok' => false, 'message' => 'No tienes permiso para realizar esta acción.']);
    }
    return $user;
}

function require_csrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        respond(403, ['ok' => false, 'message' => 'La sesión expiró o la solicitud no es válida. Recarga la página.']);
    }
}

function public_user(array $user): array
{
    unset($user['password_hash']);
    return $user;
}

function products_list(PDO $pdo): array
{
    $rows = $pdo->query('SELECT product_id, name, category, price, stock, description, badge, image, color, icon FROM products ORDER BY created_at, product_id')->fetchAll();
    return array_map(static function (array $row): array {
        return [
            'id' => $row['product_id'], 'name' => $row['name'], 'category' => $row['category'],
            'price' => (int) $row['price'], 'stock' => (int) $row['stock'], 'description' => $row['description'],
            'badge' => $row['badge'], 'image' => $row['image'], 'color' => $row['color'], 'icon' => $row['icon'],
        ];
    }, $rows);
}

function find_product(PDO $pdo, string $id, bool $lock = false): ?array
{
    $suffix = $lock && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite' ? ' FOR UPDATE' : '';
    $statement = $pdo->prepare('SELECT product_id, name, category, price, stock, description, badge, image, color, icon FROM products WHERE product_id = :id' . $suffix);
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    if (!$row) {
        return null;
    }
    return [
        'id' => $row['product_id'], 'name' => $row['name'], 'category' => $row['category'],
        'price' => (int) $row['price'], 'stock' => (int) $row['stock'], 'description' => $row['description'],
        'badge' => $row['badge'], 'image' => $row['image'], 'color' => $row['color'], 'icon' => $row['icon'],
    ];
}

function save_product(PDO $pdo, string $id, array $product, bool $insert): void
{
    if ($insert) {
        $sql = 'INSERT INTO products (product_id, name, category, price, stock, description, badge, image, color, icon)
                VALUES (:id, :name, :category, :price, :stock, :description, :badge, :image, :color, :icon)';
    } else {
        $sql = 'UPDATE products SET name = :name, category = :category, price = :price, stock = :stock,
                description = :description, badge = :badge, image = :image, color = :color, icon = :icon
                WHERE product_id = :id';
    }
    $statement = $pdo->prepare($sql);
    $statement->execute([
        'id' => $id, 'name' => $product['name'], 'category' => $product['category'],
        'price' => $product['price'], 'stock' => $product['stock'], 'description' => $product['description'],
        'badge' => $product['badge'], 'image' => $product['image'], 'color' => $product['color'], 'icon' => $product['icon'],
    ]);
}

function order_items(PDO $pdo, string $orderId): array
{
    $statement = $pdo->prepare('SELECT product_id, product_name, unit_price, quantity FROM order_items WHERE order_id = :id ORDER BY id');
    $statement->execute(['id' => $orderId]);
    return array_map(static fn(array $item): array => [
        'productId' => $item['product_id'], 'name' => $item['product_name'],
        'price' => (int) $item['unit_price'], 'quantity' => (int) $item['quantity'],
    ], $statement->fetchAll());
}

function order_record(PDO $pdo, array $row): array
{
    return [
        'id' => $row['order_id'],
        'customer' => [
            'name' => $row['customer_name'], 'email' => $row['customer_email'], 'phone' => $row['customer_phone'],
            'city' => $row['customer_city'], 'address' => $row['customer_address'], 'notes' => $row['customer_notes'],
        ],
        'items' => order_items($pdo, $row['order_id']),
        'subtotal' => (int) $row['subtotal'], 'shipping' => (int) $row['shipping'], 'total' => (int) $row['total'],
        'status' => $row['status'], 'payment' => $row['payment'],
        'seller' => isset($row['seller_user_id']) && $row['seller_user_id'] !== null
            ? ['id' => (int) $row['seller_user_id'], 'name' => (string) $row['seller_name']]
            : null,
        'createdAt' => date(DATE_ATOM, strtotime($row['created_at'])),
        'demo' => (bool) $row['is_demo'],
        'test' => (bool) ($row['is_test'] ?? false),
    ];
}

function orders_list(PDO $pdo, ?array $user, ?string $guestToken, ?string $orderId = null): array
{
    $sql = 'SELECT o.*, u.id AS seller_user_id, u.name AS seller_name FROM orders o LEFT JOIN users u ON u.id = o.sold_by_user_id';
    $conditions = [];
    $params = [];
    if ($user !== null && $user['role'] === 'cliente') {
        $conditions[] = 'o.customer_user_id = :user_id';
        $params['user_id'] = (int) $user['id'];
    } elseif ($user === null) {
        if ($guestToken === null || $guestToken === '') {
            return [];
        }
        $conditions[] = 'o.guest_token_hash = :guest_token_hash';
        $params['guest_token_hash'] = hash('sha256', $guestToken);
    }
    if ($orderId !== null) {
        $conditions[] = 'o.order_id = :order_id';
        $params['order_id'] = $orderId;
    }
    if ($conditions !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY o.created_at DESC';
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return array_map(static function (array $row) use ($pdo, $user): array {
        $order = order_record($pdo, $row);
        if ($user === null || $user['role'] === 'cliente') {
            unset($order['seller']);
        }
        return $order;
    }, $statement->fetchAll());
}

function insert_order(PDO $pdo, array $order, array $items, ?int $userId, ?string $guestToken, ?int $sellerUserId): void
{
    $statement = $pdo->prepare('INSERT INTO orders (order_id, customer_user_id, guest_token_hash, sold_by_user_id, customer_name, customer_email, customer_phone, customer_city, customer_address, customer_notes, subtotal, shipping, total, status, payment, is_demo, is_test, created_at)
        VALUES (:id, :user_id, :guest_hash, :seller_id, :name, :email, :phone, :city, :address, :notes, :subtotal, :shipping, :total, :status, :payment, :is_demo, :is_test, :created_at)');
    $createdAt = new DateTimeImmutable((string) ($order['createdAt'] ?? 'now'));
    $statement->execute([
        'id' => (string) $order['id'], 'user_id' => $userId,
        'guest_hash' => $guestToken === null ? null : hash('sha256', $guestToken),
        'seller_id' => $sellerUserId,
        'name' => (string) ($order['customer']['name'] ?? ''), 'email' => (string) ($order['customer']['email'] ?? ''),
        'phone' => (string) ($order['customer']['phone'] ?? ''), 'city' => (string) ($order['customer']['city'] ?? ''),
        'address' => (string) ($order['customer']['address'] ?? ''), 'notes' => (string) ($order['customer']['notes'] ?? ''),
        'subtotal' => (int) ($order['subtotal'] ?? 0), 'shipping' => (int) ($order['shipping'] ?? 0),
        'total' => (int) ($order['total'] ?? 0), 'status' => (string) ($order['status'] ?? 'Pedido recibido'),
        'payment' => (string) ($order['payment'] ?? 'Pago contra entrega'), 'is_demo' => !empty($order['demo']) ? 1 : 0,
        'is_test' => !empty($order['test']) ? 1 : 0,
        'created_at' => $createdAt->format('Y-m-d H:i:s'),
    ]);
    $itemStatement = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity) VALUES (:order_id, :product_id, :name, :price, :quantity)');
    foreach ($items as $item) {
        $itemStatement->execute([
            'order_id' => (string) $order['id'], 'product_id' => $item['productId'] ?? null,
            'name' => (string) ($item['name'] ?? ''), 'price' => (int) ($item['price'] ?? 0),
            'quantity' => (int) ($item['quantity'] ?? 0),
        ]);
    }
}

function report_summary(PDO $pdo): array
{
    $startMonth = new DateTimeImmutable('first day of this month');
    $startMonth = $startMonth->modify('-5 months');
    $since = $startMonth->format('Y-m-01 00:00:00');
    $monthExpression = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
        ? "strftime('%Y-%m', created_at)"
        : "DATE_FORMAT(created_at, '%Y-%m')";
    $monthlyStatement = $pdo->prepare("SELECT {$monthExpression} AS month, COUNT(*) AS order_count, COALESCE(SUM(total), 0) AS sales FROM orders WHERE is_demo = 0 AND created_at >= :since GROUP BY month ORDER BY month");
    $monthlyStatement->execute(['since' => $since]);
    $monthlyRows = [];
    foreach ($monthlyStatement->fetchAll() as $row) {
        $monthlyRows[$row['month']] = ['month' => $row['month'], 'orders' => (int) $row['order_count'], 'sales' => (int) $row['sales']];
    }

    $monthlySales = [];
    $monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    for ($offset = 0; $offset < 6; $offset++) {
        $month = $startMonth->modify("+{$offset} months");
        $key = $month->format('Y-m');
        $monthlySales[] = ($monthlyRows[$key] ?? ['month' => $key, 'orders' => 0, 'sales' => 0]) + [
            'label' => $monthNames[(int) $month->format('n') - 1] . ' ' . $month->format('y'),
        ];
    }

    $sellerRows = $pdo->query("SELECT u.id, u.name, u.role,
        COUNT(o.order_id) AS order_count,
        COALESCE(SUM(o.total), 0) AS sales,
        COUNT(CASE WHEN o.is_test = 1 THEN o.order_id END) AS test_order_count,
        COALESCE(SUM(CASE WHEN o.is_test = 1 THEN o.total ELSE 0 END), 0) AS test_sales
        FROM users u
        LEFT JOIN orders o ON o.sold_by_user_id = u.id AND o.is_demo = 0
        WHERE u.role IN ('administrador', 'operador')
        GROUP BY u.id, u.name, u.role
        ORDER BY sales DESC, order_count DESC, u.name
        LIMIT 10")->fetchAll();

    return [
        'products' => (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
        'orders' => (int) $pdo->query('SELECT COUNT(*) FROM orders WHERE is_demo = 0')->fetchColumn(),
        'sales' => (int) $pdo->query('SELECT COALESCE(SUM(total), 0) FROM orders WHERE is_demo = 0')->fetchColumn(),
        'openOrders' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE is_demo = 0 AND status <> 'Entregado'")->fetchColumn(),
        'testOrders' => (int) $pdo->query('SELECT COUNT(*) FROM orders WHERE is_demo = 0 AND is_test = 1')->fetchColumn(),
        'testSales' => (int) $pdo->query('SELECT COALESCE(SUM(total), 0) FROM orders WHERE is_demo = 0 AND is_test = 1')->fetchColumn(),
        'lowStock' => (int) $pdo->query('SELECT COUNT(*) FROM products WHERE stock <= 9')->fetchColumn(),
        'byStatus' => $pdo->query("SELECT status, COUNT(*) AS count, COALESCE(SUM(total), 0) AS sales,
            SUM(CASE WHEN is_test = 1 THEN 1 ELSE 0 END) AS test_count
            FROM orders WHERE is_demo = 0 GROUP BY status ORDER BY status")->fetchAll(),
        'topProducts' => $pdo->query("SELECT oi.product_name AS name, SUM(oi.quantity) AS units, SUM(oi.unit_price * oi.quantity) AS sales
            FROM order_items oi JOIN orders o ON o.order_id = oi.order_id
            WHERE o.is_demo = 0
            GROUP BY oi.product_name ORDER BY units DESC, sales DESC LIMIT 10")->fetchAll(),
        'monthlySales' => $monthlySales,
        'salespeople' => $sellerRows,
        'generatedAt' => date(DATE_ATOM),
    ];
}

function valid_product(array $data): array
{
    $name = trim((string) ($data['name'] ?? ''));
    $category = trim((string) ($data['category'] ?? ''));
    $price = filter_var($data['price'] ?? null, FILTER_VALIDATE_INT);
    $stock = filter_var($data['stock'] ?? null, FILTER_VALIDATE_INT);
    if (mb_strlen($name) < 3 || mb_strlen($name) > 100 || !in_array($category, ['Juegos didácticos', 'Piñatería', 'Fiesta y decoración'], true)
        || $price === false || $price < 100 || $price > 10_000_000 || $stock === false || $stock < 0 || $stock > 9999) {
        respond(422, ['ok' => false, 'message' => 'Revisa el nombre, categoría, precio y existencias del producto.']);
    }
    foreach (['description' => 180, 'badge' => 28, 'image' => 180, 'color' => 30, 'icon' => 16] as $field => $max) {
        if (isset($data[$field]) && mb_strlen((string) $data[$field]) > $max) {
            respond(422, ['ok' => false, 'message' => "El campo {$field} excede la longitud permitida."]);
        }
    }
    return [
        'name' => $name,
        'category' => $category,
        'price' => $price,
        'stock' => $stock,
        'description' => trim((string) ($data['description'] ?? '')),
        'badge' => trim((string) ($data['badge'] ?? 'Para imaginar')),
        'image' => trim((string) ($data['image'] ?? '')),
        'color' => trim((string) ($data['color'] ?? 'lilac')),
        'icon' => trim((string) ($data['icon'] ?? '🎨')),
    ];
}

function validate_user(array $data, bool $passwordRequired): array
{
    $name = trim((string) ($data['name'] ?? ''));
    $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
    $password = (string) ($data['password'] ?? '');
    $role = (string) ($data['role'] ?? 'cliente');
    if (mb_strlen($name) < 2 || mb_strlen($name) > 90 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120) {
        respond(422, ['ok' => false, 'message' => 'Ingresa un nombre y correo electrónico válidos.']);
    }
    if (($passwordRequired || $password !== '') && (strlen($password) < 8 || strlen($password) > 128)) {
        respond(422, ['ok' => false, 'message' => 'La contraseña debe tener entre 8 y 128 caracteres.']);
    }
    $phone = trim((string) ($data['phone'] ?? ''));
    $city = trim((string) ($data['city'] ?? ''));
    $address = trim((string) ($data['address'] ?? ''));
    if (!in_array($role, ['administrador', 'operador', 'consulta', 'cliente'], true)) {
        respond(422, ['ok' => false, 'message' => 'El perfil seleccionado no es válido.']);
    }
    if (mb_strlen($phone) > 20 || mb_strlen($city) > 60 || mb_strlen($address) > 120) {
        respond(422, ['ok' => false, 'message' => 'Uno de los datos de contacto excede la longitud permitida.']);
    }
    if ($role === 'cliente' && $passwordRequired
        && (!preg_match('/^[0-9+(). -]{7,20}$/', $phone) || mb_strlen($city) < 2 || mb_strlen($address) < 5)) {
        respond(422, ['ok' => false, 'message' => 'Completa un celular, ciudad y dirección de entrega válidos.']);
    }
    return [
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'role' => $role,
        'phone' => $phone,
        'city' => $city,
        'address' => $address,
    ];
}

$resource = trim((string) ($_GET['resource'] ?? ''), '/');
$segments = explode('/', $resource);
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$id = isset($_GET['id']) ? (string) $_GET['id'] : (count($segments) > 1 ? $segments[1] : null);
$resource = $segments[0] ?? '';

if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
    header('Allow: GET, POST, PUT, DELETE');
    respond(405, ['ok' => false, 'message' => 'Método no permitido.']);
}
if ($method !== 'GET') {
    require_csrf();
}

try {
    $pdo = database();
    ensure_report_schema($pdo);

    if ($resource === 'auth') {
        $action = $segments[1] ?? '';
        if ($method === 'GET' && $action === 'me') {
            respond(200, ['ok' => true, 'user' => current_user(), 'csrfToken' => $_SESSION['csrf_token']]);
        }
        if ($method === 'POST' && $action === 'login') {
            $data = input();
            $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
            $password = (string) ($data['password'] ?? '');
            $statement = $pdo->prepare('SELECT * FROM users WHERE email = :email');
            $statement->execute(['email' => $email]);
            $user = $statement->fetch();
            if (!$user || !password_verify($password, $user['password_hash'])) {
                respond(401, ['ok' => false, 'message' => 'El correo o la contraseña no son correctos.']);
            }
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $update = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
                $update->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            respond(200, ['ok' => true, 'user' => public_user($user), 'csrfToken' => $_SESSION['csrf_token']]);
        }
        if ($method === 'POST' && $action === 'register') {
            $data = validate_user(input(), true);
            $data['role'] = 'cliente';
            $statement = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, phone, city, address) VALUES (:name, :email, :password_hash, :role, :phone, :city, :address)');
            try {
                $statement->execute([
                    'name' => $data['name'], 'email' => $data['email'],
                    'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                    'role' => 'cliente', 'phone' => $data['phone'], 'city' => $data['city'], 'address' => $data['address'],
                ]);
            } catch (PDOException $error) {
                if ($error->getCode() === '23000') {
                    respond(409, ['ok' => false, 'message' => 'Ya existe una cuenta con ese correo electrónico.']);
                }
                throw $error;
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $pdo->lastInsertId();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $data['id'] = (int) $_SESSION['user_id'];
            unset($data['password']);
            respond(201, ['ok' => true, 'user' => $data, 'csrfToken' => $_SESSION['csrf_token']]);
        }
        if ($method === 'POST' && $action === 'logout') {
            require_user();
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            respond(200, ['ok' => true, 'csrfToken' => $_SESSION['csrf_token']]);
        }
        respond(404, ['ok' => false, 'message' => 'Recurso de autenticación no encontrado.']);
    }

    if ($resource === 'products') {
        if ($method === 'GET') {
            if ($id !== null) {
                $product = find_product($pdo, $id);
                if ($product !== null) {
                    respond(200, ['ok' => true, 'data' => $product]);
                }
                respond(404, ['ok' => false, 'message' => 'Producto no encontrado.']);
            }
            respond(200, ['ok' => true, 'data' => products_list($pdo)]);
        }
        require_user(['administrador', 'operador']);
        if ($method === 'POST') {
            $product = valid_product(input());
            $product['id'] = 'p-' . bin2hex(random_bytes(6));
            try {
                save_product($pdo, $product['id'], $product, true);
            } catch (PDOException $error) {
                if ($error->getCode() === '23000') {
                    respond(409, ['ok' => false, 'message' => 'Ya existe un producto con ese nombre.']);
                }
                throw $error;
            }
            respond(201, ['ok' => true, 'data' => $product]);
        }
        if ($id === null) {
            respond(400, ['ok' => false, 'message' => 'Indica el identificador del producto.']);
        }
        $existing = find_product($pdo, $id);
        if ($existing === null) {
            respond(404, ['ok' => false, 'message' => 'Producto no encontrado.']);
        }
        if ($method === 'DELETE') {
            require_user(['administrador']);
            $statement = $pdo->prepare('DELETE FROM products WHERE product_id = :id');
            $statement->execute(['id' => $id]);
            respond(200, ['ok' => true]);
        }
        if ($method === 'PUT') {
            $updated = valid_product(input());
            try {
                save_product($pdo, $id, $updated, false);
            } catch (PDOException $error) {
                if ($error->getCode() === '23000') {
                    respond(409, ['ok' => false, 'message' => 'Ya existe un producto con ese nombre.']);
                }
                throw $error;
            }
            $updated['id'] = $id;
            respond(200, ['ok' => true, 'data' => $updated]);
        }
        respond(405, ['ok' => false, 'message' => 'Método no permitido para productos.']);
    }

    if ($resource === 'orders') {
        if ($method === 'GET') {
            $user = current_user();
            $guestToken = $user === null ? ($_SESSION['guest_order_token'] ?? null) : null;
            respond(200, ['ok' => true, 'data' => orders_list($pdo, $user, $guestToken, $id)]);
        }
        if ($method === 'POST') {
            $data = input();
            $customer = $data['customer'] ?? [];
            $lines = $data['items'] ?? [];
            if (!is_array($customer) || !is_array($lines) || $lines === []) {
                respond(422, ['ok' => false, 'message' => 'El pedido debe incluir datos del cliente y productos.']);
            }
            $name = trim((string) ($customer['name'] ?? ''));
            $email = mb_strtolower(trim((string) ($customer['email'] ?? '')));
            if (mb_strlen($name) < 3 || mb_strlen($name) > 90 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120
                || mb_strlen((string) ($customer['phone'] ?? '')) > 20 || mb_strlen((string) ($customer['city'] ?? '')) > 60
                || mb_strlen((string) ($customer['address'] ?? '')) > 120 || mb_strlen((string) ($customer['notes'] ?? '')) > 500) {
                respond(422, ['ok' => false, 'message' => 'Los datos del cliente no son válidos.']);
            }
            $owner = current_user();
            if ($owner !== null && $owner['role'] === 'cliente') {
                if (mb_strtolower($email) !== mb_strtolower((string) $owner['email'])) {
                    respond(403, ['ok' => false, 'message' => 'El pedido debe usar el correo de tu cuenta.']);
                }
                $name = (string) $owner['name'];
                $email = (string) $owner['email'];
                $customer['phone'] = $owner['phone'];
                $customer['city'] = $owner['city'];
                $customer['address'] = $owner['address'];
            }
            $pdo->beginTransaction();
            try {
                $items = [];
                $subtotal = 0;
                foreach ($lines as $line) {
                    $productId = (string) ($line['productId'] ?? '');
                    $quantity = filter_var($line['quantity'] ?? null, FILTER_VALIDATE_INT);
                    if ($quantity === false || $quantity < 1) {
                        throw new DomainException('Uno de los productos del pedido no es válido.');
                    }
                    $product = find_product($pdo, $productId, true);
                    if ($product === null) {
                        throw new DomainException('Uno de los productos del pedido ya no está disponible.');
                    }
                    if ($product['stock'] < $quantity) {
                        throw new DomainException('No hay existencias suficientes de ' . $product['name'] . '.');
                    }
                    $stockUpdate = $pdo->prepare('UPDATE products SET stock = stock - :quantity WHERE product_id = :id AND stock >= :quantity');
                    $stockUpdate->execute(['quantity' => $quantity, 'id' => $productId]);
                    if ($stockUpdate->rowCount() !== 1) {
                        throw new DomainException('Las existencias cambiaron. Revisa tu carrito e inténtalo de nuevo.');
                    }
                    $items[] = ['productId' => $productId, 'name' => $product['name'], 'price' => (int) $product['price'], 'quantity' => $quantity];
                    $subtotal += (int) $product['price'] * $quantity;
                }
                $shipping = $subtotal === 0 || $subtotal >= 120000 ? 0 : 8900;
                $guestToken = $owner === null || $owner['role'] !== 'cliente'
                    ? ($_SESSION['guest_order_token'] ??= bin2hex(random_bytes(32)))
                    : null;
                $sellerUserId = $owner !== null && in_array($owner['role'], ['administrador', 'operador'], true)
                    ? (int) $owner['id']
                    : null;
                $order = [
                    'id' => 'AT-' . strtoupper(bin2hex(random_bytes(3))),
                    'customer' => [
                        'name' => $name, 'email' => $email,
                        'phone' => trim((string) ($customer['phone'] ?? '')),
                        'city' => trim((string) ($customer['city'] ?? '')),
                        'address' => trim((string) ($customer['address'] ?? '')),
                        'notes' => trim((string) ($customer['notes'] ?? '')),
                    ],
                    'items' => $items, 'subtotal' => $subtotal, 'shipping' => $shipping, 'total' => $subtotal + $shipping,
                    'seller' => $sellerUserId === null ? null : ['id' => $sellerUserId, 'name' => (string) $owner['name']],
                    'status' => 'Pedido recibido', 'payment' => 'Pago contra entrega',
                    'createdAt' => date(DATE_ATOM), 'demo' => false,
                ];
                insert_order($pdo, $order, $items, $owner !== null && $owner['role'] === 'cliente' ? (int) $owner['id'] : null, $guestToken, $sellerUserId);
                $pdo->commit();
                respond(201, ['ok' => true, 'data' => $order]);
            } catch (DomainException $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                respond(409, ['ok' => false, 'message' => $error->getMessage()]);
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $error;
            }
        }
        if ($method === 'PUT' && $id !== null) {
            require_user(['administrador', 'operador']);
            $data = input();
            $status = (string) ($data['status'] ?? '');
            if (!in_array($status, ['Pedido recibido', 'En preparación', 'Enviado', 'Entregado'], true)) {
                respond(422, ['ok' => false, 'message' => 'El estado del pedido no es válido.']);
            }
            $statement = $pdo->prepare('UPDATE orders SET status = :status WHERE order_id = :id');
            $statement->execute(['status' => $status, 'id' => $id]);
            if ($statement->rowCount() === 0) {
                $exists = $pdo->prepare('SELECT 1 FROM orders WHERE order_id = :id');
                $exists->execute(['id' => $id]);
                if (!$exists->fetchColumn()) {
                    respond(404, ['ok' => false, 'message' => 'Pedido no encontrado.']);
                }
            }
            respond(200, ['ok' => true]);
        }
        respond(405, ['ok' => false, 'message' => 'Operación de pedidos no permitida.']);
    }

    if ($resource === 'users') {
        $admin = require_user(['administrador']);
        if ($method === 'GET') {
            if ($id !== null) {
                $statement = $pdo->prepare('SELECT id, name, email, role, phone, city, address, created_at AS createdAt FROM users WHERE id = :id');
                $statement->execute(['id' => $id]);
                $user = $statement->fetch();
                if (!$user) {
                    respond(404, ['ok' => false, 'message' => 'Cuenta no encontrada.']);
                }
                respond(200, ['ok' => true, 'data' => $user]);
            }
            $statement = $pdo->query('SELECT id, name, email, role, phone, city, address, created_at AS createdAt FROM users ORDER BY name');
            respond(200, ['ok' => true, 'data' => $statement->fetchAll()]);
        }
        if ($method === 'POST') {
            $data = validate_user(input(), true);
            $statement = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, phone, city, address) VALUES (:name, :email, :password_hash, :role, :phone, :city, :address)');
            try {
                $statement->execute([
                    'name' => $data['name'], 'email' => $data['email'],
                    'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                    'role' => $data['role'], 'phone' => $data['phone'], 'city' => $data['city'], 'address' => $data['address'],
                ]);
            } catch (PDOException $error) {
                if ($error->getCode() === '23000') {
                    respond(409, ['ok' => false, 'message' => 'Ya existe una cuenta con ese correo electrónico.']);
                }
                throw $error;
            }
            $data['id'] = (int) $pdo->lastInsertId();
            unset($data['password']);
            respond(201, ['ok' => true, 'data' => $data]);
        }
        if ($id === null) {
            respond(400, ['ok' => false, 'message' => 'Indica el identificador de la cuenta.']);
        }
        $targetStatement = $pdo->prepare('SELECT id, name, email, role, phone, city, address FROM users WHERE id = :id');
        $targetStatement->execute(['id' => $id]);
        $target = $targetStatement->fetch();
        if (!$target) {
            respond(404, ['ok' => false, 'message' => 'Cuenta no encontrada.']);
        }
        if ($method === 'DELETE') {
            if ((int) $target['id'] === (int) $admin['id']) {
                respond(409, ['ok' => false, 'message' => 'No puedes eliminar tu propia cuenta mientras tienes sesión iniciada.']);
            }
            if ($target['role'] === 'administrador') {
                $count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'administrador'")->fetchColumn();
                if ($count <= 1) {
                    respond(409, ['ok' => false, 'message' => 'Debe permanecer al menos una cuenta de administración.']);
                }
            }
            $statement = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $statement->execute(['id' => $id]);
            respond(200, ['ok' => true]);
        }
        if ($method === 'PUT') {
            $data = validate_user(input(), false);
            if ((int) $target['id'] === (int) $admin['id'] && $data['role'] !== 'administrador') {
                respond(409, ['ok' => false, 'message' => 'No puedes cambiar tu propio perfil de administración.']);
            }
            if ($target['role'] === 'administrador' && $data['role'] !== 'administrador') {
                $count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'administrador'")->fetchColumn();
                if ($count <= 1) {
                    respond(409, ['ok' => false, 'message' => 'Debe permanecer al menos una cuenta de administración.']);
                }
            }
            $sql = 'UPDATE users SET name = :name, email = :email, role = :role, phone = :phone, city = :city, address = :address';
            $params = ['name' => $data['name'], 'email' => $data['email'], 'role' => $data['role'], 'phone' => $data['phone'], 'city' => $data['city'], 'address' => $data['address'], 'id' => $id];
            if ($data['password'] !== '') {
                $sql .= ', password_hash = :password_hash';
                $params['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            $sql .= ' WHERE id = :id';
            $statement = $pdo->prepare($sql);
            try {
                $statement->execute($params);
            } catch (PDOException $error) {
                if ($error->getCode() === '23000') {
                    respond(409, ['ok' => false, 'message' => 'Ya existe una cuenta con ese correo electrónico.']);
                }
                throw $error;
            }
            unset($data['password']);
            $data['id'] = (int) $id;
            respond(200, ['ok' => true, 'data' => $data]);
        }
    }

    if ($resource === 'reports' && $method === 'GET') {
        require_user(['administrador', 'operador', 'consulta']);
        $type = $segments[1] ?? 'summary';
        if ($type === 'summary') {
            respond(200, ['ok' => true, 'data' => report_summary($pdo)]);
        }
        if (!in_array($type, ['pdf', 'orders', 'products'], true)) {
            respond(404, ['ok' => false, 'message' => 'Tipo de reporte no encontrado.']);
        }
        if (!is_file(__DIR__ . '/../vendor/autoload.php')) {
            respond(503, ['ok' => false, 'message' => 'Falta instalar Dompdf. Ejecuta composer install en el proyecto.']);
        }
        require_once __DIR__ . '/../vendor/autoload.php';
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new \Dompdf\Dompdf($options);
        $reportType = $type === 'pdf' ? 'summary' : $type;
        $report = report_summary($pdo);
        if ($reportType === 'orders') {
            $orders = $pdo->query("SELECT o.order_id, o.is_demo, o.is_test, o.created_at, o.customer_name, o.customer_email, o.status,
                o.subtotal, o.shipping, o.total, COALESCE(seller.name, 'Sin atribuir') AS seller_name
                FROM orders o LEFT JOIN users seller ON seller.id = o.sold_by_user_id
                ORDER BY o.created_at DESC, o.order_id DESC")->fetchAll();
            $items = $pdo->query('SELECT order_id, product_name, quantity, unit_price FROM order_items ORDER BY id')->fetchAll();
            $itemsByOrder = [];
            foreach ($items as $item) {
                $itemsByOrder[$item['order_id']][] = $item;
            }
            $statusCounts = [];
            $sales = 0;
            $testOrders = 0;
            foreach ($orders as &$order) {
                $order['items'] = $itemsByOrder[$order['order_id']] ?? [];
                $order['type_label'] = (bool) $order['is_demo'] ? 'Demostración' : ((bool) $order['is_test'] ? 'Prueba' : 'Registrado');
                $order['type_class'] = (bool) $order['is_demo'] ? 'tag-demo' : ((bool) $order['is_test'] ? '' : 'tag-registered');
                $statusCounts[$order['status']] = ($statusCounts[$order['status']] ?? 0) + 1;
                $sales += (float) $order['total'];
                $testOrders += (bool) $order['is_test'] ? 1 : 0;
            }
            unset($order);
            arsort($statusCounts);
            $report['ordersData'] = $orders;
            $report['orderStatusCounts'] = $statusCounts;
            $report['orderCount'] = count($orders);
            $report['orderSales'] = $sales;
            $report['orderAverage'] = $orders ? $sales / count($orders) : 0;
            $report['orderTestCount'] = $testOrders;
        } elseif ($reportType === 'products') {
            $products = $pdo->query('SELECT product_id, name, category, description, price, stock FROM products ORDER BY category, name')->fetchAll();
            $categoryStats = [];
            $totalUnits = 0;
            $stockValue = 0;
            $lowStock = 0;
            $outOfStock = 0;
            foreach ($products as $product) {
                $stock = (int) $product['stock'];
                $totalUnits += $stock;
                $stockValue += $stock * (float) $product['price'];
                $lowStock += $stock > 0 && $stock <= 5 ? 1 : 0;
                $outOfStock += $stock === 0 ? 1 : 0;
                $category = $product['category'];
                $categoryStats[$category] = ($categoryStats[$category] ?? 0) + $stock;
                $product['stock_state'] = $stock === 0 ? 'Agotado' : ($stock <= 5 ? 'Reponer' : 'Disponible');
                $product['stock_class'] = $stock === 0 ? 'stock-empty' : ($stock <= 5 ? 'stock-low' : '');
                $inventoryProducts[] = $product;
            }
            $report['inventoryProducts'] = $inventoryProducts ?? [];
            $report['categoryStats'] = $categoryStats;
            $report['productCount'] = count($products);
            $report['totalUnits'] = $totalUnits;
            $report['stockValue'] = $stockValue;
            $report['lowStockCount'] = $lowStock;
            $report['outOfStockCount'] = $outOfStock;
        }
        ob_start();
        require __DIR__ . '/report_template.php';
        $html = ob_get_clean();
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        header_remove('Content-Type');
        header('Content-Type: application/pdf');
        $filename = match ($reportType) {
            'orders' => 'artesoft-pedidos-',
            'products' => 'artesoft-inventario-',
            default => 'artesoft-reporte-',
        };
        header('Content-Disposition: attachment; filename="' . $filename . date('Y-m-d') . '.pdf"');
        header('Cache-Control: no-store');
        echo $pdf->output();
        exit;
    }

    respond(404, ['ok' => false, 'message' => 'Recurso no encontrado.']);
} catch (Throwable $error) {
    error_log('ARTESOFT API failed: ' . $error->getMessage());
    respond(500, ['ok' => false, 'message' => 'Ocurrió un error interno al procesar la solicitud.']);
}
