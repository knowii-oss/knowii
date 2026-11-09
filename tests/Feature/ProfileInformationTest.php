<?php

use App\Constants;
use App\Http\Resources\UserProfileResource;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

test('profile information can be updated', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->create());

    $this->put('/user/profile-information', [
        'email' => 'test@example.com',
        'name' => 'Test Name',
    ]);

    expect($user->fresh())
        ->name->toEqual('Test Name')
        ->email->toEqual('test@example.com');
});

test('username can be updated via profile information', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->create());

    $this->put('/user/profile-information', [
        'email' => 'test@example.com',
        'username' => 'foobar36',
        'name' => 'Test Name',
    ]);

    expect($user->fresh())
        ->name->toEqual('Test Name')
        ->email->toEqual('test@example.com')
        ->username->toEqual('foobar36');
});

test('username cannot be set to an invalid value via profile information', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->create());

    $response = $this->put('/user/profile-information', [
        'name' => 'Test Name',
        'email' => 'test@example.com',
        'username' => 'f', // too short
    ]);

    expect($response->exception)->toBeInstanceOf(ValidationException::class);
});

test('the new social link types can be updated via profile information', function () {
    $this->actingAs($user = User::factory()->withUserProfile()->create());

    $links = [
        'social_link_wikipedia' => 'https://en.wikipedia.org/wiki/User:Example',
        'social_link_google_scholar' => 'https://scholar.google.com/citations?user=abc',
        'social_link_orcid' => 'https://orcid.org/0000-0002-1825-0097',
        'social_link_kofi' => 'https://ko-fi.com/example',
    ];

    $this->put('/user/profile-information', [
        'email' => $user->email,
        'name' => 'Test Name',
        ...$links,
    ]);

    expect($user->fresh()->profile->only(array_keys($links)))->toEqual($links);
});

test('every social link property exists on the user profile and in its API resource', function () {
    $user = User::factory()->withUserProfile()->create();
    $serialized = (new UserProfileResource($user->profile))->toArray(request());

    foreach (Constants::SOCIAL_MEDIA_LINK_PROPERTIES as $property) {
        expect(Schema::hasColumn('user_profiles', $property))->toBeTrue("Missing column {$property}")
            ->and($serialized)->toHaveKey($property);
    }
});
