<?php

use App\Constants;
use App\Contracts\MemberTags\AddsMemberTagMembers;
use App\Contracts\MemberTags\CreatesMemberTags;
use App\Contracts\MemberTags\DeletesMemberTags;
use App\Contracts\MemberTags\RemovesMemberTagMembers;
use App\Contracts\MemberTags\UpdatesMemberTags;
use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiMemberTagColor;
use App\Enums\KnowiiMemberTagVisibility;
use App\Exceptions\BusinessException;
use App\Models\Community;
use App\Models\MemberTag;
use App\Models\User;
use App\Support\MemberTagAssignments;
use App\Support\MemberTagsOfMembers;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/*
 * The actions of the member tags (#1389, ADR 0060), the one writer of their links (the contract of #1390 and #1391), the
 * class that decides which tags a reader gets, and the table.
 */

beforeEach(function () {
    $this->c = communityWithMembers();
    $this->community = $this->c['community'];
});

test('each action checks its gate by itself (#1389, ADR 0006)', function () {
    $tag = MemberTag::factory()->create(['community_id' => $this->community->id, 'name' => 'Founder']);
    $member = $this->c['member'];

    expect(fn () => app(CreatesMemberTags::class)->create($member, $this->community, ['name' => 'Mine']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(UpdatesMemberTags::class)->update($member, $tag, ['name' => 'Mine']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(DeletesMemberTags::class)->delete($member, $tag))->toThrow(AuthorizationException::class)
        ->and(fn () => app(AddsMemberTagMembers::class)->add($member, $tag, ['member' => $member->cuid]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RemovesMemberTagMembers::class)->remove($member, $tag, $member->cuid))->toThrow(AuthorizationException::class)
        ->and(fn () => app(CreatesMemberTags::class)->create($this->c['outsider'], $this->community, ['name' => 'Mine']))->toThrow(AuthorizationException::class);
});

test('a create cleans the name, sets every field, and logs cuids only; nothing is broadcast (#1389)', function () {
    $events = [];
    Event::listen('*', static function (string $name) use (&$events): void {
        $events[] = $name;
    });
    $lines = [];
    Log::partialMock()->shouldReceive('info')->andReturnUsing(static function (string $message, array $context = []) use (&$lines): void {
        $lines[] = [$message, $context];
    });

    $tag = app(CreatesMemberTags::class)->create($this->c['owner'], $this->community, ['name' => "  Qxlog \u{00A0}  name  "]);

    expect($tag->name)->toBe('Qxlog name')
        ->and($tag->color)->toBe(KnowiiMemberTagColor::Gray)
        ->and($tag->visibility)->toBe(KnowiiMemberTagVisibility::Managers)
        ->and($lines)->toBe([['Member tag created', ['community' => $this->community->cuid, 'tag' => $tag->cuid]]]);

    MemberTagAssignments::add($tag, $this->c['member']);
    app(UpdatesMemberTags::class)->update($this->c['owner'], $tag, ['name' => 'Qxlog other', 'visibility' => 'members']);
    app(AddsMemberTagMembers::class)->add($this->c['owner'], $tag, ['username' => $this->c['admin']->username]);
    app(RemovesMemberTagMembers::class)->remove($this->c['owner'], $tag, $this->c['admin']->cuid);
    app(DeletesMemberTags::class)->delete($this->c['owner'], $tag);

    // No log line names the tag, and no event of the slice is broadcast: nothing tells a member
    expect(array_column($lines, 0))->toBe(['Member tag created', 'Member tag updated', 'Member tag added to a member', 'Member tag removed from a member', 'Member tag deleted'])
        ->and(json_encode($lines))->not->toContain('Qxlog')
        ->and(array_values(array_filter($events, static fn (string $name): bool => class_exists($name) && is_subclass_of($name, ShouldBroadcast::class))))->toBe([]);
});

test('the 101st tag of a community is refused (#1389)', function () {
    MemberTag::factory()->count(Constants::MAX_MEMBER_TAGS_PER_COMMUNITY)->create(['community_id' => $this->community->id]);

    expect(fn () => app(CreatesMemberTags::class)->create($this->c['admin'], $this->community, ['name' => 'One more']))
        ->toThrow(BusinessException::class, 'A community can have at most 100 member tags');
    // Another community has its own limit
    $other = Community::factory()->create(['owner_id' => $this->c['admin']->id]);
    expect(app(CreatesMemberTags::class)->create($this->c['admin'], $other, ['name' => 'One more'])->community_id)->toBe($other->id);
});

test('the index of the table refuses the same name in another case, also without the action (#1389)', function () {
    MemberTag::factory()->create(['community_id' => $this->community->id, 'name' => 'Founder']);

    // Each in its own savepoint: a refused statement ends the transaction of the test
    expect(fn () => DB::transaction(fn () => MemberTag::factory()->create(['community_id' => $this->community->id, 'name' => 'FOUNDER'])))->toThrow(UniqueConstraintViolationException::class)
        ->and(fn () => DB::transaction(fn () => DB::table('member_tags')->where('community_id', $this->community->id)->update(['color' => 'pink'])))->toThrow(QueryException::class, 'member_tags_color_check')
        ->and(fn () => DB::transaction(fn () => DB::table('member_tags')->where('community_id', $this->community->id)->update(['visibility' => 'public'])))->toThrow(QueryException::class, 'member_tags_visibility_check')
        ->and(MemberTag::query()->where('community_id', $this->community->id)->count())->toBe(1);
});

test('findByName finds the tag of the community whatever the case and the spaces, and nothing elsewhere (#1389)', function () {
    $tag = MemberTag::factory()->create(['community_id' => $this->community->id, 'name' => 'Early bird']);
    MemberTag::factory()->create(['name' => 'Elsewhere']);

    expect(MemberTag::findByName($this->community, '  EARLY   bird ')?->id)->toBe($tag->id)
        ->and(MemberTag::findByName($this->community, 'early bird')?->id)->toBe($tag->id)
        ->and(MemberTag::findByName($this->community, 'Elsewhere'))->toBeNull()
        ->and(MemberTag::findByName($this->community, '   '))->toBeNull()
        ->and(MemberTag::findByName($this->community, 'Early'))->toBeNull();
});

test('MemberTagAssignments is the one writer of the links: a member of the community, once, at most 20 (#1389)', function () {
    $tag = MemberTag::factory()->create(['community_id' => $this->community->id, 'name' => 'Founder']);

    expect(MemberTagAssignments::add($tag, $this->c['member']))->toBeTrue()
        ->and(MemberTagAssignments::add($tag, $this->c['member']))->toBeFalse()
        // The owner has no membership row, and counts as a member
        ->and(MemberTagAssignments::add($tag, $this->c['owner']))->toBeTrue()
        ->and(DB::table('member_tag_members')->where('member_tag_id', $tag->id)->count())->toBe(2);

    expect(fn () => MemberTagAssignments::add($tag, $this->c['outsider']))->toThrow(ValidationException::class, 'No member of the community matches');

    foreach (MemberTag::factory()->count(Constants::MAX_MEMBER_TAGS_PER_MEMBER - 1)->create(['community_id' => $this->community->id]) as $more) {
        expect(MemberTagAssignments::add($more, $this->c['member']))->toBeTrue();
    }
    $last = MemberTag::factory()->create(['community_id' => $this->community->id]);
    expect(fn () => MemberTagAssignments::add($last, $this->c['member']))->toThrow(BusinessException::class, 'A member can have at most 20 tags');

    expect(MemberTagAssignments::remove($tag, $this->c['member']))->toBeTrue()
        ->and(MemberTagAssignments::remove($tag, $this->c['member']))->toBeFalse()
        ->and(MemberTagAssignments::add($last, $this->c['member']))->toBeTrue();
});

test('MemberTagsOfMembers gives each reader the tags that the reader may see, with one query (#1389)', function () {
    $shown = MemberTag::factory()->forMembers()->create(['community_id' => $this->community->id, 'name' => 'b shown']);
    $hidden = MemberTag::factory()->forManagers()->create(['community_id' => $this->community->id, 'name' => 'A hidden']);
    $users = [$this->c['member'], $this->c['admin'], $this->c['moderator']];
    foreach ($users as $user) {
        MemberTagAssignments::add($shown, $user);
        MemberTagAssignments::add($hidden, $user);
    }
    $names = fn (?User $reader): array => array_map(
        static fn ($tags) => $tags->pluck('name')->all(),
        MemberTagsOfMembers::for($this->community, $users, $reader?->fresh()),
    );

    expect($names($this->c['owner']))->toEqual([
        $this->c['member']->id => ['A hidden', 'b shown'],
        $this->c['admin']->id => ['A hidden', 'b shown'],
        $this->c['moderator']->id => ['A hidden', 'b shown'],
    ])
        ->and($names($this->c['member'])[$this->c['member']->id])->toBe(['b shown'])
        ->and($names($this->c['moderator'])[$this->c['admin']->id])->toBe(['b shown'])
        ->and($names($this->c['outsider']))->toBe([])
        ->and($names(null))->toBe([])
        ->and(MemberTagsOfMembers::for($this->community, [], $this->c['owner']))->toBe([]);

    // The tags carry no column of the link
    $tag = MemberTagsOfMembers::for($this->community, $users, $this->c['owner']->fresh())[$this->c['member']->id]->first();
    expect($tag?->getAttributes())->not->toHaveKey('tagged_user_id');

    // One query for the tags of all the users (the reader and their rights are read first)
    $reader = $this->c['owner']->fresh();
    MemberTagsOfMembers::for($this->community, $users, $reader);
    DB::flushQueryLog();
    DB::enableQueryLog();
    MemberTagsOfMembers::for($this->community, $users, $reader);
    DB::disableQueryLog();
    expect(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql) => str_contains($sql, 'member_tags'))->count())->toBe(1);
});

test('a moderator who gets the right sees every tag, and a moderator who loses it sees the tags of the members only (#1389)', function () {
    $hidden = MemberTag::factory()->forManagers()->create(['community_id' => $this->community->id, 'name' => 'Hidden']);
    MemberTagAssignments::add($hidden, $this->c['member']);
    $see = fn (): bool => MemberTagsOfMembers::exists($hidden->fresh() ?? $hidden, $this->c['moderator']->fresh());

    expect($see())->toBeFalse();
    $this->community->update(['moderator_permissions' => [...$this->community->moderatorPermissions(), 'manage_members' => true]]);
    expect($see())->toBeTrue();
    $this->community->update(['moderator_permissions' => [...$this->community->moderatorPermissions(), 'manage_members' => false]]);
    expect($see())->toBeFalse();
    // A member who becomes an admin
    $this->community->users()->updateExistingPivot($this->c['moderator']->id, ['role' => KnowiiCommunityMemberRole::Admin]);
    expect($see())->toBeTrue();
});

test('a delete of a community deletes its tags and their links (#1389)', function () {
    $tag = MemberTag::factory()->create(['community_id' => $this->community->id]);
    MemberTagAssignments::add($tag, $this->c['member']);

    $this->community->purge();

    expect(MemberTag::query()->whereKey($tag->id)->exists())->toBeFalse()
        ->and(DB::table('member_tag_members')->where('member_tag_id', $tag->id)->exists())->toBeFalse();
});

test('the migration of the member tags goes down and up again, and changes no other table (#1389)', function () {
    $migration = require database_path('migrations/2026_11_22_100000_create_member_tags_tables.php');
    MemberTagAssignments::add(MemberTag::factory()->create(['community_id' => $this->community->id]), $this->c['member']);

    $migration->down();
    expect(Schema::hasTable('member_tags'))->toBeFalse()
        ->and(Schema::hasTable('member_tag_members'))->toBeFalse()
        ->and(Community::query()->whereKey($this->community->id)->exists())->toBeTrue();

    $migration->up();
    expect(Schema::hasColumns('member_tags', ['id', 'cuid', 'community_id', 'name', 'color', 'visibility', 'created_at', 'updated_at']))->toBeTrue()
        ->and(Schema::getColumnListing('member_tag_members'))->toEqualCanonicalizing(['id', 'member_tag_id', 'user_id', 'created_at'])
        ->and(array_column(Schema::getIndexes('member_tag_members'), 'columns'))->toContain(['user_id'], ['member_tag_id', 'user_id'])
        ->and(collect(Schema::getIndexes('member_tags'))->pluck('name'))->toContain('member_tags_community_name_unique');
});
