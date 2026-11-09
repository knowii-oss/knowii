<?php

use App\Models\User;
use App\Support\ProfileFieldVisibility;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->c = communityWithMembers();
    $this->c['member']->profile->forceFill([
        'bio' => 'I like notes',
        'location' => 'Brussels',
        'phone' => '+32 470 12 34 56',
        'social_link_github' => 'https://github.com/ada',
        'social_link_website' => 'https://ada.example.com',
    ])->save();
});

function memberPageAs(User $viewer, array $c): TestResponse
{
    return test()->actingAs($viewer)->get(route('members.show', ['communitySlug' => $c['community']->slug, 'username' => $c['member']->username]));
}

test('by default, members see the bio, the location and the links, not the phone (#1076)', function () {
    memberPageAs($this->c['admin'], $this->c)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('member.bio', 'I like notes')
            ->where('member.location', 'Brussels')
            ->where('member.phone', '')
            ->where('member.social_links.social_link_github', 'https://github.com/ada'));
});

test('a member hides fields and shows the phone from the profile form (#1076)', function () {
    $member = $this->c['member'];

    $this->actingAs($member)->put('/user/profile-information', [
        'email' => $member->email,
        'name' => $member->name,
        'bio' => 'I like notes',
        'location' => 'Brussels',
        'phone' => '+32 470 12 34 56',
        'social_link_github' => 'https://github.com/ada',
        'social_link_website' => 'https://ada.example.com',
        'field_visibility' => ['bio' => false, 'phone' => true, 'social_link_github' => false, 'is_admin' => true],
    ])->assertSessionHasNoErrors();

    $stored = $member->profile()->first()?->field_visibility;
    expect($stored)->toBeArray()->not->toHaveKey('is_admin')
        ->and($stored['bio'])->toBeFalse()
        ->and($stored['location'])->toBeTrue();

    memberPageAs($this->c['admin'], $this->c)
        ->assertInertia(fn ($page) => $page->where('member.bio', '')
            ->where('member.location', 'Brussels')
            ->where('member.phone', '+32 470 12 34 56')
            ->missing('member.social_links.social_link_github')
            ->where('member.social_links.social_link_website', 'https://ada.example.com'));
});

test('the visibility choices must be booleans (#1076)', function () {
    $member = $this->c['member'];

    $this->actingAs($member)->put('/user/profile-information', [
        'email' => $member->email,
        'name' => $member->name,
        'field_visibility' => ['bio' => 'maybe'],
    ])->assertSessionHasErrorsIn('updateProfileInformation', ['field_visibility.bio']);
});

test('a profile form without the choices keeps them (#1076)', function () {
    $member = $this->c['member'];
    $member->profile->forceFill(['field_visibility' => [...ProfileFieldVisibility::defaults(), 'location' => false]])->save();

    $this->actingAs($member)->put('/user/profile-information', ['email' => $member->email, 'name' => 'Ada L.'])->assertSessionHasNoErrors();

    expect($member->profile()->first()?->fieldVisibility()['location'])->toBeFalse();
});

test('the member sees every visibility choice in their own profile (#1076)', function () {
    $this->actingAs($this->c['member'])->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.profile.field_visibility.phone', false)
        ->assertJsonPath('data.profile.field_visibility.bio', true);
});
