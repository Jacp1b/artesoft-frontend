<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SeedTestOrders extends Command
{
    protected $signature = 'artesoft:seed-test-orders';

    protected $description = 'Crea pedidos de prueba idempotentes para validar los informes.';

    public function handle(): int
    {
        foreach (['sold_by_user_id', 'is_test'] as $column) {
            if (!Schema::hasColumn('orders', $column)) {
                throw new RuntimeException("Falta la columna {$column}. Ejecuta php artisan migrate primero.");
            }
        }

        $profiles = [];
        foreach ([
            'admin.prueba1@artesoft.local' => 'administrador',
            'admin.prueba2@artesoft.local' => 'administrador',
            'operador.prueba1@artesoft.local' => 'operador',
            'operador.prueba2@artesoft.local' => 'operador',
            'cliente.prueba1@artesoft.local' => 'cliente',
            'cliente.prueba2@artesoft.local' => 'cliente',
        ] as $email => $role) {
            $user = DB::table('users')->where('email', $email)->where('role', $role)->first();
            if ($user === null) {
                throw new RuntimeException("No se encontró el perfil de prueba {$email}. Ejecuta php artisan db:seed primero.");
            }
            $profiles[$email] = $user;
        }

        $products = DB::table('products')->orderBy('product_id')->limit(6)->get();
        if ($products->count() < 3) {
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
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($products, $sellers, $customers, $statuses, $monthOffsets, $days, &$created, &$skipped): void {
            for ($index = 0; $index < 8; $index++) {
                $orderId = sprintf('AT-TEST-%s-%03d', now()->format('Y'), $index + 1);
                if (DB::table('orders')->where('order_id', $orderId)->exists()) {
                    $skipped++;
                    continue;
                }

                $seller = $sellers[intdiv($index, 2)];
                $customer = $customers[$index % count($customers)];
                $firstProduct = $products[$index % $products->count()];
                $secondProduct = $products[($index + 2) % $products->count()];
                $items = [
                    [$firstProduct, ($index % 3) + 1],
                    [$secondProduct, ($index % 2) + 1],
                ];
                $subtotal = 0;
                foreach ($items as [$product, $quantity]) {
                    $subtotal += (int) $product->price * $quantity;
                }
                $shipping = $subtotal >= 120000 ? 0 : 8900;
                $createdAt = now()->startOfMonth()->subMonths($monthOffsets[$index])
                    ->setDay($days[$index])->setTime(10 + ($index % 7), ($index * 7) % 60);
                DB::table('orders')->insert([
                    'order_id' => $orderId,
                    'customer_user_id' => $customer->id,
                    'guest_token_hash' => null,
                    'sold_by_user_id' => $seller->id,
                    'customer_name' => $customer->name,
                    'customer_email' => $customer->email,
                    'customer_phone' => $customer->phone,
                    'customer_city' => $customer->city,
                    'customer_address' => $customer->address,
                    'customer_notes' => 'Pedido de prueba para validar reportes. No corresponde a una venta real.',
                    'subtotal' => $subtotal,
                    'shipping' => $shipping,
                    'total' => $subtotal + $shipping,
                    'status' => $statuses[$index],
                    'payment' => 'Pago contra entrega',
                    'is_demo' => false,
                    'is_test' => true,
                    'created_at' => $createdAt,
                ]);
                foreach ($items as [$product, $quantity]) {
                    DB::table('order_items')->insert([
                        'order_id' => $orderId,
                        'product_id' => $product->product_id,
                        'product_name' => $product->name,
                        'unit_price' => (int) $product->price,
                        'quantity' => $quantity,
                    ]);
                }
                $created++;
            }
        });

        $this->info("Pedidos de prueba creados: {$created}. Ya existentes: {$skipped}. No se modificaron las existencias.");
        return self::SUCCESS;
    }
}
