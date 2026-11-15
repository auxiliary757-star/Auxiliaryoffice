<?php
declare(strict_types=1);

function sendSmtpEmail(string $recipient, string $subject, string $html, string $text): void
{
    if (!class_exists(PHPMailer\PHPMailer\PHPMailer::class)) {
        throw new RuntimeException('Run composer install to enable SMTP email.');
    }
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Email recipient is invalid.');
    }

    $host = getenv('SMTP_HOST') ?: '';
    $from = getenv('MAIL_FROM') ?: '';
    if ($host === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('SMTP_HOST and a valid MAIL_FROM are required.');
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->Port = (int) (getenv('SMTP_PORT') ?: 587);
    $mail->SMTPAuth = (getenv('SMTP_USER') ?: '') !== '';
    $mail->Username = getenv('SMTP_USER') ?: '';
    $mail->Password = getenv('SMTP_PASS') ?: '';
    $secure = strtolower(getenv('SMTP_SECURE') ?: 'tls');
    if ($secure === 'tls' || $secure === 'ssl') {
        $mail->SMTPSecure = $secure;
    } elseif ($secure !== 'none') {
        throw new RuntimeException('SMTP_SECURE must be tls, ssl, or none.');
    }
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($from);
    $mail->addAddress($recipient);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $html;
    $mail->AltBody = $text;
    $mail->send();
}
