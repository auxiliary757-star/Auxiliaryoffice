<?php
declare(strict_types=1);

require_once __DIR__ . '/../email/SmtpMailer.php';

function createPasswordResetOtp(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function hashPasswordResetOtp(string $otp): string
{
    if (!preg_match('/^\d{6}$/', $otp)) {
        throw new InvalidArgumentException('Password reset code must contain six digits.');
    }
    $hash = password_hash($otp, PASSWORD_DEFAULT);
    if ($hash === false) {
        throw new RuntimeException('Unable to secure the password reset code.');
    }
    return $hash;
}

function passwordResetEmailDeliveryEnabled(): bool
{
    return filter_var(getenv('NOTIFICATIONS_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN);
}

function requestPasswordResetOtp(PDO $pdo, string $email): void
{
    $pdo->beginTransaction();
    try {
        $userStatement = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'User' LIMIT 1 FOR UPDATE");
        $userStatement->execute([$email]);
        $user = $userStatement->fetch();
        if (!$user) {
            $pdo->commit();
            return;
        }

        $recent = $pdo->prepare(
            'SELECT id FROM password_reset_tokens
             WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
             LIMIT 1 FOR UPDATE'
        );
        $recent->execute([$user['id']]);
        if ($recent->fetch()) {
            $pdo->commit();
            return;
        }

        $otp = createPasswordResetOtp();
        $invalidate = $pdo->prepare(
            'UPDATE password_reset_tokens SET used_at = NOW()
             WHERE user_id = ? AND used_at IS NULL'
        );
        $invalidate->execute([$user['id']]);
        $insert = $pdo->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, attempts)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0)'
        );
        $insert->execute([$user['id'], hashPasswordResetOtp($otp)]);
        $pdo->commit();

        sendPasswordResetOtpEmail($email, $otp);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function resetUserPasswordWithOtp(PDO $pdo, string $email, string $otp, string $password): string
{
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare(
            "SELECT t.id, t.user_id, t.token_hash, t.attempts
             FROM password_reset_tokens t
             INNER JOIN users u ON u.id = t.user_id
             WHERE u.email = ? AND u.role = 'User'
               AND t.used_at IS NULL AND t.expires_at > NOW() AND t.attempts < 5
             ORDER BY t.created_at DESC
             LIMIT 1 FOR UPDATE"
        );
        $statement->execute([$email]);
        $reset = $statement->fetch();
        if (!$reset) {
            $pdo->commit();
            return 'invalid';
        }

        if (!password_verify($otp, (string) $reset['token_hash'])) {
            $attempts = (int) $reset['attempts'] + 1;
            $update = $pdo->prepare(
                'UPDATE password_reset_tokens
                 SET attempts = ?, used_at = IF(? >= 5, NOW(), used_at)
                 WHERE id = ?'
            );
            $update->execute([$attempts, $attempts, $reset['id']]);
            $pdo->commit();
            return 'invalid';
        }

        $updateUser = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $updateUser->execute([password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]);
        $consume = $pdo->prepare(
            'UPDATE password_reset_tokens SET used_at = NOW()
             WHERE user_id = ? AND used_at IS NULL'
        );
        $consume->execute([$reset['user_id']]);
        $pdo->commit();
        return 'success';
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function sendPasswordResetOtpEmail(string $recipient, string $otp): void
{
    if (!passwordResetEmailDeliveryEnabled()) {
        error_log("PASSWORD RESET DEV MODE to {$recipient}: OTP {$otp}");
        return;
    }

    sendSmtpEmail(
        $recipient,
        'Your SSU Reservation password reset code',
        '<!doctype html><html><body><h2>Password reset code</h2>'
            . '<p>Enter this one-time code to reset your password. It expires in 10 minutes.</p>'
            . '<p style="font-size:28px;font-weight:700;letter-spacing:8px;">' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p>If you did not request this code, you can ignore this email.</p></body></html>',
        "Your SSU Reservation password reset code is {$otp}. It expires in 10 minutes.\n\nIf you did not request this code, ignore this email."
    );
}
