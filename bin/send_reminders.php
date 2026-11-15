<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/notifications/NotificationService.php';

$queued = queueDueReservationReminders(db());
fwrite(STDOUT, "Queued {$queued} reminder event(s).\n");
