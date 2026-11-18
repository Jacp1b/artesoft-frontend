<?php
if (
    !isset($report, $reportType)
    || !is_array($report)
    || !in_array($reportType, ['summary', 'orders', 'products'], true)
) {
    http_response_code(404);
    exit;
}

$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$money = static fn(mixed $value): string => '$ ' . number_format((float) $value, 0, ',', '.');
$maxMonth = max(array_map(static fn(array $row): int => (int) $row['sales'], $report['monthlySales'] ?: [['sales' => 0]]));
$maxSeller = max(array_map(static fn(array $row): int => (int) $row['sales'], $report['salespeople'] ?: [['sales' => 0]]));
$maxProduct = max(array_map(static fn(array $row): int => (int) $row['sales'], $report['topProducts'] ?: [['sales' => 0]]));
$topSellers = [];
$topSellerSales = 0;
foreach ($report['salespeople'] as $seller) {
    if ((int) $seller['sales'] > $topSellerSales) {
        $topSellerSales = (int) $seller['sales'];
        $topSellers = [$seller];
    } elseif ($topSellerSales > 0 && (int) $seller['sales'] === $topSellerSales) {
        $topSellers[] = $seller;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<?php require __DIR__ . '/report_pdf_styles.php'; ?>
</head>
<body>
<header class="masthead">
  <div class="brand">ARTESOFT <span style="color:#b5cd42">/</span> INTELIGENCIA COMERCIAL</div>
  <?php if ($reportType === 'orders'): ?>
    <h1>Reporte detallado de pedidos</h1>
    <div class="subtitle">Seguimiento de ventas, clientes, vendedores y estados</div>
  <?php elseif ($reportType === 'products'): ?>
    <h1>Reporte de inventario</h1>
    <div class="subtitle">Catálogo, existencias disponibles y alertas de reposición</div>
  <?php else: ?>
    <h1>Reporte de ventas e inventario</h1>
    <div class="subtitle">Desempeño, tendencias, equipo comercial y productos con ventas registradas</div>
  <?php endif; ?>
  <div class="generated">Generado: <?= $escape(date('d/m/Y H:i', strtotime($report['generatedAt']))) ?> · Todos los valores en pesos colombianos (COP).</div>
</header>

<?php if ($reportType === 'summary'): ?>
<table class="summary"><tr>
  <td><span class="label">Ventas registradas</span><strong class="value"><?= $money($report['sales']) ?></strong></td>
  <td><span class="label">Pedidos guardados</span><strong class="value"><?= number_format((int) $report['orders'], 0, ',', '.') ?></strong></td>
  <td><span class="label">Por atender</span><strong class="value"><?= number_format((int) $report['openOrders'], 0, ',', '.') ?></strong></td>
  <td><span class="label">Productos activos</span><strong class="value"><?= number_format((int) $report['products'], 0, ',', '.') ?></strong></td>
</tr></table>

<?php if ($report['testOrders'] > 0): ?>
<div class="feature">
  <strong>Atención: este informe incluye <?= number_format((int) $report['testOrders'], 0, ',', '.') ?> pedidos de prueba</strong>
  <span> (<?= $money($report['testSales']) ?>). Están identificados en el detalle y no representan ventas reales.</span>
</div>
<?php endif; ?>

<div class="feature">
  <span class="label">Empleados con mayor importe atribuido (incluye pruebas)</span><br>
  <?php if ($topSellers !== []): ?>
    <?php foreach ($topSellers as $index => $seller): ?>
      <?php if ($index > 0): ?> · <?php endif; ?>
      <strong><?= $escape($seller['name']) ?></strong>
      <span> · <?= $money($seller['sales']) ?> en <?= number_format((int) $seller['order_count'], 0, ',', '.') ?> pedidos (<?= number_format((int) $seller['test_order_count'], 0, ',', '.') ?> de prueba)</span>
    <?php endforeach; ?>
  <?php else: ?>
    <strong>Aún no hay ventas atribuidas a una cuenta de administración u operación.</strong>
  <?php endif; ?>
</div>

<h2>Tendencia mensual de ventas</h2>
<p class="section-note">Últimos seis meses calendario. Incluye pedidos registrados y de prueba; las demostraciones están excluidas.</p>
<table class="data">
  <thead><tr><th>Mes</th><th class="bar-cell">Ventas</th><th class="right">Importe</th><th class="right">Pedidos</th></tr></thead>
  <tbody>
  <?php foreach ($report['monthlySales'] as $row): $width = $maxMonth > 0 ? min(100, (int) $row['sales'] * 100 / $maxMonth) : 0; ?>
    <tr>
      <td><?= $escape($row['label']) ?></td>
      <td><div class="track"><div class="bar" style="width:<?= number_format($width, 2, '.', '') ?>%"></div></div></td>
      <td class="right"><?= $money($row['sales']) ?></td>
      <td class="right"><?= number_format((int) $row['orders'], 0, ',', '.') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2>Distribución por estado</h2>
<table class="data">
<thead><tr><th>Estado del pedido</th><th class="right">Pedidos</th><th class="right">Prueba</th><th class="right">Ventas</th></tr></thead>
  <tbody>
  <?php if ($report['byStatus']): foreach ($report['byStatus'] as $row): ?>
    <tr><td><?= $escape($row['status']) ?></td><td class="right"><?= number_format((int) $row['count'], 0, ',', '.') ?></td><td class="right"><?= number_format((int) $row['test_count'], 0, ',', '.') ?></td><td class="right"><?= $money($row['sales']) ?></td></tr>
  <?php endforeach; else: ?>
    <tr><td colspan="4" class="empty">No hay pedidos registrados.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
<div class="footer">ARTESOFT · Reporte interno · <?= number_format((int) $report['lowStock'], 0, ',', '.') ?> productos tienen existencias bajas (9 unidades o menos).</div>

<section class="page-break">
  <h2>Ventas por empleado</h2>
  <p class="section-note">Solo se atribuyen pedidos creados durante una sesión de administrador u operador. Las cantidades incluyen pedidos de prueba, identificados en observaciones.</p>
  <table class="data">
    <thead><tr><th>Empleado</th><th>Perfil</th><th class="bar-cell">Ventas registradas</th><th class="right">Importe</th><th class="right">Pedidos</th><th class="right">Prueba</th></tr></thead>
    <tbody>
    <?php if ($report['salespeople']): foreach ($report['salespeople'] as $seller): $width = $maxSeller > 0 ? min(100, (int) $seller['sales'] * 100 / $maxSeller) : 0; ?>
      <tr>
        <td><?= $escape($seller['name']) ?></td>
        <td><?= $escape(ucfirst($seller['role'])) ?></td>
        <td><div class="track"><div class="bar green" style="width:<?= number_format($width, 2, '.', '') ?>%"></div></div></td>
        <td class="right"><?= $money($seller['sales']) ?></td>
        <td class="right"><?= number_format((int) $seller['order_count'], 0, ',', '.') ?></td>
        <td class="right"><?= number_format((int) $seller['test_order_count'], 0, ',', '.') ?></td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="6" class="empty">No hay cuentas de administración u operación.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <h2>Productos más vendidos</h2>
  <p class="section-note">Clasificación por unidades vendidas e importe acumulado de las líneas de pedido.</p>
  <table class="data">
    <thead><tr><th>#</th><th>Producto</th><th class="bar-cell">Ventas</th><th class="right">Unidades</th><th class="right">Importe</th></tr></thead>
    <tbody>
    <?php if ($report['topProducts']): foreach ($report['topProducts'] as $index => $product): $width = $maxProduct > 0 ? min(100, (int) $product['sales'] * 100 / $maxProduct) : 0; ?>
      <tr>
        <td><?= $index + 1 ?></td>
        <td><?= $escape($product['name']) ?></td>
        <td><div class="track"><div class="bar peach" style="width:<?= number_format($width, 2, '.', '') ?>%"></div></div></td>
        <td class="right"><?= number_format((int) $product['units'], 0, ',', '.') ?></td>
        <td class="right"><?= $money($product['sales']) ?></td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="5" class="empty">No hay ventas reales por producto.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  <div class="footer">ARTESOFT · Reporte interno generado a partir de la base de datos.</div>
<?php elseif ($reportType === 'orders'): ?>
  <table class="summary"><tr>
    <td><span class="label">Pedidos incluidos</span><strong class="value"><?= number_format($report['orderCount'], 0, ',', '.') ?></strong></td>
    <td><span class="label">Ventas totales</span><strong class="value"><?= $money($report['orderSales']) ?></strong></td>
    <td><span class="label">Promedio por pedido</span><strong class="value"><?= $money($report['orderAverage']) ?></strong></td>
    <td><span class="label">Pedidos de prueba</span><strong class="value"><?= number_format($report['orderTestCount'], 0, ',', '.') ?></strong></td>
  </tr></table>
  <?php if ($report['orderStatusCounts']): ?>
    <h2>Pedidos por estado</h2>
    <table class="data">
      <thead><tr><th>Estado</th><th class="bar-cell">Proporción</th><th class="right">Pedidos</th></tr></thead>
      <tbody>
      <?php foreach ($report['orderStatusCounts'] as $status => $count): $width = $report['orderCount'] > 0 ? $count * 100 / $report['orderCount'] : 0; ?>
        <tr><td><?= $escape($status) ?></td><td><div class="track"><div class="bar green" style="width:<?= number_format($width, 2, '.', '') ?>%"></div></div></td><td class="right"><?= number_format($count, 0, ',', '.') ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
  <h2>Detalle de pedidos</h2>
  <p class="section-note">Incluye pedidos registrados, de prueba y de demostración. Cada registro identifica al cliente, vendedor y contenido de la compra.</p>
  <table class="data">
    <thead><tr><th>Pedido / fecha</th><th>Cliente</th><th>Vendedor</th><th>Productos</th><th>Tipo / estado</th><th class="right">Total</th></tr></thead>
    <tbody>
    <?php if ($report['ordersData']): foreach ($report['ordersData'] as $order): ?>
      <tr>
        <td><strong><?= $escape($order['order_id']) ?></strong><br><span class="muted"><?= $escape(date('d/m/Y', strtotime($order['created_at']))) ?></span></td>
        <td><?= $escape($order['customer_name']) ?><br><span class="muted"><?= $escape($order['customer_email']) ?></span></td>
        <td><?= $escape($order['seller_name']) ?></td>
        <td><?php if ($order['items']): foreach ($order['items'] as $item): ?><?= number_format((int) $item['quantity'], 0, ',', '.') ?> × <?= $escape($item['product_name']) ?><br><?php endforeach; else: ?><span class="muted">Sin detalle</span><?php endif; ?></td>
        <td><span class="tag <?= $escape($order['type_class']) ?>"><?= $escape($order['type_label']) ?></span><br><?= $escape($order['status']) ?></td>
        <td class="right"><?= $money($order['total']) ?></td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="6" class="empty">No hay pedidos registrados.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  <div class="footer">ARTESOFT · Reporte de pedidos generado a partir de la base de datos.</div>
<?php else: ?>
  <table class="summary"><tr>
    <td><span class="label">Referencias</span><strong class="value"><?= number_format($report['productCount'], 0, ',', '.') ?></strong></td>
    <td><span class="label">Unidades disponibles</span><strong class="value"><?= number_format($report['totalUnits'], 0, ',', '.') ?></strong></td>
    <td><span class="label">Valor del inventario</span><strong class="value"><?= $money($report['stockValue']) ?></strong></td>
    <td><span class="label">Requieren atención</span><strong class="value"><?= number_format($report['lowStockCount'] + $report['outOfStockCount'], 0, ',', '.') ?></strong></td>
  </tr></table>
  <div class="feature"><strong><?= number_format($report['outOfStockCount'], 0, ',', '.') ?> agotados</strong><br><span class="muted"><?= number_format($report['lowStockCount'], 0, ',', '.') ?> referencias con cinco unidades o menos requieren reposición.</span></div>
  <h2>Existencias por categoría</h2>
  <table class="data">
    <thead><tr><th>Categoría</th><th class="bar-cell">Unidades</th><th class="right">Referencias</th></tr></thead>
    <tbody>
    <?php $maxCategoryStock = max($report['categoryStats'] ?: [0]); foreach ($report['categoryStats'] as $category => $units):
      $categoryProducts = count(array_filter($report['inventoryProducts'], static fn(array $product): bool => $product['category'] === $category));
      $width = $maxCategoryStock > 0 ? $units * 100 / $maxCategoryStock : 0; ?>
      <tr><td><?= $escape($category) ?></td><td><div class="track"><div class="bar" style="width:<?= number_format($width, 2, '.', '') ?>%"></div></div></td><td class="right"><?= number_format($categoryProducts, 0, ',', '.') ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <h2>Detalle del inventario</h2>
  <p class="section-note">Valor calculado con el precio de venta multiplicado por las unidades disponibles.</p>
  <table class="data">
    <thead><tr><th>ID</th><th>Producto</th><th>Categoría</th><th class="right">Precio</th><th class="right">Unidades</th><th>Estado</th><th class="right">Valor en stock</th></tr></thead>
    <tbody>
    <?php if ($report['inventoryProducts']): foreach ($report['inventoryProducts'] as $product): ?>
      <tr>
        <td><?= $escape($product['product_id']) ?></td>
        <td><strong><?= $escape($product['name']) ?></strong><?php if ($product['description'] !== ''): ?><br><span class="muted"><?= $escape($product['description']) ?></span><?php endif; ?></td>
        <td><?= $escape($product['category']) ?></td>
        <td class="right"><?= $money($product['price']) ?></td>
        <td class="right"><?= number_format((int) $product['stock'], 0, ',', '.') ?></td>
        <td class="<?= $escape($product['stock_class']) ?>"><?= $escape($product['stock_state']) ?></td>
        <td class="right"><?= $money((int) $product['stock'] * (float) $product['price']) ?></td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="7" class="empty">No hay productos registrados.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  <div class="footer">ARTESOFT · Reporte de inventario generado a partir de la base de datos.</div>
<?php endif; ?>
</section>
</body>
</html>
