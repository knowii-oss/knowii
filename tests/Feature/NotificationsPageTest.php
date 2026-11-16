<?php

/*
 * The page "Notifications" of the member (#1476): the lists load from the API like the panel of the bell, so the page holds the
 * communities of the member (the "Catch up" cards need their names and slugs). A visitor goes to the sign-in page.
 */

test('the page lists the communities of the member', function () {
    $c = communityWithMembers();

    $this->actingAs($c['member'])
        ->get('/notifications')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Notifications/Index')
            ->has('communities', 1)
            ->where('communities.0.cuid', $c['community']->cuid)
            ->where('communities.0.slug', $c['community']->slug));
});

test('the page is named notifications.index', function () {
    expect(route('notifications.index', absolute: false))->toBe('/notifications');
});

test('the page shows no community to a person who belongs to none', function () {
    $c = communityWithMembers();

    $this->actingAs($c['outsider'])
        ->get('/notifications')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Notifications/Index')->has('communities', 0));
});

test('a visitor goes to the sign-in page', function () {
    $this->get('/notifications')->assertRedirect(route('login'));
});

test('the shared prop catchUp reaches the page for a member who was away for 7 days or more', function () {
    $c = communityWithMembers();
    $c['member']->forceFill(['last_visit_on' => now('UTC')->subDays(9)->toDateString()])->save();

    $this->actingAs($c['member'])
        ->get('/notifications')
        ->assertInertia(fn ($page) => $page->where('catchUp.since', now('UTC')->subDays(9)->toDateString()));
});
