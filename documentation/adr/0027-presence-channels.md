# 0027. Presence through Reverb presence channels, hidden members never join

- Status: Accepted
- Date: 2026-11-10
- Issue: #1187
- Pull request: #1298

## Context

Members want to see who is online in their community, who is in a chat space, and who is typing. A member can hide their online status: then no other member may learn that they are connected, and they still see the others.

Reverb implements the Pusher protocol: presence channels give the list of members with their join and leave events, and client events ("whispers") carry short signals between the browsers without the server.

## Decision

1. Two presence channels, authorized by classes in `app/Broadcasting`:
   1. `community.{communityCuid}.online` for the members of the community (`CommunityOnlineChannel`).
   2. `community.{communityCuid}.space.{spaceCuid}.presence` for a chat space, with the rule of `SpacePolicy::view`, access groups included (`SpacePresenceChannel`).
2. The channels identify a member by their cuid (`User::getAuthIdentifierForBroadcasting`), never by the internal id (ADR 0005). The member info is the public summary only: cuid, name, username, avatar URL (`Presence::info`).
3. A member who hides their online status (`users.show_online_status`, shown by default) never joins a presence channel: the authorization refuses them. Their pages read the lists through `GET .../online-members` every 30 seconds instead; the server asks Reverb through the Pusher HTTP API (`ReadsPresenceChannels`) and checks the status and the access of each member again. Hidden members send no typing indicator and do not see the others type (they are not in the channel).
4. Typing indicators are client events on the presence channel of the chat space: `typing` with a scope (`''` for the main conversation, the cuid of the first message for a thread) and `typing: true | false`. Reverb accepts client events only from connections in the channel and adds the cuid of the sender (`accept_client_events_from: members` in `config/reverb.php`): a member cannot type as someone else, and a connection outside the channel cannot send events to it. The receivers ignore a sender who is not in the member list, and an indicator expires after 6 seconds without a new signal.
5. In the browser, one store per channel (`usePresence`) shares the subscription between components and leaves the channel 3 seconds after the last component goes, so a page change does not show a leave and a join to the others.

## Consequences

1. Reverb must run for presence; without it, the lists are empty and nothing else breaks.
2. A new presence channel needs a class in `app/Broadcasting`, a constant in `Constants.php`, the same name in `use-presence.ts`, and a check of `showsOnlineStatus()`.
3. Direct messages (#1186) reuse the space mechanism for their typing indicators.
4. The online lists of hidden members are up to 30 seconds old.

## Alternatives considered

1. Hidden members join with a flag and the clients filter them out: every member could still read the hidden member in the WebSocket traffic.
2. Typing signals through the server (an API call per keystroke burst, broadcast as an event): more load and latency for a short-lived signal that needs no storage.
3. Typing signals on the private channel of the space: hidden members would see them, but private channels carry no verified sender, so a member could show another member as typing.
