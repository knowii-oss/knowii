<?php

/*
 * The notification settings page of the member (#1497): "Your spaces" lists the communities of the member. The levels load from
 * the API, so the page holds the communities only, and a visitor goes to the sign-in page.
 */

test('the page lists the communities of the member', function () {
    $c = communityWithMembers();

    $this->actingAs($c['member'])
        ->get(route('profile.notifications'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Profile/Notifications')
            ->has('communities', 1)
            ->where('communities.0.cuid', $c['community']->cuid));
});

test('the page shows no community to a person who belongs to none', function () {
    $c = communityWithMembers();

    $this->actingAs($c['outsider'])
        ->get(route('profile.notifications'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Profile/Notifications')->has('communities', 0));
});

test('a visitor goes to the sign-in page', function () {
    $this->get(route('profile.notifications'))->assertRedirect(route('login'));
});
