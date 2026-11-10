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
