<?php

use App\Actions\Auth\SignInWithOpenIdConnect;
use App\Contracts\AccessGroups\CreatesAccessGroups;
use App\Contracts\AccessGroups\UpdatesAccessGroups;
use App\Contracts\Communities\CreatesCommunities;
use App\Contracts\Communities\UpdatesCommunities;
use App\Contracts\Communities\VerifiesCommunityNameAvailability;
use App\Contracts\CourseMaterials\CreatesCourseMaterials;
use App\Contracts\CourseMaterials\UpdatesCourseMaterials;
use App\Contracts\Events\CreatesEvents;
use App\Contracts\Events\UpdatesEvents;
use App\Contracts\Pages\CreatesPages;
use App\Contracts\Pages\UpdatesPages;
use App\Contracts\Posts\CreatesPosts;
use App\Contracts\Posts\UpdatesPosts;
use App\Contracts\Resources\CreatesCommunityResources;
use App\Contracts\SpaceGroups\CreatesSpaceGroups;
use App\Contracts\SpaceGroups\UpdatesSpaceGroups;
use App\Contracts\Spaces\CreatesSpaces;
use App\Contracts\Spaces\UpdatesSpaces;
use App\Contracts\Uploads\UploadsImages;
use App\Enums\KnowiiCaptureStatus;
use App\Enums\KnowiiCommunityVisibility;
use App\Enums\KnowiiSpaceType;
use App\Jobs\CaptureCommunityResource;
use App\Models\AccessGroup;
use App\Models\Community;
use App\Models\CommunityResource;
use App\Models\Event as EventModel;
use App\Models\Page;
use App\Models\Post;
use App\Models\Resource;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\User;
use App\Support\CourseMaterials;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

/*
 * A name is one line of plain text (ADR 0069): no control character, no character that changes the direction of the
 * text, no invisible character, and no line break. Each one-line field is cleaned when it is written, in its action; a
 * description or a bio keeps its line breaks and loses the control and the direction characters. A migration cleans the
 * stored values. The cleaning itself is in tests/Unit/PlainTextTest.php.
 */

const PLAIN_TEXT_MIGRATION = '2026_11_24_153900_clean_the_plain_texts_of_names_and_titles.php';

beforeEach(function () {
    Storage::fake('public');
    $this->c = communityWithMembers();
    $this->owner = $this->c['owner'];
    $this->community = $this->c['community'];
    $this->group = SpaceGroup::factory()->create(['community_id' => $this->community->id]);
});

/**
 * A space of the community of the test, of this type
 */
function plainTextSpace(KnowiiSpaceType $type): Space
{
    return Space::factory()->inGroup(test()->group)->create(['type' => $type]);
}

/**
 * Writes the text in one field, through the action of the field (the creation, then the update when the field has
 * one), and returns each stored value. The text of an update ends with " two", and so does its stored value.
 *
 * @return array<string, string|null>
 */
function plainTextStored(string $field, string $text): array
{
    $owner = test()->owner;
    $community = test()->community;
    $two = $text.' two';
    $event = ['title' => 'A call', 'starts_at' => '2026-12-18T17:00:00+01:00', 'ends_at' => '2026-12-18T18:00:00+01:00', 'time_zone' => 'Europe/Brussels'];
    $space = ['space_group_cuid' => test()->group->cuid, 'name' => 'Announcements', 'type' => 'discussion', 'visibility' => 'members', 'posting_policy' => 'members'];

    switch ($field) {
        case 'the name of an account, at the sign-up':
            signUpThroughLink(['name' => $text, 'email' => 'plain.text@example.com', 'password' => 'a-Long-Password-9!'])->assertSessionHasNoErrors();
            $user = User::query()->where('email', 'plain.text@example.com')->firstOrFail();

            return ['account' => $user->name, 'profile' => $user->profile->name, 'personal community' => preg_replace("/'s Space\$/", '', (string) $user->ownedCommunities()->value('name'))];
        case 'the name of an account, in the profile':
            app(UpdatesUserProfileInformation::class)->update($owner, ['name' => $text, 'email' => $owner->email]);

            return ['account' => $owner->fresh()?->name, 'profile' => $owner->profile->fresh()?->name];
        case 'the location of a profile':
        case 'the headline of a profile':
        case 'the bio of a profile':
            $key = str_contains($field, 'location') ? 'location' : (str_contains($field, 'headline') ? 'headline' : 'bio');
            app(UpdatesUserProfileInformation::class)->update($owner, ['email' => $owner->email, $key => $text]);

            return [$key => $owner->profile->fresh()?->{$key}];
        case 'the name of a community':
            $created = app(CreatesCommunities::class)->create($owner, ['name' => $text, 'visibility' => 'private']);
            $available = app(VerifiesCommunityNameAvailability::class)->verify($owner, ['name' => $text]);

            return ['created' => $created->name, 'updated' => app(UpdatesCommunities::class)->update($owner, $community, ['name' => $two])->name, 'taken, as the stored name' => $available ? 'free' : $created->name];
        case 'the description of a community':
            $created = app(CreatesCommunities::class)->create($owner, ['name' => 'Plain Text Notes', 'description' => $text, 'visibility' => 'private']);

            return ['created' => $created->description, 'updated' => app(UpdatesCommunities::class)->update($owner, $community, ['description' => $two])->description];
        case 'the name of a space group':
            $created = app(CreatesSpaceGroups::class)->create($owner, $community, ['name' => $text]);

            return ['created' => $created->name, 'updated' => app(UpdatesSpaceGroups::class)->update($owner, $created, ['name' => $two])->name];
        case 'the name of a space':
        case 'the description of a space':
            $key = str_contains($field, 'name') ? 'name' : 'description';
            $created = app(CreatesSpaces::class)->create($owner, $community, [...$space, $key => $text]);

            return ['created' => $created->{$key}, 'updated' => app(UpdatesSpaces::class)->update($owner, $created, [$key => $two])->{$key}];
        case 'the name of an access group':
        case 'the description of an access group':
            $key = str_contains($field, 'name') ? 'name' : 'description';
            $created = app(CreatesAccessGroups::class)->create($owner, $community, ['name' => 'Paid members', $key => $text]);

            return ['created' => $created->{$key}, 'updated' => app(UpdatesAccessGroups::class)->update($owner, $created, [$key => $two])->{$key}];
        case 'the title of a post':
            $created = app(CreatesPosts::class)->create($owner, plainTextSpace(KnowiiSpaceType::Discussion), ['title' => $text, 'body' => 'Hello']);

            return ['created' => $created->title, 'updated' => app(UpdatesPosts::class)->update($owner, $created, ['title' => $two])->title];
        case 'the title of a page':
            $created = app(CreatesPages::class)->create($owner, plainTextSpace(KnowiiSpaceType::Pages), ['title' => $text, 'body' => 'Hello']);

            return ['created' => $created->title, 'updated' => app(UpdatesPages::class)->update($owner, $created, ['title' => $two])->title];
        case 'the title of an event':
        case 'the place of an event':
        case 'the text of the cover of an event':
            $key = str_contains($field, 'title') ? 'title' : (str_contains($field, 'place') ? 'place' : 'cover_alt');
            $created = app(CreatesEvents::class)->create($owner, plainTextSpace(KnowiiSpaceType::Events), [...$event, $key => $text]);

            return ['created' => $created->{$key}, 'updated' => app(UpdatesEvents::class)->update($owner, $created, [$key => $two])->{$key}];
        case 'the title of a course material':
            $created = app(CreatesCourseMaterials::class)->create($owner, plainTextSpace(KnowiiSpaceType::Course), ['kind' => 'link', 'title' => $text, 'url' => 'https://example.com/template']);

            return ['created' => $created->title, 'updated' => app(UpdatesCourseMaterials::class)->update($owner, $created, ['title' => $two])->title];
        case 'the name of the file of a course material':
            return ['name' => preg_replace('/\.pdf$/', '', CourseMaterials::displayName($text.'.pdf'))];
        case 'the name of a resource':
        case 'the description of a resource':
            $key = str_contains($field, 'name') ? 'name' : 'description';
            $created = app(CreatesCommunityResources::class)->create($owner, plainTextSpace(KnowiiSpaceType::Resources), ['url' => 'https://example.com/smart-notes', 'level' => 'unknown', 'capture' => false, $key => $text]);

            return ['created' => $created->{$key}];
        case 'the title and the description of a captured page':
            $resource = CommunityResource::factory()->create([
                'space_id' => plainTextSpace(KnowiiSpaceType::Resources)->id,
                'name' => 'Some Article',
                'resource_id' => Resource::factory()->create(['url' => 'https://example.com/some-article', 'name' => 'Some Article', 'keywords' => []])->id,
                'capture_status' => KnowiiCaptureStatus::Pending,
                'curator_id' => $owner->profile->id,
            ]);
            $html = '<html><head><title>'.e($text).'</title><meta name="description" content="'.e($text).'"><meta property="og:image:alt" content="'.e($text).'"></head><body><p>Hello</p></body></html>';
            (new CaptureCommunityResource($resource, nameFromPage: true))->handle(fakeCapturer($html));

            return ['title' => $resource->resource->fresh()?->name, 'description' => $resource->resource->fresh()?->description, 'cover text' => $resource->resource->fresh()?->cover_image_alt, 'name in the community' => $resource->fresh()?->name];
        case 'the text of an image':
            return ['created' => app(UploadsImages::class)->upload($owner, $community, ['file' => UploadedFile::fake()->image('a.jpg', 20, 20), 'alt' => $text])->alt];
        case 'the name of an API token':
            test()->actingAs($owner)->post('/user/api-tokens', ['name' => $text])->assertSessionHasNoErrors();

            return ['created' => $owner->tokens()->value('name')];
        default:
            throw new LogicException("No write for the field: {$field}");
    }
}

dataset('one-line fields', [
    'the name of an account, at the sign-up',
    'the name of an account, in the profile',
    'the location of a profile',
    'the headline of a profile',
    'the name of a community',
    'the name of a space group',
    'the name of a space',
    'the name of an access group',
    'the title of a post',
    'the title of a page',
    'the title of an event',
    'the place of an event',
    'the text of the cover of an event',
    'the title of a course material',
    'the name of the file of a course material',
    'the name of a resource',
    'the title and the description of a captured page',
    'the text of an image',
    'the name of an API token',
]);

dataset('fields of several lines', [
    'the bio of a profile',
    'the description of a community',
    'the description of a space',
    'the description of an access group',
    'the description of a resource',
]);

// The typed text, what a one-line field stores, and what a field of several lines stores
dataset('characters that a name does not keep', [
    'a direction mark' => ["Ada\u{202E} Lovelace", 'Ada Lovelace', 'Ada Lovelace'],
    'a zero width character' => ["Ada Love\u{200B}lace", 'Ada Lovelace', "Ada Love\u{200B}lace"],
    'a direction mark, a soft hyphen and a Hangul filler' => ["Ada\u{200E} Love\u{00AD}lace\u{115F}", 'Ada Lovelace', "Ada Love\u{00AD}lace\u{115F}"],
    'a control character' => ["Ada Love\u{0007}lace", 'Ada Lovelace', 'Ada Lovelace'],
    'a line break' => ["Ada\r\nLovelace", 'Ada Lovelace', "Ada\nLovelace"],
    'spaces of several kinds' => ["Ada \u{00A0}\t Lovelace", 'Ada Lovelace', "Ada \u{00A0}\t Lovelace"],
]);

test('a one-line field stores one line of plain text', function (string $field, string $typed, string $line) {
    foreach (plainTextStored($field, $typed) as $where => $stored) {
        expect($stored)->toBe(str_contains($where, 'updated') ? $line.' two' : $line, $where);
    }
})->with('one-line fields')->with('characters that a name does not keep');

test('a field of several lines keeps its line breaks, and loses the control and the direction characters', function (string $field, string $typed, string $line, string $lines) {
    foreach (plainTextStored($field, $typed) as $where => $stored) {
        expect($stored)->toBe(str_contains($where, 'updated') ? $lines.' two' : $lines, $where);
    }
})->with('fields of several lines')->with('characters that a name does not keep');

test('the name that a sign-in provider gives is one line of plain text too', function (string $given, string $stored) {
    // A control or a format character of a provider becomes a space, as before; the rest is the cleaning of every name
    expect((new ReflectionMethod(SignInWithOpenIdConnect::class, 'cleanName'))->invoke(null, $given))->toBe($stored);
})->with([
    'a direction mark' => ["Ada\u{202E} Lovelace", 'Ada Lovelace'],
    'a zero width character' => ["Ada\u{200B}Lovelace", 'Ada Lovelace'],
    'a control character' => ["Ada\u{0007}Lovelace", 'Ada Lovelace'],
    'a line break' => ["Ada\r\nLovelace", 'Ada Lovelace'],
    'spaces of several kinds' => ["  Ada \u{00A0}\t Lovelace ", 'Ada Lovelace'],
    'an invisible filler' => ["Ada\u{3164} Lovelace\u{2800}", 'Ada Lovelace'],
]);

test('the emoji of a space keeps an emoji with a zero width joiner, and no other invisible character', function () {
    $input = ['space_group_cuid' => $this->group->cuid, 'name' => 'Announcements', 'type' => 'discussion', 'visibility' => 'members', 'posting_policy' => 'members'];
    $family = "\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}";

    $space = app(CreatesSpaces::class)->create($this->owner, $this->community, [...$input, 'emoji' => "\u{202E}\u{1F4E3}\u{200B}\r\n"]);
    expect($space->emoji)->toBe("\u{1F4E3}")
        ->and(app(UpdatesSpaces::class)->update($this->owner, $space, ['emoji' => $family."\u{202E}"])->emoji)->toBe($family);
});

test('a normal name in any script is stored as the person wrote it', function (string $name) {
    app(UpdatesUserProfileInformation::class)->update($this->owner, ['name' => $name, 'email' => $this->owner->email, 'location' => $name, 'bio' => $name."\n\n".$name]);
    $group = app(CreatesSpaceGroups::class)->create($this->owner, $this->community, ['name' => $name]);
    $post = app(CreatesPosts::class)->create($this->owner, plainTextSpace(KnowiiSpaceType::Discussion), ['title' => $name, 'body' => 'Hello']);

    expect($this->owner->fresh()?->name)->toBe($name)
        ->and($this->owner->profile->fresh()?->name)->toBe($name)
        ->and($this->owner->profile->fresh()?->location)->toBe($name)
        ->and($this->owner->profile->fresh()?->bio)->toBe($name."\n\n".$name)
        ->and($group->name)->toBe($name)
        ->and($post->title)->toBe($name);
})->with([
    'Latin letters with accents' => ['Zoé Lefèvre-Müller'],
    'an apostrophe, an ampersand and brackets' => ["Sam O'Neil & Sons [EU] <3"],
    'Arabic' => ["\u{0645}\u{062D}\u{0645}\u{062F} \u{0639}\u{0644}\u{064A}"],
    'Persian with a zero width non-joiner' => ["\u{0645}\u{06CC}\u{200C}\u{062E}\u{0648}\u{0627}\u{0647}\u{0645}"],
    'Hebrew' => ["\u{05D3}\u{05D5}\u{05D3} \u{05D1}\u{05DF}"],
    'Chinese' => ["\u{738B}\u{5C0F}\u{660E}"],
    'an emoji with a zero width joiner' => ["Ada \u{1F469}\u{200D}\u{1F4BB}"],
    'Hindi with a zero width joiner' => ["\u{0915}\u{094D}\u{200D}\u{0937}"],
    'Malayalam with a zero width joiner at the end of a word' => ["\u{0D05}\u{0D35}\u{0D28}\u{0D4D}\u{200D} \u{0D30}\u{0D3E}\u{0D1C}"],
    'Bengali with a zero width joiner at the end of a word' => ["\u{09A4}\u{09CD}\u{200D}"],
    'the flag of a country' => ["Ada \u{1F1E7}\u{1F1EA}"],
    'the flag of Scotland' => ["Ada \u{1F3F4}\u{E0067}\u{E0062}\u{E0073}\u{E0063}\u{E0074}\u{E007F}"],
]);

test('a name of characters that show nothing is refused as an empty one', function (string $nothing) {
    expect(fn () => app(UpdatesUserProfileInformation::class)->update($this->owner, ['name' => $nothing, 'email' => $this->owner->email]))->toThrow(ValidationException::class)
        ->and(fn () => app(CreatesAccessGroups::class)->create($this->owner, $this->community, ['name' => $nothing]))->toThrow(ValidationException::class)
        ->and($this->owner->fresh()?->name)->not->toBe($nothing);
})->with([
    'two Hangul fillers' => ["\u{115F}\u{1160}"],
    'a halfwidth Hangul filler' => ["\u{FFA0}"],
    'two invisible operators' => ["\u{2061}\u{2062}"],
    'a soft hyphen' => ["\u{00AD}"],
    'a left-to-right mark' => ["\u{200E}"],
    'tag characters' => ["\u{E0041}\u{E0042}"],
]);

test('an access group that reads as a taken name is refused as taken', function (string $name) {
    app(CreatesAccessGroups::class)->create($this->owner, $this->community, ['name' => 'Staff']);

    try {
        app(CreatesAccessGroups::class)->create($this->owner, $this->community, ['name' => $name]);
        $this->fail('The access group '.json_encode($name).' is created next to "Staff"');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('name');
    }
    expect(AccessGroup::query()->where('community_id', $this->community->id)->pluck('name')->all())->toBe(['Staff']);
})->with([
    'a soft hyphen' => ["Staff\u{00AD}"],
    'an invisible operator' => ["Staff\u{2061}"],
    'a left-to-right mark' => ["Staff\u{200E}"],
    'a Mongolian vowel separator' => ["Staff\u{180E}"],
    'a tag character' => ["Staff\u{E0020}"],
    'a combining grapheme joiner' => ["Staff\u{034F}"],
    'a Hangul filler' => ["Staff\u{115F}"],
    'a zero width space' => ["Staff\u{200B}"],
]);

test('a required name with only characters that the cleaning removes is refused as an empty one', function () {
    $nothing = "\u{202E}\u{200B} \u{0000}\r\n";

    expect(fn () => app(UpdatesUserProfileInformation::class)->update($this->owner, ['name' => $nothing, 'email' => $this->owner->email]))->toThrow(ValidationException::class)
        ->and(fn () => app(CreatesSpaceGroups::class)->create($this->owner, $this->community, ['name' => $nothing]))->toThrow(ValidationException::class)
        ->and(fn () => app(CreatesPosts::class)->create($this->owner, plainTextSpace(KnowiiSpaceType::Discussion), ['title' => $nothing, 'body' => 'Hello']))->toThrow(ValidationException::class)
        ->and(fn () => app(CreatesCommunities::class)->create($this->owner, ['name' => $nothing, 'visibility' => 'private']))->toThrow(ValidationException::class)
        ->and(fn () => app(UploadsImages::class)->upload($this->owner, $this->community, ['file' => UploadedFile::fake()->image('a.jpg', 20, 20), 'alt' => $nothing]))->toThrow(ValidationException::class);
    $this->post('/register', ['name' => $nothing, 'email' => 'nothing@example.com'])->assertSessionHasErrors('name');
    $this->actingAs($this->owner)->post('/user/api-tokens', ['name' => $nothing])->assertSessionHasErrors('name');

    // A field that may be empty becomes empty
    app(UpdatesUserProfileInformation::class)->update($this->owner, ['email' => $this->owner->email, 'location' => $nothing, 'bio' => "\u{202E}\u{0000}"]);
    expect($this->owner->fresh()?->name)->not->toBe('')
        ->and($this->owner->profile->fresh()?->location)->toBeNull()
        ->and($this->owner->profile->fresh()?->bio)->toBeNull();
});

test('the length of a name counts what is stored', function () {
    // 200 letters, the longest title, with invisible characters around and inside
    $title = "\u{200B}".str_repeat('a', 100)."\u{202E}".str_repeat('b', 100)."\u{200B}";

    $post = app(CreatesPosts::class)->create($this->owner, plainTextSpace(KnowiiSpaceType::Discussion), ['title' => $title, 'body' => 'Hello']);

    expect($post->title)->toBe(str_repeat('a', 100).str_repeat('b', 100));
});

// ---------------------------------------------------------------------------------------------------------------------
// The stored values
// ---------------------------------------------------------------------------------------------------------------------

test('the migration cleans the stored names, titles and descriptions, and changes nothing the second time', function () {
    $dirty = "Ada\u{202E}\r\nLove\u{200B}lace\u{0007}";
    $owner = $this->owner;
    $profile = $owner->profile;
    $space = plainTextSpace(KnowiiSpaceType::Discussion);
    $events = plainTextSpace(KnowiiSpaceType::Events);
    $post = Post::factory()->inSpace($space)->create();
    $page = Page::factory()->inSpace(plainTextSpace(KnowiiSpaceType::Pages))->create();
    $event = EventModel::factory()->inSpace($events)->hostedBy($owner)->create();
    $group = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Paid']);
    $upload = app(UploadsImages::class)->upload($owner, $this->community, ['file' => UploadedFile::fake()->image('a.jpg', 20, 20), 'alt' => 'A photo']);
    $token = $owner->createToken('Script')->accessToken;
    $resource = CommunityResource::factory()->create(['space_id' => plainTextSpace(KnowiiSpaceType::Resources)->id, 'resource_id' => Resource::factory()->create(['keywords' => []])->id, 'curator_id' => $profile->id]);
    $material = app(CreatesCourseMaterials::class)->create($owner, plainTextSpace(KnowiiSpaceType::Course), ['kind' => 'link', 'title' => 'Template', 'url' => 'https://example.com/template']);
    $personal = Community::factory()->create(['owner_id' => $owner->id, 'visibility' => KnowiiCommunityVisibility::Personal]);

    // What an earlier version stored
    $rows = [
        ['users', $owner->id, ['name' => $dirty]],
        ['user_profiles', $profile->id, ['name' => $dirty, 'location' => $dirty, 'headline' => $dirty, 'bio' => $dirty]],
        ['communities', $this->community->id, ['description' => $dirty]],
        ['communities', $personal->id, ['name' => $dirty."'s Space"]],
        ['space_groups', $this->group->id, ['name' => $dirty]],
        ['spaces', $space->id, ['name' => $dirty, 'description' => $dirty, 'emoji' => "\u{1F4E3}\u{200B}"]],
        ['access_groups', $group->id, ['name' => $dirty, 'description' => $dirty]],
        ['posts', $post->id, ['title' => $dirty]],
        ['pages', $page->id, ['title' => $dirty]],
        ['events', $event->id, ['title' => $dirty, 'place' => $dirty, 'cover_alt' => $dirty]],
        // A file, as the upload of a material stores it
        ['course_materials', $material->id, ['title' => $dirty, 'kind' => 'file', 'url' => null, 'disk' => 'public', 'path' => 'course-materials/a.pdf', 'file_name' => "report\u{202E}fdp.exe", 'mime_type' => 'application/pdf', 'size_bytes' => 10]],
        ['community_resources', $resource->id, ['name' => $dirty, 'description' => $dirty]],
        ['resources', $resource->resource_id, ['name' => $dirty, 'description' => $dirty, 'cover_image_alt' => $dirty]],
        ['uploads', $upload->id, ['alt' => $dirty]],
        ['personal_access_tokens', $token->id, ['name' => $dirty]],
    ];
    foreach ($rows as [$table, $id, $values]) {
        DB::table($table)->where('id', $id)->update($values);
    }
    $slugs = [DB::table('communities')->where('id', $personal->id)->value('slug'), DB::table('spaces')->where('id', $space->id)->value('slug'), DB::table('posts')->where('id', $post->id)->value('slug')];

    $migration = require database_path('migrations/'.PLAIN_TEXT_MIGRATION);
    $migration->up();

    $line = 'Ada Lovelace';
    $lines = "Ada\nLove\u{200B}lace";
    $expected = [
        'name' => $line, 'title' => $line, 'location' => $line, 'headline' => $line, 'place' => $line, 'cover_alt' => $line, 'alt' => $line, 'cover_image_alt' => $line,
        'bio' => $lines, 'description' => $lines, 'emoji' => "\u{1F4E3}", 'file_name' => 'reportfdp.exe',
    ];
    foreach ($rows as [$table, $id, $values]) {
        $stored = (array) DB::table($table)->where('id', $id)->first(array_keys($values));
        foreach (array_intersect(array_keys($values), array_keys($expected)) as $column) {
            $want = match (true) {
                $table === 'communities' && $column === 'name' => $line."'s Space",
                // The description of a captured page is one line, as its title
                $table === 'resources' && $column === 'description' => $line,
                default => $expected[$column],
            };
            expect($stored[$column])->toBe($want, $table.'.'.$column);
        }
    }
    // The addresses of the pages stay: a slug does not change
    expect([DB::table('communities')->where('id', $personal->id)->value('slug'), DB::table('spaces')->where('id', $space->id)->value('slug'), DB::table('posts')->where('id', $post->id)->value('slug')])->toBe($slugs);

    // The second time: no row is written
    $writes = 0;
    DB::listen(function ($query) use (&$writes) {
        $writes += str_starts_with(strtolower($query->sql), 'update') ? 1 : 0;
    });
    $migration->up();
    expect($writes)->toBe(0);
});

test('the migration gives a required name with nothing left a safe value, and keeps a unique name unique', function () {
    $nothing = "\u{202E}\u{200B}\u{115F}\u{1160}\u{FFA0}\u{2061}\u{00AD}\u{200E}\u{E0041}";
    $owner = $this->owner;
    $space = plainTextSpace(KnowiiSpaceType::Discussion);
    $post = Post::factory()->inSpace($space)->create();
    $upload = app(UploadsImages::class)->upload($owner, $this->community, ['file' => UploadedFile::fake()->image('a.jpg', 20, 20), 'alt' => 'A photo']);
    $token = $owner->createToken('Script')->accessToken;
    $paid = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Paid']);
    $twin = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => "Pa\u{200B}id\u{00AD}"]);
    $third = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => "Paid\u{202E}"]);
    $empty = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Temporary']);
    $other = Community::factory()->create(['owner_id' => $owner->id, 'visibility' => KnowiiCommunityVisibility::Private, 'name' => 'Plain Notes']);
    $same = Community::factory()->create(['owner_id' => $owner->id, 'visibility' => KnowiiCommunityVisibility::Private, 'name' => 'Temporary Name']);
    $personal = Community::factory()->create(['owner_id' => $owner->id, 'visibility' => KnowiiCommunityVisibility::Personal, 'name' => 'Temporary Personal']);

    DB::table('users')->where('id', $owner->id)->update(['name' => $nothing]);
    DB::table('user_profiles')->where('id', $owner->profile->id)->update(['name' => $nothing]);
    DB::table('communities')->where('id', $this->community->id)->update(['name' => $nothing]);
    DB::table('communities')->where('id', $same->id)->update(['name' => 'plain  notes']);
    DB::table('communities')->where('id', $personal->id)->update(['name' => 'Plain  Notes']);
    DB::table('space_groups')->where('id', $this->group->id)->update(['name' => $nothing]);
    DB::table('spaces')->where('id', $space->id)->update(['name' => $nothing]);
    DB::table('access_groups')->where('id', $empty->id)->update(['name' => $nothing]);
    DB::table('posts')->where('id', $post->id)->update(['title' => $nothing]);
    DB::table('uploads')->where('id', $upload->id)->update(['alt' => $nothing]);
    DB::table('personal_access_tokens')->where('id', $token->id)->update(['name' => $nothing]);

    $migration = require database_path('migrations/'.PLAIN_TEXT_MIGRATION);
    $migration->up();

    expect(DB::table('users')->where('id', $owner->id)->value('name'))->toBe($owner->username)
        ->and(DB::table('user_profiles')->where('id', $owner->profile->id)->value('name'))->toBe($owner->username)
        ->and(DB::table('communities')->where('id', $this->community->id)->value('name'))->toBe($this->community->slug)
        ->and(DB::table('space_groups')->where('id', $this->group->id)->value('name'))->toBe('Group')
        ->and(DB::table('spaces')->where('id', $space->id)->value('name'))->toBe($space->slug)
        ->and(DB::table('access_groups')->where('id', $empty->id)->value('name'))->toBe('Group')
        ->and(DB::table('posts')->where('id', $post->id)->value('title'))->toBe('Untitled')
        ->and(DB::table('uploads')->where('id', $upload->id)->value('alt'))->toBe('Image')
        ->and(DB::table('personal_access_tokens')->where('id', $token->id)->value('name'))->toBe('Token')
        // Two access groups of a community never get the same name
        ->and(DB::table('access_groups')->where('id', $paid->id)->value('name'))->toBe('Paid')
        ->and(DB::table('access_groups')->where('id', $twin->id)->value('name'))->toBe('Paid 2')
        ->and(DB::table('access_groups')->where('id', $third->id)->value('name'))->toBe('Paid 3')
        // Two shared communities never get the same name, whatever the case; a personal community may have any name
        ->and(DB::table('communities')->where('id', $other->id)->value('name'))->toBe('Plain Notes')
        ->and(DB::table('communities')->where('id', $same->id)->value('name'))->toBe('plain notes 2')
        ->and(DB::table('communities')->where('id', $personal->id)->value('name'))->toBe('Plain Notes');
});

test('the migration keeps a stored name with a joiner at the end of a word, a flag or a Mongolian vowel separator', function () {
    $names = [
        "\u{0D05}\u{0D35}\u{0D28}\u{0D4D}\u{200D} \u{0D30}\u{0D3E}\u{0D1C}",
        "\u{09A4}\u{09CD}\u{200D}",
        "Ada \u{1F3F4}\u{E0067}\u{E0062}\u{E0073}\u{E0063}\u{E0074}\u{E007F}",
        "\u{1828}\u{1820}\u{180E}\u{1820}",
        "\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467} \u{1F1E7}\u{1F1EA}",
    ];
    $groups = [];
    foreach ($names as $name) {
        $groups[] = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => $name])->id;
    }
    DB::table('users')->where('id', $this->owner->id)->update(['name' => $names[0]]);

    $migration = require database_path('migrations/'.PLAIN_TEXT_MIGRATION);
    $migration->up();

    expect(DB::table('access_groups')->whereIn('id', $groups)->orderBy('id')->pluck('name')->all())->toBe($names)
        ->and(DB::table('users')->where('id', $this->owner->id)->value('name'))->toBe($names[0]);
});
