<?php

namespace App\Contracts\Notifications;

use App\Models\Community;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * The data of the "Catch up" card (#1476, ADR 0048, design 5.5): at most 5 lines, in this order, each with what the client
 * needs to show two lines of text and to open the item.
 *
 * - `needs_you`: up to 2 open notifications of level 1 of the member in the community, newest first
 * - `announcement`: up to 2 posts of other members in the discussion spaces where only the moderators post, since the date
 * - `thread`: 1 line for the thread with the most new comments since the date, among the posts that the member follows and
 *   the posts of the spaces at "Everything"
 *
 * `has_more` is true when a source holds more than what is shown. The answer never holds a count of missed things. Every
 * line passes the reading rule of the member, and nothing of an author whom the member blocked shows. No text is stored.
 *
 * @phpstan-type Target array{post_slug: ?string, post_cuid: ?string, comment_cuid: ?string, chat_message_cuid: ?string, thread_cuid: ?string, resource_cuid: ?string, resource_slug: ?string, page_slug: ?string, event_cuid: ?string, event_slug: ?string}
 * @phpstan-type Line array{kind: 'needs_you'|'announcement'|'thread', title: ?string, excerpt: string, space: array{cuid: string, slug: string, name: string}|null, author: array{cuid: string, name: string, username: string, avatar_url: string|null}|null, date: CarbonInterface, target: Target}
 */
interface BuildsCatchUp
{
    /**
     * @param  array<mixed>  $input  `since`: a date `YYYY-MM-DD`, not in the future, at most 366 days back
     * @return array{lines: list<Line>, has_more: bool}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function build(User $user, Community $community, array $input): array;
}
