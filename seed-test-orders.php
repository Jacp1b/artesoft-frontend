<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/seed-test-orders.php';

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
    'path' => '/',
]);
session_start();

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Método no permitido.');
}

$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string) $userId)) {
    http_response_code(401);
    exit('Inicia sesión con una cuenta administradora y vuelve a abrir esta página.');
}

try {
    $statement = artesoft_database()->prepare('SELECT role FROM users WHERE id = :id');
    $statement->execute(['id' => (int) $userId]);
    if ($statement->fetchColumn() !== 'administrador') {
        http_response_code(403);
        exit('Esta acción requiere una cuenta administradora.');
    }
} catch (Throwable $error) {
    error_log('ARTESOFT seeder authorization failed: ' . $error->getMessage());
    http_response_code(500);
    exit('No se pudo verificar la cuenta. Inténtalo más tarde.');
}

$result = null;
$errorMessage = null;
if ($method === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('La sesión expiró o la solicitud no es válida. Recarga la página.');
    }

    try {
        $result = artesoft_seed_test_orders();
    } catch (Throwable $error) {
        error_log('ARTESOFT web seeder failed: ' . $error->getMessage());
        http_response_code(500);
        $errorMessage = 'No se pudo ejecutar el seeder. Revisa que la instalación esté inicializada y consulta el registro de errores del hosting.';
    }
}

$csrfToken = $_SESSION['csrf_token'] ?? '';
if (!is_string($csrfToken) || $csrfToken === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $csrfToken = $_SESSION['csrf_token'];
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ejecutar pedidos de prueba · ARTESOFT</title>
</head>
<body>
  <main>
    <h1>Ejecutar pedidos de prueba</h1>
    <p>Esta herramienta crea hasta ocho pedidos de prueba. No modifica las existencias y no duplica pedidos ya creados.</p>
    <?php if ($result !== null): ?>
      <p role="status">Pedidos creados: <?= (int) $result['created'] ?>. Ya existentes: <?= (int) $result['skipped'] ?>.</p>
      <p>Por seguridad, elimina el archivo <code>seed-test-orders.php</code> del hosting cuando termines.</p>
    <?php elseif ($errorMessage !== null): ?>
      <p role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
    <?php else: ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" onclick="return confirm('¿Crear los pedidos de prueba?')">Ejecutar seeder</button>
      </form>
    <?php endif; ?>
    <p><a href="index.php">Volver a ARTESOFT</a></p>
  </main>
</body>
</html>
