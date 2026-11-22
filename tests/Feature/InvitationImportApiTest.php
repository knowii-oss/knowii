<?php

use App\ApiRoutes;
use App\Enums\KnowiiModeratorPermission;
use App\Models\AccessGroup;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\InvitationImport;
use App\Models\MemberTag;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\Mail\RefusingTransport;

/*
 * The routes of the invitations from a file (#1390 part B, ADR 0061): who may call each, the resources, the pagination,
 * the token abilities and the limiter. The rules of the rows are in InvitationImportTest.
 */

beforeEach(function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-18T10:00:00Z'));
    config(['queue.default' => 'database', 'mail.default' => 'array', 'mail.queue' => 'mail', 'mail.rate_limit_per_second' => 1000]);
    // An upload never writes a file: every disk of the configuration is a fake
    foreach (array_keys((array) config('filesystems.disks')) as $disk) {
        Storage::fake((string) $disk);
    }
    $this->c = communityWithMembers();
    $this->community = $this->c['community'];
    foreach (['owner', 'admin', 'moderator', 'member', 'outsider'] as $who) {
        $this->c[$who]->forceFill(['email' => 'qx-'.$who.'@example.com'])->save();
    }
});

afterEach(function () {
    Carbon::setTestNow();
});

function importApiPath(string $route, Community $community, ?InvitationImport $import = null): string
{
    $params = [ApiRoutes::PARAM_COMMUNITY => $community->cuid];
    if ($import !== null) {
        $params[ApiRoutes::PARAM_INVITATION_IMPORT] = $import->cuid;
    }

    return ApiRoutes::path($route, $params);
}

function importApiFile(string $content, string $name = 'invitations.csv', string $type = 'text/csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content)->mimeType($type);
}

/**
 * @param  array<string, mixed>  $fields
 */
function importApiUpload(User $user, Community $community, string $content, array $fields = []): TestResponse
{
    app('auth')->forgetGuards();

    return test()->actingAs($user)->post(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORTS, $community), ['file' => importApiFile($content), ...$fields], ['Accept' => 'application/json']);
}

function importApiManageMembers(Community $community): void
{
    $community->update(['moderator_permissions' => [...KnowiiModeratorPermission::defaults(), 'manage_members' => true]]);
}

test('the owner, an admin and a moderator who manages members upload a file and get the preview (#1390)', function (string $who) {
    importApiManageMembers($this->community);
    $group = AccessGroup::query()->create(['community_id' => $this->community->id, 'name' => 'Paid']);
    $tag = MemberTag::factory()->forManagers()->create(['community_id' => $this->community->id, 'name' => 'Onboarded']);

    $response = importApiUpload($this->c[$who], $this->community, "email,name,phone\nqx-1@example.com,Ada Example,123\nnot an address,,\n", [
        'access_groups' => [$group->cuid],
        'member_tags' => [$tag->cuid],
    ])->assertCreated();

    $response->assertJsonPath('data.state', 'preview')
        ->assertJsonPath('data.default_role', 'member')
        ->assertJsonPath('data.default_access_groups', [['cuid' => $group->cuid, 'name' => 'Paid']])
        ->assertJsonPath('data.default_member_tags.0.cuid', $tag->cuid)
        ->assertJsonPath('data.ignored_columns', ['phone'])
        ->assertJsonPath('data.counts', ['rows' => 2, 'valid' => 1, 'refused' => 1, 'queued' => 0, 'sent' => 0, 'held' => 0, 'failed' => 0, 'skipped' => 0, 'still_invited' => 0, 'not_sent' => 0])
        ->assertJsonPath('data.started_by.cuid', $this->c[$who]->cuid)
        ->assertJsonPath('data.confirmed_at', null)
        ->assertJsonPath('data.can_resend_not_joined_from', null)
        ->assertJsonPath('data.kept_until', '2026-11-19T10:00:00.000000Z');
    expect(json_encode($response->json()))->not->toContain('Ada Example');
})->with(['owner', 'admin', 'moderator']);

test('a moderator without the right, a member and a person who is not a member get 403 on every route (#1390)', function (string $who) {
    $import = InvitationImport::factory()->create(['community_id' => $this->community->id, 'user_id' => $this->c['owner']->id]);
    $actor = $this->c[$who];

    importApiUpload($actor, $this->community, "email\nqx-1@example.com\n")->assertForbidden();
    $this->actingAs($actor)->getJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORTS, $this->community))->assertForbidden();
    $this->actingAs($actor)->getJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT, $this->community, $import))->assertForbidden();
    $this->actingAs($actor)->getJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT_ROWS, $this->community, $import))->assertForbidden();
    $this->actingAs($actor)->postJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT_CONFIRM, $this->community, $import))->assertForbidden();
    $this->actingAs($actor)->postJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT_RESEND, $this->community, $import), ['rows' => 'not_sent'])->assertForbidden();
    $this->actingAs($actor)->deleteJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT, $this->community, $import))->assertForbidden();

    expect(InvitationImport::query()->whereKey($import->id)->exists())->toBeTrue();
})->with(['moderator', 'member', 'outsider']);

test('an import of another community answers 404 (#1390)', function () {
    $other = communityWithMembers();
    $import = InvitationImport::factory()->create(['community_id' => $other['community']->id, 'user_id' => $other['owner']->id]);

    $this->actingAs($this->c['owner'])->getJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT, $this->community, $import))->assertNotFound();
    $this->actingAs($this->c['owner'])->getJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT_ROWS, $this->community, $import))->assertNotFound();
    $this->actingAs($this->c['owner'])->deleteJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT, $this->community, $import))->assertNotFound();
});

test('an error of the file is a 400 on the field file (#1390)', function (string $content, string $name, string $message) {
    app('auth')->forgetGuards();
    $this->actingAs($this->c['owner'])
        ->post(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORTS, $this->community), ['file' => importApiFile($content, $name)], ['Accept' => 'application/json'])
        ->assertStatus(400)
        ->assertJsonPath('errors.file.0', $message);

    expect(InvitationImport::query()->count())->toBe(0);
})->with([
    'no column email' => ["name\nAda\n", 'invitations.csv', 'The file needs a column named email'],
    'not UTF-8' => ["email,name\nada@example.com,Ren\xE9e\n", 'invitations.csv', 'Save the file as CSV UTF-8, then upload it again'],
    'larger than 1 MB' => ["email\n".str_repeat('a', 1024 * 1024 + 10), 'invitations.csv', 'The file is larger than 1 MB'],
]);

test('a spreadsheet or a binary file with a .csv name gets the words of a file that is not CSV UTF-8 (#1390)', function (string $content, string $type) {
    app('auth')->forgetGuards();
    $this->actingAs($this->c['owner'])
        ->post(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORTS, $this->community), ['file' => importApiFile($content, 'invitations.csv', $type)], ['Accept' => 'application/json'])
        ->assertStatus(400)
        ->assertJsonPath('errors.file.0', 'Save the file as CSV UTF-8, then upload it again');

    expect(InvitationImport::query()->count())->toBe(0);
})->with([
    'a spreadsheet' => ["PK\x03\x04 the start of a spreadsheet", 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'a binary file' => ["\x00\x01\x02\x03", 'application/octet-stream'],
]);

test('a file is a CSV file: another extension is refused, a spreadsheet type of Windows is read (#1390)', function () {
    app('auth')->forgetGuards();
    $this->actingAs($this->c['owner'])
        ->post(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORTS, $this->community), ['file' => importApiFile("email\nqx@example.com\n", 'invitations.txt', 'text/plain')], ['Accept' => 'application/json'])
        ->assertStatus(400);
    $this->actingAs($this->c['owner'])
        ->post(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORTS, $this->community), ['file' => importApiFile("email\nqx@example.com\n", 'invitations.csv', 'application/vnd.ms-excel')], ['Accept' => 'application/json'])
        ->assertCreated();
});

test('the list, one import and its rows, with the pagination and the filter of the outcome (#1390)', function () {
    $import = importApiUpload($this->c['admin'], $this->community, "email,role\nqx-1@example.com,\nnot valid,\nqx-3@example.com,owner\nqx-4@example.com,moderator\n")->assertCreated();
    $cuid = $import->json('data.cuid');
    $model = InvitationImport::query()->where('cuid', $cuid)->sole();
    InvitationImport::factory()->done()->create(['community_id' => $this->community->id, 'created_at' => now()->subDay()]);

    $this->actingAs($this->c['owner'])->getJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORTS, $this->community).'?limit=1')
        ->assertOk()
        ->assertJsonPath('data.0.cuid', $cuid)
        ->assertJsonPath('data.0.counts.rows', 4)
        ->assertJsonPath('metadata.pagination.totalCount', 2)
        ->assertJsonPath('metadata.pagination.nextOffset', 1);
    $this->actingAs($this->c['owner'])->getJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT, $this->community, $model))
        ->assertOk()->assertJsonPath('data.counts.refused', 2)->assertJsonPath('data.started_by.cuid', $this->c['admin']->cuid);

    $this->actingAs($this->c['owner'])->getJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT_ROWS, $this->community, $model).'?outcome=refused')
        ->assertOk()
        ->assertJsonPath('data.*.row_number', [3, 4])
        ->assertJsonPath('data.0.email', 'not valid')
        ->assertJsonPath('data.0.reason', 'invalid_email')
        ->assertJsonPath('data.0.reason_text', 'This is not a valid email address')
        ->assertJsonPath('data.1.reason_text', 'Use member, moderator or admin')
        ->assertJsonPath('data.1.still_invited', false);
    $this->actingAs($this->c['owner'])->getJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT_ROWS, $this->community, $model).'?outcome=valid,refused&limit=2&offset=2')
        ->assertOk()->assertJsonPath('data.*.row_number', [4, 5])->assertJsonPath('metadata.pagination.totalCount', 4);
    $this->actingAs($this->c['owner'])->getJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT_ROWS, $this->community, $model).'?outcome=lost')->assertStatus(400);
});

test('a preview older than 24 hours answers 404, also before the prune step; it leaves the list (#1390)', function () {
    $import = InvitationImport::factory()->create(['community_id' => $this->community->id, 'user_id' => $this->c['owner']->id]);
    $this->travel(25)->hours();

    $this->actingAs($this->c['owner'])->getJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT, $this->community, $import))->assertNotFound();
    $this->actingAs($this->c['owner'])->postJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT_CONFIRM, $this->community, $import))->assertNotFound();
    $this->actingAs($this->c['owner'])->getJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORTS, $this->community))->assertOk()->assertJsonCount(0, 'data');
});

test('the confirm answers 202 with the import; the uploader only; a send again answers 202 (#1390)', function () {
    $cuid = importApiUpload($this->c['admin'], $this->community, "email\nqx-1@example.com\n")->json('data.cuid');
    $import = InvitationImport::query()->where('cuid', $cuid)->sole();

    $this->actingAs($this->c['owner'])->postJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT_CONFIRM, $this->community, $import))
        ->assertForbidden()->assertJsonPath('message', 'Only the person who uploaded the file sends it');
    $this->actingAs($this->c['admin'])->postJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT_CONFIRM, $this->community, $import))
        ->assertStatus(202)
        ->assertJsonPath('data.state', 'sending')
        ->assertJsonPath('data.counts.queued', 1)
        ->assertJsonPath('data.counts.still_invited', 1)
        ->assertJsonPath('data.can_resend_not_joined_from', '2026-11-21T10:00:00.000000Z')
        ->assertJsonPath('data.kept_until', '2026-12-25T10:00:00.000000Z');
    $this->actingAs($this->c['admin'])->postJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT_CONFIRM, $this->community, $import))
        ->assertStatus(422)->assertJsonPath('message', 'This import is sent already');

    $import->rows()->update(['outcome' => 'failed']);
    $this->actingAs($this->c['owner'])->postJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT_RESEND, $this->community, $import), ['rows' => 'not_sent'])
        ->assertStatus(202)->assertJsonPath('data.counts.queued', 1);
    $this->actingAs($this->c['owner'])->postJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT_RESEND, $this->community, $import), ['rows' => 'all'])->assertStatus(400);
});

test('a preview is discarded by its uploader, the owner or an admin; a sent record by the owner or an admin only (#1390)', function () {
    importApiManageMembers($this->community);
    $preview = fn (User $user) => InvitationImport::factory()->create(['community_id' => $this->community->id, 'user_id' => $user->id]);
    $path = fn (InvitationImport $import) => importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT, $this->community, $import);

    $this->actingAs($this->c['moderator'])->deleteJson($path($preview($this->c['admin'])))->assertForbidden();
    $this->actingAs($this->c['moderator'])->deleteJson($path($preview($this->c['moderator'])))->assertNoContent();
    $this->actingAs($this->c['admin'])->deleteJson($path($preview($this->c['owner'])))->assertNoContent();

    $sent = InvitationImport::factory()->done()->create(['community_id' => $this->community->id, 'user_id' => $this->c['moderator']->id]);
    $invitation = $this->community->communityInvitations()->create(['email' => 'qx-kept@example.com', 'role' => 'member']);
    $sent->rows()->create(['row_number' => 2, 'email' => 'qx-kept@example.com', 'role' => 'member', 'access_group_names' => [], 'member_tag_names' => [], 'outcome' => 'sent', 'community_invitation_id' => $invitation->id]);
    $this->actingAs($this->c['moderator'])->deleteJson($path($sent))->assertForbidden();
    $this->actingAs($this->c['owner'])->deleteJson($path($sent))->assertNoContent();

    // A record whose mails go out stays: 422
    $sending = InvitationImport::factory()->sending()->create(['community_id' => $this->community->id, 'user_id' => $this->c['owner']->id]);
    $sending->rows()->create(['row_number' => 2, 'email' => 'qx-wait@example.com', 'role' => 'member', 'access_group_names' => [], 'member_tag_names' => [], 'outcome' => 'queued', 'queued_at' => now()]);
    $this->actingAs($this->c['owner'])->deleteJson($path($sending))
        ->assertStatus(422)->assertJsonPath('message', 'The mails of this import are going out. Delete this record when they are sent.');
    $sending->delete();

    expect(InvitationImport::query()->count())->toBe(1)
        ->and(CommunityInvitation::query()->whereKey($invitation->id)->exists())->toBeTrue();
});

test('a token needs invitations:read and invitations:write; communities:write is not enough (#1390)', function () {
    $import = InvitationImport::factory()->create(['community_id' => $this->community->id, 'user_id' => $this->c['owner']->id]);

    Sanctum::actingAs($this->c['owner'], ['communities:read', 'communities:write']);
    $this->getJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORTS, $this->community))->assertForbidden();
    $this->getJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT_ROWS, $this->community, $import))->assertForbidden();
    $this->post(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORTS, $this->community), ['file' => importApiFile("email\nqx@example.com\n")], ['Accept' => 'application/json'])->assertForbidden();
    $this->postJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT_CONFIRM, $this->community, $import))->assertForbidden();
    $this->deleteJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT, $this->community, $import))->assertForbidden();

    Sanctum::actingAs($this->c['owner'], ['invitations:read']);
    $this->getJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT, $this->community, $import))->assertOk();
    $this->deleteJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT, $this->community, $import))->assertForbidden();

    Sanctum::actingAs($this->c['owner'], ['invitations:write']);
    $this->deleteJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT, $this->community, $import))->assertNoContent();
});

test('the routes that send mail are limited to 5 a minute per person (#1390)', function () {
    foreach (range(1, 5) as $try) {
        importApiUpload($this->c['owner'], $this->community, "email\nqx-".$try."@example.com\n")->assertCreated();
    }
    importApiUpload($this->c['owner'], $this->community, "email\nqx-6@example.com\n")->assertStatus(429);
});

test('no log line holds an address or a cell, and no file stays on a disk: an upload, a confirm, a send, a hold, a failure (#1390)', function () {
    $lines = [];
    Log::listen(static function ($message) use (&$lines): void {
        $lines[] = $message->message.' '.json_encode($message->context);
    });

    $cuid = importApiUpload($this->c['owner'], $this->community, "email,name,role,tags\nqx-secret1@example.com,Secret Name,,\nqx-secret2@example.com,,,Nopetag\nqx-secret3@example.com,,,\n")->json('data.cuid');
    $import = InvitationImport::query()->where('cuid', $cuid)->sole();
    $this->actingAs($this->c['owner'])->postJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT_CONFIRM, $this->community, $import))->assertStatus(202);
    // The first mail goes out
    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--queue' => 'mail', '--sleep' => 0, '--tries' => 3]);
    // The second fails at the provider for a plain error, three times
    Mail::extend('refusing', static fn () => new RefusingTransport(null));
    config(['mail.mailers.refusing' => ['transport' => 'refusing'], 'mail.default' => 'refusing']);
    Mail::purge('refusing');
    foreach (range(1, 3) as $try) {
        DB::table('jobs')->where('queue', 'mail')->update(['available_at' => now()->getTimestamp()]);
        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--queue' => 'mail', '--sleep' => 0, '--tries' => 3]);
    }
    // A send again while the budget holds mail
    $this->actingAs($this->c['owner'])->postJson(importApiPath(ApiRoutes::COMMUNITY_INVITATION_IMPORT_RESEND, $this->community, $import), ['rows' => 'not_sent'])->assertStatus(202);
    DB::table('mail_usage_months')->updateOrInsert(['month' => '2026-11-01'], ['cuid' => 'invitationimportapirow00a', 'sent_count' => 1, 'held_until' => '2026-12-01 00:00:00', 'created_at' => now(), 'updated_at' => now()]);
    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--queue' => 'mail', '--sleep' => 0, '--tries' => 3]);

    expect($import->rows()->orderBy('row_number')->pluck('outcome')->map(fn ($outcome) => $outcome->value)->all())->toBe(['sent', 'refused', 'held'])
        ->and($lines)->not->toBeEmpty();
    $all = implode("\n", $lines);
    expect($all)->not->toContain('qx-secret')->not->toContain('Secret Name')->not->toContain('Nopetag');
    foreach (array_keys((array) config('filesystems.disks')) as $disk) {
        expect(Storage::disk((string) $disk)->allFiles())->toBe([]);
    }
});
