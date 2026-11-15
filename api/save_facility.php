<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

requireMethod('POST');
requireRole('Administrator', true);
$payload = requestJson();
$id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
$name = trim((string) ($payload['name'] ?? ''));
$location = trim((string) ($payload['location'] ?? ''));
$image = trim((string) ($payload['image'] ?? ''));
$capacity = trim((string) ($payload['capacity'] ?? ''));
$equipment = trim((string) ($payload['equipment'] ?? ''));
$description = trim((string) ($payload['description'] ?? ''));

if ($name === '' || $location === '' || $image === '' || $capacity === '') {
    jsonResponse(['success' => false, 'message' => 'Facility name, location, image, and capacity are required.'], 422);
}

try {
    $pdo = db();
    if ($id) {
        $statement = $pdo->prepare('UPDATE facilities SET name = ?, location = ?, image = ?, capacity = ?, equipment = ?, description = ? WHERE id = ?');
        $statement->execute([$name, $location, $image, $capacity, $equipment, $description, $id]);
    } else {
        $statement = $pdo->prepare('INSERT INTO facilities (name, location, image, capacity, equipment, description) VALUES (?, ?, ?, ?, ?, ?)');
        $statement->execute([$name, $location, $image, $capacity, $equipment, $description]);
    }
    jsonResponse(['success' => true]);
} catch (Throwable $error) {
    error_log($error->getMessage());
    jsonResponse(['success' => false, 'message' => 'Unable to save facility.'], 500);
}
