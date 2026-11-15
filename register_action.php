<?php declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/services/notifications/PhoneNumber.php';

startSession();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}
$name     = trim((string) ($_POST['full_name'] ?? ''));
$email    = strtolower(trim((string) ($_POST['email'] ?? '')));
$phone    = trim((string) ($_POST['phone'] ?? ''));
$type     = trim((string) ($_POST['user_type'] ?? 'Faculty'));
$password = (string) ($_POST['password'] ?? '');
$passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
if (!in_array($type, ['Faculty', 'Student'], true) || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || $password !== $passwordConfirmation) {
    $_SESSION['register_error'] = 'Enter a valid name, email, and password of at least 8 characters.';
    header('Location: register.php');
    exit;
}
if ($phone !== '') {
    try {
        $phone = normalizePhilippinePhone($phone);
    } catch (InvalidArgumentException $error) {
        $_SESSION['register_error'] = $error->getMessage();
        header('Location: register.php');
        exit;
    }
}
$role = 'User';
try {
    $statement = db()->prepare('INSERT INTO users (full_name, email, phone, user_type, password_hash, role) VALUES (?, ?, ?, ?, ?, ?)');
    $statement->execute([$name, $email, $phone !== '' ? $phone : null, $type, password_hash($password, PASSWORD_DEFAULT), $role]);
    header('Location: login.php');
    exit;
} catch (PDOException $error) {
    $_SESSION['register_error'] = $error->getCode() === '23000' ? 'That email address is already registered.' : 'Unable to create the account.';
    header('Location: register.php');
    exit;
}
