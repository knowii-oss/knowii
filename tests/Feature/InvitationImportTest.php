<?php

use App\Actions\InvitationImports\SendInvitationImportMails;
use App\ApiRoutes;
use App\Contracts\CommunityMembers\AcceptsCommunityInvitations;
use App\Contracts\InvitationImports\ConfirmsInvitationImports;
use App\Contracts\InvitationImports\CreatesInvitationImports;
use App\Contracts\InvitationImports\DeletesInvitationImports;
use App\Contracts\InvitationImports\ResendsInvitationImports;
use App\Contracts\InvitationImports\SendsInvitationImportMails;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiInvitationBounceType;
use App\Enums\KnowiiInvitationImportOutcome;
use App\Enums\KnowiiInvitationImportState;
use App\Enums\KnowiiMailKind;
use App\Enums\KnowiiModeratorPermission;
use App\Events\CommunityMembers\CommunityMembersChanged;
use App\Events\InvitationImports\InvitationImportChanged;
use App\Exceptions\BusinessException;
use App\Jobs\SendInvitationImportMail;
use App\Mail\CommunityInvitation as CommunityInvitationMail;
use App\Models\AccessGroup;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\CommunityJoinBlock;
use App\Models\InvitationImport;
use App\Models\InvitationImportRow;
use App\Models\MailDelivery;
use App\Models\MemberTag;
use App\Models\User;
use App\Support\InvitationGrants;
use App\Support\InvitationImports\InvitationCsv;
use App\Support\InvitationImports\InvitationImportCheckInput;
use App\Support\InvitationImports\InvitationImportChecks;
use App\Support\InvitationImports\InvitationImportCounts;
use App\Support\InvitationImports\InvitationImports;
use App\Support\InvitationMailCaps;
use App\Support\MailSettings;
use App\Support\MemberTagAssignments;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\Mail\RefusingTransport;

/*
 * Invitations from a file (#1390 part B, ADR 0061): the checks of the rows, the confirm, the mails, the hold and the send
 * again. The API routes are in InvitationImportApiTest.
 */

beforeEach(function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-18T10:00:00Z'));
    config(['queue.default' => 'database', 'mail.default' => 'array', 'mail.queue' => 'mail', 'mail.rate_limit_per_second' => 1000]);
    $this->c = communityWithMembers();
    $this->community = $this->c['community'];
    // Fixed names: the assertions read them
    $this->community->forceFill(['name' => 'Qxcommunity Example', 'slug' => 'qxcommunity-example'])->save();
    foreach (['owner', 'admin', 'moderator', 'member', 'outsider'] as $who) {
        $this->c[$who]->forceFill(['email' => 'qx-'.$who.'@example.com', 'name' => 'Qx '.$who])->save();
    }
    $this->paid = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Paid']);
    $this->founder = MemberTag::factory()->forMembers()->create(['community_id' => $this->community->id, 'name' => 'Founder']);
    $this->onboarded = MemberTag::factory()->forManagers()->create(['community_id' => $this->community->id, 'name' => 'Onboarded']);
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * @param  array<string, mixed>  $defaults
 */
function importFrom(User $user, Community $community, string $csv, array $defaults = []): InvitationImport
{
    $result = InvitationCsv::parse($csv);
    expect($result->error)->toBeNull();

    return app(CreatesInvitationImports::class)->create($user, $community, $result->records, $defaults);
}

/**
 * The result of each row, by its number: [outcome, reason, detail]
 *
 * @return array<int, array{0: string, 1: string|null, 2: string|null}>
 */
function importRows(InvitationImport $import): array
{
    $rows = [];
    foreach ($import->rows()->orderBy('row_number')->get() as $row) {
        $rows[$row->row_number] = [$row->outcome->value, $row->reason?->value, $row->detail];
    }

    return $rows;
}

function importLetModeratorsManageMembers(Community $community): void
{
    $community->update(['moderator_permissions' => [...KnowiiModeratorPermission::defaults(), 'manage_members' => true]]);
}

/**
 * Runs the queued jobs of the mail queue with a real worker, like production (--tries=3)
 */
function importRunWorker(int $times = 1): void
{
    foreach (range(1, $times) as $ignored) {
        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--queue' => 'mail', '--sleep' => 0, '--tries' => 3]);
    }
}

/**
 * The mails that the array transport holds
 *
 * @return list<string> their recipients
 */
function importSentTo(): array
{
    $transport = Mail::mailer('array')->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(ArrayTransport::class);
    $to = [];
    if ($transport instanceof ArrayTransport) {
        foreach ($transport->messages() as $message) {
            foreach ($message->getEnvelope()->getRecipients() as $address) {
                $to[] = $address->getAddress();
            }
        }
    }

    return $to;
}

function importHoldMail(?CarbonImmutable $until): void
{
    DB::table('mail_usage_months')->updateOrInsert(
        ['month' => '2026-11-01'],
        ['cuid' => 'invitationimportrow00000a', 'sent_count' => 0, 'held_until' => $until, 'created_at' => now(), 'updated_at' => now()],
    );
}

/**
 * Invitation mails that went to the queue at a time, as the caps count them
 */
function importMailsSent(?Community $community, ?User $user, int $count, CarbonInterface $at): void
{
    DB::table('invitation_mails')->insert(array_fill(0, $count, ['community_id' => $community?->id, 'user_id' => $user?->id, 'created_at' => $at]));
}

function importInvitePath(Community $community): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_INVITATIONS, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]);
}

/**
 * A sent import of one row per address, with its invitations, as the confirm leaves it
 *
 * @param  list<string>  $emails
 */
function importSent(Community $community, User $user, array $emails): InvitationImport
{
    $import = importFrom($user, $community, "email\n".implode("\n", $emails)."\n");
    app(ConfirmsInvitationImports::class)->confirm($user, $import);

    return $import->refresh();
}

// ---------------------------------------------------------------------------------------------------------------------
// The checks of the rows
// ---------------------------------------------------------------------------------------------------------------------

test('each reason of a refused row, with its detail (#1390)', function () {
    $removed = User::factory()->create(['email' => 'qx-removed@example.com']);
    CommunityJoinBlock::query()->create(['community_id' => $this->community->id, 'user_id' => $removed->id]);
    $this->community->communityInvitations()->create(['email' => 'qx-pending@example.com', 'role' => KnowiiCommunityMemberRole::Member]);
    $many = implode('|', MemberTag::factory()->count(21)->sequence(fn ($sequence) => ['name' => 'Qxmany '.$sequence->index])->create(['community_id' => $this->community->id])->pluck('name')->all());
    $long = str_repeat('x', 1001);

    $import = importFrom($this->c['owner'], $this->community, implode("\n", [
        'email,role,access_groups,tags',
        'qx-a@example.com,member,,,extra',
        'qx-b@example.com,'.$long.',,',
        'not an address,member,,',
        'qx-d@example.com,,,',
        'QX-D@example.com,,,',
        'qx-f@example.com,owner,,',
        'qx-g@example.com,admin,Nope,',
        'qx-h@example.com,,Paid,Nope',
        'qx-i@example.com,,,'.$many,
        'qx-member@example.com,,,',
        'qx-removed@example.com,,,',
        'qx-pending@example.com,,,',
    ])."\n");

    expect(importRows($import))->toBe([
        2 => ['refused', 'extra_cells', null],
        3 => ['refused', 'cell_too_long', null],
        4 => ['refused', 'invalid_email', null],
        5 => ['valid', null, null],
        6 => ['refused', 'duplicate_in_file', '5'],
        7 => ['refused', 'unknown_role', null],
        8 => ['refused', 'unknown_access_group', 'Nope'],
        9 => ['refused', 'unknown_tag', 'Nope'],
        10 => ['refused', 'too_many_tags', null],
        11 => ['refused', 'already_member', null],
        12 => ['refused', 'removed_by_manager', null],
        13 => ['refused', 'pending_invitation', '2026-11-25T10:00:00Z'],
    ]);
});

test('a row with two problems gets the first reason of the order (#1390)', function () {
    $import = importFrom($this->c['admin'], $this->community, "email,role,access_groups\nqx-member@example.com,admin,Nope\nqx-x@example.com,owner,Nope\nqx-member@example.com,,Nope\n");

    expect(importRows($import))->toBe([
        2 => ['refused', 'role_not_allowed', null],
        3 => ['refused', 'unknown_role', null],
        4 => ['refused', 'duplicate_in_file', '2'],
    ]);
});

test('the rights of the uploader: groups and tags of a row need them (#1390)', function () {
    Gate::before(static fn (User $user, string $ability) => in_array($ability, ['manageAccessGroupMembers', 'assignMemberTags'], true) ? false : null);

    $import = importFrom($this->c['admin'], $this->community, "email,access_groups,tags\nqx-a@example.com,Paid,\nqx-b@example.com,,Founder\nqx-c@example.com,,\n");

    expect(importRows($import))->toBe([
        2 => ['refused', 'access_groups_not_allowed', null],
        3 => ['refused', 'tags_not_allowed', null],
        4 => ['valid', null, null],
    ]);
});

test('addresses are compared in lower case: an account, a pending invitation, an earlier row, the owner (#1390)', function () {
    $this->c['member']->forceFill(['email' => 'Qx-Member@Example.com'])->save();
    $this->community->communityInvitations()->create(['email' => 'qx-pending@example.com', 'role' => KnowiiCommunityMemberRole::Member]);

    $import = importFrom($this->c['owner'], $this->community, "email\nqx-member@example.com\nQX-PENDING@example.com\nqx-new@example.com\nQx-New@Example.com\nQX-OWNER@example.com\n");

    expect(array_map(fn (array $row) => $row[1], importRows($import)))->toBe([
        2 => 'already_member',
        3 => 'pending_invitation',
        4 => null,
        5 => 'duplicate_in_file',
        6 => 'already_member',
    ])
        ->and($import->rows()->where('row_number', 5)->value('email'))->toBe('qx-new@example.com');
});

test('a valid pending invitation refuses its row; an expired one is replaced at the confirm (#1390)', function () {
    $this->community->communityInvitations()->create(['email' => 'qx-valid@example.com', 'role' => KnowiiCommunityMemberRole::Member]);
    $expired = $this->community->communityInvitations()->create(['email' => 'qx-expired@example.com', 'role' => KnowiiCommunityMemberRole::Admin]);
    $expired->forceFill(['created_at' => now()->subDays(8)])->save();
    Queue::fake();

    $import = importFrom($this->c['owner'], $this->community, "email\nqx-valid@example.com\nqx-expired@example.com\n");
    expect(array_map(fn (array $row) => $row[1], importRows($import)))->toBe([2 => 'pending_invitation', 3 => null]);

    app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $import);

    $new = CommunityInvitation::query()->where('email', 'qx-expired@example.com')->sole();
    expect($new->id)->not->toBe($expired->id)
        ->and($new->role)->toBe(KnowiiCommunityMemberRole::Member)
        ->and(importRows($import)[3][0])->toBe('queued');
});

test('a person whom a manager removed is refused; a person who left by themselves is valid (#1390)', function () {
    $removed = User::factory()->create(['email' => 'Qx-Removed@Example.com']);
    $left = User::factory()->create(['email' => 'qx-left@example.com']);
    CommunityJoinBlock::query()->create(['community_id' => $this->community->id, 'user_id' => $removed->id]);

    $import = importFrom($this->c['owner'], $this->community, "email\nqx-removed@example.com\nqx-left@example.com\nqx-nobody@example.com\n");

    expect(array_map(fn (array $row) => $row[1], importRows($import)))->toBe([2 => 'removed_by_manager', 3 => null, 4 => null])
        ->and($left->exists)->toBeTrue();
});

test('the role hierarchy: each row needs a role below the role of the uploader (#1390)', function () {
    importLetModeratorsManageMembers($this->community);
    $csv = "email,role\nqx-1@example.com,member\nqx-2@example.com,Moderator\nqx-3@example.com,ADMIN\n";

    expect(array_map(fn (array $row) => $row[1], importRows(importFrom($this->c['moderator'], $this->community, $csv))))->toBe([2 => null, 3 => 'role_not_allowed', 4 => 'role_not_allowed'])
        ->and(array_map(fn (array $row) => $row[1], importRows(importFrom($this->c['admin'], $this->community, $csv))))->toBe([2 => null, 3 => null, 4 => 'role_not_allowed'])
        ->and(array_map(fn (array $row) => $row[1], importRows(importFrom($this->c['owner'], $this->community, $csv))))->toBe([2 => null, 3 => null, 4 => null]);
});

test('groups by name: exact, in another case, two that differ in the case, unknown; tags by name in any case (#1390)', function () {
    AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Vip']);
    AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'VIP']);

    $import = importFrom($this->c['owner'], $this->community, implode("\n", [
        'email,access_groups,tags',
        'qx-1@example.com,Paid,',
        'qx-2@example.com,  paid  ,',
        'qx-3@example.com,vip,',
        'qx-4@example.com,VIP,',
        'qx-5@example.com,Unknown | Paid,',
        'qx-6@example.com,,FOUNDER|onboarded',
        'qx-7@example.com,,Founder|Nobody',
    ])."\n");

    expect(importRows($import))->toBe([
        2 => ['valid', null, null],
        3 => ['valid', null, null],
        4 => ['refused', 'ambiguous_access_group', 'vip'],
        5 => ['valid', null, null],
        6 => ['refused', 'unknown_access_group', 'Unknown'],
        7 => ['valid', null, null],
        8 => ['refused', 'unknown_tag', 'Nobody'],
    ])
        ->and($import->rows()->where('row_number', 3)->sole()->access_group_names)->toBe(['Paid'])
        ->and($import->rows()->where('row_number', 7)->sole()->member_tag_names)->toBe(['Founder', 'Onboarded']);
});

test('the defaults join each row; 21 tags with the defaults refuse the row; the role of a row replaces the default (#1390)', function () {
    $nineteen = MemberTag::factory()->count(19)->sequence(fn ($sequence) => ['name' => 'Qxdef '.$sequence->index])->create(['community_id' => $this->community->id]);

    $import = importFrom($this->c['owner'], $this->community, "email,role,tags\nqx-1@example.com,,\nqx-2@example.com,moderator,Founder\nqx-3@example.com,,Founder|Onboarded\n", [
        'default_role' => 'admin',
        'access_groups' => [$this->paid->cuid],
        'member_tags' => $nineteen->pluck('cuid')->all(),
    ]);
    $rows = $import->rows()->orderBy('row_number')->get();

    expect($import->default_role)->toBe(KnowiiCommunityMemberRole::Admin)
        ->and($import->default_access_groups)->toBe([$this->paid->cuid])
        ->and($rows[0]->role)->toBe(KnowiiCommunityMemberRole::Admin)
        ->and($rows[0]->access_group_names)->toBe(['Paid'])
        ->and($rows[0]->member_tag_names)->toHaveCount(19)
        ->and($rows[1]->role)->toBe(KnowiiCommunityMemberRole::Moderator)
        ->and($rows[1]->member_tag_names)->toHaveCount(20)
        ->and($rows[1]->outcome)->toBe(KnowiiInvitationImportOutcome::Valid)
        ->and($rows[2]->reason?->value)->toBe('too_many_tags');
});

test('a default that the uploader may not give refuses the whole upload (#1390)', function () {
    importLetModeratorsManageMembers($this->community);

    expect(fn () => importFrom($this->c['moderator'], $this->community, "email\nqx@example.com\n", ['default_role' => 'moderator']))->toThrow(AuthorizationException::class)
        ->and(fn () => importFrom($this->c['owner'], $this->community, "email\nqx@example.com\n", ['access_groups' => ['nothing']]))->toThrow(ValidationException::class)
        ->and(fn () => importFrom($this->c['member'], $this->community, "email\nqx@example.com\n"))->toThrow(AuthorizationException::class);

    Gate::before(static fn (User $user, string $ability) => $ability === 'assignMemberTags' ? false : null);
    expect(fn () => importFrom($this->c['admin'], $this->community, "email\nqx@example.com\n", ['member_tags' => [$this->founder->cuid]]))->toThrow(AuthorizationException::class, 'You cannot give member tags')
        ->and(InvitationImport::query()->count())->toBe(0);
});

test('the checks run with the same number of queries for 10 and for 1000 rows (#1390)', function () {
    $this->community->communityInvitations()->create(['email' => 'qx-p1@example.com', 'role' => KnowiiCommunityMemberRole::Member]);
    $count = function (int $rows): int {
        $inputs = array_map(fn (int $n) => new InvitationImportCheckInput($n + 1, 'qx-p'.$n.'@example.com', '', $n % 2 === 0 ? ['Paid'] : [], ['Founder']), range(1, $rows));
        InvitationImportChecks::check($this->c['owner'], $this->community, array_slice($inputs, 0, 1), KnowiiCommunityMemberRole::Member);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $checked = InvitationImportChecks::check($this->c['owner'], $this->community, $inputs, KnowiiCommunityMemberRole::Member, [$this->paid], [$this->onboarded]);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        expect($checked)->toHaveCount($rows)
            ->and($checked[0]->reason?->value)->toBe('pending_invitation');

        return $queries;
    };

    $ten = $count(10);
    $thousand = $count(1000);

    expect($thousand)->toBe($ten)->and($ten)->toBeLessThanOrEqual(6);
});

test('a new upload of the same person deletes their older preview; the previews of others stay (#1390)', function () {
    $first = importFrom($this->c['owner'], $this->community, "email\nqx-1@example.com\n");
    $other = importFrom($this->c['admin'], $this->community, "email\nqx-2@example.com\n");
    $second = importFrom($this->c['owner'], $this->community, "email\nqx-3@example.com\n");

    expect(InvitationImport::query()->pluck('id')->sort()->values()->all())->toBe([$other->id, $second->id])
        ->and(InvitationImportRow::query()->where('invitation_import_id', $first->id)->count())->toBe(0);
});

test('a personal community imports nobody (#1390)', function () {
    $owner = User::factory()->withUserProfile()->create();
    $personal = Community::factory()->create(['owner_id' => $owner->id, 'visibility' => 'personal']);

    expect(fn () => importFrom($owner, $personal, "email\nqx@example.com\n"))->toThrow(BusinessException::class, 'A personal community cannot invite anyone');
});

// ---------------------------------------------------------------------------------------------------------------------
// The confirm
// ---------------------------------------------------------------------------------------------------------------------

test('the confirm creates the invitations with their groups and tags, queues one job per row and tells the uploader (#1390)', function () {
    Queue::fake();
    Event::fake([CommunityMembersChanged::class, InvitationImportChanged::class]);
    $import = importFrom($this->c['owner'], $this->community, "email,role,access_groups,tags\nqx-1@example.com,moderator,Paid,Founder\nqx-2@example.com,,,\nnot valid,,,\n", ['member_tags' => [$this->onboarded->cuid]]);

    $sent = app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $import);

    $first = CommunityInvitation::query()->where('email', 'qx-1@example.com')->sole();
    expect($sent->state)->toBe(KnowiiInvitationImportState::Sending)
        ->and($sent->confirmed_at?->toIso8601ZuluString())->toBe('2026-11-18T10:00:00Z')
        ->and($first->role)->toBe(KnowiiCommunityMemberRole::Moderator)
        ->and($first->accessGroups()->pluck('access_groups.id')->all())->toBe([$this->paid->id])
        ->and($first->memberTags()->pluck('member_tags.id')->sort()->values()->all())->toBe([$this->founder->id, $this->onboarded->id])
        ->and(importRows($import))->toBe([2 => ['queued', null, null], 3 => ['queued', null, null], 4 => ['refused', 'invalid_email', null]])
        ->and($import->rows()->where('row_number', 2)->value('community_invitation_id'))->toBe($first->id);
    Queue::assertPushed(SendInvitationImportMail::class, 2);
    Queue::assertPushedOn('mail', SendInvitationImportMail::class);
    Event::assertDispatchedTimes(CommunityMembersChanged::class, 1);
    Event::assertDispatched(InvitationImportChanged::class, fn (InvitationImportChanged $event) => $event->userCuid === $this->c['owner']->cuid && $event->importCuid === $import->cuid);
});

test('only the uploader sends a preview, one time (#1390)', function () {
    Queue::fake();
    $import = importFrom($this->c['owner'], $this->community, "email\nqx-1@example.com\n");

    expect(fn () => app(ConfirmsInvitationImports::class)->confirm($this->c['admin'], $import))->toThrow(AuthorizationException::class, 'Only the person who uploaded the file sends it');
    app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $import);
    expect(fn () => app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $import->refresh()))->toThrow(BusinessException::class, 'This import is sent already')
        ->and(CommunityInvitation::query()->where('email', 'qx-1@example.com')->count())->toBe(1);
    Queue::assertPushed(SendInvitationImportMail::class, 1);
});

test('a preview older than 24 hours cannot be sent, also before the prune step (#1390)', function () {
    $import = importFrom($this->c['owner'], $this->community, "email\nqx-1@example.com\n");
    $this->travel(24)->hours();
    $this->travel(1)->seconds();

    expect(fn () => app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $import))->toThrow(ModelNotFoundException::class);
});

test('the confirm checks each row again: a person who joined, a tag deleted, an invitation sent meanwhile (#1390)', function () {
    Queue::fake();
    $import = importFrom($this->c['owner'], $this->community, "email,tags\nqx-joined@example.com,\nqx-tagged@example.com,Founder\nqx-single@example.com,\nqx-fine@example.com,\n");
    $joined = User::factory()->create(['email' => 'qx-joined@example.com']);
    $this->community->users()->attach($joined, ['role' => KnowiiCommunityMemberRole::Member]);
    $this->founder->delete();
    $this->actingAs($this->c['admin'])->postJson(ApiRoutes::path(ApiRoutes::COMMUNITY_INVITATIONS, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid]), ['email' => 'qx-single@example.com', 'role' => 'member'])->assertCreated();

    app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $import);

    expect(importRows($import))->toBe([
        2 => ['refused', 'already_member', null],
        3 => ['refused', 'unknown_tag', 'Founder'],
        4 => ['refused', 'pending_invitation', '2026-11-25T10:00:00Z'],
        5 => ['queued', null, null],
    ])
        ->and(CommunityInvitation::query()->where('email', 'qx-single@example.com')->count())->toBe(1);
    Queue::assertPushed(SendInvitationImportMail::class, 1);
});

test('the confirm refuses the whole import while the budget holds mail, and nothing changes (#1390)', function () {
    Queue::fake();
    $import = importFrom($this->c['owner'], $this->community, "email\nqx-1@example.com\n");
    // The end of the hold is rounded up to the minute
    importHoldMail(CarbonImmutable::parse('2026-12-01T00:00:30Z'));

    expect(fn () => app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $import))
        ->toThrow(BusinessException::class, 'This instance holds its mail now. Send the invitations from 2026-12-01 00:01 UTC.')
        ->and($import->refresh()->state)->toBe(KnowiiInvitationImportState::Preview)
        ->and(importRows($import))->toBe([2 => ['valid', null, null]])
        ->and(CommunityInvitation::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('the confirm refuses an import that needs more mails than the month has left before the hold; no number of the instance shows (#1390)', function () {
    Queue::fake();
    config(['mail.budget.monthly_quota' => 100, 'mail.budget.hold_percent' => 90]);
    DB::table('mail_usage_months')->insert(['cuid' => 'invitationimportrow00000b', 'month' => '2026-11-01', 'sent_count' => 88, 'created_at' => now(), 'updated_at' => now()]);
    $import = importFrom($this->c['owner'], $this->community, "email\nqx-1@example.com\nqx-2@example.com\nqx-3@example.com\n");

    expect(fn () => app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $import))
        ->toThrow(BusinessException::class, 'This import needs 3 emails. This instance cannot send so many this month. Send fewer rows.')
        ->and($import->refresh()->state)->toBe(KnowiiInvitationImportState::Preview)
        ->and(CommunityInvitation::query()->count())->toBe(0);

    // Two mails are left (90 - 88): a file of two rows goes
    $two = importFrom($this->c['owner'], $this->community, "email\nqx-1@example.com\nqx-2@example.com\n");
    expect(app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $two)->state)->toBe(KnowiiInvitationImportState::Sending);
});

test('the confirm refuses a community past 1000 invitation emails in 24 hours, says how many can go and when; a delete lowers nothing (#1390)', function () {
    Queue::fake();
    importMailsSent($this->community, null, 999, now()->subHours(23));
    $import = importFrom($this->c['owner'], $this->community, "email\nqx-1@example.com\nqx-2@example.com\n");
    // The records that a manager deletes hold no part of the count
    InvitationImport::query()->whereKeyNot($import->id)->delete();
    CommunityInvitation::query()->delete();

    expect(fn () => app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $import))
        ->toThrow(BusinessException::class, 'A community sends at most 1000 invitation emails in 24 hours. 1 more can go now. These 2 invitations can go from 2026-11-18 11:00 UTC.')
        ->and($import->refresh()->state)->toBe(KnowiiInvitationImportState::Preview)
        ->and(CommunityInvitation::query()->count())->toBe(0)
        ->and(DB::table('invitation_mails')->count())->toBe(999);

    // 24 hours after them the older mails do not count, and the two rows count from now
    $this->travel(1)->hours();
    expect(app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $import)->state)->toBe(KnowiiInvitationImportState::Sending)
        ->and(DB::table('invitation_mails')->where('created_at', now())->where('community_id', $this->community->id)->where('user_id', $this->c['owner']->id)->count())->toBe(2);
});

test('every invitation mail counts in the cap of the community: a single invitation, the rows of a confirm, a send again (#1390)', function () {
    Queue::fake();
    Mail::fake();
    config(['mail.invitations.per_community_per_day' => 4, 'mail.invitations.per_account_per_day' => 100]);

    // A single invitation, then a replace of it: two mails
    $this->actingAs($this->c['owner'])->postJson(importInvitePath($this->community), ['email' => 'qx-single@example.com', 'role' => 'member'])->assertCreated();
    $this->actingAs($this->c['owner'])->postJson(importInvitePath($this->community), ['email' => 'qx-single@example.com', 'role' => 'member'])->assertCreated();
    // A confirm of one row: three
    $this->travel(1)->minutes();
    $import = importSent($this->community, $this->c['admin'], ['qx-1@example.com']);
    // A send again of that row: four
    $this->travel(1)->minutes();
    $import->rows()->update(['outcome' => 'held']);
    app(ResendsInvitationImports::class)->resend($this->c['owner'], $import->refresh(), ['rows' => 'not_sent']);
    expect(DB::table('invitation_mails')->where('community_id', $this->community->id)->count())->toBe(4);

    // The fifth mail of any kind is refused, with the time from when it works again
    $this->actingAs($this->c['admin'])->postJson(importInvitePath($this->community), ['email' => 'qx-other@example.com', 'role' => 'member'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'A community sends at most 4 invitation emails in 24 hours. Invite this person from 2026-11-19 10:00 UTC.');
    $next = importFrom($this->c['owner'], $this->community, "email\nqx-2@example.com\n");
    expect(fn () => app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $next))
        ->toThrow(BusinessException::class, 'A community sends at most 4 invitation emails in 24 hours. 0 more can go now. This invitation can go from 2026-11-19 10:00 UTC.');
    $import->rows()->update(['outcome' => 'held']);
    expect(fn () => app(ResendsInvitationImports::class)->resend($this->c['owner'], $import->refresh(), ['rows' => 'not_sent']))
        ->toThrow(BusinessException::class, 'A community sends at most 4 invitation emails in 24 hours. 0 more can go now. This invitation can go from 2026-11-19 10:00 UTC.');

    // Nothing changed: no new invitation, no mail, no count
    expect(CommunityInvitation::query()->where('email', 'qx-other@example.com')->exists())->toBeFalse()
        ->and(DB::table('invitation_mails')->count())->toBe(4)
        ->and(importRows($import))->toBe([2 => ['held', null, null]]);
    Mail::assertQueued(CommunityInvitationMail::class, 2);

    // A cancel of the invitation lowers nothing
    CommunityInvitation::query()->where('email', 'qx-single@example.com')->delete();
    $this->actingAs($this->c['owner'])->postJson(importInvitePath($this->community), ['email' => 'qx-single@example.com', 'role' => 'member'])->assertStatus(422);

    // 24 hours after the first two mails, two can go again
    $this->travelTo(CarbonImmutable::parse('2026-11-19T10:00:00Z'));
    $this->actingAs($this->c['owner'])->postJson(importInvitePath($this->community), ['email' => 'qx-single@example.com', 'role' => 'member'])->assertCreated();
});

test('"Invite again the people who did not join" counts in the caps too, and is refused past one (#1390)', function () {
    Queue::fake();
    config(['mail.invitations.per_community_per_day' => 2, 'mail.invitations.per_account_per_day' => 100]);
    $import = importSent($this->community, $this->c['owner'], ['qx-1@example.com']);
    $import->rows()->update(['outcome' => 'sent', 'sent_at' => now()]);
    $this->travel(3)->days();
    importMailsSent($this->community, null, 2, now()->subHour());

    expect(fn () => app(ResendsInvitationImports::class)->resend($this->c['owner'], $import->refresh(), ['rows' => 'not_joined']))
        ->toThrow(BusinessException::class, 'A community sends at most 2 invitation emails in 24 hours. 0 more can go now. This invitation can go from 2026-11-22 09:00 UTC.')
        ->and($import->refresh()->resent_at)->toBeNull();

    $this->travelTo(CarbonImmutable::parse('2026-11-22T09:00:00Z'));
    app(ResendsInvitationImports::class)->resend($this->c['owner'], $import->refresh(), ['rows' => 'not_joined']);
    expect(DB::table('invitation_mails')->where('created_at', now())->where('user_id', $this->c['owner']->id)->count())->toBe(1);
});

test('an account sends at most its cap of invitation emails in all its communities, and each other account has its own (#1390)', function () {
    Queue::fake();
    Mail::fake();
    config(['mail.invitations.per_community_per_day' => 100, 'mail.invitations.per_account_per_day' => 3]);
    $second = Community::factory()->create(['owner_id' => $this->c['owner']->id, 'name' => 'Qxcommunity Second', 'slug' => 'qxcommunity-second']);

    $this->actingAs($this->c['owner'])->postJson(importInvitePath($this->community), ['email' => 'qx-1@example.com', 'role' => 'member'])->assertCreated();
    $this->actingAs($this->c['owner'])->postJson(importInvitePath($second), ['email' => 'qx-2@example.com', 'role' => 'member'])->assertCreated();
    $import = importFrom($this->c['owner'], $second, "email\nqx-3@example.com\nqx-4@example.com\n");

    expect(fn () => app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $import))
        ->toThrow(BusinessException::class, 'An account sends at most 3 invitation emails in 24 hours, in all its communities. 1 more can go now. These 2 invitations can go from 2026-11-19 10:00 UTC.');
    $this->actingAs($this->c['owner'])->postJson(importInvitePath($second), ['email' => 'qx-5@example.com', 'role' => 'member'])->assertCreated();
    $this->actingAs($this->c['owner'])->postJson(importInvitePath($this->community), ['email' => 'qx-6@example.com', 'role' => 'member'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'An account sends at most 3 invitation emails in 24 hours, in all its communities. Invite this person from 2026-11-19 10:00 UTC.');

    // Another manager of the same community sends with their own account
    $this->actingAs($this->c['admin'])->postJson(importInvitePath($this->community), ['email' => 'qx-6@example.com', 'role' => 'member'])->assertCreated();
    expect(DB::table('invitation_mails')->where('user_id', $this->c['owner']->id)->count())->toBe(3)
        ->and(DB::table('invitation_mails')->where('user_id', $this->c['admin']->id)->count())->toBe(1);
});

test('a deleted community or account leaves the count of the other cap (#1390)', function () {
    Queue::fake();
    Mail::fake();
    config(['mail.invitations.per_community_per_day' => 2, 'mail.invitations.per_account_per_day' => 2]);
    $second = Community::factory()->create(['owner_id' => $this->c['owner']->id, 'name' => 'Qxcommunity Second', 'slug' => 'qxcommunity-second']);
    $this->actingAs($this->c['owner'])->postJson(importInvitePath($second), ['email' => 'qx-1@example.com', 'role' => 'member'])->assertCreated();
    $this->actingAs($this->c['owner'])->postJson(importInvitePath($second), ['email' => 'qx-2@example.com', 'role' => 'member'])->assertCreated();

    $second->delete();

    expect(DB::table('invitation_mails')->whereNull('community_id')->where('user_id', $this->c['owner']->id)->count())->toBe(2);
    $this->actingAs($this->c['owner'])->postJson(importInvitePath($this->community), ['email' => 'qx-3@example.com', 'role' => 'member'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'An account sends at most 2 invitation emails in 24 hours, in all its communities. Invite this person from 2026-11-19 10:00 UTC.');
});

test('a refusal reads both caps: the words of the cap that frees last, the fewest that can go now and the later time (#1390)', function () {
    Queue::fake();
    Mail::fake();
    config(['mail.invitations.per_community_per_day' => 1, 'mail.invitations.per_account_per_day' => 1]);
    $second = Community::factory()->create(['owner_id' => $this->c['owner']->id, 'name' => 'Qxcommunity Second', 'slug' => 'qxcommunity-second']);

    // The community is full from 10:00, the account of the owner from 11:00
    $this->actingAs($this->c['admin'])->postJson(importInvitePath($this->community), ['email' => 'qx-1@example.com', 'role' => 'member'])->assertCreated();
    $this->travel(1)->hours();
    $this->actingAs($this->c['owner'])->postJson(importInvitePath($second), ['email' => 'qx-2@example.com', 'role' => 'member'])->assertCreated();
    $this->travel(1)->hours();
    $this->actingAs($this->c['owner'])->postJson(importInvitePath($this->community), ['email' => 'qx-3@example.com', 'role' => 'member'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'An account sends at most 1 invitation emails in 24 hours, in all its communities. Invite this person from 2026-11-19 11:00 UTC.');

    // A set of 3: the community has 1 place now and room for it from 08:00 tomorrow, the account 2 places and room from 11:00
    DB::table('invitation_mails')->delete();
    config(['mail.invitations.per_community_per_day' => 3, 'mail.invitations.per_account_per_day' => 4]);
    importMailsSent($this->community, null, 2, CarbonImmutable::parse('2026-11-18T08:00:00Z'));
    importMailsSent(null, $this->c['owner'], 2, CarbonImmutable::parse('2026-11-18T11:00:00Z'));
    $import = importFrom($this->c['owner'], $this->community, "email\nqx-4@example.com\nqx-5@example.com\nqx-6@example.com\n");
    expect(fn () => app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $import))
        ->toThrow(BusinessException::class, 'An account sends at most 4 invitation emails in 24 hours, in all its communities. 1 more can go now. These 3 invitations can go from 2026-11-19 11:00 UTC.');
});

test('the cap of the instance counts every invitation mail of the last 24 hours, also one whose community and account are deleted (#1713)', function () {
    Mail::fake();
    config(['mail.invitations.per_instance_per_day' => 3]);
    importMailsSent(null, null, 3, now()->subHour());

    $this->actingAs($this->c['owner'])->postJson(importInvitePath($this->community), ['email' => 'qx-1@example.com', 'role' => 'member'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This instance sends no more invitation emails now. Invite this person from 2026-11-19 09:00 UTC.');
    expect(CommunityInvitation::query()->where('email', 'qx-1@example.com')->exists())->toBeFalse()
        ->and(DB::table('invitation_mails')->count())->toBe(3);
    Mail::assertNothingQueued();

    // 24 hours after them the mails do not count
    $this->travelTo(CarbonImmutable::parse('2026-11-19T09:00:00Z'));
    $this->actingAs($this->c['owner'])->postJson(importInvitePath($this->community), ['email' => 'qx-1@example.com', 'role' => 'member'])->assertCreated();
});

test('every invitation mail of every community and account counts in the cap of the instance, and the fifth of each kind is refused with no change (#1713)', function () {
    Queue::fake();
    Mail::fake();
    config(['mail.invitations.per_community_per_day' => 100, 'mail.invitations.per_account_per_day' => 100, 'mail.invitations.per_instance_per_day' => 4]);
    $second = Community::factory()->create(['owner_id' => $this->c['outsider']->id, 'name' => 'Qxcommunity Second', 'slug' => 'qxcommunity-second']);

    // Four mails, from two communities and three accounts: two single invitations and two confirms of one row
    $this->actingAs($this->c['outsider'])->postJson(importInvitePath($second), ['email' => 'qx-a@example.com', 'role' => 'member'])->assertCreated();
    $this->actingAs($this->c['owner'])->postJson(importInvitePath($this->community), ['email' => 'qx-single@example.com', 'role' => 'member'])->assertCreated();
    $notSent = importSent($this->community, $this->c['admin'], ['qx-1@example.com']);
    $notSent->rows()->update(['outcome' => 'held']);
    $notJoined = importSent($second, $this->c['outsider'], ['qx-2@example.com']);
    $notJoined->rows()->update(['outcome' => 'sent', 'sent_at' => now()]);
    $notJoined->forceFill(['confirmed_at' => now()->subDays(10)])->save();
    $next = importFrom($this->c['owner'], $this->community, "email\nqx-3@example.com\n");
    expect(DB::table('invitation_mails')->distinct()->count('community_id'))->toBe(2)
        ->and(DB::table('invitation_mails')->count())->toBe(4);
    $invitations = CommunityInvitation::query()->orderBy('id')->pluck('id')->all();

    // A single invitation, a replace of an invitation, a confirm, a send again `not_sent` and `not_joined`
    $this->actingAs($this->c['admin'])->postJson(importInvitePath($this->community), ['email' => 'qx-new@example.com', 'role' => 'member'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This instance sends no more invitation emails now. Invite this person from 2026-11-19 10:00 UTC.');
    $this->actingAs($this->c['owner'])->postJson(importInvitePath($this->community), ['email' => 'qx-single@example.com', 'role' => 'member'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This instance sends no more invitation emails now. Invite this person from 2026-11-19 10:00 UTC.');
    expect(fn () => app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $next))
        ->toThrow(BusinessException::class, 'This instance cannot send so many invitation emails now. This invitation can go from 2026-11-19 10:00 UTC.');
    expect(fn () => app(ResendsInvitationImports::class)->resend($this->c['owner'], $notSent->refresh(), ['rows' => 'not_sent']))
        ->toThrow(BusinessException::class, 'This instance cannot send so many invitation emails now. This invitation can go from 2026-11-19 10:00 UTC.');
    expect(fn () => app(ResendsInvitationImports::class)->resend($this->c['outsider'], $notJoined->refresh(), ['rows' => 'not_joined']))
        ->toThrow(BusinessException::class, 'This instance cannot send so many invitation emails now. This invitation can go from 2026-11-19 10:00 UTC.');

    // Nothing changed: no invitation, no count, no mail, no change of an import or of its rows
    expect(CommunityInvitation::query()->orderBy('id')->pluck('id')->all())->toBe($invitations)
        ->and(DB::table('invitation_mails')->count())->toBe(4)
        ->and($next->refresh()->state)->toBe(KnowiiInvitationImportState::Preview)
        ->and(importRows($notSent))->toBe([2 => ['held', null, null]])
        ->and(importRows($notJoined))->toBe([2 => ['sent', null, null]])
        ->and($notJoined->refresh()->resent_at)->toBeNull();
    Mail::assertQueued(CommunityInvitationMail::class, 2);
    Queue::assertPushed(SendInvitationImportMail::class, 2);
});

test('a set larger than the cap of the instance can never go in one go, and the words show no number of the instance (#1713)', function () {
    Queue::fake();
    config(['mail.invitations.per_instance_per_day' => 2]);
    $import = importFrom($this->c['owner'], $this->community, "email\nqx-1@example.com\nqx-2@example.com\nqx-3@example.com\n");

    expect(fn () => app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $import))
        ->toThrow(BusinessException::class, 'This instance cannot send so many invitation emails in 24 hours. Send fewer rows.')
        ->and($import->refresh()->state)->toBe(KnowiiInvitationImportState::Preview)
        ->and(DB::table('invitation_mails')->count())->toBe(0);

    // A set of 3 with 1 place left: no count of the places
    config(['mail.invitations.per_instance_per_day' => 4]);
    importMailsSent(null, null, 3, CarbonImmutable::parse('2026-11-18T09:00:00Z'));
    expect(fn () => app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $import))
        ->toThrow(BusinessException::class, 'This instance cannot send so many invitation emails now. These 3 invitations can go from 2026-11-19 09:00 UTC.');
});

test('the most specific cap that refuses gives its words: the instance only when the community and the account take the set (#1713)', function () {
    Queue::fake();
    Mail::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-18T12:00:00Z'));
    config(['mail.invitations.per_community_per_day' => 1, 'mail.invitations.per_account_per_day' => 100, 'mail.invitations.per_instance_per_day' => 1]);

    // The community frees at 10:00 tomorrow, the instance at 11:00: the words and the time of the community
    importMailsSent($this->community, null, 1, CarbonImmutable::parse('2026-11-18T10:00:00Z'));
    importMailsSent(null, null, 1, CarbonImmutable::parse('2026-11-18T11:00:00Z'));
    $this->actingAs($this->c['owner'])->postJson(importInvitePath($this->community), ['email' => 'qx-1@example.com', 'role' => 'member'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'A community sends at most 1 invitation emails in 24 hours. Invite this person from 2026-11-19 10:00 UTC.');

    // The community frees at 11:00 tomorrow, the instance at 10:00
    DB::table('invitation_mails')->delete();
    config(['mail.invitations.per_instance_per_day' => 2]);
    importMailsSent(null, null, 1, CarbonImmutable::parse('2026-11-18T10:00:00Z'));
    importMailsSent($this->community, null, 1, CarbonImmutable::parse('2026-11-18T11:00:00Z'));
    $this->actingAs($this->c['owner'])->postJson(importInvitePath($this->community), ['email' => 'qx-1@example.com', 'role' => 'member'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'A community sends at most 1 invitation emails in 24 hours. Invite this person from 2026-11-19 11:00 UTC.');
    $one = importFrom($this->c['owner'], $this->community, "email\nqx-2@example.com\n");
    expect(fn () => app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $one))
        ->toThrow(BusinessException::class, 'A community sends at most 1 invitation emails in 24 hours. 0 more can go now. This invitation can go from 2026-11-19 11:00 UTC.');

    // The account refuses too: its words, as on main
    config(['mail.invitations.per_community_per_day' => 100, 'mail.invitations.per_account_per_day' => 1]);
    importMailsSent(null, $this->c['owner'], 1, CarbonImmutable::parse('2026-11-18T11:30:00Z'));
    expect(fn () => app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $one))
        ->toThrow(BusinessException::class, 'An account sends at most 1 invitation emails in 24 hours, in all its communities. 0 more can go now. This invitation can go from 2026-11-19 11:30 UTC.');

    // A cap of the community that can never take the set: its words "in 24 hours", with the places of the community
    config(['mail.invitations.per_community_per_day' => 1, 'mail.invitations.per_account_per_day' => 100]);
    $two = importFrom($this->c['owner'], $this->community, "email\nqx-3@example.com\nqx-4@example.com\n");
    expect(fn () => app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $two))
        ->toThrow(BusinessException::class, 'A community sends at most 1 invitation emails in 24 hours. 0 more can go now.');

    // Only the instance refuses: its words, with no number of the instance
    config(['mail.invitations.per_community_per_day' => 100]);
    expect(fn () => app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $two))
        ->toThrow(BusinessException::class, 'This instance cannot send so many invitation emails now. These 2 invitations can go from 2026-11-19 11:30 UTC.')
        ->and(CommunityInvitation::query()->count())->toBe(0)
        ->and(DB::table('invitation_mails')->count())->toBe(3);
    Mail::assertNothingQueued();
    Queue::assertNothingPushed();
});

test('the hold of the mail budget and the month refuse before the cap of the instance (#1713)', function () {
    Queue::fake();
    Mail::fake();
    config(['mail.invitations.per_instance_per_day' => 1]);
    importMailsSent(null, null, 1, now()->subHour());
    $import = importFrom($this->c['owner'], $this->community, "email\nqx-1@example.com\nqx-2@example.com\nqx-3@example.com\n");

    // The hold first, for a single invitation and for a confirm
    importHoldMail(CarbonImmutable::parse('2026-11-18T12:00:00Z'));
    $this->actingAs($this->c['owner'])->postJson(importInvitePath($this->community), ['email' => 'qx-single@example.com', 'role' => 'member'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This instance holds its mail now. Invite this person from 2026-11-18 12:00 UTC.');
    expect(fn () => app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $import))
        ->toThrow(BusinessException::class, 'This instance holds its mail now. Send the invitations from 2026-11-18 12:00 UTC.');

    // Then the month, for a confirm
    DB::table('mail_usage_months')->delete();
    config(['mail.budget.monthly_quota' => 100, 'mail.budget.hold_percent' => 90]);
    DB::table('mail_usage_months')->insert(['cuid' => 'invitationimportrow00000d', 'month' => '2026-11-01', 'sent_count' => 88, 'created_at' => now(), 'updated_at' => now()]);
    expect(fn () => app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $import))
        ->toThrow(BusinessException::class, 'This import needs 3 emails. This instance cannot send so many this month. Send fewer rows.');

    // Then the cap of the instance
    $one = importFrom($this->c['owner'], $this->community, "email\nqx-4@example.com\n");
    expect(fn () => app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $one))
        ->toThrow(BusinessException::class, 'This instance cannot send so many invitation emails now. This invitation can go from 2026-11-19 09:00 UTC.')
        ->and(DB::table('invitation_mails')->count())->toBe(1);
});

test('a single invitation waits for the end of a hold of the mail budget: 422 with the end, and nothing is written (#1390)', function () {
    Mail::fake();
    // The end of the hold is rounded up to the minute
    importHoldMail(CarbonImmutable::parse('2026-11-18T12:34:30Z'));

    $this->actingAs($this->c['owner'])->postJson(importInvitePath($this->community), ['email' => 'qx-held@example.com', 'role' => 'member'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This instance holds its mail now. Invite this person from 2026-11-18 12:35 UTC.');
    expect(CommunityInvitation::query()->where('email', 'qx-held@example.com')->exists())->toBeFalse()
        ->and(DB::table('invitation_mails')->count())->toBe(0);
    Mail::assertNothingQueued();

    // A hold that ended refuses nothing
    importHoldMail(CarbonImmutable::parse('2026-11-18T09:00:00Z'));
    $this->actingAs($this->c['owner'])->postJson(importInvitePath($this->community), ['email' => 'qx-held@example.com', 'role' => 'member'])->assertCreated();
    expect(DB::table('invitation_mails')->count())->toBe(1);
    Mail::assertQueued(CommunityInvitationMail::class, 1);
});

test('the caps lock the community, the account, then the instance, with advisory locks of the transaction, in one order for every path; the instance just before the counts (#1390, #1713)', function () {
    Queue::fake();
    Mail::fake();
    $statements = [];
    DB::listen(function ($query) use (&$statements) {
        $statements[] = [$query->sql, $query->bindings];
    });

    $this->actingAs($this->c['owner'])->postJson(importInvitePath($this->community), ['email' => 'qx-lock@example.com', 'role' => 'member'])->assertCreated();
    $single = $statements;
    $import = importFrom($this->c['owner'], $this->community, "email\nqx-lock-2@example.com\n");
    $statements = [];
    app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $import);
    $confirm = $statements;

    $first = static fn (array $list, callable $match): ?int => array_key_first(array_filter($list, static fn (array $statement): bool => $match($statement)));
    $lockOf = static fn (int $namespace): callable => static fn (array $statement): bool => str_contains($statement[0], 'pg_advisory_xact_lock') && $statement[1][0] === $namespace;
    $from = static fn (string $table, string $lock = ''): callable => static fn (array $statement): bool => str_contains($statement[0], 'from "'.$table.'"') && str_contains($statement[0], $lock);
    foreach ([$single, $confirm] as $list) {
        $locks = array_values(array_filter($list, static fn (array $statement): bool => str_contains($statement[0], 'pg_advisory_xact_lock')));
        $order = [];
        foreach ($locks as $lock) {
            $order[$lock[1][0]] ??= $lock[1];
        }
        // The first time of each lock: the community, the account, then the instance; nothing after the instance
        expect(array_values($order))->toBe([[InvitationMailCaps::LOCK_COMMUNITY, $this->community->id], [InvitationMailCaps::LOCK_ACCOUNT, $this->c['owner']->id], [InvitationMailCaps::LOCK_INSTANCE, 0]])
            ->and(end($locks)[1])->toBe([InvitationMailCaps::LOCK_INSTANCE, 0])
            // The hold (and for the confirm the month) is read before the lock of the instance, the counts after it
            ->and($first($list, $from('mail_usage_months')))->toBeLessThan($first($list, $lockOf(InvitationMailCaps::LOCK_INSTANCE)))
            ->and($first($list, $lockOf(InvitationMailCaps::LOCK_INSTANCE)))->toBeLessThan($first($list, $from('invitation_mails')));
    }
    // A single invitation takes the locks of the community and of the account before it reads the invitations of the
    // address, and the lock of the instance after
    expect($first($single, $lockOf(InvitationMailCaps::LOCK_COMMUNITY)))->toBeLessThan($first($single, $from('community_invitations', 'for update')))
        ->and($first($single, $from('community_invitations', 'for update')))->toBeLessThan($first($single, $lockOf(InvitationMailCaps::LOCK_INSTANCE)))
        // A confirm checks its rows before the lock of the instance
        ->and($first($confirm, $lockOf(InvitationMailCaps::LOCK_ACCOUNT)))->toBeLessThan($first($confirm, $from('community_join_blocks')))
        ->and($first($confirm, $from('community_join_blocks')))->toBeLessThan($first($confirm, $lockOf(InvitationMailCaps::LOCK_INSTANCE)));

    // No row lock of the community or of the account for the caps; the confirm locks the community row `for no key update`
    $all = [...$single, ...$confirm];
    expect(array_filter($all, static fn (array $statement): bool => (str_contains($statement[0], 'from "users"') || str_contains($statement[0], 'from "communities"')) && str_contains($statement[0], 'for update')))->toBe([])
        ->and(array_filter($all, static fn (array $statement): bool => str_contains($statement[0], 'from "communities"') && str_contains($statement[0], 'for no key update')))->toHaveCount(1);
});

test('an invitation of the address that comes while a single invitation waits for its locks is replaced, and the request gives 201 (#1390)', function () {
    Mail::fake();
    $done = false;
    DB::listen(function ($query) use (&$done) {
        if (! $done && str_contains($query->sql, 'pg_advisory_xact_lock')) {
            $done = true;
            DB::table('community_invitations')->insert(['cuid' => 'qxsameinvitation0000000a', 'community_id' => $this->community->id, 'email' => 'qx-same@example.com', 'role' => 'member', 'created_at' => now(), 'updated_at' => now()]);
        }
    });

    $this->actingAs($this->c['owner'])->postJson(importInvitePath($this->community), ['email' => 'qx-same@example.com', 'role' => 'member'])->assertCreated();

    expect(CommunityInvitation::query()->where('email', 'qx-same@example.com')->count())->toBe(1)
        ->and(CommunityInvitation::query()->where('cuid', 'qxsameinvitation0000000a')->exists())->toBeFalse();
});

test('the caps are read from the configuration: a whole number of at least 1, else 1000 (#1390, #1713)', function (mixed $value, int $cap) {
    config(['mail.invitations.per_community_per_day' => $value, 'mail.invitations.per_account_per_day' => $value, 'mail.invitations.per_instance_per_day' => $value]);

    expect(MailSettings::invitationsPerCommunityPerDay())->toBe($cap)
        ->and(MailSettings::invitationsPerAccountPerDay())->toBe($cap)
        ->and(MailSettings::invitationsPerInstancePerDay())->toBe($cap);
})->with([
    'a number' => [250, 250],
    'a numeric string' => [' 40 ', 40],
    'empty' => ['', 1000],
    'zero' => ['0', 1000],
    'a word' => ['many', 1000],
]);

test('without MAIL_MONTHLY_QUOTA the confirm reads the last limit and use that the budget check stored; without any quota the caps still apply (#1390)', function () {
    Queue::fake();
    config(['mail.budget.monthly_quota' => 0, 'mail.budget.hold_percent' => 90, 'mail.invitations.per_community_per_day' => 4]);
    // The provider counted 85 of 100 when this instance had sent 48; it sent 2 more since
    DB::table('mail_usage_months')->insert([
        'cuid' => 'invitationimportrow00000c', 'month' => '2026-11-01', 'sent_count' => 50,
        'provider_limit' => 100, 'provider_used' => 85, 'provider_sent_count' => 48, 'created_at' => now(), 'updated_at' => now(),
    ]);

    // 90 before the hold, less 87: three mails are left
    $four = importFrom($this->c['owner'], $this->community, "email\nqx-1@example.com\nqx-2@example.com\nqx-3@example.com\nqx-4@example.com\n");
    expect(fn () => app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $four))
        ->toThrow(BusinessException::class, 'This import needs 4 emails. This instance cannot send so many this month. Send fewer rows.');
    $three = importFrom($this->c['owner'], $this->community, "email\nqx-1@example.com\nqx-2@example.com\nqx-3@example.com\n");
    expect(app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $three)->state)->toBe(KnowiiInvitationImportState::Sending);

    // No limit is known: the month does not refuse, the cap of the community does
    DB::table('mail_usage_months')->update(['provider_limit' => null, 'provider_used' => null, 'provider_sent_count' => null]);
    $two = importFrom($this->c['owner'], $this->community, "email\nqx-5@example.com\nqx-6@example.com\n");
    expect(fn () => app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $two))
        ->toThrow(BusinessException::class, 'A community sends at most 4 invitation emails in 24 hours. 1 more can go now.');
});

test('a row queued longer than the retries of a mail, one attempt and 15 minutes counts as not sent, and "Send the mails that were not sent" takes it (#1390)', function () {
    Queue::fake();
    config(['mail.budget.monthly_quota' => 100, 'mail.budget.hold_percent' => 100]);
    DB::table('mail_usage_months')->insert(['cuid' => 'invitationimportrow00000d', 'month' => '2026-11-01', 'sent_count' => 99, 'created_at' => now(), 'updated_at' => now()]);
    // Row 2 stays queued: its mail did not go out
    $import = importSent($this->community, $this->c['owner'], ['qx-1@example.com']);
    $counts = function () use ($import): array {
        $fresh = $import->refresh();
        InvitationImportCounts::attach([$fresh]);

        return [$fresh->rowCounts['queued'] ?? null, $fresh->rowCounts['not_sent'] ?? null];
    };
    expect($counts())->toBe([1, 0])
        ->and(fn () => app(ResendsInvitationImports::class)->resend($this->c['owner'], $import->refresh(), ['rows' => 'not_sent']))->toThrow(BusinessException::class, 'No row to send');

    // 2 hours, 60 seconds and 15 minutes: the row still waits for its job
    $this->travel(2)->hours();
    $this->travel(16)->minutes();
    expect(InvitationImports::staleQueuedBefore()->toIso8601ZuluString())->toBe('2026-11-18T10:00:00Z')
        ->and($counts())->toBe([1, 0])
        ->and(fn () => app(ResendsInvitationImports::class)->resend($this->c['owner'], $import->refresh(), ['rows' => 'not_sent']))->toThrow(BusinessException::class, 'No row to send');
    $this->travel(1)->seconds();
    expect($counts())->toBe([1, 1]);

    // The month counts it no more: the one mail left goes
    app(ResendsInvitationImports::class)->resend($this->c['owner'], $import->refresh(), ['rows' => 'not_sent']);
    expect(importRows($import))->toBe([2 => ['queued', null, null]])
        ->and($import->rows()->sole()->queued_at?->toIso8601ZuluString())->toBe('2026-11-18T12:16:01Z')
        ->and($counts())->toBe([1, 0]);
    Queue::assertPushed(SendInvitationImportMail::class, 2);
});

test('the record of a sent import cannot go while its mails go out, nor before 24 hours after its last mail; a preview goes at once (#1390)', function () {
    Queue::fake();
    $import = importSent($this->community, $this->c['owner'], ['qx-1@example.com']);
    $delete = fn () => app(DeletesInvitationImports::class)->delete($this->c['owner'], $import->refresh());

    expect($delete)->toThrow(BusinessException::class, 'The mails of this import are going out. Delete this record when they are sent.');
    $import->rows()->update(['outcome' => 'sent', 'sent_at' => now()]);
    $import->forceFill(['state' => 'done', 'finished_at' => now()])->save();
    $this->travel(1)->hours();
    expect($delete)->toThrow(BusinessException::class, 'You can delete this record from 2026-11-19 10:00 UTC.')
        ->and(InvitationImport::query()->whereKey($import->id)->exists())->toBeTrue();

    $this->travelTo(CarbonImmutable::parse('2026-11-19T10:00:00Z'));
    $delete();
    expect(InvitationImport::query()->whereKey($import->id)->exists())->toBeFalse()
        ->and(CommunityInvitation::query()->where('email', 'qx-1@example.com')->exists())->toBeTrue();

    // A row that stays queued does not keep the record for ever: 24 hours after it, the record goes
    $stuck = importSent($this->community, $this->c['owner'], ['qx-2@example.com']);
    $this->travel(24)->hours();
    $this->travel(1)->seconds();
    app(DeletesInvitationImports::class)->delete($this->c['owner'], $stuck->refresh());
    expect(InvitationImport::query()->whereKey($stuck->id)->exists())->toBeFalse();

    $preview = importFrom($this->c['owner'], $this->community, "email\nqx-3@example.com\n");
    app(DeletesInvitationImports::class)->delete($this->c['owner'], $preview);
    expect(InvitationImport::query()->whereKey($preview->id)->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------------------------------------------------
// The mails
// ---------------------------------------------------------------------------------------------------------------------

test('each job sends the mail of a single invitation, counts the link from the mail, and the import ends (#1390)', function () {
    Event::fake([CommunityMembersChanged::class, InvitationImportChanged::class]);
    $import = importSent($this->community, $this->c['admin'], ['qx-1@example.com', 'qx-2@example.com']);
    $job = DB::table('jobs')->where('queue', 'mail')->orderBy('id')->first();
    expect((string) $job?->payload)->not->toContain('qx-1@example.com')
        ->and(DB::table('jobs')->where('queue', 'mail')->count())->toBe(2);

    $this->travel(1)->hours();
    importRunWorker(2);

    $invitation = CommunityInvitation::query()->where('email', 'qx-1@example.com')->sole();
    expect(importSentTo())->toBe(['qx-1@example.com', 'qx-2@example.com'])
        ->and(importRows($import))->toBe([2 => ['sent', null, null], 3 => ['sent', null, null]])
        ->and($invitation->created_at->toIso8601ZuluString())->toBe('2026-11-18T11:00:00Z')
        ->and($import->refresh()->state)->toBe(KnowiiInvitationImportState::Done)
        ->and($import->finished_at?->toIso8601ZuluString())->toBe('2026-11-18T11:00:00Z');
    Event::assertDispatched(InvitationImportChanged::class);

    // The same mail as a single invitation: its subject, its footer, and the name of the person who uploaded the file
    $message = Mail::mailer('array')->getSymfonyTransport()->messages()->first()?->getOriginalMessage();
    $single = (new CommunityInvitationMail($invitation, $this->c['admin']))->render();
    expect($message?->getSubject())->toBe('You are invited to Qxcommunity Example on Knowii')
        ->and((string) $message?->getHtmlBody())->toContain('We got your email address from the person who invited you')
        ->and($single)->toContain('Qx admin');
});

test('a held budget leaves the row held, and the job ends without a send (#1390)', function () {
    $import = importSent($this->community, $this->c['owner'], ['qx-1@example.com']);
    importHoldMail(CarbonImmutable::parse('2026-12-01T00:00:00Z'));

    importRunWorker();

    expect(importSentTo())->toBe([])
        ->and(importRows($import))->toBe([2 => ['held', null, null]])
        ->and(DB::table('jobs')->where('queue', 'mail')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and($import->refresh()->state)->toBe(KnowiiInvitationImportState::Done);
});

test('a refusal of the provider for its quota holds the budget, and the row is held (#1390)', function () {
    $import = importSent($this->community, $this->c['owner'], ['qx-1@example.com']);
    Mail::extend('refusing', static fn () => new RefusingTransport('monthly_quota_exceeded'));
    config(['mail.mailers.refusing' => ['transport' => 'refusing'], 'mail.default' => 'refusing']);
    Mail::purge('refusing');

    importRunWorker();

    expect(DB::table('mail_usage_months')->where('month', '2026-11-01')->value('held_until'))->toBe('2026-12-01 00:00:00')
        ->and(importRows($import))->toBe([2 => ['held', null, null]])
        ->and(DB::table('jobs')->where('queue', 'mail')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

test('a mail that fails after its retries leaves the row failed (#1390)', function () {
    $import = importSent($this->community, $this->c['owner'], ['qx-1@example.com']);
    Mail::extend('refusing', static fn () => new RefusingTransport(null));
    config(['mail.mailers.refusing' => ['transport' => 'refusing'], 'mail.default' => 'refusing']);
    Mail::purge('refusing');

    foreach (range(1, 3) as $try) {
        DB::table('jobs')->where('queue', 'mail')->update(['available_at' => now()->getTimestamp()]);
        importRunWorker();
    }

    expect(importRows($import))->toBe([2 => ['failed', null, null]])
        ->and(DB::table('failed_jobs')->count())->toBe(1)
        ->and((string) DB::table('failed_jobs')->value('payload'))->not->toContain('qx-1@example.com')
        ->and($import->refresh()->state)->toBe(KnowiiInvitationImportState::Done);
});

test('a cancelled invitation and a person who joined meanwhile are skipped (#1390)', function () {
    $import = importSent($this->community, $this->c['owner'], ['qx-cancelled@example.com', 'qx-joined@example.com']);
    CommunityInvitation::query()->where('email', 'qx-cancelled@example.com')->delete();
    $joined = User::factory()->create(['email' => 'Qx-Joined@Example.com']);
    $this->community->users()->attach($joined, ['role' => KnowiiCommunityMemberRole::Member]);

    importRunWorker(2);

    expect(importSentTo())->toBe([])
        ->and(importRows($import))->toBe([2 => ['skipped', null, null], 3 => ['skipped', null, null]])
        ->and(CommunityInvitation::query()->where('email', 'qx-joined@example.com')->exists())->toBeFalse();
});

test('an invitation accepted between the read of the job and its update gets no mail, and its row is skipped (#1390)', function () {
    $import = importSent($this->community, $this->c['owner'], ['qx-accepted@example.com']);
    // The accept commits right before the update of the invitation: the update changes no row
    $done = false;
    DB::beforeExecuting(static function (string $query) use (&$done): void {
        if (! $done && str_starts_with($query, 'update "community_invitations"')) {
            $done = true;
            DB::table('community_invitations')->where('email', 'qx-accepted@example.com')->delete();
        }
    });

    importRunWorker();

    expect($done)->toBeTrue()
        ->and(importSentTo())->toBe([])
        ->and(importRows($import))->toBe([2 => ['skipped', null, null]]);
});

test('the import becomes done one time, also when two workers end its last rows (#1390)', function () {
    Event::fake([CommunityMembersChanged::class, InvitationImportChanged::class]);
    $import = importSent($this->community, $this->c['owner'], ['qx-1@example.com']);
    InvitationImportRow::query()->where('invitation_import_id', $import->id)->update(['outcome' => 'sent']);

    expect(SendInvitationImportMails::finishIfDone($import, $this->community))->toBeTrue()
        ->and(SendInvitationImportMails::finishIfDone($import, $this->community))->toBeFalse();
    Event::assertDispatchedTimes(CommunityMembersChanged::class, 2);
    // A second worker on the same row changes nothing
    app(SendsInvitationImportMails::class)->hold($import->rows()->sole()->id, 1);
    expect(importRows($import))->toBe([2 => ['sent', null, null]]);
});

test('an invitation of an import gives its groups and its tags at the join (#1390)', function () {
    $import = importFrom($this->c['owner'], $this->community, "email,access_groups,tags\nqx-grace@example.com,Paid,Onboarded\n");
    Queue::fake();
    app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $import);
    $grace = User::factory()->withUserProfile()->create(['email' => 'qx-grace@example.com']);

    app(AcceptsCommunityInvitations::class)->accept($grace, CommunityInvitation::query()->where('email', 'qx-grace@example.com')->sole());

    expect($this->paid->members()->whereKey($grace->id)->exists())->toBeTrue()
        ->and($this->onboarded->members()->whereKey($grace->id)->exists())->toBeTrue()
        ->and($import->rows()->sole()->community_invitation_id)->toBeNull();
});

// ---------------------------------------------------------------------------------------------------------------------
// Send again
// ---------------------------------------------------------------------------------------------------------------------

test('"Send the mails that were not sent" queues the held and the failed rows again, at any time (#1390)', function () {
    $import = importSent($this->community, $this->c['owner'], ['qx-1@example.com', 'qx-2@example.com', 'qx-3@example.com']);
    $rows = $import->rows()->orderBy('row_number')->get();
    $rows[0]->forceFill(['outcome' => 'held'])->save();
    $rows[1]->forceFill(['outcome' => 'failed'])->save();
    $rows[2]->forceFill(['outcome' => 'sent'])->save();
    $import->forceFill(['state' => 'done', 'finished_at' => now()])->save();
    DB::table('jobs')->where('queue', 'mail')->delete();
    $this->travel(5)->minutes();

    app(ResendsInvitationImports::class)->resend($this->c['admin'], $import, ['rows' => 'not_sent']);

    expect(importRows($import))->toBe([2 => ['queued', null, null], 3 => ['queued', null, null], 4 => ['sent', null, null]])
        ->and($import->refresh()->state)->toBe(KnowiiInvitationImportState::Sending)
        ->and($import->resent_at)->toBeNull()
        ->and($import->rows()->where('row_number', 2)->sole()->queued_at?->toIso8601ZuluString())->toBe('2026-11-18T10:05:00Z')
        ->and(DB::table('jobs')->where('queue', 'mail')->count())->toBe(2);

    importRunWorker(2);
    expect(importSentTo())->toBe(['qx-1@example.com', 'qx-2@example.com'])->and($import->refresh()->state)->toBe(KnowiiInvitationImportState::Done);
});

test('a row sent again after the time of the retries of its mail gets its mail; the job of the earlier time changes nothing (#1390)', function () {
    $import = importSent($this->community, $this->c['owner'], ['qx-1@example.com']);
    expect($import->rows()->sole()->queued_count)->toBe(1);
    $this->travel(2)->hours();
    $this->travel(17)->minutes();

    app(ResendsInvitationImports::class)->resend($this->c['owner'], $import->refresh(), ['rows' => 'not_sent']);
    expect($import->rows()->sole()->queued_count)->toBe(2)
        ->and(DB::table('jobs')->where('queue', 'mail')->count())->toBe(2);

    // The worker takes the job of the first time after its retries and fails it, then the job of the second time
    importRunWorker(2);

    expect(importRows($import))->toBe([2 => ['sent', null, null]])
        ->and(importSentTo())->toBe(['qx-1@example.com'])
        ->and(DB::table('failed_jobs')->count())->toBe(1)
        ->and(DB::table('jobs')->where('queue', 'mail')->count())->toBe(0)
        ->and($import->refresh()->state)->toBe(KnowiiInvitationImportState::Done);

    // A job of an earlier time holds, fails and sends nothing
    $mails = app(SendsInvitationImportMails::class);
    $rowId = $import->rows()->sole()->id;
    $import->rows()->update(['outcome' => 'queued']);
    $mails->hold($rowId, 1);
    $mails->fail($rowId, 1);
    $mails->send($rowId, 1);
    expect(importRows($import))->toBe([2 => ['queued', null, null]])
        ->and(importSentTo())->toBe(['qx-1@example.com']);
});

test('a send of the mails that were not sent leaves a row whose job may still send: one mail (#1390)', function () {
    $import = importSent($this->community, $this->c['owner'], ['qx-1@example.com']);
    $rowId = $import->rows()->sole()->id;
    // Past the retries of the job: an attempt that started just before their end still runs
    $this->travel(2)->hours();
    $this->travel(1)->minutes();
    $refusal = null;
    $done = false;
    DB::listen(function ($query) use (&$done, &$refusal, $import) {
        if (! $done && str_contains($query->sql, 'from "invitation_import_rows"')) {
            $done = true;
            try {
                app(ResendsInvitationImports::class)->resend($this->c['owner'], $import->refresh(), ['rows' => 'not_sent']);
            } catch (BusinessException $e) {
                $refusal = $e->getMessage();
            }
        }
    });

    app(SendsInvitationImportMails::class)->send($rowId, 1);
    importRunWorker(2);

    expect($refusal)->toBe('No row to send')
        ->and(importSentTo())->toBe(['qx-1@example.com'])
        ->and(importRows($import))->toBe([2 => ['sent', null, null]])
        ->and($import->rows()->sole()->queued_count)->toBe(1);
});

test('"Invite again the people who did not join": from 3 days after the send, one time (#1390)', function () {
    $import = importSent($this->community, $this->c['owner'], ['qx-1@example.com', 'qx-2@example.com']);
    importRunWorker(2);
    CommunityInvitation::query()->where('email', 'qx-2@example.com')->delete();
    $resend = fn () => app(ResendsInvitationImports::class)->resend($this->c['owner'], $import->refresh(), ['rows' => 'not_joined']);

    $this->travel(3)->days();
    $this->travel(-1)->seconds();
    expect($resend)->toThrow(BusinessException::class, 'You can invite these people again from 2026-11-21 10:00 UTC.');

    $this->travel(1)->seconds();
    expect($resend()->resent_at?->toIso8601ZuluString())->toBe('2026-11-21T10:00:00Z')
        ->and(importRows($import))->toBe([2 => ['queued', null, null], 3 => ['sent', null, null]]);

    importRunWorker();
    expect($resend)->toThrow(BusinessException::class, 'You invited these people again on 2026-11-21 already.');
});

test('a send again needs the role of every row, the checks of the mail, and a row (#1390)', function () {
    importLetModeratorsManageMembers($this->community);
    $import = importFrom($this->c['owner'], $this->community, "email,role\nqx-1@example.com,admin\n");
    Queue::fake();
    app(ConfirmsInvitationImports::class)->confirm($this->c['owner'], $import);
    $import->rows()->update(['outcome' => 'held']);

    expect(fn () => app(ResendsInvitationImports::class)->resend($this->c['admin'], $import->refresh(), ['rows' => 'not_sent']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ResendsInvitationImports::class)->resend($this->c['owner'], $import, ['rows' => 'not_joined']))->toThrow(BusinessException::class);

    importHoldMail(CarbonImmutable::parse('2026-12-01T00:00:00Z'));
    expect(fn () => app(ResendsInvitationImports::class)->resend($this->c['owner'], $import, ['rows' => 'not_sent']))->toThrow(BusinessException::class, 'This instance holds its mail now')
        ->and(importRows($import))->toBe([2 => ['held', null, null]]);

    importHoldMail(null);
    $import->rows()->update(['community_invitation_id' => null]);
    expect(fn () => app(ResendsInvitationImports::class)->resend($this->c['owner'], $import, ['rows' => 'not_sent']))->toThrow(BusinessException::class, 'No row to send');
});

test('a token without the abilities of the groups or the tags cannot give them by a file (#1390)', function () {
    $token = $this->c['owner']->createToken('qx', ['invitations:write']);
    $this->c['owner']->withAccessToken($token->accessToken);

    expect(InvitationGrants::mayGiveAccessGroups($this->c['owner'], $this->community))->toBeFalse()
        ->and(InvitationGrants::mayGiveMemberTags($this->c['owner'], $this->community))->toBeFalse()
        ->and(importRows(importFrom($this->c['owner'], $this->community, "email,access_groups,tags\nqx-1@example.com,Paid,\nqx-2@example.com,,Founder\n")))->toBe([
            2 => ['refused', 'access_groups_not_allowed', null],
            3 => ['refused', 'tags_not_allowed', null],
        ])
        ->and(fn () => importFrom($this->c['owner'], $this->community, "email\nqx-3@example.com\n", ['access_groups' => [$this->paid->cuid]]))->toThrow(AuthorizationException::class, 'You cannot give access groups');
});

test('a member with 20 tags who joins through an import keeps the join; the tag is skipped (#1390)', function () {
    $grace = User::factory()->create(['email' => 'qx-grace@example.com']);
    $this->community->users()->attach($grace, ['role' => KnowiiCommunityMemberRole::Member]);
    MemberTag::factory()->count(20)->sequence(fn ($sequence) => ['name' => 'Qxfull '.$sequence->index])->create(['community_id' => $this->community->id])
        ->each(fn (MemberTag $tag) => MemberTagAssignments::add($tag, $grace));
    $invitation = $this->community->communityInvitations()->create(['email' => 'qx-grace@example.com', 'role' => KnowiiCommunityMemberRole::Member]);
    InvitationGrants::attach($invitation, [], [$this->founder]);

    app(AcceptsCommunityInvitations::class)->accept($grace, $invitation);

    expect($this->founder->members()->whereKey($grace->id)->exists())->toBeFalse();
});

test('the migration of the imports goes down and up again, and changes no other table (#1390)', function () {
    $migration = require database_path('migrations/2026_11_22_210000_create_invitation_imports_tables.php');
    $import = importFrom($this->c['owner'], $this->community, "email\nqx-1@example.com\n");

    $migration->down();
    expect(Schema::hasTable('invitation_imports'))->toBeFalse()
        ->and(Schema::hasTable('invitation_import_rows'))->toBeFalse()
        ->and(Community::query()->whereKey($this->community->id)->exists())->toBeTrue();

    $migration->up();
    expect(Schema::hasColumns('invitation_imports', ['id', 'cuid', 'community_id', 'user_id', 'state', 'default_role', 'default_access_groups', 'default_member_tags', 'ignored_columns', 'confirmed_at', 'finished_at', 'resent_at', 'created_at', 'updated_at']))->toBeTrue()
        ->and(Schema::hasColumns('invitation_import_rows', ['id', 'cuid', 'invitation_import_id', 'row_number', 'email', 'role', 'access_group_names', 'member_tag_names', 'outcome', 'reason', 'detail', 'community_invitation_id', 'queued_at', 'queued_count', 'sent_at']))->toBeTrue()
        ->and(collect(Schema::getIndexes('invitation_import_rows'))->pluck('name'))->toContain('invitation_import_rows_email_lower_index')
        ->and($import->exists)->toBeTrue();
    expect(fn () => DB::table('invitation_import_rows')->insert(['cuid' => 'qxbadrow000000000000000a', 'invitation_import_id' => 1, 'row_number' => 2, 'email' => 'x', 'access_group_names' => '[]', 'member_tag_names' => '[]', 'outcome' => 'lost']))
        ->toThrow(QueryException::class);
});

test('the mail of a row has a row of the delivery log of its invitation; a send again clears the report of the mail provider (#1478)', function () {
    $import = importSent($this->community, $this->c['owner'], ['qx-1@example.com']);
    importRunWorker();

    $invitation = CommunityInvitation::query()->where('email', 'qx-1@example.com')->sole();
    $delivery = MailDelivery::query()->sole();
    expect($delivery->kind)->toBe(KnowiiMailKind::Invitation)
        ->and($delivery->community_invitation_id)->toBe($invitation->id)
        ->and($delivery->user_id)->toBeNull();

    $invitation->forceFill(['bounced_at' => now(), 'bounce_type' => KnowiiInvitationBounceType::Hard])->save();
    $import->rows()->update(['outcome' => 'failed']);
    $import->forceFill(['state' => 'done', 'finished_at' => now()])->save();
    $this->travel(5)->minutes();
    app(ResendsInvitationImports::class)->resend($this->c['owner'], $import, ['rows' => 'not_sent']);
    importRunWorker();

    $invitation->refresh();
    expect($invitation->bounced_at)->toBeNull()
        ->and($invitation->bounce_type)->toBeNull()
        ->and(MailDelivery::query()->count())->toBe(2);
});
