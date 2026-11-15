<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/auth/PasswordReset.php';

$previousNotificationSetting = getenv('NOTIFICATIONS_ENABLED');
putenv('NOTIFICATIONS_ENABLED=false');
if (passwordResetEmailDeliveryEnabled()) {
    throw new RuntimeException('Email delivery must be disabled when configured as false.');
}
putenv('NOTIFICATIONS_ENABLED=true');
if (!passwordResetEmailDeliveryEnabled()) {
    throw new RuntimeException('Email delivery must be enabled when configured as true.');
}
if ($previousNotificationSetting === false) {
    putenv('NOTIFICATIONS_ENABLED');
} else {
    putenv('NOTIFICATIONS_ENABLED=' . $previousNotificationSetting);
}

$otp = createPasswordResetOtp();
if (!preg_match('/^\d{6}$/', $otp)) {
    throw new RuntimeException('Reset codes must contain six digits.');
}
$hash = hashPasswordResetOtp($otp);
if (hash_equals($otp, $hash) || !password_verify($otp, $hash)) {
    throw new RuntimeException('Reset codes must be stored as a one-way password hash.');
}
if (password_verify(str_pad((string) (((int) $otp + 1) % 1000000), 6, '0', STR_PAD_LEFT), $hash)) {
    throw new RuntimeException('A different code must not verify.');
}

try {
    hashPasswordResetOtp('12345');
    throw new RuntimeException('Malformed reset codes must be rejected.');
} catch (InvalidArgumentException $error) {
    if ($error->getMessage() !== 'Password reset code must contain six digits.') {
        throw $error;
    }
}

fwrite(STDOUT, "Password reset OTP tests passed.\n");
