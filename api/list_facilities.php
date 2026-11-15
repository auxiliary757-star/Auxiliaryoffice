<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

try {
    $statement = db()->query('SELECT id, name, location, image, capacity, equipment, description FROM facilities ORDER BY id');
    jsonResponse($statement->fetchAll());
} catch (Throwable $error) {
    error_log($error->getMessage());
    jsonResponse(['success' => false, 'message' => 'Unable to load facilities.'], 500);
}
