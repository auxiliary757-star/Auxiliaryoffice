<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

$user = currentUser();
if ($user && $user['role'] === 'Administrator') {
    header('Location: admin.php');
    exit;
}
$error = $_SESSION['admin_login_error'] ?? null;
unset($_SESSION['admin_login_error']);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Administrator Sign In | SSU Auxiliary Office</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="../style.css" />
    <style>
      .admin-login-page {
        min-height: 100vh;
        display: grid;
        place-items: center;
        padding: 24px;
        background: linear-gradient(135deg, rgba(7, 27, 58, .72), rgba(11, 47, 104, .5)), url("../assets/bg-img.jpg") center / cover no-repeat;
      }
      .admin-login-card {
        width: min(100%, 420px);
        padding: 38px;
        border: 1px solid rgba(255, 255, 255, .6);
        border-radius: 18px;
        background: rgba(255, 255, 255, .94);
        box-shadow: 0 24px 60px rgba(3, 17, 38, .3);
      }
      .admin-login-card img { display: block; width: 66px; height: 66px; object-fit: contain; margin: 0 auto 16px; }
      .admin-login-card h1, .admin-login-card > p { text-align: center; }
      .admin-login-card h1 { margin-bottom: 8px; color: var(--blue-950); font-family: "Poppins", sans-serif; font-size: 1.65rem; }
      .admin-login-card > p { margin-bottom: 22px; color: var(--muted); }
      .admin-login-card label { display: block; margin: 13px 0 6px; font-size: .84rem; font-weight: 700; }
      .admin-login-card input { width: 100%; min-height: 44px; padding: 10px 12px; border: 1px solid rgba(11, 47, 104, .2); border-radius: 8px; }
      .admin-login-card button { width: 100%; min-height: 46px; margin-top: 20px; border: 0; border-radius: 8px; background: var(--blue-800); color: #fff; font-weight: 700; cursor: pointer; }
      .admin-login-card a { color: var(--blue-800); font-weight: 700; }
      .admin-login-error { padding: 10px 12px; border: 1px solid #f5b7b1; border-radius: 8px; background: #fff3f1; color: #b42318; }
      .admin-login-footer { margin: 16px 0 0; text-align: center; }
      @media (max-width: 420px) { .admin-login-card { padding: 28px 22px; } }
    </style>
  </head>
  <body>
    <main class="admin-login-page">
      <form class="admin-login-card" method="post" action="login_action.php">
        <img src="../assets/LOGO (2).png" alt="Samar State University logo" />
        <h1>Administrator Sign In</h1>
        <p>Access the Auxiliary Office reservation dashboard.</p>
        <?php if ($error): ?>
        <div class="admin-login-error" role="alert">
          <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>
        <label for="email">Administrator email</label>
        <input id="email" name="email" type="email" required autocomplete="username" />
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required autocomplete="current-password" />
        <button type="submit">Sign In</button>
        <p class="admin-login-footer"><a href="../login.php">Return to user sign in</a></p>
      </form>
    </main>
  </body>
</html>
