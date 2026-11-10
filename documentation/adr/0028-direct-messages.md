# 0028. Direct messages are chat messages in a conversation

- Status: Accepted
- Date: 2026-11-10
- Issue: #1186
- Pull request: #1300

## Context

Members of a community write to each other in private: one to one, or in small groups of up to 10. A direct message needs what a chat message already has: Markdown rendered on the server (ADR 0013), mentions, images with upload usages (#1201), reactions, edit and delete markers, cursor pagination (ADR 0011), real time (ADR 0009), and typing indicators (ADR 0027). Members must be able to block each other, and the owner must be able to turn direct messages off.

## Decision

1. A conversation (`conversations`) belongs to a community and has 2 to 10 participants (`conversation_participants`, with the last message each one read). A one-to-one conversation has a `direct_key` (the two user ids in order), unique in its community: the same two members always share one conversation. A group is a new conversation each time.
2. A direct message is a row of `chat_messages` with a `conversation_id` instead of a `space_id`. The database allows exactly one of the two (`chat_messages_one_place`), and threads only in chat spaces (`chat_messages_threads_in_spaces`). Reactions, mentions, upload usages, the edit and delete actions and the front-end chat room (`ChatRoom` with a `ChatBackend`) serve both places.
3. Only the participants who are still members of the community read a conversation (`ConversationPolicy::view`). They write while the community allows direct messages and, in a one-to-one conversation, while neither blocked the other (`ConversationPolicy::send`). Nobody moderates direct messages; the author edits and deletes their own. Reports to moderators come with the moderation work (M4).
4. A direct message mentions its participants only: the others cannot read it (`HasMentions::canMention`).
5. A block (`user_blocks`) is between two users, in every community, unlike most data (ADR 0004): a member who blocks someone wants it everywhere. Neither of the two starts or continues a one-to-one conversation with the other, and neither adds the other to a group. The blocked member is not told. A group that already holds both stays open to both.
6. Events carry identifiers only (`ConversationChanged`): on the private channel of the conversation and on the private channel of each participant (`user.{cuid}`), so the lists and the unread counts follow on every page. Typing indicators use the presence channel of the conversation (ADR 0027).
7. Direct messages have their own token abilities, `messages:read` and `messages:write`, never in the default set of a new token: a token for a chat bot does not read private messages.
8. `communities.direct_messages_enabled` (on by default): off, nobody starts a conversation or sends a message, and the members still read theirs.

## Consequences

1. Code that reads `chat_messages` for a space filters on `space_id` (the space relations already do); code that reads a message must handle both places (`ChatMessage::isDirectMessage`).
2. Deleting an account removes its participations; its messages stay with "Deleted member" (ADR 0024).
3. Email notifications for unread direct messages come with the notifications (M4).

## Alternatives considered

1. Separate `direct_messages` tables: the same columns, and reactions, mentions, upload usages, the actions and the UI built a second time.
2. Conversations as hidden chat spaces: spaces have slugs, groups, visibility and navigation that make no sense for private conversations, and the space policies would need exceptions everywhere.
3. Blocks per community: a member would have to block the same person in each shared community.
