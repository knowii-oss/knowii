<?php

use Illuminate\Support\Facades\Schedule;

// The retention periods of the privacy policy (ADR 0030): each period in App\Support\Retention has a task here or a
// configured expiry. RetentionScheduleTest fails when one of these tasks is missing.

// The uploaded images that no post or comment uses for 7 days (#1201, #1273)
Schedule::command('uploads:prune')->dailyAt('03:30')->withoutOverlapping()->onOneServer();

// Expired invitations, API tokens, failed jobs, password reset tokens, sessions and cache entries (#1306)
Schedule::command('retention:prune')->dailyAt('03:40')->withoutOverlapping()->onOneServer();

// Old waitlist contacts, unconfirmed and unsubscribed contacts of the EmailOctopus lists (#77)
Schedule::command('mailing-lists:prune')->dailyAt('03:50')->withoutOverlapping()->onOneServer();
