<?php

declare(strict_types=1);

function database_connection(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $projectEnv = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    $fileValues = [];
    if (is_file($projectEnv)) {
        $parsed = parse_ini_file($projectEnv, false, INI_SCANNER_RAW);
        if (is_array($parsed)) {
            $fileValues = $parsed;
        }
    }

    $setting = static function (string $name, string $fallback) use ($fileValues): string {
        $environmentValue = getenv($name);
        if ($environmentValue !== false) {
            return $environmentValue;
        }
        return isset($fileValues[$name]) ? (string) $fileValues[$name] : $fallback;
    };

    $host = $setting('DB_HOST', '127.0.0.1');
    $port = $setting('DB_PORT', '3306');
    $database = $setting('DB_NAME', 'db_gdgevents');
    $username = $setting('DB_USER', 'root');
    $password = $setting('DB_PASSWORD', '');

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);
    $connection = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $connection;
}
