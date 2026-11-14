# 0036. Some community names are reserved for the administrators of the instance

- Status: Accepted
- Date: 2026-11-14
- Issue: #1340
- Pull request: #1358

## Context

The name of a shared community is unique, first come, first served ([ADR 0033](0033-unique-names-for-shared-communities.md)). When sign-ups open, anyone can create "Support", "Official" or a community with the name of the platform or of a product of its operator, before the real one exists. Members would trust it, and the real community could never take the name.

Which names matter depends on the instance: knowii.net protects the DeveloPassion products, a self-hosted instance protects its own brand.

## Decision

1. `config/communities.php` holds a built-in list of reserved names (`reserved_names`): the words that members read as "the platform" or "its team" (Admin, Administrator, Moderator, Staff, Team, Support, Help, Official, Security, System) and the names of the platform (Knowii, Knowii Community, Knowii Platform, Knowii Team, Knowii Support, Knowii Official).
2. An instance adds its own names with `COMMUNITY_RESERVED_NAMES` (separated by commas).
3. `App\Support\ReservedCommunityNames` compares a name like ADR 0033 does: whatever the case, and by the address (slug) that the name gives. "KNOWII", "knowii" and "Knowii-" are reserved by "Knowii". A longer name ("Knowii Fans") is not: the list names exact names, not prefixes.
4. `App\Rules\AvailableCommunityName` treats a reserved name as taken, for the creation, the rename and the name check. The answer is the same as for a name in use, so nobody reads the list through the API.
5. The accounts of `COMMUNITY_RESERVED_NAMES_OWNERS` (email addresses, separated by commas) can use a reserved name. The address must be verified. These are the administrators of the instance; there is no administrator role in the database yet.
6. A community keeps its own name: a name that becomes reserved later does not block the settings of the community that has it.
7. Personal communities are not checked, like in ADR 0033.

## Consequences

1. The operator of an instance creates the real "Support" or brand communities when it wants to, with an account of the list.
2. The list protects exact names. A look-alike ("Knowii Suport", "Kn0wii") still needs moderation: the content policy and the reports cover it.
3. A change of the lists needs a deployment or an environment change, not a migration.
4. `SELF_HOSTING.md` and `.env.example` describe the two values.

## Alternatives considered

1. Create the brand communities before the sign-ups open, with no list: it protects only the names that exist, and not "Support" or "Official".
2. Reserve every name that starts with a reserved word: "Support for gardeners" and "Team sports" are honest names.
3. A table of reserved names with a screen to manage it: more code for a list that changes a few times a year. To build with the administration screens of the instance, if they come.
4. A command that releases the old name of a renamed community (the address of a community never changes, so its old name stays taken): not decided. It needs a choice first: whether the old address then redirects or stops working.
