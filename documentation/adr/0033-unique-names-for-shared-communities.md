# 0033. The name of a shared community is unique

- Status: Accepted
- Date: 2026-11-10
- Issue: #1136
- Pull request: #1332

## Context

The create and rename forms of a community need a name check, like the username check (#1136). Until now, two communities could have the same name: the slug library added `-1`, `-2` to the address, and nothing else told them apart. Two public communities named "Knowledge Builders" confuse the members, and a copy of a known community could take its name to mislead people.

Personal communities are different. Every member gets one at sign-up, named after them ("Ada Lovelace's Space"), and nobody else sees it. Two members can have the same name.

The address (slug) of a community never changes after a rename (#508), so an old name keeps pointing to its community.

## Decision

1. The name of a shared community (private or public) is unique among the shared communities. A name is taken when another shared community has the same name, whatever the case, or has the address that this name gives (so "Knowledge-Builders" is taken by "Knowledge Builders", and the old name of a renamed community stays taken).
2. Personal communities never take a name, and nobody checks theirs.
3. `App\Rules\AvailableCommunityName` holds the rule. `CreateCommunity` and `UpdateCommunity` apply it (a 400 validation error on `name`). A community that is renamed keeps its own name and address available to itself.
4. `POST /api/v1/communities/is-name-available` (ability `communities:read`, rate limited) answers `{ "available": bool }` for a name. The answer never tells which community took the name, or whether it is private. To check a new name for a community, its owner sends its cuid in `community`; anyone else gets a 403.
5. A partial unique index on `lower(name)` for the shared communities stops two requests that create the same name at the same time.
6. The forms check the name after each pause in the typing (300 ms) and cancel the older request. While the answer is "taken" or not there yet, they cannot be sent. When the check fails (network, rate limit), they can: the server checks again.

## Consequences

1. Any name gives one community at most. A rename can be refused, also when only the punctuation differs from another community.
2. A name stays taken while a community uses its address, also after the rename of that community.
3. "Taken" tells that a community with this name exists, also a private one. Nothing more: no cuid, no slug, no owner. The rate limit makes a long list of guesses slow.

## Alternatives considered

1. Keep duplicate names and only show the address in the check: the address is not what members see, and the check would never say "taken".
2. Unique names for every community, personal ones included: two members with the same name could not sign up.
3. A `GET` with the name in the query: the name lands in the access logs. `POST` keeps it in the body, like the username check.
