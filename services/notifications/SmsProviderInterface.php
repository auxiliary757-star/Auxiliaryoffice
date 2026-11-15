<?php
declare(strict_types=1);

interface SmsProviderInterface
{
    public function send(string $phone, string $message): void;
}
