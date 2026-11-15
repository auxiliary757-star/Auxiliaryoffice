<?php
declare(strict_types=1);

function normalizePhilippinePhone(string $phone, string $format = 'e164'): string
{
    $phone = trim($phone);
    if ($phone === '' || !preg_match('/^\+?[\d\s().-]+$/', $phone)) {
        throw new InvalidArgumentException('Phone number is invalid.');
    }
    $digits = preg_replace('/\D+/', '', $phone);

    if (preg_match('/^09\d{9}$/', $digits)) {
        $digits = '63' . substr($digits, 1);
    } elseif (preg_match('/^639\d{9}$/', $digits)) {
        // Already in international format without the plus sign.
    } else {
        throw new InvalidArgumentException('Enter a valid Philippine mobile number.');
    }

    if ($format === 'e164') {
        return '+' . $digits;
    }
    if ($format === 'semaphore') {
        return $digits;
    }

    throw new InvalidArgumentException('Unsupported phone number format.');
}
