<?php
declare(strict_types=1);

final class TwilioProvider implements SmsProviderInterface
{
    public function send(string $phone, string $message): void
    {
        $accountSid = getenv('TWILIO_ACCOUNT_SID') ?: '';
        $authToken = getenv('TWILIO_AUTH_TOKEN') ?: '';
        $fromNumber = getenv('TWILIO_FROM_NUMBER') ?: '';
        if ($accountSid === '' || $authToken === '' || $fromNumber === '') {
            throw new RuntimeException('Twilio credentials are not configured.');
        }

        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($accountSid) . '/Messages.json';
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Unable to initialize the Twilio request.');
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'To' => normalizePhilippinePhone($phone),
                'From' => $fromNumber,
                'Body' => $message,
            ]),
            CURLOPT_USERPWD => $accountSid . ':' . $authToken,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($response === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('Twilio request failed (HTTP ' . $status . '): ' . ($error ?: (string) $response));
        }
    }
}
