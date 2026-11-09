<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->withUserProfile()->create();
});

test('a member uploads an avatar: a re-encoded square of 256 pixels (#109)', function () {
    $response = $this->actingAs($this->user)
        ->post('/api/v1/user/avatar', ['file' => UploadedFile::fake()->image('me.jpg', 1200, 800)], ['Accept' => 'application/json'])
        ->assertOk();

    $path = $this->user->fresh()?->avatar_path;
    expect($path)->toStartWith('avatars/'.$this->user->cuid.'/');
    Storage::disk('public')->assertExists((string) $path);

    $size = getimagesizefromstring(Storage::disk('public')->get((string) $path));
    expect($size[0])->toBe(256)->and($size[1])->toBe(256)
        ->and($response->json('data.avatar_url'))->toContain($path);
});

test('a new avatar replaces the old file (#109)', function () {
    $this->actingAs($this->user)->post('/api/v1/user/avatar', ['file' => UploadedFile::fake()->image('a.png', 300, 300)], ['Accept' => 'application/json'])->assertOk();
    $first = (string) $this->user->fresh()?->avatar_path;

    $this->actingAs($this->user)->post('/api/v1/user/avatar', ['file' => UploadedFile::fake()->image('b.png', 300, 300)], ['Accept' => 'application/json'])->assertOk();

    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists((string) $this->user->fresh()?->avatar_path);
});

test('a member removes the avatar (#109)', function () {
    $this->actingAs($this->user)->post('/api/v1/user/avatar', ['file' => UploadedFile::fake()->image('a.png', 300, 300)], ['Accept' => 'application/json'])->assertOk();
    $path = (string) $this->user->fresh()?->avatar_path;

    $this->actingAs($this->user)->deleteJson('/api/v1/user/avatar')->assertOk()->assertJsonPath('data.avatar_url', null);

    Storage::disk('public')->assertMissing($path);
    expect($this->user->fresh()?->avatar_path)->toBeNull();
});

test('only images are accepted as avatars (#109)', function () {
    $this->actingAs($this->user)
        ->post('/api/v1/user/avatar', ['file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertStatus(400);

    expect($this->user->fresh()?->avatar_path)->toBeNull();
});

test('guests cannot change an avatar (#109)', function () {
    $this->post('/api/v1/user/avatar', ['file' => UploadedFile::fake()->image('a.png', 10, 10)], ['Accept' => 'application/json'])->assertUnauthorized();
});

test('members see the avatar of other members, never a third-party placeholder (#109)', function () {
    $c = communityWithMembers();
    $c['member']->forceFill(['avatar_path' => 'avatars/x/y.webp'])->save();

    $this->actingAs($c['admin'])
        ->getJson('/api/v1/communities/'.$c['community']->cuid.'/members')
        ->assertOk()
        ->assertJsonPath('data.3.avatar_url', Storage::disk('public')->url('avatars/x/y.webp'))
        ->assertJsonPath('data.0.avatar_url', null)
        ->assertJsonMissingPath('data.3.avatar_path');
});
