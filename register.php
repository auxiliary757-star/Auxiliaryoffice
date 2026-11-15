<?php
declare(strict_types=1);
require_once __DIR__ . '/config/auth.php';

if (currentUser()) { header('Location: index.php'); exit; }
$error = $_SESSION['register_error'] ?? null;
unset($_SESSION['register_error']);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Create Account | SSU Auxiliary Office</title>
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
        padding: 30px;
        background:
          linear-gradient(135deg, rgba(7, 27, 58, 0.2), rgba(11, 47, 104, 0.08)),
          url("assets/bg-img.jpg") center center / cover no-repeat;
        background-attachment: fixed;
      }
      .auth-layout {
        width: min(100%, 500px);
        overflow: hidden;
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
        padding: 32px clamp(24px, 5vw, 42px);
        background: rgba(248, 251, 255, 0.76);
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
        margin-bottom: 20px;
        text-align: center;
      }
      .auth-brand img {
        width: 62px;
        height: 62px;
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
      .auth-card h1 {
        color: var(--blue-950);
        font-family: "Poppins", sans-serif;
        font-size: 1.75rem;
        line-height: 1.2;
        margin-bottom: 6px;
      }
      .auth-card > p {
        color: var(--muted);
        margin-bottom: 18px;
      }
      .auth-card label {
        display: block;
        color: var(--ink);
        font-size: 0.84rem;
        font-weight: 700;
        margin: 11px 0 5px;
      }
      .auth-card input,
      .auth-card select {
        width: 100%;
        min-height: 42px;
        padding: 9px 12px;
        border: 1px solid rgba(11, 47, 104, 0.18);
        border-radius: 9px;
        background: rgba(255, 255, 255, 0.76);
        color: var(--ink);
        outline: 0;
        transition: border-color 160ms ease, box-shadow 160ms ease, background 160ms ease;
      }
      .auth-card input:focus,
      .auth-card select:focus {
        border-color: var(--blue-700);
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 0 0 4px rgba(23, 105, 210, 0.16);
      }
      .auth-select {
        position: relative;
      }
      .auth-select::after {
        content: "";
        position: absolute;
        top: 50%;
        right: 16px;
        width: 8px;
        height: 8px;
        border-right: 2px solid var(--blue-800);
        border-bottom: 2px solid var(--blue-800);
        pointer-events: none;
        transform: translateY(-65%) rotate(45deg);
      }
      .auth-card select {
        padding-right: 42px;
        appearance: none;
        cursor: pointer;
      }
      .auth-card button {
        width: 100%;
        min-height: 46px;
        margin-top: 20px;
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
        margin-bottom: 14px;
        padding: 10px 12px;
        border: 1px solid #f5b7b1;
        border-radius: 9px;
        background: rgba(255, 243, 241, 0.92);
        color: #b42318;
        font-size: 0.9rem;
      }
      .auth-footer {
        margin-top: 14px;
        margin-bottom: 0;
        text-align: center;
      }
      @media (max-width: 760px) {
        .auth-page {
          padding: 20px 14px;
          background-attachment: scroll;
        }
        .auth-layout {
          max-width: 460px;
        }
      }
      @media (max-width: 380px) {
        .auth-page {
          padding: 12px;
        }
        .auth-panel {
          padding: 26px 20px;
        }
        .auth-brand img {
          width: 56px;
          height: 56px;
        }
        .auth-card h1 {
          font-size: 1.6rem;
        }
      }
      @media (max-height: 760px) {
        .auth-page {
          align-items: flex-start;
        }
      }
    </style>
  </head>
  <body>
    <main class="auth-page">
      <div class="auth-layout">
        <section class="auth-panel" aria-labelledby="register-heading">
          <form class="auth-card" method="post" action="register_action.php">
            <div class="auth-brand">
              <img src="assets/LOGO (2).png" alt="Samar State University logo" />
              <div>
                <strong>SAMAR STATE UNIVERSITY</strong>
                <span>AUXILIARY OFFICE</span>
              </div>
            </div>
            <h1 id="register-heading">Create account</h1>
            <p>Register to submit and track your reservations.</p>
            <?php if ($error): ?>
            <div class="auth-error" role="alert">
              <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>
            <label for="full_name">Full name</label>
            <input id="full_name" name="full_name" required autocomplete="name" />
            <label for="email">Email address</label>
            <input
              id="email"
              name="email"
              type="email"
              required
              autocomplete="email"
            />
            <label for="phone">Mobile number (optional, for SMS updates)</label>
            <input id="phone" name="phone" type="tel" autocomplete="tel" placeholder="09XXXXXXXXX" />
            <label for="user_type">User type</label>
            <div class="auth-select">
              <select id="user_type" name="user_type" required>
                <option value="Faculty">Admin</option>
                <option value="Student">Student</option>
              </select>
            </div>
            <label for="password">Password</label>
            <input
              id="password"
              name="password"
              type="password"
              minlength="8"
              required
              autocomplete="new-password"
            />
            <label for="password_confirmation">Confirm password</label>
            <input
              id="password_confirmation"
              name="password_confirmation"
              type="password"
              minlength="8"
              required
              autocomplete="new-password"
            />
            <button type="submit">Create Account</button>
            <p class="auth-footer">
              Already registered? <a href="login.php">Sign in</a>
            </p>
          </form>
        </section>
      </div>
    </main>
  </body>
</html>
