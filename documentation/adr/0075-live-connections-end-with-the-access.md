# 0075. Live connections end with the access, and live events carry identifiers only

- Status: Accepted
- Date: 2026-11-24
- Issue: none
- Pull request: none
- Extends: [0009](0009-real-time-through-reverb.md) (point 4), [0027](0027-presence-channels.md), [0059](0059-the-first-proof-of-an-address-takes-the-account-over.md) (the end of the live connections after a takeover)

## Context

The Pusher protocol checks the rule of a channel once, when the connection subscribes to it. After that, the WebSocket server sends every event of the channel to the connection until it closes. The access of an account changes while its pages are open: a member leaves or is removed, loses an access group, a space changes its access, the account signs out or is deleted, the member hides the online status.

Reverb (1.12) ends the connections of an account through its HTTP API (`POST /apps/{id}/users/{user}/terminate_connections`). It finds a connection by the `user_id` of the data of its subscriptions. A presence channel always has that data. A private channel has it when its signature holds it: Reverb checks the data with the signature and keeps it on the subscription. Reverb has no other way to name the account of a connection (no `pusher:signin`).

## Decision

1. Every connection is known by its account. The authorization of every private channel is signed with the account: its answer holds `channel_data = {"user_id": "<cuid>"}`, as a presence channel does (`App\Broadcasting\AccountBoundBroadcaster`, the broadcaster of the driver `reverb`). So Reverb knows the account of every connection that holds a channel, and `terminate_connections` ends each of them. A private channel shows its subscribers to nobody. One connection belongs to one account: the first account that gets a channel on a connection keeps it for 24 hours (`live-socket:{socket id}` in the cache), and a channel for another account on that connection is refused. The sign-out from a page loads the next page in full (`LogoutResponse`), so the next account in the same tab gets a new connection. Every page of a signed-in account also joins the presence form of the channel of the account, `presence-user.{cuid}` (`useAccountConnection`, in `NotificationsProvider`), also when the member hides the online status. Its rule is the rule of `user.{cuid}`: the own cuid only. Its member data is the cuid only. Nobody else joins it, so it tells nobody anything.
2. When the access of an account ends or shrinks, its live connections end, and the page connects again with the present rights: Pusher JS connects again by itself, subscribes to its channels again, and each channel asks its rule again. The pages that follow `useSocketReconnected` load what they show again. `App\Support\LiveConnections` sends the job `App\Jobs\EndLiveConnections` on the queue `broadcasts`, after the commit of the change, never inside the request. The job calls `App\Contracts\Realtime\EndsLiveConnections` for each account (100 accounts a job at most). Ending the connections twice does no harm. A failure is one line of the log with the cuids only, and the job is tried again 30 seconds later, 3 times in all.
3. The changes that end the connections of the account that lost something:
   1. A member is removed, or leaves the community.
   2. A role goes down (an admin reads every space), or a right of the moderators goes off (the moderators of the community).
   3. A member leaves an access group by hand, or an entitlement rule changes the access groups of a member.
   4. The account is deleted, signs out (`Illuminate\Auth\Events\Logout`), changes or resets the password, or ends its other sessions or changes the second factor (`EndsOtherSessions`, ADR 0066). A takeover (ADR 0059) too.
   5. The member hides the online status: the presence channels refuse the new connection, on every page and device of the member.
   6. A block: the connections of both members end. The presence channel of a conversation asks `ConversationPolicy::send`, so the typing signal follows the block and the switch of direct messages of the community.
4. A change of many members at once ends the connections of every member of the community and of its owner: an access group is deleted, an access group loses a space group or a space group loses an access group, a space changes its access (visibility, space group or access groups), the community changes its visibility or turns direct messages off.
5. A live update carries identifiers only: the cuid of the item, of its space and of its community. The resource events send `community_cuid`, `space_cuid` and `resource_cuid`, and the resource list reads the resource through the API. The community events send the cuid of the community only. The channel `communities` is gone: the dashboard lists the communities of the person only. `tests/Feature/LiveUpdatePayloadsTest.php` walks every broadcast class of `app/Events` and fails on a key that is not in its list.
6. A live update does not name the person who made the change. The change of a person (a post, a comment, a page, a message, an event) goes to every page but the one that made it: the API client sends the id of the live connection of the page in `X-Socket-ID`, and `SpaceContentChanged` leaves that connection out. Other pages of the same person get the update.
7. The WebSocket server limits the size and the rate of the messages of a client: `max_message_size` is 10 000 bytes (`REVERB_APP_MAX_MESSAGE_SIZE`), a connection sends at most 30 messages a second and ends at the limit (`rate_limiting` in `config/reverb.php`), and `REVERB_APP_MAX_CONNECTIONS` limits the open connections of the instance. A typing signal is kept for the main conversation and for the threads of the messages that the page shows only, and a thread where nobody types goes.

## Consequences

1. A new change of access calls `LiveConnections::endFor()` (or `endForMembersOf()`) after its write, with a test that fakes `EndsLiveConnections`.
2. A new broadcast event needs a builder in `LiveUpdatePayloadsTest`, and identifiers only.
3. A page that needs its own change reloads it from the answer of its request: the live update does not come back to it.
4. The queue worker reads the queue `broadcasts` first: the connections end a moment after the change.

## Alternatives considered

1. Check the rule again for each event: the Pusher protocol has no such hook, and Reverb would need a call to the application per event and per subscriber.
2. End the connections of the members who lost the read only, for a change of many members: the list costs a read of the rule for each member and each space, for a short reconnection of the others.
3. Leave the actor in the payload and filter it in the page: the cuid of the person who made the change would still reach every subscriber.
4. Know a connection by its account through the presence channel of the account only: the account of a connection would then depend on the channels that the page joins. The signature ties every channel to the account.
