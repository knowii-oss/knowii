<?php

use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\User;
use App\Support\Retention;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
        ->and($periods['password_reset_link'])->toBe('60 minutes')
        ->and($periods['invitation'])->toBe('7 days')
        ->and($periods['expired_invitations'])->toBe('30 days')
        ->and($periods['api_token_default'])->toBe('90 days')
        ->and($periods['expired_api_tokens'])->toBe('30 days')
        ->and($periods['failed_jobs'])->toBe('7 days')
        ->and($periods['unused_uploads'])->toBe('7 days')
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
        ->expectsTable(['Data', 'To delete'], [['invitations', 1], ['api_tokens', 0], ['failed_jobs', 0], ['password_reset_tokens', 0], ['sessions', 0], ['cache', 0]])
        ->assertSuccessful();

    expect(CommunityInvitation::query()->whereKey($old->id)->exists())->toBeTrue();
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
