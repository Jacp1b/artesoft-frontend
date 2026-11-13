<?php
declare(strict_types=1);

function artesoft_database(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $dsn = getenv('ARTESOFT_DB_DSN');
    if ($dsn === false || $dsn === '') {
        $host = getenv('ARTESOFT_DB_HOST') ?: '127.0.0.1';
        $name = getenv('ARTESOFT_DB_NAME') ?: 'artesoft';
        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
    }

    $user = getenv('ARTESOFT_DB_USER') ?: 'root';
    $password = getenv('ARTESOFT_DB_PASSWORD') ?: '';

    $connection = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $connection;
}
