# 0026. Navigation links are a space type

- Status: Accepted
- Date: 2026-11-10
- Issue: #1294
- Pull request: #1297

## Context

Community owners want links in the navigation of their community: the community website, a shop, a booking page, an external course. Circle has them next to its spaces. Some links are for every visitor, some for members, some only for paying members.

The navigation is a tree of space groups and spaces ([ADR 0012](0012-space-groups-and-typed-spaces.md)). A space already has what a link needs: a name, an emoji, a position in its group, a visibility with access groups ([ADR 0025](0025-access-groups.md)), the reorder and move screens, the API and the live `community.space_tree.changed` event.

## Decision

A navigation link is a space of type `link` with a `url` column.

1. `spaces.url` is required for the `link` type and refused for the other types, in the actions (400) and in the database (`spaces_link_url_check`). Only `http` and `https` URLs are accepted, up to 2048 characters.
2. A link space holds no content: `SpacePolicy::contribute` is false for it, so no post, message, page or resource goes in it. It needs no posting policy; the API stores the default.
3. Everything else is the space model: visibility and access groups decide who sees the link (its URL is only in the answers for the members who can read the space), and owners, admins and moderators with "Manage spaces" manage it in the same dialogs.
4. The navigation opens the URL in a new tab with `rel="noopener noreferrer"` (the site does not learn the address of the community), an "external link" icon and an accessible name that says so. A URL that is not `http` or `https` never becomes a link: the navigation falls back to the space page.
5. The space page of a link (from a bookmark or a shared address) shows where the link goes and a button to open it. It never redirects: Knowii is not an open redirect, and the member sees the destination before leaving.

## Consequences

1. Code that lists spaces must handle the `link` type: there is no content to load, count or search.
2. A link counts in the limit of 200 spaces per community.
3. The space page and the `slug` of a link exist but are rarely used.

## Alternatives considered

1. A separate `navigation_links` table: cleaner rows (no slug, no posting policy), but links mixed with spaces in a group need a second ordering, and the visibility, access groups, reorder screens, API and live updates would be built a second time.
2. Links as a list on the space group: no visibility per link, and no place between two spaces.
