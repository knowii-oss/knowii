# 0043. A member can delete their posts, comments and messages with the account

- Status: Accepted
- Date: 2026-11-15
- Issue: #1344
- Pull request: #1491

## Context

[ADR 0024](0024-account-deletion-removes-personal-data.md) keeps what a member wrote when they delete their account: the content stays, under the name "Deleted member". That protects the threads of other members, but a member cannot take their words back, and the text of the content can still name them. Today the only way out is to delete each item by hand before the account, or to write to us.

The owner decided on 2026-11-15 to give the member the choice in the delete dialog, in one step, with these rules.

## Decision

1. The delete dialog has a box, off by default: "Also delete my posts, comments and messages". The dialog says what stays in any case, and that the member can write to the contact address afterwards to have content removed. The privacy policy (section 5, item 1) says the same.
2. The option is `delete_content`, a boolean of the input of `DeleteUser` (validated there). The web route `DELETE /user` keeps its address and name (`current-user.destroy`) and is served by `DeleteAccountController`, because the Jetstream controller cannot pass an option to the action. There is no API route for the deletion of an account, so no Bruno request.
3. With the box ticked, `DeleteMemberContent` deletes in every community, in the same transaction as the account, in chunks of 500 ids:
   1. The posts of the member. A post that has comments of other members stays as a marker: `deleted_at` is set, the title, the text, the author and the pin are cleared, and the slug is replaced by one that names nothing (`deleted-{cuid}`). The comments of the others stay. A post without comments of others is deleted with its own comments.
   2. The comments of the member. A reply is deleted. A top-level comment that has replies of others stays as a marker (`deleted_at`, no text, no author). Any other comment is deleted. A marker whose last reply goes is deleted too.
   3. The chat messages of the member in chat spaces, with the same marker rule as the deleted chat messages (#1185): a message that still has replies in its thread stays as a marker, with no text and no author, and the reply count of the thread is computed again. A message without replies is deleted (a reply in a thread too).
   4. The direct messages of the member ([ADR 0028](0028-direct-messages.md)). They stay as markers (no text, no author) for the other participants, who keep their own messages. Conversations are never deleted.
4. Every item that is deleted or becomes a marker loses its reactions, mentions and upload usages (the database cascades for the rows that go, the action for the markers). The notification rows that point at them go too (`PrunesNotifications::forPosts`, `forComments` and `forChatMessages`, [ADR 0042](0042-notifications.md)), so that no row points at a marker. The uploaded images need nothing: `uploads:prune` deletes the ones that nothing uses.
5. A marker never shows the old content, to anyone, a visitor of a public space included ([ADR 0044](0044-public-spaces-for-visitors.md)): the resources return an empty title, text and author whatever the row holds, with `deleted_at` as the marker flag. The notification list shows no title for a marker. Nobody edits, pins, comments or reacts to a marker (the policies), and a moderator can still delete it.
6. A marker post is not listed (the post list of a space, the API list and the community export skip it). It shows only on its own page, with the comments of the others and the words "This post was deleted". A marker comment shows "This comment was deleted." with its replies.
7. Not covered: the resources that the member added, and the pages and lessons that they wrote. They are shared material of the community and stay under "Deleted member", as in ADR 0024.
8. No event is sent per item. A member with thousands of items would send thousands of broadcasts, and the events carry one item each. The open pages of the other members show the change when they load again. A later change can add one event per touched space.
9. Without the box, nothing changes: ADR 0024 as it is.

## Consequences

1. Changed by this record: ADR 0024, decision 1 and consequence 2. The content stays by default, and goes when the member asks.
2. `posts` and `comments` get a `deleted_at` column, like `chat_messages`. An ordinary delete of a post or a comment is unchanged (the row goes, with its comments).
3. A member cannot take back a deletion: it runs with the account, in one transaction.
4. A marker post keeps its row, its cuid and the comments of the others. A link to it still works.
5. The text of the content of other members can still name the deleted member (a quote, a mention written by hand). The member writes to us for that case, as the dialog says.
6. A later slice that adds a kind of content with an author must say in its record what happens to it with `delete_content`.

## Alternatives considered

1. Always delete the content with the account: complete, but it breaks the threads of other members without a choice.
2. Delete a post with all its comments: removes the words of other people for a decision of one member.
3. Hard-delete a comment or a message that has replies: the replies lose their place. The marker keeps the thread readable and holds no content.
4. A broadcast per item, or one per space: one event carries one item today, so a member with thousands of items would flood the channels. Reloading the page is enough.
5. A route and a request in the API for the deletion of an account: it does not exist today, and it is not part of this issue.
