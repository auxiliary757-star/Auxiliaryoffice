<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function db(): PDO
{
    static $connection;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = getenv('SSU_DB_HOST') ?: '127.0.0.1';
    $port = getenv('SSU_DB_PORT') ?: '3306';
    $name = getenv('SSU_DB_NAME') ?: 'ssu_reservations';
    $user = getenv('SSU_DB_USER') ?: 'root';
    $password = getenv('SSU_DB_PASSWORD') ?: '';

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $connection = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $connection;
}

function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function requestJson(): array
{
    $payload = json_decode(file_get_contents('php://input'), true);
    return is_array($payload) ? $payload : $_POST;
}

function requireMethod(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
}
