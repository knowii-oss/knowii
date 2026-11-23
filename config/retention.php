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

    // An import of invitations from a file (#1390, ADR 0061) that nobody sent is deleted this many hours after the upload.
    // The record of a sent import follows the periods of the invitations: it is deleted the validity of an invitation
    // plus expired_invitations_days after the confirm
    'invitation_import_previews_hours' => (int) env('RETENTION_INVITATION_IMPORT_PREVIEWS_HOURS', 24),

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

    // The record of a join through an invite link (#1391, ADR 0062): the link, the member and the date, deleted this many
    // days after the join. It also goes when the member leaves the community or is removed, with the link and with the
    // account. The count of the link stays
    'invite_link_uses_days' => (int) env('RETENTION_INVITE_LINK_USES_DAYS', 365),

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

    // A new email address that waits for its confirmation (#1706, ADR 0058) counts this many days at most. After that it does
    // not count any more, and the `pending_emails` step of retention:prune removes it, with the time of the request
    'pending_email_days' => (int) env('RETENTION_PENDING_EMAIL_DAYS', 7),

    // Email delivery (#1478, ADR 0054): a row of the delivery log (the kind and the state of a sent mail, no text) and the id
    // of a report of the mail provider are deleted this many days after they were written. A count of soft bounces that
    // stopped nothing is deleted this many days after the last bounce. The stop of the notification email after a report
    // has no period: it lives while the address stays (a change of the address or a delivery lifts it), and goes with the
    // account. The groups that a member stopped live with the account
    'mail_deliveries_days' => (int) env('RETENTION_MAIL_DELIVERIES_DAYS', 30),
    'mail_webhook_events_days' => (int) env('RETENTION_MAIL_WEBHOOK_EVENTS_DAYS', 30),
    'email_soft_bounces_days' => (int) env('RETENTION_EMAIL_SOFT_BOUNCES_DAYS', 90),
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
