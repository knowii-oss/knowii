# Personal knowledge layer: draft design (v2)

Status: draft, for the approval of the owner. Not approved, not for build now. Issues: #1525 (the design), #1524 (the epic, in LATER).

This is a draft on paper. No code exists and none starts. The epic stays in LATER.

A first draft was attacked by two reviewers against the code (167 findings). This version takes their main results. The database rules marked "tested" were run in a throwaway transaction on PostgreSQL 18 (the development database, rolled back).

## 1. The model in short

1. The library is the `resources` spaces of the personal community. A resource of the library is "my entry" for a page, a file or a book. No new item table.
2. Everything that is mine about what I read lives only in my personal community: my reading state and my highlights. This holds also for a page that I read in a shared community.
3. When I highlight an article on the page of a shared community, the platform makes (or finds) an entry for the same page in my library, as a link with no copy of the text, and stores the highlight there. The global `resources` row is what says "the same page" in both places.
4. On the community page, my highlights are found through that same `resources` row and placed in the text by their quote.
5. Sharing a highlight is a pointer from my highlight to the community resource. The pointer holds no text.

Why this shape: the first draft stored highlights inside the shared community and moved them when access ended. Both reviews broke it. A forgotten move fails without a sign, it blocked the deletion of a community and of an account, and an entitlement that lapses for one day would damage the notes of a member for good. With the shape above, no act of another person can reach my highlights, and the foreign keys alone give that guarantee.

## 2. Invariants (the database refuses, and a test proves it with a raw insert)

1. One personal community per account: a partial unique index on the owner. Tested.
2. A personal community stays personal and keeps its owner (trigger on update). It cannot be deleted alone, and it goes with its account (trigger on delete). Tested. `DeleteUser` no longer deletes it through `DeleteCommunity` and its policy: today that path would break as soon as the policy refuses.
3. A personal community is sealed: no member row, no invitation, no join block, no access group, no conversation (one trigger per table). Its spaces are `members` only and of an allowed type (`resources` at first). Today nothing refuses an invitation or a public space there, and a public space would send the titles and addresses of saved pages to any signed-in person.
4. A resource belongs to one community and to a space of that community (composite foreign keys). Today `community_id` and `space_id` of a resource are not tied.
5. A highlight and a reading state live in the personal community of their author (trigger). Tested.
6. One reading state per member and resource (unique index). Progress stays between 0 and 1, and the furthest point is never behind the last location (checks).
7. The anchor of a highlight is complete or absent: the quote is required, offsets come in pairs, a locator has a kind, sizes are bounded (checks).
8. A share points to a community resource of the same page as the highlight (composite foreign keys through the `resources` row). Tested. It is deleted with the community resource or with the highlight (cascade). Tested: the deletion of a shared community removes the share and keeps the highlight.
9. A create is safe to repeat: a key from the client, unique per member. The same key with the same content answers with the stored row. The same key with other content is a conflict (409).
10. Every deletion leaves a marker for sync: a trigger writes the identifier and the date into a marker table. A marker holds no content. It also sees deletions by cascade, which model events do not.
11. Everything of a member goes with the account (cascade). Tested.

## 3. Rules that the application enforces (a policy or an action, with a test per path)

1. A shared highlight is shown only to a signed-in member of the community who can view the space, and only while its author can still view the space. So a leave, a removal or the end of an entitlement hides the shares at once, with no cleanup step, and they come back if the access comes back.
2. A visitor of a public space never sees a shared highlight (the captured article is also withheld from visitors, ADR 0044).
3. A shared highlight shows the quote, the author and the date. It never shows the note, the labels or the color. It has its own API resource class.
4. A share needs a quote that is found in the current text of the community resource, and a quote within the shared limit.
5. Shared highlights are not sent on the space channel in the first version (that channel admits outsiders of a public space). Private changes go on the channel of the member only.
6. Tokens: new abilities `library:read`, `library:write` and `library:create`, none in the default set. One middleware refuses every route of a personal community to a token without them. The web session is not limited.
7. Saved addresses: stored as typed (today `cleanupUrl` puts the whole address in lower case, which breaks some of them), no user and password part, no name or slug from the path. The server opens the page only when the address is plain. An address with a query or a token is saved without opening, and the member can ask for the capture.
8. Only a server fetch writes the shared `resources` row. What a client sends never goes there.
9. No log line and no failed job holds an address, a title or a text of the personal layer. Today `downloadImage` and the failure path of the capture job log full addresses.
10. Until private files exist (#1289), a capture in a personal community stores no image. The alternative text stays.
11. A new capture never destroys a highlight: the highlight holds its own quote. It is placed again by the quote. If it is not found, it stays in the list as "not found in the current text".

## 4. Left out of the first version, on purpose

1. Capture versions. One capture per resource stays, as today. The highlight keeps its quote and context, which is what must survive.
2. Private rows in `resources`. The database cannot prove them without heavy machinery, and rules 7 and 8 above remove the real risks.
3. A notification kind for the library, labels on reading states, encryption of notes in the application.

## 5. Decisions for the owner

1. The shape of section 1: highlights and reading state only in the personal community, sharing as a pointer. Recommended.
2. Quote limits: 2,000 characters for a highlight and 300 for a shared one, to check with counsel (#1547). The first draft had 8,000 and 600.
3. A share shows the quote, the author and the date, never the note. Recommended.
4. When the author leaves or is removed, their shares are hidden. Recommended, because it follows from the read-time rule.
5. Visitors never see shared highlights. Recommended.
6. The server opens a saved address only when it is plain. Recommended.
7. The 12-hour login token gets no library ability (today it holds every ability). Recommended.
8. Space types in a personal community: `resources` only for now. More types come with #1550.
9. No capture versions in the first version. Recommended.
10. Sharing ships after the report tool exists (#1394). Recommended.
11. The operator can read the database. Say it in the privacy policy, with no application encryption in the first version. Recommended.
12. Existing development data that breaks invariants 1 to 3 is repaired by the migration, with a report. Recommended.

## 6. Slices, for later

1. S0: the personal community is unique and sealed (invariants 1 to 4, rule 6, the `DeleteUser` change, a default "Library" space). This is #1549, and it is larger than it looks.
2. S1: logs without addresses, the saved address rules, no images in personal captures.
3. S2: highlights (table, checks, marker trigger, policy, API).
4. S3: select and highlight on the reading page, the side list.
5. S4: "My highlights" and the Markdown export.
6. S5: reading state and labels.
7. S6: sharing.

## 7. Accepted decisions that this changes

ADR 0018 (client data never on the shared row, images of personal captures), ADR 0022 (abilities, the login token), ADR 0024 and 0043 (highlights go with the account, shares are pointers), ADR 0033 (the personal community is unique, sealed, and holds the library), ADR 0044 (visitors and shares), and rule 1 stays as it is.

Working files of the maintainer: the first draft, the two reviews and the database test.
