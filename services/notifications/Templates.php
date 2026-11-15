<?php
declare(strict_types=1);

function notificationTemplate(string $event, array $reservation): array
{
    $labels = [
        'booking_confirmation' => ['Reservation received', 'Your reservation request has been received.'],
        'booking_conflict' => [
            'Venue date unavailable',
            'Your reservation request was not booked because this venue already has a pending or approved reservation on that date. Please choose another date.',
        ],
        'approval' => ['Reservation approved', 'Your reservation has been approved.'],
        'reminder' => ['Reservation reminder', 'This is a reminder about your upcoming reservation.'],
        'cancellation' => ['Reservation cancelled', 'Your reservation has been cancelled.'],
    ];
    if (!isset($labels[$event])) {
        throw new InvalidArgumentException('Unsupported notification event.');
    }

    $name = (string) ($reservation['name'] ?? '');
    $code = (string) ($reservation['reservation_code'] ?? '');
    $venue = (string) ($reservation['venue'] ?? '');
    $date = (string) ($reservation['reservation_date'] ?? '');
    $start = substr((string) ($reservation['start_time'] ?? ''), 0, 5);
    $end = substr((string) ($reservation['end_time'] ?? ''), 0, 5);
    $status = (string) ($reservation['status'] ?? '');
    $url = rtrim((string) (getenv('APP_BASE_URL') ?: ''), '/') . '/index.php#my-reservations';
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $subject = $labels[$event][0] . ' - ' . $code;
    $text = "{$labels[$event][1]}\n\n"
        . "Reservation: {$code}\nName: {$name}\nDate/time: {$date}, {$start}-{$end}\n"
        . "Facility: {$venue}\nStatus: {$status}\nManage booking: {$url}";
    $html = '<!doctype html><html><body><h2>' . $escape($labels[$event][0]) . '</h2><p>'
        . $escape($labels[$event][1]) . '</p><dl>'
        . '<dt>Reservation</dt><dd>' . $escape($code) . '</dd>'
        . '<dt>Name</dt><dd>' . $escape($name) . '</dd>'
        . '<dt>Date and time</dt><dd>' . $escape($date . ', ' . $start . '-' . $end) . '</dd>'
        . '<dt>Facility</dt><dd>' . $escape($venue) . '</dd>'
        . '<dt>Status</dt><dd>' . $escape($status) . '</dd></dl>'
        . '<p><a href="' . $escape($url) . '">View or manage your booking</a></p></body></html>';

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

function notificationSmsTemplate(string $event, array $reservation): string
{
    $labels = [
        'booking_confirmation' => 'received',
        'booking_conflict' => 'not booked',
        'approval' => 'approved',
        'reminder' => 'reminder',
        'cancellation' => 'cancelled',
    ];
    if (!isset($labels[$event])) {
        throw new InvalidArgumentException('Unsupported notification event.');
    }

    $url = rtrim((string) (getenv('APP_BASE_URL') ?: ''), '/') . '/index.php#my-reservations';
    return sprintf(
        'SSU %s: %s %s, %s %s. %s',
        $labels[$event],
        (string) ($reservation['reservation_code'] ?? ''),
        (string) ($reservation['venue'] ?? ''),
        (string) ($reservation['reservation_date'] ?? ''),
        substr((string) ($reservation['start_time'] ?? ''), 0, 5),
        $url
    );
}
