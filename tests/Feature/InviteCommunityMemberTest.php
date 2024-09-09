<?php

use App\Mail\CommunityInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('users can be invited to a community', function () {
    Mail::fake();

    $this->actingAs($user = User::factory()->withPersonalCommunity()->create());

    $this->post('/communities/'.$user->personalCommunity()->id.'/members', [
        'email' => 'test@example.com',
        'role' => 'admin',
    ]);

    Mail::assertSent(CommunityInvitation::class);

    expect($user->personalCommunity()->communityInvitations)->toHaveCount(1);
});

test('community member invitations can be cancelled', function () {
    Mail::fake();

    $this->actingAs($user = User::factory()->withPersonalCommunity()->create());

    $invitation = $user->personalCommunity()->communityInvitations()->create([
        'email' => 'test@example.com',
        'role' => 'admin',
    ]);

    $this->delete('/community-invitations/'.$invitation->id);

    expect($user->personalCommunity()->fresh()->communityInvitations)->toHaveCount(0);
});
