<?php

use App\Contracts\Retention\PrunesExpiredData;
use App\Enums\KnowiiEmailSuppressionReason;
use App\Enums\KnowiiMailDeliveryStatus;
use App\Enums\KnowiiMailKind;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\EmailSuppression;
use App\Models\Event as EventModel;
use App\Models\EventAnswer;
use App\Models\InvitationImport;
use App\Models\InvitationImportRow;
use App\Models\InviteLink;
use App\Models\InviteLinkUse;
use App\Models\MailDelivery;
use App\Models\MailWebhookEvent;
use App\Models\User;
use App\Support\Retention;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The retention periods of the privacy policy are true (ADR 0030, #77, #1306): each one has a scheduled task.
 */
function invitationCreatedDaysAgo(Community $community, int $days): CommunityInvitation
{
    $invitation = $community->communityInvitations()->create(['email' => Str::random(8).'@example.com', 'role' => 'member']);
    $invitation->forceFill(['created_at' => now()->subDays($days)])->saveQuietly();

    return $invitation;
}

test('every retention task is scheduled every day (ADR 0030)', function () {
    $commands = collect(app(Schedule::class)->events())
        ->map(static fn (Event $event): string => (string) $event->command)
        ->filter(static fn (string $command): bool => Str::contains($command, ['uploads:prune', 'retention:prune', 'mailing-lists:prune']));

    expect($commands)->toHaveCount(3);
    foreach (app(Schedule::class)->events() as $event) {
        if (Str::contains((string) $event->command, ['uploads:prune', 'retention:prune', 'mailing-lists:prune'])) {
            expect($event->expression)->toMatch('/^\d+ \d+ \* \* \*$/');
        }
    }
});

test('the periods of the privacy policy come from the configuration (ADR 0030)', function () {
    $periods = Retention::periods();

    expect($periods['session'])->toBe('2 hours')
        ->and($periods['sign_in_link'])->toBe('15 minutes')
        ->and($periods['sign_up_link'])->toBe('60 minutes')
        ->and($periods['password_reset_link'])->toBe('60 minutes')
        ->and($periods['invitation'])->toBe('7 days')
        ->and($periods['expired_invitations'])->toBe('30 days')
        ->and($periods['invitation_import_previews'])->toBe('24 hours')
        ->and($periods['invitation_imports'])->toBe('37 days')
        ->and($periods['invitation_mails'])->toBe('2 days at most')
        ->and($periods['api_token_default'])->toBe('90 days')
        ->and($periods['expired_api_tokens'])->toBe('30 days')
        ->and($periods['failed_jobs'])->toBe('7 days')
        ->and($periods['unused_uploads'])->toBe('7 days')
        ->and($periods['notifications_read'])->toBe('60 days')
        ->and($periods['notifications_unread'])->toBe('90 days')
        ->and($periods['event_answers'])->toBe('90 days')
        ->and($periods['invite_link_uses'])->toBe('365 days')
        ->and($periods['pending_email'])->toBe('7 days')
        ->and($periods['logs'])->toBe('14 days')
        ->and($periods['waitlist'])->toBe('12 months')
        ->and($periods['inactive_contacts'])->toBe('30 days')
        ->and($periods['backups_longest'])->toBe('12 months');

    config(['session.lifetime' => 30, 'retention.waitlist_months' => 1]);

    expect(Retention::periods()['session'])->toBe('30 minutes')
        ->and(Retention::periods()['waitlist'])->toBe('1 month');
});

test('invitations are deleted 30 days after they expire (#1306)', function () {
    $community = Community::factory()->create();
    $old = invitationCreatedDaysAgo($community, 7 + 31);
    $recentlyExpired = invitationCreatedDaysAgo($community, 7 + 29);
    $valid = invitationCreatedDaysAgo($community, 1);

    $this->artisan('retention:prune')->assertSuccessful();

    expect(CommunityInvitation::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(CommunityInvitation::query()->whereKey($recentlyExpired->id)->exists())->toBeTrue()
        ->and(CommunityInvitation::query()->whereKey($valid->id)->exists())->toBeTrue();
});

test('API tokens are deleted 30 days after their expiry date, tokens without expiry stay (#1306)', function () {
    $user = User::factory()->create();
    $old = $user->createToken('old', ['*'], now()->subDays(31))->accessToken;
    $recentlyExpired = $user->createToken('recent', ['*'], now()->subDays(10))->accessToken;
    $never = $user->createToken('never')->accessToken;

    $this->artisan('retention:prune')->assertSuccessful();

    expect(PersonalAccessToken::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(PersonalAccessToken::query()->whereKey($recentlyExpired->id)->exists())->toBeTrue()
        ->and(PersonalAccessToken::query()->whereKey($never->id)->exists())->toBeTrue();
});

test('failed jobs are deleted after 7 days, password reset tokens after 60 minutes (#1306)', function () {
    foreach ([8, 6] as $days) {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
            'payload' => '{}', 'exception' => 'Failure', 'failed_at' => now()->subDays($days),
        ]);
    }
    DB::table('password_reset_tokens')->insert([
        ['email' => 'old@example.com', 'token' => 'x', 'created_at' => now()->subMinutes(61)],
        ['email' => 'new@example.com', 'token' => 'y', 'created_at' => now()->subMinutes(10)],
    ]);

    $this->artisan('retention:prune')->assertSuccessful();

    expect(DB::table('failed_jobs')->count())->toBe(1)
        ->and(DB::table('failed_jobs')->value('failed_at'))->toBeGreaterThan(now()->subDays(7)->toDateTimeString())
        ->and(DB::table('password_reset_tokens')->pluck('email')->all())->toBe(['new@example.com']);
});

test('database sessions and cache entries are deleted when they expire (#1306)', function () {
    config(['session.driver' => 'database', 'cache.default' => 'database']);
    DB::table('sessions')->insert([
        ['id' => 'old', 'user_id' => null, 'ip_address' => '192.0.2.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => now()->subMinutes(121)->getTimestamp()],
        ['id' => 'new', 'user_id' => null, 'ip_address' => '192.0.2.2', 'user_agent' => 'x', 'payload' => '', 'last_activity' => now()->subMinutes(5)->getTimestamp()],
    ]);
    DB::table('cache')->insert([
        ['key' => 'expired', 'value' => 'x', 'expiration' => now()->subMinute()->getTimestamp()],
        ['key' => 'valid', 'value' => 'x', 'expiration' => now()->addMinute()->getTimestamp()],
    ]);

    $this->artisan('retention:prune')->assertSuccessful();

    expect(DB::table('sessions')->pluck('id')->all())->toBe(['new'])
        ->and(DB::table('cache')->pluck('key')->all())->toBe(['valid']);
});

test('sessions and cache entries stay alone when they are not stored in the database (#1306)', function () {
    DB::table('sessions')->insert(['id' => 'old', 'user_id' => null, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => now()->subDay()->getTimestamp()]);

    $this->artisan('retention:prune')->assertSuccessful();

    expect(DB::table('sessions')->count())->toBe(1);
});

test('the dry run of the retention prune deletes nothing (#1306)', function () {
    $community = Community::factory()->create();
    $old = invitationCreatedDaysAgo($community, 60);

    $this->artisan('retention:prune', ['--dry-run' => true])
        ->expectsTable(['Data', 'To delete'], [['invitations', 1], ['invitation_imports', 0], ['invitation_mails', 0], ['api_tokens', 0], ['failed_jobs', 0], ['password_reset_tokens', 0], ['sessions', 0], ['cache', 0], ['notifications', 0], ['event_answers', 0], ['exports', 0], ['pending_emails', 0]])
        ->assertSuccessful();

    expect(CommunityInvitation::query()->whereKey($old->id)->exists())->toBeTrue();
});

test('the answer to an event is deleted 90 days after the end of the event, and the dry run deletes nothing (#1515)', function () {
    $answerOfEventThatEnded = function (int $daysAgo): EventAnswer {
        $event = EventModel::factory()->create(['starts_at' => now()->subDays($daysAgo)->subHour(), 'ends_at' => now()->subDays($daysAgo)]);

        return EventAnswer::factory()->to($event)->create();
    };
    $old = $answerOfEventThatEnded(91);
    $recent = $answerOfEventThatEnded(89);
    $upcoming = EventAnswer::factory()->create();
    // An answer that the member gave long ago to an event that is still to come stays: the end of the event counts
    $upcoming->forceFill(['created_at' => now()->subDays(400), 'updated_at' => now()->subDays(400)])->saveQuietly();

    // The dry run counts the one answer, and deletes nothing
    expect(app(PrunesExpiredData::class)->prune(true)['event_answers'])->toBe(1);
    $this->artisan('retention:prune', ['--dry-run' => true])->expectsOutputToContain('event_answers')->assertSuccessful();
    expect(EventAnswer::query()->count())->toBe(3);

    $this->artisan('retention:prune')->assertSuccessful();

    expect(EventAnswer::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(EventAnswer::query()->whereKey($recent->id)->exists())->toBeTrue()
        ->and(EventAnswer::query()->whereKey($upcoming->id)->exists())->toBeTrue()
        // The event stays: it is content of the community
        ->and(EventModel::query()->whereKey($old->event_id)->exists())->toBeTrue();

    // The period comes from the configuration
    config(['retention.event_answers_days' => 80]);
    $this->artisan('retention:prune')->assertSuccessful();
    expect(EventAnswer::query()->whereKey($recent->id)->exists())->toBeFalse();
});

function exportFileAgeDays(string $name, int $days): string
{
    Storage::disk('local')->put('exports/'.$name, 'zip');
    touch(Storage::disk('local')->path('exports/'.$name), now()->subDays($days)->getTimestamp());

    return 'exports/'.$name;
}

test('community export files are deleted after 7 days, a recent one stays (#1353)', function () {
    Storage::fake('local');
    $old = exportFileAgeDays('community-old-20260101-000000.zip', 8);
    $recent = exportFileAgeDays('community-new-20260101-000000.zip', 6);

    $this->artisan('retention:prune')->assertSuccessful();

    expect(Storage::disk('local')->exists($old))->toBeFalse()
        ->and(Storage::disk('local')->exists($recent))->toBeTrue();
});

test('the period of the export files is configurable, and the dry run keeps them (#1353)', function () {
    Storage::fake('local');
    $file = exportFileAgeDays('community-late-20260101-000000.zip', 2);
    expect(Retention::exportsDays())->toBe(7);

    config(['retention.exports_days' => 1]);
    $this->artisan('retention:prune', ['--dry-run' => true])
        ->expectsTable(['Data', 'To delete'], [['invitations', 0], ['api_tokens', 0], ['failed_jobs', 0], ['password_reset_tokens', 0], ['sessions', 0], ['cache', 0], ['exports', 1]])
        ->assertSuccessful();

    expect(Storage::disk('local')->exists($file))->toBeTrue();

    $this->artisan('retention:prune')->assertSuccessful();

    expect(Storage::disk('local')->exists($file))->toBeFalse();
});

function configureEmailOctopus(): void
{
    config([
        'services.emailoctopus.base_url' => 'https://emailoctopus.test',
        'services.emailoctopus.api_key' => 'key',
        'services.emailoctopus.waitlist_list_id' => 'wait',
        'services.emailoctopus.newsletter_list_id' => 'news',
    ]);
}

test('the waitlist keeps a contact 12 months, every list deletes unconfirmed and unsubscribed contacts after 30 days (#77)', function () {
    configureEmailOctopus();
    $this->freezeTime();
    Http::fake(function (Request $request) {
        if ($request->method() === 'DELETE') {
            return Http::response(null, 204);
        }
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $list = str_contains($request->url(), '/lists/wait/') ? 'wait' : 'news';
        $filter = isset($query['created_at_lte']) ? 'created' : 'updated';
        $status = $query['status'] ?? '';
        $cursor = $query['starting_after'] ?? null;

        // Two pages for the pending waitlist contacts, to follow the cursor
        if ($list === 'wait' && $filter === 'updated' && $status === 'pending') {
            return $cursor === null
                ? Http::response(['data' => [['id' => 'w-pending-1']], 'paging' => ['next' => ['starting_after' => 'c1']]])
                : Http::response(['data' => [['id' => 'w-pending-2']], 'paging' => ['next' => null]]);
        }
        $ids = match (true) {
            $list === 'wait' && $filter === 'created' && $status === 'subscribed' => ['w-old'],
            $list === 'wait' && $filter === 'created' && $status === 'pending' => ['w-pending-1'],
            $list === 'news' && $filter === 'updated' && $status === 'unsubscribed' => ['n-gone'],
            default => [],
        };

        return Http::response(['data' => array_map(static fn (string $id): array => ['id' => $id], $ids), 'paging' => ['next' => null]]);
    });

    $this->artisan('mailing-lists:prune')
        ->expectsTable(['List', 'Deleted'], [['waitlist', 3], ['newsletter', 1]])
        ->assertSuccessful();

    $deleted = collect(Http::recorded())->map(fn (array $pair) => $pair[0])->filter(fn (Request $r) => $r->method() === 'DELETE')
        ->map(fn (Request $r) => Str::after($r->url(), 'https://emailoctopus.test'))->values()->all();
    expect($deleted)->toEqualCanonicalizing([
        '/lists/wait/contacts/w-pending-1', '/lists/wait/contacts/w-pending-2', '/lists/wait/contacts/w-old', '/lists/news/contacts/n-gone',
    ]);

    Http::assertSent(fn (Request $r) => $r->method() === 'GET' && str_contains($r->url(), '/lists/wait/contacts')
        && str_contains(urldecode($r->url()), 'created_at.lte='.now()->subMonths(12)->toIso8601String())
        && $r->hasHeader('Authorization', 'Bearer key'));
    Http::assertSent(fn (Request $r) => $r->method() === 'GET' && str_contains($r->url(), '/lists/news/contacts')
        && str_contains(urldecode($r->url()), 'last_updated_at.lte='.now()->subDays(30)->toIso8601String()));
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/lists/news/contacts') && str_contains(urldecode($r->url()), 'created_at.lte'));
});

test('the mailing list prune does nothing without EmailOctopus (#77)', function () {
    Http::fake();
    config(['services.emailoctopus.api_key' => null]);
    $this->artisan('mailing-lists:prune')->assertSuccessful();
    Http::assertNothingSent();
});

test('the mailing list prune fails when the EmailOctopus API fails (#77)', function () {
    configureEmailOctopus();
    Http::fake(['*' => Http::response(['detail' => 'Unauthorised'], 401)]);
    $this->artisan('mailing-lists:prune')->expectsOutputToContain('status 401')->assertFailed();
});

test('the dry run of the mailing list prune deletes nothing (#77)', function () {
    configureEmailOctopus();
    Http::fake(['*' => Http::response(['data' => [['id' => 'x']], 'paging' => ['next' => null]])]);

    $this->artisan('mailing-lists:prune', ['--dry-run' => true])
        ->expectsTable(['List', 'To delete'], [['waitlist', 1], ['newsletter', 1]])
        ->assertSuccessful();

    Http::assertNotSent(fn (Request $r) => $r->method() === 'DELETE');
});

test('a new email address that waits for its confirmation is removed 7 days after the request, the account stays (#1706)', function () {
    Storage::fake('local');
    $this->travelTo(Carbon\Carbon::parse('2026-11-21 10:00:00'));
    $old = User::factory()->create(['email' => 'old-request@example.com', 'pending_email' => 'old-request.new@example.com', 'pending_email_requested_at' => now()->subDays(7)]);
    $recent = User::factory()->create(['email' => 'recent-request@example.com', 'pending_email' => 'recent-request.new@example.com', 'pending_email_requested_at' => now()->subDays(6)]);

    expect(Retention::pendingEmailDays())->toBe(7);

    $this->artisan('retention:prune', ['--dry-run' => true])->assertSuccessful();
    expect($old->fresh()->getRawOriginal('pending_email'))->toBe('old-request.new@example.com');

    $this->artisan('retention:prune')->assertSuccessful();
    expect($old->fresh())->not->toBeNull()
        ->and($old->fresh()->email)->toBe('old-request@example.com')
        ->and($old->fresh()->getRawOriginal('pending_email'))->toBeNull()
        ->and($old->fresh()->getRawOriginal('pending_email_requested_at'))->toBeNull()
        ->and($recent->fresh()->getRawOriginal('pending_email'))->toBe('recent-request.new@example.com');
});

test('a preview of invitations that nobody sent goes 24 hours after the upload, a sent import 37 days after its confirm, with its rows (#1390)', function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-11-18T10:00:00Z'));
    $community = Community::factory()->create();
    $oldPreview = InvitationImport::factory()->create(['community_id' => $community->id, 'created_at' => now()->subHours(24)->subSecond()]);
    $youngPreview = InvitationImport::factory()->create(['community_id' => $community->id, 'created_at' => now()->subHours(24)->addSecond()]);
    $oldSent = InvitationImport::factory()->sending()->create(['community_id' => $community->id, 'confirmed_at' => now()->subDays(37)->subSecond()]);
    $youngSent = InvitationImport::factory()->done()->create(['community_id' => $community->id, 'confirmed_at' => now()->subDays(37)->addSecond()]);
    InvitationImportRow::factory()->create(['invitation_import_id' => $oldSent->id]);

    $this->artisan('retention:prune', ['--dry-run' => true])->assertSuccessful();
    expect(InvitationImport::query()->count())->toBe(4);

    expect(app(PrunesExpiredData::class)->prune()['invitation_imports'])->toBe(2)
        ->and(InvitationImport::query()->pluck('id')->sort()->values()->all())->toBe([$youngPreview->id, $youngSent->id])
        ->and(InvitationImportRow::query()->where('invitation_import_id', $oldSent->id)->exists())->toBeFalse();

    config(['retention.invitation_import_previews_hours' => 48]);
    expect(Retention::periods()['invitation_import_previews'])->toBe('48 hours');
});

test('the count of the daily caps of the invitation mails goes 24 hours after each mail (#1390)', function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-11-18T10:00:00Z'));
    $community = Community::factory()->create();
    DB::table('invitation_mails')->insert([
        ['community_id' => $community->id, 'user_id' => null, 'created_at' => now()->subHours(24)->subSecond()],
        ['community_id' => $community->id, 'user_id' => null, 'created_at' => now()->subHours(24)->addSecond()],
    ]);

    expect(app(PrunesExpiredData::class)->prune(dryRun: true)['invitation_mails'])->toBe(1)
        ->and(DB::table('invitation_mails')->count())->toBe(2)
        ->and(app(PrunesExpiredData::class)->prune()['invitation_mails'])->toBe(1)
        ->and(DB::table('invitation_mails')->pluck('created_at')->all())->toBe(['2026-11-17 10:00:01']);
});

test('the record of a join through an invite link goes 365 days after the join, and the count of the link stays (#1391)', function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-11-23T10:00:00Z'));
    $link = InviteLink::factory()->create();
    $link->forceFill(['uses_count' => 2])->save();
    $old = InviteLinkUse::query()->create(['invite_link_id' => $link->id, 'user_id' => User::factory()->create()->id]);
    $old->forceFill(['created_at' => now()->subDays(365)->subSecond()])->save();
    $recent = InviteLinkUse::query()->create(['invite_link_id' => $link->id, 'user_id' => User::factory()->create()->id]);
    $recent->forceFill(['created_at' => now()->subDays(365)->addSecond()])->save();

    expect(app(PrunesExpiredData::class)->prune(dryRun: true)['invite_link_uses'])->toBe(1)
        ->and(InviteLinkUse::query()->count())->toBe(2)
        ->and(app(PrunesExpiredData::class)->prune()['invite_link_uses'])->toBe(1)
        ->and(InviteLinkUse::query()->pluck('id')->all())->toBe([$recent->id])
        ->and($link->fresh()?->uses_count)->toBe(2);

    config(['retention.invite_link_uses_days' => 30]);
    expect(Retention::periods()['invite_link_uses'])->toBe('30 days')
        ->and(app(PrunesExpiredData::class)->prune()['invite_link_uses'])->toBe(1);
});

test('the delivery log and the reports of the mail provider go 30 days after they were written (#1478)', function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-11-23T10:00:00Z'));
    $user = User::factory()->create();
    foreach ([31, 29] as $days) {
        $delivery = MailDelivery::query()->create(['kind' => KnowiiMailKind::AskedFor, 'user_id' => $user->id, 'status' => KnowiiMailDeliveryStatus::Sent]);
        $delivery->forceFill(['created_at' => now()->subDays($days)])->save();
        $event = MailWebhookEvent::query()->create(['provider_event_id' => 'msg_'.$days, 'type' => 'email.delivered']);
        $event->forceFill(['created_at' => now()->subDays($days)])->save();
    }

    expect(app(PrunesExpiredData::class)->prune(dryRun: true))->toMatchArray(['mail_deliveries' => 1, 'mail_webhook_events' => 1])
        ->and(MailDelivery::query()->count())->toBe(2)
        ->and(app(PrunesExpiredData::class)->prune())->toMatchArray(['mail_deliveries' => 1, 'mail_webhook_events' => 1])
        ->and(MailWebhookEvent::query()->pluck('provider_event_id')->all())->toBe(['msg_29'])
        ->and(Retention::periods())->toMatchArray(['mail_deliveries' => '30 days', 'mail_webhook_events' => '30 days', 'email_soft_bounces' => '90 days']);
});

test('a count of soft bounces goes 90 days after the last bounce, and a row of another address goes; a suppressed row of the current address stays (#1478)', function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-11-23T10:00:00Z'));
    $old = User::factory()->create(['email' => 'old@example.com']);
    $recent = User::factory()->create(['email' => 'recent@example.com']);
    $suppressed = User::factory()->create(['email' => 'Kept@example.com']);
    $moved = User::factory()->create(['email' => 'moved@example.com']);
    EmailSuppression::query()->create(['user_id' => $old->id, 'email' => 'old@example.com', 'soft_bounce_count' => 2, 'last_event_at' => now()->subDays(91)]);
    EmailSuppression::query()->create(['user_id' => $recent->id, 'email' => 'recent@example.com', 'soft_bounce_count' => 1, 'last_event_at' => now()->subDays(89)]);
    EmailSuppression::query()->create(['user_id' => $suppressed->id, 'email' => 'kept@example.com', 'reason' => KnowiiEmailSuppressionReason::HardBounce, 'suppressed_at' => now()->subYears(2), 'last_event_at' => now()->subYears(2)]);
    EmailSuppression::query()->create(['user_id' => $moved->id, 'email' => 'moved.before@example.com', 'reason' => KnowiiEmailSuppressionReason::Complaint, 'suppressed_at' => now(), 'last_event_at' => now()]);

    expect(app(PrunesExpiredData::class)->prune(dryRun: true)['email_suppressions'])->toBe(2)
        ->and(EmailSuppression::query()->count())->toBe(4)
        ->and(app(PrunesExpiredData::class)->prune()['email_suppressions'])->toBe(2)
        ->and(EmailSuppression::query()->orderBy('email')->pluck('email')->all())->toBe(['kept@example.com', 'recent@example.com']);
});

test('the prune of the mail data runs on empty tables (#1478)', function () {
    Storage::fake('local');

    expect(app(PrunesExpiredData::class)->prune())->toMatchArray(['mail_deliveries' => 0, 'mail_webhook_events' => 0, 'email_suppressions' => 0]);
});
