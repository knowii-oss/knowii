# 0054. Email delivery: the mail queue, the budget, bounces and the one-click stop

- Status: Accepted
- Date: 2026-11-18
- Issue: #1477 (slice 4a of the notifications; slice 4b, #1478, adds its section later)
- Pull request: #1677
- Amends: [0042](0042-notifications.md) (point 26: the queue `mail` and its worker, which that record announced), [0046](0046-production-image-and-compose.md) (point 6: the service `queue-mail`)
- Design: [email groundwork](../design/notifications/email-groundwork.md), sections 1, 2 and 8

## Context

Slice 4c sends the first notification email. Before it, the platform needs a road for that mail that cannot hurt the rest:

1. The provider (Resend) accepts 10 requests a second per team, and refuses every mail over the monthly quota (and over the daily quota of the free plan), the sign-in link included. A burst of notification mail must not use the place of a sign-in link, and must not use the whole quota.
2. One worker reads `broadcasts,notifications,default` (ADR 0042 point 26). `smtp` has no timeout (`config/mail.php`): a slow send on that worker would stop the live updates.
3. A job that a rate limiter releases counts one attempt. With `--tries=3` in production, three releases would fail a mail that never failed.
4. Nobody knows when the quota runs out: there is no counter and no alert. The quota of the provider is per team, and each operator chooses whether other products send with the same team (owner, 2026-11-17).

## Decision

### Which mail goes where

1. Only notification mail goes on the queue `mail` (`Constants::QUEUE_MAIL`, the configuration key `mail.queue`, env `MAIL_QUEUE`, default `mail`). Code reads the configuration, never the constant. `MagicLink`, `CommunityInvitation`, `CommunityOwnershipTransferred` and the address verification stay as they are, on `default`, with no limiter and no hold: they are level 0 and never held (design 6.13 point 6).
2. `MAIL_QUEUE=default` is the documented downgrade for an instance with one worker: the limiter and the hold still apply.
3. Every notification mail extends the abstract `App\Mail\NotificationMail` (`Mailable`, `ShouldQueue`). It fixes the queue (set when the mail is queued, so a subclass needs no code of its own), the middleware `[HoldNotificationMail, RateLimited('mail')]` in this order, `retryUntil()` (2 hours after the mail is due, `Constants::MAIL_NOTIFICATION_RETRY_HOURS`: the time it was queued, or the end of the delay of `later()`), `$maxExceptions = 3` and `backoff()` `[60, 300, 1800]`. With `retryUntil`, the worker ignores `--tries`, so in these 2 hours a release never fails a job; only real exceptions count. After them, the worker fails the job the next time it takes it (`Worker::markJobAsFailedIfAlreadyExceedsMaxAttempts`), also a job that never ran: a mail that waited for too long (a stopped mail worker, a backlog of more than 2 hours behind the limit) is not sent late. It goes to `failed_jobs` and the notification stays in the app. The backoff is read by the number of the attempt, and a release counts as an attempt: a mail with no release waits 1 minute after its first exception and 5 minutes after the second one, and the third one fails it; a mail that was released before waits longer. No subclass ships before slice 4c.

### The rate limit

4. `RateLimiter::for('mail')` in `AppServiceProvider`: `Limit::perSecond(MAIL_RATE_LIMIT_PER_SECOND)->by('mail')`, default 5 (half of the provider limit, so that the level 0 mail on `default` keeps room), `Limit::none()` for 0, and a value below 0 counts as 0. One global key: two mail workers share the count. The vendored middleware releases a limited job for the time left plus 3 seconds; a release is not a failure. The limit is not exact with two mail workers: the middleware reads the count and then adds to it in two steps, and the `database` cache store counts in whole seconds, so a second can carry up to twice the value. The default of 5 keeps that below the 10 of the provider.

### The hold and the quota refusal

5. `App\Jobs\Middleware\HoldNotificationMail` comes first. While notification mail is held, it logs one info line and ends the job as done: nothing is sent, nothing goes to `failed_jobs`, the notification stays in the app (design 6.5 point 4).
6. Else it runs the job and catches the transport exception. The design said "detect the codes in the message, in `failed()`". The code does not allow it: `failed()` runs after the job failed, and the transport wraps the error of the provider. So the middleware walks the previous exceptions to the `Resend\Exceptions\ErrorException` and compares its error type, exact text: `monthly_quota_exceeded` holds notification mail until the first day of the next month, `daily_quota_exceeded` until the next 00:00 UTC, with a warning, and the job ends. Any other error is thrown again, for the backoff.

### The count of sent mail

7. Table `mail_usage_months`: `id`, `cuid`, `month` (the first day of a calendar month in UTC, unique), `sent_count`, `alert_80_sent_at`, `hold_notified_at`, `held_until`, timestamps. No personal data: no retention period, no line in the privacy policy, nothing in the member export.
8. The listener `App\Listeners\CountSentEmails` (`MessageSent`) counts the recipients of the envelope (to, cc and bcc), whatever the mailer, the queue or the kind of mail, through `App\Contracts\Mail\RecordsSentEmails` (`RecordSentEmails`): one statement, `insert ... on conflict (month) do update set sent_count = mail_usage_months.sent_count + excluded.sent_count`. A refused send fires no event, so it counts nothing. The listener never throws: an exception after a send would retry the job and send the mail twice. The design named the contract `RecordsMailSent`; the naming rules ask for a third-person verb and a plural noun.

### The budget check

9. `mail:check-budget` runs every hour (`withoutOverlapping()->onOneServer()`) and calls `App\Contracts\Mail\ChecksMailBudgets` (`CheckMailBudget`: `check()`, `isHeld()`, `holdUntil()`). All the state is on the row of the current month: a new month has a new row, and the hold lifts by itself. The design kept the hold in the cache; the row is one place for the check, the workers and the middleware, and a cleared cache (`php artisan cache:clear`) does not lift the hold. The lock of the scheduled run ends after 10 minutes, so a killed run never stops the check for a day.
10. The quota: `MAIL_MONTHLY_QUOTA`, else the monthly limit of the provider, else none, and the check does nothing more (an instance with `log` or `smtp`). The use: the monthly count of the provider when the read worked (it sees the other senders of the same team), else `sent_count`. The quota is configuration (owner decision 1); the read of the provider is an option.
11. The read: `App\Contracts\Mail\ReadsMailUsage` (`ResendMailUsage`), only with the mailer `resend` and a key. It calls the usage endpoint of the SDK with a client of 10 seconds of timeout (to connect, and in total) and narrows the answer. Any failure gives null and one warning with the error type of the provider (`restricted_api_key` for a key that cannot read the usage), never the key or the message.
12. At 80% (`Constants::MAIL_BUDGET_ALERT_PERCENT`), once a month: a warning with the numbers, and the alert mail `App\Mail\MailBudgetAlert` to each valid address of `MAIL_ALERT_TO` (an invalid address is skipped). Without an address, the log line is the alert. The address is configuration, never in the code (owner decision 2): anyone can host the platform.
13. At `MAIL_NOTIFICATION_HOLD_PERCENT` (default 95, kept between 1 and 100; 100 means no reserve): `held_until` is the monthly reset time of the provider, else the first day of the next month, with a second warning and alert once a month (one mail when both steps come in the same run). When the provider gives a daily limit and the day reaches the hold percent of it, `held_until` is at least the daily reset time (else the next 00:00 UTC), with a log line and no mail: a mail would use the daily quota. When both numbers are below the hold percent, the check clears `held_until` (the operator raised the quota). So a hold set by a refusal can end at the next check when the numbers of the provider disagree; the next refusal sets it again. The check does not clear a hold when the read of the provider failed: the count of this instance can be lower than the count of the team, and one timeout must not lift the hold. A hold that comes back in the same month is a warning in the log, with no second mail. `holdUntil()` writes in one statement, so a shorter hold of another worker never replaces a longer one.
14. `MailBudgetAlert` is level 0: a plain mailable on `default`, never held. Its words are the ones that the owner approved on 2026-11-17.

### The second worker

15. Each file that runs a worker runs a second one that reads only `mail`, with the flags of the first worker of the same file: `queue_worker_mail` in `docker/8.5/supervisord.conf`, the service `queue-mail` in `compose.production.yaml`, `knowii-queue-mail` in `SELF_HOSTING.md`, a second `queue:listen` in `CONTRIBUTING.md`. The first worker keeps `--queue=broadcasts,notifications,default` and never reads `mail`. A test checks the four files.
16. The service `queue-mail` keeps the health check `knowii-healthcheck-process queue:work`. The design feared that it calls a dead mail worker healthy; it cannot, because each service is its own container and the script reads the processes of its own container.
17. `app:health` gets the check `mail-queue`: with the `database` queue it fails when a job of the mail queue is not reserved and could run for more than 10 minutes (`Constants::MAIL_QUEUE_STALE_MINUTES`), and no job of that queue ended in the same time. A released job waits for a later time and does not count. The listener `App\Listeners\RecordMailWorkerActivity` (`JobProcessed`) notes each ended job of the mail queue in the cache (sent, held or released): with one worker and a long backlog, old jobs wait while the worker runs, and the deployment workflow rolls back when `app:health` fails. A send that hangs ends no job, so it does not hide a blocked worker. With another driver the check passes.

### API parity

18. Slice 4a has nothing that a member can read or change. The operator reads the numbers with `mail:check-budget`, the log and the alert mail, and changes them only through configuration. So there is no route, no token ability, no Bruno request and no change of the OpenAPI document.

## Consequences

1. Slice 4c only builds content: a notification mailable extends `NotificationMail` and gets the queue, the limiter, the hold and the retries.
2. Every operator runs one more process. The update notes of `SELF_HOSTING.md` say it, and `app:health` fails when notification mail waits for a worker that does not run.
3. A sign-in link can still wait behind a backlog of `notifications`, because `default` is the last queue of the first worker. This is not new; a queue of its own for level 0 mail is an open point (#1478, risk 1).
4. A crash after the provider accepted a mail and before the job ended can send it twice. The delivery log of slice 4b (#1478) makes it at most once.
5. The count of this instance does not see the other senders of a shared team. The operator sets `MAIL_MONTHLY_QUOTA` lower than the plan in that case (owner, 2026-11-17).
6. Rules for slice 4c and later. A notification mail goes out only through `queue()`, `later()` or `send()` of the mailer: `sendNow()`, and a Laravel notification that returns the mailable, skip the queue, the hold and the limiter. The refusal for a quota is read only with the mailer `resend`: the `failover` and `roundrobin` mailers throw an exception without the error of the provider, so the hold then comes from the hourly check alone. The `sync` queue driver drops a job that the limiter releases: it is for tests.
7. `MAIL_QUEUE` has two useful values, `mail` and `default`: the worker files read these names. Another name needs a worker that the operator adds.
8. The three values that are numbers (`MAIL_RATE_LIMIT_PER_SECOND`, `MAIL_MONTHLY_QUOTA`, `MAIL_NOTIFICATION_HOLD_PERCENT`) are not cast in `config/mail.php`: an empty value would become 0, which means no limit or a hold at 1%. `App\Support\MailSettings` narrows them and gives the default for an empty or a wrong value.

## Alternatives considered

1. All mail on the queue `mail`: one rule, but a burst of notification mail would make a sign-in link wait behind the limiter, and the existing mails are a handful a day. Design 6.13 point 6 says they are never held.
2. One worker for every queue, with `mail` added to the list: a slow SMTP send (no timeout) would block the live updates. The limiter alone would not be a reason: a release does not block a worker.
3. No hold, only the alert: the quota runs out and the provider refuses the sign-in links too. The owner chose the hold at 95% (decision 6).
4. The hold in the cache, as the design said: a cleared cache would lift the hold, and the check, the workers and the middleware would read two places.
5. The detection of the quota codes in `failed()`: it runs after the job failed and sees only the wrapped message.
