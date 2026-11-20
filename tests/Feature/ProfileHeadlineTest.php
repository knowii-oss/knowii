<?php

use App\ApiRoutes;
use App\Constants;
use App\Enums\KnowiiCommunityMemberRole;
use App\Models\Community;
use App\Models\User;
use App\Support\ProfileFieldVisibility;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\TestCase;

/*
 * The headline of a profile (#1688, ADR 0020): one line of plain text that a member writes about themselves. The member
 * writes it on the profile form and chooses who sees it. The member page and the entries of the member list hold it only
 * when the member shows it, for every reader: a manager gets what a member gets. The cleaning itself is in
 * tests/Unit/ProfileHeadlineTest.php.
 */

// A text that no page holds by accident, with no character that JSON or HTML writes in another way
const HEADLINE_PROBE = 'HEADLINE-PROBE-q7x2k9w4 writes about note-taking';

beforeEach(function () {
    $this->c = communityWithMembers();
    $this->community = $this->c['community'];
    // The member whose headline is read, and a second plain member who reads it
    $this->member = $this->c['member'];
    $this->reader = User::factory()->withUserProfile()->create();
    $this->community->users()->attach($this->reader, ['role' => KnowiiCommunityMemberRole::Member]);
});

/**
 * Store a headline without the form, with the choice of the member when one is given
 */
function headlineGive(User $user, ?string $headline, ?bool $visible = null): void
{
    $user->profile->forceFill([
        'headline' => $headline,
        ...($visible === null ? [] : ['field_visibility' => ['headline' => $visible]]),
    ])->save();
}

function headlineOf(User $user): ?string
{
    return $user->profile()->firstOrFail()->headline;
}

/**
 * Sign in for the next request. A test keeps its guards from one request to the next, and the profile form and the pages
 * do not use the same one: without new guards, a second request could run as the person of the first one.
 */
function headlineActingAs(User $user): TestCase
{
    app('auth')->forgetGuards();

    return test()->actingAs($user, 'web');
}

/**
 * Send the profile form of a member, with the two fields that the form always sends
 *
 * @param  array<string, mixed>  $input
 */
function headlineSave(User $user, array $input): TestResponse
{
    return headlineActingAs($user)->put('/user/profile-information', ['email' => $user->email, 'name' => $user->name, ...$input]);
}

function headlineMemberPage(User $viewer, User $member, Community $community): TestResponse
{
    return headlineActingAs($viewer)->get(route('members.show', ['communitySlug' => $community->slug, 'username' => $member->username]));
}

function headlineMembersPath(Community $community): string
{
    return ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBERS, [ApiRoutes::PARAM_COMMUNITY => $community->cuid]);
}

function headlineAboutPage(User $viewer, Community $community): TestResponse
{
    return headlineActingAs($viewer)->get(route('communities.about', ['communitySlug' => $community->slug]));
}

/**
 * The props of an Inertia page, as the browser gets them
 *
 * @return array<string, mixed>
 */
function headlinePropsOf(TestResponse $response): array
{
    /** @var array{props: array<string, mixed>} $page */
    $page = $response->viewData('page');

    return $page['props'];
}

dataset('headline readers', ['reader', 'moderator', 'admin', 'owner']);

// ---------------------------------------------------------------------------------------------------------------------
// The column
// ---------------------------------------------------------------------------------------------------------------------

test('the migration of the headline goes down and up again, and keeps the profiles (#1688)', function () {
    $migration = require database_path('migrations/2026_11_20_120000_add_headline_to_user_profiles.php');
    headlineGive($this->member, 'The first headline');
    $this->member->profile->forceFill(['bio' => 'A bio that stays'])->save();
    $profiles = DB::table('user_profiles')->count();

    $migration->down();
    expect(Schema::hasColumn('user_profiles', 'headline'))->toBeFalse()
        ->and(DB::table('user_profiles')->count())->toBe($profiles)
        ->and(DB::table('user_profiles')->where('user_id', $this->member->id)->value('bio'))->toBe('A bio that stays');

    $migration->up();
    expect(Schema::hasColumn('user_profiles', 'headline'))->toBeTrue()
        ->and(DB::table('user_profiles')->count())->toBe($profiles)
        // A new column: every profile starts without a headline, and the other fields stay
        ->and(DB::table('user_profiles')->whereNotNull('headline')->exists())->toBeFalse()
        ->and(DB::table('user_profiles')->where('user_id', $this->member->id)->value('bio'))->toBe('A bio that stays');
});

// ---------------------------------------------------------------------------------------------------------------------
// The profile form
// ---------------------------------------------------------------------------------------------------------------------

test('the profile form saves a headline, cleaned to one line (#1688)', function () {
    headlineSave($this->member, ['headline' => "Product designer,\r\n\r\nwrites\tabout   note-taking\u{202E}"])->assertSessionHasNoErrors();

    expect(headlineOf($this->member))->toBe('Product designer, writes about note-taking');
});

test('the profile form makes one plain space of wide spaces, and removes the characters that show nothing (#1688)', function () {
    $family = "\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}";

    // Two em spaces and an ideographic space, a zero width space in a word, a zero width joiner between two letters
    headlineSave($this->member, ['headline' => "\u{3000}Parent\u{2003}\u{2003}\u{3000}of one {$family}, de\u{200B}sig\u{200D}ner\u{3164}\u{2800}"])->assertSessionHasNoErrors();

    // The joiners of the family stay: without them, the one picture is three
    expect(headlineOf($this->member))->toBe("Parent of one {$family}, designer");
});

test('the profile form stores HTML and Markdown as the member typed them (#1688)', function () {
    headlineSave($this->member, ['headline' => '<b>Hi</b> & **you**, I <3 notes'])->assertSessionHasNoErrors();

    expect(headlineOf($this->member))->toBe('<b>Hi</b> & **you**, I <3 notes');
});

test('a headline holds at most 80 characters (#1688)', function () {
    expect(Constants::MAX_LENGTH_USER_HEADLINE)->toBe(80);

    $longest = str_repeat('a', 80);
    headlineSave($this->member, ['headline' => $longest])->assertSessionHasNoErrors();
    expect(headlineOf($this->member))->toBe($longest);

    headlineSave($this->member, ['headline' => str_repeat('b', 81)])->assertSessionHasErrorsIn('updateProfileInformation', ['headline']);
    expect(headlineOf($this->member))->toBe($longest);
});

test('a headline that is too long saves nothing of the form (#1688)', function () {
    headlineGive($this->member, 'The first headline');
    $name = $this->member->name;

    headlineSave($this->member, ['name' => 'Another Name', 'bio' => 'A new bio', 'headline' => str_repeat('b', 81)])
        ->assertSessionHasErrorsIn('updateProfileInformation', ['headline']);

    expect(headlineOf($this->member))->toBe('The first headline')
        ->and($this->member->fresh()?->name)->toBe($name)
        ->and($this->member->profile()->firstOrFail()->bio)->toBeNull();
});

test('each code point of an emoji counts for one character (#1688)', function () {
    $emoji = str_repeat("\u{1F600}", 80);
    headlineSave($this->member, ['headline' => $emoji])->assertSessionHasNoErrors();
    expect(headlineOf($this->member))->toBe($emoji);

    headlineSave($this->member, ['headline' => $emoji."\u{1F600}"])->assertSessionHasErrorsIn('updateProfileInformation', ['headline']);
    expect(headlineOf($this->member))->toBe($emoji);
});

test('the headline is cleaned before it is counted: 80 characters with spaces around them pass (#1688)', function () {
    $longest = str_repeat('a', 80);

    headlineSave($this->member, ['headline' => "  \t".$longest." \u{00A0} "])->assertSessionHasNoErrors();
    expect(headlineOf($this->member))->toBe($longest);

    // The action is also called without a request (the seed of the screen atlas): it cleans by itself
    $inside = str_repeat('a', 40)."\r\n\r\n".str_repeat('b', 39);
    app(UpdatesUserProfileInformation::class)->update($this->member, ['email' => $this->member->email, 'headline' => "\u{202E}   ".$inside."   \u{0000}"]);
    expect(headlineOf($this->member))->toBe(str_repeat('a', 40).' '.str_repeat('b', 39));
});

test('the action refuses 81 characters without a request, and keeps the headline (#1688)', function () {
    headlineGive($this->member, 'The first headline');

    expect(fn () => app(UpdatesUserProfileInformation::class)->update($this->member, ['email' => $this->member->email, 'headline' => str_repeat('b', 81)]))
        ->toThrow(ValidationException::class);
    expect(headlineOf($this->member))->toBe('The first headline');
});

test('an empty headline is stored as null, never as an empty text (#1688)', function (?string $typed) {
    headlineGive($this->member, 'The first headline');

    headlineSave($this->member, ['headline' => $typed])->assertSessionHasNoErrors();
    expect(headlineOf($this->member))->toBeNull();

    // And without a request, where no middleware trims the text
    headlineGive($this->member, 'The first headline');
    app(UpdatesUserProfileInformation::class)->update($this->member, ['email' => $this->member->email, 'headline' => $typed]);
    expect(headlineOf($this->member))->toBeNull()
        ->and(DB::table('user_profiles')->where('headline', '')->exists())->toBeFalse();
})->with([
    'an empty text' => [''],
    'null' => [null],
    'spaces only' => ['     '],
    'white space of every kind' => [" \t\r\n\u{00A0}\u{2028} "],
    'only characters that the cleaning removes' => ["\u{202E}\u{0000}"],
    'an ideographic space only' => ["\u{3000}"],
    'a zero width space only' => ["\u{200B}"],
    'a zero width joiner only' => ["\u{200D}"],
    'a Hangul filler only' => ["\u{3164}"],
    'a blank braille pattern only' => ["\u{2800}"],
]);

test('the error of the server comes back to the profile form under the name of its error bag (#1688)', function () {
    $taken = User::factory()->withUserProfile()->create(['email' => 'taken@example.com']);

    // As the form sends it. The page that comes back holds the errors under the name of the bag: the form reads them there
    // (UpdateProfileInformationForm.server-errors.spec.tsx gives the form this answer). The model is fresh, as a new
    // request has: the page reads columns that the factory did not set
    headlineActingAs(User::query()->findOrFail($this->member->id))
        ->followingRedirects()
        ->from(route('profile.show'))
        ->put('/user/profile-information', ['name' => $this->member->name, 'email' => $taken->email, 'headline' => str_repeat('b', 81)], ['X-Inertia-Error-Bag' => 'updateProfileInformation'])
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Profile/Show')
            ->where('errors.updateProfileInformation.headline', 'The headline field must not be greater than 80 characters.')
            ->where('errors.updateProfileInformation.email', 'The email has already been taken.')
            ->missing('errors.headline'));
});

test('the profile form saves the profile fields with one update (#1688)', function () {
    headlineGive($this->member, 'The first headline');
    $member = User::query()->findOrFail($this->member->id);

    DB::flushQueryLog();
    DB::enableQueryLog();
    headlineSave($member, ['headline' => 'Another headline', 'bio' => 'A new bio', 'location' => 'Namur'])->assertSessionHasNoErrors();
    $updates = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $query) => str_starts_with($query, 'update "user_profiles"'));
    DB::disableQueryLog();

    expect($updates)->toHaveCount(1)
        ->and($updates->first())->toContain('"bio"', '"location"', '"headline"')
        ->and(headlineOf($this->member))->toBe('Another headline');
});

test('a headline that is not a text is refused (#1688)', function (mixed $value) {
    headlineGive($this->member, 'The first headline');

    headlineSave($this->member, ['headline' => $value])->assertSessionHasErrorsIn('updateProfileInformation', ['headline']);

    expect(headlineOf($this->member))->toBe('The first headline');
})->with([
    'a list' => [['Product designer', 'writer']],
    'a map' => [['text' => 'Product designer']],
    'a number' => [42],
    'a boolean' => [true],
]);

test('a profile form without the headline keeps it, and a null headline clears it (#1688)', function () {
    headlineGive($this->member, 'The first headline');

    headlineSave($this->member, ['bio' => 'A new bio'])->assertSessionHasNoErrors();
    expect(headlineOf($this->member))->toBe('The first headline');

    headlineSave($this->member, ['headline' => null])->assertSessionHasNoErrors();
    expect(headlineOf($this->member))->toBeNull();
});

test('an account without a verified address does not change its headline (#1688)', function () {
    headlineGive($this->member, 'The first headline');
    $this->member->forceFill(['email_verified_at' => null])->save();

    headlineSave($this->member, ['headline' => 'Another headline', 'field_visibility' => ['headline' => false]])->assertSessionHasNoErrors();
    app(UpdatesUserProfileInformation::class)->update($this->member, ['email' => $this->member->email, 'headline' => 'Another headline']);

    $profile = $this->member->profile()->firstOrFail();
    expect($profile->headline)->toBe('The first headline')
        ->and($profile->fieldVisibility()['headline'])->toBeTrue();
});

// ---------------------------------------------------------------------------------------------------------------------
// The switch (ADR 0020)
// ---------------------------------------------------------------------------------------------------------------------

test('the headline is one of the fields with a switch, shown by default (#1688)', function () {
    expect(ProfileFieldVisibility::fields())->toContain('headline')
        ->and(ProfileFieldVisibility::defaults()['headline'])->toBeTrue()
        ->and(ProfileFieldVisibility::defaults()['phone'])->toBeFalse();
});

test('a member who never chose shows the headline (#1688)', function () {
    headlineGive($this->member, HEADLINE_PROBE);
    expect($this->member->profile()->firstOrFail()->field_visibility)->toBeNull();

    headlineMemberPage($this->reader, $this->member, $this->community)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('member.headline', HEADLINE_PROBE));
});

test('a member with choices from before the headline shows it (#1688)', function () {
    headlineGive($this->member, HEADLINE_PROBE);
    $this->member->profile->forceFill(['field_visibility' => ['bio' => false, 'location' => true, 'phone' => true]])->save();

    expect($this->member->profile()->firstOrFail()->fieldVisibility()['headline'])->toBeTrue();
    headlineMemberPage($this->reader, $this->member, $this->community)
        ->assertInertia(fn ($page) => $page->where('member.headline', HEADLINE_PROBE));
});

test('the switch of the profile form hides the headline and shows it again (#1688)', function () {
    headlineSave($this->member, ['headline' => HEADLINE_PROBE, 'field_visibility' => ['headline' => false]])->assertSessionHasNoErrors();

    expect($this->member->profile()->firstOrFail()->field_visibility['headline'])->toBeFalse();
    headlineMemberPage($this->reader, $this->member, $this->community)
        ->assertInertia(fn ($page) => $page->where('member.headline', ''));

    // A form without the choices keeps the choice
    headlineSave($this->member, ['headline' => HEADLINE_PROBE])->assertSessionHasNoErrors();
    expect($this->member->profile()->firstOrFail()->fieldVisibility()['headline'])->toBeFalse();
    headlineMemberPage($this->reader, $this->member, $this->community)
        ->assertInertia(fn ($page) => $page->where('member.headline', ''));

    headlineSave($this->member, ['headline' => HEADLINE_PROBE, 'field_visibility' => ['headline' => true]])->assertSessionHasNoErrors();
    headlineMemberPage($this->reader, $this->member, $this->community)
        ->assertInertia(fn ($page) => $page->where('member.headline', HEADLINE_PROBE));
});

test('the choice for the headline must be a boolean (#1688)', function () {
    headlineGive($this->member, HEADLINE_PROBE, true);

    headlineSave($this->member, ['headline' => HEADLINE_PROBE, 'field_visibility' => ['headline' => 'maybe']])
        ->assertSessionHasErrorsIn('updateProfileInformation', ['field_visibility.headline']);

    expect($this->member->profile()->firstOrFail()->fieldVisibility()['headline'])->toBeTrue();
});

// ---------------------------------------------------------------------------------------------------------------------
// The member reads the own headline
// ---------------------------------------------------------------------------------------------------------------------

test('the own account of the API holds the headline and its choice, shown or hidden (#1688)', function (bool $visible) {
    headlineGive($this->member, HEADLINE_PROBE, $visible);

    headlineActingAs($this->member)->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.profile.headline', HEADLINE_PROBE)
        ->assertJsonPath('data.profile.field_visibility.headline', $visible);
})->with(['shown' => [true], 'hidden' => [false]]);

test('the own account of the API gives an empty text without a headline (#1688)', function () {
    headlineActingAs($this->member)->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.profile.headline', '')
        ->assertJsonPath('data.profile.field_visibility.headline', true);
});

test('the profile form gets the stored headline, null when there is none (#1688)', function () {
    // fresh(): the page reads columns that a model built in memory does not hold
    headlineActingAs($this->member->fresh())->get(route('profile.show'))
        ->assertInertia(fn ($page) => $page->where('userProfile.headline', null));

    headlineGive($this->member, HEADLINE_PROBE, false);

    headlineActingAs($this->member->fresh())->get(route('profile.show'))
        ->assertInertia(fn ($page) => $page->where('userProfile.headline', HEADLINE_PROBE)->where('userProfile.field_visibility.headline', false));
});

test('on the own member page, the member sees what the others see: nothing of a hidden headline (#1688)', function () {
    headlineGive($this->member, HEADLINE_PROBE, false);

    headlineMemberPage($this->member, $this->member, $this->community)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('member.headline', '')->where('messaging.isSelf', true));
});

// ---------------------------------------------------------------------------------------------------------------------
// The member page, for the other members
// ---------------------------------------------------------------------------------------------------------------------

test('the member page holds a shown headline for a member and for each manager (#1688)', function (string $viewer) {
    headlineGive($this->member, HEADLINE_PROBE, true);

    headlineMemberPage($viewer === 'reader' ? $this->reader : $this->c[$viewer], $this->member, $this->community)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Members/Show')->where('member.headline', HEADLINE_PROBE));
})->with('headline readers');

test('the member page holds nothing of a hidden headline, also for a manager (#1688)', function (string $viewer) {
    headlineGive($this->member, HEADLINE_PROBE, false);

    $response = headlineMemberPage($viewer === 'reader' ? $this->reader : $this->c[$viewer], $this->member, $this->community)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Members/Show')->where('member.headline', ''));

    expect((string) $response->getContent())->not->toContain('HEADLINE-PROBE')
        ->and((string) json_encode(headlinePropsOf($response)))->not->toContain('HEADLINE-PROBE');
})->with('headline readers');

test('a hidden headline and no headline give the same member page (#1688)', function () {
    headlineGive($this->member, HEADLINE_PROBE, false);
    $hidden = headlinePropsOf(headlineMemberPage($this->reader, $this->member, $this->community))['member'];

    headlineGive($this->member, null, true);
    $none = headlinePropsOf(headlineMemberPage($this->reader, $this->member, $this->community))['member'];

    expect(json_encode($hidden))->toBe(json_encode($none))
        ->and(json_decode((string) json_encode($hidden), true)['headline'])->toBe('');
});

// ---------------------------------------------------------------------------------------------------------------------
// The entries of the member list: the API, the answer of a role change, the team of "About"
// ---------------------------------------------------------------------------------------------------------------------

test('each entry of the member list holds the headline that its member shows (#1688)', function (string $viewer) {
    headlineGive($this->member, HEADLINE_PROBE, true);
    headlineGive($this->c['admin'], 'The headline of the admin');

    $response = headlineActingAs($viewer === 'reader' ? $this->reader : $this->c[$viewer])
        ->getJson(headlineMembersPath($this->community))
        ->assertOk()
        ->assertJsonCount(5, 'data');

    $entries = collect($response->json('data'));
    expect($entries->every(fn (array $entry) => array_key_exists('headline', $entry) && is_string($entry['headline'])))->toBeTrue()
        ->and($entries->firstWhere('cuid', $this->member->cuid)['headline'])->toBe(HEADLINE_PROBE)
        ->and($entries->firstWhere('cuid', $this->c['admin']->cuid)['headline'])->toBe('The headline of the admin')
        ->and($entries->firstWhere('cuid', $this->c['owner']->cuid)['headline'])->toBe('')
        ->and(array_keys($entries->firstWhere('cuid', $this->member->cuid)))->toEqualCanonicalizing(['cuid', 'name', 'username', 'avatar_url', 'role', 'member_since', 'headline']);
})->with(['reader', 'owner']);

test('the member list holds nothing of a hidden headline, also for the owner (#1688)', function (string $viewer) {
    headlineGive($this->member, HEADLINE_PROBE, false);

    $response = headlineActingAs($viewer === 'reader' ? $this->reader : $this->c[$viewer])
        ->getJson(headlineMembersPath($this->community))
        ->assertOk()
        ->assertJsonCount(5, 'data');

    $entries = collect($response->json('data'));
    expect($entries->every(fn (array $entry) => array_key_exists('headline', $entry)))->toBeTrue()
        ->and($entries->firstWhere('cuid', $this->member->cuid)['headline'])->toBe('')
        ->and((string) $response->getContent())->not->toContain('HEADLINE-PROBE');
})->with(['reader', 'owner']);

test('the search of the member list holds the headline under the same rule, and does not search in it (#1688)', function (bool $visible) {
    headlineGive($this->member, HEADLINE_PROBE, $visible);
    // A name that no generated name of the other members starts with: the search finds this member alone
    $this->member->update(['username' => 'zyxwina_qorvath', 'name' => 'Zyxwina Qorvath']);

    $response = headlineActingAs($this->reader)->getJson(headlineMembersPath($this->community).'?query=zyxwina')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.headline', $visible ? HEADLINE_PROBE : '');
    expect(str_contains((string) $response->getContent(), 'HEADLINE-PROBE'))->toBe($visible);

    // No search reads the headline
    headlineActingAs($this->reader)->getJson(headlineMembersPath($this->community).'?query=HEADLINE-PROBE')->assertOk()->assertJsonCount(0, 'data');
    headlineActingAs($this->reader)->getJson(headlineMembersPath($this->community).'?query=writes')->assertOk()->assertJsonCount(0, 'data');
})->with(['shown' => [true], 'hidden' => [false]]);

test('the answer of a role change holds the headline only when the member shows it (#1688)', function (bool $visible) {
    headlineGive($this->member, HEADLINE_PROBE, $visible);

    $response = headlineActingAs($this->c['owner'])
        ->patchJson(ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBER, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid, ApiRoutes::PARAM_MEMBER => $this->member->cuid]), ['role' => 'moderator'])
        ->assertOk()
        ->assertJsonPath('data.role', 'moderator')
        ->assertJsonPath('data.headline', $visible ? HEADLINE_PROBE : '');

    expect(str_contains((string) $response->getContent(), 'HEADLINE-PROBE'))->toBe($visible);
})->with(['shown' => [true], 'hidden' => [false]]);

test('the team of the page "About" holds the headline only when the member shows it (#1688)', function (string $viewer, bool $visible) {
    headlineGive($this->c['admin'], HEADLINE_PROBE, $visible);

    $response = headlineAboutPage($viewer === 'reader' ? $this->reader : $this->c[$viewer], $this->community)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Communities/About')
            ->has('team', 3)
            ->where('team.0.headline', '')
            ->where('team.1.cuid', $this->c['admin']->cuid)
            ->where('team.1.headline', $visible ? HEADLINE_PROBE : '')
            ->where('team.2.headline', ''));

    expect(str_contains((string) json_encode(headlinePropsOf($response)), 'HEADLINE-PROBE'))->toBe($visible)
        ->and(str_contains((string) $response->getContent(), 'HEADLINE-PROBE'))->toBe($visible);
})->with(['reader', 'owner'])->with(['shown' => [true], 'hidden' => [false]]);

test('the member list reads the headlines with the same number of queries for 3 members and for 30 (#1688)', function () {
    $count = function (int $members): int {
        $owner = User::factory()->withUserProfile()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        headlineGive($owner, 'The headline of the owner');
        foreach (User::factory()->withUserProfile()->count($members - 1)->create() as $index => $user) {
            $community->users()->attach($user, ['role' => KnowiiCommunityMemberRole::Member]);
            headlineGive($user, 'The headline of member '.$index, $index % 2 === 0);
        }
        // A fresh model, as a new request has: no relation in memory
        $viewer = User::query()->findOrFail($owner->id);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = headlineActingAs($viewer)->getJson(headlineMembersPath($community).'?limit=100')->assertOk()->assertJsonCount($members, 'data');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Each member is there with the own headline, or with none when it is hidden
        expect(collect($response->json('data'))->where('headline', '!==', '')->count())->toBe(1 + intdiv($members, 2));

        return $queries;
    };

    $three = $count(3);
    $thirty = $count(30);

    // The 4 queries: the community, the number of members, the members of the page, and the profiles of these members.
    // The last one is the query that the headline adds (3 before #1688): one for the whole page, whatever its size
    expect($three)->toBe(4)
        ->and($thirty)->toBe(4);
});

// ---------------------------------------------------------------------------------------------------------------------
// What leaves the application
// ---------------------------------------------------------------------------------------------------------------------

test('no broadcast, notification, mail or log line holds the headline (#1688)', function () {
    Event::fake();
    Notification::fake();
    Mail::fake();
    $log = new TestHandler;
    $logger = Log::getLogger();
    assert($logger instanceof Logger);
    $logger->pushHandler($log);
    Log::info('The log of this test is read');

    // The member saves a headline with a new address (the one save of the form that sends a notification), other members
    // read it, and the owner changes the role of the member (the one answer with a headline that also broadcasts)
    headlineSave($this->member, ['email' => 'another-address@example.com', 'headline' => HEADLINE_PROBE, 'field_visibility' => ['headline' => true]])
        ->assertSessionHasNoErrors();
    expect(headlineOf($this->member))->toBe(HEADLINE_PROBE);
    headlineMemberPage($this->reader, $this->member, $this->community)->assertOk();
    headlineActingAs($this->reader)->getJson(headlineMembersPath($this->community))->assertOk()->assertSee('HEADLINE-PROBE');
    headlineActingAs($this->c['owner'])
        ->patchJson(ApiRoutes::path(ApiRoutes::COMMUNITY_MEMBER, [ApiRoutes::PARAM_COMMUNITY => $this->community->cuid, ApiRoutes::PARAM_MEMBER => $this->member->cuid]), ['role' => 'moderator'])
        ->assertOk()
        ->assertJsonPath('data.headline', HEADLINE_PROBE);

    // The broadcasts: at least the one of the role change, each with all that it holds
    $broadcasts = collect(Event::dispatchedEvents())->flatten()->filter(fn (mixed $event) => $event instanceof ShouldBroadcast);
    // The notifications: the objects, the mail of each one, and the rows of the notifications of the application
    $notifications = collect(Notification::sentNotifications())->flatten(3)
        ->map(fn (array $sent) => [
            $sent['notification'],
            method_exists($sent['notification'], 'toMail') && ($mail = $sent['notification']->toMail($sent['notifiable'])) instanceof MailMessage ? (string) $mail->render() : null,
        ]);

    expect($broadcasts)->not->toBeEmpty()
        ->and($notifications)->not->toBeEmpty()
        ->and($log->hasInfoThatContains('The log of this test is read'))->toBeTrue()
        ->and(print_r($broadcasts->all(), true))->not->toContain('HEADLINE-PROBE')
        ->and(print_r($notifications->all(), true))->not->toContain('HEADLINE-PROBE')
        ->and(print_r(DB::table('notifications')->get()->all(), true))->not->toContain('HEADLINE-PROBE')
        ->and(print_r($log->getRecords(), true))->not->toContain('HEADLINE-PROBE');
    Mail::assertNothingOutgoing();
});

// ---------------------------------------------------------------------------------------------------------------------
// The people who get nothing
// ---------------------------------------------------------------------------------------------------------------------

test('a person who is not a member of the community gets no headline (#1688)', function () {
    headlineGive($this->member, HEADLINE_PROBE, true);
    $outsider = $this->c['outsider'];

    $page = headlineMemberPage($outsider, $this->member, $this->community);
    expect($page->getStatusCode())->toBeIn([403, 404])
        ->and((string) $page->getContent())->not->toContain('HEADLINE-PROBE');

    $pageAsJson = headlineActingAs($outsider)->getJson(route('members.show', ['communitySlug' => $this->community->slug, 'username' => $this->member->username]));
    expect($pageAsJson->getStatusCode())->toBeIn([403, 404])
        ->and((string) $pageAsJson->getContent())->not->toContain('HEADLINE-PROBE');

    $list = headlineActingAs($outsider)->getJson(headlineMembersPath($this->community))->assertForbidden();
    expect((string) $list->getContent())->not->toContain('HEADLINE-PROBE');

    $about = headlineAboutPage($outsider, $this->community);
    expect($about->getStatusCode())->toBeIn([403, 404])
        ->and((string) $about->getContent())->not->toContain('HEADLINE-PROBE');
});

test('a member of another community gets no headline of a member whom they do not share a community with (#1688)', function () {
    headlineGive($this->member, HEADLINE_PROBE, true);
    $other = communityWithMembers();

    // The member page of the other community does not know this member
    $page = headlineMemberPage($other['member'], $this->member, $other['community']);
    expect($page->getStatusCode())->toBe(404)
        ->and((string) $page->getContent())->not->toContain('HEADLINE-PROBE');

    $list = headlineActingAs($other['member'])->getJson(headlineMembersPath($other['community']))->assertOk();
    expect((string) $list->getContent())->not->toContain('HEADLINE-PROBE');
});

test('a visitor gets no headline from the member page and from the member list (#1688)', function () {
    headlineGive($this->member, HEADLINE_PROBE, true);
    app('auth')->forgetGuards();

    $page = $this->get(route('members.show', ['communitySlug' => $this->community->slug, 'username' => $this->member->username]))->assertRedirect();
    expect((string) $page->getContent())->not->toContain('HEADLINE-PROBE');

    $list = $this->getJson(headlineMembersPath($this->community))->assertUnauthorized();
    expect((string) $list->getContent())->not->toContain('HEADLINE-PROBE');
});
