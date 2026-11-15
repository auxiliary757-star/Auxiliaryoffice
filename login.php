<?php
declare(strict_types=1);
require_once __DIR__ . '/config/auth.php';

$user = currentUser();
if ($user) {
    header('Location: ' . ($user['role'] === 'Administrator' ? 'admin/admin.php' : 'index.php'));
    exit;
}
$error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sign In | SSU Auxiliary Office</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="style.css" />
    <style>
      .auth-page {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 36px;
        background:
          linear-gradient(135deg, rgba(7, 27, 58, 0.2), rgba(11, 47, 104, 0.08)),
          url("assets/bg-img.jpg") center center / cover no-repeat;
        background-attachment: fixed;
      }
      .auth-layout {
        width: min(100%, 460px);
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.48);
        border-radius: 20px;
        box-shadow: 0 28px 75px rgba(3, 17, 38, 0.28);
        -webkit-backdrop-filter: blur(24px);
        backdrop-filter: blur(24px);
      }
      .auth-panel {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px clamp(24px, 5vw, 42px);
        background: rgba(248, 251, 255, 0.72);
        -webkit-backdrop-filter: blur(16px);
        backdrop-filter: blur(16px);
      }
      .auth-card {
        width: 100%;
        max-width: 360px;
      }
      .auth-brand {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        margin-bottom: 24px;
        text-align: center;
      }
      .auth-brand img {
        width: 66px;
        height: 66px;
        object-fit: contain;
        filter: drop-shadow(0 5px 10px rgba(7, 27, 58, 0.12));
      }
      .auth-brand strong,
      .auth-brand span {
        display: block;
      }
      .auth-brand strong {
        color: var(--blue-950);
        font-family: "Poppins", sans-serif;
        font-size: 0.86rem;
        letter-spacing: 0.04em;
      }
      .auth-brand span {
        margin-top: 2px;
        color: var(--blue-800);
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.16em;
      }
      .auth-card h2 {
        margin-bottom: 8px;
        color: var(--blue-950);
        font-family: "Poppins", sans-serif;
        font-size: clamp(1.65rem, 3vw, 1.9rem);
        line-height: 1.2;
      }
      .auth-card > p {
        margin-bottom: 22px;
        color: var(--muted);
      }
      .auth-card label {
        display: block;
        color: var(--ink);
        font-size: 0.84rem;
        font-weight: 700;
        margin: 13px 0 6px;
      }
      .auth-card input {
        width: 100%;
        min-height: 44px;
        padding: 10px 12px;
        border: 1px solid rgba(11, 47, 104, 0.18);
        border-radius: 9px;
        background: rgba(255, 255, 255, 0.72);
        color: var(--ink);
        outline: 0;
        transition: border-color 160ms ease, box-shadow 160ms ease, background 160ms ease;
      }
      .auth-card input:focus {
        border-color: var(--blue-700);
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 0 0 4px rgba(23, 105, 210, 0.16);
      }
      .auth-card button {
        width: 100%;
        min-height: 46px;
        margin-top: 22px;
        border: 1px solid rgba(255, 255, 255, 0.28);
        border-radius: 9px;
        padding: 10px 16px;
        background: linear-gradient(135deg, rgba(18, 76, 170, 0.96), rgba(11, 47, 104, 0.96));
        color: #fff;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 8px 20px rgba(11, 47, 104, 0.22);
        transition: background 160ms ease, transform 160ms ease, box-shadow 160ms ease;
      }
      .auth-card button:hover {
        background: linear-gradient(135deg, rgba(23, 105, 210, 0.98), rgba(11, 47, 104, 0.98));
        box-shadow: 0 11px 25px rgba(11, 47, 104, 0.3);
        transform: translateY(-1px);
      }
      .auth-card button:focus-visible {
        outline: 3px solid rgba(231, 184, 75, 0.7);
        outline-offset: 3px;
      }
      .auth-card a {
        color: var(--blue-800);
        font-weight: 700;
      }
      .auth-error {
        margin-bottom: 18px;
        padding: 11px 13px;
        border: 1px solid #f5b7b1;
        border-radius: 9px;
        background: rgba(255, 243, 241, 0.92);
        color: #b42318;
        font-size: 0.9rem;
      }
      .auth-footer {
        margin-top: 15px;
        margin-bottom: 0;
        text-align: center;
      }
      @media (max-width: 760px) {
        .auth-page {
          padding: 22px 14px;
        }
        .auth-layout {
          max-width: 460px;
        }
        .auth-panel {
          padding: 32px 26px 34px;
        }
      }
      @media (max-width: 380px) {
        .auth-page {
          padding: 12px;
        }
        .auth-panel {
          padding: 26px 20px 28px;
        }
        .auth-brand img {
          width: 60px;
          height: 60px;
        }
      }
      @media (max-width: 760px) {
        .auth-page {
          background-attachment: scroll;
        }
      }
    </style>
  </head>
  <body>
    <main class="auth-page">
      <div class="auth-layout">
        <section class="auth-panel" aria-labelledby="login-heading">
          <form class="auth-card" method="post" action="login_action.php">
            <div class="auth-brand">
              <img src="assets/LOGO (2).png" alt="Samar State University logo" />
              <div>
                <strong>SAMAR STATE UNIVERSITY</strong>
                <span>AUXILIARY OFFICE</span>
              </div>
            </div>
            <h2 id="login-heading">Welcome back</h2>
            <p>Sign in to continue to your reservation portal.</p>
            <?php if ($error): ?>
            <div class="auth-error" role="alert">
              <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>
            <label for="email">Email address</label>
            <input
              id="email"
              name="email"
              type="email"
              required
              autocomplete="email"
            />
            <label for="password">Password</label>
            <input
              id="password"
              name="password"
              type="password"
              required
              autocomplete="current-password"
            />
            <button type="submit">Sign In</button>
            <p class="auth-footer">
              <a href="forgot_password.php">Forgot password?</a>
            </p>
            <p class="auth-footer">
              New user? <a href="register.php">Create an account</a>
            </p>
            <p class="auth-footer">
              Administrator? <a href="admin/login.php">Sign in here</a>
            </p>
          </form>
        </section>
      </div>
    </main>
  </body>
</html>
