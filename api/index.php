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

function state_read(PDO $pdo, string $key, bool $lock = false): array
{
    $forUpdate = $lock && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite' ? ' FOR UPDATE' : '';
    $statement = $pdo->prepare('SELECT payload FROM app_state WHERE state_key = :state_key' . $forUpdate);
    $statement->execute(['state_key' => $key]);
    $payload = $statement->fetchColumn();
    if ($payload === false) {
        return [];
    }
    $value = json_decode((string) $payload, true, 512, JSON_THROW_ON_ERROR);
    return is_array($value) ? $value : [];
}

function state_write(PDO $pdo, string $key, array $value): void
{
    $payload = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $sql = 'INSERT INTO app_state (state_key, payload) VALUES (:state_key, :payload)
                ON CONFLICT(state_key) DO UPDATE SET payload = excluded.payload';
    } else {
        $sql = 'INSERT INTO app_state (state_key, payload) VALUES (:state_key, :payload)
                ON DUPLICATE KEY UPDATE payload = VALUES(payload)';
    }
    $statement = $pdo->prepare($sql);
    $statement->execute(['state_key' => $key, 'payload' => $payload]);
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
            $products = state_read($pdo, 'artesoft:v3:productos');
            if ($id !== null) {
                foreach ($products as $product) {
                    if ((string) ($product['id'] ?? '') === $id) {
                        respond(200, ['ok' => true, 'data' => $product]);
                    }
                }
                respond(404, ['ok' => false, 'message' => 'Producto no encontrado.']);
            }
            respond(200, ['ok' => true, 'data' => $products]);
        }
        $user = require_user(['administrador', 'operador']);
        if ($method === 'POST') {
            $product = valid_product(input());
            $product['id'] = 'p-' . bin2hex(random_bytes(6));
            $pdo->beginTransaction();
            $products = state_read($pdo, 'artesoft:v3:productos', true);
            foreach ($products as $existing) {
                if (mb_strtolower(trim((string) $existing['name'])) === mb_strtolower($product['name'])) {
                    $pdo->rollBack();
                    respond(409, ['ok' => false, 'message' => 'Ya existe un producto con ese nombre.']);
                }
            }
            array_unshift($products, $product);
            state_write($pdo, 'artesoft:v3:productos', $products);
            $pdo->commit();
            respond(201, ['ok' => true, 'data' => $product]);
        }
        if ($id === null) {
            respond(400, ['ok' => false, 'message' => 'Indica el identificador del producto.']);
        }
        if ($method === 'PUT' || $method === 'DELETE') {
            $pdo->beginTransaction();
        }
        $products = state_read($pdo, 'artesoft:v3:productos', $method === 'PUT' || $method === 'DELETE');
        $index = null;
        foreach ($products as $key => $product) {
            if ((string) ($product['id'] ?? '') === $id) {
                $index = $key;
                break;
            }
        }
        if ($index === null) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            respond(404, ['ok' => false, 'message' => 'Producto no encontrado.']);
        }
        if ($method === 'DELETE') {
            require_user(['administrador']);
            array_splice($products, $index, 1);
            state_write($pdo, 'artesoft:v3:productos', $products);
            $pdo->commit();
            respond(200, ['ok' => true]);
        }
        $updated = array_merge($products[$index], valid_product(input()));
        $updated['id'] = $products[$index]['id'];
        foreach ($products as $key => $existing) {
            if ($key !== $index && mb_strtolower(trim((string) $existing['name'])) === mb_strtolower($updated['name'])) {
                $pdo->rollBack();
                respond(409, ['ok' => false, 'message' => 'Ya existe un producto con ese nombre.']);
            }
        }
        $products[$index] = $updated;
        state_write($pdo, 'artesoft:v3:productos', $products);
        $pdo->commit();
        respond(200, ['ok' => true, 'data' => $updated]);
    }

    if ($resource === 'orders') {
        if ($method === 'GET') {
            $user = current_user();
            $orders = state_read($pdo, 'artesoft:v3:pedidos');
            if ($user !== null && $user['role'] === 'cliente') {
                $orders = array_values(array_filter($orders, static fn(array $order): bool =>
                    (int) ($order['userId'] ?? 0) === (int) $user['id']
                ));
            } elseif ($user === null) {
                $guestToken = $_SESSION['guest_order_token'] ?? '';
                $orders = array_values(array_filter($orders, static fn(array $order): bool =>
                    is_string($guestToken) && $guestToken !== '' && hash_equals($guestToken, (string) ($order['guestToken'] ?? ''))
                ));
            }
            if ($id !== null) {
                $orders = array_values(array_filter($orders, static fn(array $order): bool => (string) ($order['id'] ?? '') === $id));
            }
            respond(200, ['ok' => true, 'data' => $orders]);
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
            } else {
                $_SESSION['guest_order_token'] ??= bin2hex(random_bytes(32));
            }
            $pdo->beginTransaction();
            try {
                $products = state_read($pdo, 'artesoft:v3:productos', true);
                $items = [];
                $subtotal = 0;
                foreach ($lines as $line) {
                    $productId = (string) ($line['productId'] ?? '');
                    $quantity = filter_var($line['quantity'] ?? null, FILTER_VALIDATE_INT);
                    $productIndex = null;
                    foreach ($products as $key => $product) {
                        if ((string) ($product['id'] ?? '') === $productId) {
                            $productIndex = $key;
                            break;
                        }
                    }
                    if ($productIndex === null || $quantity === false || $quantity < 1) {
                        throw new DomainException('Uno de los productos del pedido no es válido.');
                    }
                    if ((int) $products[$productIndex]['stock'] < $quantity) {
                        throw new DomainException('No hay existencias suficientes de ' . $products[$productIndex]['name'] . '.');
                    }
                    $product = $products[$productIndex];
                    $products[$productIndex]['stock'] -= $quantity;
                    $items[] = ['productId' => $productId, 'name' => $product['name'], 'price' => (int) $product['price'], 'quantity' => $quantity];
                    $subtotal += (int) $product['price'] * $quantity;
                }
                $shipping = $subtotal === 0 || $subtotal >= 120000 ? 0 : 8900;
                $orders = state_read($pdo, 'artesoft:v3:pedidos', true);
                $order = [
                    'id' => 'AT-' . strtoupper(bin2hex(random_bytes(3))),
                    'customer' => [
                        'name' => $name, 'email' => $email,
                        'phone' => trim((string) ($customer['phone'] ?? '')),
                        'city' => trim((string) ($customer['city'] ?? '')),
                        'address' => trim((string) ($customer['address'] ?? '')),
                        'notes' => trim((string) ($customer['notes'] ?? '')),
                    ],
                    'userId' => $owner !== null && $owner['role'] === 'cliente' ? (int) $owner['id'] : null,
                    'guestToken' => $owner === null || $owner['role'] !== 'cliente' ? $_SESSION['guest_order_token'] : null,
                    'items' => $items, 'subtotal' => $subtotal, 'shipping' => $shipping, 'total' => $subtotal + $shipping,
                    'status' => 'Pedido recibido', 'payment' => 'Pago contra entrega',
                    'createdAt' => date(DATE_ATOM), 'demo' => false,
                ];
                array_unshift($orders, $order);
                state_write($pdo, 'artesoft:v3:productos', $products);
                state_write($pdo, 'artesoft:v3:pedidos', $orders);
                $pdo->commit();
                unset($order['guestToken']);
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
            $pdo->beginTransaction();
            $orders = state_read($pdo, 'artesoft:v3:pedidos', true);
            foreach ($orders as &$order) {
                if ((string) ($order['id'] ?? '') === $id) {
                    $order['status'] = $status;
                    unset($order);
                    state_write($pdo, 'artesoft:v3:pedidos', $orders);
                    $pdo->commit();
                    respond(200, ['ok' => true]);
                }
            }
            unset($order);
            $pdo->rollBack();
            respond(404, ['ok' => false, 'message' => 'Pedido no encontrado.']);
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
        $type = $segments[1] ?? 'orders';
        header_remove('Content-Type');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="artesoft-' . ($type === 'products' ? 'inventario' : 'pedidos') . '-' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            respond(500, ['ok' => false, 'message' => 'No se pudo generar el reporte.']);
        }
        fwrite($output, "\xEF\xBB\xBF");
        if ($type === 'products') {
            fputcsv($output, ['ID', 'Producto', 'Categoría', 'Precio COP', 'Existencias']);
            foreach (state_read($pdo, 'artesoft:v3:productos') as $product) {
                fputcsv($output, array_map('csv_safe', [(string) $product['id'], (string) $product['name'], (string) $product['category'], (int) $product['price'], (int) $product['stock']]));
            }
        } elseif ($type === 'orders') {
            fputcsv($output, ['Pedido', 'Fecha', 'Cliente', 'Correo', 'Estado', 'Subtotal COP', 'Envío COP', 'Total COP', 'Productos']);
            foreach (state_read($pdo, 'artesoft:v3:pedidos') as $order) {
                fputcsv($output, array_map('csv_safe', [
                    (string) ($order['id'] ?? ''), (string) ($order['createdAt'] ?? ''),
                    (string) ($order['customer']['name'] ?? ''), (string) ($order['customer']['email'] ?? ''),
                    (string) ($order['status'] ?? ''), (int) ($order['subtotal'] ?? 0),
                    (int) ($order['shipping'] ?? 0), (int) ($order['total'] ?? 0),
                    implode('; ', array_map(static fn(array $item): string => (int) ($item['quantity'] ?? 0) . ' x ' . (string) ($item['name'] ?? ''), $order['items'] ?? [])),
                ]));
            }
        } else {
            respond(404, ['ok' => false, 'message' => 'Tipo de reporte no encontrado.']);
        }
        fclose($output);
        exit;
    }

    respond(404, ['ok' => false, 'message' => 'Recurso no encontrado.']);
} catch (Throwable $error) {
    error_log('ARTESOFT API failed: ' . $error->getMessage());
    respond(500, ['ok' => false, 'message' => 'Ocurrió un error interno al procesar la solicitud.']);
}

function csv_safe(mixed $value): string|int
{
    if (!is_string($value)) {
        return $value;
    }
    return preg_match('/^[\s]*[=+\-@]/u', $value) ? "'" . $value : $value;
}
