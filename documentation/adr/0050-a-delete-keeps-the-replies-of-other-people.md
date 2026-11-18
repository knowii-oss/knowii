# 0050. A delete by the author keeps the replies of other people

- Status: Accepted
- Date: 2026-11-18
- Issue: #1565 (with #1603)
- Amends: [0043](0043-delete-content-with-the-account.md) (the point that the issue calls 33.2: consequence 2, "An ordinary delete of a post or a comment is unchanged (the row goes, with its comments)")

## Context

Until now the author of a comment deleted it with every reply under it, written by other people, and the author of a post deleted the whole discussion. A deleted chat message (#1185) and the content of a deleted account ([ADR 0043](0043-delete-content-with-the-account.md)) leave a marker instead. ADR 0043 kept the ordinary delete as it was (consequence 2). The UI review (#1523, finding R-M5) found that one member removes the words of others, without their consent and without undo.

The options of the issue:

1. The delete of the author leaves a marker ("This comment was deleted.") and keeps the replies, as chat messages and the account deletion do.
2. The same, and a moderator can still delete the whole thread.
3. Keep the cascade and say it more clearly in the dialog.

The owner chose option 2 on 2026-11-17.

## Decision

1. "Other people" means the same as in `DeleteMemberContent`: a comment or a reply that is not a marker and whose author is not the person who deletes. A comment of a deleted account counts as one of another person.
2. A person deletes a comment that has at least one reply of other people: the comment becomes a marker (no text, no author, `deleted_at` set; its reactions, mentions, upload usages and notifications go). Every reply stays, the own replies of that person too. A comment without replies of other people goes with its replies, as before.
3. A person deletes a post that has at least one comment of other people: the post becomes a marker (no title, no text, no author, the slug `deleted-{cuid}`, not pinned; its reactions, mentions, upload usages, notifications and follows go). Every comment stays. A post without comments of other people goes with its comments, as before.
4. A person who may moderate the space (`SpacePolicy::moderate`) chooses. By default the rules 2 and 3 apply. With `delete_replies` (a comment) or `delete_comments` (a post), the item and everything under it go. These options have their own abilities, `CommentPolicy::deleteWithReplies` and `PostPolicy::deleteWithComments`, true only for a moderator of the space. Anyone else who sends the option gets 403.
5. A marker is never deleted again with an ordinary delete, like a chat message (#1185): `delete` is false for a marker. A moderator deletes it with the option, with everything under it.
6. A marker whose last reply goes stays, like a chat message marker (`DeleteChatMessage` keeps the parent marker and counts its replies again). The account deletion keeps its own rule (ADR 0043, decision 3.2: an orphan marker goes with the account).
7. One code path for a marker: `App\Contracts\Content\LeavesMarkers` (`LeaveMarkers`) finds the items that other people answered and turns a list of ids into markers. `DeleteComment`, `DeletePost` and `DeleteMemberContent` use it, so the two kinds of delete cannot give different markers. It takes lists of ids, for the chunks of the account deletion.
8. The delete runs in one transaction. After it, `SpaceContentChanged` tells the open pages: `space.comment.updated` or `space.post.updated` for a marker (the pages load the item again and show the marker; the post lists drop a marker post), `space.comment.deleted` or `space.post.deleted` when the item goes.
9. The API keeps the two `DELETE` routes, their addresses and their token abilities (`posts:write`; `resources:write` for the comments of a resource). The option is a boolean field of the JSON body, as `delete_content` of `DELETE /v1/user`; a value that is not a boolean is a validation error (400). The answer follows the chat message: 200 with a message when a marker stays, 204 when the item goes.
10. The dialogs say what happens, by case, and a moderator gets a box, not ticked, "Also delete the replies (N)" or "Also delete the comments (N)". After a delete that leaves a marker, the page shows it in place without a reload; the author of a post stays on the post page, at the new address of the marker.
11. Resources, pages, lessons, chat messages and direct messages do not change.

## Consequences

1. Changed by this record: ADR 0043, consequence 2 (an ordinary delete now leaves a marker too) and decision 5 (a moderator deletes a marker with the option). The rest of ADR 0043 stays.
2. A thread keeps the words of everyone who wrote in it, whatever its first author does. An empty marker can stay for good when its replies go; a moderator removes it.
3. A new kind of content with replies follows the same rule and adds its marker to `LeavesMarkers`.
4. The account deletion form says under its box that a post or a comment with replies stays as an empty marker (#1603).

## Alternatives considered

1. Keep the cascade and say it more clearly (option 3 of the issue): the words of other people still go for the decision of one member.
2. A marker for every delete, as in the chat: a comment without replies of others would leave an empty line that helps nobody.
3. Delete the marker when its last reply goes, as the account deletion does: the chat keeps it, and one rule for both kinds of markers is easier to explain. The account deletion keeps its rule because it cleans up after the member.
4. A query parameter for the option (`?deleteReplies=true`): the only other `DELETE` with an option (`DELETE /v1/user`) takes a JSON body, so the API stays with one form.
