# SSU Reservation Backend

The project now uses PHP and MySQL as the primary reservation store.

## Email and SMS notifications

Install the PHP dependencies from the project root:

```powershell
composer install
Copy-Item .env.example .env
```

If Composer reports that ZIP/unzip tooling is missing, enable `extension=zip` in the CLI PHP configuration (for this XAMPP install, `C:\xampp\php\php.ini`) and rerun `composer install`.

Set the `SSU_DB_*` variables in `.env`, then configure:

- SMTP: `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE` (`tls`, `ssl`, or `none`), `SMTP_USER`, `SMTP_PASS`, `MAIL_FROM`
- SMS: `SMS_PROVIDER=semaphore` or `twilio`, and the matching `SEMAPHORE_*` or `TWILIO_*` credentials
- `NOTIFICATIONS_ENABLED=false` logs rendered messages to the PHP console without sending. Set it to `true` only after configuring the providers.
- `APP_BASE_URL`, `APP_TIMEZONE`, `REMINDER_WINDOW_HOURS` (defaults to 24), `NOTIFICATION_MAX_ATTEMPTS`, and `NOTIFICATION_RETRY_BASE_SECONDS`

Apply `database/migrations/20261114_notifications.sql` once to an existing database. Apply `database/migrations/20261115_reservation_activity_logs.sql` to create and backfill the unified reservation activity log. If the notifications migration was applied before the password-reset changes, apply `database/migrations/20261114_password_reset_remove_notification_preferences.sql` as well. New installations can use the updated `database/schema.sql`. Registration accepts an optional Philippine mobile number. Notifications go to the account email and, when a phone number is present, to that number by SMS; there are no per-channel preference controls.

User password recovery is available from the user sign-in page only. A six-digit OTP is sent by email; it expires after 10 minutes, permits at most five attempts, and is stored as a password hash. Requests are limited to one per account per minute. The administrator sign-in page has no self-service reset flow.

To send OTPs through Gmail, configure `.env` with `SMTP_HOST=smtp.gmail.com`, `SMTP_PORT=587`, `SMTP_SECURE=tls`, your Gmail address as `SMTP_USER` and `MAIL_FROM`, and a Google **App Password** as `SMTP_PASS`. Google requires 2-Step Verification to create an App Password. Do not use or share your regular Gmail password. Set `NOTIFICATIONS_ENABLED=true` only after SMTP is configured. With it set to `false`, the OTP is written to the PHP console for local testing, not emailed.

To test, open the user sign-in page, choose **Forgot password?**, enter the Gmail address on a regular `User` account, and submit the six-digit code from the inbox with a new password. Old Faculty accounts with the `Administrator` role are intentionally excluded from user password recovery.

If the preference-removal migration has already been applied, also apply `database/migrations/20261114_password_reset_otp.sql` once to add OTP attempt tracking and a salted password-hash column.

Public registration always creates a regular `User` role, whether the profile type is Faculty or Student. Do not expose Administrator as a public sign-up choice. An existing administrator can promote a faculty account using the SQL example below.

The app only enqueues messages in reservation requests. Run the worker periodically (for example, once per minute in Windows Task Scheduler):

```powershell
php C:\xampp\htdocs\Reservation\bin\process_notifications.php
```

Run the reminder command every 15 minutes:

```powershell
php C:\xampp\htdocs\Reservation\bin\send_reminders.php
```

Both commands can be scheduled in Windows Task Scheduler with the **Program/script** set to the full `php.exe` path and the script path as the argument. They can also be run manually while testing. Failed delivery attempts are recorded in `notification_logs` and retried with exponential backoff; the queue prevents duplicate event/channel jobs.

### Safe notification testing

Point SMTP at Mailtrap (or another test SMTP inbox), then test each email event without touching a real reservation:

```powershell
php C:\xampp\htdocs\Reservation\bin\test_notifications.php email your-test-inbox@example.com booking_confirmation
php C:\xampp\htdocs\Reservation\bin\test_notifications.php email your-test-inbox@example.com booking_conflict
php C:\xampp\htdocs\Reservation\bin\test_notifications.php email your-test-inbox@example.com approval
php C:\xampp\htdocs\Reservation\bin\test_notifications.php email your-test-inbox@example.com reminder
php C:\xampp\htdocs\Reservation\bin\test_notifications.php email your-test-inbox@example.com cancellation
```

With `NOTIFICATIONS_ENABLED=false`, the script logs the rendered message only. For live email testing, set it to `true` while SMTP points to Mailtrap. To test SMS safely, first use `NOTIFICATIONS_ENABLED=false` to validate the dev log, then set it to `true` and use a number you control. Keep the provider in test/development mode when available; Semaphore does not provide a general delivery sandbox, so use your own handset and a low-value test account. Example:

```powershell
php C:\xampp\htdocs\Reservation\bin\test_notifications.php sms 09171234567 booking_confirmation
```

Submitting a booking for a venue/date that already has an active reservation saves the attempt as rejected and queues a booking-conflict email (and SMS when the account has a phone number). The notification worker sends the notice. The other event paths can be exercised by submitting an available booking, approving it in the admin screen, cancelling it as a user/admin, and running the reminder command with an approved reservation inside the configured window. Reminder jobs are deduplicated and cancelled reservations are excluded.

Run the focused checks with:

```powershell
php C:\xampp\htdocs\Reservation\tests\notification_tests.php
php C:\xampp\htdocs\Reservation\tests\reservation_activity_log_tests.php
php C:\xampp\htdocs\Reservation\tests\reservation_schedule_tests.php
php C:\xampp\htdocs\Reservation\tests\reservation_date_blocking_tests.php
```

## 1. Create the database

Run `database/schema.sql` in MySQL or phpMyAdmin. It creates:

- `users` for requesters and administrators
- `facilities` for venue details and image paths
- `reservations` for the complete reservation lifecycle

## 2. Configure the database connection

The connection reads these environment variables:

- `SSU_DB_HOST` (default: `127.0.0.1`)
- `SSU_DB_PORT` (default: `3306`)
- `SSU_DB_NAME` (default: `ssu_reservations`)
- `SSU_DB_USER` (default: `root`)
- `SSU_DB_PASSWORD` (default: empty)

For local XAMPP with the default MySQL account, the defaults are usually enough.

## 3. Serve the project through PHP

Do not open `index.php` with `file://`. Start Apache/PHP and open the project through a URL such as:

`http://localhost/Reservation%20-%20Copy/index.php`

The API endpoints are:

- `api/create_reservation.php`
- `api/list_reservations.php`
- `api/update_reservation_status.php`
- `api/cancel_reservation.php`
- `api/list_facilities.php`
- `api/save_facility.php`

## Reservation behavior

- A `PENDING`, `APPROVED`, or `OCCUPIED` reservation blocks the same venue for the entire reservation date, regardless of its start time. A new request for that venue/date is saved as `REJECTED`; cancelled, rejected, or ended reservations do not block it. Approval checks for any other active reservation on that venue/date inside a database transaction. Pending and approved reservations appear as gray dates on user and admin calendars. Venue cards show a venue as reserved for the current date while any active reservation remains; after its end time, it becomes available without changing reservation history.
- Ended reservations are removed from user and admin calendars, and cancelled or rejected reservations are removed from the admin calendar. Completed approved reservations leave the admin's active requests table and appear under Reservation Logs; they are not deleted from the database.
- Rejected reservations remain stored with their status.
- User cancellation remains limited to pending or rejected reservations; cancellations are retained as `CANCELLED`. Administrators can cancel any reservation that has not already been cancelled.
- Requesters are stored in `users`; when the current session has a user ID, that session can be used by the status endpoint as the reviewer.
- LocalStorage remains only as an offline fallback when the PHP API cannot be reached.

Users register through `register.php` and sign in through `login.php`. Administrators sign in through `admin/login.php`. Passwords are stored with `password_hash()` and sessions are regenerated after login. The public dashboard only loads the current user's reservations; administrator endpoints require the `Administrator` role.

To create the first administrator, register an account, then assign its role once in MySQL:

```sql
UPDATE users SET role = 'Administrator' WHERE email = 'admin@example.com';
```

Replace the email with the account you registered. Do not store plaintext passwords in SQL files.
