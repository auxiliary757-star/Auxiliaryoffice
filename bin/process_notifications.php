<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/notifications/NotificationService.php';

$processed = processNotificationQueue(db());
fwrite(STDOUT, "Processed {$processed} notification job(s).\n");
