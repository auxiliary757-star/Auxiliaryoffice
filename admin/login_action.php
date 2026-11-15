<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

startSession();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$password = (string) ($_POST['password'] ?? '');
$statement = db()->prepare('SELECT id, full_name, email, user_type, password_hash, role FROM users WHERE email = ? LIMIT 1');
$statement->execute([$email]);
$user = $statement->fetch();

if (!$user || $user['role'] !== 'Administrator' || !$user['password_hash'] || !password_verify($password, $user['password_hash'])) {
    $_SESSION['admin_login_error'] = 'The administrator email or password is incorrect.';
    header('Location: login.php');
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['email'] = $user['email'];
$_SESSION['user_type'] = $user['user_type'];
$_SESSION['role'] = $user['role'];

header('Location: admin.php');
exit;
