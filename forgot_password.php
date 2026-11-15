<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/services/auth/PasswordReset.php';

startSession();
$notice = null;
$error = null;
$emailValue = '';
$showOtpForm = false;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $emailValue = $email;
    $mode = (string) ($_POST['mode'] ?? 'request_code');

    if ($mode === 'request_code') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } else {
            $emailDeliveryEnabled = passwordResetEmailDeliveryEnabled();
            try {
                requestPasswordResetOtp(db(), $email);
                $notice = $emailDeliveryEnabled
                    ? 'If an eligible account exists for that email, a six-digit code has been sent. Enter it below; codes expire after 10 minutes.'
                    : 'Email delivery is disabled. Reset codes are written to the PHP error log for local testing, not emailed. Configure SMTP and set NOTIFICATIONS_ENABLED=true to email codes.';
                $showOtpForm = true;
            } catch (Throwable $exception) {
                error_log('Password reset code request failed: ' . $exception->getMessage());
                $error = 'We could not process the reset-code request. Check the server configuration and try again.';
                $showOtpForm = true;
            }
        }
    } elseif ($mode === 'reset_password') {
        $otp = trim((string) ($_POST['otp'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        $showOtpForm = true;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } elseif (!preg_match('/^\d{6}$/', $otp)) {
            $error = 'Enter the six-digit code from your email.';
        } elseif (strlen($password) < 8) {
            $error = 'Choose a password with at least 8 characters.';
        } elseif ($password !== $confirmation) {
            $error = 'The passwords do not match.';
        } else {
            try {
                $result = resetUserPasswordWithOtp(db(), $email, $otp, $password);
                if ($result === 'success') {
                    $success = true;
                    $notice = 'Your password has been updated. You can now sign in.';
                    $showOtpForm = false;
                } else {
                    $error = 'That code is invalid, expired, or has been used too many times. Request a new code and try again.';
                }
            } catch (Throwable $exception) {
                error_log('Password reset failed: ' . $exception->getMessage());
                $error = 'Unable to reset your password right now. Please try again.';
            }
        }
    } else {
        $error = 'Invalid password reset request.';
    }

    $_SESSION['forgot_password_state'] = [
        'notice' => $notice,
        'error' => $error,
        'email' => $emailValue,
        'show_otp' => $showOtpForm,
        'success' => $success,
    ];
    header('Location: forgot_password.php');
    exit;
}

$state = $_SESSION['forgot_password_state'] ?? [];
unset($_SESSION['forgot_password_state']);
$notice = $state['notice'] ?? null;
$error = $state['error'] ?? null;
$emailValue = $state['email'] ?? '';
$showOtpForm = (bool) ($state['show_otp'] ?? false);
$success = (bool) ($state['success'] ?? false);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Forgot Password | SSU Auxiliary Office</title>
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
        width: min(100%, 480px);
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.48);
        border-radius: 20px;
        box-shadow: 0 28px 75px rgba(3, 17, 38, 0.28);
        -webkit-backdrop-filter: blur(24px);
        backdrop-filter: blur(24px);
      }
      .auth-panel {
        display: flex;
        justify-content: center;
        padding: 38px clamp(24px, 5vw, 42px);
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
      .auth-kicker {
        color: var(--blue-700);
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0.14em;
      }
      .auth-card h1 {
        margin: 7px 0 8px;
        color: var(--blue-950);
        font-family: "Poppins", sans-serif;
        font-size: clamp(1.55rem, 3vw, 1.85rem);
        line-height: 1.2;
      }
      .auth-description {
        margin-bottom: 18px;
        color: var(--muted);
        line-height: 1.6;
      }
      .auth-card label {
        display: block;
        margin: 13px 0 6px;
        color: var(--ink);
        font-size: 0.84rem;
        font-weight: 700;
      }
      .auth-card input {
        width: 100%;
        min-height: 46px;
        padding: 10px 12px;
        border: 1px solid rgba(11, 47, 104, 0.2);
        border-radius: 9px;
        background: rgba(255, 255, 255, 0.86);
        color: var(--ink);
        outline: 0;
        transition: border-color 160ms ease, box-shadow 160ms ease, background 160ms ease;
      }
      .auth-card input:focus {
        border-color: var(--blue-700);
        background: #fff;
        box-shadow: 0 0 0 4px rgba(23, 105, 210, 0.16);
      }
      .auth-card button {
        width: 100%;
        min-height: 46px;
        margin-top: 16px;
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
        background: linear-gradient(135deg, rgba(23, 105, 210, 0.98), rgba(11, 47, 104, 0.96));
        box-shadow: 0 11px 25px rgba(11, 47, 104, 0.3);
        transform: translateY(-1px);
      }
      .auth-card button:focus-visible,
      .auth-card a:focus-visible {
        outline: 3px solid rgba(231, 184, 75, 0.8);
        outline-offset: 3px;
      }
      .auth-notice {
        margin: 0 0 16px;
        padding: 11px 13px;
        border: 1px solid rgba(18, 76, 170, 0.2);
        border-radius: 9px;
        background: rgba(220, 234, 255, 0.74);
        color: var(--blue-950);
        font-size: 0.88rem;
        line-height: 1.5;
      }
      .auth-error {
        margin: 0 0 16px;
        padding: 11px 13px;
        border: 1px solid #f5b7b1;
        border-radius: 9px;
        background: rgba(255, 243, 241, 0.92);
        color: #b42318;
        font-size: 0.88rem;
        line-height: 1.5;
      }
      .auth-card input.auth-otp {
        text-align: center;
        font-size: 1.35rem;
        font-weight: 800;
        letter-spacing: 0.45em;
      }
      .auth-security-note {
        margin: 15px 0 0;
        color: var(--muted);
        font-size: 0.78rem;
        line-height: 1.5;
      }
      .auth-card a {
        color: var(--blue-800);
        font-weight: 700;
        text-decoration-thickness: 1px;
        text-underline-offset: 3px;
      }
      .auth-footer {
        margin: 18px 0 0;
        text-align: center;
        font-size: 0.88rem;
      }
      @media (max-width: 760px) {
        .auth-page {
          padding: 22px 14px;
          background-attachment: scroll;
        }
        .auth-panel {
          padding: 32px 26px;
        }
      }
      @media (max-width: 380px) {
        .auth-page {
          padding: 12px;
        }
        .auth-panel {
          padding: 26px 20px;
        }
      }
    </style>
  </head>
  <body>
    <main class="auth-page">
      <div class="auth-layout">
        <section class="auth-panel" aria-labelledby="forgot-password-heading">
          <div class="auth-card">
            <div class="auth-brand">
              <img src="assets/LOGO (2).png" alt="Samar State University logo" />
              <div>
                <strong>SAMAR STATE UNIVERSITY</strong>
                <span>AUXILIARY OFFICE</span>
              </div>
            </div>
            <span class="auth-kicker">SECURE ACCOUNT RECOVERY</span>
            <h1 id="forgot-password-heading"><?= $success ? 'Password updated' : ($showOtpForm ? 'Enter your reset code' : 'Forgot your password?') ?></h1>
            <p class="auth-description">
              <?= $success
                  ? 'Your password has been changed. Sign in with your new password.'
                  : ($showOtpForm
                      ? 'Enter the six-digit code and choose a new password. Check the message below if the code was not emailed.'
                      : 'Enter the email address on your user account. We will send a one-time code to verify it is you.') ?>
            </p>
            <?php if ($notice): ?>
            <p class="auth-notice" role="status" aria-live="polite">
              <?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?>
            </p>
            <?php endif; ?>
            <?php if ($error): ?>
            <p class="auth-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
            <p class="auth-footer"><a href="login.php">Return to user sign in</a></p>
            <?php elseif ($showOtpForm): ?>
            <form method="post" action="forgot_password.php">
              <input type="hidden" name="mode" value="reset_password" />
              <label for="email">Email address</label>
              <input
                id="email"
                name="email"
                type="email"
                required
                autocomplete="email"
                value="<?= htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8') ?>"
              />
              <label for="otp">Six-digit email code</label>
              <input
                class="auth-otp"
                id="otp"
                name="otp"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                pattern="[0-9]{6}"
                minlength="6"
                maxlength="6"
                required
                autofocus
                aria-describedby="otp-help"
              />
              <label for="password">New password</label>
              <input id="password" name="password" type="password" minlength="8" required autocomplete="new-password" />
              <label for="password_confirmation">Confirm new password</label>
              <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" required autocomplete="new-password" />
              <button type="submit">Verify code and reset password</button>
            </form>
            <p class="auth-security-note" id="otp-help">The code expires in 10 minutes and allows up to five attempts. For account privacy, the same response is shown even if no user account matches.</p>
            <p class="auth-footer"><a href="forgot_password.php">Request another code</a></p>
            <?php else: ?>
            <form method="post" action="forgot_password.php">
              <input type="hidden" name="mode" value="request_code" />
              <label for="email">Email address</label>
              <input
                id="email"
                name="email"
                type="email"
                required
                autocomplete="email"
                autofocus
                placeholder="you@example.com"
                value="<?= htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8') ?>"
                aria-describedby="email-help"
              />
              <button type="submit">Send verification code</button>
            </form>
            <p class="auth-security-note" id="email-help">For your security, we do not reveal whether an email address is registered. You can request a new code once per minute.</p>
            <p class="auth-footer"><a href="login.php">Return to user sign in</a></p>
            <?php endif; ?>
          </div>
        </section>
      </div>
    </main>
  </body>
</html>
