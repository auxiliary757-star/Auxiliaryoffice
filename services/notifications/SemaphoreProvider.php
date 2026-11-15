<?php
declare(strict_types=1);

final class SemaphoreProvider implements SmsProviderInterface
{
    public function send(string $phone, string $message): void
    {
        $apiKey = getenv('SEMAPHORE_API_KEY') ?: '';
        $sender = getenv('SEMAPHORE_SENDER_NAME') ?: '';
        if ($apiKey === '' || $sender === '') {
            throw new RuntimeException('Semaphore credentials are not configured.');
        }

        $this->post('https://api.semaphore.co/api/v4/messages', [
            'apikey' => $apiKey,
            'number' => normalizePhilippinePhone($phone, 'semaphore'),
            'message' => $message,
            'sendername' => $sender,
        ]);
    }

    private function post(string $url, array $fields): void
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Unable to initialize the Semaphore request.');
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($response === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('Semaphore request failed (HTTP ' . $status . '): ' . ($error ?: (string) $response));
        }
    }
}
