# 0030. Retention periods are configuration, and scheduled tasks apply them

- Status: Accepted
- Date: 2026-11-10
- Issue: #1306, #77
- Pull request: #1307

## Context

The privacy policy (#77) tells members how long Knowii keeps each kind of data. Sébastien accepted the periods on one condition: each one must be true. The audit found data that stayed forever: expired invitations and API tokens, failed jobs (their payload can hold personal data), expired password reset tokens, sessions and cache entries in the database (Laravel deletes them only at random, or never), and the waitlist and the unconfirmed contacts in EmailOctopus. A period written only in the policy text becomes false as soon as someone changes the code or the configuration.

## Decision

1. Every retention period lives in configuration: `config/retention.php` for the periods Knowii applies itself, and the Laravel keys for the others (`session.lifetime`, `auth.passwords.users.expire`, `logging.channels.daily.days`). `App\Support\Retention` reads them all, in one place.
2. Scheduled tasks apply them every day (`routes/console.php`): `uploads:prune`, `retention:prune` (invitations, API tokens, failed jobs, password reset tokens, database sessions, database cache entries) and `mailing-lists:prune` (EmailOctopus API: waitlist contacts after 12 months, unconfirmed and unsubscribed contacts after 30 days). Each task has a dry run.
3. The legal pages show the periods through placeholders filled from `Retention::periods()`, never as numbers typed in the text.
4. Periods that the server applies (log rotation, backups) are configuration values too. `SELF_HOSTING.md` tells the operator to use the same numbers. The backup script of #1182 reads `RETENTION_BACKUPS_*`.

## Consequences

1. Changing a period changes the policy at the same time: a self-hosted instance with other values shows its own numbers.
2. A new kind of data with a limited life needs a period in `config/retention.php`, a prune step with a test, and a line in the privacy policy, in the same pull request (rule in `documentation/conventions/rules.md`).
3. `RetentionTest` fails when a prune task is no longer scheduled.
4. The scheduler must run in production (it already runs `uploads:prune`). Without it, the policy is false.

## Alternatives considered

1. Model pruning (`Prunable` and `model:prune`): it covers Eloquent models only, not the session, cache, password reset and failed job tables, nor EmailOctopus. One action with a contract keeps the rules in one place.
2. The built-in commands (`sanctum:prune-expired`, `queue:prune-failed`, `auth:clear-resets`) scheduled one by one: each one takes its own period as a flag in `routes/console.php`, far from the policy. Our action reads the shared configuration and has one test file.
3. Periods typed in the policy text: they drift from the code without anyone noticing.
