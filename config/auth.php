<?php
declare(strict_types=1);
require_once __DIR__ . '/database.php';

function startSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function currentUser(): ?array
{
    startSession();
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $statement = db()->prepare('SELECT id, full_name, email, phone, user_type, role FROM users WHERE id = ? LIMIT 1');
    $statement->execute([(int) $_SESSION['user_id']]);
    $user = $statement->fetch();
    if (!$user) {
        unset(
            $_SESSION['user_id'],
            $_SESSION['full_name'],
            $_SESSION['email'],
            $_SESSION['user_type'],
            $_SESSION['role']
        );
        return null;
    }

    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['role'] = $user['role'];

    return [
        'id' => (int) $user['id'],
        'name' => (string) $user['full_name'],
        'email' => (string) $user['email'],
        'phone' => (string) ($user['phone'] ?? ''),
        'role' => (string) $user['role'],
        'user_type' => (string) $user['user_type'],
    ];
}

function requireLogin(bool $json = false): array
{
    $user = currentUser();
    if ($user) return $user;

    if ($json) {
        jsonResponse(['success' => false, 'message' => 'Login is required.'], 401);
    }

    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $prefix = str_contains($script, '/api/') ? '../' : '';
    $loginPage = str_contains($script, '/admin/') ? 'login.php' : $prefix . 'login.php';
    header('Location: ' . $loginPage);
    exit;
}

function requireRole(string $role, bool $json = false): array
{
    $user = requireLogin($json);
    if ($user['role'] === $role) return $user;

    if ($json) {
        jsonResponse(['success' => false, 'message' => 'You are not authorized to access this resource.'], 403);
    }

    if ($role === 'Administrator') {
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $prefix = str_contains($script, '/admin/') ? '' : 'admin/';
        header('Location: ' . $prefix . 'login.php');
        exit;
    }

    http_response_code(403);
    exit('Forbidden');
}
