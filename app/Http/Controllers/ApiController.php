<?php

namespace App\Http\Controllers;

use DateTimeImmutable;
use DomainException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ApiController extends Controller
{
    public function handle(Request $request)
    {
        $method = strtoupper($request->method());
        if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
            return response()->json(['ok' => false, 'message' => 'Método no permitido.'], 405)
                ->header('Allow', 'GET, POST, PUT, DELETE');
        }
        if ($method !== 'GET') {
            $this->requireCsrf($request);
        }

        $segments = explode('/', trim((string) $request->query('resource', ''), '/'));
        $resource = $segments[0] ?? '';
        $id = $request->query('id', $segments[1] ?? null);
        $id = $id === null ? null : (string) $id;

        try {
            return match ($resource) {
                'auth' => $this->auth($request, $method, $segments[1] ?? ''),
                'products' => $this->products($request, $method, $id),
                'orders' => $this->orders($request, $method, $id),
                'users' => $this->users($request, $method, $id),
                'reports' => $this->reports($request, $method, implode('/', array_slice($segments, 1)) ?: 'summary'),
                default => $this->respond(404, ['ok' => false, 'message' => 'Recurso no encontrado.']),
            };
        } catch (HttpResponseException $error) {
            throw $error;
        } catch (Throwable $error) {
            Log::error('ARTESOFT API failed.', ['exception' => $error]);
            return response()->json([
                'ok' => false,
                'message' => 'Ocurrió un error interno al procesar la solicitud.',
            ], 500);
        }
    }

    private function auth(Request $request, string $method, string $action)
    {
        if ($method === 'GET' && $action === 'me') {
            return response()->json([
                'ok' => true,
                'user' => $this->currentUser(),
                'csrfToken' => csrf_token(),
            ]);
        }

        if ($method === 'POST' && $action === 'login') {
            $data = $this->input($request);
            $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
            $password = (string) ($data['password'] ?? '');
            $user = DB::table('users')->where('email', $email)->first();
            if (!$user || !password_verify($password, $user->password_hash)) {
                return $this->respond(401, ['ok' => false, 'message' => 'El correo o la contraseña no son correctos.']);
            }
            if (password_needs_rehash($user->password_hash, PASSWORD_DEFAULT)) {
                DB::table('users')->where('id', $user->id)->update([
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);
            }
            $request->session()->regenerate();
            $request->session()->put('user_id', (int) $user->id);
            return response()->json([
                'ok' => true,
                'user' => $this->publicUser((array) $user),
                'csrfToken' => csrf_token(),
            ]);
        }

        if ($method === 'POST' && $action === 'register') {
            $data = $this->validateUser($this->input($request), true);
            $id = $this->insertUser($data, 'cliente');
            $request->session()->regenerate();
            $request->session()->put('user_id', $id);
            return response()->json([
                'ok' => true,
                'user' => [
                    'id' => $id,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'role' => 'cliente',
                    'phone' => $data['phone'],
                    'city' => $data['city'],
                    'address' => $data['address'],
                ],
                'csrfToken' => csrf_token(),
            ], 201);
        }

        if ($method === 'POST' && $action === 'logout') {
            $this->requireUser();
            $request->session()->forget(['user_id', 'guest_order_token']);
            $request->session()->regenerate();
            $request->session()->regenerateToken();
            return response()->json(['ok' => true, 'csrfToken' => csrf_token()]);
        }

        return $this->respond(404, ['ok' => false, 'message' => 'Recurso de autenticación no encontrado.']);
    }

    private function products(Request $request, string $method, ?string $id)
    {
        if ($method === 'GET') {
            if ($id !== null) {
                $product = $this->findProduct($id);
                return $product === null
                    ? $this->respond(404, ['ok' => false, 'message' => 'Producto no encontrado.'])
                    : response()->json(['ok' => true, 'data' => $product]);
            }
            return response()->json(['ok' => true, 'data' => $this->productsList()]);
        }

        $this->requireUser(['administrador', 'operador']);
        if ($method === 'POST') {
            $product = $this->validProduct($this->input($request));
            $product['id'] = 'p-' . bin2hex(random_bytes(6));
            try {
                DB::table('products')->insert($this->productRow($product) + ['product_id' => $product['id']]);
            } catch (\Illuminate\Database\QueryException $error) {
                if (($error->errorInfo[0] ?? null) === '23000' || (int) ($error->errorInfo[1] ?? 0) === 1062) {
                    return $this->respond(409, ['ok' => false, 'message' => 'Ya existe un producto con ese nombre.']);
                }
                throw $error;
            }
            return response()->json(['ok' => true, 'data' => $product], 201);
        }
        if ($id === null) {
            return $this->respond(400, ['ok' => false, 'message' => 'Indica el identificador del producto.']);
        }
        if ($this->findProduct($id) === null) {
            return $this->respond(404, ['ok' => false, 'message' => 'Producto no encontrado.']);
        }
        if ($method === 'DELETE') {
            $this->requireUser(['administrador']);
            DB::table('products')->where('product_id', $id)->delete();
            return response()->json(['ok' => true]);
        }
        if ($method === 'PUT') {
            $updated = $this->validProduct($this->input($request));
            try {
                DB::table('products')->where('product_id', $id)->update($this->productRow($updated));
            } catch (\Illuminate\Database\QueryException $error) {
                if (($error->errorInfo[0] ?? null) === '23000' || (int) ($error->errorInfo[1] ?? 0) === 1062) {
                    return $this->respond(409, ['ok' => false, 'message' => 'Ya existe un producto con ese nombre.']);
                }
                throw $error;
            }
            $updated['id'] = $id;
            return response()->json(['ok' => true, 'data' => $updated]);
        }
        return $this->respond(405, ['ok' => false, 'message' => 'Método no permitido para productos.']);
    }

    private function orders(Request $request, string $method, ?string $id)
    {
        if ($method === 'GET') {
            $user = $this->currentUser();
            $guestToken = $user === null ? $request->session()->get('guest_order_token') : null;
            return response()->json(['ok' => true, 'data' => $this->ordersList($user, $guestToken, $id)]);
        }

        if ($method === 'POST') {
            $data = $this->input($request);
            $customer = $data['customer'] ?? [];
            $lines = $data['items'] ?? [];
            if (!is_array($customer) || !is_array($lines) || $lines === []) {
                return $this->respond(422, ['ok' => false, 'message' => 'El pedido debe incluir datos del cliente y productos.']);
            }
            $name = trim((string) ($customer['name'] ?? ''));
            $email = mb_strtolower(trim((string) ($customer['email'] ?? '')));
            if (mb_strlen($name) < 3 || mb_strlen($name) > 90 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120
                || mb_strlen((string) ($customer['phone'] ?? '')) > 20 || mb_strlen((string) ($customer['city'] ?? '')) > 60
                || mb_strlen((string) ($customer['address'] ?? '')) > 120 || mb_strlen((string) ($customer['notes'] ?? '')) > 500) {
                return $this->respond(422, ['ok' => false, 'message' => 'Los datos del cliente no son válidos.']);
            }

            $owner = $this->currentUser();
            if ($owner !== null && $owner['role'] === 'cliente') {
                if (mb_strtolower($email) !== mb_strtolower((string) $owner['email'])) {
                    return $this->respond(403, ['ok' => false, 'message' => 'El pedido debe usar el correo de tu cuenta.']);
                }
                $name = (string) $owner['name'];
                $email = (string) $owner['email'];
                $customer['phone'] = $owner['phone'];
                $customer['city'] = $owner['city'];
                $customer['address'] = $owner['address'];
            }

            try {
                $order = DB::transaction(function () use ($request, $owner, $lines, $customer, $name, $email): array {
                    $items = [];
                    $subtotal = 0;
                    foreach ($lines as $line) {
                        $productId = (string) ($line['productId'] ?? '');
                        $quantity = filter_var($line['quantity'] ?? null, FILTER_VALIDATE_INT);
                        if ($quantity === false || $quantity < 1) {
                            throw new DomainException('Uno de los productos del pedido no es válido.');
                        }
                        $productRow = DB::table('products')->where('product_id', $productId)->lockForUpdate()->first();
                        $product = $productRow === null ? null : $this->productFromRow($productRow);
                        if ($product === null) {
                            throw new DomainException('Uno de los productos del pedido ya no está disponible.');
                        }
                        if ($product['stock'] < $quantity) {
                            throw new DomainException('No hay existencias suficientes de ' . $product['name'] . '.');
                        }
                        $changed = DB::table('products')->where('product_id', $productId)
                            ->where('stock', '>=', $quantity)->decrement('stock', $quantity);
                        if ($changed !== 1) {
                            throw new DomainException('Las existencias cambiaron. Revisa tu carrito e inténtalo de nuevo.');
                        }
                        $items[] = [
                            'productId' => $productId,
                            'name' => $product['name'],
                            'price' => (int) $product['price'],
                            'quantity' => $quantity,
                        ];
                        $subtotal += (int) $product['price'] * $quantity;
                    }

                    $shipping = $subtotal === 0 || $subtotal >= 120000 ? 0 : 8900;
                    $guestToken = $owner === null || $owner['role'] !== 'cliente'
                        ? $request->session()->get('guest_order_token')
                        : null;
                    if ($guestToken === null && ($owner === null || $owner['role'] !== 'cliente')) {
                        $guestToken = bin2hex(random_bytes(32));
                        $request->session()->put('guest_order_token', $guestToken);
                    }
                    $sellerId = $owner !== null && in_array($owner['role'], ['administrador', 'operador'], true)
                        ? (int) $owner['id']
                        : null;
                    $order = [
                        'id' => 'AT-' . strtoupper(bin2hex(random_bytes(3))),
                        'customer' => [
                            'name' => $name,
                            'email' => $email,
                            'phone' => trim((string) ($customer['phone'] ?? '')),
                            'city' => trim((string) ($customer['city'] ?? '')),
                            'address' => trim((string) ($customer['address'] ?? '')),
                            'notes' => trim((string) ($customer['notes'] ?? '')),
                        ],
                        'items' => $items,
                        'subtotal' => $subtotal,
                        'shipping' => $shipping,
                        'total' => $subtotal + $shipping,
                        'seller' => $sellerId === null ? null : ['id' => $sellerId, 'name' => (string) $owner['name']],
                        'status' => 'Pedido recibido',
                        'payment' => 'Pago contra entrega',
                        'createdAt' => now()->toAtomString(),
                        'demo' => false,
                    ];
                    $this->insertOrder(
                        $order,
                        $items,
                        $owner !== null && $owner['role'] === 'cliente' ? (int) $owner['id'] : null,
                        $guestToken,
                        $sellerId
                    );
                    return $order;
                });
                return response()->json(['ok' => true, 'data' => $order], 201);
            } catch (DomainException $error) {
                return $this->respond(409, ['ok' => false, 'message' => $error->getMessage()]);
            }
        }

        if ($method === 'PUT' && $id !== null) {
            $this->requireUser(['administrador', 'operador']);
            $data = $this->input($request);
            $status = (string) ($data['status'] ?? '');
            if (!in_array($status, ['Pedido recibido', 'En preparación', 'Enviado', 'Entregado'], true)) {
                return $this->respond(422, ['ok' => false, 'message' => 'El estado del pedido no es válido.']);
            }
            $updated = DB::table('orders')->where('order_id', $id)->update(['status' => $status]);
            if ($updated === 0 && !DB::table('orders')->where('order_id', $id)->exists()) {
                return $this->respond(404, ['ok' => false, 'message' => 'Pedido no encontrado.']);
            }
            return response()->json(['ok' => true]);
        }
        return $this->respond(405, ['ok' => false, 'message' => 'Operación de pedidos no permitida.']);
    }

    private function users(Request $request, string $method, ?string $id)
    {
        $admin = $this->requireUser(['administrador']);
        if ($method === 'GET') {
            $query = DB::table('users')->select([
                'id', 'name', 'email', 'role', 'phone', 'city', 'address',
                'created_at as createdAt',
            ]);
            if ($id !== null) {
                $user = $query->where('id', $id)->first();
                return $user === null
                    ? $this->respond(404, ['ok' => false, 'message' => 'Cuenta no encontrada.'])
                    : response()->json(['ok' => true, 'data' => $user]);
            }
            return response()->json(['ok' => true, 'data' => $query->orderBy('name')->get()]);
        }
        if ($method === 'POST') {
            $data = $this->validateUser($this->input($request), true);
            $data['id'] = $this->insertUser($data, $data['role']);
            unset($data['password']);
            return response()->json(['ok' => true, 'data' => $data], 201);
        }
        if ($id === null) {
            return $this->respond(400, ['ok' => false, 'message' => 'Indica el identificador de la cuenta.']);
        }

        $target = DB::table('users')->where('id', $id)->first([
            'id', 'name', 'email', 'role', 'phone', 'city', 'address',
        ]);
        if ($target === null) {
            return $this->respond(404, ['ok' => false, 'message' => 'Cuenta no encontrada.']);
        }
        if ($method === 'DELETE') {
            if ((int) $target->id === (int) $admin['id']) {
                return $this->respond(409, ['ok' => false, 'message' => 'No puedes eliminar tu propia cuenta mientras tienes sesión iniciada.']);
            }
            if ($target->role === 'administrador' && DB::table('users')->where('role', 'administrador')->count() <= 1) {
                return $this->respond(409, ['ok' => false, 'message' => 'Debe permanecer al menos una cuenta de administración.']);
            }
            DB::table('users')->where('id', $id)->delete();
            return response()->json(['ok' => true]);
        }
        if ($method === 'PUT') {
            $data = $this->validateUser($this->input($request), false);
            if ((int) $target->id === (int) $admin['id'] && $data['role'] !== 'administrador') {
                return $this->respond(409, ['ok' => false, 'message' => 'No puedes cambiar tu propio perfil de administración.']);
            }
            if ($target->role === 'administrador' && $data['role'] !== 'administrador'
                && DB::table('users')->where('role', 'administrador')->count() <= 1) {
                return $this->respond(409, ['ok' => false, 'message' => 'Debe permanecer al menos una cuenta de administración.']);
            }
            $update = [
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
                'phone' => $data['phone'],
                'city' => $data['city'],
                'address' => $data['address'],
            ];
            if ($data['password'] !== '') {
                $update['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            DB::table('users')->where('id', $id)->update($update);
            unset($data['password']);
            $data['id'] = (int) $id;
            return response()->json(['ok' => true, 'data' => $data]);
        }
        return $this->respond(405, ['ok' => false, 'message' => 'Método no permitido para cuentas.']);
    }

    private function reports(Request $request, string $method, string $type)
    {
        $this->requireUser(['administrador', 'operador', 'consulta']);
        if ($method !== 'GET') {
            return $this->respond(405, ['ok' => false, 'message' => 'Método no permitido.']);
        }
        if ($type === 'summary') {
            return response()->json(['ok' => true, 'data' => $this->reportSummary()]);
        }
        $normalizedCsvType = match ($type) {
            'csv', 'general/csv', 'csv/general' => 'summary',
            'orders/csv', 'csv/orders', 'orders-csv' => 'orders',
            'products/csv', 'csv/products', 'products-csv', 'inventory/csv', 'csv/inventory' => 'products',
            default => null,
        };
        if ($normalizedCsvType !== null) {
            return $this->csvReport($normalizedCsvType, $this->reportFilters($request));
        }
        if (!in_array($type, ['pdf', 'orders', 'products'], true)) {
            return $this->respond(404, ['ok' => false, 'message' => 'Tipo de reporte no encontrado.']);
        }

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new \Dompdf\Dompdf($options);
        $reportType = $type === 'pdf' ? 'summary' : $type;
        $report = $this->reportSummary();
        $filters = $this->reportFilters($request);
        $report['filtersLabel'] = $this->reportFiltersLabel($filters);
        if ($reportType === 'orders') {
            $this->appendOrderReport($report, $filters);
        } elseif ($reportType === 'products') {
            $this->appendInventoryReport($report);
        }

        ob_start();
        require base_path('api/report_template.php');
        $html = ob_get_clean();
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        $filename = match ($reportType) {
            'orders' => 'artesoft-pedidos-',
            'products' => 'artesoft-inventario-',
            default => 'artesoft-reporte-',
        };
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . now()->format('Y-m-d') . '.pdf"',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function csvReport(string $reportType, array $filters = [])
    {
        $report = $this->reportSummary();
        $filename = 'artesoft-reporte-' . now()->format('Y-m-d') . '.csv';
        $filtersLabel = $this->reportFiltersLabel($filters);

        if ($reportType === 'orders') {
            $this->appendOrderReport($report, $filters);
            $rows = [
                ['Filtros', $filtersLabel],
                [],
                ['Pedido', 'Tipo', 'Cliente', 'Correo', 'Telefono', 'Vendedor', 'Fecha', 'Subtotal', 'Envio', 'Total', 'Estado', 'Productos'],
            ];
            foreach ($report['ordersData'] as $order) {
                $items = collect($order['items'])->map(
                    static fn (array $item): string => $item['quantity'] . ' x ' . $item['product_name']
                )->implode('; ');
                $rows[] = [
                    $order['order_id'],
                    $order['type_label'],
                    $order['customer_name'],
                    $order['customer_email'],
                    $order['customer_phone'] ?? '',
                    $order['seller_name'],
                    $order['created_at'],
                    $order['subtotal'],
                    $order['shipping'],
                    $order['total'],
                    $order['status'],
                    $items,
                ];
            }
            $filename = 'artesoft-pedidos-' . now()->format('Y-m-d') . '.csv';
        } elseif ($reportType === 'products') {
            $this->appendInventoryReport($report);
            $rows = [
                ['ID', 'Producto', 'Categoria', 'Precio', 'Stock', 'Estado', 'Valor en inventario'],
            ];
            foreach ($report['inventoryProducts'] as $product) {
                $rows[] = [
                    $product['product_id'],
                    $product['name'],
                    $product['category'],
                    $product['price'],
                    $product['stock'],
                    $product['stock_state'],
                    (int) $product['stock'] * (float) $product['price'],
                ];
            }
            $filename = 'artesoft-inventario-' . now()->format('Y-m-d') . '.csv';
        } else {
            $rows = [
                ['Filtros', $filtersLabel],
                [],
                ['Indicador', 'Valor'],
                ['Ventas registradas', $report['sales']],
                ['Pedidos registrados', $report['orders']],
                ['Pedidos por atender', $report['openOrders']],
                ['Pedidos de prueba', $report['testOrders']],
                ['Ventas de prueba', $report['testSales']],
                ['Productos activos', $report['products']],
                ['Inventario bajo', $report['lowStock']],
            ];
        }

        $csv = "\xEF\xBB\xBF";
        foreach ($rows as $row) {
            $csv .= implode(',', array_map([$this, 'csvCell'], $row)) . "\r\n";
        }
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function csvCell($value): string
    {
        $text = str_replace(["\r", "\n"], ' ', (string) $value);
        if (strpbrk($text, ",\"\r\n") !== false) {
            $text = '"' . str_replace('"', '""', $text) . '"';
        }
        return $text;
    }

    private function reportFilters(Request $request): array
    {
        $status = trim((string) $request->query('status', ''));
        $allowedStatuses = ['Pedido recibido', 'En preparación', 'Enviado', 'Entregado', 'pending'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $seller = trim((string) $request->query('seller', ''));
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));
        $datePattern = '/^\d{4}-\d{2}-\d{2}$/';
        if (!preg_match($datePattern, $from)) {
            $from = '';
        }
        if (!preg_match($datePattern, $to)) {
            $to = '';
        }

        return [
            'status' => $status,
            'seller' => ctype_digit($seller) ? (int) $seller : null,
            'from' => $from,
            'to' => $to,
        ];
    }

    private function reportFiltersLabel(array $filters): string
    {
        $labels = [];
        if (($filters['status'] ?? '') === 'pending') {
            $labels[] = 'Estado: pendientes';
        } elseif (!empty($filters['status'])) {
            $labels[] = 'Estado: ' . $filters['status'];
        }
        if (!empty($filters['seller'])) {
            $sellerName = DB::table('users')->where('id', (int) $filters['seller'])->value('name');
            if ($sellerName) {
                $labels[] = 'Vendedor: ' . $sellerName;
            }
        }
        if (!empty($filters['from'])) {
            $labels[] = 'Desde: ' . $filters['from'];
        }
        if (!empty($filters['to'])) {
            $labels[] = 'Hasta: ' . $filters['to'];
        }
        return $labels ? implode(' · ', $labels) : 'Sin filtros';
    }

    private function appendOrderReport(array &$report, array $filters = []): void
    {
        $query = DB::table('orders as o')
            ->leftJoin('users as seller', 'seller.id', '=', 'o.sold_by_user_id')
            ->where('o.is_demo', 0);
        $status = $filters['status'] ?? '';
        if ($status === 'pending') {
            $query->where('o.status', '<>', 'Entregado');
        } elseif ($status !== '') {
            $query->where('o.status', $status);
        }
        if (!empty($filters['seller'])) {
            $query->where('o.sold_by_user_id', (int) $filters['seller']);
        }
        if (!empty($filters['from'])) {
            $query->whereDate('o.created_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->whereDate('o.created_at', '<=', $filters['to']);
        }
        $orders = $query
            ->orderByDesc('o.created_at')
            ->orderByDesc('o.order_id')
            ->get([
                'o.order_id', 'o.is_demo', 'o.is_test', 'o.created_at',
                'o.customer_name', 'o.customer_email', 'o.customer_phone', 'o.status', 'o.subtotal',
                'o.shipping', 'o.total', DB::raw("COALESCE(seller.name, 'Sin atribuir') AS seller_name"),
            ])->map(static fn (object $row): array => (array) $row)->all();
        $items = DB::table('order_items')->orderBy('id')->get()->groupBy('order_id');
        $statusCounts = [];
        $sales = 0;
        $testOrders = 0;
        foreach ($orders as &$order) {
            $order['items'] = ($items[$order['order_id']] ?? collect())->map(
                static fn (object $item): array => (array) $item
            )->all();
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
    }

    private function appendInventoryReport(array &$report): void
    {
        $products = DB::table('products')->orderBy('category')->orderBy('name')->get()->map(
            static fn (object $row): array => (array) $row
        )->all();
        $categoryStats = [];
        $totalUnits = 0;
        $stockValue = 0;
        $lowStock = 0;
        $outOfStock = 0;
        foreach ($products as &$product) {
            $stock = (int) $product['stock'];
            $totalUnits += $stock;
            $stockValue += $stock * (float) $product['price'];
            $lowStock += $stock > 0 && $stock <= 5 ? 1 : 0;
            $outOfStock += $stock === 0 ? 1 : 0;
            $category = $product['category'];
            $categoryStats[$category] = ($categoryStats[$category] ?? 0) + $stock;
            $product['stock_state'] = $stock === 0 ? 'Agotado' : ($stock <= 5 ? 'Reponer' : 'Disponible');
            $product['stock_class'] = $stock === 0 ? 'stock-empty' : ($stock <= 5 ? 'stock-low' : '');
        }
        unset($product);
        $report['inventoryProducts'] = $products;
        $report['categoryStats'] = $categoryStats;
        $report['productCount'] = count($products);
        $report['totalUnits'] = $totalUnits;
        $report['stockValue'] = $stockValue;
        $report['lowStockCount'] = $lowStock;
        $report['outOfStockCount'] = $outOfStock;
    }

    private function reportSummary(): array
    {
        $startMonth = new DateTimeImmutable('first day of this month');
        $startMonth = $startMonth->modify('-5 months');
        $since = $startMonth->format('Y-m-01 00:00:00');
        $monthExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";
        $monthlyRows = DB::select(
            "SELECT {$monthExpression} AS month, COUNT(*) AS order_count, COALESCE(SUM(total), 0) AS sales
             FROM orders WHERE is_demo = 0 AND created_at >= ? GROUP BY month ORDER BY month",
            [$since]
        );
        $byMonth = [];
        foreach ($monthlyRows as $row) {
            $byMonth[$row->month] = [
                'month' => $row->month,
                'orders' => (int) $row->order_count,
                'sales' => (int) $row->sales,
            ];
        }
        $monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $monthlySales = [];
        for ($offset = 0; $offset < 6; $offset++) {
            $month = $startMonth->modify("+{$offset} months");
            $key = $month->format('Y-m');
            $monthlySales[] = ($byMonth[$key] ?? ['month' => $key, 'orders' => 0, 'sales' => 0]) + [
                'label' => $monthNames[(int) $month->format('n') - 1] . ' ' . $month->format('y'),
            ];
        }
        $salespeople = DB::table('users as u')
            ->leftJoin('orders as o', function ($join): void {
                $join->on('o.sold_by_user_id', '=', 'u.id')->where('o.is_demo', '=', 0);
            })
            ->whereIn('u.role', ['administrador', 'operador'])
            ->groupBy('u.id', 'u.name', 'u.role')
            ->orderByDesc('sales')
            ->orderByDesc('order_count')
            ->orderBy('u.name')
            ->limit(10)
            ->get([
                'u.id', 'u.name', 'u.role',
                DB::raw('COUNT(o.order_id) AS order_count'),
                DB::raw('COALESCE(SUM(o.total), 0) AS sales'),
                DB::raw('COUNT(CASE WHEN o.is_test = 1 THEN o.order_id END) AS test_order_count'),
                DB::raw('COALESCE(SUM(CASE WHEN o.is_test = 1 THEN o.total ELSE 0 END), 0) AS test_sales'),
            ])->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'role' => $row->role,
                'order_count' => (int) $row->order_count,
                'sales' => (int) $row->sales,
                'test_order_count' => (int) $row->test_order_count,
                'test_sales' => (int) $row->test_sales,
            ])->all();

        return [
            'products' => DB::table('products')->count(),
            'orders' => DB::table('orders')->where('is_demo', 0)->count(),
            'sales' => DB::table('orders')->where('is_demo', 0)->sum('total'),
            'openOrders' => DB::table('orders')->where('is_demo', 0)->where('status', '<>', 'Entregado')->count(),
            'testOrders' => DB::table('orders')->where('is_demo', 0)->where('is_test', 1)->count(),
            'testSales' => DB::table('orders')->where('is_demo', 0)->where('is_test', 1)->sum('total'),
            'lowStock' => DB::table('products')->where('stock', '<=', 9)->count(),
            'byStatus' => DB::table('orders')
                ->where('is_demo', 0)
                ->groupBy('status')
                ->orderBy('status')
                ->get([
                    'status',
                    DB::raw('COUNT(*) AS count'),
                    DB::raw('COALESCE(SUM(total), 0) AS sales'),
                    DB::raw('SUM(CASE WHEN is_test = 1 THEN 1 ELSE 0 END) AS test_count'),
                ])->map(static fn (object $row): array => [
                    'status' => $row->status,
                    'count' => (int) $row->count,
                    'sales' => (int) $row->sales,
                    'test_count' => (int) $row->test_count,
                ])->all(),
            'topProducts' => DB::table('order_items as oi')
                ->join('orders as o', 'o.order_id', '=', 'oi.order_id')
                ->where('o.is_demo', 0)
                ->groupBy('oi.product_name')
                ->orderByDesc('units')
                ->orderByDesc('sales')
                ->limit(10)
                ->get([
                    'oi.product_name as name',
                    DB::raw('SUM(oi.quantity) AS units'),
                    DB::raw('SUM(oi.unit_price * oi.quantity) AS sales'),
                ])->map(static fn (object $row): array => [
                    'name' => $row->name,
                    'units' => (int) $row->units,
                    'sales' => (int) $row->sales,
                ])->all(),
            'monthlySales' => $monthlySales,
            'salespeople' => $salespeople,
            'generatedAt' => now()->toAtomString(),
        ];
    }

    private function ordersList(?array $user, ?string $guestToken, ?string $orderId = null): array
    {
        $query = DB::table('orders as o')
            ->leftJoin('users as u', 'u.id', '=', 'o.sold_by_user_id');
        if ($user !== null && $user['role'] === 'cliente') {
            $query->where('o.customer_user_id', (int) $user['id']);
        } elseif ($user === null) {
            if ($guestToken === null || $guestToken === '') {
                return [];
            }
            $query->where('o.guest_token_hash', hash('sha256', $guestToken));
        }
        if ($orderId !== null) {
            $query->where('o.order_id', $orderId);
        }
        return $query->orderByDesc('o.created_at')->get([
            'o.*', 'u.id as seller_user_id', 'u.name as seller_name',
        ])->map(function (object $row) use ($user): array {
            $order = $this->orderRecord((array) $row);
            if ($user === null || $user['role'] === 'cliente') {
                unset($order['seller']);
            }
            return $order;
        })->all();
    }

    private function orderRecord(array $row): array
    {
        return [
            'id' => $row['order_id'],
            'customer' => [
                'name' => $row['customer_name'],
                'email' => $row['customer_email'],
                'phone' => $row['customer_phone'],
                'city' => $row['customer_city'],
                'address' => $row['customer_address'],
                'notes' => $row['customer_notes'],
            ],
            'items' => $this->orderItems($row['order_id']),
            'subtotal' => (int) $row['subtotal'],
            'shipping' => (int) $row['shipping'],
            'total' => (int) $row['total'],
            'status' => $row['status'],
            'payment' => $row['payment'],
            'seller' => isset($row['seller_user_id']) && $row['seller_user_id'] !== null
                ? ['id' => (int) $row['seller_user_id'], 'name' => (string) $row['seller_name']]
                : null,
            'createdAt' => date(DATE_ATOM, strtotime($row['created_at'])),
            'demo' => (bool) $row['is_demo'],
            'test' => (bool) ($row['is_test'] ?? false),
        ];
    }

    private function orderItems(string $orderId): array
    {
        return DB::table('order_items')->where('order_id', $orderId)->orderBy('id')->get()
            ->map(static fn (object $item): array => [
                'productId' => $item->product_id,
                'name' => $item->product_name,
                'price' => (int) $item->unit_price,
                'quantity' => (int) $item->quantity,
            ])->all();
    }

    private function insertOrder(array $order, array $items, ?int $userId, ?string $guestToken, ?int $sellerUserId): void
    {
        DB::table('orders')->insert([
            'order_id' => $order['id'],
            'customer_user_id' => $userId,
            'guest_token_hash' => $guestToken === null ? null : hash('sha256', $guestToken),
            'sold_by_user_id' => $sellerUserId,
            'customer_name' => $order['customer']['name'],
            'customer_email' => $order['customer']['email'],
            'customer_phone' => $order['customer']['phone'],
            'customer_city' => $order['customer']['city'],
            'customer_address' => $order['customer']['address'],
            'customer_notes' => $order['customer']['notes'],
            'subtotal' => $order['subtotal'],
            'shipping' => $order['shipping'],
            'total' => $order['total'],
            'status' => $order['status'],
            'payment' => $order['payment'],
            'is_demo' => false,
            'is_test' => false,
            'created_at' => now(),
        ]);
        foreach ($items as $item) {
            DB::table('order_items')->insert([
                'order_id' => $order['id'],
                'product_id' => $item['productId'],
                'product_name' => $item['name'],
                'unit_price' => $item['price'],
                'quantity' => $item['quantity'],
            ]);
        }
    }

    private function productsList(): array
    {
        return DB::table('products')->orderBy('created_at')->orderBy('product_id')->get()
            ->map(fn (object $row): array => $this->productFromRow($row))->all();
    }

    private function findProduct(string $id): ?array
    {
        $row = DB::table('products')->where('product_id', $id)->first();
        return $row === null ? null : $this->productFromRow($row);
    }

    private function productFromRow(object $row): array
    {
        return [
            'id' => $row->product_id,
            'name' => $row->name,
            'category' => $row->category,
            'price' => (int) $row->price,
            'stock' => (int) $row->stock,
            'description' => $row->description,
            'badge' => $row->badge,
            'image' => $row->image,
            'color' => $row->color,
            'icon' => $row->icon,
        ];
    }

    private function productRow(array $product): array
    {
        return [
            'name' => $product['name'],
            'category' => $product['category'],
            'price' => $product['price'],
            'stock' => $product['stock'],
            'description' => $product['description'],
            'badge' => $product['badge'],
            'image' => $product['image'],
            'color' => $product['color'],
            'icon' => $product['icon'],
        ];
    }

    private function validProduct(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $category = trim((string) ($data['category'] ?? ''));
        $price = filter_var($data['price'] ?? null, FILTER_VALIDATE_INT);
        $stock = filter_var($data['stock'] ?? null, FILTER_VALIDATE_INT);
        if (mb_strlen($name) < 3 || mb_strlen($name) > 100 || !in_array($category, ['Juegos didácticos', 'Piñatería', 'Fiesta y decoración'], true)
            || $price === false || $price < 100 || $price > 10_000_000 || $stock === false || $stock < 0 || $stock > 9999) {
            $this->respond(422, ['ok' => false, 'message' => 'Revisa el nombre, categoría, precio y existencias del producto.']);
        }
        foreach (['description' => 180, 'badge' => 28, 'image' => 180, 'color' => 30, 'icon' => 16] as $field => $max) {
            if (isset($data[$field]) && mb_strlen((string) $data[$field]) > $max) {
                $this->respond(422, ['ok' => false, 'message' => "El campo {$field} excede la longitud permitida."]);
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

    private function validateUser(array $data, bool $passwordRequired): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $role = (string) ($data['role'] ?? 'cliente');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 90 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120) {
            $this->respond(422, ['ok' => false, 'message' => 'Ingresa un nombre y correo electrónico válidos.']);
        }
        if (($passwordRequired || $password !== '') && (strlen($password) < 8 || strlen($password) > 128)) {
            $this->respond(422, ['ok' => false, 'message' => 'La contraseña debe tener entre 8 y 128 caracteres.']);
        }
        $phone = trim((string) ($data['phone'] ?? ''));
        $city = trim((string) ($data['city'] ?? ''));
        $address = trim((string) ($data['address'] ?? ''));
        if (!in_array($role, ['administrador', 'operador', 'consulta', 'cliente'], true)) {
            $this->respond(422, ['ok' => false, 'message' => 'El perfil seleccionado no es válido.']);
        }
        if (mb_strlen($phone) > 20 || mb_strlen($city) > 60 || mb_strlen($address) > 120) {
            $this->respond(422, ['ok' => false, 'message' => 'Uno de los datos de contacto excede la longitud permitida.']);
        }
        if ($role === 'cliente' && $passwordRequired
            && (!preg_match('/^[0-9+(). -]{7,20}$/', $phone) || mb_strlen($city) < 2 || mb_strlen($address) < 5)) {
            $this->respond(422, ['ok' => false, 'message' => 'Completa un celular, ciudad y dirección de entrega válidos.']);
        }
        return compact('name', 'email', 'password', 'role', 'phone', 'city', 'address');
    }

    private function insertUser(array $data, string $role): int
    {
        try {
            return (int) DB::table('users')->insertGetId([
                'name' => $data['name'],
                'email' => $data['email'],
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                'role' => $role,
                'phone' => $data['phone'],
                'city' => $data['city'],
                'address' => $data['address'],
            ]);
        } catch (\Illuminate\Database\QueryException $error) {
            if (($error->errorInfo[0] ?? null) === '23000' || (int) ($error->errorInfo[1] ?? 0) === 1062) {
                $this->respond(409, ['ok' => false, 'message' => 'Ya existe una cuenta con ese correo electrónico.']);
            }
            throw $error;
        }
    }

    private function currentUser(): ?array
    {
        $id = request()->session()->get('user_id');
        if (!is_int($id) && !ctype_digit((string) $id)) {
            return null;
        }
        $user = DB::table('users')->where('id', (int) $id)->first([
            'id', 'name', 'email', 'role', 'phone', 'city', 'address',
        ]);
        if ($user === null) {
            request()->session()->forget('user_id');
            return null;
        }
        return (array) $user;
    }

    private function requireUser(array $roles = []): array
    {
        $user = $this->currentUser();
        if ($user === null) {
            $this->respond(401, ['ok' => false, 'message' => 'Inicia sesión para continuar.']);
        }
        if ($roles !== [] && !in_array($user['role'], $roles, true)) {
            $this->respond(403, ['ok' => false, 'message' => 'No tienes permiso para realizar esta acción.']);
        }
        return $user;
    }

    private function requireCsrf(Request $request): void
    {
        $token = (string) $request->header('X-CSRF-Token', '');
        if ($token === '' || !hash_equals(csrf_token(), $token)) {
            $this->respond(403, ['ok' => false, 'message' => 'La sesión expiró o la solicitud no es válida. Recarga la página.']);
        }
    }

    private function input(Request $request): array
    {
        $raw = $request->getContent();
        if (strlen($raw) > 1_000_000) {
            $this->respond(413, ['ok' => false, 'message' => 'La solicitud excede el tamaño permitido.']);
        }
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->respond(400, ['ok' => false, 'message' => 'El cuerpo JSON no es válido.']);
        }
        if (!is_array($data)) {
            $this->respond(400, ['ok' => false, 'message' => 'El cuerpo JSON debe ser un objeto.']);
        }
        return $data;
    }

    private function publicUser(array $user): array
    {
        unset($user['password_hash']);
        return $user;
    }

    private function respond(int $status, array $body): never
    {
        throw new HttpResponseException(response()->json($body, $status));
    }
}
