<?php

use App\Contracts\Comments\CreatesComments;
use App\Contracts\Comments\DeletesComments;
use App\Contracts\Comments\UpdatesComments;
use App\Contracts\Posts\CreatesPosts;
use App\Contracts\Posts\DeletesPosts;
use App\Contracts\Posts\PinsPosts;
use App\Contracts\Posts\UpdatesPosts;
use App\Contracts\Reactions\TogglesReactions;
use App\Enums\KnowiiSpaceType;
use App\Events\Spaces\SpaceContentChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\CommunityResource;
use App\Models\Space;
use App\Models\SpaceGroup;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([SpaceTreeChanged::class, SpaceContentChanged::class]);
    $this->c = communityWithMembers();
    $this->space = Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]))->create(['type' => KnowiiSpaceType::Discussion]);
});

function assertSpaceEvent(string $name, array $payload, ?Space $space = null): void
{
    Event::assertDispatched(SpaceContentChanged::class, function (SpaceContentChanged $event) use ($name, $payload, $space) {
        $space ??= test()->space;
        $channel = $event->broadcastOn()[0];

        return $event->broadcastAs() === $name
            && $channel instanceof PrivateChannel
            && $channel->name === "private-community.{$space->community->cuid}.space.{$space->cuid}"
            && array_intersect_assoc($event->broadcastWith(), $payload) === $payload;
    });
}

test('creating, editing, pinning and deleting a post broadcast to the space (#1180)', function () {
    $member = $this->c['member'];
    $post = app(CreatesPosts::class)->create($member, $this->space, ['title' => 'Hello there', 'body' => 'Text']);
    assertSpaceEvent(SpaceContentChanged::POST_CREATED, ['space_cuid' => $this->space->cuid, 'post_cuid' => $post->cuid, 'author_cuid' => $member->cuid]);

    app(UpdatesPosts::class)->update($member, $post, ['body' => 'Edited']);
    app(PinsPosts::class)->pin($this->c['owner'], $post, ['pinned' => true]);
    Event::assertDispatchedTimes(SpaceContentChanged::class, 3);
    assertSpaceEvent(SpaceContentChanged::POST_UPDATED, ['post_cuid' => $post->cuid, 'author_cuid' => $this->c['owner']->cuid]);

    app(DeletesPosts::class)->delete($member, $post);
    assertSpaceEvent(SpaceContentChanged::POST_DELETED, ['post_cuid' => $post->cuid]);
});

test('the payload holds identifiers only, never the content (#1180)', function () {
    app(CreatesPosts::class)->create($this->c['member'], $this->space, ['title' => 'Secret title', 'body' => 'Secret body']);

    Event::assertDispatched(SpaceContentChanged::class, fn (SpaceContentChanged $event) => ! str_contains((string) json_encode($event->broadcastWith()), 'Secret'));
});

test('comments and reactions broadcast to the space of their post (#1180)', function () {
    $post = app(CreatesPosts::class)->create($this->c['member'], $this->space, ['title' => 'Hello there', 'body' => 'Text']);

    $comment = app(CreatesComments::class)->create($this->c['admin'], $post, ['body' => 'A comment']);
    assertSpaceEvent(SpaceContentChanged::COMMENT_CREATED, ['post_cuid' => $post->cuid, 'comment_cuid' => $comment->cuid]);

    app(UpdatesComments::class)->update($this->c['admin'], $comment, ['body' => 'Edited']);
    assertSpaceEvent(SpaceContentChanged::COMMENT_UPDATED, ['comment_cuid' => $comment->cuid]);

    app(TogglesReactions::class)->toggle($this->c['member'], $post, ['kind' => 'like']);
    assertSpaceEvent(SpaceContentChanged::REACTIONS_CHANGED, ['post_cuid' => $post->cuid]);

    app(TogglesReactions::class)->toggle($this->c['member'], $comment, ['kind' => 'like']);
    assertSpaceEvent(SpaceContentChanged::REACTIONS_CHANGED, ['post_cuid' => $post->cuid, 'comment_cuid' => $comment->cuid]);

    app(DeletesComments::class)->delete($this->c['admin'], $comment);
    assertSpaceEvent(SpaceContentChanged::COMMENT_DELETED, ['comment_cuid' => $comment->cuid]);
});

test('comments on a resource name the resource (#1180)', function () {
    $resources = Space::factory()->inGroup(SpaceGroup::factory()->create(['community_id' => $this->c['community']->id]))->create(['type' => KnowiiSpaceType::Resources]);
    $resource = CommunityResource::factory()->create(['space_id' => $resources->id]);

    $comment = app(CreatesComments::class)->create($this->c['member'], $resource, ['body' => 'Good read']);

    assertSpaceEvent(SpaceContentChanged::COMMENT_CREATED, ['resource_cuid' => $resource->cuid, 'comment_cuid' => $comment->cuid], $resources);
});
