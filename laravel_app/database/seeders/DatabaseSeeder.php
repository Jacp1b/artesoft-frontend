<?php

namespace Database\Seeders;

use DateTimeImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $users = [
            [
                'name' => 'Administración ARTESOFT',
                'email' => 'admin@artesoft.local',
                'password_hash' => Hash::make(env('ARTESOFT_ADMIN_PASSWORD', 'Demo1234!')),
                'role' => 'administrador',
                'phone' => '',
                'city' => '',
                'address' => '',
            ],
        ];

        foreach ([
            ['Prueba Administrador 1', 'admin.prueba1@artesoft.local', 'administrador'],
            ['Prueba Administrador 2', 'admin.prueba2@artesoft.local', 'administrador'],
            ['Prueba Operador 1', 'operador.prueba1@artesoft.local', 'operador'],
            ['Prueba Operador 2', 'operador.prueba2@artesoft.local', 'operador'],
            ['Prueba Consulta 1', 'consulta.prueba1@artesoft.local', 'consulta'],
            ['Prueba Consulta 2', 'consulta.prueba2@artesoft.local', 'consulta'],
            ['Prueba Cliente 1', 'cliente.prueba1@artesoft.local', 'cliente'],
            ['Prueba Cliente 2', 'cliente.prueba2@artesoft.local', 'cliente'],
        ] as [$name, $email, $role]) {
            $users[] = [
                'name' => $name,
                'email' => $email,
                'password_hash' => Hash::make('Prueba2026!'),
                'role' => $role,
                'phone' => str_starts_with($role, 'cliente') ? '3005550101' : '',
                'city' => $role === 'cliente' ? 'Bogotá' : '',
                'address' => $role === 'cliente' ? 'Calle 1 # 1-01' : '',
            ];
        }

        foreach ($users as &$user) {
            $user['created_at'] = $now;
            $user['updated_at'] = $now;
        }
        unset($user);
        DB::table('users')->insertOrIgnore($users);

        $demoData = require base_path('config/demo-data.php');
        $legacy = [];
        if (Schema::hasTable('app_state')) {
            $legacy = DB::table('app_state')
                ->whereIn('state_key', ['artesoft:v3:productos', 'artesoft:v3:pedidos'])
                ->pluck('payload', 'state_key')
                ->map(static fn (string $payload): array => json_decode($payload, true, 512, JSON_THROW_ON_ERROR))
                ->all();
        }

        if (DB::table('products')->count() === 0) {
            $products = $legacy['artesoft:v3:productos'] ?? [];
            foreach ($products ?: $demoData['products'] as $product) {
                DB::table('products')->insertOrIgnore([
                    'product_id' => (string) ($product['id'] ?? $product['product_id']),
                    'name' => (string) $product['name'],
                    'category' => (string) $product['category'],
                    'price' => (int) $product['price'],
                    'stock' => (int) $product['stock'],
                    'description' => (string) ($product['description'] ?? ''),
                    'badge' => (string) ($product['badge'] ?? ''),
                    'image' => (string) ($product['image'] ?? ''),
                    'color' => (string) ($product['color'] ?? 'lilac'),
                    'icon' => (string) ($product['icon'] ?? '🎨'),
                ]);
            }
        }

        if (DB::table('orders')->count() === 0) {
            $products = DB::table('products')->pluck('product_id')->flip();
            $users = DB::table('users')->pluck('id', 'id');
            foreach (($legacy['artesoft:v3:pedidos'] ?? []) ?: $demoData['orders'] as $order) {
                $customer = $order['customer'] ?? [];
                $orderId = (string) $order['id'];
                $createdAt = (new DateTimeImmutable((string) ($order['createdAt'] ?? 'now')))
                    ->format('Y-m-d H:i:s');
                $legacyUserId = (string) ($order['userId'] ?? '');
                DB::table('orders')->insertOrIgnore([
                    'order_id' => $orderId,
                    'customer_user_id' => $users->has($legacyUserId) ? (int) $legacyUserId : null,
                    'guest_token_hash' => isset($order['guestToken']) ? hash('sha256', (string) $order['guestToken']) : null,
                    'customer_name' => (string) ($customer['name'] ?? ''),
                    'customer_email' => (string) ($customer['email'] ?? ''),
                    'customer_phone' => (string) ($customer['phone'] ?? ''),
                    'customer_city' => (string) ($customer['city'] ?? ''),
                    'customer_address' => (string) ($customer['address'] ?? ''),
                    'customer_notes' => (string) ($customer['notes'] ?? ''),
                    'subtotal' => (int) ($order['subtotal'] ?? 0),
                    'shipping' => (int) ($order['shipping'] ?? 0),
                    'total' => (int) ($order['total'] ?? 0),
                    'status' => (string) ($order['status'] ?? 'Pedido recibido'),
                    'payment' => (string) ($order['payment'] ?? 'Pago contra entrega'),
                    'is_demo' => !empty($order['demo']),
                    'is_test' => !empty($order['test']),
                    'created_at' => $createdAt,
                ]);

                foreach ($order['items'] ?? [] as $item) {
                    DB::table('order_items')->insertOrIgnore([
                        'order_id' => $orderId,
                        'product_id' => $products->has((string) ($item['productId'] ?? ''))
                            ? (string) $item['productId']
                            : null,
                        'product_name' => (string) ($item['name'] ?? ''),
                        'unit_price' => (int) ($item['price'] ?? 0),
                        'quantity' => (int) ($item['quantity'] ?? 0),
                    ]);
                }
            }
        }
    }
}
