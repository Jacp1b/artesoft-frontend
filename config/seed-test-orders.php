<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

function artesoft_seed_test_orders(): array
{
    $pdo = artesoft_database();
    $columns = $pdo->query('SHOW COLUMNS FROM orders')->fetchAll(PDO::FETCH_COLUMN);
    foreach (['sold_by_user_id', 'is_test'] as $requiredColumn) {
        if (!in_array($requiredColumn, $columns, true)) {
            throw new RuntimeException("Falta la columna {$requiredColumn}. Abre la aplicación para aplicar la migración e inténtalo de nuevo.");
        }
    }

    $findUser = $pdo->prepare('SELECT id, name, email, role, phone, city, address FROM users WHERE email = :email AND role = :role');
    $profiles = [];
    foreach ([
        'admin.prueba1@artesoft.local' => 'administrador',
        'admin.prueba2@artesoft.local' => 'administrador',
        'operador.prueba1@artesoft.local' => 'operador',
        'operador.prueba2@artesoft.local' => 'operador',
        'cliente.prueba1@artesoft.local' => 'cliente',
        'cliente.prueba2@artesoft.local' => 'cliente',
    ] as $email => $role) {
        $findUser->execute(['email' => $email, 'role' => $role]);
        $user = $findUser->fetch();
        if (!$user) {
            throw new RuntimeException("No se encontró el perfil de prueba {$email}. Ejecuta primero database.sql.");
        }
        $profiles[$email] = $user;
    }

    $products = $pdo->query('SELECT product_id, name, price FROM products ORDER BY product_id LIMIT 6')->fetchAll();
    if (count($products) < 3) {
        throw new RuntimeException('El catálogo debe tener al menos tres productos antes de crear pedidos de prueba.');
    }

    $sellers = [
        $profiles['admin.prueba1@artesoft.local'],
        $profiles['admin.prueba2@artesoft.local'],
        $profiles['operador.prueba1@artesoft.local'],
        $profiles['operador.prueba2@artesoft.local'],
    ];
    $customers = [
        $profiles['cliente.prueba1@artesoft.local'],
        $profiles['cliente.prueba2@artesoft.local'],
    ];
    $statuses = ['Entregado', 'Enviado', 'En preparación', 'Pedido recibido', 'Entregado', 'Enviado', 'En preparación', 'Pedido recibido'];
    $monthOffsets = [5, 4, 3, 2, 1, 1, 0, 0];
    $days = [7, 11, 14, 9, 17, 22, 5, 12];
    $year = date('Y');
    $created = 0;
    $skipped = 0;

    $pdo->beginTransaction();
    try {
        $exists = $pdo->prepare('SELECT 1 FROM orders WHERE order_id = :id');
        $insertOrder = $pdo->prepare('INSERT INTO orders (
            order_id, customer_user_id, guest_token_hash, sold_by_user_id, customer_name, customer_email,
            customer_phone, customer_city, customer_address, customer_notes, subtotal, shipping, total,
            status, payment, is_demo, is_test, created_at
        ) VALUES (
            :id, :customer_id, NULL, :seller_id, :name, :email, :phone, :city, :address, :notes,
            :subtotal, :shipping, :total, :status, :payment, 0, 1, :created_at
        )');
        $insertItem = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity)
            VALUES (:order_id, :product_id, :name, :price, :quantity)');

        for ($index = 0; $index < 8; $index++) {
            $orderId = sprintf('AT-TEST-%s-%03d', $year, $index + 1);
            $exists->execute(['id' => $orderId]);
            if ($exists->fetchColumn()) {
                $skipped++;
                continue;
            }

            $seller = $sellers[intdiv($index, 2)];
            $customer = $customers[$index % count($customers)];
            $firstProduct = $products[$index % count($products)];
            $secondProduct = $products[($index + 2) % count($products)];
            $firstQuantity = ($index % 3) + 1;
            $secondQuantity = ($index % 2) + 1;
            $items = [
                [$firstProduct, $firstQuantity],
                [$secondProduct, $secondQuantity],
            ];
            $subtotal = 0;
            foreach ($items as [$product, $quantity]) {
                $subtotal += (int) $product['price'] * $quantity;
            }
            $shipping = $subtotal >= 120000 ? 0 : 8900;
            $month = (new DateTimeImmutable('first day of this month'))->modify('-' . $monthOffsets[$index] . ' months');
            $createdAt = $month->setDate(
            (int) $month->format('Y'),
            (int) $month->format('n'),
            $days[$index]
            )->setTime(10 + ($index % 7), ($index * 7) % 60);
            $insertOrder->execute([
                'id' => $orderId,
                'customer_id' => (int) $customer['id'],
                'seller_id' => (int) $seller['id'],
                'name' => $customer['name'],
                'email' => $customer['email'],
                'phone' => $customer['phone'],
                'city' => $customer['city'],
                'address' => $customer['address'],
                'notes' => 'Pedido de prueba para validar reportes. No corresponde a una venta real.',
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'total' => $subtotal + $shipping,
                'status' => $statuses[$index],
                'payment' => 'Pago contra entrega',
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
            ]);
            foreach ($items as [$product, $quantity]) {
                $insertItem->execute([
                    'order_id' => $orderId,
                    'product_id' => $product['product_id'],
                    'name' => $product['name'],
                    'price' => (int) $product['price'],
                    'quantity' => $quantity,
                ]);
            }
            $created++;
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    return ['created' => $created, 'skipped' => $skipped];
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $result = artesoft_seed_test_orders();
    printf(
        "Pedidos de prueba creados: %d. Ya existentes: %d. No se modificaron las existencias.\n",
        $result['created'],
        $result['skipped']
    );
}
