# 0009. Real time through Reverb

- Status: Accepted
- Date: 2026-11-09
- Issue: #1180

## Context

Chat, threads, direct messages, presence and live updates of posts need server push. Polling costs requests and feels slow.

## Decision

1. Laravel Reverb (Pusher protocol) is the WebSocket server. Laravel Echo is the client, wrapped in the `useSocket` hook.
2. Private channels carry data events, one per community and per space or conversation. Their authorization in `routes/channels.php` equals the read permission of the data.
3. Presence channels show who is online and carry typing indicators as client events (`client-typing`), never stored.
4. Model events broadcast after the commit (`ShouldDispatchAfterCommit`), with identifiers only: the page reads the item through the API, which checks the access of the reader now ([ADR 0075](0075-live-connections-end-with-the-access.md)). A live update never names the person who made the change.

## Consequences

1. The queue worker and Reverb must run in every environment.
2. Payload size matters: send the identifiers of the changed item, never lists. The maximum size of a message of a client is `REVERB_APP_MAX_MESSAGE_SIZE` (10 000 bytes).
3. The CSP lists the Reverb origin (`connect-src`).

## Alternatives considered

1. Polling: more load, slower.
2. A hosted service (Pusher, Ably): cost per connection and a third party for member data.
