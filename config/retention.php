<?php

/*
|--------------------------------------------------------------------------
| Retention periods (ADR 0030, #77, #1306)
|--------------------------------------------------------------------------
|
| How long Knowii keeps the data that has no use after a time. The scheduled tasks of routes/console.php delete it,
| and the privacy policy reads its numbers from App\Support\Retention, so the policy always tells what the code does.
| The other periods live where Laravel reads them: session.lifetime, auth.passwords.users.expire,
| logging.channels.daily.days.
|
*/

return [
    // An invitation is valid for 7 days (CommunityInvitation::VALIDITY_DAYS), then deleted this many days later
    'expired_invitations_days' => (int) env('RETENTION_EXPIRED_INVITATIONS_DAYS', 30),

    // A personal API token is deleted this many days after its expiry date
    'expired_api_tokens_days' => (int) env('RETENTION_EXPIRED_API_TOKENS_DAYS', 30),

    // A failed background job keeps its data (it can hold personal data) this many days
    'failed_jobs_days' => (int) env('RETENTION_FAILED_JOBS_DAYS', 7),

    // An uploaded image that no content uses is deleted after this many days (#1273)
    'unused_uploads_days' => (int) env('RETENTION_UNUSED_UPLOADS_DAYS', 7),

    // An export (php artisan communities:export or users:export, #1353, #1343) is a copy of personal data: the file is deleted after
    // this many days
    'exports_days' => (int) env('RETENTION_EXPORTS_DAYS', 7),

    // A notification (#1474, ADR 0042) is deleted this many days after the member read it, or this many days after it was
    // written when the member never read it
    'notifications_read_days' => (int) env('RETENTION_NOTIFICATIONS_READ_DAYS', 60),
    'notifications_unread_days' => (int) env('RETENTION_NOTIFICATIONS_UNREAD_DAYS', 90),

    // The answer of a member to an event (#1515, ADR 0053) is deleted this many days after the end of the event. It also goes
    // with the event, with the account, and when the member leaves the community
    'event_answers_days' => (int) env('RETENTION_EVENT_ANSWERS_DAYS', 90),

    // Where I am (#1476, ADR 0048) has no period of its own: `users.time_zone` lives with the account, `users.last_visit_on` is one
    // date that each visit replaces, and the rows of `space_visits` are replaced at each visit and go with the membership (a member
    // who leaves or is removed) and with the account. Nothing accumulates, so `retention:prune` has no step for them.

    // The proof of a new sign-in for the deletion of an account without a password (#1521, ADR 0052) has no period of its own:
    // `users.deletion_signed_in_at` is one time per account, replaced by each new proof, cleared by the deletion that uses it and
    // deleted with the account. It holds only services.oidc.fresh_sign_in_seconds. Nothing accumulates, so `retention:prune` has
    // no step for it.

    // The ids (`jti`) of the change calls of the sign-in provider (#1386, ADR 0047) are kept 24 hours in the cache
    // (ReceiveEntitlementEvent::JTI_HOURS) against a replay, and forgotten with it: the `cache` step of retention:prune
    // deletes the expired entries when the cache is the database. The stored entitlement keys follow the link to the
    // provider and the account: there is no period.

    // EmailOctopus: a waitlist contact is deleted this many months after the signup
    'waitlist_months' => (int) env('RETENTION_WAITLIST_MONTHS', 12),

    // EmailOctopus: an unconfirmed (pending) or unsubscribed contact is deleted this many days after its last change
    'inactive_contacts_days' => (int) env('RETENTION_INACTIVE_CONTACTS_DAYS', 30),

    // The encrypted backups (#1182). The backup script on the server must use these numbers ("restic forget")
    'backups' => [
        'daily' => (int) env('RETENTION_BACKUPS_DAILY', 7),
        'weekly' => (int) env('RETENTION_BACKUPS_WEEKLY', 4),
        'monthly' => (int) env('RETENTION_BACKUPS_MONTHLY', 12),
    ],
];
